<?php
/**
 * Admin Users API
 * Handles CRUD operations for user management
 * CRITICAL: Requires General Manager role
 */

require_once __DIR__ . '/../bootstrap.php';

// SECURITY: Require GM or Admin role for user management
$currentUser = Auth::requireRole(['general_manager', 'admin']);

// Get database connection
$pdo = Database::getInstance()->getConnection();

// Handle request
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            requireActionRole($currentUser, ['general_manager', 'admin'], 'Access forbidden');
            if (isset($_GET['id'])) {
                getUser($_GET['id']);
            } else {
                getUsers();
            }
            break;
            
        case 'POST':
            requireActionRole($currentUser, ['general_manager', 'admin'], 'Access forbidden');
            $action = $_GET['action'] ?? '';
            if ($action === 'unlock_login') {
                unlockUserLogin();
                break;
            }
            createUser();
            break;
            
        case 'PUT':
            requireActionRole($currentUser, ['general_manager', 'admin'], 'Access forbidden');
            if (!isset($_GET['id'])) {
                Response::error('User ID is required', 400);
            }
            updateUser($_GET['id']);
            break;
            
        case 'DELETE':
            requireActionRole($currentUser, ['general_manager', 'admin'], 'Access forbidden');
            if (!isset($_GET['id'])) {
                Response::error('User ID is required', 400);
            }
            deleteUser($_GET['id']);
            break;
            
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    Response::error($e->getMessage(), 500);
}

/**
 * Check if onboarding columns exist
 */
function hasOnboardingColumns() {
    global $pdo;
    static $result = null;
    if ($result !== null) return $result;
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'users'
              AND COLUMN_NAME = 'login_identifier'
        ");
        $stmt->execute();
        $result = (int) $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        $result = false;
    }
    return $result;
}

/**
 * Derive login_identifier and login_type from user data
 */
function deriveLoginIdentifier($email, $employeeId, $username) {
    if (!empty($email) && trim($email) !== '') {
        return [
            'login_type' => 'email',
            'login_identifier' => strtolower(trim($email))
        ];
    }
    if (!empty($employeeId) && trim($employeeId) !== '') {
        return [
            'login_type' => 'employee_id',
            'login_identifier' => trim($employeeId)
        ];
    }
    return [
        'login_type' => 'username',
        'login_identifier' => strtolower(trim($username))
    ];
}

/**
 * Get all users with optional filtering
 */
function getUsers() {
    global $pdo;
    
    $extraCols = '';
    if (hasOnboardingColumns()) {
        $extraCols = ', COALESCE(login_type, \'username\') as login_type,
                        COALESCE(must_change_password, 0) as must_change_password,
                        last_login_at';
    }

    $query = "SELECT id, username, full_name, first_name, last_name, email, 
                     employee_id, role, is_active, created_at, updated_at{$extraCols}
              FROM users WHERE 1=1";
    $params = [];
    
    // Apply filters
    if (isset($_GET['role']) && !empty($_GET['role'])) {
        $query .= " AND role = ?";
        $params[] = $_GET['role'];
    }
    
    if (isset($_GET['is_active']) && $_GET['is_active'] !== '') {
        $query .= " AND is_active = ?";
        $params[] = $_GET['is_active'];
    }
    
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search = '%' . $_GET['search'] . '%';
        $query .= " AND (full_name LIKE ? OR username LIKE ? OR email LIKE ?)";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
    }
    
    $query .= " ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    Response::success($users);
}

/**
 * Get single user by ID
 */
function getUser($id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT id, username, full_name, first_name, last_name, email, 
                                  employee_id, role, is_active, created_at, updated_at 
                           FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        Response::error('User not found', 404);
    }
    
    Response::success($user);
}

/**
 * Allowed roles — must match users.role ENUM in MySQL
 */
function allowedUserRoles() {
    return [
        'general_manager',
        'qc_officer',
        'production_staff',
        'warehouse_raw',
        'warehouse_fg',
        'sales_custodian',
        'cashier',
        'purchaser',
        'finance_officer',
        'bookkeeper',
        'maintenance_head',
    ];
}

/**
 * Normalize + validate payload shared by create/update
 */
function normalizeUserPayload(array $data, $isCreate = false) {
    $first = trim((string) ($data['first_name'] ?? ''));
    $last = trim((string) ($data['last_name'] ?? ''));
    $username = trim((string) ($data['username'] ?? ''));
    $email = trim((string) ($data['email'] ?? ''));
    $employeeId = trim((string) ($data['employee_id'] ?? ''));
    $role = trim((string) ($data['role'] ?? ''));
    $fullName = trim((string) ($data['full_name'] ?? ''));
    if ($fullName === '') {
        $fullName = trim($first . ' ' . $last);
    }

    $errors = [];
    if ($isCreate || array_key_exists('first_name', $data)) {
        if ($first === '') {
            $errors['first_name'] = 'First name is required';
        } elseif (mb_strlen($first) > 100) {
            $errors['first_name'] = 'First name must be at most 100 characters';
        }
    }
    if ($isCreate || array_key_exists('last_name', $data)) {
        if ($last === '') {
            $errors['last_name'] = 'Last name is required';
        } elseif (mb_strlen($last) > 100) {
            $errors['last_name'] = 'Last name must be at most 100 characters';
        }
    }
    if ($isCreate || array_key_exists('username', $data)) {
        if ($username === '') {
            $errors['username'] = 'Username is required';
        } elseif (mb_strlen($username) > 50) {
            $errors['username'] = 'Username must be at most 50 characters';
        } elseif (!preg_match('/^[A-Za-z0-9._-]+$/', $username)) {
            $errors['username'] = 'Username may only contain letters, numbers, dots, underscores, and hyphens';
        }
    }
    if ($isCreate || array_key_exists('role', $data)) {
        if ($role === '' || !in_array($role, allowedUserRoles(), true)) {
            $errors['role'] = 'A valid role is required';
        }
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address';
    }
    if ($email !== '' && mb_strlen($email) > 100) {
        $errors['email'] = 'Email must be at most 100 characters';
    }
    if ($employeeId !== '' && mb_strlen($employeeId) > 100) {
        $errors['employee_id'] = 'Employee ID must be at most 100 characters';
    }
    // full_name only required on create, or when name fields are being updated
    if ($isCreate || array_key_exists('first_name', $data) || array_key_exists('last_name', $data) || array_key_exists('full_name', $data)) {
        if ($fullName === '') {
            $errors['full_name'] = 'Full name is required';
        } elseif (mb_strlen($fullName) > 100) {
            $errors['full_name'] = 'Full name must be at most 100 characters (shorten first/last name)';
        }
    }

    // Dual-path onboarding (create only): email_invite | temp_credentials
    // Manual admin password entry is no longer allowed on create.
    $onboardingMethod = trim((string) ($data['onboarding_method'] ?? ''));
    // Backward-compat: older clients that still send auto_generate_password
    if ($isCreate && $onboardingMethod === '') {
        $legacyAuto = !empty($data['auto_generate_password'])
            || $data['auto_generate_password'] === 1
            || $data['auto_generate_password'] === '1'
            || $data['auto_generate_password'] === true;
        $onboardingMethod = $legacyAuto ? 'temp_credentials' : '';
    }

    if ($isCreate) {
        if (!in_array($onboardingMethod, ['email_invite', 'temp_credentials'], true)) {
            $errors['onboarding_method'] = 'Choose Send Email Invite or Generate Temporary Credentials';
        }
        if ($onboardingMethod === 'email_invite') {
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'A valid email is required for the invite path';
            }
        }
        // Ignore any client-supplied password on create
    } elseif (!empty($data['password']) && strlen((string) $data['password']) < 6) {
        $errors['password'] = 'Password must be at least 6 characters';
    }

    if (!empty($errors)) {
        Response::validationError($errors);
    }

    // UI toggle → TINYINT(1): checked=1, unchecked=0
    $isActive = 1;
    if (array_key_exists('is_active', $data)) {
        $rawActive = $data['is_active'];
        $isActive = ($rawActive === 0 || $rawActive === '0' || $rawActive === false || $rawActive === 'false' || $rawActive === null)
            ? 0
            : 1;
    }

    return [
        'first_name' => $first !== '' ? $first : null,
        'last_name' => $last !== '' ? $last : null,
        'full_name' => $fullName,
        'username' => $username,
        'email' => $email !== '' ? strtolower($email) : null,
        'employee_id' => $employeeId !== '' ? $employeeId : null,
        'role' => $role,
        'is_active' => $isActive,
        // Password only accepted on update (admin reset path); never on create
        'password' => (!$isCreate && isset($data['password'])) ? (string) $data['password'] : '',
        'onboarding_method' => $onboardingMethod,
    ];
}

/**
 * Secure random temporary password (same charset style as invite.php)
 */
function generateAdminTempPassword($length = null) {
    $length = $length ?: (defined('TEMP_CREDENTIAL_LENGTH') ? (int) TEMP_CREDENTIAL_LENGTH : 10);
    if ($length < 8) {
        $length = 10;
    }
    // Avoid ambiguous characters (0/O, 1/l/I)
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789!@#$';
    $password = '';
    $max = strlen($chars) - 1;
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $max)];
    }
    return $password;
}

/**
 * Ensure auth_invites exists (invite tokens live here, not on users).
 */
function ensureAdminAuthInvitesTable() {
    global $pdo;
    $pdo->exec("
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

function hasOnboardingMethodColumn() {
    global $pdo;
    static $result = null;
    if ($result !== null) {
        return $result;
    }
    try {
        // Avoid INFORMATION_SCHEMA (can hang under lock contention on some MariaDB builds)
        $pdo->query('SELECT onboarding_method FROM users LIMIT 0');
        $result = true;
    } catch (Throwable $e) {
        $result = false;
    }
    return $result;
}

function formatRoleDisplayName($role) {
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
        'maintenance_head' => 'Maintenance Head',
    ];
    return $roles[$role] ?? $role;
}

function buildUserInviteEmailBody($userName, $roleName, $inviteUrl, $expiresAt) {
    $expiresFormatted = date('M j, Y \a\t g:i A', strtotime($expiresAt));
    $safeName = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');
    $safeRole = htmlspecialchars($roleName, ENT_QUOTES, 'UTF-8');
    $safeUrl = htmlspecialchars($inviteUrl, ENT_QUOTES, 'UTF-8');
    $safeExp = htmlspecialchars($expiresFormatted, ENT_QUOTES, 'UTF-8');

    return <<<HTML
<p style="color:#495057;font-size:15px;line-height:1.7;margin:0 0 20px;">
    Hi <strong>{$safeName}</strong>,
</p>
<p style="color:#495057;font-size:15px;line-height:1.7;margin:0 0 20px;">
    Your account has been created on the Highland Fresh Dairy Production System.
    You have been assigned the role of <strong>{$safeRole}</strong>.
</p>
<p style="color:#495057;font-size:15px;line-height:1.7;margin:0 0 24px;">
    Click the button below to set your password and activate your account:
</p>
<div style="text-align:center;margin:0 0 24px;">
    <a href="{$safeUrl}"
       style="display:inline-block;background:linear-gradient(135deg,#2b7a3e,#3da553);color:#ffffff;text-decoration:none;padding:14px 36px;border-radius:8px;font-weight:600;font-size:16px;letter-spacing:0.3px;box-shadow:0 4px 12px rgba(43,122,62,0.3);">
        Set My Password
    </a>
</div>
<div style="background-color:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:16px;margin:0 0 20px;">
    <p style="color:#856404;margin:0;font-size:13px;">
        <strong>⚠ Important:</strong> This link expires on <strong>{$safeExp}</strong>
        and can only be used once. If you did not expect this email, please ignore it.
    </p>
</div>
<p style="color:#6c757d;font-size:13px;margin:0;">
    If the button doesn't work, copy and paste this URL into your browser:<br>
    <a href="{$safeUrl}" style="color:#2b7a3e;word-break:break-all;">{$safeUrl}</a>
</p>
HTML;
}

/**
 * Create new user via dual onboarding path:
 *   email_invite      → token in auth_invites + registration email (no password shown)
 *   temp_credentials  → random password hashed, must_change_password=1, plain text returned ONCE
 */
function createUser() {
    global $pdo, $currentUser;

    $raw = json_decode(file_get_contents('php://input'), true);
    if (!is_array($raw)) {
        Response::error('Invalid JSON body', 400);
    }

    // Strip any client-supplied password on create (security)
    unset($raw['password'], $raw['confirm_password']);

    $data = normalizeUserPayload($raw, true);
    $onboardingMethod = $data['onboarding_method'];

    // Uniqueness checks
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$data['username']]);
    if ($stmt->fetch()) {
        Response::error('Username already exists', 400);
    }

    if (!empty($data['email'])) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            Response::error('Email already exists', 400);
        }
    }

    if (!empty($data['employee_id'])) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE employee_id = ?");
        $stmt->execute([$data['employee_id']]);
        if ($stmt->fetch()) {
            Response::error('Employee ID already exists', 400);
        }
    }

    $loginInfo = [
        'login_identifier' => $data['username'],
        'login_type' => 'username',
    ];
    if (hasOnboardingColumns()) {
        $loginInfo = deriveLoginIdentifier(
            $data['email'],
            $data['employee_id'],
            $data['username']
        );
        $chkStmt = $pdo->prepare("SELECT id FROM users WHERE login_identifier = ?");
        $chkStmt->execute([$loginInfo['login_identifier']]);
        if ($chkStmt->fetch()) {
            Response::error('A user with this login identifier already exists', 400);
        }
    }

    $plainTempPassword = null;
    $mustChangePassword = 1; // both paths require the user to set/change their password
    $passwordSetAtSql = 'NULL';

    if ($onboardingMethod === 'temp_credentials') {
        // Path B: random password, hash stored, plaintext returned once
        $plainTempPassword = generateAdminTempPassword();
        $hashedPassword = password_hash($plainTempPassword, PASSWORD_BCRYPT, [
            'cost' => defined('PASSWORD_COST') ? PASSWORD_COST : 12
        ]);
        $passwordSetAtSql = 'NOW()';
    } else {
        // Path A: unusable random secret until invite set-password completes
        $hashedPassword = password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT, [
            'cost' => defined('PASSWORD_COST') ? PASSWORD_COST : 12
        ]);
        $passwordSetAtSql = 'NULL';
    }

    $adminUserId = (int) ($currentUser['user_id'] ?? 0);
    $hasOnboarding = hasOnboardingColumns();
    $hasMethodCol = hasOnboardingMethodColumn();

    $pdo->beginTransaction();
    try {
        if ($hasOnboarding) {
            $cols = 'username, password, full_name, first_name, last_name, email, employee_id, role, is_active,
                     login_identifier, login_type, must_change_password, password_set_at, created_at';
            $placeholders = '?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ' . $passwordSetAtSql . ', NOW()';
            $params = [
                $data['username'],
                $hashedPassword,
                $data['full_name'],
                $data['first_name'],
                $data['last_name'],
                $data['email'],
                $data['employee_id'],
                $data['role'],
                $data['is_active'],
                $loginInfo['login_identifier'],
                $loginInfo['login_type'],
                $mustChangePassword,
            ];
            if ($hasMethodCol) {
                $cols .= ', onboarding_method';
                $placeholders .= ', ?';
                $params[] = $onboardingMethod;
            }
            $stmt = $pdo->prepare("INSERT INTO users ({$cols}) VALUES ({$placeholders})");
            $stmt->execute($params);
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, first_name, last_name,
                                                      email, employee_id, role, is_active, created_at)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $data['username'],
                $hashedPassword,
                $data['full_name'],
                $data['first_name'],
                $data['last_name'],
                $data['email'],
                $data['employee_id'],
                $data['role'],
                $data['is_active'],
            ]);
        }

        $userId = (int) $pdo->lastInsertId();

        $responseData = [
            'id' => $userId,
            'username' => $data['username'],
            'onboarding_method' => $onboardingMethod,
            'must_change_password' => $mustChangePassword,
            'requires_password_reset' => $mustChangePassword, // product alias
        ];

        if ($onboardingMethod === 'email_invite') {
            ensureAdminAuthInvitesTable();

            // Invalidate unused invites (none yet for new user, but keep pattern)
            $pdo->prepare("UPDATE auth_invites SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL")
                ->execute([$userId]);

            $rawToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);
            $expiryHours = defined('INVITE_TOKEN_EXPIRY_HOURS') ? (int) INVITE_TOKEN_EXPIRY_HOURS : 48;
            $expiresAt = date('Y-m-d H:i:s', time() + ($expiryHours * 3600));

            $pdo->prepare("
                INSERT INTO auth_invites (token_hash, user_id, invite_type, email_sent_to, expires_at, created_by)
                VALUES (?, ?, 'email', ?, ?, ?)
            ")->execute([$tokenHash, $userId, $data['email'], $expiresAt, $adminUserId ?: 0]);

            $inviteUrl = (defined('APP_URL') ? APP_URL : '') . '/html/set-password.html?token=' . urlencode($rawToken);

            $responseData['invite'] = [
                'email_sent_to' => $data['email'],
                'expires_at' => $expiresAt,
                'email_sent' => false,
            ];

            try {
                if (!class_exists('Mailer')) {
                    require_once __DIR__ . '/../config/mailer.php';
                }
                $roleName = formatRoleDisplayName($data['role']);
                $bodyHtml = buildUserInviteEmailBody($data['full_name'], $roleName, $inviteUrl, $expiresAt);
                $emailHtml = Mailer::buildTemplate('Welcome to Highland Fresh', $bodyHtml);
                Mailer::send(
                    $data['email'],
                    'Welcome to Highland Fresh — Set Your Password',
                    $emailHtml
                );
                $responseData['invite']['email_sent'] = true;
            } catch (Throwable $mailErr) {
                error_log('Create-user invite email error: ' . $mailErr->getMessage());
                $responseData['invite']['email_sent'] = false;
                $responseData['invite']['email_error'] = $mailErr->getMessage();
                // Admin can copy the link if SMTP fails (token only in this response)
                $responseData['invite']['invite_url'] = $inviteUrl;
            }

            // Never return the raw token except as invite_url on mail failure
            $message = !empty($responseData['invite']['email_sent'])
                ? 'User created. Invitation email sent.'
                : 'User created, but the invitation email failed. Use the invite link shown once.';
        } else {
            // Path B — return plaintext password exactly once
            $responseData['temp_credential'] = [
                'login_id' => $loginInfo['login_identifier'] ?: $data['username'],
                'login_type' => $loginInfo['login_type'] ?? 'username',
                'temp_password' => $plainTempPassword,
                'note' => 'This password is shown once. The user must change it at first login.',
            ];
            $message = 'User created. Temporary credentials are shown once — hand them over securely.';
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('User create failed: ' . $e->getMessage());
        Response::error('Failed to create user: ' . $e->getMessage(), 500);
    }

    try {
        logAudit($adminUserId, 'USER_CREATED', 'users', $userId, null, [
            'username' => $data['username'],
            'role' => $data['role'],
            'onboarding_method' => $onboardingMethod,
            'must_change_password' => $mustChangePassword,
        ]);
    } catch (Throwable $e) {
        error_log('User create audit warning: ' . $e->getMessage());
    }

    Response::success($responseData, $message);
}

/**
 * Update existing user.
 * Supports:
 *  - Full form save (first_name, username, role, …)
 *  - Partial patches (e.g. { is_active: 0 } or { password: "..." }) without re-validating the whole form
 */
function updateUser($id) {
    global $pdo;

    $existFields = 'id, username, email, role, is_active, first_name, last_name, full_name';
    if (hasOnboardingColumns()) {
        $existFields .= ', COALESCE(employee_id, \'\') as employee_id';
    }
    $stmt = $pdo->prepare("SELECT {$existFields} FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existingUser) {
        Response::error('User not found', 404);
    }

    $raw = json_decode(file_get_contents('php://input'), true);
    if (!is_array($raw)) {
        Response::error('Invalid JSON body', 400);
    }

    // Detect full form vs action-style partial patch
    $profileKeys = ['first_name', 'last_name', 'full_name', 'username', 'role', 'email', 'employee_id'];
    $isFullForm = false;
    foreach ($profileKeys as $k) {
        if (array_key_exists($k, $raw)) {
            $isFullForm = true;
            break;
        }
    }

    if ($isFullForm) {
        // Full edit form: fill missing fields from DB so validation uses real values
        $merged = array_merge([
            'first_name' => $existingUser['first_name'] ?? '',
            'last_name' => $existingUser['last_name'] ?? '',
            'username' => $existingUser['username'] ?? '',
            'role' => $existingUser['role'] ?? '',
            'email' => $existingUser['email'] ?? '',
            'employee_id' => $existingUser['employee_id'] ?? '',
            'full_name' => $existingUser['full_name'] ?? '',
            'is_active' => $existingUser['is_active'] ?? 1,
            'password' => '',
        ], $raw);
        $normalized = normalizeUserPayload($merged, false);
        $data = array_merge($raw, $normalized);
    } else {
        // Partial patch only — never invent empty name fields
        $data = $raw;
        if (array_key_exists('is_active', $data)) {
            $rawActive = $data['is_active'];
            $data['is_active'] = ($rawActive === 0 || $rawActive === '0' || $rawActive === false || $rawActive === 'false')
                ? 0
                : 1;
        }
        if (!empty($data['password']) && strlen((string) $data['password']) < 6) {
            Response::validationError(['password' => 'Password must be at least 6 characters']);
        }
    }

    if (!empty($data['username']) && $data['username'] !== $existingUser['username']) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$data['username'], $id]);
        if ($stmt->fetch()) {
            Response::error('Username already exists', 400);
        }
    }

    if (!empty($data['email']) && $data['email'] !== ($existingUser['email'] ?? '')) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$data['email'], $id]);
        if ($stmt->fetch()) {
            Response::error('Email already exists', 400);
        }
    }

    $updates = [];
    $params = [];
    $allowedFields = ['username', 'full_name', 'first_name', 'last_name', 'email',
                      'employee_id', 'role', 'is_active'];

    foreach ($allowedFields as $field) {
        // Only persist fields that the client actually sent
        if (array_key_exists($field, $raw) || ($isFullForm && array_key_exists($field, $data))) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            // For partial patches, only touch keys present in $raw
            if (!$isFullForm && !array_key_exists($field, $raw)) {
                continue;
            }
            $updates[] = "$field = ?";
            $params[] = $data[$field];
        }
    }

    $passwordChanged = false;
    if (!empty($data['password'])) {
        $updates[] = 'password = ?';
        $params[] = password_hash($data['password'], PASSWORD_BCRYPT, [
            'cost' => defined('PASSWORD_COST') ? PASSWORD_COST : 12
        ]);
        $passwordChanged = true;

        if (hasOnboardingColumns()) {
            $updates[] = 'must_change_password = 1';
            $updates[] = 'password_set_at = NOW()';
        }
    }

    if (hasOnboardingColumns()) {
        $emailChanged = array_key_exists('email', $raw) && ($data['email'] ?? '') !== ($existingUser['email'] ?? '');
        $empIdChanged = array_key_exists('employee_id', $raw)
            && ($data['employee_id'] ?? '') !== ($existingUser['employee_id'] ?? '');
        $usernameChanged = array_key_exists('username', $raw)
            && ($data['username'] ?? '') !== $existingUser['username'];

        if ($emailChanged || $empIdChanged || $usernameChanged) {
            $newEmail = $data['email'] ?? $existingUser['email'] ?? '';
            $newEmpId = $data['employee_id'] ?? $existingUser['employee_id'] ?? '';
            $newUsername = $data['username'] ?? $existingUser['username'];
            $loginInfo = deriveLoginIdentifier($newEmail, $newEmpId, $newUsername);

            $chkStmt = $pdo->prepare('SELECT id FROM users WHERE login_identifier = ? AND id != ?');
            $chkStmt->execute([$loginInfo['login_identifier'], $id]);
            if ($chkStmt->fetch()) {
                Response::error('A user with this login identifier already exists', 400);
            }

            $updates[] = 'login_identifier = ?';
            $params[] = $loginInfo['login_identifier'];
            $updates[] = 'login_type = ?';
            $params[] = $loginInfo['login_type'];
        }
    }

    if (empty($updates)) {
        Response::error('No fields to update', 400);
    }

    $updates[] = 'updated_at = NOW()';
    $params[] = $id;

    $query = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?';
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    $roleChanged = array_key_exists('role', $raw) && ($data['role'] ?? '') !== ($existingUser['role'] ?? '');
    $deactivated = array_key_exists('is_active', $raw)
        && (int) $data['is_active'] === 0
        && (int) ($existingUser['is_active'] ?? 1) === 1;
    $activated = array_key_exists('is_active', $raw)
        && (int) $data['is_active'] === 1
        && (int) ($existingUser['is_active'] ?? 0) === 0;

    $revokedCount = 0;
    if ($deactivated) {
        $revokedCount = Auth::revokeAllSessionsByUserId($id, 'account_deactivated');
    } elseif ($roleChanged) {
        $revokedCount = Auth::revokeAllSessionsByUserId($id, 'role_changed');
    } elseif ($passwordChanged) {
        $revokedCount = Auth::revokeAllSessionsByUserId($id, 'admin_password_reset');
    }

    if ($passwordChanged) {
        try {
            $currentUser = Auth::requireAuth();
            logAudit((int) $currentUser['user_id'], 'ADMIN_PASSWORD_RESET', 'users', $id, [
                'action' => 'password_reset'
            ], [
                'action' => 'password_reset',
                'reset_reason' => $data['reset_reason'] ?? 'not_specified',
                'reset_notes' => $data['reset_notes'] ?? '',
                'sessions_revoked' => $revokedCount,
                'must_change_password' => 1,
                'admin_user_id' => $currentUser['user_id']
            ]);
        } catch (Exception $e) {
            error_log('Admin password reset audit warning: ' . $e->getMessage());
        }
    }

    if ($deactivated) {
        try {
            $currentUser = Auth::requireAuth();
            logAudit((int) $currentUser['user_id'], 'USER_DEACTIVATED', 'users', $id, [
                'is_active' => 1
            ], [
                'is_active' => 0,
                'sessions_revoked' => $revokedCount
            ]);
        } catch (Exception $e) {
            error_log('Deactivation audit warning: ' . $e->getMessage());
        }
    }

    if ($activated) {
        try {
            $currentUser = Auth::requireAuth();
            logAudit((int) $currentUser['user_id'], 'USER_ACTIVATED', 'users', $id, [
                'is_active' => 0
            ], [
                'is_active' => 1
            ]);
        } catch (Exception $e) {
            error_log('Activation audit warning: ' . $e->getMessage());
        }
    }

    if ($roleChanged) {
        try {
            $currentUser = Auth::requireAuth();
            logAudit((int) $currentUser['user_id'], 'USER_ROLE_CHANGED', 'users', $id, [
                'role' => $existingUser['role'] ?? ''
            ], [
                'role' => $data['role'],
                'sessions_revoked' => $revokedCount
            ]);
        } catch (Exception $e) {
            error_log('Role change audit warning: ' . $e->getMessage());
        }
    }

    $msg = 'User updated successfully';
    if ($deactivated) {
        $msg = 'User deactivated successfully';
    } elseif ($activated) {
        $msg = 'User activated successfully';
    } elseif ($passwordChanged) {
        $msg = 'Password reset successfully';
    }

    Response::success([
        'id' => (int) $id,
        'is_active' => array_key_exists('is_active', $data) ? (int) $data['is_active'] : (int) $existingUser['is_active'],
        'sessions_revoked' => $revokedCount,
    ], $msg);
}

/**
 * Delete user (soft delete by deactivating)
 */
function deleteUser($id) {
    global $pdo;
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        Response::error('User not found', 404);
    }
    
    // Soft delete by deactivating
    $stmt = $pdo->prepare("UPDATE users SET is_active = 0, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$id]);

    // Revoke all active sessions
    Auth::revokeAllSessionsByUserId($id, 'account_deactivated');
    
    Response::success(null, 'User deactivated successfully');
}

/**
 * Unlock login attempts for a user (all IPs)
 */
function unlockUserLogin() {
    global $pdo;

    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $username = trim((string) ($data['username'] ?? ''));

    if (isset($_GET['id']) && $_GET['id'] !== '') {
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            Response::error('User not found', 404);
        }

        $username = $user['username'];
    }

    if ($username === '') {
        Response::error('Username or user ID is required', 400);
    }

    ensureLoginAttemptsTable();

    $normalizedUsername = normalizeLoginIdentifier($username);
    $unlockStmt = $pdo->prepare("DELETE FROM login_attempts WHERE username = ?");
    $unlockStmt->execute([$normalizedUsername]);

    Response::success([
        'username' => $username,
        'cleared_records' => $unlockStmt->rowCount()
    ], 'Login lockout cleared successfully');
}

function normalizeLoginIdentifier($username) {
    $normalized = trim((string) $username);
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($normalized, 'UTF-8');
    }
    return strtolower($normalized);
}

function ensureLoginAttemptsTable() {
    global $pdo;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `login_attempts` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `username` VARCHAR(100) NOT NULL,
            `ip_address` VARCHAR(45) NOT NULL,
            `failed_attempts` INT NOT NULL DEFAULT 0,
            `first_failed_at` DATETIME DEFAULT NULL,
            `last_failed_at` DATETIME DEFAULT NULL,
            `locked_until` DATETIME DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_login_attempts_username_ip` (`username`, `ip_address`),
            KEY `idx_login_attempts_username` (`username`),
            KEY `idx_login_attempts_locked_until` (`locked_until`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}
