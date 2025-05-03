<?php
/**
 * KwetuPizza WhatsApp Helper Functions
 * 
 * This file provides compatibility functions for WhatsApp integration
 * to ensure smooth operation with various implementations.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if a message is a greeting
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
 * Send a default response when message is not understood
 */
if (!function_exists('kwetupizza_send_default_response')) {
    function kwetupizza_send_default_response($from) {
        $message = "Sorry, I didn't understand that. Here are your options:\n\n";
        $message .= "• Type 'menu' to browse our delicious pizza menu\n";
        $message .= "• Type 'order' to start a new order\n";
        $message .= "• Type 'status' to check your recent order\n";
        $message .= "• Type 'help' for more information";
        
        return kwetupizza_send_whatsapp_message($from, $message);
    }
}

/**
 * Compatibility wrapper for start_conversation
 */
if (!function_exists('kwetupizza_start_conversation')) {
    function kwetupizza_start_conversation($from) {
        if (function_exists('kwetupizza_send_greeting')) {
            return kwetupizza_send_greeting($from);
        } else {
            $message = "👋 Hello! Welcome to KwetuPizza! How can I help you today?\n\n";
            $message .= "• Type 'menu' to see our menu\n";
            $message .= "• Type 'order' to start ordering\n";
            $message .= "• Type 'help' for assistance";
            
            kwetupizza_set_conversation_context($from, ['state' => 'greeting']);
            return kwetupizza_send_whatsapp_message($from, $message);
        }
    }
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