<?php
/**
 * Debug endpoint - diagnose auth issues on InfinityFree
 * DELETE THIS FILE after debugging is complete
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, X-Auth-Token, Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$results = [];

// 1. What headers does PHP actually see?
$results['all_server_vars_auth'] = [];
foreach ($_SERVER as $key => $value) {
    if (stripos($key, 'auth') !== false || stripos($key, 'token') !== false || $key === 'HTTP_AUTHORIZATION' || $key === 'REDIRECT_HTTP_AUTHORIZATION') {
        $results['all_server_vars_auth'][$key] = $value;
    }
}

// 2. getallheaders() available?
$results['getallheaders_available'] = function_exists('getallheaders');
if (function_exists('getallheaders')) {
    $allHeaders = getallheaders();
    $results['getallheaders_auth_related'] = [];
    foreach ($allHeaders as $name => $value) {
        if (stripos($name, 'auth') !== false || stripos($name, 'token') !== false) {
            $results['getallheaders_auth_related'][$name] = $value;
        }
    }
}

// 3. Cookie check
$results['cookies'] = $_COOKIE;

// 4. HTTPS detection
$results['https_detection'] = [
    'HTTPS_var' => $_SERVER['HTTPS'] ?? 'NOT SET',
    'SERVER_PORT' => $_SERVER['SERVER_PORT'] ?? 'NOT SET',
    'HTTP_X_FORWARDED_PROTO' => $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'NOT SET',
    'HTTP_X_FORWARDED_SSL' => $_SERVER['HTTP_X_FORWARDED_SSL'] ?? 'NOT SET',
    'REQUEST_SCHEME' => $_SERVER['REQUEST_SCHEME'] ?? 'NOT SET',
];

// 5. MySQL timezone check
try {
    define('HIGHLAND_FRESH', true);
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/database.php';

    $db = Database::getInstance()->getConnection();

    $tz = $db->query("SELECT @@session.time_zone AS session_tz, @@global.time_zone AS global_tz, NOW() AS mysql_now, UTC_TIMESTAMP() AS mysql_utc")->fetch(PDO::FETCH_ASSOC);
    $results['mysql_timezone'] = $tz;
    $results['php_time'] = date('Y-m-d H:i:s');
    $results['php_timezone'] = date_default_timezone_get();
    $results['php_unix_time'] = time();

    // 6. Check if auth_sessions table exists and has data
    try {
        $sessCount = $db->query("SELECT COUNT(*) as cnt FROM auth_sessions")->fetch(PDO::FETCH_ASSOC);
        $results['auth_sessions_count'] = $sessCount['cnt'];

        $latestSess = $db->query("SELECT session_id, user_id, issued_at, last_activity, expires_at, revoked_at, revoked_reason FROM auth_sessions ORDER BY id DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
        $results['latest_sessions'] = $latestSess;
    } catch (Exception $e) {
        $results['auth_sessions_error'] = $e->getMessage();
    }

} catch (Exception $e) {
    $results['db_error'] = $e->getMessage();
}

// 7. Check if dashboard files exist
$results['dashboard_files'] = [];
$dashboards = ['admin', 'production', 'purchasing', 'qc', 'warehouse/raw', 'maintenance', 'sales', 'finance', 'pos'];
foreach ($dashboards as $d) {
    $path = dirname(__DIR__, 2) . '/html/' . $d . '/dashboard.html';
    $results['dashboard_files'][$d] = file_exists($path);
}

// 8. Document root info
$results['document_root'] = $_SERVER['DOCUMENT_ROOT'] ?? 'NOT SET';
$results['script_filename'] = $_SERVER['SCRIPT_FILENAME'] ?? 'NOT SET';
$results['request_uri'] = $_SERVER['REQUEST_URI'] ?? 'NOT SET';

echo json_encode($results, JSON_PRETTY_PRINT);
