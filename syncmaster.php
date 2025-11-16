<?php
/**
 * Plugin Name: SyncMaster (Mother Site)
 * Description: مدیریت و ارسال خودکار محصولات و موجودی به سایت‌های فرزند.
 * Version: 1.0.0
 * Author: Denni Miner
 * Text Domain: syncmaster
 */

if ( ! defined('ABSPATH') ) exit;

define('SYNCMASTER_PATH', plugin_dir_path(__FILE__));
define('SYNCMASTER_URL', plugin_dir_url(__FILE__));

require_once SYNCMASTER_PATH . 'includes/class-master-core.php';
require_once SYNCMASTER_PATH . 'includes/class-master-orders.php';
new \SyncMaster\Master_Orders();
require_once SYNCMASTER_PATH . 'includes/class-master-order-sync.php';
new \SyncMaster\Master_Order_Sync();


add_action('plugins_loaded', function() {
    if ( class_exists('SyncMaster\\Master_Core') ) {
        new SyncMaster\Master_Core();
    }
});
add_action('admin_enqueue_scripts', function() {
    wp_enqueue_script(
        'syncmaster-admin',
        plugin_dir_url(__FILE__) . 'assets/admin.js',
        ['jquery'],
        '1.0',
        true
    );
    wp_localize_script('syncmaster-admin', 'syncmaster_admin', [
        'nonce' => wp_create_nonce('syncmaster_nonce')
    ]);
});
