<?php
/**
 * PayPal Checkout Page
 * 
 * This template is used to handle PayPal checkouts
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the PayPal checkout page
 */
function kwetupizza_render_paypal_checkout() {
    // Get parameters from URL
    $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
    $tx_ref = isset($_GET['tx_ref']) ? sanitize_text_field($_GET['tx_ref']) : '';
    $amount = isset($_GET['amount']) ? floatval($_GET['amount']) : 0;
    $currency = isset($_GET['currency']) ? sanitize_text_field($_GET['currency']) : 'USD';
    $client_id = isset($_GET['client_id']) ? sanitize_text_field($_GET['client_id']) : '';
    $sandbox = isset($_GET['sandbox']) && $_GET['sandbox'] === '1';
    $return_url = isset($_GET['return_url']) ? urldecode($_GET['return_url']) : '';
    $cancel_url = isset($_GET['cancel_url']) ? urldecode($_GET['cancel_url']) : '';
    $customer_name = isset($_GET['customer_name']) ? urldecode($_GET['customer_name']) : '';
    $customer_email = isset($_GET['customer_email']) ? urldecode($_GET['customer_email']) : '';
    
    // Validate required parameters
    if (empty($order_id) || empty($tx_ref) || empty($amount) || empty($client_id)) {
        wp_die('Missing required parameters for PayPal checkout');
    }
    
    // Get order details
    global $wpdb;
    $orders_table = $wpdb->prefix . 'kwetupizza_orders';
    $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM $orders_table WHERE id = %d", $order_id));
    
    if (!$order) {
        wp_die('Order not found');
    }
    
    // Check if payment is already completed
    $transactions_table = $wpdb->prefix . 'kwetupizza_transactions';
    $transaction = $wpdb->get_row($wpdb->prepare("SELECT * FROM $transactions_table WHERE order_id = %d", $order_id));
    
    if ($transaction && $transaction->payment_status === 'completed') {
        // Redirect to thank you page
        wp_redirect(get_permalink(get_page_by_path('thank-you')));
        exit;
    }
    
    // Build page title and description
    $site_name = get_bloginfo('name');
    $page_title = 'Pay for Order #' . $order_id . ' - ' . $site_name;
    $description = 'Complete your payment for KwetuPizza order #' . $order_id;
    
    // Determine API endpoint based on sandbox mode
    $script_src = $sandbox ? 
        'https://www.paypal.com/sdk/js?client-id=' . $client_id . '&currency=' . $currency . '&intent=capture' : 
        'https://www.paypal.com/sdk/js?client-id=' . $client_id . '&currency=' . $currency . '&intent=capture';
    
    // Render the page
    ?><!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo esc_html($page_title); ?></title>
        <style>
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background-color: #f8f9fa;
                margin: 0;
                padding: 0;
                color: #333;
                display: flex;
                flex-direction: column;
                min-height: 100vh;
            }
            header {
                background-color: #ff5a00;
                color: white;
                text-align: center;
                padding: 1rem;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            }
            main {
                max-width: 800px;
                margin: 2rem auto;
                padding: 2rem;
                background-color: white;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                flex: 1;
            }
            .order-summary {
                margin-bottom: 2rem;
                padding-bottom: 1rem;
                border-bottom: 1px solid #eee;
            }
            .amount {
                font-size: 1.4rem;
                font-weight: bold;
                color: #ff5a00;
                margin: 1rem 0;
            }
            .payment-container {
                margin-top: 2rem;
            }
            footer {
                background-color: #333;
                color: white;
                text-align: center;
                padding: 1rem;
                margin-top: auto;
            }
            .loading-indicator {
                text-align: center;
                margin: 2rem 0;
                display: none;
            }
            .spinner {
                border: 4px solid rgba(0, 0, 0, 0.1);
                border-radius: 50%;
                border-top: 4px solid #ff5a00;
                width: 40px;
                height: 40px;
                animation: spin 1s linear infinite;
                margin: 0 auto 1rem;
            }
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>
    </head>
    <body>
        <header>
            <h1>KwetuPizza Payment</h1>
        </header>
        
        <main>
            <div class="order-summary">
                <h2>Order Summary</h2>
                <p>Order #<?php echo esc_html($order_id); ?></p>
                <p>Customer: <?php echo esc_html($customer_name); ?></p>
                <p class="amount">Total: <?php echo esc_html(number_format($amount, 2) . ' ' . $currency); ?></p>
            </div>
            
            <div class="payment-container">
                <h3>Complete Your Payment</h3>
                <p>Please select your preferred payment method below:</p>
                
                <div id="paypal-button-container"></div>
            </div>
            
            <div class="loading-indicator" id="loading">
                <div class="spinner"></div>
                <p>Processing your payment...</p>
            </div>
        </main>
        
        <footer>
            <p>&copy; <?php echo date('Y'); ?> KwetuPizza. All rights reserved.</p>
        </footer>
        
        <script src="<?php echo esc_url($script_src); ?>"></script>
        <script>
            paypal.Buttons({
                style: {
                    color: 'blue',
                    shape: 'rect',
                    layout: 'vertical'
                },
                createOrder: function() {
                    return "<?php echo esc_js($tx_ref); ?>";
                },
                onApprove: function(data, actions) {
                    // Show loading indicator
                    document.getElementById('loading').style.display = 'block';
                    
                    // Redirect to success URL with PayPal order ID
                    let successUrl = "<?php echo esc_js($return_url); ?>";
                    window.location.href = successUrl + "&paypal_order_id=" + data.orderID;
                },
                onCancel: function() {
                    // Redirect to cancel URL
                    window.location.href = "<?php echo esc_js($cancel_url); ?>";
                },
                onError: function(err) {
                    console.error('PayPal error:', err);
                    alert('There was an error processing your payment. Please try again.');
                }
            }).render('#paypal-button-container');
        </script>
    </body>
    </html>
    <?php
    exit;
}

/**
 * Register a page template for the PayPal checkout
 */
function kwetupizza_add_paypal_checkout_template($templates) {
    $templates['paypal-checkout.php'] = 'PayPal Checkout';
    return $templates;
}
add_filter('theme_page_templates', 'kwetupizza_add_paypal_checkout_template');

/**
 * Load the PayPal checkout template
 */
function kwetupizza_load_paypal_checkout_template($template) {
    if (is_page('paypal-checkout')) {
        return kwetupizza_render_paypal_checkout();
    }
    return $template;
}
add_filter('template_include', 'kwetupizza_load_paypal_checkout_template'); 