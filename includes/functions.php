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

/**
 * KwetuPizza Core Functions
 * 
 * This file contains all the core functions for the KwetuPizza plugin.
 * Functions are organized by category for better maintainability.
 */

// ========================
// DATABASE FUNCTIONS
// ========================

/**
 * Create custom database tables
 */
if (!function_exists('kwetupizza_create_tables')) {
function kwetupizza_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    // Check if we need to upgrade the tables
    $current_db_version = get_option('kwetupizza_db_version', '0');
    
    // Include WordPress upgrade functions
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    // Users Table
    $users_table = $wpdb->prefix . 'kwetupizza_users';
    $sql = "CREATE TABLE $users_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        name varchar(100) NOT NULL,
        email varchar(100) DEFAULT '',
        phone varchar(20) NOT NULL,
        dob varchar(20) DEFAULT '',
        location varchar(255) DEFAULT '',
        role varchar(20) DEFAULT 'customer',
        state varchar(50) DEFAULT 'new',
        total_orders int(11) DEFAULT 0,
        total_spent decimal(10,2) DEFAULT 0.00,
        created_at datetime DEFAULT NULL,
        updated_at datetime DEFAULT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql);

    // Products Table
    $products_table = $wpdb->prefix . 'kwetupizza_products';
    $sql = "CREATE TABLE IF NOT EXISTS $products_table (
        id mediumint(9) UNSIGNED NOT NULL AUTO_INCREMENT,
        product_name varchar(255) NOT NULL,
        description text NOT NULL,
        price float NOT NULL,
        currency varchar(10) NOT NULL,
        category varchar(50) NOT NULL,
        image_url varchar(255) DEFAULT '',
        stock_status varchar(50) DEFAULT 'in_stock',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql);

    // Orders Table
    $orders_table = $wpdb->prefix . 'kwetupizza_orders';
    $sql = "CREATE TABLE IF NOT EXISTS $orders_table (
        id mediumint(9) UNSIGNED NOT NULL AUTO_INCREMENT,
        order_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        customer_name varchar(100) NOT NULL,
        customer_phone varchar(20) NOT NULL,
        customer_email varchar(100) DEFAULT '',
        delivery_address text NOT NULL,
        delivery_phone varchar(20) NOT NULL,
        status varchar(50) NOT NULL,
        total float NOT NULL,
        currency varchar(10) NOT NULL,
        delivery_notes text DEFAULT '',
        estimated_delivery_time datetime,
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql);

    // Order Items Table
    $order_items_table = $wpdb->prefix . 'kwetupizza_order_items';
    $sql = "CREATE TABLE IF NOT EXISTS $order_items_table (
        id mediumint(9) UNSIGNED NOT NULL AUTO_INCREMENT,
        order_id mediumint(9) UNSIGNED NOT NULL,
        product_id mediumint(9) UNSIGNED NOT NULL,
        quantity int NOT NULL,
        price float NOT NULL,
        special_instructions text DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        FOREIGN KEY (order_id) REFERENCES {$orders_table}(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES {$products_table}(id) ON DELETE CASCADE
    ) $charset_collate;";
    dbDelta($sql);

    // Transactions Table
    $transactions_table = $wpdb->prefix . 'kwetupizza_transactions';
    $sql = "CREATE TABLE IF NOT EXISTS $transactions_table (
        id mediumint(9) UNSIGNED NOT NULL AUTO_INCREMENT,
        order_id mediumint(9) UNSIGNED NOT NULL,
        transaction_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        payment_method varchar(50) NOT NULL,
        payment_status varchar(50) NOT NULL,
        amount float NOT NULL,
        currency varchar(10) NOT NULL,
        payment_provider varchar(50) NOT NULL,
        transaction_reference varchar(100) DEFAULT '',
        tx_ref varchar(100) DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY (tx_ref),
        FOREIGN KEY (order_id) REFERENCES {$wpdb->prefix}kwetupizza_orders(id) ON DELETE CASCADE
    ) $charset_collate;";
    dbDelta($sql);

    // Delivery Zones Table (New)
    $delivery_zones_table = $wpdb->prefix . 'kwetupizza_delivery_zones';
    $sql = "CREATE TABLE IF NOT EXISTS $delivery_zones_table (
        id mediumint(9) UNSIGNED NOT NULL AUTO_INCREMENT,
        zone_name varchar(100) NOT NULL,
        description text DEFAULT '',
        coordinates text NOT NULL,
        delivery_fee float NOT NULL,
        min_delivery_time int DEFAULT 30,
        max_delivery_time int DEFAULT 60,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql);

    // Customer Loyalty Table (New)
    $customer_loyalty_table = $wpdb->prefix . 'kwetupizza_customer_loyalty';
    $sql = "CREATE TABLE IF NOT EXISTS $customer_loyalty_table (
        id mediumint(9) UNSIGNED NOT NULL AUTO_INCREMENT,
        customer_phone varchar(20) NOT NULL,
        points int DEFAULT 0,
        total_orders int DEFAULT 0,
        total_spent float DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY (customer_phone)
    ) $charset_collate;";
    dbDelta($sql);
    }
}

// ========================
// ACTIVATION FUNCTIONS
// ========================

/**
 * Create required pages for the plugin
 */
if (!function_exists('kwetupizza_create_pages')) {
function kwetupizza_create_pages() {
    // Create Thank You page
        $thank_you_page = get_page_by_path('thank-you');
        if (empty($thank_you_page)) {
        wp_insert_post(array(
            'post_title' => 'Thank You',
            'post_content' => 'Thank you for your order! Your payment was successful.',
            'post_name' => 'thank-you',
            'post_status' => 'publish',
            'post_type' => 'page'
        ));
    }

    // Create Retry Payment page
        $retry_payment_page = get_page_by_path('retry-payment');
        if (empty($retry_payment_page)) {
        wp_insert_post(array(
            'post_title' => 'Retry Payment',
            'post_content' => 'It seems your payment has failed. Please retry the payment by clicking the link below.',
            'post_name' => 'retry-payment',
            'post_status' => 'publish',
            'post_type' => 'page'
        ));
    }

    // Create Order Tracking page (New)
        $order_tracking_page = get_page_by_path('order-tracking');
        if (empty($order_tracking_page)) {
        wp_insert_post(array(
            'post_title' => 'Order Tracking',
            'post_content' => '[kwetupizza_order_tracking]',
            'post_name' => 'order-tracking',
            'post_status' => 'publish',
            'post_type' => 'page'
        ));
    }

    // Create Menu page (New)
        $menu_page = get_page_by_path('pizza-menu');
        if (empty($menu_page)) {
        wp_insert_post(array(
            'post_title' => 'Our Menu',
            'post_content' => '[kwetupizza_menu]',
            'post_name' => 'pizza-menu',
            'post_status' => 'publish',
            'post_type' => 'page'
        ));
    }

    // Create Customer Account page (New)
        $account_page = get_page_by_path('customer-account');
        if (empty($account_page)) {
        wp_insert_post(array(
            'post_title' => 'My Account',
            'post_content' => '[kwetupizza_customer_account]',
            'post_name' => 'customer-account',
            'post_status' => 'publish',
            'post_type' => 'page'
        ));
    }
    
    // Create PayPal Checkout page
        $paypal_checkout_page = get_page_by_path('paypal-checkout');
        if (empty($paypal_checkout_page)) {
        wp_insert_post(array(
            'post_title' => 'PayPal Checkout',
            'post_content' => '[kwetupizza_paypal_checkout]',
            'post_name' => 'paypal-checkout',
            'post_status' => 'publish',
            'post_type' => 'page',
            'page_template' => 'paypal-checkout.php'
        ));
    }
}
}

// ========================
// WEBHOOK & API FUNCTIONS
// ========================

/**
 * Generate callback URLs for webhooks
 */
function kwetupizza_get_callback_url($service) {
    return esc_url(home_url('/wp-json/kwetupizza/v1/' . $service . '-webhook'));
}

/**
 * Verify a webhook signature from Flutterwave
 */
function kwetupizza_verify_flutterwave_signature($request) {
    $secret_hash = get_option('kwetupizza_flw_webhook_secret');
    $signature = $request->get_header('verif-hash');
    
    if (!$signature || $signature !== $secret_hash) {
        return false;
    }
    
    return true;
}

// ========================
// UTILITY FUNCTIONS
// ========================

/**
 * Sanitize and validate phone number
 */
function kwetupizza_sanitize_phone($phone) {
    // Remove any non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Ensure the number starts with country code
    if (substr($phone, 0, 3) !== '255') {
        // If number starts with 0, replace it with 255
        if (substr($phone, 0, 1) === '0') {
        $phone = '255' . substr($phone, 1);
        } else {
            // Otherwise assume it's a local number and add country code
            $phone = '255' . $phone;
        }
    }
    
    return $phone;
}

/**
 * Format currency values
 */
function kwetupizza_format_currency($amount, $currency = '') {
    if (empty($currency)) {
        $currency = get_option('kwetupizza_currency', 'TZS');
    }
    
    return number_format($amount, 2) . ' ' . $currency;
}

/**
 * Generate secure random string
 */
function kwetupizza_generate_random_string($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[wp_rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

/**
 * Log messages to a file
 */
function kwetupizza_log($message, $type = 'info', $file = 'kwetupizza-debug.log') {
    $log_file = plugin_dir_path(dirname(__FILE__)) . 'includes/' . $file;
    $timestamp = date('Y-m-d H:i:s');
    $formatted_message = "[$timestamp][$type] $message" . PHP_EOL;
    file_put_contents($log_file, $formatted_message, FILE_APPEND);
}

/**
 * Add security headers
 */
if (!function_exists('kwetupizza_add_security_headers')) {
    function kwetupizza_add_security_headers() {
        // Content Security Policy
        header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; style-src 'self' https://fonts.googleapis.com 'unsafe-inline'; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self' https://api.flutterwave.com https://graph.facebook.com;");
        
        // Prevent MIME type sniffing
        header("X-Content-Type-Options: nosniff");
        
        // Enable XSS protection
        header("X-XSS-Protection: 1; mode=block");
        
        // Prevent clickjacking
        header("X-Frame-Options: SAMEORIGIN");
        
        // Referrer Policy
        header("Referrer-Policy: strict-origin-when-cross-origin");
    }
}
add_action('send_headers', 'kwetupizza_add_security_headers');

// ========================
// NOTIFICATION FUNCTIONS
// ========================

/**
 * Send WhatsApp message
 */
function kwetupizza_send_whatsapp_message($phone, $message) {
    // Log the attempt for debugging
    kwetupizza_log("Attempting to send WhatsApp message to $phone", 'info', 'whatsapp.log');
    
    $token = get_option('kwetupizza_whatsapp_token');
    $phone_id = get_option('kwetupizza_whatsapp_phone_id');
    
    // Log configuration for troubleshooting
    kwetupizza_log("WhatsApp configuration - Phone ID: $phone_id, Token exists: " . (!empty($token) ? 'Yes' : 'No'), 'info', 'whatsapp.log');
    
    if (empty($token) || empty($phone_id)) {
        kwetupizza_log('WhatsApp API credentials not set or incomplete', 'error', 'whatsapp.log');
        return false;
    }
    
    // Sanitize phone number with enhanced validation
    $phone = kwetupizza_sanitize_phone($phone);
    
    // Log the sanitized phone number
    kwetupizza_log("Sanitized phone number: $phone", 'info', 'whatsapp.log');
    
    // Ensure the phone number starts with country code and has no leading '+'
    if (substr($phone, 0, 1) === '+') {
        $phone = substr($phone, 1);
        kwetupizza_log("Removed leading + from phone number: $phone", 'info', 'whatsapp.log');
    }
    
    // WhatsApp Cloud API endpoint
    $url = "https://graph.facebook.com/v17.0/{$phone_id}/messages";
    kwetupizza_log("Using WhatsApp endpoint: $url", 'info', 'whatsapp.log');

    // Setup the request payload
    $data = array(
        'messaging_product' => 'whatsapp',
        'recipient_type' => 'individual',
        'to' => $phone,
        'type' => 'text',
        'text' => array(
            'preview_url' => false,
            'body' => $message
        )
    );
    
    // Log the payload for debugging
    kwetupizza_log("WhatsApp payload: " . json_encode($data), 'info', 'whatsapp.log');
    
    // Send the request
    $response = wp_remote_post($url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode($data),
        'timeout' => 30
    ));

    // Check for errors
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        kwetupizza_log("WhatsApp API Error: $error_message", 'error', 'whatsapp.log');
        error_log("WhatsApp API Error: $error_message");
        return false;
    }
    
    $status_code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    // Log the response for debugging
    kwetupizza_log("WhatsApp API Response Code: $status_code", 'info', 'whatsapp.log');
    kwetupizza_log("WhatsApp API Response: " . print_r($body, true), 'info', 'whatsapp.log');
    
    // Check for successful response
    if (isset($body['messages']) && !empty($body['messages'])) {
        kwetupizza_log("WhatsApp message sent successfully to $phone", 'info', 'whatsapp.log');
        return true;
    } else {
        $error_detail = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown error';
        kwetupizza_log("WhatsApp message failed: $error_detail", 'error', 'whatsapp.log');
        return false;
    }
}

/**
 * Send SMS via NextSMS
 */
function kwetupizza_send_nextsms($phone, $message) {
    // Log the attempt to send an SMS
    kwetupizza_log("Attempting to send SMS to $phone", 'info', 'sms.log');
    
    $username = get_option('kwetupizza_nextsms_username');
    $password = get_option('kwetupizza_nextsms_password');
    $sender_id = get_option('kwetupizza_nextsms_sender_id', 'KwetuPizza');
    
    if (empty($username) || empty($password)) {
        kwetupizza_log('NextSMS credentials not set', 'error', 'sms.log');
        return false;
    }
    
    // Sanitize phone number
    $phone = kwetupizza_sanitize_phone($phone);
    
    // Ensure phone is in proper format (remove leading +)
    if (substr($phone, 0, 1) === '+') {
        $phone = substr($phone, 1);
    }
    
    // Verify required data is present
    if (empty($phone) || empty($message)) {
        kwetupizza_log('NextSMS Error: Missing required parameters (phone or message)', 'error', 'sms.log');
        return false;
    }
    
    // NextSMS API endpoint
    $url = 'https://messaging-service.co.tz/api/sms/v1/text/single';
    kwetupizza_log("Using NextSMS API endpoint: $url", 'info', 'sms.log');
    
    // Setup the request payload with all required fields
    $payload = array(
        'from' => $sender_id,
        'to' => $phone,
        'text' => $message
    );
    
    // Log the payload for debugging
    kwetupizza_log('NextSMS Payload: ' . print_r($payload, true), 'info', 'sms.log');
    
    // Log authentication info (without exposing the password)
    kwetupizza_log("Using NextSMS username: $username with sender ID: $sender_id", 'info', 'sms.log');
    
    // Send the request
    $response = wp_remote_post($url, array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode("$username:$password")
        ),
        'body' => json_encode($payload),
        'timeout' => 30
    ));
    
    // Check for errors
    if (is_wp_error($response)) {
        kwetupizza_log('NextSMS Error: ' . $response->get_error_message(), 'error', 'sms.log');
        return false;
    }
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    // Log the response for debugging
    kwetupizza_log('NextSMS Response: ' . print_r($body, true), 'info', 'sms.log');
    
    // Check for successful response
    if (isset($body['success']) && $body['success']) {
        kwetupizza_log("SMS sent to $phone successfully", 'info', 'sms.log');
        return true;
    } else {
        $error_message = isset($body['message']) ? $body['message'] : 'Unknown error';
        
        if (isset($body['errors']) && is_array($body['errors'])) {
            foreach ($body['errors'] as $field => $errors) {
                if (is_array($errors) && !empty($errors)) {
                    $error_message .= ' - ' . $field . ': ' . implode(', ', $errors);
                }
            }
        }
        
        kwetupizza_log("Failed to send SMS to $phone: $error_message", 'error', 'sms.log');
        return false;
    }
}

/**
 * Notify admin about new orders or payment status
 */
function kwetupizza_notify_admin($order_id, $success = true) {
    kwetupizza_log("Starting admin notification for order #$order_id", 'info', 'admin-notification.log');
    
    global $wpdb;
    $orders_table = $wpdb->prefix . 'kwetupizza_orders';
    $order_items_table = $wpdb->prefix . 'kwetupizza_order_items';
    
    $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM $orders_table WHERE id = %d", $order_id));
    
    if (!$order) {
        kwetupizza_log("Failed to find order #$order_id for admin notification", 'error', 'admin-notification.log');
        return false;
    }
    
    // Get order items
    $order_items = $wpdb->get_results($wpdb->prepare(
        "SELECT oi.*, p.product_name 
        FROM $order_items_table oi 
        JOIN {$wpdb->prefix}kwetupizza_products p ON oi.product_id = p.id 
        WHERE oi.order_id = %d", 
        $order_id
    ));
    
    $admin_phone = get_option('kwetupizza_admin_whatsapp');
    kwetupizza_log("Admin WhatsApp number from settings: $admin_phone", 'info', 'admin-notification.log');
    
    $status = $success ? 'successful' : 'failed';
    
    $message = $success ? "✅ PAYMENT CONFIRMED" : "❌ PAYMENT FAILED";
    $message .= "\n\n🍕 *Order #$order_id*\n";
    $message .= "👤 *Customer:* {$order->customer_name}\n";
    $message .= "📞 *Phone:* {$order->customer_phone}\n";
    $message .= "🏠 *Address:* {$order->delivery_address}\n";
    
    // Add order items
    $message .= "\n📋 *Order Details:*\n";
    if ($order_items) {
        foreach ($order_items as $item) {
            $message .= "• {$item->quantity}x {$item->product_name}\n";
        }
    }
    
    $message .= "\n💰 *Total:* " . kwetupizza_format_currency($order->total, $order->currency) . "\n";
    $message .= "💳 *Payment:* $status\n";
    $message .= "⏱️ *Time:* " . date('Y-m-d H:i:s');
    
    // Log message content for debugging
    kwetupizza_log("Prepared admin WhatsApp notification message: " . substr($message, 0, 100) . "...", 'info', 'admin-notification.log');
    
    $whatsapp_sent = false;
    if (!empty($admin_phone)) {
        kwetupizza_log("Attempting to send WhatsApp notification to admin at $admin_phone", 'info', 'admin-notification.log');
        $whatsapp_sent = kwetupizza_send_whatsapp_message($admin_phone, $message);
        if ($whatsapp_sent) {
            kwetupizza_log("Successfully sent WhatsApp notification to admin", 'info', 'admin-notification.log');
        } else {
            kwetupizza_log("Failed to send WhatsApp notification to admin", 'error', 'admin-notification.log');
        }
    } else {
        kwetupizza_log("No admin WhatsApp number configured, skipping WhatsApp notification", 'warning', 'admin-notification.log');
    }
    
    $admin_sms = get_option('kwetupizza_admin_sms');
    $sms_sent = false;
    if (!empty($admin_sms)) {
        // Simplified message for SMS due to length constraints
        $sms_message = "Order #$order_id: {$order->customer_name}, {$order->customer_phone}, " . 
                       kwetupizza_format_currency($order->total, $order->currency) . ". Payment: $status";
        kwetupizza_log("Attempting to send SMS notification to admin at $admin_sms", 'info', 'admin-notification.log');
        $sms_sent = kwetupizza_send_nextsms($admin_sms, $sms_message);
        if ($sms_sent) {
            kwetupizza_log("Successfully sent SMS notification to admin", 'info', 'admin-notification.log');
        } else {
            kwetupizza_log("Failed to send SMS notification to admin", 'error', 'admin-notification.log');
        }
    } else {
        kwetupizza_log("No admin SMS number configured, skipping SMS notification", 'warning', 'admin-notification.log');
    }
    
    kwetupizza_log("Admin notification process completed for order #$order_id", 'info', 'admin-notification.log');
    return ($whatsapp_sent || $sms_sent); // Return true if at least one notification was sent
}

/**
 * Notify admin of new orders via WhatsApp and SMS
 */
function kwetupizza_notify_admin_of_order($order_id, $order_details) {
    $admin_whatsapp = get_option('kwetupizza_admin_whatsapp');
    $admin_sms = get_option('kwetupizza_admin_sms');
    
    if (empty($admin_whatsapp) && empty($admin_sms)) {
        error_log('No admin notification numbers set');
        return false;
    }
    
    // Create notification message
    $message = "New Order #{$order_id} Received!\n\n";
    $message .= "Customer: {$order_details['customer_name']}\n";
    $message .= "Phone: {$order_details['customer_phone']}\n";
    $message .= "Amount: {$order_details['amount']} {$order_details['currency']}\n";
    $message .= "Items: {$order_details['items']}\n";
    $message .= "Delivery: {$order_details['delivery_address']}\n";
    $message .= "Time: " . current_time('mysql');
    
    $success = true;
    
    // Send WhatsApp notification
    if (!empty($admin_whatsapp)) {
        $whatsapp_sent = kwetupizza_send_whatsapp_message($admin_whatsapp, $message);
        if (!$whatsapp_sent) {
            error_log('Failed to send WhatsApp notification to admin');
            $success = false;
        }
    }
    
    // Send SMS notification
    if (!empty($admin_sms)) {
        // Create shorter SMS message for cost efficiency
        $sms_message = "New Order #{$order_id}. Customer: {$order_details['customer_name']}. Amount: {$order_details['amount']} {$order_details['currency']}. Check dashboard.";
        
        $sms_sent = kwetupizza_send_nextsms($admin_sms, $sms_message);
        if (!$sms_sent) {
            error_log('Failed to send SMS notification to admin');
            $success = false;
        }
    }
    
    return $success;
}

/**
 * Register AJAX handlers for WhatsApp and SMS
 */
function kwetupizza_register_ajax_handlers() {
    add_action('wp_ajax_kwetupizza_test_whatsapp', 'kwetupizza_test_whatsapp_ajax');
    add_action('wp_ajax_kwetupizza_test_sms', 'kwetupizza_test_sms_ajax');
    add_action('wp_ajax_kwetupizza_init_sample_data', 'kwetupizza_init_sample_data_ajax');
}
add_action('init', 'kwetupizza_register_ajax_handlers');

/**
 * AJAX handler for sending test WhatsApp message
 */
function kwetupizza_test_whatsapp_ajax() {
    check_ajax_referer('kwetupizza_test_whatsapp', 'nonce');
    
    $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : 'This is a test WhatsApp message from KwetuPizza';
    
    if (empty($phone)) {
        wp_send_json_error(['message' => 'Phone number is required']);
        return;
    }
    
    $result = kwetupizza_send_whatsapp_message($phone, $message);
    
    if ($result) {
        wp_send_json_success(['message' => 'WhatsApp message sent successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to send WhatsApp message. Please check your WhatsApp API settings.']);
    }
}

/**
 * AJAX handler for sending test SMS
 */
function kwetupizza_test_sms_ajax() {
    check_ajax_referer('kwetupizza_test_sms', 'nonce');
    
    $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : 'This is a test SMS from KwetuPizza';
    
    if (empty($phone)) {
        wp_send_json_error(['message' => 'Phone number is required']);
        return;
    }
    
    $result = kwetupizza_send_nextsms($phone, $message);
    
    if ($result) {
        wp_send_json_success(['message' => 'SMS sent successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to send SMS. Please check your NextSMS settings.']);
    }
}

/**
 * Send failed payment notification
 */

// ========================
// PAYMENT FUNCTIONS
// ========================

/**
 * Verify Flutterwave payment
 */
function kwetupizza_verify_payment($transaction_id) {
    $flw_secret_key = get_option('kwetupizza_flw_secret_key');
    
    if (empty($flw_secret_key)) {
        kwetupizza_log('Flutterwave secret key not configured', 'error');
        return false;
    }
    
    $url = "https://api.flutterwave.com/v3/transactions/{$transaction_id}/verify";
    
    $response = wp_remote_get($url, [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $flw_secret_key
        ]
    ]);
    
    if (is_wp_error($response)) {
        kwetupizza_log('Flutterwave verification error: ' . $response->get_error_message(), 'error');
        return false;
    }
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    if (isset($body['status']) && $body['status'] === 'success' && 
        isset($body['data']['status']) && $body['data']['status'] === 'successful') {
        return $body['data'];
    }
    
    return false;
}

/**
 * Process successful payment
 */
function kwetupizza_process_successful_payment($data) {
    global $wpdb;
    $transactions_table = $wpdb->prefix . 'kwetupizza_transactions';
    $orders_table = $wpdb->prefix . 'kwetupizza_orders';
    
    $tx_ref = $data['tx_ref'];
    
    // Extract order ID from tx_ref (format could be order_ID or order-ID or order-ID-TIMESTAMP)
    preg_match('/order[-_](\d+)/', $tx_ref, $matches);
    $order_id = isset($matches[1]) ? (int)$matches[1] : 0;
    
    // If the new format with timestamp is used
    if ($order_id === 0 && strpos($tx_ref, '-') !== false) {
        $parts = explode('-', $tx_ref);
        if (count($parts) >= 2 && $parts[0] === 'order') {
            $order_id = (int)$parts[1];
        }
    }
    
    if ($order_id === 0) {
        kwetupizza_log("Failed to extract order ID from tx_ref: $tx_ref", 'error');
        return false;
    }
    
    // Update transaction status
    $wpdb->update(
        $transactions_table,
        [
            'payment_status' => 'completed',
            'transaction_reference' => $data['id'],
            'updated_at' => current_time('mysql')
        ],
        ['order_id' => $order_id]
    );
    
    // Update order status
    $wpdb->update(
        $orders_table,
        [
            'status' => 'processing',
            'updated_at' => current_time('mysql')
        ],
        ['id' => $order_id]
    );
    
    // Add loyalty points (new feature)
    kwetupizza_add_loyalty_points($order_id);
    
    // Notify admin
    kwetupizza_notify_admin($order_id, true);
    
    // Notify customer via both WhatsApp and SMS
    kwetupizza_notify_customer($order_id, 'payment_confirmed');
    
    // Add timeline event
    kwetupizza_add_order_timeline_event($order_id, 'payment_confirmed', 'Payment confirmed');
    
    return true;
}

/**
 * Add loyalty points for customer
 */
function kwetupizza_add_loyalty_points($order_id) {
    global $wpdb;
    $orders_table = $wpdb->prefix . 'kwetupizza_orders';
    $loyalty_table = $wpdb->prefix . 'kwetupizza_customer_loyalty';
    
    $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM $orders_table WHERE id = %d", $order_id));
    
    if (!$order) {
        return false;
    }
    
    // Calculate points - 1 point for every 1000 TZS spent
    $points_earned = floor($order->total / 1000);
    
    // Check if customer already exists in loyalty program
    $customer = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $loyalty_table WHERE customer_phone = %s",
        $order->customer_phone
    ));
    
    if ($customer) {
        // Update existing customer
        $wpdb->update(
            $loyalty_table,
            [
                'points' => $customer->points + $points_earned,
                'total_orders' => $customer->total_orders + 1,
                'total_spent' => $customer->total_spent + $order->total,
                'updated_at' => current_time('mysql')
            ],
            ['customer_phone' => $order->customer_phone]
        );
    } else {
        // Add new customer to loyalty program
        $wpdb->insert(
            $loyalty_table,
            [
                'customer_phone' => $order->customer_phone,
                'points' => $points_earned,
                'total_orders' => 1,
                'total_spent' => $order->total,
                'created_at' => current_time('mysql')
            ]
        );
    }
    
    return true;
}

/**
 * Get customer email
 */
if (!function_exists('kwetupizza_get_customer_email')) {
    function kwetupizza_get_customer_email($phone) {
        global $wpdb;
        $users_table = $wpdb->prefix . 'kwetupizza_users';
        
        $email = $wpdb->get_var($wpdb->prepare("SELECT email FROM $users_table WHERE phone = %s", $phone));
        
        if ($email) {
            return $email;
        }
        
        // Generate a placeholder email if not found
        return 'customer_' . substr(md5($phone), 0, 8) . '@example.com';
    }
}

/**
 * Flutterwave webhook handler
 */
if (!function_exists('kwetupizza_flutterwave_webhook')) {
    function kwetupizza_flutterwave_webhook(WP_REST_Request $request) {
        // Log that webhook was triggered
        kwetupizza_log('Flutterwave webhook triggered', 'info', 'payment-webhook.log');
        
        // Verify webhook signature
        if (!kwetupizza_verify_flutterwave_signature($request)) {
            kwetupizza_log('Invalid webhook signature', 'error', 'payment-webhook.log');
            return new WP_REST_Response('Invalid signature', 401);
        }
        
        $webhook_data = json_decode($request->get_body(), true);
        kwetupizza_log('Webhook received: ' . print_r($webhook_data, true), 'info', 'payment-webhook.log');
        
        if (isset($webhook_data['event']) && $webhook_data['event'] === 'charge.completed') {
            $status = $webhook_data['data']['status'];
            $transaction_id = $webhook_data['data']['id'];
            $tx_ref = $webhook_data['data']['tx_ref'];
            
            kwetupizza_log("Processing payment with status: $status, tx_ref: $tx_ref", 'info', 'payment-webhook.log');
            
            if ($status === 'successful') {
                // Verify payment with Flutterwave API
                kwetupizza_log("Verifying payment with transaction ID: $transaction_id", 'info', 'payment-webhook.log');
                $verification_data = kwetupizza_verify_payment($transaction_id);
                
                if ($verification_data) {
                    kwetupizza_log("Payment verification successful, processing payment", 'info', 'payment-webhook.log');
                    $result = kwetupizza_process_successful_payment($verification_data);
                    
                    if ($result) {
                        kwetupizza_log("Payment processed successfully for tx_ref: $tx_ref", 'info', 'payment-webhook.log');
                    } else {
                        kwetupizza_log("Failed to process payment for tx_ref: $tx_ref", 'error', 'payment-webhook.log');
                    }
                    
                    return new WP_REST_Response('Payment processed successfully', 200);
                } else {
                    kwetupizza_log('Payment verification failed for transaction ID: ' . $transaction_id, 'error', 'payment-webhook.log');
                    return new WP_REST_Response('Payment verification failed', 400);
                }
            } elseif ($status === 'failed') {
                // Extract failure reason if available
                $failure_reason = '';
                if (isset($webhook_data['data']['processor_response'])) {
                    $failure_reason = $webhook_data['data']['processor_response'];
                } elseif (isset($webhook_data['data']['gateway_response'])) {
                    $failure_reason = $webhook_data['data']['gateway_response'];
                }
                
                kwetupizza_log("Processing failed payment for tx_ref: $tx_ref, reason: $failure_reason", 'info', 'payment-webhook.log');
                $result = kwetupizza_handle_failed_payment($tx_ref, $failure_reason);
                
                if ($result) {
                    kwetupizza_log("Failed payment handled successfully for tx_ref: $tx_ref", 'info', 'payment-webhook.log');
                } else {
                    kwetupizza_log("Error handling failed payment for tx_ref: $tx_ref", 'error', 'payment-webhook.log');
                }
                
                return new WP_REST_Response('Payment failed', 400);
            }
        } else {
            kwetupizza_log("Unsupported webhook event: " . (isset($webhook_data['event']) ? $webhook_data['event'] : 'unknown'), 'info', 'payment-webhook.log');
        }
        
        return new WP_REST_Response('Event not supported', 400);
    }
}

/**
 * Handle failed payment
 */
if (!function_exists('kwetupizza_handle_failed_payment')) {
    function kwetupizza_handle_failed_payment($tx_ref, $failure_reason = '') {
        global $wpdb;
        $transactions_table = $wpdb->prefix . 'kwetupizza_transactions';
        $orders_table = $wpdb->prefix . 'kwetupizza_orders';
        
        // Extract order ID from tx_ref (format could be order_ID or order-ID or order-ID-TIMESTAMP)
        preg_match('/order[-_](\d+)/', $tx_ref, $matches);
        $order_id = isset($matches[1]) ? (int)$matches[1] : 0;
        
        // If the new format with timestamp is used
        if ($order_id === 0 && strpos($tx_ref, '-') !== false) {
            $parts = explode('-', $tx_ref);
            if (count($parts) >= 2 && $parts[0] === 'order') {
                $order_id = (int)$parts[1];
            }
        }
        
        if ($order_id === 0) {
            kwetupizza_log("Failed to extract order ID from tx_ref: $tx_ref", 'error');
            return false;
        }
        
        // Get order details for better context
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $orders_table WHERE id = %d",
            $order_id
        ));
        
        if (!$order) {
            kwetupizza_log("Failed to find order for tx_ref: $tx_ref", 'error');
            return false;
        }
        
        // Update transaction status
        $wpdb->update(
            $transactions_table,
            [
                'payment_status' => 'failed',
                'updated_at' => current_time('mysql')
            ],
            ['order_id' => $order_id]
        );
        
        // Update order status
        $wpdb->update(
            $orders_table,
            [
                'status' => 'payment_failed',
                'updated_at' => current_time('mysql')
            ],
            ['id' => $order_id]
        );
        
        // Get retry payment link
        $retry_url = add_query_arg(
            array('order_id' => $order_id),
            get_permalink(get_page_by_path('retry-payment'))
        );
        
        // Add failure reason if available
        $failure_details = !empty($failure_reason) ? 
            "Reason: $failure_reason\n\n" : 
            "This may be due to insufficient funds, network issues, or incorrect payment details.\n\n";
        
        // Notify customer via both WhatsApp and SMS
        $additional_message = $failure_details . "You can retry your payment using this link: " . $retry_url . 
            "\n\nIf you continue to experience issues, please contact our support team at " . 
            get_option('kwetupizza_support_phone', '+255000000000');
        
        kwetupizza_notify_customer($order_id, 'payment_failed', $additional_message);
        
        // Notify admin
        kwetupizza_notify_admin($order_id, false);
        
        // Add timeline event with more details
        $event_description = 'Payment failed';
        if (!empty($failure_reason)) {
            $event_description .= " - $failure_reason";
        }
        kwetupizza_add_order_timeline_event($order_id, 'payment_failed', $event_description);
        
        return true;
    }
}

/**
 * Plugin deactivation function
 */
if (!function_exists('kwetupizza_deactivate')) {
    function kwetupizza_deactivate() {
        // Clean up any transients and options
        delete_option('kwetupizza_db_version');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}

/**
 * Plugin uninstall function
 */
if (!function_exists('kwetupizza_uninstall')) {
    function kwetupizza_uninstall() {
        // Only run this when explicitly uninstalling via the admin dashboard
        if (!defined('WP_UNINSTALL_PLUGIN')) {
            return;
        }
        
        // Additional uninstall actions can be added here
    }
}

// ========================
// WHATSAPP FUNCTIONS
// ========================

/**
 * Main WhatsApp message handler
 */
if (!function_exists('kwetupizza_handle_whatsapp_message')) {
    function kwetupizza_handle_whatsapp_message($from, $message) {
        // Log the context and input for debugging
        kwetupizza_log_context_and_input($from, $message);
        
        // Get current context (if any)
        $context = kwetupizza_get_conversation_context($from);
        
        // Check if we're expecting a specific type of response
        if (!empty($context['awaiting'])) {
            $awaiting = $context['awaiting'];
            
            // Handle different expected responses
            if ($awaiting === 'registration_name') {
                kwetupizza_handle_registration_name($from, $message);
                return;
            } else if ($awaiting === 'registration_email') {
                kwetupizza_handle_registration_email($from, $message);
                return;
            } else if ($awaiting === 'registration_location') {
                kwetupizza_handle_registration_location($from, $message);
                return;
            } else if ($awaiting === 'category_selection') {
                kwetupizza_handle_category_selection($from, $message);
                return;
            } else if ($awaiting === 'menu_selection') {
                kwetupizza_process_order($from, $message);
                return;
            } else if ($awaiting === 'quantity') {
                // Get the last product in the cart
                $last_product = end($context['cart']);
                $product_id = $last_product['product_id'];
                kwetupizza_confirm_order_and_request_address($from, $product_id, intval(trim($message)));
                return;
            } else if ($awaiting === 'product_quantity') {
                kwetupizza_confirm_order_and_request_address($from, $context['product_id'], intval(trim($message)));
                return;
            } else if ($awaiting === 'add_or_checkout') {
                kwetupizza_handle_add_or_checkout($from, $message);
                return;
            } else if ($awaiting === 'user_name') {
                kwetupizza_handle_user_name_input($from, $message);
                return;
            } else if ($awaiting === 'user_email') {
                kwetupizza_handle_user_email_input($from, $message);
                return;
            } else if ($awaiting === 'delivery_zone') {
                kwetupizza_handle_delivery_zone_selection($from, $message);
                return;
            } else if ($awaiting === 'delivery_address') {
                kwetupizza_handle_address_and_ask_payment_provider($from, $message);
                return;
            } else if ($awaiting === 'payment_provider') {
                kwetupizza_handle_payment_provider($from, $message);
                return;
            } else if ($awaiting === 'order_completion') {
                // Order is complete, just reply with a generic message
                kwetupizza_send_whatsapp_message($from, "Thank you for your order! You'll receive updates as your pizza is prepared and delivered. If you have any questions, please type 'help'.");
                return;
            }
        }
        
        // If not expecting a specific response, check for standard commands
        $message = trim($message);
        $lowercase_message = strtolower($message);
        
        // Check for standard commands
        if ($lowercase_message === 'menu' || $lowercase_message === 'order') {
            kwetupizza_send_menu_categories($from);
            return;
        }
        
        if ($lowercase_message === 'status') {
            kwetupizza_check_order_status($from);
            return;
        }
        
        if (strtolower(trim($message)) === 'help' || strpos(strtolower($message), 'help me') !== false) {
            kwetupizza_send_help_message($from);
            return;
        }
        
        // Check if the message is a greeting
        if (kwetupizza_is_greeting($message)) {
            kwetupizza_start_conversation($from);
            return;
        }
        
        // Default response for unrecognized messages
        kwetupizza_send_default_response($from);
    }
}

/**
 * Get user by phone number without creating a new one
 */
if (!function_exists('kwetupizza_get_user_by_phone')) {
    function kwetupizza_get_user_by_phone($phone) {
        global $wpdb;
        $users_table = $wpdb->prefix . 'kwetupizza_users';
        
        // Sanitize phone number
        $phone = kwetupizza_sanitize_phone($phone);
        
        // Check if user exists
        $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $users_table WHERE phone = %s", $phone));
        
        // If user exists, return user data
        if ($user) {
            return $user;
        }
        
        // Return null if user not found
        return null;
    }
}

/**
 * Welcome new user and start registration process
 */
if (!function_exists('kwetupizza_send_welcome_and_start_registration')) {
    function kwetupizza_send_welcome_and_start_registration($from) {
        // Sanitize the phone number for display
        $sanitized_phone = kwetupizza_sanitize_phone($from);
        
        $message = "👋 *Welcome to KwetuPizza* 🍕\n\n";
        $message .= "It looks like this is your first time chatting with us. We've already identified your WhatsApp number ($sanitized_phone) as the first step!\n\n";
        $message .= "Now, to personalize your experience, please tell us your name. What should we call you?";
        
        kwetupizza_send_whatsapp_message($from, $message);
        
        // Set context to expect name input
        kwetupizza_set_conversation_context($from, ['state' => 'greeting']);
    }
}

/**
 * Handle registration name input
 */
if (!function_exists('kwetupizza_handle_registration_name')) {
    function kwetupizza_handle_registration_name($from, $name) {
        if (empty(trim($name))) {
            kwetupizza_send_whatsapp_message($from, "Please provide a valid name to continue with your registration.");
            return;
        }
        
        // Get context that includes the phone number
        $existing_context = kwetupizza_get_conversation_context($from);
        $phone = isset($existing_context['phone']) ? $existing_context['phone'] : kwetupizza_sanitize_phone($from);
        
        // Create context with user's name and phone
        $context = [
            'user_name' => $name, 
            'awaiting' => 'registration_email',
            'phone' => $phone
        ];
        kwetupizza_set_conversation_context($from, $context);
        
        // Ask for email
        $message = "Nice to meet you, " . explode(' ', $name)[0] . "! 😊\n\n";
        $message .= "We already have your WhatsApp number ($phone) saved.\n\n";
        $message .= "Please share your email address so we can send you order confirmations and special offers.\n\n";
        $message .= "(Or type 'skip' if you prefer not to share your email right now)";
        
        kwetupizza_send_whatsapp_message($from, $message);
    }
}

/**
 * Handle registration email input
 */
if (!function_exists('kwetupizza_handle_registration_email')) {
    function kwetupizza_handle_registration_email($from, $email) {
        $context = kwetupizza_get_conversation_context($from);
        
        // Check if user wants to skip
        if (strtolower(trim($email)) === 'skip') {
            $email = '';
        } else {
            // Basic email validation
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) && !empty(trim($email))) {
                kwetupizza_send_whatsapp_message($from, "That doesn't look like a valid email address. Please try again or type 'skip'.");
                return;
            }
        }
        
        // Save email to context while preserving phone
        $context['user_email'] = $email;
        $context['awaiting'] = 'registration_location';
        // Ensure phone is still in context
        if (!isset($context['phone'])) {
            $context['phone'] = kwetupizza_sanitize_phone($from);
        }
        kwetupizza_set_conversation_context($from, $context);
        
        // Get user's first name from context
        $first_name = isset($context['user_name']) ? explode(' ', $context['user_name'])[0] : '';
        
        // Ask for location
        $message = "Thank you, $first_name! 👍\n\n";
        $message .= "Finally, please tell us your location or area where you typically want deliveries sent:";
        
        kwetupizza_send_whatsapp_message($from, $message);
    }
}

/**
 * Handle user location input
 */
if (!function_exists('kwetupizza_handle_registration_location')) {
    function kwetupizza_handle_registration_location($from, $location) {
        $context = kwetupizza_get_conversation_context($from);
        $location_value = trim($location);
        
        if (empty($location_value)) {
            kwetupizza_send_whatsapp_message($from, "Please provide a valid location to continue.");
            return;
        }
        
        // Make sure we have a phone number
        $phone = isset($context['phone']) ? $context['phone'] : kwetupizza_sanitize_phone($from);
        
        // Get user data from context
        $user_name = isset($context['user_name']) ? $context['user_name'] : "Customer-" . substr($from, -5);
        $user_email = isset($context['user_email']) ? $context['user_email'] : '';
        
        global $wpdb;
        $users_table = $wpdb->prefix . 'kwetupizza_users';
        
        // Create user in database with location
        $wpdb->insert(
            $users_table,
            array(
                'name' => $user_name,
                'email' => $user_email,
                'phone' => $phone,
                'location' => $location_value, // Store the location
                'role' => 'customer',
                'state' => 'active',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            )
        );
        
        // Get the user's first name for personalization
        $first_name = explode(' ', $user_name)[0];
        
        // Update context to maintain user info throughout the conversation
        $new_context = [
            'state' => 'menu_browsing',
            'user_name' => $user_name,
            'first_name' => $first_name,
            'awaiting' => 'category_selection'
        ];
        kwetupizza_set_conversation_context($from, $new_context);
        
        // Send welcome message
        $message = "🎉 *Registration Complete!* 🎉\n\n";
        $message .= "Thank you, $first_name! Your account has been created.\n\n";
        $message .= "Now, let's see what you'd like to order today:";
        
        kwetupizza_send_whatsapp_message($from, $message);
        
        // Show menu categories
        kwetupizza_send_menu_categories($from);
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
        
        // Set context to await menu selection while preserving user info
        $updated_context = array_merge($context, ['awaiting' => 'menu_selection']);
        kwetupizza_set_conversation_context($from, $updated_context);
    }
}

/**
 * Process order selection
 */
if (!function_exists('kwetupizza_process_order')) {
    function kwetupizza_process_order($from, $product_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kwetupizza_products';
        $product = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $product_id));

        if ($product) {
            $message = "You've selected " . $product->product_name . ". Please enter the quantity.";
            kwetupizza_send_whatsapp_message($from, $message);
            
            $context = kwetupizza_get_conversation_context($from);
            $context['cart'][] = [
                'product_id' => $product_id,
                'product_name' => $product->product_name,
                'price' => $product->price
            ];
            kwetupizza_set_conversation_context($from, array_merge($context, ['awaiting' => 'quantity']));
        } else {
            kwetupizza_send_whatsapp_message($from, "Sorry, the selected item is not available.");
        }
    }
}

/**
 * Confirm order and request address
 */
if (!function_exists('kwetupizza_confirm_order_and_request_address')) {
    function kwetupizza_confirm_order_and_request_address($from, $product_id, $quantity) {
        global $wpdb;
        $context = kwetupizza_get_conversation_context($from);

        foreach ($context['cart'] as &$cart_item) {
            if ($cart_item['product_id'] == $product_id) {
                $cart_item['quantity'] = $quantity;
                $cart_item['total'] = $cart_item['price'] * $quantity;
                break;
            }
        }

        kwetupizza_set_conversation_context($from, $context);

        $message = "Would you like to add more items or proceed to checkout? Type 'add' to add more items or 'checkout' to proceed.";
        kwetupizza_send_whatsapp_message($from, $message);

        kwetupizza_set_conversation_context($from, array_merge($context, ['awaiting' => 'add_or_checkout']));
    }
}

/**
 * Handle add or checkout response
 */
if (!function_exists('kwetupizza_handle_add_or_checkout')) {
    function kwetupizza_handle_add_or_checkout($from, $response) {
        $response = strtolower(trim($response));
        $context = kwetupizza_get_conversation_context($from);

        if ($response === 'add') {
            kwetupizza_send_menu_categories($from);
            $context['awaiting'] = 'menu_selection';
            kwetupizza_set_conversation_context($from, $context);
        } elseif ($response === 'checkout') {
            // Calculate the order total
            $total = 0;
            $summary_message = "📋 *Order Summary* 📋\n\n";
            foreach ($context['cart'] as $cart_item) {
                $summary_message .= "{$cart_item['quantity']} x {$cart_item['product_name']} - " . number_format($cart_item['total'], 2) . " TZS\n";
                $total += $cart_item['total'];
            }
            $summary_message .= "\nSubtotal: " . number_format($total, 2) . " TZS\n";
            
            // Save the total in the context
            $context['total'] = $total;
            kwetupizza_set_conversation_context($from, $context);
            
            // Send the order summary
            kwetupizza_send_whatsapp_message($from, $summary_message);
            
            // Check if we need to collect user information
            $user = kwetupizza_get_or_create_user($from);
            if (strpos($user->name, 'Customer-') === 0 || empty($user->email)) {
                // We need to collect user info before proceeding
                kwetupizza_request_user_information($from);
            } else {
                // User information exists, proceed to delivery zone selection
                $message = "Thank you, " . explode(' ', $user->name)[0] . "! Let's continue with your delivery information.";
                kwetupizza_send_whatsapp_message($from, $message);
                kwetupizza_show_delivery_zones($from);
            }
        } else {
            kwetupizza_send_whatsapp_message($from, "Sorry, I didn't understand that. Type 'add' to add more items or 'checkout' to proceed.");
        }
    }
}

/**
 * Request user information for registration
 */
if (!function_exists('kwetupizza_request_user_information')) {
    function kwetupizza_request_user_information($from) {
        $message = "Before we continue with your order, we'd like to know a bit more about you.\n\n";
        $message .= "Please enter your full name:";
        
        kwetupizza_send_whatsapp_message($from, $message);
        
        $context = kwetupizza_get_conversation_context($from);
        kwetupizza_set_conversation_context($from, array_merge($context, ['awaiting' => 'user_name']));
    }
}

/**
 * Handle user name input
 */
if (!function_exists('kwetupizza_handle_user_name_input')) {
    function kwetupizza_handle_user_name_input($from, $name) {
        $context = kwetupizza_get_conversation_context($from);
        
        if (empty(trim($name))) {
            kwetupizza_send_whatsapp_message($from, "Please provide a valid name to continue with your order.");
            return;
        }
        
        // Save name in context
        $context['user_name'] = $name;
        
        // Update user in database
        global $wpdb;
        $users_table = $wpdb->prefix . 'kwetupizza_users';
        $wpdb->update(
            $users_table,
            ['name' => $name],
            ['phone' => kwetupizza_sanitize_phone($from)]
        );
        
        // Request email
        $message = "Thank you, " . explode(' ', $name)[0] . "! Please provide your email address (or type 'skip' if you prefer not to):";
        kwetupizza_send_whatsapp_message($from, $message);
        
        // Update context to await email
        kwetupizza_set_conversation_context($from, array_merge($context, ['awaiting' => 'user_email']));
    }
}

/**
 * Handle user email input
 */
if (!function_exists('kwetupizza_handle_user_email_input')) {
    function kwetupizza_handle_user_email_input($from, $email) {
        $context = kwetupizza_get_conversation_context($from);
        
        // Check if user wants to skip
        if (strtolower(trim($email)) === 'skip') {
            $message = "No problem! Let's continue with your delivery information.";
            kwetupizza_send_whatsapp_message($from, $message);
            kwetupizza_show_delivery_zones($from);
            return;
        }
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            kwetupizza_send_whatsapp_message($from, "Please provide a valid email address or type 'skip' to continue without it.");
            return;
        }
        
        // Update user in database
        global $wpdb;
        $users_table = $wpdb->prefix . 'kwetupizza_users';
        $wpdb->update(
            $users_table,
            ['email' => $email],
            ['phone' => kwetupizza_sanitize_phone($from)]
        );
        
        $message = "Thank you for providing your information! Now let's continue with your delivery details.";
        kwetupizza_send_whatsapp_message($from, $message);
        
        // Move to delivery zone selection
        kwetupizza_show_delivery_zones($from);
    }
}

/**
 * Show available delivery zones
 */
if (!function_exists('kwetupizza_show_delivery_zones')) {
    function kwetupizza_show_delivery_zones($from) {
        global $wpdb;
        $zones_table = $wpdb->prefix . 'kwetupizza_delivery_zones';
        
        // Get all delivery zones
        $zones = $wpdb->get_results("SELECT id, zone_name, description, delivery_fee FROM $zones_table ORDER BY delivery_fee ASC");
        
        if (empty($zones)) {
            // If no zones defined, proceed with asking for the full address
            $message = "Please provide your full delivery address with street and landmarks.";
            kwetupizza_send_whatsapp_message($from, $message);
            
            $context = kwetupizza_get_conversation_context($from);
            kwetupizza_set_conversation_context($from, array_merge($context, ['awaiting' => 'full_address']));
            return;
        }
        
        // Format the delivery zones message
        $message = "📍 *Select Your Delivery Area* 📍\n\n";
        $message .= "Please type the number of your delivery area:\n\n";
        
        foreach ($zones as $index => $zone) {
            $message .= "{$zone->id}. *{$zone->zone_name}*\n";
            $message .= "   {$zone->description}\n";
            $message .= "   Delivery Fee: " . number_format($zone->delivery_fee, 2) . " TZS\n\n";
        }
        
        kwetupizza_send_whatsapp_message($from, $message);
        
        // Set context to await delivery zone selection
        $context = kwetupizza_get_conversation_context($from);
        kwetupizza_set_conversation_context($from, array_merge($context, ['awaiting' => 'delivery_zone']));
    }
}

/**
 * Handle delivery zone selection
 */
if (!function_exists('kwetupizza_handle_delivery_zone_selection')) {
    function kwetupizza_handle_delivery_zone_selection($from, $zone_id) {
        global $wpdb;
        $zones_table = $wpdb->prefix . 'kwetupizza_delivery_zones';
        
        // Check if zone_id is valid
        $zone = $wpdb->get_row($wpdb->prepare("SELECT * FROM $zones_table WHERE id = %d", $zone_id));
        
        if (!$zone) {
            kwetupizza_send_whatsapp_message($from, "Please select a valid delivery area number.");
            kwetupizza_show_delivery_zones($from);
            return;
        }
        
        // Save the selected zone in context
        $context = kwetupizza_get_conversation_context($from);
        $context['delivery_zone'] = [
            'id' => $zone->id,
            'name' => $zone->zone_name,
            'fee' => $zone->delivery_fee
        ];
        
        // Add delivery fee to order total
        if (!isset($context['total'])) {
            $context['total'] = 0;
            foreach ($context['cart'] as $item) {
                $context['total'] += $item['total'];
            }
        }
        
        $context['delivery_fee'] = $zone->delivery_fee;
        $context['grand_total'] = $context['total'] + $zone->delivery_fee;
        
        kwetupizza_set_conversation_context($from, $context);
        
        // Ask for specific address within the zone
        $message = "You've selected: *{$zone->zone_name}*\n\n";
        $message .= "Please provide your specific address within this area (street, house/apartment number, landmarks):";
        
        kwetupizza_send_whatsapp_message($from, $message);
        
        // Update context to await full address
        kwetupizza_set_conversation_context($from, array_merge($context, ['awaiting' => 'full_address']));
    }
}

/**
 * Handle address input and ask for payment provider
 */
if (!function_exists('kwetupizza_handle_address_and_ask_payment_provider')) {
    function kwetupizza_handle_address_and_ask_payment_provider($from, $address) {
        $context = kwetupizza_get_conversation_context($from);

        if (isset($context['cart'])) {
            // Save the address in the conversation context
            $context['address'] = $address;
            kwetupizza_set_conversation_context($from, $context);

            // If we have a delivery zone and fee set, include it in the order summary
            $summary_message = "📋 *Order Summary* 📋\n\n";
            
            foreach ($context['cart'] as $cart_item) {
                $summary_message .= "{$cart_item['quantity']} x {$cart_item['product_name']} - " . number_format($cart_item['total'], 2) . " TZS\n";
            }
            
            $summary_message .= "\nSubtotal: " . number_format($context['total'], 2) . " TZS\n";
            
            if (isset($context['delivery_fee'])) {
                $summary_message .= "Delivery Fee: " . number_format($context['delivery_fee'], 2) . " TZS\n";
                $summary_message .= "Total: " . number_format($context['grand_total'], 2) . " TZS\n\n";
            } else {
                $summary_message .= "Total: " . number_format($context['total'], 2) . " TZS\n\n";
            }
            
            $summary_message .= "Delivery Address: {$address}\n\n";
            $summary_message .= "Please select your Mobile Money network for payment:";
            
            // Show interactive network selection buttons
            $networks = [
                "1" => "Vodacom (M-Pesa)",
                "2" => "Tigo (Tigo Pesa)",
                "3" => "Airtel (Airtel Money)",
                "4" => "Halotel (Halopesa)"
            ];
            
            foreach ($networks as $key => $network) {
                $summary_message .= "\n{$key}. {$network}";
            }
            
            kwetupizza_send_whatsapp_message($from, $summary_message);

            // Set the context to expect a network provider response
            kwetupizza_set_conversation_context($from, array_merge($context, ['awaiting' => 'payment_provider']));
        } else {
            kwetupizza_send_whatsapp_message($from, "Error processing your order. Please try again.");
        }
    }
}

/**
 * Handle payment provider response
 */
if (!function_exists('kwetupizza_handle_payment_provider_response')) {
    function kwetupizza_handle_payment_provider_response($from, $provider) {
        $context = kwetupizza_get_conversation_context($from);
        
        if (isset($context['awaiting']) && $context['awaiting'] === 'payment_provider') {
            $valid_providers = array(
                '1' => 'vodacom',
                '2' => 'tigo',
                '3' => 'airtel',
                '4' => 'halopesa',
                '5' => 'paypal' // New PayPal option
            );
            
            // Map common names to our provider keys
            $provider_map = array(
                'vodacom' => 'vodacom',
                'mpesa' => 'vodacom',
                'tigo' => 'tigo',
                'tigopesa' => 'tigo',
                'airtel' => 'airtel',
                'airtelmoney' => 'airtel',
                'halo' => 'halopesa',
                'halopesa' => 'halopesa',
                'card' => 'paypal',
                'paypal' => 'paypal',
                'creditcard' => 'paypal',
                'debitcard' => 'paypal'
            );
            
            // Try to match input to a provider
            $provider_key = null;
            if (isset($valid_providers[$provider])) {
                $provider_key = $valid_providers[$provider];
            } elseif (isset($provider_map[strtolower($provider)])) {
                $provider_key = $provider_map[strtolower($provider)];
            }
            
            if ($provider_key) {
                // Save the provider to the conversation context
                $context['payment_provider'] = $provider_key;
                kwetupizza_set_conversation_context($from, $context);
                
                if ($provider_key === 'paypal') {
                    // Handle PayPal/card payment flow
                    kwetupizza_handle_paypal_payment($from);
                } else {
                    // Handle mobile money flow
                    // Ask if the user wants to use their WhatsApp number for payment
                    $message = "Would you like to use your WhatsApp number ($from) for payment?\n\n";
                    $message .= "1. Yes\n";
                    $message .= "2. No (provide another number)";
                    
                    kwetupizza_send_whatsapp_message($from, $message);
    
                    // Set the context to expect a yes/no response
                    kwetupizza_set_conversation_context($from, array_merge($context, ['awaiting' => 'use_whatsapp_number']));
                }
            } else {
                // Invalid provider input
                $message = "Please reply with a valid payment option:\n\n";
                $message .= "1. Vodacom M-Pesa\n";
                $message .= "2. Tigo Pesa\n";
                $message .= "3. Airtel Money\n";
                $message .= "4. Halo Pesa\n";
                $message .= "5. Card Payment (PayPal)";
                
                kwetupizza_send_whatsapp_message($from, $message);
            }
        } else {
            // Unexpected response, send a default message
            kwetupizza_send_whatsapp_message($from, "Sorry, I didn't understand that. Type 'menu' to see available options.");
        }
    }
}

/**
 * Handle user's response to using WhatsApp number for payment
 */
if (!function_exists('kwetupizza_handle_use_whatsapp_number_response')) {
    function kwetupizza_handle_use_whatsapp_number_response($from, $response) {
        $response = strtolower(trim($response));
        $context = kwetupizza_get_conversation_context($from);

        if (isset($context['awaiting']) && $context['awaiting'] === 'use_whatsapp_number') {
            if ($response === 'yes' || $response === '1') {
                // Proceed with using WhatsApp number for payment
                kwetupizza_generate_mobile_money_push($from, $context['cart'], $context['address'], $from);
            } elseif ($response === 'no' || $response === '2') {
                // Ask for an alternative phone number
                $message = "Please provide the phone number you'd like to use for mobile money payment (e.g., 255XXXXXXXXX):";
                kwetupizza_send_whatsapp_message($from, $message);

                // Update context to expect a new phone number for payment
                kwetupizza_set_conversation_context($from, array_merge($context, ['awaiting' => 'payment_phone']));
            } else {
                kwetupizza_send_whatsapp_message($from, "Please reply with '1' for Yes or '2' for No.");
            }
        } else {
            kwetupizza_send_whatsapp_message($from, "Error: No active payment process. Please restart your order.");
        }
    }
}

/**
 * Handle the input for the payment phone number
 */
if (!function_exists('kwetupizza_handle_payment_phone_input')) {
    function kwetupizza_handle_payment_phone_input($from, $payment_phone) {
        $context = kwetupizza_get_conversation_context($from);

        // Check if the user is expected to provide a phone number
        if (isset($context['awaiting']) && $context['awaiting'] === 'payment_phone') {
            // Proceed with the provided phone number for payment
            kwetupizza_generate_mobile_money_push($from, $context['cart'], $context['address'], $payment_phone);
        } else {
            kwetupizza_send_whatsapp_message($from, "I'm not expecting a payment phone number at this moment. Please restart your order if you want to make changes.");
        }
    }
}

/**
 * Generate Mobile Money Push payment request
 */
if (!function_exists('kwetupizza_generate_mobile_money_push')) {
    function kwetupizza_generate_mobile_money_push($from, $cart, $address, $payment_phone) {
        global $wpdb;
        
        // Get context for additional data like delivery zone fee
        $context = kwetupizza_get_conversation_context($from);
        
        // Validate inputs
        if (empty($cart) || !is_array($cart)) {
            kwetupizza_log("ERROR: Invalid cart data for payment request", 'error', 'payment.log');
            kwetupizza_send_whatsapp_message($from, "⚠️ Error: Invalid order data. Please try ordering again.");
            return false;
        }
        
        if (empty($payment_phone)) {
            kwetupizza_log("ERROR: Missing payment phone number", 'error', 'payment.log');
            kwetupizza_send_whatsapp_message($from, "⚠️ Error: Missing payment phone number. Please provide your mobile money number.");
            return false;
        }
        
        // Calculate total amount including delivery fee
        $subtotal = 0;
        foreach ($cart as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }
        
        // Add delivery fee from context if available
        $delivery_fee = isset($context['delivery_fee']) ? $context['delivery_fee'] : 0;
        $total = $subtotal + $delivery_fee;
        
        // Format items for notification
        $items_text = "";
        foreach ($cart as $item) {
            $items_text .= "{$item['product_name']} x {$item['quantity']} = " . kwetupizza_format_currency($item['price'] * $item['quantity']) . "\n";
        }
        
        // Get user information
        $user = kwetupizza_get_or_create_user($from);
        
        // Create a unique transaction reference
        $tx_ref = 'order-' . time() . '-' . kwetupizza_generate_random_string(6);
        
        // Save order to database before payment initiation
        $order_id = kwetupizza_save_order_to_db($from, $cart, $address, $total, $context);
        
        if (!$order_id) {
            kwetupizza_log("ERROR: Failed to save order to database", 'error', 'payment.log');
            kwetupizza_send_whatsapp_message($from, "⚠️ Error processing your order. Please try again later or contact support.");
            return false;
        }
        
        // Update tx_ref to include order ID for better tracking
        $tx_ref = 'order-' . $order_id . '-' . time();
        
        // Get API key
        $flw_secret_key = get_option('kwetupizza_flw_secret_key');
        if (empty($flw_secret_key)) {
            kwetupizza_log("ERROR: Missing Flutterwave secret key", 'error', 'payment.log');
            kwetupizza_send_whatsapp_message($from, "Error: Payment gateway not properly configured. Please contact support.");
            return false;
        }

        // Get user's email, with fallback
        $user_email = !empty($user->email) ? $user->email : kwetupizza_get_customer_email($from);
        
        // Get user's name, with fallback
        $user_name = $user->name;
        if (strpos($user_name, 'Customer-') === 0) {
            $user_name = isset($context['user_name']) ? $context['user_name'] : $user_name;
        }

        // Prepare payment payload
        $payload = [
            'tx_ref' => $tx_ref,
            'amount' => $total,
            'currency' => 'TZS',
            'network' => isset($context['payment_provider']) ? strtoupper($context['payment_provider']) : 'MPESA',
            'email' => $user_email,
            'phone_number' => $payment_phone,
            'fullname' => $user_name,
            'redirect_url' => kwetupizza_get_callback_url('flutterwave'),
            'meta' => [
                'order_id' => $order_id,
                'customer_phone' => $from
            ]
        ];
        
        // Log payment attempt with detailed information
        kwetupizza_log("Initiating mobile money payment: " . json_encode($payload), 'info', 'payment.log');
        
        // Make API request to Flutterwave
        $response = wp_remote_post('https://api.flutterwave.com/v3/charges?type=mobile_money_tanzania', [
            'headers' => [
                'Authorization' => 'Bearer ' . $flw_secret_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($payload)
        ]);

        // Check for request errors
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            kwetupizza_log("ERROR: Flutterwave API request failed: $error_message", 'error', 'payment.log');
            kwetupizza_send_whatsapp_message($from, "Error initiating the payment. Please try again later or contact support.");
            return false;
        }

        $response_body = wp_remote_retrieve_body($response);
        $result = json_decode($response_body, true);
        
        // Log the response
        kwetupizza_log("Flutterwave response: " . json_encode($result), 'info', 'payment.log');
        
        // Check if payment initiation was successful
        if (isset($result['status']) && $result['status'] === 'success') {
            // Update the transaction reference in the database
            kwetupizza_update_transaction_reference($order_id, $tx_ref, $result['data']['id']);
            
            // Send confirmation message
            $message = "🍕 *Your Order is Being Processed!* 🍕\n\n";
            $message .= "Order #$order_id has been created.\n\n";
            $message .= "📱 Check your phone for a payment prompt from Flutterwave/Mpesa.\n";
            $message .= "Please enter your PIN to complete the payment of " . kwetupizza_format_currency($total) . ".\n\n";
            
            $message .= "📋 *Order Details*:\n";
            $message .= $items_text;
            if ($delivery_fee > 0) {
                $message .= "Delivery Fee: " . kwetupizza_format_currency($delivery_fee) . "\n";
            }
            $message .= "Total: " . kwetupizza_format_currency($total) . "\n\n";
            $message .= "🏠 Delivery Address: $address\n\n";
            $message .= "💳 Payment Method: " . (isset($context['payment_provider']) ? $context['payment_provider'] : 'Mobile Money') . "\n";
            $message .= "📞 Payment Number: $payment_phone\n\n";
            $message .= "We'll notify you once your payment is confirmed!";
            
            kwetupizza_send_whatsapp_message($from, $message);
            
            // Notify admin of new order
            kwetupizza_notify_admin_of_order($order_id, [
                'customer_name' => $user_name,
                'customer_phone' => $from,
                'items' => $items_text,
                'delivery_address' => $address,
                'amount' => $total,
                'currency' => 'TZS'
            ]);
            
            // Reset conversation context after successful order
            kwetupizza_set_conversation_context($from, []);
            
            return true;
        } else {
            // Handle payment initiation failure
            $error_message = isset($result['message']) ? $result['message'] : 'Unknown error';
            kwetupizza_log("ERROR: Payment initiation failed: $error_message", 'error', 'payment.log');
            
            // Update order status to failed
            $wpdb->update(
                $wpdb->prefix . 'kwetupizza_orders',
                ['status' => 'payment_failed'],
                ['id' => $order_id]
            );
            
            // Send error message to customer
            kwetupizza_send_whatsapp_message(
                $from, 
                "⚠️ *Payment Failed*\n\nWe were unable to initiate your payment: $error_message\n\n" .
                "Please try again later or contact our support team for assistance."
            );
            
            return false;
        }
    }
}

/**
 * Update transaction reference after payment initiation
 */
if (!function_exists('kwetupizza_update_transaction_reference')) {
    function kwetupizza_update_transaction_reference($order_id, $tx_ref, $transaction_id) {
        global $wpdb;
        $transactions_table = $wpdb->prefix . 'kwetupizza_transactions';
        
        $wpdb->update(
            $transactions_table,
            [
                'transaction_reference' => $transaction_id,
                'tx_ref' => $tx_ref,
                'updated_at' => current_time('mysql')
            ],
            ['order_id' => $order_id]
        );
        
        kwetupizza_log("Updated transaction reference for order #$order_id: $tx_ref, ID: $transaction_id", 'info', 'payment.log');
    }
}

/**
 * Save order to database
 */
if (!function_exists('kwetupizza_save_order_to_db')) {
    function kwetupizza_save_order_to_db($phone, $cart, $address, $total, $context) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'kwetupizza_orders';
        $order_items_table = $wpdb->prefix . 'kwetupizza_order_items';
        
        // Get or create user to use their name
        $user = kwetupizza_get_or_create_user($phone);
        
        // Check if we have a user name in context (from registration step)
        $user_name = $user->name;
        if (isset($context['user_name']) && !empty($context['user_name'])) {
            $user_name = $context['user_name'];
            
            // Update user in database if needed
            if ($user_name !== $user->name) {
                $users_table = $wpdb->prefix . 'kwetupizza_users';
                $wpdb->update(
                    $users_table,
                    ['name' => $user_name],
                    ['phone' => kwetupizza_sanitize_phone($phone)]
                );
            }
        }
        
        // Use email from context if available
        $user_email = $user->email;
        if (isset($context['user_email']) && !empty($context['user_email']) && filter_var($context['user_email'], FILTER_VALIDATE_EMAIL)) {
            $user_email = $context['user_email'];
            
            // Update user in database if needed
            if ($user_email !== $user->email) {
                $users_table = $wpdb->prefix . 'kwetupizza_users';
                $wpdb->update(
                    $users_table,
                    ['email' => $user_email],
                    ['phone' => kwetupizza_sanitize_phone($phone)]
                );
            }
        }
        
        // Prepare common order data
        $order_data = array(
            'order_date' => current_time('mysql'),
            'customer_name' => $user_name,
            'customer_phone' => kwetupizza_sanitize_phone($phone),
            'customer_email' => $user_email,
            'delivery_address' => $address,
            'delivery_phone' => kwetupizza_sanitize_phone($phone),
            'status' => 'pending_payment',
            'total' => $total,
            'currency' => 'TZS',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        // Insert order into database
        $result = $wpdb->insert($orders_table, $order_data);
        
        if ($result === false) {
            kwetupizza_log("Failed to insert order into database: " . $wpdb->last_error, 'error');
            return false;
        }
        
        $order_id = $wpdb->insert_id;
        kwetupizza_log("Order saved with ID: $order_id", 'info');
        
        // Insert order items
        foreach ($context['cart'] as $item) {
            $item_data = array(
                'order_id' => $order_id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'created_at' => current_time('mysql')
            );
            
            $wpdb->insert($order_items_table, $item_data);
        }
        
        // Create transaction record
        $transactions_table = $wpdb->prefix . 'kwetupizza_transactions';
        $transaction_data = array(
            'order_id' => $order_id,
            'transaction_date' => current_time('mysql'),
            'payment_method' => isset($context['payment_provider']) ? $context['payment_provider'] : 'Mobile Money',
            'payment_status' => 'pending',
            'amount' => $total,
            'currency' => 'TZS',
            'payment_provider' => 'Flutterwave',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        $wpdb->insert($transactions_table, $transaction_data);
        
        // Add order creation event to timeline
        kwetupizza_add_order_timeline_event($order_id, 'order_created', 'Order created via WhatsApp');
        
        return $order_id;
    }
}

/**
 * Verify payment using Flutterwave webhook and notify
 */
if (!function_exists('kwetupizza_confirm_payment_and_notify')) {
    function kwetupizza_confirm_payment_and_notify($transaction_id) {
        // Log the function call
        kwetupizza_log("Confirming payment for transaction ID: $transaction_id", 'info', 'payment-confirmation.log');
        
        // Verify payment with Flutterwave API
        $transaction_data = kwetupizza_verify_payment($transaction_id);

        if (!$transaction_data) {
            kwetupizza_log("Payment verification failed for transaction ID: $transaction_id", 'error', 'payment-confirmation.log');
            return false;
        }
        
        // Log successful verification 
        kwetupizza_log("Payment verified successfully: " . json_encode($transaction_data), 'info', 'payment-confirmation.log');

        global $wpdb;
        $tx_ref = $transaction_data['tx_ref'];

        // Extract order ID from tx_ref (format could be order_ID or order-ID or order-ID-TIMESTAMP)
        preg_match('/order[-_](\\d+)/', $tx_ref, $matches);
        $order_id = isset($matches[1]) ? $matches[1] : null;
        
        // If the new format with timestamp is used
        if (!$order_id && strpos($tx_ref, '-') !== false) {
            $parts = explode('-', $tx_ref);
            if (count($parts) >= 2 && $parts[0] === 'order') {
                $order_id = $parts[1];
            }
        }
        
        if (!$order_id) {
            kwetupizza_log("Could not extract order ID from tx_ref: $tx_ref", 'error', 'payment-confirmation.log');
            return false;
        }
        
        // Get order details
        $orders_table = $wpdb->prefix . 'kwetupizza_orders';
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $orders_table WHERE id = %d",
            $order_id
        ));
        
        if (!$order) {
            kwetupizza_log("Order not found for ID: $order_id", 'error', 'payment-confirmation.log');
            return false;
        }
        
        // Process the successful payment
        kwetupizza_process_successful_payment($transaction_data);
        
        // Additional notification
        $customer_message = "✅ *Payment Confirmed!* ✅\n\n";
        $customer_message .= "Thank you for your payment for Order #$order_id.\n\n";
        $customer_message .= "Your order is now being prepared and will be delivered to you soon.\n";
        $customer_message .= "Delivery Address: {$order->delivery_address}\n\n";
        $customer_message .= "Amount Paid: " . kwetupizza_format_currency($order->total, $order->currency) . "\n\n";
        
        // Get estimated delivery time from order data or use default
        $estimated_time = $order->estimated_delivery_time ? $order->estimated_delivery_time : "30-45 minutes";
        $customer_message .= "Estimated Delivery Time: $estimated_time\n\n";
        $customer_message .= "We'll notify you when your order is out for delivery!\n";
        $customer_message .= "Thank you for choosing KwetuPizza! 🍕";
        
        // Send WhatsApp confirmation
        kwetupizza_send_whatsapp_message($order->customer_phone, $customer_message);
        
        // Send SMS as backup
        $sms_message = "KwetuPizza: Your payment for Order #$order_id has been confirmed! Your order is being prepared and will be delivered in approximately $estimated_time.";
        kwetupizza_send_nextsms($order->customer_phone, $sms_message);
        
        // Notify admin
        kwetupizza_notify_admin($order_id, true);
        
        // Add timeline event
        kwetupizza_add_order_timeline_event($order_id, 'payment_confirmed', 'Payment confirmed via ' . $transaction_data['payment_type']);
        
        kwetupizza_log("Payment confirmation process completed for order #$order_id", 'info', 'payment-confirmation.log');
        
        return true;
    }
}

/**
 * Log current context and input for debugging
 */
if (!function_exists('kwetupizza_log_context_and_input')) {
    function kwetupizza_log_context_and_input($from, $input) {
        $log_file = plugin_dir_path(dirname(__FILE__)) . 'includes/kwetupizza-debug.log';
        $context = kwetupizza_get_conversation_context($from);

        $log_content = "Current Context for user [$from]:\n";
        $log_content .= print_r($context, true);
        $log_content .= "Received Input: $input\n\n";

        file_put_contents($log_file, $log_content, FILE_APPEND);
        
        // Log to error log as well for easy access
        error_log($log_content);
    }
}

/**
 * Log and handle Flutterwave payment webhook (backup handler)
 */
if (!function_exists('log_flutterwave_payment_webhook')) {
    function log_flutterwave_payment_webhook(WP_REST_Request $request) {
        $webhook_data = json_decode($request->get_body(), true);
        
        if (!empty($webhook_data)) {
            if (isset($webhook_data['event']) && $webhook_data['event'] === 'charge.completed') {
                $status = $webhook_data['data']['status'];
                $transaction_id = $webhook_data['data']['id'];
                $tx_ref = $webhook_data['data']['tx_ref'];
                $phone_number = $webhook_data['data']['customer']['phone_number'];
                $delivery_address = $webhook_data['meta']['delivery_address'];

                if ($status === 'successful') {
                    $verification_result = kwetupizza_confirm_payment_and_notify($transaction_id);

                    if ($verification_result) {
                        // Extract the order_id from tx_ref (format: order_TIMESTAMP)
                        preg_match('/order_(\d+)/', $tx_ref, $matches);
                        $order_id = isset($matches[1]) ? $matches[1] : null;
                        
                        if ($order_id) {
                            // Use the new notification function for both WhatsApp and SMS
                            kwetupizza_notify_customer($order_id, 'payment_confirmed', 
                                "Your delicious pizza is on the way to $delivery_address!");
                        }
                        
                        return new WP_REST_Response('Payment processed successfully', 200);
                    } else {
                        return new WP_REST_Response('Payment verification failed', 400);
                    }
                } elseif ($status === 'failed') {
                    // Extract the order_id from tx_ref
                    preg_match('/order_(\d+)/', $tx_ref, $matches);
                    $order_id = isset($matches[1]) ? $matches[1] : null;
                    
                    // Extract failure reason if available
                    $failure_reason = '';
                    if (isset($webhook_data['data']['processor_response'])) {
                        $failure_reason = $webhook_data['data']['processor_response'];
                    } elseif (isset($webhook_data['data']['gateway_response'])) {
                        $failure_reason = $webhook_data['data']['gateway_response'];
                    }
                    
                    if ($order_id) {
                        // Use handle_failed_payment for consistent handling
                        kwetupizza_handle_failed_payment($tx_ref, $failure_reason);
                    }
                    
                    return new WP_REST_Response('Payment failed', 400);
                }
            }
        }

        return new WP_REST_Response('Invalid data received', 400);
    }
}

/**
 * Handle WhatsApp webhook messages
 */
if (!function_exists('kwetupizza_handle_whatsapp_messages')) {
    function kwetupizza_handle_whatsapp_messages($request) {
        $webhook_data = $request->get_json_params();

        // Log the incoming data for debugging
        $log_file = plugin_dir_path(dirname(__FILE__)) . 'includes/whatsapp-webhook.log';
        file_put_contents($log_file, "WhatsApp Webhook Data: " . print_r($webhook_data, true) . PHP_EOL, FILE_APPEND);

        if (isset($webhook_data['entry'][0]['changes'][0]['value']['messages'][0])) {
            $message_data = $webhook_data['entry'][0]['changes'][0]['value']['messages'][0];
            
            // Check if 'from' exists before using it
            if (isset($message_data['from'])) {
                $from = $message_data['from'];

                // Check if text body exists
                if (isset($message_data['text']['body'])) {
                    $message = trim($message_data['text']['body']);
                    kwetupizza_handle_whatsapp_message($from, $message);
                    return new WP_REST_Response('Message processed', 200);
                }
            }

        } elseif (isset($webhook_data['entry'][0]['changes'][0]['value']['statuses'][0])) {
            error_log('Received a message status update');
            return new WP_REST_Response('Status update received', 200);
        } else {
            error_log('WhatsApp message structure not as expected.');
            return new WP_REST_Response('No valid message or status found', 400);
        }

        return new WP_REST_Response('Invalid data received', 400);
    }
}

/**
 * Adds an event to the order timeline
 * 
 * @param int $order_id The ID of the order
 * @param string $event_type The type of event (e.g. 'payment_confirmed', 'order_delivered')
 * @param string $description Description of the event
 * @return bool Whether the event was added successfully
 */
if (!function_exists('kwetupizza_add_order_timeline_event')) {
    function kwetupizza_add_order_timeline_event($order_id, $event_type, $description) {
        global $wpdb;
        $timeline_table = $wpdb->prefix . 'kwetupizza_order_timeline';
        
        // Insert the timeline event
        $result = $wpdb->insert(
            $timeline_table,
            array(
                'order_id' => $order_id,
                'event_type' => $event_type,
                'description' => $description,
                'created_at' => current_time('mysql')
            )
        );
        
        if ($result) {
            kwetupizza_log("Added timeline event '$event_type' for order #$order_id", 'info');
            return true;
        } else {
            kwetupizza_log("Failed to add timeline event for order #$order_id: " . $wpdb->last_error, 'error');
            return false;
        }
    }
}

/**
 * Check and report the status of a customer's most recent order
 * 
 * @param string $from The customer's phone number
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
        
        // Add estimated delivery info if the order is not yet delivered
        if ($order->status != 'delivered' && $order->status != 'cancelled') {
            $message .= "\n⏰ *Estimated Delivery:* ";
            
            // Calculate estimated delivery time based on order creation
            $order_time = strtotime($order->created_at);
            $current_time = time();
            $time_difference = $current_time - $order_time;
            
            if ($order->status == 'processing' || $order->status == 'preparing') {
                // Still in kitchen - estimate 45 minutes from order time
                $delivery_estimate = $order_time + (45 * 60);
                if ($current_time > $delivery_estimate) {
                    $message .= "Your order is taking longer than expected. We're working on it!";
                } else {
                    $minutes_remaining = ceil(($delivery_estimate - $current_time) / 60);
                    $message .= "Approximately $minutes_remaining minutes";
                }
            } elseif ($order->status == 'out_for_delivery') {
                // Out for delivery - estimate 15 minutes
                $message .= "Your order is on its way! Approximately 10-15 minutes.";
            }
        }
        
        // Add a note about contacting for issues
        $message .= "\n\nThank you for your patience! If you have any questions, please reply with 'help'.";
        
        // Send the status message
        kwetupizza_send_whatsapp_message($from, $message);
    }
}

/**
 * Get an appropriate icon for a timeline event
 * 
 * @param string $event_type The type of event
 * @return string An emoji icon representing the event
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

/**
 * Send help information to the customer
 * 
 * @param string $from The customer's phone number
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
        $message .= "If you encounter payment problems, please try again or contact our customer support at " . get_option('kwetupizza_support_phone', '+255xxxxxxxxx') . "\n\n";
        
        $message .= "*Business Hours:*\n";
        $message .= "We're open from 10:00 AM to 10:00 PM, every day\n\n";
        
        $message .= "Thank you for choosing KwetuPizza! 🍕";
        
        kwetupizza_send_whatsapp_message($from, $message);
    }
}

/**
 * Send default message when input is not understood
 */
if (!function_exists('kwetupizza_send_default_message')) {
    function kwetupizza_send_default_message($from) {
        kwetupizza_send_whatsapp_message($from, "Sorry, I didn't understand that. Type 'menu' to see available options.");
    }
}

/**
 * Get conversation context
 */
if (!function_exists('kwetupizza_get_conversation_context')) {
    function kwetupizza_get_conversation_context($from) {
        $context = get_transient("kwetupizza_whatsapp_context_$from");
        return $context ? $context : [];
    }
}

/**
 * Set conversation context
 */
if (!function_exists('kwetupizza_set_conversation_context')) {
    function kwetupizza_set_conversation_context($from, $context) {
        // Set context with 5 minutes expiry for auto-reset chat feature
        set_transient("kwetupizza_whatsapp_context_$from", $context, 60 * 5); // 5 minutes expiry
        
        // Also update the last activity timestamp
        $context['last_activity'] = time();
        
        // Log the context change for debugging
        kwetupizza_log("Set context for $from: " . print_r($context, true), 'debug', 'context.log');
    }
}

/**
 * Create sample delivery zones if none exist
 */
if (!function_exists('kwetupizza_create_sample_delivery_zones')) {
    function kwetupizza_create_sample_delivery_zones() {
        global $wpdb;
        $zones_table = $wpdb->prefix . 'kwetupizza_delivery_zones';
        
        // Check if any zones exist
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $zones_table");
        
        if ($count > 0) {
            return; // Zones already exist, don't create samples
        }
        
        // Sample delivery zones data
        $sample_zones = array(
            array(
                'zone_name' => 'City Center',
                'description' => 'Downtown and central business district',
                'coordinates' => '[-6.8123,39.2891],[-6.8156,39.2982],[-6.8230,39.2956],[-6.8209,39.2874]',
                'delivery_fee' => 2000,
                'min_delivery_time' => 15,
                'max_delivery_time' => 30
            ),
            array(
                'zone_name' => 'Northern Suburbs',
                'description' => 'Residential areas to the north of the city',
                'coordinates' => '[-6.7923,39.2791],[-6.7856,39.2882],[-6.7930,39.2956],[-6.8009,39.2774]',
                'delivery_fee' => 3000,
                'min_delivery_time' => 25,
                'max_delivery_time' => 45
            ),
            array(
                'zone_name' => 'Eastern District',
                'description' => 'Commercial and residential areas to the east',
                'coordinates' => '[-6.8223,39.3091],[-6.8156,39.3182],[-6.8230,39.3256],[-6.8309,39.3074]',
                'delivery_fee' => 4000,
                'min_delivery_time' => 30,
                'max_delivery_time' => 50
            ),
            array(
                'zone_name' => 'Southern Beach Area',
                'description' => 'Tourist and beach areas to the south',
                'coordinates' => '[-6.8523,39.2791],[-6.8456,39.2882],[-6.8530,39.2956],[-6.8609,39.2774]',
                'delivery_fee' => 5000,
                'min_delivery_time' => 35,
                'max_delivery_time' => 60
            )
        );
        
        // Insert sample zones
        foreach ($sample_zones as $zone) {
            $wpdb->insert(
                $zones_table,
                array(
                    'zone_name' => $zone['zone_name'],
                    'description' => $zone['description'],
                    'coordinates' => $zone['coordinates'],
                    'delivery_fee' => $zone['delivery_fee'],
                    'min_delivery_time' => $zone['min_delivery_time'],
                    'max_delivery_time' => $zone['max_delivery_time'],
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                )
            );
        }
        
        kwetupizza_log('Created sample delivery zones', 'info');
    }
}

/**
 * Initialize sample data for testing
 */
if (!function_exists('kwetupizza_init_sample_data_ajax')) {
    function kwetupizza_init_sample_data_ajax() {
        // Check nonce for security
        check_ajax_referer('kwetupizza-nonce', 'nonce');
        
        // Check if user has permission
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        // Create sample delivery zones
        kwetupizza_create_sample_delivery_zones();
        
        // Respond with success message
        wp_send_json_success('Sample data initialized successfully.');
    }
}
add_action('wp_ajax_kwetupizza_init_sample_data', 'kwetupizza_init_sample_data_ajax');

// Update register_ajax_handlers function to include new handlers
if (!function_exists('kwetupizza_register_ajax_handlers')) {
    function kwetupizza_register_ajax_handlers() {
        add_action('wp_ajax_kwetupizza_test_whatsapp', 'kwetupizza_test_whatsapp_ajax');
        add_action('wp_ajax_kwetupizza_test_sms', 'kwetupizza_test_sms_ajax');
        add_action('wp_ajax_kwetupizza_init_sample_data', 'kwetupizza_init_sample_data_ajax');
    }
}

/**
 * Send greeting message
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
 * Notify customer about order status changes
 */
if (!function_exists('kwetupizza_notify_customer')) {
    function kwetupizza_notify_customer($order_id, $status, $additional_message = '') {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'kwetupizza_orders';
        
        // Log that this function is being called
        kwetupizza_log("Starting notification for order #$order_id with status: $status", 'info', 'notifications.log');
        
        $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM $orders_table WHERE id = %d", $order_id));
        
        if (!$order) {
            kwetupizza_log("Failed to find order #$order_id for notification", 'error', 'notifications.log');
            return false;
        }
        
        // Get user and ensure it exists in the database
        $user = kwetupizza_get_or_create_user($order->customer_phone);
        $name = $user->name;
        
        // Get first name for more casual communication
        $name_parts = explode(' ', $name);
        $first_name = $name_parts[0];
        
        // Don't use Customer- prefix in messages
        if (strpos($first_name, 'Customer-') === 0) {
            $greeting = "";
        } else {
            $greeting = "Hi $first_name! ";
        }
        
        kwetupizza_log("Found order #$order_id for: {$order->customer_name}, phone: {$order->customer_phone}", 'info', 'notifications.log');
        
        $status_messages = array(
            'processing' => "{$greeting}Your order #$order_id is now being processed. We'll update you when it's ready for delivery.",
            'preparing' => "{$greeting}Your order #$order_id is being prepared in our kitchen. It will be ready soon!",
            'ready_for_delivery' => "{$greeting}Good news! Your order #$order_id is ready and out for delivery. It will arrive in approximately 15-30 minutes.",
            'delivered' => "{$greeting}Your order #$order_id has been delivered. Enjoy your meal! Thank you for choosing Kwetu Pizza.",
            'cancelled' => "{$greeting}Your order #$order_id has been cancelled. Please contact us if you have any questions.",
            'payment_confirmed' => "{$greeting}Payment confirmed for Order #$order_id! Your pizza is being prepared and will be delivered to you soon.",
            'payment_failed' => "❌ {$greeting}Payment for Order #$order_id has failed. This could be due to insufficient funds, network issues, or a declined transaction. You can retry payment from your account or contact our support at " . get_option('kwetupizza_support_phone', '+255000000000') . " for assistance.",
            'dispatched' => "{$greeting}Great news! Your order #$order_id has been dispatched and is on the way to you. Expect delivery soon!"
        );
        
        $message = isset($status_messages[$status]) ? $status_messages[$status] : "{$greeting}Your order #$order_id status has been updated to: $status";
        
        if (!empty($additional_message)) {
            $message .= "\n\n" . $additional_message;
        }
        
        kwetupizza_log("Preparing to send notification message: " . substr($message, 0, 100) . "...", 'info', 'notifications.log');
        
        // Send WhatsApp notification - no need to further personalize as we did it already
        $whatsapp_sent = kwetupizza_send_whatsapp_message($order->customer_phone, $message);
        
        if ($whatsapp_sent) {
            kwetupizza_log("WhatsApp message sent successfully to {$order->customer_phone}", 'info', 'notifications.log');
        } else {
            kwetupizza_log("Failed to send WhatsApp message to {$order->customer_phone}", 'error', 'notifications.log');
        }
        
        // Send SMS notification
        $sms_sent = kwetupizza_send_nextsms($order->customer_phone, $message);
        
        if ($sms_sent) {
            kwetupizza_log("SMS sent successfully to {$order->customer_phone}", 'info', 'notifications.log');
        } else {
            kwetupizza_log("Failed to send SMS to {$order->customer_phone}", 'error', 'notifications.log');
        }
        
        return ($whatsapp_sent || $sms_sent);
    }
}

/**
 * Update order status and send appropriate notifications
 */
if (!function_exists('kwetupizza_update_order_status')) {
    function kwetupizza_update_order_status($order_id, $new_status, $admin_notes = '') {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'kwetupizza_orders';
        
        // Get current order data
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $orders_table WHERE id = %d",
            $order_id
        ));
        
        if (!$order) {
            kwetupizza_log("Failed to update status for order ID: $order_id - Order not found", 'error');
            return false;
        }
        
        // Update order status
        $wpdb->update(
            $orders_table,
            [
                'status' => $new_status,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $order_id]
        );
        
        // Add timeline event
        $event_description = "Order status updated to: $new_status";
        if (!empty($admin_notes)) {
            $event_description .= " - Note: $admin_notes";
        }
        kwetupizza_add_order_timeline_event($order_id, $new_status, $event_description);
        
        // Send notifications based on the new status
        kwetupizza_notify_customer($order_id, $new_status);
        
        // Log the status change
        kwetupizza_log("Order #$order_id status updated to $new_status", 'info');
        
        return true;
    }
}

/**
 * Send order dispatch notification to customer
 */
if (!function_exists('kwetupizza_notify_order_dispatched')) {
    function kwetupizza_notify_order_dispatched($order_id, $estimated_delivery_time = '') {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'kwetupizza_orders';
        
        // Get order data
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT customer_name, customer_phone, delivery_address FROM $orders_table WHERE id = %d",
            $order_id
        ));
        
        if (!$order) {
            kwetupizza_log("Failed to send dispatch notification for order ID: $order_id - Order not found", 'error');
            return false;
        }
        
        // Update the order status to dispatched
        kwetupizza_update_order_status($order_id, 'dispatched');
        
        // Format the delivery time message
        $delivery_time_msg = !empty($estimated_delivery_time) ? 
            "Estimated delivery time: $estimated_delivery_time minutes." : 
            "It will be delivered shortly.";
        
        // Craft the WhatsApp message
        $message = "🛵 *Order #$order_id Dispatched!* 🛵\n\n";
        $message .= "Hello {$order->customer_name},\n\n";
        $message .= "Great news! Your pizza is on its way to you! $delivery_time_msg\n\n";
        $message .= "🏠 *Delivery Address*:\n{$order->delivery_address}\n\n";
        $message .= "Our delivery partner will call you when they arrive.\n\n";
        $message .= "Thank you for choosing KwetuPizza! 🍕";
        
        // Send the notification
        $whatsapp_result = kwetupizza_send_whatsapp_message($order->customer_phone, $message);
        
        // Send SMS as backup
        $sms_message = "KwetuPizza: Your order #$order_id has been dispatched! $delivery_time_msg Thank you for choosing us!";
        $sms_result = kwetupizza_send_nextsms($order->customer_phone, $sms_message);
        
        // Log the notification attempt
        if ($whatsapp_result || $sms_result) {
            kwetupizza_log("Dispatch notification sent for order #$order_id", 'info');
            return true;
        } else {
            kwetupizza_log("Failed to send dispatch notification for order #$order_id", 'error');
            return false;
        }
    }
}

/**
 * Send order delivered notification to customer
 */
if (!function_exists('kwetupizza_notify_order_delivered')) {
    function kwetupizza_notify_order_delivered($order_id) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'kwetupizza_orders';
        
        // Get order data
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT customer_name, customer_phone FROM $orders_table WHERE id = %d",
            $order_id
        ));
        
        if (!$order) {
            kwetupizza_log("Failed to send delivery notification for order ID: $order_id - Order not found", 'error');
            return false;
        }
        
        // Update the order status to delivered
        kwetupizza_update_order_status($order_id, 'delivered');
        
        // Add loyalty points for completed order
        kwetupizza_add_loyalty_points($order_id);
        
        // Craft the WhatsApp message
        $message = "✅ *Order #$order_id Delivered!* 🎉\n\n";
        $message .= "Hello {$order->customer_name},\n\n";
        $message .= "Your order has been delivered. We hope you enjoy your meal!\n\n";
        $message .= "We've added loyalty points to your account for this purchase.\n\n";
        $message .= "You'll receive a message shortly to confirm your delivery and later to rate your experience.\n\n";
        $message .= "Thank you for choosing KwetuPizza! 🍕";
        
        // Send the notification
        $whatsapp_result = kwetupizza_send_whatsapp_message($order->customer_phone, $message);
        
        // Send SMS as backup
        $sms_message = "KwetuPizza: Your order #$order_id has been delivered! Thank you for choosing us!";
        $sms_result = kwetupizza_send_nextsms($order->customer_phone, $sms_message);
        
        // Send delivery confirmation request after 5 minutes
        wp_schedule_single_event(time() + (5 * MINUTE_IN_SECONDS), 'kwetupizza_send_delivery_confirmation', [$order_id]);
        
        // Log the notification attempt
        if ($whatsapp_result || $sms_result) {
            kwetupizza_log("Delivery notification sent for order #$order_id", 'info');
            return true;
        } else {
            kwetupizza_log("Failed to send delivery notification for order #$order_id", 'error');
            return false;
        }
    }
}

/**
 * Streamlined order process to reduce friction
 */
if (!function_exists('kwetupizza_process_streamlined_order')) {
    function kwetupizza_process_streamlined_order($customer_phone, $order_data) {
        global $wpdb;
        
        // Validate required data
        if (empty($order_data['items']) || !is_array($order_data['items'])) {
            kwetupizza_log("Invalid order items data for streamlined order", 'error');
            return [
                'success' => false,
                'message' => 'Please provide valid order items'
            ];
        }
        
        if (empty($order_data['delivery_address'])) {
            kwetupizza_log("Missing delivery address for streamlined order", 'error');
            return [
                'success' => false,
                'message' => 'Please provide a delivery address'
            ];
        }
        
        // Format cart items from order data
        $cart = [];
        $subtotal = 0;
        $items_text = "";
        
        foreach ($order_data['items'] as $item) {
            // Verify the product exists
            $product = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}kwetupizza_products WHERE id = %d",
                $item['product_id']
            ));
            
            if (!$product) {
                continue; // Skip invalid products
            }
            
            $item_total = $product->price * $item['quantity'];
            $subtotal += $item_total;
            
            $cart[] = [
                'product_id' => $product->id,
                'name' => $product->product_name,
                'price' => $product->price,
                'quantity' => $item['quantity'],
                'total' => $item_total
            ];
            
            $items_text .= "{$product->product_name} x {$item['quantity']} = " . 
                kwetupizza_format_currency($item_total) . "\n";
        }
        
        if (empty($cart)) {
            kwetupizza_log("No valid products found for streamlined order", 'error');
            return [
                'success' => false,
                'message' => 'No valid products found in your order'
            ];
        }
        
        // Calculate delivery fee based on zone if provided
        $delivery_fee = 0;
        if (!empty($order_data['delivery_zone_id'])) {
            $zone = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}kwetupizza_delivery_zones WHERE id = %d",
                $order_data['delivery_zone_id']
            ));
            
            if ($zone) {
                $delivery_fee = $zone->delivery_fee;
            }
        }
        
        // Calculate total
        $total = $subtotal + $delivery_fee;
        
        // Prepare context data
        $context = [
            'customer_name' => isset($order_data['customer_name']) ? $order_data['customer_name'] : '',
            'delivery_zone_id' => isset($order_data['delivery_zone_id']) ? $order_data['delivery_zone_id'] : 0,
            'delivery_fee' => $delivery_fee,
            'payment_provider' => isset($order_data['payment_method']) ? $order_data['payment_method'] : 'MPESA'
        ];
        
        // Save order to database
        $order_id = kwetupizza_save_order_to_db($customer_phone, $cart, $order_data['delivery_address'], $total, $context);
        
        if (!$order_id) {
            kwetupizza_log("Failed to save streamlined order to database", 'error');
            return [
                'success' => false,
                'message' => 'Error processing your order'
            ];
        }
        
        // Process payment if payment details are provided
        if (!empty($order_data['payment_phone'])) {
            // Create a unique transaction reference
            $tx_ref = 'order-' . $order_id . '-' . time();
            
            // Update transaction with tx_ref
            $wpdb->update(
                $wpdb->prefix . 'kwetupizza_transactions',
                ['tx_ref' => $tx_ref],
                ['order_id' => $order_id]
            );
            
            // Notify admin of new order
            kwetupizza_notify_admin_of_order($order_id, [
                'customer_name' => $context['customer_name'],
                'customer_phone' => $customer_phone,
                'items' => $items_text,
                'delivery_address' => $order_data['delivery_address'],
                'total' => kwetupizza_format_currency($total)
            ]);
            
            // Add order to timeline
            kwetupizza_add_order_timeline_event($order_id, 'order_placed', 'Order placed, awaiting payment');
            
            // If direct payment processing is requested
            if (isset($order_data['process_payment']) && $order_data['process_payment'] === true) {
                // Get API key
                $flw_secret_key = get_option('kwetupizza_flw_secret_key');
                if (empty($flw_secret_key)) {
                    kwetupizza_log("Missing Flutterwave secret key for streamlined order", 'error');
                    return [
                        'success' => true,
                        'order_id' => $order_id,
                        'payment_status' => 'not_initiated',
                        'message' => 'Order created, but payment not initiated due to configuration issue'
                    ];
                }
                
                // Prepare payment payload
                $payload = [
                    'tx_ref' => $tx_ref,
                    'amount' => $total,
                    'currency' => 'TZS',
                    'network' => strtoupper($context['payment_provider']),
                    'email' => kwetupizza_get_customer_email($customer_phone),
                    'phone_number' => $order_data['payment_phone'],
                    'fullname' => $context['customer_name'],
                    'redirect_url' => kwetupizza_get_callback_url('flutterwave'),
                    'meta' => [
                        'order_id' => $order_id,
                        'customer_phone' => $customer_phone
                    ]
                ];
                
                // Make API request to Flutterwave
                $response = wp_remote_post('https://api.flutterwave.com/v3/charges?type=mobile_money_tanzania', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $flw_secret_key,
                        'Content-Type' => 'application/json'
                    ],
                    'body' => json_encode($payload)
                ]);
                
                if (is_wp_error($response)) {
                    $error_message = $response->get_error_message();
                    kwetupizza_log("Flutterwave API request failed: $error_message", 'error');
                    return [
                        'success' => true,
                        'order_id' => $order_id,
                        'payment_status' => 'failed',
                        'message' => 'Order created, but payment could not be initiated'
                    ];
                }
                
                $response_body = wp_remote_retrieve_body($response);
                $result = json_decode($response_body, true);
                
                if (isset($result['status']) && $result['status'] === 'success') {
                    kwetupizza_update_transaction_reference($order_id, $tx_ref, $result['data']['id']);
                    
                    // Send confirmation message
                    $message = "🍕 *Your Order is Being Processed!* 🍕\n\n";
                    $message .= "Order #$order_id has been created.\n\n";
                    $message .= "📱 Check your phone for a payment prompt from Flutterwave/Mpesa.\n";
                    $message .= "Please enter your PIN to complete the payment of " . kwetupizza_format_currency($total) . ".\n\n";
                    $message .= "Thank you for choosing KwetuPizza! 🍕";
                    
                    kwetupizza_send_whatsapp_message($customer_phone, $message);
                    
                    return [
                        'success' => true,
                        'order_id' => $order_id,
                        'payment_status' => 'initiated',
                        'message' => 'Order created and payment request sent'
                    ];
                } else {
                    $error_message = isset($result['message']) ? $result['message'] : 'Unknown error';
                    kwetupizza_log("Payment initiation failed: $error_message", 'error');
                    
                    return [
                        'success' => true,
                        'order_id' => $order_id,
                        'payment_status' => 'failed',
                        'error' => $error_message,
                        'message' => 'Order created, but payment could not be initiated'
                    ];
                }
            }
        }
        
        // Return success response without payment processing
        return [
            'success' => true,
            'order_id' => $order_id,
            'message' => 'Order created successfully'
        ];
    }
}

/**
 * Send delivery confirmation link to customer
 */
if (!function_exists('kwetupizza_send_delivery_confirmation_request')) {
    function kwetupizza_send_delivery_confirmation_request($order_id) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'kwetupizza_orders';
        
        // Get order data
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT customer_name, customer_phone, delivery_address FROM $orders_table WHERE id = %d",
            $order_id
        ));
        
        if (!$order) {
            kwetupizza_log("Failed to send delivery confirmation request for order ID: $order_id - Order not found", 'error');
            return false;
        }
        
        // Generate a unique confirmation token
        $confirmation_token = md5('kwetupizza_order_' . $order_id . time());
        
        // Store the token in the order
        $wpdb->update(
            $orders_table,
            [
                'confirmation_token' => $confirmation_token,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $order_id]
        );
        
        // Create confirmation link
        $confirmation_link = home_url('/confirm-delivery/?order=' . $order_id . '&token=' . $confirmation_token);
        
        // Craft the WhatsApp message
        $message = "🍕 *KwetuPizza Order Delivered* 🍕\n\n";
        $message .= "Hello {$order->customer_name},\n\n";
        $message .= "Your order #$order_id should have been delivered to you. Please confirm if you've received your order by clicking the link below:\n\n";
        $message .= "$confirmation_link\n\n";
        $message .= "Thank you for choosing KwetuPizza! 🍕";
        
        // Send the notification
        $whatsapp_result = kwetupizza_send_whatsapp_message($order->customer_phone, $message);
        
        // Send SMS as backup
        $sms_message = "KwetuPizza: Your order #$order_id should have been delivered. Please confirm: $confirmation_link";
        $sms_result = kwetupizza_send_nextsms($order->customer_phone, $sms_message);
        
        // Log the notification attempt
        if ($whatsapp_result || $sms_result) {
            kwetupizza_log("Delivery confirmation request sent for order #$order_id", 'info');
            
            // Schedule feedback request after 30 minutes
            wp_schedule_single_event(time() + (30 * MINUTE_IN_SECONDS), 'kwetupizza_send_customer_feedback_request', [$order_id]);
            
            return true;
        } else {
            kwetupizza_log("Failed to send delivery confirmation request for order #$order_id", 'error');
            return false;
        }
    }
}

/**
 * Handle delivery confirmation from customer
 */
if (!function_exists('kwetupizza_handle_delivery_confirmation')) {
    function kwetupizza_handle_delivery_confirmation($order_id, $token) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'kwetupizza_orders';
        
        // Verify the token
        $valid_token = $wpdb->get_var($wpdb->prepare(
            "SELECT confirmation_token FROM $orders_table WHERE id = %d",
            $order_id
        ));
        
        if (!$valid_token || $valid_token !== $token) {
            return [
                'success' => false,
                'message' => 'Invalid or expired confirmation link.'
            ];
        }
        
        // Update order status to confirmed_delivered
        $updated = $wpdb->update(
            $orders_table,
            [
                'status' => 'confirmed_delivered',
                'customer_confirmed_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ],
            ['id' => $order_id]
        );
        
        if ($updated) {
            // Add timeline event
            kwetupizza_add_order_timeline_event($order_id, 'customer_confirmed', 'Customer confirmed delivery receipt');
            
            return [
                'success' => true,
                'message' => 'Thank you for confirming your delivery! We hope you enjoy your meal.'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Unable to confirm delivery. Please try again or contact support.'
            ];
        }
    }
}

/**
 * Send customer feedback request after delivery
 */
if (!function_exists('kwetupizza_send_customer_feedback_request')) {
    function kwetupizza_send_customer_feedback_request($order_id) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'kwetupizza_orders';
        
        // Get order data
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT customer_name, customer_phone, status FROM $orders_table WHERE id = %d",
            $order_id
        ));
        
        if (!$order) {
            kwetupizza_log("Failed to send feedback request for order ID: $order_id - Order not found", 'error');
            return false;
        }
        
        // Only send if the order is delivered or confirmed_delivered
        if ($order->status !== 'delivered' && $order->status !== 'confirmed_delivered') {
            kwetupizza_log("Skipping feedback request for order #$order_id - Order status is {$order->status}", 'info');
            return false;
        }
        
        // Generate a unique feedback token
        $feedback_token = md5('kwetupizza_feedback_' . $order_id . time());
        
        // Store the token in the order
        $wpdb->update(
            $orders_table,
            [
                'feedback_token' => $feedback_token,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $order_id]
        );
        
        // Create feedback link
        $feedback_link = home_url('/order-feedback/?order=' . $order_id . '&token=' . $feedback_token);
        
        // Craft the WhatsApp message
        $message = "🌟 *How was your KwetuPizza experience?* 🌟\n\n";
        $message .= "Hello {$order->customer_name},\n\n";
        $message .= "We hope you enjoyed your meal! We'd love to hear your feedback about your recent order #$order_id.\n\n";
        $message .= "Please rate your experience by visiting:\n$feedback_link\n\n";
        $message .= "Your feedback helps us improve our service. Thank you for choosing KwetuPizza! 🍕";
        
        // Send the notification
        $whatsapp_result = kwetupizza_send_whatsapp_message($order->customer_phone, $message);
        
        // Send SMS as backup
        $sms_message = "KwetuPizza: How was your meal? Please rate your order #$order_id experience: $feedback_link";
        $sms_result = kwetupizza_send_nextsms($order->customer_phone, $sms_message);
        
        // Log the notification attempt
        if ($whatsapp_result || $sms_result) {
            kwetupizza_log("Feedback request sent for order #$order_id", 'info');
            return true;
        } else {
            kwetupizza_log("Failed to send feedback request for order #$order_id", 'error');
            return false;
        }
    }
}

/**
 * Save customer feedback
 */
if (!function_exists('kwetupizza_save_customer_feedback')) {
    function kwetupizza_save_customer_feedback($order_id, $token, $rating, $comments = '') {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'kwetupizza_orders';
        $feedback_table = $wpdb->prefix . 'kwetupizza_feedback';
        
        // Verify the token
        $valid_token = $wpdb->get_var($wpdb->prepare(
            "SELECT feedback_token FROM $orders_table WHERE id = %d",
            $order_id
        ));
        
        if (!$valid_token || $valid_token !== $token) {
            return [
                'success' => false,
                'message' => 'Invalid or expired feedback link.'
            ];
        }
        
        // Save the feedback
        $result = $wpdb->insert(
            $feedback_table,
            [
                'order_id' => $order_id,
                'rating' => $rating,
                'comments' => $comments,
                'created_at' => current_time('mysql')
            ]
        );
        
        if ($result) {
            // Update order status to include feedback
            $wpdb->update(
                $orders_table,
                [
                    'has_feedback' => 1,
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $order_id]
            );
            
            // Add timeline event
            kwetupizza_add_order_timeline_event($order_id, 'customer_feedback', "Customer provided feedback (Rating: $rating/5)");
            
            return [
                'success' => true,
                'message' => 'Thank you for your feedback! We appreciate your input.'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Unable to save your feedback. Please try again or contact support.'
            ];
        }
    }
}

// Register the hook for sending feedback requests
add_action('kwetupizza_send_customer_feedback_request', 'kwetupizza_send_customer_feedback_request');

// Add a hook for sending delivery confirmation
add_action('kwetupizza_send_delivery_confirmation', 'kwetupizza_send_delivery_confirmation_request');

/**
 * Get user by phone number or create a new one if doesn't exist
 */
if (!function_exists('kwetupizza_get_or_create_user')) {
    function kwetupizza_get_or_create_user($phone) {
        global $wpdb;
        $users_table = $wpdb->prefix . 'kwetupizza_users';
        
        // Sanitize phone number
        $phone = kwetupizza_sanitize_phone($phone);
        
        // Check if user exists
        $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $users_table WHERE phone = %s", $phone));
        
        // If user exists, return user data
        if ($user) {
            kwetupizza_log("Existing user found for phone: $phone, Name: {$user->name}", 'info');
            return $user;
        }
        
        // User doesn't exist, create a new one with default name
        $default_name = "Customer-" . substr($phone, -5); // Last 5 digits of phone number
        
        $wpdb->insert(
            $users_table,
            array(
                'name' => $default_name,
                'email' => '',
                'phone' => $phone,
                'role' => 'customer',
                'state' => 'greeting',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            )
        );
        
        if ($wpdb->insert_id) {
            kwetupizza_log("New user created for phone: $phone with name: $default_name", 'info');
            return $wpdb->get_row($wpdb->prepare("SELECT * FROM $users_table WHERE phone = %s", $phone));
        }
        
        // If insert failed, return a basic user object
        kwetupizza_log("Failed to create user record for phone: $phone", 'error');
        $fallback_user = new stdClass();
        $fallback_user->id = 0;
        $fallback_user->name = $default_name;
        $fallback_user->phone = $phone;
        $fallback_user->email = '';
        $fallback_user->role = 'customer';
        $fallback_user->state = 'greeting';
        
        return $fallback_user;
    }
}

/**
 * Personalize message with user's name
 */
if (!function_exists('kwetupizza_personalize_message')) {
    function kwetupizza_personalize_message($phone, $message) {
        // Get user
        $user = kwetupizza_get_or_create_user($phone);
        
        // Extract first name if full name is provided
        $name_parts = explode(' ', $user->name);
        $first_name = $name_parts[0];
        
        // If default customer name, don't personalize
        if (strpos($first_name, 'Customer-') === 0) {
            return $message;
        }
        
        // Add personalization if message doesn't already include name
        if (strpos($message, $first_name) === false) {
            // If message starts with an emoji, preserve it
            if (preg_match('/^(\p{So}\s)/u', $message, $matches)) {
                $emoji = $matches[1];
                $message = preg_replace('/^(\p{So}\s)/u', '', $message, 1);
                return $emoji . "Hi " . $first_name . "! " . $message;
            }
        }
    }  
}

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

/**
 * KwetuPizza Core Functions
 * 
 * This file contains all the core functions for the KwetuPizza plugin.
 * Functions are organized by category for better maintainability.
 */

// ========================
// DATABASE FUNCTIONS
// ========================

/**
 * Create custom database tables
 */
if (!function_exists('kwetupizza_create_tables')) {
function kwetupizza_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    // Check if we need to upgrade the tables
    $current_db_version = get_option('kwetupizza_db_version', '0');
    
    // Include WordPress upgrade functions
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    // Users Table
    $users_table = $wpdb->prefix . 'kwetupizza_users';
    $sql = "CREATE TABLE $users_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        name varchar(100) NOT NULL,
        email varchar(100) DEFAULT '',
        phone varchar(20) NOT NULL,
        dob varchar(20) DEFAULT '',
        location varchar(255) DEFAULT '',
        role varchar(20) DEFAULT 'customer',
        state varchar(50) DEFAULT 'new',
        total_orders int(11) DEFAULT 0,
        total_spent decimal(10,2) DEFAULT 0.00,
        created_at datetime DEFAULT NULL,
        updated_at datetime DEFAULT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql);

    // Products Table
    $products_table = $wpdb->prefix . 'kwetupizza_products';
    $sql = "CREATE TABLE IF NOT EXISTS $products_table (
        id mediumint(9) UNSIGNED NOT NULL AUTO_INCREMENT,
        product_name varchar(255) NOT NULL,
        description text NOT NULL,
        price float NOT NULL,
        currency varchar(10) NOT NULL,
        category varchar(50) NOT NULL,
        image_url varchar(255) DEFAULT '',
        stock_status varchar(50) DEFAULT 'in_stock',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql);

    // Orders Table
    $orders_table = $wpdb->prefix . 'kwetupizza_orders';
    $sql = "CREATE TABLE IF NOT EXISTS $orders_table (
        id mediumint(9) UNSIGNED NOT NULL AUTO_INCREMENT,
        order_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        customer_name varchar(100) NOT NULL,
        customer_phone varchar(20) NOT NULL,
        customer_email varchar(100) DEFAULT '',
        delivery_address text NOT NULL,
        delivery_phone varchar(20) NOT NULL,
        status varchar(50) NOT NULL,
        total float NOT NULL,
        currency varchar(10) NOT NULL,
        delivery_notes text DEFAULT '',
        estimated_delivery_time datetime,
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql);

    // Order Items Table
    $order_items_table = $wpdb->prefix . 'kwetupizza_order_items';
    $sql = "CREATE TABLE IF NOT EXISTS $order_items_table (
        id mediumint(9) UNSIGNED NOT NULL AUTO_INCREMENT,
        order_id mediumint(9) UNSIGNED NOT NULL,
        product_id mediumint(9) UNSIGNED NOT NULL,
        quantity int NOT NULL,
        price float NOT NULL,
        special_instructions text DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        FOREIGN KEY (order_id) REFERENCES {$orders_table}(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES {$products_table}(id) ON DELETE CASCADE
    ) $charset_collate;";
    dbDelta($sql);

    // Transactions Table
    $transactions_table = $wpdb->prefix . 'kwetupizza_transactions';
    $sql = "CREATE TABLE IF NOT EXISTS $transactions_table (
        id mediumint(9) UNSIGNED NOT NULL AUTO_INCREMENT,
        order_id mediumint(9) UNSIGNED NOT NULL,
        transaction_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        payment_method varchar(50) NOT NULL,
        payment_status varchar(50) NOT NULL,
        amount float NOT NULL,
        currency varchar(10) NOT NULL,
        payment_provider varchar(50) NOT NULL,
        transaction_reference varchar(100) DEFAULT '',
        tx_ref varchar(100) DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY (tx_ref),
        FOREIGN KEY (order_id) REFERENCES {$wpdb->prefix}kwetupizza_orders(id) ON DELETE CASCADE
    ) $charset_collate;";
    dbDelta($sql);

    // Delivery Zones Table (New)
    $delivery_zones_table = $wpdb->prefix . 'kwetupizza_delivery_zones';
    $sql = "CREATE TABLE IF NOT EXISTS $delivery_zones_table (
        id mediumint(9) UNSIGNED NOT NULL AUTO_INCREMENT,
        zone_name varchar(100) NOT NULL,
        description text DEFAULT '',
        coordinates text NOT NULL,
        delivery_fee float NOT NULL,
        min_delivery_time int DEFAULT 30,
        max_delivery_time int DEFAULT 60,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql);

    // Customer Loyalty Table (New)
    $customer_loyalty_table = $wpdb->prefix . 'kwetupizza_customer_loyalty';
    $sql = "CREATE TABLE IF NOT EXISTS $customer_loyalty_table (
        id mediumint(9) UNSIGNED NOT NULL AUTO_INCREMENT,
        customer_phone varchar(20) NOT NULL,
        points int DEFAULT 0,
        total_orders int DEFAULT 0,
        total_spent float DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY (customer_phone)
    ) $charset_collate;";
    dbDelta($sql);
    }
}

// ========================
// ACTIVATION FUNCTIONS
// ========================

/**
 * Create required pages for the plugin
 */
if (!function_exists('kwetupizza_create_pages')) {
function kwetupizza_create_pages() {
    // Create Thank You page
        $thank_you_page = get_page_by_path('thank-you');
        if (empty($thank_you_page)) {
        wp_insert_post(array(
            'post_title' => 'Thank You',
            'post_content' => 'Thank you for your order! Your payment was successful.',
            'post_name' => 'thank-you',
            'post_status' => 'publish',
            'post_type' => 'page'
        ));
    }

    // Create Retry Payment page
        $retry_payment_page = get_page_by_path('retry-payment');
        if (empty($retry_payment_page)) {
        wp_insert_post(array(
            'post_title' => 'Retry Payment',
            'post_content' => 'It seems your payment has failed. Please retry the payment by clicking the link below.',
            'post_name' => 'retry-payment',
            'post_status' => 'publish',
            'post_type' => 'page'
        ));
    }

    // Create Order Tracking page (New)
        $order_tracking_page = get_page_by_path('order-tracking');
        if (empty($order_tracking_page)) {
        wp_insert_post(array(
            'post_title' => 'Order Tracking',
            'post_content' => '[kwetupizza_order_tracking]',
            'post_name' => 'order-tracking',
            'post_status' => 'publish',
            'post_type' => 'page'
        ));
    }

    // Create Menu page (New)
        $menu_page = get_page_by_path('pizza-menu');
        if (empty($menu_page)) {
        wp_insert_post(array(
            'post_title' => 'Our Menu',
            'post_content' => '[kwetupizza_menu]',
            'post_name' => 'pizza-menu',
            'post_status' => 'publish',
            'post_type' => 'page'
        ));
    }

    // Create Customer Account page (New)
        $account_page = get_page_by_path('customer-account');
        if (empty($account_page)) {
        wp_insert_post(array(
            'post_title' => 'My Account',
            'post_content' => '[kwetupizza_customer_account]',
            'post_name' => 'customer-account',
            'post_status' => 'publish',
            'post_type' => 'page'
        ));
    }
    
    // Create PayPal Checkout page
        $paypal_checkout_page = get_page_by_path('paypal-checkout');
        if (empty($paypal_checkout_page)) {
        wp_insert_post(array(
            'post_title' => 'PayPal Checkout',
            'post_content' => '[kwetupizza_paypal_checkout]',
            'post_name' => 'paypal-checkout',
            'post_status' => 'publish',
            'post_type' => 'page',
            'page_template' => 'paypal-checkout.php'
        ));
    }
}
}

// ========================
// WEBHOOK & API FUNCTIONS
// ========================

/**
 * Generate callback URLs for webhooks
 */
function kwetupizza_get_callback_url($service) {
    return esc_url(home_url('/wp-json/kwetupizza/v1/' . $service . '-webhook'));
}

/**
 * Verify a webhook signature from Flutterwave
 */
function kwetupizza_verify_flutterwave_signature($request) {
    $secret_hash = get_option('kwetupizza_flw_webhook_secret');
    $signature = $request->get_header('verif-hash');
    
    if (!$signature || $signature !== $secret_hash) {
        return false;
    }
    
    return true;
}

// ========================
// UTILITY FUNCTIONS
// ========================

/**
 * Sanitize and validate phone number
 */
function kwetupizza_sanitize_phone($phone) {
    // Remove any non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Ensure the number starts with country code
    if (substr($phone, 0, 3) !== '255') {
        // If number starts with 0, replace it with 255
        if (substr($phone, 0, 1) === '0') {
        $phone = '255' . substr($phone, 1);
        } else {
            // Otherwise assume it's a local number and add country code
            $phone = '255' . $phone;
        }
    }
    
    return $phone;
}

/**
 * Format currency values
 */
function kwetupizza_format_currency($amount, $currency = '') {
    if (empty($currency)) {
        $currency = get_option('kwetupizza_currency', 'TZS');
    }
    
    return number_format($amount, 2) . ' ' . $currency;
}

/**
 * Generate secure random string
 */
function kwetupizza_generate_random_string($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[wp_rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

/**
 * Log messages to a file
 */
function kwetupizza_log($message, $type = 'info', $file = 'kwetupizza-debug.log') {
    $log_file = plugin_dir_path(dirname(__FILE__)) . 'includes/' . $file;
    $timestamp = date('Y-m-d H:i:s');
    $formatted_message = "[$timestamp][$type] $message" . PHP_EOL;
    file_put_contents($log_file, $formatted_message, FILE_APPEND);
}

/**
 * Add security headers
 */
if (!function_exists('kwetupizza_add_security_headers')) {
    function kwetupizza_add_security_headers() {
        // Content Security Policy
        header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; style-src 'self' https://fonts.googleapis.com 'unsafe-inline'; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self' https://api.flutterwave.com https://graph.facebook.com;");
        
        // Prevent MIME type sniffing
        header("X-Content-Type-Options: nosniff");
        
        // Enable XSS protection
        header("X-XSS-Protection: 1; mode=block");
        
        // Prevent clickjacking
        header("X-Frame-Options: SAMEORIGIN");
        
        // Referrer Policy
        header("Referrer-Policy: strict-origin-when-cross-origin");
    }
}
add_action('send_headers', 'kwetupizza_add_security_headers');

// ========================
// NOTIFICATION FUNCTIONS
// ========================

/**
 * Send WhatsApp message
 */
function kwetupizza_send_whatsapp_message($phone, $message) {
    // Log the attempt for debugging
    kwetupizza_log("Attempting to send WhatsApp message to $phone", 'info', 'whatsapp.log');
    
    $token = get_option('kwetupizza_whatsapp_token');
    $phone_id = get_option('kwetupizza_whatsapp_phone_id');
    
    // Log configuration for troubleshooting
    kwetupizza_log("WhatsApp configuration - Phone ID: $phone_id, Token exists: " . (!empty($token) ? 'Yes' : 'No'), 'info', 'whatsapp.log');
    
    if (empty($token) || empty($phone_id)) {
        kwetupizza_log('WhatsApp API credentials not set or incomplete', 'error', 'whatsapp.log');
        return false;
    }
    
    // Sanitize phone number with enhanced validation
    $phone = kwetupizza_sanitize_phone($phone);
    
    // Log the sanitized phone number
    kwetupizza_log("Sanitized phone number: $phone", 'info', 'whatsapp.log');
    
    // Ensure the phone number starts with country code and has no leading '+'
    if (substr($phone, 0, 1) === '+') {
        $phone = substr($phone, 1);
        kwetupizza_log("Removed leading + from phone number: $phone", 'info', 'whatsapp.log');
    }
    
    // WhatsApp Cloud API endpoint
    $url = "https://graph.facebook.com/v17.0/{$phone_id}/messages";
    kwetupizza_log("Using WhatsApp endpoint: $url", 'info', 'whatsapp.log');

    // Setup the request payload
    $data = array(
        'messaging_product' => 'whatsapp',
        'recipient_type' => 'individual',
        'to' => $phone,
        'type' => 'text',
        'text' => array(
            'preview_url' => false,
            'body' => $message
        )
    );
    
    // Log the payload for debugging
    kwetupizza_log("WhatsApp payload: " . json_encode($data), 'info', 'whatsapp.log');
    
    // Send the request
    $response = wp_remote_post($url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode($data),
        'timeout' => 30
    ));

    // Check for errors
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        kwetupizza_log("WhatsApp API Error: $error_message", 'error', 'whatsapp.log');
        error_log("WhatsApp API Error: $error_message");
        return false;
    }
    
    $status_code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    // Log the response for debugging
    kwetupizza_log("WhatsApp API Response Code: $status_code", 'info', 'whatsapp.log');
    kwetupizza_log("WhatsApp API Response: " . print_r($body, true), 'info', 'whatsapp.log');
    
    // Check for successful response
    if (isset($body['messages']) && !empty($body['messages'])) {
        kwetupizza_log("WhatsApp message sent successfully to $phone", 'info', 'whatsapp.log');
        return true;
    } else {
        $error_detail = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown error';
        kwetupizza_log("WhatsApp message failed: $error_detail", 'error', 'whatsapp.log');
        return false;
    }
}

/**
 * Send SMS via NextSMS
 */
function kwetupizza_send_nextsms($phone, $message) {
    // Log the attempt to send an SMS
    kwetupizza_log("Attempting to send SMS to $phone", 'info', 'sms.log');
    
    $username = get_option('kwetupizza_nextsms_username');
    $password = get_option('kwetupizza_nextsms_password');
    $sender_id = get_option('kwetupizza_nextsms_sender_id', 'KwetuPizza');
    
    if (empty($username) || empty($password)) {
        kwetupizza_log('NextSMS credentials not set', 'error', 'sms.log');
        return false;
    }
    
    // Sanitize phone number
    $phone = kwetupizza_sanitize_phone($phone);
    
    // Ensure phone is in proper format (remove leading +)
    if (substr($phone, 0, 1) === '+') {
        $phone = substr($phone, 1);
    }
    
    // Verify required data is present
    if (empty($phone) || empty($message)) {
        kwetupizza_log('NextSMS Error: Missing required parameters (phone or message)', 'error', 'sms.log');
        return false;
    }
    
    // NextSMS API endpoint
    $url = 'https://messaging-service.co.tz/api/sms/v1/text/single';
    kwetupizza_log("Using NextSMS API endpoint: $url", 'info', 'sms.log');
    
    // Setup the request payload with all required fields
    $payload = array(
        'from' => $sender_id,
        'to' => $phone,
        'text' => $message
    );
    
    // Log the payload for debugging
    kwetupizza_log('NextSMS Payload: ' . print_r($payload, true), 'info', 'sms.log');
    
    // Log authentication info (without exposing the password)
    kwetupizza_log("Using NextSMS username: $username with sender ID: $sender_id", 'info', 'sms.log');
    
    // Send the request
    $response = wp_remote_post($url, array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode("$username:$password")
        ),
        'body' => json_encode($payload),
        'timeout' => 30
    ));
    
    // Check for errors
    if (is_wp_error($response)) {
        kwetupizza_log('NextSMS Error: ' . $response->get_error_message(), 'error', 'sms.log');
        return false;
    }
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    // Log the response for debugging
    kwetupizza_log('NextSMS Response: ' . print_r($body, true), 'info', 'sms.log');
    
    // Check for successful response
    if (isset($body['success']) && $body['success']) {
        kwetupizza_log("SMS sent to $phone successfully", 'info', 'sms.log');
        return true;
    } else {
        $error_message = isset($body['message']) ? $body['message'] : 'Unknown error';
        
        if (isset($body['errors']) && is_array($body['errors'])) {
            foreach ($body['errors'] as $field => $errors) {
                if (is_array($errors) && !empty($errors)) {
                    $error_message .= ' - ' . $field . ': ' . implode(', ', $errors);
                }
            }
        }
        
        kwetupizza_log("Failed to send SMS to $phone: $error_message", 'error', 'sms.log');
        return false;
    }
}

/**
 * Notify admin about new orders or payment status
 */
function kwetupizza_notify_admin($order_id, $success = true) {
    kwetupizza_log("Starting admin notification for order #$order_id", 'info', 'admin-notification.log');
    
    global $wpdb;
    $orders_table = $wpdb->prefix . 'kwetupizza_orders';
    $order_items_table = $wpdb->prefix . 'kwetupizza_order_items';
    
    $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM $orders_table WHERE id = %d", $order_id));
    
    if (!$order) {
        kwetupizza_log("Failed to find order #$order_id for admin notification", 'error', 'admin-notification.log');
        return false;
    }
    
    // Get order items
    $order_items = $wpdb->get_results($wpdb->prepare(
        "SELECT oi.*, p.product_name 
        FROM $order_items_table oi 
        JOIN {$wpdb->prefix}kwetupizza_products p ON oi.product_id = p.id 
        WHERE oi.order_id = %d", 
        $order_id
    ));
    
    $admin_phone = get_option('kwetupizza_admin_whatsapp');
    kwetupizza_log("Admin WhatsApp number from settings: $admin_phone", 'info', 'admin-notification.log');
    
    $status = $success ? 'successful' : 'failed';
    
    $message = $success ? "✅ PAYMENT CONFIRMED" : "❌ PAYMENT FAILED";
    $message .= "\n\n🍕 *Order #$order_id*\n";
    $message .= "👤 *Customer:* {$order->customer_name}\n";
    $message .= "📞 *Phone:* {$order->customer_phone}\n";
    $message .= "🏠 *Address:* {$order->delivery_address}\n";
    
    // Add order items
    $message .= "\n📋 *Order Details:*\n";
    if ($order_items) {
        foreach ($order_items as $item) {
            $message .= "• {$item->quantity}x {$item->product_name}\n";
        }
    }
    
    $message .= "\n💰 *Total:* " . kwetupizza_format_currency($order->total, $order->currency) . "\n";
    $message .= "💳 *Payment:* $status\n";
    $message .= "⏱️ *Time:* " . date('Y-m-d H:i:s');
    
    // Log message content for debugging
    kwetupizza_log("Prepared admin WhatsApp notification message: " . substr($message, 0, 100) . "...", 'info', 'admin-notification.log');
    
    $whatsapp_sent = false;
    if (!empty($admin_phone)) {
        kwetupizza_log("Attempting to send WhatsApp notification to admin at $admin_phone", 'info', 'admin-notification.log');
        $whatsapp_sent = kwetupizza_send_whatsapp_message($admin_phone, $message);
        if ($whatsapp_sent) {
            kwetupizza_log("Successfully sent WhatsApp notification to admin", 'info', 'admin-notification.log');
        } else {
            kwetupizza_log("Failed to send WhatsApp notification to admin", 'error', 'admin-notification.log');
        }
    } else {
        kwetupizza_log("No admin WhatsApp number configured, skipping WhatsApp notification", 'warning', 'admin-notification.log');
    }
    
    $admin_sms = get_option('kwetupizza_admin_sms');
    $sms_sent = false;
    if (!empty($admin_sms)) {
        // Simplified message for SMS due to length constraints
        $sms_message = "Order #$order_id: {$order->customer_name}, {$order->customer_phone}, " . 
                       kwetupizza_format_currency($order->total, $order->currency) . ". Payment: $status";
        kwetupizza_log("Attempting to send SMS notification to admin at $admin_sms", 'info', 'admin-notification.log');
        $sms_sent = kwetupizza_send_nextsms($admin_sms, $sms_message);
        if ($sms_sent) {
            kwetupizza_log("Successfully sent SMS notification to admin", 'info', 'admin-notification.log');
        } else {
            kwetupizza_log("Failed to send SMS notification to admin", 'error', 'admin-notification.log');
        }
    } else {
        kwetupizza_log("No admin SMS number configured, skipping SMS notification", 'warning', 'admin-notification.log');
    }
    
    kwetupizza_log("Admin notification process completed for order #$order_id", 'info', 'admin-notification.log');
    return ($whatsapp_sent || $sms_sent); // Return true if at least one notification was sent
}

/**
 * Notify admin of new orders via WhatsApp and SMS
 */
function kwetupizza_notify_admin_of_order($order_id, $order_details) {
    $admin_whatsapp = get_option('kwetupizza_admin_whatsapp');
    $admin_sms = get_option('kwetupizza_admin_sms');
    
    if (empty($admin_whatsapp) && empty($admin_sms)) {
        error_log('No admin notification numbers set');
        return false;
    }
    
    // Create notification message
    $message = "New Order #{$order_id} Received!\n\n";
    $message .= "Customer: {$order_details['customer_name']}\n";
    $message .= "Phone: {$order_details['customer_phone']}\n";
    $message .= "Amount: {$order_details['amount']} {$order_details['currency']}\n";
    $message .= "Items: {$order_details['items']}\n";
    $message .= "Delivery: {$order_details['delivery_address']}\n";
    $message .= "Time: " . current_time('mysql');
    
    $success = true;
    
    // Send WhatsApp notification
    if (!empty($admin_whatsapp)) {
        $whatsapp_sent = kwetupizza_send_whatsapp_message($admin_whatsapp, $message);
        if (!$whatsapp_sent) {
            error_log('Failed to send WhatsApp notification to admin');
            $success = false;
        }
    }
    
    // Send SMS notification
    if (!empty($admin_sms)) {
        // Create shorter SMS message for cost efficiency
        $sms_message = "New Order #{$order_id}. Customer: {$order_details['customer_name']}. Amount: {$order_details['amount']} {$order_details['currency']}. Check dashboard.";
        
        $sms_sent = kwetupizza_send_nextsms($admin_sms, $sms_message);
        if (!$sms_sent) {
            error_log('Failed to send SMS notification to admin');
            $success = false;
        }
    }
    
    return $success;
}

/**
 * Register AJAX handlers for WhatsApp and SMS
 */
function kwetupizza_register_ajax_handlers() {
    add_action('wp_ajax_kwetupizza_test_whatsapp', 'kwetupizza_test_whatsapp_ajax');
    add_action('wp_ajax_kwetupizza_test_sms', 'kwetupizza_test_sms_ajax');
    add_action('wp_ajax_kwetupizza_init_sample_data', 'kwetupizza_init_sample_data_ajax');
}
add_action('init', 'kwetupizza_register_ajax_handlers');

/**
 * AJAX handler for sending test WhatsApp message
 */
function kwetupizza_test_whatsapp_ajax() {
    check_ajax_referer('kwetupizza_test_whatsapp', 'nonce');
    
    $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : 'This is a test WhatsApp message from KwetuPizza';
    
    if (empty($phone)) {
        wp_send_json_error(['message' => 'Phone number is required']);
        return;
    }
    
    $result = kwetupizza_send_whatsapp_message($phone, $message);
    
    if ($result) {
        wp_send_json_success(['message' => 'WhatsApp message sent successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to send WhatsApp message. Please check your WhatsApp API settings.']);
    }
}

/**
 * AJAX handler for sending test SMS
 */
function kwetupizza_test_sms_ajax() {
    check_ajax_referer('kwetupizza_test_sms', 'nonce');
    
    $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
    $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : 'This is a test SMS from KwetuPizza';
    
    if (empty($phone)) {
        wp_send_json_error(['message' => 'Phone number is required']);
        return;
    }
    
    $result = kwetupizza_send_nextsms($phone, $message);
    
    if ($result) {
        wp_send_json_success(['message' => 'SMS sent successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to send SMS. Please check your NextSMS settings.']);
    }
}

/**
 * Send failed payment notification
 */

// ========================
// PAYMENT FUNCTIONS
// ========================

/**
 * Verify Flutterwave payment
 */
function kwetupizza_verify_payment($transaction_id) {
    $flw_secret_key = get_option('kwetupizza_flw_secret_key');
    
    if (empty($flw_secret_key)) {
        kwetupizza_log('Flutterwave secret key not configured', 'error');
        return false;
    }
    
    $url = "https://api.flutterwave.com/v3/transactions/{$transaction_id}/verify";
    
    $response = wp_remote_get($url, [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $flw_secret_key
        ]
    ]);
    
    if (is_wp_error($response)) {
        kwetupizza_log('Flutterwave verification error: ' . $response->get_error_message(), 'error');
        return false;
    }
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    if (isset($body['status']) && $body['status'] === 'success' && 
        isset($body['data']['status']) && $body['data']['status'] === 'successful') {
        return $body['data'];
    }
    
    return false;
}

/**
 * Process successful payment
 */
function kwetupizza_process_successful_payment($data) {
    global $wpdb;
    $transactions_table = $wpdb->prefix . 'kwetupizza_transactions';
    $orders_table = $wpdb->prefix . 'kwetupizza_orders';
    
    $tx_ref = $data['tx_ref'];
    
    // Extract order ID from tx_ref (format could be order_ID or order-ID or order-ID-TIMESTAMP)
    preg_match('/order[-_](\d+)/', $tx_ref, $matches);
    $order_id = isset($matches[1]) ? (int)$matches[1] : 0;
    
    // If the new format with timestamp is used
    if ($order_id === 0 && strpos($tx_ref, '-') !== false) {
        $parts = explode('-', $tx_ref);
        if (count($parts) >= 2 && $parts[0] === 'order') {
            $order_id = (int)$parts[1];
        }
    }
    
    if ($order_id === 0) {
        kwetupizza_log("Failed to extract order ID from tx_ref: $tx_ref", 'error');
        return false;
    }
    
    // Update transaction status
    $wpdb->update(
        $transactions_table,
        [
            'payment_status' => 'completed',
            'transaction_reference' => $data['id'],
            'updated_at' => current_time('mysql')
        ],
        ['order_id' => $order_id]
    );
    
    // Update order status
    $wpdb->update(
        $orders_table,
        [
            'status' => 'processing',
            'updated_at' => current_time('mysql')
        ],
        ['id' => $order_id]
    );
    
    // Add loyalty points (new feature)
    kwetupizza_add_loyalty_points($order_id);
    
    // Notify admin
    kwetupizza_notify_admin($order_id, true);
    
    // Notify customer via both WhatsApp and SMS
    kwetupizza_notify_customer($order_id, 'payment_confirmed');
    
    // Add timeline event
    kwetupizza_add_order_timeline_event($order_id, 'payment_confirmed', 'Payment confirmed');
    
    return true;
}

/**
 * Add loyalty points for customer
 */
function kwetupizza_add_loyalty_points($order_id) {
    global $wpdb;
    $orders_table = $wpdb->prefix . 'kwetupizza_orders';
    $loyalty_table = $wpdb->prefix . 'kwetupizza_customer_loyalty';
    
    $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM $orders_table WHERE id = %d", $order_id));
    
    if (!$order) {
        return false;
    }
    
    // Calculate points - 1 point for every 1000 TZS spent
    $points_earned = floor($order->total / 1000);
    
    // Check if customer already exists in loyalty program
    $customer = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $loyalty_table WHERE customer_phone = %s",
        $order->customer_phone
    ));
    
    if ($customer) {
        // Update existing customer
        $wpdb->update(
            $loyalty_table,
            [
                'points' => $customer->points + $points_earned,
                'total_orders' => $customer->total_orders + 1,
                'total_spent' => $customer->total_spent + $order->total,
                'updated_at' => current_time('mysql')
            ],
            ['customer_phone' => $order->customer_phone]
        );
    } else {
        // Add new customer to loyalty program
        $wpdb->insert(
            $loyalty_table,
            [
                'customer_phone' => $order->customer_phone,
                'points' => $points_earned,
                'total_orders' => 1,
                'total_spent' => $order->total,
                'created_at' => current_time('mysql')
            ]
        );
    }
    
    return true;
}

/**
 * Get customer email
 */
if (!function_exists('kwetupizza_get_customer_email')) {
    function kwetupizza_get_customer_email($phone) {
        global $wpdb;
        $users_table = $wpdb->prefix . 'kwetupizza_users';
        
        $email = $wpdb->get_var($wpdb->prepare("SELECT email FROM $users_table WHERE phone = %s", $phone));
        
        if ($email) {
            return $email;
        }
        
        // Generate a placeholder email if not found
        return 'customer_' . substr(md5($phone), 0, 8) . '@example.com';
    }
}

/**
 * Flutterwave webhook handler
 */
if (!function_exists('kwetupizza_flutterwave_webhook')) {
    function kwetupizza_flutterwave_webhook(WP_REST_Request $request) {
        // Log that webhook was triggered
        kwetupizza_log('Flutterwave webhook triggered', 'info', 'payment-webhook.log');
        
        // Verify webhook signature
        if (!kwetupizza_verify_flutterwave_signature($request)) {
            kwetupizza_log('Invalid webhook signature', 'error', 'payment-webhook.log');
            return new WP_REST_Response('Invalid signature', 401);
        }
        
        $webhook_data = json_decode($request->get_body(), true);
        kwetupizza_log('Webhook received: ' . print_r($webhook_data, true), 'info', 'payment-webhook.log');
        
        if (isset($webhook_data['event']) && $webhook_data['event'] === 'charge.completed') {
            $status = $webhook_data['data']['status'];
            $transaction_id = $webhook_data['data']['id'];
            $tx_ref = $webhook_data['data']['tx_ref'];
            
            kwetupizza_log("Processing payment with status: $status, tx_ref: $tx_ref", 'info', 'payment-webhook.log');
            
            if ($status === 'successful') {
                // Verify payment with Flutterwave API
                kwetupizza_log("Verifying payment with transaction ID: $transaction_id", 'info', 'payment-webhook.log');
                $verification_data = kwetupizza_verify_payment($transaction_id);
                
                if ($verification_data) {
                    kwetupizza_log("Payment verification successful, processing payment", 'info', 'payment-webhook.log');
                    $result = kwetupizza_process_successful_payment($verification_data);
                    
                    if ($result) {
                        kwetupizza_log("Payment processed successfully for tx_ref: $tx_ref", 'info', 'payment-webhook.log');
                    } else {
                        kwetupizza_log("Failed to process payment for tx_ref: $tx_ref", 'error', 'payment-webhook.log');
                    }
                    
                    return new WP_REST_Response('Payment processed successfully', 200);
                } else {
                    kwetupizza_log('Payment verification failed for transaction ID: ' . $transaction_id, 'error', 'payment-webhook.log');
                    return new WP_REST_Response('Payment verification failed', 400);
                }
            } elseif ($status === 'failed') {
                // Extract failure reason if available
                $failure_reason = '';
                if (isset($webhook_data['data']['processor_response'])) {
                    $failure_reason = $webhook_data['data']['processor_response'];
                } elseif (isset($webhook_data['data']['gateway_response'])) {
                    $failure_reason = $webhook_data['data']['gateway_response'];
                }
                
                kwetupizza_log("Processing failed payment for tx_ref: $tx_ref, reason: $failure_reason", 'info', 'payment-webhook.log');
                $result = kwetupizza_handle_failed_payment($tx_ref, $failure_reason);
                
                if ($result) {
                    kwetupizza_log("Failed payment handled successfully for tx_ref: $tx_ref", 'info', 'payment-webhook.log');
                } else {
                    kwetupizza_log("Error handling failed payment for tx_ref: $tx_ref", 'error', 'payment-webhook.log');
                }
                
                return new WP_REST_Response('Payment failed', 400);
            }
        } else {
            kwetupizza_log("Unsupported webhook event: " . (isset($webhook_data['event']) ? $webhook_data['event'] : 'unknown'), 'info', 'payment-webhook.log');
        }
        
        return new WP_REST_Response('Event not supported', 400);
    }
}

/**
 * Handle failed payment
 */
if (!function_exists('kwetupizza_handle_failed_payment')) {
    function kwetupizza_handle_failed_payment($tx_ref, $failure_reason = '') {
        global $wpdb;
        $transactions_table = $wpdb->prefix . 'kwetupizza_transactions';
        $orders_table = $wpdb->prefix . 'kwetupizza_orders';
        
        // Extract order ID from tx_ref (format could be order_ID or order-ID or order-ID-TIMESTAMP)
        preg_match('/order[-_](\d+)/', $tx_ref, $matches);
        $order_id = isset($matches[1]) ? (int)$matches[1] : 0;
        
        // If the new format with timestamp is used
        if ($order_id === 0 && strpos($tx_ref, '-') !== false) {
            $parts = explode('-', $tx_ref);
            if (count($parts) >= 2 && $parts[0] === 'order') {
                $order_id = (int)$parts[1];
            }
        }
        
        if ($order_id === 0) {
            kwetupizza_log("Failed to extract order ID from tx_ref: $tx_ref", 'error');
            return false;
        }
        
        // Get order details for better context
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $orders_table WHERE id = %d",
            $order_id
        ));
        
        if (!$order) {
            kwetupizza_log("Failed to find order for tx_ref: $tx_ref", 'error');
            return false;
        }
        
        // Update transaction status
        $wpdb->update(
            $transactions_table,
            [
                'payment_status' => 'failed',
                'updated_at' => current_time('mysql')
            ],
            ['order_id' => $order_id]
        );
        
        // Update order status
        $wpdb->update(
            $orders_table,
            [
                'status' => 'payment_failed',
                'updated_at' => current_time('mysql')
            ],
            ['id' => $order_id]
        );
        
        // Get retry payment link
        $retry_url = add_query_arg(
            array('order_id' => $order_id),
            get_permalink(get_page_by_path('retry-payment'))
        );
        
        // Add failure reason if available
        $failure_details = !empty($failure_reason) ? 
            "Reason: $failure_reason\n\n" : 
            "This may be due to insufficient funds, network issues, or incorrect payment details.\n\n";
        
        // Notify customer via both WhatsApp and SMS
        $additional_message = $failure_details . "You can retry your payment using this link: " . $retry_url . 
            "\n\nIf you continue to experience issues, please contact our support team at " . 
            get_option('kwetupizza_support_phone', '+255000000000');
        
        kwetupizza_notify_customer($order_id, 'payment_failed', $additional_message);
        
        // Notify admin
        kwetupizza_notify_admin($order_id, false);
        
        // Add timeline event with more details
        $event_description = 'Payment failed';
        if (!empty($failure_reason)) {
            $event_description .= " - $failure_reason";
        }
        kwetupizza_add_order_timeline_event($order_id, 'payment_failed', $event_description);
        
        return true;
    }
}

/**
 * Plugin deactivation function
 */
if (!function_exists('kwetupizza_deactivate')) {
    function kwetupizza_deactivate() {
        // Clean up any transients and options
        delete_option('kwetupizza_db_version');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}

/**
 * Plugin uninstall function
 */
if (!function_exists('kwetupizza_uninstall')) {
    function kwetupizza_uninstall() {
        // Only run this when explicitly uninstalling via the admin dashboard
        if (!defined('WP_UNINSTALL_PLUGIN')) {
            return;
        }
        
        // Additional uninstall actions can be added here
    }
}

// ========================
// WHATSAPP FUNCTIONS
// ========================

/**
 * Main WhatsApp message handler
 */
if (!function_exists('kwetupizza_handle_whatsapp_message')) {
    function kwetupizza_handle_whatsapp_message($from, $message) {
        // Log the context and input for debugging
        kwetupizza_log_context_and_input($from, $message);
        
        // Get current context (if any)
        $context = kwetupizza_get_conversation_context($from);
        
        // Check if we're expecting a specific type of response
        if (!empty($context['awaiting'])) {
            $awaiting = $context['awaiting'];
            
            // Handle different expected responses
            if ($awaiting === 'registration_name') {
                kwetupizza_handle_registration_name($from, $message);
                return;
            } else if ($awaiting === 'registration_email') {
                kwetupizza_handle_registration_email($from, $message);
                return;
            } else if ($awaiting === 'registration_location') {
                kwetupizza_handle_registration_location($from, $message);
                return;
            } else if ($awaiting === 'category_selection') {
                kwetupizza_handle_category_selection($from, $message);
                return;
            } else if ($awaiting === 'menu_selection') {
                kwetupizza_process_order($from, $message);
                return;
            } else if ($awaiting === 'quantity') {
                // Get the last product in the cart
                $last_product = end($context['cart']);
                $product_id = $last_product['product_id'];
                kwetupizza_confirm_order_and_request_address($from, $product_id, intval(trim($message)));
                return;
            } else if ($awaiting === 'product_quantity') {
                kwetupizza_confirm_order_and_request_address($from, $context['product_id'], intval(trim($message)));
                return;
            } else if ($awaiting === 'add_or_checkout') {
                kwetupizza_handle_add_or_checkout($from, $message);
                return;
            } else if ($awaiting === 'user_name') {
                kwetupizza_handle_user_name_input($from, $message);
                return;
            } else if ($awaiting === 'user_email') {
                kwetupizza_handle_user_email_input($from, $message);
                return;
            } else if ($awaiting === 'delivery_zone') {
                kwetupizza_handle_delivery_zone_selection($from, $message);
                return;
            } else if ($awaiting === 'delivery_address') {
                kwetupizza_handle_address_and_ask_payment_provider($from, $message);
                return;
            } else if ($awaiting === 'payment_provider') {
                kwetupizza_handle_payment_provider($from, $message);
                return;
            } else if ($awaiting === 'order_completion') {
                // Order is complete, just reply with a generic message
                kwetupizza_send_whatsapp_message($from, "Thank you for your order! You'll receive updates as your pizza is prepared and delivered. If you have any questions, please type 'help'.");
                return;
            }
        }
        
        // If not expecting a specific response, check for standard commands
        $message = trim($message);
        $lowercase_message = strtolower($message);
        
        // Check for standard commands
        if ($lowercase_message === 'menu' || $lowercase_message === 'order') {
            kwetupizza_send_menu_categories($from);
            return;
        }
        
        if ($lowercase_message === 'status') {
            kwetupizza_check_order_status($from);
            return;
        }
        
        if (strtolower(trim($message)) === 'help' || strpos(strtolower($message), 'help me') !== false) {
            kwetupizza_send_help_message($from);
            return;
        }
        
        // Check if the message is a greeting
        if (kwetupizza_is_greeting($message)) {
            kwetupizza_start_conversation($from);
            return;
        }
        
        // Default response for unrecognized messages
        kwetupizza_send_default_response($from);
    }
}

/**
 * Get user by phone number without creating a new one
 */
if (!function_exists('kwetupizza_get_user_by_phone')) {
    function kwetupizza_get_user_by_phone($phone) {
        global $wpdb;
        $users_table = $wpdb->prefix . 'kwetupizza_users';
        
        // Sanitize phone number
        $phone = kwetupizza_sanitize_phone($phone);
        
        // Check if user exists
        $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $users_table WHERE phone = %s", $phone));
        
        // If user exists, return user data
        if ($user) {
            return $user;
        }
        
        // Return null if user not found
        return null;
    }
}

/**
 * Welcome new user and start registration process
 */
if (!function_exists('kwetupizza_send_welcome_and_start_registration')) {
    function kwetupizza_send_welcome_and_start_registration($from) {
        // Sanitize the phone number for display
        $sanitized_phone = kwetupizza_sanitize_phone($from);
        
        $message = "👋 *Welcome to KwetuPizza* 🍕\n\n";
        $message .= "It looks like this is your first time chatting with us. We've already identified your WhatsApp number ($sanitized_phone) as the first step!\n\n";
        $message .= "Now, to personalize your experience, please tell us your name. What should we call you?";
        
        kwetupizza_send_whatsapp_message($from, $message);
        
        // Set context to expect name input
        kwetupizza_set_conversation_context($from, ['state' => 'greeting']);
    }
}

/**
 * Handle registration name input
 */
if (!function_exists('kwetupizza_handle_registration_name')) {
    function kwetupizza_handle_registration_name($from, $name) {
        if (empty(trim($name))) {
            kwetupizza_send_whatsapp_message($from, "Please provide a valid name to continue with your registration.");
            return;
        }
        
        // Get context that includes the phone number
        $existing_context = kwetupizza_get_conversation_context($from);
        $phone = isset($existing_context['phone']) ? $existing_context['phone'] : kwetupizza_sanitize_phone($from);
        
        // Create context with user's name and phone
        $context = [
            'user_name' => $name, 
            'awaiting' => 'registration_email',
            'phone' => $phone
        ];
        kwetupizza_set_conversation_context($from, $context);
        
        // Ask for email
        $message = "Nice to meet you, " . explode(' ', $name)[0] . "! 😊\n\n";
        $message .= "We already have your WhatsApp number ($phone) saved.\n\n";
        $message .= "Please share your email address so we can send you order confirmations and special offers.\n\n";
        $message .= "(Or type 'skip' if you prefer not to share your email right now)";
        
        kwetupizza_send_whatsapp_message($from, $message);
    }
}

/**
 * Handle registration email input
 */
if (!function_exists('kwetupizza_handle_registration_email')) {
    function kwetupizza_handle_registration_email($from, $email) {
        $context = kwetupizza_get_conversation_context($from);
        
        // Check if user wants to skip
        if (strtolower(trim($email)) === 'skip') {
            $email = '';
        } else {
            // Basic email validation
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) && !empty(trim($email))) {
                kwetupizza_send_whatsapp_message($from, "That doesn't look like a valid email address. Please try again or type 'skip'.");
                return;
            }
        }
        
        // Save email to context while preserving phone
        $context['user_email'] = $email;
        $context['awaiting'] = 'registration_location';
        // Ensure phone is still in context
        if (!isset($context['phone'])) {
            $context['phone'] = kwetupizza_sanitize_phone($from);
        }
        kwetupizza_set_conversation_context($from, $context);
        
        // Get user's first name from context
        $first_name = isset($context['user_name']) ? explode(' ', $context['user_name'])[0] : '';
        
        // Ask for location
        $message = "Thank you, $first_name! 👍\n\n";
        $message .= "Finally, please tell us your location or area where you typically want deliveries sent:";
        
        kwetupizza_send_whatsapp_message($from, $message);
    }
}

/**
 * Handle user location input
 */
if (!function_exists('kwetupizza_handle_registration_location')) {
    function kwetupizza_handle_registration_location($from, $location) {
        $context = kwetupizza_get_conversation_context($from);
        $location_value = trim($location);
        
        if (empty($location_value)) {
            kwetupizza_send_whatsapp_message($from, "Please provide a valid location to continue.");
            return;
        }
        
        // Make sure we have a phone number
        $phone = isset($context['phone']) ? $context['phone'] : kwetupizza_sanitize_phone($from);
        
        // Get user data from context
        $user_name = isset($context['user_name']) ? $context['user_name'] : "Customer-" . substr($from, -5);
        $user_email = isset($context['user_email']) ? $context['user_email'] : '';
        
        global $wpdb;
        $users_table = $wpdb->prefix . 'kwetupizza_users';
        
        // Create user in database with location
        $wpdb->insert(
            $users_table,
            array(
                'name' => $user_name,
                'email' => $user_email,
                'phone' => $phone,
                'location' => $location_value, // Store the location
                'role' => 'customer',
                'state' => 'active',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            )
        );
        
        // Get the user's first name for personalization
        $first_name = explode(' ', $user_name)[0];
        
        // Update context to maintain user info throughout the conversation
        $new_context = [
            'state' => 'menu_browsing',
            'user_name' => $user_name,
            'first_name' => $first_name,
            'awaiting' => 'category_selection'
        ];
        kwetupizza_set_conversation_context($from, $new_context);
        
        // Send welcome message
        $message = "🎉 *Registration Complete!* 🎉\n\n";
        $message .= "Thank you, $first_name! Your account has been created.\n\n";
        $message .= "Now, let's see what you'd like to order today:";
        
        kwetupizza_send_whatsapp_message($from, $message);
        
        // Show menu categories
        kwetupizza_send_menu_categories($from);
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
        
        // Set context to await menu selection while preserving user info
        $updated_context = array_merge($context, ['awaiting' => 'menu_selection']);
        kwetupizza_set_conversation_context($from, $updated_context);
    }
}

/**
 * Process order selection
 */
if (!function_exists('kwetupizza_process_order')) {
    function kwetupizza_process_order($from, $product_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kwetupizza_products';
        $product = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $product_id));

        if ($product) {
            $message = "You've selected " . $product->product_name . ". Please enter the quantity.";
            kwetupizza_send_whatsapp_message($from, $message);
            
            $context = kwetupizza_get_conversation_context($from);
            $context['cart'][] = [
                'product_id' => $product_id,
                'product_name' => $product->product_name,
                'price' => $product->price
            ];
            kwetupizza_set_conversation_context($from, array_merge($context, ['awaiting' => 'quantity']));
        } else {
            kwetupizza_send_whatsapp_message($from, "Sorry, the selected item is not available.");
        }
    }
}

/**
 * Confirm order and request address
 */
if (!function_exists('kwetupizza_confirm_order_and_request_address')) {
    function kwetupizza_confirm_order_and_request_address($from, $product_id, $quantity) {
        global $wpdb;
        $context = kwetupizza_get_conversation_context($from);

        foreach ($context['cart'] as &$cart_item) {
            if ($cart_item['product_id'] == $product_id) {
                $cart_item['quantity'] = $quantity;
                $cart_item['total'] = $cart_item['price'] * $quantity;
                break;
            }
        }

        kwetupizza_set_conversation_context($from, $context);

        $message = "Would you like to add more items or proceed to checkout? Type 'add' to add more items or 'checkout' to proceed.";
        kwetupizza_send_whatsapp_message($from, $message);

        kwetupizza_set_conversation_context($from, array_merge($context, ['awaiting' => 'add_or_checkout']));
    }
}

/**
 * Handle add or checkout response
 */
if (!function_exists('kwetupizza_handle_add_or_checkout')) {
    function kwetupizza_handle_add_or_checkout($from, $response) {
        $response = strtolower(trim($response));
        $context = kwetupizza_get_conversation_context($from);

        if ($response === 'add') {
            kwetupizza_send_menu_categories($from);
            $context['awaiting'] = 'menu_selection';
            kwetupizza_set_conversation_context($from, $context);
        } elseif ($response === 'checkout') {
            // Calculate the order total
            $total = 0;
            $summary_message = "📋 *Order Summary* 📋\n\n";
            foreach ($context['cart'] as $cart_item) {
                $summary_message .= "{$cart_item['quantity']} x {$cart_item['product_name']} - " . number_format($cart_item['total'], 2) . " TZS\n";
                $total += $cart_item['total'];
            }
            $summary_message .= "\nSubtotal: " . number_format($total, 2) . " TZS\n";
            
            // Save the total in the context
            $context['total'] = $total;
            kwetupizza_set_conversation_context($from, $context);
            
            // Send the order summary
            kwetupizza_send_whatsapp_message($from, $summary_message);
            
            // Check if we need to collect user information
            $user = kwetupizza_get_or_create_user($from);
            if (strpos($user->name, 'Customer-') === 0 || empty($user->email)) {
                // We need to collect user info before proceeding
                kwetupizza_request_user_information($from);
            } else {
                // User information exists, proceed to delivery zone selection
                $message = "Thank you, " . explode(' ', $user->name)[0] . "! Let's continue with your delivery information.";
                kwetupizza_send_whatsapp_message($from, $message);
                kwetupizza_show_delivery_zones($from);
            }
        } else {
            kwetupizza_send_whatsapp_message($from, "Sorry, I didn't understand that. Type 'add' to add more items or 'checkout' to proceed.");
        }
    }
}

/**
 * Request user information for registration
 */
if (!function_exists('kwetupizza_request_user_information')) {
    function kwetupizza_request_user_information($from) {
        $message = "Before we continue with your order, we'd like to know a bit more about you.\n\n";
        $message .= "Please enter your full name:";
        
        kwetupizza_send_whatsapp_message($from, $message);
        
        $context = kwetupizza_get_conversation_context($from);
        kwetupizza_set_conversation_context($from, array_merge($context, ['awaiting' => 'user_name']));
    }
}

/**
 * Handle user name input
 */
if (!function_exists('kwetupizza_handle_user_name_input')) {
    function kwetupizza_handle_user_name_input($from, $name) {
        $context = kwetupizza_get_conversation_context($from);
        
        if (empty(trim($name))) {
            kwetupizza_send_whatsapp_message($from, "Please provide a valid name to continue with your order.");
            return;
        }
        
        // Save name in context
        $context['user_name'] = $name;
        
        // Update user in database
        global $wpdb;
        $users_table = $wpdb->prefix . 'kwetupizza_users';
        $wpdb->update(
            $users_table,
            ['name' => $name],
            ['phone' => kwetupizza_sanitize_phone($from)]
        );
        
        // Request email
        $message = "Thank you, " . explode(' ', $name)[0] . "! Please provide your email address (or type 'skip' if you prefer not to):";
        kwetupizza_send_whatsapp_message($from, $message);
        
        // Update context to await email
        kwetupizza_set_conversation_context($from, array_merge($context, ['awaiting' => 'user_email']));
    }
}

/**
 * Handle user email input
 */
if (!function_exists('kwetupizza_handle_user_email_input')) {
    function kwetupizza_handle_user_email_input($from, $email) {
        $context = kwetupizza_get_conversation_context($from);
        
        // Check if user wants to skip
        if (strtolower(trim($email)) === 'skip') {
            $message = "No problem! Let's continue with your delivery information.";
            kwetupizza_send_whatsapp_message($from, $message);
            kwetupizza_show_delivery_zones($from);
            return;
        }
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            kwetupizza_send_whatsapp_message($from, "Please provide a valid email address or type 'skip' to continue without it.");
            return;
        }
        
        // Update user in database
        global $wpdb;
        $users_table = $wpdb->prefix . 'kwetupizza_users';
        $wpdb->update(
            $users_table,
            ['email' => $email],
            ['phone' => kwetupizza_sanitize_phone($from)]
        );
        
        $message = "Thank you for providing your information! Now let's continue with your delivery details.";
        kwetupizza_send_whatsapp_message($from, $message);
        
        // Move to delivery zone selection
        kwetupizza_show_delivery_zones($from);
    }
}

/**
 * Show available delivery zones
 */
if (!function_exists('kwetupizza_show_delivery_zones')) {
    function kwetupizza_show_delivery_zones($from) {
        global $wpdb;
        $zones_table = $wpdb->prefix . 'kwetupizza_delivery_zones';
        
        // Get all delivery zones
        $zones = $wpdb->get_results("SELECT id, zone_name, description, delivery_fee FROM $zones_table ORDER BY delivery_fee ASC");
        
        if (empty($zones)) {
            // If no zones defined, proceed with asking for the full address
            $message = "Please provide your full delivery address with street and landmarks.";
            kwetupizza_send_whatsapp_message($from, $message);
            
            $context = kwetupizza_get_conversation_context($from);
            kwetupizza_set_conversation_context($from, array_merge($context, ['awaiting' => 'full_address']));
            return;
        }
        
        // Format the delivery zones message
        $message = "📍 *Select Your Delivery Area* 📍\n\n";
        $message .= "Please type the number of your delivery area:\n\n";
        
        foreach ($zones as $index => $zone) {
            $message .= "{$zone->id}. *{$zone->zone_name}*\n";
            $message .= "   {$zone->description}\n";
            $message .= "   Delivery Fee: " . number_format($zone->delivery_fee, 2) . " TZS\n\n";
        }
        
        kwetupizza_send_whatsapp_message($from, $message);
        
        // Set context to await delivery zone selection
        $context = kwetupizza_get_conversation_context($from);
        kwetupizza_set_conversation_context($from, array_merge($context, ['awaiting' => 'delivery_zone']));
    }
}

/**
 * Handle delivery zone selection
 */
if (!function_exists('kwetupizza_handle_delivery_zone_selection')) {
    function kwetupizza_handle_delivery_zone_selection($from, $zone_id) {
        global $wpdb;
        $zones_table = $wpdb->prefix . 'kwetupizza_delivery_zones';
        
        // Check if zone_id is valid
        $zone = $wpdb->get_row($wpdb->prepare("SELECT * FROM $zones_table WHERE id = %d", $zone_id));
        
        if (!$zone) {
            kwetupizza_send_whatsapp_message($from, "Please select a valid delivery area number.");
            kwetupizza_show_delivery_zones($from);
            return;
        }
        
        // Save the selected zone in context
        $context = kwetupizza_get_conversation_context($from);
        $context['delivery_zone'] = [
            'id' => $zone->id,
            'name' => $zone->zone_name,
            'fee' => $zone->delivery_fee
        ];
        
        // Add delivery fee to order total
        if (!isset($context['total'])) {
            $context['total'] = 0;
            foreach ($context['cart'] as $item) {
                $context['total'] += $item['total'];
            }
        }
        
        $context['delivery_fee'] = $zone->delivery_fee;
        $context['grand_total'] = $context['total'] + $zone->delivery_fee;
        
        kwetupizza_set_conversation_context($from, $context);
        
        // Ask for specific address within the zone
        $message = "You've selected: *{$zone->zone_name}*\n\n";
        $message .= "Please provide your specific address within this area (street, house/apartment number, landmarks):";
        
        kwetupizza_send_whatsapp_message($from, $message);
        
        // Update context to await full address
        kwetupizza_set_conversation_context($from, array_merge($context, ['awaiting' => 'full_address']));
    }
}

/**
 * Handle address input and ask for payment provider
 */
if (!function_exists('kwetupizza_handle_address_and_ask_payment_provider')) {
    function kwetupizza_handle_address_and_ask_payment_provider($from, $address) {
        $context = kwetupizza_get_conversation_context($from);

        if (isset($context['cart'])) {
            // Save the address in the conversation context
            $context['address'] = $address;
            kwetupizza_set_conversation_context($from, $context);

            // If we have a delivery zone and fee set, include it in the order summary
            $summary_message = "📋 *Order Summary* 📋\n\n";
            
            foreach ($context['cart'] as $cart_item) {
                $summary_message .= "{$cart_item['quantity']} x {$cart_item['product_name']} - " . number_format($cart_item['total'], 2) . " TZS\n";
            }
            
            $summary_message .= "\nSubtotal: " . number_format($context['total'], 2) . " TZS\n";
            
            if (isset($context['delivery_fee'])) {
                $summary_message .= "Delivery Fee: " . number_format($context['delivery_fee'], 2) . " TZS\n";
                $summary_message .= "Total: " . number_format($context['grand_total'], 2) . " TZS\n\n";
            } else {
                $summary_message .= "Total: " . number_format($context['total'], 2) . " TZS\n\n";
            }
            
            $summary_message .= "Delivery Address: {$address}\n\n";
            $summary_message .= "Please select your Mobile Money network for payment:";
            
            // Show interactive network selection buttons
            $networks = [
                "1" => "Vodacom (M-Pesa)",
                "2" => "Tigo (Tigo Pesa)",
                "3" => "Airtel (Airtel Money)",
                "4" => "Halotel (Halopesa)"
            ];
            
            foreach ($networks as $key => $network) {
                $summary_message .= "\n{$key}. {$network}";
            }
            
            kwetupizza_send_whatsapp_message($from, $summary_message);

            // Set the context to expect a network provider response
            kwetupizza_set_conversation_context($from, array_merge($context, ['awaiting' => 'payment_provider']));
        } else {
            kwetupizza_send_whatsapp_message($from, "Error processing your order. Please try again.");
        }
    }
}

/**
 * Handle payment provider response
 */
if (!function_exists('kwetupizza_handle_payment_provider_response')) {
    function kwetupizza_handle_payment_provider_response($from, $provider) {
        $context = kwetupizza_get_conversation_context($from);
        
        if (isset($context['awaiting']) && $context['awaiting'] === 'payment_provider') {
            $valid_providers = array(
                '1' => 'vodacom',
                '2' => 'tigo',
                '3' => 'airtel',
                '4' => 'halopesa',
                '5' => 'paypal' // New PayPal option
            );
            
            // Map common names to our provider keys
            $provider_map = array(
                'vodacom' => 'vodacom',
                'mpesa' => 'vodacom',
                'tigo' => 'tigo',
                'tigopesa' => 'tigo',
                'airtel' => 'airtel',
                'airtelmoney' => 'airtel',
                'halo' => 'halopesa',
                'halopesa' => 'halopesa',
                'card' => 'paypal',
                'paypal' => 'paypal',
                'creditcard' => 'paypal',
                'debitcard' => 'paypal'
            );
            
            // Try to match input to a provider
            $provider_key = null;
            if (isset($valid_providers[$provider])) {
                $provider_key = $valid_providers[$provider];
            } elseif (isset($provider_map[strtolower($provider)])) {
                $provider_key = $provider_map[strtolower($provider)];
            }
            
            if ($provider_key) {
                // Save the provider to the conversation context
                $context['payment_provider'] = $provider_key;
                kwetupizza_set_conversation_context($from, $context);
                
                if ($provider_key === 'paypal') {
                    // Handle PayPal/card payment flow
                    kwetupizza_handle_paypal_payment($from);
                } else {
                    // Handle mobile money flow
                    // Ask if the user wants to use their WhatsApp number for payment
                    $message = "Would you like to use your WhatsApp number ($from) for payment?\n\n";
                    $message .= "1. Yes\n";
                    $message .= "2. No (provide another number)";
                    
                    kwetupizza_send_whatsapp_message($from, $message);
    
                    // Set the context to expect a yes/no response
                    kwetupizza_set_conversation_context($from, array_merge($context, ['awaiting' => 'use_whatsapp_number']));
                }
            } else {
                // Invalid provider input
                $message = "Please reply with a valid payment option:\n\n";
                $message .= "1. Vodacom M-Pesa\n";
                $message .= "2. Tigo Pesa\n";
                $message .= "3. Airtel Money\n";
                $message .= "4. Halo Pesa\n";
                $message .= "5. Card Payment (PayPal)";
                
                kwetupizza_send_whatsapp_message($from, $message);
            }
        } else {
            // Unexpected response, send a default message
            kwetupizza_send_whatsapp_message($from, "Sorry, I didn't understand that. Type 'menu' to see available options.");
        }
    }
}

/**
 * Handle user's response to using WhatsApp number for payment
 */
if (!function_exists('kwetupizza_handle_use_whatsapp_number_response')) {
    function kwetupizza_handle_use_whatsapp_number_response($from, $response) {
        $response = strtolower(trim($response));
        $context = kwetupizza_get_conversation_context($from);

        if (isset($context['awaiting']) && $context['awaiting'] === 'use_whatsapp_number') {
            if ($response === 'yes' || $response === '1') {
                // Proceed with using WhatsApp number for payment
                kwetupizza_generate_mobile_money_push($from, $context['cart'], $context['address'], $from);
            } elseif ($response === 'no' || $response === '2') {
                // Ask for an alternative phone number
                $message = "Please provide the phone number you'd like to use for mobile money payment (e.g., 255XXXXXXXXX):";
                kwetupizza_send_whatsapp_message($from, $message);

                // Update context to expect a new phone number for payment
                kwetupizza_set_conversation_context($from, array_merge($context, ['awaiting' => 'payment_phone']));
            } else {
                kwetupizza_send_whatsapp_message($from, "Please reply with '1' for Yes or '2' for No.");
            }
        } else {
            kwetupizza_send_whatsapp_message($from, "Error: No active payment process. Please restart your order.");
        }
    }
}

/**
 * Handle the input for the payment phone number
 */
if (!function_exists('kwetupizza_handle_payment_phone_input')) {
    function kwetupizza_handle_payment_phone_input($from, $payment_phone) {
        $context = kwetupizza_get_conversation_context($from);

        // Check if the user is expected to provide a phone number
        if (isset($context['awaiting']) && $context['awaiting'] === 'payment_phone') {
            // Proceed with the provided phone number for payment
            kwetupizza_generate_mobile_money_push($from, $context['cart'], $context['address'], $payment_phone);
        } else {
            kwetupizza_send_whatsapp_message($from, "I'm not expecting a payment phone number at this moment. Please restart your order if you want to make changes.");
        }
    }
}

/**
 * Generate Mobile Money Push payment request
 */
if (!function_exists('kwetupizza_generate_mobile_money_push')) {
    function kwetupizza_generate_mobile_money_push($from, $cart, $address, $payment_phone) {
        global $wpdb;
        
        // Get context for additional data like delivery zone fee
        $context = kwetupizza_get_conversation_context($from);
        
        // Validate inputs
        if (empty($cart) || !is_array($cart)) {
            kwetupizza_log("ERROR: Invalid cart data for payment request", 'error', 'payment.log');
            kwetupizza_send_whatsapp_message($from, "⚠️ Error: Invalid order data. Please try ordering again.");
            return false;
        }
        
        if (empty($payment_phone)) {
            kwetupizza_log("ERROR: Missing payment phone number", 'error', 'payment.log');
            kwetupizza_send_whatsapp_message($from, "⚠️ Error: Missing payment phone number. Please provide your mobile money number.");
            return false;
        }
        
        // Calculate total amount including delivery fee
        $subtotal = 0;
        foreach ($cart as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }
        
        // Add delivery fee from context if available
        $delivery_fee = isset($context['delivery_fee']) ? $context['delivery_fee'] : 0;
        $total = $subtotal + $delivery_fee;
        
        // Format items for notification
        $items_text = "";
        foreach ($cart as $item) {
            $items_text .= "{$item['product_name']} x {$item['quantity']} = " . kwetupizza_format_currency($item['price'] * $item['quantity']) . "\n";
        }
        
        // Get user information
        $user = kwetupizza_get_or_create_user($from);
        
        // Create a unique transaction reference
        $tx_ref = 'order-' . time() . '-' . kwetupizza_generate_random_string(6);
        
        // Save order to database before payment initiation
        $order_id = kwetupizza_save_order_to_db($from, $cart, $address, $total, $context);
        
        if (!$order_id) {
            kwetupizza_log("ERROR: Failed to save order to database", 'error', 'payment.log');
            kwetupizza_send_whatsapp_message($from, "⚠️ Error processing your order. Please try again later or contact support.");
            return false;
        }
        
        // Update tx_ref to include order ID for better tracking
        $tx_ref = 'order-' . $order_id . '-' . time();
        
        // Get API key
        $flw_secret_key = get_option('kwetupizza_flw_secret_key');
        if (empty($flw_secret_key)) {
            kwetupizza_log("ERROR: Missing Flutterwave secret key", 'error', 'payment.log');
            kwetupizza_send_whatsapp_message($from, "Error: Payment gateway not properly configured. Please contact support.");
            return false;
        }

        // Get user's email, with fallback
        $user_email = !empty($user->email) ? $user->email : kwetupizza_get_customer_email($from);
        
        // Get user's name, with fallback
        $user_name = $user->name;
        if (strpos($user_name, 'Customer-') === 0) {
            $user_name = isset($context['user_name']) ? $context['user_name'] : $user_name;
        }

        // Prepare payment payload
        $payload = [
            'tx_ref' => $tx_ref,
            'amount' => $total,
            'currency' => 'TZS',
            'network' => isset($context['payment_provider']) ? strtoupper($context['payment_provider']) : 'MPESA',
            'email' => $user_email,
            'phone_number' => $payment_phone,
            'fullname' => $user_name,
            'redirect_url' => kwetupizza_get_callback_url('flutterwave'),
            'meta' => [
                'order_id' => $order_id,
                'customer_phone' => $from
            ]
        ];
        
        // Log payment attempt with detailed information
        kwetupizza_log("Initiating mobile money payment: " . json_encode($payload), 'info', 'payment.log');
        
        // Make API request to Flutterwave
        $response = wp_remote_post('https://api.flutterwave.com/v3/charges?type=mobile_money_tanzania', [
            'headers' => [
                'Authorization' => 'Bearer ' . $flw_secret_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($payload)
        ]);

        // Check for request errors
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            kwetupizza_log("ERROR: Flutterwave API request failed: $error_message", 'error', 'payment.log');
            kwetupizza_send_whatsapp_message($from, "Error initiating the payment. Please try again later or contact support.");
            return false;
        }

        $response_body = wp_remote_retrieve_body($response);
        $result = json_decode($response_body, true);
        
        // Log the response
        kwetupizza_log("Flutterwave response: " . json_encode($result), 'info', 'payment.log');
        
        // Check if payment initiation was successful
        if (isset($result['status']) && $result['status'] === 'success') {
            // Update the transaction reference in the database
            kwetupizza_update_transaction_reference($order_id, $tx_ref, $result['data']['id']);
            
            // Send confirmation message
            $message = "🍕 *Your Order is Being Processed!* 🍕\n\n";
            $message .= "Order #$order_id has been created.\n\n";
            $message .= "📱 Check your phone for a payment prompt from Flutterwave/Mpesa.\n";
            $message .= "Please enter your PIN to complete the payment of " . kwetupizza_format_currency($total) . ".\n\n";
            
            $message .= "📋 *Order Details*:\n";
            $message .= $items_text;
            if ($delivery_fee > 0) {
                $message .= "Delivery Fee: " . kwetupizza_format_currency($delivery_fee) . "\n";
            }
            $message .= "Total: " . kwetupizza_format_currency($total) . "\n\n";
            $message .= "🏠 Delivery Address: $address\n\n";
            $message .= "💳 Payment Method: " . (isset($context['payment_provider']) ? $context['payment_provider'] : 'Mobile Money') . "\n";
            $message .= "📞 Payment Number: $payment_phone\n\n";
            $message .= "We'll notify you once your payment is confirmed!";
            
            kwetupizza_send_whatsapp_message($from, $message);
            
            // Notify admin of new order
            kwetupizza_notify_admin_of_order($order_id, [
                'customer_name' => $user_name,
                'customer_phone' => $from,
                'items' => $items_text,
                'delivery_address' => $address,
                'amount' => $total,
                'currency' => 'TZS'
            ]);
            
            // Reset conversation context after successful order
            kwetupizza_set_conversation_context($from, []);
            
            return true;
        } else {
            // Handle payment initiation failure
            $error_message = isset($result['message']) ? $result['message'] : 'Unknown error';
            kwetupizza_log("ERROR: Payment initiation failed: $error_message", 'error', 'payment.log');
            
            // Update order status to failed
            $wpdb->update(
                $wpdb->prefix . 'kwetupizza_orders',
                ['status' => 'payment_failed'],
                ['id' => $order_id]
            );
            
            // Send error message to customer
            kwetupizza_send_whatsapp_message(
                $from, 
                "⚠️ *Payment Failed*\n\nWe were unable to initiate your payment: $error_message\n\n" .
                "Please try again later or contact our support team for assistance."
            );
            
            return false;
        }
    }
}

/**
 * Update transaction reference after payment initiation
 */
if (!function_exists('kwetupizza_update_transaction_reference')) {
    function kwetupizza_update_transaction_reference($order_id, $tx_ref, $transaction_id) {
        global $wpdb;
        $transactions_table = $wpdb->prefix . 'kwetupizza_transactions';
        
        $wpdb->update(
            $transactions_table,
            [
                'transaction_reference' => $transaction_id,
                'tx_ref' => $tx_ref,
                'updated_at' => current_time('mysql')
            ],
            ['order_id' => $order_id]
        );
        
        kwetupizza_log("Updated transaction reference for order #$order_id: $tx_ref, ID: $transaction_id", 'info', 'payment.log');
    }
}

/**
 * Save order to database
 */
if (!function_exists('kwetupizza_save_order_to_db')) {
    function kwetupizza_save_order_to_db($phone, $cart, $address, $total, $context) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'kwetupizza_orders';
        $order_items_table = $wpdb->prefix . 'kwetupizza_order_items';
        
        // Get or create user to use their name
        $user = kwetupizza_get_or_create_user($phone);
        
        // Check if we have a user name in context (from registration step)
        $user_name = $user->name;
        if (isset($context['user_name']) && !empty($context['user_name'])) {
            $user_name = $context['user_name'];
            
            // Update user in database if needed
            if ($user_name !== $user->name) {
                $users_table = $wpdb->prefix . 'kwetupizza_users';
                $wpdb->update(
                    $users_table,
                    ['name' => $user_name],
                    ['phone' => kwetupizza_sanitize_phone($phone)]
                );
            }
        }
        
        // Use email from context if available
        $user_email = $user->email;
        if (isset($context['user_email']) && !empty($context['user_email']) && filter_var($context['user_email'], FILTER_VALIDATE_EMAIL)) {
            $user_email = $context['user_email'];
            
            // Update user in database if needed
            if ($user_email !== $user->email) {
                $users_table = $wpdb->prefix . 'kwetupizza_users';
                $wpdb->update(
                    $users_table,
                    ['email' => $user_email],
                    ['phone' => kwetupizza_sanitize_phone($phone)]
                );
            }
        }
        
        // Prepare common order data
        $order_data = array(
            'order_date' => current_time('mysql'),
            'customer_name' => $user_name,
            'customer_phone' => kwetupizza_sanitize_phone($phone),
            'customer_email' => $user_email,
            'delivery_address' => $address,
            'delivery_phone' => kwetupizza_sanitize_phone($phone),
            'status' => 'pending_payment',
            'total' => $total,
            'currency' => 'TZS',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        // Insert order into database
        $result = $wpdb->insert($orders_table, $order_data);
        
        if ($result === false) {
            kwetupizza_log("Failed to insert order into database: " . $wpdb->last_error, 'error');
            return false;
        }
        
        $order_id = $wpdb->insert_id;
        kwetupizza_log("Order saved with ID: $order_id", 'info');
        
        // Insert order items
        foreach ($context['cart'] as $item) {
            $item_data = array(
                'order_id' => $order_id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'created_at' => current_time('mysql')
            );
            
            $wpdb->insert($order_items_table, $item_data);
        }
        
        // Create transaction record
        $transactions_table = $wpdb->prefix . 'kwetupizza_transactions';
        $transaction_data = array(
            'order_id' => $order_id,
            'transaction_date' => current_time('mysql'),
            'payment_method' => isset($context['payment_provider']) ? $context['payment_provider'] : 'Mobile Money',
            'payment_status' => 'pending',
            'amount' => $total,
            'currency' => 'TZS',
            'payment_provider' => 'Flutterwave',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        $wpdb->insert($transactions_table, $transaction_data);
        
        // Add order creation event to timeline
        kwetupizza_add_order_timeline_event($order_id, 'order_created', 'Order created via WhatsApp');
        
        return $order_id;
    }
}

/**
 * Verify payment using Flutterwave webhook and notify
 */
if (!function_exists('kwetupizza_confirm_payment_and_notify')) {
    function kwetupizza_confirm_payment_and_notify($transaction_id) {
        // Log the function call
        kwetupizza_log("Confirming payment for transaction ID: $transaction_id", 'info', 'payment-confirmation.log');
        
        // Verify payment with Flutterwave API
        $transaction_data = kwetupizza_verify_payment($transaction_id);

        if (!$transaction_data) {
            kwetupizza_log("Payment verification failed for transaction ID: $transaction_id", 'error', 'payment-confirmation.log');
            return false;
        }
        
        // Log successful verification 
        kwetupizza_log("Payment verified successfully: " . json_encode($transaction_data), 'info', 'payment-confirmation.log');

        global $wpdb;
        $tx_ref = $transaction_data['tx_ref'];

        // Extract order ID from tx_ref (format could be order_ID or order-ID or order-ID-TIMESTAMP)
        preg_match('/order[-_](\\d+)/', $tx_ref, $matches);
        $order_id = isset($matches[1]) ? $matches[1] : null;
        
        // If the new format with timestamp is used
        if (!$order_id && strpos($tx_ref, '-') !== false) {
            $parts = explode('-', $tx_ref);
            if (count($parts) >= 2 && $parts[0] === 'order') {
                $order_id = $parts[1];
            }
        }
        
        if (!$order_id) {
            kwetupizza_log("Could not extract order ID from tx_ref: $tx_ref", 'error', 'payment-confirmation.log');
            return false;
        }
        
        // Get order details
        $orders_table = $wpdb->prefix . 'kwetupizza_orders';
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $orders_table WHERE id = %d",
            $order_id
        ));
        
        if (!$order) {
            kwetupizza_log("Order not found for ID: $order_id", 'error', 'payment-confirmation.log');
            return false;
        }
        
        // Process the successful payment
        kwetupizza_process_successful_payment($transaction_data);
        
        // Additional notification
        $customer_message = "✅ *Payment Confirmed!* ✅\n\n";
        $customer_message .= "Thank you for your payment for Order #$order_id.\n\n";
        $customer_message .= "Your order is now being prepared and will be delivered to you soon.\n";
        $customer_message .= "Delivery Address: {$order->delivery_address}\n\n";
        $customer_message .= "Amount Paid: " . kwetupizza_format_currency($order->total, $order->currency) . "\n\n";
        
        // Get estimated delivery time from order data or use default
        $estimated_time = $order->estimated_delivery_time ? $order->estimated_delivery_time : "30-45 minutes";
        $customer_message .= "Estimated Delivery Time: $estimated_time\n\n";
        $customer_message .= "We'll notify you when your order is out for delivery!\n";
        $customer_message .= "Thank you for choosing KwetuPizza! 🍕";
        
        // Send WhatsApp confirmation
        kwetupizza_send_whatsapp_message($order->customer_phone, $customer_message);
        
        // Send SMS as backup
        $sms_message = "KwetuPizza: Your payment for Order #$order_id has been confirmed! Your order is being prepared and will be delivered in approximately $estimated_time.";
        kwetupizza_send_nextsms($order->customer_phone, $sms_message);
        
        // Notify admin
        kwetupizza_notify_admin($order_id, true);
        
        // Add timeline event
        kwetupizza_add_order_timeline_event($order_id, 'payment_confirmed', 'Payment confirmed via ' . $transaction_data['payment_type']);
        
        kwetupizza_log("Payment confirmation process completed for order #$order_id", 'info', 'payment-confirmation.log');
        
        return true;
    }
}

/**
 * Log current context and input for debugging
 */
if (!function_exists('kwetupizza_log_context_and_input')) {
    function kwetupizza_log_context_and_input($from, $input) {
        $log_file = plugin_dir_path(dirname(__FILE__)) . 'includes/kwetupizza-debug.log';
        $context = kwetupizza_get_conversation_context($from);

        $log_content = "Current Context for user [$from]:\n";
        $log_content .= print_r($context, true);
        $log_content .= "Received Input: $input\n\n";

        file_put_contents($log_file, $log_content, FILE_APPEND);
        
        // Log to error log as well for easy access
        error_log($log_content);
    }
}

/**
 * Log and handle Flutterwave payment webhook (backup handler)
 */
if (!function_exists('log_flutterwave_payment_webhook')) {
    function log_flutterwave_payment_webhook(WP_REST_Request $request) {
        $webhook_data = json_decode($request->get_body(), true);
        
        if (!empty($webhook_data)) {
            if (isset($webhook_data['event']) && $webhook_data['event'] === 'charge.completed') {
                $status = $webhook_data['data']['status'];
                $transaction_id = $webhook_data['data']['id'];
                $tx_ref = $webhook_data['data']['tx_ref'];
                $phone_number = $webhook_data['data']['customer']['phone_number'];
                $delivery_address = $webhook_data['meta']['delivery_address'];

                if ($status === 'successful') {
                    $verification_result = kwetupizza_confirm_payment_and_notify($transaction_id);

                    if ($verification_result) {
                        // Extract the order_id from tx_ref (format: order_TIMESTAMP)
                        preg_match('/order_(\d+)/', $tx_ref, $matches);
                        $order_id = isset($matches[1]) ? $matches[1] : null;
                        
                        if ($order_id) {
                            // Use the new notification function for both WhatsApp and SMS
                            kwetupizza_notify_customer($order_id, 'payment_confirmed', 
                                "Your delicious pizza is on the way to $delivery_address!");
                        }
                        
                        return new WP_REST_Response('Payment processed successfully', 200);
                    } else {
                        return new WP_REST_Response('Payment verification failed', 400);
                    }
                } elseif ($status === 'failed') {
                    // Extract the order_id from tx_ref
                    preg_match('/order_(\d+)/', $tx_ref, $matches);
                    $order_id = isset($matches[1]) ? $matches[1] : null;
                    
                    // Extract failure reason if available
                    $failure_reason = '';
                    if (isset($webhook_data['data']['processor_response'])) {
                        $failure_reason = $webhook_data['data']['processor_response'];
                    } elseif (isset($webhook_data['data']['gateway_response'])) {
                        $failure_reason = $webhook_data['data']['gateway_response'];
                    }
                    
                    if ($order_id) {
                        // Use handle_failed_payment for consistent handling
                        kwetupizza_handle_failed_payment($tx_ref, $failure_reason);
                    }
                    
                    return new WP_REST_Response('Payment failed', 400);
                }
            }
        }

        return new WP_REST_Response('Invalid data received', 400);
    }
}

/**
 * Handle WhatsApp webhook messages
 */
if (!function_exists('kwetupizza_handle_whatsapp_messages')) {
    function kwetupizza_handle_whatsapp_messages($request) {
        $webhook_data = $request->get_json_params();

        // Log the incoming data for debugging
        $log_file = plugin_dir_path(dirname(__FILE__)) . 'includes/whatsapp-webhook.log';
        file_put_contents($log_file, "WhatsApp Webhook Data: " . print_r($webhook_data, true) . PHP_EOL, FILE_APPEND);

        if (isset($webhook_data['entry'][0]['changes'][0]['value']['messages'][0])) {
            $message_data = $webhook_data['entry'][0]['changes'][0]['value']['messages'][0];
            
            // Check if 'from' exists before using it
            if (isset($message_data['from'])) {
                $from = $message_data['from'];

                // Check if text body exists
                if (isset($message_data['text']['body'])) {
                    $message = trim($message_data['text']['body']);
                    kwetupizza_handle_whatsapp_message($from, $message);
                    return new WP_REST_Response('Message processed', 200);
                }
            }

        } elseif (isset($webhook_data['entry'][0]['changes'][0]['value']['statuses'][0])) {
            error_log('Received a message status update');
            return new WP_REST_Response('Status update received', 200);
        } else {
            error_log('WhatsApp message structure not as expected.');
            return new WP_REST_Response('No valid message or status found', 400);
        }

        return new WP_REST_Response('Invalid data received', 400);
    }
}

/**
 * Adds an event to the order timeline
 * 
 * @param int $order_id The ID of the order
 * @param string $event_type The type of event (e.g. 'payment_confirmed', 'order_delivered')
 * @param string $description Description of the event
 * @return bool Whether the event was added successfully
 */
if (!function_exists('kwetupizza_add_order_timeline_event')) {
    function kwetupizza_add_order_timeline_event($order_id, $event_type, $description) {
        global $wpdb;
        $timeline_table = $wpdb->prefix . 'kwetupizza_order_timeline';
        
        // Insert the timeline event
        $result = $wpdb->insert(
            $timeline_table,
            array(
                'order_id' => $order_id,
                'event_type' => $event_type,
                'description' => $description,
                'created_at' => current_time('mysql')
            )
        );
        
        if ($result) {
            kwetupizza_log("Added timeline event '$event_type' for order #$order_id", 'info');
            return true;
        } else {
            kwetupizza_log("Failed to add timeline event for order #$order_id: " . $wpdb->last_error, 'error');
            return false;
        }
    }
}

/**
 * Check and report the status of a customer's most recent order
 * 
 * @param string $from The customer's phone number
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
        
        // Add estimated delivery info if the order is not yet delivered
        if ($order->status != 'delivered' && $order->status != 'cancelled') {
            $message .= "\n⏰ *Estimated Delivery:* ";
            
            // Calculate estimated delivery time based on order creation
            $order_time = strtotime($order->created_at);
            $current_time = time();
            $time_difference = $current_time - $order_time;
            
            if ($order->status == 'processing' || $order->status == 'preparing') {
                // Still in kitchen - estimate 45 minutes from order time
                $delivery_estimate = $order_time + (45 * 60);
                if ($current_time > $delivery_estimate) {
                    $message .= "Your order is taking longer than expected. We're working on it!";
                } else {
                    $minutes_remaining = ceil(($delivery_estimate - $current_time) / 60);
                    $message .= "Approximately $minutes_remaining minutes";
                }
            } elseif ($order->status == 'out_for_delivery') {
                // Out for delivery - estimate 15 minutes
                $message .= "Your order is on its way! Approximately 10-15 minutes.";
            }
        }
        
        // Add a note about contacting for issues
        $message .= "\n\nThank you for your patience! If you have any questions, please reply with 'help'.";
        
        // Send the status message
        kwetupizza_send_whatsapp_message($from, $message);
    }
}

/**
 * Get an appropriate icon for a timeline event
 * 
 * @param string $event_type The type of event
 * @return string An emoji icon representing the event
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

/**
 * Send help information to the customer
 * 
 * @param string $from The customer's phone number
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
        $message .= "If you encounter payment problems, please try again or contact our customer support at " . get_option('kwetupizza_support_phone', '+255xxxxxxxxx') . "\n\n";
        
        $message .= "*Business Hours:*\n";
        $message .= "We're open from 10:00 AM to 10:00 PM, every day\n\n";
        
        $message .= "Thank you for choosing KwetuPizza! 🍕";
        
        kwetupizza_send_whatsapp_message($from, $message);
    }
}

/**
 * Send default message when input is not understood
 */
if (!function_exists('kwetupizza_send_default_message')) {
    function kwetupizza_send_default_message($from) {
        kwetupizza_send_whatsapp_message($from, "Sorry, I didn't understand that. Type 'menu' to see available options.");
    }
}

/**
 * Get conversation context
 */
if (!function_exists('kwetupizza_get_conversation_context')) {
    function kwetupizza_get_conversation_context($from) {
        $context = get_transient("kwetupizza_whatsapp_context_$from");
        return $context ? $context : [];
    }
}

/**
 * Set conversation context
 */
if (!function_exists('kwetupizza_set_conversation_context')) {
    function kwetupizza_set_conversation_context($from, $context) {
        // Set context with 5 minutes expiry for auto-reset chat feature
        set_transient("kwetupizza_whatsapp_context_$from", $context, 60 * 5); // 5 minutes expiry
        
        // Also update the last activity timestamp
        $context['last_activity'] = time();
        
        // Log the context change for debugging
        kwetupizza_log("Set context for $from: " . print_r($context, true), 'debug', 'context.log');
    }
}

/**
 * Create sample delivery zones if none exist
 */
if (!function_exists('kwetupizza_create_sample_delivery_zones')) {
    function kwetupizza_create_sample_delivery_zones() {
        global $wpdb;
        $zones_table = $wpdb->prefix . 'kwetupizza_delivery_zones';
        
        // Check if any zones exist
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $zones_table");
        
        if ($count > 0) {
            return; // Zones already exist, don't create samples
        }
        
        // Sample delivery zones data
        $sample_zones = array(
            array(
                'zone_name' => 'City Center',
                'description' => 'Downtown and central business district',
                'coordinates' => '[-6.8123,39.2891],[-6.8156,39.2982],[-6.8230,39.2956],[-6.8209,39.2874]',
                'delivery_fee' => 2000,
                'min_delivery_time' => 15,
                'max_delivery_time' => 30
            ),
            array(
                'zone_name' => 'Northern Suburbs',
                'description' => 'Residential areas to the north of the city',
                'coordinates' => '[-6.7923,39.2791],[-6.7856,39.2882],[-6.7930,39.2956],[-6.8009,39.2774]',
                'delivery_fee' => 3000,
                'min_delivery_time' => 25,
                'max_delivery_time' => 45
            ),
            array(
                'zone_name' => 'Eastern District',
                'description' => 'Commercial and residential areas to the east',
                'coordinates' => '[-6.8223,39.3091],[-6.8156,39.3182],[-6.8230,39.3256],[-6.8309,39.3074]',
                'delivery_fee' => 4000,
                'min_delivery_time' => 30,
                'max_delivery_time' => 50
            ),
            array(
                'zone_name' => 'Southern Beach Area',
                'description' => 'Tourist and beach areas to the south',
                'coordinates' => '[-6.8523,39.2791],[-6.8456,39.2882],[-6.8530,39.2956],[-6.8609,39.2774]',
                'delivery_fee' => 5000,
                'min_delivery_time' => 35,
                'max_delivery_time' => 60
            )
        );
        
        // Insert sample zones
        foreach ($sample_zones as $zone) {
            $wpdb->insert(
                $zones_table,
                array(
                    'zone_name' => $zone['zone_name'],
                    'description' => $zone['description'],
                    'coordinates' => $zone['coordinates'],
                    'delivery_fee' => $zone['delivery_fee'],
                    'min_delivery_time' => $zone['min_delivery_time'],
                    'max_delivery_time' => $zone['max_delivery_time'],
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                )
            );
        }
        
        kwetupizza_log('Created sample delivery zones', 'info');
    }
}

/**
 * Initialize sample data for testing
 */
if (!function_exists('kwetupizza_init_sample_data_ajax')) {
    function kwetupizza_init_sample_data_ajax() {
        // Check nonce for security
        check_ajax_referer('kwetupizza-nonce', 'nonce');
        
        // Check if user has permission
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        // Create sample delivery zones
        kwetupizza_create_sample_delivery_zones();
        
        // Respond with success message
        wp_send_json_success('Sample data initialized successfully.');
    }
}
add_action('wp_ajax_kwetupizza_init_sample_data', 'kwetupizza_init_sample_data_ajax');

// Update register_ajax_handlers function to include new handlers
if (!function_exists('kwetupizza_register_ajax_handlers')) {
    function kwetupizza_register_ajax_handlers() {
        add_action('wp_ajax_kwetupizza_test_whatsapp', 'kwetupizza_test_whatsapp_ajax');
        add_action('wp_ajax_kwetupizza_test_sms', 'kwetupizza_test_sms_ajax');
        add_action('wp_ajax_kwetupizza_init_sample_data', 'kwetupizza_init_sample_data_ajax');
    }
}

/**
 * Send greeting message
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
 * Notify customer about order status changes
 */
if (!function_exists('kwetupizza_notify_customer')) {
    function kwetupizza_notify_customer($order_id, $status, $additional_message = '') {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'kwetupizza_orders';
        
        // Log that this function is being called
        kwetupizza_log("Starting notification for order #$order_id with status: $status", 'info', 'notifications.log');
        
        $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM $orders_table WHERE id = %d", $order_id));
        
        if (!$order) {
            kwetupizza_log("Failed to find order #$order_id for notification", 'error', 'notifications.log');
            return false;
        }
        
        // Get user and ensure it exists in the database
        $user = kwetupizza_get_or_create_user($order->customer_phone);
        $name = $user->name;
        
        // Get first name for more casual communication
        $name_parts = explode(' ', $name);
        $first_name = $name_parts[0];
        
        // Don't use Customer- prefix in messages
        if (strpos($first_name, 'Customer-') === 0) {
            $greeting = "";
        } else {
            $greeting = "Hi $first_name! ";
        }
        
        kwetupizza_log("Found order #$order_id for: {$order->customer_name}, phone: {$order->customer_phone}", 'info', 'notifications.log');
        
        $status_messages = array(
            'processing' => "{$greeting}Your order #$order_id is now being processed. We'll update you when it's ready for delivery.",
            'preparing' => "{$greeting}Your order #$order_id is being prepared in our kitchen. It will be ready soon!",
            'ready_for_delivery' => "{$greeting}Good news! Your order #$order_id is ready and out for delivery. It will arrive in approximately 15-30 minutes.",
            'delivered' => "{$greeting}Your order #$order_id has been delivered. Enjoy your meal! Thank you for choosing Kwetu Pizza.",
            'cancelled' => "{$greeting}Your order #$order_id has been cancelled. Please contact us if you have any questions.",
            'payment_confirmed' => "{$greeting}Payment confirmed for Order #$order_id! Your pizza is being prepared and will be delivered to you soon.",
            'payment_failed' => "❌ {$greeting}Payment for Order #$order_id has failed. This could be due to insufficient funds, network issues, or a declined transaction. You can retry payment from your account or contact our support at " . get_option('kwetupizza_support_phone', '+255000000000') . " for assistance.",
            'dispatched' => "{$greeting}Great news! Your order #$order_id has been dispatched and is on the way to you. Expect delivery soon!"
        );
        
        $message = isset($status_messages[$status]) ? $status_messages[$status] : "{$greeting}Your order #$order_id status has been updated to: $status";
        
        if (!empty($additional_message)) {
            $message .= "\n\n" . $additional_message;
        }
        
        kwetupizza_log("Preparing to send notification message: " . substr($message, 0, 100) . "...", 'info', 'notifications.log');
        
        // Send WhatsApp notification - no need to further personalize as we did it already
        $whatsapp_sent = kwetupizza_send_whatsapp_message($order->customer_phone, $message);
        
        if ($whatsapp_sent) {
            kwetupizza_log("WhatsApp message sent successfully to {$order->customer_phone}", 'info', 'notifications.log');
        } else {
            kwetupizza_log("Failed to send WhatsApp message to {$order->customer_phone}", 'error', 'notifications.log');
        }
        
        // Send SMS notification
        $sms_sent = kwetupizza_send_nextsms($order->customer_phone, $message);
        
        if ($sms_sent) {
            kwetupizza_log("SMS sent successfully to {$order->customer_phone}", 'info', 'notifications.log');
        } else {
            kwetupizza_log("Failed to send SMS to {$order->customer_phone}", 'error', 'notifications.log');
        }
        
        return ($whatsapp_sent || $sms_sent);
    }
}

/**
 * Update order status and send appropriate notifications
 */
if (!function_exists('kwetupizza_update_order_status')) {
    function kwetupizza_update_order_status($order_id, $new_status, $admin_notes = '') {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'kwetupizza_orders';
        
        // Get current order data
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $orders_table WHERE id = %d",
            $order_id
        ));
        
        if (!$order) {
            kwetupizza_log("Failed to update status for order ID: $order_id - Order not found", 'error');
            return false;
        }
        
        // Update order status
        $wpdb->update(
            $orders_table,
            [
                'status' => $new_status,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $order_id]
        );
        
        // Add timeline event
        $event_description = "Order status updated to: $new_status";
        if (!empty($admin_notes)) {
            $event_description .= " - Note: $admin_notes";
        }
        kwetupizza_add_order_timeline_event($order_id, $new_status, $event_description);
        
        // Send notifications based on the new status
        kwetupizza_notify_customer($order_id, $new_status);
        
        // Log the status change
        kwetupizza_log("Order #$order_id status updated to $new_status", 'info');
        
        return true;
    }
}

/**
 * Send order dispatch notification to customer
 */
if (!function_exists('kwetupizza_notify_order_dispatched')) {
    function kwetupizza_notify_order_dispatched($order_id, $estimated_delivery_time = '') {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'kwetupizza_orders';
        
        // Get order data
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT customer_name, customer_phone, delivery_address FROM $orders_table WHERE id = %d",
            $order_id
        ));
        
        if (!$order) {
            kwetupizza_log("Failed to send dispatch notification for order ID: $order_id - Order not found", 'error');
            return false;
        }
        
        // Update the order status to dispatched
        kwetupizza_update_order_status($order_id, 'dispatched');
        
        // Format the delivery time message
        $delivery_time_msg = !empty($estimated_delivery_time) ? 
            "Estimated delivery time: $estimated_delivery_time minutes." : 
            "It will be delivered shortly.";
        
        // Craft the WhatsApp message
        $message = "🛵 *Order #$order_id Dispatched!* 🛵\n\n";
        $message .= "Hello {$order->customer_name},\n\n";
        $message .= "Great news! Your pizza is on its way to you! $delivery_time_msg\n\n";
        $message .= "🏠 *Delivery Address*:\n{$order->delivery_address}\n\n";
        $message .= "Our delivery partner will call you when they arrive.\n\n";
        $message .= "Thank you for choosing KwetuPizza! 🍕";
        
        // Send the notification
        $whatsapp_result = kwetupizza_send_whatsapp_message($order->customer_phone, $message);
        
        // Send SMS as backup
        $sms_message = "KwetuPizza: Your order #$order_id has been dispatched! $delivery_time_msg Thank you for choosing us!";
        $sms_result = kwetupizza_send_nextsms($order->customer_phone, $sms_message);
        
        // Log the notification attempt
        if ($whatsapp_result || $sms_result) {
            kwetupizza_log("Dispatch notification sent for order #$order_id", 'info');
            return true;
        } else {
            kwetupizza_log("Failed to send dispatch notification for order #$order_id", 'error');
            return false;
        }
    }
}

/**
 * Send order delivered notification to customer
 */
if (!function_exists('kwetupizza_notify_order_delivered')) {
    function kwetupizza_notify_order_delivered($order_id) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'kwetupizza_orders';
        
        // Get order data
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT customer_name, customer_phone FROM $orders_table WHERE id = %d",
            $order_id
        ));
        
        if (!$order) {
            kwetupizza_log("Failed to send delivery notification for order ID: $order_id - Order not found", 'error');
            return false;
        }
        
        // Update the order status to delivered
        kwetupizza_update_order_status($order_id, 'delivered');
        
        // Add loyalty points for completed order
        kwetupizza_add_loyalty_points($order_id);
        
        // Craft the WhatsApp message
        $message = "✅ *Order #$order_id Delivered!* 🎉\n\n";
        $message .= "Hello {$order->customer_name},\n\n";
        $message .= "Your order has been delivered. We hope you enjoy your meal!\n\n";
        $message .= "We've added loyalty points to your account for this purchase.\n\n";
        $message .= "You'll receive a message shortly to confirm your delivery and later to rate your experience.\n\n";
        $message .= "Thank you for choosing KwetuPizza! 🍕";
        
        // Send the notification
        $whatsapp_result = kwetupizza_send_whatsapp_message($order->customer_phone, $message);
        
        // Send SMS as backup
        $sms_message = "KwetuPizza: Your order #$order_id has been delivered! Thank you for choosing us!";
        $sms_result = kwetupizza_send_nextsms($order->customer_phone, $sms_message);
        
        // Send delivery confirmation request after 5 minutes
        wp_schedule_single_event(time() + (5 * MINUTE_IN_SECONDS), 'kwetupizza_send_delivery_confirmation', [$order_id]);
        
        // Log the notification attempt
        if ($whatsapp_result || $sms_result) {
            kwetupizza_log("Delivery notification sent for order #$order_id", 'info');
            return true;
        } else {
            kwetupizza_log("Failed to send delivery notification for order #$order_id", 'error');
            return false;
        }
    }
}

/**
 * Streamlined order process to reduce friction
 */
if (!function_exists('kwetupizza_process_streamlined_order')) {
    function kwetupizza_process_streamlined_order($customer_phone, $order_data) {
        global $wpdb;
        
        // Validate required data
        if (empty($order_data['items']) || !is_array($order_data['items'])) {
            kwetupizza_log("Invalid order items data for streamlined order", 'error');
            return [
                'success' => false,
                'message' => 'Please provide valid order items'
            ];
        }
        
        if (empty($order_data['delivery_address'])) {
            kwetupizza_log("Missing delivery address for streamlined order", 'error');
            return [
                'success' => false,
                'message' => 'Please provide a delivery address'
            ];
        }
        
        // Format cart items from order data
        $cart = [];
        $subtotal = 0;
        $items_text = "";
        
        foreach ($order_data['items'] as $item) {
            // Verify the product exists
            $product = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}kwetupizza_products WHERE id = %d",
                $item['product_id']
            ));
            
            if (!$product) {
                continue; // Skip invalid products
            }
            
            $item_total = $product->price * $item['quantity'];
            $subtotal += $item_total;
            
            $cart[] = [
                'product_id' => $product->id,
                'name' => $product->product_name,
                'price' => $product->price,
                'quantity' => $item['quantity'],
                'total' => $item_total
            ];
            
            $items_text .= "{$product->product_name} x {$item['quantity']} = " . 
                kwetupizza_format_currency($item_total) . "\n";
        }
        
        if (empty($cart)) {
            kwetupizza_log("No valid products found for streamlined order", 'error');
            return [
                'success' => false,
                'message' => 'No valid products found in your order'
            ];
        }
        
        // Calculate delivery fee based on zone if provided
        $delivery_fee = 0;
        if (!empty($order_data['delivery_zone_id'])) {
            $zone = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}kwetupizza_delivery_zones WHERE id = %d",
                $order_data['delivery_zone_id']
            ));
            
            if ($zone) {
                $delivery_fee = $zone->delivery_fee;
            }
        }
        
        // Calculate total
        $total = $subtotal + $delivery_fee;
        
        // Prepare context data
        $context = [
            'customer_name' => isset($order_data['customer_name']) ? $order_data['customer_name'] : '',
            'delivery_zone_id' => isset($order_data['delivery_zone_id']) ? $order_data['delivery_zone_id'] : 0,
            'delivery_fee' => $delivery_fee,
            'payment_provider' => isset($order_data['payment_method']) ? $order_data['payment_method'] : 'MPESA'
        ];
        
        // Save order to database
        $order_id = kwetupizza_save_order_to_db($customer_phone, $cart, $order_data['delivery_address'], $total, $context);
        
        if (!$order_id) {
            kwetupizza_log("Failed to save streamlined order to database", 'error');
            return [
                'success' => false,
                'message' => 'Error processing your order'
            ];
        }
        
        // Process payment if payment details are provided
        if (!empty($order_data['payment_phone'])) {
            // Create a unique transaction reference
            $tx_ref = 'order-' . $order_id . '-' . time();
            
            // Update transaction with tx_ref
            $wpdb->update(
                $wpdb->prefix . 'kwetupizza_transactions',
                ['tx_ref' => $tx_ref],
                ['order_id' => $order_id]
            );
            
            // Notify admin of new order
            kwetupizza_notify_admin_of_order($order_id, [
                'customer_name' => $context['customer_name'],
                'customer_phone' => $customer_phone,
                'items' => $items_text,
                'delivery_address' => $order_data['delivery_address'],
                'total' => kwetupizza_format_currency($total)
            ]);
            
            // Add order to timeline
            kwetupizza_add_order_timeline_event($order_id, 'order_placed', 'Order placed, awaiting payment');
            
            // If direct payment processing is requested
            if (isset($order_data['process_payment']) && $order_data['process_payment'] === true) {
                // Get API key
                $flw_secret_key = get_option('kwetupizza_flw_secret_key');
                if (empty($flw_secret_key)) {
                    kwetupizza_log("Missing Flutterwave secret key for streamlined order", 'error');
                    return [
                        'success' => true,
                        'order_id' => $order_id,
                        'payment_status' => 'not_initiated',
                        'message' => 'Order created, but payment not initiated due to configuration issue'
                    ];
                }
                
                // Prepare payment payload
                $payload = [
                    'tx_ref' => $tx_ref,
                    'amount' => $total,
                    'currency' => 'TZS',
                    'network' => strtoupper($context['payment_provider']),
                    'email' => kwetupizza_get_customer_email($customer_phone),
                    'phone_number' => $order_data['payment_phone'],
                    'fullname' => $context['customer_name'],
                    'redirect_url' => kwetupizza_get_callback_url('flutterwave'),
                    'meta' => [
                        'order_id' => $order_id,
                        'customer_phone' => $customer_phone
                    ]
                ];
                
                // Make API request to Flutterwave
                $response = wp_remote_post('https://api.flutterwave.com/v3/charges?type=mobile_money_tanzania', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $flw_secret_key,
                        'Content-Type' => 'application/json'
                    ],
                    'body' => json_encode($payload)
                ]);
                
                if (is_wp_error($response)) {
                    $error_message = $response->get_error_message();
                    kwetupizza_log("Flutterwave API request failed: $error_message", 'error');
                    return [
                        'success' => true,
                        'order_id' => $order_id,
                        'payment_status' => 'failed',
                        'message' => 'Order created, but payment could not be initiated'
                    ];
                }
                
                $response_body = wp_remote_retrieve_body($response);
                $result = json_decode($response_body, true);
                
                if (isset($result['status']) && $result['status'] === 'success') {
                    kwetupizza_update_transaction_reference($order_id, $tx_ref, $result['data']['id']);
                    
                    // Send confirmation message
                    $message = "🍕 *Your Order is Being Processed!* 🍕\n\n";
                    $message .= "Order #$order_id has been created.\n\n";
                    $message .= "📱 Check your phone for a payment prompt from Flutterwave/Mpesa.\n";
                    $message .= "Please enter your PIN to complete the payment of " . kwetupizza_format_currency($total) . ".\n\n";
                    $message .= "Thank you for choosing KwetuPizza! 🍕";
                    
                    kwetupizza_send_whatsapp_message($customer_phone, $message);
                    
                    return [
                        'success' => true,
                        'order_id' => $order_id,
                        'payment_status' => 'initiated',
                        'message' => 'Order created and payment request sent'
                    ];
                } else {
                    $error_message = isset($result['message']) ? $result['message'] : 'Unknown error';
                    kwetupizza_log("Payment initiation failed: $error_message", 'error');
                    
                    return [
                        'success' => true,
                        'order_id' => $order_id,
                        'payment_status' => 'failed',
                        'error' => $error_message,
                        'message' => 'Order created, but payment could not be initiated'
                    ];
                }
            }
        }
        
        // Return success response without payment processing
        return [
            'success' => true,
            'order_id' => $order_id,
            'message' => 'Order created successfully'
        ];
    }
}

/**
 * Send delivery confirmation link to customer
 */
if (!function_exists('kwetupizza_send_delivery_confirmation_request')) {
    function kwetupizza_send_delivery_confirmation_request($order_id) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'kwetupizza_orders';
        
        // Get order data
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT customer_name, customer_phone, delivery_address FROM $orders_table WHERE id = %d",
            $order_id
        ));
        
        if (!$order) {
            kwetupizza_log("Failed to send delivery confirmation request for order ID: $order_id - Order not found", 'error');
            return false;
        }
        
        // Generate a unique confirmation token
        $confirmation_token = md5('kwetupizza_order_' . $order_id . time());
        
        // Store the token in the order
        $wpdb->update(
            $orders_table,
            [
                'confirmation_token' => $confirmation_token,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $order_id]
        );
        
        // Create confirmation link
        $confirmation_link = home_url('/confirm-delivery/?order=' . $order_id . '&token=' . $confirmation_token);
        
        // Craft the WhatsApp message
        $message = "🍕 *KwetuPizza Order Delivered* 🍕\n\n";
        $message .= "Hello {$order->customer_name},\n\n";
        $message .= "Your order #$order_id should have been delivered to you. Please confirm if you've received your order by clicking the link below:\n\n";
        $message .= "$confirmation_link\n\n";
        $message .= "Thank you for choosing KwetuPizza! 🍕";
        
        // Send the notification
        $whatsapp_result = kwetupizza_send_whatsapp_message($order->customer_phone, $message);
        
        // Send SMS as backup
        $sms_message = "KwetuPizza: Your order #$order_id should have been delivered. Please confirm: $confirmation_link";
        $sms_result = kwetupizza_send_nextsms($order->customer_phone, $sms_message);
        
        // Log the notification attempt
        if ($whatsapp_result || $sms_result) {
            kwetupizza_log("Delivery confirmation request sent for order #$order_id", 'info');
            
            // Schedule feedback request after 30 minutes
            wp_schedule_single_event(time() + (30 * MINUTE_IN_SECONDS), 'kwetupizza_send_customer_feedback_request', [$order_id]);
            
            return true;
        } else {
            kwetupizza_log("Failed to send delivery confirmation request for order #$order_id", 'error');
            return false;
        }
    }
}

/**
 * Handle delivery confirmation from customer
 */
if (!function_exists('kwetupizza_handle_delivery_confirmation')) {
    function kwetupizza_handle_delivery_confirmation($order_id, $token) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'kwetupizza_orders';
        
        // Verify the token
        $valid_token = $wpdb->get_var($wpdb->prepare(
            "SELECT confirmation_token FROM $orders_table WHERE id = %d",
            $order_id
        ));
        
        if (!$valid_token || $valid_token !== $token) {
            return [
                'success' => false,
                'message' => 'Invalid or expired confirmation link.'
            ];
        }
        
        // Update order status to confirmed_delivered
        $updated = $wpdb->update(
            $orders_table,
            [
                'status' => 'confirmed_delivered',
                'customer_confirmed_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ],
            ['id' => $order_id]
        );
        
        if ($updated) {
            // Add timeline event
            kwetupizza_add_order_timeline_event($order_id, 'customer_confirmed', 'Customer confirmed delivery receipt');
            
            return [
                'success' => true,
                'message' => 'Thank you for confirming your delivery! We hope you enjoy your meal.'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Unable to confirm delivery. Please try again or contact support.'
            ];
        }
    }
}

/**
 * Send customer feedback request after delivery
 */
if (!function_exists('kwetupizza_send_customer_feedback_request')) {
    function kwetupizza_send_customer_feedback_request($order_id) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'kwetupizza_orders';
        
        // Get order data
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT customer_name, customer_phone, status FROM $orders_table WHERE id = %d",
            $order_id
        ));
        
        if (!$order) {
            kwetupizza_log("Failed to send feedback request for order ID: $order_id - Order not found", 'error');
            return false;
        }
        
        // Only send if the order is delivered or confirmed_delivered
        if ($order->status !== 'delivered' && $order->status !== 'confirmed_delivered') {
            kwetupizza_log("Skipping feedback request for order #$order_id - Order status is {$order->status}", 'info');
            return false;
        }
        
        // Generate a unique feedback token
        $feedback_token = md5('kwetupizza_feedback_' . $order_id . time());
        
        // Store the token in the order
        $wpdb->update(
            $orders_table,
            [
                'feedback_token' => $feedback_token,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $order_id]
        );
        
        // Create feedback link
        $feedback_link = home_url('/order-feedback/?order=' . $order_id . '&token=' . $feedback_token);
        
        // Craft the WhatsApp message
        $message = "🌟 *How was your KwetuPizza experience?* 🌟\n\n";
        $message .= "Hello {$order->customer_name},\n\n";
        $message .= "We hope you enjoyed your meal! We'd love to hear your feedback about your recent order #$order_id.\n\n";
        $message .= "Please rate your experience by visiting:\n$feedback_link\n\n";
        $message .= "Your feedback helps us improve our service. Thank you for choosing KwetuPizza! 🍕";
        
        // Send the notification
        $whatsapp_result = kwetupizza_send_whatsapp_message($order->customer_phone, $message);
        
        // Send SMS as backup
        $sms_message = "KwetuPizza: How was your meal? Please rate your order #$order_id experience: $feedback_link";
        $sms_result = kwetupizza_send_nextsms($order->customer_phone, $sms_message);
        
        // Log the notification attempt
        if ($whatsapp_result || $sms_result) {
            kwetupizza_log("Feedback request sent for order #$order_id", 'info');
            return true;
        } else {
            kwetupizza_log("Failed to send feedback request for order #$order_id", 'error');
            return false;
        }
    }
}

/**
 * Save customer feedback
 */
if (!function_exists('kwetupizza_save_customer_feedback')) {
    function kwetupizza_save_customer_feedback($order_id, $token, $rating, $comments = '') {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'kwetupizza_orders';
        $feedback_table = $wpdb->prefix . 'kwetupizza_feedback';
        
        // Verify the token
        $valid_token = $wpdb->get_var($wpdb->prepare(
            "SELECT feedback_token FROM $orders_table WHERE id = %d",
            $order_id
        ));
        
        if (!$valid_token || $valid_token !== $token) {
            return [
                'success' => false,
                'message' => 'Invalid or expired feedback link.'
            ];
        }
        
        // Save the feedback
        $result = $wpdb->insert(
            $feedback_table,
            [
                'order_id' => $order_id,
                'rating' => $rating,
                'comments' => $comments,
                'created_at' => current_time('mysql')
            ]
        );
        
        if ($result) {
            // Update order status to include feedback
            $wpdb->update(
                $orders_table,
                [
                    'has_feedback' => 1,
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $order_id]
            );
            
            // Add timeline event
            kwetupizza_add_order_timeline_event($order_id, 'customer_feedback', "Customer provided feedback (Rating: $rating/5)");
            
            return [
                'success' => true,
                'message' => 'Thank you for your feedback! We appreciate your input.'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Unable to save your feedback. Please try again or contact support.'
            ];
        }
    }
}

// Register the hook for sending feedback requests
add_action('kwetupizza_send_customer_feedback_request', 'kwetupizza_send_customer_feedback_request');

// Add a hook for sending delivery confirmation
add_action('kwetupizza_send_delivery_confirmation', 'kwetupizza_send_delivery_confirmation_request');

/**
 * Get user by phone number or create a new one if doesn't exist
 */
if (!function_exists('kwetupizza_get_or_create_user')) {
    function kwetupizza_get_or_create_user($phone) {
        global $wpdb;
        $users_table = $wpdb->prefix . 'kwetupizza_users';
        
        // Sanitize phone number
        $phone = kwetupizza_sanitize_phone($phone);
        
        // Check if user exists
        $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $users_table WHERE phone = %s", $phone));
        
        // If user exists, return user data
        if ($user) {
            kwetupizza_log("Existing user found for phone: $phone, Name: {$user->name}", 'info');
            return $user;
        }
        
        // User doesn't exist, create a new one with default name
        $default_name = "Customer-" . substr($phone, -5); // Last 5 digits of phone number
        
        $wpdb->insert(
            $users_table,
            array(
                'name' => $default_name,
                'email' => '',
                'phone' => $phone,
                'role' => 'customer',
                'state' => 'greeting',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            )
        );
        
        if ($wpdb->insert_id) {
            kwetupizza_log("New user created for phone: $phone with name: $default_name", 'info');
            return $wpdb->get_row($wpdb->prepare("SELECT * FROM $users_table WHERE phone = %s", $phone));
        }
        
        // If insert failed, return a basic user object
        kwetupizza_log("Failed to create user record for phone: $phone", 'error');
        $fallback_user = new stdClass();
        $fallback_user->id = 0;
        $fallback_user->name = $default_name;
        $fallback_user->phone = $phone;
        $fallback_user->email = '';
        $fallback_user->role = 'customer';
        $fallback_user->state = 'greeting';
        
        return $fallback_user;
    }
}

/**
 * Personalize message with user's name
 */
if (!function_exists('kwetupizza_personalize_message')) {
    function kwetupizza_personalize_message($phone, $message) {
        // Get user
        $user = kwetupizza_get_or_create_user($phone);
        
        // Extract first name if full name is provided
        $name_parts = explode(' ', $user->name);
        $first_name = $name_parts[0];
        
        // If default customer name, don't personalize
        if (strpos($first_name, 'Customer-') === 0) {
            return $message;
        }
        
        // Add personalization if message doesn't already include name
        if (strpos($message, $first_name) === false) {
            // If message starts with an emoji, preserve it
            if (preg_match('/^(\p{So}\s)/u', $message, $matches)) {
                $emoji = $matches[1];
                $message = preg_replace('/^(\p{So}\s)/u', '', $message, 1);
                return $emoji . "Hi " . $first_name . "! " . $message;
            }
            
            return "Hi " . $first_name . "! " . $message;
        }
        
        return $message;
    }
}

// Update the send_greeting function to use the new personalization
if (!function_exists('kwetupizza_send_greeting')) {
    function kwetupizza_send_greeting($from) {
        // Get or create user
        $user = kwetupizza_get_or_create_user($from);
        
        // Create personalized greeting
        $name_parts = explode(' ', $user->name);
        $first_name = $name_parts[0];
        
        // Personalized message
        if (strpos($first_name, 'Customer-') === 0) {
            $message = "👋 *Hello! Welcome to KwetuPizza* 🍕\n\n";
        } else {
            $message = "👋 *Hello " . $first_name . "! Welcome to KwetuPizza* 🍕\n\n";
        }
        
        $message .= "How can I help you today?\n\n";
        $message .= "📱 *Available Commands:*\n";
        $message .= "• Type *menu* to browse our delicious menu by category\n";
        $message .= "• Type *order* to start a new order\n";
        $message .= "• Type *status* to check your recent order\n";
        $message .= "• Type *help* for assistance\n\n";
        $message .= "Our new interactive ordering system makes it easy to order your favorite pizza with just a few messages! Try it now by typing 'menu' 😊";
        
        // Add a prompt to update name if using default name
        if (strpos($user->name, 'Customer-') === 0) {
            $message .= "\n\n💡 *Tip:* Would you like to tell us your name? Just reply with 'name: [your name]' to personalize your experience!";
        }
        
        kwetupizza_send_whatsapp_message($from, $message);
        
        // Initialize empty context with state set to 'greeting'
        kwetupizza_set_conversation_context($from, ['state' => 'greeting']);
    }
}

// Update send_whatsapp_message to use personalization
if (!function_exists('kwetupizza_send_whatsapp_message')) {
    function kwetupizza_send_whatsapp_message($phone, $message) {
        // Only personalize messages if not already personalized
        if (strpos($message, 'Hi ') !== 0 && strpos($message, 'Hello ') !== 0) {
            $message = kwetupizza_personalize_message($phone, $message);
        }
        
        // Rest of the original function...
        
        // Compatibility with older versions that didn't require API credentials
        $api_key = get_option('kwetupizza_whatsapp_api_key', '');
        $instance_id = get_option('kwetupizza_whatsapp_instance', '');
        
        if (empty($api_key) || empty($instance_id)) {
            // Fallback to hardcoded values (legacy support)
            $api_key = '13YX7UDXCL9DQ2AF';
            $instance_id = '23';
        }
        
        // Format phone to international format
        $phone = kwetupizza_sanitize_phone($phone);
        
        // Prepare API request
        $url = "https://api.ultramsg.com/{$instance_id}/messages/chat";
        $params = array(
            'token' => $api_key,
            'to' => $phone,
            'body' => $message,
            'priority' => 10
        );
        
        // Log the message we're about to send
        kwetupizza_log("Sending WhatsApp message to $phone: " . substr($message, 0, 50) . "...", 'info');
        
        // Make API request
        $response = wp_remote_post($url, array(
            'method' => 'POST',
            'timeout' => 45,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => array('Content-Type' => 'application/x-www-form-urlencoded'),
            'body' => $params,
            'cookies' => array()
        ));
        
        // Check for successful response
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            kwetupizza_log("WhatsApp API error: $error_message", 'error');
            return false;
        } else {
            $body = wp_remote_retrieve_body($response);
            $result = json_decode($body);
            
            if (isset($result->sent) && $result->sent === 'true') {
                kwetupizza_log("WhatsApp message sent successfully to $phone", 'info');
                return true;
            } else {
                kwetupizza_log("WhatsApp API returned error: " . print_r($result, true), 'error');
                return false;
            }
        }
    }
}

// Add a name update handler to the message handling function
if (!function_exists('kwetupizza_handle_whatsapp_message')) {
    function kwetupizza_handle_whatsapp_message($from, $message) {
        $message = trim($message);
        $lowercase_message = strtolower($message);
        $context = kwetupizza_get_conversation_context($from);

        // Log the incoming message and context
        kwetupizza_log_context_and_input($from, $message);

        // Check if this is a name update request
        if (strpos($lowercase_message, 'name:') === 0) {
            // Extract the name after "name:"
            $name = trim(substr($message, 5));
            
            if (!empty($name)) {
                global $wpdb;
                $users_table = $wpdb->prefix . 'kwetupizza_users';
                
                // Update user's name
                $wpdb->update(
                    $users_table,
                    array('name' => $name),
                    array('phone' => kwetupizza_sanitize_phone($from))
                );
                
                kwetupizza_send_whatsapp_message($from, "Thanks for sharing your name! I'll call you $name from now on. 😊\n\nIs there anything else I can help you with today?");
                kwetupizza_set_conversation_context($from, ['state' => 'greeting']);
                return;
            }
        }

        // If context is empty, greet the user
        if (empty($context)) {
            kwetupizza_send_greeting($from);
            return;
        }

        // Handle different awaiting states
        if (isset($context['awaiting'])) {
            switch ($context['awaiting']) {
                case 'user_name':
                    kwetupizza_handle_user_name_input($from, $message);
                    return;
                case 'user_email':
                    kwetupizza_handle_user_email_input($from, $message);
                    return;
                case 'category_selection':
                    kwetupizza_handle_category_selection($from, $lowercase_message);
                    return;
                case 'menu_selection':
                    kwetupizza_process_order($from, $lowercase_message);
                    return;
                case 'quantity':
                    kwetupizza_confirm_order_and_request_address($from, $context['cart'][count($context['cart']) - 1]['product_id'], $message);
                    return;
                case 'add_or_checkout':
                    kwetupizza_handle_add_or_checkout($from, $lowercase_message);
                    return;
                case 'delivery_zone':
                    kwetupizza_handle_delivery_zone_selection($from, $lowercase_message);
                    return;
                case 'full_address':
                    kwetupizza_handle_address_and_ask_payment_provider($from, $message);
                    return;
                case 'payment_provider':
                    kwetupizza_handle_payment_provider_response($from, $lowercase_message);
                    return;
                case 'use_whatsapp_number':
                    kwetupizza_handle_use_whatsapp_number_response($from, $lowercase_message);
                    return;
                case 'payment_phone':
                    kwetupizza_handle_payment_phone_input($from, $message);
                    return;
            }
        }

        // Regular message handling for other cases
        if (in_array($lowercase_message, ['hi', 'hello', 'hey', 'start'])) {
            kwetupizza_send_greeting($from);
        } elseif ($lowercase_message === 'menu' || $lowercase_message === 'order') {
            kwetupizza_send_menu_categories($from);
        } elseif ($lowercase_message === 'help') {
            kwetupizza_send_help_message($from);
        } elseif (strpos($lowercase_message, 'status') !== false || strpos($lowercase_message, 'my order') !== false) {
            kwetupizza_check_order_status($from);
        } else {
            kwetupizza_send_default_message($from);
        }
    }
}

/**
 * Handle PayPal/card payment flow
 */
if (!function_exists('kwetupizza_handle_paypal_payment')) {
    function kwetupizza_handle_paypal_payment($from) {
        $context = kwetupizza_get_conversation_context($from);
        $user = kwetupizza_get_or_create_user($from);
        
        // Calculate total order amount
        $total = 0;
        $delivery_fee = isset($context['delivery_fee']) ? floatval($context['delivery_fee']) : 0;
        
        foreach ($context['cart'] as $item) {
            $total += $item['price'] * $item['quantity'];
        }
        
        // Add delivery fee
        $total += $delivery_fee;
        
        // Save order to database before payment initiation
        $order_id = kwetupizza_save_order_to_db($from, $context['cart'], $context['address'], $total, $context);
        
        if (!$order_id) {
            kwetupizza_log("ERROR: Failed to save order to database for PayPal payment", 'error', 'payment.log');
            kwetupizza_send_whatsapp_message($from, "⚠️ Error processing your order. Please try again later or contact support.");
            return false;
        }
        
        // Generate unique payment reference
        $tx_ref = 'order-' . $order_id . '-' . time();
        $payment_link = kwetupizza_generate_paypal_payment_link($order_id, $tx_ref, $total, $user);
        
        if (!$payment_link) {
            kwetupizza_log("ERROR: Failed to generate PayPal payment link", 'error', 'payment.log');
            kwetupizza_send_whatsapp_message($from, "⚠️ Error setting up card payment. Please try another payment method or contact support.");
            return false;
        }
        
        // Update transaction record with payment info
        kwetupizza_update_transaction_reference($order_id, $tx_ref, '');
        
        // Send message with payment link
        $message = "🔒 *Secure Card Payment* 🔒\n\n";
        $message .= "Your order #$order_id has been created!\n\n";
        $message .= "To complete your payment, please click the link below to pay with card via PayPal (no PayPal account required):\n\n";
        $message .= $payment_link . "\n\n";
        $message .= "After completing payment, you'll receive a confirmation message.\n\n";
        $message .= "Need help? Contact our support team.";
        
        kwetupizza_send_whatsapp_message($from, $message);
        
        // Reset conversation context
        kwetupizza_set_conversation_context($from, ['state' => 'awaiting_payment', 'order_id' => $order_id]);
        
        return true;
    }
}

/**
 * Generate PayPal payment link
 */
if (!function_exists('kwetupizza_generate_paypal_payment_link')) {
    function kwetupizza_generate_paypal_payment_link($order_id, $tx_ref, $amount, $user) {
        // Get PayPal credentials from settings
        $paypal_client_id = get_option('kwetupizza_paypal_client_id', '');
        $is_sandbox = get_option('kwetupizza_paypal_sandbox', true);
        
        if (empty($paypal_client_id)) {
            kwetupizza_log("ERROR: PayPal client ID not configured", 'error', 'payment.log');
            return false;
        }
        
        // Determine API endpoint based on sandbox mode
        $api_base = $is_sandbox ? 
            'https://api-m.sandbox.paypal.com' : 
            'https://api-m.paypal.com';
        
        // Build callback URL for PayPal success
        $return_url = home_url('/wp-json/kwetupizza/v1/paypal-success?order_id=' . $order_id . '&tx_ref=' . $tx_ref);
        $cancel_url = home_url('/wp-json/kwetupizza/v1/paypal-cancel?order_id=' . $order_id);
        
        // Create a simple checkout page URL for the customer (this is a simplified approach)
        // In a production environment, you would use PayPal's SDK or API to generate a proper payment
        $currency = get_option('kwetupizza_currency', 'USD');
        $site_url = home_url('/paypal-checkout');
        
        $checkout_url = add_query_arg(array(
            'order_id' => $order_id,
            'tx_ref' => $tx_ref,
            'amount' => $amount,
            'currency' => $currency,
            'client_id' => $paypal_client_id,
            'sandbox' => $is_sandbox ? '1' : '0',
            'return_url' => urlencode($return_url),
            'cancel_url' => urlencode($cancel_url),
            'customer_name' => urlencode($user->name),
            'customer_email' => urlencode($user->email)
        ), $site_url);
        
        return $checkout_url;
    }
}

/**
 * Handle PayPal payment success webhook
 */
if (!function_exists('kwetupizza_paypal_success_webhook')) {
    function kwetupizza_paypal_success_webhook(WP_REST_Request $request) {
        // Log that webhook was triggered
        kwetupizza_log('PayPal success webhook triggered', 'info', 'paypal-webhook.log');
        
        // Get parameters
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
        $tx_ref = isset($_GET['tx_ref']) ? sanitize_text_field($_GET['tx_ref']) : '';
        $paypal_order_id = isset($_GET['paypal_order_id']) ? sanitize_text_field($_GET['paypal_order_id']) : '';
        
        if (empty($order_id) || empty($tx_ref) || empty($paypal_order_id)) {
            kwetupizza_log('Missing required parameters in PayPal success webhook', 'error', 'paypal-webhook.log');
            return new WP_REST_Response('Missing required parameters', 400);
        }
        
        // Verify the PayPal payment
        if (kwetupizza_verify_paypal_payment($paypal_order_id)) {
            kwetupizza_log("PayPal payment verified for order ID: $order_id, PayPal order ID: $paypal_order_id", 'info', 'paypal-webhook.log');
            
            // Update transaction status in database
            global $wpdb;
            $transactions_table = $wpdb->prefix . 'kwetupizza_transactions';
            
            $wpdb->update(
                $transactions_table,
                [
                    'payment_status' => 'completed',
                    'transaction_reference' => $paypal_order_id,
                    'updated_at' => current_time('mysql')
                ],
                ['order_id' => $order_id]
            );
            
            // Update order status
            $orders_table = $wpdb->prefix . 'kwetupizza_orders';
            $wpdb->update(
                $orders_table,
                [
                    'status' => 'processing',
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $order_id]
            );
            
            // Get order details for notification
            $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM $orders_table WHERE id = %d", $order_id));
            
            // Send success notifications
            kwetupizza_notify_customer($order_id, 'payment_confirmed');
            kwetupizza_notify_admin_of_payment($order_id, true, $order->total);
            
            // Redirect to thank you page
            wp_redirect(get_permalink(get_page_by_path('thank-you')));
            exit;
        } else {
            // Payment verification failed
            kwetupizza_log("PayPal payment verification failed for order ID: $order_id", 'error', 'paypal-webhook.log');
            
            // Redirect to retry payment page
            wp_redirect(add_query_arg(['order_id' => $order_id], get_permalink(get_page_by_path('retry-payment'))));
            exit;
        }
    }
}

/**
 * Verify PayPal payment
 */
if (!function_exists('kwetupizza_verify_paypal_payment')) {
    function kwetupizza_verify_paypal_payment($paypal_order_id) {
        // Get PayPal credentials from settings
        $paypal_client_id = get_option('kwetupizza_paypal_client_id', '');
        $paypal_secret = get_option('kwetupizza_paypal_secret', '');
        $is_sandbox = get_option('kwetupizza_paypal_sandbox', true);
        
        if (empty($paypal_client_id) || empty($paypal_secret)) {
            kwetupizza_log('PayPal credentials not configured', 'error', 'paypal-webhook.log');
            return false;
        }
        
        // Determine API endpoint based on sandbox mode
        $api_base = $is_sandbox ? 
            'https://api-m.sandbox.paypal.com' : 
            'https://api-m.paypal.com';
        
        // Get OAuth token
        $token_url = $api_base . '/v1/oauth2/token';
        $token_response = wp_remote_post($token_url, [
            'method' => 'POST',
            'headers' => [
                'Accept' => 'application/json',
                'Accept-Language' => 'en_US',
                'Authorization' => 'Basic ' . base64_encode($paypal_client_id . ':' . $paypal_secret)
            ],
            'body' => 'grant_type=client_credentials'
        ]);
        
        if (is_wp_error($token_response)) {
            kwetupizza_log('PayPal OAuth error: ' . $token_response->get_error_message(), 'error', 'paypal-webhook.log');
            return false;
        }
        
        $token_data = json_decode(wp_remote_retrieve_body($token_response), true);
        
        if (!isset($token_data['access_token'])) {
            kwetupizza_log('Failed to get PayPal access token', 'error', 'paypal-webhook.log');
            return false;
        }
        
        $access_token = $token_data['access_token'];
        
        // Verify the order
        $order_url = $api_base . '/v2/checkout/orders/' . $paypal_order_id;
        $order_response = wp_remote_get($order_url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $access_token
            ]
        ]);
        
        if (is_wp_error($order_response)) {
            kwetupizza_log('PayPal order verification error: ' . $order_response->get_error_message(), 'error', 'paypal-webhook.log');
            return false;
        }
        
        $order_data = json_decode(wp_remote_retrieve_body($order_response), true);
        
        // Check if payment is completed
        if (isset($order_data['status']) && $order_data['status'] === 'COMPLETED') {
            kwetupizza_log('PayPal order verification successful. Status: ' . $order_data['status'], 'info', 'paypal-webhook.log');
            return true;
        }
        
        kwetupizza_log('PayPal order verification failed. Status: ' . (isset($order_data['status']) ? $order_data['status'] : 'unknown'), 'error', 'paypal-webhook.log');
        return false;
    }
}

/**
 * Handle address input and ask for payment provider
 */
if (!function_exists('kwetupizza_handle_address_and_ask_payment_provider')) {
    function kwetupizza_handle_address_and_ask_payment_provider($from, $address) {
        $context = kwetupizza_get_conversation_context($from);
        
        if (empty(trim($address))) {
            kwetupizza_send_whatsapp_message($from, "Please provide a valid delivery address to continue with your order.");
            return;
        }
        
        // Save address to context
        $context['address'] = $address;
        
        // Request payment provider selection
        $message = "Great! Now, please select your preferred payment method:\n\n";
        $message .= "1. Vodacom M-Pesa\n";
        $message .= "2. Tigo Pesa\n";
        $message .= "3. Airtel Money\n";
        $message .= "4. Halo Pesa\n";
        $message .= "5. Card Payment (PayPal)";
        
        kwetupizza_send_whatsapp_message($from, $message);
        
        // Update context to expect payment provider selection
        kwetupizza_set_conversation_context($from, array_merge($context, ['awaiting' => 'payment_provider']));
    }
}

// ========================
// PAYPAL FUNCTIONS
// ========================

/**
 * Register PayPal API routes
 * This function adds routes for PayPal success and cancel webhooks
 */
if (!function_exists('kwetupizza_register_paypal_routes')) {
    function kwetupizza_register_paypal_routes() {
        // PayPal success webhook
        register_rest_route('kwetupizza/v1', '/paypal-success', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'kwetupizza_paypal_success_webhook',
            'permission_callback' => '__return_true',
        ));
        
        // PayPal cancel webhook
        register_rest_route('kwetupizza/v1', '/paypal-cancel', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'kwetupizza_paypal_cancel_webhook',
            'permission_callback' => '__return_true',
        ));
    }
}

/**
 * Add PayPal routes to the main API registration function
 */
if (!function_exists('kwetupizza_register_api_routes')) {
    function kwetupizza_register_api_routes() {
        // Register existing routes
        // ...
        
        // Register PayPal routes
        kwetupizza_register_paypal_routes();
    }
}

/**
 * PayPal cancel webhook handler
 */
if (!function_exists('kwetupizza_paypal_cancel_webhook')) {
    function kwetupizza_paypal_cancel_webhook(WP_REST_Request $request) {
        // Log the cancellation
        kwetupizza_log('PayPal cancel webhook triggered', 'info', 'paypal-webhook.log');
        
        // Get order ID
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
        
        if (empty($order_id)) {
            return new WP_REST_Response('Missing order ID', 400);
        }
        
        // Process the cancellation
        kwetupizza_handle_failed_payment($order_id, 'Payment was cancelled by the customer');
        
        // Redirect to retry payment page
        wp_redirect(add_query_arg(['order_id' => $order_id], get_permalink(get_page_by_path('retry-payment'))));
        exit;
    }
}

/**
 * Generate PayPal checkout link
 * Creates a URL to the PayPal checkout page with required parameters
 */
if (!function_exists('kwetupizza_generate_paypal_payment_link')) {
    function kwetupizza_generate_paypal_payment_link($order_id, $tx_ref, $amount, $user) {
        // Get PayPal credentials from settings
        $paypal_client_id = get_option('kwetupizza_paypal_client_id', '');
        $is_sandbox = get_option('kwetupizza_paypal_sandbox', true);
        
        if (empty($paypal_client_id)) {
            kwetupizza_log("ERROR: PayPal client ID not configured", 'error', 'payment.log');
            return false;
        }
        
        // Sanitize user data
        $customer_name = isset($user['user_name']) ? sanitize_text_field($user['user_name']) : '';
        $customer_email = isset($user['user_email']) ? sanitize_email($user['user_email']) : '';
        
        // Build callback URL for PayPal success
        $return_url = home_url('/wp-json/kwetupizza/v1/paypal-success?order_id=' . $order_id . '&tx_ref=' . $tx_ref);
        $cancel_url = home_url('/wp-json/kwetupizza/v1/paypal-cancel?order_id=' . $order_id);
        
        // Create a simple checkout page URL for the customer (this is a simplified approach)
        // In a production environment, you would use PayPal's SDK or API to generate a proper payment
        $currency = get_option('kwetupizza_currency', 'USD');
        $site_url = home_url('/paypal-checkout');
        
        $checkout_url = add_query_arg(array(
            'order_id' => $order_id,
            'tx_ref' => $tx_ref,
            'amount' => $amount,
            'currency' => $currency,
            'client_id' => $paypal_client_id,
            'sandbox' => $is_sandbox ? '1' : '0',
            'return_url' => urlencode($return_url),
            'cancel_url' => urlencode($cancel_url),
            'customer_name' => urlencode($customer_name),
            'customer_email' => urlencode($customer_email)
        ), $site_url);
        
        return $checkout_url;
    }
}

/**
 * Handle PayPal payment method selection
 */
if (!function_exists('kwetupizza_handle_payment_provider_paypal')) {
    function kwetupizza_handle_payment_provider_paypal($from, $context) {
        global $wpdb;
        
        // Create an order with the cart data
        $cart = isset($context['cart']) ? $context['cart'] : [];
        $delivery_address = isset($context['delivery_address']) ? $context['delivery_address'] : '';
        $delivery_phone = isset($context['delivery_phone']) ? $context['delivery_phone'] : $from;
        $user_name = isset($context['user_name']) ? $context['user_name'] : '';
        $user_email = isset($context['user_email']) ? $context['user_email'] : '';
        
        // Calculate total
        $total = 0;
        foreach ($cart as $item) {
            $total += $item['price'] * $item['quantity'];
        }
        
        // Add delivery fee if applicable
        if (isset($context['delivery_fee']) && $context['delivery_fee'] > 0) {
            $total += $context['delivery_fee'];
        }
        
        $tx_ref = 'KP-PAYPAL-' . time() . '-' . kwetupizza_generate_random_string(5);
        
        // Create order
        $order_id = kwetupizza_create_order([
            'cart' => $cart,
            'user_name' => $user_name,
            'user_phone' => $from,
            'user_email' => $user_email,
            'delivery_address' => $delivery_address,
            'delivery_phone' => $delivery_phone,
            'total' => $total,
            'payment_provider' => 'PayPal',
            'tx_ref' => $tx_ref
        ]);
        
        if (!$order_id) {
            kwetupizza_send_whatsapp_message($from, "❌ Sorry, there was an error creating your order. Please try again.");
            return;
        }
        
        // Get PayPal checkout link
        $checkout_link = kwetupizza_generate_paypal_payment_link($order_id, $tx_ref, $total, [
            'user_name' => $user_name,
            'user_email' => $user_email
        ]);
        
        if (!$checkout_link) {
            kwetupizza_send_whatsapp_message($from, "❌ Sorry, our PayPal payment gateway is not properly configured. Please try another payment method or contact support.");
            return;
        }
        
        // Send payment instructions
        $message = "🍕 *Your KwetuPizza Order #$order_id*\n\n";
        $message .= "Thank you for your order, $user_name!\n\n";
        $message .= "Total: " . kwetupizza_format_currency($total) . "\n\n";
        $message .= "Please complete your payment using PayPal:\n";
        $message .= $checkout_link . "\n\n";
        $message .= "This link will expire in 30 minutes.\n\n";
        $message .= "If you have any issues, please contact our support.";
        
        kwetupizza_send_whatsapp_message($from, $message);
        
        // Set the awaiting context to order completion
        kwetupizza_set_conversation_context($from, ['awaiting' => 'order_completion', 'order_id' => $order_id]);
    }
}

/**
 * Handle payment provider selection
 */
if (!function_exists('kwetupizza_handle_payment_provider')) {
    function kwetupizza_handle_payment_provider($from, $message) {
        $context = kwetupizza_get_conversation_context($from);
        $message = strtolower(trim($message));
        
        // Get first name if available
        $first_name = isset($context['first_name']) ? $context['first_name'] : '';
        
        if ($message === '1' || strpos($message, 'mobile') !== false || strpos($message, 'flutterwave') !== false) {
            // Mobile Money via Flutterwave
            kwetupizza_handle_payment_provider_flutterwave($from, $context);
        } else if ($message === '2' || strpos($message, 'paypal') !== false || strpos($message, 'card') !== false) {
            // PayPal payment
            kwetupizza_handle_payment_provider_paypal($from, $context);
        } else {
            // Invalid selection
            if (!empty($first_name)) {
                $message = "🤔 I didn't understand your payment choice, {$first_name}. Please select one of the following:\n";
            } else {
                $message = "🤔 I didn't understand your payment choice. Please select one of the following:\n";
            }
            
            $message .= "1. Mobile Money (Tanzania)\n";
            $message .= "2. PayPal / Credit Card\n\n";
            
            if (!empty($first_name)) {
                $message .= "{$first_name}, simply type the number or name of your preferred payment method.";
            } else {
                $message .= "Simply type the number or name of your preferred payment method.";
            }
            
            kwetupizza_send_whatsapp_message($from, $message);
        }
    }
}

/**
 * Finalize an order by processing delivery details and requesting payment method
 */
if (!function_exists('kwetupizza_finalize_order')) {
    function kwetupizza_finalize_order($from, $context) {
        // Calculate total from cart
        $cart = isset($context['cart']) ? $context['cart'] : [];
        $total = 0;
        
        foreach ($cart as $item) {
            $total += $item['price'] * $item['quantity'];
        }
        
        // Add delivery fee if applicable
        if (isset($context['delivery_fee']) && $context['delivery_fee'] > 0) {
            $total += $context['delivery_fee'];
        }
        
        // Format a nice order summary
        $summary = "*Order Summary*\n\n";
        
        foreach ($cart as $index => $item) {
            $item_total = $item['price'] * $item['quantity'];
            $summary .= "{$item['quantity']}x {$item['name']} - " . kwetupizza_format_currency($item_total) . "\n";
        }
        
        if (isset($context['delivery_fee']) && $context['delivery_fee'] > 0) {
            $summary .= "\nDelivery Fee: " . kwetupizza_format_currency($context['delivery_fee']) . "\n";
        }
        
        $summary .= "\n*Total: " . kwetupizza_format_currency($total) . "*\n\n";
        $summary .= "*Delivery to:*\n{$context['delivery_address']}\n\n";
        
        // Get user's first name if available in context
        $first_name = isset($context['first_name']) ? $context['first_name'] : '';
        
        // Ask for payment method with personalization if name is available
        if (!empty($first_name)) {
            $message = "🍕 Great job, {$first_name}! We have everything we need to place your order.\n\n";
        } else {
            $message = "🍕 Great! We have everything we need to place your order.\n\n";
        }
        
        $message .= $summary;
        $message .= "Please select your payment method:\n\n";
        $message .= "1. Mobile Money (Tanzania)\n";
        $message .= "2. PayPal / Credit Card\n\n";
        
        if (!empty($first_name)) {
            $message .= "{$first_name}, simply type the number or name of your preferred payment method.";
        } else {
            $message .= "Simply type the number or name of your preferred payment method.";
        }
        
        kwetupizza_send_whatsapp_message($from, $message);
        
        // Update the context to await payment provider choice while preserving user info
        kwetupizza_set_conversation_context($from, array_merge($context, ['awaiting' => 'payment_provider']));
    }
}

/**
 * Start conversation with proper user flow check
 */
if (!function_exists('kwetupizza_start_conversation')) {
    function kwetupizza_start_conversation($from) {
        // Check if user exists
        $user = kwetupizza_get_user_by_phone($from);
        
        if ($user) {
            // User exists - greet them by name and show menu
            $name_parts = explode(' ', $user->name);
            $first_name = $name_parts[0];
            
            // Skip personalization for default names
            if (strpos($first_name, 'Customer-') === 0) {
                $message = "👋 *Welcome back to KwetuPizza!* 🍕\n\n";
            } else {
                $message = "👋 *Welcome back, {$first_name}!* 🍕\n\n";
            }
            
            $message .= "What would you like to order today?\n\n";
            
            // Send greeting and menu
            kwetupizza_send_whatsapp_message($from, $message);
            kwetupizza_send_menu_categories($from);
        } else {
            // New user - start registration flow
            kwetupizza_start_new_user_registration($from);
        }
    }
}

/**
 * Start registration for new users
 */
if (!function_exists('kwetupizza_start_new_user_registration')) {
    function kwetupizza_start_new_user_registration($from) {
        // Sanitize the phone number for display
        $sanitized_phone = kwetupizza_sanitize_phone($from);
        
        $message = "👋 *Welcome to KwetuPizza* 🍕\n\n";
        $message .= "It looks like this is your first time ordering with us. Let's set up your account quickly.\n\n";
        $message .= "First, please tell us your name:";
        
        kwetupizza_send_whatsapp_message($from, $message);
        
        // Set context to expect name input
        kwetupizza_set_conversation_context($from, [
            'awaiting' => 'registration_name', 
            'phone' => $sanitized_phone,
            'registration_flow' => 'menu' // Indicates where to go after registration
        ]);
    }
}

/**
 * Check if a message is a greeting
 */
if (!function_exists('kwetupizza_is_greeting')) {
    function kwetupizza_is_greeting($message) {
        $message = strtolower(trim($message));
        $greetings = array(
            'hi', 'hello', 'hey', 'hola', 'howdy', 'good morning', 'good afternoon', 
            'good evening', 'morning', 'afternoon', 'evening', 'whats up', "what's up",
            'yo', 'greetings', 'sup', 'salaam', 'salam', 'jambo', 'habari', 'mambo'
        );
        
        foreach ($greetings as $greeting) {
            if (strpos($message, $greeting) !== false || $message === $greeting) {
                return true;
            }
        }
        
        return false;
    }
}

/**
 * Default response for unrecognized messages 
 * This was missing and causing the fatal error
 */
if (!function_exists('kwetupizza_send_default_response')) {
    function kwetupizza_send_default_response($from) {
        kwetupizza_send_whatsapp_message($from, "I'm not sure what you want to do. Please type 'menu' to browse our menu, 'help' for assistance, or 'status' to check your recent order.");
    }
}