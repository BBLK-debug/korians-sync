<?php
namespace SyncChild;

if ( ! defined('ABSPATH') ) exit;

class Child_Sync {

    private $api_endpoint;
    private $log_option = 'syncchild_product_logs';
    private $products_option = 'syncchild_products_cache';

    public function __construct() {
        $settings = get_option('syncchild_settings', []);
        $this->api_endpoint = rtrim($settings['master_url'] ?? '', '/') . '/wp-json/syncmaster/v1/products';

        add_action('admin_menu', [$this, 'add_submenu']);
        add_action('admin_post_syncchild_import_products', [$this, 'import_products']);
        add_action('admin_post_syncchild_refresh_products', [$this, 'refresh_products']);
    }

    /**
     * افزودن تب جدید در منوی SyncChild
     */
    public function add_submenu() {
        add_submenu_page(
            'syncchild-settings',
            'مدیریت محصولات',
            '🛍️ محصولات دریافتی',
            'manage_options',
            'syncchild-products',
            [$this, 'render_products_page']
        );
    }

    /**
     * صفحه مدیریت محصولات
     */
    public function render_products_page() {
        $products = get_option($this->products_option, []);
        ?>
        <div class="wrap">
            <h1>🛍️ محصولات دریافتی از سایت مادر</h1>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <?php wp_nonce_field('syncchild_products_nonce', 'syncchild_nonce'); ?>
                <input type="hidden" name="action" value="syncchild_import_products">
                <button type="submit" class="button-primary">📦 دریافت محصولات از سایت مادر</button>
            </form>

            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="margin-top:10px;">
                <?php wp_nonce_field('syncchild_refresh_nonce', 'syncchild_nonce'); ?>
                <input type="hidden" name="action" value="syncchild_refresh_products">
                <button type="submit" class="button">🔁 بروزرسانی محصولات</button>
            </form>

            <hr>
            <h2>📋 فهرست محصولات</h2>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>انتخاب</th>
                        <th>شناسه</th>
                        <th>نام کالا</th>
                        <th>قیمت</th>
                        <th>موجودی</th>
                        <th>تخفیف</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($products)): ?>
                        <?php foreach ($products as $p): ?>
                            <tr>
                                <td><input type="checkbox" name="selected[]" value="<?php echo esc_attr($p['id']); ?>"></td>
                                <td><?php echo esc_html($p['id']); ?></td>
                                <td><?php echo esc_html($p['name']); ?></td>
                                <td><?php echo esc_html($p['price']); ?></td>
                                <td><?php echo esc_html($p['stock']); ?></td>
                                <td><?php echo esc_html($p['discount']); ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align:center;">هیچ محصولی دریافت نشده است.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <hr>
            <h2>🧾 لاگ سیستم</h2>
            <pre style="background:#fff;border:1px solid #ccc;padding:10px;max-height:200px;overflow:auto;">
                <?php echo esc_html(implode("\n", get_option($this->log_option, []))); ?>
            </pre>
        </div>
        <?php
    }

    /**
     * دریافت محصولات از سایت مادر
     */
    public function import_products() {
        check_admin_referer('syncchild_products_nonce', 'syncchild_nonce');
        $response = wp_remote_get($this->api_endpoint, ['timeout' => 20]);
        if (is_wp_error($response)) {
            $this->log('❌ خطا در دریافت محصولات از مادر: ' . $response->get_error_message());
        } else {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (!empty($data['products'])) {
                update_option($this->products_option, $data['products']);
                $this->log('✅ ' . count($data['products']) . ' محصول از مادر دریافت شد.');
            } else {
                $this->log('⚠️ هیچ محصولی یافت نشد یا پاسخ نامعتبر بود.');
            }
        }
        wp_redirect(admin_url('admin.php?page=syncchild-products'));
        exit;
    }

    /**
     * بروزرسانی محصولات (دوباره فراخوانی از مادر)
     */
    public function refresh_products() {
        check_admin_referer('syncchild_refresh_nonce', 'syncchild_nonce');
        $this->import_products(); // همان متد بالا را اجرا می‌کند
    }

    /**
     * ثبت لاگ
     */
    private function log($message) {
        $logs = get_option($this->log_option, []);
        $logs[] = current_time('Y-m-d H:i:s') . ' - ' . $message;
        if (count($logs) > 100) array_shift($logs);
        update_option($this->log_option, $logs);
    }
}
