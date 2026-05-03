<?php
/**
 * Highland Fresh System - Change Password API
 *
 * POST /api/auth/change_password.php
 *   - Requires authenticated user
 *   - Validates current password
 *   - Updates password hash
 *   - Sets must_change_password=0, password_set_at=NOW()
 *   - Revokes all other active sessions for this user
 *
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Only allow POST
if ($requestMethod !== 'POST') {
    Response::error('Method not allowed', 405);
}

// Require authenticated user
$currentUser = Auth::requireAuth();
$userId = (int) $currentUser['user_id'];
$sessionId = $currentUser['sid'] ?? null;

// Validate input
$currentPassword = trim((string) getParam('current_password', ''));
$newPassword = trim((string) getParam('new_password', ''));
$confirmPassword = trim((string) getParam('confirm_password', ''));

$errors = [];
if (empty($currentPassword)) {
    $errors['current_password'] = 'Current password is required';
}
if (empty($newPassword)) {
    $errors['new_password'] = 'New password is required';
}
if (strlen($newPassword) < 6) {
    $errors['new_password'] = 'New password must be at least 6 characters';
}
if ($newPassword !== $confirmPassword) {
    $errors['confirm_password'] = 'Passwords do not match';
}
if ($currentPassword === $newPassword) {
    $errors['new_password'] = 'New password must be different from current password';
}

if (!empty($errors)) {
    Response::validationError($errors);
}

try {
    $db = Database::getInstance()->getConnection();

    // Fetch current password hash
    $stmt = $db->prepare("SELECT id, password FROM users WHERE id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        Response::error('User not found or account is deactivated', 404);
    }

    // Verify current password
    if (!Auth::verifyPassword($currentPassword, $user['password'])) {
        Response::error('Current password is incorrect', 401);
    }

    // Hash new password and update
    $newHash = Auth::hashPassword($newPassword);

    // Build update - handle missing columns gracefully
    $updateFields = "password = ?, updated_at = NOW()";
    $updateParams = [$newHash];

    // Try to update must_change_password and password_set_at (Phase 1 columns)
    try {
        $checkStmt = $db->prepare("
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'users'
              AND COLUMN_NAME = 'must_change_password'
        ");
        $checkStmt->execute();
        $hasOnboardingColumns = (int) $checkStmt->fetchColumn() > 0;
    } catch (Exception $e) {
        $hasOnboardingColumns = false;
    }

    if ($hasOnboardingColumns) {
        $updateFields = "password = ?, must_change_password = 0, password_set_at = NOW(), updated_at = NOW()";
    }

    $updateStmt = $db->prepare("UPDATE users SET {$updateFields} WHERE id = ?");
    $updateParams[] = $userId;
    $updateStmt->execute($updateParams);

    // Revoke all OTHER sessions (keep current session active)
    $revokedCount = Auth::revokeAllSessionsByUserId($userId, 'password_change', $sessionId);

    // Audit log
    try {
        logAudit($userId, 'PASSWORD_CHANGE', 'users', $userId, [
            'password_changed' => false,
            'must_change_password' => 1
        ], [
            'password_changed' => true,
            'must_change_password' => 0,
            'sessions_revoked' => $revokedCount
        ]);
    } catch (Exception $e) {
        error_log("Password change audit warning: " . $e->getMessage());
    }

    Response::success([
        'sessions_revoked' => $revokedCount
    ], 'Password changed successfully');

} catch (Exception $e) {
    error_log("Password change error: " . $e->getMessage());
    Response::error('An error occurred while changing password', 500);
}
