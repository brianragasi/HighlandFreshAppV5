-- ============================================================================
-- Phase 3: Password Recovery — Password Resets Table
-- Highland Fresh Dairy Production System
--
-- Stores hashed reset tokens for the forgot-password flow
-- ============================================================================

CREATE TABLE IF NOT EXISTS `auth_password_resets` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `token_hash` CHAR(64) NOT NULL COMMENT 'SHA-256 hash of the reset token',
    `user_id` INT NOT NULL,
    `email_sent_to` VARCHAR(255) NOT NULL COMMENT 'Email that received the link',
    `expires_at` DATETIME NOT NULL COMMENT 'Token expiry',
    `used_at` DATETIME NULL COMMENT 'When the token was consumed',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_password_resets_token_hash` (`token_hash`),
    KEY `idx_password_resets_user_id` (`user_id`),
    KEY `idx_password_resets_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
