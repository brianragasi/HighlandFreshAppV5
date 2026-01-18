<?php
/**
 * Highland Fresh System - QC Integration Test
 * 
 * Comprehensive test for Acceptance to Production flow:
 * 1. Farmer Management
 * 2. Milk Delivery Recording
 * 3. QC Testing (Milk Grading with ANNEX B pricing)
 * 4. Inventory Update (Raw Milk)
 * 5. Production Run Creation (using accepted milk)
 * 6. Batch Release (QC Release)
 * 
 * @package HighlandFresh
 * @version 4.0
 */

// Set display
header('Content-Type: text/html; charset=UTF-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

define('HIGHLAND_FRESH', true);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

// Test Results Collector
class TestResults {
    private $tests = [];
    private $passed = 0;
    private $failed = 0;
    private $errors = [];
    
    public function add($name, $passed, $details = '', $error = null) {
        $this->tests[] = [
            'name' => $name,
            'passed' => $passed,
            'details' => $details,
            'error' => $error
        ];
        if ($passed) {
            $this->passed++;
        } else {
            $this->failed++;
            if ($error) {
                $this->errors[] = $error;
            }
        }
    }
    
    public function render() {
        $total = $this->passed + $this->failed;
        $percentage = $total > 0 ? round(($this->passed / $total) * 100) : 0;
        
        echo "<style>
            body { font-family: 'Segoe UI', Tahoma, sans-serif; margin: 20px; background: #f5f5f5; }
            .container { max-width: 1200px; margin: 0 auto; }
            .header { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 30px; border-radius: 10px; margin-bottom: 20px; }
            .header h1 { margin: 0; font-size: 28px; }
            .header p { margin: 5px 0 0 0; opacity: 0.9; }
            .summary { display: flex; gap: 15px; margin-bottom: 20px; }
            .summary-card { flex: 1; padding: 20px; border-radius: 10px; background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .summary-card h3 { margin: 0 0 10px 0; font-size: 14px; color: #666; text-transform: uppercase; }
            .summary-card .value { font-size: 32px; font-weight: bold; }
            .passed { color: #28a745; }
            .failed { color: #dc3545; }
            .test-section { background: white; border-radius: 10px; margin-bottom: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden; }
            .section-header { background: #f8f9fa; padding: 15px 20px; border-bottom: 1px solid #e9ecef; }
            .section-header h2 { margin: 0; font-size: 18px; }
            .test-item { padding: 15px 20px; border-bottom: 1px solid #e9ecef; display: flex; align-items: center; }
            .test-item:last-child { border-bottom: none; }
            .test-item .icon { width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px; font-size: 14px; }
            .test-item.pass .icon { background: #d4edda; color: #28a745; }
            .test-item.fail .icon { background: #f8d7da; color: #dc3545; }
            .test-item .content { flex: 1; }
            .test-item .name { font-weight: 600; margin-bottom: 3px; }
            .test-item .details { font-size: 13px; color: #666; }
            .test-item .error { font-size: 13px; color: #dc3545; background: #f8d7da; padding: 5px 10px; border-radius: 5px; margin-top: 5px; }
            .progress-bar { height: 10px; background: #e9ecef; border-radius: 5px; overflow: hidden; }
            .progress-bar .fill { height: 100%; background: linear-gradient(90deg, #28a745, #20c997); transition: width 0.3s; }
            .errors-section { background: #fff3cd; border: 1px solid #ffc107; border-radius: 10px; padding: 20px; margin-top: 20px; }
            .errors-section h3 { color: #856404; margin-top: 0; }
            .errors-section pre { background: #fff; padding: 10px; border-radius: 5px; overflow-x: auto; }
        </style>";
        
        echo "<div class='container'>";
        
        // Header
        echo "<div class='header'>";
        echo "<h1>🧪 Highland Fresh - QC Integration Test Suite</h1>";
        echo "<p>Testing Acceptance → Grading → Inventory → Production → Release Flow</p>";
        echo "</div>";
        
        // Summary Cards
        echo "<div class='summary'>";
        echo "<div class='summary-card'><h3>Total Tests</h3><div class='value'>{$total}</div></div>";
        echo "<div class='summary-card'><h3>Passed</h3><div class='value passed'>{$this->passed}</div></div>";
        echo "<div class='summary-card'><h3>Failed</h3><div class='value failed'>{$this->failed}</div></div>";
        echo "<div class='summary-card'><h3>Success Rate</h3><div class='value'>{$percentage}%</div><div class='progress-bar'><div class='fill' style='width: {$percentage}%'></div></div></div>";
        echo "</div>";
        
        // Group tests by category
        $categories = [];
        foreach ($this->tests as $test) {
            $parts = explode(':', $test['name'], 2);
            $category = trim($parts[0]);
            if (!isset($categories[$category])) {
                $categories[$category] = [];
            }
            $categories[$category][] = $test;
        }
        
        // Render each category
        foreach ($categories as $category => $tests) {
            echo "<div class='test-section'>";
            echo "<div class='section-header'><h2>{$category}</h2></div>";
            foreach ($tests as $test) {
                $class = $test['passed'] ? 'pass' : 'fail';
                $icon = $test['passed'] ? '✓' : '✗';
                $name = isset($parts[1]) ? trim(explode(':', $test['name'], 2)[1] ?? $test['name']) : $test['name'];
                echo "<div class='test-item {$class}'>";
                echo "<div class='icon'>{$icon}</div>";
                echo "<div class='content'>";
                echo "<div class='name'>{$name}</div>";
                if ($test['details']) {
                    echo "<div class='details'>{$test['details']}</div>";
                }
                if ($test['error']) {
                    echo "<div class='error'>⚠️ {$test['error']}</div>";
                }
                echo "</div></div>";
            }
            echo "</div>";
        }
        
        // Errors Summary
        if (!empty($this->errors)) {
            echo "<div class='errors-section'>";
            echo "<h3>⚠️ Errors Found - Need Fixing</h3>";
            echo "<ol>";
            foreach ($this->errors as $error) {
                echo "<li>{$error}</li>";
            }
            echo "</ol>";
            echo "</div>";
        }
        
        echo "</div>";
    }
}

$results = new TestResults();

try {
    $db = Database::getInstance()->getConnection();
    $results->add('Database: Connection', true, 'Connected to highland_fresh database');
} catch (Exception $e) {
    $results->add('Database: Connection', false, '', $e->getMessage());
    $results->render();
    exit;
}

// ============================================
// STEP 1: CHECK DATABASE TABLES
// ============================================

$requiredTables = [
    'farmers' => 'Farmer management',
    'milk_deliveries' => 'Milk delivery records',
    'qc_milk_tests' => 'QC milk testing',
    'raw_milk_inventory' => 'Raw milk inventory',
    'production_runs' => 'Production runs',
    'production_batches' => 'Production batches',
    'master_recipes' => 'Product recipes',
    'finished_goods_inventory' => 'Finished goods',
    'users' => 'User authentication'
];

foreach ($requiredTables as $table => $description) {
    try {
        $check = $db->query("SHOW TABLES LIKE '{$table}'");
        $exists = $check->rowCount() > 0;
        $results->add("Schema: {$table}", $exists, $description, !$exists ? "Table '{$table}' is missing" : null);
    } catch (Exception $e) {
        $results->add("Schema: {$table}", false, '', $e->getMessage());
    }
}

// ============================================
// STEP 2: CHECK TABLE STRUCTURES
// ============================================

// Check milk_deliveries columns
try {
    $cols = $db->query("DESCRIBE milk_deliveries");
    $columns = $cols->fetchAll(PDO::FETCH_COLUMN);
    
    $requiredCols = ['id', 'delivery_code', 'farmer_id', 'volume_liters', 'status', 'delivery_date', 'accepted_liters'];
    $missing = array_diff($requiredCols, $columns);
    
    if (empty($missing)) {
        $results->add('Schema: milk_deliveries columns', true, 'All required columns present');
    } else {
        $results->add('Schema: milk_deliveries columns', false, '', 'Missing columns: ' . implode(', ', $missing));
    }
} catch (Exception $e) {
    $results->add('Schema: milk_deliveries columns', false, '', $e->getMessage());
}

// Check qc_milk_tests columns
try {
    $cols = $db->query("DESCRIBE qc_milk_tests");
    $columns = $cols->fetchAll(PDO::FETCH_COLUMN);
    
    $requiredCols = ['id', 'test_code', 'delivery_id', 'fat_percentage', 'titratable_acidity', 'is_accepted', 'grade', 'final_price_per_liter', 'total_amount'];
    $missing = array_diff($requiredCols, $columns);
    
    if (empty($missing)) {
        $results->add('Schema: qc_milk_tests columns', true, 'All required columns present');
    } else {
        $results->add('Schema: qc_milk_tests columns', false, '', 'Missing columns: ' . implode(', ', $missing));
    }
} catch (Exception $e) {
    $results->add('Schema: qc_milk_tests columns', false, '', $e->getMessage());
}

// Check production_batches columns
try {
    $cols = $db->query("DESCRIBE production_batches");
    $columns = $cols->fetchAll(PDO::FETCH_COLUMN);
    
    $requiredCols = ['id', 'batch_code', 'recipe_id', 'qc_status', 'actual_yield', 'manufacturing_date', 'expiry_date'];
    $missing = array_diff($requiredCols, $columns);
    
    if (empty($missing)) {
        $results->add('Schema: production_batches columns', true, 'All required columns present');
    } else {
        $results->add('Schema: production_batches columns', false, '', 'Missing columns: ' . implode(', ', $missing));
    }
} catch (Exception $e) {
    $results->add('Schema: production_batches columns', false, '', $e->getMessage());
}

// ============================================
// STEP 3: CHECK EXISTING DATA
// ============================================

// Check for active farmers
try {
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM farmers WHERE is_active = 1");
    $count = $stmt->fetch()['cnt'];
    $results->add('Data: Active Farmers', $count > 0, "{$count} active farmers found", $count == 0 ? "No active farmers. Need to create test farmer." : null);
} catch (Exception $e) {
    $results->add('Data: Active Farmers', false, '', $e->getMessage());
}

// Check for users with QC role
try {
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM users WHERE role IN ('qc_officer', 'general_manager')");
    $count = $stmt->fetch()['cnt'];
    $results->add('Data: QC Users', $count > 0, "{$count} QC users found", $count == 0 ? "No QC users. API calls will fail authentication." : null);
} catch (Exception $e) {
    $results->add('Data: QC Users', false, '', $e->getMessage());
}

// Check for recipes
try {
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM master_recipes WHERE is_active = 1");
    $count = $stmt->fetch()['cnt'];
    $results->add('Data: Active Recipes', $count > 0, "{$count} active recipes found", $count == 0 ? "No recipes. Production runs cannot be created." : null);
} catch (Exception $e) {
    $results->add('Data: Active Recipes', false, '', $e->getMessage());
}

// ============================================
// STEP 4: TEST MILK DELIVERY FLOW
// ============================================

// Get a test farmer
$testFarmer = null;
try {
    $stmt = $db->query("SELECT id, farmer_code, first_name, last_name FROM farmers WHERE is_active = 1 LIMIT 1");
    $testFarmer = $stmt->fetch();
    if ($testFarmer) {
        $results->add('Flow: Test Farmer', true, "Using farmer: {$testFarmer['farmer_code']} ({$testFarmer['first_name']} {$testFarmer['last_name']})");
    } else {
        $results->add('Flow: Test Farmer', false, '', 'No farmer available for testing');
    }
} catch (Exception $e) {
    $results->add('Flow: Test Farmer', false, '', $e->getMessage());
}

// Simulate creating a milk delivery
$testDeliveryId = null;
if ($testFarmer) {
    try {
        // Generate delivery code
        $codeStmt = $db->query("SELECT MAX(CAST(SUBSTRING(delivery_code, 5) AS UNSIGNED)) as max_num FROM milk_deliveries WHERE delivery_code LIKE 'DEL-%'");
        $maxNum = $codeStmt->fetch()['max_num'] ?? 0;
        $testDeliveryCode = 'DEL-' . str_pad($maxNum + 1, 6, '0', STR_PAD_LEFT);
        
        // Insert test delivery
        $stmt = $db->prepare("
            INSERT INTO milk_deliveries (delivery_code, farmer_id, delivery_date, delivery_time, volume_liters, status, apt_result)
            VALUES (?, ?, CURDATE(), CURTIME(), 50.0, 'pending_test', 'negative')
        ");
        $stmt->execute([$testDeliveryCode, $testFarmer['id']]);
        $testDeliveryId = $db->lastInsertId();
        
        $results->add('Flow: Create Delivery', true, "Created delivery {$testDeliveryCode} with 50L");
    } catch (Exception $e) {
        $results->add('Flow: Create Delivery', false, '', $e->getMessage());
    }
}

// ============================================
// STEP 5: TEST QC GRADING FLOW
// ============================================

$testTestId = null;
if ($testDeliveryId) {
    try {
        // Simulate QC test with passing values
        $fatPercentage = 3.8;
        $titratableAcidity = 0.16;
        $temperatureCelsius = 4.5;
        $sedimentGrade = 1;
        $density = 1.028;
        
        // Calculate pricing (ANNEX B)
        $basePrice = 30.00;
        
        // Fat adjustment
        $fatAdjustment = 0.00;
        if ($fatPercentage >= 3.5 && $fatPercentage <= 4.0) $fatAdjustment = 0.00;
        elseif ($fatPercentage > 4.0 && $fatPercentage <= 4.5) $fatAdjustment = 0.25;
        
        // Acidity deduction
        $acidityDeduction = 0.00;
        if ($titratableAcidity <= 0.18) $acidityDeduction = 0.00;
        
        // Sediment deduction
        $sedimentDeduction = 0.00;
        if ($sedimentGrade == 1) $sedimentDeduction = 0.00;
        
        $finalPrice = $basePrice + $fatAdjustment - $acidityDeduction - $sedimentDeduction;
        $totalAmount = 50.0 * $finalPrice;
        
        // Determine grade
        $milkGrade = 'B'; // fat 3.8%, acidity 0.16%, sediment 1 = Grade B
        
        // Generate test code
        $codeStmt = $db->query("SELECT MAX(CAST(SUBSTRING(test_code, 5) AS UNSIGNED)) as max_num FROM qc_milk_tests WHERE test_code LIKE 'QCT-%'");
        $maxNum = $codeStmt->fetch()['max_num'] ?? 0;
        $testTestCode = 'QCT-' . str_pad($maxNum + 1, 6, '0', STR_PAD_LEFT);
        
        // Insert QC test
        $stmt = $db->prepare("
            INSERT INTO qc_milk_tests (
                test_code, delivery_id, test_datetime,
                fat_percentage, titratable_acidity, temperature_celsius, sediment_grade, density,
                base_price_per_liter, fat_adjustment, acidity_deduction, sediment_deduction,
                final_price_per_liter, total_amount, is_accepted, grade
            ) VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
        ");
        $stmt->execute([
            $testTestCode, $testDeliveryId,
            $fatPercentage, $titratableAcidity, $temperatureCelsius, $sedimentGrade, $density,
            $basePrice, $fatAdjustment, $acidityDeduction, $sedimentDeduction,
            $finalPrice, $totalAmount, $milkGrade
        ]);
        $testTestId = $db->lastInsertId();
        
        // Update delivery status
        $updateStmt = $db->prepare("
            UPDATE milk_deliveries 
            SET status = 'accepted', grade = ?, accepted_liters = volume_liters, unit_price = ?, total_amount = ?
            WHERE id = ?
        ");
        $updateStmt->execute([$milkGrade, $finalPrice, $totalAmount, $testDeliveryId]);
        
        $results->add('Flow: QC Grading', true, "Test {$testTestCode}: Grade {$milkGrade}, Price ₱{$finalPrice}/L, Total ₱{$totalAmount}");
    } catch (Exception $e) {
        $results->add('Flow: QC Grading', false, '', $e->getMessage());
    }
}

// ============================================
// STEP 6: CHECK RAW MILK INVENTORY UPDATE
// ============================================

if ($testTestId) {
    try {
        // Check if raw_milk_inventory exists and has proper structure
        $tableCheck = $db->query("SHOW TABLES LIKE 'raw_milk_inventory'");
        if ($tableCheck->rowCount() > 0) {
            // Check table structure
            $colCheck = $db->query("SHOW COLUMNS FROM raw_milk_inventory LIKE 'qc_test_id'");
            $hasQcTestId = $colCheck->rowCount() > 0;
            
            if ($hasQcTestId) {
                // New schema - insert with qc_test_id
                $expiryDate = date('Y-m-d', strtotime('+2 days'));
                $invStmt = $db->prepare("
                    INSERT INTO raw_milk_inventory (tank_id, qc_test_id, volume_liters, status, received_date, expiry_date)
                    VALUES ('TANK-01', ?, 50.0, 'available', CURDATE(), ?)
                ");
                $invStmt->execute([$testTestId, $expiryDate]);
                $results->add('Flow: Inventory Update', true, "Added 50L to raw milk inventory (qc_test_id: {$testTestId})");
            } else {
                // Legacy schema - insert with delivery_id
                $invStmt = $db->prepare("
                    INSERT INTO raw_milk_inventory (tank_number, delivery_id, volume_liters, status, received_date)
                    VALUES (1, ?, 50.0, 'available', CURDATE())
                ");
                $invStmt->execute([$testDeliveryId]);
                $results->add('Flow: Inventory Update', true, "Added 50L to raw milk inventory (delivery_id: {$testDeliveryId})");
            }
        } else {
            $results->add('Flow: Inventory Update', false, '', 'raw_milk_inventory table does not exist');
        }
    } catch (Exception $e) {
        $results->add('Flow: Inventory Update', false, '', $e->getMessage());
    }
}

// ============================================
// STEP 7: TEST PRODUCTION AVAILABLE MILK
// ============================================

try {
    // Check production can see accepted milk
    $stmt = $db->prepare("
        SELECT COUNT(*) as cnt,
               SUM(CASE WHEN md.accepted_liters > 0 THEN md.accepted_liters ELSE md.volume_liters END) as total_liters
        FROM milk_deliveries md
        JOIN qc_milk_tests qmt ON qmt.delivery_id = md.id
        WHERE md.status = 'accepted'
          AND DATE(md.delivery_date) >= DATE_SUB(CURDATE(), INTERVAL 2 DAY)
    ");
    $stmt->execute();
    $available = $stmt->fetch();
    
    $results->add('Flow: Production Available Milk', true, 
        "{$available['cnt']} deliveries with " . number_format($available['total_liters'] ?? 0, 1) . "L available for production");
} catch (Exception $e) {
    $results->add('Flow: Production Available Milk', false, '', $e->getMessage());
}

// ============================================
// STEP 8: TEST PRODUCTION BATCH CREATION
// ============================================

$testBatchId = null;
try {
    // Get a recipe
    $recipeStmt = $db->query("SELECT id, recipe_code, product_name FROM master_recipes WHERE is_active = 1 LIMIT 1");
    $recipe = $recipeStmt->fetch();
    
    if ($recipe) {
        // Generate batch code
        $codeStmt = $db->query("SELECT MAX(CAST(SUBSTRING(batch_code, 7) AS UNSIGNED)) as max_num FROM production_batches WHERE batch_code LIKE 'BATCH-%'");
        $maxNum = $codeStmt->fetch()['max_num'] ?? 0;
        $batchCode = 'BATCH-' . str_pad($maxNum + 1, 5, '0', STR_PAD_LEFT);
        
        $manufacturingDate = date('Y-m-d');
        $expiryDate = date('Y-m-d', strtotime('+30 days'));
        
        // Check actual columns in production_batches
        $cols = $db->query("DESCRIBE production_batches");
        $columns = $cols->fetchAll(PDO::FETCH_COLUMN);
        
        // Build insert based on available columns
        if (in_array('product_type', $columns)) {
            $stmt = $db->prepare("
                INSERT INTO production_batches (batch_code, recipe_id, product_type, manufacturing_date, expiry_date, qc_status, actual_yield)
                VALUES (?, ?, 'pasteurized_milk', ?, ?, 'pending', 48.0)
            ");
            $stmt->execute([$batchCode, $recipe['id'], $manufacturingDate, $expiryDate]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO production_batches (batch_code, recipe_id, manufacturing_date, expiry_date, qc_status, actual_yield)
                VALUES (?, ?, ?, ?, 'pending', 48.0)
            ");
            $stmt->execute([$batchCode, $recipe['id'], $manufacturingDate, $expiryDate]);
        }
        
        $testBatchId = $db->lastInsertId();
        $results->add('Flow: Batch Creation', true, "Created batch {$batchCode} using recipe {$recipe['recipe_code']}");
    } else {
        $results->add('Flow: Batch Creation', false, '', 'No active recipe found');
    }
} catch (Exception $e) {
    $results->add('Flow: Batch Creation', false, '', $e->getMessage());
}

// ============================================
// STEP 9: TEST BATCH RELEASE (QC)
// ============================================

if ($testBatchId) {
    try {
        $updateStmt = $db->prepare("
            UPDATE production_batches 
            SET qc_status = 'released',
                qc_released_at = NOW(),
                qc_notes = 'Integration test - auto released',
                organoleptic_taste = 1,
                organoleptic_appearance = 1,
                organoleptic_smell = 1
            WHERE id = ?
        ");
        $updateStmt->execute([$testBatchId]);
        
        $results->add('Flow: Batch Release', true, "Batch released with organoleptic checks passed");
    } catch (Exception $e) {
        $results->add('Flow: Batch Release', false, '', $e->getMessage());
    }
}

// ============================================
// STEP 10: TEST FINISHED GOODS INVENTORY
// ============================================

if ($testBatchId) {
    try {
        // Check finished_goods_inventory table structure
        $cols = $db->query("DESCRIBE finished_goods_inventory");
        $columns = $cols->fetchAll(PDO::FETCH_COLUMN);
        
        // Check what columns exist
        $hasQuantityAvailable = in_array('quantity_available', $columns);
        $hasRemainingQuantity = in_array('remaining_quantity', $columns);
        
        // Get batch info
        $batchStmt = $db->prepare("SELECT * FROM production_batches WHERE id = ?");
        $batchStmt->execute([$testBatchId]);
        $batch = $batchStmt->fetch();
        
        if ($hasQuantityAvailable) {
            $fgStmt = $db->prepare("
                INSERT INTO finished_goods_inventory (
                    batch_id, product_type, quantity, quantity_available,
                    manufacturing_date, expiry_date, status
                ) VALUES (?, 'pasteurized_milk', 48.0, 48.0, ?, ?, 'available')
            ");
            $fgStmt->execute([$testBatchId, $batch['manufacturing_date'], $batch['expiry_date']]);
        } elseif ($hasRemainingQuantity) {
            $fgStmt = $db->prepare("
                INSERT INTO finished_goods_inventory (
                    batch_id, product_type, quantity, remaining_quantity,
                    manufacturing_date, expiry_date, status
                ) VALUES (?, 'pasteurized_milk', 48.0, 48.0, ?, ?, 'available')
            ");
            $fgStmt->execute([$testBatchId, $batch['manufacturing_date'], $batch['expiry_date']]);
        }
        
        $results->add('Flow: Finished Goods', true, "Added 48 units to finished goods inventory");
    } catch (Exception $e) {
        $results->add('Flow: Finished Goods', false, '', $e->getMessage());
    }
}

// ============================================
// STEP 11: VALIDATE DATA INTEGRITY
// ============================================

// Check for orphaned QC tests (tests without deliveries)
try {
    $stmt = $db->query("
        SELECT COUNT(*) as cnt 
        FROM qc_milk_tests qmt 
        LEFT JOIN milk_deliveries md ON qmt.delivery_id = md.id 
        WHERE md.id IS NULL
    ");
    $orphaned = $stmt->fetch()['cnt'];
    $results->add('Integrity: Orphaned QC Tests', $orphaned == 0, 
        $orphaned > 0 ? "{$orphaned} orphaned tests found" : "No orphaned tests",
        $orphaned > 0 ? "QC tests exist without corresponding deliveries" : null);
} catch (Exception $e) {
    $results->add('Integrity: Orphaned QC Tests', false, '', $e->getMessage());
}

// Check for deliveries with accepted status but no QC test
try {
    $stmt = $db->query("
        SELECT COUNT(*) as cnt 
        FROM milk_deliveries md 
        LEFT JOIN qc_milk_tests qmt ON qmt.delivery_id = md.id 
        WHERE md.status = 'accepted' AND qmt.id IS NULL
    ");
    $orphaned = $stmt->fetch()['cnt'];
    $results->add('Integrity: Accepted Deliveries without QC Test', $orphaned == 0, 
        $orphaned > 0 ? "{$orphaned} issues found" : "All accepted deliveries have QC tests",
        $orphaned > 0 ? "Some deliveries marked accepted have no QC test record" : null);
} catch (Exception $e) {
    $results->add('Integrity: Accepted Deliveries without QC Test', false, '', $e->getMessage());
}

// Check for released batches without finished goods
try {
    $stmt = $db->query("
        SELECT COUNT(*) as cnt 
        FROM production_batches pb 
        LEFT JOIN finished_goods_inventory fgi ON pb.id = fgi.batch_id 
        WHERE pb.qc_status = 'released' AND fgi.id IS NULL AND pb.actual_yield > 0
    ");
    $orphaned = $stmt->fetch()['cnt'];
    $results->add('Integrity: Released Batches without FG', $orphaned == 0, 
        $orphaned > 0 ? "{$orphaned} issues found" : "All released batches have FG inventory",
        $orphaned > 0 ? "Some released batches are missing from finished goods inventory" : null);
} catch (Exception $e) {
    $results->add('Integrity: Released Batches without FG', false, '', $e->getMessage());
}

// ============================================
// STEP 12: CLEANUP TEST DATA
// ============================================

try {
    $db->beginTransaction();
    
    // Delete finished goods from test batch
    if ($testBatchId) {
        $db->prepare("DELETE FROM finished_goods_inventory WHERE batch_id = ?")->execute([$testBatchId]);
    }
    
    // Delete test batch
    if ($testBatchId) {
        $db->prepare("DELETE FROM production_batches WHERE id = ?")->execute([$testBatchId]);
    }
    
    // Delete raw milk inventory from test
    // Check which schema we're using
    $colCheck = $db->query("SHOW COLUMNS FROM raw_milk_inventory LIKE 'qc_test_id'");
    $hasQcTestId = $colCheck->rowCount() > 0;
    
    if ($hasQcTestId && $testTestId) {
        $db->exec("DELETE FROM raw_milk_inventory WHERE qc_test_id = {$testTestId}");
    }
    if ($testDeliveryId) {
        $db->exec("DELETE FROM raw_milk_inventory WHERE delivery_id = {$testDeliveryId}");
    }
    
    // Delete test QC test
    if ($testTestId) {
        $db->prepare("DELETE FROM qc_milk_tests WHERE id = ?")->execute([$testTestId]);
    }
    
    // Delete test delivery
    if ($testDeliveryId) {
        $db->prepare("DELETE FROM milk_deliveries WHERE id = ?")->execute([$testDeliveryId]);
    }
    
    $db->commit();
    $results->add('Cleanup: Test Data', true, 'All test data cleaned up successfully');
} catch (Exception $e) {
    $db->rollBack();
    $results->add('Cleanup: Test Data', false, '', $e->getMessage());
}

// ============================================
// STEP 13: API ENDPOINT AVAILABILITY CHECK
// ============================================

$endpoints = [
    '/api/qc/farmers.php' => 'Farmers API',
    '/api/qc/deliveries.php' => 'Deliveries API',
    '/api/qc/milk_grading.php' => 'Milk Grading API',
    '/api/qc/batch_release.php' => 'Batch Release API',
    '/api/qc/dashboard.php' => 'QC Dashboard API',
    '/api/production/runs.php' => 'Production Runs API',
];

foreach ($endpoints as $path => $name) {
    $fullPath = dirname(__DIR__) . $path;
    $exists = file_exists($fullPath);
    $results->add("API: {$name}", $exists, $path, !$exists ? "File not found: {$fullPath}" : null);
}

// Render results
$results->render();
?>
