# KwetuPizza Plugin

A modernized pizza order management system with WhatsApp bot integration, online payments, loyalty program, and customer-facing interfaces.

## Features

### Core Functionality
- **Menu Management**: Create and manage your pizza menu with categories, prices, and images
- **Order Management**: Process and track orders from receipt to delivery
- **Transaction Management**: Track payments and financial records
- **User Management**: Manage customer data and staff accounts

### Customer Interfaces
- **Online Menu**: Beautiful, responsive menu display with online ordering
- **Order Tracking**: Real-time order status tracking system
- **Customer Accounts**: Customer loyalty program and order history
- **WhatsApp Ordering**: Conversational bot for ordering via WhatsApp

### Payment Systems
- **Flutterwave Integration**: Accept online payments through cards and mobile money
- **Cash on Delivery**: Support for traditional payment methods
- **Payment Verification**: Automatic verification and order status updates

### Enhanced Features
- **Loyalty Program**: Points-based system with rewards for repeat customers
- **Delivery Zones**: Define delivery areas with custom fees and time estimates
- **Inventory Tracking**: Monitor ingredient usage and stock levels
- **Analytics Dashboard**: Sales reports and business insights

## Requirements
- WordPress 5.6 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher
- WhatsApp Business API account (for WhatsApp integration)
- Flutterwave account (for online payments)
- NextSMS account (for SMS notifications)

## Installation

1. Upload the `kwetu-pizza-plugin` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to KwetuPizza -> Settings to configure your restaurant details and API keys
4. Set up your menu items, delivery zones, and other settings as needed

## Configuration

### WhatsApp API Setup
1. Create a WhatsApp Business account at [Meta for Developers](https://developers.facebook.com/)
2. Generate a WhatsApp API token and phone number ID
3. Set up a webhook verification token
4. Enter these details in the WhatsApp tab of the plugin settings

### Flutterwave Payment Setup
1. Create a Flutterwave account at [Flutterwave](https://flutterwave.com/)
2. Generate your public and secret keys
3. Enter these details in the Flutterwave tab of the plugin settings
4. Configure your webhook URL in your Flutterwave dashboard

### NextSMS Setup
1. Create a NextSMS account at [NextSMS](https://nextsms.co.tz/)
2. Generate your API credentials
3. Enter these details in the NextSMS tab of the plugin settings

## Usage

### Creating Menu Items
1. Navigate to KwetuPizza -> Menu Management
2. Click "Add New Item"
3. Fill in the details including name, description, price, and category
4. Upload an image for the menu item
5. Click "Save"

### Setting Up Delivery Zones
1. Navigate to KwetuPizza -> Delivery Zones
2. Click "Add New Zone"
3. Enter a name and description for the zone
4. Set delivery fees and estimated delivery times
5. Draw the zone boundaries on the map
6. Click "Save Zone"

### Managing Orders
1. Orders are automatically created when customers place them via the website or WhatsApp
2. Navigate to KwetuPizza -> Order Management to view all orders
3. Click on an order to view details or update its status
4. Use the status dropdown to update the order progress

### Viewing Analytics
1. Navigate to KwetuPizza -> Dashboard
2. View sales data, popular products, and transaction volumes
3. Use the date filters to analyze specific time periods
4. Export reports as needed

## Shortcodes

The plugin provides several shortcodes for integration with your WordPress site:

- `[kwetupizza_menu]` - Displays your menu with online ordering functionality
- `[kwetupizza_order_tracking]` - Provides an order tracking interface for customers
- `[kwetupizza_customer_account]` - Displays the customer account and loyalty program interface

## Development

### File Structure
- `kwetu-pizza-plugin.php` - Main plugin file
- `includes/functions.php` - Core functions and utilities
- `includes/api-controller.php` - REST API endpoints
- `includes/shortcodes.php` - Front-end shortcodes
- `includes/whatsapp-handler.php` - WhatsApp bot functionality
- `admin/` - Admin interface files
- `assets/` - CSS, JS, and image files

### Extending the Plugin
Custom functionality can be added through WordPress hooks and filters. Key hooks include:

- `kwetupizza_before_order_create` - Action before a new order is created
- `kwetupizza_after_order_status_change` - Action after an order status is updated
- `kwetupizza_payment_success` - Action after payment is successfully completed
- `kwetupizza_get_menu_items` - Filter to modify menu items
- `kwetupizza_loyalty_points_calculation` - Filter to customize loyalty points calculation

## Support
For support inquiries, please contact [your email or support URL]. 