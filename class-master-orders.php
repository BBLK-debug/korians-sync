<?php
namespace SyncMaster;

if ( ! defined('ABSPATH') ) exit;

class Master_Orders {

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('add_meta_boxes', [$this, 'add_child_order_meta_box']);
        add_action('save_post_shop_order', [$this, 'save_child_order_meta']);
    }

    /**
     * ثبت REST API برای دریافت سفارش از سایت فرزند
     */
    public function register_rest_routes() {
        register_rest_route('wms/v1', '/order/push', [
            'methods'  => 'POST',
            'callback' => [$this, 'receive_order_from_child'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('wms/v1', '/order/status', [
            'methods'  => 'POST',
            'callback' => [$this, 'update_order_status_from_child'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * دریافت سفارش از سایت فرزند
     */
    public function receive_order_from_child(\WP_REST_Request $request) {
        $data = $request->get_json_params();
        if (empty($data['order_id']) || empty($data['items'])) {
            Logger::add('❌ داده سفارش نامعتبر از Child.', 'error');
            return new \WP_REST_Response(['success' => false, 'message' => 'Invalid data'], 400);
        }

        // ایجاد سفارش در ووکامرس
        $order = wc_create_order();
        foreach ($data['items'] as $item) {
            $product = wc_get_product($item['product_id']);
            if ($product) {
                $order->add_product($product, intval($item['quantity']));
            }
        }

        $order->set_address($data['billing'], 'billing');
        $order->set_address($data['shipping'] ?? $data['billing'], 'shipping');
        $order->set_total($data['total']);
        $order->update_status('processing', '📦 سفارش از Child دریافت شد.');
        $order->update_meta_data('_child_site', sanitize_text_field($data['site_url']));
        $order->update_meta_data('_child_order_id', intval($data['order_id']));
        $order->save();

        Logger::add("✅ سفارش جدید از سایت فرزند دریافت شد: #{$order->get_id()}");
        return new \WP_REST_Response(['success' => true, 'order_id' => $order->get_id()]);
    }

    /**
     * بروزرسانی وضعیت سفارش از سمت Child
     */
    public function update_order_status_from_child(\WP_REST_Request $request) {
        $data = $request->get_json_params();
        $child_order_id = intval($data['order_id']);
        $status = sanitize_text_field($data['status']);

        $orders = wc_get_orders([
            'meta_key'   => '_child_order_id',
            'meta_value' => $child_order_id,
            'limit'      => 1
        ]);

        if (!empty($orders)) {
            $order = $orders[0];
            $order->update_status($status, "🔄 وضعیت از Child بروز شد: {$status}");
            Logger::add("🔄 وضعیت سفارش #{$order->get_id()} از سایت فرزند به {$status} تغییر یافت.");
            return new \WP_REST_Response(['success' => true]);
        }

        return new \WP_REST_Response(['success' => false, 'message' => 'Order not found'], 404);
    }

    /**
     * متاباکس اطلاعات Child در سفارشات ووکامرس
     */
    public function add_child_order_meta_box() {
        add_meta_box(
            'child_order_meta',
            '📡 اطلاعات سفارش Child',
            [$this, 'render_child_order_meta_box'],
            'shop_order',
            'side',
            'default'
        );
    }

    public function render_child_order_meta_box($post) {
        $child_url = get_post_meta($post->ID, '_child_site', true);
        $child_id  = get_post_meta($post->ID, '_child_order_id', true);
        if ($child_url && $child_id) {
            echo "<p><strong>سایت فرزند:</strong><br>{$child_url}</p>";
            echo "<p><strong>شناسه سفارش در Child:</strong><br>#{$child_id}</p>";
        } else {
            echo "<p>این سفارش از سایت فرزند نیامده است.</p>";
        }
    }

    public function save_child_order_meta($post_id) {
        // رزرو برای تغییرات بعدی (فعلاً خالی)
    }
}
