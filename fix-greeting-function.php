<?php
/**
 * Fix for missing kwetupizza_is_greeting function
 * Upload this file to your server at /home/u568743147/domains/kwetupizza.online/public_html/wp-content/plugins/pizzadelivery/includes/
 * Then add require_once 'fix-greeting-function.php'; at the top of your functions.php file
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
?> 