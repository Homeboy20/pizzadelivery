<?php
/**
 * KwetuPizza Core Functions
 * 
 * This file contains all the core functions for the KwetuPizza plugin.
 * Functions are organized by category for better maintainability.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

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

        // Include WordPress upgrade functions
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    // Users Table
    $users_table = $wpdb->prefix . 'kwetupizza_users';
    $sql = "CREATE TABLE IF NOT EXISTS $users_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(100) NOT NULL,
        email varchar(100) NOT NULL,
        phone varchar(20) NOT NULL,
        role varchar(20) NOT NULL,
        state varchar(255) DEFAULT 'greeting' NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY phone (phone),
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
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
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
            'post_type' => 'page',
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
            'post_type' => 'page',
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
            'post_type' => 'page',
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
            'post_type' => 'page',
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
            'post_type' => 'page',
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
    $token = get_option('kwetupizza_whatsapp_token');
    $phone_id = get_option('kwetupizza_whatsapp_phone_id');
    
    if (empty($token) || empty($phone_id)) {
        error_log('WhatsApp API credentials not set');
        return false;
    }
    
    // Sanitize phone number
    $phone = kwetupizza_sanitize_phone($phone);
    
    // WhatsApp Cloud API endpoint
    $url = "https://graph.facebook.com/v17.0/{$phone_id}/messages";

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
        error_log('WhatsApp API Error: ' . $response->get_error_message());
        return false;
    }
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    // Log the response for debugging
    error_log('WhatsApp API Response: ' . print_r($body, true));
    
    // Check for successful response
    if (isset($body['messages']) && !empty($body['messages'])) {
        return true;
    }
    
    return false;
}

/**
 * Send SMS via NextSMS
 */
function kwetupizza_send_nextsms($phone, $message) {
    $username = get_option('kwetupizza_nextsms_username');
    $password = get_option('kwetupizza_nextsms_password');
    $sender_id = get_option('kwetupizza_nextsms_sender_id', 'KwetuPizza');
    
    if (empty($username) || empty($password)) {
        error_log('NextSMS credentials not set');
        return false;
    }
    
    // Sanitize phone number
    $phone = kwetupizza_sanitize_phone($phone);
    
    // NextSMS API endpoint
    $url = 'https://messaging-service.co.tz/api/sms/v1/text/single';
    
    // Setup the request payload
    $data = array(
        'source_addr' => $sender_id,
        'encoding' => 0,
        'message' => $message,
        'recipients' => array(
            array('recipient_id' => 1, 'dest_addr' => $phone)
        )
    );
    
    // Send the request
    $response = wp_remote_post($url, array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode($username . ':' . $password)
        ),
        'body' => json_encode($data),
        'timeout' => 30
    ));
    
    // Check for errors
    if (is_wp_error($response)) {
        error_log('NextSMS Error: ' . $response->get_error_message());
        return false;
    }
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    // Log the response for debugging
    error_log('NextSMS Response: ' . print_r($body, true));
    
    // Check for successful response
    if (isset($body['successful']) && $body['successful']) {
        return true;
    }
    
    return false;
}

/**
 * Notify admin about new orders or payment status
 */
function kwetupizza_notify_admin($order_id, $success = true) {
    global $wpdb;
    $orders_table = $wpdb->prefix . 'kwetupizza_orders';
    $order_items_table = $wpdb->prefix . 'kwetupizza_order_items';
    
    $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM $orders_table WHERE id = %d", $order_id));
    
    if (!$order) {
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
    
    if (!empty($admin_phone)) {
        kwetupizza_send_whatsapp_message($admin_phone, $message);
    }
    
    $admin_sms = get_option('kwetupizza_admin_sms');
    if (!empty($admin_sms)) {
        // Simplified message for SMS due to length constraints
        $sms_message = "Order #$order_id: {$order->customer_name}, {$order->customer_phone}, " . 
                       kwetupizza_format_currency($order->total, $order->currency) . ". Payment: $status";
        kwetupizza_send_nextsms($admin_sms, $sms_message);
    }
    
    return true;
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
if (!function_exists('kwetupizza_send_payment_failed_notification')) {
    function kwetupizza_send_payment_failed_notification($order_id) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'kwetupizza_orders';
        
        $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM $orders_table WHERE id = %d", $order_id));
        
        if (!$order) {
            return false;
        }
        
        $retry_url = add_query_arg(
            array('order_id' => $order_id),
            get_permalink(get_page_by_path('retry-payment'))
        );
        
        $message = "Your payment for Order #{$order_id} has failed. Please click the link below to retry the payment:\n\n";
        $message .= $retry_url;
        
        return kwetupizza_send_whatsapp_message($order->customer_phone, $message);
    }
}

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
    $order_id = intval(str_replace('order-', '', $tx_ref));
    
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
        // Verify webhook signature
        if (!kwetupizza_verify_flutterwave_signature($request)) {
            kwetupizza_log('Invalid Flutterwave webhook signature', 'error', 'flutterwave-webhook.log');
            return new WP_REST_Response('Invalid signature', 401);
        }
        
        $webhook_data = $request->get_json_params();
        kwetupizza_log('Flutterwave webhook received: ' . json_encode($webhook_data), 'info', 'flutterwave-webhook.log');
        
        if (empty($webhook_data)) {
            return new WP_REST_Response('Invalid data received', 400);
        }
        
        if (isset($webhook_data['event']) && $webhook_data['event'] === 'charge.completed') {
            $status = $webhook_data['data']['status'];
            $transaction_id = $webhook_data['data']['id'];
            
            if ($status === 'successful') {
                // Verify payment with Flutterwave API
                $verification_data = kwetupizza_verify_payment($transaction_id);
                
                if ($verification_data) {
                    kwetupizza_process_successful_payment($verification_data);
                    return new WP_REST_Response('Payment processed successfully', 200);
                } else {
                    kwetupizza_log('Payment verification failed for transaction ID: ' . $transaction_id, 'error', 'flutterwave-webhook.log');
                    return new WP_REST_Response('Payment verification failed', 400);
                }
            } elseif ($status === 'failed') {
                $tx_ref = $webhook_data['data']['tx_ref'];
                kwetupizza_handle_failed_payment($tx_ref);
                return new WP_REST_Response('Payment failed', 400);
            }
        }
        
        return new WP_REST_Response('Event not supported', 400);
    }
}

/**
 * Handle failed payment
 */
if (!function_exists('kwetupizza_handle_failed_payment')) {
    function kwetupizza_handle_failed_payment($tx_ref) {
        global $wpdb;
        $transactions_table = $wpdb->prefix . 'kwetupizza_transactions';
        $orders_table = $wpdb->prefix . 'kwetupizza_orders';
        
        $order_id = intval(str_replace('order-', '', $tx_ref));
        
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
        
        // Send payment failed notification
        kwetupizza_send_payment_failed_notification($order_id);
        
        // Notify admin
        kwetupizza_notify_admin($order_id, false);
        
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
        $message = strtolower(trim($message));
        $context = kwetupizza_get_conversation_context($from);

        // If context is empty, greet the user
        if (empty($context)) {
            kwetupizza_send_greeting($from);
            return;
        }

        if (in_array($message, ['hi', 'hello', 'hey'])) {
            kwetupizza_send_greeting($from);
        } elseif ($message === 'menu') {
            kwetupizza_send_full_menu($from);
        } elseif ($message === 'help') {
            kwetupizza_send_help_message($from);
        } elseif (strpos($message, 'status') !== false || strpos($message, 'my order') !== false) {
            kwetupizza_check_order_status($from);
        } elseif (is_numeric($message) && isset($context['awaiting']) && $context['awaiting'] === 'menu_selection') {
            kwetupizza_process_order($from, $message);
        } elseif (isset($context['awaiting']) && $context['awaiting'] === 'quantity') {
            kwetupizza_confirm_order_and_request_address($from, $context['cart'][count($context['cart']) - 1]['product_id'], $message);
        } elseif (isset($context['awaiting']) && $context['awaiting'] === 'address') {
            kwetupizza_handle_address_and_ask_payment_provider($from, $message);
        } elseif (isset($context['awaiting']) && $context['awaiting'] === 'add_or_checkout') {
            kwetupizza_handle_add_or_checkout($from, $message);
        } elseif (isset($context['awaiting']) && $context['awaiting'] === 'payment_provider') {
            kwetupizza_handle_payment_provider_response($from, $message);
        } elseif (isset($context['awaiting']) && $context['awaiting'] === 'use_whatsapp_number') {
            kwetupizza_handle_use_whatsapp_number_response($from, $message);
        } elseif (isset($context['awaiting']) && $context['awaiting'] === 'payment_phone') {
            kwetupizza_handle_payment_phone_input($from, $message);
        } else {
            kwetupizza_send_default_message($from);
        }
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
            kwetupizza_send_full_menu($from);
            $context['awaiting'] = 'menu_selection';
        } elseif ($response === 'checkout') {
            $total = 0;
            $summary_message = "Here is your order summary:\n";
            foreach ($context['cart'] as $cart_item) {
                $summary_message .= $cart_item['quantity'] . " x " . $cart_item['product_name'] . " - " . number_format($cart_item['total'], 2) . " TZS\n";
                $total += $cart_item['total'];
            }
            $summary_message .= "\nTotal: " . number_format($total, 2) . " TZS\n";
            $summary_message .= "Please provide your delivery address.";

            kwetupizza_send_whatsapp_message($from, $summary_message);
            $context['total'] = $total;
            $context['awaiting'] = 'address';
        } else {
            kwetupizza_send_whatsapp_message($from, "Sorry, I didn't understand that. Type 'add' to add more items or 'checkout' to proceed.");
            return;
        }

        // Update the conversation context
        kwetupizza_set_conversation_context($from, $context);
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

            // Ask which payment network provider the customer will use
            $message = "Which Mobile Money network would you like to use for payment? Reply with one of the following: Vodacom, Tigo, Halopesa, or Airtel";
            kwetupizza_send_whatsapp_message($from, $message);

            // Set the context to expect a network provider response
            kwetupizza_set_conversation_context($from, array_merge($context, ['awaiting' => 'payment_provider']));
        } else {
            kwetupizza_send_whatsapp_message($from, "Error processing your order. Please try again.");
        }
    }
}

/**
 * Send greeting message
 */
if (!function_exists('kwetupizza_send_greeting')) {
    function kwetupizza_send_greeting($from) {
        $message = "Hello! Welcome to KwetuPizza 🍕\n\n";
        $message .= "How can I help you today?\n";
        $message .= "• Type 'menu' to view our delicious options\n";
        $message .= "• Type 'order' to start a new order\n";
        $message .= "• Type 'status' to check your recent order\n";
        $message .= "• Type 'help' for assistance";
        kwetupizza_send_whatsapp_message($from, $message);
    }
}

/**
 * Send full menu to customer
 */
if (!function_exists('kwetupizza_send_full_menu')) {
    function kwetupizza_send_full_menu($from) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kwetupizza_products';

        $pizzas = $wpdb->get_results("SELECT id, product_name, price FROM $table_name WHERE category = 'Pizza'");
        $drinks = $wpdb->get_results("SELECT id, product_name, price FROM $table_name WHERE category = 'Drinks'");
        $desserts = $wpdb->get_results("SELECT id, product_name, price FROM $table_name WHERE category = 'Dessert'");

        $message = "Here's our menu. Please type the number of the item you'd like to order:\n\n";

        if ($pizzas) {
            $message .= "🍕 Pizzas:\n";
            foreach ($pizzas as $pizza) {
                $message .= $pizza->id . ". " . $pizza->product_name . " - " . number_format($pizza->price, 2) . " TZS\n";
            }
        }

        if ($drinks) {
            $message .= "\n🥤 Drinks:\n";
            foreach ($drinks as $drink) {
                $message .= $drink->id . ". " . $drink->product_name . " - " . number_format($drink->price, 2) . " TZS\n";
            }
        }

        if ($desserts) {
            $message .= "\n🍰 Desserts:\n";
            foreach ($desserts as $dessert) {
                $message .= $dessert->id . ". " . $dessert->product_name . " - " . number_format($dessert->price, 2) . " TZS\n";
            }
        }

        kwetupizza_send_whatsapp_message($from, $message);
        kwetupizza_set_conversation_context($from, ['awaiting' => 'menu_selection']);
    }
}

/**
 * Handle payment provider response
 */
if (!function_exists('kwetupizza_handle_payment_provider_response')) {
    function kwetupizza_handle_payment_provider_response($from, $provider) {
        $provider = strtolower(trim($provider)); // Convert input to lowercase
        $context = kwetupizza_get_conversation_context($from);

        // Validate if we're awaiting a payment provider response
        if (isset($context['awaiting']) && $context['awaiting'] === 'payment_provider') {
            $valid_providers = ['vodacom', 'tigo', 'halopesa', 'airtel'];

            if (in_array($provider, $valid_providers)) {
                // Set payment provider in the context
                $context['payment_provider'] = ucfirst($provider); // Capitalize for display purposes
                kwetupizza_set_conversation_context($from, $context);

                // Ask if the user wants to use their WhatsApp number for payment
                $message = "Would you like to use your WhatsApp number ($from) for payment? Reply with 'yes' to proceed with this number or 'no' to provide another number.";
                kwetupizza_send_whatsapp_message($from, $message);

                // Set the context to expect a yes/no response
                kwetupizza_set_conversation_context($from, array_merge($context, ['awaiting' => 'use_whatsapp_number']));
            } else {
                // Invalid provider input
                kwetupizza_send_whatsapp_message($from, "Please reply with one of the following options: Vodacom, Tigo, Halopesa, or Airtel.");
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
        $response = strtolower(trim($response)); // Convert response to lowercase
        $context = kwetupizza_get_conversation_context($from);

        if (isset($context['awaiting']) && $context['awaiting'] === 'use_whatsapp_number') {
            if ($response === 'yes') {
                // Proceed with using WhatsApp number for payment
                kwetupizza_generate_mobile_money_push($from, $context['cart'], $context['address'], $from);
            } elseif ($response === 'no') {
                // Ask for an alternative phone number
                $message = "Please provide the phone number you'd like to use for mobile money payment.";
                kwetupizza_send_whatsapp_message($from, $message);

                // Update context to expect a new phone number for payment
                kwetupizza_set_conversation_context($from, array_merge($context, ['awaiting' => 'payment_phone']));
            } else {
                kwetupizza_send_whatsapp_message($from, "Please reply with 'yes' or 'no'.");
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

        // Calculate the total
        $total = 0;
        foreach ($cart as $item) {
            $total += $item['total'];
        }

        $tx_ref = 'order_' . time();  // Unique transaction reference
        $email = kwetupizza_get_customer_email($from);
        $context = kwetupizza_get_conversation_context($from);
        $network = $context['payment_provider'];

        $body = array(
            "tx_ref" => $tx_ref,
            "amount" => $total,
            "currency" => "TZS",
            "email" => $email,
            "phone_number" => $payment_phone,
            "network" => ucfirst($network),
            "fullname" => "KwetuPizza Customer",
            "meta" => array("delivery_address" => $address),
        );

        $response = wp_remote_post('https://api.flutterwave.com/v3/charges?type=mobile_money_tanzania', [
            'headers' => [
                'Authorization' => 'Bearer ' . get_option('kwetupizza_flw_secret_key'),
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($body)
        ]);

        $response_body = wp_remote_retrieve_body($response);
        $result = json_decode($response_body, true);

        if (isset($result['status']) && $result['status'] == 'success') {
            kwetupizza_send_whatsapp_message($from, "Payment request has been sent to $payment_phone. Please confirm the payment.");
        } else {
            kwetupizza_send_whatsapp_message($from, "Error initiating the payment. Please try again.");
        }
    }
}

/**
 * Verify payment using Flutterwave webhook and notify
 */
if (!function_exists('kwetupizza_confirm_payment_and_notify')) {
    function kwetupizza_confirm_payment_and_notify($transaction_id) {
        $transaction_data = kwetupizza_verify_payment($transaction_id);

        if ($transaction_data) {
            global $wpdb;
            $tx_ref = $transaction_data['tx_ref'];

            preg_match('/order_(\d+)/', $tx_ref, $matches);
            $order_id = isset($matches[1]) ? $matches[1] : null;

            if ($order_id) {
                $orders_table = $wpdb->prefix . 'kwetupizza_orders';
                $order_items_table = $wpdb->prefix . 'kwetupizza_order_items';

                // Update order status
                $wpdb->update(
                    $orders_table,
                    array('status' => 'completed'),
                    array('id' => $order_id)
                );

                // Get order details
                $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}kwetupizza_orders WHERE id = %d", $order_id));
                
                // Get order items
                $order_items = $wpdb->get_results($wpdb->prepare(
                    "SELECT oi.*, p.product_name 
                    FROM $order_items_table oi 
                    JOIN {$wpdb->prefix}kwetupizza_products p ON oi.product_id = p.id 
                    WHERE oi.order_id = %d", 
                    $order_id
                ));
                
                // Prepare detailed confirmation message
                $message = "🎉 Payment Confirmed! 🎉\n\n";
                $message .= "Thank you for your order #{$order_id}!\n\n";
                $message .= "📋 *Order Summary:*\n";
                
                // Add order items
                if ($order_items) {
                    foreach ($order_items as $item) {
                        $message .= "• {$item->quantity}x {$item->product_name}: " . 
                                    kwetupizza_format_currency($item->price * $item->quantity, $order->currency) . "\n";
                    }
                }
                
                // Add total and delivery info
                $message .= "\n💰 *Total:* " . kwetupizza_format_currency($order->total, $order->currency) . "\n";
                $message .= "🏠 *Delivery Address:* {$order->delivery_address}\n";
                $message .= "⏱️ *Estimated Delivery:* 30-45 minutes\n\n";
                $message .= "We're preparing your delicious pizza right now! You'll receive an update when your order is out for delivery.\n\n";
                $message .= "🙏 Thank you for choosing Kwetu Pizza!";
                
                // Send detailed confirmation to customer
                kwetupizza_send_whatsapp_message($order->customer_phone, $message);
                
                // Notify admin
                kwetupizza_notify_admin($order->id, true);

                // Add order to timeline
                kwetupizza_add_order_timeline_event($order_id, 'payment_confirmed', 'Payment confirmed');

                return true;
            }
        }

        return false;
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
 * Log Flutterwave Payment Webhook Data
 */
if (!function_exists('log_flutterwave_payment_webhook')) {
    function log_flutterwave_payment_webhook($request) {
        $webhook_data = $request->get_json_params();

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
                        kwetupizza_send_whatsapp_message($phone_number, "Your payment has been confirmed. Your delicious pizza is on the way to $delivery_address!");
                        return new WP_REST_Response('Payment processed successfully', 200);
                    } else {
                        return new WP_REST_Response('Payment verification failed', 400);
                    }
                } elseif ($status === 'failed') {
                    kwetupizza_send_whatsapp_message($phone_number, "Your payment for the order has failed. Please try again.");
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
        set_transient("kwetupizza_whatsapp_context_$from", $context, 60 * 60 * 24); // 24 hours expiry
    }
} 