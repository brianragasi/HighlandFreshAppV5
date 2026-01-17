<?php
/**
 * Check milk status
 */
define('HIGHLAND_FRESH', true);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$db = Database::getInstance()->getConnection();

echo "=== MILK DELIVERIES ===\n\n";
$stmt = $db->query("SELECT id, delivery_code, status, volume_liters, accepted_liters, delivery_date FROM milk_deliveries ORDER BY id DESC LIMIT 10");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$r['id']} | {$r['delivery_code']} | STATUS: {$r['status']} | {$r['volume_liters']}L | accepted: {$r['accepted_liters']}L | {$r['delivery_date']}\n";
}

echo "\n=== QC MILK TESTS ===\n\n";
$stmt = $db->query("SELECT * FROM qc_milk_tests ORDER BY id DESC LIMIT 10");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Count: " . count($rows) . "\n";
foreach ($rows as $r) {
    echo "Test ID: {$r['id']} | Delivery: {$r['delivery_id']} | Accepted: " . ($r['is_accepted'] ? 'YES' : 'NO') . " | Fat: {$r['fat_percentage']}%\n";
}

echo "\n=== AVAILABLE MILK QUERY (what production sees) ===\n\n";
$stmt = $db->query("
    SELECT 
        md.id,
        md.delivery_code,
        md.status,
        CASE WHEN md.accepted_liters > 0 THEN md.accepted_liters ELSE md.volume_liters END as available_liters,
        md.delivery_date,
        qmt.id as test_id
    FROM milk_deliveries md
    JOIN qc_milk_tests qmt ON qmt.delivery_id = md.id
    WHERE md.status = 'accepted'
      AND DATE(md.delivery_date) >= DATE_SUB(CURDATE(), INTERVAL 2 DAY)
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Available for production: " . count($rows) . " deliveries\n";
foreach ($rows as $r) {
    echo "  - {$r['delivery_code']} | {$r['available_liters']}L | {$r['delivery_date']}\n";
}

echo "\n=== WHY MIGHT MILK NOT SHOW? ===\n";
echo "Checking deliveries with 'accepted' status but no QC test...\n";
$stmt = $db->query("
    SELECT md.id, md.delivery_code, md.status, md.accepted_liters
    FROM milk_deliveries md
    LEFT JOIN qc_milk_tests qmt ON qmt.delivery_id = md.id
    WHERE md.status = 'accepted' AND qmt.id IS NULL
");
$rows = $stmt->fetchAll();
echo "Found " . count($rows) . " 'accepted' deliveries WITHOUT QC tests\n";
foreach ($rows as $r) {
    echo "  - {$r['delivery_code']} (status={$r['status']}, {$r['accepted_liters']}L) - MISSING QC TEST!\n";
}
