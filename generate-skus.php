<?php
/*
Plugin Name: SKU Generator for WooCommerce Variations
Description: Generate SKUs for WooCommerce Product variations with Excel export functionality
Version: 1.0.0
Author: Marc Maninang
License: GPL2
Text Domain: sku-generator
Domain Path: /languages
Requires at least: 5.0
Requires PHP: 8.1
WC requires at least: 7.0
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('SKU Generator requires WooCommerce to be installed and active.', 'sku-generator'); ?></p>
        </div>
        <?php
    });
    return;
}

// Define plugin constants
define('SKU_GENERATOR_VERSION', '1.0.0');
define('SKU_GENERATOR_PATH', plugin_dir_path(__FILE__));
define('SKU_GENERATOR_URL', plugin_dir_url(__FILE__));
define('SKU_GENERATOR_MIN_PHP_VERSION', '8.1');
define('SKU_GENERATOR_MIN_WP_VERSION', '5.0');
define('SKU_GENERATOR_MIN_WC_VERSION', '7.0');

// Include required files
require_once SKU_GENERATOR_PATH . 'includes/class-sku-generator.php';
require_once SKU_GENERATOR_PATH . 'admin/sku-generator-page.php';

// Initialize the plugin
add_action('plugins_loaded', 'initialize_sku_generator_plugin');

function initialize_sku_generator_plugin() {
    // Check PHP version
    if (version_compare(PHP_VERSION, SKU_GENERATOR_MIN_PHP_VERSION, '<')) {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p><?php printf(__('SKU Generator requires PHP version %s or higher.', 'sku-generator'), SKU_GENERATOR_MIN_PHP_VERSION); ?></p>
            </div>
            <?php
        });
        return;
    }

    // Initialize main class
    SKU_Generator::get_instance();
}

// Register custom admin page for SKU listing
add_action('admin_menu', 'sku_generator_register_page');

function sku_generator_register_page() {
    add_submenu_page(
        null,
        'SKU Generator',
        'SKU Generator',
        'manage_options',
        'generate_sku_list',
        'sku_generator_page_callback'
    );
}
