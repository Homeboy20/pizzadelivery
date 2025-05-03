<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Default response for unrecognized messages
 * This was missing and causing the fatal error
 * 
 * @param string $from The customer's phone number
 */
if (!function_exists('kwetupizza_send_default_response')) {
    function kwetupizza_send_default_response($from) {
        if (function_exists('kwetupizza_send_whatsapp_message')) {
            kwetupizza_send_whatsapp_message($from, "I'm not sure what you want to do. Please type 'menu' to browse our menu, 'help' for assistance, or 'status' to check your recent order.");
        }
    }
} 