<?php
/**
 * KwetuPizza Admin Functions
 * 
 * Contains functions needed for the admin interface of the KwetuPizza plugin.
 * This file was missing and has been recreated to fix the fatal error.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Format money values
 * 
 * @param float $amount The amount to format
 * @param string $currency The currency code (default: TZS)
 * @return string Formatted amount with currency
 */
function kwetupizza_admin_format_money($amount, $currency = 'TZS') {
    return number_format($amount, 2) . ' ' . $currency;
}

/**
 * Format date for admin interface
 * 
 * @param string $date_string MySQL date string
 * @param bool $include_time Whether to include time
 * @return string Formatted date
 */
function kwetupizza_admin_format_date($date_string, $include_time = true) {
    if (empty($date_string)) {
        return 'N/A';
    }
    
    $format = $include_time ? 'M j, Y \a\t g:i a' : 'M j, Y';
    return date($format, strtotime($date_string));
}

/**
 * Get order status label with appropriate color coding
 * 
 * @param string $status The order status
 * @return string HTML for the status label
 */
function kwetupizza_admin_get_status_label($status) {
    $status_colors = array(
        'pending_payment' => '#f39c12', // Orange
        'processing' => '#3498db',      // Blue
        'preparing' => '#27ae60',       // Green
        'out_for_delivery' => '#8e44ad', // Purple
        'delivered' => '#2ecc71',       // Light Green
        'cancelled' => '#e74c3c',       // Red
        'payment_failed' => '#e74c3c',  // Red
        'completed' => '#2ecc71'        // Light Green
    );
    
    $status_text = ucwords(str_replace('_', ' ', $status));
    $color = isset($status_colors[$status]) ? $status_colors[$status] : '#95a5a6';
    
    return '<span class="kwetupizza-status" style="background-color: ' . $color . ';">' . $status_text . '</span>';
}

/**
 * Display admin notice
 * 
 * @param string $message The message to display
 * @param string $type The notice type (error, warning, success, info)
 */
function kwetupizza_admin_notice($message, $type = 'info') {
    add_action('admin_notices', function() use ($message, $type) {
        echo '<div class="notice notice-' . $type . ' is-dismissible"><p>' . $message . '</p></div>';
    });
}

/**
 * Clean input data for database operations
 * 
 * @param mixed $data The data to sanitize
 * @return mixed Sanitized data
 */
function kwetupizza_admin_sanitize_data($data) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = kwetupizza_admin_sanitize_data($value);
        }
        return $data;
    }
    
    if (is_numeric($data)) {
        return $data;
    }
    
    // For string values
    return sanitize_text_field($data);
}

/**
 * Get a list of all delivery zones
 * 
 * @return array Array of delivery zone objects
 */
function kwetupizza_admin_get_delivery_zones() {
    global $wpdb;
    $zones_table = $wpdb->prefix . 'kwetupizza_delivery_zones';
    
    return $wpdb->get_results("SELECT * FROM $zones_table ORDER BY delivery_fee ASC");
}

/**
 * Check if a phone number is valid
 * 
 * @param string $phone The phone number to validate
 * @return bool True if valid, false otherwise
 */
function kwetupizza_admin_is_valid_phone($phone) {
    // Remove any non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Check if it's a valid length (assuming Tanzania numbers)
    return (strlen($phone) >= 9 && strlen($phone) <= 13);
}

/**
 * Create test data for demonstration
 * This is useful for testing the plugin
 */
function kwetupizza_admin_create_test_data() {
    // Implementation would go here
    return true;
} 