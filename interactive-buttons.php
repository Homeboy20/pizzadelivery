<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * KwetuPizza Interactive WhatsApp Buttons Functions
 * This file contains functions for implementing interactive buttons in WhatsApp messages
 */

/**
 * Create WhatsApp interactive message payload
 * 
 * @param string $type Type of interactive message: 'button' or 'list'
 * @param string $message The text body message
 * @param array $options Options for buttons or list items
 * @return array Structured message payload for WhatsApp API
 */
function kwetupizza_create_interactive_message($type, $message, $options) {
    $payload = [];
    
    if ($type === 'button') {
        $buttons = [];
        // WhatsApp API limits to 3 buttons
        $option_count = min(count($options), 3);
        
        for ($i = 0; $i < $option_count; $i++) {
            $buttons[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => $options[$i]['id'],
                    'title' => $options[$i]['title']
                ]
            ];
        }
        
        $payload = [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => [
                    'text' => $message
                ],
                'action' => [
                    'buttons' => $buttons
                ]
            ]
        ];
    } 
    elseif ($type === 'list') {
        $list_items = [];
        // WhatsApp API has limits on list items
        $option_count = min(count($options), 10);
        
        for ($i = 0; $i < $option_count; $i++) {
            $item = [
                'id' => $options[$i]['id'],
                'title' => $options[$i]['title']
            ];
            
            if (isset($options[$i]['description'])) {
                $item['description'] = $options[$i]['description'];
            }
            
            $list_items[] = $item;
        }
        
        $payload = [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'body' => [
                    'text' => $message
                ],
                'action' => [
                    'button' => 'Select Option',
                    'sections' => [
                        [
                            'title' => 'Options',
                            'rows' => $list_items
                        ]
                    ]
                ]
            ]
        ];
    }
    
    return $payload;
}

/**
 * Send WhatsApp message with optional interactive elements
 * 
 * Extends the existing WhatsApp messaging function to support interactive elements
 * 
 * @param string $phone Recipient phone number
 * @param string $message Message content
 * @param string $interactive_type Optional: 'button' or 'list'
 * @param array $interactive_options Optional: Options for buttons or list items
 * @return bool Success or failure
 */
function kwetupizza_send_interactive_message($phone, $message, $interactive_type = null, $interactive_options = []) {
    // Log the attempt for debugging
    kwetupizza_log("Attempting to send interactive WhatsApp message to $phone", 'info', 'whatsapp.log');
    
    $token = get_option('kwetupizza_whatsapp_token');
    $phone_id = get_option('kwetupizza_whatsapp_phone_id');
    
    if (empty($token) || empty($phone_id)) {
        kwetupizza_log('WhatsApp API credentials not set or incomplete', 'error', 'whatsapp.log');
        return false;
    }
    
    // Sanitize phone number with enhanced validation
    $phone = kwetupizza_sanitize_phone($phone);
    
    // Ensure the phone number starts with country code and has no leading '+'
    if (substr($phone, 0, 1) === '+') {
        $phone = substr($phone, 1);
    }
    
    // WhatsApp Cloud API endpoint
    $url = "https://graph.facebook.com/v17.0/{$phone_id}/messages";

    // Setup the base request payload
    $data = [
        'messaging_product' => 'whatsapp',
        'recipient_type' => 'individual',
        'to' => $phone
    ];
    
    // Check if we need to send an interactive message
    if ($interactive_type && in_array($interactive_type, ['button', 'list']) && !empty($interactive_options)) {
        $interactive_payload = kwetupizza_create_interactive_message($interactive_type, $message, $interactive_options);
        $data = array_merge($data, $interactive_payload);
    } else {
        // Standard text message
        $data['type'] = 'text';
        $data['text'] = [
            'preview_url' => false,
            'body' => $message
        ];
    }
    
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
        kwetupizza_log("Interactive WhatsApp message sent successfully to $phone", 'info', 'whatsapp.log');
        return true;
    } else {
        $error_detail = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown error';
        kwetupizza_log("WhatsApp message failed: $error_detail", 'error', 'whatsapp.log');
        return false;
    }
}

/**
 * Process an interactive message response
 * 
 * @param array $request_data The webhook request data
 * @return array|null Extracted interactive data or null
 */
function kwetupizza_process_interactive_response($request_data) {
    $interactive_data = null;
    
    if (isset($request_data['entry'][0]['changes'][0]['value']['messages'][0]['interactive'])) {
        $interactive = $request_data['entry'][0]['changes'][0]['value']['messages'][0]['interactive'];
        
        if (isset($interactive['button_reply'])) {
            $interactive_data = [
                'type' => 'button',
                'id' => $interactive['button_reply']['id'],
                'title' => $interactive['button_reply']['title']
            ];
        } elseif (isset($interactive['list_reply'])) {
            $interactive_data = [
                'type' => 'list',
                'id' => $interactive['list_reply']['id'],
                'title' => $interactive['list_reply']['title'],
                'description' => isset($interactive['list_reply']['description']) ? $interactive['list_reply']['description'] : ''
            ];
        }
    }
    
    return $interactive_data;
}

/**
 * Send interactive checkout buttons for 'add more items' or 'checkout'
 * 
 * @param string $from Customer's phone number
 * @return bool Success or failure
 */
function kwetupizza_send_checkout_buttons($from) {
    $message = "Would you like to add more items or proceed to checkout?";
    
    $options = [
        ['id' => 'add', 'title' => 'Add More Items'],
        ['id' => 'checkout', 'title' => 'Proceed to Checkout']
    ];
    
    return kwetupizza_send_interactive_message($from, $message, 'button', $options);
}

/**
 * Send interactive delivery zone selection list
 * 
 * @param string $from Customer's phone number
 * @param array $zones Array of delivery zone objects
 * @return bool Success or failure
 */
function kwetupizza_send_delivery_zone_list($from, $zones) {
    if (empty($zones)) {
        return false;
    }
    
    $message = "📍 *Select Your Delivery Area* 📍\n\nPlease choose your delivery area:";
    
    $options = [];
    foreach ($zones as $zone) {
        $options[] = [
            'id' => 'zone_' . $zone->id,
            'title' => $zone->zone_name,
            'description' => $zone->description . " - Fee: " . number_format($zone->delivery_fee, 2) . " TZS"
        ];
    }
    
    return kwetupizza_send_interactive_message($from, $message, 'list', $options);
}

/**
 * Send interactive payment method selection list
 * 
 * @param string $from Customer's phone number
 * @param string $order_summary Order summary message
 * @return bool Success or failure
 */
function kwetupizza_send_payment_method_list($from, $order_summary) {
    $message = $order_summary . "\n\nPlease select your payment method:";
    
    $options = [
        ['id' => 'payment_1', 'title' => 'Vodacom M-Pesa', 'description' => 'Pay using M-Pesa mobile money'],
        ['id' => 'payment_2', 'title' => 'Tigo Pesa', 'description' => 'Pay using Tigo Pesa mobile money'],
        ['id' => 'payment_3', 'title' => 'Airtel Money', 'description' => 'Pay using Airtel Money mobile money'],
        ['id' => 'payment_4', 'title' => 'Halotel Halopesa', 'description' => 'Pay using Halopesa mobile money'],
        ['id' => 'payment_5', 'title' => 'Card Payment', 'description' => 'Pay with credit/debit card via PayPal']
    ];
    
    return kwetupizza_send_interactive_message($from, $message, 'list', $options);
}

/**
 * Send Yes/No buttons for phone number confirmation
 * 
 * @param string $from Customer's phone number
 * @param string $message The confirmation message
 * @return bool Success or failure
 */
function kwetupizza_send_yes_no_buttons($from, $message) {
    $options = [
        ['id' => 'yes', 'title' => 'Yes'],
        ['id' => 'no', 'title' => 'No']
    ];
    
    return kwetupizza_send_interactive_message($from, $message, 'button', $options);
}

/**
 * Extract interactive button or list response ID
 * 
 * @param array $webhook_data The webhook request data
 * @return string|null The selected option ID or null
 */
function kwetupizza_get_interactive_response_id($webhook_data) {
    $interactive_data = kwetupizza_process_interactive_response($webhook_data);
    
    if ($interactive_data) {
        return $interactive_data['id'];
    }
    
    return null;
} 