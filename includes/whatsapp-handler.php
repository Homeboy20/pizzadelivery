<?php
/**
 * KwetuPizza WhatsApp Handler
 * 
 * This file was previously used to handle all WhatsApp interactions with customers.
 * All functions have been moved to functions.php for better code organization.
 * This file is kept for backward compatibility.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include compatibility functions for older code references
require_once(plugin_dir_path(dirname(__FILE__)) . 'fix-whatsapp-missing-functions.php');
require_once(plugin_dir_path(dirname(__FILE__)) . 'fix-whatsapp-default-response.php');

// Include core functions file - use require_once to prevent duplicate function definitions
if (file_exists(dirname(__FILE__) . '/functions.php')) {
    require_once dirname(__FILE__) . '/functions.php';
}

