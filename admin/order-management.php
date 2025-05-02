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

    // Enqueue Bootstrap
    wp_enqueue_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css');
    wp_enqueue_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js', array('jquery'), '5.2.3', true);
    
    // Custom styles
    echo '<style>
        .order-card {
            transition: all 0.3s ease;
            border-left: 4px solid #ccc;
        }
        .order-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .order-card.pending { border-left-color: #ffc107; }
        .order-card.processing { border-left-color: #17a2b8; }
        .order-card.payment_confirmed { border-left-color: #6f42c1; }
        .order-card.dispatched { border-left-color: #fd7e14; }
        .order-card.delivered { border-left-color: #20c997; }
        .order-card.completed { border-left-color: #28a745; }
        .order-card.cancelled { border-left-color: #dc3545; }
        
        .badge.pending { background-color: #ffc107; color: #000; }
        .badge.processing { background-color: #17a2b8; }
        .badge.payment_confirmed { background-color: #6f42c1; }
        .badge.dispatched { background-color: #fd7e14; }
        .badge.delivered { background-color: #20c997; }
        .badge.completed { background-color: #28a745; }
        .badge.cancelled { background-color: #dc3545; }
        
        .modal-backdrop {
            z-index: 1040;
        }
        .modal {
            z-index: 1050;
        }
    </style>';

    ?>
    <div class="wrap pt-4">
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col">
                    <h1 class="display-5 fw-bold">Order Management</h1>
                    <p class="text-muted">Manage customer orders, dispatch deliveries, and track order statuses</p>
                </div>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Recent Orders</h5>
                            <div>
                                <a href="#" class="btn btn-sm btn-outline-primary refresh-orders">
                                    <i class="dashicons dashicons-update"></i> Refresh
                                </a>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Date</th>
                                            <th>Customer</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($orders)): ?>
                                            <?php foreach ($orders as $order): ?>
                                                <tr>
                                                    <td>#<?php echo esc_html($order->id); ?></td>
                                                    <td><?php echo esc_html(date('M d, H:i', strtotime($order->order_date))); ?></td>
                                                    <td>
                                                        <div><?php echo esc_html($order->customer_name); ?></div>
                                                        <small class="text-muted"><?php echo esc_html($order->customer_phone); ?></small>
                                                    </td>
                                                    <td><?php echo esc_html(number_format($order->total, 2)); ?> Tzs</td>
                                                    <td>
                                                        <span class="badge <?php echo esc_attr($order->status); ?>">
                                                            <?php echo esc_html(ucfirst(str_replace('_', ' ', $order->status))); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <button class="btn btn-outline-primary view-order" data-id="<?php echo esc_attr($order->id); ?>">
                                                                <i class="dashicons dashicons-visibility"></i>
                                                            </button>
                                                            <button class="btn btn-outline-secondary edit-order" data-id="<?php echo esc_attr($order->id); ?>">
                                                                <i class="dashicons dashicons-edit"></i>
                                                            </button>
                                                            <?php if ($order->status == 'processing' || $order->status == 'payment_confirmed'): ?>
                                                                <button class="btn btn-outline-warning dispatch-order" data-id="<?php echo esc_attr($order->id); ?>">
                                                                    <i class="dashicons dashicons-airplane"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                            <?php if ($order->status == 'dispatched'): ?>
                                                                <button class="btn btn-outline-success mark-delivered" data-id="<?php echo esc_attr($order->id); ?>">
                                                                    <i class="dashicons dashicons-yes-alt"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                            <button class="btn btn-outline-danger delete-order" data-id="<?php echo esc_attr($order->id); ?>">
                                                                <i class="dashicons dashicons-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-4">
                                                    <div class="text-muted">
                                                        <i class="dashicons dashicons-clipboard"></i><br>
                                                        No orders found
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Order Statistics</h5>
                        </div>
                        <div class="card-body">
                            <?php
                                // Get order statistics
                                $total_orders = count($orders);
                                $pending_orders = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $orders_table WHERE status = %s", 'pending'));
                                $delivered_orders = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $orders_table WHERE status = %s", 'delivered'));
                                $dispatched_orders = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $orders_table WHERE status = %s", 'dispatched'));
                            ?>
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="border rounded px-3 py-2 h-100">
                                        <div class="text-muted small mb-1">Total Orders</div>
                                        <div class="h4"><?php echo $total_orders; ?></div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="border rounded px-3 py-2 text-warning h-100">
                                        <div class="text-muted small mb-1">Pending</div>
                                        <div class="h4"><?php echo $pending_orders; ?></div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="border rounded px-3 py-2 text-primary h-100">
                                        <div class="text-muted small mb-1">Dispatched</div>
                                        <div class="h4"><?php echo $dispatched_orders; ?></div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="border rounded px-3 py-2 text-success h-100">
                                        <div class="text-muted small mb-1">Delivered</div>
                                        <div class="h4"><?php echo $delivered_orders; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button class="btn btn-outline-primary">
                                    <i class="dashicons dashicons-admin-customizer"></i> Order Settings
                                </button>
                                <button class="btn btn-outline-secondary">
                                    <i class="dashicons dashicons-backup"></i> Export Orders
                                </button>
                                <button class="btn btn-outline-info">
                                    <i class="dashicons dashicons-chart-bar"></i> View Reports
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- View Order Modal -->
        <div class="modal fade" id="view-order-modal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Order Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="container-fluid">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Customer Information</h6>
                                    <p id="view_customer_info"></p>
                                </div>
                                <div class="col-md-6">
                                    <h6>Order Information</h6>
                                    <p id="view_order_info"></p>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-12">
                                    <h6>Delivery Address</h6>
                                    <p id="view_delivery_address"></p>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-12">
                                    <h6>Order Items</h6>
                                    <div id="view_order_items"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Order Modal -->
        <div class="modal fade" id="edit-order-modal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Order</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="edit-order-form">
                            <input type="hidden" name="order_id" id="order_id">
                            <div class="mb-3">
                                <label for="edit_customer_name" class="form-label">Customer Name</label>
                                <input type="text" class="form-control" name="customer_name" id="edit_customer_name">
                            </div>
                            <div class="mb-3">
                                <label for="edit_customer_phone" class="form-label">Phone</label>
                                <input type="text" class="form-control" name="customer_phone" id="edit_customer_phone">
                            </div>
                            <div class="mb-3">
                                <label for="edit_delivery_address" class="form-label">Address</label>
                                <textarea class="form-control" name="delivery_address" id="edit_delivery_address"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="edit_total" class="form-label">Total (Tzs)</label>
                                <input type="text" class="form-control" name="total" id="edit_total">
                            </div>
                            <div class="mb-3">
                                <label for="edit_status" class="form-label">Status</label>
                                <select class="form-select" name="status" id="edit_status">
                                    <option value="pending">Pending</option>
                                    <option value="processing">Processing</option>
                                    <option value="payment_confirmed">Payment Confirmed</option>
                                    <option value="dispatched">Dispatched</option>
                                    <option value="delivered">Delivered</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" form="edit-order-form" class="btn btn-primary">Save Changes</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dispatch Order Modal -->
        <div class="modal fade" id="dispatch-order-modal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Dispatch Order</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="dispatch-order-form">
                            <input type="hidden" name="order_id" id="dispatch_order_id">
                            <div class="mb-3">
                                <label for="estimated_delivery_time" class="form-label">Estimated Delivery Time (minutes)</label>
                                <input type="range" class="form-range" id="estimated_delivery_time" name="estimated_delivery_time" min="5" max="120" value="30">
                                <div class="text-center mt-2">
                                    <span class="badge bg-secondary" id="delivery_time_display">30 minutes</span>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" form="dispatch-order-form" class="btn btn-primary">
                            <i class="dashicons dashicons-airplane"></i> Dispatch Order & Notify Customer
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        jQuery(document).ready(function($) {
            // Update delivery time display
            $('#estimated_delivery_time').on('input', function() {
                $('#delivery_time_display').text($(this).val() + ' minutes');
            });

            // View Order button action
            $('.view-order').click(function() {
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
                            // Set customer info
                            $('#view_customer_info').html(`
                                <strong>Name:</strong> ${order.customer_name}<br>
                                <strong>Phone:</strong> ${order.customer_phone}
                            `);
                            
                            // Set order info
                            $('#view_order_info').html(`
                                <strong>Order ID:</strong> #${order.id}<br>
                                <strong>Date:</strong> ${order.order_date}<br>
                                <strong>Status:</strong> <span class="badge ${order.status}">${order.status}</span><br>
                                <strong>Total:</strong> ${order.total} Tzs
                            `);
                            
                            // Set delivery address
                            $('#view_delivery_address').text(order.delivery_address);
                            
                            // Open modal
                            var viewModal = new bootstrap.Modal(document.getElementById('view-order-modal'));
                            viewModal.show();
                        }
                    }
                });
            });

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

                            // Open modal
                            var editModal = new bootstrap.Modal(document.getElementById('edit-order-modal'));
                            editModal.show();
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
                            // Close modal
                            var editModal = bootstrap.Modal.getInstance(document.getElementById('edit-order-modal'));
                            editModal.hide();
                            
                            // Show success message
                            alert('Order updated successfully!');
                            
                            // Reload page
                            location.reload();
                        } else {
                            alert('Failed to update order.');
                        }
                    }
                });
            });

            // Dispatch Order button action
            $('.dispatch-order').click(function() {
                var orderId = $(this).data('id');
                $('#dispatch_order_id').val(orderId);
                
                // Open modal
                var dispatchModal = new bootstrap.Modal(document.getElementById('dispatch-order-modal'));
                dispatchModal.show();
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
                            // Close modal
                            var dispatchModal = bootstrap.Modal.getInstance(document.getElementById('dispatch-order-modal'));
                            dispatchModal.hide();
                            
                            // Show success message
                            alert('Order dispatched successfully!');
                            
                            // Reload page
                            location.reload();
                        } else {
                            alert('Failed to dispatch order.');
                        }
                    }
                });
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
                                // Show success message
                                alert('Order marked as delivered!');
                                
                                // Reload page
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
                                // Show success message
                                alert('Order deleted successfully!');
                                
                                // Reload page
                                location.reload();
                            } else {
                                alert('Failed to delete order.');
                            }
                        }
                    });
                }
            });
            
            // Refresh orders button
            $('.refresh-orders').click(function(e) {
                e.preventDefault();
                location.reload();
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
