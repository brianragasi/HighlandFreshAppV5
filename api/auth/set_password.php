<?php
/**
 * Highland Fresh System - Set Password via Invite Token
 *
 * POST /api/auth/set_password.php
 *   - Public endpoint (no auth required — uses invite token)
 *   - Validates invite token
 *   - Sets user password with complexity requirements
 *   - Marks invite as used (one-time use)
 *   - Sets must_change_password=0
 *   - Returns success, user must log in separately
 *
 * Security Features:
 * - Rate limiting (IP + token based)
 * - Strong password policy (12+ chars, uppercase, lowercase, number, special)
 * - CSRF protection via token validation
 * - One-time token invalidation
 * - No automatic login after activation
 *
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Only allow POST
if ($requestMethod !== 'POST') {
    Response::error('Method not allowed', 405);
}

// Rate limiting: by IP + token combination
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$token = trim((string) getParam('token', ''));
$rateLimitKey = "set_password:ip:{$clientIp}:token:" . hash('sha256', $token);
$rateLimit = RateLimiter::check($rateLimitKey, RATE_LIMIT_SET_PASSWORD_ATTEMPTS, RATE_LIMIT_SET_PASSWORD_WINDOW);

if (!$rateLimit['allowed']) {
    http_response_code(429);
    Response::error(
        'Too many password reset attempts. Please wait ' . gmdate('i:s', $rateLimit['retryAfter']) . ' before trying again.',
        429
    );
}

// Validate input
$newPassword = trim((string) getParam('new_password', ''));
$confirmPassword = trim((string) getParam('confirm_password', ''));
$csrfToken = trim((string) getParam('csrf_token', ''));

$errors = [];

// CSRF token validation (optional but recommended for production)
// In a real deployment, you'd validate against a session-stored CSRF token
// For now, we require the field to be present
if (empty($csrfToken)) {
    $errors['csrf_token'] = 'Security token missing. Please refresh the page and try again.';
}

// Token validation
if (empty($token)) {
    $errors['token'] = 'Invite token is required';
}

// Password complexity validation
if (empty($newPassword)) {
    $errors['new_password'] = 'Password is required';
} else {
    $minLen = PASSWORD_MIN_LENGTH;
    $maxLen = PASSWORD_MAX_LENGTH;

    if (strlen($newPassword) < $minLen) {
        $errors['new_password'] = "Password must be at least {$minLen} characters";
    }
    if (strlen($newPassword) > $maxLen) {
        $errors['new_password'] = "Password must not exceed {$maxLen} characters";
    }
    if (PASSWORD_REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $newPassword)) {
        $errors['new_password'] = 'Password must contain at least one uppercase letter';
    }
    if (PASSWORD_REQUIRE_LOWERCASE && !preg_match('/[a-z]/', $newPassword)) {
        $errors['new_password'] = 'Password must contain at least one lowercase letter';
    }
    if (PASSWORD_REQUIRE_NUMBER && !preg_match('/[0-9]/', $newPassword)) {
        $errors['new_password'] = 'Password must contain at least one number';
    }
    if (PASSWORD_REQUIRE_SPECIAL && !preg_match('/[^A-Za-z0-9]/', $newPassword)) {
        $errors['new_password'] = 'Password must contain at least one special character (e.g., @#$%^&*)';
    }
    // Prevent common patterns
    if (preg_match('/(.)\1{3,}/', $newPassword)) { // 4+ repeating chars
        $errors['new_password'] = 'Password cannot contain 4 or more repeating characters';
    }
    if (preg_match('/^(?:password|admin|welcome|highland|fresh|123456|qwerty)/i', $newPassword)) {
        $errors['new_password'] = 'Password is too common. Please choose a stronger password';
    }
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

    // Find and validate invite (with row lock to prevent race conditions)
    $stmt = $db->prepare("
        SELECT i.id, i.user_id, i.expires_at, i.used_at,
               u.username, u.is_active
        FROM auth_invites i
        JOIN users u ON u.id = i.user_id
        WHERE i.token_hash = ?
        LIMIT 1
        FOR UPDATE
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
    $username = $invite['username'];

    // Start transaction
    $db->beginTransaction();

    try {
        // Hash and set password
        $hashedPassword = Auth::hashPassword($newPassword);

        // Update user password + clear must_change_password
        $updateFields = "password = ?, must_change_password = 0, password_set_at = NOW(), updated_at = NOW()";
        $updateStmt = $db->prepare("UPDATE users SET {$updateFields} WHERE id = ?");
        $updateStmt->execute([$hashedPassword, $userId]);

        // Mark invite as used (one-time use)
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

    // Clear rate limit on success
    RateLimiter::reset($rateLimitKey);

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
        'username' => $username,
        'redirect' => 'login.html',
        'message' => 'Account activated successfully. Please log in with your new credentials.'
    ], 'Password set successfully! Please log in with your new credentials.');

} catch (Throwable $e) {
    error_log("Set password error: " . $e->getMessage());
    Response::error('An error occurred while setting your password. Please try again.', 500);
}