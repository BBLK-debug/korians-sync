<?php
namespace SyncMaster;

if (!defined('ABSPATH')) exit;

class Master_Core {

    private $option_key = 'syncmaster_settings';
    private $log_path;

    public function __construct() {
        $this->log_path = WP_CONTENT_DIR . '/uploads/syncmaster.log';
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_post_syncmaster_save_settings', [$this, 'save_settings']);
        add_action('admin_post_syncmaster_test_connection', [$this, 'test_connection']);
        add_action('admin_post_syncmaster_sync_now', [$this, 'manual_sync_trigger']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    /** 📌 افزودن منو در پیشخوان **/
    public function add_menu() {
        add_menu_page(
            'SyncMaster تنظیمات',
            'SyncMaster',
            'manage_options',
            'syncmaster-main',
            [$this, 'render_admin_page'],
            'dashicons-cloud',
            55
        );
    }

    /** 🧭 صفحه تنظیمات **/
    public function render_admin_page() {
        $settings = get_option($this->option_key, [
            'child_sites' => [],
            'auto_sync' => true,
            'auto_orders' => true,
            'auto_stock' => true,
        ]);

        $child_sites = $settings['child_sites'];
        ?>
        <div class="wrap">
            <h1>⚙️ تنظیمات SyncMaster</h1>

            <h2 class="nav-tab-wrapper">
                <a href="#tab-settings" class="nav-tab nav-tab-active">🔧 تنظیمات</a>
                <a href="#tab-products" class="nav-tab">📦 محصولات</a>
                <a href="#tab-orders" class="nav-tab">🧾 سفارشات</a>
                <a href="#tab-logs" class="nav-tab">🪵 لاگ‌ها</a>
            </h2>

            <!-- تنظیمات -->
            <div id="tab-settings" class="tab-content" style="display:block;">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="syncmaster_save_settings">
                    <?php wp_nonce_field('syncmaster_save_settings', 'syncmaster_nonce'); ?>

                    <h3>🌍 سایت‌های فرزند</h3>
                    <table class="widefat" id="child-sites-table">
                        <thead><tr><th>🌐 آدرس سایت</th><th>🔑 لایسنس</th><th>🧩 عملیات</th></tr></thead>
                        <tbody>
                        <?php if (!empty($child_sites)): ?>
                            <?php foreach ($child_sites as $url => $data): ?>
                                <tr>
                                    <td><input type="url" name="child_url[]" value="<?php echo esc_attr($url); ?>" class="regular-text" required></td>
                                    <td><input type="text" name="child_license[]" value="<?php echo esc_attr($data['license']); ?>" class="regular-text"></td>
                                    <td>
                                        <button type="button" class="button test-connection" data-url="<?php echo esc_attr($url); ?>">🔗 تست اتصال</button>
                                        <button type="button" class="button remove-row">❌ حذف</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>

                    <p><button type="button" id="add-row" class="button">➕ افزودن سایت جدید</button></p>

                    <h3>⚙️ تنظیمات خودکار</h3>
                    <label><input type="checkbox" name="auto_sync" value="1" <?php checked($settings['auto_sync']); ?>> همگام‌سازی خودکار محصولات</label><br>
                    <label><input type="checkbox" name="auto_orders" value="1" <?php checked($settings['auto_orders']); ?>> دریافت خودکار سفارشات</label><br>
                    <label><input type="checkbox" name="auto_stock" value="1" <?php checked($settings['auto_stock']); ?>> به‌روزرسانی موجودی محصولات</label>

                    <p><button type="submit" class="button button-primary">💾 ذخیره تنظیمات</button></p>
                </form>

                <div id="test-result" style="margin-top:10px;"></div>
            </div>

            <!-- تب محصولات -->
            <div id="tab-products" class="tab-content" style="display:none;">
                <h3>📦 همگام‌سازی محصولات</h3>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="syncmaster_sync_now">
                    <?php wp_nonce_field('syncmaster_sync_now', 'syncmaster_sync_nonce'); ?>
                    <button type="submit" class="button button-primary">🚀 ارسال محصولات به تمام فرزندها</button>
                </form>
            </div>

            <!-- تب سفارشات -->
            <div id="tab-orders" class="tab-content" style="display:none;">
                <h3>🧾 سفارشات دریافتی</h3>
                <?php echo $this->render_received_orders(); ?>
            </div>

            <!-- تب لاگ -->
            <div id="tab-logs" class="tab-content" style="display:none;">
                <h3>🪵 گزارش سیستم</h3>
                <?php echo $this->render_logs(); ?>
                <form method="post">
                    <button type="submit" name="clear_log" value="1" class="button">🧹 پاک‌سازی لاگ</button>
                </form>
                <?php if (isset($_POST['clear_log'])) { $this->clear_logs(); echo "<p>✅ لاگ‌ها پاک شدند.</p>"; } ?>
            </div>
        </div>

        <script>
        // تب‌ها
        document.querySelectorAll('.nav-tab').forEach(tab=>{
            tab.addEventListener('click',e=>{
                e.preventDefault();
                document.querySelectorAll('.nav-tab').forEach(t=>t.classList.remove('nav-tab-active'));
                tab.classList.add('nav-tab-active');
                document.querySelectorAll('.tab-content').forEach(c=>c.style.display='none');
                document.querySelector(tab.getAttribute('href')).style.display='block';
            });
        });

        // افزودن سطر جدید
        document.getElementById('add-row').addEventListener('click', ()=>{
            let tbody = document.querySelector('#child-sites-table tbody');
            let tr = document.createElement('tr');
            tr.innerHTML = `
                <td><input type="url" name="child_url[]" class="regular-text" required></td>
                <td><input type="text" name="child_license[]" class="regular-text"></td>
                <td><button type="button" class="button test-connection">🔗 تست اتصال</button>
                <button type="button" class="button remove-row">❌ حذف</button></td>`;
            tbody.appendChild(tr);
        });

        // حذف سطر
        document.addEventListener('click', e=>{
            if(e.target.classList.contains('remove-row')){
                e.target.closest('tr').remove();
            }
        });

        // تست اتصال هر سایت
        document.addEventListener('click', e=>{
            if(e.target.classList.contains('test-connection')){
                let url = e.target.closest('tr').querySelector('input[name="child_url[]"]').value;
                document.getElementById('test-result').innerHTML = "⏳ در حال بررسی...";
                fetch(ajaxurl, {
                    method: "POST",
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: "action=syncmaster_test_connection&url=" + encodeURIComponent(url)
                })
                .then(r=>r.text())
                .then(t=>{
                    document.getElementById('test-result').innerHTML = "🔍 " + t;
                });
            }
        });
        </script>
        <?php
    }

    /** 💾 ذخیره تنظیمات **/
    public function save_settings() {
        if (!isset($_POST['syncmaster_nonce']) || !wp_verify_nonce($_POST['syncmaster_nonce'], 'syncmaster_save_settings')) {
            wp_die('دسترسی غیرمجاز');
        }

        $urls = $_POST['child_url'] ?? [];
        $licenses = $_POST['child_license'] ?? [];
        $child_sites = [];

        foreach ($urls as $i => $url) {
            $url = trim($url);
            if (!$url) continue;
            $child_sites[$url] = ['license' => sanitize_text_field($licenses[$i] ?? '')];
        }

        $settings = [
            'child_sites' => $child_sites,
            'auto_sync' => isset($_POST['auto_sync']),
            'auto_orders' => isset($_POST['auto_orders']),
            'auto_stock' => isset($_POST['auto_stock']),
        ];

        update_option($this->option_key, $settings);
        $this->log('✅ تنظیمات ذخیره شد. ' . count($child_sites) . ' سایت ثبت شد.');
        wp_redirect(admin_url('admin.php?page=syncmaster-main&saved=1'));
        exit;
    }

    /** 🔗 تست اتصال **/
    public function test_connection() {
        $url = sanitize_text_field($_POST['url'] ?? '');
        if (!$url) { echo '❌ آدرس معتبر نیست'; wp_die(); }

        $ping_url = trailingslashit($url) . 'wp-json/wms/v1/ping';
        $response = wp_remote_get($ping_url, ['timeout' => 10]);

        if (is_wp_error($response)) {
            echo '❌ خطا: ' . $response->get_error_message();
        } else {
            echo '✅ پاسخ: ' . wp_remote_retrieve_body($response);
        }

        $this->log("🔗 تست اتصال با $url انجام شد.");
        wp_die();
    }

    /** سایر توابع مثل render_logs، clear_logs، receive_order... بدون تغییر **/
    private function log($msg) {
        if (!file_exists($this->log_path)) file_put_contents($this->log_path, '');
        file_put_contents($this->log_path, "[".date('Y-m-d H:i:s')."] ".$msg."\n", FILE_APPEND);
    }

    private function render_logs() {
        if (!file_exists($this->log_path)) return '<p>لاگی موجود نیست.</p>';
        $lines = file($this->log_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) return '<p>هیچ لاگی وجود ندارد.</p>';
        return "<pre style='background:#f8f8f8;padding:10px;max-height:300px;overflow:auto;'>".esc_html(implode("\n",$lines))."</pre>";
    }

    private function clear_logs() {
        if (file_exists($this->log_path)) unlink($this->log_path);
    }

    private function render_received_orders() {
        $orders = wc_get_orders(['limit'=>10]);
        if (!$orders) return '<p>هیچ سفارشی ثبت نشده.</p>';
        echo '<ul>';
        foreach ($orders as $o) {
            echo '<li>🧾 سفارش #' . $o->get_id() . ' — ' . $o->get_total() . ' تومان (' . $o->get_status() . ')</li>';
        }
        echo '</ul>';
    }

    public function register_rest_routes() {
        register_rest_route('wms/v1', '/ping', [
            'methods' => 'GET',
            'callback' => fn()=>'pong',
            'permission_callback'=>'__return_true'
        ]);
    }
}
