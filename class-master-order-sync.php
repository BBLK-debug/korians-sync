<?php
namespace SyncMaster;

if ( ! defined('ABSPATH') ) exit;

class Master_Order_Sync {

    public function __construct() {
        add_action('woocommerce_order_status_changed', [$this, 'sync_status_to_child'], 20, 3);
        add_action('admin_menu', [$this, 'add_child_orders_page']);
        add_action('admin_init', [$this, 'handle_status_update']);
    }

    /**
     * 📤 ارسال وضعیت سفارش از Master به Child
     */
    public function sync_status_to_child($order_id, $old_status, $new_status) {
        $order = wc_get_order($order_id);
        if (!$order) return;

        $child_url = $order->get_meta('_child_site');
        $child_order_id = $order->get_meta('_child_order_id');
        if (! $child_url || ! $child_order_id) return;

        $payload = [
            'order_id' => $child_order_id,
            'status'   => $new_status,
            'master_url' => get_site_url(),
        ];

        $url = trailingslashit($child_url) . 'wp-json/wms/v1/order/status';
        $response = wp_remote_post($url, [
            'timeout' => 10,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($payload)
        ]);

        if (is_wp_error($response)) {
            $order->update_meta_data('_sync_status', '❌ خطا در ارسال وضعیت: '.$response->get_error_message());
            $order->save();
            Logger::add('❌ خطا در ارسال وضعیت سفارش #' . $order_id . ' به Child', 'error');
        } else {
            $order->update_meta_data('_sync_status', '✅ وضعیت همگام‌سازی شد');
            $order->save();
            Logger::add("🔁 وضعیت سفارش #{$order_id} به Child ارسال شد ({$new_status})");
        }
    }

    /**
     * 📋 تب سفارشات فرزند با فیلتر
     */
    public function add_child_orders_page() {
        add_submenu_page(
            'syncmaster-settings',
            'سفارشات فرزندان',
            '📦 سفارشات فرزندان',
            'manage_woocommerce',
            'syncmaster-child-orders',
            [$this, 'render_child_orders_page']
        );
    }

    /**
     * 🧾 نمایش سفارشات Child در جدول با جزئیات کامل
     */
    public function render_child_orders_page() {
        $filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : '';
        $args = [
            'limit' => 100,
            'meta_key' => '_child_site',
            'orderby' => 'date',
            'order' => 'DESC'
        ];
        if ($filter === 'error') $args['meta_value'] = '❌';
        $orders = wc_get_orders($args);

        echo '<div class="wrap"><h1>📦 سفارشات فرزندان</h1>';
        echo '<form method="get" style="margin-bottom:10px;">
                <input type="hidden" name="page" value="syncmaster-child-orders">
                <select name="filter">
                    <option value="">همه سفارشات</option>
                    <option value="error" '.selected($filter,'error',false).'>فقط خطادارها</option>
                </select>
                <button class="button">فیلتر</button>
              </form>';

        if (empty($orders)) {
            echo '<div style="background:#fff3cd;padding:15px;border:1px solid #ffeeba;">
                    ⚙️ در حال آماده‌سازی... هنوز سفارشی از سایت‌های فرزند دریافت نشده است.
                  </div></div>';
            return;
        }

        echo '<table class="widefat fixed striped"><thead>
                <tr><th>ID</th><th>Child ID</th><th>سایت فرزند</th><th>مشتری</th><th>مبلغ</th><th>وضعیت</th><th>سینک</th></tr>
              </thead><tbody>';

        foreach ($orders as $order) {
            $sync = $order->get_meta('_sync_status');
            $color = str_contains($sync, '❌') ? 'color:red;' : (str_contains($sync, '✅') ? 'color:green;' : 'color:#666;');
            echo '<tr>
                    <td>#'.$order->get_id().'</td>
                    <td>#'.$order->get_meta('_child_order_id').'</td>
                    <td>'.$order->get_meta('_child_site').'</td>
                    <td>'.$order->get_billing_first_name().' '.$order->get_billing_last_name().'</td>
                    <td>'.wc_price($order->get_total()).'</td>
                    <td>'.wc_get_order_status_name($order->get_status()).'</td>
                    <td style="'.$color.'">'.$sync.'</td>
                  </tr>';
        }
        echo '</tbody></table></div>';
    }

    public function handle_status_update() {
        if (!isset($_POST['syncmaster_change_status']) || !isset($_POST['order_id'])) return;
        if (!wp_verify_nonce($_POST['syncmaster_nonce'], 'syncmaster_status_update')) return;

        $order_id = intval($_POST['order_id']);
        $new_status = sanitize_text_field($_POST['new_status']);
        $order = wc_get_order($order_id);
        if (!$order) return;

        $order->update_status(str_replace('wc-', '', $new_status), 'تغییر از طریق SyncMaster');
        Logger::add("✅ وضعیت سفارش #{$order_id} تغییر یافت به {$new_status}");
    }
}
