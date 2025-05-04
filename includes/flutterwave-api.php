<?php
/**
 * KwetuPizza Flutterwave API Integration
 * 
 * Contains functions for integrating with the Flutterwave payment gateway.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initialize a Flutterwave transaction
 * 
 * @param float $amount The amount to charge
 * @param string $tx_ref The transaction reference
 * @param array $customer Customer details (email, phone_number, name)
 * @param string $redirect_url URL to redirect after payment
 * @param string $currency The currency code (default: TZS)
 * @param array $meta Additional metadata for the transaction
 * @param string $payment_method Payment method (default: mobile_money_tanzania)
 * @return array|WP_Error Response from Flutterwave or error
 */
if (!function_exists('kwetupizza_initialize_flutterwave_transaction')) {
    function kwetupizza_initialize_flutterwave_transaction($amount, $tx_ref, $customer, $redirect_url, $currency = 'TZS', $meta = [], $payment_method = 'mobile_money_tanzania') {
        // Get the Flutterwave API key
        $flw_secret_key = get_option('kwetupizza_flw_secret_key');
        
        if (empty($flw_secret_key)) {
            return new WP_Error('missing_api_key', 'Flutterwave API key is not configured');
        }
        
        // Validate required parameters
        if (empty($amount) || empty($tx_ref) || empty($customer['email']) || empty($customer['phone_number'])) {
            return new WP_Error('missing_params', 'Missing required parameters for Flutterwave transaction');
        }
        
        // Prepare the API endpoint
        $api_url = 'https://api.flutterwave.com/v3/charges?type=' . $payment_method;
        
        // Prepare the payload
        $payload = [
            'tx_ref' => $tx_ref,
            'amount' => $amount,
            'currency' => $currency,
            'email' => $customer['email'],
            'phone_number' => $customer['phone_number'],
            'fullname' => isset($customer['name']) ? $customer['name'] : '',
            'redirect_url' => $redirect_url,
            'meta' => $meta
        ];
        
        // Add payment method specific details
        if ($payment_method === 'mobile_money_tanzania') {
            $payload['network'] = isset($meta['network']) ? $meta['network'] : 'MPESA';
        }
        
        // Log the transaction request
        kwetupizza_log('Initializing Flutterwave transaction: ' . json_encode($payload), 'info', 'flutterwave.log');
        
        // Make the API request
        $response = wp_remote_post($api_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $flw_secret_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($payload),
            'timeout' => 30
        ]);
        
        // Check for errors
        if (is_wp_error($response)) {
            kwetupizza_log('Flutterwave API Error: ' . $response->get_error_message(), 'error', 'flutterwave.log');
            return $response;
        }
        
        // Process the response
        $body = json_decode(wp_remote_retrieve_body($response), true);
        kwetupizza_log('Flutterwave API Response: ' . wp_remote_retrieve_body($response), 'info', 'flutterwave.log');
        
        return $body;
    }
}

/**
 * Verify a Flutterwave transaction
 * 
 * @param string $transaction_id The transaction ID from Flutterwave
 * @return array|WP_Error Transaction data or error
 */
if (!function_exists('kwetupizza_verify_flutterwave_transaction')) {
    function kwetupizza_verify_flutterwave_transaction($transaction_id) {
        // Get the Flutterwave API key
        $flw_secret_key = get_option('kwetupizza_flw_secret_key');
        
        if (empty($flw_secret_key)) {
            return new WP_Error('missing_api_key', 'Flutterwave API key is not configured');
        }
        
        // Validate the transaction ID
        if (empty($transaction_id)) {
            return new WP_Error('missing_transaction_id', 'Transaction ID is required');
        }
        
        // Prepare the API endpoint
        $api_url = 'https://api.flutterwave.com/v3/transactions/' . $transaction_id . '/verify';
        
        // Log the verification request
        kwetupizza_log('Verifying Flutterwave transaction: ' . $transaction_id, 'info', 'flutterwave.log');
        
        // Make the API request
        $response = wp_remote_get($api_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $flw_secret_key,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ]);
        
        // Check for errors
        if (is_wp_error($response)) {
            kwetupizza_log('Flutterwave Verification Error: ' . $response->get_error_message(), 'error', 'flutterwave.log');
            return $response;
        }
        
        // Process the response
        $body = json_decode(wp_remote_retrieve_body($response), true);
        kwetupizza_log('Flutterwave Verification Response: ' . wp_remote_retrieve_body($response), 'info', 'flutterwave.log');
        
        // Check if the transaction was successful
        if (isset($body['status']) && $body['status'] === 'success' && 
            isset($body['data']['status']) && $body['data']['status'] === 'successful') {
            return $body['data'];
        } else {
            $error_message = isset($body['message']) ? $body['message'] : 'Unknown error';
            kwetupizza_log('Flutterwave Verification Failed: ' . $error_message, 'error', 'flutterwave.log');
            return new WP_Error('verification_failed', $error_message);
        }
    }
}

/**
 * Process a mobile money payment via Flutterwave
 * 
 * @param string $phone The phone number to charge
 * @param float $amount The amount to charge
 * @param string $network The mobile money network (MPESA, TIGOPESA, etc.)
 * @param string $order_id The order ID
 * @param string $customer_name Customer's name
 * @param string $customer_email Customer's email
 * @return array Response data with status and message
 */
if (!function_exists('kwetupizza_process_mobile_money_payment')) {
    function kwetupizza_process_mobile_money_payment($phone, $amount, $network, $order_id, $customer_name, $customer_email) {
        // Sanitize phone number
        $phone = kwetupizza_sanitize_phone($phone);
        
        // Create a unique transaction reference
        $tx_ref = 'order-' . $order_id . '-' . time();
        
        // Prepare customer data
        $customer = [
            'email' => $customer_email,
            'phone_number' => $phone,
            'name' => $customer_name
        ];
        
        // Prepare metadata
        $meta = [
            'order_id' => $order_id,
            'network' => strtoupper($network)
        ];
        
        // Get the callback URL
        $redirect_url = kwetupizza_get_callback_url('flutterwave');
        
        // Initialize the transaction
        $result = kwetupizza_initialize_flutterwave_transaction(
            $amount,
            $tx_ref,
            $customer,
            $redirect_url,
            'TZS',
            $meta,
            'mobile_money_tanzania'
        );
        
        // Process the result
        if (is_wp_error($result)) {
            return [
                'success' => false,
                'message' => $result->get_error_message(),
                'tx_ref' => $tx_ref
            ];
        }
        
        if (isset($result['status']) && $result['status'] === 'success') {
            // Update the order with transaction reference
            kwetupizza_update_order_transaction($order_id, $tx_ref, $result['data']['id']);
            
            return [
                'success' => true,
                'message' => 'Payment request sent successfully',
                'tx_ref' => $tx_ref,
                'transaction_id' => $result['data']['id']
            ];
        } else {
            $error_message = isset($result['message']) ? $result['message'] : 'Unknown error';
            
            return [
                'success' => false,
                'message' => $error_message,
                'tx_ref' => $tx_ref
            ];
        }
    }
}

/**
 * Update order with transaction information
 * 
 * @param int $order_id The order ID
 * @param string $tx_ref The transaction reference
 * @param string $transaction_id The Flutterwave transaction ID
 * @return bool Success or failure
 */
if (!function_exists('kwetupizza_update_order_transaction')) {
    function kwetupizza_update_order_transaction($order_id, $tx_ref, $transaction_id) {
        global $wpdb;
        $transactions_table = $wpdb->prefix . 'kwetupizza_transactions';
        
        $result = $wpdb->update(
            $transactions_table,
            [
                'transaction_reference' => $transaction_id,
                'tx_ref' => $tx_ref,
                'updated_at' => current_time('mysql')
            ],
            ['order_id' => $order_id]
        );
        
        if ($result !== false) {
            kwetupizza_log("Updated transaction reference for order #$order_id: $tx_ref, ID: $transaction_id", 'info', 'payment.log');
            return true;
        } else {
            kwetupizza_log("Failed to update transaction reference for order #$order_id: " . $wpdb->last_error, 'error', 'payment.log');
            return false;
        }
    }
}

/**
 * Handle Flutterwave webhook data
 * 
 * @param array $webhook_data The webhook data from Flutterwave
 * @return array Response with status and message
 */
if (!function_exists('kwetupizza_handle_flutterwave_webhook_data')) {
    function kwetupizza_handle_flutterwave_webhook_data($webhook_data) {
        // Log the webhook data
        kwetupizza_log('Flutterwave webhook received: ' . json_encode($webhook_data), 'info', 'flutterwave-webhook.log');
        
        // Check if it's a transaction event
        if (!isset($webhook_data['event']) || $webhook_data['event'] !== 'charge.completed') {
            return [
                'success' => false,
                'message' => 'Unsupported webhook event'
            ];
        }
        
        // Extract transaction data
        $status = isset($webhook_data['data']['status']) ? $webhook_data['data']['status'] : '';
        $transaction_id = isset($webhook_data['data']['id']) ? $webhook_data['data']['id'] : '';
        $tx_ref = isset($webhook_data['data']['tx_ref']) ? $webhook_data['data']['tx_ref'] : '';
        
        // Log the transaction data
        kwetupizza_log("Processing payment with status: $status, tx_ref: $tx_ref", 'info', 'flutterwave-webhook.log');
        
        // Handle successful transaction
        if ($status === 'successful' && !empty($transaction_id)) {
            // Verify the transaction
            $verification_result = kwetupizza_verify_flutterwave_transaction($transaction_id);
            
            if (!is_wp_error($verification_result)) {
                // Extract order ID from tx_ref (format: order-ID-TIMESTAMP)
                preg_match('/order-(\d+)-/', $tx_ref, $matches);
                $order_id = isset($matches[1]) ? (int)$matches[1] : 0;
                
                if ($order_id > 0) {
                    // Process the successful payment
                    $result = kwetupizza_process_successful_payment($verification_result, $order_id);
                    
                    if ($result) {
                        return [
                            'success' => true,
                            'message' => 'Payment processed successfully',
                            'order_id' => $order_id
                        ];
                    } else {
                        return [
                            'success' => false,
                            'message' => 'Failed to process payment',
                            'order_id' => $order_id
                        ];
                    }
                } else {
                    return [
                        'success' => false,
                        'message' => 'Invalid order ID in transaction reference'
                    ];
                }
            } else {
                return [
                    'success' => false,
                    'message' => 'Payment verification failed: ' . $verification_result->get_error_message()
                ];
            }
        } 
        // Handle failed transaction
        elseif ($status === 'failed') {
            // Extract order ID from tx_ref
            preg_match('/order-(\d+)-/', $tx_ref, $matches);
            $order_id = isset($matches[1]) ? (int)$matches[1] : 0;
            
            if ($order_id > 0) {
                // Extract failure reason if available
                $failure_reason = '';
                if (isset($webhook_data['data']['processor_response'])) {
                    $failure_reason = $webhook_data['data']['processor_response'];
                } elseif (isset($webhook_data['data']['gateway_response'])) {
                    $failure_reason = $webhook_data['data']['gateway_response'];
                }
                
                // Handle the failed payment
                $result = kwetupizza_handle_failed_payment($tx_ref, $failure_reason);
                
                if ($result) {
                    return [
                        'success' => true,
                        'message' => 'Failed payment handled successfully',
                        'order_id' => $order_id
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => 'Error handling failed payment',
                        'order_id' => $order_id
                    ];
                }
            } else {
                return [
                    'success' => false,
                    'message' => 'Invalid order ID in transaction reference'
                ];
            }
        }
        else {
            return [
                'success' => false,
                'message' => 'Unsupported transaction status: ' . $status
            ];
        }
    }
}

/**
 * Process a successful payment
 * 
 * @param array $verification_data The verification data from Flutterwave
 * @param int $order_id The order ID
 * @return bool Success or failure
 */
if (!function_exists('kwetupizza_process_successful_flutterwave_payment')) {
    function kwetupizza_process_successful_flutterwave_payment($verification_data, $order_id) {
        global $wpdb;
        $transactions_table = $wpdb->prefix . 'kwetupizza_transactions';
        $orders_table = $wpdb->prefix . 'kwetupizza_orders';
        
        // Check if order exists
        $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM $orders_table WHERE id = %d", $order_id));
        
        if (!$order) {
            kwetupizza_log("Order not found for ID: $order_id", 'error', 'flutterwave.log');
            return false;
        }
        
        // Update transaction status
        $transaction_updated = $wpdb->update(
            $transactions_table,
            [
                'payment_status' => 'completed',
                'transaction_reference' => $verification_data['id'],
                'updated_at' => current_time('mysql')
            ],
            ['order_id' => $order_id]
        );
        
        // Update order status
        $order_updated = $wpdb->update(
            $orders_table,
            [
                'status' => 'processing',
                'updated_at' => current_time('mysql')
            ],
            ['id' => $order_id]
        );
        
        if ($transaction_updated !== false && $order_updated !== false) {
            // Add timeline event
            kwetupizza_add_order_timeline_event($order_id, 'payment_confirmed', 'Payment confirmed via Flutterwave');
            
            // Add loyalty points
            if (function_exists('kwetupizza_add_loyalty_points')) {
                kwetupizza_add_loyalty_points($order_id);
            }
            
            // Notify admin
            if (function_exists('kwetupizza_notify_admin')) {
                kwetupizza_notify_admin($order_id, true);
            }
            
            // Notify customer
            if (function_exists('kwetupizza_notify_customer')) {
                kwetupizza_notify_customer($order_id, 'payment_confirmed');
            }
            
            kwetupizza_log("Payment processed successfully for order #$order_id", 'info', 'flutterwave.log');
            return true;
        } else {
            kwetupizza_log("Failed to update order or transaction record for order #$order_id", 'error', 'flutterwave.log');
            return false;
        }
    }
} 