<?php
/**
 * Plugin Name:       PayPing Gateway for Paid Memberships Pro
 * Plugin URI:        https://github.com/payping/pmpro-payping
 * Description:       درگاه پرداخت پی‌پینگ برای افزونه Paid Memberships Pro – پشتیبانی کامل از API v3، تومان/ریال، تأیید خودکار تراکنش
 * Version:           1.4.3
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Tested up to:      6.7.2
 * Author:            PayPing Development Team
 * Author URI:        https://payping.ir
 * License:           GPL v3 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       payping-pmpro
 * Domain Path:       /languages
 * Tags:              payping, pmpro, paid memberships pro, درگاه پرداخت, عضویت, پی‌پینگ
 */

defined('ABSPATH') || exit;

// ثابت‌های مسیر
define('PAYPING_PMPRO_BASENAME', plugin_basename(__FILE__));
define('PAYPING_PMPRO_DIR', plugin_dir_path(__FILE__));

// ------------------------------------------------------------------
// 1. بررسی وابستگی به PMPro
// ------------------------------------------------------------------
add_action('admin_init', 'payping_pmpro_check_dependency');
function payping_pmpro_check_dependency() {
    if (!current_user_can('activate_plugins') || !is_admin()) {
        return;
    }

    if (!class_exists('PMProGateway')) {
        if (is_plugin_active(PAYPING_PMPRO_BASENAME)) {
            deactivate_plugins(PAYPING_PMPRO_BASENAME);
            add_action('admin_notices', 'payping_pmpro_missing_notice');
        }
    }
}

function payping_pmpro_missing_notice() {
    echo '<div class="notice notice-error is-dismissible"><p>';
    echo '<strong>' . esc_html__('درگاه پی‌پینگ غیرفعال شد.', 'payping-pmpro') . '</strong><br>';
    echo wp_kses_post(sprintf(
        __('این افزونه نیازمند فعال بودن افزونه <strong>Paid Memberships Pro</strong> است.<br><a href="%s">جستجو و نصب PMPro</a>', 'payping-pmpro'),
        esc_url(admin_url('plugin-install.php?s=paid+memberships+pro&tab=search&type=term'))
    ));
    echo '</p></div>';
}

// ------------------------------------------------------------------
// 2. بارگذاری کلاس درگاه
// ------------------------------------------------------------------
$gateway_file = PAYPING_PMPRO_DIR . 'includes/class-pmpro-payping-gateway.php';
if (file_exists($gateway_file)) {
    require_once $gateway_file;
} else {
    add_action('admin_notices', function() use ($gateway_file) {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('خطای بحرانی: فایل کلاس درگاه یافت نشد → ', 'payping-pmpro') . esc_html($gateway_file);
        echo '</p></div>';
    });
}

// ------------------------------------------------------------------
// 3. ارزهای ایرانی
// ------------------------------------------------------------------
add_filter('pmpro_currencies', 'payping_pmpro_add_iranian_currencies');
function payping_pmpro_add_iranian_currencies($currencies) {
    $currencies['IRT'] = [
        'name'                => __('تومان ایران', 'payping-pmpro'),
        'symbol'              => 'تومان',
        'position'            => 'left',
        'decimals'            => 0,
        'thousands_separator'=> ',',
        'decimal_separator'   => '.'
    ];
    $currencies['IRR'] = [
        'name'                => __('ریال ایران', 'payping-pmpro'),
        'symbol'              => 'ریال',
        'position'            => 'left',
        'decimals'            => 0,
        'thousands_separator'=> ',',
        'decimal_separator'   => '.'
    ];
    return $currencies;
}

// ------------------------------------------------------------------
// 4. بارگذاری ترجمه
// ------------------------------------------------------------------
add_action('init', 'payping_pmpro_load_textdomain');
function payping_pmpro_load_textdomain() {
    load_plugin_textdomain('payping-pmpro', false, dirname(PAYPING_PMPRO_BASENAME) . '/languages');
}

// ------------------------------------------------------------------
// 5. ثبت درگاه – مهم‌ترین قسمت! (باید زودتر از plugins_loaded اجرا شود)
// ------------------------------------------------------------------

// ثبت کلاس درگاه (این فیلتر باید قبل از لود PMPro اعمال شود)
add_filter('pmpro_gateways_classes', function($classes) {
    if (class_exists('PMProGateway_payping')) {
        $classes['payping'] = 'PMProGateway_payping';
    }
    return $classes;
});

// اجرای init کلاس درگاه (بعد از ثبت کلاس)
add_action('plugins_loaded', function() {
    if (class_exists('PMProGateway') && class_exists('PMProGateway_payping') && method_exists('PMProGateway_payping', 'init')) {
        PMProGateway_payping::init();
    }
}, 15); // زودتر از 20
