<?php
/**
 * KwetuPizza Plugin
 *
 * @package     KwetuPizza
 * @author      KwetuPizza
 * @copyright   2024 KwetuPizza
 * @license     GPL-2.0+
 *
 * @wordpress-plugin
 * Plugin Name: KwetuPizza
 * Plugin URI:  https://kwetupizza.online
 * Description: Handle pizza ordering via WhatsApp with interactive buttons and payment processing.
 * Version:     1.0.0
 * Author:      KwetuPizza
 * Author URI:  https://kwetupizza.online
 * Text Domain: kwetupizza
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    die('Access denied.');
}

// Main plugin file
define('KWETUPIZZA_PLUGIN_FILE', __FILE__);
define('KWETUPIZZA_PLUGIN_DIR', plugin_dir_path(__FILE__));

// Include core files
require_once KWETUPIZZA_PLUGIN_DIR . 'includes/functions.php';
if (file_exists(KWETUPIZZA_PLUGIN_DIR . 'includes/flutterwave-api.php')) {
    require_once KWETUPIZZA_PLUGIN_DIR . 'includes/flutterwave-api.php';
}

/**
 * Initialize the plugin
 */
function kwetupizza_init() {
    // Register activation and deactivation hooks
    register_activation_hook(KWETUPIZZA_PLUGIN_FILE, 'kwetupizza_activate');
    register_deactivation_hook(KWETUPIZZA_PLUGIN_FILE, 'kwetupizza_deactivate');
    
    // Register AJAX handlers
    add_action('init', 'kwetupizza_register_ajax_handlers');
    
    // Register REST API endpoints
    add_action('rest_api_init', 'kwetupizza_register_rest_routes');
}
add_action('plugins_loaded', 'kwetupizza_init');

/**
 * Plugin activation
 */
function kwetupizza_activate() {
    // Create database tables
    kwetupizza_create_tables();
    
    // Create required pages
    kwetupizza_create_pages();
    
    // Set default options
    update_option('kwetupizza_db_version', '1.0.0');
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Plugin deactivation
 */
function kwetupizza_deactivate() {
    // Clean up transients and options
    delete_option('kwetupizza_db_version');
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Register REST API routes
 */
function kwetupizza_register_rest_routes() {
    register_rest_route('kwetupizza/v1', '/whatsapp-webhook', array(
        'methods' => 'POST',
        'callback' => 'kwetupizza_handle_whatsapp_messages',
        'permission_callback' => '__return_true'
    ));
    
    register_rest_route('kwetupizza/v1', '/flutterwave-webhook', array(
        'methods' => 'POST',
        'callback' => 'kwetupizza_flutterwave_webhook',
        'permission_callback' => '__return_true'
    ));
}

/**
 * WhatsApp order flow is implemented in includes/functions.php
 * This provides access to those functions from the main plugin file.
 */

// WhatsApp Bot Integration Functions

/**
 * Main WhatsApp message handler
 */
function kwetupizza_process_whatsapp_message($from, $message, $interactive_data = null) {
    // Call the detailed implementation from functions.php
    if (function_exists('kwetupizza_handle_whatsapp_message')) {
        return kwetupizza_handle_whatsapp_message($from, $message, $interactive_data);
    }
    
    // Fallback if function not found
    error_log("KwetuPizza: Missing WhatsApp handler implementation");
    return false;
}

/**
 * Send WhatsApp message wrapper
 */
function kwetupizza_send_message($phone, $message) {
    if (function_exists('kwetupizza_send_whatsapp_message')) {
        return kwetupizza_send_whatsapp_message($phone, $message);
    }
    
    // Fallback if function not found
    error_log("KwetuPizza: Missing WhatsApp sending implementation");
    return false;
}

/**
 * Process payment wrapper
 */
function kwetupizza_process_payment($order_id, $payment_method, $payment_phone) {
    if (function_exists('kwetupizza_generate_mobile_money_push')) {
        // Get order details
        $order = kwetupizza_get_order($order_id);
        if (!$order) {
            return false;
        }
        
        // Create context for the payment
        $context = array(
            'payment_provider' => $payment_method,
            'delivery_address' => $order->delivery_address,
            'total' => $order->total
        );
        
        // Generate payment request
        return kwetupizza_generate_mobile_money_push(
            $order->customer_phone, 
            kwetupizza_get_order_items($order_id),
            $order->delivery_address,
            $payment_phone
        );
    }
    
    // Fallback if function not found
    error_log("KwetuPizza: Missing payment processing implementation");
    return false;
}

/**
 * Get order details
 */
function kwetupizza_get_order($order_id) {
    global $wpdb;
    $orders_table = $wpdb->prefix . 'kwetupizza_orders';
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $orders_table WHERE id = %d",
        $order_id
    ));
}

/**
 * Get order items
 */
function kwetupizza_get_order_items($order_id) {
    global $wpdb;
    $items_table = $wpdb->prefix . 'kwetupizza_order_items';
    $products_table = $wpdb->prefix . 'kwetupizza_products';
    
    $items = $wpdb->get_results($wpdb->prepare(
        "SELECT oi.*, p.product_name 
        FROM $items_table oi
        JOIN $products_table p ON oi.product_id = p.id
        WHERE oi.order_id = %d",
        $order_id
    ));
    
    $formatted_items = array();
    foreach ($items as $item) {
        $formatted_items[] = array(
            'product_id' => $item->product_id,
            'product_name' => $item->product_name,
            'price' => $item->price,
            'quantity' => $item->quantity,
            'total' => $item->price * $item->quantity
        );
    }
    
    return $formatted_items;
}

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
        $message = '';
        
        if ($interactive_data['type'] === 'button_reply') {
            // Extract the message from button payload
            $message = $interactive_data['button_reply']['title'];
        } elseif ($interactive_data['type'] === 'list_reply') {
            // Extract the message from list selection
            $message = $interactive_data['list_reply']['title'];
        }
        
        return $message;
    }
}

/**
 * Handle specific awaiting responses in the conversation flow
 */
if (!function_exists('kwetupizza_handle_awaiting_response')) {
    function kwetupizza_handle_awaiting_response($from, $message, $context) {
        switch ($context['awaiting']) {
            case 'category_selection':
                return kwetupizza_handle_category_selection($from, $message);
                
            case 'product_selection':
                return kwetupizza_handle_product_selection($from, $message);
                
            case 'quantity':
                return kwetupizza_handle_quantity_input($from, $message);
                
            case 'add_or_checkout':
                return kwetupizza_handle_add_or_checkout($from, $message);
                
            case 'delivery_address':
                return kwetupizza_handle_address_input($from, $message);
                
            case 'payment_method':
                return kwetupizza_handle_payment_method($from, $message);
                
            case 'payment_phone':
                return kwetupizza_handle_payment_phone($from, $message);
                
            default:
                // If we don't recognize the awaiting state, reset context
                kwetupizza_set_conversation_context($from, ['state' => 'greeting']);
                kwetupizza_send_whatsapp_message($from, "I'm not sure what to do next. Let's start over. Type 'menu' to see our options.");
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
            // Ask for quantity
            $message = "You've selected: *{$product->product_name}*\nPrice: " . 
                kwetupizza_format_currency($product->price) . "\n\nHow many would you like to order?";
            kwetupizza_send_whatsapp_message($from, $message);
            
            // Update context with selected product
            $context = kwetupizza_get_conversation_context($from);
            $context['current_product'] = [
                'id' => $product->id,
                'name' => $product->product_name,
                'price' => $product->price
            ];
            $context['awaiting'] = 'quantity';
            kwetupizza_set_conversation_context($from, $context);
        } else {
            kwetupizza_send_whatsapp_message($from, "Sorry, I couldn't find that product. Please try again or type 'menu' to see our options.");
        }
    }
}

/**
 * Handle quantity input
 */
if (!function_exists('kwetupizza_handle_quantity_input')) {
    function kwetupizza_handle_quantity_input($from, $quantity) {
        $context = kwetupizza_get_conversation_context($from);
        
        // Validate quantity
        $quantity = intval($quantity);
        if ($quantity <= 0) {
            kwetupizza_send_whatsapp_message($from, "Please enter a valid quantity (a positive number).");
            return;
        }
        
        // Add item to cart
        if (!isset($context['cart'])) {
            $context['cart'] = [];
        }
        
        $product = $context['current_product'];
        $context['cart'][] = [
            'product_id' => $product['id'],
            'product_name' => $product['name'],
            'price' => $product['price'],
            'quantity' => $quantity,
            'total' => $product['price'] * $quantity
        ];
        
        // Calculate subtotal
        $subtotal = 0;
        foreach ($context['cart'] as $item) {
            $subtotal += $item['total'];
        }
        $context['subtotal'] = $subtotal;
        
        // Ask if user wants to add more or checkout
        $message = "Added to cart: {$quantity}x {$product['name']}\n\n";
        $message .= "Your current total is: " . kwetupizza_format_currency($subtotal) . "\n\n";
        $message .= "Would you like to:\n";
        $message .= "1. Add more items\n";
        $message .= "2. Proceed to checkout";
        
        kwetupizza_send_whatsapp_message($from, $message);
        
        // Update context to await decision
        $context['awaiting'] = 'add_or_checkout';
        kwetupizza_set_conversation_context($from, $context);
    }
}

/**
 * Handle add or checkout response
 */
if (!function_exists('kwetupizza_handle_add_or_checkout')) {
    function kwetupizza_handle_add_or_checkout($from, $response) {
        $context = kwetupizza_get_conversation_context($from);
        $response = strtolower(trim($response));
        
        if ($response === '1' || $response === 'add' || $response === 'add more') {
            // Show menu categories
            kwetupizza_send_menu_categories($from);
        } elseif ($response === '2' || $response === 'checkout' || $response === 'proceed') {
            // Show order summary
            $message = "📋 *Order Summary* 📋\n\n";
            foreach ($context['cart'] as $item) {
                $message .= "{$item['quantity']}x {$item['product_name']} - " . 
                    kwetupizza_format_currency($item['total']) . "\n";
            }
            $message .= "\nSubtotal: " . kwetupizza_format_currency($context['subtotal']) . "\n\n";
            $message .= "Please provide your delivery address:";
            
            kwetupizza_send_whatsapp_message($from, $message);
            
            // Update context to await delivery address
            $context['awaiting'] = 'delivery_address';
            kwetupizza_set_conversation_context($from, $context);
        } else {
            // Invalid response
            kwetupizza_send_whatsapp_message($from, "Please reply with '1' to add more items or '2' to proceed to checkout.");
        }
    }
}

/**
 * Handle delivery address input
 */
if (!function_exists('kwetupizza_handle_address_input')) {
    function kwetupizza_handle_address_input($from, $address) {
        $context = kwetupizza_get_conversation_context($from);
        
        // Save address to context
        $context['delivery_address'] = $address;
        
        // Ask for payment method
        $message = "📍 Delivery Address: $address\n\n";
        $message .= "Please select your payment method:\n";
        $message .= "1. M-Pesa\n";
        $message .= "2. Tigo Pesa\n";
        $message .= "3. Airtel Money\n";
        $message .= "4. Halopesa";
        
        kwetupizza_send_whatsapp_message($from, $message);
        
        // Update context to await payment method
        $context['awaiting'] = 'payment_method';
        kwetupizza_set_conversation_context($from, $context);
    }
}

/**
 * Handle payment method selection
 */
if (!function_exists('kwetupizza_handle_payment_method')) {
    function kwetupizza_handle_payment_method($from, $method) {
        $context = kwetupizza_get_conversation_context($from);
        $method = trim(strtolower($method));
        
        // Map input to payment provider
        $providers = [
            '1' => 'mpesa',
            '2' => 'tigo',
            '3' => 'airtel',
            '4' => 'halopesa',
            'mpesa' => 'mpesa',
            'tigo' => 'tigo',
            'airtel' => 'airtel',
            'halopesa' => 'halopesa'
        ];
        
        if (!isset($providers[$method])) {
            kwetupizza_send_whatsapp_message($from, "Please select a valid payment method (1-4).");
            return;
        }
        
        // Save payment method to context
        $context['payment_provider'] = $providers[$method];
        
        // Ask for payment phone number
        $message = "Would you like to use your WhatsApp number ($from) for the {$providers[$method]} payment?";
        $message .= "\n\n1. Yes\n2. No (I'll provide a different number)";
        
        kwetupizza_send_whatsapp_message($from, $message);
        
        // Update context
        $context['awaiting'] = 'payment_phone';
        kwetupizza_set_conversation_context($from, $context);
    }
}

/**
 * Handle payment phone number input
 */
if (!function_exists('kwetupizza_handle_payment_phone')) {
    function kwetupizza_handle_payment_phone($from, $response) {
        $context = kwetupizza_get_conversation_context($from);
        $response = trim(strtolower($response));
        
        $payment_phone = '';
        
        if ($response === '1' || $response === 'yes') {
            // Use WhatsApp number
            $payment_phone = $from;
        } elseif ($response === '2' || $response === 'no') {
            // Ask for different number
            kwetupizza_send_whatsapp_message($from, "Please provide the phone number you'd like to use for payment:");
            
            // Change awaiting state
            $context['awaiting'] = 'payment_phone_input';
            kwetupizza_set_conversation_context($from, $context);
            return;
        } elseif ($context['awaiting'] === 'payment_phone_input') {
            // This is a direct phone number input
            $payment_phone = kwetupizza_sanitize_phone($response);
        } else {
            kwetupizza_send_whatsapp_message($from, "Please reply with '1' for Yes or '2' for No.");
            return;
        }
        
        // Process with the payment phone
        if (!empty($payment_phone)) {
            // Complete the order
            kwetupizza_complete_order($from, $payment_phone);
        }
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
            $context['subtotal'],
            $context
        );
        
        if (!$order_id) {
            kwetupizza_send_whatsapp_message($from, "Sorry, we couldn't process your order. Please try again.");
            return;
        }
        
        // Process payment based on provider
        if (isset($context['payment_provider'])) {
            // Create a mobile money payment request
            $result = kwetupizza_generate_mobile_money_push(
                $from,
                $context['cart'],
                $context['delivery_address'],
                $payment_phone
            );
            
            if ($result) {
                // Payment request sent successfully
                // The confirmation message is sent in the payment processing function
                
                // Clear conversation context after successful order
                kwetupizza_set_conversation_context($from, []);
                
                return true;
            } else {
                // Payment request failed
                kwetupizza_send_whatsapp_message(
                    $from, 
                    "There was a problem processing your payment. Please try again later or contact our support."
                );
                return false;
            }
        } else {
            // No payment provider selected - should not happen
            kwetupizza_send_whatsapp_message($from, "No payment method selected. Please try ordering again.");
            return false;
        }
    }
}

/**
 * Send greeting to new user
 */
if (!function_exists('kwetupizza_send_greeting')) {
    function kwetupizza_send_greeting($from) {
        $message = "👋 *Hello! Welcome to KwetuPizza* 🍕\n\n";
        $message .= "How can I help you today?\n\n";
        $message .= "📱 *Available Commands:*\n";
        $message .= "• Type *menu* to browse our delicious menu by category\n";
        $message .= "• Type *order* to start a new order\n";
        $message .= "• Type *status* to check your recent order\n";
        $message .= "• Type *help* for assistance\n\n";
        $message .= "Our new interactive ordering system makes it easy to order your favorite pizza with just a few messages! Try it now by typing 'menu' 😊";
        
        kwetupizza_send_whatsapp_message($from, $message);
        
        // Initialize empty context with state set to 'greeting'
        kwetupizza_set_conversation_context($from, ['state' => 'greeting']);
    }
}

/**
 * Send menu categories to customer
 */
if (!function_exists('kwetupizza_send_menu_categories')) {
    function kwetupizza_send_menu_categories($from) {
        // Check if we have context with user name
        $context = kwetupizza_get_conversation_context($from);
        $first_name = isset($context['first_name']) ? $context['first_name'] : '';
        
        $message = "🍽️ *Our Menu Categories* 🍽️\n\n";
        
        if (!empty($first_name)) {
            $message .= "{$first_name}, please select a category by typing the number:\n\n";
        } else {
            $message .= "Please select a category by typing the number:\n\n";
        }
        
        $message .= "1. 🍕 Pizzas\n";
        $message .= "2. 🥤 Drinks\n";
        $message .= "3. 🍰 Desserts\n";
        $message .= "4. 🎁 Special Offers\n";
        
        kwetupizza_send_whatsapp_message($from, $message);
        
        // Set context to await category selection while preserving user info
        $updated_context = array_merge($context, ['awaiting' => 'category_selection']);
        kwetupizza_set_conversation_context($from, $updated_context);
    }
}

/**
 * Handle menu category selection
 */
if (!function_exists('kwetupizza_handle_category_selection')) {
    function kwetupizza_handle_category_selection($from, $selection) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kwetupizza_products';
        
        // Get current context to retrieve user name if available
        $context = kwetupizza_get_conversation_context($from);
        $first_name = isset($context['first_name']) ? $context['first_name'] : '';
        
        // Map selection to category
        $categories = [
            '1' => 'Pizza',
            '2' => 'Drinks',
            '3' => 'Dessert',
            '4' => 'Special'
        ];
        
        if (!isset($categories[$selection])) {
            $message = empty($first_name) ? 
                "Please select a valid category (1-4)." : 
                "$first_name, please select a valid category (1-4).";
            kwetupizza_send_whatsapp_message($from, $message);
            return;
        }
        
        $category = $categories[$selection];
        
        // Get products in the selected category
        $products = $wpdb->get_results($wpdb->prepare(
            "SELECT id, product_name, description, price FROM $table_name WHERE category = %s",
            $category
        ));
        
        if (empty($products)) {
            $message = empty($first_name) ?
                "Sorry, no products found in this category. Please select another category." :
                "Sorry $first_name, no products found in this category. Please select another category.";
            kwetupizza_send_whatsapp_message($from, $message);
            kwetupizza_send_menu_categories($from);
            return;
        }
        
        // Format the category menu with emojis
        $emoji = ['Pizza' => '🍕', 'Drinks' => '🥤', 'Dessert' => '🍰', 'Special' => '🎁'][$category];
        $message = "$emoji *{$category} Menu* $emoji\n\n";
        
        // Add personalized greeting if first name is available
        if (!empty($first_name)) {
            $message .= "Here you go, $first_name! ";
        }
        
        $message .= "Please type the number of the item you'd like to order:\n\n";
        
        foreach ($products as $index => $product) {
            $message .= "{$product->id}. *{$product->product_name}*\n";
            $message .= "   {$product->description}\n";
            $message .= "   Price: " . number_format($product->price, 2) . " TZS\n\n";
        }
        
        kwetupizza_send_whatsapp_message($from, $message);
        
        // Set context to await product selection while preserving user info
        $updated_context = array_merge($context, ['awaiting' => 'product_selection']);
        kwetupizza_set_conversation_context($from, $updated_context);
    }
}

/**
 * Utility: Check if a message is a greeting
 */
if (!function_exists('kwetupizza_is_greeting')) {
    function kwetupizza_is_greeting($message) {
        $message = strtolower(trim($message));
        $greetings = array(
            'hello', 'hi', 'hey', 'howdy', 'greetings', 'good morning', 'good afternoon', 
            'good evening', 'hola', 'bonjour', 'jambo', 'habari', 'mambo', 'sasa', 'niaje'
        );
        
        foreach ($greetings as $greeting) {
            if (strpos($message, $greeting) !== false) {
                return true;
            }
        }
        
        return false;
    }
}

/**
 * Utility: Send default message when input is not understood
 */
if (!function_exists('kwetupizza_send_default_message')) {
    function kwetupizza_send_default_message($from) {
        kwetupizza_send_whatsapp_message($from, "Sorry, I didn't understand that. Type 'menu' to see available options.");
    }
}

/**
 * Utility: Send help information to the customer
 */
if (!function_exists('kwetupizza_send_help_message')) {
    function kwetupizza_send_help_message($from) {
        $message = "📱 *KwetuPizza Help Guide*\n\n";
        
        $message .= "Here's how to use our WhatsApp service:\n\n";
        
        $message .= "*Available Commands:*\n";
        $message .= "• *menu* - View our menu with prices\n";
        $message .= "• *order* - Start a new order\n";
        $message .= "• *status* - Check your recent order status\n";
        $message .= "• *help* - Show this help message\n\n";
        
        $message .= "*Ordering Process:*\n";
        $message .= "1. Type *menu* to see available items\n";
        $message .= "2. Choose items by entering their number\n";
        $message .= "3. Specify quantity when prompted\n";
        $message .= "4. Provide your delivery address\n";
        $message .= "5. Select payment method (mobile money)\n";
        $message .= "6. Confirm payment on your mobile device\n\n";
        
        $message .= "*Payment Issues:*\n";
        $message .= "If you encounter payment problems, please contact our customer support at " . get_option('kwetupizza_support_phone', '+255xxxxxxxxx') . "\n\n";
        
        $message .= "*Business Hours:*\n";
        $message .= "We're open from 10:00 AM to 10:00 PM, every day\n\n";
        
        $message .= "Thank you for choosing KwetuPizza! 🍕";
        
        kwetupizza_send_whatsapp_message($from, $message);
    }
}

/**
 * Check and report the status of a customer's most recent order
 */
if (!function_exists('kwetupizza_check_order_status')) {
    function kwetupizza_check_order_status($from) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'kwetupizza_orders';
        $timeline_table = $wpdb->prefix . 'kwetupizza_order_timeline';
        
        // Find the customer's most recent order
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $orders_table 
            WHERE customer_phone = %s 
            ORDER BY created_at DESC 
            LIMIT 1",
            $from
        ));
        
        if (!$order) {
            kwetupizza_send_whatsapp_message($from, "You don't have any recent orders. Would you like to place an order now? Type 'menu' to see our options.");
            return;
        }
        
        // Get the timeline events for this order
        $timeline_events = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $timeline_table 
            WHERE order_id = %d 
            ORDER BY created_at ASC",
            $order->id
        ));
        
        // Create status message
        $message = "🍕 *Order #{$order->id} Status*\n\n";
        
        // Add order details
        $message .= "📋 *Order Details:*\n";
        $message .= "• Date: " . date('Y-m-d H:i', strtotime($order->created_at)) . "\n";
        $message .= "• Total: " . kwetupizza_format_currency($order->total, $order->currency) . "\n";
        $message .= "• Status: " . ucfirst($order->status) . "\n";
        $message .= "• Delivery Address: {$order->delivery_address}\n\n";
        
        // Add timeline events
        $message .= "⏱️ *Order Timeline:*\n";
        
        if ($timeline_events) {
            foreach ($timeline_events as $event) {
                $icon = kwetupizza_get_timeline_icon($event->event_type);
                $time = date('H:i', strtotime($event->created_at));
                $message .= "$icon $time - {$event->description}\n";
            }
        } else {
            $message .= "• Order received and being processed\n";
        }
        
        // Add a note about contacting for issues
        $message .= "\n\nThank you for your patience! If you have any questions, please reply with 'help'.";
        
        // Send the status message
        kwetupizza_send_whatsapp_message($from, $message);
    }
}

/**
 * Get an appropriate icon for a timeline event
 */
if (!function_exists('kwetupizza_get_timeline_icon')) {
    function kwetupizza_get_timeline_icon($event_type) {
        switch ($event_type) {
            case 'order_placed':
                return '📝';
            case 'payment_confirmed':
                return '💰';
            case 'order_confirmed':
                return '✅';
            case 'preparing':
                return '👨‍🍳';
            case 'out_for_delivery':
                return '🛵';
            case 'delivered':
                return '🎉';
            case 'cancelled':
                return '❌';
            default:
                return '•';
        }
    }
} 