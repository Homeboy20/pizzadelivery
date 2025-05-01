<?php
/**
 * KwetuPizza Delivery Zones Management
 * 
 * Handles the delivery zones management interface.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the delivery zones management page
 */
function kwetupizza_render_delivery_zones() {
    global $wpdb;
    $zones_table = $wpdb->prefix . 'kwetupizza_delivery_zones';
    
    // Handle form submissions
    if (isset($_POST['submit_zone']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'kwetupizza_save_zone')) {
        $zone_id = isset($_POST['zone_id']) ? intval($_POST['zone_id']) : 0;
        $zone_name = sanitize_text_field($_POST['zone_name']);
        $description = sanitize_textarea_field($_POST['description']);
        $coordinates = sanitize_textarea_field($_POST['coordinates']);
        $delivery_fee = floatval($_POST['delivery_fee']);
        $min_delivery_time = intval($_POST['min_delivery_time']);
        $max_delivery_time = intval($_POST['max_delivery_time']);
        
        if ($zone_id > 0) {
            // Update existing zone
            $wpdb->update(
                $zones_table,
                array(
                    'zone_name' => $zone_name,
                    'description' => $description,
                    'coordinates' => $coordinates,
                    'delivery_fee' => $delivery_fee,
                    'min_delivery_time' => $min_delivery_time,
                    'max_delivery_time' => $max_delivery_time,
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $zone_id)
            );
            
            echo '<div class="notice notice-success"><p>Delivery zone updated successfully!</p></div>';
        } else {
            // Add new zone
            $wpdb->insert(
                $zones_table,
                array(
                    'zone_name' => $zone_name,
                    'description' => $description,
                    'coordinates' => $coordinates,
                    'delivery_fee' => $delivery_fee,
                    'min_delivery_time' => $min_delivery_time,
                    'max_delivery_time' => $max_delivery_time,
                    'created_at' => current_time('mysql')
                )
            );
            
            echo '<div class="notice notice-success"><p>Delivery zone added successfully!</p></div>';
        }
    } elseif (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['zone_id']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_zone_' . $_GET['zone_id'])) {
        $zone_id = intval($_GET['zone_id']);
        $wpdb->delete($zones_table, array('id' => $zone_id));
        
        echo '<div class="notice notice-success"><p>Delivery zone deleted successfully!</p></div>';
    }
    
    // Get all zones
    $zones = $wpdb->get_results("SELECT * FROM $zones_table ORDER BY zone_name");
    
    // Check if editing a zone
    $editing = false;
    $zone_to_edit = null;
    
    if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['zone_id'])) {
        $zone_id = intval($_GET['zone_id']);
        $zone_to_edit = $wpdb->get_row($wpdb->prepare("SELECT * FROM $zones_table WHERE id = %d", $zone_id));
        
        if ($zone_to_edit) {
            $editing = true;
        }
    }
    
    // Include Google Maps API
    wp_enqueue_script('google-maps', 'https://maps.googleapis.com/maps/api/js?key=' . get_option('kwetupizza_google_maps_key', '') . '&libraries=drawing', array(), null, true);
    
    // Load custom admin script for mapping
    wp_enqueue_script('kwetupizza-admin-maps', KWETUPIZZA_PLUGIN_URL . 'assets/js/admin-maps.js', array('jquery', 'google-maps'), KWETUPIZZA_VERSION, true);
    ?>
    <div class="wrap">
        <h1><?php echo $editing ? 'Edit Delivery Zone' : 'Add New Delivery Zone'; ?></h1>
        
        <div class="kwetupizza-admin-content">
            <div class="kwetupizza-admin-left">
                <form method="post" action="">
                    <?php wp_nonce_field('kwetupizza_save_zone'); ?>
                    
                    <?php if ($editing) : ?>
                        <input type="hidden" name="zone_id" value="<?php echo esc_attr($zone_to_edit->id); ?>">
                    <?php endif; ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="zone_name">Zone Name</label></th>
                            <td>
                                <input type="text" id="zone_name" name="zone_name" class="regular-text" 
                                    value="<?php echo $editing ? esc_attr($zone_to_edit->zone_name) : ''; ?>" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="description">Description</label></th>
                            <td>
                                <textarea id="description" name="description" rows="3" class="regular-text"><?php 
                                    echo $editing ? esc_textarea($zone_to_edit->description) : ''; 
                                ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="delivery_fee">Delivery Fee (<?php echo esc_html(get_option('kwetupizza_currency', 'TZS')); ?>)</label></th>
                            <td>
                                <input type="number" id="delivery_fee" name="delivery_fee" step="0.01" min="0" 
                                    value="<?php echo $editing ? esc_attr($zone_to_edit->delivery_fee) : '0'; ?>" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="min_delivery_time">Min Delivery Time (minutes)</label></th>
                            <td>
                                <input type="number" id="min_delivery_time" name="min_delivery_time" min="5" 
                                    value="<?php echo $editing ? esc_attr($zone_to_edit->min_delivery_time) : '30'; ?>" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="max_delivery_time">Max Delivery Time (minutes)</label></th>
                            <td>
                                <input type="number" id="max_delivery_time" name="max_delivery_time" min="5" 
                                    value="<?php echo $editing ? esc_attr($zone_to_edit->max_delivery_time) : '60'; ?>" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="coordinates">Map Coordinates</label></th>
                            <td>
                                <textarea id="coordinates" name="coordinates" rows="4" class="large-text" readonly><?php 
                                    echo $editing ? esc_textarea($zone_to_edit->coordinates) : ''; 
                                ?></textarea>
                                <p class="description">Use the map below to draw the delivery zone area. The coordinates will be automatically populated.</p>
                            </td>
                        </tr>
                    </table>
                    
                    <div id="kwetupizza-map-container" style="height: 400px; margin-bottom: 20px;"></div>
                    
                    <div class="kwetupizza-map-controls">
                        <button type="button" id="clear-map" class="button">Clear Map</button>
                        <p class="description">Click the polygon tool in the map controls to draw your delivery zone.</p>
                    </div>
                    
                    <p class="submit">
                        <input type="submit" name="submit_zone" class="button button-primary" value="<?php echo $editing ? 'Update Zone' : 'Add Zone'; ?>">
                        <?php if ($editing) : ?>
                            <a href="<?php echo admin_url('admin.php?page=kwetupizza-delivery-zones'); ?>" class="button">Cancel</a>
                        <?php endif; ?>
                    </p>
                </form>
            </div>
            
            <div class="kwetupizza-admin-right">
                <h2>Existing Delivery Zones</h2>
                
                <?php if (!empty($zones)) : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Zone Name</th>
                                <th>Delivery Fee</th>
                                <th>Delivery Time</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($zones as $zone) : ?>
                                <tr>
                                    <td><?php echo esc_html($zone->zone_name); ?></td>
                                    <td><?php echo kwetupizza_format_currency($zone->delivery_fee); ?></td>
                                    <td><?php echo esc_html($zone->min_delivery_time . '-' . $zone->max_delivery_time . ' mins'); ?></td>
                                    <td>
                                        <a href="<?php echo add_query_arg(array('action' => 'edit', 'zone_id' => $zone->id), admin_url('admin.php?page=kwetupizza-delivery-zones')); ?>" class="button button-small">Edit</a>
                                        <a href="<?php echo wp_nonce_url(add_query_arg(array('action' => 'delete', 'zone_id' => $zone->id), admin_url('admin.php?page=kwetupizza-delivery-zones')), 'delete_zone_' . $zone->id); ?>" class="button button-small" onclick="return confirm('Are you sure you want to delete this zone?');">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p>No delivery zones defined yet. Create your first zone using the form.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Initialize the map when the admin-maps.js is loaded
        $(document).on('kwetupizza_maps_loaded', function() {
            <?php if ($editing && !empty($zone_to_edit->coordinates)) : ?>
            var existingCoordinates = <?php echo json_encode($zone_to_edit->coordinates); ?>;
            initializeMapWithExistingZone('kwetupizza-map-container', 'coordinates', existingCoordinates);
            <?php else : ?>
            initializeMap('kwetupizza-map-container', 'coordinates');
            <?php endif; ?>
        });
        
        // Clear map button
        $('#clear-map').on('click', function() {
            clearMap();
            $('#coordinates').val('');
        });
    });
    </script>
    
    <style>
    .kwetupizza-admin-content {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
    }
    
    .kwetupizza-admin-left {
        flex: 1;
        min-width: 500px;
    }
    
    .kwetupizza-admin-right {
        flex: 1;
        min-width: 300px;
    }
    
    #kwetupizza-map-container {
        border: 1px solid #ddd;
        margin-top: 10px;
    }
    
    .kwetupizza-map-controls {
        margin-top: 10px;
        margin-bottom: 20px;
    }
    </style>
    <?php
}

/**
 * AJAX handler to check if an address is within a delivery zone
 */
function kwetupizza_check_delivery_zone() {
    check_ajax_referer('kwetupizza-nonce', 'nonce');
    
    $address = isset($_POST['address']) ? sanitize_text_field($_POST['address']) : '';
    
    if (empty($address)) {
        wp_send_json_error('Address is required');
        return;
    }
    
    // Get geocoding data for the address
    $geocoding_result = kwetupizza_geocode_address($address);
    
    if (!$geocoding_result) {
        wp_send_json_error('Could not geocode the address');
        return;
    }
    
    $lat = $geocoding_result['lat'];
    $lng = $geocoding_result['lng'];
    
    // Check if the coordinates are within any delivery zone
    global $wpdb;
    $zones_table = $wpdb->prefix . 'kwetupizza_delivery_zones';
    $zones = $wpdb->get_results("SELECT * FROM $zones_table");
    
    $in_zone = false;
    $zone_data = null;
    
    foreach ($zones as $zone) {
        $coordinates = json_decode($zone->coordinates, true);
        
        if (kwetupizza_point_in_polygon($lat, $lng, $coordinates)) {
            $in_zone = true;
            $zone_data = array(
                'zone_name' => $zone->zone_name,
                'delivery_fee' => $zone->delivery_fee,
                'min_delivery_time' => $zone->min_delivery_time,
                'max_delivery_time' => $zone->max_delivery_time
            );
            break;
        }
    }
    
    if ($in_zone) {
        wp_send_json_success($zone_data);
    } else {
        wp_send_json_error('Address is outside our delivery zones');
    }
}
add_action('wp_ajax_kwetupizza_check_delivery_zone', 'kwetupizza_check_delivery_zone');
add_action('wp_ajax_nopriv_kwetupizza_check_delivery_zone', 'kwetupizza_check_delivery_zone');

/**
 * Geocode an address using Google Maps API
 */
function kwetupizza_geocode_address($address) {
    $api_key = get_option('kwetupizza_google_maps_key', '');
    
    if (empty($api_key)) {
        return false;
    }
    
    $url = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($address) . '&key=' . $api_key;
    
    $response = wp_remote_get($url);
    
    if (is_wp_error($response)) {
        return false;
    }
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    if ($body['status'] === 'OK' && !empty($body['results'][0]['geometry']['location'])) {
        return array(
            'lat' => $body['results'][0]['geometry']['location']['lat'],
            'lng' => $body['results'][0]['geometry']['location']['lng']
        );
    }
    
    return false;
}

/**
 * Check if a point is inside a polygon (ray casting algorithm)
 */
function kwetupizza_point_in_polygon($lat, $lng, $polygon) {
    $vertices = count($polygon);
    $inside = false;
    
    for ($i = 0, $j = $vertices - 1; $i < $vertices; $j = $i++) {
        $xi = $polygon[$i]['lat'];
        $yi = $polygon[$i]['lng'];
        $xj = $polygon[$j]['lat'];
        $yj = $polygon[$j]['lng'];
        
        $intersect = (($yi > $lng) != ($yj > $lng)) && ($lat < ($xj - $xi) * ($lng - $yi) / ($yj - $yi) + $xi);
        
        if ($intersect) {
            $inside = !$inside;
        }
    }
    
    return $inside;
} 