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

// Keep this endpoint snappy so the login UI cannot stick on "Sending..."
@set_time_limit(25);
ignore_user_abort(true);

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

// Rate limit: max 8 new tokens per email per hour.
// If over the limit but the user has no usable open token, still issue one recovery token
// so they are not permanently stuck after a failed email / used link.
try {
    $db = Database::getInstance()->getConnection();
    ensurePasswordResetsTable($db);

    $rlStmt = $db->prepare("
        SELECT COUNT(*) FROM auth_password_resets
        WHERE email_sent_to = ?
          AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $rlStmt->execute([$email]);
    $recentCount = (int) $rlStmt->fetchColumn();

    $stmt = $db->prepare("
        SELECT id, username, full_name, first_name, last_name, email, role, is_active
        FROM users
        WHERE LOWER(email) = ? AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $openStmt = $db->prepare("
            SELECT COUNT(*) FROM auth_password_resets
            WHERE user_id = ?
              AND used_at IS NULL
              AND expires_at > NOW()
        ");
        $openStmt->execute([$user['id']]);
        $openCount = (int) $openStmt->fetchColumn();

        // Block only when hammering requests AND a still-valid unused token already exists
        $allowIssue = ($recentCount < 8) || ($openCount === 0);

        if ($allowIssue) {
            $userName = $user['full_name'] ?: trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

            // Invalidate previous unused tokens so only the newest email link works
            $db->prepare("
                UPDATE auth_password_resets
                SET used_at = NOW(),
                    expires_at = LEAST(expires_at, NOW())
                WHERE user_id = ? AND used_at IS NULL
            ")->execute([$user['id']]);

            $rawToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);
            $expiresAt = date('Y-m-d H:i:s', time() + 3600);

            $db->prepare("
                INSERT INTO auth_password_resets (token_hash, user_id, email_sent_to, expires_at)
                VALUES (?, ?, ?, ?)
            ")->execute([$tokenHash, $user['id'], $email, $expiresAt]);

            // Build reset URL (prefer current host when running on localhost)
            $resetUrl = rtrim(APP_URL, '/') . '/html/reset-password.html?token=' . urlencode($rawToken);
            $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
            if ($host !== '' && (str_contains($host, 'localhost') || str_contains($host, '127.0.0.1'))) {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $basePath = '';
                $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
                if (preg_match('#^(.*?)/api/auth/forgot_password\.php$#', $script, $m)) {
                    $basePath = $m[1];
                }
                $resetUrl = $scheme . '://' . $host . $basePath . '/html/reset-password.html?token=' . urlencode($rawToken);
            }

            try {
                $bodyHtml = buildResetEmailBody($userName ?: $user['username'], $resetUrl, $expiresAt);
                $emailHtml = Mailer::buildTemplate('Password Reset Request', $bodyHtml);
                Mailer::send(
                    $user['email'],
                    'Password Reset — Highland Fresh Dairy',
                    $emailHtml
                );
            } catch (Throwable $e) {
                error_log("Forgot password email error for user {$user['id']}: " . $e->getMessage());
                error_log("Forgot password reset URL (email failed) for user {$user['id']}: {$resetUrl}");
            }

            try {
                logAudit($user['id'], 'PASSWORD_RESET_REQUESTED', 'users', $user['id'], null, [
                    'email' => $email,
                    'expires_at' => $expiresAt
                ]);
            } catch (Exception $e) {
                error_log("Forgot password audit warning: " . $e->getMessage());
            }
        } else {
            error_log("Forgot password rate-limited for {$email} (recent={$recentCount}, open={$openCount})");
        }
    }
    // If user not found, still return success to prevent email enumeration

} catch (Exception $e) {
    error_log("Forgot password error: " . $e->getMessage());
    // Still return success to prevent information leakage
}

// Always return success to prevent email enumeration
Response::success(null, 'If an account exists with that email address, a password reset link has been sent. Please check your inbox.');


/**
 * Build password reset email body (inner content for Mailer::buildTemplate)
 * Uses table-based CTA for Outlook; inline styles only for Gmail/Apple Mail.
 */
function buildResetEmailBody($userName, $resetUrl, $expiresAt) {
    $expiresFormatted = date('g:i A', strtotime($expiresAt));
    $name = htmlspecialchars((string) $userName, ENT_QUOTES, 'UTF-8');
    $url = htmlspecialchars((string) $resetUrl, ENT_QUOTES, 'UTF-8');
    $expiresSafe = htmlspecialchars($expiresFormatted, ENT_QUOTES, 'UTF-8');
    $fontStack = "system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";

    return <<<HTML
<p style="margin:0 0 8px 0;font-family:{$fontStack};color:#17211b;font-size:16px;font-weight:600;line-height:1.4;">
    Hi {$name},
</p>
<p style="margin:0 0 24px 0;font-family:{$fontStack};color:#4A5560;font-size:15px;font-weight:400;line-height:1.65;">
    We received a request to reset the password for your Highland Fresh Dairy account.
    Use the button below to choose a new password.
</p>

<!-- CTA button (bulletproof table for Outlook) -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="margin:0 auto 28px auto;border-collapse:collapse;">
    <tr>
        <td align="center" bgcolor="#1f7a4d" style="background-color:#1f7a4d;border-radius:8px;mso-padding-alt:14px 32px;">
            <!--[if mso]>
            <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" href="{$url}" style="height:48px;v-text-anchor:middle;width:240px;" arcsize="17%" stroke="f" fillcolor="#1f7a4d">
                <w:anchorlock/>
                <center style="color:#ffffff;font-family:Arial,sans-serif;font-size:16px;font-weight:bold;">Reset My Password</center>
            </v:roundrect>
            <![endif]-->
            <!--[if !mso]><!-- -->
            <a href="{$url}" target="_blank" rel="noopener"
               style="display:inline-block;background-color:#1f7a4d;color:#ffffff;font-family:{$fontStack};font-size:16px;font-weight:700;line-height:1.25;text-align:center;text-decoration:none;padding:14px 32px;border-radius:8px;mso-padding-alt:0;">
                Reset My Password
            </a>
            <!--<![endif]-->
        </td>
    </tr>
</table>

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:collapse;margin:0 0 24px 0;">
    <tr>
        <td style="background-color:#F7FAF8;border:1px solid #D8E8DE;border-radius:8px;padding:14px 16px;font-family:{$fontStack};color:#3D5C4A;font-size:13px;font-weight:400;line-height:1.55;">
            <strong style="font-weight:700;color:#1f7a4d;">Important:</strong>
            This link expires in <strong>1 hour</strong> (around {$expiresSafe}) and can only be used once.
        </td>
    </tr>
</table>

<p style="margin:0 0 16px 0;font-family:{$fontStack};color:#6B756F;font-size:13px;font-weight:400;line-height:1.6;">
    If you did not request this password reset, you can safely ignore this email. Your password will not change.
</p>
<p style="margin:0;font-family:{$fontStack};color:#8A9590;font-size:12px;font-weight:400;line-height:1.55;">
    If the button does not work, copy and paste this URL into your browser:<br />
    <a href="{$url}" style="color:#1f7a4d;text-decoration:underline;word-break:break-all;">{$url}</a>
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
