<?php
/**
 * Plugin Name: PayGuard Payment Gateway
 * Plugin URI:  https://app.sourcemonkey.online
 * Description: Accept bKash, Nagad, TAP and more via PayGuard — zero commission payment gateway for Bangladesh.
 * Version:     1.0.0
 * Author:      Md. Sanaullah Asif
 * Author URI:  https://app.sourcemonkey.online
 * License:     GPL-2.0+
 * Text Domain: payguard
 * Domain Path: /languages
 *
 * WC requires at least: 6.0
 * WC tested up to:      8.0
 */

if (! defined('ABSPATH')) {
    exit;
}

define('PAYGUARD_VERSION',     '1.0.0');
define('PAYGUARD_PLUGIN_DIR',  plugin_dir_path(__FILE__));
define('PAYGUARD_PLUGIN_URL',  plugin_dir_url(__FILE__));

/**
 * Check WooCommerce is active before loading.
 */
add_action('plugins_loaded', function () {
    if (! class_exists('WC_Payment_Gateway')) {
        add_action('admin_notices', function () {
            echo '<div class="error"><p><strong>PayGuard</strong> requires WooCommerce to be installed and active.</p></div>';
        });
        return;
    }

    require_once PAYGUARD_PLUGIN_DIR . 'includes/class-payguard-gateway.php';
    require_once PAYGUARD_PLUGIN_DIR . 'includes/class-payguard-ipn-handler.php';

    // Register gateway
    add_filter('woocommerce_payment_gateways', function ($gateways) {
        $gateways[] = 'WC_PayGuard_Gateway';
        return $gateways;
    });
});

/**
 * Register IPN webhook route.
 * URL: /wp-json/payguard/v1/ipn
 */
add_action('rest_api_init', function () {
    register_rest_route('payguard/v1', '/ipn', [
        'methods'             => 'POST',
        'callback'            => ['WC_PayGuard_IPN_Handler', 'handle'],
        'permission_callback' => '__return_true',
    ]);
});

/**
 * Add plugin settings link.
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    $links[] = '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=payguard') . '">Settings</a>';
    return $links;
});