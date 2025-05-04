<?php
// Prevent direct access and ensure WordPress is loaded
if (!defined('ABSPATH')) {
    die('Access denied.');
}

// Ensure we have access to WordPress core functions
if (!function_exists('add_action')) {
    require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php');
}

// Include core functions - ensure we're only loading the main functions file
if (file_exists(dirname(__FILE__) . '/api-controller.php')) {
    require_once dirname(__FILE__) . '/api-controller.php';
}

// Include interactive buttons functionality
if (file_exists(dirname(__FILE__) . '/interactive-buttons.php')) {
    require_once dirname(__FILE__) . '/interactive-buttons.php';
}

if (file_exists(dirname(__FILE__) . '/whatsapp-interactive-buttons.php')) {
    require_once dirname(__FILE__) . '/whatsapp-interactive-buttons.php';
}

if (file_exists(dirname(__FILE__) . '/kwetu-interactive-buttons-setup.php')) {
    require_once dirname(__FILE__) . '/kwetu-interactive-buttons-setup.php';
}

// Include Flutterwave API functions
if (file_exists(dirname(__FILE__) . '/includes/flutterwave-api.php')) {
    require_once dirname(__FILE__) . '/includes/flutterwave-api.php';
}

/**
 * KwetuPizza Core Functions
 * 
 * This file contains all the core functions for the KwetuPizza plugin.
 * Functions are organized by category for better maintainability.
 */

// ========================
// WHATSAPP ORDER FLOW FUNCTIONS
// ========================

/**
 * Main WhatsApp message handler - Enhanced with complete order flow
 */
if (!function_exists('kwetupizza_handle_whatsapp_message')) {
    function kwetupizza_handle_whatsapp_message($from, $message, $interactive_data = null) {
        // Log the context and input for debugging
        kwetupizza_log("Received message from $from: $message", 'info', 'whatsapp.log');
        
        // Get current context (if any)
        $context = kwetupizza_get_conversation_context($from);
        
        // Process interactive data if provided
        if ($interactive_data) {
            $message = kwetupizza_process_interactive_response($interactive_data);
        }
        
        // Check if this is a greeting
        if (kwetupizza_is_greeting($message)) {
            kwetupizza_send_greeting($from);
            return;
        }
        
        // Handle help command
        if (strtolower(trim($message)) === 'help') {
            kwetupizza_send_help_message($from);
            return;
        }
        
        // Handle status check
        if (strtolower(trim($message)) === 'status') {
            kwetupizza_check_order_status($from);
            return;
        }

        // Check if we're waiting for a specific response
        if (isset($context['awaiting'])) {
            return kwetupizza_handle_awaiting_response($from, $message, $context);
        }
        
        // No context or no awaiting state, handle general commands
        $message = strtolower(trim($message));
        
        if ($message === 'menu' || $message === 'order' || $message === 'start') {
            kwetupizza_start_order_flow($from);
            return;
        }
        
        // Default response for unrecognized messages
        kwetupizza_send_default_message($from);
    }
}

/**
 * Start the order flow by showing menu categories
 */
if (!function_exists('kwetupizza_start_order_flow')) {
    function kwetupizza_start_order_flow($from) {
        // Send the menu categories
        kwetupizza_send_menu_categories($from);
        
        // Set context to await category selection
        $context = [
            'state' => 'ordering',
            'step' => 'category_selection',
            'awaiting' => 'category_selection',
            'cart' => []
        ];
        kwetupizza_set_conversation_context($from, $context);
    }
}

/**
 * Process interactive responses (buttons, lists)
 */
if (!function_exists('kwetupizza_process_interactive_response')) {
    function kwetupizza_process_interactive_response($interactive_data) {
        kwetupizza_log("Processing interactive data: " . json_encode($interactive_data), 'info', 'whatsapp.log');
        
        if ($interactive_data['type'] === 'button') {
            $button_id = $interactive_data['button_id'];
            
            // Map button IDs to expected message responses
            $button_map = [
                'btn_add' => 'add',
                'btn_checkout' => 'checkout',
                'btn_yes' => '1',
                'btn_no' => '2',
                'btn_menu' => 'menu',
                'btn_help' => 'help',
                'btn_status' => 'status'
            ];
            
            return $button_map[$button_id] ?? $interactive_data['button_text'];
        } 
        elseif ($interactive_data['type'] === 'list') {
            return $interactive_data['list_title'];
        }
        
        return '';
    }
}

/**
 * Handle responses when we're awaiting specific input
 */
if (!function_exists('kwetupizza_handle_awaiting_response')) {
    function kwetupizza_handle_awaiting_response($from, $message, $context) {
        switch ($context['awaiting']) {
            case 'registration_name':
                return kwetupizza_handle_registration_name($from, $message);
                
            case 'registration_email':
                return kwetupizza_handle_registration_email($from, $message);
                
            case 'registration_location':
                return kwetupizza_handle_registration_location($from, $message);
                
            case 'category_selection':
                return kwetupizza_handle_category_selection($from, $message);
                
            case 'product_selection':
                return kwetupizza_handle_product_selection($from, $message);
                
            case 'quantity':
                return kwetupizza_handle_quantity_input($from, $message);
                
            case 'add_or_checkout':
                return kwetupizza_handle_add_or_checkout($from, $message);
                
            case 'user_name':
                return kwetupizza_handle_user_name_input($from, $message);
                
            case 'user_email':
                return kwetupizza_handle_user_email_input($from, $message);
                
            case 'delivery_zone':
                return kwetupizza_handle_delivery_zone_selection($from, $message);
                
            case 'address':
                return kwetupizza_handle_address_input($from, $message);
                
            case 'payment_provider':
                return kwetupizza_handle_payment_provider_selection($from, $message);
                
            case 'use_whatsapp_number':
                return kwetupizza_handle_use_whatsapp_number_response($from, $message);
                
            case 'payment_phone':
                return kwetupizza_handle_payment_phone_input($from, $message);
                
            default:
                // Reset context and send default message
                kwetupizza_set_conversation_context($from, []);
                kwetupizza_send_whatsapp_message($from, "Sorry, I didn't understand that. Type 'menu' to see available options.");
                return;
        }
    }
}

/**
 * Handle product selection from menu
 */
if (!function_exists('kwetupizza_handle_product_selection')) {
    function kwetupizza_handle_product_selection($from, $product_id) {
        global $wpdb;
        $products_table = $wpdb->prefix . 'kwetupizza_products';
        
        // Try to get product by ID
        $product = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $products_table WHERE id = %d", 
            $product_id
        ));
        
        // If not found by ID, try to find by name
        if (!$product) {
            $product = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $products_table WHERE product_name LIKE %s",
                '%' . $wpdb->esc_like($product_id) . '%'
            ));
        }
        
        if ($product) {
            // Add product to context
            $context = kwetupizza_get_conversation_context($from);
            $context['current_product'] = [
                'id' => $product->id,
                'name' => $product->product_name,
                'price' => $product->price
            ];
            $context['awaiting'] = 'quantity';
            kwetupizza_set_conversation_context($from, $context);
            
            // Ask for quantity
            $message = "You've selected: *{$product->product_name}*\n";
            $message .= "Price: " . kwetupizza_format_currency($product->price) . "\n\n";
            $message .= "Please enter the quantity you'd like to order:";
            
            kwetupizza_send_whatsapp_message($from, $message);
        } else {
            kwetupizza_send_whatsapp_message($from, "Sorry, I couldn't find that product. Please try again.");
            kwetupizza_send_menu_categories($from);
        }
    }
}

/**
 * Handle quantity input for selected product
 */
if (!function_exists('kwetupizza_handle_quantity_input')) {
    function kwetupizza_handle_quantity_input($from, $quantity) {
        $context = kwetupizza_get_conversation_context($from);
        
        if (!is_numeric($quantity) || $quantity < 1) {
            kwetupizza_send_whatsapp_message($from, "Please enter a valid quantity (1 or more).");
            return;
        }
        
        $quantity = (int)$quantity;
        $product = $context['current_product'];
        
        // Add to cart
        $context['cart'][] = [
            'product_id' => $product['id'],
            'product_name' => $product['name'],
            'price' => $product['price'],
            'quantity' => $quantity,
            'total' => $product['price'] * $quantity
        ];
        
        // Calculate new subtotal
        $subtotal = array_sum(array_column($context['cart'], 'total'));
        $context['subtotal'] = $subtotal;
        
        // Ask if user wants to add more or checkout
        $context['awaiting'] = 'add_or_checkout';
        kwetupizza_set_conversation_context($from, $context);
        
        // Send cart summary
        $message = "✅ Added to your order:\n";
        $message .= "{$quantity}x {$product['name']} - " . kwetupizza_format_currency($product['price'] * $quantity) . "\n\n";
        $message .= "📋 *Current Order Total:* " . kwetupizza_format_currency($subtotal) . "\n\n";
        $message .= "Would you like to:\n";
        $message .= "1. Add more items\n";
        $message .= "2. Proceed to checkout";
        
        kwetupizza_send_whatsapp_message($from, $message);
    }
}

/**
 * Handle address input and proceed to payment
 */
if (!function_exists('kwetupizza_handle_address_input')) {
    function kwetupizza_handle_address_input($from, $address) {
        $context = kwetupizza_get_conversation_context($from);
        
        if (empty(trim($address))) {
            kwetupizza_send_whatsapp_message($from, "Please provide a valid delivery address.");
            return;
        }
        
        // Save address to context
        $context['delivery_address'] = trim($address);
        $context['awaiting'] = 'payment_provider';
        kwetupizza_set_conversation_context($from, $context);
        
        // Calculate total with delivery fee
        $delivery_fee = $context['delivery_fee'] ?? 0;
        $total = $context['subtotal'] + $delivery_fee;
        $context['total'] = $total;
        kwetupizza_set_conversation_context($from, $context);
        
        // Send order summary and payment options
        $message = "📋 *Order Summary*\n\n";
        
        foreach ($context['cart'] as $item) {
            $message .= "{$item['quantity']}x {$item['product_name']} - " . kwetupizza_format_currency($item['total']) . "\n";
        }
        
        if ($delivery_fee > 0) {
            $message .= "Delivery Fee: " . kwetupizza_format_currency($delivery_fee) . "\n";
        }
        
        $message .= "Total: " . kwetupizza_format_currency($total) . "\n\n";
        $message .= "Delivery Address: {$context['delivery_address']}\n\n";
        $message .= "Please select your payment method:\n";
        $message .= "1. Vodacom M-Pesa\n";
        $message .= "2. Tigo Pesa\n";
        $message .= "3. Airtel Money\n";
        $message .= "4. Halo Pesa\n";
        $message .= "5. Card Payment (PayPal)";
        
        kwetupizza_send_whatsapp_message($from, $message);
    }
}

/**
 * Handle payment provider selection
 */
if (!function_exists('kwetupizza_handle_payment_provider_selection')) {
    function kwetupizza_handle_payment_provider_selection($from, $selection) {
        $context = kwetupizza_get_conversation_context($from);
        
        $providers = [
            '1' => 'vodacom',
            '2' => 'tigo', 
            '3' => 'airtel',
            '4' => 'halopesa',
            '5' => 'paypal'
        ];
        
        if (!isset($providers[$selection])) {
            kwetupizza_send_whatsapp_message($from, "Please select a valid payment option (1-5).");
            return;
        }
        
        $provider = $providers[$selection];
        $context['payment_provider'] = $provider;
        
        if ($provider === 'paypal') {
            // Handle PayPal payment flow
            kwetupizza_handle_paypal_payment($from, $context);
        } else {
            // Handle mobile money payment
            $context['awaiting'] = 'use_whatsapp_number';
            kwetupizza_set_conversation_context($from, $context);
            
            $message = "Would you like to use your WhatsApp number ($from) for payment?\n\n";
            $message .= "1. Yes\n";
            $message .= "2. No (provide another number)";
            
            kwetupizza_send_whatsapp_message($from, $message);
        }
    }
}

/**
 * Handle the response for using WhatsApp number for payment
 */
if (!function_exists('kwetupizza_handle_use_whatsapp_number_response')) {
    function kwetupizza_handle_use_whatsapp_number_response($from, $response) {
        $context = kwetupizza_get_conversation_context($from);
        
        if ($response === '1' || strtolower($response) === 'yes') {
            // Use WhatsApp number for payment
            kwetupizza_complete_order($from, $from);
        } else {
            // Ask for a different number
            $context['awaiting'] = 'payment_phone';
            kwetupizza_set_conversation_context($from, $context);
            
            kwetupizza_send_whatsapp_message($from, "Please enter the mobile money number to use for payment:");
        }
    }
}

/**
 * Handle payment phone input
 */
if (!function_exists('kwetupizza_handle_payment_phone_input')) {
    function kwetupizza_handle_payment_phone_input($from, $phone) {
        $phone = kwetupizza_sanitize_phone($phone);
        
        if (empty($phone)) {
            kwetupizza_send_whatsapp_message($from, "Please enter a valid phone number for payment.");
            return;
        }
        
        // Complete the order with the provided phone
        kwetupizza_complete_order($from, $phone);
    }
}

/**
 * Complete the order and process payment
 */
if (!function_exists('kwetupizza_complete_order')) {
    function kwetupizza_complete_order($from, $payment_phone) {
        $context = kwetupizza_get_conversation_context($from);
        
        // Save order to database
        $order_id = kwetupizza_save_order_to_db(
            $from,
            $context['cart'],
            $context['delivery_address'],
            $context['total'],
            $context
        );
        
        if (!$order_id) {
            kwetupizza_send_whatsapp_message($from, "Sorry, we couldn't process your order. Please try again.");
            return;
        }
        
        // Process payment based on provider
        if ($context['payment_provider'] === 'paypal') {
            $result = kwetupizza_process_paypal_payment($order_id, $context);
        } else {
            $result = kwetupizza_process_mobile_money_payment($payment_phone, $context['total'], $context['payment_provider'], $order_id, $context['user_name'] ?? '', $context['user_email'] ?? '');
        }
        
        if ($result['success']) {
            // Send order confirmation
            $message = "✅ *Order Confirmed!* 🎉\n\n";
            $message .= "Thank you for your order #$order_id!\n\n";
            $message .= "We've sent a payment request to your mobile money account.\n";
            $message .= "Please complete the payment to process your order.\n\n";
            $message .= "You'll receive updates on your order status.";
            
            kwetupizza_send_whatsapp_message($from, $message);
            
            // Notify admin
            kwetupizza_notify_admin($order_id, false);
            
            // Reset conversation context
            kwetupizza_set_conversation_context($from, []);
        } else {
            // Handle payment failure
            $message = "⚠️ *Payment Request Failed*\n\n";
            $message .= "We couldn't send the payment request for your order #$order_id.\n";
            $message .= "Error: {$result['message']}\n\n";
            $message .= "Please try again or contact support.";
            
            kwetupizza_send_whatsapp_message($from, $message);
        }
    }
}

/**
 * Save order to database - Complete implementation
 */
if (!function_exists('kwetupizza_save_order_to_db')) {
    function kwetupizza_save_order_to_db($phone, $cart_items, $address, $total, $context) {
        global $wpdb;
        
        // Get or create user
        $user = kwetupizza_get_or_create_user($phone);
        
        // Prepare order data
        $order_data = [
            'order_date' => current_time('mysql'),
            'customer_name' => $context['user_name'] ?? $user->name,
            'customer_phone' => $phone,
            'customer_email' => $context['user_email'] ?? $user->email,
            'delivery_address' => $address,
            'delivery_phone' => $phone,
            'status' => 'pending_payment',
            'total' => $total,
            'currency' => 'TZS',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];
        
        // Insert order
        $wpdb->insert($wpdb->prefix . 'kwetupizza_orders', $order_data);
        $order_id = $wpdb->insert_id;
        
        if (!$order_id) {
            return false;
        }
        
        // Insert order items
        foreach ($cart_items as $item) {
            $wpdb->insert($wpdb->prefix . 'kwetupizza_order_items', [
                'order_id' => $order_id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'created_at' => current_time('mysql')
            ]);
        }
        
        // Create transaction record
        $wpdb->insert($wpdb->prefix . 'kwetupizza_transactions', [
            'order_id' => $order_id,
            'transaction_date' => current_time('mysql'),
            'payment_method' => $context['payment_provider'],
            'payment_status' => 'pending',
            'amount' => $total,
            'currency' => 'TZS',
            'payment_provider' => 'Flutterwave',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ]);
        
        // Add timeline event
        kwetupizza_add_order_timeline_event($order_id, 'order_created', 'Order created via WhatsApp');
        
        return $order_id;
    }
}

/**
 * Handle category selection
 */
if (!function_exists('kwetupizza_handle_category_selection')) {
    function kwetupizza_handle_category_selection($from, $category) {
        global $wpdb;
        $categories_table = $wpdb->prefix . 'kwetupizza_categories';
        
        // Get the category ID either directly or by name
        if (is_numeric($category)) {
            $category_id = intval($category);
            $category_record = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $categories_table WHERE id = %d", 
                $category_id
            ));
        } else {
            $category_record = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $categories_table WHERE category_name LIKE %s",
                '%' . $wpdb->esc_like($category) . '%'
            ));
            $category_id = $category_record ? $category_record->id : 0;
        }
        
        if (!$category_id) {
            kwetupizza_send_whatsapp_message($from, "Sorry, I couldn't find that category. Please try again.");
            kwetupizza_send_menu_categories($from);
            return;
        }
        
        // Update context
        $context = kwetupizza_get_conversation_context($from);
        $context['category_id'] = $category_id;
        $context['awaiting'] = 'product_selection';
        kwetupizza_set_conversation_context($from, $context);
        
        // Send products in this category
        kwetupizza_send_category_products($from, $category_id);
    }
}

/**
 * Handle add or checkout decision
 */
if (!function_exists('kwetupizza_handle_add_or_checkout')) {
    function kwetupizza_handle_add_or_checkout($from, $response) {
        $context = kwetupizza_get_conversation_context($from);
        
        if ($response === '1' || strtolower($response) === 'add') {
            // User wants to add more items
            kwetupizza_send_menu_categories($from);
            $context['awaiting'] = 'category_selection';
            kwetupizza_set_conversation_context($from, $context);
        } 
        else if ($response === '2' || strtolower($response) === 'checkout') {
            // User wants to checkout
            $user = kwetupizza_get_user_by_phone($from);
            
            if (!$user || empty($user->name)) {
                // Need user name
                $context['awaiting'] = 'user_name';
                kwetupizza_set_conversation_context($from, $context);
                kwetupizza_send_whatsapp_message($from, "Please enter your name:");
            } 
            else if (!$user || empty($user->email)) {
                // Need user email
                $context['user_name'] = $user->name;
                $context['awaiting'] = 'user_email';
                kwetupizza_set_conversation_context($from, $context);
                kwetupizza_send_whatsapp_message($from, "Please enter your email address:");
            } 
            else {
                // User info is complete, proceed to delivery details
                $context['user_name'] = $user->name;
                $context['user_email'] = $user->email;
                
                // Get delivery zones
                kwetupizza_send_delivery_zones($from);
                $context['awaiting'] = 'delivery_zone';
                kwetupizza_set_conversation_context($from, $context);
            }
        } 
        else {
            // Invalid response
            kwetupizza_send_whatsapp_message($from, "Please select a valid option:\n1. Add more items\n2. Proceed to checkout");
        }
    }
}

/**
 * Handle user name input
 */
if (!function_exists('kwetupizza_handle_user_name_input')) {
    function kwetupizza_handle_user_name_input($from, $name) {
        $context = kwetupizza_get_conversation_context($from);
        
        if (empty(trim($name))) {
            kwetupizza_send_whatsapp_message($from, "Please provide a valid name.");
            return;
        }
        
        $context['user_name'] = trim($name);
        $context['awaiting'] = 'user_email';
        kwetupizza_set_conversation_context($from, $context);
        
        // Update user record
        kwetupizza_update_user_name($from, $name);
        
        kwetupizza_send_whatsapp_message($from, "Thank you, {$name}. Now, please enter your email address:");
    }
}

/**
 * Handle user email input
 */
if (!function_exists('kwetupizza_handle_user_email_input')) {
    function kwetupizza_handle_user_email_input($from, $email) {
        $context = kwetupizza_get_conversation_context($from);
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            kwetupizza_send_whatsapp_message($from, "Please provide a valid email address.");
            return;
        }
        
        $context['user_email'] = $email;
        kwetupizza_set_conversation_context($from, $context);
        
        // Update user record
        kwetupizza_update_user_email($from, $email);
        
        // Get delivery zones
        kwetupizza_send_delivery_zones($from);
        $context['awaiting'] = 'delivery_zone';
        kwetupizza_set_conversation_context($from, $context);
    }
}

/**
 * Handle delivery zone selection
 */
if (!function_exists('kwetupizza_handle_delivery_zone_selection')) {
    function kwetupizza_handle_delivery_zone_selection($from, $zone_id) {
        global $wpdb;
        $zones_table = $wpdb->prefix . 'kwetupizza_delivery_zones';
        
        // Get zone details
        $zone = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $zones_table WHERE id = %d",
            $zone_id
        ));
        
        if (!$zone) {
            // Try to find by name
            $zone = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $zones_table WHERE zone_name LIKE %s",
                '%' . $wpdb->esc_like($zone_id) . '%'
            ));
        }
        
        if (!$zone) {
            kwetupizza_send_whatsapp_message($from, "Sorry, that's not a valid delivery zone. Please select from the list.");
            kwetupizza_send_delivery_zones($from);
            return;
        }
        
        // Update context with zone details
        $context = kwetupizza_get_conversation_context($from);
        $context['delivery_zone'] = $zone->id;
        $context['delivery_fee'] = $zone->delivery_fee;
        $context['awaiting'] = 'address';
        kwetupizza_set_conversation_context($from, $context);
        
        // Ask for specific address
        $message = "You selected: {$zone->zone_name}\n";
        $message .= "Delivery Fee: " . kwetupizza_format_currency($zone->delivery_fee) . "\n\n";
        $message .= "Please enter your specific delivery address:";
        
        kwetupizza_send_whatsapp_message($from, $message);
    }
}

/**
 * Get conversation context for a phone number
 */
if (!function_exists('kwetupizza_get_conversation_context')) {
    function kwetupizza_get_conversation_context($phone) {
        // Sanitize phone number
        $phone = kwetupizza_sanitize_phone($phone);
        
        // Try to get from transient
        $context = get_transient('kwetupizza_context_' . $phone);
        
        return $context ?: [];
    }
}

/**
 * Set conversation context for a phone number
 */
if (!function_exists('kwetupizza_set_conversation_context')) {
    function kwetupizza_set_conversation_context($phone, $context) {
        // Sanitize phone number
        $phone = kwetupizza_sanitize_phone($phone);
        
        // Store in transient for 1 hour
        set_transient('kwetupizza_context_' . $phone, $context, 3600);
    }
}

/**
 * Format currency value
 */
if (!function_exists('kwetupizza_format_currency')) {
    function kwetupizza_format_currency($amount, $currency = 'TZS') {
        return number_format($amount, 0) . ' ' . $currency;
    }
}

/**
 * Sanitize phone number
 */
if (!function_exists('kwetupizza_sanitize_phone')) {
    function kwetupizza_sanitize_phone($phone) {
        // Remove non-numeric characters
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Make sure it starts with country code
        if (strpos($phone, '+') !== 0) {
            // If it starts with 0, replace with Tanzania code
            if (strpos($phone, '0') === 0) {
                $phone = '+255' . substr($phone, 1);
            }
        }
        
        return $phone;
    }
}

/**
 * Check if a message is a greeting
 */
if (!function_exists('kwetupizza_is_greeting')) {
    function kwetupizza_is_greeting($message) {
        $greetings = ['hello', 'hi', 'hey', 'hola', 'salam', 'salaam', 'habari', 'mambo', 'jambo'];
        $message = trim(strtolower($message));
        
        foreach ($greetings as $greeting) {
            if (strpos($message, $greeting) === 0) {
                return true;
            }
        }
        
        return false;
    }
}

/**
 * Send a greeting message
 */
if (!function_exists('kwetupizza_send_greeting')) {
    function kwetupizza_send_greeting($phone) {
        $message = "👋 Hello! Welcome to KwetuPizza! 🍕\n\n";
        $message .= "I'm your virtual pizza assistant. Here's what you can do:\n";
        $message .= "• Type 'menu' to see our delicious menu\n";
        $message .= "• Type 'status' to check your order status\n";
        $message .= "• Type 'help' for more information\n\n";
        $message .= "What would you like to do today?";
        
        kwetupizza_send_whatsapp_message($phone, $message);
    }
}

/**
 * Send a help message
 */
if (!function_exists('kwetupizza_send_help_message')) {
    function kwetupizza_send_help_message($phone) {
        $message = "🍕 *KwetuPizza Help* 🍕\n\n";
        $message .= "Here are the commands you can use:\n\n";
        $message .= "• *menu* - Browse our menu and order\n";
        $message .= "• *status* - Check your order status\n";
        $message .= "• *help* - Show this help message\n\n";
        $message .= "If you have any questions or need assistance, please contact us at:\n";
        $message .= "📞 +255 123 456 789\n";
        $message .= "✉️ support@kwetupizza.com";
        
        kwetupizza_send_whatsapp_message($phone, $message);
    }
}

/**
 * Send default message for unrecognized commands
 */
if (!function_exists('kwetupizza_send_default_message')) {
    function kwetupizza_send_default_message($phone) {
        $message = "I'm sorry, I didn't understand that command. Here are some options:\n\n";
        $message .= "• Type 'menu' to browse our menu and order\n";
        $message .= "• Type 'status' to check your order status\n";
        $message .= "• Type 'help' for more information";
        
        kwetupizza_send_whatsapp_message($phone, $message);
    }
}

/**
 * Send menu categories
 */
if (!function_exists('kwetupizza_send_menu_categories')) {
    function kwetupizza_send_menu_categories($phone) {
        global $wpdb;
        $categories_table = $wpdb->prefix . 'kwetupizza_categories';
        
        // Get active categories
        $categories = $wpdb->get_results("SELECT id, category_name, image_url FROM $categories_table WHERE is_active = 1 ORDER BY display_order ASC");
        
        if (empty($categories)) {
            kwetupizza_send_whatsapp_message($phone, "Sorry, our menu is currently unavailable. Please try again later.");
            return;
        }
        
        $message = "🍕 *Our Menu Categories* 🍕\n\n";
        
        foreach ($categories as $index => $category) {
            $message .= ($index + 1) . ". " . $category->category_name . "\n";
        }
        
        $message .= "\nPlease select a category by typing its number or name:";
        
        kwetupizza_send_whatsapp_message($phone, $message);
    }
}

/**
 * Send category products
 */
if (!function_exists('kwetupizza_send_category_products')) {
    function kwetupizza_send_category_products($phone, $category_id) {
        global $wpdb;
        $products_table = $wpdb->prefix . 'kwetupizza_products';
        
        // Get active products in this category
        $products = $wpdb->get_results($wpdb->prepare(
            "SELECT id, product_name, description, price, image_url FROM $products_table WHERE category_id = %d AND is_active = 1 ORDER BY display_order ASC",
            $category_id
        ));
        
        if (empty($products)) {
            kwetupizza_send_whatsapp_message($phone, "Sorry, there are no products in this category. Please select another category.");
            kwetupizza_send_menu_categories($phone);
            return;
        }
        
        $message = "📋 *Available Products* 📋\n\n";
        
        foreach ($products as $index => $product) {
            $message .= ($index + 1) . ". " . $product->product_name . " - " . kwetupizza_format_currency($product->price) . "\n";
            if (!empty($product->description)) {
                $message .= "   " . $product->description . "\n";
            }
            $message .= "\n";
        }
        
        $message .= "Please select a product by typing its number or name:";
        
        kwetupizza_send_whatsapp_message($phone, $message);
    }
}

/**
 * Send delivery zones
 */
if (!function_exists('kwetupizza_send_delivery_zones')) {
    function kwetupizza_send_delivery_zones($phone) {
        global $wpdb;
        $zones_table = $wpdb->prefix . 'kwetupizza_delivery_zones';
        
        // Get active delivery zones
        $zones = $wpdb->get_results("SELECT id, zone_name, delivery_fee FROM $zones_table WHERE is_active = 1 ORDER BY display_order ASC");
        
        if (empty($zones)) {
            kwetupizza_send_whatsapp_message($phone, "Sorry, delivery service is currently unavailable. Please try again later.");
            return;
        }
        
        $message = "📍 *Delivery Zones* 📍\n\n";
        
        foreach ($zones as $index => $zone) {
            $message .= ($index + 1) . ". " . $zone->zone_name . " - " . kwetupizza_format_currency($zone->delivery_fee) . "\n";
        }
        
        $message .= "\nPlease select your delivery zone by typing its number or name:";
        
        kwetupizza_send_whatsapp_message($phone, $message);
    }
}

/**
 * Get user by phone number
 */
if (!function_exists('kwetupizza_get_user_by_phone')) {
    function kwetupizza_get_user_by_phone($phone) {
        global $wpdb;
        $users_table = $wpdb->prefix . 'kwetupizza_users';
        
        // Sanitize phone
        $phone = kwetupizza_sanitize_phone($phone);
        
        // Look up user
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $users_table WHERE phone = %s",
            $phone
        ));
        
        return $user;
    }
}

/**
 * Get or create user by phone
 */
if (!function_exists('kwetupizza_get_or_create_user')) {
    function kwetupizza_get_or_create_user($phone) {
        global $wpdb;
        $users_table = $wpdb->prefix . 'kwetupizza_users';
        
        // Sanitize phone
        $phone = kwetupizza_sanitize_phone($phone);
        
        // Look up user
        $user = kwetupizza_get_user_by_phone($phone);
        
        // If user doesn't exist, create one
        if (!$user) {
            $wpdb->insert(
                $users_table,
                [
                    'phone' => $phone,
                    'created_at' => current_time('mysql')
                ]
            );
            
            // Get the newly created user
            $user = kwetupizza_get_user_by_phone($phone);
        }
        
        return $user;
    }
}

/**
 * Update user name
 */
if (!function_exists('kwetupizza_update_user_name')) {
    function kwetupizza_update_user_name($phone, $name) {
        global $wpdb;
        $users_table = $wpdb->prefix . 'kwetupizza_users';
        
        // Sanitize phone
        $phone = kwetupizza_sanitize_phone($phone);
        
        // Update name
        $wpdb->update(
            $users_table,
            [
                'name' => $name,
                'updated_at' => current_time('mysql')
            ],
            ['phone' => $phone]
        );
    }
}

/**
 * Update user email
 */
if (!function_exists('kwetupizza_update_user_email')) {
    function kwetupizza_update_user_email($phone, $email) {
        global $wpdb;
        $users_table = $wpdb->prefix . 'kwetupizza_users';
        
        // Sanitize phone
        $phone = kwetupizza_sanitize_phone($phone);
        
        // Update email
        $wpdb->update(
            $users_table,
            [
                'email' => $email,
                'updated_at' => current_time('mysql')
            ],
            ['phone' => $phone]
        );
    }
}

/**
 * Check order status
 */
if (!function_exists('kwetupizza_check_order_status')) {
    function kwetupizza_check_order_status($phone) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'kwetupizza_orders';
        
        // Sanitize phone
        $phone = kwetupizza_sanitize_phone($phone);
        
        // Get latest order
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $orders_table WHERE customer_phone = %s ORDER BY order_date DESC LIMIT 1",
            $phone
        ));
        
        if (!$order) {
            kwetupizza_send_whatsapp_message($phone, "You don't have any recent orders. Type 'menu' to place an order.");
            return;
        }
        
        // Get order items
        $order_items_table = $wpdb->prefix . 'kwetupizza_order_items';
        $products_table = $wpdb->prefix . 'kwetupizza_products';
        
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT oi.*, p.product_name FROM $order_items_table oi 
            LEFT JOIN $products_table p ON oi.product_id = p.id
            WHERE oi.order_id = %d",
            $order->id
        ));
        
        // Get timeline events
        $timeline_table = $wpdb->prefix . 'kwetupizza_order_timeline';
        $events = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $timeline_table WHERE order_id = %d ORDER BY event_time ASC",
            $order->id
        ));
        
        // Format status message
        $message = "📋 *Order #" . $order->id . " Status*\n\n";
        $message .= "Status: " . ucfirst(str_replace('_', ' ', $order->status)) . "\n";
        $message .= "Date: " . date('d M Y, H:i', strtotime($order->order_date)) . "\n\n";
        
        $message .= "*Items:*\n";
        foreach ($items as $item) {
            $message .= "• " . $item->quantity . "x " . $item->product_name . " - " . kwetupizza_format_currency($item->price * $item->quantity) . "\n";
        }
        
        $message .= "\nTotal: " . kwetupizza_format_currency($order->total) . "\n\n";
        
        if (!empty($events)) {
            $message .= "*Order Timeline:*\n";
            foreach ($events as $event) {
                $event_type = ucfirst(str_replace('_', ' ', $event->event_type));
                $message .= "• " . date('d M, H:i', strtotime($event->event_time)) . " - " . $event_type . "\n";
            }
        }
        
        kwetupizza_send_whatsapp_message($phone, $message);
    }
}

/**
 * Add timeline event for an order
 */
if (!function_exists('kwetupizza_add_order_timeline_event')) {
    function kwetupizza_add_order_timeline_event($order_id, $event_type, $description = '') {
        global $wpdb;
        $timeline_table = $wpdb->prefix . 'kwetupizza_order_timeline';
        
        $result = $wpdb->insert(
            $timeline_table,
            [
                'order_id' => $order_id,
                'event_type' => $event_type,
                'description' => $description,
                'event_time' => current_time('mysql'),
                'created_at' => current_time('mysql')
            ]
        );
        
        return $result ? true : false;
    }
}

/**
 * Get callback URL for payment provider
 */
if (!function_exists('kwetupizza_get_callback_url')) {
    function kwetupizza_get_callback_url($provider = 'flutterwave') {
        $url = home_url('wp-json/kwetupizza/v1/payment-callback/' . $provider);
        return $url;
    }
}

/**
 * Notify admin about a new order
 */
if (!function_exists('kwetupizza_notify_admin')) {
    function kwetupizza_notify_admin($order_id, $is_paid = false) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'kwetupizza_orders';
        
        // Get order details
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $orders_table WHERE id = %d",
            $order_id
        ));
        
        if (!$order) {
            return false;
        }
        
        // Get admin phone number
        $admin_phone = get_option('kwetupizza_admin_phone');
        
        if (empty($admin_phone)) {
            return false;
        }
        
        // Format message
        $status = $is_paid ? 'New Paid Order' : 'New Order (Payment Pending)';
        $message = "🔔 *" . $status . "* 🔔\n\n";
        $message .= "Order #: " . $order_id . "\n";
        $message .= "Customer: " . $order->customer_name . "\n";
        $message .= "Phone: " . $order->customer_phone . "\n";
        $message .= "Amount: " . kwetupizza_format_currency($order->total) . "\n";
        $message .= "Address: " . $order->delivery_address . "\n\n";
        $message .= "Please check the admin panel for details.";
        
        // Send notification
        kwetupizza_send_whatsapp_message($admin_phone, $message);
        
        return true;
    }
}

/**
 * Notify customer about order updates
 */
if (!function_exists('kwetupizza_notify_customer')) {
    function kwetupizza_notify_customer($order_id, $status) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'kwetupizza_orders';
        
        // Get order details
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $orders_table WHERE id = %d",
            $order_id
        ));
        
        if (!$order) {
            return false;
        }
        
        $message = "";
        
        switch ($status) {
            case 'payment_confirmed':
                $message = "✅ *Payment Confirmed* ✅\n\n";
                $message .= "Thank you! We've received your payment of " . kwetupizza_format_currency($order->total) . " for order #" . $order_id . ".\n\n";
                $message .= "Your pizza will be prepared and delivered soon.";
                break;
                
            case 'preparing':
                $message = "👨‍🍳 *Order Preparing* 👨‍🍳\n\n";
                $message .= "Good news! Your pizza for order #" . $order_id . " is now being prepared by our chefs.";
                break;
                
            case 'ready_for_delivery':
                $message = "🔔 *Order Ready* 🔔\n\n";
                $message .= "Your pizza for order #" . $order_id . " is now ready and will be dispatched for delivery soon.";
                break;
                
            case 'out_for_delivery':
                $message = "🛵 *Out for Delivery* 🛵\n\n";
                $message .= "Your pizza for order #" . $order_id . " is on the way to " . $order->delivery_address . ".\n\n";
                $message .= "Estimated delivery time: 20-30 minutes.";
                break;
                
            case 'delivered':
                $message = "🎉 *Order Delivered* 🎉\n\n";
                $message .= "Your order #" . $order_id . " has been delivered successfully.\n\n";
                $message .= "Thank you for choosing KwetuPizza. Enjoy your meal!";
                break;
                
            default:
                $message = "🔔 *Order Update* 🔔\n\n";
                $message .= "Your order #" . $order_id . " has been updated to: " . ucfirst(str_replace('_', ' ', $status));
        }
        
        // Send notification
        kwetupizza_send_whatsapp_message($order->customer_phone, $message);
        
        return true;
    }
} 