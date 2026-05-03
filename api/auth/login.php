<?php
/**
 * Highland Fresh System - Authentication API
 * 
 * Endpoints:
 * POST /api/auth/login.php - User login
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

const MAX_LOGIN_ATTEMPTS = 5;
const LOCKOUT_MINUTES = 15;
const INVALID_LOGIN_MESSAGE = 'Invalid username or password';
const LOCKOUT_MESSAGE = 'Too many login attempts. Please try again later.';

// Only allow POST
if ($requestMethod !== 'POST') {
    Response::error('Method not allowed', 405);
}

// Validate input — accept either 'identifier' (new) or 'username' (legacy)
$identifier = trim((string) getParam('identifier', ''));
if (empty($identifier)) {
    $identifier = trim((string) getParam('username', ''));
}
$password = getParam('password');
$identifierKey = normalizeLoginIdentifier($identifier);

if (empty($identifier) || empty($password)) {
    Response::validationError([
        'identifier' => empty($identifier) ? 'Email, Employee ID, or Username is required' : null,
        'password' => empty($password) ? 'Password is required' : null
    ]);
}

try {
    $db = Database::getInstance()->getConnection();
    ensureLoginAttemptsTable($db);
    $clientIp = getClientIpAddress();

    if (isLoginTemporarilyLocked($db, $identifierKey, $clientIp)) {
        Response::error(LOCKOUT_MESSAGE, 429);
    }

    // Check if onboarding columns exist
    $hasOnboardingColumns = false;
    try {
        $colCheck = $db->prepare("
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'users'
              AND COLUMN_NAME = 'login_identifier'
        ");
        $colCheck->execute();
        $hasOnboardingColumns = (int) $colCheck->fetchColumn() > 0;
    } catch (Exception $e) {
        $hasOnboardingColumns = false;
    }

    $user = null;

    // Try login_identifier first (Phase 1 path)
    if ($hasOnboardingColumns) {
        $stmt = $db->prepare("
            SELECT id, username, password, role, is_active,
                   COALESCE(employee_id, '') as employee_id,
                   COALESCE(full_name, CONCAT(first_name, ' ', last_name)) as full_name,
                   COALESCE(first_name, username) as first_name,
                   COALESCE(last_name, '') as last_name,
                   COALESCE(email, '') as email,
                   COALESCE(login_identifier, '') as login_identifier,
                   COALESCE(login_type, 'username') as login_type,
                   COALESCE(must_change_password, 0) as must_change_password
            FROM users
            WHERE login_identifier = ? AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$identifierKey]);
        $user = $stmt->fetch();
    }

    // Fallback: try username (backward compatibility / migration window)
    if (!$user) {
        $selectFields = "id, username, password, role, is_active,
                   COALESCE(employee_id, '') as employee_id,
                   COALESCE(full_name, CONCAT(first_name, ' ', last_name)) as full_name,
                   COALESCE(first_name, username) as first_name,
                   COALESCE(last_name, '') as last_name,
                   COALESCE(email, '') as email";

        if ($hasOnboardingColumns) {
            $selectFields .= ",
                   COALESCE(login_identifier, '') as login_identifier,
                   COALESCE(login_type, 'username') as login_type,
                   COALESCE(must_change_password, 0) as must_change_password";
        }

        $stmt = $db->prepare("
            SELECT {$selectFields}
            FROM users
            WHERE username = ? AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$identifier]);
        $user = $stmt->fetch();

        // If still no user and we have onboarding columns, also try by email directly
        if (!$user && $hasOnboardingColumns) {
            $stmt = $db->prepare("
                SELECT id, username, password, role, is_active,
                       COALESCE(employee_id, '') as employee_id,
                       COALESCE(full_name, CONCAT(first_name, ' ', last_name)) as full_name,
                       COALESCE(first_name, username) as first_name,
                       COALESCE(last_name, '') as last_name,
                       COALESCE(email, '') as email,
                       COALESCE(login_identifier, '') as login_identifier,
                       COALESCE(login_type, 'username') as login_type,
                       COALESCE(must_change_password, 0) as must_change_password
                FROM users
                WHERE LOWER(email) = ? AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$identifierKey]);
            $user = $stmt->fetch();
        }
    }

    // Ensure must_change_password has a default for pre-migration users
    if ($user && !isset($user['must_change_password'])) {
        $user['must_change_password'] = 0;
    }
    
    if (!$user) {
        $isLocked = recordFailedLoginAttempt($db, $identifierKey, $clientIp, MAX_LOGIN_ATTEMPTS, LOCKOUT_MINUTES);
        if ($isLocked) {
            Response::error(LOCKOUT_MESSAGE, 429);
        }
        Response::error(INVALID_LOGIN_MESSAGE, 401);
    }
    
    // Verify password
    if (!Auth::verifyPassword($password, $user['password'])) {
        $isLocked = recordFailedLoginAttempt($db, $identifierKey, $clientIp, MAX_LOGIN_ATTEMPTS, LOCKOUT_MINUTES);
        if ($isLocked) {
            Response::error(LOCKOUT_MESSAGE, 429);
        }
        Response::error(INVALID_LOGIN_MESSAGE, 401);
    }

    clearLoginAttempts($db, $identifierKey, $clientIp);
    
    // Update last login — use last_login_at if available, fall back to last_login
    try {
        if ($hasOnboardingColumns) {
            $updateStmt = $db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
        } else {
            $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        }
        $updateStmt->execute([$user['id']]);
    } catch (Exception $e) {
        // Ignore last_login update errors
    }
    
    // Generate token + create server-side session
    $issuedAt = time();
    $sessionId = Auth::generateSessionId();
    $token = Auth::generateToken($user, $sessionId, $issuedAt);
    Auth::createSession(
        $user['id'],
        $sessionId,
        $clientIp,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
        $issuedAt
    );
    
    // Log audit (don't fail login if audit fails)
    try {
        logAudit($user['id'], 'LOGIN', 'users', $user['id'], [
            'authenticated' => false
        ], [
            'authenticated' => true,
            'username' => $user['username'],
            'session_id' => $sessionId,
            'login_type' => $user['login_type'] ?? 'username'
        ]);
    } catch (Exception $e) {
        // Ignore audit log errors - don't block login
        error_log("Audit log warning: " . $e->getMessage());
    }
    
    // Remove password from response
    unset($user['password']);
    
    // Build response data
    $responseData = [
        'user' => $user,
        'token' => $token,
        'expires_in' => JWT_EXPIRY,
        'idle_timeout' => SESSION_IDLE_TIMEOUT,
        'must_change_password' => (int) ($user['must_change_password'] ?? 0)
    ];

    Response::success($responseData, 'Login successful');
    
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    Response::error('An error occurred during login: ' . $e->getMessage(), 500);
}

function normalizeLoginIdentifier($username) {
    $normalized = trim((string) $username);
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($normalized, 'UTF-8');
    }
    return strtolower($normalized);
}

function getClientIpAddress() {
    $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if (!empty($forwarded)) {
        $parts = explode(',', $forwarded);
        return trim($parts[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function ensureLoginAttemptsTable($db) {
    $db->exec("
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

function isLoginTemporarilyLocked($db, $username, $clientIp) {
    $stmt = $db->prepare("
        SELECT id, locked_until
        FROM login_attempts
        WHERE username = ? AND ip_address = ?
        LIMIT 1
    ");
    $stmt->execute([$username, $clientIp]);
    $attempt = $stmt->fetch();

    if (!$attempt || empty($attempt['locked_until'])) {
        return false;
    }

    $lockedUntil = strtotime($attempt['locked_until']);
    if ($lockedUntil !== false && $lockedUntil > time()) {
        return true;
    }

    $resetStmt = $db->prepare("
        UPDATE login_attempts
        SET failed_attempts = 0,
            first_failed_at = NULL,
            last_failed_at = NULL,
            locked_until = NULL,
            updated_at = NOW()
        WHERE id = ?
    ");
    $resetStmt->execute([$attempt['id']]);

    return false;
}

function recordFailedLoginAttempt($db, $username, $clientIp, $maxAttempts, $lockoutMinutes) {
    $selectStmt = $db->prepare("
        SELECT id, failed_attempts, first_failed_at, locked_until
        FROM login_attempts
        WHERE username = ? AND ip_address = ?
        LIMIT 1
    ");
    $selectStmt->execute([$username, $clientIp]);
    $attempt = $selectStmt->fetch();

    $now = date('Y-m-d H:i:s');

    if (!$attempt) {
        $failedAttempts = 1;
        $lockedUntil = null;
        if ($failedAttempts >= $maxAttempts) {
            $lockedUntil = date('Y-m-d H:i:s', time() + ($lockoutMinutes * 60));
        }

        $insertStmt = $db->prepare("
            INSERT INTO login_attempts (username, ip_address, failed_attempts, first_failed_at, last_failed_at, locked_until)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $insertStmt->execute([$username, $clientIp, $failedAttempts, $now, $now, $lockedUntil]);
        return $lockedUntil !== null;
    }

    $lockedUntilTs = !empty($attempt['locked_until']) ? strtotime($attempt['locked_until']) : false;
    $lockExpired = $lockedUntilTs !== false && $lockedUntilTs <= time();

    $failedAttempts = $lockExpired ? 1 : ((int) $attempt['failed_attempts'] + 1);
    $firstFailedAt = ($lockExpired || empty($attempt['first_failed_at'])) ? $now : $attempt['first_failed_at'];
    $nextLockedUntil = null;
    if ($failedAttempts >= $maxAttempts) {
        $nextLockedUntil = date('Y-m-d H:i:s', time() + ($lockoutMinutes * 60));
    }

    $updateStmt = $db->prepare("
        UPDATE login_attempts
        SET failed_attempts = ?,
            first_failed_at = ?,
            last_failed_at = ?,
            locked_until = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $updateStmt->execute([$failedAttempts, $firstFailedAt, $now, $nextLockedUntil, $attempt['id']]);

    return $nextLockedUntil !== null;
}

function clearLoginAttempts($db, $username, $clientIp) {
    $stmt = $db->prepare("
        DELETE FROM login_attempts
        WHERE username = ? AND ip_address = ?
    ");
    $stmt->execute([$username, $clientIp]);
}
