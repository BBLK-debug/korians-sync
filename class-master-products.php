<?php
namespace SyncMaster;

if ( ! defined('ABSPATH') ) exit;

/**
 * کلاس مدیریت همگام‌سازی محصولات از سایت مادر
 */
class Master_Products {

    private $opt_children_key = 'syncmaster_children_simple'; // تنظیمات سایت‌های فرزند

    public function __construct() {
        add_action('admin_menu', [$this, 'register_admin_page']);
        add_action('admin_post_syncmaster_push_products', [$this, 'handle_push_products']);
    }

    /**
     * ثبت زیرمنوی جدید در پیشخوان
     */
    public function register_admin_page() {
        add_submenu_page(
            'syncmaster-settings',
            'همگام‌سازی محصولات',
            '📦 همگام‌سازی محصولات',
            'manage_options',
            'syncmaster-products',
            [$this, 'render_products_page']
        );
    }

    /**
     * دریافت لیست سایت‌های فرزند
     */
    private function get_children_list() {
        $rows = get_option($this->opt_children_key, []);
        return is_array($rows) ? $rows : [];
    }

    /**
     * دریافت لیست محصولات ووکامرس
     */
    private function get_products($paged = 1, $per_page = 20) {
        $q = new \WP_Query([
            'post_type'      => 'product',
            'post_status'    => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => $per_page,
            'paged'          => max(1, intval($paged)),
            'fields'         => 'ids',
        ]);
        return $q;
    }

    /**
     * ساخت دادهٔ مورد نیاز برای ارسال به سایت فرزند
     */
    private function collect_product_payload($product_id) {
        if ( ! class_exists('\WC_Product') ) return null;
        $p = wc_get_product($product_id);
        if ( ! $p ) return null;

        $payload = [
            'master_id'      => $product_id,
            'sku'            => $p->get_sku(),
            'slug'           => get_post_field('post_name', $product_id),
            'title'          => $p->get_name(),
            'content'        => get_post_field('post_content', $product_id),
            'excerpt'        => get_post_field('post_excerpt', $product_id),
            'status'         => $p->get_status(),
            'type'           => $p->get_type(),
            'regular_price'  => $p->get_regular_price(),
            'sale_price'     => $p->get_sale_price(),
            'stock_status'   => $p->get_stock_status(),
            'stock_quantity' => $p->get_stock_quantity(),
            'manage_stock'   => $p->get_manage_stock(),
            'attributes'     => [],
            'categories'     => [],
            'image_urls'     => [],
            'gallery_urls'   => [],
        ];

        // دسته‌ها
        $terms = wp_get_post_terms($product_id, 'product_cat', ['fields'=>'ids']);
        foreach ($terms as $tid) {
            $term = get_term($tid, 'product_cat');
            if ($term && !is_wp_error($term)) {
                $payload['categories'][] = [
                    'id'   => $term->term_id,
                    'slug' => $term->slug,
                    'name' => $term->name,
                ];
            }
        }

        // ویژگی‌ها
        foreach ($p->get_attributes() as $attr) {
            $payload['attributes'][] = [
                'name'    => $attr->get_name(),
                'visible' => $attr->get_visible(),
                'options' => $attr->is_taxonomy()
                    ? wp_get_post_terms($product_id, $attr->get_name(), ['fields'=>'names'])
                    : $attr->get_options(),
            ];
        }

        // تصویر شاخص و گالری
        $thumb_id = get_post_thumbnail_id($product_id);
        if ($thumb_id) {
            $url = wp_get_attachment_url($thumb_id);
            if ($url) $payload['image_urls'][] = $url;
        }
        $gallery_ids = $p->get_gallery_image_ids();
        foreach ($gallery_ids as $gid) {
            $u = wp_get_attachment_url($gid);
            if ($u) $payload['gallery_urls'][] = $u;
        }

        return $payload;
    }

    /**
     * صفحهٔ ادمین برای انتخاب و ارسال محصولات
     */
    public function render_products_page() {
        if ( ! current_user_can('manage_options')) return;

        $children = $this->get_children_list();
        $paged    = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
        $q        = $this->get_products($paged, 20);

        ?>
        <div class="wrap">
            <h1>📦 همگام‌سازی محصولات</h1>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('syncmaster_push_products_nonce','syncmaster_nonce'); ?>
                <input type="hidden" name="action" value="syncmaster_push_products" />

                <p>
                    <label><strong>ارسال به:</strong></label>
                    <select name="child_target">
                        <option value="all">تمام سایت‌های فرزند</option>
                        <?php foreach ($children as $i => $c):
                            $label = $c['label'] ?? $c['url'] ?? 'Child '.($i+1); ?>
                            <option value="<?php echo esc_attr($i); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>

                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th style="width:30px;"><input type="checkbox" onclick="jQuery('.sm-prod').prop('checked', this.checked);" /></th>
                            <th>ID</th>
                            <th>نام محصول</th>
                            <th>قیمت</th>
                            <th>وضعیت</th>
                            <th>موجودی</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($q->have_posts()): foreach ($q->posts as $pid):
                        $p = wc_get_product($pid); if(!$p) continue; ?>
                        <tr>
                            <td><input type="checkbox" class="sm-prod" name="product_ids[]" value="<?php echo esc_attr($pid); ?>"></td>
                            <td><?php echo esc_html($pid); ?></td>
                            <td><?php echo esc_html($p->get_name()); ?></td>
                            <td><?php echo esc_html($p->get_price()); ?></td>
                            <td><?php echo esc_html($p->get_status()); ?></td>
                            <td><?php echo esc_html($p->get_manage_stock() ? $p->get_stock_quantity() : $p->get_stock_status()); ?></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="6">محصولی یافت نشد.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>

                <p><button type="submit" class="button-primary">🚀 ارسال برای همگام‌سازی</button></p>
            </form>
        </div>
        <?php
    }

    /**
     * پردازش ارسال محصولات به سایت‌های فرزند
     */
    public function handle_push_products() {
        if ( ! current_user_can('manage_options') ) wp_die('Unauthorized');
        check_admin_referer('syncmaster_push_products_nonce','syncmaster_nonce');

        $ids = isset($_POST['product_ids']) ? array_map('intval', (array)$_POST['product_ids']) : [];
        $target = sanitize_text_field($_POST['child_target'] ?? 'all');

        if (empty($ids)) {
            if (class_exists('\SyncMaster\Logger')) Logger::add('هیچ محصولی انتخاب نشده است.', 'warning');
            wp_redirect(menu_page_url('syncmaster-products', false));
            exit;
        }

        $children = $this->get_children_list();
        $targets = ($target === 'all') ? $children : [ $children[intval($target)] ?? null ];
        $targets = array_filter($targets);

        $batch = [];
        foreach ($ids as $pid) {
            $data = $this->collect_product_payload($pid);
            if ($data) $batch[] = $data;
        }

        foreach ($targets as $c) {
            $this->send_batch_to_child($c, $batch);
        }

        wp_redirect(menu_page_url('syncmaster-products', false));
        exit;
    }

    /**
     * ارسال گروهی محصولات به سایت فرزند از طریق REST
     */
    private function send_batch_to_child($child, $batch) {
        $url     = rtrim($child['url'] ?? '', '/');
        $license = $child['license'] ?? '';

        if (!$url || !$license || !$batch) return;

        $endpoint = $url . '/wp-json/wms/v1/products/ingest';
        $resp = wp_remote_post($endpoint, [
            'timeout' => 30,
            'headers' => [
                'Content-Type'  => 'application/json; charset=utf-8',
                'X-SMC-License' => $license,
            ],
            'body' => wp_json_encode([
                'license'  => $license,
                'products' => $batch,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $code = wp_remote_retrieve_response_code($resp);
        $body = wp_remote_retrieve_body($resp);

        if (class_exists('\SyncMaster\Logger')) {
            Logger::add('ارسال به چایلد ' . $url . ' => ' . $code . ' | ' . $body, ($code>=200 && $code<300)?'info':'error');
        } else {
            error_log('[SyncMaster] Push ' . $url . ' => ' . $code);
        }
    }
}
