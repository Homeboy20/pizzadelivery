<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Compatibility function for older code that uses kwetupizza_send_help
 * Redirects to kwetupizza_send_help_message
 * 
 * @param string $from The customer's phone number
 */
if (!function_exists('kwetupizza_send_help')) {
    function kwetupizza_send_help($from) {
        // Make sure kwetupizza_send_help_message exists
        if (function_exists('kwetupizza_send_help_message')) {
            return kwetupizza_send_help_message($from);
        } else {
            // Fallback in case the help message function doesn't exist
            $message = "📱 *KwetuPizza Help Guide*\n\n";
            $message .= "Type 'menu' to see our available pizza options.\n";
            $message .= "Type 'status' to check your current order.\n";
            $message .= "Thank you for choosing KwetuPizza! 🍕";
            
            if (function_exists('kwetupizza_send_whatsapp_message')) {
                kwetupizza_send_whatsapp_message($from, $message);
            }
        }
    }
} 