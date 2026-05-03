-- ============================================================================
-- Phase 2: Onboarding Credential Delivery
-- Highland Fresh Dairy Production System
--
-- Creates the auth_invites table for invitation token flow
-- ============================================================================

CREATE TABLE IF NOT EXISTS `auth_invites` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `token_hash` CHAR(64) NOT NULL COMMENT 'SHA-256 hash of the invite token',
    `user_id` INT NOT NULL COMMENT 'The user this invite is for',
    `invite_type` ENUM('email','manual') NOT NULL DEFAULT 'email' COMMENT 'How the invite was delivered',
    `email_sent_to` VARCHAR(255) NULL COMMENT 'Email address the invite was sent to',
    `expires_at` DATETIME NOT NULL COMMENT 'When the invite token expires',
    `used_at` DATETIME NULL COMMENT 'When the invite was used (set-password)',
    `created_by` INT NOT NULL COMMENT 'Admin who created the invite',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_auth_invites_token_hash` (`token_hash`),
    KEY `idx_auth_invites_user_id` (`user_id`),
    KEY `idx_auth_invites_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
