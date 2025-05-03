<?php
/**
 * KwetuPizza API Controller
 * 
 * This file registers all API endpoints for the KwetuPizza plugin.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include core functions
if (file_exists(dirname(__FILE__) . '/functions.php')) {
    require_once dirname(__FILE__) . '/functions.php';
}

/**
 * Register all REST API routes
 */
if (!function_exists('kwetupizza_register_api_routes')) {
    function kwetupizza_register_api_routes() {
        // Flutterwave webhook
        register_rest_route('kwetupizza/v1', '/flutterwave-webhook', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'kwetupizza_flutterwave_webhook',
            'permission_callback' => '__return_true',
        ));
        
        // WhatsApp webhook verification
        register_rest_route('kwetupizza/v1', '/whatsapp-webhook', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'kwetupizza_handle_whatsapp_verification',
            'permission_callback' => '__return_true',
        ));

        // WhatsApp webhook messages
        register_rest_route('kwetupizza/v1', '/whatsapp-webhook', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'kwetupizza_handle_whatsapp_messages',
            'permission_callback' => '__return_true',
        ));
        
        // NextSMS webhook
        register_rest_route('kwetupizza/v1', '/sms-webhook', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'kwetupizza_nextsms_webhook',
            'permission_callback' => '__return_true',
        ));
        
        // Order tracking API
        register_rest_route('kwetupizza/v1', '/track-order/(?P<order_id>\d+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'kwetupizza_track_order_api',
            'permission_callback' => '__return_true',
            'args' => array(
                'order_id' => array(
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ),
            ),
        ));
            
        // Loyalty API endpoint
        register_rest_route('kwetupizza/v1', '/loyalty/(?P<phone>[0-9]+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'kwetupizza_get_customer_loyalty',
            'permission_callback' => '__return_true',
            'args' => array(
                'phone' => array(
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ),
            ),
        ));
        
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
 * Handle WhatsApp webhook verification
 */
if (!function_exists('kwetupizza_handle_whatsapp_verification')) {
function kwetupizza_handle_whatsapp_verification(WP_REST_Request $request) {
    $verify_token = get_option('kwetupizza_whatsapp_verify_token');
        
    $mode = $request->get_param('hub_mode');
    $token = $request->get_param('hub_verify_token');
    $challenge = $request->get_param('hub_challenge');
    
    if ($mode === 'subscribe' && $token === $verify_token) {
        return new WP_REST_Response($challenge, 200);
    }
    
    return new WP_REST_Response('Verification failed', 403);
    }
}

/**
 * Handle NextSMS webhook
 */
if (!function_exists('kwetupizza_nextsms_webhook')) {
function kwetupizza_nextsms_webhook(WP_REST_Request $request) {
        $params = $request->get_params();
        
        kwetupizza_log('NextSMS webhook received: ' . json_encode($params), 'info', 'nextsms-webhook.log');
        
        // Process the SMS response from NextSMS
        if (isset($params['status']) && isset($params['messageId'])) {
            // Update delivery status in your database
            return new WP_REST_Response('SMS status processed', 200);
}

        return new WP_REST_Response('Invalid data', 400);
        }
}

/**
 * Track order API endpoint
 */
if (!function_exists('kwetupizza_track_order_api')) {
function kwetupizza_track_order_api(WP_REST_Request $request) {
    $order_id = $request->get_param('order_id');
    
    global $wpdb;
    $orders_table = $wpdb->prefix . 'kwetupizza_orders';
        $timeline_table = $wpdb->prefix . 'kwetupizza_order_timeline';
        
        $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM $orders_table WHERE id = %d", $order_id));
    
    if (!$order) {
            return new WP_REST_Response(['error' => 'Order not found'], 404);
    }
    
        $timeline = $wpdb->get_results($wpdb->prepare(
            "SELECT event_type, description, created_at FROM $timeline_table WHERE order_id = %d ORDER BY created_at ASC",
        $order_id
    ));
    
        $data = [
        'order' => [
            'id' => $order->id,
            'status' => $order->status,
            'customer_name' => $order->customer_name,
            'delivery_address' => $order->delivery_address,
                'total' => $order->total,
                'currency' => $order->currency,
                'created_at' => $order->created_at,
                'estimated_delivery_time' => $order->estimated_delivery_time
            ],
            'timeline' => $timeline
        ];
        
        return new WP_REST_Response($data, 200);
    }
}

/**
 * Get customer loyalty information
 */
if (!function_exists('kwetupizza_get_customer_loyalty')) {
function kwetupizza_get_customer_loyalty(WP_REST_Request $request) {
    $phone = $request->get_param('phone');
    
    global $wpdb;
    $loyalty_table = $wpdb->prefix . 'kwetupizza_customer_loyalty';
    
        $loyalty = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $loyalty_table WHERE customer_phone = %s",
            $phone
    ));
    
        if (!$loyalty) {
            return new WP_REST_Response(['error' => 'Customer not found in loyalty program'], 404);
    }
    
        $data = [
            'phone' => $phone,
            'points' => $loyalty->points,
            'total_orders' => $loyalty->total_orders,
            'total_spent' => $loyalty->total_spent,
            'rewards_available' => floor($loyalty->points / 100) // Example: 100 points = 1 reward
        ];
        
        return new WP_REST_Response($data, 200);
                }
}

/**
 * Register Enhanced WhatsApp Webhook
 */
function kwetupizza_register_enhanced_whatsapp_webhook() {
    register_rest_route('kwetupizza/v1', '/enhanced-whatsapp-webhook', array(
        'methods' => 'POST',
        'callback' => 'kwetupizza_handle_enhanced_whatsapp_webhook',
        'permission_callback' => '__return_true'
    ));
}
add_action('rest_api_init', 'kwetupizza_register_enhanced_whatsapp_webhook'); 