<?php
// Application script to update KwetuPizza's functions with interactive button support
// To run: php apply-interactive-buttons.php

// Make sure we're in the plugin directory
chdir(dirname(__FILE__));

// Define ABSPATH to prevent direct access checks from failing
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(dirname(dirname(dirname(__FILE__)))) . '/');
}

// Include the functions update file
require_once 'functions-update.php';

echo "Starting KwetuPizza function updates for interactive button support...\n";

// Apply the updates
$result = kwetupizza_update_functions_for_interactive_buttons();

if ($result) {
    echo "Success! KwetuPizza functions have been updated to support interactive buttons.\n";
    echo "A backup file has been created at includes/functions.php.bak\n";
    
    // Also update the whatsapp-handler.php
    echo "Updating WhatsApp handler to process interactive responses...\n";
    
    $handler_file = 'includes/whatsapp-handler.php';
    
    // Backup the handler file
    if (!file_exists($handler_file . '.bak')) {
        copy($handler_file, $handler_file . '.bak');
    }
    
    // Check if there's a kwetupizza_handle_whatsapp_messages function in the handler
    $handler_content = file_get_contents($handler_file);
    
    if (strpos($handler_content, 'function kwetupizza_handle_whatsapp_messages') !== false &&
        strpos($handler_content, 'kwetupizza_enhanced_whatsapp_handler') === false) {
        
        // Add enhanced handler
        $handler_update = <<<EOF

/**
 * Enhanced WhatsApp message handler that supports interactive responses
 *
 * @param WP_REST_Request \$request The request object
 * @return WP_REST_Response The response
 */
function kwetupizza_handle_whatsapp_messages_with_interactive(\$request) {
    \$data = \$request->get_params();
    \$response = new WP_REST_Response();
    
    // Log the incoming webhook for debugging
    kwetupizza_log(print_r(\$data, true), 'debug', 'whatsapp-webhook.log');
    
    // Process message
    if (isset(\$data['entry'][0]['changes'][0]['value']['messages'][0])) {
        \$message_data = \$data['entry'][0]['changes'][0]['value']['messages'][0];
        \$from = \$message_data['from'];
        
        // Check if this is an interactive message
        if (isset(\$message_data['interactive'])) {
            \$interactive_data = null;
            
            if (isset(\$message_data['interactive']['button_reply'])) {
                \$button_id = \$message_data['interactive']['button_reply']['id'];
                \$text = \$message_data['interactive']['button_reply']['title'];
                kwetupizza_log("Interactive button response received: \$button_id (\$text)", 'info', 'whatsapp.log');
                
                // Process the button using enhanced handler
                kwetupizza_enhanced_whatsapp_handler(\$from, \$text, \$data);
            }
            elseif (isset(\$message_data['interactive']['list_reply'])) {
                \$list_id = \$message_data['interactive']['list_reply']['id'];
                \$text = \$message_data['interactive']['list_reply']['title'];
                kwetupizza_log("Interactive list response received: \$list_id (\$text)", 'info', 'whatsapp.log');
                
                // Process the list selection using enhanced handler
                kwetupizza_enhanced_whatsapp_handler(\$from, \$text, \$data);
            }
        }
        else if (isset(\$message_data['text']['body'])) {
            \$message = \$message_data['text']['body'];
            
            // Handle with regular handler
            kwetupizza_handle_whatsapp_message(\$from, \$message);
        }
    }
    
    // Always return 200 OK for WhatsApp webhooks
    \$response->set_status(200);
    return \$response;
}

// Register the enhanced handler
add_action('rest_api_init', function() {
    register_rest_route('kwetupizza/v1', '/whatsapp-webhook', [
        'methods' => 'POST',
        'callback' => 'kwetupizza_handle_whatsapp_messages_with_interactive',
        'permission_callback' => '__return_true'
    ]);
});

EOF;
        
        // Append the enhanced handler to the file
        file_put_contents($handler_file, $handler_content . $handler_update);
        echo "WhatsApp handler has been updated to support interactive responses.\n";
    } else {
        echo "WhatsApp handler already updated or not found - skipping.\n";
    }
    
    echo "\nUpdate complete! Please test your WhatsApp order flow now.\n";
    echo "If you encounter any issues, you can restore from the backup files using: php rollback-interactive-buttons.php\n";
} else {
    echo "Error: Failed to update KwetuPizza functions. Please check error log or try again.\n";
} 