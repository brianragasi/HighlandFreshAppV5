<?php
/**
 * Highland Fresh System - Connection Test
 * 
 * Diagnostic endpoint for troubleshooting Azure deployment
 * DELETE THIS FILE AFTER DEBUGGING
 */

// Set headers
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

$diagnostics = [
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => phpversion(),
    'server' => php_uname()
];

// Check if running on Azure
$isAzure = getenv('WEBSITE_SITE_NAME') !== false;
$diagnostics['is_azure'] = $isAzure;
$diagnostics['azure_site_name'] = getenv('WEBSITE_SITE_NAME') ?: 'not set';

// Check environment variables (masked for security)
$diagnostics['env_vars'] = [
    'DB_HOST' => getenv('DB_HOST') ? 'SET (' . substr(getenv('DB_HOST'), 0, 10) . '...)' : 'NOT SET',
    'DB_NAME' => getenv('DB_NAME') ? 'SET (' . getenv('DB_NAME') . ')' : 'NOT SET',
    'DB_USERNAME' => getenv('DB_USERNAME') ? 'SET' : 'NOT SET',
    'DB_PASSWORD' => getenv('DB_PASSWORD') ? 'SET (hidden)' : 'NOT SET',
    'DB_PORT' => getenv('DB_PORT') ?: 'NOT SET (default 3306)'
];

// Check SSL cert file
$sslCertPath = '/home/site/wwwroot/api/config/DigiCertGlobalRootCA.crt.pem';
$diagnostics['ssl_cert'] = [
    'path' => $sslCertPath,
    'exists' => file_exists($sslCertPath) ? 'YES' : 'NO'
];

// Try database connection
try {
    define('HIGHLAND_FRESH', true);
    
    // Manually set up connection variables
    $dbHost = $isAzure ? (getenv('DB_HOST') ?: 'localhost') : 'localhost';
    $dbName = $isAzure ? (getenv('DB_NAME') ?: 'highland_fresh') : 'highland_fresh';
    $dbUser = $isAzure ? (getenv('DB_USERNAME') ?: 'root') : 'root';
    $dbPass = $isAzure ? (getenv('DB_PASSWORD') ?: '') : '';
    $dbPort = $isAzure ? (getenv('DB_PORT') ?: 3306) : 3306;
    
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ];
    
    // Add SSL options for Azure
    if ($isAzure && file_exists($sslCertPath)) {
        $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCertPath;
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }
    
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
    
    $diagnostics['database'] = [
        'status' => 'CONNECTED',
        'server_info' => $pdo->getAttribute(PDO::ATTR_SERVER_INFO),
        'server_version' => $pdo->getAttribute(PDO::ATTR_SERVER_VERSION)
    ];
    
    // Test a simple query
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    $diagnostics['database']['test_query'] = 'Users count: ' . $result['count'];
    
    // Test QC-specific tables
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM milk_receiving");
    $result = $stmt->fetch();
    $diagnostics['database']['milk_receiving_count'] = $result['count'];
    
} catch (PDOException $e) {
    $diagnostics['database'] = [
        'status' => 'FAILED',
        'error' => $e->getMessage()
    ];
} catch (Exception $e) {
    $diagnostics['database'] = [
        'status' => 'FAILED',
        'error' => $e->getMessage()
    ];
}

// Check required files
$requiredFiles = [
    'bootstrap.php' => __DIR__ . '/bootstrap.php',
    'config.php' => __DIR__ . '/config/config.php',
    'database.php' => __DIR__ . '/config/database.php',
    'auth.php' => __DIR__ . '/config/auth.php',
    'response.php' => __DIR__ . '/config/response.php'
];

$diagnostics['files'] = [];
foreach ($requiredFiles as $name => $path) {
    $diagnostics['files'][$name] = file_exists($path) ? 'EXISTS' : 'MISSING';
}

echo json_encode($diagnostics, JSON_PRETTY_PRINT);
