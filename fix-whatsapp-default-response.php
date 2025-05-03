<?php
/**
 * KwetuPizza WhatsApp Fallback Functions
 * 
 * This file provides fallback implementations for interactive message functions
 * to ensure the plugin still works even if the main interactive buttons files aren't loaded.
 */

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

/**
 * Fallback for sending button messages - will send regular message with numbered options
 */
if (!function_exists('kwetupizza_send_buttons')) {
    function kwetupizza_send_buttons($phone, $message, $buttons) {
        $full_message = $message . "\n\n";
        foreach ($buttons as $index => $button) {
            $full_message .= ($index + 1) . ". " . $button['title'] . "\n";
        }
        return kwetupizza_send_whatsapp_message($phone, $full_message);
    }
}

/**
 * Fallback for sending list messages - will send regular message with numbered options
 */
if (!function_exists('kwetupizza_send_list')) {
    function kwetupizza_send_list($phone, $message, $button_text, $sections) {
        $full_message = $message . "\n\n";
        $full_message .= "$button_text:\n\n";
        
        $option_index = 1;
        foreach ($sections as $section) {
            if (!empty($section['title'])) {
                $full_message .= "*{$section['title']}*\n";
            }
            
            foreach ($section['rows'] as $row) {
                $full_message .= "{$option_index}. {$row['title']}";
                if (!empty($row['description'])) {
                    $full_message .= " - {$row['description']}";
                }
                $full_message .= "\n";
                $option_index++;
            }
            $full_message .= "\n";
        }
        
        return kwetupizza_send_whatsapp_message($phone, $full_message);
    }
}

/**
 * Fallback functions for specific interactive scenarios
 */

// Checkout options
if (!function_exists('kwetupizza_send_checkout_options')) {
    function kwetupizza_send_checkout_options($from) {
        $message = "Would you like to add more items or proceed to checkout?\n\n";
        $message .= "1. Add More Items\n";
        $message .= "2. Proceed to Checkout";
        return kwetupizza_send_whatsapp_message($from, $message);
    }
}

// Delivery zones
if (!function_exists('kwetupizza_send_delivery_zones')) {
    function kwetupizza_send_delivery_zones($from) {
        return kwetupizza_show_delivery_zones($from);
    }
}

// Payment options
if (!function_exists('kwetupizza_send_payment_options')) {
    function kwetupizza_send_payment_options($from) {
        $message = "Please select your payment method:\n\n";
        $message .= "1. Vodacom M-Pesa\n";
        $message .= "2. Tigo Pesa\n";
        $message .= "3. Airtel Money\n";
        $message .= "4. Halo Pesa\n";
        $message .= "5. Card Payment (PayPal)";
        return kwetupizza_send_whatsapp_message($from, $message);
    }
}

// Phone confirmation
if (!function_exists('kwetupizza_send_phone_confirmation')) {
    function kwetupizza_send_phone_confirmation($from) {
        $message = "Would you like to use your WhatsApp number ($from) for payment?\n\n";
        $message .= "1. Yes\n";
        $message .= "2. No (provide another number)";
        return kwetupizza_send_whatsapp_message($from, $message);
    }
} 