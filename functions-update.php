<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * KwetuPizza Functions Update for Interactive Buttons
 * 
 * This file updates the core functions to integrate with the WhatsApp interactive buttons
 */

/**
 * Update functions to use interactive buttons
 */
function kwetupizza_update_functions_for_interactive_buttons() {
    // Path to the main functions file
    $functions_file = dirname(__FILE__) . '/includes/functions.php';
    
    // Backup the file before making changes
    $backup_file = dirname(__FILE__) . '/includes/functions.php.bak';
    if (!file_exists($backup_file)) {
        copy($functions_file, $backup_file);
    }
    
    // Read the current content
    $content = file_get_contents($functions_file);
    
    // Update confirm_order_and_request_address function
    $pattern = '/function kwetupizza_confirm_order_and_request_address\(\$from, \$product_id, \$quantity\) \{.*?kwetupizza_set_conversation_context\(\$from, array_merge\(\$context, \[\'awaiting\' => \'add_or_checkout\'\]\)\);.*?\}/s';
    $replacement = 'function kwetupizza_confirm_order_and_request_address($from, $product_id, $quantity) {
        global $wpdb;
        $context = kwetupizza_get_conversation_context($from);

        foreach ($context[\'cart\'] as &$cart_item) {
            if ($cart_item[\'product_id\'] == $product_id) {
                $cart_item[\'quantity\'] = $quantity;
                $cart_item[\'total\'] = $cart_item[\'price\'] * $quantity;
                break;
            }
        }

        kwetupizza_set_conversation_context($from, $context);

        // Use interactive buttons if available
        if (function_exists(\'kwetupizza_send_checkout_options\')) {
            kwetupizza_send_checkout_options($from);
        } else {
            // Fallback to regular text message
            $message = "Would you like to add more items or proceed to checkout? Type \'add\' to add more items or \'checkout\' to proceed.";
            kwetupizza_send_whatsapp_message($from, $message);
        }

        kwetupizza_set_conversation_context($from, array_merge($context, [\'awaiting\' => \'add_or_checkout\']));
    }';
    
    $content = preg_replace($pattern, $replacement, $content);
    
    // Update handle_add_or_checkout function
    $pattern = '/function kwetupizza_handle_add_or_checkout\(\$from, \$response\) \{.*?\$response = strtolower\(trim\(\$response\)\);/s';
    $replacement = 'function kwetupizza_handle_add_or_checkout($from, $response) {
        // Process possible interactive button response
        if (function_exists(\'kwetupizza_get_button_response\') && isset($_POST[\'entry\'])) {
            $button_id = kwetupizza_get_button_response($_POST);
            if ($button_id) {
                $response = $button_id; // Use the button ID as the response
            }
        }
        
        $response = strtolower(trim($response));';
    
    $content = preg_replace($pattern, $replacement, $content);
    
    // Update show_delivery_zones function
    $pattern = '/function kwetupizza_show_delivery_zones\(\$from\) \{.*?kwetupizza_set_conversation_context\(\$from, array_merge\(\$context, \[\'awaiting\' => \'delivery_zone\'\]\)\);.*?\}/s';
    $replacement = 'function kwetupizza_show_delivery_zones($from) {
        global $wpdb;
        $zones_table = $wpdb->prefix . \'kwetupizza_delivery_zones\';
        
        // Get all delivery zones
        $zones = $wpdb->get_results("SELECT id, zone_name, description, delivery_fee FROM $zones_table ORDER BY delivery_fee ASC");
        
        if (empty($zones)) {
            // If no zones defined, proceed with asking for the full address
            $message = "Please provide your full delivery address with street and landmarks.";
            kwetupizza_send_whatsapp_message($from, $message);
            
            $context = kwetupizza_get_conversation_context($from);
            kwetupizza_set_conversation_context($from, array_merge($context, [\'awaiting\' => \'full_address\']));
            return;
        }
        
        // Use interactive list if available
        if (function_exists(\'kwetupizza_send_delivery_zones\')) {
            kwetupizza_send_delivery_zones($from);
        } else {
            // Format the delivery zones message for regular text
            $message = "📍 *Select Your Delivery Area* 📍\\n\\n";
            $message .= "Please type the number of your delivery area:\\n\\n";
            
            foreach ($zones as $index => $zone) {
                $message .= "{$zone->id}. *{$zone->zone_name}*\\n";
                $message .= "   {$zone->description}\\n";
                $message .= "   Delivery Fee: " . number_format($zone->delivery_fee, 2) . " TZS\\n\\n";
            }
            
            kwetupizza_send_whatsapp_message($from, $message);
        }
        
        // Set context to await delivery zone selection
        $context = kwetupizza_get_conversation_context($from);
        kwetupizza_set_conversation_context($from, array_merge($context, [\'awaiting\' => \'delivery_zone\']));
    }';
    
    $content = preg_replace($pattern, $replacement, $content);
    
    // Update handle_delivery_zone_selection function
    $pattern = '/function kwetupizza_handle_delivery_zone_selection\(\$from, \$zone_id\) \{.*?\$zone_id = \(int\)\$zone_id;/s';
    $replacement = 'function kwetupizza_handle_delivery_zone_selection($from, $zone_selection) {
        global $wpdb;
        $zones_table = $wpdb->prefix . \'kwetupizza_delivery_zones\';
        
        // Check for interactive list response
        $zone_id = null;
        if (function_exists(\'kwetupizza_get_list_response\') && isset($_POST[\'entry\'])) {
            $list_id = kwetupizza_get_list_response($_POST);
            if ($list_id && strpos($list_id, \'zone_\') === 0) {
                $zone_id = (int)substr($list_id, 5); // Extract ID from "zone_X" format
            }
        }
        
        // If not interactive, use the provided zone_id
        if (!$zone_id) {
            $zone_id = (int)$zone_selection;
        }';
    
    $content = preg_replace($pattern, $replacement, $content);
    
    // Update handle_address_and_ask_payment_provider function
    $pattern = '/function kwetupizza_handle_address_and_ask_payment_provider\(\$from, \$address\) \{.*?\$summary_message \.\= "Please select your Mobile Money network for payment:/s';
    $replacement = 'function kwetupizza_handle_address_and_ask_payment_provider($from, $address) {
        $context = kwetupizza_get_conversation_context($from);

        if (isset($context[\'cart\'])) {
            // Save the address in the conversation context
            $context[\'address\'] = $address;
            kwetupizza_set_conversation_context($from, $context);

            // If we have a delivery zone and fee set, include it in the order summary
            $summary_message = "📋 *Order Summary* 📋\\n\\n";
            
            foreach ($context[\'cart\'] as $cart_item) {
                $summary_message .= "{$cart_item[\'quantity\']} x {$cart_item[\'product_name\']} - " . number_format($cart_item[\'total\'], 2) . " TZS\\n";
            }
            
            $summary_message .= "\\nSubtotal: " . number_format($context[\'total\'], 2) . " TZS\\n";
            
            if (isset($context[\'delivery_fee\'])) {
                $summary_message .= "Delivery Fee: " . number_format($context[\'delivery_fee\'], 2) . " TZS\\n";
                $summary_message .= "Total: " . number_format($context[\'grand_total\'], 2) . " TZS\\n\\n";
            } else {
                $summary_message .= "Total: " . number_format($context[\'total\'], 2) . " TZS\\n\\n";
            }
            
            $summary_message .= "Delivery Address: {$address}\\n\\n";
            
            // Use interactive payment method list if available
            if (function_exists(\'kwetupizza_send_payment_options\')) {
                kwetupizza_send_payment_options($from, $summary_message);
            } else {
                // Fallback to regular text message
                $summary_message .= "Please select your Mobile Money network for payment:';
    
    $content = preg_replace($pattern, $replacement, $content);
    
    // Update handle_payment_provider_response function
    $pattern = '/function kwetupizza_handle_payment_provider_response\(\$from, \$provider\) \{.*?if \(isset\(\$context\[\'awaiting\'\]\) && \$context\[\'awaiting\'\] === \'payment_provider\'\) \{/s';
    $replacement = 'function kwetupizza_handle_payment_provider_response($from, $provider) {
        $context = kwetupizza_get_conversation_context($from);
        
        if (isset($context[\'awaiting\']) && $context[\'awaiting\'] === \'payment_provider\') {
            // Check for interactive list response
            if (function_exists(\'kwetupizza_get_list_response\') && isset($_POST[\'entry\'])) {
                $list_id = kwetupizza_get_list_response($_POST);
                if ($list_id && strpos($list_id, \'payment_\') === 0) {
                    $provider = substr($list_id, 8); // Extract number from "payment_X" format
                }
            }';
    
    $content = preg_replace($pattern, $replacement, $content);
    
    // Update handle_use_whatsapp_number_response function
    $pattern = '/function kwetupizza_handle_use_whatsapp_number_response\(\$from, \$response\) \{.*?\$response = strtolower\(trim\(\$response\)\);/s';
    $replacement = 'function kwetupizza_handle_use_whatsapp_number_response($from, $response) {
        $context = kwetupizza_get_conversation_context($from);

        if (isset($context[\'awaiting\']) && $context[\'awaiting\'] === \'use_whatsapp_number\') {
            // Check for interactive button response
            if (function_exists(\'kwetupizza_get_button_response\') && isset($_POST[\'entry\'])) {
                $button_id = kwetupizza_get_button_response($_POST);
                if ($button_id) {
                    $response = $button_id;
                }
            }
            
            $response = strtolower(trim($response));';
    
    $content = preg_replace($pattern, $replacement, $content);
    
    // Replace message about phone number with interactive buttons
    $pattern = '/\$message = "Would you like to use your WhatsApp number \(\$from\) for payment\?";.*?\$message \.\= "\\n\\n1\. Yes\\n2\. No \(provide another number\)";/s';
    $replacement = '$message = "Would you like to use your WhatsApp number ($from) for payment?";
                    
                    if (function_exists(\'kwetupizza_send_phone_confirmation\')) {
                        kwetupizza_send_phone_confirmation($from);
                    } else {
                        // Fallback to regular text message
                        $message .= "\\n\\n1. Yes\\n2. No (provide another number)";';
    
    $content = preg_replace($pattern, $replacement, $content);
    
    // Write the updated content back to the file
    file_put_contents($functions_file, $content);
    
    return true;
}

// Function to rollback changes if needed
function kwetupizza_rollback_function_updates() {
    $functions_file = dirname(__FILE__) . '/includes/functions.php';
    $backup_file = dirname(__FILE__) . '/includes/functions.php.bak';
    
    if (file_exists($backup_file)) {
        copy($backup_file, $functions_file);
        return true;
    }
    
    return false;
} 