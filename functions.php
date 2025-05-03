<?php
// Prevent direct access and ensure WordPress is loaded
if (!defined('ABSPATH')) {
    die('Access denied.');
}

// Ensure we have access to WordPress core functions
if (!function_exists('add_action')) {
    require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php');
}

// Include core functions - ensure we're only loading the main functions file
if (file_exists(dirname(__FILE__) . '/api-controller.php')) {
    require_once dirname(__FILE__) . '/api-controller.php';
}

// Include interactive buttons functionality
if (file_exists(dirname(dirname(__FILE__)) . '/interactive-buttons.php')) {
    require_once dirname(dirname(__FILE__)) . '/interactive-buttons.php';
}

if (file_exists(dirname(dirname(__FILE__)) . '/whatsapp-interactive-buttons.php')) {
    require_once dirname(dirname(__FILE__)) . '/whatsapp-interactive-buttons.php';
}

if (file_exists(dirname(dirname(__FILE__)) . '/kwetu-interactive-buttons-setup.php')) {
    require_once dirname(dirname(__FILE__)) . '/kwetu-interactive-buttons-setup.php';
}

/**
 * KwetuPizza Core Functions
 * 
 * This file contains all the core functions for the KwetuPizza plugin.
 * Functions are organized by category for better maintainability.
 */
// ... existing code ... 