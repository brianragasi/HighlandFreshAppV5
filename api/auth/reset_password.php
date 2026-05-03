<?php
/**
 * Highland Fresh System - Reset Password via Token
 *
 * GET /api/auth/reset_password.php?token=xxx
 *   - Validates reset token (public)
 *
 * POST /api/auth/reset_password.php
 *   - Public endpoint (no auth required — uses reset token)
 *   - Sets new password
 *   - Marks token as used
 *   - Revokes all active sessions
 *
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

if ($requestMethod === 'GET') {
    handleValidateResetToken();
} elseif ($requestMethod === 'POST') {
    handleResetPassword();
} else {
    Response::error('Method not allowed', 405);
}


/**
 * Validate reset token (GET — used by frontend to check before showing form)
 */
function handleValidateResetToken() {
    $token = trim((string) ($_GET['token'] ?? ''));
    if (empty($token)) {
        Response::error('Reset token is required', 400);
    }

    $db = Database::getInstance()->getConnection();
    ensurePasswordResetsTableExists($db);
    $tokenHash = hash('sha256', $token);

    $stmt = $db->prepare("
        SELECT r.id, r.user_id, r.expires_at, r.used_at,
               u.full_name, u.first_name, u.last_name, u.username, u.email
        FROM auth_password_resets r
        JOIN users u ON u.id = r.user_id
        WHERE r.token_hash = ?
        LIMIT 1
    ");
    $stmt->execute([$tokenHash]);
    $reset = $stmt->fetch();

    if (!$reset) {
        Response::error('Invalid or expired reset link. Please request a new one.', 404);
    }

    if ($reset['used_at'] !== null) {
        Response::error('This reset link has already been used. Please request a new one.', 410);
    }

    if (strtotime($reset['expires_at']) <= time()) {
        Response::error('This reset link has expired. Please request a new one.', 410);
    }

    Response::success([
        'full_name' => $reset['full_name'] ?: trim(($reset['first_name'] ?? '') . ' ' . ($reset['last_name'] ?? '')),
        'username' => $reset['username'],
        'email' => $reset['email']
    ], 'Reset token is valid');
}


/**
 * Reset password using token
 */
function handleResetPassword() {
    $token = trim((string) getParam('token', ''));
    $newPassword = trim((string) getParam('new_password', ''));
    $confirmPassword = trim((string) getParam('confirm_password', ''));

    $errors = [];
    if (empty($token)) {
        $errors['token'] = 'Reset token is required';
    }
    if (empty($newPassword)) {
        $errors['new_password'] = 'New password is required';
    }
    if (strlen($newPassword) < 6) {
        $errors['new_password'] = 'Password must be at least 6 characters';
    }
    if ($newPassword !== $confirmPassword) {
        $errors['confirm_password'] = 'Passwords do not match';
    }

    if (!empty($errors)) {
        Response::validationError($errors);
    }

    try {
        $db = Database::getInstance()->getConnection();
        ensurePasswordResetsTableExists($db);
        $tokenHash = hash('sha256', $token);

        // Find and validate reset record
        $stmt = $db->prepare("
            SELECT r.id, r.user_id, r.expires_at, r.used_at,
                   u.username, u.is_active
            FROM auth_password_resets r
            JOIN users u ON u.id = r.user_id
            WHERE r.token_hash = ?
            LIMIT 1
        ");
        $stmt->execute([$tokenHash]);
        $reset = $stmt->fetch();

        if (!$reset) {
            Response::error('Invalid reset link. Please request a new one.', 404);
        }

        if ($reset['used_at'] !== null) {
            Response::error('This reset link has already been used.', 410);
        }

        if (strtotime($reset['expires_at']) <= time()) {
            Response::error('This reset link has expired. Please request a new one.', 410);
        }

        if (!(int) $reset['is_active']) {
            Response::error('Your account has been deactivated. Please contact your administrator.', 403);
        }

        $userId = (int) $reset['user_id'];
        $resetId = (int) $reset['id'];

        // Transaction
        $db->beginTransaction();

        try {
            // Hash and set new password
            $hashedPassword = Auth::hashPassword($newPassword);

            $updateStmt = $db->prepare("
                UPDATE users 
                SET password = ?,
                    must_change_password = 0,
                    password_set_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$hashedPassword, $userId]);

            // Mark reset token as used
            $db->prepare("UPDATE auth_password_resets SET used_at = NOW() WHERE id = ?")->execute([$resetId]);

            // Revoke all active sessions (security — force re-login with new password)
            Auth::revokeAllSessionsByUserId($userId, 'password_reset_via_email');

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }

        // Audit log
        try {
            logAudit($userId, 'PASSWORD_RESET_COMPLETED', 'users', $userId, [
                'must_change_password' => 1
            ], [
                'must_change_password' => 0,
                'reset_id' => $resetId,
                'method' => 'email_token'
            ]);
        } catch (Exception $e) {
            error_log("Password reset audit warning: " . $e->getMessage());
        }

        Response::success([
            'username' => $reset['username']
        ], 'Password reset successfully! You can now log in with your new password.');

    } catch (Exception $e) {
        error_log("Reset password error: " . $e->getMessage());
        Response::error('An error occurred. Please try again.', 500);
    }
}


/**
 * Ensure table exists
 */
function ensurePasswordResetsTableExists($db) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `auth_password_resets` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `token_hash` CHAR(64) NOT NULL,
            `user_id` INT NOT NULL,
            `email_sent_to` VARCHAR(255) NOT NULL,
            `expires_at` DATETIME NOT NULL,
            `used_at` DATETIME NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_password_resets_token_hash` (`token_hash`),
            KEY `idx_password_resets_user_id` (`user_id`),
            KEY `idx_password_resets_expires_at` (`expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}
