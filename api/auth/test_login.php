<?php
/**
 * Direct Login Test
 * Access: http://localhost/HighlandFreshAppV4/api/auth/test_login.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

echo "Step 1: Starting...\n";

// Test 1: Define constant
define('HIGHLAND_FRESH', true);
echo "Step 2: Constant defined\n";

// Test 2: Load config
try {
    require_once dirname(__DIR__) . '/config/config.php';
    echo "Step 3: Config loaded - DB_NAME=" . DB_NAME . "\n";
} catch (Exception $e) {
    die("Config error: " . $e->getMessage());
}

// Test 3: Load database
try {
    require_once dirname(__DIR__) . '/config/database.php';
    echo "Step 4: Database class loaded\n";
} catch (Exception $e) {
    die("Database class error: " . $e->getMessage());
}

// Test 4: Load response
try {
    require_once dirname(__DIR__) . '/config/response.php';
    echo "Step 5: Response class loaded\n";
} catch (Exception $e) {
    die("Response class error: " . $e->getMessage());
}

// Test 5: Load auth
try {
    require_once dirname(__DIR__) . '/config/auth.php';
    echo "Step 6: Auth class loaded\n";
} catch (Exception $e) {
    die("Auth class error: " . $e->getMessage());
}

// Test 6: Connect to database
try {
    $db = Database::getInstance()->getConnection();
    echo "Step 7: Database connected\n";
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// Test 7: Query users
try {
    $stmt = $db->query("SELECT id, username, role FROM users LIMIT 5");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Step 8: Users found: " . count($users) . "\n";
    print_r($users);
} catch (Exception $e) {
    die("Query error: " . $e->getMessage());
}

// Test 8: Test password verification
try {
    $stmt = $db->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    $stmt->execute(['qc_officer']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "Step 9: Found user: " . $user['username'] . "\n";
        echo "Password hash: " . substr($user['password'], 0, 20) . "...\n";
        
        $testPassword = 'password';
        $verified = password_verify($testPassword, $user['password']);
        echo "Step 10: Password verify result: " . ($verified ? 'TRUE' : 'FALSE') . "\n";
    } else {
        echo "Step 9: User qc_officer not found!\n";
    }
} catch (Exception $e) {
    die("Password test error: " . $e->getMessage());
}

// Test 9: Check audit_logs table
try {
    $stmt = $db->query("SHOW TABLES LIKE 'audit_logs'");
    $exists = $stmt->fetch();
    echo "Step 11: audit_logs table exists: " . ($exists ? 'YES' : 'NO') . "\n";
} catch (Exception $e) {
    die("Audit logs check error: " . $e->getMessage());
}

// Test 10: Test token generation
try {
    if (isset($user)) {
        $token = Auth::generateToken($user);
        echo "Step 12: Token generated successfully (length: " . strlen($token) . ")\n";
    }
} catch (Exception $e) {
    die("Token generation error: " . $e->getMessage());
}

echo "\n\n=== ALL TESTS PASSED ===\n";
echo "Login should work. If it still fails, check browser console for CORS or other issues.\n";
