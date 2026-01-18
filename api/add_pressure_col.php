<?php
define('HIGHLAND_FRESH', true);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$db = Database::getInstance()->getConnection();

try {
    $db->exec("ALTER TABLE production_ccp_logs ADD COLUMN pressure DECIMAL(6,2) NULL AFTER temperature");
    echo "✓ Pressure column added to production_ccp_logs\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "→ Pressure column already exists\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
