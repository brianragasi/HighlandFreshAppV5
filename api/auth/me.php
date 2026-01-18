<?php
/**
 * Highland Fresh System - Get Current User
 * 
 * GET /api/auth/me.php - Get current authenticated user
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Only allow GET
if ($requestMethod !== 'GET') {
    Response::error('Method not allowed', 405);
}

// Require authentication
$currentUser = Auth::requireAuth();

try {
    $db = Database::getInstance()->getConnection();
    
    // Get user details
    $stmt = $db->prepare("
        SELECT id, employee_id, username, first_name, last_name, email, role, created_at
        FROM users
        WHERE id = ? AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$currentUser['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        Response::notFound('User not found');
    }
    
    Response::success($user, 'User retrieved successfully');
    
} catch (Exception $e) {
    error_log("Get user error: " . $e->getMessage());
    Response::error('An error occurred', 500);
}
