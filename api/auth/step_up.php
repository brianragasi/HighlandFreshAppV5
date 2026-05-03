<?php
/**
 * Highland Fresh System - Step-up Authentication API
 *
 * POST /api/auth/step_up.php - Verify password and issue one-time step-up token
 */

require_once dirname(__DIR__) . '/bootstrap.php';

if ($requestMethod !== 'POST') {
    Response::error('Method not allowed', 405);
}

$currentUser = Auth::requireAuth();
$data = getRequestBody();

$scope = trim((string) ($data['scope'] ?? ''));
$password = (string) ($data['password'] ?? '');

$allowedScopes = ['po_approval', 'payment_release', 'disposal_approval'];
if (!in_array($scope, $allowedScopes, true)) {
    Response::error('Invalid step-up scope', 400);
}

if ($password === '') {
    Response::validationError(['password' => 'Password is required']);
}

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        SELECT id, password, is_active
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([(int) $currentUser['user_id']]);
    $user = $stmt->fetch();

    if (!$user || (int) $user['is_active'] !== 1 || !Auth::verifyPassword($password, $user['password'])) {
        Response::error('Step-up authentication failed', 401);
    }

    $token = Auth::issueStepUpToken($currentUser, $scope);

    Response::success([
        'step_up_token' => $token,
        'expires_in' => STEP_UP_TOKEN_EXPIRY,
        'scope' => $scope
    ], 'Step-up authentication successful');
} catch (Exception $e) {
    error_log("Step-up auth error: " . $e->getMessage());
    Response::error('An error occurred during step-up authentication', 500);
}
