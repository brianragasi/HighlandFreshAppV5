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
 * Create new user
 */
function createUser() {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['username', 'role', 'password'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            Response::error("Field '$field' is required", 400);
        }
    }
    
    // Check if username already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$data['username']]);
    if ($stmt->fetch()) {
        Response::error('Username already exists', 400);
    }
    
    // Check if email already exists (if provided)
    if (!empty($data['email'])) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            Response::error('Email already exists', 400);
        }
    }
    
    // Hash password
    $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
    
    if (hasOnboardingColumns()) {
        // Phase 1: Include onboarding fields
        $loginInfo = deriveLoginIdentifier(
            $data['email'] ?? null,
            $data['employee_id'] ?? null,
            $data['username']
        );

        // Check login_identifier uniqueness
        $chkStmt = $pdo->prepare("SELECT id FROM users WHERE login_identifier = ?");
        $chkStmt->execute([$loginInfo['login_identifier']]);
        if ($chkStmt->fetch()) {
            Response::error('A user with this login identifier already exists', 400);
        }

        $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, first_name, last_name, 
                                                  email, employee_id, role, is_active,
                                                  login_identifier, login_type, must_change_password,
                                                  password_set_at, created_at) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())");
        
        $stmt->execute([
            $data['username'],
            $hashedPassword,
            $data['full_name'] ?? null,
            $data['first_name'] ?? null,
            $data['last_name'] ?? null,
            $data['email'] ?? null,
            $data['employee_id'] ?? null,
            $data['role'],
            $data['is_active'] ?? 1,
            $loginInfo['login_identifier'],
            $loginInfo['login_type']
        ]);
    } else {
        // Legacy path
        $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, first_name, last_name, 
                                                  email, employee_id, role, is_active, created_at) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        
        $stmt->execute([
            $data['username'],
            $hashedPassword,
            $data['full_name'] ?? null,
            $data['first_name'] ?? null,
            $data['last_name'] ?? null,
            $data['email'] ?? null,
            $data['employee_id'] ?? null,
            $data['role'],
            $data['is_active'] ?? 1
        ]);
    }
    
    $userId = $pdo->lastInsertId();
    
    Response::success(['id' => $userId], 'User created successfully');
}

/**
 * Update existing user
 */
function updateUser($id) {
    global $pdo;
    
    // Check if user exists
    $existFields = 'id, username, email, role, is_active';
    if (hasOnboardingColumns()) {
        $existFields .= ', COALESCE(employee_id, \'\') as employee_id';
    }
    $stmt = $pdo->prepare("SELECT {$existFields} FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existingUser) {
        Response::error('User not found', 404);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Check if username is being changed and already exists
    if (!empty($data['username']) && $data['username'] !== $existingUser['username']) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$data['username'], $id]);
        if ($stmt->fetch()) {
            Response::error('Username already exists', 400);
        }
    }
    
    // Check if email is being changed and already exists
    if (!empty($data['email']) && $data['email'] !== $existingUser['email']) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$data['email'], $id]);
        if ($stmt->fetch()) {
            Response::error('Email already exists', 400);
        }
    }
    
    // Build update query dynamically
    $updates = [];
    $params = [];
    
    $allowedFields = ['username', 'full_name', 'first_name', 'last_name', 'email', 
                      'employee_id', 'role', 'is_active'];
    
    foreach ($allowedFields as $field) {
        if (array_key_exists($field, $data)) {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
        }
    }
    
    // Handle password update
    $passwordChanged = false;
    if (!empty($data['password'])) {
        $updates[] = "password = ?";
        $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        $passwordChanged = true;

        // Admin reset: force password change on next login
        if (hasOnboardingColumns()) {
            $updates[] = "must_change_password = 1";
            $updates[] = "password_set_at = NOW()";
        }
    }

    // Re-derive login_identifier if email or employee_id changed
    if (hasOnboardingColumns()) {
        $emailChanged = array_key_exists('email', $data) && $data['email'] !== ($existingUser['email'] ?? '');
        $empIdChanged = array_key_exists('employee_id', $data) && $data['employee_id'] !== ($existingUser['employee_id'] ?? '');
        $usernameChanged = array_key_exists('username', $data) && $data['username'] !== $existingUser['username'];

        if ($emailChanged || $empIdChanged || $usernameChanged) {
            $newEmail = $data['email'] ?? $existingUser['email'] ?? '';
            $newEmpId = $data['employee_id'] ?? $existingUser['employee_id'] ?? '';
            $newUsername = $data['username'] ?? $existingUser['username'];
            $loginInfo = deriveLoginIdentifier($newEmail, $newEmpId, $newUsername);

            // Uniqueness check
            $chkStmt = $pdo->prepare("SELECT id FROM users WHERE login_identifier = ? AND id != ?");
            $chkStmt->execute([$loginInfo['login_identifier'], $id]);
            if ($chkStmt->fetch()) {
                Response::error('A user with this login identifier already exists', 400);
            }

            $updates[] = "login_identifier = ?";
            $params[] = $loginInfo['login_identifier'];
            $updates[] = "login_type = ?";
            $params[] = $loginInfo['login_type'];
        }
    }
    
    if (empty($updates)) {
        Response::error('No fields to update', 400);
    }
    
    $updates[] = "updated_at = NOW()";
    $params[] = $id;
    
    $query = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    // Session revocation on sensitive changes
    $roleChanged = array_key_exists('role', $data) && $data['role'] !== ($existingUser['role'] ?? '');
    $deactivated = array_key_exists('is_active', $data) && (int) $data['is_active'] === 0 && (int) ($existingUser['is_active'] ?? 1) === 1;

    $revokedCount = 0;
    $revokeReason = '';
    if ($deactivated) {
        $revokedCount = Auth::revokeAllSessionsByUserId($id, 'account_deactivated');
        $revokeReason = 'account_deactivated';
    } elseif ($roleChanged) {
        $revokedCount = Auth::revokeAllSessionsByUserId($id, 'role_changed');
        $revokeReason = 'role_changed';
    } elseif ($passwordChanged) {
        $revokedCount = Auth::revokeAllSessionsByUserId($id, 'admin_password_reset');
        $revokeReason = 'admin_password_reset';
    }

    // Audit log for password reset with mandatory reason
    if ($passwordChanged) {
        try {
            $currentUser = Auth::requireAuth();
            $resetReason = $data['reset_reason'] ?? 'not_specified';
            $resetNotes = $data['reset_notes'] ?? '';

            logAudit((int) $currentUser['user_id'], 'ADMIN_PASSWORD_RESET', 'users', $id, [
                'action' => 'password_reset'
            ], [
                'action' => 'password_reset',
                'reset_reason' => $resetReason,
                'reset_notes' => $resetNotes,
                'sessions_revoked' => $revokedCount,
                'must_change_password' => 1,
                'admin_user_id' => $currentUser['user_id']
            ]);
        } catch (Exception $e) {
            error_log("Admin password reset audit warning: " . $e->getMessage());
        }
    }

    // Audit log for deactivation
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
            error_log("Deactivation audit warning: " . $e->getMessage());
        }
    }

    // Audit log for role change
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
            error_log("Role change audit warning: " . $e->getMessage());
        }
    }
    
    Response::success(null, 'User updated successfully');
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
