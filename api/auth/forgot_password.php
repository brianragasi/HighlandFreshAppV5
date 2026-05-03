<?php
/**
 * Highland Fresh System - Forgot Password API
 *
 * POST /api/auth/forgot_password.php
 *   - Public endpoint (no auth required)
 *   - Accepts email address
 *   - If user exists with that email, sends a password reset link
 *   - Always returns success (prevents email enumeration)
 *
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Only allow POST
if ($requestMethod !== 'POST') {
    Response::error('Method not allowed', 405);
}

$email = strtolower(trim((string) getParam('email', '')));

if (empty($email)) {
    Response::validationError(['email' => 'Email address is required']);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    Response::validationError(['email' => 'Please enter a valid email address']);
}

// Rate limit: max 3 reset requests per email per hour
$rateLimitOk = true;

try {
    $db = Database::getInstance()->getConnection();
    ensurePasswordResetsTable($db);

    // Rate limit check
    $rlStmt = $db->prepare("
        SELECT COUNT(*) FROM auth_password_resets
        WHERE email_sent_to = ?
          AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $rlStmt->execute([$email]);
    $recentCount = (int) $rlStmt->fetchColumn();

    if ($recentCount >= 3) {
        $rateLimitOk = false;
    }

    if ($rateLimitOk) {
        // Find user by email
        $stmt = $db->prepare("
            SELECT id, username, full_name, first_name, last_name, email, role, is_active
            FROM users
            WHERE LOWER(email) = ? AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Invalidate any existing unused reset tokens for this user
            $db->prepare("
                UPDATE auth_password_resets
                SET used_at = NOW()
                WHERE user_id = ? AND used_at IS NULL
            ")->execute([$user['id']]);

            // Generate token
            $rawToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);
            $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiry

            // Store reset record
            $insertStmt = $db->prepare("
                INSERT INTO auth_password_resets (token_hash, user_id, email_sent_to, expires_at)
                VALUES (?, ?, ?, ?)
            ");
            $insertStmt->execute([$tokenHash, $user['id'], $email, $expiresAt]);

            // Build reset URL
            $resetUrl = APP_URL . '/html/reset-password.html?token=' . urlencode($rawToken);

            // Send email
            try {
                $userName = $user['full_name'] ?: trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                $bodyHtml = buildResetEmailBody($userName, $resetUrl, $expiresAt);
                $emailHtml = Mailer::buildTemplate('Password Reset Request', $bodyHtml);

                Mailer::send(
                    $user['email'],
                    'Password Reset — Highland Fresh Dairy',
                    $emailHtml
                );
            } catch (Exception $e) {
                error_log("Forgot password email error for user {$user['id']}: " . $e->getMessage());
                // Don't expose email errors to prevent enumeration
            }

            // Audit log
            try {
                logAudit($user['id'], 'PASSWORD_RESET_REQUESTED', 'users', $user['id'], null, [
                    'email' => $email,
                    'expires_at' => $expiresAt
                ]);
            } catch (Exception $e) {
                error_log("Forgot password audit warning: " . $e->getMessage());
            }
        }
        // If user not found, we still return success to prevent email enumeration
    }

} catch (Exception $e) {
    error_log("Forgot password error: " . $e->getMessage());
    // Still return success to prevent information leakage
}

// Always return success to prevent email enumeration
Response::success(null, 'If an account exists with that email address, a password reset link has been sent. Please check your inbox.');


/**
 * Build password reset email HTML
 */
function buildResetEmailBody($userName, $resetUrl, $expiresAt) {
    $expiresFormatted = date('g:i A', strtotime($expiresAt));

    return <<<HTML
<p style="color:#495057;font-size:15px;line-height:1.7;margin:0 0 20px;">
    Hi <strong>{$userName}</strong>,
</p>
<p style="color:#495057;font-size:15px;line-height:1.7;margin:0 0 20px;">
    We received a request to reset your password for your Highland Fresh Dairy account.
    Click the button below to set a new password:
</p>
<div style="text-align:center;margin:0 0 24px;">
    <a href="{$resetUrl}"
       style="display:inline-block;background:linear-gradient(135deg,#dc3545,#e04855);color:#ffffff;text-decoration:none;padding:14px 36px;border-radius:8px;font-weight:600;font-size:16px;letter-spacing:0.3px;box-shadow:0 4px 12px rgba(220,53,69,0.3);">
        Reset My Password
    </a>
</div>
<div style="background-color:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:16px;margin:0 0 20px;">
    <p style="color:#856404;margin:0;font-size:13px;">
        <strong>⚠ Important:</strong> This link expires in <strong>1 hour</strong> (at {$expiresFormatted})
        and can only be used once.
    </p>
</div>
<p style="color:#6c757d;font-size:13px;margin:0 0 12px;">
    If you didn't request this password reset, you can safely ignore this email.
    Your password will remain unchanged.
</p>
<p style="color:#6c757d;font-size:13px;margin:0;">
    If the button doesn't work, copy and paste this URL into your browser:<br>
    <a href="{$resetUrl}" style="color:#dc3545;word-break:break-all;">{$resetUrl}</a>
</p>
HTML;
}


/**
 * Ensure auth_password_resets table exists
 */
function ensurePasswordResetsTable($db) {
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
