<?php
// Function to render the order management page
function kwetupizza_render_order_management() {
    global $wpdb;
    $orders_table = $wpdb->prefix . 'kwetupizza_orders';

    // Fetch all orders
    $orders = $wpdb->get_results("SELECT * FROM $orders_table ORDER BY order_date DESC");

    if ($orders === false) {
        error_log("Error fetching orders: " . $wpdb->last_error);
    }

    ?>
    <div class="wrap">
        <h1>Order Management</h1>
        <table class="wp-list-table widefat fixed striped table-view-list posts">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Order Date</th>
                    <th>Customer Name</th>
                    <th>Phone</th>
                    <th>Address</th>
                    <th>Total (Tzs)</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($orders)): ?>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?php echo esc_html($order->id); ?></td>
                            <td><?php echo esc_html($order->order_date); ?></td>
                            <td><?php echo esc_html($order->customer_name); ?></td>
                            <td><?php echo esc_html($order->customer_phone); ?></td>
                            <td><?php echo esc_html($order->delivery_address); ?></td>
                            <td><?php echo esc_html(number_format($order->total, 2)); ?></td>
                            <td><?php echo esc_html($order->status); ?></td>
                            <td>
                                <button class="button edit-order" data-id="<?php echo esc_attr($order->id); ?>">Edit</button>
                                <?php if ($order->status == 'processing' || $order->status == 'payment_confirmed'): ?>
                                    <button class="button dispatch-order" data-id="<?php echo esc_attr($order->id); ?>">Dispatch</button>
                                <?php endif; ?>
                                <?php if ($order->status == 'dispatched'): ?>
                                    <button class="button mark-delivered" data-id="<?php echo esc_attr($order->id); ?>">Mark Delivered</button>
                                <?php endif; ?>
                                <button class="button delete-order" data-id="<?php echo esc_attr($order->id); ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8">No orders found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Edit Order Modal -->
        <div id="edit-order-modal" style="display: none;">
            <h2>Edit Order</h2>
            <form id="edit-order-form">
                <input type="hidden" name="order_id" id="order_id">
                <label for="edit_customer_name">Customer Name:</label>
                <input type="text" name="customer_name" id="edit_customer_name"><br>
                <label for="edit_customer_phone">Phone:</label>
                <input type="text" name="customer_phone" id="edit_customer_phone"><br>
                <label for="edit_delivery_address">Address:</label>
                <input type="text" name="delivery_address" id="edit_delivery_address"><br>
                <label for="edit_total">Total (Tzs):</label>
                <input type="text" name="total" id="edit_total"><br>
                <label for="edit_status">Status:</label>
                <select name="status" id="edit_status">
                    <option value="pending">Pending</option>
                    <option value="processing">Processing</option>
                    <option value="payment_confirmed">Payment Confirmed</option>
                    <option value="dispatched">Dispatched</option>
                    <option value="delivered">Delivered</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select><br>
                <button type="submit" class="button button-primary">Save Changes</button>
                <button type="button" class="button" id="cancel-edit">Cancel</button>
            </form>
        </div>

        <!-- Dispatch Order Modal -->
        <div id="dispatch-order-modal" style="display: none;">
            <h2>Dispatch Order</h2>
            <form id="dispatch-order-form">
                <input type="hidden" name="order_id" id="dispatch_order_id">
                <label for="estimated_delivery_time">Estimated Delivery Time (minutes):</label>
                <input type="number" name="estimated_delivery_time" id="estimated_delivery_time" min="5" max="120" value="30"><br>
                <button type="submit" class="button button-primary">Dispatch Order & Notify Customer</button>
                <button type="button" class="button" id="cancel-dispatch">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        jQuery(document).ready(function($) {
            // Edit Order button action
            $('.edit-order').click(function() {
                var orderId = $(this).data('id');
                // Fetch order data via Ajax
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'kwetupizza_get_order',
                        order_id: orderId,
                        _ajax_nonce: '<?php echo wp_create_nonce("kwetupizza_order_nonce"); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var order = response.data;
                            $('#order_id').val(order.id);
                            $('#edit_customer_name').val(order.customer_name);
                            $('#edit_customer_phone').val(order.customer_phone);
                            $('#edit_delivery_address').val(order.delivery_address);
                            $('#edit_total').val(order.total);
                            $('#edit_status').val(order.status);
                            $('#edit-order-modal').show(); // Show the modal
                        }
                    }
                });
            });

            // Submit edited order
            $('#edit-order-form').submit(function(e) {
                e.preventDefault();
                var formData = $(this).serialize();
                // Save edited order via Ajax
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: formData + '&action=kwetupizza_update_order&send_notification=true',
                    success: function(response) {
                        if (response.success) {
                            location.reload(); // Reload page to show updated data
                        } else {
                            alert('Failed to update order.');
                        }
                    }
                });
            });

            // Cancel Edit Order
            $('#cancel-edit').click(function() {
                $('#edit-order-modal').hide(); // Hide the modal
            });

            // Dispatch Order button action
            $('.dispatch-order').click(function() {
                var orderId = $(this).data('id');
                $('#dispatch_order_id').val(orderId);
                $('#dispatch-order-modal').show();
            });

            // Submit dispatch order form
            $('#dispatch-order-form').submit(function(e) {
                e.preventDefault();
                var orderId = $('#dispatch_order_id').val();
                var estimatedTime = $('#estimated_delivery_time').val();
                
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'kwetupizza_dispatch_order',
                        order_id: orderId,
                        estimated_delivery_time: estimatedTime,
                        _ajax_nonce: '<?php echo wp_create_nonce("kwetupizza_order_nonce"); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#dispatch-order-modal').hide();
                            location.reload();
                        } else {
                            alert('Failed to dispatch order.');
                        }
                    }
                });
            });

            // Cancel Dispatch
            $('#cancel-dispatch').click(function() {
                $('#dispatch-order-modal').hide();
            });

            // Mark as Delivered button action
            $('.mark-delivered').click(function() {
                var orderId = $(this).data('id');
                if (confirm('Mark this order as delivered? This will notify the customer.')) {
                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'kwetupizza_mark_delivered',
                            order_id: orderId,
                            _ajax_nonce: '<?php echo wp_create_nonce("kwetupizza_order_nonce"); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert('Failed to mark order as delivered.');
                            }
                        }
                    });
                }
            });

            // Delete Order button action
            $('.delete-order').click(function() {
                var orderId = $(this).data('id');
                if (confirm('Are you sure you want to delete this order?')) {
                    // Delete order via Ajax
                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'kwetupizza_delete_order',
                            order_id: orderId,
                            _ajax_nonce: '<?php echo wp_create_nonce("kwetupizza_order_nonce"); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                location.reload(); // Reload page to remove deleted order
                            } else {
                                alert('Failed to delete order.');
                            }
                        }
                    });
                }
            });
        });
    </script>
    <?php
}

// Ajax handler to get order details
function kwetupizza_get_order() {
    check_ajax_referer('kwetupizza_order_nonce', '_ajax_nonce');

    global $wpdb;
    $order_id = intval($_POST['order_id']);
    $orders_table = $wpdb->prefix . 'kwetupizza_orders';

    $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM $orders_table WHERE id = %d", $order_id));

    if ($order) {
        wp_send_json_success($order);
    } else {
        wp_send_json_error('Order not found.');
    }
}
add_action('wp_ajax_kwetupizza_get_order', 'kwetupizza_get_order');

// Ajax handler to update order
function kwetupizza_update_order() {
    check_ajax_referer('kwetupizza_order_nonce', '_ajax_nonce');

    global $wpdb;
    $order_id = intval($_POST['order_id']);
    $customer_name = sanitize_text_field($_POST['customer_name']);
    $customer_phone = sanitize_text_field($_POST['customer_phone']);
    $delivery_address = sanitize_text_field($_POST['delivery_address']);
    $total = floatval($_POST['total']);
    $status = sanitize_text_field($_POST['status']);
    $send_notification = isset($_POST['send_notification']) && $_POST['send_notification'] === 'true';

    $orders_table = $wpdb->prefix . 'kwetupizza_orders';
    
    $previous_status = $wpdb->get_var($wpdb->prepare("SELECT status FROM $orders_table WHERE id = %d", $order_id));

    $updated = $wpdb->update(
        $orders_table,
        array(
            'customer_name' => $customer_name,
            'customer_phone' => $customer_phone,
            'delivery_address' => $delivery_address,
            'total' => $total,
            'status' => $status,
            'updated_at' => current_time('mysql')
        ),
        array('id' => $order_id)
    );

    if ($updated !== false) {
        // Send notification if status has changed and notifications are enabled
        if ($send_notification && $previous_status !== $status) {
            kwetupizza_notify_customer($order_id, $status);
        }
        
        wp_send_json_success('Order updated successfully.');
    } else {
        wp_send_json_error('Failed to update order.');
    }
}
add_action('wp_ajax_kwetupizza_update_order', 'kwetupizza_update_order');

// Ajax handler to delete order
function kwetupizza_delete_order() {
    check_ajax_referer('kwetupizza_order_nonce', '_ajax_nonce');

    global $wpdb;
    $order_id = intval($_POST['order_id']);
    $orders_table = $wpdb->prefix . 'kwetupizza_orders';
    $order_items_table = $wpdb->prefix . 'kwetupizza_order_items';

    // First delete order items
    $wpdb->delete($order_items_table, array('order_id' => $order_id));
    
    // Then delete order
    $deleted = $wpdb->delete($orders_table, array('id' => $order_id));

    if ($deleted) {
        wp_send_json_success('Order deleted successfully.');
    } else {
        wp_send_json_error('Failed to delete order.');
    }
}
add_action('wp_ajax_kwetupizza_delete_order', 'kwetupizza_delete_order');

// Ajax handler to dispatch order
function kwetupizza_dispatch_order() {
    check_ajax_referer('kwetupizza_order_nonce', '_ajax_nonce');
    
    $order_id = intval($_POST['order_id']);
    $estimated_time = intval($_POST['estimated_delivery_time']);
    
    if (function_exists('kwetupizza_notify_order_dispatched')) {
        $result = kwetupizza_notify_order_dispatched($order_id, $estimated_time);
        if ($result) {
            wp_send_json_success('Order dispatched successfully.');
        } else {
            wp_send_json_error('Failed to dispatch order.');
        }
    } else {
        wp_send_json_error('Notification function not available.');
    }
}
add_action('wp_ajax_kwetupizza_dispatch_order', 'kwetupizza_dispatch_order');

// Ajax handler to mark order as delivered
function kwetupizza_mark_delivered() {
    check_ajax_referer('kwetupizza_order_nonce', '_ajax_nonce');
    
    $order_id = intval($_POST['order_id']);
    
    if (function_exists('kwetupizza_notify_order_delivered')) {
        $result = kwetupizza_notify_order_delivered($order_id);
        if ($result) {
            wp_send_json_success('Order marked as delivered.');
        } else {
            wp_send_json_error('Failed to mark order as delivered.');
        }
    } else {
        wp_send_json_error('Notification function not available.');
    }
}
add_action('wp_ajax_kwetupizza_mark_delivered', 'kwetupizza_mark_delivered');
?>
