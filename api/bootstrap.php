<?php
/**
 * Highland Fresh System - API Bootstrap
 * 
 * Common initialization for all API endpoints
 * 
 * @package HighlandFresh
 * @version 4.0
 */

// Define application constant
define('HIGHLAND_FRESH', true);

// Error reporting (enable for debugging)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Set headers for JSON API
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-HTTP-Method-Override, X-Auth-Token');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load configuration
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/response.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/mailer.php';
require_once __DIR__ . '/config/stock.php';

// Get request method (with method override support for nginx)
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Support X-HTTP-Method-Override header for PUT/DELETE via POST
if ($requestMethod === 'POST') {
    $override = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? null;
    if ($override && in_array(strtoupper($override), ['PUT', 'DELETE', 'PATCH'])) {
        $requestMethod = strtoupper($override);
    }
}

$requestBody = json_decode(file_get_contents('php://input'), true) ?? [];

// Also check for _method in body (alternative override)
if (isset($requestBody['_method']) && in_array(strtoupper($requestBody['_method']), ['PUT', 'DELETE', 'PATCH'])) {
    $requestMethod = strtoupper($requestBody['_method']);
}

// Helper function to get request parameter
function getParam($key, $default = null) {
    global $requestBody;
    return $requestBody[$key] ?? $_GET[$key] ?? $_POST[$key] ?? $default;
}

// Helper function to get all params
function getParams() {
    global $requestBody;
    return array_merge($_GET, $_POST, $requestBody);
}

// Helper function to get request body
function getRequestBody() {
    global $requestBody;
    return $requestBody;
}

// Helper function to generate unique codes
function generateCode($prefix, $length = 6) {
    return $prefix . '-' . str_pad(mt_rand(1, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

function auditGetClientIpAddress() {
    $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if (!empty($forwarded)) {
        $parts = explode(',', $forwarded);
        return trim($parts[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? null;
}

function auditIsAssocArray($value) {
    if (!is_array($value)) {
        return false;
    }
    return array_keys($value) !== range(0, count($value) - 1);
}

function auditNormalizeValue($value) {
    if (!is_array($value)) {
        return $value;
    }

    $normalized = [];
    foreach ($value as $key => $item) {
        $normalized[$key] = auditNormalizeValue($item);
    }

    if (auditIsAssocArray($normalized)) {
        ksort($normalized);
    }

    return $normalized;
}

function auditJsonEncode($value) {
    if ($value === null) {
        return null;
    }
    return json_encode(auditNormalizeValue($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function auditColumnExists($db, $tableName, $columnName) {
    $stmt = $db->prepare("
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
        LIMIT 1
    ");
    $stmt->execute([$tableName, $columnName]);
    return (bool) $stmt->fetchColumn();
}

function auditTableExists($db, $tableName) {
    $stmt = $db->prepare("
        SELECT 1
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
        LIMIT 1
    ");
    $stmt->execute([$tableName]);
    return (bool) $stmt->fetchColumn();
}

function ensureAuditLogIntegrityColumns($db) {
    static $checked = false;
    static $hasIntegrityColumns = false;

    if ($checked) {
        return $hasIntegrityColumns;
    }

    $checked = true;

    $hasPrevHash = auditColumnExists($db, 'audit_logs', 'prev_hash');
    $hasEntryHash = auditColumnExists($db, 'audit_logs', 'entry_hash');

    if (!$hasPrevHash) {
        $db->exec("ALTER TABLE audit_logs ADD COLUMN prev_hash CHAR(64) NULL AFTER user_agent");
    }
    if (!$hasEntryHash) {
        $db->exec("ALTER TABLE audit_logs ADD COLUMN entry_hash CHAR(64) NULL AFTER prev_hash");
    }

    try {
        $db->exec("ALTER TABLE audit_logs ADD UNIQUE KEY uk_audit_logs_entry_hash (entry_hash)");
    } catch (Exception $e) {
        // index already exists
    }
    try {
        $db->exec("ALTER TABLE audit_logs ADD KEY idx_audit_logs_prev_hash (prev_hash)");
    } catch (Exception $e) {
        // index already exists
    }

    $hasIntegrityColumns = auditColumnExists($db, 'audit_logs', 'prev_hash')
        && auditColumnExists($db, 'audit_logs', 'entry_hash');

    return $hasIntegrityColumns;
}

// Helper function for audit logging
function logAudit($userId, $action, $tableName, $recordId, $oldValues = null, $newValues = null) {
    try {
        $db = Database::getInstance()->getConnection();
        if ($db->inTransaction()) {
            // Avoid implicit commits from DDL while a caller transaction is open.
            $hasIntegrityColumns = auditColumnExists($db, 'audit_logs', 'prev_hash')
                && auditColumnExists($db, 'audit_logs', 'entry_hash');
        } else {
            $hasIntegrityColumns = ensureAuditLogIntegrityColumns($db);
        }

        $oldJson = auditJsonEncode($oldValues);
        $newJson = auditJsonEncode($newValues);
        $ipAddress = auditGetClientIpAddress();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $createdAt = date('Y-m-d H:i:s');

        if (!$hasIntegrityColumns) {
            $stmt = $db->prepare("
                INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $action,
                $tableName,
                $recordId,
                $oldJson,
                $newJson,
                $ipAddress,
                $userAgent,
                $createdAt
            ]);
            return;
        }

        $lockStmt = $db->query("SELECT GET_LOCK('audit_logs_chain', 5) AS lock_ok");
        $lockRow = $lockStmt ? $lockStmt->fetch() : null;
        $lockAcquired = $lockRow && (int) ($lockRow['lock_ok'] ?? 0) === 1;

        $startedTransaction = false;

        try {
            if (!$db->inTransaction()) {
                $db->beginTransaction();
                $startedTransaction = true;
            }

            $prevStmt = $db->query("
                SELECT entry_hash
                FROM audit_logs
                WHERE entry_hash IS NOT NULL
                ORDER BY id DESC
                LIMIT 1
                FOR UPDATE
            ");
            $prevHash = $prevStmt ? $prevStmt->fetchColumn() : null;
            $prevHash = $prevHash ?: str_repeat('0', 64);

            $payload = [
                'user_id' => $userId,
                'action' => $action,
                'table_name' => $tableName,
                'record_id' => $recordId,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'created_at' => $createdAt,
                'prev_hash' => $prevHash
            ];
            $payloadJson = auditJsonEncode($payload) ?: '{}';
            $entryHash = hash_hmac('sha256', $payloadJson, AUDIT_LOG_SECRET);

            $insertStmt = $db->prepare("
                INSERT INTO audit_logs
                (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, prev_hash, entry_hash)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insertStmt->execute([
                $userId,
                $action,
                $tableName,
                $recordId,
                $oldJson,
                $newJson,
                $ipAddress,
                $userAgent,
                $createdAt,
                $prevHash,
                $entryHash
            ]);

            if ($startedTransaction) {
                $db->commit();
            }
        } catch (Exception $e) {
            if ($startedTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        } finally {
            if ($lockAcquired) {
                $db->query("SELECT RELEASE_LOCK('audit_logs_chain')");
            }
        }
    } catch (Exception $e) {
        error_log("Audit log error: " . $e->getMessage());
    }
}

// Helper function to send success response (wraps Response::success)
function sendSuccess($data = null, $message = 'Success', $code = 200) {
    Response::success($data, $message, $code);
}

// Helper function to send error response (wraps Response::error)
function sendError($message = 'Error', $code = 400, $errors = null) {
    Response::error($message, $code, $errors);
}

// Helper function to send validation error (wraps Response::validationError)
function sendValidationError($errors) {
    Response::validationError($errors);
}

// Helper function to enforce per-action role authorization
function requireActionRole($currentUser, $roles, $message = 'Access forbidden') {
    if (is_string($roles)) {
        $roles = [$roles];
    }

    $role = $currentUser['role'] ?? null;
    if (!$role || !in_array($role, $roles, true)) {
        Response::forbidden($message);
    }

    return $currentUser;
}

// Legacy compatibility functions
function requireAuth($roles = []) {
    return Auth::requireRole($roles);
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}
