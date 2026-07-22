<?php
/**
 * Layer 10: Caching & CDN Helper
 * Handles in-memory session caching and descriptor/token store for high-performance lookups.
 */

class CacheHelper {
    private static $cacheDir = __DIR__ . '/../uploads/cache/';

    public static function init() {
        if (!file_exists(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0777, true);
        }
    }

    /**
     * Get item from cache or session
     */
    public static function get($key) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (isset($_SESSION['cache_' . $key])) {
            return $_SESSION['cache_' . $key];
        }

        self::init();
        $file = self::$cacheDir . md5($key) . '.cache';
        if (file_exists($file) && (time() - filemtime($file) < 3600)) {
            return json_decode(file_get_contents($file), true);
        }

        return null;
    }

    /**
     * Store item in cache
     */
    public static function set($key, $value, $ttlSeconds = 3600) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['cache_' . $key] = $value;

        self::init();
        $file = self::$cacheDir . md5($key) . '.cache';
        file_put_contents($file, json_encode($value));
    }

    /**
     * Clear cached key
     */
    public static function delete($key) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        unset($_SESSION['cache_' . $key]);

        self::init();
        $file = self::$cacheDir . md5($key) . '.cache';
        if (file_exists($file)) {
            unlink($file);
        }
    }
}
?>
