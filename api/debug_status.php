<?php
/**
 * Debug status flow
 */
define('HIGHLAND_FRESH', true);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$db = Database::getInstance()->getConnection();

echo "=== QC_MILK_TESTS GRADES ===\n";
$stmt = $db->query("SELECT id, delivery_id, grade, is_accepted, titratable_acidity, rejection_reason FROM qc_milk_tests ORDER BY id DESC LIMIT 10");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$r['id']} | Delivery: {$r['delivery_id']} | Grade: '{$r['grade']}' | Accepted: {$r['is_accepted']} | Acidity: {$r['titratable_acidity']}% | Reason: {$r['rejection_reason']}\n";
}

echo "\n=== MILK_DELIVERIES GRADE + STATUS ===\n";
$stmt = $db->query("SELECT id, delivery_code, status, grade, accepted_liters FROM milk_deliveries ORDER BY id DESC LIMIT 10");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$r['id']} | {$r['delivery_code']} | status='{$r['status']}' | grade='{$r['grade']}' | accepted={$r['accepted_liters']}\n";
}

echo "\n=== DASHBOARD TODAY QUERY ===\n";
$today = date('Y-m-d');
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending_test' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'accepted' THEN volume_liters ELSE 0 END) as accepted_liters,
        SUM(CASE WHEN status = 'rejected' THEN volume_liters ELSE 0 END) as rejected_liters
    FROM milk_deliveries
    WHERE delivery_date = ?
");
$stmt->execute([$today]);
$r = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Today ({$today}): Total={$r['total']}, Pending={$r['pending']}, Accepted={$r['accepted']}, Rejected={$r['rejected']}\n";
echo "Accepted Liters: {$r['accepted_liters']}, Rejected Liters: {$r['rejected_liters']}\n";
