<?php
namespace SyncChild; // ← در نسخه Child اینو بکن SyncChild

if ( ! defined('ABSPATH') ) exit;

class Logger {

    /**
     * مسیر فایل لاگ در wp-content/uploads/sync-logs/sync.log
     */
    public static function path(): string {
        $uploads = wp_upload_dir(null, false);
        $dir = trailingslashit($uploads['basedir']) . 'sync-logs';
        if ( ! file_exists($dir) ) {
            wp_mkdir_p($dir);
        }
        return $dir . '/sync.log';
    }

    /**
     * آدرس عمومی برای مشاهده لاگ در مرورگر
     */
    public static function url(): string {
        $uploads = wp_upload_dir(null, false);
        $dirurl = trailingslashit($uploads['baseurl']) . 'sync-logs';
        return $dirurl . '/sync.log';
    }

    /**
     * ثبت لاگ جدید در فایل
     */
    public static function add(string $message, string $level = 'info'): void {
        $time = gmdate('Y-m-d H:i:s');
        $line = sprintf("[%s] [%s] %s\n", $time, strtoupper($level), $message);

        // ثبت در error_log برای دیباگ
        error_log('[SYNC] ' . $message);

        // ثبت در فایل اختصاصی
        @file_put_contents(self::path(), $line, FILE_APPEND);
    }

    /**
     * خواندن آخرین خطوط لاگ
     */
    public static function tail(int $lines = 200): array {
        $path = self::path();
        if ( ! file_exists($path) ) return [];
        $content = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ( ! is_array($content) ) return [];
        return array_slice($content, -abs($lines));
    }

    /**
     * پاک‌سازی کامل لاگ‌ها
     */
    public static function clear(): void {
        $path = self::path();
        if ( file_exists($path) ) {
            @unlink($path);
        }
    }

    /**
     * بررسی اندازه فایل لاگ
     */
    public static function size(): string {
        $path = self::path();
        if ( ! file_exists($path) ) return '0 KB';
        $bytes = filesize($path);
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 2) . ' KB';
        return round($bytes / 1048576, 2) . ' MB';
    }

    /**
     * حذف خودکار لاگ در صورت سنگین شدن بیش از 5MB
     */
    public static function auto_cleanup(): void {
        $path = self::path();
        if ( file_exists($path) && filesize($path) > 5 * 1024 * 1024 ) {
            @unlink($path);
            self::add('🧹 فایل لاگ بیش از ۵ مگابایت بود و حذف شد.', 'warn');
        }
    }
}
