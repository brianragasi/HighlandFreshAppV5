<?php
/**
 * Debug and fix QC integration issues
 */
define('HIGHLAND_FRESH', true);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$db = Database::getInstance()->getConnection();

echo "=== CHECKING raw_milk_inventory STRUCTURE ===\n";
$cols = $db->query('DESCRIBE raw_milk_inventory');
foreach ($cols as $col) {
    echo "  " . $col['Field'] . " (" . $col['Type'] . ")\n";
}

echo "\n=== DELIVERIES WITH STATUS 'accepted' BUT NO QC TEST ===\n";
$stmt = $db->query("
    SELECT md.id, md.delivery_code, md.status, md.volume_liters, md.delivery_date
    FROM milk_deliveries md 
    LEFT JOIN qc_milk_tests qmt ON qmt.delivery_id = md.id 
    WHERE md.status = 'accepted' AND qmt.id IS NULL
");
$rows = $stmt->fetchAll();
echo "Found " . count($rows) . " orphaned accepted deliveries:\n";
foreach ($rows as $r) {
    echo "  - ID: {$r['id']} | {$r['delivery_code']} | {$r['volume_liters']}L | {$r['delivery_date']}\n";
}
