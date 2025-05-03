<?php
/**
 * KwetuPizza Interactive Buttons Integration
 * 
 * This file integrates WhatsApp interactive buttons with the core KwetuPizza functionality.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Make sure the required files exist
$plugin_dir = dirname(__FILE__);
$interactive_buttons_file = $plugin_dir . '/interactive-buttons.php';
$whatsapp_buttons_file = $plugin_dir . '/whatsapp-interactive-buttons.php';

// Include the interactive buttons files if they exist
if (file_exists($interactive_buttons_file)) {
    require_once $interactive_buttons_file;
}

if (file_exists($whatsapp_buttons_file)) {
    require_once $whatsapp_buttons_file;
}

/**
 * Register the enhanced WhatsApp webhook handler in WordPress
 */
function kwetupizza_register_interactive_handlers() {
    // Register the enhanced WhatsApp webhook handler that supports interactive elements
    add_action('rest_api_init', function() {
        register_rest_route('kwetupizza/v1', '/whatsapp-webhook', [
            'methods' => 'POST',
            'callback' => 'kwetupizza_handle_enhanced_whatsapp_webhook',
            'permission_callback' => '__return_true'
        ]);
    });
}
add_action('init', 'kwetupizza_register_interactive_handlers');

/**
 * Enhanced WhatsApp webhook handler that supports interactive elements
 *
 * @param WP_REST_Request $request The webhook request
 * @return WP_REST_Response The response
 */
function kwetupizza_handle_enhanced_whatsapp_webhook($request) {
    $data = $request->get_json_params();
    $response = new WP_REST_Response();
    
    // Log the incoming webhook for debugging
    if (function_exists('kwetupizza_log')) {
        kwetupizza_log(print_r($data, true), 'debug', 'whatsapp-webhook.log');
    } else {
        error_log('KwetuPizza WhatsApp webhook data: ' . print_r($data, true));
    }
    
    // Process message
    if (isset($data['entry'][0]['changes'][0]['value']['messages'][0])) {
        $message_data = $data['entry'][0]['changes'][0]['value']['messages'][0];
        $from = $message_data['from'];
        
        // Check if this is an interactive message
        if (isset($message_data['interactive'])) {
            $interactive_data = null;
            
            if (isset($message_data['interactive']['button_reply'])) {
                $button_id = $message_data['interactive']['button_reply']['id'];
                $text = $message_data['interactive']['button_reply']['title'];
                
                if (function_exists('kwetupizza_log')) {
                    kwetupizza_log("Interactive button response received: $button_id ($text)", 'info', 'whatsapp.log');
                }
                
                // Process the button using enhanced handler if available
                if (function_exists('kwetupizza_enhanced_whatsapp_handler')) {
                    kwetupizza_enhanced_whatsapp_handler($from, $text, $data);
                } else {
                    // Fallback to regular handler
                    kwetupizza_handle_whatsapp_message($from, $text);
                }
            }
            elseif (isset($message_data['interactive']['list_reply'])) {
                $list_id = $message_data['interactive']['list_reply']['id'];
                $text = $message_data['interactive']['list_reply']['title'];
                
                if (function_exists('kwetupizza_log')) {
                    kwetupizza_log("Interactive list response received: $list_id ($text)", 'info', 'whatsapp.log');
                }
                
                // Process the list selection using enhanced handler if available
                if (function_exists('kwetupizza_enhanced_whatsapp_handler')) {
                    kwetupizza_enhanced_whatsapp_handler($from, $text, $data);
                } else {
                    // Fallback to regular handler with the ID as text
                    // This allows handling list selections even if the enhanced handler isn't available
                    if (strpos($list_id, 'zone_') === 0 && function_exists('kwetupizza_handle_delivery_zone_selection')) {
                        $zone_id = substr($list_id, 5);
                        kwetupizza_handle_delivery_zone_selection($from, $zone_id);
                    } 
                    elseif (strpos($list_id, 'payment_') === 0 && function_exists('kwetupizza_handle_payment_provider_response')) {
                        $provider = substr($list_id, 8);
                        kwetupizza_handle_payment_provider_response($from, $provider);
                    }
                    else {
                        // Just pass the text to the regular handler
                        kwetupizza_handle_whatsapp_message($from, $text);
                    }
                }
            }
        }
        else if (isset($message_data['text']['body'])) {
            $message = $message_data['text']['body'];
            
            // Handle with regular handler
            if (function_exists('kwetupizza_handle_whatsapp_message')) {
                kwetupizza_handle_whatsapp_message($from, $message);
            } else {
                error_log("KwetuPizza: WhatsApp handler function not found");
            }
        }
    }
    
    // Always return 200 OK for WhatsApp webhooks
    $response->set_status(200);
    return $response;
}

/**
 * Enhanced handler for WhatsApp messages that includes interactive responses
 * 
 * @param string $from Customer's phone number
 * @param string $message Message content
 * @param array $webhook_data Raw webhook data
 * @return mixed Response from original handler
 */
function kwetupizza_enhanced_whatsapp_handler($from, $message, $webhook_data = null) {
    // Check for interactive responses
    if ($webhook_data && function_exists('kwetupizza_process_interactive_response')) {
        $interactive = kwetupizza_process_interactive_response($webhook_data);
        
        if ($interactive) {
            // Get context to determine what we're waiting for
            if (function_exists('kwetupizza_get_conversation_context')) {
                $context = kwetupizza_get_conversation_context($from);
                $awaiting = isset($context['awaiting']) ? $context['awaiting'] : '';
                
                // Handle different interactive responses based on context
                switch ($awaiting) {
                    case 'add_or_checkout':
                        if ($interactive['id'] === 'add' || $interactive['id'] === 'checkout') {
                            if (function_exists('kwetupizza_handle_add_or_checkout')) {
                                return kwetupizza_handle_add_or_checkout($from, $interactive['id']);
                            }
                        }
                        break;
                        
                    case 'delivery_zone':
                        if (strpos($interactive['id'], 'zone_') === 0) {
                            $zone_id = substr($interactive['id'], 5);
                            if (function_exists('kwetupizza_handle_delivery_zone_selection')) {
                                return kwetupizza_handle_delivery_zone_selection($from, $zone_id);
                            }
                        }
                        break;
                        
                    case 'payment_provider':
                        if (strpos($interactive['id'], 'payment_') === 0) {
                            $provider = substr($interactive['id'], 8);
                            if (function_exists('kwetupizza_handle_payment_provider_response')) {
                                return kwetupizza_handle_payment_provider_response($from, $provider);
                            }
                        }
                        break;
                        
                    case 'use_whatsapp_number':
                        if ($interactive['id'] === 'yes' || $interactive['id'] === 'no') {
                            if (function_exists('kwetupizza_handle_use_whatsapp_number_response')) {
                                return kwetupizza_handle_use_whatsapp_number_response($from, $interactive['id']);
                            }
                        }
                        break;
                }
            }
        }
    }
    
    // If not interactive or not handled above, proceed with regular text handling
    if (function_exists('kwetupizza_handle_whatsapp_message')) {
        return kwetupizza_handle_whatsapp_message($from, $message);
    }
    
    return false;
}

/**
 * Add our interactive buttons to the checkout flow
 */
function kwetupizza_integrate_interactive_buttons() {
    // Define helper functions for checkout flow
    
    // Function to send checkout options (add more or proceed to checkout) as buttons
    if (!function_exists('kwetupizza_send_checkout_options')) {
        function kwetupizza_send_checkout_options($from) {
            $message = "What would you like to do next?";
            $buttons = [
                ['id' => 'add', 'title' => 'Add More Items'],
                ['id' => 'checkout', 'title' => 'Proceed to Checkout']
            ];
            
            if (function_exists('kwetupizza_send_buttons')) {
                return kwetupizza_send_buttons($from, $message, $buttons);
            } elseif (function_exists('kwetupizza_send_interactive_message')) {
                return kwetupizza_send_interactive_message($from, $message, 'button', $buttons);
            } else {
                // Fallback to regular message
                $message = "Would you like to add more items or proceed to checkout? Type 'add' to add more items or 'checkout' to proceed.";
                return kwetupizza_send_whatsapp_message($from, $message);
            }
        }
    }
    
    // Function to send delivery zones as a list for selection
    if (!function_exists('kwetupizza_send_delivery_zones')) {
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
            
            if (function_exists('kwetupizza_send_list')) {
                return kwetupizza_send_list($from, $message, $list_items, 'Select Delivery Area');
            } elseif (function_exists('kwetupizza_send_interactive_message')) {
                return kwetupizza_send_interactive_message($from, $message, 'list', $list_items);
            } else {
                // Fallback to regular message
                $message = "📍 *Select Your Delivery Area* 📍\n\nPlease type the number of your delivery area:\n\n";
                foreach ($zones as $zone) {
                    $message .= "{$zone->id}. *{$zone->zone_name}*\n";
                    $message .= "   {$zone->description}\n";
                    $message .= "   Delivery Fee: " . number_format($zone->delivery_fee, 2) . " TZS\n\n";
                }
                return kwetupizza_send_whatsapp_message($from, $message);
            }
        }
    }
    
    // Function to send payment options as a list for selection
    if (!function_exists('kwetupizza_send_payment_options')) {
        function kwetupizza_send_payment_options($from, $order_summary) {
            $message = $order_summary . "\n\nPlease select your payment method:";
            
            $list_items = [
                ['id' => 'payment_1', 'title' => 'Vodacom M-Pesa', 'description' => 'Pay using M-Pesa mobile money'],
                ['id' => 'payment_2', 'title' => 'Tigo Pesa', 'description' => 'Pay using Tigo Pesa mobile money'],
                ['id' => 'payment_3', 'title' => 'Airtel Money', 'description' => 'Pay using Airtel Money mobile money'],
                ['id' => 'payment_4', 'title' => 'Halotel Halopesa', 'description' => 'Pay using Halopesa mobile money'],
                ['id' => 'payment_5', 'title' => 'Card Payment', 'description' => 'Pay with credit/debit card via PayPal']
            ];
            
            if (function_exists('kwetupizza_send_list')) {
                return kwetupizza_send_list($from, $message, $list_items, 'Select Payment Method');
            } elseif (function_exists('kwetupizza_send_interactive_message')) {
                return kwetupizza_send_interactive_message($from, $message, 'list', $list_items);
            } else {
                // Fallback to regular message
                $message .= "\n\n1. Vodacom M-Pesa\n2. Tigo Pesa\n3. Airtel Money\n4. Halotel Halopesa\n5. Card Payment (PayPal)";
                return kwetupizza_send_whatsapp_message($from, $message);
            }
        }
    }
    
    // Function to send phone number confirmation buttons (yes/no)
    if (!function_exists('kwetupizza_send_phone_confirmation')) {
        function kwetupizza_send_phone_confirmation($from) {
            $message = "Would you like to use your WhatsApp number ($from) for payment?";
            $buttons = [
                ['id' => 'yes', 'title' => 'Yes'],
                ['id' => 'no', 'title' => 'No (Use Another Number)']
            ];
            
            if (function_exists('kwetupizza_send_buttons')) {
                return kwetupizza_send_buttons($from, $message, $buttons);
            } elseif (function_exists('kwetupizza_send_interactive_message')) {
                return kwetupizza_send_interactive_message($from, $message, 'button', $buttons);
            } else {
                // Fallback to regular message
                $message .= "\n\n1. Yes\n2. No (provide another number)";
                return kwetupizza_send_whatsapp_message($from, $message);
            }
        }
    }
}
add_action('plugins_loaded', 'kwetupizza_integrate_interactive_buttons');

// Process interactive elements
if (!function_exists('kwetupizza_process_interactive_response')) {
    /**
     * Process WhatsApp webhook data for interactive elements
     * 
     * @param array $data The webhook data
     * @return array|null Extracted interaction data or null
     */
    function kwetupizza_process_interactive_response($data) {
        if (!isset($data['entry'][0]['changes'][0]['value']['messages'][0]['interactive'])) {
            return null;
        }
        
        $interactive = $data['entry'][0]['changes'][0]['value']['messages'][0]['interactive'];
        $type = null;
        $response_id = null;
        $response_text = null;
        
        if (isset($interactive['button_reply'])) {
            $type = 'button';
            $response_id = $interactive['button_reply']['id'];
            $response_text = $interactive['button_reply']['title'];
        } elseif (isset($interactive['list_reply'])) {
            $type = 'list';
            $response_id = $interactive['list_reply']['id'];
            $response_text = $interactive['list_reply']['title'];
        }
        
        if ($type && $response_id) {
            return [
                'type' => $type,
                'id' => $response_id,
                'text' => $response_text
            ];
        }
        
        return null;
    }
} 