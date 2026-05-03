-- =============================================
-- Highland Fresh - Login rate limiting table
-- Tracks failed login attempts by username + IP
-- =============================================

CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(100) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `failed_attempts` INT NOT NULL DEFAULT 0,
    `first_failed_at` DATETIME DEFAULT NULL,
    `last_failed_at` DATETIME DEFAULT NULL,
    `locked_until` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_login_attempts_username_ip` (`username`, `ip_address`),
    KEY `idx_login_attempts_username` (`username`),
    KEY `idx_login_attempts_locked_until` (`locked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
