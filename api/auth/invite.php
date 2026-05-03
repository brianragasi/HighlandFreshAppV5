<?php
/**
 * Highland Fresh System - Invitation API
 *
 * POST /api/auth/invite.php
 *   - Admin creates an invite for a user
 *   - For email users: sends one-time invite link
 *   - For no-email users: generates and returns a one-time temp credential
 *
 * GET /api/auth/invite.php?token=xxx
 *   - Validates an invite token (public — no auth required)
 *   - Returns user info if token is valid
 *
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Ensure auth_invites table exists
ensureAuthInvitesTable();

if ($requestMethod === 'GET') {
    // Public endpoint: validate an invite token
    handleValidateToken();
} elseif ($requestMethod === 'POST') {
    // Admin endpoint: create invite
    handleCreateInvite();
} else {
    Response::error('Method not allowed', 405);
}


/**
 * Validate invite token (public — used by set-password page)
 */
function handleValidateToken() {
    $token = trim((string) ($_GET['token'] ?? ''));
    if (empty($token)) {
        Response::error('Invite token is required', 400);
    }

    $db = Database::getInstance()->getConnection();
    $tokenHash = hash('sha256', $token);

    $stmt = $db->prepare("
        SELECT i.id, i.user_id, i.invite_type, i.expires_at, i.used_at,
               u.full_name, u.first_name, u.last_name, u.username, u.email, u.role
        FROM auth_invites i
        JOIN users u ON u.id = i.user_id
        WHERE i.token_hash = ?
        LIMIT 1
    ");
    $stmt->execute([$tokenHash]);
    $invite = $stmt->fetch();

    if (!$invite) {
        Response::error('Invalid or expired invite link', 404);
    }

    if ($invite['used_at'] !== null) {
        Response::error('This invite link has already been used', 410);
    }

    if (strtotime($invite['expires_at']) <= time()) {
        Response::error('This invite link has expired. Please contact your administrator for a new one.', 410);
    }

    Response::success([
        'user_id' => (int) $invite['user_id'],
        'full_name' => $invite['full_name'] ?: trim(($invite['first_name'] ?? '') . ' ' . ($invite['last_name'] ?? '')),
        'username' => $invite['username'],
        'role' => $invite['role'],
        'invite_type' => $invite['invite_type'],
        'expires_at' => $invite['expires_at']
    ], 'Invite token is valid');
}


/**
 * Create invite for a user (admin only)
 */
function handleCreateInvite() {
    $currentUser = Auth::requireRole(['general_manager', 'admin']);
    $adminUserId = (int) $currentUser['user_id'];

    $data = getRequestBody();
    $userId = (int) ($data['user_id'] ?? 0);
    $method = trim((string) ($data['method'] ?? 'auto')); // 'email', 'manual', or 'auto'

    if ($userId <= 0) {
        Response::error('User ID is required', 400);
    }

    $db = Database::getInstance()->getConnection();

    // Get user
    $stmt = $db->prepare("
        SELECT id, username, full_name, first_name, last_name, email, employee_id,
               role, is_active, login_type
        FROM users WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        Response::error('User not found', 404);
    }

    if (!(int) $user['is_active']) {
        Response::error('Cannot invite a deactivated user', 400);
    }

    // Determine method
    $hasEmail = !empty($user['email']) && trim($user['email']) !== '';
    if ($method === 'auto') {
        $method = $hasEmail ? 'email' : 'manual';
    }

    if ($method === 'email' && !$hasEmail) {
        Response::error('User does not have an email address. Use manual invite instead.', 400);
    }

    // Invalidate any existing unused invites for this user
    $db->prepare("
        UPDATE auth_invites
        SET used_at = NOW()
        WHERE user_id = ? AND used_at IS NULL
    ")->execute([$userId]);

    // Generate token
    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $expiresAt = date('Y-m-d H:i:s', time() + (INVITE_TOKEN_EXPIRY_HOURS * 3600));

    // Insert invite record
    $insertStmt = $db->prepare("
        INSERT INTO auth_invites (token_hash, user_id, invite_type, email_sent_to, expires_at, created_by)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $insertStmt->execute([
        $tokenHash,
        $userId,
        $method,
        $method === 'email' ? $user['email'] : null,
        $expiresAt,
        $adminUserId
    ]);

    // Ensure user has must_change_password = 1
    try {
        $db->prepare("UPDATE users SET must_change_password = 1 WHERE id = ?")->execute([$userId]);
    } catch (Exception $e) {
        // Column may not exist if Phase 1 migration wasn't run
    }

    $responseData = [
        'user_id' => $userId,
        'method' => $method,
        'expires_at' => $expiresAt
    ];

    if ($method === 'email') {
        // Build invite URL and send email
        $inviteUrl = APP_URL . '/html/set-password.html?token=' . urlencode($rawToken);

        try {
            $userName = $user['full_name'] ?: trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            $roleName = formatRoleDisplay($user['role']);

            $bodyHtml = buildInviteEmailBody($userName, $roleName, $inviteUrl, $expiresAt);
            $emailHtml = Mailer::buildTemplate('Welcome to Highland Fresh', $bodyHtml);

            Mailer::send(
                $user['email'],
                'Welcome to Highland Fresh — Set Your Password',
                $emailHtml
            );

            $responseData['email_sent_to'] = $user['email'];
            $responseData['email_sent'] = true;
        } catch (Exception $e) {
            error_log("Invite email error: " . $e->getMessage());
            // Still return the link so admin can share manually
            $responseData['email_sent'] = false;
            $responseData['email_error'] = $e->getMessage();
            $responseData['invite_url'] = $inviteUrl;
        }
    } else {
        // Manual: generate a temp password, set it, and return it once
        $tempPassword = generateTempPassword(TEMP_CREDENTIAL_LENGTH);
        $hashedPassword = Auth::hashPassword($tempPassword);

        $db->prepare("UPDATE users SET password = ?, must_change_password = 1, password_set_at = NOW() WHERE id = ?")->execute([$hashedPassword, $userId]);

        // Determine login identifier for display
        $loginId = $user['email'] ?: $user['employee_id'] ?: $user['username'];
        $loginType = $user['login_type'] ?? 'username';

        $responseData['temp_credential'] = [
            'login_id' => $loginId,
            'login_type' => $loginType,
            'temp_password' => $tempPassword,
            'note' => 'This password is displayed once. The user will be required to change it at first login.'
        ];
    }

    // Audit log
    try {
        logAudit($adminUserId, 'INVITE_CREATED', 'auth_invites', $userId, null, [
            'method' => $method,
            'expires_at' => $expiresAt,
            'user_email' => $user['email'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("Invite audit warning: " . $e->getMessage());
    }

    Response::success($responseData, $method === 'email'
        ? 'Invite email sent successfully'
        : 'Temporary credentials generated');
}


/**
 * Build invite email HTML body
 */
function buildInviteEmailBody($userName, $roleName, $inviteUrl, $expiresAt) {
    $expiresFormatted = date('M j, Y \a\t g:i A', strtotime($expiresAt));

    return <<<HTML
<p style="color:#495057;font-size:15px;line-height:1.7;margin:0 0 20px;">
    Hi <strong>{$userName}</strong>,
</p>
<p style="color:#495057;font-size:15px;line-height:1.7;margin:0 0 20px;">
    Your account has been created on the Highland Fresh Dairy Production System.
    You have been assigned the role of <strong>{$roleName}</strong>.
</p>
<p style="color:#495057;font-size:15px;line-height:1.7;margin:0 0 24px;">
    Click the button below to set your password and activate your account:
</p>
<div style="text-align:center;margin:0 0 24px;">
    <a href="{$inviteUrl}"
       style="display:inline-block;background:linear-gradient(135deg,#2b7a3e,#3da553);color:#ffffff;text-decoration:none;padding:14px 36px;border-radius:8px;font-weight:600;font-size:16px;letter-spacing:0.3px;box-shadow:0 4px 12px rgba(43,122,62,0.3);">
        Set My Password
    </a>
</div>
<div style="background-color:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:16px;margin:0 0 20px;">
    <p style="color:#856404;margin:0;font-size:13px;">
        <strong>⚠ Important:</strong> This link expires on <strong>{$expiresFormatted}</strong>
        and can only be used once. If you did not expect this email, please ignore it.
    </p>
</div>
<p style="color:#6c757d;font-size:13px;margin:0;">
    If the button doesn't work, copy and paste this URL into your browser:<br>
    <a href="{$inviteUrl}" style="color:#2b7a3e;word-break:break-all;">{$inviteUrl}</a>
</p>
HTML;
}


/**
 * Generate a random temporary password
 */
function generateTempPassword($length = 10) {
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789!@#$';
    $password = '';
    $max = strlen($chars) - 1;
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $max)];
    }
    return $password;
}


/**
 * Format role for display in emails
 */
function formatRoleDisplay($role) {
    $roles = [
        'general_manager' => 'General Manager',
        'qc_officer' => 'QC Officer',
        'production_staff' => 'Production Staff',
        'warehouse_raw' => 'Warehouse (Raw Materials)',
        'warehouse_fg' => 'Warehouse (Finished Goods)',
        'sales_custodian' => 'Sales Custodian',
        'cashier' => 'Cashier',
        'purchaser' => 'Purchaser',
        'finance_officer' => 'Finance Officer',
        'bookkeeper' => 'Bookkeeper',
        'maintenance_head' => 'Maintenance Head'
    ];
    return $roles[$role] ?? $role;
}


/**
 * Ensure auth_invites table exists
 */
function ensureAuthInvitesTable() {
    $db = Database::getInstance()->getConnection();
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
}
