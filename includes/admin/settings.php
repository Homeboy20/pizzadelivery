<?php
/**
 * KwetuPizza Admin Settings Functions
 * 
 * Contains functions related to plugin settings in the admin area.
 * This file was created to fix the missing file error.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register plugin settings
 * This function registers all the settings used by the plugin
 */
function kwetupizza_register_settings() {
    // Register Flutterwave settings
    register_setting('kwetupizza_settings', 'kwetupizza_flw_public_key');
    register_setting('kwetupizza_settings', 'kwetupizza_flw_secret_key');
    register_setting('kwetupizza_settings', 'kwetupizza_flw_webhook_secret');
    
    // Register WhatsApp settings
    register_setting('kwetupizza_settings', 'kwetupizza_whatsapp_token');
    register_setting('kwetupizza_settings', 'kwetupizza_whatsapp_phone_id');
    register_setting('kwetupizza_settings', 'kwetupizza_whatsapp_verify_token');
    
    // Register SMS settings
    register_setting('kwetupizza_settings', 'kwetupizza_nextsms_username');
    register_setting('kwetupizza_settings', 'kwetupizza_nextsms_password');
    register_setting('kwetupizza_settings', 'kwetupizza_nextsms_sender_id');
    
    // Register admin notification settings
    register_setting('kwetupizza_settings', 'kwetupizza_admin_whatsapp');
    register_setting('kwetupizza_settings', 'kwetupizza_admin_sms');
    register_setting('kwetupizza_settings', 'kwetupizza_admin_email');
    
    // Register general settings
    register_setting('kwetupizza_settings', 'kwetupizza_currency');
    register_setting('kwetupizza_settings', 'kwetupizza_support_phone');
    register_setting('kwetupizza_settings', 'kwetupizza_support_email');
    
    // Register PayPal settings
    register_setting('kwetupizza_settings', 'kwetupizza_paypal_client_id');
    register_setting('kwetupizza_settings', 'kwetupizza_paypal_client_secret');
    register_setting('kwetupizza_settings', 'kwetupizza_paypal_sandbox');
}
add_action('admin_init', 'kwetupizza_register_settings'); 