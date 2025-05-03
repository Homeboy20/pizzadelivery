<?php
/**
 * Plugin Name: KwetuPizza Interactive Buttons
 * Description: Adds interactive buttons to KwetuPizza WhatsApp checkout flow
 * Version: 1.0.0
 * Author: KwetuPizza Team
 * Text Domain: kwetu-pizza
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include the main setup file
require_once plugin_dir_path(__FILE__) . 'kwetu-interactive-buttons-setup.php';

/**
 * Initialize the plugin
 */
function kwetu_pizza_interactive_init() {
    // Check if KwetuPizza plugin is active
    if (!function_exists('kwetupizza_handle_whatsapp_message')) {
        // Show admin notice if KwetuPizza is not active
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p><?php _e('KwetuPizza Interactive Buttons requires the main KwetuPizza plugin to be active.', 'kwetu-pizza'); ?></p>
            </div>
            <?php
        });
        return;
    }
}
add_action('plugins_loaded', 'kwetu_pizza_interactive_init'); 