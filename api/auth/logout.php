<?php
/**
 * Highland Fresh System - Logout API
 *
 * POST /api/auth/logout.php - Revoke current authenticated session
 *
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Only allow POST
if ($requestMethod !== 'POST') {
    Response::error('Method not allowed', 405);
}

$currentUser = Auth::requireAuth();

if (empty($currentUser['sid'])) {
    Response::unauthorized('Please login to continue');
}

$requestedReason = (string) getParam('reason', 'logout');
$allowedReasons = ['logout', 'manual_logout', 'idle_timeout', 'absolute_timeout', 'session_expired'];
$reason = in_array($requestedReason, $allowedReasons, true) ? $requestedReason : 'logout';

Auth::revokeSession($currentUser['sid'], $reason);

try {
    logAudit($currentUser['user_id'], 'LOGOUT', 'users', $currentUser['user_id'], [
        'authenticated' => true,
        'session_id' => $currentUser['sid']
    ], [
        'authenticated' => false,
        'session_id' => $currentUser['sid'],
        'reason' => $reason
    ]);
} catch (Exception $e) {
    error_log("Logout audit warning: " . $e->getMessage());
}

Response::success(null, 'Logged out successfully');
