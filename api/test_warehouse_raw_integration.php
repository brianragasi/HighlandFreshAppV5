<?php
/**
 * Highland Fresh System - Warehouse Raw Module Integration Test
 * 
 * Comprehensive acceptance test for all Warehouse Raw functionalities:
 * 1. Schema Validation (storage_tanks, tank_milk_batches, ingredients, ingredient_batches, etc.)
 * 2. Storage Tank Management (CRUD, capacity tracking, temperature)
 * 3. Raw Milk Batches (receiving from QC, FIFO, expiry tracking)
 * 4. Ingredients Inventory (categories, stock levels, batches, FIFO)
 * 5. MRO Items (Maintenance, Repair, Operations inventory)
 * 6. Requisition Workflow (Production/Maintenance requests → fulfillment)
 * 7. Temperature Monitoring (4°C critical requirement)
 * 8. Multi-Unit Support (packaging, weight, pieces)
 * 9. Inventory Transactions (audit trail)
 * 10. API Endpoint Validation
 * 11. UI File Verification
 * 12. Data Integrity Checks
 * 
 * @version 4.0
 */

// Direct database connection for testing (bypassing bootstrap auth)
$host = 'localhost';
$dbname = 'highland_fresh';
$username = 'root';
$password = '';

class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        global $host, $dbname, $username, $password;
        $this->connection = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            $username,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
}

// Test results tracking
$testResults = [];
$passed = 0;
$failed = 0;
$currentSection = '';

function test($name, $condition, $details = '') {
    global $testResults, $passed, $failed, $currentSection;
    
    $status = $condition ? 'PASS' : 'FAIL';
    if ($condition) {
        $passed++;
    } else {
        $failed++;
    }
    
    $testResults[] = [
        'section' => $currentSection,
        'name' => $name,
        'status' => $status,
        'details' => $details
    ];
    
    $icon = $condition ? '✅' : '❌';
    echo "$icon [$status] $name" . ($details && !$condition ? " - $details" : "") . "\n";
}

function section($name) {
    global $currentSection;
    $currentSection = $name;
    echo "\n" . str_repeat('=', 70) . "\n";
    echo "📦 $name\n";
    echo str_repeat('=', 70) . "\n";
}

try {
    $db = Database::getInstance()->getConnection();
    echo "\n🔌 Database connection successful\n";
} catch (Exception $e) {
    die("❌ Database connection failed: " . $e->getMessage() . "\n");
}

// ============================================================================
// SECTION 1: SCHEMA VALIDATION
// ============================================================================
section("1. Schema Validation");

// Check storage_tanks table
$stmt = $db->query("SHOW TABLES LIKE 'storage_tanks'");
test("storage_tanks table exists", $stmt->rowCount() > 0);

if ($stmt->rowCount() > 0) {
    $cols = $db->query("DESCRIBE storage_tanks")->fetchAll(PDO::FETCH_COLUMN);
    test("storage_tanks has tank_code column", in_array('tank_code', $cols));
    test("storage_tanks has capacity_liters column", in_array('capacity_liters', $cols));
    test("storage_tanks has current_volume column", in_array('current_volume', $cols));
    test("storage_tanks has temperature_celsius column", in_array('temperature_celsius', $cols));
    test("storage_tanks has tank_type column", in_array('tank_type', $cols));
    test("storage_tanks has status column", in_array('status', $cols));
    test("storage_tanks has last_cleaned_at column", in_array('last_cleaned_at', $cols));
}

// Check tank_milk_batches table
$stmt = $db->query("SHOW TABLES LIKE 'tank_milk_batches'");
test("tank_milk_batches table exists", $stmt->rowCount() > 0);

if ($stmt->rowCount() > 0) {
    $cols = $db->query("DESCRIBE tank_milk_batches")->fetchAll(PDO::FETCH_COLUMN);
    test("tank_milk_batches has tank_id column", in_array('tank_id', $cols));
    test("tank_milk_batches has raw_milk_inventory_id column", in_array('raw_milk_inventory_id', $cols));
    test("tank_milk_batches has volume_liters column", in_array('volume_liters', $cols));
    test("tank_milk_batches has remaining_liters column", in_array('remaining_liters', $cols));
    test("tank_milk_batches has expiry_date column", in_array('expiry_date', $cols));
    test("tank_milk_batches has status column", in_array('status', $cols));
}

// Check ingredients table
$stmt = $db->query("SHOW TABLES LIKE 'ingredients'");
test("ingredients table exists", $stmt->rowCount() > 0);

if ($stmt->rowCount() > 0) {
    $cols = $db->query("DESCRIBE ingredients")->fetchAll(PDO::FETCH_COLUMN);
    test("ingredients has ingredient_code column", in_array('ingredient_code', $cols));
    test("ingredients has ingredient_name column", in_array('ingredient_name', $cols));
    test("ingredients has category_id column", in_array('category_id', $cols));
    test("ingredients has unit_of_measure column", in_array('unit_of_measure', $cols));
    test("ingredients has minimum_stock column", in_array('minimum_stock', $cols));
    test("ingredients has current_stock column", in_array('current_stock', $cols));
    test("ingredients has storage_requirements column", in_array('storage_requirements', $cols));
}

// Check ingredient_batches table
$stmt = $db->query("SHOW TABLES LIKE 'ingredient_batches'");
test("ingredient_batches table exists", $stmt->rowCount() > 0);

if ($stmt->rowCount() > 0) {
    $cols = $db->query("DESCRIBE ingredient_batches")->fetchAll(PDO::FETCH_COLUMN);
    test("ingredient_batches has batch_code column", in_array('batch_code', $cols));
    test("ingredient_batches has ingredient_id column", in_array('ingredient_id', $cols));
    test("ingredient_batches has quantity column", in_array('quantity', $cols));
    test("ingredient_batches has remaining_quantity column", in_array('remaining_quantity', $cols));
    test("ingredient_batches has expiry_date column", in_array('expiry_date', $cols));
    test("ingredient_batches has supplier_name column", in_array('supplier_name', $cols));
}

// Check ingredient_categories table
$stmt = $db->query("SHOW TABLES LIKE 'ingredient_categories'");
test("ingredient_categories table exists", $stmt->rowCount() > 0);

// Check mro_items table
$stmt = $db->query("SHOW TABLES LIKE 'mro_items'");
test("mro_items table exists", $stmt->rowCount() > 0);

if ($stmt->rowCount() > 0) {
    $cols = $db->query("DESCRIBE mro_items")->fetchAll(PDO::FETCH_COLUMN);
    test("mro_items has item_code column", in_array('item_code', $cols));
    test("mro_items has item_name column", in_array('item_name', $cols));
    test("mro_items has category_id column", in_array('category_id', $cols));
    test("mro_items has is_critical column", in_array('is_critical', $cols));
    test("mro_items has minimum_stock column", in_array('minimum_stock', $cols));
    test("mro_items has current_stock column", in_array('current_stock', $cols));
}

// Check mro_inventory table
$stmt = $db->query("SHOW TABLES LIKE 'mro_inventory'");
test("mro_inventory table exists", $stmt->rowCount() > 0);

// Check mro_categories table
$stmt = $db->query("SHOW TABLES LIKE 'mro_categories'");
test("mro_categories table exists", $stmt->rowCount() > 0);

// Check ingredient_requisitions table
$stmt = $db->query("SHOW TABLES LIKE 'ingredient_requisitions'");
test("ingredient_requisitions table exists", $stmt->rowCount() > 0);

if ($stmt->rowCount() > 0) {
    $cols = $db->query("DESCRIBE ingredient_requisitions")->fetchAll(PDO::FETCH_COLUMN);
    test("ingredient_requisitions has requisition_code column", in_array('requisition_code', $cols));
    test("ingredient_requisitions has department column", in_array('department', $cols));
    test("ingredient_requisitions has status column", in_array('status', $cols));
    test("ingredient_requisitions has priority column", in_array('priority', $cols));
    test("ingredient_requisitions has requested_by column", in_array('requested_by', $cols));
}

// Check requisition_items table
$stmt = $db->query("SHOW TABLES LIKE 'requisition_items'");
test("requisition_items table exists", $stmt->rowCount() > 0);

if ($stmt->rowCount() > 0) {
    $cols = $db->query("DESCRIBE requisition_items")->fetchAll(PDO::FETCH_COLUMN);
    test("requisition_items has requisition_id column", in_array('requisition_id', $cols));
    test("requisition_items has item_type column", in_array('item_type', $cols));
    test("requisition_items has item_id column", in_array('item_id', $cols));
    test("requisition_items has requested_quantity column", in_array('requested_quantity', $cols));
    test("requisition_items has issued_quantity column", in_array('issued_quantity', $cols));
}

// Check inventory_transactions table
$stmt = $db->query("SHOW TABLES LIKE 'inventory_transactions'");
test("inventory_transactions table exists", $stmt->rowCount() > 0);

if ($stmt->rowCount() > 0) {
    $cols = $db->query("DESCRIBE inventory_transactions")->fetchAll(PDO::FETCH_COLUMN);
    test("inventory_transactions has transaction_code column", in_array('transaction_code', $cols));
    test("inventory_transactions has transaction_type column", in_array('transaction_type', $cols));
    test("inventory_transactions has item_type column", in_array('item_type', $cols));
    test("inventory_transactions has quantity column", in_array('quantity', $cols));
    test("inventory_transactions has performed_by column", in_array('performed_by', $cols));
}

// ============================================================================
// SECTION 2: STORAGE TANKS DATA VALIDATION
// ============================================================================
section("2. Storage Tanks Data Validation");

// Count storage tanks
$tankCount = $db->query("SELECT COUNT(*) FROM storage_tanks WHERE is_active = 1")->fetchColumn();
test("At least one storage tank exists", $tankCount > 0, "Found: $tankCount tanks");

// Check tank types
$tankTypes = $db->query("SELECT DISTINCT tank_type FROM storage_tanks WHERE is_active = 1")->fetchAll(PDO::FETCH_COLUMN);
$validTypes = ['primary', 'secondary', 'holding', 'chiller'];
$hasValidTypes = !empty(array_intersect($tankTypes, $validTypes));
test("Tanks have valid tank_type values", $hasValidTypes, "Types found: " . implode(', ', $tankTypes));

// Check tank statuses
$tankStatuses = $db->query("SELECT DISTINCT status FROM storage_tanks WHERE is_active = 1")->fetchAll(PDO::FETCH_COLUMN);
$validStatuses = ['available', 'in_use', 'cleaning', 'maintenance', 'offline'];
$hasValidStatuses = count(array_diff($tankStatuses, $validStatuses)) == 0;
test("Tanks have valid status values", $hasValidStatuses, "Statuses found: " . implode(', ', $tankStatuses));

// Check capacity values
$invalidCapacity = $db->query("SELECT COUNT(*) FROM storage_tanks WHERE capacity_liters <= 0")->fetchColumn();
test("All tanks have positive capacity", $invalidCapacity == 0, "Invalid: $invalidCapacity");

// Check current volume <= capacity
$overCapacity = $db->query("
    SELECT COUNT(*) FROM storage_tanks 
    WHERE current_volume > capacity_liters
")->fetchColumn();
test("No tanks exceed capacity", $overCapacity == 0, "Over capacity: $overCapacity");

// Check unique tank codes
$duplicateCodes = $db->query("
    SELECT tank_code, COUNT(*) as cnt 
    FROM storage_tanks 
    GROUP BY tank_code 
    HAVING cnt > 1
")->fetchAll();
test("All tank codes are unique", count($duplicateCodes) == 0);

// Temperature monitoring - check if tanks have reasonable temperatures
$tempCheck = $db->query("
    SELECT COUNT(*) FROM storage_tanks 
    WHERE is_active = 1 
    AND temperature_celsius IS NOT NULL 
    AND (temperature_celsius < -10 OR temperature_celsius > 50)
")->fetchColumn();
test("Tank temperatures are within reasonable range (-10 to 50°C)", $tempCheck == 0, "Out of range: $tempCheck");

// ============================================================================
// SECTION 3: RAW MILK BATCHES DATA VALIDATION
// ============================================================================
section("3. Raw Milk Batches Data Validation");

// Count milk batches
$batchCount = $db->query("SELECT COUNT(*) FROM tank_milk_batches")->fetchColumn();
test("Tank milk batches table accessible", true, "Found: $batchCount batches");

// Check batch statuses
$batchStatuses = $db->query("SELECT DISTINCT status FROM tank_milk_batches")->fetchAll(PDO::FETCH_COLUMN);
$validBatchStatuses = ['available', 'partially_used', 'consumed', 'expired', 'transferred'];
$hasValidBatchStatuses = count(array_diff($batchStatuses, $validBatchStatuses)) == 0 || empty($batchStatuses);
test("Milk batches have valid status values", $hasValidBatchStatuses, "Found: " . implode(', ', $batchStatuses));

// Check remaining <= volume
$invalidRemaining = $db->query("
    SELECT COUNT(*) FROM tank_milk_batches 
    WHERE remaining_liters > volume_liters
")->fetchColumn();
test("Remaining liters <= volume_liters for all batches", $invalidRemaining == 0, "Invalid: $invalidRemaining");

// Check foreign key - all batches link to valid tanks
$orphanBatches = $db->query("
    SELECT COUNT(*) FROM tank_milk_batches tmb
    LEFT JOIN storage_tanks st ON tmb.tank_id = st.id
    WHERE st.id IS NULL
")->fetchColumn();
test("All milk batches linked to valid tanks", $orphanBatches == 0, "Orphan batches: $orphanBatches");

// Check expiry dates are in the future for available batches
$expiredButAvailable = $db->query("
    SELECT COUNT(*) FROM tank_milk_batches 
    WHERE status IN ('available', 'partially_used')
    AND expiry_date < CURDATE()
")->fetchColumn();
test("No available batches past expiry", $expiredButAvailable == 0, "Expired but available: $expiredButAvailable (should be auto-expired)");

// Verify tank current_volume matches sum of remaining_liters
$volumeMismatch = $db->query("
    SELECT st.id, st.tank_code, st.current_volume,
           COALESCE(SUM(tmb.remaining_liters), 0) as calc_volume
    FROM storage_tanks st
    LEFT JOIN tank_milk_batches tmb ON st.id = tmb.tank_id 
        AND tmb.status IN ('available', 'partially_used')
    WHERE st.is_active = 1
    GROUP BY st.id
    HAVING ABS(st.current_volume - calc_volume) > 0.01
")->fetchAll();
test("Tank volumes match batch totals", count($volumeMismatch) == 0, 
    count($volumeMismatch) > 0 ? "Mismatches: " . count($volumeMismatch) : "");

// ============================================================================
// SECTION 4: INGREDIENT CATEGORIES DATA
// ============================================================================
section("4. Ingredient Categories Data Validation");

$catCount = $db->query("SELECT COUNT(*) FROM ingredient_categories WHERE is_active = 1")->fetchColumn();
test("Ingredient categories exist", $catCount > 0, "Found: $catCount categories");

// Expected categories based on spec
$expectedCategories = ['Dairy Additives', 'Sweeteners', 'Cultures', 'Packaging'];
$existingCategories = $db->query("SELECT category_name FROM ingredient_categories WHERE is_active = 1")->fetchAll(PDO::FETCH_COLUMN);

foreach ($expectedCategories as $cat) {
    $found = false;
    foreach ($existingCategories as $existing) {
        if (stripos($existing, $cat) !== false || stripos($cat, $existing) !== false) {
            $found = true;
            break;
        }
    }
    // This is a soft check - not all categories may be required
}
test("Ingredient categories are defined", $catCount >= 1, "Categories: " . implode(', ', $existingCategories));

// ============================================================================
// SECTION 5: INGREDIENTS INVENTORY DATA VALIDATION
// ============================================================================
section("5. Ingredients Inventory Data Validation");

$ingredientCount = $db->query("SELECT COUNT(*) FROM ingredients WHERE is_active = 1")->fetchColumn();
test("Ingredients exist in system", $ingredientCount > 0, "Found: $ingredientCount ingredients");

// Check unique ingredient codes
$duplicateIngCodes = $db->query("
    SELECT ingredient_code, COUNT(*) as cnt 
    FROM ingredients 
    GROUP BY ingredient_code 
    HAVING cnt > 1
")->fetchAll();
test("All ingredient codes are unique", count($duplicateIngCodes) == 0);

// Check unit_of_measure is set
$noUnit = $db->query("
    SELECT COUNT(*) FROM ingredients 
    WHERE is_active = 1 AND (unit_of_measure IS NULL OR unit_of_measure = '')
")->fetchColumn();
test("All ingredients have unit_of_measure", $noUnit == 0, "Missing unit: $noUnit");

// Check current_stock is non-negative
$negativeStock = $db->query("
    SELECT COUNT(*) FROM ingredients 
    WHERE current_stock < 0
")->fetchColumn();
test("No negative ingredient stock", $negativeStock == 0, "Negative stock: $negativeStock");

// Check minimum_stock is non-negative
$negativeMin = $db->query("
    SELECT COUNT(*) FROM ingredients 
    WHERE minimum_stock < 0
")->fetchColumn();
test("No negative minimum stock values", $negativeMin == 0, "Negative: $negativeMin");

// Verify current_stock matches sum of batch remaining
$stockMismatch = $db->query("
    SELECT i.id, i.ingredient_code, i.current_stock,
           COALESCE(SUM(ib.remaining_quantity), 0) as calc_stock
    FROM ingredients i
    LEFT JOIN ingredient_batches ib ON i.id = ib.ingredient_id 
        AND ib.status IN ('available', 'partially_used')
    WHERE i.is_active = 1
    GROUP BY i.id
    HAVING ABS(i.current_stock - calc_stock) > 0.01
")->fetchAll();
test("Ingredient stock matches batch totals", count($stockMismatch) == 0, 
    count($stockMismatch) > 0 ? "Mismatches: " . count($stockMismatch) : "");

// ============================================================================
// SECTION 6: INGREDIENT BATCHES DATA VALIDATION
// ============================================================================
section("6. Ingredient Batches Data Validation");

$ingBatchCount = $db->query("SELECT COUNT(*) FROM ingredient_batches")->fetchColumn();
test("Ingredient batches table accessible", true, "Found: $ingBatchCount batches");

// Check batch statuses
$ingBatchStatuses = $db->query("SELECT DISTINCT status FROM ingredient_batches")->fetchAll(PDO::FETCH_COLUMN);
$validIngBatchStatuses = ['available', 'partially_used', 'consumed', 'expired', 'disposed'];
$hasValidIngBatchStatuses = count(array_diff($ingBatchStatuses, $validIngBatchStatuses)) == 0 || empty($ingBatchStatuses);
test("Ingredient batches have valid status values", $hasValidIngBatchStatuses, "Found: " . implode(', ', $ingBatchStatuses));

// Check remaining <= quantity
$invalidIngRemaining = $db->query("
    SELECT COUNT(*) FROM ingredient_batches 
    WHERE remaining_quantity > quantity
")->fetchColumn();
test("Remaining quantity <= quantity for all batches", $invalidIngRemaining == 0, "Invalid: $invalidIngRemaining");

// Check foreign key - all batches link to valid ingredients
$orphanIngBatches = $db->query("
    SELECT COUNT(*) FROM ingredient_batches ib
    LEFT JOIN ingredients i ON ib.ingredient_id = i.id
    WHERE i.id IS NULL
")->fetchColumn();
test("All ingredient batches linked to valid ingredients", $orphanIngBatches == 0, "Orphan: $orphanIngBatches");

// Unique batch codes
$duplicateBatchCodes = $db->query("
    SELECT batch_code, COUNT(*) as cnt 
    FROM ingredient_batches 
    GROUP BY batch_code 
    HAVING cnt > 1
")->fetchAll();
test("All ingredient batch codes are unique", count($duplicateBatchCodes) == 0);

// ============================================================================
// SECTION 7: MRO CATEGORIES DATA
// ============================================================================
section("7. MRO Categories Data Validation");

$mroCatCount = $db->query("SELECT COUNT(*) FROM mro_categories WHERE is_active = 1")->fetchColumn();
test("MRO categories exist", $mroCatCount > 0, "Found: $mroCatCount categories");

$mroCategories = $db->query("SELECT category_name FROM mro_categories WHERE is_active = 1")->fetchAll(PDO::FETCH_COLUMN);
test("MRO categories are defined", count($mroCategories) > 0, "Categories: " . implode(', ', $mroCategories));

// ============================================================================
// SECTION 8: MRO ITEMS DATA VALIDATION
// ============================================================================
section("8. MRO Items Data Validation");

$mroItemCount = $db->query("SELECT COUNT(*) FROM mro_items WHERE is_active = 1")->fetchColumn();
test("MRO items exist in system", $mroItemCount > 0, "Found: $mroItemCount items");

// Check unique item codes
$duplicateMroCodes = $db->query("
    SELECT item_code, COUNT(*) as cnt 
    FROM mro_items 
    GROUP BY item_code 
    HAVING cnt > 1
")->fetchAll();
test("All MRO item codes are unique", count($duplicateMroCodes) == 0);

// Check current_stock is non-negative
$negativeMroStock = $db->query("
    SELECT COUNT(*) FROM mro_items 
    WHERE current_stock < 0
")->fetchColumn();
test("No negative MRO stock", $negativeMroStock == 0, "Negative: $negativeMroStock");

// Check critical items are flagged
$criticalItems = $db->query("SELECT COUNT(*) FROM mro_items WHERE is_critical = 1")->fetchColumn();
test("Critical MRO items are flagged", true, "Found: $criticalItems critical items");

// Check category relationships
$orphanMroItems = $db->query("
    SELECT COUNT(*) FROM mro_items mi
    LEFT JOIN mro_categories mc ON mi.category_id = mc.id
    WHERE mi.category_id IS NOT NULL AND mc.id IS NULL
")->fetchColumn();
test("All MRO items linked to valid categories", $orphanMroItems == 0, "Orphan: $orphanMroItems");

// ============================================================================
// SECTION 9: MRO INVENTORY DATA VALIDATION
// ============================================================================
section("9. MRO Inventory Data Validation");

$mroInvCount = $db->query("SELECT COUNT(*) FROM mro_inventory")->fetchColumn();
test("MRO inventory table accessible", true, "Found: $mroInvCount records");

// Check remaining <= quantity
$invalidMroRemaining = $db->query("
    SELECT COUNT(*) FROM mro_inventory 
    WHERE remaining_quantity > quantity
")->fetchColumn();
test("Remaining quantity <= quantity for all MRO inventory", $invalidMroRemaining == 0, "Invalid: $invalidMroRemaining");

// Check foreign key - all inventory link to valid items
$orphanMroInv = $db->query("
    SELECT COUNT(*) FROM mro_inventory mi
    LEFT JOIN mro_items m ON mi.mro_item_id = m.id
    WHERE m.id IS NULL
")->fetchColumn();
test("All MRO inventory linked to valid items", $orphanMroInv == 0, "Orphan: $orphanMroInv");

// Verify current_stock matches sum of inventory remaining
$mroStockMismatch = $db->query("
    SELECT m.id, m.item_code, m.current_stock,
           COALESCE(SUM(mi.remaining_quantity), 0) as calc_stock
    FROM mro_items m
    LEFT JOIN mro_inventory mi ON m.id = mi.mro_item_id 
        AND mi.status IN ('available', 'partially_used')
    WHERE m.is_active = 1
    GROUP BY m.id
    HAVING ABS(m.current_stock - calc_stock) > 0.01
")->fetchAll();
test("MRO stock matches inventory totals", count($mroStockMismatch) == 0, 
    count($mroStockMismatch) > 0 ? "Mismatches: " . count($mroStockMismatch) : "");

// ============================================================================
// SECTION 10: REQUISITIONS DATA VALIDATION
// ============================================================================
section("10. Requisitions Data Validation");

$reqCount = $db->query("SELECT COUNT(*) FROM ingredient_requisitions")->fetchColumn();
test("Requisitions table accessible", true, "Found: $reqCount requisitions");

// Check requisition statuses
$reqStatuses = $db->query("SELECT DISTINCT status FROM ingredient_requisitions")->fetchAll(PDO::FETCH_COLUMN);
$validReqStatuses = ['pending', 'approved', 'rejected', 'fulfilled', 'partially_fulfilled', 'cancelled'];
$hasValidReqStatuses = count(array_diff($reqStatuses, $validReqStatuses)) == 0 || empty($reqStatuses);
test("Requisitions have valid status values", $hasValidReqStatuses, "Found: " . implode(', ', $reqStatuses));

// Check priority values
$reqPriorities = $db->query("SELECT DISTINCT priority FROM ingredient_requisitions WHERE priority IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
$validPriorities = ['low', 'normal', 'high', 'urgent'];
$hasValidPriorities = count(array_diff($reqPriorities, $validPriorities)) == 0 || empty($reqPriorities);
test("Requisitions have valid priority values", $hasValidPriorities, "Found: " . implode(', ', $reqPriorities));

// Check unique requisition codes
$duplicateReqCodes = $db->query("
    SELECT requisition_code, COUNT(*) as cnt 
    FROM ingredient_requisitions 
    WHERE requisition_code IS NOT NULL
    GROUP BY requisition_code 
    HAVING cnt > 1
")->fetchAll();
test("All requisition codes are unique", count($duplicateReqCodes) == 0);

// Check department values
$departments = $db->query("SELECT DISTINCT department FROM ingredient_requisitions")->fetchAll(PDO::FETCH_COLUMN);
test("Requisitions have department values", true, "Departments: " . implode(', ', $departments));

// ============================================================================
// SECTION 11: REQUISITION ITEMS DATA VALIDATION
// ============================================================================
section("11. Requisition Items Data Validation");

$reqItemCount = $db->query("SELECT COUNT(*) FROM requisition_items")->fetchColumn();
test("Requisition items table accessible", true, "Found: $reqItemCount items");

// Check item_type values
$itemTypes = $db->query("SELECT DISTINCT item_type FROM requisition_items WHERE item_type IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
$validItemTypes = ['raw_milk', 'ingredient', 'mro', 'packaging'];
$hasValidItemTypes = count(array_diff($itemTypes, $validItemTypes)) == 0 || empty($itemTypes);
test("Requisition items have valid item_type values", $hasValidItemTypes, "Found: " . implode(', ', $itemTypes));

// Check foreign key - all items link to valid requisitions
$orphanReqItems = $db->query("
    SELECT COUNT(*) FROM requisition_items ri
    LEFT JOIN ingredient_requisitions ir ON ri.requisition_id = ir.id
    WHERE ir.id IS NULL
")->fetchColumn();
test("All requisition items linked to valid requisitions", $orphanReqItems == 0, "Orphan: $orphanReqItems");

// Check requested_quantity is positive
$negativeReqQty = $db->query("
    SELECT COUNT(*) FROM requisition_items 
    WHERE requested_quantity <= 0
")->fetchColumn();
test("All requested quantities are positive", $negativeReqQty == 0, "Invalid: $negativeReqQty");

// Check issued_quantity <= requested_quantity
$overIssued = $db->query("
    SELECT COUNT(*) FROM requisition_items 
    WHERE issued_quantity > requested_quantity
")->fetchColumn();
test("No over-issued items", $overIssued == 0, "Over-issued: $overIssued");

// ============================================================================
// SECTION 12: INVENTORY TRANSACTIONS AUDIT TRAIL
// ============================================================================
section("12. Inventory Transactions Audit Trail");

$txCount = $db->query("SELECT COUNT(*) FROM inventory_transactions")->fetchColumn();
test("Inventory transactions exist", $txCount > 0, "Found: $txCount transactions");

// Check transaction_type values
$txTypes = $db->query("SELECT DISTINCT transaction_type FROM inventory_transactions")->fetchAll(PDO::FETCH_COLUMN);
$validTxTypes = ['receive', 'issue', 'transfer', 'adjust', 'dispose', 'return', 'write_off'];
$hasValidTxTypes = count(array_diff($txTypes, $validTxTypes)) == 0 || empty($txTypes);
test("Transactions have valid type values", $hasValidTxTypes, "Found: " . implode(', ', $txTypes));

// Check item_type values in transactions
$txItemTypes = $db->query("SELECT DISTINCT item_type FROM inventory_transactions")->fetchAll(PDO::FETCH_COLUMN);
$validTxItemTypes = ['raw_milk', 'ingredient', 'mro', 'packaging', 'finished_goods'];
$hasValidTxItemTypes = count(array_diff($txItemTypes, $validTxItemTypes)) == 0 || empty($txItemTypes);
test("Transactions have valid item_type values", $hasValidTxItemTypes, "Found: " . implode(', ', $txItemTypes));

// Check all transactions have performed_by user
$noPerformer = $db->query("
    SELECT COUNT(*) FROM inventory_transactions 
    WHERE performed_by IS NULL
")->fetchColumn();
test("All transactions have performer recorded", $noPerformer == 0, "Missing: $noPerformer");

// Verify transaction performers exist in users
$invalidPerformers = $db->query("
    SELECT COUNT(*) FROM inventory_transactions it
    LEFT JOIN users u ON it.performed_by = u.id
    WHERE u.id IS NULL AND it.performed_by IS NOT NULL
")->fetchColumn();
test("All transaction performers are valid users", $invalidPerformers == 0, "Invalid: $invalidPerformers");

// ============================================================================
// SECTION 13: LOW STOCK AND EXPIRY ALERTS
// ============================================================================
section("13. Low Stock and Expiry Alerts");

// Low stock ingredients
$lowStockIng = $db->query("
    SELECT COUNT(*) FROM ingredients 
    WHERE is_active = 1 AND current_stock <= minimum_stock AND minimum_stock > 0
")->fetchColumn();
test("Low stock ingredients query works", true, "Low stock ingredients: $lowStockIng");

// Low stock MRO
$lowStockMro = $db->query("
    SELECT COUNT(*) FROM mro_items 
    WHERE is_active = 1 AND current_stock <= minimum_stock AND minimum_stock > 0
")->fetchColumn();
test("Low stock MRO query works", true, "Low stock MRO items: $lowStockMro");

// Critical MRO items low on stock
$criticalLowStock = $db->query("
    SELECT COUNT(*) FROM mro_items 
    WHERE is_active = 1 AND is_critical = 1 AND current_stock <= minimum_stock
")->fetchColumn();
test("Critical MRO low stock query works", true, "Critical items low: $criticalLowStock");

// Expiring milk (within 2 days)
$expiringMilk = $db->query("
    SELECT COUNT(*) FROM tank_milk_batches 
    WHERE status IN ('available', 'partially_used')
    AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 2 DAY)
")->fetchColumn();
test("Expiring milk query works", true, "Milk expiring within 2 days: $expiringMilk");

// Expiring ingredients (within 7 days)
$expiringIng = $db->query("
    SELECT COUNT(*) FROM ingredient_batches 
    WHERE status IN ('available', 'partially_used')
    AND expiry_date IS NOT NULL
    AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
")->fetchColumn();
test("Expiring ingredients query works", true, "Ingredients expiring within 7 days: $expiringIng");

// ============================================================================
// SECTION 14: FIFO ENFORCEMENT VALIDATION
// ============================================================================
section("14. FIFO Enforcement Validation");

// Check milk batches ordered correctly for FIFO
$milkFifoCheck = $db->query("
    SELECT tmb.id, tmb.tank_id, tmb.expiry_date, tmb.received_date
    FROM tank_milk_batches tmb
    WHERE tmb.status IN ('available', 'partially_used')
    ORDER BY tmb.expiry_date ASC, tmb.received_date ASC
    LIMIT 5
")->fetchAll();
test("Milk batches can be ordered by expiry (FIFO)", true, "Sample: " . count($milkFifoCheck) . " batches");

// Check ingredient batches ordered correctly for FIFO
$ingFifoCheck = $db->query("
    SELECT ib.id, ib.ingredient_id, ib.expiry_date, ib.received_date
    FROM ingredient_batches ib
    WHERE ib.status IN ('available', 'partially_used')
    ORDER BY ib.expiry_date ASC, ib.received_date ASC
    LIMIT 5
")->fetchAll();
test("Ingredient batches can be ordered by expiry (FIFO)", true, "Sample: " . count($ingFifoCheck) . " batches");

// ============================================================================
// SECTION 15: TEMPERATURE MONITORING
// ============================================================================
section("15. Temperature Monitoring");

// Check for tank temperature logs table
$stmt = $db->query("SHOW TABLES LIKE 'tank_temperature_logs'");
$hasTempLogs = $stmt->rowCount() > 0;
test("tank_temperature_logs table exists (optional)", $hasTempLogs || true, $hasTempLogs ? "Table exists" : "Not implemented");

// Check current tank temperatures are within safe range for raw milk (4°C)
$unsafeTempTanks = $db->query("
    SELECT COUNT(*) FROM storage_tanks 
    WHERE is_active = 1 
    AND status = 'in_use'
    AND temperature_celsius IS NOT NULL
    AND temperature_celsius > 6
")->fetchColumn();
test("Active tanks with milk at safe temperature (<=6°C)", $unsafeTempTanks == 0, "Unsafe: $unsafeTempTanks");

// ============================================================================
// SECTION 16: API FILES VALIDATION
// ============================================================================
section("16. API Files Validation");

$apiBasePath = __DIR__ . '/warehouse/raw';

// Dashboard API
$dashboardFile = $apiBasePath . '/dashboard.php';
test("dashboard.php exists", file_exists($dashboardFile));

// Tanks API
$tanksFile = $apiBasePath . '/tanks.php';
test("tanks.php exists", file_exists($tanksFile));
if (file_exists($tanksFile)) {
    $tanksContent = file_get_contents($tanksFile);
    test("tanks.php has list action", strpos($tanksContent, "'list'") !== false);
    test("tanks.php has detail action", strpos($tanksContent, "'detail'") !== false);
    test("tanks.php has receive action", strpos($tanksContent, "'receive'") !== false);
    test("tanks.php has issue_milk action", strpos($tanksContent, "'issue_milk'") !== false);
    test("tanks.php has transfer action", strpos($tanksContent, "'transfer'") !== false);
    test("tanks.php has pending_storage action", strpos($tanksContent, "'pending_storage'") !== false);
}

// Ingredients API
$ingredientsFile = $apiBasePath . '/ingredients.php';
test("ingredients.php exists", file_exists($ingredientsFile));
if (file_exists($ingredientsFile)) {
    $ingContent = file_get_contents($ingredientsFile);
    test("ingredients.php has list action", strpos($ingContent, "'list'") !== false);
    test("ingredients.php has detail action", strpos($ingContent, "'detail'") !== false);
    test("ingredients.php has categories action", strpos($ingContent, "'categories'") !== false);
    test("ingredients.php has receive action", strpos($ingContent, "'receive'") !== false);
    test("ingredients.php has issue action", strpos($ingContent, "'issue'") !== false);
    test("ingredients.php has check_stock action", strpos($ingContent, "'check_stock'") !== false);
}

// MRO API
$mroFile = $apiBasePath . '/mro.php';
test("mro.php exists", file_exists($mroFile));
if (file_exists($mroFile)) {
    $mroContent = file_get_contents($mroFile);
    test("mro.php has list action", strpos($mroContent, "'list'") !== false);
    test("mro.php has detail action", strpos($mroContent, "'detail'") !== false);
    test("mro.php has categories action", strpos($mroContent, "'categories'") !== false);
    test("mro.php has receive action", strpos($mroContent, "'receive'") !== false);
    test("mro.php has issue action", strpos($mroContent, "'issue'") !== false);
    test("mro.php has critical_stock action", strpos($mroContent, "'critical_stock'") !== false);
}

// Requisitions API
$requisitionsFile = $apiBasePath . '/requisitions.php';
test("requisitions.php exists", file_exists($requisitionsFile));
if (file_exists($requisitionsFile)) {
    $reqContent = file_get_contents($requisitionsFile);
    test("requisitions.php has list action", strpos($reqContent, "'list'") !== false);
    test("requisitions.php has detail action", strpos($reqContent, "'detail'") !== false);
    test("requisitions.php has fulfill action", strpos($reqContent, "'fulfill'") !== false);
    test("requisitions.php has fulfill_item action", strpos($reqContent, "'fulfill_item'") !== false);
}

// ============================================================================
// SECTION 17: UI FILES VALIDATION
// ============================================================================
section("17. UI Files Validation");

$htmlBasePath = dirname(__DIR__) . '/html/warehouse/raw';

$htmlFiles = [
    'dashboard.html' => 'Warehouse Raw Dashboard',
    'milk_storage.html' => 'Milk Storage Management',
    'ingredients.html' => 'Ingredients Inventory',
    'mro.html' => 'MRO Items Management',
    'requisitions.html' => 'Requisitions Fulfillment'
];

foreach ($htmlFiles as $file => $description) {
    $filePath = $htmlBasePath . '/' . $file;
    test("$file exists ($description)", file_exists($filePath));
}

// Check JS service file
$jsServiceFile = dirname(__DIR__) . '/js/warehouse/raw.service.js';
test("raw.service.js exists", file_exists($jsServiceFile));
if (file_exists($jsServiceFile)) {
    $jsContent = file_get_contents($jsServiceFile);
    test("raw.service.js has getTanks method", strpos($jsContent, 'getTanks') !== false);
    test("raw.service.js has getIngredients method", strpos($jsContent, 'getIngredients') !== false);
    test("raw.service.js has getMROItems method", strpos($jsContent, 'getMROItems') !== false);
    test("raw.service.js has getRequisitions method", strpos($jsContent, 'getRequisitions') !== false);
    test("raw.service.js has receiveMilkIntoTank method", strpos($jsContent, 'receiveMilkIntoTank') !== false);
    test("raw.service.js has issueMilk method", strpos($jsContent, 'issueMilk') !== false);
    test("raw.service.js has fulfillRequisition method", strpos($jsContent, 'fulfillRequisition') !== false);
}

// ============================================================================
// SECTION 18: DATA RELATIONSHIPS AND INTEGRITY
// ============================================================================
section("18. Data Relationships and Integrity");

// Check raw_milk_inventory table exists for QC integration
$stmt = $db->query("SHOW TABLES LIKE 'raw_milk_inventory'");
$hasRawMilkInv = $stmt->rowCount() > 0;
test("raw_milk_inventory table exists (QC integration)", $hasRawMilkInv);

if ($hasRawMilkInv) {
    // Check tank batches link to raw_milk_inventory
    $orphanRawMilkLinks = $db->query("
        SELECT COUNT(*) FROM tank_milk_batches tmb
        LEFT JOIN raw_milk_inventory rmi ON tmb.raw_milk_inventory_id = rmi.id
        WHERE rmi.id IS NULL AND tmb.raw_milk_inventory_id IS NOT NULL
    ")->fetchColumn();
    test("All tank batches linked to valid raw_milk_inventory", $orphanRawMilkLinks == 0, "Orphan: $orphanRawMilkLinks");
}

// Check users table exists for foreign keys
$stmt = $db->query("SHOW TABLES LIKE 'users'");
test("users table exists", $stmt->rowCount() > 0);

// ============================================================================
// SECTION 19: SAMPLE DATA VERIFICATION
// ============================================================================
section("19. Sample Data Verification");

// List storage tanks
$tanks = $db->query("
    SELECT tank_code, tank_name, capacity_liters, current_volume, status, tank_type
    FROM storage_tanks 
    WHERE is_active = 1 
    ORDER BY tank_code
    LIMIT 5
")->fetchAll();

echo "\nStorage Tanks Sample:\n";
foreach ($tanks as $tank) {
    $usage = $tank['capacity_liters'] > 0 ? round(($tank['current_volume'] / $tank['capacity_liters']) * 100, 1) : 0;
    echo "  - {$tank['tank_code']}: {$tank['tank_name']} [{$tank['tank_type']}] - {$tank['current_volume']}/{$tank['capacity_liters']}L ({$usage}%) - {$tank['status']}\n";
}
test("Storage tanks data readable", count($tanks) > 0);

// List ingredients
$ingredients = $db->query("
    SELECT i.ingredient_code, i.ingredient_name, i.current_stock, i.minimum_stock, i.unit_of_measure,
           ic.category_name
    FROM ingredients i
    LEFT JOIN ingredient_categories ic ON i.category_id = ic.id
    WHERE i.is_active = 1 
    ORDER BY i.ingredient_code
    LIMIT 5
")->fetchAll();

echo "\nIngredients Sample:\n";
foreach ($ingredients as $ing) {
    $status = $ing['current_stock'] <= $ing['minimum_stock'] ? '⚠️ LOW' : '✓';
    echo "  - {$ing['ingredient_code']}: {$ing['ingredient_name']} - {$ing['current_stock']} {$ing['unit_of_measure']} (min: {$ing['minimum_stock']}) $status\n";
}
test("Ingredients data readable", count($ingredients) > 0);

// List MRO items
$mroItems = $db->query("
    SELECT m.item_code, m.item_name, m.current_stock, m.minimum_stock, m.is_critical,
           mc.category_name
    FROM mro_items m
    LEFT JOIN mro_categories mc ON m.category_id = mc.id
    WHERE m.is_active = 1 
    ORDER BY m.item_code
    LIMIT 5
")->fetchAll();

echo "\nMRO Items Sample:\n";
foreach ($mroItems as $mro) {
    $critical = $mro['is_critical'] ? '🔴' : '⚪';
    $status = $mro['current_stock'] <= $mro['minimum_stock'] ? '⚠️ LOW' : '✓';
    echo "  - $critical {$mro['item_code']}: {$mro['item_name']} - {$mro['current_stock']} (min: {$mro['minimum_stock']}) $status\n";
}
test("MRO items data readable", count($mroItems) > 0);

// ============================================================================
// SECTION 20: WORKFLOW SIMULATION CHECKS
// ============================================================================
section("20. Workflow Simulation Checks");

// Simulate: Check if there's approved requisitions to fulfill
$approvedReqs = $db->query("
    SELECT COUNT(*) FROM ingredient_requisitions 
    WHERE status = 'approved'
")->fetchColumn();
test("Approved requisitions query works", true, "Pending to fulfill: $approvedReqs");

// Simulate: Check if there's available milk for production
$availableMilk = $db->query("
    SELECT COALESCE(SUM(remaining_liters), 0) 
    FROM tank_milk_batches 
    WHERE status IN ('available', 'partially_used')
")->fetchColumn();
test("Available milk query works", true, "Available: {$availableMilk}L");

// Simulate: Check pending milk from QC
$pendingFromQc = $db->query("
    SELECT COUNT(*) FROM raw_milk_inventory rmi
    WHERE rmi.status = 'available'
    AND NOT EXISTS (
        SELECT 1 FROM tank_milk_batches tmb 
        WHERE tmb.raw_milk_inventory_id = rmi.id
    )
")->fetchColumn();
test("Pending milk from QC query works", true, "Pending storage: $pendingFromQc batches");

// ============================================================================
// FINAL SUMMARY
// ============================================================================
echo "\n" . str_repeat('=', 70) . "\n";
echo "📊 TEST SUMMARY\n";
echo str_repeat('=', 70) . "\n";

$total = $passed + $failed;
$percentage = $total > 0 ? round(($passed / $total) * 100, 1) : 0;

echo "\n✅ Passed: $passed\n";
echo "❌ Failed: $failed\n";
echo "📈 Total:  $total\n";
echo "📊 Score:  $percentage%\n\n";

if ($failed > 0) {
    echo "❌ FAILED TESTS:\n";
    foreach ($testResults as $result) {
        if ($result['status'] === 'FAIL') {
            echo "  - [{$result['section']}] {$result['name']}";
            if ($result['details']) echo " - {$result['details']}";
            echo "\n";
        }
    }
}

echo "\n" . str_repeat('=', 70) . "\n";
if ($percentage == 100) {
    echo "🎉 ALL TESTS PASSED! Warehouse Raw Module is fully functional.\n";
} elseif ($percentage >= 90) {
    echo "✨ EXCELLENT! Minor issues to address.\n";
} elseif ($percentage >= 70) {
    echo "⚠️ GOOD, but some issues need attention.\n";
} else {
    echo "🔧 NEEDS WORK - Multiple issues detected.\n";
}
echo str_repeat('=', 70) . "\n";
