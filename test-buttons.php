<?php
/**
 * KwetuPizza Interactive Buttons Test Script - Web Version
 * 
 * Access this via http://yoursite.com/wp-content/plugins/kwetu-pizza-complete-with-payment-comfirmation/test-buttons.php
 */

// Bootstrap WordPress
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php');

// Define output function
function output($message, $success = true) {
    echo '<div style="margin: 10px 0; padding: 10px; border-radius: 5px; background-color: ' . 
        ($success ? '#e8f5e9' : '#ffebee') . '; color: ' . 
        ($success ? '#2e7d32' : '#c62828') . ';">' . 
        $message . '</div>';
}

echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KwetuPizza Interactive Buttons Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        h1 { color: #d32f2f; }
        pre { background: #f5f5f5; padding: 10px; overflow: auto; }
    </style>
</head>
<body>
    <h1>KwetuPizza Interactive Buttons Test</h1>';

// Check for files
$plugin_dir = dirname(__FILE__);
$files_to_check = [
    'interactive-buttons.php',
    'whatsapp-interactive-buttons.php',
    'kwetu-interactive-buttons-setup.php'
];

echo '<h2>Files Check</h2>';
$all_files_found = true;
foreach ($files_to_check as $file) {
    $file_path = $plugin_dir . '/' . $file;
    if (file_exists($file_path)) {
        output("✓ $file found");
    } else {
        output("✗ $file not found", false);
        $all_files_found = false;
    }
}

// Check for functions
$required_functions = [
    'kwetupizza_send_buttons',
    'kwetupizza_send_list',
    'kwetupizza_process_interactive_response',
    'kwetupizza_send_checkout_options',
    'kwetupizza_send_delivery_zones',
    'kwetupizza_send_payment_options',
    'kwetupizza_send_phone_confirmation'
];

echo '<h2>Functions Check</h2>';
$functions_available = 0;
foreach ($required_functions as $function) {
    if (function_exists($function)) {
        output("✓ $function is available");
        $functions_available++;
    } else {
        output("✗ $function is not available", false);
    }
}

// Display core WhatsApp functions
echo '<h2>Core WhatsApp Functions</h2>';
$core_functions = [
    'kwetupizza_handle_whatsapp_message',
    'kwetupizza_enhanced_whatsapp_handler',
    'kwetupizza_send_whatsapp_message',
    'kwetupizza_get_conversation_context'
];

foreach ($core_functions as $function) {
    if (function_exists($function)) {
        output("✓ $function is available");
    } else {
        output("✗ $function is not available", false);
    }
}

// Check WhatsApp API settings
echo '<h2>WhatsApp API Settings</h2>';
$token = get_option('kwetupizza_whatsapp_token');
$phone_id = get_option('kwetupizza_whatsapp_phone_id');

if (!empty($token)) {
    output("✓ WhatsApp API token is configured");
} else {
    output("✗ WhatsApp API token is not configured", false);
}

if (!empty($phone_id)) {
    output("✓ WhatsApp phone ID is configured");
} else {
    output("✗ WhatsApp phone ID is not configured", false);
}

// Test messaging (only if settings are configured)
if (!empty($_GET['phone']) && !empty($token) && !empty($phone_id)) {
    $test_phone = sanitize_text_field($_GET['phone']);
    echo '<h2>Test Messaging to ' . esc_html($test_phone) . '</h2>';
    
    // Test sending a simple message
    if (function_exists('kwetupizza_send_whatsapp_message')) {
        $result = kwetupizza_send_whatsapp_message($test_phone, "This is a test message from KwetuPizza plugin");
        output($result ? "✓ Regular message sent successfully" : "✗ Failed to send regular message", $result);
    }
    
    // Test sending buttons
    if (function_exists('kwetupizza_send_buttons')) {
        $message = "This is a test of buttons";
        $buttons = [
            ['id' => 'test_yes', 'title' => 'Yes'],
            ['id' => 'test_no', 'title' => 'No']
        ];
        $result = kwetupizza_send_buttons($test_phone, $message, $buttons);
        output($result ? "✓ Buttons sent successfully" : "✗ Failed to send buttons", $result);
    }
    
    // Test sending checkout options
    if (function_exists('kwetupizza_send_checkout_options')) {
        $result = kwetupizza_send_checkout_options($test_phone);
        output($result ? "✓ Checkout options sent successfully" : "✗ Failed to send checkout options", $result);
    }
} else {
    echo '<p>To test sending messages, add ?phone=255XXXXXXXXX to the URL</p>';
}

// Summary
echo '<h2>Summary</h2>';
if ($all_files_found) {
    output("✓ All required files found");
} else {
    output("✗ Some required files are missing", false);
}

output("$functions_available of " . count($required_functions) . " interactive button functions available");

echo '</body>
</html>';
?> 