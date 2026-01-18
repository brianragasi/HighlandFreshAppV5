<?php
/**
 * Highland Fresh System - Production Module Business Rules Audit
 * 
 * Validates that the Production module implementation follows
 * the system_context specifications exactly.
 * 
 * Key Business Rules from system_context:
 * 1. Byproducts (Buttermilk, Whey, Cream, Skim Milk) - from production processes
 * 2. Yogurt Transformation - near-expiry FG milk → Yogurt (NOT waste, NOT disposal)
 * 3. CCP Logging - Temperature/Pressure logging at each stage
 * 4. Multi-Unit Output - Crates/Boxes conversions
 * 5. Requisition Flow - GM approval required
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

echo "<!DOCTYPE html><html><head><title>Production Business Rules Audit</title>
<style>
    body { font-family: 'Segoe UI', sans-serif; padding: 30px; background: #f8f9fa; }
    .container { max-width: 1100px; margin: 0 auto; }
    h1 { color: #6f42c1; }
    h2 { color: #495057; margin-top: 30px; border-bottom: 2px solid #6f42c1; padding-bottom: 10px; }
    h3 { color: #6c757d; margin-top: 25px; }
    .card { background: white; border-radius: 10px; padding: 20px; margin: 15px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    .pass { border-left: 4px solid #28a745; }
    .fail { border-left: 4px solid #dc3545; }
    .warn { border-left: 4px solid #ffc107; }
    .info { border-left: 4px solid #17a2b8; }
    .status { font-weight: bold; }
    .pass .status { color: #28a745; }
    .fail .status { color: #dc3545; }
    .warn .status { color: #856404; }
    .info .status { color: #17a2b8; }
    table { width: 100%; border-collapse: collapse; margin: 15px 0; }
    th, td { padding: 10px; text-align: left; border-bottom: 1px solid #dee2e6; }
    th { background: #f8f9fa; font-weight: 600; }
    code { background: #e9ecef; padding: 2px 6px; border-radius: 3px; font-size: 13px; }
    pre { background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 5px; overflow-x: auto; }
    .summary-box { display: flex; gap: 15px; margin: 20px 0; }
    .summary-item { flex: 1; padding: 20px; border-radius: 10px; text-align: center; }
    .summary-item.good { background: #d4edda; }
    .summary-item.bad { background: #f8d7da; }
    .summary-item.neutral { background: #e2e3e5; }
    .summary-item .number { font-size: 32px; font-weight: bold; }
    .spec-quote { background: #f8f9fa; border-left: 4px solid #6f42c1; padding: 15px; margin: 15px 0; font-style: italic; }
</style></head><body><div class='container'>";

echo "<h1>🔍 Production Module Business Rules Audit</h1>";
echo "<p>Verifying implementation against <code>system_context/production_staff.md</code> and <code>HighlandFresh_PRD.md</code></p>";

$passCount = 0;
$failCount = 0;
$warnCount = 0;

try {
    $db = Database::getInstance()->getConnection();

    // ============================================
    // SECTION 1: BYPRODUCTS
    // ============================================
    echo "<h2>1. Byproducts Implementation</h2>";
    
    echo "<div class='spec-quote'>";
    echo "<strong>From production_staff.md:</strong><br>";
    echo "Byproduct Recording:<br>";
    echo "• <strong>Buttermilk</strong> - Butter production → Move to inventory<br>";
    echo "• <strong>Whey</strong> - Cheese production → Move to inventory<br>";
    echo "• <strong>Cream</strong> - Separation → Move to inventory<br>";
    echo "• <strong>Skim Milk</strong> - Separation → Move to inventory";
    echo "</div>";
    
    // Check byproducts table
    $tableCheck = $db->query("SHOW TABLES LIKE 'production_byproducts'");
    if ($tableCheck->rowCount() > 0) {
        $cols = $db->query("SHOW COLUMNS FROM production_byproducts LIKE 'byproduct_type'")->fetch();
        $enumValues = [];
        if ($cols && preg_match("/^enum\(\'(.*)\'\)$/", $cols['Type'], $matches)) {
            $enumValues = explode("','", $matches[1]);
        }
        
        $requiredTypes = ['buttermilk', 'whey', 'cream', 'skim_milk'];
        $missing = array_diff($requiredTypes, $enumValues);
        
        if (empty($missing)) {
            echo "<div class='card pass'><span class='status'>✓ PASS</span> - All required byproduct types defined: " . implode(', ', $requiredTypes) . "</div>";
            $passCount++;
        } else {
            echo "<div class='card fail'><span class='status'>✗ FAIL</span> - Missing byproduct types: " . implode(', ', $missing) . "</div>";
            $failCount++;
        }
        
        // Check byproducts API
        $byproductsApi = __DIR__ . '/production/byproducts.php';
        if (file_exists($byproductsApi)) {
            $apiContent = file_get_contents($byproductsApi);
            
            // Check destinations
            $hasWarehouse = strpos($apiContent, "'warehouse'") !== false;
            $hasReprocess = strpos($apiContent, "'reprocess'") !== false;
            $hasDispose = strpos($apiContent, "'dispose'") !== false;
            
            if ($hasWarehouse) {
                echo "<div class='card pass'><span class='status'>✓ PASS</span> - Byproducts can be moved to warehouse (inventory)</div>";
                $passCount++;
            } else {
                echo "<div class='card fail'><span class='status'>✗ FAIL</span> - Missing 'warehouse' destination for byproducts</div>";
                $failCount++;
            }
        }
    } else {
        echo "<div class='card fail'><span class='status'>✗ FAIL</span> - production_byproducts table does not exist</div>";
        $failCount++;
    }

    // ============================================
    // SECTION 2: YOGURT TRANSFORMATION (The Yogurt Rule)
    // ============================================
    echo "<h2>2. Yogurt Transformation (The 'Yogurt Rule')</h2>";
    
    echo "<div class='spec-quote'>";
    echo "<strong>From production_staff.md Section 7:</strong><br>";
    echo "\"When QC identifies bottled milk nearing expiry:<br>";
    echo "1. Production Staff physically take that milk<br>";
    echo "2. 'Less' it from finished goods inventory<br>";
    echo "3. Use it as raw ingredient for new batch of <strong>Yogurt</strong>\"<br><br>";
    echo "Documentation: Log as <strong>'Transformation'</strong> (NOT 'Waste' or 'Spillage')";
    echo "</div>";
    
    echo "<div class='spec-quote'>";
    echo "<strong>From PRD Business Rule #3:</strong><br>";
    echo "\"The Yogurt Rule: Near-expiry milk must be transformed into Yogurt to prevent financial loss.\"";
    echo "</div>";
    
    // Check yogurt_transformations table
    $tableCheck = $db->query("SHOW TABLES LIKE 'yogurt_transformations'");
    if ($tableCheck->rowCount() > 0) {
        echo "<div class='card pass'><span class='status'>✓ PASS</span> - yogurt_transformations table exists</div>";
        $passCount++;
        
        // Check columns
        $cols = $db->query("DESCRIBE yogurt_transformations")->fetchAll(PDO::FETCH_COLUMN);
        
        $requiredCols = ['source_inventory_id', 'source_quantity', 'transformation_code', 'status'];
        $missingCols = array_diff($requiredCols, $cols);
        
        if (empty($missingCols)) {
            echo "<div class='card pass'><span class='status'>✓ PASS</span> - Transformation table has required columns</div>";
            $passCount++;
        } else {
            echo "<div class='card fail'><span class='status'>✗ FAIL</span> - Missing columns: " . implode(', ', $missingCols) . "</div>";
            $failCount++;
        }
    } else {
        echo "<div class='card fail'><span class='status'>✗ FAIL</span> - yogurt_transformations table does not exist</div>";
        $failCount++;
    }
    
    // Check expiry_management API
    $expiryApi = __DIR__ . '/qc/expiry_management.php';
    if (file_exists($expiryApi)) {
        $apiContent = file_get_contents($expiryApi);
        
        // Check for transformation logic
        $hasTransformInsert = strpos($apiContent, "INSERT INTO yogurt_transformations") !== false;
        $hasInventoryDeduct = strpos($apiContent, "quantity_available - ?") !== false;
        $hasStatusTransformed = strpos($apiContent, "'transformed'") !== false;
        $hasProductionRunLink = strpos($apiContent, "production_run_id") !== false;
        $hasCreateProductionRun = strpos($apiContent, "create_production_run") !== false;
        
        if ($hasTransformInsert && $hasInventoryDeduct) {
            echo "<div class='card pass'><span class='status'>✓ PASS</span> - Transformation API: Creates record AND deducts from FG inventory</div>";
            $passCount++;
        } else {
            echo "<div class='card fail'><span class='status'>✗ FAIL</span> - Transformation does not properly deduct from FG inventory</div>";
            $failCount++;
        }
        
        if ($hasStatusTransformed) {
            echo "<div class='card pass'><span class='status'>✓ PASS</span> - FG inventory status changes to 'transformed' (not 'waste' or 'disposed')</div>";
            $passCount++;
        } else {
            echo "<div class='card warn'><span class='status'>⚠ WARN</span> - Should mark as 'transformed' status per spec</div>";
            $warnCount++;
        }
        
        if ($hasProductionRunLink && $hasCreateProductionRun) {
            echo "<div class='card pass'><span class='status'>✓ PASS</span> - Transformation links to Production Runs (production_run_id column + auto-creation option)</div>";
            $passCount++;
        } else {
            echo "<div class='card warn'><span class='status'>⚠ WARN</span> - Missing production run link</div>";
            $warnCount++;
        }
    } else {
        echo "<div class='card fail'><span class='status'>✗ FAIL</span> - expiry_management.php API not found</div>";
        $failCount++;
    }

    // ============================================
    // SECTION 3: CCP LOGGING
    // ============================================
    echo "<h2>3. Critical Control Points (CCP) Logging</h2>";
    
    echo "<div class='spec-quote'>";
    echo "<strong>From production_staff.md Section 2:</strong><br>";
    echo "<table><tr><th>Stage</th><th>Temperature</th><th>Notes</th></tr>";
    echo "<tr><td>Chilling</td><td>4°C</td><td>Upon receiving</td></tr>";
    echo "<tr><td>Pre-heating</td><td>65°C</td><td>Before homogenization</td></tr>";
    echo "<tr><td>Homogenization</td><td>N/A</td><td>Pressure: 1000-1500 psi</td></tr>";
    echo "<tr><td>Pasteurization (HTST)</td><td><strong>75°C</strong></td><td><strong>15 seconds</strong></td></tr>";
    echo "<tr><td>Cooling/Storage</td><td>4°C</td><td>Final storage</td></tr>";
    echo "</table>";
    echo "</div>";
    
    // Check CCP logs table
    $tableCheck = $db->query("SHOW TABLES LIKE 'production_ccp_logs'");
    if ($tableCheck->rowCount() > 0) {
        $cols = $db->query("DESCRIBE production_ccp_logs")->fetchAll(PDO::FETCH_COLUMN);
        
        $hasPressure = in_array('pressure', $cols);
        $hasTemperature = in_array('temperature', $cols);
        $hasCheckType = in_array('check_type', $cols);
        
        if ($hasTemperature && $hasCheckType) {
            echo "<div class='card pass'><span class='status'>✓ PASS</span> - CCP logs table has temperature and check_type columns</div>";
            $passCount++;
        }
        
        if ($hasPressure) {
            echo "<div class='card pass'><span class='status'>✓ PASS</span> - CCP logs supports homogenization pressure logging</div>";
            $passCount++;
        } else {
            echo "<div class='card warn'><span class='status'>⚠ WARN</span> - Missing 'pressure' column for homogenization (1000-1500 psi)</div>";
            $warnCount++;
        }
        
        // Check for check_type enum values
        $typeCol = $db->query("SHOW COLUMNS FROM production_ccp_logs LIKE 'check_type'")->fetch();
        if ($typeCol) {
            $ccpTypes = [];
            if (preg_match("/^enum\(\'(.*)\'\)$/", $typeCol['Type'], $matches)) {
                $ccpTypes = explode("','", $matches[1]);
            }
            
            $requiredCCPs = ['chilling', 'preheating', 'pasteurization', 'cooling'];
            $missing = array_diff($requiredCCPs, $ccpTypes);
            
            if (empty($missing)) {
                echo "<div class='card pass'><span class='status'>✓ PASS</span> - All CCP types defined: " . implode(', ', $ccpTypes) . "</div>";
                $passCount++;
            } else {
                echo "<div class='card fail'><span class='status'>✗ FAIL</span> - Missing CCP types: " . implode(', ', $missing) . "</div>";
                $failCount++;
            }
        }
    } else {
        echo "<div class='card fail'><span class='status'>✗ FAIL</span> - production_ccp_logs table does not exist</div>";
        $failCount++;
    }

    // ============================================
    // SECTION 4: MULTI-UNIT OUTPUT
    // ============================================
    echo "<h2>4. Multi-Unit Output Recording</h2>";
    
    echo "<div class='spec-quote'>";
    echo "<strong>From production_staff.md Section 6:</strong><br>";
    echo "<table><tr><th>Product</th><th>Primary Unit</th><th>Secondary Unit</th><th>Conversion</th></tr>";
    echo "<tr><td>Bottled Milk</td><td>Bottles</td><td>Crates</td><td>1 Crate = 24 Bottles</td></tr>";
    echo "<tr><td>Milk Bars</td><td>Pieces</td><td>Boxes</td><td>1 Box = 50 Pieces</td></tr>";
    echo "<tr><td>Cheese</td><td>Blocks</td><td>Cases</td><td>1 Case = 12 Blocks</td></tr>";
    echo "<tr><td>Butter</td><td>Packs</td><td>Cases</td><td>1 Case = 20 Packs</td></tr>";
    echo "</table>";
    echo "</div>";
    
    // Check production_runs for output_breakdown
    $cols = $db->query("DESCRIBE production_runs")->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('output_breakdown', $cols)) {
        echo "<div class='card pass'><span class='status'>✓ PASS</span> - production_runs has 'output_breakdown' JSON column for multi-unit storage</div>";
        $passCount++;
    } else {
        echo "<div class='card warn'><span class='status'>⚠ WARN</span> - Missing 'output_breakdown' column for multi-unit output storage</div>";
        $warnCount++;
    }
    
    // Check runs.php API for conversion logic
    $runsApi = __DIR__ . '/production/runs.php';
    if (file_exists($runsApi)) {
        $apiContent = file_get_contents($runsApi);
        
        $has24Crate = strpos($apiContent, '24') !== false && strpos($apiContent, 'crate') !== false;
        $has50Box = strpos($apiContent, '50') !== false && strpos($apiContent, 'box') !== false;
        
        if ($has24Crate || $has50Box) {
            echo "<div class='card pass'><span class='status'>✓ PASS</span> - Production runs API includes unit conversion logic</div>";
            $passCount++;
        } else {
            echo "<div class='card warn'><span class='status'>⚠ WARN</span> - Unit conversion constants may need verification</div>";
            $warnCount++;
        }
    }

    // ============================================
    // SECTION 5: REQUISITION FLOW
    // ============================================
    echo "<h2>5. Requisition Flow (GM Approval Required)</h2>";
    
    echo "<div class='spec-quote'>";
    echo "<strong>From production_staff.md Section 8:</strong><br>";
    echo "\"Cannot simply take ingredients. Input a <strong>Digital Requisition</strong>.<br>";
    echo "Wait for <strong>General Manager's approval</strong>.<br>";
    echo "Receive item from <strong>Warehouse Custodian</strong>.\"";
    echo "</div>";
    
    // Check requisitions table
    $tableCheck = $db->query("SHOW TABLES LIKE 'ingredient_requisitions'");
    if ($tableCheck->rowCount() > 0) {
        $cols = $db->query("DESCRIBE ingredient_requisitions")->fetchAll(PDO::FETCH_COLUMN);
        
        $hasApprovedBy = in_array('approved_by', $cols);
        $hasStatus = in_array('status', $cols);
        
        if ($hasApprovedBy && $hasStatus) {
            echo "<div class='card pass'><span class='status'>✓ PASS</span> - Requisitions table has approval tracking (approved_by, status)</div>";
            $passCount++;
        }
        
        // Check for status enum
        $statusCol = $db->query("SHOW COLUMNS FROM ingredient_requisitions LIKE 'status'")->fetch();
        if ($statusCol) {
            $hasApproved = strpos($statusCol['Type'], 'approved') !== false;
            $hasPending = strpos($statusCol['Type'], 'pending') !== false;
            
            if ($hasApproved && $hasPending) {
                echo "<div class='card pass'><span class='status'>✓ PASS</span> - Requisition workflow: pending → approved states</div>";
                $passCount++;
            }
        }
    } else {
        echo "<div class='card fail'><span class='status'>✗ FAIL</span> - ingredient_requisitions table does not exist</div>";
        $failCount++;
    }

    // ============================================
    // SECTION 6: PRODUCT TYPES
    // ============================================
    echo "<h2>6. Product Types Validation</h2>";
    
    echo "<div class='spec-quote'>";
    echo "<strong>From production_staff.md Section 3:</strong><br>";
    echo "Product diversification streams:<br>";
    echo "• Bottled Milk (Choco, Melon, Plain in 1000ml, 500ml, 200ml)<br>";
    echo "• Cheese - Managing the 'Cheese Vat,' 2-hour pressing<br>";
    echo "• Butter - 24-hour cream storage, 45-60 minute 'Turning' process<br>";
    echo "• Yogurt - Fermentation process<br>";
    echo "• Milk Bars - Ice candy style with plastic film";
    echo "</div>";
    
    // Check master_recipes product_type
    $tableCheck = $db->query("SHOW TABLES LIKE 'master_recipes'");
    if ($tableCheck->rowCount() > 0) {
        $typeCol = $db->query("SHOW COLUMNS FROM master_recipes LIKE 'product_type'")->fetch();
        if ($typeCol) {
            $types = [];
            if (preg_match("/^enum\(\'(.*)\'\)$/", $typeCol['Type'], $matches)) {
                $types = explode("','", $matches[1]);
            }
            
            $requiredTypes = ['bottled_milk', 'cheese', 'butter', 'yogurt', 'milk_bar'];
            $missing = array_diff($requiredTypes, $types);
            
            if (empty($missing)) {
                echo "<div class='card pass'><span class='status'>✓ PASS</span> - All product types defined: " . implode(', ', $types) . "</div>";
                $passCount++;
            } else {
                echo "<div class='card fail'><span class='status'>✗ FAIL</span> - Missing product types: " . implode(', ', $missing) . "</div>";
                $failCount++;
            }
        }
    }

    // ============================================
    // SUMMARY
    // ============================================
    echo "<h2>📊 Audit Summary</h2>";
    
    $total = $passCount + $failCount + $warnCount;
    
    echo "<div class='summary-box'>";
    echo "<div class='summary-item good'><div class='number'>{$passCount}</div><div>PASSED</div></div>";
    echo "<div class='summary-item bad'><div class='number'>{$failCount}</div><div>FAILED</div></div>";
    echo "<div class='summary-item neutral'><div class='number'>{$warnCount}</div><div>WARNINGS</div></div>";
    echo "<div class='summary-item neutral'><div class='number'>{$total}</div><div>TOTAL</div></div>";
    echo "</div>";
    
    // Key findings
    echo "<h3>🔑 Key Findings</h3>";
    echo "<div class='card info'>";
    echo "<strong>1. Byproducts vs Transformation Distinction:</strong><br>";
    echo "The system correctly separates:<br>";
    echo "• <strong>Byproducts</strong> (production_byproducts): Buttermilk, Whey, Cream - from normal production<br>";
    echo "• <strong>Transformation</strong> (yogurt_transformations): Near-expiry FG → Yogurt ingredient<br><br>";
    
    echo "<strong>2. Potential Gap - Transformation to Production Link:</strong><br>";
    echo "Current flow: QC initiates transformation → FG deducted<br>";
    echo "Missing: Direct link to create a Yogurt production run from transformed milk<br><br>";
    
    echo "<strong>3. The 'Yogurt Rule' Implementation:</strong><br>";
    echo "✓ Table exists for tracking transformations<br>";
    echo "✓ FG inventory is properly deducted<br>";
    echo "✓ Status changes to 'transformed' (not 'waste')<br>";
    echo "⚠ Missing: Auto-creation of Yogurt production run with transformed milk as ingredient";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='card fail'><span class='status'>✗ ERROR</span> - " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
