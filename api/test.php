<?php
// Debug endpoint to verify Azure deployment
header('Content-Type: application/json');

$info = [
    'status' => 'ok',
    'message' => 'API is working',
    'php_version' => phpversion(),
    'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'unknown',
    'script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? 'unknown',
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
    'files_exist' => [
        'bootstrap' => file_exists(__DIR__ . '/bootstrap.php'),
        'config' => file_exists(__DIR__ . '/config/config.php'),
        'database' => file_exists(__DIR__ . '/config/database.php'),
        'qc_dashboard' => file_exists(__DIR__ . '/qc/dashboard.php'),
        'auth_login' => file_exists(__DIR__ . '/auth/login.php'),
    ],
    'timestamp' => date('Y-m-d H:i:s')
];

echo json_encode($info, JSON_PRETTY_PRINT);
