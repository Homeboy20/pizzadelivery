<?php
/*
Plugin Name: KwetuPizza Plugin
Description: A modernized pizza order management plugin with custom database structure, WhatsApp bot integration, loyalty system, and advanced features.
Version: 2.0
Author: Your Name
Text Domain: kwetupizza
Domain Path: /languages
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('KWETUPIZZA_VERSION', '2.0');
define('KWETUPIZZA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KWETUPIZZA_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include core functions
require_once KWETUPIZZA_PLUGIN_DIR . 'includes/functions.php';

// Register CSS and JS
function kwetupizza_enqueue_scripts() {
    // Admin styles
    if (is_admin()) {
        wp_enqueue_style('kwetupizza-admin-style', KWETUPIZZA_PLUGIN_URL . 'assets/css/admin-style.css', array(), KWETUPIZZA_VERSION);
        wp_enqueue_script('kwetupizza-admin-script', KWETUPIZZA_PLUGIN_URL . 'assets/js/admin-script.js', array('jquery'), KWETUPIZZA_VERSION, true);
    }
    
    // Frontend styles
    wp_enqueue_style('kwetupizza-style', KWETUPIZZA_PLUGIN_URL . 'assets/css/style.css', array(), KWETUPIZZA_VERSION);
    wp_enqueue_script('kwetupizza-script', KWETUPIZZA_PLUGIN_URL . 'assets/js/script.js', array('jquery'), KWETUPIZZA_VERSION, true);
    
    // Localize script for AJAX
    wp_localize_script('kwetupizza-script', 'kwetupizza_params', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('kwetupizza-nonce')
    ));
}
add_action('wp_enqueue_scripts', 'kwetupizza_enqueue_scripts');
add_action('admin_enqueue_scripts', 'kwetupizza_enqueue_scripts');

// Activation hooks
register_activation_hook(__FILE__, 'kwetupizza_create_tables');
register_activation_hook(__FILE__, 'kwetupizza_create_pages');

// Create menu in the WordPress dashboard
function kwetupizza_create_menu() {
    add_menu_page(
        'KwetuPizza Dashboard',
        'KwetuPizza',
        'manage_options',
        'kwetupizza-dashboard',
        'kwetupizza_render_dashboard',
        'dashicons-pizza',
        20
    );
    add_submenu_page('kwetupizza-dashboard', 'Menu Management', 'Menu Management', 'manage_options', 'kwetupizza-menu', 'kwetupizza_render_menu_management');
    add_submenu_page('kwetupizza-dashboard', 'Order Management', 'Order Management', 'manage_options', 'kwetupizza-orders', 'kwetupizza_render_order_management');
    add_submenu_page('kwetupizza-dashboard', 'Transaction Management', 'Transaction Management', 'manage_options', 'kwetupizza-transactions', 'kwetupizza_render_transaction_management');
    add_submenu_page('kwetupizza-dashboard', 'User Management', 'User Management', 'manage_options', 'kwetupizza-users', 'kwetupizza_render_user_management');
    add_submenu_page('kwetupizza-dashboard', 'Delivery Zones', 'Delivery Zones', 'manage_options', 'kwetupizza-delivery-zones', 'kwetupizza_render_delivery_zones');
    add_submenu_page('kwetupizza-dashboard', 'Loyalty Program', 'Loyalty Program', 'manage_options', 'kwetupizza-loyalty', 'kwetupizza_render_loyalty_program');
    add_submenu_page('kwetupizza-dashboard', 'Settings', 'Settings', 'manage_options', 'kwetupizza-settings', 'kwetupizza_render_settings_page');
}
add_action('admin_menu', 'kwetupizza_create_menu');

// Include admin page files
require_once KWETUPIZZA_PLUGIN_DIR . 'admin/dashboard.php';
require_once KWETUPIZZA_PLUGIN_DIR . 'admin/menu-management.php';
require_once KWETUPIZZA_PLUGIN_DIR . 'admin/order-management.php';
require_once KWETUPIZZA_PLUGIN_DIR . 'admin/transaction-management.php';
require_once KWETUPIZZA_PLUGIN_DIR . 'admin/user-management.php';
require_once KWETUPIZZA_PLUGIN_DIR . 'admin/settings-page.php';

// Include new admin pages
require_once KWETUPIZZA_PLUGIN_DIR . 'admin/delivery-zones.php';
require_once KWETUPIZZA_PLUGIN_DIR . 'admin/loyalty-program.php';

// Include webhooks and API handlers
require_once KWETUPIZZA_PLUGIN_DIR . 'includes/whatsapp-handler.php';
require_once KWETUPIZZA_PLUGIN_DIR . 'includes/api-controller.php';

// Include shortcodes
require_once KWETUPIZZA_PLUGIN_DIR . 'includes/shortcodes.php';

// Register REST API routes
add_action('rest_api_init', 'kwetupizza_register_api_routes');

// Add security headers
add_action('send_headers', 'kwetupizza_add_security_headers');

// Deactivation hook
register_deactivation_hook(__FILE__, 'kwetupizza_deactivate');

// Uninstall hook - not directly called but set up for WordPress to use
register_uninstall_hook(__FILE__, 'kwetupizza_uninstall');
