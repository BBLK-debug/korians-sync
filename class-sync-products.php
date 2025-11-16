<?php
namespace SyncMaster;

if ( ! defined( 'ABSPATH' ) ) exit;

class Sync_Products {

    public function __construct() {
        add_action('save_post_product', [$this, 'on_product_save'], 20, 3);
    }

    /**
     * وقتی محصول ذخیره می‌شود، برای تمام سایت‌های فرزند ارسال کن
     */
    public function on_product_save($post_ID, $post, $update) {
        if ( wp_is_post_revision($post_ID) || get_post_status($post_ID) !== 'publish' ) return;

        $product = wc_get_product($post_ID);
        if ( ! $product ) return;

        $data = [
            'sync_id'        => $post_ID,
            'title'          => $product->get_name(),
            'description'    => $product->get_description(),
            'short_desc'     => $product->get_short_description(),
            'price'          => $product->get_price(),
            'regular_price'  => $product->get_regular_price(),
            'sale_price'     => $product->get_sale_price(),
            'sku'            => $product->get_sku(),
            'stock_status'   => $product->get_stock_status(),
            'stock_quantity' => $product->get_stock_quantity(),
            'categories'     => wp_get_post_terms($post_ID, 'product_cat', ['fields' => 'names']),
            'images'         => $this->get_product_images($product),
        ];

        $sites = get_option('syncmaster_sites', []);
        if ( empty($sites) ) return;

        foreach ($sites as $site) {
            $child_url   = trailingslashit($site['child_url']);
            $license_key = $site['license_key'];
            $endpoint    = $child_url . 'wp-json/wms/v1/sync/product';

            $response = wp_remote_post($endpoint, [
                'timeout' => 20,
                'headers' => ['Content-Type' => 'application/json'],
                'body'    => wp_json_encode(['license' => $license_key, 'product' => $data]),
            ]);

            if (is_wp_error($response)) {
                Logger::add("❌ ارسال محصول {$post_ID} به {$child_url} شکست خورد: " . $response->get_error_message(), 'error');
            } else {
                Logger::add("✅ محصول {$post_ID} به {$child_url} ارسال شد.");
            }
        }
    }

    private function get_product_images($product) {
        $images = [];

        if ($product->get_image_id()) {
            $images[] = wp_get_attachment_url($product->get_image_id());
        }

        foreach ($product->get_gallery_image_ids() as $id) {
            $images[] = wp_get_attachment_url($id);
        }

        return array_values(array_unique(array_filter($images)));
    }
}
