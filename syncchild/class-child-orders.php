<?php
namespace SyncChild;

if ( ! defined('ABSPATH') ) exit;

class Child_Orders {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_orders_page']);
        add_action('admin_post_syncchild_resend_order', [$this, 'resend_order_to_master']);
    }

    /**
     * 📋 تب سفارشات ارسال‌شده
     */
    public function add_orders_page() {
        add_submenu_page(
            'syncchild-settings',
            'سفارشات ارسال‌شده',
            '📤 سفارشات ارسال‌شده',
            'manage_woocommerce',
            'syncchild-orders',
            [$this, 'render_child_orders_page']
        );
    }

    /**
     * 📬 نمایش سفارشات Child و دکمه ارسال مجدد
     */
    public function render_child_orders_page() {
        $orders = wc_get_orders([
            'limit' => 50,
            'orderby' => 'date',
            'order' => 'DESC'
        ]);

        echo '<div class="wrap"><h1>📤 سفارشات ارسال‌شده به سایت مادر</h1>';
        echo '<p>در اینجا سفارش‌هایی که به سایت مادر ارسال شده‌اند یا ارسال آن‌ها با خطا مواجه شده‌اند نمایش داده می‌شوند.</p>';

        if (empty($orders)) {
            echo '<div style="background:#fff3cd;padding:15px;border:1px solid #ffeeba;margin-top:10px;">
                    ⚙️ در حال آماده‌سازی... هنوز سفارشی ارسال نشده است.
                  </div></div>';
            return;
        }

        echo '<table class="widefat fixed striped" style="margin-top:15px;">';
        echo '<thead><tr><th>شناسه سفارش</th><th>مبلغ</th><th>وضعیت سفارش</th><th>وضعیت سینک</th><th>تاریخ</th><th>اقدامات</th></tr></thead><tbody>';

        foreach ($orders as $order) {
            $sync_status = $order->get_meta('_sync_status', true);
            $sync_status = $sync_status ? $sync_status : '⏳ در انتظار ارسال';

            $status_color = str_contains($sync_status, 'خطا') ? 'color:red;' : (str_contains($sync_status, 'موفق') ? 'color:green;' : 'color:#555;');

            echo '<tr>
                    <td>#'.$order->get_id().'</td>
                    <td>'.wc_price($order->get_total()).'</td>
                    <td>'.wc_get_order_status_name($order->get_status()).'</td>
                    <td style="'.$status_color.'">'.$sync_status.'</td>
                    <td>'.$order->get_date_created()->date('Y-m-d H:i').'</td>
                    <td>
                        <form method="post" action="'.admin_url('admin-post.php').'">
                            <input type="hidden" name="action" value="syncchild_resend_order">
                            <input type="hidden" name="order_id" value="'.$order->get_id().'">
                            '.wp_nonce_field('syncchild_resend_order', 'syncchild_nonce', true, false).'
                            <button type="submit" class="button">🔁 ارسال مجدد</button>
                        </form>
                    </td>
                  </tr>';
        }

        echo '</tbody></table></div>';
    }

    /**
     * 📦 ارسال مجدد سفارش به سایت مادر در صورت خطا
     */
    public function resend_order_to_master() {
        if (!isset($_POST['order_id']) || !wp_verify_nonce($_POST['syncchild_nonce'], 'syncchild_resend_order')) {
            wp_die('درخواست نامعتبر است.');
        }

        $order_id = intval($_POST['order_id']);
        $order = wc_get_order($order_id);
        if (!$order) wp_die('سفارش یافت نشد.');

        $master_url = get_option('syncchild_master_url');
        if (!$master_url) wp_die('آدرس سایت مادر تعریف نشده است.');

        $payload = [
            'order_id' => $order->get_id(),
            'total' => $order->get_total(),
            'items' => [],
            'billing' => $order->get_address('billing'),
            'child_url' => get_site_url(),
        ];

        foreach ($order->get_items() as $item) {
            $payload['items'][] = [
                'name' => $item->get_name(),
                'qty'  => $item->get_quantity(),
                'price'=> $item->get_total(),
            ];
        }

        $response = wp_remote_post(trailingslashit($master_url) . 'wp-json/wms/v1/order/receive', [
            'timeout' => 15,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            $order->update_meta_data('_sync_status', '❌ خطا در ارسال مجدد: '.$response->get_error_message());
            $order->save();
            wp_redirect(admin_url('admin.php?page=syncchild-orders&error=1'));
            exit;
        }

        $order->update_meta_data('_sync_status', '✅ ارسال مجدد موفق به سایت مادر');
        $order->save();
        wp_redirect(admin_url('admin.php?page=syncchild-orders&success=1'));
        exit;
    }
}
