<?php
// Test finance_officer login flow
define('HIGHLAND_FRESH', true);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';

require_once __DIR__ . '/../api/config/config.php';
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../api/config/auth.php';

$db = Database::getInstance()->getConnection();

// Step 1: Get user
$stmt = $db->prepare("SELECT id, username, password, role, is_active, full_name, first_name, last_name FROM users WHERE username = ?");
$stmt->execute(['finance_officer']);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

echo "1. User found: " . ($user ? 'YES' : 'NO') . PHP_EOL;
if (!$user) { echo "FATAL: No user found!" . PHP_EOL; exit(1); }
echo "   ID={$user['id']}, Role={$user['role']}, Active={$user['is_active']}" . PHP_EOL;

// Step 2: Verify password
$pwOk = password_verify('password', $user['password']);
echo "2. Password verify: " . ($pwOk ? 'OK' : 'FAIL') . PHP_EOL;
if (!$pwOk) {
    echo "   Hash: {$user['password']}" . PHP_EOL;
    echo "   FATAL: Password does not match!" . PHP_EOL;
    exit(1);
}

// Step 3: Generate JWT
$token = Auth::generateToken($user);
echo "3. JWT generated: " . substr($token, 0, 50) . "..." . PHP_EOL;

// Step 4: Validate JWT
$decoded = Auth::validateToken($token);
echo "4. JWT validate: " . ($decoded ? 'OK' : 'FAIL') . PHP_EOL;
if ($decoded) {
    echo "   user_id={$decoded['user_id']}, role={$decoded['role']}" . PHP_EOL;
}

// Step 5: Check Auth::authenticate simulation
echo "5. Full auth check: ";
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
try {
    $authUser = Auth::authenticate();
    echo "OK - user_id={$authUser['id']}, role={$authUser['role']}" . PHP_EOL;
} catch (Exception $e) {
    echo "FAIL - " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "=== DONE ===" . PHP_EOL;
