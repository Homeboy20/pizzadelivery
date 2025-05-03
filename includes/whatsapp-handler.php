<?php
/**
 * KwetuPizza WhatsApp Handler
 * 
 * This file was previously used to handle all WhatsApp interactions with customers.
 * All functions have been moved to functions.php for better code organization.
 * This file is kept for backward compatibility.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Make sure we have access to WordPress functions
if (!function_exists('add_action')) {
    require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/wp-load.php');
}

// Include compatibility functions for older code references
$plugin_root = dirname(dirname(__FILE__));
require_once($plugin_root . '/fix-whatsapp-missing-functions.php');
require_once($plugin_root . '/fix-whatsapp-default-response.php');

// Include interactive buttons functionality
if (file_exists($plugin_root . '/interactive-buttons.php')) {
    require_once($plugin_root . '/interactive-buttons.php');
}

if (file_exists($plugin_root . '/whatsapp-interactive-buttons.php')) {
    require_once($plugin_root . '/whatsapp-interactive-buttons.php');
}

// Include core functions file - use require_once to prevent duplicate function definitions
if (file_exists(dirname(__FILE__) . '/functions.php')) {
    require_once dirname(__FILE__) . '/functions.php';
}

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

/**
 * Get interactive response ID from webhook data
 * 
 * @param array $data The webhook data
 * @return string|null The response ID or null
 */
function kwetupizza_get_interactive_id($data) {
    $interactive = kwetupizza_process_interactive_response($data);
    
    if ($interactive && isset($interactive['id'])) {
        return $interactive['id'];
    }
    
    return null;
}

// Update the handler to process interactive responses
$original_handler = 'kwetupizza_handle_whatsapp_message';
if (function_exists($original_handler)) {
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
                $context = kwetupizza_get_conversation_context($from);
                $awaiting = isset($context['awaiting']) ? $context['awaiting'] : '';
                
                // Handle different interactive responses based on context
                switch ($awaiting) {
                    case 'add_or_checkout':
                        if ($interactive['id'] === 'add' || $interactive['id'] === 'checkout') {
                            return kwetupizza_handle_add_or_checkout($from, $interactive['id']);
                        }
                        break;
                        
                    case 'delivery_zone':
                        if (strpos($interactive['id'], 'zone_') === 0) {
                            $zone_id = substr($interactive['id'], 5);
                            return kwetupizza_handle_delivery_zone_selection($from, $zone_id);
                        }
                        break;
                        
                    case 'payment_provider':
                        if (strpos($interactive['id'], 'payment_') === 0) {
                            $provider = substr($interactive['id'], 8);
                            return kwetupizza_handle_payment_provider_response($from, $provider);
                        }
                        break;
                        
                    case 'use_whatsapp_number':
                        if ($interactive['id'] === 'yes' || $interactive['id'] === 'no') {
                            return kwetupizza_handle_use_whatsapp_number_response($from, $interactive['id']);
                        }
                        break;
                }
            }
        }
        
        // If not interactive or not handled above, proceed with regular text handling
        $original_handler = 'kwetupizza_handle_whatsapp_message';
        return $original_handler($from, $message);
    }
}

// Only register handlers if we're in WordPress
if (function_exists('add_action')) {
    /**
     * Register the enhanced WhatsApp webhook handler
     * This replaces the standard handler with one that supports interactive elements
     */
    function kwetupizza_register_enhanced_whatsapp_handler() {
        add_action('rest_api_init', function() {
            // Remove the existing handler if registered
            if (function_exists('rest_get_route_data')) {
                $existing_route = rest_get_route_data('/kwetupizza/v1/whatsapp-webhook');
                if ($existing_route) {
                    global $wp_rest_server;
                    if ($wp_rest_server && method_exists($wp_rest_server, 'unregister_route')) {
                        $wp_rest_server->unregister_route('/kwetupizza/v1/whatsapp-webhook');
                    }
                }
            }
            
            // Register our enhanced handler
            register_rest_route('kwetupizza/v1', '/whatsapp-webhook', [
                'methods' => 'POST',
                'callback' => 'kwetupizza_handle_enhanced_whatsapp_webhook',
                'permission_callback' => '__return_true'
            ]);
        });
    }

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
        kwetupizza_log(print_r($data, true), 'debug', 'whatsapp-webhook.log');
        
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
                    kwetupizza_log("Interactive button response received: $button_id ($text)", 'info', 'whatsapp.log');
                    
                    // Process the button using enhanced handler
                    kwetupizza_enhanced_whatsapp_handler($from, $text, $data);
                }
                elseif (isset($message_data['interactive']['list_reply'])) {
                    $list_id = $message_data['interactive']['list_reply']['id'];
                    $text = $message_data['interactive']['list_reply']['title'];
                    kwetupizza_log("Interactive list response received: $list_id ($text)", 'info', 'whatsapp.log');
                    
                    // Process the list selection using enhanced handler
                    kwetupizza_enhanced_whatsapp_handler($from, $text, $data);
                }
            }
            else if (isset($message_data['text']['body'])) {
                $message = $message_data['text']['body'];
                
                // Handle with regular handler
                kwetupizza_handle_whatsapp_message($from, $message);
            }
        }
        
        // Always return 200 OK for WhatsApp webhooks
        $response->set_status(200);
        return $response;
    }

    // Register our enhanced handler
    add_action('init', 'kwetupizza_register_enhanced_whatsapp_handler');
}

