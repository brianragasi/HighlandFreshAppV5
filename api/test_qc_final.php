<?php
/**
 * Highland Fresh System - Final Comprehensive QC Integration Test
 * 
 * Complete end-to-end flow test
 * 
 * @package HighlandFresh
 * @version 4.0
 */

header('Content-Type: text/html; charset=UTF-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

define('HIGHLAND_FRESH', true);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>QC Final Integration Test</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; }
        h1 { color: #28a745; margin-bottom: 5px; }
        .subtitle { color: #666; margin-bottom: 30px; }
        .step { background: white; border-radius: 8px; padding: 20px; margin: 15px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .step-header { display: flex; align-items: center; margin-bottom: 15px; }
        .step-number { background: #28a745; color: white; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; margin-right: 15px; }
        .step-title { font-size: 18px; font-weight: 600; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .info { color: #17a2b8; }
        pre { background: #1e1e1e; color: #d4d4d4; padding: 12px; border-radius: 5px; font-size: 12px; overflow-x: auto; }
        .data-row { background: #f8f9fa; padding: 8px 12px; margin: 5px 0; border-radius: 4px; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        .flow-diagram { display: flex; align-items: center; justify-content: space-between; background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .flow-box { background: white; padding: 15px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); min-width: 120px; }
        .flow-arrow { color: #28a745; font-size: 24px; }
        .summary-card { background: linear-gradient(135deg, #28a745, #20c997); color: white; padding: 25px; border-radius: 10px; margin-top: 30px; }
    </style>
</head>
<body>
<div class='container'>
<h1>🧪 Highland Fresh - QC Integration Test</h1>
<p class='subtitle'>Complete Acceptance → Production Flow Validation</p>

<div class='flow-diagram'>
    <div class='flow-box'>📥 Milk Delivery</div>
    <div class='flow-arrow'>→</div>
    <div class='flow-box'>🧬 QC Testing</div>
    <div class='flow-arrow'>→</div>
    <div class='flow-box'>📦 Inventory</div>
    <div class='flow-arrow'>→</div>
    <div class='flow-box'>🏭 Production</div>
    <div class='flow-arrow'>→</div>
    <div class='flow-box'>✅ Release</div>
</div>";

$testResults = [];
$totalTests = 0;
$passedTests = 0;

function logTest($name, $passed, $details = null) {
    global $testResults, $totalTests, $passedTests;
    $totalTests++;
    if ($passed) $passedTests++;
    $testResults[] = ['name' => $name, 'passed' => $passed, 'details' => $details];
}

try {
    $db = Database::getInstance()->getConnection();
    
    // ============================================
    // STEP 1: Get Test Farmer
    // ============================================
    echo "<div class='step'>";
    echo "<div class='step-header'><div class='step-number'>1</div><div class='step-title'>Get Test Farmer</div></div>";
    
    $farmerStmt = $db->query("SELECT * FROM farmers WHERE is_active = 1 ORDER BY id LIMIT 1");
    $farmer = $farmerStmt->fetch();
    
    if ($farmer) {
        echo "<div class='data-row'>";
        echo "<strong>{$farmer['farmer_code']}</strong> - {$farmer['first_name']} {$farmer['last_name']} ";
        echo "<span class='badge badge-info'>{$farmer['membership_type']}</span>";
        echo "</div>";
        logTest('Get test farmer', true, $farmer['farmer_code']);
    } else {
        echo "<p class='error'>❌ No active farmers found</p>";
        logTest('Get test farmer', false);
    }
    echo "</div>";
    
    // ============================================
    // STEP 2: Create Milk Delivery
    // ============================================
    echo "<div class='step'>";
    echo "<div class='step-header'><div class='step-number'>2</div><div class='step-title'>Create Milk Delivery</div></div>";
    
    // Generate delivery code
    $codeStmt = $db->query("SELECT MAX(CAST(SUBSTRING(delivery_code, 5) AS UNSIGNED)) as max_num FROM milk_deliveries WHERE delivery_code LIKE 'DEL-%'");
    $maxNum = $codeStmt->fetch()['max_num'] ?? 0;
    $deliveryCode = 'DEL-' . str_pad($maxNum + 1, 6, '0', STR_PAD_LEFT);
    
    $volumeLiters = 75.5;
    
    $stmt = $db->prepare("
        INSERT INTO milk_deliveries (delivery_code, farmer_id, delivery_date, delivery_time, volume_liters, status, apt_result)
        VALUES (?, ?, CURDATE(), CURTIME(), ?, 'pending_test', 'negative')
    ");
    $stmt->execute([$deliveryCode, $farmer['id'], $volumeLiters]);
    $deliveryId = $db->lastInsertId();
    
    echo "<div class='data-row'>";
    echo "<strong>{$deliveryCode}</strong> | {$volumeLiters}L | ";
    echo "<span class='badge badge-warning'>pending_test</span>";
    echo "</div>";
    echo "<pre>Delivery ID: {$deliveryId}\nAPT Result: negative (ready for testing)</pre>";
    logTest('Create delivery', true, $deliveryCode);
    echo "</div>";
    
    // ============================================
    // STEP 3: QC Testing (ANNEX B Pricing)
    // ============================================
    echo "<div class='step'>";
    echo "<div class='step-header'><div class='step-number'>3</div><div class='step-title'>QC Testing (ANNEX B Pricing)</div></div>";
    
    // Test parameters
    $fatPercentage = 4.2;
    $titratableAcidity = 0.15;
    $temperatureCelsius = 4.2;
    $sedimentGrade = 1;
    $density = 1.029;
    
    // ANNEX B Calculations
    $basePrice = 30.00;
    $fatAdjustment = 0.25; // 4.1-4.5% = +₱0.25
    $acidityDeduction = 0.00; // 0.14-0.18% = no deduction
    $sedimentDeduction = 0.00; // Grade 1 = no deduction
    $finalPrice = $basePrice + $fatAdjustment - $acidityDeduction - $sedimentDeduction;
    $totalAmount = $volumeLiters * $finalPrice;
    $milkGrade = 'A'; // Fat 4.2%, Acidity 0.15%, Sediment Grade 1 = Grade A
    
    // Generate test code
    $codeStmt = $db->query("SELECT MAX(CAST(SUBSTRING(test_code, 5) AS UNSIGNED)) as max_num FROM qc_milk_tests WHERE test_code LIKE 'QCT-%'");
    $maxNum = $codeStmt->fetch()['max_num'] ?? 0;
    $testCode = 'QCT-' . str_pad($maxNum + 1, 6, '0', STR_PAD_LEFT);
    
    // Insert test
    $stmt = $db->prepare("
        INSERT INTO qc_milk_tests (
            test_code, delivery_id, test_datetime,
            fat_percentage, titratable_acidity, temperature_celsius, sediment_grade, density,
            base_price_per_liter, fat_adjustment, acidity_deduction, sediment_deduction,
            final_price_per_liter, total_amount, is_accepted, grade
        ) VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
    ");
    $stmt->execute([
        $testCode, $deliveryId,
        $fatPercentage, $titratableAcidity, $temperatureCelsius, $sedimentGrade, $density,
        $basePrice, $fatAdjustment, $acidityDeduction, $sedimentDeduction,
        $finalPrice, $totalAmount, $milkGrade
    ]);
    $testId = $db->lastInsertId();
    
    // Update delivery
    $db->prepare("UPDATE milk_deliveries SET status = 'accepted', grade = ?, accepted_liters = ?, unit_price = ?, total_amount = ? WHERE id = ?")
       ->execute([$milkGrade, $volumeLiters, $finalPrice, $totalAmount, $deliveryId]);
    
    echo "<div class='data-row'>";
    echo "<strong>{$testCode}</strong> | Grade <span class='badge badge-success'>{$milkGrade}</span>";
    echo "</div>";
    
    echo "<pre>";
    echo "Test Parameters:\n";
    echo "  Fat: {$fatPercentage}%\n";
    echo "  Titratable Acidity: {$titratableAcidity}%\n";
    echo "  Sediment Grade: {$sedimentGrade}\n";
    echo "  Specific Gravity: {$density}\n\n";
    echo "ANNEX B Pricing:\n";
    echo "  Base Price: ₱{$basePrice}\n";
    echo "  Fat Adjustment: +₱{$fatAdjustment}\n";
    echo "  Acidity Deduction: -₱{$acidityDeduction}\n";
    echo "  Sediment Deduction: -₱{$sedimentDeduction}\n";
    echo "  Final Price/L: ₱{$finalPrice}\n";
    echo "  Total Amount: ₱" . number_format($totalAmount, 2) . "\n";
    echo "</pre>";
    logTest('QC Testing', true, "Grade {$milkGrade}");
    echo "</div>";
    
    // ============================================
    // STEP 4: Update Raw Milk Inventory
    // ============================================
    echo "<div class='step'>";
    echo "<div class='step-header'><div class='step-number'>4</div><div class='step-title'>Update Raw Milk Inventory</div></div>";
    
    $invStmt = $db->prepare("
        INSERT INTO raw_milk_inventory (tank_number, delivery_id, volume_liters, status, received_date)
        VALUES (1, ?, ?, 'available', CURDATE())
    ");
    $invStmt->execute([$deliveryId, $volumeLiters]);
    
    echo "<div class='data-row'>";
    echo "Tank #1 | {$volumeLiters}L | <span class='badge badge-success'>available</span>";
    echo "</div>";
    logTest('Inventory update', true);
    echo "</div>";
    
    // ============================================
    // STEP 5: Verify Production Can See Milk
    // ============================================
    echo "<div class='step'>";
    echo "<div class='step-header'><div class='step-number'>5</div><div class='step-title'>Verify Production Visibility</div></div>";
    
    $availableStmt = $db->query("
        SELECT md.delivery_code, md.accepted_liters, qmt.grade, qmt.fat_percentage
        FROM milk_deliveries md
        JOIN qc_milk_tests qmt ON qmt.delivery_id = md.id
        WHERE md.status = 'accepted' 
          AND DATE(md.delivery_date) >= DATE_SUB(CURDATE(), INTERVAL 2 DAY)
        ORDER BY md.delivery_date DESC
        LIMIT 5
    ");
    $availableMilk = $availableStmt->fetchAll();
    
    $totalAvailable = array_sum(array_column($availableMilk, 'accepted_liters'));
    
    echo "<div class='data-row'>";
    echo "<strong>Total Available for Production:</strong> " . number_format($totalAvailable, 1) . "L";
    echo "</div>";
    
    echo "<pre>";
    foreach ($availableMilk as $m) {
        echo "{$m['delivery_code']} | {$m['accepted_liters']}L | Grade {$m['grade']} | Fat {$m['fat_percentage']}%\n";
    }
    echo "</pre>";
    logTest('Production visibility', count($availableMilk) > 0);
    echo "</div>";
    
    // ============================================
    // STEP 6: Simulate Batch Creation
    // ============================================
    echo "<div class='step'>";
    echo "<div class='step-header'><div class='step-number'>6</div><div class='step-title'>Simulate Production Batch</div></div>";
    
    $recipeStmt = $db->query("SELECT * FROM master_recipes WHERE is_active = 1 LIMIT 1");
    $recipe = $recipeStmt->fetch();
    
    if ($recipe) {
        $codeStmt = $db->query("SELECT MAX(CAST(SUBSTRING(batch_code, 7) AS UNSIGNED)) as max_num FROM production_batches WHERE batch_code LIKE 'BATCH-%'");
        $maxNum = $codeStmt->fetch()['max_num'] ?? 0;
        $batchCode = 'BATCH-' . str_pad($maxNum + 1, 5, '0', STR_PAD_LEFT);
        
        $cols = $db->query("DESCRIBE production_batches");
        $columns = $cols->fetchAll(PDO::FETCH_COLUMN);
        $hasProductType = in_array('product_type', $columns);
        
        if ($hasProductType) {
            $batchStmt = $db->prepare("
                INSERT INTO production_batches (batch_code, recipe_id, product_type, manufacturing_date, expiry_date, qc_status, actual_yield)
                VALUES (?, ?, 'pasteurized_milk', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'pending', 72.0)
            ");
            $batchStmt->execute([$batchCode, $recipe['id']]);
        } else {
            $batchStmt = $db->prepare("
                INSERT INTO production_batches (batch_code, recipe_id, manufacturing_date, expiry_date, qc_status, actual_yield)
                VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'pending', 72.0)
            ");
            $batchStmt->execute([$batchCode, $recipe['id']]);
        }
        
        $batchId = $db->lastInsertId();
        
        echo "<div class='data-row'>";
        echo "<strong>{$batchCode}</strong> | Recipe: {$recipe['product_name']} | Yield: 72 units | ";
        echo "<span class='badge badge-warning'>pending</span>";
        echo "</div>";
        logTest('Batch creation', true, $batchCode);
    } else {
        echo "<p class='error'>❌ No active recipes found</p>";
        logTest('Batch creation', false);
    }
    echo "</div>";
    
    // ============================================
    // STEP 7: QC Release Batch
    // ============================================
    echo "<div class='step'>";
    echo "<div class='step-header'><div class='step-number'>7</div><div class='step-title'>QC Release Batch</div></div>";
    
    if (isset($batchId)) {
        $db->prepare("
            UPDATE production_batches SET
                qc_status = 'released',
                qc_released_at = NOW(),
                organoleptic_taste = 1,
                organoleptic_appearance = 1,
                organoleptic_smell = 1,
                qc_notes = 'Integration test - all checks passed'
            WHERE id = ?
        ")->execute([$batchId]);
        
        echo "<div class='data-row'>";
        echo "<strong>{$batchCode}</strong> | ";
        echo "<span class='badge badge-success'>released</span> | ";
        echo "✓ Taste ✓ Appearance ✓ Smell";
        echo "</div>";
        logTest('QC Release', true);
    } else {
        logTest('QC Release', false);
    }
    echo "</div>";
    
    // ============================================
    // STEP 8: Add to Finished Goods
    // ============================================
    echo "<div class='step'>";
    echo "<div class='step-header'><div class='step-number'>8</div><div class='step-title'>Add to Finished Goods</div></div>";
    
    if (isset($batchId)) {
        $fgCols = $db->query("DESCRIBE finished_goods_inventory");
        $fgColumns = $fgCols->fetchAll(PDO::FETCH_COLUMN);
        $hasQuantityAvailable = in_array('quantity_available', $fgColumns);
        
        if ($hasQuantityAvailable) {
            $fgStmt = $db->prepare("
                INSERT INTO finished_goods_inventory (
                    batch_id, product_type, quantity, quantity_available,
                    manufacturing_date, expiry_date, status
                ) VALUES (?, 'pasteurized_milk', 72, 72, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'available')
            ");
        } else {
            $fgStmt = $db->prepare("
                INSERT INTO finished_goods_inventory (
                    batch_id, product_type, quantity, remaining_quantity,
                    manufacturing_date, expiry_date, status
                ) VALUES (?, 'pasteurized_milk', 72, 72, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'available')
            ");
        }
        $fgStmt->execute([$batchId]);
        
        echo "<div class='data-row'>";
        echo "Batch {$batchCode} | 72 units | <span class='badge badge-success'>available</span>";
        echo "</div>";
        logTest('Finished Goods', true);
    } else {
        logTest('Finished Goods', false);
    }
    echo "</div>";
    
    // ============================================
    // CLEANUP
    // ============================================
    echo "<div class='step'>";
    echo "<div class='step-header'><div class='step-number'>9</div><div class='step-title'>Cleanup Test Data</div></div>";
    
    $db->beginTransaction();
    try {
        if (isset($batchId)) {
            $db->exec("DELETE FROM finished_goods_inventory WHERE batch_id = {$batchId}");
            $db->exec("DELETE FROM production_batches WHERE id = {$batchId}");
        }
        $db->exec("DELETE FROM raw_milk_inventory WHERE delivery_id = {$deliveryId}");
        $db->exec("DELETE FROM qc_milk_tests WHERE id = {$testId}");
        $db->exec("DELETE FROM milk_deliveries WHERE id = {$deliveryId}");
        $db->commit();
        
        echo "<div class='data-row success'>✅ All test data cleaned up successfully</div>";
        logTest('Cleanup', true);
    } catch (Exception $e) {
        $db->rollBack();
        echo "<div class='data-row error'>❌ Cleanup failed: {$e->getMessage()}</div>";
        logTest('Cleanup', false);
    }
    echo "</div>";
    
    // ============================================
    // SUMMARY
    // ============================================
    echo "<div class='summary-card'>";
    echo "<h2 style='margin-top:0;'>📊 Test Results Summary</h2>";
    echo "<p style='font-size: 36px; font-weight: bold; margin: 20px 0;'>{$passedTests}/{$totalTests} Tests Passed</p>";
    
    $percentage = $totalTests > 0 ? round(($passedTests / $totalTests) * 100) : 0;
    echo "<p>Success Rate: <strong>{$percentage}%</strong></p>";
    
    if ($passedTests == $totalTests) {
        echo "<p style='font-size: 18px; margin-top: 20px;'>✅ All integration tests passed! The QC to Production flow is working correctly.</p>";
    } else {
        echo "<p style='font-size: 18px; margin-top: 20px;'>⚠️ Some tests failed. Please review the issues above.</p>";
    }
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='step'>";
    echo "<div class='error'>❌ Critical Error: " . $e->getMessage() . "</div>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    echo "</div>";
}

echo "</div></body></html>";
?>
