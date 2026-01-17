<?php
/**
 * Check production_batches table structure
 */
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: text/plain');

try {
    $db = Database::getInstance()->getConnection();
    
    echo "=== PRODUCTION_BATCHES TABLE STRUCTURE ===\n\n";
    
    $stmt = $db->query("DESCRIBE production_batches");
    while ($row = $stmt->fetch()) {
        echo "{$row['Field']} ({$row['Type']})" . ($row['Null'] === 'NO' ? ' NOT NULL' : '') . "\n";
    }
    
    echo "\n=== SAMPLE DATA ===\n\n";
    $stmt = $db->query("SELECT * FROM production_batches LIMIT 2");
    $rows = $stmt->fetchAll();
    foreach ($rows as $row) {
        print_r($row);
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
