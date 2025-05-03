<?php
/**
 * Fix Checkout Flow Missing Quantity Handler
 * 
 * This script patches the WhatsApp handler to properly handle quantity inputs
 * Upload to your WordPress root directory and run it once via browser
 * 
 * Version: 1.0
 */

// Security check - only run if directly accessed
if (!defined('ABSPATH')) {
    // Code to add
    $code_to_add = '} else if ($awaiting === \'quantity\') {
                // Get the last product in the cart
                $last_product = end($context[\'cart\']);
                $product_id = $last_product[\'product_id\'];
                kwetupizza_confirm_order_and_request_address($from, $product_id, intval(trim($message)));
                return;';
    
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
    
    // Check if the code is already there
    if (strpos($current_contents, 'awaiting === \'quantity\'') !== false) {
        echo "The quantity handler already exists. No action needed.<br>";
    } else {
        // Find insertion point - right after menu_selection handler
        $marker = "} else if (\$awaiting === 'menu_selection') {
                kwetupizza_process_order(\$from, \$message);
                return;";
        
        $insertion_point = strpos($current_contents, $marker);
        
        if ($insertion_point === false) {
            echo "Error: Could not find the right location to insert the code.<br>";
            echo "The file structure might have changed. Please apply the fix manually.<br>";
            die();
        }
        
        // Insert code after the marker
        $insertion_point += strlen($marker);
        $new_contents = substr($current_contents, 0, $insertion_point) . "\n            " . $code_to_add . substr($current_contents, $insertion_point);
        
        // Backup original file
        $backup_file = $functions_file . '.bak.' . date('YmdHis');
        if (!copy($functions_file, $backup_file)) {
            echo "Warning: Could not create backup file. Proceeding anyway.<br>";
        } else {
            echo "Backup created: " . basename($backup_file) . "<br>";
        }
        
        // Write new content
        if (file_put_contents($functions_file, $new_contents)) {
            echo "Successfully added missing quantity handler to fix checkout flow.<br>";
        } else {
            echo "Failed to write to functions.php. Please check file permissions.<br>";
        }
    }
    
    echo "<h2>Checkout Flow Fix Utility</h2>";
    echo "<p>The utility has completed. If successful, delete this file from your server immediately for security!</p>";
    die();
}
?>

<h1>Error: This file must be uploaded to the root of your WordPress installation</h1>
<p>This utility is designed to be run directly by accessing it in a web browser.</p> 