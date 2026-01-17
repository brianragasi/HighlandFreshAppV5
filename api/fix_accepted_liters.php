<?php
/**
 * Fix accepted deliveries with 0 accepted_liters
 */
define('HIGHLAND_FRESH', true);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$db = Database::getInstance()->getConnection();

// Fix deliveries
$stmt = $db->prepare("
    UPDATE milk_deliveries 
    SET accepted_liters = volume_liters 
    WHERE status = 'accepted' 
      AND (accepted_liters IS NULL OR accepted_liters = 0)
");
$stmt->execute();
echo "Fixed " . $stmt->rowCount() . " deliveries with 0 accepted_liters\n";

// Show current status
echo "\n=== CURRENT ACCEPTED DELIVERIES ===\n";
$stmt = $db->query("
    SELECT md.id, md.delivery_code, md.status, md.volume_liters, md.accepted_liters, md.delivery_date
    FROM milk_deliveries md
    WHERE md.status = 'accepted'
    ORDER BY md.delivery_date DESC
    LIMIT 10
");
while ($r = $stmt->fetch()) {
    echo "{$r['delivery_code']} | {$r['volume_liters']}L | accepted: {$r['accepted_liters']}L | {$r['delivery_date']}\n";
}
