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

// Include core functions
if (file_exists(dirname(__FILE__) . '/functions.php')) {
    require_once dirname(__FILE__) . '/functions.php';
}

