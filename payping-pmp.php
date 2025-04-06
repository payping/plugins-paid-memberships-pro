<?php
/*
Plugin Name: PayPing for Paid Memberships Pro
Version: 1.0.0
Description: افزونه درگاه پرداخت پی‌پینگ برای Paid Memberships Pro
Plugin URI: https://www.payping.ir/
Author: hadihosseini
Author URI: https://payping.ir/
*/

// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

// Include the gateway class
require_once(plugin_dir_path(__FILE__) . 'includes/class-pmpro-payping-gateway.php');

// Initialize the plugin
add_action('plugins_loaded', 'load_payping_pmpro_class', 11);
add_action('plugins_loaded', ['PMProGateway_payping', 'init'], 12);

// Add Iranian currencies
add_filter('pmpro_currencies', 'pmpro_add_currency');
function pmpro_add_currency($currencies) {
    $currencies['IRT'] =  array(
        'name' => 'تومان',
        'symbol' => ' تومان ',
        'position' => 'left'
    );
    $currencies['IRR'] = array(
        'name' => 'ریال',
        'symbol' => ' ریال ',
        'position' => 'left'
    );
    return $currencies;
}

function load_payping_pmpro_class() {
    if (class_exists('PMProGateway')) {
        // Class will be loaded from includes/class-pmpro-payping-gateway.php
        return true;
    }
    return false;
}
