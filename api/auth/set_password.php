<?php
/**
 * Highland Fresh System - Set Password via Invite Token
 *
 * POST /api/auth/set_password.php
 *   - Public endpoint (no auth required — uses invite token)
 *   - Validates invite token
 *   - Sets user password
 *   - Marks invite as used
 *   - Sets must_change_password=0
 *
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Only allow POST
if ($requestMethod !== 'POST') {
    Response::error('Method not allowed', 405);
}

// Validate input
$token = trim((string) getParam('token', ''));
$newPassword = trim((string) getParam('new_password', ''));
$confirmPassword = trim((string) getParam('confirm_password', ''));

$errors = [];
if (empty($token)) {
    $errors['token'] = 'Invite token is required';
}
if (empty($newPassword)) {
    $errors['new_password'] = 'Password is required';
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

    // Ensure table exists
    $db->exec("
        CREATE TABLE IF NOT EXISTS `auth_invites` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `token_hash` CHAR(64) NOT NULL,
            `user_id` INT NOT NULL,
            `invite_type` ENUM('email','manual') NOT NULL DEFAULT 'email',
            `email_sent_to` VARCHAR(255) NULL,
            `expires_at` DATETIME NOT NULL,
            `used_at` DATETIME NULL,
            `created_by` INT NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_auth_invites_token_hash` (`token_hash`),
            KEY `idx_auth_invites_user_id` (`user_id`),
            KEY `idx_auth_invites_expires_at` (`expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $tokenHash = hash('sha256', $token);

    // Find and validate invite
    $stmt = $db->prepare("
        SELECT i.id, i.user_id, i.expires_at, i.used_at,
               u.username, u.is_active
        FROM auth_invites i
        JOIN users u ON u.id = i.user_id
        WHERE i.token_hash = ?
        LIMIT 1
    ");
    $stmt->execute([$tokenHash]);
    $invite = $stmt->fetch();

    if (!$invite) {
        Response::error('Invalid invite link. Please request a new one from your administrator.', 404);
    }

    if ($invite['used_at'] !== null) {
        Response::error('This invite link has already been used. If you need access, contact your administrator.', 410);
    }

    if (strtotime($invite['expires_at']) <= time()) {
        Response::error('This invite link has expired. Please contact your administrator for a new one.', 410);
    }

    if (!(int) $invite['is_active']) {
        Response::error('Your account has been deactivated. Please contact your administrator.', 403);
    }

    $userId = (int) $invite['user_id'];
    $inviteId = (int) $invite['id'];

    // Start transaction
    $db->beginTransaction();

    try {
        // Hash and set password
        $hashedPassword = Auth::hashPassword($newPassword);

        // Update user password + clear must_change_password
        $updateFields = "password = ?, must_change_password = 0, password_set_at = NOW(), updated_at = NOW()";
        $updateStmt = $db->prepare("UPDATE users SET {$updateFields} WHERE id = ?");
        $updateStmt->execute([$hashedPassword, $userId]);

        // Mark invite as used
        $db->prepare("UPDATE auth_invites SET used_at = NOW() WHERE id = ?")->execute([$inviteId]);

        // Revoke any existing sessions (fresh start)
        Auth::revokeAllSessionsByUserId($userId, 'password_set_via_invite');

        if ($db->inTransaction()) {
            $db->commit();
        }
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            try {
                $db->rollBack();
            } catch (Throwable $rollbackError) {
                error_log('Set password rollback warning: ' . $rollbackError->getMessage());
            }
        }
        throw $e;
    }

    // Audit log
    try {
        logAudit($userId, 'PASSWORD_SET_VIA_INVITE', 'users', $userId, [
            'must_change_password' => 1,
            'invite_id' => $inviteId
        ], [
            'must_change_password' => 0,
            'password_set' => true,
            'invite_id' => $inviteId
        ]);
    } catch (Throwable $e) {
        error_log("Set password audit warning: " . $e->getMessage());
    }

    Response::success([
        'username' => $invite['username']
    ], 'Password set successfully! You can now log in with your new password.');

} catch (Throwable $e) {
    error_log("Set password error: " . $e->getMessage());
    Response::error('An error occurred while setting your password. Please try again.', 500);
}
