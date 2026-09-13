<?php
// Temporary deployment probe. It reports only a broad connection category and
// is removed immediately after the live database issue is identified.
header('Content-Type: application/json; charset=UTF-8');
if (!hash_equals('hf-db-check-20260913', (string) ($_GET['key'] ?? ''))) {
    http_response_code(404);
    echo json_encode(['success' => false]);
    exit;
}

define('HIGHLAND_FRESH', true);

try {
    require dirname(__DIR__) . '/config/config.php';
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );
    $db = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    $tableCount = (int) $db->query(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE()"
    )->fetchColumn();
    echo json_encode([
        'success' => true,
        'category' => 'connected',
        'database' => DB_NAME,
        'tables' => $tableCount,
    ]);
} catch (Throwable $error) {
    $driverCode = (int) ($error instanceof PDOException ? ($error->errorInfo[1] ?? 0) : 0);
    $category = match ($driverCode) {
        1045 => 'database_login_rejected',
        1049 => 'database_name_not_found',
        2002, 2003 => 'database_server_unreachable',
        default => 'configuration_or_query_failed',
    };
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'category' => $category,
        'driver_code' => $driverCode,
    ]);
}
