-- =============================================
-- Highland Fresh - Step-up authentication tokens
-- Short-lived, one-time tokens tied to auth session
-- =============================================

CREATE TABLE IF NOT EXISTS `auth_stepups` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `token_hash` CHAR(64) NOT NULL,
    `user_id` INT NOT NULL,
    `session_id` VARCHAR(64) NOT NULL,
    `scope` VARCHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_auth_stepups_token_hash` (`token_hash`),
    KEY `idx_auth_stepups_user_session_scope` (`user_id`, `session_id`, `scope`),
    KEY `idx_auth_stepups_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
