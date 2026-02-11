<?php
// Debug endpoint to verify Azure deployment
header('Content-Type: application/json');

// Check Azure detection
$isAzure = getenv('WEBSITE_SITE_NAME') !== false;

$info = [
    'status' => 'ok',
    'message' => 'API is working',
    'php_version' => phpversion(),
    'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
    'host' => $_SERVER['HTTP_HOST'] ?? 'unknown',
    'is_azure_detected' => $isAzure,
    'azure_site_name' => getenv('WEBSITE_SITE_NAME') ?: 'NOT SET',
    'db_host_env' => getenv('DB_HOST') ? 'SET' : 'NOT SET',
    'db_name_env' => getenv('DB_NAME') ? 'SET' : 'NOT SET',
    'db_user_env' => getenv('DB_USERNAME') ? 'SET' : 'NOT SET',
    'db_pass_env' => getenv('DB_PASSWORD') ? 'SET (hidden)' : 'NOT SET',
    'ssl_cert_exists' => file_exists('/home/site/wwwroot/api/config/DigiCertGlobalRootCA.crt.pem'),
    'files_exist' => [
        'bootstrap' => file_exists(__DIR__ . '/bootstrap.php'),
        'config' => file_exists(__DIR__ . '/config/config.php'),
        'database' => file_exists(__DIR__ . '/config/database.php'),
    ],
    'timestamp' => date('Y-m-d H:i:s')
];

// Try database connection
if ($isAzure) {
    try {
        $host = getenv('DB_HOST') ?: 'localhost';
        $name = getenv('DB_NAME') ?: 'highland_fresh';
        $user = getenv('DB_USERNAME') ?: 'root';
        $pass = getenv('DB_PASSWORD') ?: '';
        $port = getenv('DB_PORT') ?: 3306;
        
        $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
        $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
        
        // Try with SSL
        $sslCert = '/home/site/wwwroot/api/config/DigiCertGlobalRootCA.crt.pem';
        if (file_exists($sslCert)) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCert;
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }
        
        $pdo = new PDO($dsn, $user, $pass, $options);
        $info['db_connection'] = 'SUCCESS';
        $info['db_server_info'] = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    } catch (Exception $e) {
        $info['db_connection'] = 'FAILED';
        $info['db_error'] = $e->getMessage();
    }
}

echo json_encode($info, JSON_PRETTY_PRINT);
