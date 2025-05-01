/**
 * KwetuPizza Frontend Scripts
 */
jQuery(document).ready(function($) {
    
    // Cart functionality
    var cart = [];
    var cartTotal = 0;
    
    // Add to cart button click handler
    $('.kwetupizza-add-to-cart').on('click', function() {
        var productId = $(this).data('product-id');
        var productName = $(this).closest('.kwetupizza-product-card').find('h3').text();
        var productPrice = parseFloat($(this).closest('.kwetupizza-product-card').find('.kwetupizza-product-price').text().replace(/[^0-9.]/g, ''));
        
        // Check if product already in cart
        var existingItem = cart.find(item => item.id === productId);
        
        if (existingItem) {
            existingItem.quantity += 1;
            existingItem.total = existingItem.price * existingItem.quantity;
        } else {
            cart.push({
                id: productId,
                name: productName,
                price: productPrice,
                quantity: 1,
                total: productPrice
            });
        }
        
        updateCart();
        
        // Show notification
        alert(productName + ' added to cart!');
    });
    
    // Update cart display
    function updateCart() {
        var cartItems = $('.kwetupizza-cart-items');
        cartItems.empty();
        
        if (cart.length === 0) {
            cartItems.html('<p>Your cart is empty.</p>');
            $('.kwetupizza-checkout-button').prop('disabled', true);
        } else {
            cartTotal = 0;
            
            cart.forEach(function(item, index) {
                cartTotal += item.total;
                
                var itemHtml = '<div class="kwetupizza-cart-item">' +
                    '<span class="item-name">' + item.name + '</span>' +
                    '<div class="item-quantity">' +
                    '<button class="quantity-decrease" data-index="' + index + '">-</button>' +
                    '<span>' + item.quantity + '</span>' +
                    '<button class="quantity-increase" data-index="' + index + '">+</button>' +
                    '</div>' +
                    '<span class="item-price">' + formatCurrency(item.total) + '</span>' +
                    '<button class="remove-item" data-index="' + index + '">×</button>' +
                    '</div>';
                
                cartItems.append(itemHtml);
            });
            
            $('.kwetupizza-checkout-button').prop('disabled', false);
        }
        
        $('.kwetupizza-cart-total-amount').text(formatCurrency(cartTotal));
        
        // Add event handlers for quantity changes and removals
        $('.quantity-decrease').on('click', function() {
            var index = $(this).data('index');
            if (cart[index].quantity > 1) {
                cart[index].quantity -= 1;
                cart[index].total = cart[index].price * cart[index].quantity;
                updateCart();
            }
        });
        
        $('.quantity-increase').on('click', function() {
            var index = $(this).data('index');
            cart[index].quantity += 1;
            cart[index].total = cart[index].price * cart[index].quantity;
            updateCart();
        });
        
        $('.remove-item').on('click', function() {
            var index = $(this).data('index');
            cart.splice(index, 1);
            updateCart();
        });
    }
    
    // Format currency display
    function formatCurrency(amount) {
        return amount.toFixed(2) + ' TZS';
    }
    
    // Checkout button click handler
    $('.kwetupizza-checkout-button').on('click', function() {
        if (cart.length === 0) {
            return;
        }
        
        // Show checkout modal
        $('#kwetupizza-checkout-modal').show();
        
        // Update order summary in checkout modal
        var summaryItems = $('.kwetupizza-summary-items');
        summaryItems.empty();
        
        cart.forEach(function(item) {
            var itemHtml = '<div class="summary-item">' +
                '<span class="item-name">' + item.quantity + ' × ' + item.name + '</span>' +
                '<span class="item-price">' + formatCurrency(item.total) + '</span>' +
                '</div>';
            
            summaryItems.append(itemHtml);
        });
        
        $('.kwetupizza-summary-total-amount').text(formatCurrency(cartTotal));
    });
    
    // Close modal
    $('.kwetupizza-modal-close').on('click', function() {
        $(this).closest('.kwetupizza-modal').hide();
    });
    
    // Payment method change handler
    $('#payment_method').on('change', function() {
        var method = $(this).val();
        
        if (method === 'mobile_money') {
            $('#mobile_money_fields').show();
        } else {
            $('#mobile_money_fields').hide();
        }
    });
    
    // Handle checkout form submission
    $('#kwetupizza-checkout-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        
        // Add cart data to the form submission
        var cartData = encodeURIComponent(JSON.stringify(cart));
        
        $.ajax({
            url: kwetupizza_params.ajax_url,
            type: 'POST',
            data: formData + '&action=kwetupizza_place_order&cart=' + cartData + '&total=' + cartTotal + '&nonce=' + kwetupizza_params.nonce,
            success: function(response) {
                if (response.success) {
                    // Clear cart
                    cart = [];
                    updateCart();
                    
                    // Hide checkout modal
                    $('#kwetupizza-checkout-modal').hide();
                    
                    // Show success message
                    alert('Your order has been placed successfully. You will be redirected to complete payment.');
                    
                    // Redirect to payment or thank you page
                    if (response.data.redirect_url) {
                        window.location.href = response.data.redirect_url;
                    }
                } else {
                    alert('Error placing order: ' + response.data.message);
                }
            }
        });
    });
    
    // Menu filtering
    $('.kwetupizza-menu-filters a').on('click', function(e) {
        e.preventDefault();
        
        $(this).addClass('active').siblings().removeClass('active');
        
        var category = $(this).data('category');
        
        if (category === 'all') {
            $('.kwetupizza-category-section').show();
        } else {
            $('.kwetupizza-category-section').hide();
            $('#' + category).show();
        }
    });
    
    // Order tracking functionality
    $('#kwetupizza-track-order-button').on('click', function() {
        var orderId = $('#order_id').val();
        var phone = $('#phone').val();
        
        if (!orderId) {
            alert('Please enter your order number.');
            return;
        }
        
        $.ajax({
            url: kwetupizza_params.ajax_url,
            type: 'POST',
            data: {
                action: 'kwetupizza_track_order',
                order_id: orderId,
                phone: phone,
                nonce: kwetupizza_params.nonce
            },
            success: function(response) {
                if (response.success) {
                    displayOrderTracking(response.data.order);
                } else {
                    alert('Error: ' + response.data.message);
                }
            }
        });
    });
    
    // Display order tracking results
    function displayOrderTracking(order) {
        $('#tracking-order-id').text(order.id);
        $('#tracking-status').text(order.status);
        $('#tracking-customer').text(order.customer_name);
        $('#tracking-address').text(order.delivery_address);
        $('#tracking-total').text(order.total);
        $('#tracking-date').text(order.order_date);
        
        // Display order items
        var itemsList = $('#tracking-items');
        itemsList.empty();
        
        order.items.forEach(function(item) {
            itemsList.append('<li>' + item.quantity + ' × ' + item.product_name + '</li>');
        });
        
        // Show tracking results
        $('.kwetupizza-tracking-results').show();
    }
    
    // Initialize cart on page load
    updateCart();
}); 