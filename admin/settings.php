// Add PayPal settings section
$paypal_section = array(
    'title' => 'PayPal Settings',
    'fields' => array(
        array(
            'id' => 'kwetupizza_paypal_client_id',
            'title' => 'PayPal Client ID',
            'type' => 'text',
            'placeholder' => 'Enter your PayPal Client ID',
            'description' => 'Client ID from your PayPal Developer account.'
        ),
        array(
            'id' => 'kwetupizza_paypal_secret',
            'title' => 'PayPal Secret',
            'type' => 'password',
            'placeholder' => 'Enter your PayPal Secret',
            'description' => 'Secret key from your PayPal Developer account.'
        ),
        array(
            'id' => 'kwetupizza_paypal_sandbox',
            'title' => 'Sandbox Mode',
            'type' => 'checkbox',
            'label' => 'Enable PayPal Sandbox (test) mode',
            'description' => 'Check this to use PayPal Sandbox for testing.'
        )
    )
);

// Add PayPal section to settings
$kwetupizza_settings_sections['paypal_settings'] = $paypal_section; 