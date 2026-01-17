<?php
/**
 * Highland Fresh System - API Debug/Test
 * 
 * Access: http://localhost/HighlandFreshAppV4/api/test.php
 */

header('Content-Type: application/json; charset=UTF-8');

$results = [];

// Test 1: PHP Version
$results['php_version'] = PHP_VERSION;
$results['php_version_ok'] = version_compare(PHP_VERSION, '7.4.0', '>=');

// Test 2: Check required files
$files = [
    'config/config.php',
    'config/database.php',
    'config/response.php',
    'config/auth.php',
    'auth/login.php'
];

$results['files'] = [];
foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    $results['files'][$file] = file_exists($path);
}

// Test 3: Database connection
try {
    define('HIGHLAND_FRESH', true);
    require_once __DIR__ . '/config/config.php';
    
    $dsn = "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $results['mysql_connection'] = true;
    
    // Test 4: Check if database exists
    $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . DB_NAME . "'");
    $dbExists = $stmt->fetch();
    $results['database_exists'] = (bool) $dbExists;
    
    if ($dbExists) {
        $pdo->exec("USE " . DB_NAME);
        
        // Test 5: Check if users table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        $results['users_table_exists'] = (bool) $stmt->fetch();
        
        if ($results['users_table_exists']) {
            // Test 6: Check if QC user exists
            $stmt = $pdo->query("SELECT id, username, role FROM users WHERE username = 'qc_officer'");
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $results['qc_user_exists'] = (bool) $user;
            if ($user) {
                $results['qc_user'] = $user;
            }
        }
        
        // Test 7: Check audit_logs table
        $stmt = $pdo->query("SHOW TABLES LIKE 'audit_logs'");
        $results['audit_logs_table_exists'] = (bool) $stmt->fetch();
    }
    
} catch (PDOException $e) {
    $results['mysql_connection'] = false;
    $results['mysql_error'] = $e->getMessage();
}

// Summary
$results['status'] = 'ok';
$results['issues'] = [];

if (!$results['mysql_connection']) {
    $results['status'] = 'error';
    $results['issues'][] = 'Cannot connect to MySQL. Make sure XAMPP MySQL is running.';
}

if (isset($results['database_exists']) && !$results['database_exists']) {
    $results['status'] = 'error';
    $results['issues'][] = "Database '" . DB_NAME . "' does not exist. Run setup.php first.";
}

if (isset($results['users_table_exists']) && !$results['users_table_exists']) {
    $results['status'] = 'error';
    $results['issues'][] = "Users table does not exist. Run setup.php first.";
}

if (isset($results['qc_user_exists']) && !$results['qc_user_exists']) {
    $results['status'] = 'warning';
    $results['issues'][] = "QC Officer user does not exist. Run setup.php to create it.";
}

if (isset($results['audit_logs_table_exists']) && !$results['audit_logs_table_exists']) {
    $results['status'] = 'warning';
    $results['issues'][] = "Audit logs table does not exist. This will cause login to fail.";
}

// Output
echo json_encode($results, JSON_PRETTY_PRINT);
