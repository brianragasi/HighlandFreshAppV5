<?php
/**
 * Highland Fresh - Fix Production Data Integrity
 * 
 * Fixes completed production runs that are missing batch records
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

echo "<!DOCTYPE html><html><head><title>Fix Production Data Integrity</title>";
echo "<style>
    body { font-family: 'Segoe UI', sans-serif; padding: 30px; background: #f8f9fa; }
    .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    h1 { color: #6f42c1; border-bottom: 2px solid #6f42c1; padding-bottom: 15px; }
    h2 { color: #495057; margin-top: 30px; }
    .issue { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 10px 0; }
    .fix { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 10px 0; }
    .error { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 10px 0; }
    table { width: 100%; border-collapse: collapse; margin: 20px 0; }
    th, td { padding: 10px; text-align: left; border-bottom: 1px solid #dee2e6; }
    th { background: #f8f9fa; font-weight: 600; }
    .badge { padding: 4px 10px; border-radius: 15px; font-size: 12px; }
    .badge-warning { background: #ffc107; color: #212529; }
    .badge-success { background: #28a745; color: white; }
    code { background: #e9ecef; padding: 2px 6px; border-radius: 3px; }
</style></head><body><div class='container'>";

try {
    $db = Database::getInstance()->getConnection();
    echo "<h1>🔧 Production Data Integrity Fix</h1>";
    echo "<p>This script finds and fixes production runs that are completed but have no batch records.</p>";

    // Find orphaned completed runs
    echo "<h2>Step 1: Identify Issues</h2>";
    
    $stmt = $db->query("
        SELECT pr.*, 
               mr.recipe_code, mr.product_name, mr.product_type,
               (SELECT COUNT(*) FROM production_batches pb WHERE pb.run_id = pr.id) as batch_count
        FROM production_runs pr 
        LEFT JOIN master_recipes mr ON pr.recipe_id = mr.id
        WHERE pr.status = 'completed' AND pr.actual_quantity > 0
        HAVING batch_count = 0
        ORDER BY pr.id
    ");
    $orphanedRuns = $stmt->fetchAll();
    
    if (count($orphanedRuns) == 0) {
        echo "<div class='fix'>✓ No orphaned production runs found. All completed runs have batch records.</div>";
    } else {
        echo "<div class='issue'>⚠️ Found " . count($orphanedRuns) . " completed runs without batch records</div>";
        
        echo "<table>";
        echo "<tr><th>Run Code</th><th>Recipe</th><th>Product Type</th><th>Quantity</th><th>Date</th><th>Status</th></tr>";
        foreach ($orphanedRuns as $run) {
            echo "<tr>";
            echo "<td><code>{$run['run_code']}</code></td>";
            echo "<td>{$run['product_name']}</td>";
            echo "<td>{$run['product_type']}</td>";
            echo "<td>" . number_format($run['actual_quantity']) . " units</td>";
            echo "<td>" . date('Y-m-d', strtotime($run['end_datetime'] ?? $run['created_at'])) . "</td>";
            echo "<td><span class='badge badge-warning'>Missing Batch</span></td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Fix the issues
        echo "<h2>Step 2: Create Missing Batch Records</h2>";
        
        $created = 0;
        $errors = [];
        
        foreach ($orphanedRuns as $run) {
            try {
                $db->beginTransaction();
                
                // Generate batch code
                $codeStmt = $db->query("SELECT MAX(CAST(SUBSTRING(batch_code, 7) AS UNSIGNED)) as max_num FROM production_batches WHERE batch_code LIKE 'BATCH-%'");
                $maxNum = $codeStmt->fetch()['max_num'] ?? 0;
                $batchCode = 'BATCH-' . str_pad($maxNum + 1, 5, '0', STR_PAD_LEFT);
                
                // Calculate expiry date (30 days from manufacturing)
                $mfgDate = date('Y-m-d', strtotime($run['end_datetime'] ?? $run['created_at']));
                $expiryDate = date('Y-m-d', strtotime($mfgDate . ' + 30 days'));
                
                // Insert batch record
                $stmt = $db->prepare("
                    INSERT INTO production_batches (
                        batch_code, 
                        recipe_id, 
                        run_id,
                        product_type, 
                        raw_milk_liters,
                        manufacturing_date, 
                        expiry_date, 
                        qc_status, 
                        actual_yield,
                        qc_notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, 'Auto-generated from completed run')
                ");
                $stmt->execute([
                    $batchCode,
                    $run['recipe_id'],
                    $run['id'],
                    $run['product_type'],
                    $run['milk_liters_used'],
                    $mfgDate,
                    $expiryDate,
                    $run['actual_quantity']
                ]);
                
                $db->commit();
                $created++;
                
                echo "<div class='fix'>✓ Created <code>{$batchCode}</code> for run <code>{$run['run_code']}</code> ({$run['product_name']})</div>";
                
            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = "Failed for {$run['run_code']}: " . $e->getMessage();
                echo "<div class='error'>✗ Failed to create batch for {$run['run_code']}: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
        
        // Summary
        echo "<h2>Step 3: Summary</h2>";
        echo "<table>";
        echo "<tr><td>Total orphaned runs found</td><td><strong>" . count($orphanedRuns) . "</strong></td></tr>";
        echo "<tr><td>Batches created successfully</td><td><strong class='text-success'>{$created}</strong></td></tr>";
        echo "<tr><td>Errors</td><td><strong class='text-danger'>" . count($errors) . "</strong></td></tr>";
        echo "</table>";
        
        if ($created > 0) {
            echo "<div class='fix'>🎉 Data integrity fix completed! {$created} batch records created.</div>";
        }
    }
    
    // Additional check: Production runs with NULL run_id in batches
    echo "<h2>Step 4: Check for Batches Without Run Links</h2>";
    
    $stmt = $db->query("
        SELECT pb.*, mr.product_name 
        FROM production_batches pb
        LEFT JOIN master_recipes mr ON pb.recipe_id = mr.id
        WHERE pb.run_id IS NULL
    ");
    $unlinkedBatches = $stmt->fetchAll();
    
    if (count($unlinkedBatches) == 0) {
        echo "<div class='fix'>✓ All batches are properly linked to production runs.</div>";
    } else {
        echo "<div class='issue'>⚠️ Found " . count($unlinkedBatches) . " batches without run links (may be historical data before run_id column was added)</div>";
    }

} catch (Exception $e) {
    echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
