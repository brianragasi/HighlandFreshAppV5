<?php
/**
 * Admin — unified user row actions (AJAX)
 *
 * POST /api/admin/process_user_action.php
 * Body JSON:
 *   {
 *     "action": "deactivate|activate|unlock_login|reset_password|resend_invite|temp_credentials",
 *     "user_id": 15,
 *     // reset_password only:
 *     "password": "...",
 *     "reset_reason": "...",
 *     "reset_notes": "..."
 *   }
 *
 * Auth: general_manager | admin
 */

require_once __DIR__ . '/../bootstrap.php';

$currentUser = Auth::requireRole(['general_manager', 'admin']);
requireActionRole($currentUser, ['general_manager', 'admin'], 'Access forbidden');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    Response::error('Method not allowed', 405);
}

$pdo = Database::getInstance()->getConnection();
$body = getRequestBody();
if (!is_array($body) || empty($body)) {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
}

$action = trim((string) ($body['action'] ?? $_GET['action'] ?? ''));
$userId = (int) ($body['user_id'] ?? $body['id'] ?? $_GET['user_id'] ?? 0);

if ($userId <= 0) {
    Response::validationError(['user_id' => 'User ID is required']);
}
if ($action === '') {
    Response::validationError(['action' => 'Action is required']);
}

// Load target user
$stmt = $pdo->prepare("
    SELECT id, username, full_name, first_name, last_name, email, employee_id,
           role, is_active, login_type, login_identifier
    FROM users WHERE id = ?
");
$stmt->execute([$userId]);
$target = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$target) {
    Response::error('User not found', 404);
}

// Prevent self-deactivation lockout for the signed-in admin
$adminId = (int) ($currentUser['user_id'] ?? 0);
if ($action === 'deactivate' && $userId === $adminId) {
    Response::error('You cannot deactivate your own account while signed in', 400);
}

try {
    switch ($action) {
        case 'deactivate':
            handleSetActive($pdo, $target, 0, $adminId);
            break;
        case 'activate':
            handleSetActive($pdo, $target, 1, $adminId);
            break;
        case 'unlock_login':
            handleUnlockLogin($pdo, $target);
            break;
        case 'reset_password':
            handleResetPassword($pdo, $target, $body, $adminId);
            break;
        case 'resend_invite':
            handleResendInvite($target, $adminId);
            break;
        case 'temp_credentials':
            handleTempCredentials($pdo, $target, $adminId);
            break;
        default:
            Response::error('Unknown action: ' . $action, 400);
    }
} catch (Throwable $e) {
    error_log('process_user_action error: ' . $e->getMessage());
    Response::error($e->getMessage(), 500);
}

// ─────────────────────────────────────────────────────────────────────────────

function handleSetActive(PDO $pdo, array $target, int $isActive, int $adminId): void {
    $userId = (int) $target['id'];
    $prev = (int) ($target['is_active'] ?? 0);

    $stmt = $pdo->prepare('UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$isActive, $userId]);

    $revoked = 0;
    if ($isActive === 0 && $prev === 1) {
        $revoked = Auth::revokeAllSessionsByUserId($userId, 'account_deactivated');
        try {
            logAudit($adminId, 'USER_DEACTIVATED', 'users', $userId, ['is_active' => 1], [
                'is_active' => 0,
                'sessions_revoked' => $revoked,
            ]);
        } catch (Throwable $e) { /* ignore */ }
    } elseif ($isActive === 1 && $prev === 0) {
        try {
            logAudit($adminId, 'USER_ACTIVATED', 'users', $userId, ['is_active' => 0], [
                'is_active' => 1,
            ]);
        } catch (Throwable $e) { /* ignore */ }
    }

    Response::success([
        'user_id' => $userId,
        'is_active' => $isActive,
        'sessions_revoked' => $revoked,
    ], $isActive === 1 ? 'User activated successfully' : 'User deactivated successfully');
}

function handleUnlockLogin(PDO $pdo, array $target): void {
    // Ensure table exists (same shape as users.php)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `login_attempts` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `username` VARCHAR(100) NOT NULL,
            `ip_address` VARCHAR(45) NOT NULL,
            `failed_attempts` INT NOT NULL DEFAULT 0,
            `first_failed_at` DATETIME DEFAULT NULL,
            `last_failed_at` DATETIME DEFAULT NULL,
            `locked_until` DATETIME DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_login_attempts_username` (`username`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $candidates = array_unique(array_filter([
        $target['username'] ?? '',
        $target['login_identifier'] ?? '',
        $target['email'] ?? '',
        isset($target['username']) ? mb_strtolower(trim($target['username']), 'UTF-8') : '',
        isset($target['email']) ? mb_strtolower(trim((string) $target['email']), 'UTF-8') : '',
    ]));

    $cleared = 0;
    if (!empty($candidates)) {
        $placeholders = implode(',', array_fill(0, count($candidates), '?'));
        $del = $pdo->prepare("DELETE FROM login_attempts WHERE username IN ({$placeholders})");
        $del->execute(array_values($candidates));
        $cleared = $del->rowCount();
    }

    Response::success([
        'user_id' => (int) $target['id'],
        'username' => $target['username'],
        'cleared_records' => $cleared,
    ], $cleared > 0
        ? 'Login lockout cleared successfully'
        : 'No active lockout found for this user');
}

function handleResetPassword(PDO $pdo, array $target, array $body, int $adminId): void {
    $password = (string) ($body['password'] ?? $body['new_password'] ?? '');
    $reason = trim((string) ($body['reset_reason'] ?? 'admin_reset'));
    $notes = trim((string) ($body['reset_notes'] ?? ''));

    if (strlen($password) < 6) {
        Response::validationError(['password' => 'Password must be at least 6 characters']);
    }

    $hash = password_hash($password, PASSWORD_BCRYPT, [
        'cost' => defined('PASSWORD_COST') ? PASSWORD_COST : 12,
    ]);

    $userId = (int) $target['id'];
    try {
        $pdo->prepare("
            UPDATE users
            SET password = ?, must_change_password = 1, password_set_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ")->execute([$hash, $userId]);
    } catch (Throwable $e) {
        // Legacy schema without must_change_password
        $pdo->prepare('UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$hash, $userId]);
    }

    $revoked = Auth::revokeAllSessionsByUserId($userId, 'admin_password_reset');

    try {
        logAudit($adminId, 'ADMIN_PASSWORD_RESET', 'users', $userId, [
            'action' => 'password_reset',
        ], [
            'action' => 'password_reset',
            'reset_reason' => $reason,
            'reset_notes' => $notes,
            'sessions_revoked' => $revoked,
            'must_change_password' => 1,
        ]);
    } catch (Throwable $e) { /* ignore */ }

    Response::success([
        'user_id' => $userId,
        'must_change_password' => 1,
        'sessions_revoked' => $revoked,
    ], 'Password reset successfully. User must change it at next login.');
}

/**
 * Proxy to invite email flow (auth/invite.php logic inlined lightly via HTTP-free call).
 * We reimplement the essential path so this endpoint is self-contained.
 */
function handleResendInvite(array $target, int $adminId): void {
    $email = trim((string) ($target['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        Response::error('User has no valid email. Use temporary credentials instead.', 400);
    }
    if (!(int) $target['is_active']) {
        Response::error('Cannot invite a deactivated user. Activate them first.', 400);
    }

    // Delegate to invite API internals by requiring the script is awkward;
    // call the same DB/email path used by invite.php via a sub-request is unnecessary.
    // Inline minimal invite creation:
    $db = Database::getInstance()->getConnection();
    ensureProcessAuthInvites($db);

    $db->prepare('UPDATE auth_invites SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')
        ->execute([(int) $target['id']]);

    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $hours = defined('INVITE_TOKEN_EXPIRY_HOURS') ? (int) INVITE_TOKEN_EXPIRY_HOURS : 48;
    $expiresAt = date('Y-m-d H:i:s', time() + $hours * 3600);

    $db->prepare("
        INSERT INTO auth_invites (token_hash, user_id, invite_type, email_sent_to, expires_at, created_by)
        VALUES (?, ?, 'email', ?, ?, ?)
    ")->execute([$tokenHash, (int) $target['id'], $email, $expiresAt, $adminId]);

    try {
        $db->prepare('UPDATE users SET must_change_password = 1 WHERE id = ?')->execute([(int) $target['id']]);
    } catch (Throwable $e) { /* ignore */ }

    $inviteUrl = (defined('APP_URL') ? APP_URL : '') . '/html/set-password.html?token=' . urlencode($rawToken);
    $result = [
        'user_id' => (int) $target['id'],
        'method' => 'email',
        'email_sent_to' => $email,
        'expires_at' => $expiresAt,
        'email_sent' => false,
    ];

    try {
        if (!class_exists('Mailer')) {
            require_once __DIR__ . '/../config/mailer.php';
        }
        $name = $target['full_name'] ?: trim(($target['first_name'] ?? '') . ' ' . ($target['last_name'] ?? ''));
        $bodyHtml = '<p>Hi <strong>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</strong>,</p>'
            . '<p>Set your Highland Fresh password using this one-time link:</p>'
            . '<p><a href="' . htmlspecialchars($inviteUrl, ENT_QUOTES, 'UTF-8') . '">Set My Password</a></p>'
            . '<p style="font-size:12px;color:#666">Expires: ' . htmlspecialchars($expiresAt, ENT_QUOTES, 'UTF-8') . '</p>';
        $html = Mailer::buildTemplate('Set your password', $bodyHtml);
        Mailer::send($email, 'Highland Fresh — Set Your Password', $html);
        $result['email_sent'] = true;
    } catch (Throwable $e) {
        $result['email_sent'] = false;
        $result['email_error'] = $e->getMessage();
        $result['invite_url'] = $inviteUrl;
    }

    try {
        logAudit($adminId, 'INVITE_CREATED', 'auth_invites', (int) $target['id'], null, [
            'method' => 'email',
            'expires_at' => $expiresAt,
            'user_email' => $email,
        ]);
    } catch (Throwable $e) { /* ignore */ }

    Response::success($result, $result['email_sent']
        ? 'Invite email sent successfully'
        : 'Invite created but email failed — copy the link shown once');
}

function handleTempCredentials(PDO $pdo, array $target, int $adminId): void {
    if (!(int) $target['is_active']) {
        Response::error('Cannot set credentials on a deactivated user. Activate them first.', 400);
    }

    $length = defined('TEMP_CREDENTIAL_LENGTH') ? (int) TEMP_CREDENTIAL_LENGTH : 10;
    if ($length < 8) {
        $length = 10;
    }
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789!@#$';
    $temp = '';
    $max = strlen($chars) - 1;
    for ($i = 0; $i < $length; $i++) {
        $temp .= $chars[random_int(0, $max)];
    }

    $hash = password_hash($temp, PASSWORD_BCRYPT, [
        'cost' => defined('PASSWORD_COST') ? PASSWORD_COST : 12,
    ]);
    $userId = (int) $target['id'];

    try {
        $pdo->prepare("
            UPDATE users
            SET password = ?, must_change_password = 1, password_set_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ")->execute([$hash, $userId]);
    } catch (Throwable $e) {
        $pdo->prepare('UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$hash, $userId]);
    }

    Auth::revokeAllSessionsByUserId($userId, 'temp_credentials_issued');

    $loginId = $target['email'] ?: ($target['employee_id'] ?: $target['username']);
    $loginType = $target['login_type'] ?? 'username';

    try {
        logAudit($adminId, 'TEMP_CREDENTIALS_ISSUED', 'users', $userId, null, [
            'login_type' => $loginType,
        ]);
    } catch (Throwable $e) { /* ignore */ }

    // Plaintext password returned ONCE only
    Response::success([
        'user_id' => $userId,
        'method' => 'manual',
        'temp_credential' => [
            'login_id' => $loginId,
            'login_type' => $loginType,
            'temp_password' => $temp,
            'note' => 'Shown once. User must change password at first login.',
        ],
    ], 'Temporary credentials generated');
}

function ensureProcessAuthInvites(PDO $db): void {
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
