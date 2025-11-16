<?php
namespace SyncChild;

if ( ! defined('ABSPATH') ) exit;

/**
 * 🎯 پلاگین فرزند (SyncChild)
 * نسخه‌ی نهایی – شامل تب‌ها، همگام‌سازی، لاگ، تست اتصال و REST API
 */
class Child_Core {

    private $option_key = 'syncchild_settings';

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_post_syncchild_save_settings', [$this, 'save_settings']);
        add_action('admin_post_syncchild_test_connection', [$this, 'test_connection']);
        add_action('admin_post_syncchild_sync_products', [$this, 'manual_sync_products']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('woocommerce_order_status_changed', [$this, 'send_order_to_master'], 10, 4);
    }

    /** 🧭 منو اصلی در پیشخوان **/
    public function add_menu() {
        add_menu_page(
            'SyncChild تنظیمات',
            'SyncChild',
            'manage_options',
            'syncchild-main',
            [$this, 'render_admin_page'],
            'dashicons-rest-api',
            56
        );
    }

    /** ⚙️ رابط کاربری پلاگین در پیشخوان **/
    public function render_admin_page() {
        $settings = get_option($this->option_key, [
            'master_url' => '',
            'license' => '',
            'sync_images' => true,
            'sync_descriptions' => true,
            'sync_stock' => true
        ]);
        ?>
        <div class="wrap">
            <h1>🔑 تنظیمات SyncChild</h1>

            <h2 class="nav-tab-wrapper">
                <a href="#tab-settings" class="nav-tab nav-tab-active">⚙️ تنظیمات</a>
                <a href="#tab-products" class="nav-tab">📥 محصولات دریافتی</a>
                <a href="#tab-orders" class="nav-tab">📦 سفارشات ارسالی</a>
                <a href="#tab-logs" class="nav-tab">🧾 لاگ‌ها</a>
            </h2>

            <!-- تنظیمات -->
            <div id="tab-settings" class="tab-content" style="display:block;">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="syncchild_save_settings">
                    <?php wp_nonce_field('syncchild_save_settings', 'syncchild_nonce'); ?>

                    <table class="form-table">
                        <tr><th>🌍 آدرس سایت مادر</th>
                            <td><input type="url" name="master_url" value="<?php echo esc_attr($settings['master_url']); ?>" class="regular-text" required></td>
                        </tr>
                        <tr><th>🔐 کد لایسنس</th>
                            <td><input type="text" name="license" value="<?php echo esc_attr($settings['license']); ?>" class="regular-text"></td>
                        </tr>
                    </table>

                    <h3>⚙️ تنظیمات همگام‌سازی</h3>
                    <label><input type="checkbox" name="sync_images" value="1" <?php checked($settings['sync_images']); ?>> همگام‌سازی عکس‌ها</label><br>
                    <label><input type="checkbox" name="sync_descriptions" value="1" <?php checked($settings['sync_descriptions']); ?>> همگام‌سازی توضیحات</label><br>
                    <label><input type="checkbox" name="sync_stock" value="1" <?php checked($settings['sync_stock']); ?>> همگام‌سازی موجودی</label>

                    <p><button type="submit" class="button button-primary">💾 ذخیره تنظیمات</button></p>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="syncchild_test_connection">
                    <?php wp_nonce_field('syncchild_test_connection', 'syncchild_test_nonce'); ?>
                    <button type="submit" class="button">🔗 تست اتصال</button>
                </form>
            </div>

            <!-- محصولات -->
            <div id="tab-products" class="tab-content" style="display:none;">
                <h3>📥 محصولات دریافتی</h3>
                <p>در اینجا می‌توانید محصولات سایت مادر را به‌صورت دستی همگام‌سازی کنید.</p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="syncchild_sync_products">
                    <?php wp_nonce_field('syncchild_sync_products', 'syncchild_sync_nonce'); ?>
                    <button type="submit" class="button button-primary">🔄 همگام‌سازی کامل محصولات</button>
                </form>
                <div style="margin-top:15px;">
                    <?php echo $this->render_synced_products(); ?>
                </div>
            </div>

            <!-- سفارشات -->
            <div id="tab-orders" class="tab-content" style="display:none;">
                <h3>🧾 سفارشات ارسالی</h3>
                <p>در اینجا سفارش‌هایی که به سایت مادر ارسال شده‌اند نمایش داده می‌شوند.</p>
                <p>⚙️ در حال آماده‌سازی...</p>
            </div>

            <!-- لاگ -->
            <div id="tab-logs" class="tab-content" style="display:none;">
                <h3>🧾 لاگ سیستم</h3>
                <?php echo $this->render_logs(); ?>
                <form method="post">
                    <button type="submit" name="clear_log" value="1" class="button">🗑️ پاک‌سازی لاگ</button>
                </form>
                <?php if (isset($_POST['clear_log'])) { $this->clear_logs(); echo "<p>✅ لاگ‌ها پاک شدند.</p>"; } ?>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.nav-tab');
            const contents = document.querySelectorAll('.tab-content');
            tabs.forEach(tab => tab.addEventListener('click', function(e) {
                e.preventDefault();
                tabs.forEach(t => t.classList.remove('nav-tab-active'));
                tab.classList.add('nav-tab-active');
                contents.forEach(c => c.style.display = 'none');
                document.querySelector(tab.getAttribute('href')).style.display = 'block';
            }));
        });
        </script>
        <?php
    }

    /** 💾 ذخیره تنظیمات **/
    public function save_settings() {
        if (!isset($_POST['syncchild_nonce']) || !wp_verify_nonce($_POST['syncchild_nonce'], 'syncchild_save_settings')) {
            wp_die('❌ دسترسی غیرمجاز');
        }

        $settings = [
            'master_url' => esc_url_raw($_POST['master_url']),
            'license' => sanitize_text_field($_POST['license']),
            'sync_images' => isset($_POST['sync_images']),
            'sync_descriptions' => isset($_POST['sync_descriptions']),
            'sync_stock' => isset($_POST['sync_stock']),
        ];

        update_option($this->option_key, $settings);
        $this->log('✅ تنظیمات ذخیره شد.');
        wp_redirect(admin_url('admin.php?page=syncchild-main&saved=1'));
        exit;
    }

    /** 🔗 تست اتصال به سایت مادر **/
    public function test_connection() {
        $settings = get_option($this->option_key);
        $url = trailingslashit($settings['master_url']) . 'wp-json/wms/v1/ping';
        $response = wp_remote_get($url, ['timeout' => 10]);

        $msg = is_wp_error($response)
            ? '❌ خطا در اتصال: ' . $response->get_error_message()
            : '✅ پاسخ سرور: ' . wp_remote_retrieve_body($response);

        $this->log($msg);
        wp_redirect(admin_url('admin.php?page=syncchild-main&tested=1'));
        exit;
    }

    /** 🔄 همگام‌سازی دستی محصولات **/
    public function manual_sync_products() {
        $settings = get_option($this->option_key);
        if (empty($settings['master_url'])) return;

        $url = trailingslashit($settings['master_url']) . 'wp-json/wms/v1/products';
        $response = wp_remote_get($url, ['timeout' => 20]);

        if (is_wp_error($response)) {
            $this->log('❌ خطا در دریافت محصولات: ' . $response->get_error_message());
            wp_redirect(admin_url('admin.php?page=syncchild-main&error=1'));
            exit;
        }

        $products = json_decode(wp_remote_retrieve_body($response), true);
        if (!empty($products)) {
            foreach ($products as $p) {
                $post_id = wp_insert_post([
                    'post_title' => $p['name'] ?? 'بدون عنوان',
                    'post_content' => $p['description'] ?? '',
                    'post_status' => 'publish',
                    'post_type' => 'product',
                ]);
            }
            $this->log('✅ محصولات با موفقیت همگام‌سازی شدند.');
        } else {
            $this->log('⚠️ محصولی برای دریافت وجود ندارد.');
        }

        wp_redirect(admin_url('admin.php?page=syncchild-main&synced=1'));
        exit;
    }

    /** 📦 ارسال سفارش‌ها به سایت مادر **/
    public function send_order_to_master($order_id, $old_status, $new_status, $order) {
        $settings = get_option($this->option_key);
        if (empty($settings['master_url'])) return;

        $data = [
            'order_id' => $order_id,
            'status' => $new_status,
            'total' => $order->get_total(),
            'items' => [],
            'billing' => $order->get_address('billing'),
            'child_url' => get_site_url(),
        ];

        foreach ($order->get_items() as $item) {
            $data['items'][] = [
                'name' => $item->get_name(),
                'qty' => $item->get_quantity(),
            ];
        }

        $response = wp_remote_post(trailingslashit($settings['master_url']) . 'wp-json/wms/v1/order/receive', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode($data),
            'timeout' => 15,
        ]);

        $body = is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_body($response);
        $this->log('📦 سفارش ارسال شد → ' . $body);
    }

    /** 📡 REST Endpoint **/
    public function register_rest_routes() {
        register_rest_route('wms/v1', '/ping', [
            'methods' => 'GET',
            'callback' => fn() => 'pong',
            'permission_callback' => '__return_true',
        ]);
    }

    /** 🧾 نمایش لاگ **/
    private function render_logs() {
        $path = WP_CONTENT_DIR . '/uploads/syncchild.log';
        if (!file_exists($path)) file_put_contents($path, '');
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) return '<p>هیچ لاگی وجود ندارد.</p>';
        return "<pre style='background:#f9f9f9; padding:10px; max-height:400px; overflow:auto;'>" . esc_html(implode("\n", array_slice($lines, -50))) . "</pre>";
    }

    /** 🧹 پاک‌سازی لاگ **/
    private function clear_logs() {
        $path = WP_CONTENT_DIR . '/uploads/syncchild.log';
        if (file_exists($path)) unlink($path);
    }

    /** 📋 محصولات همگام‌شده **/
    private function render_synced_products() {
        $args = ['post_type' => 'product', 'posts_per_page' => 10];
        $products = get_posts($args);
        if (empty($products)) return '<p>هنوز محصولی همگام‌سازی نشده است.</p>';
        echo '<ul>';
        foreach ($products as $p) {
            echo '<li>🛍️ ' . esc_html($p->post_title) . '</li>';
        }
        echo '</ul>';
    }

    /** 🪵 لاگ‌نویسی **/
    private function log($msg) {
        $path = WP_CONTENT_DIR . '/uploads/syncchild.log';
        if (!file_exists($path)) file_put_contents($path, '');
        file_put_contents($path, "[".date('Y-m-d H:i:s')."] ".$msg."\n", FILE_APPEND);
    }
}
