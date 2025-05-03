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

// Include compatibility functions for older code references
require_once(plugin_dir_path(dirname(__FILE__)) . 'fix-whatsapp-missing-functions.php');
require_once(plugin_dir_path(dirname(__FILE__)) . 'fix-whatsapp-default-response.php');

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

