<?php
/**
 * TEMPORARY DEBUG ENDPOINT — DELETE AFTER DEBUGGING
 * Shows what DB config PHP actually loaded, and the real PDO error
 * (NOT the generic "Database connection failed" wrapper).
 *
 * Safe: no passwords shown.
 */
define('HIGHLAND_FRESH', true);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

header('Content-Type: text/plain; charset=UTF-8');

echo "=== DB config that PHP resolved ===\n";
echo "DB_HOST     = " . DB_HOST . "\n";
echo "DB_PORT     = " . DB_PORT . "\n";
echo "DB_NAME     = " . DB_NAME . "\n";
echo "DB_USER     = " . DB_USER . "\n";
echo "DB_PASS set = " . (DB_PASS !== '' ? 'yes' : 'NO (empty!)') . "\n";
echo "DB_SSL_CERT = " . (defined('DB_SSL_CERT') && DB_SSL_CERT ? DB_SSL_CERT : 'null') . "\n";
echo "IS_AZURE    = " . (defined('IS_AZURE') ? var_export(IS_AZURE, true) : 'undefined') . "\n\n";

echo "=== .env file status ===\n";
$envPath = dirname(__DIR__, 2) . '/.env';
echo "Path checked: $envPath\n";
echo "Exists:       " . (is_readable($envPath) ? 'YES' : 'NO') . "\n";
if (is_readable($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, 'PASSWORD') !== false) {
            echo "  " . preg_replace('/=.*/', '=***REDACTED***', $line) . "\n";
        } else {
            echo "  $line\n";
        }
    }
}
echo "\n";

echo "=== Attempting connection ===\n";
try {
    $db = Database::getInstance()->getConnection();
    echo "SUCCESS — connection works\n";
    $row = $db->query('SELECT VERSION() AS v, DATABASE() AS d')->fetch();
    echo "MySQL version: " . $row['v'] . "\n";
    echo "Current DB:    " . $row['d'] . "\n";
} catch (Exception $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
    $prev = $e->getPrevious();
    if ($prev) {
        echo "Root cause: " . $prev->getMessage() . "\n";
    }
}
