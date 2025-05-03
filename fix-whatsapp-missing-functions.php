<?php
/**
 * Fix for WhatsApp Handler Missing Functions
 * 
 * This file fixes the missing functions in the WhatsApp handler
 * Upload to your server root and access it via browser once
 * 
 * Version: 1.0
 */

// Security check - only run if directly accessed
if (!defined('ABSPATH')) {
    // Functions to add
    $functions_to_add = '
/**
 * Default response for unrecognized messages 
 * This was missing and causing the fatal error
 */
if (!function_exists(\'kwetupizza_send_default_response\')) {
    function kwetupizza_send_default_response($from) {
        kwetupizza_send_whatsapp_message($from, "I\'m not sure what you want to do. Please type \'menu\' to browse our menu, \'help\' for assistance, or \'status\' to check your recent order.");
    }
}

// Fix the calling of kwetupizza_send_help to kwetupizza_send_help_message
// Make a compatibility function
if (!function_exists(\'kwetupizza_send_help\')) {
    function kwetupizza_send_help($from) {
        // Just call the real function that exists
        return kwetupizza_send_help_message($from);
    }
}';

    // Path to functions.php file
    $functions_file = __DIR__ . '/wp-content/plugins/pizzadelivery/includes/functions.php';
    
    // Make sure we can access the file
    if (!file_exists($functions_file)) {
        die("Error: Could not find functions.php file at $functions_file");
    }
    
    // Read current file contents
    $current_contents = file_get_contents($functions_file);
    
    if ($current_contents === false) {
        die("Error: Could not read the functions.php file. Please check permissions.");
    }
    
    // Check if functions already exist
    if (strpos($current_contents, 'kwetupizza_send_default_response') !== false) {
        echo "The default response function already exists. No action needed.<br>";
    } else {
        // Find a good insertion point - right before the get_conversation_context function
        $insertion_point = strpos($current_contents, "function kwetupizza_get_conversation_context");
        
        if ($insertion_point === false) {
            // Alternative insertion point - EOF
            $insertion_point = strlen($current_contents) - 2;
            $new_contents = substr($current_contents, 0, $insertion_point) . "\n" . $functions_to_add . "\n" . substr($current_contents, $insertion_point);
        } else {
            // Find the beginning of the line to insert before
            $line_start = strrpos(substr($current_contents, 0, $insertion_point), "if (!function_exists");
            $new_contents = substr($current_contents, 0, $line_start) . $functions_to_add . "\n\n" . substr($current_contents, $line_start);
        }
        
        // Backup original file
        $backup_file = $functions_file . '.bak.' . date('YmdHis');
        if (!copy($functions_file, $backup_file)) {
            echo "Warning: Could not create backup file. Proceeding anyway.<br>";
        } else {
            echo "Backup created: " . basename($backup_file) . "<br>";
        }
        
        // Write new content
        if (file_put_contents($functions_file, $new_contents)) {
            echo "Successfully added missing functions to fix WhatsApp handler.<br>";
        } else {
            echo "Failed to write to functions.php. Please check file permissions.<br>";
        }
    }
    
    echo "<h2>WhatsApp Handler Fix Utility</h2>";
    echo "<p>The utility has completed. If successful, delete this file from your server immediately for security!</p>";
    die();
}
?>

<h1>Error: This file must be uploaded to the root of your WordPress installation</h1>
<p>This utility is designed to be run directly by accessing it in a web browser.</p> 