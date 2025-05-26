<?php
/*
Plugin Name: PayPing for Paid Memberships Pro
Version: 1.0.1
Description: افزونه درگاه پرداخت پی‌پینگ برای Paid Memberships Pro
Plugin URI: https://www.payping.ir/
Author: hadihosseini
Author URI: https://payping.ir/
*/

// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}
register_activation_hook(__FILE__, 'check_pmpro_dependency');

function check_pmpro_dependency() {
    require_once(ABSPATH . 'wp-admin/includes/plugin.php');

    if (!is_plugin_active('paid-memberships-pro/paid-memberships-pro.php')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            __('این افزونه نیازمند افزونه Paid Memberships Pro است. لطفاً ابتدا آن را نصب و فعال کنید.', 'text-domain') . 
            '<br><a href="' . admin_url('plugins.php') . '">' . __('بازگشت به صفحه افزونه‌ها', 'text-domain') . '</a>'
        );
    }
}

add_action('admin_init', 'pmpro_dependency_check_runtime');

function pmpro_dependency_check_runtime() {
    if (!is_plugin_active('paid-memberships-pro/paid-memberships-pro.php')) {
        if (is_plugin_active(plugin_basename(__FILE__))) {
            deactivate_plugins(plugin_basename(__FILE__));
            add_action('admin_notices', 'pmpro_missing_notice');
            if (isset($_GET['activate'])) {
                unset($_GET['activate']);
            }
        }
    }
}

function pmpro_missing_notice() {
    ?>
    <div class="notice notice-error is-dismissible">
        <p>
            <?php _e('افزونه شما به دلیل عدم وجود افزونه Paid Memberships Pro غیرفعال شد. لطفاً آن را نصب و فعال کنید.', 'text-domain'); ?>
        </p>
    </div>
    <?php
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
