<?php
// Rollback script to revert interactive button changes
// To run: php rollback-interactive-buttons.php

// Make sure we're in the plugin directory
chdir(dirname(__FILE__));

// Define ABSPATH to prevent direct access checks from failing
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(dirname(dirname(dirname(__FILE__)))) . '/');
}

// Include the functions update file for rollback function
require_once 'functions-update.php';

echo "Starting rollback of KwetuPizza function updates...\n";

// Check for function backups
$functions_file = 'includes/functions.php';
$functions_backup = 'includes/functions.php.bak';

if (file_exists($functions_backup)) {
    // Copy the backup over the modified file
    if (copy($functions_backup, $functions_file)) {
        echo "Successfully restored functions.php from backup.\n";
    } else {
        echo "Error: Failed to restore functions.php. Please manually copy $functions_backup to $functions_file.\n";
    }
} else {
    echo "Warning: No backup found for functions.php.\n";
}

// Check for WhatsApp handler backups
$handler_file = 'includes/whatsapp-handler.php';
$handler_backup = 'includes/whatsapp-handler.php.bak';

if (file_exists($handler_backup)) {
    // Copy the backup over the modified file
    if (copy($handler_backup, $handler_file)) {
        echo "Successfully restored whatsapp-handler.php from backup.\n";
    } else {
        echo "Error: Failed to restore whatsapp-handler.php. Please manually copy $handler_backup to $handler_file.\n";
    }
} else {
    echo "Warning: No backup found for whatsapp-handler.php.\n";
}

echo "\nRollback complete! The plugin has been restored to its previous state.\n"; 