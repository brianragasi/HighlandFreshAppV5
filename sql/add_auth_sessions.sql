-- =============================================
-- Highland Fresh - Server-side auth sessions
-- Enforces idle timeout and token revocation
-- =============================================

CREATE TABLE IF NOT EXISTS `auth_sessions` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `session_id` VARCHAR(64) NOT NULL,
    `user_id` INT NOT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL,
    `issued_at` DATETIME NOT NULL,
    `last_activity` DATETIME NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `revoked_at` DATETIME DEFAULT NULL,
    `revoked_reason` VARCHAR(50) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_auth_sessions_session_id` (`session_id`),
    KEY `idx_auth_sessions_user_id` (`user_id`),
    KEY `idx_auth_sessions_expires_at` (`expires_at`),
    KEY `idx_auth_sessions_revoked_at` (`revoked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
