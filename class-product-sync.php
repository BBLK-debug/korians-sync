<?php
namespace SyncMaster;

if ( ! defined('ABSPATH') ) exit;

require_once SYNCMASTER_PATH . 'includes/class-logger.php';

class Product_Sync {

    public function __construct() {
        // ارسال خودکار هنگام ذخیره محصول (فقط محصولات موجود)
        add_action('save_post_product', [$this, 'on_product_save'], 20, 3);
    }

    public function on_product_save($post_id, $post, $update) {
        if ( wp_is_post_revision($post_id) ) return;

        $product = wc_get_product($post_id);
        if ( ! $product || $product->get_stock_status() !== 'instock' ) return;

        $data = $this->prepare_product_data($product);

        $settings = get_option('syncmaster_settings', []);
        if (empty($settings['child_sites'])) return;

        foreach ($settings['child_sites'] as $child) {
            $this->send_to_child($child['url'], $child['license'], $data);
        }
    }

    private function prepare_product_data($product) {
        $images = [];
        $thumb_id = $product->get_image_id();
        if ($thumb_id) $images[] = wp_get_attachment_url($thumb_id);
        $gallery = $product->get_gallery_image_ids();
        foreach ($gallery as $img_id) {
            $images[] = wp_get_attachment_url($img_id);
        }

        return [
            'sku'               => $product->get_sku(),
            'name'              => $product->get_name(),
            'regular_price'     => $product->get_regular_price(),
            'sale_price'        => $product->get_sale_price(),
            'description'       => $product->get_description(),
            'short_description' => $product->get_short_description(),
            'stock_quantity'    => $product->get_stock_quantity(),
            'stock_status'      => $product->get_stock_status(),
            'type'              => $product->get_type(),
            'images'            => $images,
        ];
    }

    private function send_to_child($url, $license, $data) {
        $endpoint = rtrim($url, '/') . '/wp-json/wms/v1/product';
        $response = wp_remote_post($endpoint, [
            'timeout' => 20,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode([
                'license' => $license,
                'product' => $data,
            ])
        ]);

        if (is_wp_error($response)) {
            Logger::add("❌ ارسال محصول به {$url} ناموفق: " . $response->get_error_message(), 'error');
            return;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code === 200) {
            Logger::add("✅ محصول «{$data['name']}» به {$url} ارسال شد", 'success');
        } else {
            Logger::add("⚠️ پاسخ نامعتبر از {$url} (کد {$code})", 'warning');
        }
    }
}
