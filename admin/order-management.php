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
        /* Order Card Styles */
        .order-card {
            transition: all 0.3s ease;
            border-left: 4px solid #ccc;
        }
        .order-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        /* Status Colors */
        .order-card.pending, .badge.pending { border-left-color: #ffc107; background-color: #ffc107; color: #000; }
        .order-card.processing, .badge.processing { border-left-color: #17a2b8; background-color: #17a2b8; color: #fff; }
        .order-card.payment_confirmed, .badge.payment_confirmed { border-left-color: #6f42c1; background-color: #6f42c1; color: #fff; }
        .order-card.dispatched, .badge.dispatched { border-left-color: #fd7e14; background-color: #fd7e14; color: #fff; }
        .order-card.delivered, .badge.delivered { border-left-color: #20c997; background-color: #20c997; color: #fff; }
        .order-card.completed, .badge.completed { border-left-color: #28a745; background-color: #28a745; color: #fff; }
        .order-card.cancelled, .badge.cancelled { border-left-color: #dc3545; background-color: #dc3545; color: #fff; }
        
        /* Table Optimizations */
        .table-responsive {
            overflow-x: visible;
        }
        
        .table th, .table td {
            padding: 0.5rem;
            vertical-align: middle;
        }
        
        /* Compact Text */
        .compact-text {
            font-size: 0.875rem;
            line-height: 1.3;
        }
        
        .customer-info {
            max-width: 180px;
        }
        
        /* Action Dropdown */
        .action-dropdown .dropdown-menu {
            min-width: 200px;
        }
        
        /* Badge Styling */
        .badge {
            font-size: 0.75rem;
            padding: 0.35em 0.65em;
            font-weight: 500;
        }
        
        /* Modal Z-Index */
        .modal-backdrop {
            z-index: 1040;
        }
        .modal {
            z-index: 1050;
        }
        
        /* Responsive adjustments */
        @media (max-width: 992px) {
            .order-table th:nth-child(2), .order-table td:nth-child(2) {
                display: none;
            }
        }
        
        @media (max-width: 1000px) {
            .col-md-9, .col-md-3 {
                width: 100%;
            }
            
            .card-stats {
                margin-top: 1rem;
            }
        }
        
        /* Set Recent Orders card width to 900px */
        .col-md-9 {
            width: 900px;
            max-width: 100%;
            flex: 0 0 auto;
        }
        
        /* Button sizing for quick actions */
        .action-buttons .btn {
            width: 30px;
            height: 30px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .action-buttons .dashicons {
            width: 18px;
            height: 18px;
            font-size: 18px;
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
                <div class="col-md-9">
                    <div class="card shadow-sm" style="width: 900px; max-width: 100%;">
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
                                <table class="table table-hover mb-0 order-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="8%">ID</th>
                                            <th width="15%">Date</th>
                                            <th width="27%">Customer</th>
                                            <th width="15%">Amount</th>
                                            <th width="15%">Status</th>
                                            <th width="20%">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($orders)): ?>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                                                    <td class="compact-text">#<?php echo esc_html($order->id); ?></td>
                                                    <td class="compact-text"><?php echo esc_html(date('M d, H:i', strtotime($order->order_date))); ?></td>
                                                    <td class="customer-info compact-text">
                                                        <div><strong><?php echo esc_html($order->customer_name); ?></strong></div>
                                                        <small class="text-muted"><?php echo esc_html($order->customer_phone); ?></small>
                                                    </td>
                                                    <td class="compact-text"><?php echo esc_html(number_format($order->total, 2)); ?> Tzs</td>
                                                    <td>
                                                        <span class="badge <?php echo esc_attr($order->status); ?>">
                                                            <?php echo esc_html(ucfirst(str_replace('_', ' ', $order->status))); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="dropdown action-dropdown">
                                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                                <i class="dashicons dashicons-admin-tools"></i> Actions
                                                            </button>
                                                            <ul class="dropdown-menu dropdown-menu-end">
                                                                <!-- Quick access buttons -->
                                                                <li class="px-2 py-1 d-flex justify-content-between action-buttons">
                                                                    <button class="btn btn-sm btn-outline-primary me-1 view-order" data-id="<?php echo esc_attr($order->id); ?>" title="View Details">
                                                                        <i class="dashicons dashicons-visibility"></i>
                                                                    </button>
                                                                    <button class="btn btn-sm btn-outline-secondary me-1 edit-order" data-id="<?php echo esc_attr($order->id); ?>" title="Edit Order">
                                                                        <i class="dashicons dashicons-edit"></i>
                                                                    </button>
                                                                    <button class="btn btn-sm btn-outline-warning me-1 dispatch-order" data-id="<?php echo esc_attr($order->id); ?>" <?php echo ($order->status != 'pending' && $order->status != 'processing' && $order->status != 'payment_confirmed') ? 'disabled' : ''; ?> title="Dispatch Order">
                                                                        <i class="dashicons dashicons-airplane"></i>
                                                                    </button>
                                                                    <button class="btn btn-sm btn-outline-success me-1 mark-delivered" data-id="<?php echo esc_attr($order->id); ?>" <?php echo ($order->status != 'dispatched') ? 'disabled' : ''; ?> title="Mark Delivered">
                                                                        <i class="dashicons dashicons-yes-alt"></i>
                                                                    </button>
                                                                </li>
                                                                <li><hr class="dropdown-divider"></li>
                                                                <!-- Detailed menu items -->
                                                                <li>
                                                                    <button class="dropdown-item view-order" data-id="<?php echo esc_attr($order->id); ?>">
                                                                        <i class="dashicons dashicons-visibility"></i> View Details
                                                                    </button>
                                                                </li>
                                                                <li>
                                                                    <button class="dropdown-item edit-order" data-id="<?php echo esc_attr($order->id); ?>">
                                                                        <i class="dashicons dashicons-edit"></i> Edit Order
                                                                    </button>
                                                                </li>
                                                                <li>
                                                                    <button class="dropdown-item dispatch-order" data-id="<?php echo esc_attr($order->id); ?>" <?php echo ($order->status != 'pending' && $order->status != 'processing' && $order->status != 'payment_confirmed') ? 'disabled' : ''; ?>>
                                                                        <i class="dashicons dashicons-airplane"></i> Dispatch
                                                                    </button>
                                                                </li>
                                                                <li>
                                                                    <button class="dropdown-item mark-delivered" data-id="<?php echo esc_attr($order->id); ?>" <?php echo ($order->status != 'dispatched') ? 'disabled' : ''; ?>>
                                                                        <i class="dashicons dashicons-yes-alt"></i> Mark Delivered
                                                                    </button>
                                                                </li>
                                                                <li><hr class="dropdown-divider"></li>
                                                                <li>
                                                                    <button class="dropdown-item text-danger delete-order" data-id="<?php echo esc_attr($order->id); ?>">
                                                                        <i class="dashicons dashicons-trash"></i> Delete
                                                                    </button>
                                                                </li>
                                                            </ul>
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
                
                <div class="col-md-3">
                    <div class="card shadow-sm mb-4 card-stats">
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
                                    <div class="card mb-3">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0">Customer Information</h6>
                                        </div>
                                        <div class="card-body" id="view_customer_info">
                                            <!-- Customer info will be populated here -->
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card mb-3">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0">Order Information</h6>
                                        </div>
                                        <div class="card-body" id="view_order_info">
                                            <!-- Order info will be populated here -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <div class="card mb-3">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0">Delivery Address</h6>
                                        </div>
                                        <div class="card-body" id="view_delivery_address">
                                            <!-- Delivery address will be populated here -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <div class="card">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0">Order Items</h6>
                                        </div>
                                        <div class="card-body p-0">
                                            <div class="table-responsive">
                                                <table class="table table-sm mb-0" id="view_order_items_table">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Item</th>
                                                            <th>Price</th>
                                                            <th>Qty</th>
                                                            <th class="text-end">Total</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="view_order_items_body">
                                                        <!-- Order items will be populated here -->
                                                    </tbody>
                                                    <tfoot class="table-light" id="view_order_items_footer">
                                                        <!-- Total will be populated here -->
                                                    </tfoot>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <div class="d-flex justify-content-between w-100">
                            <div>
                                <button type="button" class="btn btn-outline-primary" id="view_edit_btn">
                                    <i class="dashicons dashicons-edit"></i> Edit
                                </button>
                                <button type="button" class="btn btn-outline-warning" id="view_dispatch_btn">
                                    <i class="dashicons dashicons-airplane"></i> Dispatch
                                </button>
                                <button type="button" class="btn btn-outline-success" id="view_deliver_btn">
                                    <i class="dashicons dashicons-yes-alt"></i> Mark Delivered
                                </button>
                            </div>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
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
                            <input type="hidden" name="_ajax_nonce" value="<?php echo wp_create_nonce('kwetupizza_order_nonce'); ?>">
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
            // Initialize Bootstrap tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl, {
                    placement: 'top',
                    delay: { show: 500, hide: 100 }
                });
            });
            
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
                        action: 'kwetupizza_get_order_details',
                        order_id: orderId,
                        _ajax_nonce: '<?php echo wp_create_nonce("kwetupizza_order_nonce"); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var order = response.data.order;
                            var items = response.data.items;
                            
                            // Set customer info
                            $('#view_customer_info').html(`
                                <p class="mb-1"><strong>Name:</strong> ${order.customer_name}</p>
                                <p class="mb-1"><strong>Phone:</strong> ${order.customer_phone}</p>
                            `);
                            
                            // Set order info
                            $('#view_order_info').html(`
                                <p class="mb-1"><strong>Order ID:</strong> #${order.id}</p>
                                <p class="mb-1"><strong>Date:</strong> ${order.order_date}</p>
                                <p class="mb-1"><strong>Status:</strong> <span class="badge ${order.status}">${order.status.replace('_', ' ')}</span></p>
                                <p class="mb-0"><strong>Total:</strong> ${parseFloat(order.total).toFixed(2)} Tzs</p>
                            `);
                            
                            // Set delivery address
                            $('#view_delivery_address').html(`
                                <p class="mb-0">${order.delivery_address}</p>
                            `);
                            
                            // Set order items
                            var itemsHtml = '';
                            var subtotal = 0;
                            
                            if (items && items.length > 0) {
                                items.forEach(function(item) {
                                    var itemTotal = parseFloat(item.price) * parseInt(item.quantity);
                                    subtotal += itemTotal;
                                    
                                    itemsHtml += `
                                        <tr>
                                            <td>${item.product_name}</td>
                                            <td>${parseFloat(item.price).toFixed(2)} Tzs</td>
                                            <td>${item.quantity}</td>
                                            <td class="text-end">${itemTotal.toFixed(2)} Tzs</td>
                                        </tr>
                                    `;
                                });
                                
                                $('#view_order_items_body').html(itemsHtml);
                                $('#view_order_items_footer').html(`
                                    <tr>
                                        <th colspan="3" class="text-end">Subtotal:</th>
                                        <th class="text-end">${subtotal.toFixed(2)} Tzs</th>
                                    </tr>
                                    <tr>
                                        <th colspan="3" class="text-end">Delivery Fee:</th>
                                        <th class="text-end">${(parseFloat(order.total) - subtotal).toFixed(2)} Tzs</th>
                                    </tr>
                                    <tr>
                                        <th colspan="3" class="text-end">Total:</th>
                                        <th class="text-end">${parseFloat(order.total).toFixed(2)} Tzs</th>
                                    </tr>
                                `);
                            } else {
                                $('#view_order_items_body').html('<tr><td colspan="4" class="text-center">No items found</td></tr>');
                                $('#view_order_items_footer').html('');
                            }
                            
                            // Configure action buttons based on order status
                            var status = order.status;
                            
                            // Edit button is always enabled
                            $('#view_edit_btn').prop('disabled', false).show();
                            
                            // Dispatch button - enabled only for pending, processing, or payment_confirmed orders
                            if (status === 'pending' || status === 'processing' || status === 'payment_confirmed') {
                                $('#view_dispatch_btn').prop('disabled', false).show();
                            } else {
                                $('#view_dispatch_btn').prop('disabled', true);
                            }
                            
                            // Deliver button - enabled only for dispatched orders
                            if (status === 'dispatched') {
                                $('#view_deliver_btn').prop('disabled', false).show();
                            } else {
                                $('#view_deliver_btn').prop('disabled', true);
                            }
                            
                            // Add button action handlers
                            $('#view_edit_btn').off('click').on('click', function() {
                                // Close current modal
                                $('#view-order-modal').modal('hide');
                                
                                // Trigger edit for this order
                                $('.edit-order[data-id="' + orderId + '"]').click();
                            });
                            
                            $('#view_dispatch_btn').off('click').on('click', function() {
                                // Close current modal
                                $('#view-order-modal').modal('hide');
                                
                                // Trigger dispatch for this order
                                $('.dispatch-order[data-id="' + orderId + '"]').click();
                            });
                            
                            $('#view_deliver_btn').off('click').on('click', function() {
                                // Close current modal
                                $('#view-order-modal').modal('hide');
                                
                                // Trigger mark as delivered for this order
                                $('.mark-delivered[data-id="' + orderId + '"]').click();
                            });
                            
                            // Open modal
                            var viewModal = new bootstrap.Modal(document.getElementById('view-order-modal'));
                            viewModal.show();
                        } else {
                            alert('Error loading order details: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('Failed to load order details. Please try again.');
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
                
                console.log('Form submission data:', formData); // Debug log
                
                // Save edited order via Ajax
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: formData + '&action=kwetupizza_update_order&send_notification=true',
                    success: function(response) {
                        console.log('Server response:', response); // Debug log
                        
                        if (response.success) {
                            // Close modal
                            var editModal = bootstrap.Modal.getInstance(document.getElementById('edit-order-modal'));
                            editModal.hide();
                            
                            // Show success message
                            alert('Order updated successfully!');
                            
                            // Reload page
                            location.reload();
                        } else {
                            alert('Failed to update order: ' + (response.data || 'Unknown error'));
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', status, error);
                        alert('Failed to update order due to a server error. Please try again.');
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
    // Verify the nonce
    if (!check_ajax_referer('kwetupizza_order_nonce', '_ajax_nonce', false)) {
        wp_send_json_error('Security verification failed. Please refresh the page and try again.');
        return;
    }

    // Check if the necessary data exists
    if (!isset($_POST['order_id']) || empty($_POST['order_id'])) {
        wp_send_json_error('Order ID is missing.');
        return;
    }

    global $wpdb;
    $order_id = intval($_POST['order_id']);
    
    // Check if the order exists
    $order_exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}kwetupizza_orders WHERE id = %d", $order_id));
    if ($order_exists == 0) {
        wp_send_json_error('Order not found. It may have been deleted.');
        return;
    }
    
    // Sanitize inputs
    $customer_name = isset($_POST['customer_name']) ? sanitize_text_field($_POST['customer_name']) : '';
    $customer_phone = isset($_POST['customer_phone']) ? sanitize_text_field($_POST['customer_phone']) : '';
    $delivery_address = isset($_POST['delivery_address']) ? sanitize_text_field($_POST['delivery_address']) : '';
    $total = isset($_POST['total']) ? floatval($_POST['total']) : 0;
    $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
    $send_notification = isset($_POST['send_notification']) && $_POST['send_notification'] === 'true';

    // Validate required fields
    if (empty($customer_name) || empty($customer_phone) || empty($delivery_address) || $total <= 0 || empty($status)) {
        wp_send_json_error('All fields are required. Please fill in all details.');
        return;
    }

    $orders_table = $wpdb->prefix . 'kwetupizza_orders';

    // Get previous status for notification purposes
    $previous_status = $wpdb->get_var($wpdb->prepare("SELECT status FROM $orders_table WHERE id = %d", $order_id));

    // Log the update attempt
    error_log('Attempting to update order #' . $order_id . ' with status: ' . $status);

    // Perform the update operation
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

    // Handle the update result
    if ($updated !== false) {
        // If status has changed, send notification
        if ($send_notification && $previous_status !== $status) {
            if (function_exists('kwetupizza_notify_customer')) {
                $notification_sent = kwetupizza_notify_customer($order_id, $status);
                error_log('Notification for order #' . $order_id . ' ' . ($notification_sent ? 'sent' : 'failed'));
            } else {
                error_log('Notification function not available for order #' . $order_id);
            }
        }
        
        wp_send_json_success('Order updated successfully.');
    } else {
        // Check if it's a non-change that appears as a failure
        if ($wpdb->last_error) {
            error_log('Database error during order update: ' . $wpdb->last_error);
            wp_send_json_error('Database error: ' . $wpdb->last_error);
        } else {
            // No DB error usually means no rows were affected (no changes)
            wp_send_json_success('No changes were made to the order.');
        }
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

// Ajax handler to get order details with items
function kwetupizza_get_order_details() {
    check_ajax_referer('kwetupizza_order_nonce', '_ajax_nonce');

    global $wpdb;
    $order_id = intval($_POST['order_id']);
    $orders_table = $wpdb->prefix . 'kwetupizza_orders';
    $order_items_table = $wpdb->prefix . 'kwetupizza_order_items';
    $products_table = $wpdb->prefix . 'kwetupizza_products';

    // Get order data
    $order = $wpdb->get_row($wpdb->prepare("SELECT * FROM $orders_table WHERE id = %d", $order_id));

    if (!$order) {
        wp_send_json_error('Order not found.');
        return;
    }

    // Get order items
    $items = $wpdb->get_results($wpdb->prepare("
        SELECT oi.*, p.product_name 
        FROM $order_items_table oi
        LEFT JOIN $products_table p ON oi.product_id = p.id
        WHERE oi.order_id = %d
    ", $order_id));

    // Send response with order and items
    wp_send_json_success(array(
        'order' => $order,
        'items' => $items
    ));
}
add_action('wp_ajax_kwetupizza_get_order_details', 'kwetupizza_get_order_details');
?>
