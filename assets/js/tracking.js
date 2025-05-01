/**
 * KwetuPizza Order Tracking JavaScript
 * 
 * Handles the order tracking functionality.
 */

jQuery(document).ready(function($) {
    // Order tracking form submission
    $('#kwetupizza-track-order-button').on('click', function() {
        const orderId = $('#order_id').val().trim();
        const phone = $('#phone').val().trim();
        
        if (!orderId) {
            alert('Please enter your order number');
            return;
        }
        
        // Show loading state
        $(this).prop('disabled', true).text('Tracking...');
        
        // Hide previous results and errors
        $('.kwetupizza-tracking-results').hide();
        $('.kwetupizza-tracking-error').hide();
        
        // Fetch order details
        $.ajax({
            url: kwetupizza_params.ajax_url,
            type: 'GET',
            data: {
                action: 'kwetupizza_track_order',
                order_id: orderId,
                phone: phone,
                nonce: kwetupizza_params.nonce
            },
            success: function(response) {
                // Reset button
                $('#kwetupizza-track-order-button').prop('disabled', false).text('Track Order');
                
                if (response.success) {
                    displayOrderDetails(response.data.order);
                } else {
                    $('.kwetupizza-tracking-error').show();
                }
            },
            error: function() {
                // Reset button
                $('#kwetupizza-track-order-button').prop('disabled', false).text('Track Order');
                
                // Show error
                $('.kwetupizza-tracking-error').show();
            }
        });
    });
    
    // Alternative: allow tracking via URL parameter
    const urlParams = new URLSearchParams(window.location.search);
    const orderIdParam = urlParams.get('order_id');
    const phoneParam = urlParams.get('phone');
    
    if (orderIdParam) {
        $('#order_id').val(orderIdParam);
        if (phoneParam) {
            $('#phone').val(phoneParam);
        }
        $('#kwetupizza-track-order-button').trigger('click');
    }
    
    // Display order details
    function displayOrderDetails(order) {
        // Set order ID
        $('#tracking-order-id').text(order.id);
        
        // Set status with class
        const statusClass = 'status-' + order.status;
        $('#tracking-status').text(order.status_message).addClass(statusClass);
        
        // Set order details
        $('#tracking-customer').text(order.customer_name);
        $('#tracking-address').text(order.delivery_address);
        $('#tracking-date').text(formatDate(order.order_date));
        $('#tracking-total').text(order.total);
        
        // Set order items
        const $itemsList = $('#tracking-items');
        $itemsList.empty();
        
        order.items.forEach(item => {
            $itemsList.append(`
                <li>
                    <strong>${item.quantity} × ${item.product_name}</strong>
                    <div>${formatCurrency(item.price * item.quantity)}</div>
                </li>
            `);
        });
        
        // Set timeline
        const $timeline = $('#tracking-timeline');
        $timeline.empty();
        
        order.timeline.forEach(event => {
            $timeline.append(`
                <li class="${event.event === 'Order Delivered' ? 'completed' : ''}">
                    <div class="kwetupizza-timeline-event">${event.event}</div>
                    <span class="kwetupizza-timeline-time">${formatDateTime(event.time)}</span>
                    <div class="kwetupizza-timeline-description">${event.description}</div>
                </li>
            `);
        });
        
        // Show results
        $('.kwetupizza-tracking-results').fadeIn();
        
        // If order is "out for delivery", add map
        if (order.status === 'out_for_delivery' && order.delivery_coords) {
            initializeDeliveryMap(order.delivery_coords, order.customer_address_coords);
        }
    }
    
    // Initialize delivery map (if Google Maps API is available)
    function initializeDeliveryMap(deliveryCoords, customerCoords) {
        // Check if Google Maps API is loaded
        if (typeof google === 'undefined' || typeof google.maps === 'undefined') {
            return;
        }
        
        // Create map container if it doesn't exist
        if ($('#kwetupizza-delivery-map').length === 0) {
            $('.kwetupizza-tracking-results').append('<div id="kwetupizza-delivery-map" class="kwetupizza-delivery-map"></div>');
        }
        
        // Initialize map
        const map = new google.maps.Map(document.getElementById('kwetupizza-delivery-map'), {
            zoom: 15,
            center: customerCoords,
            mapTypeId: google.maps.MapTypeId.ROADMAP
        });
        
        // Add marker for customer address
        new google.maps.Marker({
            position: customerCoords,
            map: map,
            icon: {
                url: 'https://maps.google.com/mapfiles/ms/icons/red-dot.png',
                scaledSize: new google.maps.Size(40, 40)
            },
            title: 'Delivery Address'
        });
        
        // Add marker for delivery vehicle
        const deliveryMarker = new google.maps.Marker({
            position: deliveryCoords,
            map: map,
            icon: {
                url: 'https://maps.google.com/mapfiles/ms/icons/green-dot.png',
                scaledSize: new google.maps.Size(40, 40)
            },
            title: 'Delivery Vehicle'
        });
        
        // Draw route
        const directionsService = new google.maps.DirectionsService();
        const directionsRenderer = new google.maps.DirectionsRenderer({
            map: map,
            suppressMarkers: true,
            polylineOptions: {
                strokeColor: '#FF5722',
                strokeWeight: 5
            }
        });
        
        directionsService.route({
            origin: deliveryCoords,
            destination: customerCoords,
            travelMode: google.maps.TravelMode.DRIVING
        }, function(response, status) {
            if (status === 'OK') {
                directionsRenderer.setDirections(response);
            }
        });
    }
    
    // Format date function
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }
    
    // Format date and time
    function formatDateTime(dateTimeString) {
        const date = new Date(dateTimeString);
        return date.toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    
    // Format currency
    function formatCurrency(amount) {
        if (typeof amount === 'string') {
            return amount;
        }
        return amount.toFixed(2) + ' TZS';
    }
});

// AJAX handler for order tracking
function kwetupizza_track_order_ajax() {
    jQuery(document).ready(function($) {
        // Register AJAX handler
        wp.ajax.add('kwetupizza_track_order', function(data) {
            return $.ajax({
                url: kwetupizza_params.rest_url + 'kwetupizza/v1/track-order/' + data.order_id,
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', kwetupizza_params.nonce);
                },
                data: {
                    phone: data.phone
                }
            });
        });
    });
}

// If WP API is available
if (typeof wp !== 'undefined' && wp.ajax) {
    kwetupizza_track_order_ajax();
} 