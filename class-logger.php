<?php
namespace SyncMaster;

if ( ! defined('ABSPATH') ) exit;

class Logger {

    /**
     * 📍 مسیر فایل لاگ (در پوشه uploads/syncmaster)
     */
    public static function path(): string {
        $uploads = wp_upload_dir(null, false);
        $dir     = trailingslashit($uploads['basedir']) . 'syncmaster';
        if (!file_exists($dir)) wp_mkdir_p($dir);
        return $dir . '/syncmaster.log';
    }

    /**
     * 🔗 آدرس عمومی برای نمایش در داشبورد
     */
    public static function url(): string {
        $uploads = wp_upload_dir(null, false);
        $dirurl  = trailingslashit($uploads['baseurl']) . 'syncmaster';
        return $dirurl . '/syncmaster.log';
    }

    /**
     * 🧾 ثبت لاگ جدید
     */
    public static function add(string $message, string $level = 'INFO'): void {
        $line = sprintf("[%s] [%s] %s\n", gmdate('Y-m-d H:i:s'), strtoupper($level), $message);
        error_log('[SyncMaster] ' . $message);
        @file_put_contents(self::path(), $line, FILE_APPEND);
    }

    /**
     * 📜 خواندن آخرین N خط لاگ
     */
    public static function tail(int $lines = 200): array {
        $path = self::path();
        if (!file_exists($path)) return [];
        $content = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return array_slice($content, -1 * abs($lines));
    }

    /**
     * 🧹 پاک کردن کامل لاگ
     */
    public static function clear(): void {
        $path = self::path();
        if (file_exists($path)) @unlink($path);
    }

    /**
     * ⚙️ نمایش در داشبورد (HTML)
     */
    public static function render_admin_logs() {
        echo '<div class="wrap"><h1>🧾 گزارش سیستم SyncMaster</h1>';
        $logs = self::tail();
        echo '<div style="background:#fff;border:1px solid #ccc;padding:10px;max-height:500px;overflow:auto;font-family:monospace;">';
        if (empty($logs)) {
            echo '<p>⚙️ فعلاً داده‌ای برای نمایش وجود ندارد.</p>';
        } else {
            foreach ($logs as $line) {
                if (str_contains($line, '[ERROR]')) {
                    echo '<div style="color:#d00;">' . esc_html($line) . '</div>';
                } elseif (str_contains($line, '[WARN]')) {
                    echo '<div style="color:#e6b800;">' . esc_html($line) . '</div>';
                } else {
                    echo '<div style="color:#333;">' . esc_html($line) . '</div>';
                }
            }
        }
        echo '</div>';
        echo '<form method="post" action=""><button name="clear_logs" value="1" class="button button-secondary" style="margin-top:10px;">🧹 پاک‌سازی لاگ‌ها</button></form>';
        echo '</div>';

        if (isset($_POST['clear_logs'])) {
            self::clear();
            wp_safe_redirect(add_query_arg(['logs_cleared' => 1]));
            exit;
        }
    }
}
