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
echo "putenv() enabled: " . (false === getenv('PATH') ? 'unknown (no PATH set)' : (ini_get('safe_mode') ? 'NO (safe_mode)' : 'yes')) . "\n";
// Probe whether putenv actually works by setting a test value
$probe = 'highland_test_' . random_int(1000, 9999);
putenv("TEST_PROBE=$probe");
$putenvWorks = (getenv('TEST_PROBE') === $probe);
putenv('TEST_PROBE'); // unset
echo "putenv() round-trip: " . ($putenvWorks ? 'WORKS' : 'BROKEN (host disables putenv)') . "\n";
echo "DB_HOST     = " . DB_HOST . "\n";
echo "DB_PORT     = " . DB_PORT . "\n";
echo "DB_NAME     = " . DB_NAME . "\n";
echo "DB_USER     = " . DB_USER . "\n";
echo "DB_PASS set = " . (DB_PASS !== '' ? 'yes' : 'NO (empty!)') . "\n";
echo "DB_SSL_CERT = " . (defined('DB_SSL_CERT') && DB_SSL_CERT ? DB_SSL_CERT : 'null') . "\n";
echo "IS_AZURE    = " . (defined('IS_AZURE') ? var_export(IS_AZURE, true) : 'undefined') . "\n\n";

echo "=== .env file status (all candidate locations) ===\n";
$searchDirs = [
    dirname(__DIR__, 2),
    dirname(dirname(__DIR__, 2)),
    __DIR__ . '/..',
];
foreach (array_unique($searchDirs) as $dir) {
    $envFile = $dir . '/.env';
    $locFile = $envFile . '.local';
    $envStatus = is_readable($envFile) ? 'READABLE' : (file_exists($envFile) ? 'NOT READABLE' : 'missing');
    $locStatus = is_readable($locFile) ? 'READABLE' : (file_exists($locFile) ? 'NOT READABLE' : 'missing');
    echo sprintf("  %-60s  .env=%-12s  .env.local=%s\n", $envFile, $envStatus, $locStatus);
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
