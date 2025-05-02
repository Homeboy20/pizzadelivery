<?php
/**
 * Test Notifications Script for KwetuPizza Plugin
 *
 * This script is used to manually test SMS and WhatsApp notifications
 * for successful and failed payments.
 */

// Load WordPress environment
require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/wp-load.php');

// Include functions file
require_once(dirname(__FILE__) . '/functions.php');

// Prevent direct access without admin privileges
if (!current_user_can('manage_options')) {
    wp_die('Access denied.');
}

// Process form submission
$message = '';
if (isset($_POST['action'])) {
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    $test_phone = isset($_POST['test_phone']) ? sanitize_text_field($_POST['test_phone']) : '';
    $notification_type = isset($_POST['notification_type']) ? sanitize_text_field($_POST['notification_type']) : '';
    
    if ($notification_type === 'test_sms') {
        // Send a test SMS
        if (!empty($test_phone)) {
            $result = kwetupizza_send_nextsms($test_phone, 'This is a test SMS from KwetuPizza plugin at ' . current_time('mysql'));
            if ($result) {
                $message = 'Test SMS sent successfully to ' . $test_phone;
            } else {
                $message = 'Failed to send test SMS. Check the SMS log for details.';
            }
        } else {
            $message = 'Phone number is required for testing SMS.';
        }
    } elseif ($notification_type === 'test_whatsapp') {
        // Send a test WhatsApp message
        if (!empty($test_phone)) {
            $result = kwetupizza_send_whatsapp_message($test_phone, 'This is a test WhatsApp message from KwetuPizza plugin at ' . current_time('mysql'));
            if ($result) {
                $message = 'Test WhatsApp message sent successfully to ' . $test_phone;
            } else {
                $message = 'Failed to send test WhatsApp message. Check the logs for details.';
            }
        } else {
            $message = 'Phone number is required for testing WhatsApp.';
        }
    } elseif ($notification_type === 'payment_confirmed') {
        // Simulate a successful payment notification
        if (!empty($order_id)) {
            $result = kwetupizza_notify_customer($order_id, 'payment_confirmed');
            if ($result) {
                $message = 'Payment confirmed notification sent successfully for order #' . $order_id;
            } else {
                $message = 'Failed to send payment confirmed notification. Check the logs for details.';
            }
        } else {
            $message = 'Order ID is required for testing payment notifications.';
        }
    } elseif ($notification_type === 'payment_failed') {
        // Simulate a failed payment notification
        if (!empty($order_id)) {
            $result = kwetupizza_notify_customer($order_id, 'payment_failed');
            if ($result) {
                $message = 'Payment failed notification sent successfully for order #' . $order_id;
            } else {
                $message = 'Failed to send payment failed notification. Check the logs for details.';
            }
        } else {
            $message = 'Order ID is required for testing payment notifications.';
        }
    }
}

// Get recent orders for the dropdown
global $wpdb;
$orders_table = $wpdb->prefix . 'kwetupizza_orders';
$recent_orders = $wpdb->get_results("SELECT id, customer_name, customer_phone FROM $orders_table ORDER BY id DESC LIMIT 10");

// Output the HTML form
?>
<!DOCTYPE html>
<html>
<head>
    <title>KwetuPizza - Test Notifications</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; }
        h1 { color: #d32f2f; }
        .message { background-color: #f8f9fa; padding: 10px; margin-bottom: 20px; border-left: 4px solid #28a745; }
        .error { border-left-color: #dc3545; }
        label { display: block; margin-top: 10px; }
        input, select { width: 100%; padding: 8px; margin-top: 5px; box-sizing: border-box; }
        button { background-color: #d32f2f; color: white; border: none; padding: 10px 15px; margin-top: 20px; cursor: pointer; }
        hr { margin: 30px 0; }
        .section { background-color: #f8f9fa; padding: 20px; margin-bottom: 20px; }
        h2 { margin-top: 0; }
    </style>
</head>
<body>
    <h1>KwetuPizza - Test Notifications</h1>
    
    <?php if (!empty($message)): ?>
        <div class="message <?php echo (strpos($message, 'Failed') !== false) ? 'error' : ''; ?>">
            <?php echo esc_html($message); ?>
        </div>
    <?php endif; ?>
    
    <div class="section">
        <h2>Test Direct SMS/WhatsApp</h2>
        <form method="post">
            <label for="test_phone">Phone Number (with country code, e.g., 255712345678):</label>
            <input type="text" id="test_phone" name="test_phone" required>
            
            <label for="notification_type">Notification Type:</label>
            <select id="notification_type" name="notification_type">
                <option value="test_sms">Test SMS</option>
                <option value="test_whatsapp">Test WhatsApp</option>
            </select>
            
            <button type="submit" name="action" value="test_direct">Send Test Message</button>
        </form>
    </div>
    
    <div class="section">
        <h2>Test Payment Notifications</h2>
        <form method="post">
            <label for="order_id">Select Order:</label>
            <select id="order_id" name="order_id" required>
                <option value="">Select an order</option>
                <?php foreach ($recent_orders as $order): ?>
                    <option value="<?php echo esc_attr($order->id); ?>">
                        #<?php echo esc_html($order->id); ?> - <?php echo esc_html($order->customer_name); ?> (<?php echo esc_html($order->customer_phone); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            
            <label for="notification_type">Notification Type:</label>
            <select id="notification_type" name="notification_type">
                <option value="payment_confirmed">Payment Confirmed</option>
                <option value="payment_failed">Payment Failed</option>
            </select>
            
            <button type="submit" name="action" value="test_payment">Send Payment Notification</button>
        </form>
    </div>
    
    <p>
        <a href="<?php echo admin_url('admin.php?page=kwetupizza-settings'); ?>">&laquo; Back to Settings</a>
    </p>
</body>
</html> 