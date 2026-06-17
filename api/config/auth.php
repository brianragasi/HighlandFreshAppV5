<?php
/**
 * Highland Fresh System - Authentication Helper
 * 
 * JWT-based authentication for API
 * 
 * @package HighlandFresh
 * @version 4.0
 */

// Prevent direct access
if (!defined('HIGHLAND_FRESH')) {
    http_response_code(403);
    exit('Direct access not allowed');
}

class Auth {
    
    /**
     * Generate JWT token
     */
    public static function generateToken($user, $sessionId, $issuedAt = null) {
        if (empty($sessionId)) {
            throw new InvalidArgumentException('Session ID is required');
        }

        $issuedAt = $issuedAt ?: time();
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        
        // Handle missing full_name/first_name/last_name gracefully
        $fullName = $user['full_name'] ?? null;
        if (!$fullName) {
            $firstName = $user['first_name'] ?? $user['username'] ?? 'User';
            $lastName = $user['last_name'] ?? '';
            $fullName = trim($firstName . ' ' . $lastName);
        }
        
        $payload = json_encode([
            'iss' => APP_NAME,
            'iat' => $issuedAt,
            'exp' => $issuedAt + JWT_EXPIRY,
            'sid' => $sessionId,
            'user_id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'name' => $fullName
        ]);
        
        $base64Header = self::base64UrlEncode($header);
        $base64Payload = self::base64UrlEncode($payload);
        
        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, JWT_SECRET, true);
        $base64Signature = self::base64UrlEncode($signature);
        
        return $base64Header . "." . $base64Payload . "." . $base64Signature;
    }
    
    /**
     * Verify JWT token
     */
    public static function verifyToken($token) {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return false;
        }
        
        list($base64Header, $base64Payload, $base64Signature) = $parts;
        
        // Verify signature
        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, JWT_SECRET, true);
        $expectedSignature = self::base64UrlEncode($signature);
        
        if (!hash_equals($expectedSignature, $base64Signature)) {
            return false;
        }
        
        // Decode payload
        $payload = json_decode(self::base64UrlDecode($base64Payload), true);
        if (!is_array($payload)) {
            return false;
        }
        
        // Check expiration
        if (!isset($payload['exp']) || $payload['exp'] < time()) {
            return false;
        }

        if (empty($payload['sid']) || empty($payload['user_id'])) {
            return false;
        }

        if (!self::validateServerSession($payload)) {
            return false;
        }
        
        return $payload;
    }
    
    /**
     * Get current user from request
     */
    public static function getCurrentUser() {
        $token = self::extractBearerToken();
        if (!$token) {
            return null;
        }

        return self::verifyToken($token);
    }

    /**
     * Extract bearer token from request
     */
    public static function extractBearerToken() {
        // getallheaders() is only available under mod_php, NOT under FPM/CGI
        // (which InfinityFree uses). Build the headers array from $_SERVER
        // instead, which is universal.
        $headers = [];
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        } else {
            foreach ($_SERVER as $key => $value) {
                if (strpos($key, 'HTTP_') === 0) {
                    // HTTP_AUTHORIZATION -> Authorization
                    $name = str_replace('_', '-', substr($key, 5));
                    $name = ucwords(strtolower($name), '-');
                    $headers[$name] = $value;
                } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                    $name = str_replace('_', '-', $key);
                    $name = ucwords(strtolower($name), '-');
                    $headers[$name] = $value;
                }
            }
            // PHP-FPM exposes the Authorization header in a non-standard
            // variable when running behind Apache with mod_rewrite/PHP-FPM
            if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                $headers['Authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            }
        }

        $authHeader = $headers['Authorization']
            ?? $headers['authorization']
            ?? $headers['X-Auth-Token']
            ?? $headers['x-auth-token']
            ?? $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['HTTP_X_AUTH_TOKEN']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? $_COOKIE['highland_token'] ?? '';

        if (empty($authHeader)) {
            return null;
        }

        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        return $authHeader;
    }

    /**
     * Create random session ID for JWT
     */
    public static function generateSessionId() {
        return bin2hex(random_bytes(32));
    }

    /**
     * Persist server-side session metadata
     */
    public static function createSession($userId, $sessionId, $ipAddress = null, $userAgent = null, $issuedAt = null) {
        $issuedAt = $issuedAt ?: time();

        $db = Database::getInstance()->getConnection();
        self::ensureSessionsTable($db);

        $stmt = $db->prepare("
            INSERT INTO auth_sessions (session_id, user_id, ip_address, user_agent, issued_at, last_activity, expires_at)
            VALUES (?, ?, ?, ?, FROM_UNIXTIME(?), NOW(), FROM_UNIXTIME(?))
        ");
        $stmt->execute([
            $sessionId,
            (int) $userId,
            $ipAddress,
            $userAgent,
            $issuedAt,
            $issuedAt + JWT_EXPIRY
        ]);
    }

    /**
     * Revoke a server-side session
     */
    public static function revokeSession($sessionId, $reason = 'logout') {
        if (empty($sessionId)) {
            return;
        }

        $db = Database::getInstance()->getConnection();
        self::ensureSessionsTable($db);

        $stmt = $db->prepare("
            UPDATE auth_sessions
            SET revoked_at = NOW(),
                revoked_reason = ?,
                updated_at = NOW()
            WHERE session_id = ? AND revoked_at IS NULL
        ");
        $stmt->execute([$reason, $sessionId]);
    }

    /**
     * Revoke ALL active sessions for a given user
     * Used when password changes, role changes, or account is deactivated
     *
     * @param int    $userId           The user whose sessions to revoke
     * @param string $reason           Reason for revocation (for audit)
     * @param string|null $exceptSessionId  Optional session ID to keep (e.g. the current session)
     * @return int   Number of sessions revoked
     */
    public static function revokeAllSessionsByUserId($userId, $reason = 'security', $exceptSessionId = null) {
        $db = Database::getInstance()->getConnection();
        self::ensureSessionsTable($db);

        if ($exceptSessionId) {
            $stmt = $db->prepare("
                UPDATE auth_sessions
                SET revoked_at = NOW(),
                    revoked_reason = ?,
                    updated_at = NOW()
                WHERE user_id = ?
                  AND revoked_at IS NULL
                  AND session_id != ?
            ");
            $stmt->execute([$reason, (int) $userId, $exceptSessionId]);
        } else {
            $stmt = $db->prepare("
                UPDATE auth_sessions
                SET revoked_at = NOW(),
                    revoked_reason = ?,
                    updated_at = NOW()
                WHERE user_id = ?
                  AND revoked_at IS NULL
            ");
            $stmt->execute([$reason, (int) $userId]);
        }

        return $stmt->rowCount();
    }

    /**
     * Create one-time step-up token for high-risk actions
     */
    public static function issueStepUpToken($currentUser, $scope) {
        $userId = (int) ($currentUser['user_id'] ?? 0);
        $sessionId = $currentUser['sid'] ?? null;

        if ($userId <= 0 || empty($sessionId) || empty($scope)) {
            throw new InvalidArgumentException('Invalid step-up context');
        }

        $db = Database::getInstance()->getConnection();
        self::ensureStepUpsTable($db);

        $db->prepare("
            DELETE FROM auth_stepups
            WHERE expires_at <= NOW() OR used_at IS NOT NULL
        ")->execute();

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        $stmt = $db->prepare("
            INSERT INTO auth_stepups (token_hash, user_id, session_id, scope, expires_at)
            VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))
        ");
        $stmt->execute([$tokenHash, $userId, $sessionId, $scope, STEP_UP_TOKEN_EXPIRY]);

        return $token;
    }

    /**
     * Consume one-time step-up token
     */
    public static function consumeStepUpToken($currentUser, $scope, $token) {
        $userId = (int) ($currentUser['user_id'] ?? 0);
        $sessionId = $currentUser['sid'] ?? null;

        if ($userId <= 0 || empty($sessionId) || empty($scope) || empty($token)) {
            return false;
        }

        $db = Database::getInstance()->getConnection();
        self::ensureStepUpsTable($db);

        $tokenHash = hash('sha256', $token);
        $selectStmt = $db->prepare("
            SELECT id
            FROM auth_stepups
            WHERE token_hash = ?
              AND user_id = ?
              AND session_id = ?
              AND scope = ?
              AND used_at IS NULL
              AND expires_at > NOW()
            LIMIT 1
        ");
        $selectStmt->execute([$tokenHash, $userId, $sessionId, $scope]);
        $row = $selectStmt->fetch();

        if (!$row) {
            return false;
        }

        $updateStmt = $db->prepare("
            UPDATE auth_stepups
            SET used_at = NOW()
            WHERE id = ? AND used_at IS NULL
        ");
        $updateStmt->execute([$row['id']]);

        return $updateStmt->rowCount() > 0;
    }

    /**
     * Enforce step-up token for sensitive actions
     */
    public static function requireStepUp($currentUser, $scope, $token) {
        if (!self::consumeStepUpToken($currentUser, $scope, $token)) {
            Response::error('Step-up authentication required for this action', 403);
        }
    }
    
    /**
     * Require authentication
     */
    public static function requireAuth() {
        $user = self::getCurrentUser();
        
        if (!$user) {
            Response::unauthorized('Please login to continue');
        }
        
        return $user;
    }
    
    /**
     * Require specific role(s)
     */
    public static function requireRole($roles) {
        $user = self::requireAuth();
        
        if (is_string($roles)) {
            $roles = [$roles];
        }
        
        if (!in_array($user['role'], $roles)) {
            Response::forbidden('You do not have permission to access this resource');
        }
        
        return $user;
    }
    
    /**
     * Hash password
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => PASSWORD_COST]);
    }
    
    /**
     * Verify password
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Base64 URL encode
     */
    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Base64 URL decode
     */
    private static function base64UrlDecode($data) {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
    }

    /**
     * Ensure auth_sessions table exists
     */
    private static function ensureSessionsTable($db) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `auth_sessions` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `session_id` VARCHAR(64) NOT NULL,
                `user_id` INT NOT NULL,
                `ip_address` VARCHAR(45) DEFAULT NULL,
                `user_agent` VARCHAR(255) DEFAULT NULL,
                `issued_at` DATETIME NOT NULL,
                `last_activity` DATETIME NOT NULL,
                `expires_at` DATETIME NOT NULL,
                `revoked_at` DATETIME DEFAULT NULL,
                `revoked_reason` VARCHAR(50) DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_auth_sessions_session_id` (`session_id`),
                KEY `idx_auth_sessions_user_id` (`user_id`),
                KEY `idx_auth_sessions_expires_at` (`expires_at`),
                KEY `idx_auth_sessions_revoked_at` (`revoked_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }

    /**
     * Ensure auth_stepups table exists
     */
    private static function ensureStepUpsTable($db) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `auth_stepups` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `token_hash` CHAR(64) NOT NULL,
                `user_id` INT NOT NULL,
                `session_id` VARCHAR(64) NOT NULL,
                `scope` VARCHAR(64) NOT NULL,
                `expires_at` DATETIME NOT NULL,
                `used_at` DATETIME DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_auth_stepups_token_hash` (`token_hash`),
                KEY `idx_auth_stepups_user_session_scope` (`user_id`, `session_id`, `scope`),
                KEY `idx_auth_stepups_expires_at` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }

    /**
     * Validate and refresh server-side session activity
     */
    private static function validateServerSession($payload) {
        $db = Database::getInstance()->getConnection();
        self::ensureSessionsTable($db);

        $stmt = $db->prepare("
            SELECT id, last_activity, expires_at, revoked_at
            FROM auth_sessions
            WHERE session_id = ? AND user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$payload['sid'], (int) $payload['user_id']]);
        $session = $stmt->fetch();

        if (!$session || !empty($session['revoked_at'])) {
            return false;
        }

        // CRITICAL: MySQL stores NOW() in the SERVER's timezone (UTC on
        // InfinityFree), but PHP's date_default_timezone_set() may be set to
        // something else (e.g. Asia/Manila). strtotime() interprets the
        // datetime string in PHP's timezone, which produces a Unix timestamp
        // off by the timezone difference. That made a freshly-created
        // session immediately look "idle" and get revoked.
        //
        // Fix: parse the MySQL datetime with an EXPLICIT UTC timezone, so
        // the resulting Unix timestamp matches time() (which is always UTC).
        $utc = new DateTimeZone('UTC');
        try {
            $expiresDt = new DateTime($session['expires_at'], $utc);
            $lastActivityDt = new DateTime($session['last_activity'], $utc);
        } catch (Exception $e) {
            return false;
        }

        $now = time();
        $expiresAt = $expiresDt->getTimestamp();
        $lastActivity = $lastActivityDt->getTimestamp();

        if ($expiresAt <= $now) {
            self::markSessionRevoked($db, $session['id'], 'absolute_timeout');
            return false;
        }

        if (($lastActivity + SESSION_IDLE_TIMEOUT) <= $now) {
            self::markSessionRevoked($db, $session['id'], 'idle_timeout');
            return false;
        }

        $updateStmt = $db->prepare("
            UPDATE auth_sessions
            SET last_activity = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$session['id']]);

        return true;
    }

    /**
     * Revoke session by internal row ID
     */
    private static function markSessionRevoked($db, $sessionRowId, $reason) {
        $stmt = $db->prepare("
            UPDATE auth_sessions
            SET revoked_at = NOW(),
                revoked_reason = ?,
                updated_at = NOW()
            WHERE id = ? AND revoked_at IS NULL
        ");
        $stmt->execute([$reason, $sessionRowId]);
    }
}
