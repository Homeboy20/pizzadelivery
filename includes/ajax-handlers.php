<?php
/**
 * KwetuPizza AJAX Handlers
 * 
 * Contains functions to handle AJAX requests for the KwetuPizza plugin.
 * This file was created to fix the missing file error.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register AJAX handlers for WhatsApp and SMS
 */
function kwetupizza_register_ajax_handlers() {
    // Admin-side AJAX handlers
    add_action('wp_ajax_kwetupizza_test_whatsapp', 'kwetupizza_test_whatsapp_ajax');
    add_action('wp_ajax_kwetupizza_test_sms', 'kwetupizza_test_sms_ajax');
    add_action('wp_ajax_kwetupizza_init_sample_data', 'kwetupizza_init_sample_data_ajax');
    
    // Frontend AJAX handlers
    add_action('wp_ajax_kwetupizza_process_order', 'kwetupizza_process_order_ajax');
    add_action('wp_ajax_nopriv_kwetupizza_process_order', 'kwetupizza_process_order_ajax');
    add_action('wp_ajax_kwetupizza_update_cart', 'kwetupizza_update_cart_ajax');
    add_action('wp_ajax_nopriv_kwetupizza_update_cart', 'kwetupizza_update_cart_ajax');
    add_action('wp_ajax_kwetupizza_calculate_delivery', 'kwetupizza_calculate_delivery_ajax');
    add_action('wp_ajax_nopriv_kwetupizza_calculate_delivery', 'kwetupizza_calculate_delivery_ajax');
}
add_action('init', 'kwetupizza_register_ajax_handlers');

/**
 * AJAX handler for sending test WhatsApp message
 */
function kwetupizza_test_whatsapp_ajax() {
    // Verify nonce
    check_ajax_referer('kwetupizza_test_whatsapp', 'nonce');
    
    $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : 'This is a test WhatsApp message from KwetuPizza';
    
    if (empty($phone)) {
        wp_send_json_error(['message' => 'Phone number is required']);
        return;
    }
    
    if (function_exists('kwetupizza_send_whatsapp_message')) {
        $result = kwetupizza_send_whatsapp_message($phone, $message);
        
        if ($result) {
            wp_send_json_success(['message' => 'WhatsApp message sent successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to send WhatsApp message. Please check your WhatsApp API settings.']);
        }
    } else {
        wp_send_json_error(['message' => 'WhatsApp sending function not available. Please check plugin configuration.']);
    }
}

/**
 * AJAX handler for sending test SMS
 */
function kwetupizza_test_sms_ajax() {
    // Verify nonce
    check_ajax_referer('kwetupizza_test_sms', 'nonce');
    
    $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : 'This is a test SMS from KwetuPizza';
    
    if (empty($phone)) {
        wp_send_json_error(['message' => 'Phone number is required']);
        return;
    }
    
    if (function_exists('kwetupizza_send_nextsms')) {
        $result = kwetupizza_send_nextsms($phone, $message);
        
        if ($result) {
            wp_send_json_success(['message' => 'SMS sent successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to send SMS. Please check your NextSMS settings.']);
        }
    } else {
        wp_send_json_error(['message' => 'SMS sending function not available. Please check plugin configuration.']);
    }
}

/**
 * AJAX handler for initializing sample data
 */
function kwetupizza_init_sample_data_ajax() {
    // Check nonce for security
    check_ajax_referer('kwetupizza-nonce', 'nonce');
    
    // Check if user has permission
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized access');
        return;
    }
    
    // Initialize sample data
    $success = true;
    $message = 'Sample data initialized successfully.';
    
    // Create sample delivery zones
    if (function_exists('kwetupizza_create_sample_delivery_zones')) {
        kwetupizza_create_sample_delivery_zones();
    } else {
        $success = false;
        $message .= ' Warning: Could not create sample delivery zones.';
    }
    
    // Create sample menu items
    if (function_exists('kwetupizza_create_sample_menu_items')) {
        kwetupizza_create_sample_menu_items();
    } else {
        $success = false;
        $message .= ' Warning: Could not create sample menu items.';
    }
    
    // Respond with success or error message
    if ($success) {
        wp_send_json_success($message);
    } else {
        wp_send_json_error($message);
    }
}

/**
 * AJAX handler for processing orders
 */
function kwetupizza_process_order_ajax() {
    // Verify nonce
    check_ajax_referer('kwetupizza-nonce', 'nonce');
    
    // Get form data
    $cart = isset($_POST['cart']) ? json_decode(stripslashes($_POST['cart']), true) : [];
    $customer_name = isset($_POST['customer_name']) ? sanitize_text_field($_POST['customer_name']) : '';
    $customer_phone = isset($_POST['customer_phone']) ? sanitize_text_field($_POST['customer_phone']) : '';
    $customer_email = isset($_POST['customer_email']) ? sanitize_email($_POST['customer_email']) : '';
    $delivery_address = isset($_POST['delivery_address']) ? sanitize_textarea_field($_POST['delivery_address']) : '';
    $delivery_zone_id = isset($_POST['delivery_zone_id']) ? intval($_POST['delivery_zone_id']) : 0;
    $payment_method = isset($_POST['payment_method']) ? sanitize_text_field($_POST['payment_method']) : '';
    
    // Validate data
    if (empty($cart)) {
        wp_send_json_error(['message' => 'Your cart is empty']);
        return;
    }
    
    if (empty($customer_name) || empty($customer_phone) || empty($delivery_address)) {
        wp_send_json_error(['message' => 'Please fill in all required fields']);
        return;
    }
    
    // Process the order
    if (function_exists('kwetupizza_process_order')) {
        $result = kwetupizza_process_order(
            $customer_name,
            $customer_phone,
            $customer_email,
            $delivery_address,
            $cart,
            $delivery_zone_id,
            $payment_method
        );
        
        if ($result['success']) {
            wp_send_json_success([
                'message' => 'Order placed successfully!',
                'order_id' => $result['order_id'],
                'redirect_url' => $result['redirect_url']
            ]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    } else {
        wp_send_json_error(['message' => 'Order processing function not available. Please try again later.']);
    }
}

/**
 * AJAX handler for updating cart
 */
function kwetupizza_update_cart_ajax() {
    // Verify nonce
    check_ajax_referer('kwetupizza-nonce', 'nonce');
    
    $cart = isset($_POST['cart']) ? json_decode(stripslashes($_POST['cart']), true) : [];
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
    $action = isset($_POST['cart_action']) ? sanitize_text_field($_POST['cart_action']) : '';
    
    if (empty($action) || empty($product_id)) {
        wp_send_json_error(['message' => 'Invalid product information']);
        return;
    }
    
    // Get product details
    global $wpdb;
    $products_table = $wpdb->prefix . 'kwetupizza_products';
    $product = $wpdb->get_row($wpdb->prepare("SELECT * FROM $products_table WHERE id = %d", $product_id));
    
    if (!$product) {
        wp_send_json_error(['message' => 'Product not found']);
        return;
    }
    
    // Handle cart actions
    switch ($action) {
        case 'add':
            $found = false;
            foreach ($cart as &$item) {
                if ($item['product_id'] == $product_id) {
                    $item['quantity'] += $quantity;
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $cart[] = [
                    'product_id' => $product_id,
                    'product_name' => $product->product_name,
                    'price' => $product->price,
                    'quantity' => $quantity
                ];
            }
            break;
            
        case 'update':
            foreach ($cart as &$item) {
                if ($item['product_id'] == $product_id) {
                    $item['quantity'] = $quantity;
                    break;
                }
            }
            break;
            
        case 'remove':
            foreach ($cart as $key => $item) {
                if ($item['product_id'] == $product_id) {
                    unset($cart[$key]);
                    break;
                }
            }
            $cart = array_values($cart); // Reindex array
            break;
    }
    
    // Calculate totals
    $subtotal = 0;
    foreach ($cart as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }
    
    wp_send_json_success([
        'cart' => $cart,
        'subtotal' => $subtotal,
        'item_count' => count($cart)
    ]);
}

/**
 * AJAX handler for calculating delivery cost
 */
function kwetupizza_calculate_delivery_ajax() {
    // Verify nonce
    check_ajax_referer('kwetupizza-nonce', 'nonce');
    
    $zone_id = isset($_POST['zone_id']) ? intval($_POST['zone_id']) : 0;
    
    if (!$zone_id) {
        wp_send_json_error(['message' => 'Please select a delivery zone']);
        return;
    }
    
    // Get delivery fee for the zone
    global $wpdb;
    $zones_table = $wpdb->prefix . 'kwetupizza_delivery_zones';
    $zone = $wpdb->get_row($wpdb->prepare("SELECT * FROM $zones_table WHERE id = %d", $zone_id));
    
    if (!$zone) {
        wp_send_json_error(['message' => 'Invalid delivery zone']);
        return;
    }
    
    wp_send_json_success([
        'delivery_fee' => $zone->delivery_fee,
        'min_delivery_time' => $zone->min_delivery_time,
        'max_delivery_time' => $zone->max_delivery_time
    ]);
} 