<?php
/**
 * Fix WhatsApp Handler 
 * 
 * This script will patch the kwetupizza_is_greeting function issue by adding
 * the missing function at the right position in functions.php
 * 
 * USAGE:
 * 1. Upload this file to your WordPress root directory 
 * 2. Run it once by visiting: https://kwetupizza.online/fix-whatsapp-handler.php
 * 3. Delete this file after it's run successfully
 */

// Security check - only run if directly accessed
if (!defined('ABSPATH')) {

    // Function definition to add
    $function_code = '
/**
 * Check if a message is a greeting
 */
if (!function_exists(\'kwetupizza_is_greeting\')) {
    function kwetupizza_is_greeting($message) {
        $message = strtolower(trim($message));
        $greetings = array(
            \'hi\', \'hello\', \'hey\', \'hola\', \'howdy\', \'good morning\', \'good afternoon\', 
            \'good evening\', \'morning\', \'afternoon\', \'evening\', \'whats up\', "what\'s up",
            \'yo\', \'greetings\', \'sup\', \'salaam\', \'salam\', \'jambo\', \'habari\', \'mambo\'
        );
        
        foreach ($greetings as $greeting) {
            if (strpos($message, $greeting) !== false || $message === $greeting) {
                return true;
            }
        }
        
        return false;
    }
}
';

    // Path to the functions.php file
    $target_file = __DIR__ . '/wp-content/plugins/kwetu-pizza- complete with payment comfirmation/includes/functions.php';

    // Check if the file exists
    if (!file_exists($target_file)) {
        die('Error: functions.php file not found at: ' . $target_file);
    }

    // Create a backup of the original file
    $backup_file = $target_file . '.bak.' . date('Y-m-d-H-i-s');
    if (!copy($target_file, $backup_file)) {
        die('Error: Could not create backup file');
    }

    // Read the file contents
    $file_contents = file_get_contents($target_file);
    if ($file_contents === false) {
        die('Error: Could not read functions.php file');
    }

    // Check if the function already exists
    if (strpos($file_contents, 'function kwetupizza_is_greeting') !== false) {
        die('Function already exists in the file. No changes needed.');
    }

    // Find the position where the function is first called
    $error_position = strpos($file_contents, 'kwetupizza_is_greeting($message)');
    if ($error_position === false) {
        die('Error: Could not find where the function is called');
    }

    // Find the start of the line where the function is called
    $line_start = strrpos(substr($file_contents, 0, $error_position), "\n") + 1;

    // Insert the function definition before this line
    $new_contents = substr($file_contents, 0, $line_start) . $function_code . substr($file_contents, $line_start);

    // Write the modified contents back to the file
    if (file_put_contents($target_file, $new_contents) === false) {
        die('Error: Could not write to functions.php file');
    }

    // Success message
    echo '<html><body>';
    echo '<h1>Fix Applied Successfully!</h1>';
    echo '<p>The <code>kwetupizza_is_greeting()</code> function has been added to your functions.php file.</p>';
    echo '<p>A backup of your original file was created at: ' . basename($backup_file) . '</p>';
    echo '<p><strong>IMPORTANT:</strong> Please delete this fix script immediately for security reasons.</p>';
    echo '</body></html>';
}
?> 