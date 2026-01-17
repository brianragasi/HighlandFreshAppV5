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

// Only allow POST
if ($requestMethod !== 'POST') {
    Response::error('Method not allowed', 405);
}

// Validate input
$username = getParam('username');
$password = getParam('password');

if (empty($username) || empty($password)) {
    Response::validationError([
        'username' => empty($username) ? 'Username is required' : null,
        'password' => empty($password) ? 'Password is required' : null
    ]);
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Find user - handle missing columns gracefully
    $stmt = $db->prepare("
        SELECT id, username, password, role, is_active,
               COALESCE(employee_id, '') as employee_id,
               COALESCE(first_name, username) as first_name,
               COALESCE(last_name, '') as last_name,
               COALESCE(email, '') as email
        FROM users
        WHERE username = ? AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        Response::error('Invalid username or password', 401);
    }
    
    // Verify password
    if (!Auth::verifyPassword($password, $user['password'])) {
        Response::error('Invalid username or password', 401);
    }
    
    // Update last login (ignore if column doesn't exist)
    try {
        $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $updateStmt->execute([$user['id']]);
    } catch (Exception $e) {
        // Ignore last_login update errors
    }
    
    // Generate token
    $token = Auth::generateToken($user);
    
    // Log audit (don't fail login if audit fails)
    try {
        logAudit($user['id'], 'LOGIN', 'users', $user['id'], null, ['ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    } catch (Exception $e) {
        // Ignore audit log errors - don't block login
        error_log("Audit log warning: " . $e->getMessage());
    }
    
    // Remove password from response
    unset($user['password']);
    
    Response::success([
        'user' => $user,
        'token' => $token,
        'expires_in' => JWT_EXPIRY
    ], 'Login successful');
    
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    Response::error('An error occurred during login: ' . $e->getMessage(), 500);
}
