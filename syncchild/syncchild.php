<?php
/**
 * Plugin Name: SyncChild (Client Site)
 * Description: اتصال به سایت مادر برای همگام‌سازی محصولات، موجودی و سفارش‌ها.
 * Version: 1.0.0
 * Author: Korians
 * Text Domain: Korians.com
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define('SYNCCHILD_PATH', plugin_dir_path(__FILE__));
define('SYNCCHILD_URL', plugin_dir_url(__FILE__));

/**
 * لود کلاس‌ها
 * توجه: require_once ها را اینجا (فایل اصلی پلاگین) نگه دارید، نه داخل کلاس‌ها
 */
require_once SYNCCHILD_PATH . 'includes/class-child-core.php';
require_once SYNCCHILD_PATH . 'includes/class-child-orders.php';
new \SyncChild\Child_Orders();

/**
 * بوت‌استرپ پلاگین
 */
add_action('plugins_loaded', function() {
    if ( class_exists('SyncChild\\Child_Core') ) {
        new SyncChild\Child_Core();
    }
    if ( class_exists('SyncChild\\Child_Orders') ) {
        new SyncChild\Child_Orders();
    }
});
