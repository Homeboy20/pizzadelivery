<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * KwetuPizza Interactive WhatsApp Buttons
 * This file contains functions for implementing interactive buttons in the WhatsApp checkout flow
 */

/**
 * Function to create and send WhatsApp interactive buttons
 *
 * @param string $phone The recipient's phone number
 * @param string $message The message text
 * @param array $buttons Array of button options
 * @return bool Success or failure
 */
function kwetupizza_send_buttons($phone, $message, $buttons) {
    // Log the attempt for debugging
    kwetupizza_log("Attempting to send WhatsApp buttons to $phone", 'info', 'whatsapp.log');
    
    $token = get_option('kwetupizza_whatsapp_token');
    $phone_id = get_option('kwetupizza_whatsapp_phone_id');
    
    if (empty($token) || empty($phone_id)) {
        kwetupizza_log('WhatsApp API credentials not set or incomplete', 'error', 'whatsapp.log');
        return false;
    }
    
    // Sanitize phone number
    $phone = kwetupizza_sanitize_phone($phone);
    
    // Ensure the phone number starts with country code and has no leading '+'
    if (substr($phone, 0, 1) === '+') {
        $phone = substr($phone, 1);
    }
    
    // WhatsApp Cloud API endpoint
    $url = "https://graph.facebook.com/v17.0/{$phone_id}/messages";
    
    // Format buttons for WhatsApp API
    $formatted_buttons = [];
    foreach ($buttons as $button) {
        $formatted_buttons[] = [
            'type' => 'reply',
            'reply' => [
                'id' => $button['id'],
                'title' => $button['title']
            ]
        ];
    }
    
    // Setup the interactive message payload
    $data = [
        'messaging_product' => 'whatsapp',
        'recipient_type' => 'individual',
        'to' => $phone,
        'type' => 'interactive',
        'interactive' => [
            'type' => 'button',
            'body' => [
                'text' => $message
            ],
            'action' => [
                'buttons' => $formatted_buttons
            ]
        ]
    ];
    
    // Send the request
    $response = wp_remote_post($url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode($data),
        'timeout' => 30
    ]);
    
    // Check for errors
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        kwetupizza_log("WhatsApp API Error: $error_message", 'error', 'whatsapp.log');
        return false;
    }
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    // Check for successful response
    if (isset($body['messages']) && !empty($body['messages'])) {
        kwetupizza_log("WhatsApp buttons sent successfully to $phone", 'info', 'whatsapp.log');
        return true;
    } else {
        $error_detail = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown error';
        kwetupizza_log("WhatsApp buttons failed: $error_detail", 'error', 'whatsapp.log');
        return false;
    }
}

/**
 * Function to create and send WhatsApp interactive list
 *
 * @param string $phone The recipient's phone number
 * @param string $message The message text
 * @param array $list_items Array of list item options
 * @param string $button_text The button text
 * @return bool Success or failure
 */
function kwetupizza_send_list($phone, $message, $list_items, $button_text = 'Select an option') {
    // Log the attempt for debugging
    kwetupizza_log("Attempting to send WhatsApp list to $phone", 'info', 'whatsapp.log');
    
    $token = get_option('kwetupizza_whatsapp_token');
    $phone_id = get_option('kwetupizza_whatsapp_phone_id');
    
    if (empty($token) || empty($phone_id)) {
        kwetupizza_log('WhatsApp API credentials not set or incomplete', 'error', 'whatsapp.log');
        return false;
    }
    
    // Sanitize phone number
    $phone = kwetupizza_sanitize_phone($phone);
    
    // Ensure the phone number starts with country code and has no leading '+'
    if (substr($phone, 0, 1) === '+') {
        $phone = substr($phone, 1);
    }
    
    // WhatsApp Cloud API endpoint
    $url = "https://graph.facebook.com/v17.0/{$phone_id}/messages";
    
    // Format list items for WhatsApp API
    $formatted_items = [];
    foreach ($list_items as $item) {
        $formatted_item = [
            'id' => $item['id'],
            'title' => $item['title']
        ];
        
        if (isset($item['description'])) {
            $formatted_item['description'] = $item['description'];
        }
        
        $formatted_items[] = $formatted_item;
    }
    
    // Setup the interactive message payload
    $data = [
        'messaging_product' => 'whatsapp',
        'recipient_type' => 'individual',
        'to' => $phone,
        'type' => 'interactive',
        'interactive' => [
            'type' => 'list',
            'body' => [
                'text' => $message
            ],
            'action' => [
                'button' => $button_text,
                'sections' => [
                    [
                        'title' => 'Options',
                        'rows' => $formatted_items
                    ]
                ]
            ]
        ]
    ];
    
    // Send the request
    $response = wp_remote_post($url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode($data),
        'timeout' => 30
    ]);
    
    // Check for errors
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        kwetupizza_log("WhatsApp API Error: $error_message", 'error', 'whatsapp.log');
        return false;
    }
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    // Check for successful response
    if (isset($body['messages']) && !empty($body['messages'])) {
        kwetupizza_log("WhatsApp list sent successfully to $phone", 'info', 'whatsapp.log');
        return true;
    } else {
        $error_detail = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown error';
        kwetupizza_log("WhatsApp list failed: $error_detail", 'error', 'whatsapp.log');
        return false;
    }
}

/**
 * Extract interactive button response ID from webhook data
 *
 * @param array $webhook_data The webhook data received from WhatsApp
 * @return string|null The button ID or null if not found
 */
function kwetupizza_get_button_response($webhook_data) {
    if (isset($webhook_data['entry'][0]['changes'][0]['value']['messages'][0]['interactive']['button_reply']['id'])) {
        return $webhook_data['entry'][0]['changes'][0]['value']['messages'][0]['interactive']['button_reply']['id'];
    }
    return null;
}

/**
 * Extract interactive list response ID from webhook data
 *
 * @param array $webhook_data The webhook data received from WhatsApp
 * @return string|null The list item ID or null if not found
 */
function kwetupizza_get_list_response($webhook_data) {
    if (isset($webhook_data['entry'][0]['changes'][0]['value']['messages'][0]['interactive']['list_reply']['id'])) {
        return $webhook_data['entry'][0]['changes'][0]['value']['messages'][0]['interactive']['list_reply']['id'];
    }
    return null;
}

/**
 * Send checkout options (add more or proceed to checkout) as buttons
 *
 * @param string $from Customer's phone number
 */
function kwetupizza_send_checkout_options($from) {
    $message = "What would you like to do next?";
    $buttons = [
        ['id' => 'add', 'title' => 'Add More Items'],
        ['id' => 'checkout', 'title' => 'Proceed to Checkout']
    ];
    
    return kwetupizza_send_buttons($from, $message, $buttons);
}

/**
 * Send delivery zones as a list for selection
 *
 * @param string $from Customer's phone number
 */
function kwetupizza_send_delivery_zones($from) {
    global $wpdb;
    $zones_table = $wpdb->prefix . 'kwetupizza_delivery_zones';
    
    // Get all delivery zones
    $zones = $wpdb->get_results("SELECT id, zone_name, description, delivery_fee FROM $zones_table ORDER BY delivery_fee ASC");
    
    if (empty($zones)) {
        return false;
    }
    
    $message = "📍 *Select Your Delivery Area* 📍\n\nPlease choose your delivery area:";
    
    $list_items = [];
    foreach ($zones as $zone) {
        $list_items[] = [
            'id' => 'zone_' . $zone->id,
            'title' => $zone->zone_name,
            'description' => $zone->description . " - Fee: " . number_format($zone->delivery_fee, 2) . " TZS"
        ];
    }
    
    return kwetupizza_send_list($from, $message, $list_items, 'Select Delivery Area');
}

/**
 * Send payment options as a list for selection
 *
 * @param string $from Customer's phone number
 * @param string $order_summary The order summary to show above payment options
 */
function kwetupizza_send_payment_options($from, $order_summary) {
    $message = $order_summary . "\n\nPlease select your payment method:";
    
    $list_items = [
        ['id' => 'payment_1', 'title' => 'Vodacom M-Pesa', 'description' => 'Pay using M-Pesa mobile money'],
        ['id' => 'payment_2', 'title' => 'Tigo Pesa', 'description' => 'Pay using Tigo Pesa mobile money'],
        ['id' => 'payment_3', 'title' => 'Airtel Money', 'description' => 'Pay using Airtel Money mobile money'],
        ['id' => 'payment_4', 'title' => 'Halotel Halopesa', 'description' => 'Pay using Halopesa mobile money'],
        ['id' => 'payment_5', 'title' => 'Card Payment', 'description' => 'Pay with credit/debit card via PayPal']
    ];
    
    return kwetupizza_send_list($from, $message, $list_items, 'Select Payment Method');
}

/**
 * Send phone number confirmation buttons (yes/no)
 *
 * @param string $from Customer's phone number
 */
function kwetupizza_send_phone_confirmation($from) {
    $message = "Would you like to use your WhatsApp number ($from) for payment?";
    $buttons = [
        ['id' => 'yes', 'title' => 'Yes'],
        ['id' => 'no', 'title' => 'No (Use Another Number)']
    ];
    
    return kwetupizza_send_buttons($from, $message, $buttons);
}

/**
 * Update Kwetu Pizza function files to use interactive buttons
 * This should be called on plugin activation or update
 */
function kwetupizza_update_for_interactive_buttons() {
    // Check if confirm_order_and_request_address function already uses interactive buttons
    $functions_file = plugin_dir_path(dirname(__FILE__)) . 'includes/functions.php';
    $functions_content = file_get_contents($functions_file);
    
    if (strpos($functions_content, 'kwetupizza_send_checkout_options') === false) {
        // Modify the confirm_order_and_request_address function
        $pattern = '/function kwetupizza_confirm_order_and_request_address\(\$from, \$product_id, \$quantity\).*?kwetupizza_set_conversation_context\(\$from, array_merge\(\$context, \[\'awaiting\' => \'add_or_checkout\'\]\)\);/s';
        $replacement = "function kwetupizza_confirm_order_and_request_address(\$from, \$product_id, \$quantity) {
        global \$wpdb;
        \$context = kwetupizza_get_conversation_context(\$from);

        foreach (\$context['cart'] as &\$cart_item) {
            if (\$cart_item['product_id'] == \$product_id) {
                \$cart_item['quantity'] = \$quantity;
                \$cart_item['total'] = \$cart_item['price'] * \$quantity;
                break;
            }
        }

        kwetupizza_set_conversation_context(\$from, \$context);
        
        // Try to use interactive buttons if available
        if (function_exists('kwetupizza_send_checkout_options')) {
            kwetupizza_send_checkout_options(\$from);
        } else {
            // Fallback to regular text message
            \$message = \"Would you like to add more items or proceed to checkout? Type 'add' to add more items or 'checkout' to proceed.\";
            kwetupizza_send_whatsapp_message(\$from, \$message);
        }

        kwetupizza_set_conversation_context(\$from, array_merge(\$context, ['awaiting' => 'add_or_checkout']));";
        
        $functions_content = preg_replace($pattern, $replacement, $functions_content);
        
        // Save the modified file
        file_put_contents($functions_file, $functions_content);
    }
}

// Hook to update functions on plugin activation
register_activation_hook(__FILE__, 'kwetupizza_update_for_interactive_buttons'); 