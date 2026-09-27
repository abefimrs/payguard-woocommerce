<?php
/**
 * Plugin Name: PayGuard Payment Gateway for WooCommerce
 * Plugin URI:  https://app.sourcemonkey.online
 * Description: Accept bKash, Nagad, Rocket, Upay, Credit/Debit Card and TAP Wallet via PayGuard — zero commission gateway for Bangladesh.
 * Version:     1.0.0
 * Author:      Md. Sanaullah Asif
 * Author URI:  https://github.com/abefimrs
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: payguard-for-woocommerce
 * Domain Path: /languages
 *
 * WC requires at least: 6.0
 * WC tested up to:      9.0
 */

if (! defined('ABSPATH')) exit;

define('PAYGUARD_VERSION',    '1.0.0');
define('PAYGUARD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PAYGUARD_PLUGIN_URL', plugin_dir_url(__FILE__));

// Declare block checkout incompatibility
add_action('before_woocommerce_init', function () {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, false);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Load gateway after WooCommerce is ready
add_action('plugins_loaded', function () {
    if (! class_exists('WC_Payment_Gateway')) {
        add_action('admin_notices', function () {
            echo '<div class="error"><p><strong>PayGuard</strong> requires WooCommerce to be installed and active.</p></div>';
        });
        return;
    }

    require_once PAYGUARD_PLUGIN_DIR . 'includes/class-payguard-gateway.php';
    require_once PAYGUARD_PLUGIN_DIR . 'includes/class-payguard-ipn-handler.php';

    add_filter('woocommerce_payment_gateways', function ($gateways) {
        $gateways[] = 'WC_PayGuard_Gateway';
        return $gateways;
    });
});

// IPN webhook endpoint
add_action('rest_api_init', function () {
    register_rest_route('payguard/v1', '/ipn', [
        'methods'             => 'POST',
        'callback'            => ['WC_PayGuard_IPN_Handler', 'handle'],
        'permission_callback' => '__return_true',
    ]);
});
