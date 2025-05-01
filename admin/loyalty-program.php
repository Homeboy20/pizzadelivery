<?php
/**
 * KwetuPizza Loyalty Program Management
 * 
 * Handles the loyalty program management interface.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the loyalty program management page
 */
function kwetupizza_render_loyalty_program() {
    global $wpdb;
    $loyalty_table = $wpdb->prefix . 'kwetupizza_customer_loyalty';
    $users_table = $wpdb->prefix . 'kwetupizza_users';
    
    // Handle form submissions for settings
    if (isset($_POST['save_loyalty_settings']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'kwetupizza_loyalty_settings')) {
        update_option('kwetupizza_points_per_order', intval($_POST['points_per_order']));
        update_option('kwetupizza_points_per_amount', intval($_POST['points_per_amount']));
        update_option('kwetupizza_points_amount_threshold', intval($_POST['points_amount_threshold']));
        update_option('kwetupizza_enable_loyalty_program', isset($_POST['enable_loyalty_program']) ? '1' : '0');
        update_option('kwetupizza_notify_loyalty_points', isset($_POST['notify_loyalty_points']) ? '1' : '0');
        
        echo '<div class="notice notice-success"><p>Loyalty program settings updated successfully!</p></div>';
    }
    
    // Handle manual point adjustment
    if (isset($_POST['adjust_points']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'kwetupizza_adjust_points')) {
        $customer_phone = kwetupizza_sanitize_phone($_POST['customer_phone']);
        $points_adjustment = intval($_POST['points_adjustment']);
        $adjustment_note = sanitize_text_field($_POST['adjustment_note']);
        
        // Check if customer exists in loyalty program
        $customer = $wpdb->get_row($wpdb->prepare("SELECT * FROM $loyalty_table WHERE customer_phone = %s", $customer_phone));
        
        if ($customer) {
            // Update points
            $new_points = max(0, $customer->points + $points_adjustment);
            
            $wpdb->update(
                $loyalty_table,
                array(
                    'points' => $new_points,
                    'updated_at' => current_time('mysql')
                ),
                array('customer_phone' => $customer_phone)
            );
            
            // Log the adjustment
            $wpdb->insert(
                $wpdb->prefix . 'kwetupizza_loyalty_log',
                array(
                    'customer_phone' => $customer_phone,
                    'points_change' => $points_adjustment,
                    'reason' => $adjustment_note,
                    'admin_user' => get_current_user_id(),
                    'created_at' => current_time('mysql')
                )
            );
            
            // Notify customer if enabled
            if (get_option('kwetupizza_notify_loyalty_points', '1') === '1') {
                $message = $points_adjustment > 0 
                    ? "Congratulations! We've added $points_adjustment loyalty points to your account. Your new balance is $new_points points."
                    : "We've adjusted your loyalty points by $points_adjustment. Your new balance is $new_points points.";
                
                kwetupizza_send_whatsapp_message($customer_phone, $message);
            }
            
            echo '<div class="notice notice-success"><p>Customer loyalty points adjusted successfully!</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Customer not found in the loyalty program.</p></div>';
        }
    }
    
    // Get current settings
    $points_per_order = get_option('kwetupizza_points_per_order', 10);
    $points_per_amount = get_option('kwetupizza_points_per_amount', 1);
    $points_amount_threshold = get_option('kwetupizza_points_amount_threshold', 1000);
    $enable_loyalty_program = get_option('kwetupizza_enable_loyalty_program', '1');
    $notify_loyalty_points = get_option('kwetupizza_notify_loyalty_points', '1');
    
    // Get customer loyalty data
    $customers = $wpdb->get_results("
        SELECT l.*, 
            u.name as customer_name, 
            u.email as customer_email,
            COUNT(o.id) as order_count
        FROM $loyalty_table l
        LEFT JOIN $users_table u ON l.customer_phone = u.phone
        LEFT JOIN {$wpdb->prefix}kwetupizza_orders o ON l.customer_phone = o.customer_phone
        GROUP BY l.customer_phone
        ORDER BY l.points DESC
    ");
    
    // Get redemption history if table exists
    $redemption_history = array();
    if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}kwetupizza_loyalty_redemption'") == $wpdb->prefix . 'kwetupizza_loyalty_redemption') {
        $redemption_history = $wpdb->get_results("
            SELECT r.*, u.name as customer_name
            FROM {$wpdb->prefix}kwetupizza_loyalty_redemption r
            LEFT JOIN $users_table u ON r.customer_phone = u.phone
            ORDER BY r.redeemed_at DESC
            LIMIT 20
        ");
    }
    ?>
    <div class="wrap">
        <h1>Loyalty Program Management</h1>
        
        <div class="kwetupizza-admin-content">
            <div class="kwetupizza-admin-left">
                <div class="postbox">
                    <h2 class="hndle"><span>Loyalty Program Settings</span></h2>
                    <div class="inside">
                        <form method="post" action="">
                            <?php wp_nonce_field('kwetupizza_loyalty_settings'); ?>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">Enable Loyalty Program</th>
                                    <td>
                                        <input type="checkbox" id="enable_loyalty_program" name="enable_loyalty_program" value="1" 
                                            <?php checked($enable_loyalty_program, '1'); ?>>
                                        <label for="enable_loyalty_program">Enable loyalty points and rewards</label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="points_per_order">Points Per Order</label></th>
                                    <td>
                                        <input type="number" id="points_per_order" name="points_per_order" min="0" 
                                            value="<?php echo esc_attr($points_per_order); ?>">
                                        <p class="description">Fixed points awarded for each order regardless of amount</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="points_per_amount">Points Per Amount</label></th>
                                    <td>
                                        <input type="number" id="points_per_amount" name="points_per_amount" min="0" 
                                            value="<?php echo esc_attr($points_per_amount); ?>">
                                        <span>points for every</span>
                                        <input type="number" id="points_amount_threshold" name="points_amount_threshold" min="1" 
                                            value="<?php echo esc_attr($points_amount_threshold); ?>">
                                        <span><?php echo esc_html(get_option('kwetupizza_currency', 'TZS')); ?> spent</span>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Notify Customers</th>
                                    <td>
                                        <input type="checkbox" id="notify_loyalty_points" name="notify_loyalty_points" value="1" 
                                            <?php checked($notify_loyalty_points, '1'); ?>>
                                        <label for="notify_loyalty_points">Send WhatsApp notifications when points are earned or redeemed</label>
                                    </td>
                                </tr>
                            </table>
                            
                            <p class="submit">
                                <input type="submit" name="save_loyalty_settings" class="button button-primary" value="Save Settings">
                            </p>
                        </form>
                    </div>
                </div>
                
                <div class="postbox">
                    <h2 class="hndle"><span>Adjust Customer Points</span></h2>
                    <div class="inside">
                        <form method="post" action="">
                            <?php wp_nonce_field('kwetupizza_adjust_points'); ?>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="customer_phone">Customer Phone</label></th>
                                    <td>
                                        <input type="text" id="customer_phone" name="customer_phone" class="regular-text" required>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="points_adjustment">Points Adjustment</label></th>
                                    <td>
                                        <input type="number" id="points_adjustment" name="points_adjustment" required>
                                        <p class="description">Use positive number to add points, negative to deduct points</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="adjustment_note">Note</label></th>
                                    <td>
                                        <input type="text" id="adjustment_note" name="adjustment_note" class="regular-text" 
                                            placeholder="Reason for adjustment" required>
                                    </td>
                                </tr>
                            </table>
                            
                            <p class="submit">
                                <input type="submit" name="adjust_points" class="button button-primary" value="Adjust Points">
                            </p>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="kwetupizza-admin-right">
                <div class="postbox">
                    <h2 class="hndle"><span>Customer Loyalty Summary</span></h2>
                    <div class="inside">
                        <?php if (!empty($customers)) : ?>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th>Customer</th>
                                        <th>Phone</th>
                                        <th>Points</th>
                                        <th>Orders</th>
                                        <th>Total Spent</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($customers as $customer) : ?>
                                        <tr>
                                            <td>
                                                <?php echo !empty($customer->customer_name) ? esc_html($customer->customer_name) : 'Guest'; ?>
                                            </td>
                                            <td><?php echo esc_html($customer->customer_phone); ?></td>
                                            <td><?php echo esc_html($customer->points); ?></td>
                                            <td><?php echo esc_html($customer->total_orders); ?></td>
                                            <td><?php echo kwetupizza_format_currency($customer->total_spent); ?></td>
                                            <td>
                                                <button type="button" class="button button-small adjust-points-button" 
                                                    data-phone="<?php echo esc_attr($customer->customer_phone); ?>">
                                                    Adjust Points
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else : ?>
                            <p>No customers have enrolled in the loyalty program yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!empty($redemption_history)) : ?>
                <div class="postbox">
                    <h2 class="hndle"><span>Recent Redemptions</span></h2>
                    <div class="inside">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Reward</th>
                                    <th>Points Used</th>
                                    <th>Redeemed On</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($redemption_history as $redemption) : ?>
                                    <tr>
                                        <td>
                                            <?php echo !empty($redemption->customer_name) ? esc_html($redemption->customer_name) : 'Guest'; ?>
                                            (<?php echo esc_html($redemption->customer_phone); ?>)
                                        </td>
                                        <td><?php echo esc_html($redemption->reward_name); ?></td>
                                        <td><?php echo esc_html($redemption->points_used); ?></td>
                                        <td><?php echo esc_html(date('Y-m-d H:i', strtotime($redemption->redeemed_at))); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="postbox">
                    <h2 class="hndle"><span>Available Rewards</span></h2>
                    <div class="inside">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>Reward</th>
                                    <th>Points Required</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Free Regular Drink</td>
                                    <td>50</td>
                                    <td>A free regular drink with any order</td>
                                </tr>
                                <tr>
                                    <td>Free Delivery</td>
                                    <td>100</td>
                                    <td>Free delivery on any order</td>
                                </tr>
                                <tr>
                                    <td>10% Discount</td>
                                    <td>200</td>
                                    <td>10% discount on any order</td>
                                </tr>
                                <tr>
                                    <td>Free Medium Pizza</td>
                                    <td>500</td>
                                    <td>A free medium pizza of your choice</td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <p class="description">These rewards are currently hardcoded in the system. Future updates will add a UI to manage rewards.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // When adjust points button is clicked, fill in the phone number
        $('.adjust-points-button').on('click', function() {
            var phone = $(this).data('phone');
            $('#customer_phone').val(phone);
            // Scroll to the adjustment form
            $('html, body').animate({
                scrollTop: $("#customer_phone").offset().top - 100
            }, 500);
        });
    });
    </script>
    
    <style>
    .kwetupizza-admin-content {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
    }
    
    .kwetupizza-admin-left {
        flex: 1;
        min-width: 400px;
    }
    
    .kwetupizza-admin-right {
        flex: 2;
        min-width: 600px;
    }
    
    .postbox {
        margin-bottom: 20px;
    }
    
    .hndle {
        padding: 12px 15px;
        margin: 0;
        border-bottom: 1px solid #eee;
    }
    
    .inside {
        padding: 15px;
    }
    </style>
    <?php
}

/**
 * Add loyalty points when order is completed
 */
function kwetupizza_add_loyalty_points_on_order_status_change($order_id, $old_status, $new_status) {
    // Only proceed if loyalty program is enabled
    if (get_option('kwetupizza_enable_loyalty_program', '1') !== '1') {
        return;
    }
    
    // Only add points when order is completed
    if ($new_status !== 'completed') {
        return;
    }
    
    // Get order data
    global $wpdb;
    $orders_table = $wpdb->prefix . 'kwetupizza_orders';
    
    $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM $orders_table WHERE id = %d", $order_id));
    
    if (!$order) {
        return;
    }
    
    // Calculate points
    $points_per_order = get_option('kwetupizza_points_per_order', 10);
    $points_per_amount = get_option('kwetupizza_points_per_amount', 1);
    $points_amount_threshold = get_option('kwetupizza_points_amount_threshold', 1000);
    
    $points_from_order = $points_per_order;
    $points_from_amount = floor(($order->total / $points_amount_threshold) * $points_per_amount);
    
    $total_points = $points_from_order + $points_from_amount;
    
    // Add points to customer
    kwetupizza_add_loyalty_points($order_id, $total_points);
    
    // Notify customer if enabled
    if (get_option('kwetupizza_notify_loyalty_points', '1') === '1') {
        $message = "Thank you for your order! You've earned $total_points loyalty points. Check your balance and available rewards by visiting " . home_url('/customer-account/');
        kwetupizza_send_whatsapp_message($order->customer_phone, $message);
    }
}
add_action('kwetupizza_order_status_changed', 'kwetupizza_add_loyalty_points_on_order_status_change', 10, 3);

/**
 * AJAX handler for redeeming loyalty rewards
 */
function kwetupizza_redeem_loyalty_reward() {
    check_ajax_referer('kwetupizza-nonce', 'nonce');
    
    // Only proceed if loyalty program is enabled
    if (get_option('kwetupizza_enable_loyalty_program', '1') !== '1') {
        wp_send_json_error('Loyalty program is currently disabled');
        return;
    }
    
    $phone = isset($_POST['phone']) ? kwetupizza_sanitize_phone($_POST['phone']) : '';
    $reward_id = isset($_POST['reward_id']) ? intval($_POST['reward_id']) : 0;
    
    if (empty($phone)) {
        wp_send_json_error('Phone number is required');
        return;
    }
    
    // Define rewards (future enhancement: make this configurable)
    $rewards = array(
        1 => array('name' => 'Free Regular Drink', 'points' => 50, 'code' => 'FREEDRINK'),
        2 => array('name' => 'Free Delivery', 'points' => 100, 'code' => 'FREEDEL'),
        3 => array('name' => '10% Discount', 'points' => 200, 'code' => 'DISC10'),
        4 => array('name' => 'Free Medium Pizza', 'points' => 500, 'code' => 'FREEPIZZA')
    );
    
    if (!isset($rewards[$reward_id])) {
        wp_send_json_error('Invalid reward selected');
        return;
    }
    
    $reward = $rewards[$reward_id];
    
    // Check if customer has enough points
    global $wpdb;
    $loyalty_table = $wpdb->prefix . 'kwetupizza_customer_loyalty';
    
    $customer = $wpdb->get_row($wpdb->prepare("SELECT * FROM $loyalty_table WHERE customer_phone = %s", $phone));
    
    if (!$customer) {
        wp_send_json_error('Customer not found in loyalty program');
        return;
    }
    
    if ($customer->points < $reward['points']) {
        wp_send_json_error('Not enough points to redeem this reward');
        return;
    }
    
    // Deduct points
    $wpdb->update(
        $loyalty_table,
        array(
            'points' => $customer->points - $reward['points'],
            'updated_at' => current_time('mysql')
        ),
        array('customer_phone' => $phone)
    );
    
    // Create redemption record
    if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}kwetupizza_loyalty_redemption'") != $wpdb->prefix . 'kwetupizza_loyalty_redemption') {
        // Create table if it doesn't exist
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$wpdb->prefix}kwetupizza_loyalty_redemption (
            id mediumint(9) UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_phone varchar(20) NOT NULL,
            reward_id int NOT NULL,
            reward_name varchar(100) NOT NULL,
            points_used int NOT NULL,
            reward_code varchar(20) NOT NULL,
            redeemed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            used_at TIMESTAMP NULL DEFAULT NULL,
            order_id int NULL DEFAULT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    // Generate unique redemption code
    $redemption_code = $reward['code'] . strtoupper(kwetupizza_generate_random_string(6));
    
    // Insert redemption record
    $wpdb->insert(
        $wpdb->prefix . 'kwetupizza_loyalty_redemption',
        array(
            'customer_phone' => $phone,
            'reward_id' => $reward_id,
            'reward_name' => $reward['name'],
            'points_used' => $reward['points'],
            'reward_code' => $redemption_code,
            'redeemed_at' => current_time('mysql')
        )
    );
    
    // Notify customer if enabled
    if (get_option('kwetupizza_notify_loyalty_points', '1') === '1') {
        $message = "You've successfully redeemed {$reward['name']} for {$reward['points']} points!\n\nYour redemption code is: $redemption_code\n\nUse this code during checkout to apply your reward.";
        kwetupizza_send_whatsapp_message($phone, $message);
    }
    
    wp_send_json_success(array(
        'message' => "You've successfully redeemed {$reward['name']}!",
        'redemption_code' => $redemption_code,
        'remaining_points' => $customer->points - $reward['points']
    ));
}
add_action('wp_ajax_kwetupizza_redeem_loyalty_reward', 'kwetupizza_redeem_loyalty_reward');
add_action('wp_ajax_nopriv_kwetupizza_redeem_loyalty_reward', 'kwetupizza_redeem_loyalty_reward'); 