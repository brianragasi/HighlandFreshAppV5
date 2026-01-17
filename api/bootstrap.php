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
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

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

// Get request method and body
$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestBody = json_decode(file_get_contents('php://input'), true) ?? [];

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

// Helper function for audit logging
function logAudit($userId, $action, $tableName, $recordId, $oldValues = null, $newValues = null) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $action,
            $tableName,
            $recordId,
            $oldValues ? json_encode($oldValues) : null,
            $newValues ? json_encode($newValues) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("Audit log error: " . $e->getMessage());
    }
}
