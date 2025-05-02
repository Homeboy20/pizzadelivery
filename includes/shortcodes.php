<?php
/**
 * KwetuPizza Shortcodes
 * 
 * Handles all shortcodes for customer-facing interfaces.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all shortcodes
 */
function kwetupizza_register_shortcodes() {
    add_shortcode('kwetupizza_menu', 'kwetupizza_menu_shortcode');
    add_shortcode('kwetupizza_order_tracking', 'kwetupizza_order_tracking_shortcode');
    add_shortcode('kwetupizza_customer_account', 'kwetupizza_customer_account_shortcode');
    add_shortcode('kwetupizza_confirm_delivery', 'kwetupizza_delivery_confirmation_shortcode');
    add_shortcode('kwetupizza_feedback', 'kwetupizza_feedback_shortcode');
}
add_action('init', 'kwetupizza_register_shortcodes');

/**
 * Menu shortcode
 */
function kwetupizza_menu_shortcode($atts) {
    $atts = shortcode_atts(array(
        'category' => '',
    ), $atts);
    
    // Enqueue necessary scripts and styles
    wp_enqueue_style('kwetupizza-menu-style', KWETUPIZZA_PLUGIN_URL . 'assets/css/menu.css', array(), KWETUPIZZA_VERSION);
    wp_enqueue_script('kwetupizza-menu-script', KWETUPIZZA_PLUGIN_URL . 'assets/js/menu.js', array('jquery'), KWETUPIZZA_VERSION, true);
    
    global $wpdb;
    $products_table = $wpdb->prefix . 'kwetupizza_products';
    
    // Get categories
    $categories = $wpdb->get_col("SELECT DISTINCT category FROM $products_table ORDER BY category");
    
    // Get products by category
    $products_by_category = array();
    foreach ($categories as $category) {
        if (!empty($atts['category']) && $atts['category'] !== $category) {
            continue;
        }
        
        $products = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $products_table 
            WHERE category = %s 
            ORDER BY product_name", 
            $category
        ));
        
        if (!empty($products)) {
            $products_by_category[$category] = $products;
        }
    }
    
    // Generate output
    ob_start();
    ?>
    <div class="kwetupizza-menu-container">
        <div class="kwetupizza-menu-filters">
            <ul>
                <li><a href="#" data-category="all" class="active">All</a></li>
                <?php foreach ($categories as $category) : ?>
                    <li><a href="#" data-category="<?php echo esc_attr(strtolower($category)); ?>"><?php echo esc_html($category); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <div class="kwetupizza-menu-items">
            <?php foreach ($products_by_category as $category => $products) : ?>
                <div class="kwetupizza-category-section" id="<?php echo esc_attr(strtolower($category)); ?>">
                    <h2><?php echo esc_html($category); ?></h2>
                    <div class="kwetupizza-product-grid">
                        <?php foreach ($products as $product) : ?>
                            <div class="kwetupizza-product-card">
                                <?php if (!empty($product->image_url)) : ?>
                                    <div class="kwetupizza-product-image">
                                        <img src="<?php echo esc_url($product->image_url); ?>" alt="<?php echo esc_attr($product->product_name); ?>">
                                    </div>
                                <?php endif; ?>
                                <div class="kwetupizza-product-details">
                                    <h3><?php echo esc_html($product->product_name); ?></h3>
                                    <p class="kwetupizza-product-description"><?php echo esc_html($product->description); ?></p>
                                    <div class="kwetupizza-product-price">
                                        <?php echo kwetupizza_format_currency($product->price, $product->currency); ?>
                                    </div>
                                    <button class="kwetupizza-add-to-cart" data-product-id="<?php echo esc_attr($product->id); ?>">
                                        Add to Cart
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="kwetupizza-cart">
            <h2>Your Order</h2>
            <div class="kwetupizza-cart-items"></div>
            <div class="kwetupizza-cart-total">
                <span>Total:</span>
                <span class="kwetupizza-cart-total-amount">0.00 TZS</span>
            </div>
            <button class="kwetupizza-checkout-button" disabled>Proceed to Checkout</button>
        </div>
        
        <!-- Checkout Modal -->
        <div class="kwetupizza-modal" id="kwetupizza-checkout-modal">
            <div class="kwetupizza-modal-content">
                <span class="kwetupizza-modal-close">&times;</span>
                <h2>Complete Your Order</h2>
                <form id="kwetupizza-checkout-form">
                    <div class="kwetupizza-form-group">
                        <label for="customer_name">Your Name</label>
                        <input type="text" id="customer_name" name="customer_name" required>
                    </div>
                    <div class="kwetupizza-form-group">
                        <label for="customer_phone">Phone Number</label>
                        <input type="tel" id="customer_phone" name="customer_phone" required>
                    </div>
                    <div class="kwetupizza-form-group">
                        <label for="delivery_address">Delivery Address</label>
                        <textarea id="delivery_address" name="delivery_address" required></textarea>
                    </div>
                    <div class="kwetupizza-form-group">
                        <label for="payment_method">Payment Method</label>
                        <select id="payment_method" name="payment_method" required>
                            <option value="">Select Payment Method</option>
                            <option value="mobile_money">Mobile Money</option>
                            <option value="card">Credit/Debit Card</option>
                            <option value="cash">Cash on Delivery</option>
                        </select>
                    </div>
                    <div class="kwetupizza-form-group" id="mobile_money_fields" style="display: none;">
                        <label for="payment_phone">Mobile Money Number</label>
                        <input type="tel" id="payment_phone" name="payment_phone">
                    </div>
                    <div class="kwetupizza-order-summary">
                        <h3>Order Summary</h3>
                        <div class="kwetupizza-summary-items"></div>
                        <div class="kwetupizza-summary-total">
                            <span>Total:</span>
                            <span class="kwetupizza-summary-total-amount">0.00 TZS</span>
                        </div>
                    </div>
                    <button type="submit" class="kwetupizza-place-order-button">Place Order</button>
                </form>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Order tracking shortcode
 */
function kwetupizza_order_tracking_shortcode($atts) {
    $atts = shortcode_atts(array(), $atts);
    
    // Enqueue necessary scripts and styles
    wp_enqueue_style('kwetupizza-tracking-style', KWETUPIZZA_PLUGIN_URL . 'assets/css/tracking.css', array(), KWETUPIZZA_VERSION);
    wp_enqueue_script('kwetupizza-tracking-script', KWETUPIZZA_PLUGIN_URL . 'assets/js/tracking.js', array('jquery'), KWETUPIZZA_VERSION, true);
    
    // Generate output
    ob_start();
    ?>
    <div class="kwetupizza-tracking-container">
        <h2>Track Your Order</h2>
        <div class="kwetupizza-tracking-form">
            <div class="kwetupizza-form-group">
                <label for="order_id">Order Number</label>
                <input type="text" id="order_id" name="order_id" placeholder="Enter your order number" required>
            </div>
            <div class="kwetupizza-form-group">
                <label for="phone">Phone Number (optional)</label>
                <input type="tel" id="phone" name="phone" placeholder="Enter your phone number">
                <small>For additional verification</small>
            </div>
            <button id="kwetupizza-track-order-button">Track Order</button>
        </div>
        
        <div class="kwetupizza-tracking-results" style="display: none;">
            <div class="kwetupizza-tracking-header">
                <h3>Order #<span id="tracking-order-id"></span></h3>
                <div class="kwetupizza-tracking-status">
                    Status: <span id="tracking-status"></span>
                </div>
            </div>
            
            <div class="kwetupizza-tracking-details">
                <div class="kwetupizza-tracking-info">
                    <div class="kwetupizza-info-group">
                        <label>Customer:</label>
                        <span id="tracking-customer"></span>
                    </div>
                    <div class="kwetupizza-info-group">
                        <label>Delivery Address:</label>
                        <span id="tracking-address"></span>
                    </div>
                    <div class="kwetupizza-info-group">
                        <label>Order Date:</label>
                        <span id="tracking-date"></span>
                    </div>
                    <div class="kwetupizza-info-group">
                        <label>Total:</label>
                        <span id="tracking-total"></span>
                    </div>
                </div>
                
                <div class="kwetupizza-order-items">
                    <h4>Order Items</h4>
                    <ul id="tracking-items"></ul>
                </div>
            </div>
            
            <div class="kwetupizza-tracking-timeline">
                <h4>Order Timeline</h4>
                <ul id="tracking-timeline"></ul>
            </div>
        </div>
        
        <div class="kwetupizza-tracking-error" style="display: none;">
            <p>Sorry, we couldn't find your order. Please check your order number and try again.</p>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Customer account shortcode
 */
function kwetupizza_customer_account_shortcode($atts) {
    $atts = shortcode_atts(array(), $atts);
    
    // Enqueue necessary scripts and styles
    wp_enqueue_style('kwetupizza-account-style', KWETUPIZZA_PLUGIN_URL . 'assets/css/account.css', array(), KWETUPIZZA_VERSION);
    wp_enqueue_script('kwetupizza-account-script', KWETUPIZZA_PLUGIN_URL . 'assets/js/account.js', array('jquery'), KWETUPIZZA_VERSION, true);
    
    // Generate output
    ob_start();
    ?>
    <div class="kwetupizza-account-container">
        <div class="kwetupizza-login-section" id="kwetupizza-login-section">
            <h2>Login to Your Account</h2>
            <form id="kwetupizza-login-form">
                <div class="kwetupizza-form-group">
                    <label for="login_phone">Phone Number</label>
                    <input type="tel" id="login_phone" name="login_phone" required>
                </div>
                <button type="submit" class="kwetupizza-login-button">Login / Sign Up</button>
            </form>
            <p class="kwetupizza-login-info">We'll send a verification code to your phone</p>
        </div>
        
        <div class="kwetupizza-verification-section" id="kwetupizza-verification-section" style="display: none;">
            <h2>Verify Your Phone</h2>
            <p>Please enter the verification code sent to your phone</p>
            <form id="kwetupizza-verification-form">
                <div class="kwetupizza-form-group">
                    <label for="verification_code">Verification Code</label>
                    <input type="text" id="verification_code" name="verification_code" required>
                </div>
                <button type="submit" class="kwetupizza-verify-button">Verify</button>
            </form>
        </div>
        
        <div class="kwetupizza-dashboard-section" id="kwetupizza-dashboard-section" style="display: none;">
            <div class="kwetupizza-dashboard-header">
                <h2>Welcome, <span id="customer-name">Customer</span></h2>
                <button id="kwetupizza-logout-button">Logout</button>
            </div>
            
            <div class="kwetupizza-dashboard-tabs">
                <div class="kwetupizza-tab active" data-tab="orders">Order History</div>
                <div class="kwetupizza-tab" data-tab="loyalty">Loyalty Points</div>
                <div class="kwetupizza-tab" data-tab="profile">Profile</div>
            </div>
            
            <div class="kwetupizza-tab-content" id="kwetupizza-orders-content">
                <h3>Your Orders</h3>
                <div class="kwetupizza-orders-list" id="kwetupizza-orders-list">
                    <p class="kwetupizza-loading">Loading your orders...</p>
                </div>
            </div>
            
            <div class="kwetupizza-tab-content" id="kwetupizza-loyalty-content" style="display: none;">
                <h3>Your Loyalty Points</h3>
                <div class="kwetupizza-loyalty-summary">
                    <div class="kwetupizza-points-card">
                        <div class="kwetupizza-points-header">
                            <h4>Available Points</h4>
                        </div>
                        <div class="kwetupizza-points-value" id="loyalty-points">0</div>
                        <div class="kwetupizza-points-footer">
                            <span id="loyalty-orders">0</span> orders completed
                        </div>
                    </div>
                </div>
                
                <div class="kwetupizza-rewards-list">
                    <h4>Available Rewards</h4>
                    <ul id="loyalty-rewards"></ul>
                </div>
            </div>
            
            <div class="kwetupizza-tab-content" id="kwetupizza-profile-content" style="display: none;">
                <h3>Your Profile</h3>
                <form id="kwetupizza-profile-form">
                    <div class="kwetupizza-form-group">
                        <label for="profile_name">Name</label>
                        <input type="text" id="profile_name" name="profile_name" required>
                    </div>
                    <div class="kwetupizza-form-group">
                        <label for="profile_email">Email (optional)</label>
                        <input type="email" id="profile_email" name="profile_email">
                    </div>
                    <div class="kwetupizza-form-group">
                        <label for="profile_phone">Phone Number</label>
                        <input type="tel" id="profile_phone" name="profile_phone" readonly>
                    </div>
                    <div class="kwetupizza-form-group">
                        <label for="profile_address">Default Delivery Address</label>
                        <textarea id="profile_address" name="profile_address"></textarea>
                    </div>
                    <button type="submit" class="kwetupizza-update-profile-button">Update Profile</button>
                </form>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * AJAX handler for order placement
 */
function kwetupizza_place_order() {
    // Verify nonce
    check_ajax_referer('kwetupizza-nonce', 'nonce');
    
    // Get form data
    $customer_name = sanitize_text_field($_POST['customer_name']);
    $customer_phone = kwetupizza_sanitize_phone($_POST['customer_phone']);
    $delivery_address = sanitize_textarea_field($_POST['delivery_address']);
    $payment_method = sanitize_text_field($_POST['payment_method']);
    $cart_items = isset($_POST['cart_items']) ? json_decode(stripslashes($_POST['cart_items']), true) : array();
    
    if (empty($cart_items)) {
        wp_send_json_error('Cart is empty');
        return;
    }
    
    global $wpdb;
    $orders_table = $wpdb->prefix . 'kwetupizza_orders';
    $order_items_table = $wpdb->prefix . 'kwetupizza_order_items';
    $products_table = $wpdb->prefix . 'kwetupizza_products';
    
    // Calculate total
    $total = 0;
    foreach ($cart_items as $item) {
        $product_id = intval($item['product_id']);
        $quantity = intval($item['quantity']);
        
        $product = $wpdb->get_row($wpdb->prepare("SELECT price FROM $products_table WHERE id = %d", $product_id));
        
        if ($product) {
            $total += $product->price * $quantity;
        }
    }
    
    // Create order
    $wpdb->insert(
        $orders_table,
        array(
            'customer_name' => $customer_name,
            'customer_phone' => $customer_phone,
            'delivery_address' => $delivery_address,
            'delivery_phone' => $customer_phone, // Use same phone for delivery
            'status' => 'pending',
            'total' => $total,
            'currency' => get_option('kwetupizza_currency', 'TZS'),
            'created_at' => current_time('mysql')
        )
    );
    
    $order_id = $wpdb->insert_id;
    
    if (!$order_id) {
        wp_send_json_error('Failed to create order');
        return;
    }
    
    // Add order items
    foreach ($cart_items as $item) {
        $product_id = intval($item['product_id']);
        $quantity = intval($item['quantity']);
        
        $product = $wpdb->get_row($wpdb->prepare("SELECT price FROM $products_table WHERE id = %d", $product_id));
        
        if ($product) {
            $wpdb->insert(
                $order_items_table,
                array(
                    'order_id' => $order_id,
                    'product_id' => $product_id,
                    'quantity' => $quantity,
                    'price' => $product->price
                )
            );
        }
    }
    
    // Create transaction
    $transactions_table = $wpdb->prefix . 'kwetupizza_transactions';
    $wpdb->insert(
        $transactions_table,
        array(
            'order_id' => $order_id,
            'transaction_date' => current_time('mysql'),
            'payment_method' => $payment_method,
            'payment_status' => $payment_method === 'cash' ? 'pending' : 'initiated',
            'amount' => $total,
            'currency' => get_option('kwetupizza_currency', 'TZS'),
            'payment_provider' => $payment_method === 'mobile_money' ? 'flutterwave' : ($payment_method === 'card' ? 'flutterwave' : 'cash'),
            'created_at' => current_time('mysql')
        )
    );
    
    // Process payment based on method
    if ($payment_method === 'mobile_money' || $payment_method === 'card') {
        $payment_response = kwetupizza_initiate_flutterwave_payment($order_id, $customer_name, $customer_phone, $total, $payment_method);
        wp_send_json_success(array(
            'order_id' => $order_id,
            'redirect' => $payment_response['redirect_url']
        ));
    } else {
        // For cash on delivery
        kwetupizza_notify_admin($order_id, true);
        
        // Send confirmation to customer
        $message = "Thank you for your order! Your order #$order_id has been received and will be delivered soon. You will pay " . kwetupizza_format_currency($total) . " upon delivery.";
        kwetupizza_send_whatsapp_message($customer_phone, $message);
        
        wp_send_json_success(array(
            'order_id' => $order_id,
            'message' => 'Order placed successfully. You will receive a confirmation message shortly.'
        ));
    }
}
add_action('wp_ajax_kwetupizza_place_order', 'kwetupizza_place_order');
add_action('wp_ajax_nopriv_kwetupizza_place_order', 'kwetupizza_place_order');

/**
 * Initiate Flutterwave payment
 */
function kwetupizza_initiate_flutterwave_payment($order_id, $customer_name, $customer_phone, $amount, $payment_method) {
    $flw_public_key = get_option('kwetupizza_flw_public_key');
    $tx_ref = 'order-' . $order_id;
    $redirect_url = home_url('/thank-you/');
    
    // API integration
    $payment_data = array(
        'tx_ref' => $tx_ref,
        'amount' => $amount,
        'currency' => get_option('kwetupizza_currency', 'TZS'),
        'redirect_url' => $redirect_url,
        'customer' => array(
            'email' => kwetupizza_get_customer_email($customer_phone),
            'phone_number' => $customer_phone,
            'name' => $customer_name
        ),
        'meta' => array(
            'order_id' => $order_id,
            'delivery_address' => get_post_meta($order_id, 'delivery_address', true)
        ),
        'payment_options' => $payment_method === 'mobile_money' ? 'mobilemoneytanzania' : 'card'
    );
    
    // For testing/demo purposes
    return array(
        'redirect_url' => add_query_arg(array(
            'flw_tx_ref' => $tx_ref,
            'order_id' => $order_id
        ), $redirect_url)
    );
}

// The kwetupizza_get_customer_email function is defined in includes/functions.php
// and should not be redefined here to avoid PHP fatal errors.

/**
 * Shortcode for the delivery confirmation page
 */
function kwetupizza_delivery_confirmation_shortcode() {
    ob_start();
    
    $order_id = isset($_GET['order']) ? intval($_GET['order']) : 0;
    $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
    $confirmed = isset($_GET['confirmed']) && $_GET['confirmed'] == '1';
    
    if ($order_id && $token && !$confirmed && function_exists('kwetupizza_handle_delivery_confirmation')) {
        $result = kwetupizza_handle_delivery_confirmation($order_id, $token);
        
        if ($result['success']) {
            // Redirect to the same page with confirmed flag to prevent multiple confirmations
            wp_redirect(add_query_arg('confirmed', '1', remove_query_arg(['order', 'token'])));
            exit;
        }
    }
    
    ?>
    <div class="kwetupizza-delivery-confirmation">
        <?php if ($confirmed): ?>
            <div class="confirmation-success">
                <div class="icon-wrapper">
                    <i class="dashicons dashicons-yes"></i>
                </div>
                <h2>Thank You!</h2>
                <p>Your delivery has been confirmed. We hope you enjoy your meal!</p>
                <p>You'll receive a request for feedback shortly to let us know about your experience.</p>
            </div>
        <?php elseif ($order_id && $token): ?>
            <div class="confirmation-form">
                <h2>Confirm Your Delivery</h2>
                <p>Please confirm that you've received your order #<?php echo $order_id; ?>.</p>
                
                <?php if (isset($result) && !$result['success']): ?>
                    <div class="error-message">
                        <?php echo esc_html($result['message']); ?>
                    </div>
                <?php endif; ?>
                
                <form method="post">
                    <input type="hidden" name="order_id" value="<?php echo esc_attr($order_id); ?>">
                    <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">
                    <button type="submit" class="button confirm-button">Yes, I've Received My Order</button>
                </form>
            </div>
        <?php else: ?>
            <div class="invalid-request">
                <h2>Invalid Request</h2>
                <p>Sorry, this confirmation link is invalid or has expired.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <style>
        .kwetupizza-delivery-confirmation {
            max-width: 600px;
            margin: 0 auto;
            padding: 30px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .confirmation-success .icon-wrapper {
            width: 80px;
            height: 80px;
            background-color: #28a745;
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        .confirmation-success .dashicons {
            font-size: 40px;
            width: 40px;
            height: 40px;
        }
        .confirmation-form {
            padding: 20px 0;
        }
        .confirm-button {
            background-color: #28a745 !important;
            color: #fff !important;
            border: none !important;
            padding: 12px 24px !important;
            font-size: 16px !important;
            border-radius: 4px !important;
            cursor: pointer !important;
            margin-top: 20px !important;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
    </style>
    <?php
    
    return ob_get_clean();
}

/**
 * Shortcode for the feedback form
 */
function kwetupizza_feedback_shortcode() {
    ob_start();
    
    $order_id = isset($_GET['order']) ? intval($_GET['order']) : 0;
    $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
    $submitted = isset($_GET['submitted']) && $_GET['submitted'] == '1';
    
    // Process form submission
    if (isset($_POST['submit_feedback']) && $order_id && $token && function_exists('kwetupizza_save_customer_feedback')) {
        $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
        $comments = isset($_POST['comments']) ? sanitize_textarea_field($_POST['comments']) : '';
        
        if ($rating >= 1 && $rating <= 5) {
            $result = kwetupizza_save_customer_feedback($order_id, $token, $rating, $comments);
            
            if ($result['success']) {
                // Redirect to the same page with submitted flag
                wp_redirect(add_query_arg('submitted', '1', remove_query_arg(['order', 'token'])));
                exit;
            }
        } else {
            $error_message = 'Please select a rating between 1 and 5.';
        }
    }
    
    ?>
    <div class="kwetupizza-feedback-form">
        <?php if ($submitted): ?>
            <div class="feedback-success">
                <div class="icon-wrapper">
                    <i class="dashicons dashicons-star-filled"></i>
                </div>
                <h2>Thank You for Your Feedback!</h2>
                <p>We appreciate you taking the time to rate your experience with us.</p>
                <p>Your feedback helps us improve our service for all customers.</p>
            </div>
        <?php elseif ($order_id && $token): ?>
            <div class="feedback-form">
                <h2>Rate Your Experience</h2>
                <p>Please tell us about your recent order experience.</p>
                
                <?php if (isset($result) && !$result['success']): ?>
                    <div class="error-message">
                        <?php echo esc_html($result['message']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="error-message">
                        <?php echo esc_html($error_message); ?>
                    </div>
                <?php endif; ?>
                
                <form method="post">
                    <div class="rating-container">
                        <div class="rating">
                            <input type="radio" id="star5" name="rating" value="5" /><label for="star5"></label>
                            <input type="radio" id="star4" name="rating" value="4" /><label for="star4"></label>
                            <input type="radio" id="star3" name="rating" value="3" /><label for="star3"></label>
                            <input type="radio" id="star2" name="rating" value="2" /><label for="star2"></label>
                            <input type="radio" id="star1" name="rating" value="1" /><label for="star1"></label>
                        </div>
                        <div class="rating-text">How would you rate your experience?</div>
                    </div>
                    
                    <div class="comments-container">
                        <label for="comments">Additional Comments (Optional)</label>
                        <textarea name="comments" id="comments" rows="4" placeholder="Tell us what you liked or how we can improve..."></textarea>
                    </div>
                    
                    <input type="hidden" name="order_id" value="<?php echo esc_attr($order_id); ?>">
                    <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">
                    <button type="submit" name="submit_feedback" class="button submit-button">Submit Feedback</button>
                </form>
            </div>
        <?php else: ?>
            <div class="invalid-request">
                <h2>Invalid Request</h2>
                <p>Sorry, this feedback link is invalid or has expired.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <style>
        .kwetupizza-feedback-form {
            max-width: 600px;
            margin: 0 auto;
            padding: 30px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .feedback-success .icon-wrapper {
            width: 80px;
            height: 80px;
            background-color: #ffc107;
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        .feedback-success .dashicons {
            font-size: 40px;
            width: 40px;
            height: 40px;
        }
        .feedback-form {
            padding: 20px 0;
        }
        .rating-container {
            margin: 20px 0;
        }
        .rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: center;
            margin-bottom: 10px;
        }
        .rating:not(:checked) > input {
            position: absolute;
            clip: rect(0,0,0,0);
        }
        .rating:not(:checked) > label {
            float: right;
            width: 1.2em;
            overflow: hidden;
            white-space: nowrap;
            cursor: pointer;
            font-size: 2em;
            color: #ccc;
        }
        .rating:not(:checked) > label:before {
            content: '★';
        }
        .rating > input:checked ~ label {
            color: #ffc107;
        }
        .rating:not(:checked) > label:hover,
        .rating:not(:checked) > label:hover ~ label {
            color: #ffdb70;
        }
        .rating > input:checked + label:hover,
        .rating > input:checked + label:hover ~ label,
        .rating > input:checked ~ label:hover,
        .rating > input:checked ~ label:hover ~ label,
        .rating > label:hover ~ input:checked ~ label {
            color: #ffd25e;
        }
        .comments-container {
            margin: 20px 0;
            text-align: left;
        }
        .comments-container label {
            display: block;
            margin-bottom: 5px;
        }
        .comments-container textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .submit-button {
            background-color: #ffc107 !important;
            color: #212529 !important;
            border: none !important;
            padding: 12px 24px !important;
            font-size: 16px !important;
            border-radius: 4px !important;
            cursor: pointer !important;
            margin-top: 20px !important;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
    </style>
    <?php
    
    return ob_get_clean();
} 