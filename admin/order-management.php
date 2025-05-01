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
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select><br>
                <button type="submit" class="button button-primary">Save Changes</button>
                <button type="button" class="button" id="cancel-edit">Cancel</button>
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
                    data: formData + '&action=kwetupizza_update_order',
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

    $orders_table = $wpdb->prefix . 'kwetupizza_orders';

    $updated = $wpdb->update(
        $orders_table,
        array(
            'customer_name' => $customer_name,
            'customer_phone' => $customer_phone,
            'delivery_address' => $delivery_address,
            'total' => $total,
            'status' => $status,
        ),
        array('id' => $order_id)
    );

    if ($updated !== false) {
        wp_send_json_success();
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

    $deleted = $wpdb->delete($orders_table, array('id' => $order_id));

    if ($deleted !== false) {
        wp_send_json_success();
    } else {
        wp_send_json_error('Failed to delete order.');
    }
}
add_action('wp_ajax_kwetupizza_delete_order', 'kwetupizza_delete_order');
?>
