<?php
/**
 * Highland Fresh System - Rate Limiting Helper
 *
 * Provides IP-based and user-based rate limiting for sensitive endpoints.
 *
 * @package HighlandFresh
 * @version 4.0
 */

// Prevent direct access
if (!defined('HIGHLAND_FRESH')) {
    http_response_code(403);
    exit('Direct access not allowed');
}

class RateLimiter {

    /**
     * Check and record a rate limit hit
     *
     * @param string $key       Unique identifier (e.g., 'set_password:ip:192.168.1.1' or 'set_password:user:123')
     * @param int    $maxAttempts Maximum attempts allowed in the window
     * @param int    $windowSeconds Time window in seconds
     * @return array ['allowed' => bool, 'remaining' => int, 'resetAt' => int, 'retryAfter' => int|null]
     */
    public static function check($key, $maxAttempts, $windowSeconds) {
        $db = Database::getInstance()->getConnection();

        // Ensure table exists
        self::ensureTable($db);

        $now = time();
        $windowStart = $now - $windowSeconds;

        // Clean old entries
        $db->prepare("DELETE FROM rate_limits WHERE expires_at < ?")->execute([$now]);

        // Get current count
        $stmt = $db->prepare("SELECT count, expires_at FROM rate_limits WHERE `key` = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $count = (int)$row['count'];
            $expiresAt = (int)$row['expires_at'];

            if ($count >= $maxAttempts) {
                return [
                    'allowed' => false,
                    'remaining' => 0,
                    'resetAt' => $expiresAt,
                    'retryAfter' => max(1, $expiresAt - $now)
                ];
            }

            // Increment
            $newCount = $count + 1;
            $db->prepare("UPDATE rate_limits SET count = ?, updated_at = ? WHERE `key` = ?")
                ->execute([$newCount, $now, $key]);

            return [
                'allowed' => true,
                'remaining' => max(0, $maxAttempts - $newCount),
                'resetAt' => $expiresAt,
                'retryAfter' => null
            ];
        }

        // First attempt
        $expiresAt = $now + $windowSeconds;
        $db->prepare("INSERT INTO rate_limits (`key`, count, expires_at, created_at, updated_at) VALUES (?, 1, ?, ?, ?)")
            ->execute([$key, $expiresAt, $now, $now]);

        return [
            'allowed' => true,
            'remaining' => $maxAttempts - 1,
            'resetAt' => $expiresAt,
            'retryAfter' => null
        ];
    }

    /**
     * Reset rate limit for a key (e.g., after successful authentication)
     */
    public static function reset($key) {
        $db = Database::getInstance()->getConnection();
        self::ensureTable($db);
        $db->prepare("DELETE FROM rate_limits WHERE `key` = ?")->execute([$key]);
    }

    /**
     * Ensure rate_limits table exists
     */
    private static function ensureTable($db) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `rate_limits` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `key` VARCHAR(191) NOT NULL,
                `count` INT NOT NULL DEFAULT 1,
                `expires_at` INT NOT NULL,
                `created_at` INT NOT NULL,
                `updated_at` INT NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_rate_limits_key` (`key`),
                KEY `idx_rate_limits_expires` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }
}