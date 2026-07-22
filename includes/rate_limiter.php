<?php
/**
 * Layer 09: Rate Limiting & Cooldown Management
 * Handles API rate limiting and anti-double-trigger logic (e.g., 30s cooldowns).
 */

class RateLimiter {
    private static $defaultCooldown = 30; // seconds

    /**
     * Check if an action is allowed for a key (e.g. user_id or IP)
     */
    public static function isAllowed($key, $action = 'default', $cooldownSeconds = 30) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $rateKey = "rate_limit_{$action}_{$key}";
        $currentTime = time();

        if (isset($_SESSION[$rateKey])) {
            $lastTime = $_SESSION[$rateKey];
            if (($currentTime - $lastTime) < $cooldownSeconds) {
                return false; // Rate limited
            }
        }

        // Record execution time
        $_SESSION[$rateKey] = $currentTime;
        return true;
    }

    /**
     * Get remaining cooldown seconds
     */
    public static function getRemainingCooldown($key, $action = 'default', $cooldownSeconds = 30) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $rateKey = "rate_limit_{$action}_{$key}";
        if (isset($_SESSION[$rateKey])) {
            $elapsed = time() - $_SESSION[$rateKey];
            if ($elapsed < $cooldownSeconds) {
                return $cooldownSeconds - $elapsed;
            }
        }
        return 0;
    }
}
?>
