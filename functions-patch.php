<?php
/**
 * This is a patch for your functions.php file on the live server
 * Here's how to apply it:
 * 
 * 1. Log in to your server via FTP or file manager
 * 2. Navigate to /home/u568743147/domains/kwetupizza.online/public_html/wp-content/plugins/pizzadelivery/includes/
 * 3. Download a backup of your functions.php file
 * 4. Open functions.php in a text editor
 * 5. Find line 1153 (the line mentioned in the error)
 * 6. Add the function below BEFORE that line, around line 1140-1150
 */

/**
 * Check if a message is a greeting
 */
if (!function_exists('kwetupizza_is_greeting')) {
    function kwetupizza_is_greeting($message) {
        $message = strtolower(trim($message));
        $greetings = array(
            'hi', 'hello', 'hey', 'hola', 'howdy', 'good morning', 'good afternoon', 
            'good evening', 'morning', 'afternoon', 'evening', 'whats up', "what's up",
            'yo', 'greetings', 'sup', 'salaam', 'salam', 'jambo', 'habari', 'mambo'
        );
        
        foreach ($greetings as $greeting) {
            if (strpos($message, $greeting) !== false || $message === $greeting) {
                return true;
            }
        }
        
        return false;
    }
}

/* 
 * Alternative approach:
 * If you prefer not to edit the main functions.php file directly,
 * save this file as fix-greeting-function.php in the same directory
 * and add this line at the top of your functions.php file:
 * 
 * require_once 'fix-greeting-function.php';
 */
?> 