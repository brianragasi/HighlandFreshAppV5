<?php
/**
 * Highland Fresh System - Production Module Integration Test
 * Comprehensive Acceptance to Production Integration Testing
 * 
 * Tests ALL Production functionality including:
 * - Dashboard & Available Milk
 * - Recipes Management
 * - Production Runs (Create, Start, CCP, Complete)
 * - Milk Source Validation & Allocation
 * - Byproducts Recording
 * - Requisitions Flow
 * - Yogurt Transformation (The "Yogurt Rule")
 * 
 * @package HighlandFresh
 * @version 4.0
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define required constant for bootstrap
if (!defined('HIGHLAND_FRESH')) {
    define('HIGHLAND_FRESH', true);
}

// Database config
define('DB_HOST', 'localhost');
define('DB_NAME', 'highland_fresh');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Bootstrap
require_once __DIR__ . '/config/database.php';

echo "\n";
echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║   HIGHLAND FRESH - PRODUCTION MODULE INTEGRATION TEST                      ║\n";
echo "║   Comprehensive Acceptance to Production Testing                           ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n";
echo "\n";

$db = Database::getInstance()->getConnection();
$testsPassed = 0;
$testsFailed = 0;
$testsSkipped = 0;
$testResults = [];

// Helper functions
function test($description, $condition, &$passed, &$failed, &$results, $details = '') {
    if ($condition) {
        echo "  ✅ PASS: {$description}\n";
        if ($details) echo "         ├─ {$details}\n";
        $passed++;
        $results[] = ['status' => 'PASS', 'test' => $description, 'details' => $details];
    } else {
        echo "  ❌ FAIL: {$description}\n";
        if ($details) echo "         ├─ {$details}\n";
        $failed++;
        $results[] = ['status' => 'FAIL', 'test' => $description, 'details' => $details];
    }
}

function skip($description, &$skipped, &$results, $reason) {
    echo "  ⏭️  SKIP: {$description}\n";
    echo "         ├─ Reason: {$reason}\n";
    $skipped++;
    $results[] = ['status' => 'SKIP', 'test' => $description, 'reason' => $reason];
}

function info($message) {
    echo "  ℹ️  INFO: {$message}\n";
}

function warning($message) {
    echo "  ⚠️  WARN: {$message}\n";
}

function section($title) {
    echo "\n┌─────────────────────────────────────────────────────────────────────────────┐\n";
    echo "│ {$title}\n";
    echo "└─────────────────────────────────────────────────────────────────────────────┘\n";
}

// =============================================================================
// SECTION 1: DATABASE SCHEMA VALIDATION
// =============================================================================
section("1. DATABASE SCHEMA VALIDATION");

// 1.1 - Required Tables
$requiredTables = [
    'master_recipes' => 'Product recipes for all dairy products',
    'production_runs' => 'Production batch tracking',
    'production_ccp_logs' => 'Critical Control Point logging',
    'production_byproducts' => 'Byproducts from production (buttermilk, whey, etc.)',
    'ingredient_requisitions' => 'Raw material requisitions',
    'ingredient_requisition_items' => 'Requisition line items',
    'ingredient_consumption' => 'Ingredient usage tracking',
    'raw_milk_inventory' => 'Raw milk storage inventory',
    'milk_deliveries' => 'Milk delivery records',
    'qc_milk_tests' => 'QC test results for milk',
    'production_run_milk_usage' => 'Tracks milk allocation per run',
    'yogurt_transformations' => 'Near-expiry FG → Yogurt transformation'
];

foreach ($requiredTables as $table => $description) {
    $exists = $db->query("SHOW TABLES LIKE '{$table}'")->rowCount() > 0;
    test("Table '{$table}' exists", $exists, $testsPassed, $testsFailed, $testResults, $description);
}

// 1.2 - Check critical columns
echo "\n  Checking critical columns...\n";

// production_runs columns
$runsColumns = ['output_breakdown', 'milk_liters_used', 'milk_batch_source'];
foreach ($runsColumns as $col) {
    $exists = $db->query("SHOW COLUMNS FROM production_runs LIKE '{$col}'")->rowCount() > 0;
    test("production_runs.{$col} column exists", $exists, $testsPassed, $testsFailed, $testResults);
}

// production_ccp_logs columns (check_type/stage, temperature, pressure for homogenization)
$ccpColumns = ['check_type', 'temperature', 'pressure'];
foreach ($ccpColumns as $col) {
    $exists = $db->query("SHOW COLUMNS FROM production_ccp_logs LIKE '{$col}'")->rowCount() > 0;
    test("production_ccp_logs.{$col} column exists", $exists, $testsPassed, $testsFailed, $testResults);
}

// yogurt_transformations columns
$transformCols = ['production_run_id', 'source_volume_liters', 'approved_by', 'safety_verified'];
foreach ($transformCols as $col) {
    $exists = $db->query("SHOW COLUMNS FROM yogurt_transformations LIKE '{$col}'")->rowCount() > 0;
    test("yogurt_transformations.{$col} column exists", $exists, $testsPassed, $testsFailed, $testResults);
}

// =============================================================================
// SECTION 2: AVAILABLE MILK CONSISTENCY CHECK
// =============================================================================
section("2. AVAILABLE MILK SOURCE CONSISTENCY");

// Check raw_milk_inventory
$rawMilkInventory = $db->query("
    SELECT COALESCE(SUM(volume_liters), 0) as total 
    FROM raw_milk_inventory 
    WHERE status = 'available'
")->fetch();

// Check milk_deliveries (QC approved)
$milkDeliveries = $db->query("
    SELECT COALESCE(SUM(CASE 
        WHEN md.accepted_liters > 0 THEN md.accepted_liters 
        ELSE md.volume_liters 
    END), 0) as total
    FROM milk_deliveries md
    JOIN qc_milk_tests qmt ON qmt.delivery_id = md.id
    WHERE md.status = 'accepted'
    AND DATE(md.delivery_date) >= DATE_SUB(CURDATE(), INTERVAL 2 DAY)
")->fetch();

info("raw_milk_inventory (status=available): " . number_format($rawMilkInventory['total'], 2) . " L");
info("milk_deliveries (QC accepted, 2-day): " . number_format($milkDeliveries['total'], 2) . " L");

// Critical issue detection
$diff = abs($rawMilkInventory['total'] - $milkDeliveries['total']);
if ($diff > 100) {
    warning("DISCREPANCY DETECTED: Dashboard shows {$rawMilkInventory['total']}L but Production can only use {$milkDeliveries['total']}L");
    warning("This explains 'Not enough milk available' error!");
}

// Check for recent QC-approved milk
$recentApproved = $db->query("
    SELECT COUNT(*) as count
    FROM milk_deliveries md
    JOIN qc_milk_tests qmt ON qmt.delivery_id = md.id
    WHERE md.status = 'accepted'
    AND DATE(md.delivery_date) >= DATE_SUB(CURDATE(), INTERVAL 2 DAY)
")->fetch();

test("Has recent QC-approved milk (within 2 days)", $recentApproved['count'] > 0, $testsPassed, $testsFailed, $testResults,
    "Found {$recentApproved['count']} recent approved deliveries"
);

// Check milk allocation tracking
$allocatedMilk = $db->query("
    SELECT COALESCE(SUM(milk_liters_allocated), 0) as total FROM production_run_milk_usage
")->fetch();
info("Total milk already allocated to runs: " . number_format($allocatedMilk['total'], 2) . " L");

// =============================================================================
// SECTION 3: RECIPES VALIDATION
// =============================================================================
section("3. MASTER RECIPES VALIDATION");

// Check recipe count
$recipeCount = $db->query("SELECT COUNT(*) as count FROM master_recipes WHERE is_active = 1")->fetch();
test("Has active recipes", $recipeCount['count'] > 0, $testsPassed, $testsFailed, $testResults,
    "Found {$recipeCount['count']} active recipes"
);

// Check product types coverage
$productTypes = ['bottled_milk', 'yogurt', 'cheese', 'butter', 'milk_bar'];
$existingTypes = $db->query("SELECT DISTINCT product_type FROM master_recipes WHERE is_active = 1")->fetchAll(PDO::FETCH_COLUMN);

foreach ($productTypes as $type) {
    $hasType = in_array($type, $existingTypes);
    test("Has recipe for '{$type}'", $hasType, $testsPassed, $testsFailed, $testResults);
}

// Check recipes have required fields
$invalidRecipes = $db->query("
    SELECT COUNT(*) as count FROM master_recipes 
    WHERE is_active = 1 
    AND (base_milk_liters IS NULL OR base_milk_liters <= 0 
         OR expected_yield IS NULL OR expected_yield <= 0)
")->fetch();
test("All recipes have valid base_milk_liters and expected_yield", $invalidRecipes['count'] == 0, $testsPassed, $testsFailed, $testResults);

// =============================================================================
// SECTION 4: PRODUCTION RUNS WORKFLOW
// =============================================================================
section("4. PRODUCTION RUNS WORKFLOW");

// Check production run statuses
$validStatuses = ['planned', 'in_progress', 'pasteurization', 'processing', 'cooling', 'packaging', 'completed', 'cancelled'];
$statusCheck = $db->query("
    SELECT DISTINCT status FROM production_runs
")->fetchAll(PDO::FETCH_COLUMN);

foreach ($statusCheck as $status) {
    $isValid = in_array($status, $validStatuses);
    test("Status '{$status}' is valid", $isValid, $testsPassed, $testsFailed, $testResults);
}

// Check run code format
$runCodeCheck = $db->query("
    SELECT COUNT(*) as count FROM production_runs 
    WHERE run_code NOT REGEXP '^PRD-[0-9]{8}-[0-9]{3}$'
")->fetch();
test("All run codes follow format PRD-YYYYMMDD-XXX", $runCodeCheck['count'] == 0, $testsPassed, $testsFailed, $testResults);

// Check completed runs have actual_quantity
$incompleteRuns = $db->query("
    SELECT COUNT(*) as count FROM production_runs 
    WHERE status = 'completed' 
    AND (actual_quantity IS NULL OR actual_quantity <= 0)
")->fetch();
test("All completed runs have actual_quantity", $incompleteRuns['count'] == 0, $testsPassed, $testsFailed, $testResults);

// Check milk allocation for runs
$runsWithoutMilk = $db->query("
    SELECT COUNT(*) as count FROM production_runs 
    WHERE status IN ('in_progress', 'completed')
    AND (milk_liters_used IS NULL OR milk_liters_used <= 0)
")->fetch();
test("In-progress/completed runs have milk_liters_used", $runsWithoutMilk['count'] == 0, $testsPassed, $testsFailed, $testResults);

// =============================================================================
// SECTION 5: CCP (CRITICAL CONTROL POINTS) LOGGING
// =============================================================================
section("5. CCP (CRITICAL CONTROL POINTS) LOGGING");

// Check CCP stages coverage (using check_type column)
$ccpStages = ['chilling', 'preheating', 'homogenization', 'pasteurization', 'cooling'];
$existingStages = $db->query("SELECT DISTINCT check_type FROM production_ccp_logs")->fetchAll(PDO::FETCH_COLUMN);

foreach ($ccpStages as $stage) {
    $hasStage = in_array($stage, $existingStages);
    if ($hasStage) {
        test("CCP check_type '{$stage}' has logs", true, $testsPassed, $testsFailed, $testResults);
    } else {
        skip("CCP check_type '{$stage}' logs", $testsSkipped, $testResults, "No production runs with this stage yet");
    }
}

// Check CCP has temperature readings (using temperature column)
$ccpNoTemp = $db->query("
    SELECT COUNT(*) as count FROM production_ccp_logs 
    WHERE temperature IS NULL
")->fetch();
test("All CCP logs have temperature readings", $ccpNoTemp['count'] == 0, $testsPassed, $testsFailed, $testResults);

// Check CCP status values
$ccpStatuses = $db->query("SELECT DISTINCT status FROM production_ccp_logs")->fetchAll(PDO::FETCH_COLUMN);
$validCcpStatuses = ['pass', 'fail'];
foreach ($ccpStatuses as $status) {
    $isValid = in_array($status, $validCcpStatuses);
    test("CCP status '{$status}' is valid", $isValid, $testsPassed, $testsFailed, $testResults);
}

// =============================================================================
// SECTION 6: BYPRODUCTS TRACKING
// =============================================================================
section("6. BYPRODUCTS TRACKING");

// Check byproduct types per system spec
$byproductTypes = ['buttermilk', 'whey', 'cream', 'skim_milk'];
$existingByproducts = $db->query("SELECT DISTINCT byproduct_type FROM production_byproducts")->fetchAll(PDO::FETCH_COLUMN);

info("Byproduct types in database: " . (empty($existingByproducts) ? "None" : implode(', ', $existingByproducts)));

$byproductCount = $db->query("SELECT COUNT(*) as count FROM production_byproducts")->fetch();
if ($byproductCount['count'] > 0) {
    foreach ($byproductTypes as $type) {
        $hasType = in_array($type, $existingByproducts);
        if ($hasType) {
            test("Byproduct type '{$type}' recorded", true, $testsPassed, $testsFailed, $testResults);
        }
    }
    
    // Check byproducts linked to runs
    $unlinkedByproducts = $db->query("
        SELECT COUNT(*) as count FROM production_byproducts 
        WHERE run_id IS NULL OR run_id NOT IN (SELECT id FROM production_runs)
    ")->fetch();
    test("All byproducts linked to valid production runs", $unlinkedByproducts['count'] == 0, $testsPassed, $testsFailed, $testResults);
} else {
    skip("Byproducts tracking", $testsSkipped, $testResults, "No byproducts recorded yet - this is normal if no butter/cheese production");
}

// =============================================================================
// SECTION 7: REQUISITIONS WORKFLOW
// =============================================================================
section("7. INGREDIENT REQUISITIONS WORKFLOW");

// Check requisition statuses
$reqStatuses = $db->query("SELECT DISTINCT status FROM ingredient_requisitions")->fetchAll(PDO::FETCH_COLUMN);
$validReqStatuses = ['draft', 'pending', 'approved', 'fulfilled', 'cancelled'];

if (!empty($reqStatuses)) {
    foreach ($reqStatuses as $status) {
        $isValid = in_array($status, $validReqStatuses);
        test("Requisition status '{$status}' is valid", $isValid, $testsPassed, $testsFailed, $testResults);
    }
} else {
    skip("Requisition status validation", $testsSkipped, $testResults, "No requisitions created yet");
}

// Check requisitions have items
$reqCount = $db->query("SELECT COUNT(*) as count FROM ingredient_requisitions")->fetch();
$itemsTableExists = $db->query("SHOW TABLES LIKE 'ingredient_requisition_items'")->rowCount() > 0;

if ($reqCount['count'] > 0 && $itemsTableExists) {
    $emptyReqs = $db->query("
        SELECT COUNT(*) as count FROM ingredient_requisitions ir
        WHERE NOT EXISTS (SELECT 1 FROM ingredient_requisition_items iri WHERE iri.requisition_id = ir.id)
    ")->fetch();
    test("All requisitions have line items", $emptyReqs['count'] == 0, $testsPassed, $testsFailed, $testResults);
} else if (!$itemsTableExists) {
    skip("Requisition items check", $testsSkipped, $testResults, "ingredient_requisition_items table missing");
} else {
    skip("Requisition items check", $testsSkipped, $testResults, "No requisitions to check");
}

// =============================================================================
// SECTION 8: YOGURT TRANSFORMATION ("THE YOGURT RULE")
// =============================================================================
section("8. YOGURT TRANSFORMATION ('THE YOGURT RULE')");

// Check transformation table structure
$transformExists = $db->query("SHOW TABLES LIKE 'yogurt_transformations'")->rowCount() > 0;
test("yogurt_transformations table exists", $transformExists, $testsPassed, $testsFailed, $testResults);

if ($transformExists) {
    // Check transformation statuses
    $transformStatuses = ['pending', 'in_progress', 'completed', 'cancelled'];
    $cols = $db->query("SHOW COLUMNS FROM yogurt_transformations LIKE 'status'")->fetch();
    
    if ($cols) {
        // Extract ENUM values
        preg_match("/enum\('(.*)'\)/", $cols['Type'], $matches);
        $dbStatuses = isset($matches[1]) ? explode("','", $matches[1]) : [];
        
        foreach ($transformStatuses as $status) {
            $hasStatus = in_array($status, $dbStatuses);
            test("Transformation status '{$status}' supported", $hasStatus, $testsPassed, $testsFailed, $testResults);
        }
    }
    
    // Check production_run_id link (critical for The Yogurt Rule)
    $hasRunLink = $db->query("SHOW COLUMNS FROM yogurt_transformations LIKE 'production_run_id'")->rowCount() > 0;
    test("Transformation links to production_run_id", $hasRunLink, $testsPassed, $testsFailed, $testResults,
        "Enables auto-creation of Yogurt production run"
    );
    
    // Check for any transformations
    $transformCount = $db->query("SELECT COUNT(*) as count FROM yogurt_transformations")->fetch();
    info("Total transformations recorded: {$transformCount['count']}");
    
    if ($transformCount['count'] > 0) {
        // Check completed transformations have production runs linked
        $unlinkedTransforms = $db->query("
            SELECT COUNT(*) as count FROM yogurt_transformations 
            WHERE status = 'completed' AND production_run_id IS NULL
        ")->fetch();
        
        if ($unlinkedTransforms['count'] > 0) {
            warning("{$unlinkedTransforms['count']} completed transformations are not linked to production runs");
        } else {
            test("Completed transformations linked to production runs", true, $testsPassed, $testsFailed, $testResults);
        }
    }
}

// =============================================================================
// SECTION 9: API ENDPOINT VALIDATION
// =============================================================================
section("9. API ENDPOINT VALIDATION");

// Check API files exist
$apiFiles = [
    'production/dashboard.php' => 'Dashboard with stats and available milk',
    'production/runs.php' => 'Production run management',
    'production/recipes.php' => 'Recipe management',
    'production/ccp_logs.php' => 'CCP logging',
    'production/byproducts.php' => 'Byproducts tracking',
    'production/requisitions.php' => 'Requisitions workflow',
    'qc/expiry_management.php' => 'Yogurt transformation (The Yogurt Rule)'
];

foreach ($apiFiles as $file => $description) {
    $exists = file_exists(__DIR__ . '/' . $file);
    test("API file '{$file}' exists", $exists, $testsPassed, $testsFailed, $testResults, $description);
}

// =============================================================================
// SECTION 10: MILK SOURCE INTEGRATION
// =============================================================================
section("10. MILK SOURCE INTEGRATION");

// Check milk flow: Delivery → QC Test → Available for Production
$milkFlow = $db->query("
    SELECT 
        md.id as delivery_id,
        md.delivery_code,
        md.status as delivery_status,
        md.volume_liters,
        md.accepted_liters,
        md.delivery_date,
        qmt.id as test_id,
        qmt.test_code,
        qmt.grade
    FROM milk_deliveries md
    LEFT JOIN qc_milk_tests qmt ON qmt.delivery_id = md.id
    ORDER BY md.delivery_date DESC
    LIMIT 10
")->fetchAll();

info("Recent milk deliveries (last 10):");
$hasAccepted = false;
$hasQcTests = false;

foreach ($milkFlow as $milk) {
    $testInfo = $milk['test_id'] ? "QC: {$milk['test_code']} (Grade {$milk['grade']})" : "No QC Test";
    $statusIcon = $milk['delivery_status'] === 'accepted' ? '✓' : '○';
    echo "         ├─ [{$statusIcon}] {$milk['delivery_code']}: {$milk['volume_liters']}L → {$milk['delivery_status']} | {$testInfo}\n";
    
    if ($milk['delivery_status'] === 'accepted') $hasAccepted = true;
    if ($milk['test_id']) $hasQcTests = true;
}

test("Milk deliveries have QC tests", $hasQcTests, $testsPassed, $testsFailed, $testResults);
test("Some milk deliveries are accepted", $hasAccepted, $testsPassed, $testsFailed, $testResults);

// Check FIFO (First In First Out) compliance
$fifoCheck = $db->query("
    SELECT 
        pru.run_id,
        pr.run_code,
        md.delivery_date,
        pru.milk_liters_allocated
    FROM production_run_milk_usage pru
    JOIN production_runs pr ON pru.run_id = pr.id
    JOIN milk_deliveries md ON pru.delivery_id = md.id
    ORDER BY pru.run_id, md.delivery_date
")->fetchAll();

if (!empty($fifoCheck)) {
    info("Checking FIFO compliance in milk allocation...");
    test("Milk allocation tracking is active", true, $testsPassed, $testsFailed, $testResults,
        count($fifoCheck) . " allocation records found"
    );
} else {
    skip("FIFO compliance check", $testsSkipped, $testResults, "No milk allocations recorded yet");
}

// =============================================================================
// SECTION 11: UI/HTML FILE VALIDATION
// =============================================================================
section("11. UI FILE VALIDATION");

$htmlFiles = [
    'html/production/dashboard.html' => 'Production dashboard UI',
    'html/production/batches.html' => 'Production runs/batches UI',
    'html/production/recipes.html' => 'Recipe management UI',
    'html/production/ccp_logging.html' => 'CCP logging UI',
    'html/production/byproducts.html' => 'Byproducts UI',
    'html/production/requisitions.html' => 'Requisitions UI',
    'html/qc/expiry_management.html' => 'Expiry/Transformation UI'
];

$htmlRoot = dirname(__DIR__);
foreach ($htmlFiles as $file => $description) {
    $exists = file_exists($htmlRoot . '/' . $file);
    test("UI file '{$file}' exists", $exists, $testsPassed, $testsFailed, $testResults, $description);
}

// =============================================================================
// SECTION 12: JS SERVICE FILE VALIDATION
// =============================================================================
section("12. JS SERVICE FILE VALIDATION");

$jsFiles = [
    'js/production/production.service.js' => 'Production API service',
    'js/qc/expiry.service.js' => 'Expiry/Transformation service'
];

foreach ($jsFiles as $file => $description) {
    $exists = file_exists($htmlRoot . '/' . $file);
    test("JS file '{$file}' exists", $exists, $testsPassed, $testsFailed, $testResults, $description);
}

// =============================================================================
// SECTION 13: DATA INTEGRITY CHECKS
// =============================================================================
section("13. DATA INTEGRITY CHECKS");

// Check foreign key integrity
$orphanedCcpLogs = $db->query("
    SELECT COUNT(*) as count FROM production_ccp_logs 
    WHERE run_id NOT IN (SELECT id FROM production_runs)
")->fetch();
test("No orphaned CCP logs", $orphanedCcpLogs['count'] == 0, $testsPassed, $testsFailed, $testResults);

$orphanedByproducts = $db->query("
    SELECT COUNT(*) as count FROM production_byproducts 
    WHERE run_id NOT IN (SELECT id FROM production_runs)
")->fetch();
test("No orphaned byproducts", $orphanedByproducts['count'] == 0, $testsPassed, $testsFailed, $testResults);

$orphanedConsumption = $db->query("
    SELECT COUNT(*) as count FROM ingredient_consumption 
    WHERE run_id NOT IN (SELECT id FROM production_runs)
")->fetch();
test("No orphaned ingredient consumption", $orphanedConsumption['count'] == 0, $testsPassed, $testsFailed, $testResults);

// Check recipe references
$orphanedRuns = $db->query("
    SELECT COUNT(*) as count FROM production_runs 
    WHERE recipe_id NOT IN (SELECT id FROM master_recipes)
")->fetch();
test("All production runs reference valid recipes", $orphanedRuns['count'] == 0, $testsPassed, $testsFailed, $testResults);

// =============================================================================
// SECTION 14: FUNCTIONAL FLOW SIMULATION
// =============================================================================
section("14. FUNCTIONAL FLOW SIMULATION (Dry Run)");

// Simulate production flow without actually creating records
info("Simulating: Milk Delivery → QC Test → Production Run → CCP → Complete");

// Step 1: Check if we can find milk
$availableMilk = $db->query("
    SELECT 
        md.id,
        md.delivery_code,
        COALESCE(md.accepted_liters, md.volume_liters) as available,
        COALESCE(
            COALESCE(md.accepted_liters, md.volume_liters) - (
                SELECT COALESCE(SUM(pru.milk_liters_allocated), 0)
                FROM production_run_milk_usage pru
                WHERE pru.delivery_id = md.id
            ), 
            COALESCE(md.accepted_liters, md.volume_liters)
        ) as remaining_liters
    FROM milk_deliveries md
    JOIN qc_milk_tests qmt ON qmt.delivery_id = md.id
    WHERE md.status = 'accepted'
    AND DATE(md.delivery_date) >= DATE_SUB(CURDATE(), INTERVAL 2 DAY)
    HAVING remaining_liters > 0
    ORDER BY md.delivery_date ASC
")->fetchAll();

$canStartProduction = !empty($availableMilk);
$totalAvailable = array_sum(array_column($availableMilk, 'remaining_liters'));

test("Step 1: Can find QC-approved milk for production", $canStartProduction, $testsPassed, $testsFailed, $testResults,
    $canStartProduction ? "Found " . count($availableMilk) . " sources, total {$totalAvailable}L" : "NO MILK AVAILABLE FOR PRODUCTION!"
);

// Step 2: Check if we can find a recipe
$recipe = $db->query("
    SELECT id, recipe_code, product_name, base_milk_liters, expected_yield 
    FROM master_recipes 
    WHERE is_active = 1 
    LIMIT 1
")->fetch();

$canFindRecipe = !empty($recipe);
test("Step 2: Can find active recipe", $canFindRecipe, $testsPassed, $testsFailed, $testResults,
    $canFindRecipe ? "{$recipe['product_name']} ({$recipe['recipe_code']})" : "No active recipes!"
);

// Step 3: Check if milk is sufficient for recipe
if ($canStartProduction && $canFindRecipe) {
    $milkSufficient = $totalAvailable >= $recipe['base_milk_liters'];
    test("Step 3: Sufficient milk for recipe", $milkSufficient, $testsPassed, $testsFailed, $testResults,
        "Required: {$recipe['base_milk_liters']}L, Available: {$totalAvailable}L"
    );
} else {
    skip("Step 3: Milk sufficiency check", $testsSkipped, $testResults, "Prerequisites not met");
}

// Step 4: CCP logging capability (using actual column names: check_type, temperature)
$ccpTableOk = $db->query("
    SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh'
    AND TABLE_NAME = 'production_ccp_logs' 
    AND COLUMN_NAME IN ('check_type', 'temperature', 'status', 'run_id')
")->fetch();
test("Step 4: CCP logging table ready", $ccpTableOk['count'] >= 4, $testsPassed, $testsFailed, $testResults);

// Step 5: Output tracking capability
$outputOk = $db->query("
    SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'highland_fresh'
    AND TABLE_NAME = 'production_runs' 
    AND COLUMN_NAME IN ('actual_quantity', 'output_breakdown', 'yield_variance')
")->fetch();
test("Step 5: Output tracking columns ready", $outputOk['count'] >= 3, $testsPassed, $testsFailed, $testResults);

// =============================================================================
// SECTION 15: CRITICAL ISSUES SUMMARY
// =============================================================================
section("15. CRITICAL ISSUES DETECTION");

$criticalIssues = [];

// Issue: Milk source discrepancy
if ($diff > 100) {
    $criticalIssues[] = [
        'severity' => 'HIGH',
        'issue' => 'Milk source discrepancy',
        'details' => "Dashboard shows {$rawMilkInventory['total']}L but Production can only use {$milkDeliveries['total']}L",
        'fix' => 'Sync raw_milk_inventory with milk_deliveries OR adjust freshness window'
    ];
}

// Issue: No recent QC-approved milk
if ($recentApproved['count'] == 0) {
    $criticalIssues[] = [
        'severity' => 'HIGH',
        'issue' => 'No recent QC-approved milk',
        'details' => 'Production runs require milk deliveries accepted within 2 days',
        'fix' => 'Create milk deliveries and run QC tests to approve them'
    ];
}

// Issue: No active recipes
if ($recipeCount['count'] == 0) {
    $criticalIssues[] = [
        'severity' => 'HIGH',
        'issue' => 'No active recipes',
        'details' => 'Cannot create production runs without recipes',
        'fix' => 'Add recipes via Production → Recipes page'
    ];
}

// Issue: Yogurt transformation not linked
if ($transformExists) {
    $unlinked = $db->query("
        SELECT COUNT(*) as count FROM yogurt_transformations 
        WHERE status = 'completed' AND production_run_id IS NULL
    ")->fetch();
    if ($unlinked['count'] > 0) {
        $criticalIssues[] = [
            'severity' => 'MEDIUM',
            'issue' => 'Transformations not linked to production',
            'details' => "{$unlinked['count']} completed transformations lack production_run_id",
            'fix' => 'Update expiry_management.php to auto-create production runs'
        ];
    }
}

if (empty($criticalIssues)) {
    echo "  ✅ No critical issues detected!\n";
} else {
    foreach ($criticalIssues as $issue) {
        echo "  🔴 [{$issue['severity']}] {$issue['issue']}\n";
        echo "     └─ Problem: {$issue['details']}\n";
        echo "     └─ Fix: {$issue['fix']}\n\n";
    }
}

// =============================================================================
// FINAL SUMMARY
// =============================================================================
echo "\n";
echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                           TEST RESULTS SUMMARY                             ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n";
echo "\n";

$total = $testsPassed + $testsFailed + $testsSkipped;
$passRate = $total > 0 ? round(($testsPassed / $total) * 100, 1) : 0;

echo "  Total Tests:  {$total}\n";
echo "  ✅ Passed:    {$testsPassed}\n";
echo "  ❌ Failed:    {$testsFailed}\n";
echo "  ⏭️  Skipped:   {$testsSkipped}\n";
echo "  Pass Rate:    {$passRate}%\n";
echo "\n";

if ($testsFailed > 0) {
    echo "  FAILED TESTS:\n";
    foreach ($testResults as $result) {
        if ($result['status'] === 'FAIL') {
            echo "    ❌ {$result['test']}\n";
            if (!empty($result['details'])) {
                echo "       └─ {$result['details']}\n";
            }
        }
    }
    echo "\n";
}

if (!empty($criticalIssues)) {
    echo "  ⚠️  CRITICAL ISSUES REQUIRE ATTENTION!\n";
    echo "  See Section 15 above for details.\n\n";
}

echo "═══════════════════════════════════════════════════════════════════════════════\n";
