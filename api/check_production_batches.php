<?php
/**
 * Check production_batches table structure
 */
define('HIGHLAND_FRESH', true);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$db = Database::getInstance()->getConnection();

echo "=== production_batches Table Structure ===\n\n";
$cols = $db->query('DESCRIBE production_batches');
foreach($cols as $c) { 
    echo $c['Field'] . ' - ' . $c['Type'] . "\n"; 
}

echo "\n\n=== Checking if run_id exists ===\n";
$columns = $cols->fetchAll(PDO::FETCH_COLUMN, 0);
// Re-query since we consumed the results
$cols = $db->query("SHOW COLUMNS FROM production_batches LIKE 'run_id'");
if ($cols->rowCount() > 0) {
    echo "run_id column EXISTS\n";
} else {
    echo "run_id column MISSING - Adding it now...\n";
    try {
        $db->exec("ALTER TABLE production_batches ADD COLUMN run_id INT(11) NULL AFTER recipe_id");
        $db->exec("ALTER TABLE production_batches ADD INDEX idx_run_id (run_id)");
        echo "✓ run_id column added successfully!\n";
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
