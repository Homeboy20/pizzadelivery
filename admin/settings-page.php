<?php
// Add the settings page for KwetuPizza
function kwetupizza_render_settings_page() {
    // Process form submissions for each integration
    if (isset($_POST['save_restaurant_config'])) {
        update_option('kwetupizza_location', sanitize_text_field($_POST['kwetupizza_location']));
        update_option('kwetupizza_currency', sanitize_text_field($_POST['kwetupizza_currency']));
        update_option('kwetupizza_delivery_area', sanitize_text_field($_POST['kwetupizza_delivery_area']));
        update_option('kwetupizza_admin_whatsapp', sanitize_text_field($_POST['kwetupizza_admin_whatsapp']));
        update_option('kwetupizza_admin_sms', sanitize_text_field($_POST['kwetupizza_admin_sms']));
        echo '<div class="notice notice-success is-dismissible"><p>Restaurant configurations saved successfully!</p></div>';
    }
    
    if (isset($_POST['save_whatsapp'])) {
        update_option('kwetupizza_whatsapp_token', sanitize_text_field($_POST['kwetupizza_whatsapp_token']));
        update_option('kwetupizza_whatsapp_phone_id', sanitize_text_field($_POST['kwetupizza_whatsapp_phone_id']));
        update_option('kwetupizza_whatsapp_verify_token', sanitize_text_field($_POST['kwetupizza_whatsapp_verify_token']));
        echo '<div class="notice notice-success is-dismissible"><p>WhatsApp settings saved successfully!</p></div>';
    }
    
    if (isset($_POST['save_flutterwave'])) {
        update_option('kwetupizza_flutterwave_public_key', sanitize_text_field($_POST['kwetupizza_flutterwave_public_key']));
        update_option('kwetupizza_flutterwave_secret_key', sanitize_text_field($_POST['kwetupizza_flutterwave_secret_key']));
        update_option('kwetupizza_flutterwave_encryption_key', sanitize_text_field($_POST['kwetupizza_flutterwave_encryption_key']));
        update_option('kwetupizza_flw_webhook_secret', sanitize_text_field($_POST['kwetupizza_flw_webhook_secret']));
        echo '<div class="notice notice-success is-dismissible"><p>Flutterwave settings saved successfully!</p></div>';
    }
    
    if (isset($_POST['save_nextsms'])) {
        update_option('kwetupizza_nextsms_username', sanitize_text_field($_POST['kwetupizza_nextsms_username']));
        update_option('kwetupizza_nextsms_password', sanitize_text_field($_POST['kwetupizza_nextsms_password']));
        update_option('kwetupizza_nextsms_sender_id', sanitize_text_field($_POST['kwetupizza_nextsms_sender_id']));
        echo '<div class="notice notice-success is-dismissible"><p>NextSMS settings saved successfully!</p></div>';
    }
    
    // Get active tab from URL or set default
    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'restaurant';
    ?>
    <div class="wrap">
        <h1>KwetuPizza Plugin Settings</h1>
        
    <style>
        /* Basic styling for two-column layout */
        .kwetu-settings-container {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .kwetu-settings-left, .kwetu-settings-right {
            width: 48%;
            background: #fff;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-radius: 4px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .kwetu-settings-left, .kwetu-settings-right {
                width: 100%;
            }
        }

        /* Styling for tabs in the right column */
        .kwetu-settings-right .nav-tabs {
            display: flex;
            border-bottom: 2px solid #ddd;
            margin: 0;
            padding: 0;
            margin-bottom: 15px;
        }

        .kwetu-settings-right .nav-tabs li {
            margin-right: 10px;
            list-style: none;
            margin-bottom: -2px;
        }

        .kwetu-settings-right .nav-tabs li a {
            display: block;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-bottom: none;
            text-decoration: none;
            background-color: #f1f1f1;
            color: #444;
            font-weight: 500;
            border-radius: 4px 4px 0 0;
        }

        .kwetu-settings-right .nav-tabs li.active a {
            background-color: #fff;
            border-bottom: 2px solid #fff;
            color: #0073aa;
        }

        .kwetu-settings-right .tab-content {
            padding: 15px;
            border: 1px solid #ddd;
            border-top: none;
            background-color: #fff;
        }
        
        .kwetu-settings-right .tab-pane {
            display: none;
        }
        
        .kwetu-settings-right .tab-pane.active {
            display: block;
        }
        
        /* Form styling */
        .form-table th {
            width: 30%;
        }
        
        .form-table input[type="text"],
        .form-table input[type="password"] {
            width: 100%;
            max-width: 400px;
        }
        
        .submit-btn {
            margin-top: 15px;
        }
        
        /* Main tabs styling */
        .nav-tab-wrapper {
            margin-bottom: 20px;
        }

        /* Test button styling */
        .test-button-container {
            display: flex;
            align-items: center;
            margin-top: 10px;
        }
        
        .test-result {
            margin-top: 10px;
            padding: 8px;
            border-radius: 4px;
        }
        
        .test-result.success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .test-result.error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .test-result.info {
            background-color: #e2e3e5;
            border: 1px solid #d6d8db;
            color: #383d41;
        }
    </style>

        <!-- Main tabs -->
        <h2 class="nav-tab-wrapper">
            <a href="?page=kwetupizza-settings&tab=restaurant" class="nav-tab <?php echo $active_tab == 'restaurant' ? 'nav-tab-active' : ''; ?>">Restaurant Config</a>
            <a href="?page=kwetupizza-settings&tab=integrations" class="nav-tab <?php echo $active_tab == 'integrations' ? 'nav-tab-active' : ''; ?>">Integrations</a>
        </h2>
        
        <?php if ($active_tab == 'restaurant'): ?>
            <!-- Restaurant Configuration -->
            <form method="post" action="">
            <table class="form-table">
                <tr>
                    <th scope="row">Restaurant Location</th>
                        <td><input type="text" name="kwetupizza_location" value="<?php echo esc_attr(get_option('kwetupizza_location')); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row">Base Currency</th>
                        <td><input type="text" name="kwetupizza_currency" value="<?php echo esc_attr(get_option('kwetupizza_currency')); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row">Google Delivery Area</th>
                        <td><input type="text" name="kwetupizza_delivery_area" value="<?php echo esc_attr(get_option('kwetupizza_delivery_area')); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Admin WhatsApp Number</th>
                        <td>
                            <input type="text" name="kwetupizza_admin_whatsapp" id="admin_whatsapp" value="<?php echo esc_attr(get_option('kwetupizza_admin_whatsapp')); ?>" placeholder="255XXXXXXXXX" />
                            <p class="description">Enter number with country code (e.g., 255XXXXXXXXX) for order notifications via WhatsApp</p>
                            <div class="test-button-container">
                                <button type="button" id="test_whatsapp" class="button">Send Test Message</button>
                            </div>
                            <div id="whatsapp_test_result"></div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Admin SMS Number</th>
                        <td>
                            <input type="text" name="kwetupizza_admin_sms" id="admin_sms" value="<?php echo esc_attr(get_option('kwetupizza_admin_sms')); ?>" placeholder="255XXXXXXXXX" />
                            <p class="description">Enter number with country code (e.g., 255XXXXXXXXX) for SMS notifications</p>
                            <div class="test-button-container">
                                <button type="button" id="test_admin_sms" class="button">Send Test SMS</button>
                            </div>
                            <div id="admin_sms_test_result"></div>
                        </td>
                </tr>
            </table>
                <p class="submit">
                    <input type="submit" name="save_restaurant_config" class="button-primary" value="Save Restaurant Settings" />
                </p>
            </form>
        <?php elseif ($active_tab == 'integrations'): ?>
            <div class="kwetu-settings-container">
        <!-- Right Column: Integrations with Tabs -->
                <div class="kwetu-settings-right" style="width: 100%;">
            <h2>Integrations</h2>

            <ul class="nav-tabs">
                <li class="active"><a href="#whatsapp-tab">WhatsApp Provider</a></li>
                <li><a href="#payment-tab">Payment Gateway</a></li>
                        <li><a href="#sms-tab">Bulk SMS (NextSMS)</a></li>
            </ul>

            <div class="tab-content">
                <!-- WhatsApp Cloud API Integration -->
                <div id="whatsapp-tab" class="tab-pane active">
                    <h3>WhatsApp Cloud API Integration</h3>
                            <form method="post" action="">
                    <table class="form-table">
                        <tr>
                            <th scope="row">Access Token</th>
                                        <td><input type="text" name="kwetupizza_whatsapp_token" value="<?php echo esc_attr(get_option('kwetupizza_whatsapp_token')); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row">Phone ID</th>
                                        <td><input type="text" name="kwetupizza_whatsapp_phone_id" value="<?php echo esc_attr(get_option('kwetupizza_whatsapp_phone_id')); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row">Verify Token</th>
                                        <td><input type="text" name="kwetupizza_whatsapp_verify_token" value="<?php echo esc_attr(get_option('kwetupizza_whatsapp_verify_token')); ?>" /></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Test WhatsApp API</th>
                                        <td>
                                            <div style="display: flex; max-width: 400px; margin-bottom: 10px;">
                                                <input type="text" id="test_whatsapp_number" placeholder="Enter phone number (255XXXXXXXXX)" style="flex-grow: 1; margin-right: 10px;" />
                                                <button type="button" id="send_test_whatsapp" class="button">Send Test</button>
                                            </div>
                                            <p class="description">Send a test WhatsApp message to verify your configuration</p>
                                            <div id="test_whatsapp_result"></div>
                                        </td>
                        </tr>
                    </table>
                                <p class="submit">
                                    <input type="submit" name="save_whatsapp" class="button-primary" value="Save WhatsApp Settings" />
                                </p>
                            </form>
                </div>

                <!-- Flutterwave Payment Integration -->
                <div id="payment-tab" class="tab-pane">
                    <h3>Flutterwave Payment Gateway</h3>
                            <form method="post" action="">
                    <table class="form-table">
                        <tr>
                            <th scope="row">Public Key</th>
                                        <td><input type="text" name="kwetupizza_flutterwave_public_key" value="<?php echo esc_attr(get_option('kwetupizza_flutterwave_public_key')); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row">Secret Key</th>
                                        <td><input type="text" name="kwetupizza_flutterwave_secret_key" value="<?php echo esc_attr(get_option('kwetupizza_flutterwave_secret_key')); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row">Encryption Key</th>
                                        <td><input type="text" name="kwetupizza_flutterwave_encryption_key" value="<?php echo esc_attr(get_option('kwetupizza_flutterwave_encryption_key')); ?>" /></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Webhook Secret</th>
                                        <td>
                                            <input type="text" name="kwetupizza_flw_webhook_secret" value="<?php echo esc_attr(get_option('kwetupizza_flw_webhook_secret')); ?>" />
                                            <p class="description">This is used to verify webhook calls from Flutterwave</p>
                                        </td>
                        </tr>
                    </table>
                                <p class="submit">
                                    <input type="submit" name="save_flutterwave" class="button-primary" value="Save Flutterwave Settings" />
                                </p>
                            </form>
                </div>

                        <!-- NextSMS Integration -->
                <div id="sms-tab" class="tab-pane">
                            <h3>NextSMS Integration</h3>
                            <form method="post" action="">
                    <table class="form-table">
                        <tr>
                                        <th scope="row">Username</th>
                                        <td><input type="text" name="kwetupizza_nextsms_username" value="<?php echo esc_attr(get_option('kwetupizza_nextsms_username')); ?>" /></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Password</th>
                                        <td><input type="password" name="kwetupizza_nextsms_password" value="<?php echo esc_attr(get_option('kwetupizza_nextsms_password')); ?>" /></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Sender ID</th>
                                        <td>
                                            <input type="text" name="kwetupizza_nextsms_sender_id" value="<?php echo esc_attr(get_option('kwetupizza_nextsms_sender_id')); ?>" />
                                            <p class="description">The name that will appear as sender of SMS messages (max 11 characters)</p>
                                        </td>
                        </tr>
                        <tr>
                                        <th scope="row">Test SMS API</th>
                                        <td>
                                            <div style="display: flex; max-width: 400px; margin-bottom: 10px;">
                                                <input type="text" id="test_sms_number" placeholder="Enter phone number (255XXXXXXXXX)" style="flex-grow: 1; margin-right: 10px;" />
                                                <button type="button" id="send_test_sms" class="button">Send Test</button>
                                            </div>
                                            <p class="description">Send a test SMS to verify your configuration</p>
                                            <div id="test_sms_result"></div>
                                        </td>
                        </tr>
                    </table>
                                <p class="submit">
                                    <input type="submit" name="save_nextsms" class="button-primary" value="Save NextSMS Settings" />
                                </p>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Tab switching functionality
        $('.kwetu-settings-right .nav-tabs a').on('click', function(e) {
            e.preventDefault();
            
            // Hide all tab panes
            $('.kwetu-settings-right .tab-pane').removeClass('active');
            
            // Show the selected tab pane
            $($(this).attr('href')).addClass('active');
            
            // Update active tab
            $(this).parent().addClass('active').siblings().removeClass('active');
        });
        
        // Test SMS functionality
        $('#send_test_sms').on('click', function() {
            var phoneNumber = $('#test_sms_number').val();
            
            if (!phoneNumber) {
                $('#test_sms_result').html('<div class="test-result error">Please enter a phone number</div>');
                return;
            }
            
            $('#test_sms_result').html('<div class="test-result info">Sending test SMS...</div>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'kwetupizza_test_sms',
                    phone: phoneNumber,
                    nonce: '<?php echo wp_create_nonce('kwetupizza_test_sms'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $('#test_sms_result').html('<div class="test-result success">Test SMS sent successfully!</div>');
                    } else {
                        $('#test_sms_result').html('<div class="test-result error">Error: ' + response.data.message + '</div>');
                    }
                },
                error: function() {
                    $('#test_sms_result').html('<div class="test-result error">Error: Could not send test SMS</div>');
                }
            });
        });
        
        // Test WhatsApp functionality
        $('#send_test_whatsapp').on('click', function() {
            var phoneNumber = $('#test_whatsapp_number').val();
            
            if (!phoneNumber) {
                $('#test_whatsapp_result').html('<div class="test-result error">Please enter a phone number</div>');
                return;
            }
            
            $('#test_whatsapp_result').html('<div class="test-result info">Sending test WhatsApp message...</div>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'kwetupizza_test_whatsapp',
                    phone: phoneNumber,
                    nonce: '<?php echo wp_create_nonce('kwetupizza_test_whatsapp'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $('#test_whatsapp_result').html('<div class="test-result success">Test WhatsApp message sent successfully!</div>');
                    } else {
                        $('#test_whatsapp_result').html('<div class="test-result error">Error: ' + response.data.message + '</div>');
                    }
                },
                error: function() {
                    $('#test_whatsapp_result').html('<div class="test-result error">Error: Could not send test WhatsApp message</div>');
                }
            });
        });
        
        // Test admin SMS
        $('#test_admin_sms').on('click', function() {
            var phoneNumber = $('#admin_sms').val();
            
            if (!phoneNumber) {
                $('#admin_sms_test_result').html('<div class="test-result error">Please enter an admin SMS number</div>');
                return;
            }
            
            $('#admin_sms_test_result').html('<div class="test-result info">Sending test SMS to admin...</div>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'kwetupizza_test_sms',
                    phone: phoneNumber,
                    message: 'This is a test notification for KwetuPizza admin',
                    nonce: '<?php echo wp_create_nonce('kwetupizza_test_sms'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $('#admin_sms_test_result').html('<div class="test-result success">Test SMS sent to admin successfully!</div>');
                    } else {
                        $('#admin_sms_test_result').html('<div class="test-result error">Error: ' + response.data.message + '</div>');
                    }
                },
                error: function() {
                    $('#admin_sms_test_result').html('<div class="test-result error">Error: Could not send test SMS to admin</div>');
                }
            });
        });
        
        // Test admin WhatsApp
        $('#test_whatsapp').on('click', function() {
            var phoneNumber = $('#admin_whatsapp').val();
            
            if (!phoneNumber) {
                $('#whatsapp_test_result').html('<div class="test-result error">Please enter an admin WhatsApp number</div>');
                return;
            }
            
            $('#whatsapp_test_result').html('<div class="test-result info">Sending test WhatsApp message to admin...</div>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'kwetupizza_test_whatsapp',
                    phone: phoneNumber,
                    message: 'This is a test notification for KwetuPizza admin',
                    nonce: '<?php echo wp_create_nonce('kwetupizza_test_whatsapp'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $('#whatsapp_test_result').html('<div class="test-result success">Test WhatsApp message sent to admin successfully!</div>');
                    } else {
                        $('#whatsapp_test_result').html('<div class="test-result error">Error: ' + response.data.message + '</div>');
                    }
                },
                error: function() {
                    $('#whatsapp_test_result').html('<div class="test-result error">Error: Could not send test WhatsApp message to admin</div>');
                }
            });
        });
    });
    </script>
    <?php
}

// Register the settings
function kwetupizza_register_settings() {
    // Restaurant Configurations
    register_setting('kwetupizza_settings_group', 'kwetupizza_location');
    register_setting('kwetupizza_settings_group', 'kwetupizza_currency');
    register_setting('kwetupizza_settings_group', 'kwetupizza_delivery_area');
    register_setting('kwetupizza_settings_group', 'kwetupizza_admin_whatsapp');
    register_setting('kwetupizza_settings_group', 'kwetupizza_admin_sms');

    // WhatsApp Cloud API settings
    register_setting('kwetupizza_settings_group', 'kwetupizza_whatsapp_token');
    register_setting('kwetupizza_settings_group', 'kwetupizza_whatsapp_phone_id');
    register_setting('kwetupizza_settings_group', 'kwetupizza_whatsapp_verify_token');

    // Flutterwave settings
    register_setting('kwetupizza_settings_group', 'kwetupizza_flutterwave_public_key');
    register_setting('kwetupizza_settings_group', 'kwetupizza_flutterwave_secret_key');
    register_setting('kwetupizza_settings_group', 'kwetupizza_flutterwave_encryption_key');
    register_setting('kwetupizza_settings_group', 'kwetupizza_flw_webhook_secret');

    // NextSMS settings
    register_setting('kwetupizza_settings_group', 'kwetupizza_nextsms_username');
    register_setting('kwetupizza_settings_group', 'kwetupizza_nextsms_password');
    register_setting('kwetupizza_settings_group', 'kwetupizza_nextsms_sender_id');
}
add_action('admin_init', 'kwetupizza_register_settings');

// Note: The AJAX handlers for SMS and WhatsApp tests are now defined in includes/functions.php
// to avoid duplicate function declarations
?>
