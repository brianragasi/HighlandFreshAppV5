<?php
/**
 * Temporary, secret-authenticated production database diagnostic.
 * Remove after the first live deployment is verified.
 */

define('HIGHLAND_FRESH', true);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

try {
    require_once __DIR__ . '/config/config.php';
} catch (Throwable $error) {
    http_response_code(503);
    echo json_encode([
        'ok' => false,
        'stage' => 'configuration',
        'category' => 'production_configuration_rejected',
    ]);
    exit;
}

$provided = trim((string) ($_SERVER['HTTP_X_DEPLOYMENT_TOKEN'] ?? ''));
$expected = hash_hmac('sha256', 'deployment-diagnostics-v1', AUDIT_LOG_SECRET);
if ($provided === '' || !hash_equals($expected, $provided)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'stage' => 'authorization']);
    exit;
}

function diagnosticCategory(Throwable $error): string
{
    $driverCode = null;
    if ($error instanceof PDOException && is_array($error->errorInfo ?? null)) {
        $driverCode = (int) ($error->errorInfo[1] ?? 0);
    }
    if ($driverCode === 1045) return 'database_credentials_rejected';
    if ($driverCode === 1049) return 'database_not_found';
    if (in_array($driverCode, [2002, 2003, 2005], true)) return 'database_host_unreachable';
    if ($driverCode === 1146) return 'required_table_missing';
    if ($driverCode === 1054) return 'required_column_missing';
    return 'database_query_failed';
}

try {
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    $pdo->query('SELECT 1')->fetchColumn();
    $userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $requiredColumns = (int) $pdo->query("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME IN ('id', 'username', 'password', 'role', 'is_active')
    ")->fetchColumn();

    echo json_encode([
        'ok' => true,
        'stage' => 'database',
        'connection' => true,
        'users_table' => true,
        'users_present' => $userCount > 0,
        'required_user_columns' => $requiredColumns === 5,
        'driver' => $pdo->getAttribute(PDO::ATTR_DRIVER_NAME),
    ]);
} catch (Throwable $error) {
    http_response_code(503);
    echo json_encode([
        'ok' => false,
        'stage' => 'database',
        'category' => diagnosticCategory($error),
    ]);
}
