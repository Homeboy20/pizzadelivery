<?php
/**
 * KwetuPizza Interactive Buttons Test Script
 * 
 * This script tests the interactive buttons implementation:
 * 1. It loads required files
 * 2. Tests sending an interactive message
 * 3. Confirms the handlers are properly set up
 * 
 * Usage: 
 * - Upload to the plugin directory
 * - Run via WP-CLI: wp eval-file interactive-buttons-test.php
 * - Or visit http://yoursite.com/wp-content/plugins/kwetu-pizza-complete-with-payment-comfirmation/interactive-buttons-test.php?phone=YOUR_PHONE
 */

// Bootstrap WordPress if not already loaded
if (!defined('ABSPATH') && file_exists(dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php')) {
    require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
    
    // Check if this is a direct web access
    if (php_sapi_name() !== 'cli' && isset($_GET['phone'])) {
        // Get test phone number from URL parameter
        $test_phone = isset($_GET['phone']) ? trim(htmlspecialchars($_GET['phone'])) : '';
    }
} else {
    // If we're in WordPress and this is direct access, block it
    if (!defined('WP_CLI') && !isset($test_phone)) {
        echo "This script must be run via WP-CLI or with a phone parameter.";
        exit;
    }
}

// Set test phone number if not provided
if (!isset($test_phone)) {
    // Default test phone (should be changed to a valid number)
    $test_phone = isset($argv[1]) ? $argv[1] : '';
    
    if (empty($test_phone)) {
        echo "Please provide a phone number as parameter.\n";
        echo "Usage: wp eval-file interactive-buttons-test.php 255XXXXXXXXX\n";
        exit;
    }
}

// Ensure test phone is in proper format
if (substr($test_phone, 0, 1) !== '+') {
    $test_phone = '+' . $test_phone;
}

// Load the required files
$plugin_dir = dirname(__FILE__);
$files_to_check = [
    'interactive-buttons.php',
    'whatsapp-interactive-buttons.php',
    'kwetu-interactive-buttons-setup.php'
];

$all_files_loaded = true;
$functions_supported = [];

foreach ($files_to_check as $file) {
    $file_path = $plugin_dir . '/' . $file;
    if (file_exists($file_path)) {
        require_once $file_path;
        echo "Successfully loaded: $file\n";
    } else {
        echo "Failed to load: $file (file not found)\n";
        $all_files_loaded = false;
    }
}

// Check for essential functions
$required_functions = [
    'kwetupizza_send_buttons',
    'kwetupizza_send_list',
    'kwetupizza_process_interactive_response',
    'kwetupizza_send_checkout_options',
    'kwetupizza_send_delivery_zones',
    'kwetupizza_send_payment_options',
    'kwetupizza_send_phone_confirmation'
];

echo "\nChecking for required functions:\n";
foreach ($required_functions as $function) {
    if (function_exists($function)) {
        echo "✓ $function is available\n";
        $functions_supported[] = $function;
    } else {
        echo "✗ $function is not available\n";
    }
}

// If no test phone provided, exit before testing
if (empty($test_phone)) {
    echo "\nNo test phone number provided. Skipping sending test messages.\n";
    exit;
}

// Test sending interactive messages if basic functions are available
echo "\nTesting interactive messaging to $test_phone...\n";

$test_results = [];

// Test 1: Send checkout buttons
if (in_array('kwetupizza_send_checkout_options', $functions_supported)) {
    echo "Testing checkout options buttons...\n";
    $result = kwetupizza_send_checkout_options($test_phone);
    $test_results['checkout_buttons'] = $result ? 'success' : 'failure';
    echo $result ? "✓ Sent checkout buttons\n" : "✗ Failed to send checkout buttons\n";
    sleep(2); // Wait to avoid rate limiting
}

// Test 2: Test simplified button sending
if (in_array('kwetupizza_send_buttons', $functions_supported)) {
    echo "Testing simple yes/no buttons...\n";
    $message = "This is a test of simple yes/no buttons.";
    $buttons = [
        ['id' => 'test_yes', 'title' => 'Yes'],
        ['id' => 'test_no', 'title' => 'No']
    ];
    $result = kwetupizza_send_buttons($test_phone, $message, $buttons);
    $test_results['simple_buttons'] = $result ? 'success' : 'failure';
    echo $result ? "✓ Sent yes/no buttons\n" : "✗ Failed to send yes/no buttons\n";
    sleep(2); // Wait to avoid rate limiting
}

// Test 3: Test list sending
if (in_array('kwetupizza_send_list', $functions_supported)) {
    echo "Testing list selection...\n";
    $message = "This is a test of list selection.";
    $options = [
        ['id' => 'test_option_1', 'title' => 'Option 1', 'description' => 'First test option'],
        ['id' => 'test_option_2', 'title' => 'Option 2', 'description' => 'Second test option'],
        ['id' => 'test_option_3', 'title' => 'Option 3', 'description' => 'Third test option']
    ];
    $result = kwetupizza_send_list($test_phone, $message, $options, 'Select an Option');
    $test_results['list_selection'] = $result ? 'success' : 'failure';
    echo $result ? "✓ Sent list selection\n" : "✗ Failed to send list selection\n";
}

// Print test summary
echo "\n====== Test Summary ======\n";
echo "Files loaded: " . ($all_files_loaded ? "All required files found" : "Some files missing") . "\n";
echo "Functions available: " . count($functions_supported) . " of " . count($required_functions) . "\n";

if (!empty($test_results)) {
    echo "Message tests:\n";
    foreach ($test_results as $test => $result) {
        echo " - $test: $result\n";
    }
    
    $success_count = array_count_values($test_results)['success'] ?? 0;
    $total_tests = count($test_results);
    
    echo "\nOverall result: $success_count of $total_tests tests passed\n";

    echo "\nCheck your WhatsApp on $test_phone to verify the messages were received correctly.\n";
    echo "You should see buttons and a list selection to interact with.\n";
} else {
    echo "No message tests were performed.\n";
}

echo "======== End of Test ========\n"; 