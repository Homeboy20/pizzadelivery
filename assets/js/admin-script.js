/**
 * KwetuPizza Admin Scripts
 */
jQuery(document).ready(function($) {
    
    // Initialize tabbed interface
    $('.kwetupizza-tabs a').on('click', function(e) {
        e.preventDefault();
        $(this).addClass('active').siblings().removeClass('active');
        var target = $(this).attr('href');
        $('.tab-content').hide();
        $(target).show();
    });
    
    // Initialize the first tab
    $('.kwetupizza-tabs a:first').click();
    
    // Initialize datepickers
    if ($.fn.datepicker) {
        $('.datepicker').datepicker({
            dateFormat: 'yy-mm-dd'
        });
    }
    
    // Order status change handler
    $('.order-status-select').on('change', function() {
        var orderId = $(this).data('order-id');
        var newStatus = $(this).val();
        
        $.ajax({
            url: kwetupizza_params.ajax_url,
            type: 'POST',
            data: {
                action: 'kwetupizza_update_order',
                order_id: orderId,
                status: newStatus,
                nonce: kwetupizza_params.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Order status updated successfully.');
                } else {
                    alert('Failed to update order status.');
                }
            }
        });
    });
    
    // Delete confirmation
    $('.delete-button').on('click', function(e) {
        if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
            e.preventDefault();
        }
    });
    
    // Menu management
    $('#kwetupizza-add-menu-item-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        
        $.ajax({
            url: kwetupizza_params.ajax_url,
            type: 'POST',
            data: formData + '&action=kwetupizza_save_menu_item&nonce=' + kwetupizza_params.nonce,
            success: function(response) {
                if (response.success) {
                    alert('Menu item saved successfully.');
                    window.location.reload();
                } else {
                    alert('Failed to save menu item.');
                }
            }
        });
    });
    
    // Loyalty program management
    $('.redeem-reward-button').on('click', function() {
        var customerId = $(this).data('customer-id');
        var rewardId = $(this).data('reward-id');
        var rewardPoints = $(this).data('points');
        
        if (confirm('Are you sure you want to redeem this reward for the customer?')) {
            $.ajax({
                url: kwetupizza_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'kwetupizza_redeem_loyalty_reward',
                    customer_id: customerId,
                    reward_id: rewardId,
                    points: rewardPoints,
                    nonce: kwetupizza_params.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('Reward redeemed successfully.');
                        window.location.reload();
                    } else {
                        alert('Failed to redeem reward: ' + response.data.message);
                    }
                }
            });
        }
    });
    
    // Dashboard chart initialization
    if (typeof Chart !== 'undefined') {
        // Sales trend chart
        var salesCtx = document.getElementById('salesTrendChart');
        if (salesCtx) {
            var salesChart = new Chart(salesCtx, {
                type: 'line',
                data: {
                    labels: salesData.labels,
                    datasets: [{
                        label: 'Sales',
                        data: salesData.values,
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
        
        // Order status distribution chart
        var orderStatusCtx = document.getElementById('orderStatusChart');
        if (orderStatusCtx) {
            var orderStatusChart = new Chart(orderStatusCtx, {
                type: 'doughnut',
                data: {
                    labels: orderStatusData.labels,
                    datasets: [{
                        data: orderStatusData.values,
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.2)',
                            'rgba(54, 162, 235, 0.2)',
                            'rgba(255, 206, 86, 0.2)',
                            'rgba(75, 192, 192, 0.2)',
                            'rgba(153, 102, 255, 0.2)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 206, 86, 1)',
                            'rgba(75, 192, 192, 1)',
                            'rgba(153, 102, 255, 1)'
                        ],
                        borderWidth: 1
                    }]
                }
            });
        }
    }
}); 