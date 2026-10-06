<?php
/** Run without --apply for a rollback-backed preview; --apply saves changes. */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
define('HIGHLAND_FRESH', true);
require dirname(__DIR__) . '/api/config/config.php';
require dirname(__DIR__) . '/api/config/database.php';
require __DIR__ . '/defense_cleanup_lib.php';

try {
    $result = hfCleanDefenseCatalog(
        Database::getInstance()->getConnection(),
        in_array('--apply', $argv ?? [], true)
    );
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Defense cleanup stopped: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
