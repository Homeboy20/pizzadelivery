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
 * Initialize the modernized WhatsApp order flow
 */
if (!function_exists('kwetupizza_init_whatsapp_order_flow')) {
    function kwetupizza_init_whatsapp_order_flow() {
        // Check if we need to update database tables
        kwetupizza_maybe_update_db_tables();
        
        // Register hooks for feedback and delivery confirmation
        add_action('kwetupizza_send_feedback_request', [kwetupizza_notifier(), 'send_feedback_request']);
        add_action('kwetupizza_send_delivery_confirmation', [kwetupizza_notifier(), 'send_delivery_confirmation_request']);
        
        // Register hook for order processing
        add_action('kwetupizza_order_paid', 'kwetupizza_process_paid_order');
    }
}
add_action('init', 'kwetupizza_init_whatsapp_order_flow');

/**
 * Update database tables if needed
 */
if (!function_exists('kwetupizza_maybe_update_db_tables')) {
    function kwetupizza_maybe_update_db_tables() {
        global $wpdb;
        
        $current_db_version = get_option('kwetupizza_whatsapp_flow_db_version', '0');
        $new_version = '1.0.0';
        
        // If we're already at the current version, no need to update
        if (version_compare($current_db_version, $new_version, '>=')) {
            return;
        }
        
        // Make sure dbDelta function is available
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Update users table to add verification fields
        $users_table = $wpdb->prefix . 'kwetupizza_users';
        $sql = "CREATE TABLE $users_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            phone varchar(20) NOT NULL,
            name varchar(100),
            email varchar(100),
            location varchar(255),
            state varchar(20) DEFAULT 'new_user',
            verification_status varchar(20) DEFAULT 'pending',
            total_orders int(11) DEFAULT 0,
            total_spent decimal(10,2) DEFAULT 0.00,
            loyalty_points int(11) DEFAULT 0,
            last_order_id bigint(20) DEFAULT NULL,
            last_active datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY phone (phone)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Update orders table to add confirmation fields
        $orders_table = $wpdb->prefix . 'kwetupizza_orders';
        $sql = "CREATE TABLE $orders_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_date datetime NOT NULL,
            customer_phone varchar(20) NOT NULL,
            customer_name varchar(100) NOT NULL,
            customer_email varchar(100),
            delivery_address text NOT NULL,
            status varchar(50) NOT NULL DEFAULT 'pending',
            total decimal(10,2) NOT NULL,
            currency varchar(10) NOT NULL DEFAULT 'TZS',
            notes text,
            confirmation_token varchar(32),
            feedback_token varchar(32),
            customer_confirmed_at datetime DEFAULT NULL,
            has_feedback tinyint(1) DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY customer_phone (customer_phone)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Update the database version
        update_option('kwetupizza_whatsapp_flow_db_version', $new_version);
    }
}

/**
 * Process a paid order (called via hook)
 */
if (!function_exists('kwetupizza_process_paid_order')) {
    function kwetupizza_process_paid_order($order_id) {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'kwetupizza_orders';
        
        // Get the order
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $orders_table WHERE id = %d",
            $order_id
        ));
        
        if (!$order) {
            kwetupizza_log("Cannot process paid order - Order not found: $order_id", 'error', 'orders.log');
            return;
        }
        
        // Update order status
        $wpdb->update(
            $orders_table,
            [
                'status' => 'preparing',
                'updated_at' => current_time('mysql')
            ],
            ['id' => $order_id]
        );
        
        // Add timeline event
        if (function_exists('kwetupizza_add_order_timeline_event')) {
            kwetupizza_add_order_timeline_event(
                $order_id,
                'preparing',
                'Order is being prepared by our chefs'
            );
        }
        
        // Send notification to customer
        kwetupizza_notifier()->send_delivery_update(
            $order_id,
            'preparing'
        );
        
        // Update user stats
        kwetupizza_user_manager()->update_order_stats(
            $order->customer_phone,
            $order_id,
            $order->total
        );
    }
}

/**
 * WhatsApp order flow is implemented above.
 * This wrapper provides access to some key functions from older code.
 */

/**
 * Main WhatsApp message handler wrapper
 */
function kwetupizza_process_whatsapp_message($from, $message, $interactive_data = null) {
    // Call the modernized implementation
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
    try {
        // Try using the modern payment processor
        return kwetupizza_payment_processor()->process_mobile_money(
            $order_id,
            $payment_phone,
            $payment_method,
            kwetupizza_get_order_total($order_id)
        );
    } catch (Exception $e) {
        kwetupizza_log("Payment processing error: " . $e->getMessage(), 'error', 'payment.log');
        
        // Fall back to legacy method
        if (function_exists('kwetupizza_generate_mobile_money_push')) {
            $order = kwetupizza_get_order($order_id);
            if (!$order) {
                return false;
            }
            
            return kwetupizza_generate_mobile_money_push(
                $order->customer_phone,
                kwetupizza_get_order_items($order_id),
                $order->delivery_address,
                $payment_phone
            );
        }
    }
    
    return false;
}

/**
 * Get order total
 */
function kwetupizza_get_order_total($order_id) {
    global $wpdb;
    $orders_table = $wpdb->prefix . 'kwetupizza_orders';
    
    return $wpdb->get_var($wpdb->prepare(
        "SELECT total FROM $orders_table WHERE id = %d",
        $order_id
    ));
}

/**
 * Get order details - compatibility function
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
 * Get order items - compatibility function
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

/**
 * WhatsApp Bot Integration Functions
 */

// ========================
// MODERNIZED WHATSAPP ORDER FLOW
// ========================

/**
 * Modern User Registration & Verification Flow
 */
if (!function_exists('kwetupizza_user_manager')) {
    function kwetupizza_user_manager() {
        static $instance = null;
        
        if ($instance === null) {
            $instance = new KwetuPizza_UserManager();
        }
        
        return $instance;
    }
}

if (!class_exists('KwetuPizza_UserManager')) {
    class KwetuPizza_UserManager {
        private $wpdb;
        private $users_table;

        public function __construct() {
            global $wpdb;
            $this->wpdb = $wpdb;
            $this->users_table = $wpdb->prefix . 'kwetupizza_users';
        }

        /**
         * Get or create user with enhanced verification
         */
        public function get_or_create_user($phone, $context = []) {
            $phone = $this->sanitize_phone($phone);
            
            // Check for existing user
            $user = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT * FROM {$this->users_table} WHERE phone = %s", 
                $phone
            ));

            if ($user) {
                // Update last active timestamp
                $this->wpdb->update(
                    $this->users_table,
                    ['last_active' => current_time('mysql')],
                    ['id' => $user->id]
                );
                return $user;
            }

            // Create new user with temporary profile
            $user_data = [
                'phone' => $phone,
                'state' => 'new_user',
                'verification_status' => 'pending',
                'created_at' => current_time('mysql'),
                'last_active' => current_time('mysql')
            ];

            // Add context data if available
            if (isset($context['name'])) {
                $user_data['name'] = sanitize_text_field($context['name']);
            }
            if (isset($context['email']) && is_email($context['email'])) {
                $user_data['email'] = sanitize_email($context['email']);
            }

            $this->wpdb->insert($this->users_table, $user_data);

            return $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT * FROM {$this->users_table} WHERE phone = %s", 
                $phone
            ));
        }

        /**
         * Complete user profile after initial order
         */
        public function complete_profile($phone, $profile_data) {
            $update_data = [
                'name' => sanitize_text_field($profile_data['name']),
                'email' => sanitize_email($profile_data['email']),
                'location' => isset($profile_data['location']) ? sanitize_text_field($profile_data['location']) : '',
                'state' => 'verified',
                'verification_status' => 'verified',
                'updated_at' => current_time('mysql')
            ];

            return $this->wpdb->update(
                $this->users_table,
                $update_data,
                ['phone' => $this->sanitize_phone($phone)]
            );
        }

        /**
         * Enhanced phone number sanitization
         */
        private function sanitize_phone($phone) {
            $phone = preg_replace('/[^0-9]/', '', $phone);
            
            // Handle Tanzanian numbers
            if (strpos($phone, '0') === 0) {
                $phone = '255' . substr($phone, 1);
            } elseif (strpos($phone, '+') === 0) {
                $phone = substr($phone, 1);
            }
            
            // Validate length
            if (strlen($phone) < 9 || strlen($phone) > 12) {
                return '255000000000'; // Return default number for invalid ones
            }
            
            return $phone;
        }
        
        /**
         * Update user order stats
         */
        public function update_order_stats($phone, $order_id, $order_total) {
            $phone = $this->sanitize_phone($phone);
            
            // Get current user
            $user = $this->get_or_create_user($phone);
            
            // Update stats
            $this->wpdb->update(
                $this->users_table,
                [
                    'total_orders' => (int)$user->total_orders + 1,
                    'total_spent' => (float)$user->total_spent + (float)$order_total,
                    'last_order_id' => $order_id,
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $user->id]
            );
        }
    }
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

/**
 * Modern Order Processing with State Machine
 */
if (!function_exists('kwetupizza_order_processor')) {
    function kwetupizza_order_processor() {
        static $instance = null;
        
        if ($instance === null) {
            $instance = new KwetuPizza_OrderProcessor();
        }
        
        return $instance;
    }
}

if (!class_exists('KwetuPizza_OrderProcessor')) {
    class KwetuPizza_OrderProcessor {
        private $wpdb;
        private $order_states = [
            'init' => ['menu', 'cart', 'checkout'],
            'menu' => ['category', 'product'],
            'cart' => ['add', 'remove', 'checkout'],
            'checkout' => ['delivery', 'payment', 'confirm'],
            'payment' => ['processing', 'success', 'failed'],
            'complete' => []
        ];

        public function __construct() {
            global $wpdb;
            $this->wpdb = $wpdb;
        }

        /**
         * Handle incoming message with state awareness
         */
        public function handle_message($from, $message, $interactive = null) {
            try {
                $user = kwetupizza_user_manager()->get_or_create_user($from);
                $context = $this->get_context($from);
                $current_state = $context['state'] ?? 'init';

                // Log for debugging
                kwetupizza_log("Processing message in state: $current_state", 'info', 'whatsapp.log');
                
                // Process interactive message if present
                if ($interactive) {
                    $message = $this->process_interactive($interactive);
                }

                // Handle universal commands first
                if ($this->handle_universal_commands($from, $message, $context)) {
                    return true;
                }

                // State machine transition
                switch ($current_state) {
                    case 'init':
                        return $this->handle_init_state($from, $message, $context);
                    
                    case 'menu':
                        return $this->handle_menu_state($from, $message, $context);
                    
                    case 'cart':
                        return $this->handle_cart_state($from, $message, $context);
                    
                    case 'checkout':
                        return $this->handle_checkout_state($from, $message, $context);
                    
                    case 'payment':
                        return $this->handle_payment_state($from, $message, $context);
                    
                    default:
                        $this->send_message($from, "Let's start over. Type 'menu' to see our delicious options!");
                        $this->reset_context($from);
                        return true;
                }
            } catch (Exception $e) {
                $this->log_error($e);
                $this->send_message($from, "⚠️ An error occurred. Please try again or type 'help' for assistance.");
                $this->reset_context($from);
                return false;
            }
        }
        
        /**
         * Handle universal commands that work in any state
         */
        private function handle_universal_commands($from, $message, $context) {
            $message = strtolower(trim($message));
            
            // Help command
            if ($message === 'help') {
                $this->send_help_message($from);
                return true;
            }
            
            // Status command
            if ($message === 'status') {
                $this->check_order_status($from);
                return true;
            }
            
            // Reset command
            if ($message === 'reset' || $message === 'restart') {
                $this->reset_context($from);
                $this->send_message($from, "Let's start over! Type 'menu' to see our options.");
                return true;
            }
            
            // Greeting detection
            if ($this->is_greeting($message)) {
                $this->send_greeting($from);
                return true;
            }
            
            return false;
        }

        /**
         * Init state - welcome and menu presentation
         */
        private function handle_init_state($from, $message, $context) {
            $message = strtolower(trim($message));
            
            if (in_array($message, ['menu', 'order', 'start'])) {
                $this->send_menu_categories($from);
                $this->update_context($from, ['state' => 'menu']);
                return true;
            }
            
            $this->send_welcome_message($from);
            return true;
        }

        /**
         * Menu state - category and product selection
         */
        private function handle_menu_state($from, $message, $context) {
            // Handle category selection
            if (empty($context['category'])) {
                return $this->handle_category_selection($from, $message, $context);
            }
            
            // Handle product selection
            return $this->handle_product_selection($from, $message, $context);
        }

        /**
         * Cart state - managing items before checkout
         */
        private function handle_cart_state($from, $message, $context) {
            $message = strtolower(trim($message));
            
            if ($message === 'checkout' || $message === '2') {
                $this->start_checkout($from, $context);
                return true;
            }
            
            if ($message === 'add' || $message === '1') {
                $this->send_menu_categories($from);
                $this->update_context($from, [
                    'state' => 'menu',
                    'category' => null
                ]);
                return true;
            }
            
            if ($message === 'remove' || $message === '3') {
                return $this->handle_remove_item($from, $context);
            }
            
            $this->send_cart_options($from, $context);
            return true;
        }

        /**
         * Checkout state - delivery and payment info
         */
        private function handle_checkout_state($from, $message, $context) {
            if (empty($context['delivery_info'])) {
                return $this->handle_delivery_info($from, $message, $context);
            }
            
            if (empty($context['payment_method'])) {
                return $this->handle_payment_method($from, $message, $context);
            }
            
            if (empty($context['payment_phone'])) {
                return $this->handle_payment_phone($from, $message, $context);
            }
            
            return $this->confirm_order($from, $context);
        }

        /**
         * Payment state - processing payment
         */
        private function handle_payment_state($from, $message, $context) {
            // Handle payment processing
            $order_id = $context['order_id'];
            
            // Generate mobile money payment
            if (function_exists('kwetupizza_generate_mobile_money_push')) {
                $result = kwetupizza_generate_mobile_money_push(
                    $from, 
                    $context['cart'], 
                    $context['delivery_info'],
                    $context['payment_phone']
                );
                
                // Handle result
                if ($result) {
                    $this->update_context($from, [
                        'state' => 'complete',
                        'payment_initiated' => true
                    ]);
                    return true;
                } else {
                    $this->send_message(
                        $from, 
                        "There was a problem processing your payment. Please try again or contact our support."
                    );
                    $this->update_context($from, [
                        'state' => 'checkout',
                        'payment_error' => true
                    ]);
                    return false;
                }
            }
            
            // Fallback message if payment function doesn't exist
            $this->send_message(
                $from, 
                "We're currently experiencing technical issues with our payment system. Please try again later."
            );
            return false;
        }
        
        /**
         * Get conversation context
         */
        private function get_context($from) {
            if (function_exists('kwetupizza_get_conversation_context')) {
                return kwetupizza_get_conversation_context($from);
            }
            
            // Fallback implementation
            $context = get_transient("kwetupizza_whatsapp_context_$from");
            return $context ? $context : [];
        }
        
        /**
         * Update context with new values
         */
        private function update_context($from, $values) {
            $context = $this->get_context($from);
            $context = array_merge($context, $values);
            
            if (function_exists('kwetupizza_set_conversation_context')) {
                kwetupizza_set_conversation_context($from, $context);
            } else {
                // Fallback implementation
                set_transient("kwetupizza_whatsapp_context_$from", $context, 60 * 5);
            }
        }
        
        /**
         * Reset context
         */
        private function reset_context($from) {
            if (function_exists('kwetupizza_set_conversation_context')) {
                kwetupizza_set_conversation_context($from, ['state' => 'init']);
            } else {
                // Fallback implementation
                set_transient("kwetupizza_whatsapp_context_$from", ['state' => 'init'], 60 * 5);
            }
        }
        
        /**
         * Send WhatsApp message
         */
        private function send_message($to, $message) {
            if (function_exists('kwetupizza_send_whatsapp_message')) {
                return kwetupizza_send_whatsapp_message($to, $message);
            }
            
            return false;
        }
        
        /**
         * Process interactive response
         */
        private function process_interactive($interactive) {
            $message = '';
            
            if (isset($interactive['type'])) {
                if ($interactive['type'] === 'button_reply' && isset($interactive['button_reply']['title'])) {
                    // Extract the message from button payload
                    $message = $interactive['button_reply']['title'];
                } elseif ($interactive['type'] === 'list_reply' && isset($interactive['list_reply']['title'])) {
                    // Extract the message from list selection
                    $message = $interactive['list_reply']['title'];
                }
            }
            
            kwetupizza_log("Processed interactive: $message", 'info', 'whatsapp.log');
            return $message;
        }
        
        /**
         * Log error
         */
        private function log_error($error) {
            if (function_exists('kwetupizza_log')) {
                kwetupizza_log("Error: " . $error->getMessage(), 'error', 'whatsapp.log');
            } else {
                error_log("KwetuPizza Error: " . $error->getMessage());
            }
        }
        
        /**
         * Check if message is a greeting
         */
        private function is_greeting($message) {
            $message = strtolower(trim($message));
            $greetings = [
                'hello', 'hi', 'hey', 'howdy', 'greetings', 'good morning', 
                'good afternoon', 'good evening', 'hola', 'jambo', 
                'habari', 'mambo', 'sasa', 'niaje'
            ];
            
            foreach ($greetings as $greeting) {
                if (strpos($message, $greeting) !== false) {
                    return true;
                }
            }
            
            return false;
        }
        
        /**
         * Send welcome/greeting message
         */
        private function send_greeting($from) {
            $message = "👋 *Hello! Welcome to KwetuPizza* 🍕\n\n";
            $message .= "How can I help you today?\n\n";
            $message .= "📱 *Available Commands:*\n";
            $message .= "• Type *menu* to browse our delicious menu\n";
            $message .= "• Type *order* to start a new order\n";
            $message .= "• Type *status* to check your recent order\n";
            $message .= "• Type *help* for assistance\n\n";
            $message .= "Our new interactive ordering system makes it easy to order your favorite pizza!";
            
            $this->send_message($from, $message);
            $this->update_context($from, ['state' => 'init']);
            
            return true;
        }

        /**
         * Handle category selection
         */
        private function handle_category_selection($from, $message, $context) {
            global $wpdb;
            $categories = [
                '1' => 'Pizza',
                '2' => 'Drinks',
                '3' => 'Dessert',
                '4' => 'Special'
            ];
            
            // Validate selection
            if (!isset($categories[$message])) {
                $this->send_message($from, "Please select a valid category (1-4).");
                return false;
            }
            
            $category = $categories[$message];
            $products_table = $wpdb->prefix . 'kwetupizza_products';
            
            // Get products in category
            $products = $wpdb->get_results($wpdb->prepare(
                "SELECT id, product_name, description, price FROM $products_table WHERE category = %s",
                $category
            ));
            
            if (empty($products)) {
                $this->send_message($from, "Sorry, no products found in this category. Please select another.");
                $this->send_menu_categories($from);
                return false;
            }
            
            // Format product list
            $emoji = ['Pizza' => '🍕', 'Drinks' => '🥤', 'Dessert' => '🍰', 'Special' => '🎁'][$category];
            $message = "$emoji *{$category} Menu* $emoji\n\n";
            $message .= "Please type the number of the item you'd like to order:\n\n";
            
            foreach ($products as $index => $product) {
                $message .= "{$product->id}. *{$product->product_name}*\n";
                $message .= "   {$product->description}\n";
                $message .= "   Price: " . number_format($product->price, 2) . " TZS\n\n";
            }
            
            $this->send_message($from, $message);
            
            // Update context
            $this->update_context($from, [
                'category' => $category,
                'awaiting' => 'product_selection'
            ]);
            
            return true;
        }
        
        /**
         * Handle product selection
         */
        private function handle_product_selection($from, $message, $context) {
            global $wpdb;
            $products_table = $wpdb->prefix . 'kwetupizza_products';
            
            // Try to get product by ID
            $product = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $products_table WHERE id = %d", 
                intval($message)
            ));
            
            // If not found by ID, try to find by name
            if (!$product) {
                $product = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $products_table WHERE product_name LIKE %s",
                    '%' . $wpdb->esc_like($message) . '%'
                ));
            }
            
            if (!$product) {
                $this->send_message($from, "Sorry, I couldn't find that product. Please try again.");
                return false;
            }
            
            // Ask for quantity
            $message = "You've selected: *{$product->product_name}*\n";
            $message .= "Price: " . number_format($product->price, 2) . " TZS\n\n";
            $message .= "How many would you like to order?";
            
            $this->send_message($from, $message);
            
            // Update context
            $this->update_context($from, [
                'current_product' => [
                    'id' => $product->id,
                    'name' => $product->product_name,
                    'price' => $product->price
                ],
                'awaiting' => 'quantity'
            ]);
            
            return true;
        }
        
        /**
         * Handle quantity input and add to cart
         */
        public function handle_quantity_input($from, $quantity) {
            $context = $this->get_context($from);
            
            // Validate quantity
            $quantity = intval($quantity);
            if ($quantity <= 0) {
                $this->send_message($from, "Please enter a valid quantity (a positive number).");
                return false;
            }
            
            // Initialize cart if needed
            if (!isset($context['cart'])) {
                $context['cart'] = [];
            }
            
            // Add item to cart
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
            
            // Send confirmation
            $message = "Added to cart: {$quantity}x {$product['name']}\n\n";
            $message .= "Your cart total is: " . number_format($subtotal, 2) . " TZS\n\n";
            $message .= "Would you like to:\n";
            $message .= "1. Add more items\n";
            $message .= "2. Proceed to checkout\n";
            $message .= "3. Remove an item";
            
            $this->send_message($from, $message);
            
            // Update context
            $this->update_context($from, [
                'state' => 'cart',
                'subtotal' => $subtotal,
                'awaiting' => 'cart_action'
            ]);
            
            return true;
        }
        
        /**
         * Handle removing items from cart
         */
        private function handle_remove_item($from, $context) {
            if (empty($context['cart']) || count($context['cart']) === 0) {
                $this->send_message($from, "Your cart is empty.");
                return false;
            }
            
            $message = "Which item would you like to remove?\n\n";
            
            foreach ($context['cart'] as $index => $item) {
                $message .= ($index + 1) . ". {$item['quantity']}x {$item['product_name']} - " . 
                    number_format($item['total'], 2) . " TZS\n";
            }
            
            $this->send_message($from, $message);
            
            $this->update_context($from, [
                'awaiting' => 'remove_item_selection'
            ]);
            
            return true;
        }
        
        /**
         * Handle user's selection for item removal
         */
        public function handle_remove_item_selection($from, $selection) {
            $context = $this->get_context($from);
            $index = intval($selection) - 1;
            
            if (!isset($context['cart'][$index])) {
                $this->send_message($from, "Invalid selection. Please try again.");
                return $this->handle_remove_item($from, $context);
            }
            
            $removed_item = $context['cart'][$index];
            array_splice($context['cart'], $index, 1);
            
            // Recalculate subtotal
            $subtotal = 0;
            foreach ($context['cart'] as $item) {
                $subtotal += $item['total'];
            }
            
            $message = "Removed: {$removed_item['quantity']}x {$removed_item['product_name']}\n\n";
            
            if (count($context['cart']) > 0) {
                $message .= "Your updated cart total is: " . number_format($subtotal, 2) . " TZS\n\n";
                $message .= "Would you like to:\n";
                $message .= "1. Add more items\n";
                $message .= "2. Proceed to checkout\n";
                $message .= "3. Remove another item";
                
                $this->update_context($from, [
                    'cart' => $context['cart'],
                    'subtotal' => $subtotal,
                    'awaiting' => 'cart_action'
                ]);
            } else {
                $message .= "Your cart is now empty. Type 'menu' to browse our delicious options!";
                
                $this->update_context($from, [
                    'state' => 'init',
                    'cart' => [],
                    'subtotal' => 0
                ]);
            }
            
            $this->send_message($from, $message);
            return true;
        }
        
        /**
         * Display cart options
         */
        private function send_cart_options($from, $context) {
            if (empty($context['cart']) || count($context['cart']) === 0) {
                $this->send_message($from, "Your cart is empty. Type 'menu' to see our options!");
                $this->update_context($from, ['state' => 'init']);
                return true;
            }
            
            $message = "📋 *Your Cart* 📋\n\n";
            
            foreach ($context['cart'] as $index => $item) {
                $message .= ($index + 1) . ". {$item['quantity']}x {$item['product_name']} - " . 
                    number_format($item['total'], 2) . " TZS\n";
            }
            
            $message .= "\nSubtotal: " . number_format($context['subtotal'], 2) . " TZS\n\n";
            $message .= "What would you like to do?\n";
            $message .= "1. Add more items\n";
            $message .= "2. Proceed to checkout\n";
            $message .= "3. Remove an item";
            
            $this->send_message($from, $message);
            
            return true;
        }
        
        /**
         * Start checkout process
         */
        private function start_checkout($from, $context) {
            if (empty($context['cart']) || count($context['cart']) === 0) {
                $this->send_message($from, "Your cart is empty. Type 'menu' to see our options!");
                $this->update_context($from, ['state' => 'init']);
                return false;
            }
            
            $message = "📋 *Order Summary* 📋\n\n";
            
            foreach ($context['cart'] as $item) {
                $message .= "{$item['quantity']}x {$item['product_name']} - " . 
                    number_format($item['total'], 2) . " TZS\n";
            }
            
            $message .= "\nSubtotal: " . number_format($context['subtotal'], 2) . " TZS\n\n";
            $message .= "Please provide your delivery address:";
            
            $this->send_message($from, $message);
            
            $this->update_context($from, [
                'state' => 'checkout',
                'awaiting' => 'delivery_address'
            ]);
            
            return true;
        }
        
        /**
         * Handle delivery address input
         */
        private function handle_delivery_info($from, $address, $context) {
            // Save address
            $this->update_context($from, [
                'delivery_info' => $address,
                'awaiting' => 'payment_method'
            ]);
            
            // Ask for payment method
            $message = "📍 Delivery Address: $address\n\n";
            $message .= "Please select your payment method:\n";
            $message .= "1. M-Pesa\n";
            $message .= "2. Tigo Pesa\n";
            $message .= "3. Airtel Money\n";
            $message .= "4. Halopesa";
            
            $this->send_message($from, $message);
            
            return true;
        }
        
        /**
         * Handle payment method selection
         */
        private function handle_payment_method($from, $method, $context) {
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
                $this->send_message($from, "Please select a valid payment method (1-4).");
                return false;
            }
            
            // Save payment method
            $this->update_context($from, [
                'payment_method' => $providers[$method],
                'awaiting' => 'payment_phone'
            ]);
            
            // Ask for payment phone
            $message = "Would you like to use your WhatsApp number ($from) for the {$providers[$method]} payment?";
            $message .= "\n\n1. Yes\n2. No (I'll provide a different number)";
            
            $this->send_message($from, $message);
            
            return true;
        }
        
        /**
         * Handle payment phone selection
         */
        private function handle_payment_phone($from, $response, $context) {
            $response = trim(strtolower($response));
            
            if ($response === '1' || $response === 'yes') {
                // Use WhatsApp number
                $this->update_context($from, [
                    'payment_phone' => $from,
                    'awaiting' => 'confirm_order'
                ]);
                
                return $this->confirm_order($from, $this->get_context($from));
            } 
            
            if ($response === '2' || $response === 'no') {
                // Ask for different number
                $this->send_message($from, "Please provide the phone number you'd like to use for payment:");
                
                $this->update_context($from, [
                    'awaiting' => 'payment_phone_input'
                ]);
                
                return true;
            }
            
            if ($context['awaiting'] === 'payment_phone_input') {
                // Sanitize phone number
                $phone = preg_replace('/[^0-9]/', '', $response);
                
                // Validate
                if (strlen($phone) < 9) {
                    $this->send_message($from, "Please provide a valid phone number.");
                    return false;
                }
                
                // Format if needed (Tanzania)
                if (strlen($phone) === 9) {
                    $phone = '255' . $phone;
                } else if (strlen($phone) === 10 && $phone[0] === '0') {
                    $phone = '255' . substr($phone, 1);
                }
                
                $this->update_context($from, [
                    'payment_phone' => $phone,
                    'awaiting' => 'confirm_order'
                ]);
                
                return $this->confirm_order($from, $this->get_context($from));
            }
            
            $this->send_message($from, "Please reply with '1' for Yes or '2' for No.");
            return false;
        }
        
        /**
         * Confirm order and process payment
         */
        private function confirm_order($from, $context) {
            // Display order summary
            $message = "🔄 *Confirming Your Order* 🔄\n\n";
            $message .= "📋 *Order Details:*\n";
            
            foreach ($context['cart'] as $item) {
                $message .= "{$item['quantity']}x {$item['product_name']} - " . 
                    number_format($item['total'], 2) . " TZS\n";
            }
            
            $message .= "\nSubtotal: " . number_format($context['subtotal'], 2) . " TZS\n";
            $message .= "Delivery Address: {$context['delivery_info']}\n";
            $message .= "Payment Method: " . ucfirst($context['payment_method']) . "\n";
            $message .= "Payment Phone: {$context['payment_phone']}\n\n";
            $message .= "💳 *Processing Payment* 💳\n";
            $message .= "Please check your phone for a payment prompt.";
            
            $this->send_message($from, $message);
            
            // Save order in database
            $order_id = $this->save_order_to_db($from, $context);
            
            if (!$order_id) {
                $this->send_message($from, "Sorry, we couldn't save your order. Please try again.");
                return false;
            }
            
            // Update context with order ID
            $this->update_context($from, [
                'state' => 'payment',
                'order_id' => $order_id,
                'awaiting' => 'payment_processing'
            ]);
            
            // Generate mobile money payment
            if (function_exists('kwetupizza_generate_mobile_money_push')) {
                return $this->handle_payment_state($from, '', $this->get_context($from));
            }
            
            return true;
        }
        
        /**
         * Save order to database
         */
        private function save_order_to_db($from, $context) {
            global $wpdb;
            
            // Get user info from database
            $user = kwetupizza_user_manager()->get_or_create_user($from);
            
            // Get table names
            $orders_table = $wpdb->prefix . 'kwetupizza_orders';
            $order_items_table = $wpdb->prefix . 'kwetupizza_order_items';
            $transactions_table = $wpdb->prefix . 'kwetupizza_transactions';
            
            // Prepare order data
            $order_data = [
                'order_date' => current_time('mysql'),
                'customer_name' => !empty($user->name) ? $user->name : 'Customer',
                'customer_phone' => $from,
                'customer_email' => !empty($user->email) ? $user->email : '',
                'delivery_address' => $context['delivery_info'],
                'status' => 'pending',
                'total' => $context['subtotal'],
                'currency' => 'TZS',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ];
            
            // Insert order
            $wpdb->insert($orders_table, $order_data);
            $order_id = $wpdb->insert_id;
            
            if (!$order_id) {
                kwetupizza_log("Error saving order: " . $wpdb->last_error, 'error', 'orders.log');
                return false;
            }
            
            // Insert order items
            foreach ($context['cart'] as $item) {
                $item_data = [
                    'order_id' => $order_id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'created_at' => current_time('mysql')
                ];
                
                $wpdb->insert($order_items_table, $item_data);
            }
            
            // Create initial transaction record
            $transaction_data = [
                'order_id' => $order_id,
                'transaction_date' => current_time('mysql'),
                'payment_method' => $context['payment_method'],
                'payment_status' => 'pending',
                'amount' => $context['subtotal'],
                'currency' => 'TZS',
                'payment_provider' => 'Flutterwave',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ];
            
            $wpdb->insert($transactions_table, $transaction_data);
            
            // Add order timeline event
            if (function_exists('kwetupizza_add_order_timeline_event')) {
                kwetupizza_add_order_timeline_event(
                    $order_id, 
                    'order_placed', 
                    'Order placed via WhatsApp'
                );
            }
            
            return $order_id;
        }
        
        /**
         * Send help message
         */
        private function send_help_message($from) {
            $message = "📱 *KwetuPizza Help Guide*\n\n";
            
            $message .= "Here's how to use our WhatsApp service:\n\n";
            
            $message .= "*Available Commands:*\n";
            $message .= "• *menu* - View our menu with prices\n";
            $message .= "• *order* - Start a new order\n";
            $message .= "• *status* - Check your recent order status\n";
            $message .= "• *help* - Show this help message\n";
            $message .= "• *reset* - Start over if you get stuck\n\n";
            
            $message .= "*Ordering Process:*\n";
            $message .= "1. Type *menu* to see available items\n";
            $message .= "2. Choose items by entering their number\n";
            $message .= "3. Specify quantity when prompted\n";
            $message .= "4. Provide your delivery address\n";
            $message .= "5. Select payment method (mobile money)\n";
            $message .= "6. Confirm payment on your mobile device\n\n";
            
            $message .= "*Payment Issues:*\n";
            $message .= "If you encounter payment problems, please contact our customer support at ";
            $message .= get_option('kwetupizza_support_phone', '+255xxxxxxxxx') . "\n\n";
            
            $message .= "Thank you for choosing KwetuPizza! 🍕";
            
            $this->send_message($from, $message);
            return true;
        }
        
        /**
         * Check order status
         */
        private function check_order_status($from) {
            global $wpdb;
            $orders_table = $wpdb->prefix . 'kwetupizza_orders';
            $timeline_table = $wpdb->prefix . 'kwetupizza_order_timeline';
            
            // Find most recent order
            $order = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $orders_table 
                WHERE customer_phone = %s 
                ORDER BY created_at DESC 
                LIMIT 1",
                $from
            ));
            
            if (!$order) {
                $this->send_message($from, "You don't have any recent orders. Type 'menu' to place an order!");
                return false;
            }
            
            // Get timeline events
            $timeline_events = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $timeline_table 
                WHERE order_id = %d 
                ORDER BY created_at ASC",
                $order->id
            ));
            
            // Create status message
            $message = "🍕 *Order #{$order->id} Status*\n\n";
            $message .= "📋 *Order Details:*\n";
            $message .= "• Date: " . date('Y-m-d H:i', strtotime($order->created_at)) . "\n";
            $message .= "• Total: " . number_format($order->total, 2) . " {$order->currency}\n";
            $message .= "• Status: " . ucfirst($order->status) . "\n";
            $message .= "• Delivery Address: {$order->delivery_address}\n\n";
            
            // Add timeline
            if ($timeline_events) {
                $message .= "⏱️ *Order Timeline:*\n";
                
                foreach ($timeline_events as $event) {
                    $icon = $this->get_timeline_icon($event->event_type);
                    $time = date('H:i', strtotime($event->created_at));
                    $message .= "$icon $time - {$event->description}\n";
                }
            } else {
                $message .= "⏱️ Your order has been received and is being processed.";
            }
            
            $this->send_message($from, $message);
            return true;
        }
        
        /**
         * Get timeline icon
         */
        private function get_timeline_icon($event_type) {
            $icons = [
                'order_placed' => '📝',
                'payment_confirmed' => '💰',
                'order_confirmed' => '✅',
                'preparing' => '👨‍🍳',
                'out_for_delivery' => '🛵',
                'delivered' => '🎉',
                'cancelled' => '❌'
            ];
            
            return isset($icons[$event_type]) ? $icons[$event_type] : '•';
        }
        
        /**
         * Send menu categories to customer
         */
        private function send_menu_categories($from) {
            $message = "🍽️ *Our Menu Categories* 🍽️\n\n";
            $message .= "Please select a category by typing the number:\n\n";
            $message .= "1. 🍕 Pizzas\n";
            $message .= "2. 🥤 Drinks\n";
            $message .= "3. 🍰 Desserts\n";
            $message .= "4. 🎁 Special Offers\n";
            
            $this->send_message($from, $message);
            
            return true;
        }
    }
} 

/**
 * Modern Payment Gateway Integration
 */
if (!function_exists('kwetupizza_payment_processor')) {
    function kwetupizza_payment_processor() {
        static $instance = null;
        
        if ($instance === null) {
            $instance = new KwetuPizza_PaymentProcessor();
        }
        
        return $instance;
    }
}

if (!class_exists('KwetuPizza_PaymentProcessor')) {
    class KwetuPizza_PaymentProcessor {
        private $flw_public_key;
        private $flw_secret_key;
        private $paypal_client_id;
        private $paypal_secret;

        public function __construct() {
            $this->flw_public_key = get_option('kwetupizza_flw_public_key');
            $this->flw_secret_key = get_option('kwetupizza_flw_secret_key');
            $this->paypal_client_id = get_option('kwetupizza_paypal_client_id');
            $this->paypal_secret = get_option('kwetupizza_paypal_secret');
        }

        /**
         * Process mobile money payment via Flutterwave
         */
        public function process_mobile_money($order_id, $phone, $provider, $amount) {
            $tx_ref = 'kwetupizza-' . $order_id . '-' . time();
            $callback_url = kwetupizza_get_callback_url('payment');
            
            // Get order and customer details
            $order = $this->get_order($order_id);
            if (!$order) {
                throw new Exception('Order not found');
            }
            
            // Map provider to network
            $network_map = [
                'mpesa' => 'MPESA',
                'tigo' => 'TIGOPESA',
                'airtel' => 'AIRTELMONEY',
                'halopesa' => 'HALOPESA'
            ];
            
            $network = isset($network_map[$provider]) ? $network_map[$provider] : 'MPESA';
            
            // Prepare customer data for API
            $customer = [
                'email' => !empty($order->customer_email) ? $order->customer_email : 'customer-' . $order_id . '@kwetupizza.com',
                'phone_number' => $this->sanitize_phone($phone),
                'name' => !empty($order->customer_name) ? $order->customer_name : 'Customer ' . $order_id
            ];
            
            // Prepare API request
            $payload = [
                'tx_ref' => $tx_ref,
                'amount' => $amount,
                'currency' => 'TZS',
                'email' => $customer['email'],
                'phone_number' => $customer['phone_number'],
                'fullname' => $customer['name'],
                'redirect_url' => $callback_url,
                'network' => $network
            ];
            
            // Log the payment request
            kwetupizza_log('Flutterwave payment request: ' . json_encode($payload), 'info', 'payment.log');
            
            // Make API request
            $response = wp_remote_post(
                'https://api.flutterwave.com/v3/charges?type=mobile_money_tanzania',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->flw_secret_key,
                        'Content-Type' => 'application/json'
                    ],
                    'body' => json_encode($payload),
                    'timeout' => 30
                ]
            );
            
            // Handle errors
            if (is_wp_error($response)) {
                kwetupizza_log('Flutterwave API Error: ' . $response->get_error_message(), 'error', 'payment.log');
                throw new Exception('Payment gateway connection failed: ' . $response->get_error_message());
            }
            
            // Parse response
            $body = json_decode(wp_remote_retrieve_body($response), true);
            kwetupizza_log('Flutterwave API Response: ' . json_encode($body), 'info', 'payment.log');
            
            if (!isset($body['status']) || $body['status'] !== 'success') {
                $error_msg = isset($body['message']) ? $body['message'] : 'Unknown error';
                kwetupizza_log('Flutterwave Error: ' . $error_msg, 'error', 'payment.log');
                throw new Exception('Payment initiation failed: ' . $error_msg);
            }
            
            // Update order with transaction reference
            $this->update_transaction_reference($order_id, $tx_ref, $body['data']['id']);
            
            return [
                'status' => 'pending',
                'transaction_id' => $body['data']['id'],
                'tx_ref' => $tx_ref,
                'success' => true,
                'message' => $body['message']
            ];
        }

        /**
         * Verify payment status
         */
        public function verify_payment($transaction_id) {
            if (empty($this->flw_secret_key)) {
                throw new Exception('Flutterwave secret key not configured');
            }
            
            $response = wp_remote_get(
                'https://api.flutterwave.com/v3/transactions/' . $transaction_id . '/verify',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->flw_secret_key,
                        'Content-Type' => 'application/json'
                    ],
                    'timeout' => 20
                ]
            );

            if (is_wp_error($response)) {
                kwetupizza_log('Verification Error: ' . $response->get_error_message(), 'error', 'payment.log');
                throw new Exception('Verification failed: ' . $response->get_error_message());
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            kwetupizza_log('Verification Response: ' . json_encode($body), 'info', 'payment.log');

            if (!isset($body['status']) || $body['status'] !== 'success') {
                $error_msg = isset($body['message']) ? $body['message'] : 'Unknown error';
                kwetupizza_log('Verification Failed: ' . $error_msg, 'error', 'payment.log');
                throw new Exception('Payment verification failed: ' . $error_msg);
            }

            return [
                'status' => $body['data']['status'],
                'amount' => $body['data']['amount'],
                'currency' => $body['data']['currency'],
                'payment_method' => isset($body['data']['payment_type']) ? $body['data']['payment_type'] : 'unknown',
                'transaction_id' => $body['data']['id'],
                'tx_ref' => $body['data']['tx_ref'],
                'success' => ($body['data']['status'] === 'successful')
            ];
        }
        
        /**
         * Update transaction reference in database
         */
        public function update_transaction_reference($order_id, $tx_ref, $transaction_id) {
            global $wpdb;
            $transactions_table = $wpdb->prefix . 'kwetupizza_transactions';
            
            $result = $wpdb->update(
                $transactions_table,
                [
                    'transaction_reference' => $transaction_id,
                    'tx_ref' => $tx_ref,
                    'updated_at' => current_time('mysql')
                ],
                ['order_id' => $order_id]
            );
            
            if ($result === false) {
                kwetupizza_log('Failed to update transaction reference: ' . $wpdb->last_error, 'error', 'payment.log');
                return false;
            }
            
            return true;
        }
        
        /**
         * Get order from database
         */
        private function get_order($order_id) {
            global $wpdb;
            $orders_table = $wpdb->prefix . 'kwetupizza_orders';
            
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $orders_table WHERE id = %d",
                $order_id
            ));
        }
        
        /**
         * Process successful payment
         */
        public function process_successful_payment($transaction_data, $order_id) {
            global $wpdb;
            $transactions_table = $wpdb->prefix . 'kwetupizza_transactions';
            $orders_table = $wpdb->prefix . 'kwetupizza_orders';
            
            // Update transaction status
            $wpdb->update(
                $transactions_table,
                [
                    'payment_status' => 'completed',
                    'transaction_reference' => $transaction_data['transaction_id'],
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
            
            // Add timeline event
            if (function_exists('kwetupizza_add_order_timeline_event')) {
                kwetupizza_add_order_timeline_event(
                    $order_id, 
                    'payment_confirmed', 
                    'Payment confirmed via ' . ucfirst($transaction_data['payment_method'])
                );
            }
            
            // Send notifications
            $order = $this->get_order($order_id);
            if ($order) {
                // Notify customer
                $this->notify_customer_of_payment($order->customer_phone, $order_id, true);
                
                // Notify admin
                $this->notify_admin_of_payment($order_id, true);
            }
            
            return true;
        }
        
        /**
         * Process failed payment
         */
        public function process_failed_payment($order_id, $reason = '') {
            global $wpdb;
            $transactions_table = $wpdb->prefix . 'kwetupizza_transactions';
            $orders_table = $wpdb->prefix . 'kwetupizza_orders';
            
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
            
            // Add timeline event
            if (function_exists('kwetupizza_add_order_timeline_event')) {
                $message = 'Payment failed';
                if (!empty($reason)) {
                    $message .= ': ' . $reason;
                }
                
                kwetupizza_add_order_timeline_event(
                    $order_id, 
                    'payment_failed', 
                    $message
                );
            }
            
            // Send notifications
            $order = $this->get_order($order_id);
            if ($order) {
                // Notify customer
                $this->notify_customer_of_payment($order->customer_phone, $order_id, false, $reason);
                
                // Notify admin
                $this->notify_admin_of_payment($order_id, false, $reason);
            }
            
            return true;
        }
        
        /**
         * Notify customer of payment status
         */
        private function notify_customer_of_payment($phone, $order_id, $success, $reason = '') {
            if ($success) {
                // Send order confirmation
                kwetupizza_notifier()->send_order_confirmation($order_id, $phone);
            } else {
                // Send delivery update
                $eta = null; // You might want to calculate ETA based on order status
                kwetupizza_notifier()->send_delivery_update($order_id, 'payment_failed', $eta);
            }
        }
    }
}

/**
 * Modern Notification System with Templates
 */
if (!function_exists('kwetupizza_notifier')) {
    function kwetupizza_notifier() {
        static $instance = null;
        
        if ($instance === null) {
            $instance = new KwetuPizza_Notifier();
        }
        
        return $instance;
    }
}

if (!class_exists('KwetuPizza_Notifier')) {
    class KwetuPizza_Notifier {
        /**
         * Send order confirmation
         */
        public function send_order_confirmation($order_id, $customer_phone) {
            global $wpdb;
            $orders_table = $wpdb->prefix . 'kwetupizza_orders';
            $order_items_table = $wpdb->prefix . 'kwetupizza_order_items';
            $products_table = $wpdb->prefix . 'kwetupizza_products';
            
            // Get order
            $order = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $orders_table WHERE id = %d", 
                $order_id
            ));
            
            if (!$order) {
                kwetupizza_log("Cannot send confirmation - Order not found: $order_id", 'error', 'notifications.log');
                return false;
            }
            
            // Get order items
            $items = $wpdb->get_results($wpdb->prepare(
                "SELECT oi.*, p.product_name 
                FROM $order_items_table oi
                JOIN $products_table p ON oi.product_id = p.id
                WHERE oi.order_id = %d",
                $order_id
            ));
            
            // Format message
            $message = "🎉 *Order Confirmation* 🎉\n\n";
            $message .= "Thank you for your order!\n\n";
            $message .= "📋 *Order #$order_id*\n";
            $message .= "Date: " . date('Y-m-d H:i', strtotime($order->created_at)) . "\n\n";
            
            $message .= "*Order Items:*\n";
            foreach ($items as $item) {
                $message .= "{$item->quantity}x {$item->product_name} - " . 
                    number_format($item->price * $item->quantity, 2) . " TZS\n";
            }
            
            $message .= "\nTotal: " . number_format($order->total, 2) . " TZS\n";
            $message .= "Delivery Address: {$order->delivery_address}\n\n";
            
            $message .= "Your order is now being processed. You'll receive payment instructions shortly.\n\n";
            $message .= "Type 'status' anytime to check your order status.";
            
            // Send message
            if (function_exists('kwetupizza_send_whatsapp_message')) {
                kwetupizza_send_whatsapp_message($customer_phone, $message);
            }
            
            // Send SMS fallback if configured
            if (function_exists('kwetupizza_send_sms')) {
                $sms_message = "Your KwetuPizza order #$order_id is confirmed. Total: " . 
                    number_format($order->total, 2) . " TZS. Payment instructions will follow.";
                kwetupizza_send_sms($customer_phone, $sms_message);
            }
            
            return true;
        }

        /**
         * Send payment confirmation
         */
        public function send_payment_confirmation($order_id, $customer_phone) {
            global $wpdb;
            $orders_table = $wpdb->prefix . 'kwetupizza_orders';
            
            // Get order
            $order = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $orders_table WHERE id = %d", 
                $order_id
            ));
            
            if (!$order) {
                kwetupizza_log("Cannot send payment confirmation - Order not found: $order_id", 'error', 'notifications.log');
                return false;
            }
            
            // Format message
            $message = "💰 *Payment Confirmed!* 💰\n\n";
            $message .= "We've received your payment of " . number_format($order->total, 2) . 
                " TZS for Order #$order_id.\n\n";
            $message .= "Your order is now being prepared and will be delivered to:\n";
            $message .= "{$order->delivery_address}\n\n";
            $message .= "We'll update you when your order is on the way!\n\n";
            $message .= "Type 'status' anytime to check your order status.";
            
            // Send message
            if (function_exists('kwetupizza_send_whatsapp_message')) {
                kwetupizza_send_whatsapp_message($customer_phone, $message);
            }
            
            // Send SMS confirmation
            if (function_exists('kwetupizza_send_sms')) {
                $sms_message = "Payment confirmed for KwetuPizza order #$order_id. " . 
                    "Your pizza is being prepared and will be delivered soon.";
                kwetupizza_send_sms($customer_phone, $sms_message);
            }
            
            // Update order status
            $wpdb->update(
                $orders_table,
                [
                    'status' => 'processing',
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $order_id]
            );
            
            // Add order timeline event
            if (function_exists('kwetupizza_add_order_timeline_event')) {
                kwetupizza_add_order_timeline_event(
                    $order_id, 
                    'payment_confirmed', 
                    'Payment confirmed, order is being prepared'
                );
            }
            
            return true;
        }

        /**
         * Send delivery updates
         */
        public function send_delivery_update($order_id, $status, $eta = null) {
            global $wpdb;
            $orders_table = $wpdb->prefix . 'kwetupizza_orders';
            
            // Get order
            $order = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $orders_table WHERE id = %d", 
                $order_id
            ));
            
            if (!$order) {
                kwetupizza_log("Cannot send delivery update - Order not found: $order_id", 'error', 'notifications.log');
                return false;
            }
            
            // Format messages based on status
            $messages = [
                'preparing' => [
                    'title' => '👨‍🍳 *Your Pizza is Being Prepared* 👨‍🍳',
                    'body' => "Our chefs are now preparing your delicious order!\n\nWe'll update you when it's ready for delivery.",
                    'icon' => '👨‍🍳'
                ],
                'out_for_delivery' => [
                    'title' => '🛵 *Your Order is on the Way!* 🛵',
                    'body' => "Your order has been dispatched and is on its way to you!",
                    'icon' => '🛵'
                ],
                'delivered' => [
                    'title' => '🎉 *Your Order has been Delivered* 🎉',
                    'body' => "Your order has been delivered. Enjoy your meal!\n\nThank you for choosing KwetuPizza.",
                    'icon' => '🎉'
                ]
            ];
            
            // Check if status exists
            if (!isset($messages[$status])) {
                return false;
            }
            
            // Build message
            $message = $messages[$status]['title'] . "\n\n";
            $message .= "Order #$order_id\n\n";
            $message .= $messages[$status]['body'] . "\n\n";
            
            // Add ETA if provided
            if ($eta && ($status === 'out_for_delivery')) {
                $message .= "Estimated delivery time: $eta\n\n";
            }
            
            // Add a note about feedback for delivered orders
            if ($status === 'delivered') {
                $message .= "We'd love to hear your feedback! We'll send you a link shortly.";
            }
            
            // Send WhatsApp message
            if (function_exists('kwetupizza_send_whatsapp_message')) {
                kwetupizza_send_whatsapp_message($order->customer_phone, $message);
            }
            
            // Send SMS for out_for_delivery and delivered statuses
            if (in_array($status, ['out_for_delivery', 'delivered']) && function_exists('kwetupizza_send_sms')) {
                $sms_message = $messages[$status]['icon'] . " KwetuPizza: Order #$order_id " . 
                    strtolower(str_replace('*', '', $messages[$status]['title']));
                
                if ($eta && $status === 'out_for_delivery') {
                    $sms_message .= " Estimated delivery: $eta";
                }
                
                kwetupizza_send_sms($order->customer_phone, $sms_message);
            }
            
            // Update order status
            $wpdb->update(
                $orders_table,
                [
                    'status' => $status,
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $order_id]
            );
            
            // Add timeline event
            if (function_exists('kwetupizza_add_order_timeline_event')) {
                $event_description = $messages[$status]['body'];
                
                if ($eta && $status === 'out_for_delivery') {
                    $event_description .= " ETA: $eta";
                }
                
                kwetupizza_add_order_timeline_event(
                    $order_id, 
                    $status, 
                    $event_description
                );
            }
            
            // For delivered orders, schedule feedback request
            if ($status === 'delivered' && function_exists('wp_schedule_single_event')) {
                wp_schedule_single_event(
                    time() + (30 * MINUTE_IN_SECONDS), 
                    'kwetupizza_send_feedback_request', 
                    [$order_id]
                );
            }
            
            return true;
        }
        
        /**
         * Send delivery confirmation request
         */
        public function send_delivery_confirmation_request($order_id) {
            global $wpdb;
            $orders_table = $wpdb->prefix . 'kwetupizza_orders';
            
            // Get order
            $order = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $orders_table WHERE id = %d", 
                $order_id
            ));
            
            if (!$order) {
                kwetupizza_log("Cannot send delivery confirmation - Order not found: $order_id", 'error', 'notifications.log');
                return false;
            }
            
            // Generate confirmation token
            $confirmation_token = wp_generate_password(12, false);
            
            // Save token to order
            $wpdb->update(
                $orders_table,
                [
                    'confirmation_token' => $confirmation_token,
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $order_id]
            );
            
            // Create confirmation link
            $confirmation_link = home_url("/confirm-delivery/?order=$order_id&token=$confirmation_token");
            
            // Format message
            $message = "📦 *Order Delivery Confirmation* 📦\n\n";
            $message .= "We hope you received your order #$order_id and everything is to your satisfaction!\n\n";
            $message .= "Please confirm you've received your order by clicking this link:\n";
            $message .= "$confirmation_link\n\n";
            $message .= "Thank you for choosing KwetuPizza!";
            
            // Send message
            if (function_exists('kwetupizza_send_whatsapp_message')) {
                kwetupizza_send_whatsapp_message($order->customer_phone, $message);
                return true;
            }
            
            return false;
        }
        
        /**
         * Send customer feedback request
         */
        public function send_feedback_request($order_id) {
            global $wpdb;
            $orders_table = $wpdb->prefix . 'kwetupizza_orders';
            
            // Get order
            $order = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $orders_table WHERE id = %d", 
                $order_id
            ));
            
            if (!$order) {
                kwetupizza_log("Cannot send feedback request - Order not found: $order_id", 'error', 'notifications.log');
                return false;
            }
            
            // Generate feedback token
            $feedback_token = wp_generate_password(12, false);
            
            // Save token to order
            $wpdb->update(
                $orders_table,
                [
                    'feedback_token' => $feedback_token,
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $order_id]
            );
            
            // Create feedback link
            $feedback_link = home_url("/order-feedback/?order=$order_id&token=$feedback_token");
            
            // Format message
            $message = "🌟 *How was your KwetuPizza experience?* 🌟\n\n";
            $message .= "We hope you enjoyed your order!\n\n";
            $message .= "We'd love to hear your feedback about your recent order #$order_id.\n\n";
            $message .= "Please take a moment to rate your experience here:\n";
            $message .= "$feedback_link\n\n";
            $message .= "Your feedback helps us improve our service. Thank you!";
            
            // Send message
            if (function_exists('kwetupizza_send_whatsapp_message')) {
                kwetupizza_send_whatsapp_message($order->customer_phone, $message);
                return true;
            }
            
            return false;
        }
    }
}

/**
 * Modern Webhook Controller
 */
if (!function_exists('kwetupizza_webhook_controller')) {
    function kwetupizza_webhook_controller() {
        static $instance = null;
        
        if ($instance === null) {
            $instance = new KwetuPizza_WebhookController();
        }
        
        return $instance;
    }
}

if (!class_exists('KwetuPizza_WebhookController')) {
    class KwetuPizza_WebhookController {
        /**
         * Handle Flutterwave webhook
         */
        public function handle_flutterwave_webhook($request) {
            // Verify webhook signature
            $signature = $request->get_header('verif-hash');
            $secret = get_option('kwetupizza_flw_webhook_secret');
            
            // Log incoming webhook
            kwetupizza_log('Flutterwave webhook received with signature: ' . $signature, 'info', 'webhook.log');
            
            // Check signature
            if (empty($signature) || $signature !== $secret) {
                kwetupizza_log('Invalid webhook signature', 'error', 'webhook.log');
                return new WP_REST_Response('Invalid signature', 403);
            }
            
            // Get webhook data
            $payload = json_decode($request->get_body(), true);
            kwetupizza_log('Webhook payload: ' . json_encode($payload), 'info', 'webhook.log');
            
            // Process based on event type
            if (!isset($payload['event'])) {
                return new WP_REST_Response('Invalid webhook data', 400);
            }
            
            switch ($payload['event']) {
                case 'charge.completed':
                    return $this->handle_payment_event($payload);
                    
                default:
                    kwetupizza_log('Unhandled event type: ' . $payload['event'], 'info', 'webhook.log');
                    return new WP_REST_Response('Event not handled', 200);
            }
        }
        
        /**
         * Handle payment webhook event
         */
        private function handle_payment_event($payload) {
            if (!isset($payload['data']['status']) || !isset($payload['data']['tx_ref'])) {
                kwetupizza_log('Missing status or tx_ref in webhook', 'error', 'webhook.log');
                return new WP_REST_Response('Invalid payment data', 400);
            }
            
            $tx_ref = $payload['data']['tx_ref'];
            $status = $payload['data']['status'];
            $transaction_id = isset($payload['data']['id']) ? $payload['data']['id'] : '';
            
            // Extract order ID from tx_ref (format: kwetupizza-ORDER_ID-TIMESTAMP)
            $pattern = '/kwetupizza-(\d+)-/';
            if (!preg_match($pattern, $tx_ref, $matches)) {
                // Try alternate format: order-ORDER_ID-TIMESTAMP
                $pattern = '/order-(\d+)-/';
                if (!preg_match($pattern, $tx_ref, $matches)) {
                    kwetupizza_log('Could not extract order ID from tx_ref: ' . $tx_ref, 'error', 'webhook.log');
                    return new WP_REST_Response('Invalid transaction reference', 400);
                }
            }
            
            $order_id = (int)$matches[1];
            kwetupizza_log("Processing payment for Order #$order_id with status: $status", 'info', 'webhook.log');
            
            if ($status === 'successful') {
                // Get payment processor
                $payment_processor = kwetupizza_payment_processor();
                
                try {
                    // Verify the transaction
                    $verification_result = $payment_processor->verify_payment($transaction_id);
                    
                    // Process successful payment
                    if (isset($verification_result['success']) && $verification_result['success']) {
                        $payment_processor->process_successful_payment($verification_result, $order_id);
                        
                        // Send notification
                        kwetupizza_notifier()->send_payment_confirmation($order_id, $this->get_customer_phone($order_id));
                        
                        return new WP_REST_Response('Payment processed successfully', 200);
                    } else {
                        kwetupizza_log("Payment verification failed for Order #$order_id", 'error', 'webhook.log');
                        $payment_processor->process_failed_payment($order_id, 'Verification failed');
                        return new WP_REST_Response('Payment verification failed', 200);
                    }
                } catch (Exception $e) {
                    kwetupizza_log('Error processing payment: ' . $e->getMessage(), 'error', 'webhook.log');
                    $payment_processor->process_failed_payment($order_id, $e->getMessage());
                    return new WP_REST_Response('Error processing payment', 200);
                }
            } elseif ($status === 'failed') {
                // Get failure reason
                $reason = isset($payload['data']['processor_response']) 
                    ? $payload['data']['processor_response'] 
                    : (isset($payload['data']['gateway_response']) ? $payload['data']['gateway_response'] : 'Unknown error');
                
                // Process failed payment
                kwetupizza_payment_processor()->process_failed_payment($order_id, $reason);
                
                return new WP_REST_Response('Failed payment processed', 200);
            } else {
                kwetupizza_log("Unhandled payment status: $status", 'info', 'webhook.log');
                return new WP_REST_Response('Payment status not handled', 200);
            }
        }
        
        /**
         * Get customer phone from order
         */
        private function get_customer_phone($order_id) {
            global $wpdb;
            $orders_table = $wpdb->prefix . 'kwetupizza_orders';
            
            $phone = $wpdb->get_var($wpdb->prepare(
                "SELECT customer_phone FROM $orders_table WHERE id = %d",
                $order_id
            ));
            
            return $phone;
        }
    }
}

/**
 * Main WhatsApp message handler with state machine
 */
if (!function_exists('kwetupizza_handle_whatsapp_message')) {
    function kwetupizza_handle_whatsapp_message($from, $message, $interactive_data = null) {
        // Log incoming message
        kwetupizza_log("Received message from $from: $message", 'info', 'whatsapp.log');
        kwetupizza_log_context_and_input($from, $message);
        
        // Track special commands first
        if ($message === 'reset_context') {
            // Special debug command to reset context
            kwetupizza_set_conversation_context($from, ['state' => 'init']);
            kwetupizza_send_whatsapp_message($from, "Context reset. Send 'menu' to start ordering.");
            return true;
        }
        
        try {
            // Use the modern order processor
            return kwetupizza_order_processor()->handle_message($from, $message, $interactive_data);
        } catch (Exception $e) {
            // Log error and send fallback message
            kwetupizza_log("Error handling message: " . $e->getMessage(), 'error', 'whatsapp.log');
            kwetupizza_send_whatsapp_message($from, "Sorry, something went wrong. Please try again or type 'help'.");
            return false;
        }
    }
}

/**
 * Handle WhatsApp webhook incoming data
 */
if (!function_exists('kwetupizza_handle_whatsapp_messages')) {
    function kwetupizza_handle_whatsapp_messages($request) {
        // Get webhook data
        $webhook_data = json_decode($request->get_body(), true);
        
        // Log the incoming data for debugging
        $log_file = plugin_dir_path(dirname(__FILE__)) . 'includes/whatsapp-webhook.log';
        file_put_contents($log_file, "WhatsApp Webhook Data: " . print_r($webhook_data, true) . PHP_EOL, FILE_APPEND);
        
        // Process text messages
        if (isset($webhook_data['entry'][0]['changes'][0]['value']['messages'][0])) {
            $message_data = $webhook_data['entry'][0]['changes'][0]['value']['messages'][0];
            $from = $message_data['from'];
            
            // Handle text messages
            if ($message_data['type'] === 'text' && isset($message_data['text']['body'])) {
                $message = trim($message_data['text']['body']);
                kwetupizza_handle_whatsapp_message($from, $message);
                return new WP_REST_Response('Message processed', 200);
            }
            
            // Handle interactive messages
            if ($message_data['type'] === 'interactive' && isset($message_data['interactive'])) {
                kwetupizza_handle_whatsapp_message($from, '', $message_data['interactive']);
                return new WP_REST_Response('Interactive message processed', 200);
            }
        }
        
        // Handle status updates
        elseif (isset($webhook_data['entry'][0]['changes'][0]['value']['statuses'][0])) {
            error_log('Received a message status update');
            return new WP_REST_Response('Status update received', 200);
        }
        
        // Invalid or unrecognized data
        else {
            error_log('WhatsApp message structure not as expected.');
            return new WP_REST_Response('No valid message found', 400);
        }
        
        return new WP_REST_Response('Invalid data received', 400);
    }
}

/**
 * Handler for Flutterwave webhook
 */
if (!function_exists('kwetupizza_flutterwave_webhook')) {
    function kwetupizza_flutterwave_webhook($request) {
        return kwetupizza_webhook_controller()->handle_flutterwave_webhook($request);
    }
}