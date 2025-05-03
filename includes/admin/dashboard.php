<?php
/**
 * KwetuPizza Admin Dashboard Functions
 * 
 * Contains functions for the plugin's admin dashboard functionality.
 * This file was created to fix the missing file error.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get dashboard analytics data
 * 
 * @return array Analytics data for the dashboard
 */
function kwetupizza_get_dashboard_analytics() {
    global $wpdb;
    $orders_table = $wpdb->prefix . 'kwetupizza_orders';
    $transactions_table = $wpdb->prefix . 'kwetupizza_transactions';
    $users_table = $wpdb->prefix . 'kwetupizza_users';
    
    // Get orders count
    $total_orders = $wpdb->get_var("SELECT COUNT(*) FROM $orders_table");
    
    // Get completed orders
    $completed_orders = $wpdb->get_var("SELECT COUNT(*) FROM $orders_table WHERE status = 'delivered' OR status = 'completed'");
    
    // Get total revenue
    $total_revenue = $wpdb->get_var("SELECT SUM(total) FROM $orders_table WHERE status = 'delivered' OR status = 'completed'");
    
    // Get users count
    $total_users = $wpdb->get_var("SELECT COUNT(*) FROM $users_table");
    
    // Get recent orders
    $recent_orders = $wpdb->get_results("SELECT * FROM $orders_table ORDER BY created_at DESC LIMIT 5");
    
    return array(
        'total_orders' => $total_orders ?: 0,
        'completed_orders' => $completed_orders ?: 0,
        'total_revenue' => $total_revenue ?: 0,
        'total_users' => $total_users ?: 0,
        'recent_orders' => $recent_orders ?: array()
    );
}

/**
 * Get pending orders count
 * Used for admin notification badge
 * 
 * @return int Number of pending orders
 */
function kwetupizza_get_pending_orders_count() {
    global $wpdb;
    $orders_table = $wpdb->prefix . 'kwetupizza_orders';
    
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $orders_table WHERE status = 'pending_payment' OR status = 'processing'");
    
    return $count ?: 0;
}

/**
 * Display a summary of recent activity on the dashboard
 * 
 * @return string HTML of the activity summary
 */
function kwetupizza_get_activity_summary() {
    global $wpdb;
    $orders_table = $wpdb->prefix . 'kwetupizza_orders';
    
    // Get orders in last 24 hours
    $last_24h = date('Y-m-d H:i:s', strtotime('-24 hours'));
    $recent_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $orders_table WHERE created_at > %s",
        $last_24h
    ));
    
    // Get orders in last 7 days
    $last_7d = date('Y-m-d H:i:s', strtotime('-7 days'));
    $weekly_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $orders_table WHERE created_at > %s",
        $last_7d
    ));
    
    // Output the summary HTML
    $html = '<div class="activity-summary">';
    $html .= '<p><strong>Last 24 Hours:</strong> ' . ($recent_count ?: 0) . ' orders</p>';
    $html .= '<p><strong>Last 7 Days:</strong> ' . ($weekly_count ?: 0) . ' orders</p>';
    $html .= '</div>';
    
    return $html;
} 