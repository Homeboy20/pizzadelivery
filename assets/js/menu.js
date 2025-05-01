/**
 * KwetuPizza Menu JS
 * 
 * Handles the menu display and cart functionality.
 */

jQuery(document).ready(function($) {
    // Initialize cart
    let cart = [];
    
    // Menu category filtering
    $('.kwetupizza-menu-filters a').on('click', function(e) {
        e.preventDefault();
        const category = $(this).data('category');
        
        // Update active class
        $('.kwetupizza-menu-filters a').removeClass('active');
        $(this).addClass('active');
        
        if (category === 'all') {
            $('.kwetupizza-category-section').show();
        } else {
            $('.kwetupizza-category-section').hide();
            $('#' + category).show();
        }
    });
    
    // Add to cart functionality
    $('.kwetupizza-add-to-cart').on('click', function() {
        const $productCard = $(this).closest('.kwetupizza-product-card');
        const productId = $(this).data('product-id');
        const productName = $productCard.find('h3').text();
        const productPrice = parseFloat($productCard.find('.kwetupizza-product-price').text().replace(/[^0-9.]/g, ''));
        
        // Check if product is already in cart
        const existingItem = cart.find(item => item.product_id === productId);
        
        if (existingItem) {
            existingItem.quantity += 1;
            existingItem.total = existingItem.quantity * existingItem.price;
        } else {
            cart.push({
                product_id: productId,
                product_name: productName,
                price: productPrice,
                quantity: 1,
                total: productPrice
            });
        }
        
        // Update cart display
        updateCartDisplay();
        
        // Show notification
        showNotification(productName + ' added to your order');
    });
    
    // Update cart display
    function updateCartDisplay() {
        const $cartItems = $('.kwetupizza-cart-items');
        $cartItems.empty();
        
        let cartTotal = 0;
        
        if (cart.length === 0) {
            $cartItems.html('<p>Your cart is empty</p>');
            $('.kwetupizza-checkout-button').prop('disabled', true);
        } else {
            cart.forEach((item, index) => {
                const $cartItem = $(`
                    <div class="kwetupizza-cart-item" data-index="${index}">
                        <div class="kwetupizza-cart-item-details">
                            <div class="kwetupizza-cart-item-name">${item.product_name}</div>
                            <div class="kwetupizza-cart-item-price">${formatCurrency(item.price)} × ${item.quantity}</div>
                        </div>
                        <div class="kwetupizza-cart-item-quantity">
                            <button class="kwetupizza-quantity-btn decrease-quantity">-</button>
                            <input type="text" class="kwetupizza-quantity-input" value="${item.quantity}" readonly>
                            <button class="kwetupizza-quantity-btn increase-quantity">+</button>
                        </div>
                        <button class="kwetupizza-cart-item-remove">×</button>
                    </div>
                `);
                
                $cartItems.append($cartItem);
                cartTotal += item.total;
            });
            
            $('.kwetupizza-checkout-button').prop('disabled', false);
        }
        
        // Update total
        $('.kwetupizza-cart-total-amount').text(formatCurrency(cartTotal));
    }
    
    // Event delegation for cart item actions
    $('.kwetupizza-cart-items').on('click', '.increase-quantity', function() {
        const index = $(this).closest('.kwetupizza-cart-item').data('index');
        cart[index].quantity += 1;
        cart[index].total = cart[index].quantity * cart[index].price;
        updateCartDisplay();
    });
    
    $('.kwetupizza-cart-items').on('click', '.decrease-quantity', function() {
        const index = $(this).closest('.kwetupizza-cart-item').data('index');
        if (cart[index].quantity > 1) {
            cart[index].quantity -= 1;
            cart[index].total = cart[index].quantity * cart[index].price;
        } else {
            cart.splice(index, 1);
        }
        updateCartDisplay();
    });
    
    $('.kwetupizza-cart-items').on('click', '.kwetupizza-cart-item-remove', function() {
        const index = $(this).closest('.kwetupizza-cart-item').data('index');
        cart.splice(index, 1);
        updateCartDisplay();
    });
    
    // Checkout button
    $('.kwetupizza-checkout-button').on('click', function() {
        if (cart.length === 0) return;
        
        // Populate order summary
        const $summaryItems = $('.kwetupizza-summary-items');
        $summaryItems.empty();
        
        let cartTotal = 0;
        
        cart.forEach(item => {
            $summaryItems.append(`
                <div class="kwetupizza-summary-item">
                    <span>${item.quantity} × ${item.product_name}</span>
                    <span>${formatCurrency(item.total)}</span>
                </div>
            `);
            cartTotal += item.total;
        });
        
        $('.kwetupizza-summary-total-amount').text(formatCurrency(cartTotal));
        
        // Show checkout modal
        $('#kwetupizza-checkout-modal').css('display', 'block');
    });
    
    // Close modal
    $('.kwetupizza-modal-close').on('click', function() {
        $(this).closest('.kwetupizza-modal').css('display', 'none');
    });
    
    // Close modal when clicking outside
    $(window).on('click', function(e) {
        if ($(e.target).hasClass('kwetupizza-modal')) {
            $('.kwetupizza-modal').css('display', 'none');
        }
    });
    
    // Toggle mobile money fields based on payment method selection
    $('#payment_method').on('change', function() {
        if ($(this).val() === 'mobile_money') {
            $('#mobile_money_fields').show();
        } else {
            $('#mobile_money_fields').hide();
        }
    });
    
    // Place order form submission
    $('#kwetupizza-checkout-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = {
            customer_name: $('#customer_name').val(),
            customer_phone: $('#customer_phone').val(),
            delivery_address: $('#delivery_address').val(),
            payment_method: $('#payment_method').val(),
            cart_items: JSON.stringify(cart),
            nonce: kwetupizza_params.nonce
        };
        
        if ($('#payment_method').val() === 'mobile_money') {
            formData.payment_phone = $('#payment_phone').val();
        }
        
        // Show loading state
        $(this).find('button[type="submit"]').prop('disabled', true).text('Processing...');
        
        // Submit via AJAX
        $.ajax({
            url: kwetupizza_params.ajax_url,
            type: 'POST',
            data: {
                action: 'kwetupizza_place_order',
                ...formData
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.redirect) {
                        // Redirect to payment page
                        window.location.href = response.data.redirect;
                    } else {
                        // Show success message
                        showSuccessPage(response.data.order_id, response.data.message);
                    }
                } else {
                    alert('Error: ' + response.data);
                    $('#kwetupizza-checkout-form button[type="submit"]').prop('disabled', false).text('Place Order');
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
                $('#kwetupizza-checkout-form button[type="submit"]').prop('disabled', false).text('Place Order');
            }
        });
    });
    
    // Show success page
    function showSuccessPage(orderId, message) {
        // Reset cart
        cart = [];
        updateCartDisplay();
        
        // Close checkout modal
        $('#kwetupizza-checkout-modal').css('display', 'none');
        
        // Create success message
        const $successMessage = $(`
            <div class="kwetupizza-success-message">
                <div class="kwetupizza-success-icon">✓</div>
                <h2>Order Placed Successfully!</h2>
                <p>Your order #${orderId} has been received.</p>
                <p>${message}</p>
                <p>You can track your order status using the <a href="${window.location.origin}/order-tracking?order_id=${orderId}">order tracking page</a>.</p>
                <button class="kwetupizza-back-to-menu">Back to Menu</button>
            </div>
        `);
        
        // Hide menu and show success message
        $('.kwetupizza-menu-container').hide();
        $('.kwetupizza-menu-container').after($successMessage);
        
        // Back to menu button
        $('.kwetupizza-back-to-menu').on('click', function() {
            $('.kwetupizza-success-message').remove();
            $('.kwetupizza-menu-container').show();
            
            // Reset form
            $('#kwetupizza-checkout-form')[0].reset();
            $('#kwetupizza-checkout-form button[type="submit"]').prop('disabled', false).text('Place Order');
        });
    }
    
    // Show notification
    function showNotification(message) {
        const $notification = $(`
            <div class="kwetupizza-notification">
                <div class="kwetupizza-notification-content">
                    ${message}
                </div>
            </div>
        `);
        
        $('body').append($notification);
        
        setTimeout(function() {
            $notification.addClass('show');
            
            setTimeout(function() {
                $notification.removeClass('show');
                setTimeout(function() {
                    $notification.remove();
                }, 300);
            }, 2000);
        }, 10);
    }
    
    // Format currency
    function formatCurrency(amount) {
        return amount.toFixed(2) + ' TZS';
    }
    
    // Add notification styles
    $('head').append(`
        <style>
            .kwetupizza-notification {
                position: fixed;
                bottom: 20px;
                right: 20px;
                z-index: 9999;
                transform: translateY(100px);
                transition: transform 0.3s ease;
            }
            
            .kwetupizza-notification.show {
                transform: translateY(0);
            }
            
            .kwetupizza-notification-content {
                background-color: #4caf50;
                color: white;
                padding: 10px 20px;
                border-radius: 4px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            }
            
            .kwetupizza-success-message {
                text-align: center;
                padding: 30px;
                background-color: white;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
                max-width: 600px;
                margin: 30px auto;
            }
            
            .kwetupizza-success-icon {
                background-color: #4caf50;
                color: white;
                width: 60px;
                height: 60px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 30px;
                margin: 0 auto 20px;
            }
            
            .kwetupizza-back-to-menu {
                background-color: #ff5722;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 4px;
                cursor: pointer;
                margin-top: 20px;
                transition: background-color 0.3s ease;
            }
            
            .kwetupizza-back-to-menu:hover {
                background-color: #e64a19;
            }
        </style>
    `);
}); 