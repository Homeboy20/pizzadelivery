/**
 * KwetuPizza Customer Account JavaScript
 * 
 * Handles the customer account functionality.
 */

jQuery(document).ready(function($) {
    // Tab navigation
    $('.kwetupizza-tab').on('click', function() {
        const tabId = $(this).data('tab');
        
        // Update active tab
        $('.kwetupizza-tab').removeClass('active');
        $(this).addClass('active');
        
        // Hide all tab content and show the selected one
        $('.kwetupizza-tab-content').hide();
        $('#kwetupizza-' + tabId + '-content').show();
    });
    
    // Login form submission
    $('#kwetupizza-login-form').on('submit', function(e) {
        e.preventDefault();
        
        const phoneNumber = $('#login_phone').val().trim();
        
        if (!phoneNumber) {
            alert('Please enter your phone number');
            return;
        }
        
        // Show loading state
        $('.kwetupizza-login-button').prop('disabled', true).text('Sending Code...');
        
        // Send verification code to phone
        $.ajax({
            url: kwetupizza_params.ajax_url,
            type: 'POST',
            data: {
                action: 'kwetupizza_send_verification_code',
                phone: phoneNumber,
                nonce: kwetupizza_params.nonce
            },
            success: function(response) {
                // Reset button
                $('.kwetupizza-login-button').prop('disabled', false).text('Login / Sign Up');
                
                if (response.success) {
                    // Show verification form
                    $('#kwetupizza-login-section').hide();
                    $('#kwetupizza-verification-section').show();
                } else {
                    alert(response.data || 'Failed to send verification code. Please try again.');
                }
            },
            error: function() {
                // Reset button
                $('.kwetupizza-login-button').prop('disabled', false).text('Login / Sign Up');
                alert('An error occurred. Please try again.');
            }
        });
    });
    
    // Verification form submission
    $('#kwetupizza-verification-form').on('submit', function(e) {
        e.preventDefault();
        
        const verificationCode = $('#verification_code').val().trim();
        const phoneNumber = $('#login_phone').val().trim();
        
        if (!verificationCode) {
            alert('Please enter the verification code');
            return;
        }
        
        // Show loading state
        $('.kwetupizza-verify-button').prop('disabled', true).text('Verifying...');
        
        // Verify code
        $.ajax({
            url: kwetupizza_params.ajax_url,
            type: 'POST',
            data: {
                action: 'kwetupizza_verify_code',
                phone: phoneNumber,
                code: verificationCode,
                nonce: kwetupizza_params.nonce
            },
            success: function(response) {
                // Reset button
                $('.kwetupizza-verify-button').prop('disabled', false).text('Verify');
                
                if (response.success) {
                    // Store authentication token in local storage
                    localStorage.setItem('kwetupizza_auth_token', response.data.token);
                    localStorage.setItem('kwetupizza_phone', phoneNumber);
                    
                    // Show dashboard
                    loadCustomerDashboard(response.data);
                } else {
                    alert(response.data || 'Invalid verification code. Please try again.');
                }
            },
            error: function() {
                // Reset button
                $('.kwetupizza-verify-button').prop('disabled', false).text('Verify');
                alert('An error occurred. Please try again.');
            }
        });
    });
    
    // Check if user is already logged in
    const authToken = localStorage.getItem('kwetupizza_auth_token');
    const storedPhone = localStorage.getItem('kwetupizza_phone');
    
    if (authToken && storedPhone) {
        // Verify token
        $.ajax({
            url: kwetupizza_params.ajax_url,
            type: 'POST',
            data: {
                action: 'kwetupizza_verify_token',
                token: authToken,
                phone: storedPhone,
                nonce: kwetupizza_params.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Show dashboard
                    loadCustomerDashboard(response.data);
                } else {
                    // Clear invalid token
                    localStorage.removeItem('kwetupizza_auth_token');
                    localStorage.removeItem('kwetupizza_phone');
                }
            }
        });
    }
    
    // Load customer dashboard
    function loadCustomerDashboard(userData) {
        // Hide login/verification sections
        $('#kwetupizza-login-section, #kwetupizza-verification-section').hide();
        
        // Set customer name
        $('#customer-name').text(userData.name || 'Customer');
        
        // Fill profile form
        $('#profile_name').val(userData.name || '');
        $('#profile_email').val(userData.email || '');
        $('#profile_phone').val(userData.phone || '');
        $('#profile_address').val(userData.address || '');
        
        // Load order history
        loadOrderHistory();
        
        // Load loyalty points
        loadLoyaltyPoints();
        
        // Show dashboard
        $('#kwetupizza-dashboard-section').show();
    }
    
    // Load order history
    function loadOrderHistory() {
        const phone = localStorage.getItem('kwetupizza_phone');
        
        if (!phone) return;
        
        $('#kwetupizza-orders-list').html('<p class="kwetupizza-loading">Loading your orders...</p>');
        
        $.ajax({
            url: kwetupizza_params.ajax_url,
            type: 'POST',
            data: {
                action: 'kwetupizza_get_customer_orders',
                phone: phone,
                nonce: kwetupizza_params.nonce
            },
            success: function(response) {
                if (response.success && response.data.orders) {
                    renderOrderHistory(response.data.orders);
                } else {
                    $('#kwetupizza-orders-list').html('<p>No order history found.</p>');
                }
            },
            error: function() {
                $('#kwetupizza-orders-list').html('<p>Failed to load order history.</p>');
            }
        });
    }
    
    // Render order history
    function renderOrderHistory(orders) {
        if (orders.length === 0) {
            $('#kwetupizza-orders-list').html('<p>No order history found.</p>');
            return;
        }
        
        let html = '<div class="kwetupizza-orders-grid">';
        
        orders.forEach(order => {
            const statusClass = 'status-' + order.status;
            
            html += `
                <div class="kwetupizza-order-card">
                    <div class="kwetupizza-order-header">
                        <div class="kwetupizza-order-number">Order #${order.id}</div>
                        <div class="kwetupizza-order-date">${formatDate(order.order_date)}</div>
                    </div>
                    <div class="kwetupizza-order-info">
                        <div class="kwetupizza-order-status ${statusClass}">${order.status}</div>
                        <div class="kwetupizza-order-total">${order.total}</div>
                    </div>
                    <div class="kwetupizza-order-items-preview">
                        ${renderOrderItems(order.items)}
                    </div>
                    <div class="kwetupizza-order-actions">
                        <a href="${window.location.origin}/order-tracking?order_id=${order.id}" class="kwetupizza-btn kwetupizza-btn-sm">Track Order</a>
                        ${order.status === 'completed' ? '<button class="kwetupizza-btn kwetupizza-btn-outline kwetupizza-btn-sm reorder-btn" data-order-id="' + order.id + '">Reorder</button>' : ''}
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        
        $('#kwetupizza-orders-list').html(html);
        
        // Add reorder functionality
        $('.reorder-btn').on('click', function() {
            const orderId = $(this).data('order-id');
            reorderFromPreviousOrder(orderId);
        });
    }
    
    // Render order items preview
    function renderOrderItems(items) {
        if (!items || items.length === 0) return '<p>No items</p>';
        
        let html = '<ul class="kwetupizza-order-items-list">';
        
        // Show at most 3 items
        const displayItems = items.slice(0, 3);
        
        displayItems.forEach(item => {
            html += `<li>${item.quantity} × ${item.product_name}</li>`;
        });
        
        if (items.length > 3) {
            html += `<li>+${items.length - 3} more items</li>`;
        }
        
        html += '</ul>';
        
        return html;
    }
    
    // Load loyalty points
    function loadLoyaltyPoints() {
        const phone = localStorage.getItem('kwetupizza_phone');
        
        if (!phone) return;
        
        $.ajax({
            url: kwetupizza_params.rest_url + 'kwetupizza/v1/loyalty/' + phone,
            type: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', kwetupizza_params.nonce);
            },
            success: function(response) {
                if (response.success) {
                    renderLoyaltyPoints(response.loyalty);
                }
            }
        });
    }
    
    // Render loyalty points
    function renderLoyaltyPoints(loyalty) {
        $('#loyalty-points').text(loyalty.points || 0);
        $('#loyalty-orders').text(loyalty.total_orders || 0);
        
        // Render available rewards
        const $rewardsList = $('#loyalty-rewards');
        $rewardsList.empty();
        
        if (loyalty.available_rewards && loyalty.available_rewards.length > 0) {
            loyalty.available_rewards.forEach((reward, index) => {
                $rewardsList.append(`
                    <li class="kwetupizza-reward-item">
                        <div class="kwetupizza-reward-info">
                            <div class="kwetupizza-reward-name">${reward.description}</div>
                            <div class="kwetupizza-reward-points">${reward.points} points</div>
                        </div>
                        <button class="kwetupizza-btn kwetupizza-btn-sm redeem-reward-btn" data-reward-id="${index + 1}">Redeem</button>
                    </li>
                `);
            });
        } else {
            if (loyalty.next_reward) {
                $rewardsList.append(`<li class="kwetupizza-reward-message">${loyalty.next_reward}</li>`);
            } else {
                $rewardsList.append('<li class="kwetupizza-reward-message">No rewards available yet.</li>');
            }
        }
        
        // Add redeem functionality
        $('.redeem-reward-btn').on('click', function() {
            const rewardId = $(this).data('reward-id');
            redeemReward(rewardId);
        });
    }
    
    // Redeem reward
    function redeemReward(rewardId) {
        const phone = localStorage.getItem('kwetupizza_phone');
        
        if (!phone) return;
        
        if (!confirm('Are you sure you want to redeem this reward?')) {
            return;
        }
        
        // Show loading
        const $btn = $(`[data-reward-id="${rewardId}"]`);
        const originalText = $btn.text();
        $btn.prop('disabled', true).text('Redeeming...');
        
        $.ajax({
            url: kwetupizza_params.ajax_url,
            type: 'POST',
            data: {
                action: 'kwetupizza_redeem_loyalty_reward',
                reward_id: rewardId,
                phone: phone,
                nonce: kwetupizza_params.nonce
            },
            success: function(response) {
                $btn.prop('disabled', false).text(originalText);
                
                if (response.success) {
                    // Show success message with redemption code
                    alert(`Reward redeemed successfully!\n\nYour redemption code is: ${response.data.redemption_code}\n\nUse this code during checkout to apply your reward.`);
                    
                    // Update points display
                    $('#loyalty-points').text(response.data.remaining_points || 0);
                    
                    // Reload loyalty data
                    loadLoyaltyPoints();
                } else {
                    alert(response.data || 'Failed to redeem reward. Please try again.');
                }
            },
            error: function() {
                $btn.prop('disabled', false).text(originalText);
                alert('An error occurred. Please try again.');
            }
        });
    }
    
    // Reorder from previous order
    function reorderFromPreviousOrder(orderId) {
        // Redirect to menu page with order ID
        window.location.href = window.location.origin + '/pizza-menu?reorder=' + orderId;
    }
    
    // Profile form submission
    $('#kwetupizza-profile-form').on('submit', function(e) {
        e.preventDefault();
        
        const name = $('#profile_name').val().trim();
        const email = $('#profile_email').val().trim();
        const phone = $('#profile_phone').val().trim();
        const address = $('#profile_address').val().trim();
        
        if (!name) {
            alert('Please enter your name');
            return;
        }
        
        // Show loading state
        $('.kwetupizza-update-profile-button').prop('disabled', true).text('Updating...');
        
        // Update profile
        $.ajax({
            url: kwetupizza_params.ajax_url,
            type: 'POST',
            data: {
                action: 'kwetupizza_update_customer_profile',
                name: name,
                email: email,
                phone: phone,
                address: address,
                nonce: kwetupizza_params.nonce
            },
            success: function(response) {
                // Reset button
                $('.kwetupizza-update-profile-button').prop('disabled', false).text('Update Profile');
                
                if (response.success) {
                    // Update customer name in header
                    $('#customer-name').text(name);
                    
                    // Show success message
                    alert('Profile updated successfully!');
                } else {
                    alert(response.data || 'Failed to update profile. Please try again.');
                }
            },
            error: function() {
                // Reset button
                $('.kwetupizza-update-profile-button').prop('disabled', false).text('Update Profile');
                alert('An error occurred. Please try again.');
            }
        });
    });
    
    // Logout button
    $('#kwetupizza-logout-button').on('click', function() {
        // Clear local storage
        localStorage.removeItem('kwetupizza_auth_token');
        localStorage.removeItem('kwetupizza_phone');
        
        // Show login form
        $('#kwetupizza-dashboard-section').hide();
        $('#kwetupizza-login-section').show();
        
        // Clear forms
        $('#kwetupizza-login-form, #kwetupizza-verification-form, #kwetupizza-profile-form').trigger('reset');
    });
    
    // Format date function
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }
}); 