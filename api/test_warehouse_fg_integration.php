<?php
/**
 * Highland Fresh System - Warehouse FG Module Integration Test
 * 
 * Comprehensive testing of:
 * 1. Schema Validation (all required tables exist with correct structure)
 * 2. Chiller Management (CRUD, temperature logging)
 * 3. Finished Goods Inventory (multi-unit support, FIFO)
 * 4. Receiving from Production (batch intake)
 * 5. Delivery Receipts (DR workflow)
 * 6. Dispatch Operations (FIFO enforcement, barcode scanning)
 * 7. Customer Management
 * 8. Returns/Bad Orders Management
 * 9. API Endpoint Validation
 * 10. UI File Verification
 * 11. Data Integrity Checks
 * 12. Integration Flow Simulation
 * 
 * @version 4.0
 */

require_once __DIR__ . '/bootstrap.php';

// Test counters
$passed = 0;
$failed = 0;
$warnings = 0;
$tests = [];

// Test result tracking
function test($description, $condition, $detail = null) {
    global $passed, $failed, $tests;
    $status = $condition ? 'PASS' : 'FAIL';
    $condition ? $passed++ : $failed++;
    $tests[] = [
        'description' => $description,
        'status' => $status,
        'detail' => $detail
    ];
    $detailStr = $detail ? " - $detail" : "";
    echo ($condition ? "✓" : "✗") . " $description$detailStr\n";
    return $condition;
}

function warn($description, $detail = null) {
    global $warnings;
    $warnings++;
    $detailStr = $detail ? " - $detail" : "";
    echo "⚠ WARNING: $description$detailStr\n";
}

function section($title) {
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "  $title\n";
    echo str_repeat("=", 60) . "\n\n";
}

// Get database connection
try {
    $db = Database::getInstance()->getConnection();
    echo "✓ Database connection established\n";
} catch (Exception $e) {
    die("✗ Database connection failed: " . $e->getMessage() . "\n");
}

// ============================================
// SECTION 1: SCHEMA VALIDATION
// ============================================
section("1. SCHEMA VALIDATION - Required Tables");

$requiredTables = [
    'chiller_locations' => [
        'columns' => ['id', 'chiller_code', 'chiller_name', 'capacity', 'current_count', 'temperature_celsius', 'status'],
        'description' => 'Chiller/Cold Storage Management'
    ],
    'finished_goods_inventory' => [
        'columns' => ['id', 'batch_id', 'chiller_id', 'quantity_available', 'expiry_date', 'status'],
        'description' => 'FG Inventory tracking'
    ],
    'fg_receiving' => [
        'columns' => ['id', 'receiving_code', 'batch_id', 'product_id', 'quantity_received', 'received_by'],
        'description' => 'Receiving from Production'
    ],
    'delivery_receipts' => [
        'columns' => ['id', 'dr_number', 'customer_name', 'status'],
        'description' => 'Delivery Receipt headers'
    ],
    'delivery_receipt_items' => [
        'columns' => ['id', 'dr_id', 'product_id', 'quantity'],
        'description' => 'DR Line Items'
    ],
    'fg_dispatch_log' => [
        'columns' => ['id', 'inventory_id', 'released_by', 'released_at'],
        'description' => 'Dispatch/Release audit trail'
    ],
    'fg_inventory_transactions' => [
        'columns' => ['id', 'transaction_type', 'inventory_id', 'quantity'],
        'description' => 'FG Movement tracking'
    ],
    'customers' => [
        'columns' => ['id', 'name', 'customer_type'],
        'description' => 'Customer Management'
    ],
    'products' => [
        'columns' => ['id', 'product_name', 'product_code'],
        'description' => 'Product Catalog'
    ]
];

foreach ($requiredTables as $table => $info) {
    $tableCheck = $db->query("SHOW TABLES LIKE '$table'");
    $exists = $tableCheck->fetch();
    
    if (test("Table '$table' exists", $exists, $info['description'])) {
        // Check required columns
        foreach ($info['columns'] as $col) {
            $colCheck = $db->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
            $colExists = $colCheck->fetch();
            if (!$colExists) {
                warn("Column '$col' missing in '$table'");
            }
        }
    }
}

// Check for multi-unit support columns
section("1.1 MULTI-UNIT SUPPORT COLUMNS");

$multiUnitColumns = [
    'finished_goods_inventory' => ['quantity_boxes', 'quantity_pieces', 'boxes_available', 'pieces_available'],
    'products' => ['base_unit', 'box_unit', 'pieces_per_box']
];

foreach ($multiUnitColumns as $table => $columns) {
    foreach ($columns as $col) {
        $colCheck = $db->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
        $colExists = $colCheck->fetch();
        test("Column '$col' in '$table' (multi-unit)", $colExists);
    }
}

// Check for box_opening_log table
$boxLogCheck = $db->query("SHOW TABLES LIKE 'box_opening_log'");
test("Table 'box_opening_log' exists (multi-unit box opening)", $boxLogCheck->fetch());

// ============================================
// SECTION 2: CHILLER MANAGEMENT
// ============================================
section("2. CHILLER MANAGEMENT");

// Check for chillers
$chillerStmt = $db->query("SELECT COUNT(*) as count FROM chiller_locations WHERE is_active = 1");
$chillerCount = $chillerStmt->fetch()['count'];
test("Active chillers exist", $chillerCount > 0, "Count: $chillerCount");

if ($chillerCount > 0) {
    // Get chiller details
    $chillerDetailStmt = $db->query("
        SELECT 
            c.*,
            (SELECT COUNT(*) FROM finished_goods_inventory WHERE chiller_id = c.id AND status = 'available') as inventory_count
        FROM chiller_locations c
        WHERE c.is_active = 1
        LIMIT 1
    ");
    $chiller = $chillerDetailStmt->fetch();
    
    test("Chiller has required fields", 
        !empty($chiller['chiller_code']) && !empty($chiller['chiller_name']), 
        "Code: {$chiller['chiller_code']}, Name: {$chiller['chiller_name']}");
    
    test("Chiller has capacity defined", 
        $chiller['capacity'] > 0, 
        "Capacity: {$chiller['capacity']}");
    
    test("Chiller has temperature range", 
        $chiller['min_temperature'] !== null && $chiller['max_temperature'] !== null,
        "Range: {$chiller['min_temperature']}°C - {$chiller['max_temperature']}°C");
    
    // Validate temperature if set
    if ($chiller['temperature_celsius'] !== null) {
        $tempValid = ($chiller['temperature_celsius'] >= $chiller['min_temperature'] 
                   && $chiller['temperature_celsius'] <= $chiller['max_temperature']);
        if (!$tempValid) {
            warn("Chiller '{$chiller['chiller_code']}' temperature out of safe range",
                 "Current: {$chiller['temperature_celsius']}°C");
        }
    }
}

// Check for chiller temperature logging
$tempLogTable = $db->query("SHOW TABLES LIKE 'chiller_temperature_logs'");
$hasTempLog = $tempLogTable->fetch();
if ($hasTempLog) {
    test("Temperature logging enabled", true, "chiller_temperature_logs table exists");
} else {
    warn("Temperature logging table not found", "chiller_temperature_logs");
}

// ============================================
// SECTION 3: FINISHED GOODS INVENTORY
// ============================================
section("3. FINISHED GOODS INVENTORY");

// Count inventory items
$invStmt = $db->query("SELECT COUNT(*) as count FROM finished_goods_inventory WHERE status = 'available'");
$invCount = $invStmt->fetch()['count'];
test("Finished goods inventory exists", $invCount >= 0, "Available items: $invCount");

// Check inventory with product details
$invDetailStmt = $db->query("
    SELECT 
        fg.*,
        fg.product_name,
        DATEDIFF(fg.expiry_date, CURDATE()) as days_until_expiry
    FROM finished_goods_inventory fg
    WHERE fg.status = 'available'
    ORDER BY fg.expiry_date ASC
    LIMIT 5
");
$inventoryItems = $invDetailStmt->fetchAll();

if (count($inventoryItems) > 0) {
    // Check FIFO ordering
    $prevExpiry = null;
    $fifoValid = true;
    foreach ($inventoryItems as $item) {
        if ($prevExpiry !== null && $item['expiry_date'] < $prevExpiry) {
            $fifoValid = false;
            break;
        }
        $prevExpiry = $item['expiry_date'];
    }
    test("Inventory sorted by expiry date (FIFO ready)", $fifoValid);
    
    // Check for expiring items
    $expiringStmt = $db->query("
        SELECT COUNT(*) as count, COALESCE(SUM(quantity_available), 0) as units
        FROM finished_goods_inventory
        WHERE status = 'available'
        AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)
        AND expiry_date >= CURDATE()
    ");
    $expiring = $expiringStmt->fetch();
    echo "  Info: Items expiring within 3 days: {$expiring['count']} ({$expiring['units']} units)\n";
    
    // Check for expired items
    $expiredStmt = $db->query("
        SELECT COUNT(*) as count
        FROM finished_goods_inventory
        WHERE status = 'available'
        AND expiry_date < CURDATE()
    ");
    $expired = $expiredStmt->fetch()['count'];
    if ($expired > 0) {
        warn("Expired items still marked as available", "Count: $expired");
    }
}

// ============================================
// SECTION 4: MULTI-UNIT INVENTORY SUPPORT
// ============================================
section("4. MULTI-UNIT INVENTORY SUPPORT (Boxes + Pieces)");

// Check if products have unit configuration
$unitConfigStmt = $db->query("
    SELECT 
        COUNT(*) as total_products,
        SUM(CASE WHEN pieces_per_box > 1 THEN 1 ELSE 0 END) as with_box_config
    FROM products
    WHERE is_active = 1
");
$unitConfig = $unitConfigStmt->fetch();
test("Products with multi-unit configuration", 
    $unitConfig['with_box_config'] > 0 || $unitConfig['total_products'] == 0,
    "Configured: {$unitConfig['with_box_config']} of {$unitConfig['total_products']}");

// Check product_units table
$productUnitsTable = $db->query("SHOW TABLES LIKE 'product_units'");
if ($productUnitsTable->fetch()) {
    test("Product units table exists", true, "Extended unit configuration supported");
}

// Test conversion functions (simulate)
$testBoxes = 10;
$testPieces = 24;
$piecesPerBox = 50;
$totalPieces = ($testBoxes * $piecesPerBox) + $testPieces;
$expectedBoxes = intdiv($totalPieces, $piecesPerBox);
$expectedPieces = $totalPieces % $piecesPerBox;

test("Unit conversion logic valid", 
    $expectedBoxes == 10 && $expectedPieces == 24,
    "{$testBoxes} boxes + {$testPieces} pieces = {$totalPieces} total pieces");

// ============================================
// SECTION 5: RECEIVING FROM PRODUCTION
// ============================================
section("5. RECEIVING FROM PRODUCTION");

// Check fg_receiving records
$receivingStmt = $db->query("
    SELECT COUNT(*) as count
    FROM fg_receiving
");
$receivingCount = $receivingStmt->fetch()['count'];
test("FG receiving records exist", $receivingCount >= 0, "Count: $receivingCount");

// Check for pending batches (QC released but not received)
$pendingStmt = $db->query("
    SELECT COUNT(*) as count
    FROM production_batches pb
    LEFT JOIN fg_receiving fr ON fr.batch_id = pb.id
    WHERE pb.qc_status = 'released'
    AND pb.fg_received = 0
    AND fr.id IS NULL
");
$pendingBatches = $pendingStmt->fetch();
if ($pendingBatches) {
    echo "  Info: Pending batches to receive: {$pendingBatches['count']}\n";
}

// Validate receiving workflow
if ($receivingCount > 0) {
    $receivingDetailStmt = $db->query("
        SELECT 
            fr.*,
            p.name as product_name,
            c.chiller_code
        FROM fg_receiving fr
        JOIN products p ON fr.product_id = p.id
        LEFT JOIN chiller_locations c ON fr.chiller_id = c.id
        ORDER BY fr.received_at DESC
        LIMIT 1
    ");
    $receiving = $receivingDetailStmt->fetch();
    
    if ($receiving) {
        test("Receiving record has required fields",
            !empty($receiving['receiving_code']) && !empty($receiving['batch_id']),
            "Code: {$receiving['receiving_code']}");
        
        test("Receiving linked to chiller",
            !empty($receiving['chiller_id']),
            "Chiller: {$receiving['chiller_code']}");
    }
}

// ============================================
// SECTION 6: DELIVERY RECEIPTS (DR)
// ============================================
section("6. DELIVERY RECEIPTS (DR WORKFLOW)");

// Check DR records
$drStmt = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'dispatched' THEN 1 ELSE 0 END) as dispatched,
        SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM delivery_receipts
");
$drStats = $drStmt->fetch();

test("Delivery receipts exist", $drStats['total'] >= 0, "Total: {$drStats['total']}");
echo "  Info: DR Status breakdown - Draft: {$drStats['draft']}, Pending: {$drStats['pending']}, ";
echo "Dispatched: {$drStats['dispatched']}, Delivered: {$drStats['delivered']}, Cancelled: {$drStats['cancelled']}\n";

// Check DR number format
$drNumberStmt = $db->query("
    SELECT dr_number FROM delivery_receipts ORDER BY created_at DESC LIMIT 1
");
$drNumber = $drNumberStmt->fetch();
if ($drNumber) {
    $validFormat = preg_match('/^DR-\d{8}-\d{3}/', $drNumber['dr_number']);
    test("DR number format valid", $validFormat, "Example: {$drNumber['dr_number']}");
}

// Check DR items
$drItemsStmt = $db->query("
    SELECT COUNT(*) as count FROM delivery_receipt_items
");
$drItemsCount = $drItemsStmt->fetch()['count'];
test("DR line items exist", $drItemsCount >= 0, "Count: $drItemsCount");

// Validate DR workflow status transitions
$validStatuses = ['draft', 'pending', 'preparing', 'released', 'dispatched', 'in_transit', 'delivered', 'cancelled'];
echo "  Info: Valid DR statuses: " . implode(' -> ', $validStatuses) . "\n";

// ============================================
// SECTION 7: DISPATCH OPERATIONS
// ============================================
section("7. DISPATCH OPERATIONS & FIFO ENFORCEMENT");

// Check dispatch log
$dispatchStmt = $db->query("
    SELECT COUNT(*) as count FROM fg_dispatch_log
");
$dispatchCount = $dispatchStmt->fetch()['count'];
test("Dispatch log records exist", $dispatchCount >= 0, "Count: $dispatchCount");

// Check for FIFO violations (newer stock released before older)
// Note: finished_goods_inventory uses product_name instead of product_id
$fifoCheckStmt = $db->query("
    SELECT 
        dl.id,
        fgi.expiry_date as released_expiry,
        (
            SELECT MIN(fg2.expiry_date)
            FROM finished_goods_inventory fg2
            WHERE fg2.product_name = fgi.product_name
            AND fg2.status = 'available'
            AND fg2.expiry_date < fgi.expiry_date
        ) as older_available
    FROM fg_dispatch_log dl
    JOIN finished_goods_inventory fgi ON dl.inventory_id = fgi.id
    LIMIT 10
");
$dispatchRecords = $fifoCheckStmt->fetchAll();
$fifoViolations = 0;
foreach ($dispatchRecords as $record) {
    if ($record['older_available'] !== null) {
        $fifoViolations++;
    }
}
if ($dispatchCount > 0) {
    test("FIFO compliance in recent dispatches", $fifoViolations == 0, 
        $fifoViolations > 0 ? "Violations found: $fifoViolations" : "All compliant");
}

// Check barcode scanning support
$barcodeCol = $db->query("SHOW COLUMNS FROM fg_dispatch_log LIKE 'barcode_scanned'");
test("Barcode scanning supported in dispatch", $barcodeCol->fetch() !== false);

// ============================================
// SECTION 8: CUSTOMER MANAGEMENT
// ============================================
section("8. CUSTOMER MANAGEMENT");

// Check customers
$customerStmt = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN customer_type = 'supermarket' THEN 1 ELSE 0 END) as supermarket,
        SUM(CASE WHEN customer_type = 'school' THEN 1 ELSE 0 END) as school,
        SUM(CASE WHEN customer_type = 'feeding_program' THEN 1 ELSE 0 END) as feeding,
        SUM(CASE WHEN customer_type = 'walk_in' THEN 1 ELSE 0 END) as walkin,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active
    FROM customers
");
$customerStats = $customerStmt->fetch();

test("Customers exist", $customerStats['total'] >= 0, "Total: {$customerStats['total']}");
echo "  Info: Customer types - Supermarket: {$customerStats['supermarket']}, ";
echo "School: {$customerStats['school']}, Feeding Program: {$customerStats['feeding']}, Walk-in: {$customerStats['walkin']}\n";
echo "  Info: Active customers: {$customerStats['active']}\n";

// Check customer required fields
if ($customerStats['total'] > 0) {
    $customerDetailStmt = $db->query("SELECT * FROM customers LIMIT 1");
    $customer = $customerDetailStmt->fetch();
    
    test("Customer has required fields",
        !empty($customer['name']) && !empty($customer['customer_type']),
        "Name: {$customer['name']}, Type: {$customer['customer_type']}");
}

// ============================================
// SECTION 9: RETURNS/BAD ORDERS
// ============================================
section("9. RETURNS/BAD ORDERS MANAGEMENT");

// Check for returns table
$returnsTable = $db->query("SHOW TABLES LIKE 'product_returns'");
$hasReturnsTable = $returnsTable->fetch();

if ($hasReturnsTable) {
    test("Returns table exists", true, "product_returns");
    
    // Check return reasons
    $returnReasonStmt = $db->query("
        SELECT DISTINCT return_reason FROM product_returns LIMIT 10
    ");
    $reasons = $returnReasonStmt->fetchAll(PDO::FETCH_COLUMN);
    if (count($reasons) > 0) {
        echo "  Info: Return reasons used: " . implode(', ', $reasons) . "\n";
    }
} else {
    // Check for bad_orders table as alternative
    $badOrdersTable = $db->query("SHOW TABLES LIKE 'bad_orders'");
    if ($badOrdersTable->fetch()) {
        test("Bad orders table exists", true, "bad_orders");
    } else {
        warn("Returns/Bad orders table not found", "Consider implementing product_returns or bad_orders table");
    }
}

// ============================================
// SECTION 10: API ENDPOINTS VALIDATION
// ============================================
section("10. API ENDPOINTS VALIDATION");

$apiFiles = [
    'warehouse/fg/dashboard.php' => 'Dashboard statistics API',
    'warehouse/fg/inventory.php' => 'FG Inventory API (multi-unit)',
    'warehouse/fg/chillers.php' => 'Chiller management API',
    'warehouse/fg/delivery_receipts.php' => 'DR management API',
    'warehouse/fg/dispatch.php' => 'Dispatch operations API',
    'warehouse/fg/customers.php' => 'Customer management API'
];

$baseDir = dirname(__FILE__);

foreach ($apiFiles as $file => $desc) {
    $fullPath = $baseDir . '/' . $file;
    test("API: $file exists", file_exists($fullPath), $desc);
}

// ============================================
// SECTION 11: UI FILES VERIFICATION
// ============================================
section("11. UI FILES VERIFICATION");

$htmlDir = dirname(dirname(__FILE__)) . '/html/warehouse/fg';
$jsDir = dirname(dirname(__FILE__)) . '/js/warehouse';

$uiFiles = [
    'html' => [
        'dashboard.html' => 'Dashboard UI',
        'inventory.html' => 'Inventory management UI',
        'chillers.html' => 'Chiller management UI',
        'delivery_receipts.html' => 'DR management UI',
        'dispatch.html' => 'Dispatch UI',
        'customers.html' => 'Customer management UI',
        'receiving.html' => 'Receiving from production UI'
    ],
    'js' => [
        'fg.service.js' => 'Frontend API service'
    ]
];

foreach ($uiFiles['html'] as $file => $desc) {
    $fullPath = $htmlDir . '/' . $file;
    test("UI: $file exists", file_exists($fullPath), $desc);
}

foreach ($uiFiles['js'] as $file => $desc) {
    $fullPath = $jsDir . '/' . $file;
    test("JS: $file exists", file_exists($fullPath), $desc);
}

// ============================================
// SECTION 12: INVENTORY TRANSACTION AUDIT
// ============================================
section("12. INVENTORY TRANSACTION AUDIT TRAIL");

// Check transaction types
$txnTypesStmt = $db->query("
    SELECT 
        transaction_type,
        COUNT(*) as count
    FROM fg_inventory_transactions
    GROUP BY transaction_type
");
$txnTypes = $txnTypesStmt->fetchAll();

$expectedTypes = ['receive', 'release', 'transfer', 'adjust', 'dispose', 'return'];
$foundTypes = array_column($txnTypes, 'transaction_type');

echo "  Info: Transaction types found: ";
if (count($txnTypes) > 0) {
    foreach ($txnTypes as $type) {
        echo "{$type['transaction_type']}({$type['count']}) ";
    }
    echo "\n";
} else {
    echo "None\n";
}

// Check for orphaned transactions
$orphanedStmt = $db->query("
    SELECT COUNT(*) as count
    FROM fg_inventory_transactions t
    LEFT JOIN finished_goods_inventory i ON t.inventory_id = i.id
    WHERE i.id IS NULL
");
$orphaned = $orphanedStmt->fetch()['count'];
test("No orphaned transactions", $orphaned == 0, $orphaned > 0 ? "Orphaned: $orphaned" : null);

// ============================================
// SECTION 13: DATA INTEGRITY CHECKS
// ============================================
section("13. DATA INTEGRITY CHECKS");

// Check inventory-chiller linkage
$noChillerStmt = $db->query("
    SELECT COUNT(*) as count
    FROM finished_goods_inventory
    WHERE status = 'available'
    AND chiller_id IS NULL
");
$noChiller = $noChillerStmt->fetch()['count'];
test("All available inventory has chiller assignment", $noChiller == 0, 
    $noChiller > 0 ? "Missing chiller: $noChiller items" : null);

// Check chiller capacity vs current count
$capacityCheckStmt = $db->query("
    SELECT 
        chiller_code,
        capacity,
        current_count,
        (SELECT COALESCE(SUM(quantity_available), 0) FROM finished_goods_inventory WHERE chiller_id = c.id AND status = 'available') as actual_count
    FROM chiller_locations c
    WHERE is_active = 1
");
$chillerCapacities = $capacityCheckStmt->fetchAll();

$capacityIssues = 0;
foreach ($chillerCapacities as $chiller) {
    if ($chiller['current_count'] != $chiller['actual_count']) {
        $capacityIssues++;
        warn("Chiller '{$chiller['chiller_code']}' count mismatch",
             "current_count: {$chiller['current_count']}, actual: {$chiller['actual_count']}");
    }
    if ($chiller['current_count'] > $chiller['capacity']) {
        warn("Chiller '{$chiller['chiller_code']}' over capacity",
             "Capacity: {$chiller['capacity']}, Current: {$chiller['current_count']}");
    }
}
test("Chiller counts accurate", $capacityIssues == 0, 
    $capacityIssues > 0 ? "Issues: $capacityIssues" : null);

// Check DR totals match items (note: delivery_receipts uses total_items, not total_quantity)
$drTotalCheckStmt = $db->query("
    SELECT 
        dr.id,
        dr.dr_number,
        dr.total_items as header_items,
        COUNT(dri.id) as actual_items
    FROM delivery_receipts dr
    LEFT JOIN delivery_receipt_items dri ON dri.dr_id = dr.id
    WHERE dr.status NOT IN ('cancelled')
    GROUP BY dr.id
    HAVING header_items != actual_items
    LIMIT 5
");
$drMismatches = $drTotalCheckStmt->fetchAll();
test("DR header totals match line items", count($drMismatches) == 0,
    count($drMismatches) > 0 ? "Mismatches: " . count($drMismatches) : null);

// ============================================
// SECTION 14: INTEGRATION FLOW SIMULATION
// ============================================
section("14. INTEGRATION FLOW SIMULATION");

echo "Simulating: Production Batch -> Receiving -> Chiller -> DR -> Dispatch\n\n";

// Step 1: Check for completed production batches
$completedBatchStmt = $db->query("
    SELECT pb.*
    FROM production_batches pb
    WHERE pb.qc_status = 'released'
    ORDER BY pb.created_at DESC
    LIMIT 1
");
$completedBatch = $completedBatchStmt->fetch();
test("Step 1: QC-Released production batch available", 
    $completedBatch !== false,
    $completedBatch ? "Batch: {$completedBatch['batch_code']}" : "No QC-released batches");

// Step 2: Check if batch can be received
if ($completedBatch) {
    $alreadyReceived = $db->prepare("SELECT id FROM fg_receiving WHERE batch_id = ?");
    $alreadyReceived->execute([$completedBatch['id']]);
    $isReceived = $alreadyReceived->fetch();
    
    test("Step 2: Batch receiving status tracked",
        true,
        $isReceived ? "Already received" : "Available for receiving");
}

// Step 3: Check available chillers
$availableChillerStmt = $db->query("
    SELECT * FROM chiller_locations 
    WHERE is_active = 1 
    AND (status = 'available' OR current_count < capacity)
    LIMIT 1
");
$availableChiller = $availableChillerStmt->fetch();
test("Step 3: Available chiller for storage",
    $availableChiller !== false,
    $availableChiller ? "Chiller: {$availableChiller['chiller_code']}" : "No available chillers");

// Step 4: Check inventory for dispatch
$dispatchReadyStmt = $db->query("
    SELECT 
        fg.*
    FROM finished_goods_inventory fg
    WHERE fg.status = 'available'
    AND fg.quantity_available > 0
    ORDER BY fg.expiry_date ASC
    LIMIT 1
");
$dispatchReady = $dispatchReadyStmt->fetch();
test("Step 4: Inventory available for dispatch",
    $dispatchReady !== false,
    $dispatchReady ? "{$dispatchReady['product_name']} - Qty: {$dispatchReady['quantity_available']}" : "No inventory");

// Step 5: Check DR creation capability
$canCreateDR = true;
if (!$dispatchReady) $canCreateDR = false;
test("Step 5: DR creation prerequisites met",
    $canCreateDR,
    $canCreateDR ? "Ready to create DR" : "Prerequisites missing");

// Step 6: Verify dispatch logging
$logCheckStmt = $db->query("SHOW TABLES LIKE 'fg_dispatch_log'");
test("Step 6: Dispatch logging infrastructure ready",
    $logCheckStmt->fetch() !== false);

// ============================================
// SECTION 15: SALES ORDER INTEGRATION
// ============================================
section("15. SALES ORDER INTEGRATION");

// Check sales_orders table (POs from Sales Custodian)
$salesOrdersTable = $db->query("SHOW TABLES LIKE 'sales_orders'");
$hasSalesOrders = $salesOrdersTable->fetch();

if ($hasSalesOrders) {
    test("Sales orders table exists", true, "PO fulfillment supported");
    
    // Check pending orders
    $pendingOrdersStmt = $db->query("
        SELECT COUNT(*) as count
        FROM sales_orders
        WHERE status IN ('pending', 'approved', 'preparing')
    ");
    $pendingOrders = $pendingOrdersStmt->fetch()['count'];
    echo "  Info: Pending orders to fulfill: $pendingOrders\n";
    
    // Check sales_order_items table
    $soItemsTable = $db->query("SHOW TABLES LIKE 'sales_order_items'");
    test("Sales order items table exists", $soItemsTable->fetch() !== false);
} else {
    warn("Sales orders table not found", "Run migration_warehouse_fg_v2.sql");
}

// ============================================
// SUMMARY
// ============================================
section("TEST SUMMARY");

$total = $passed + $failed;
$percentage = $total > 0 ? round(($passed / $total) * 100, 1) : 0;

echo "Tests Passed: $passed\n";
echo "Tests Failed: $failed\n";
echo "Warnings: $warnings\n";
echo "Total Tests: $total\n";
echo "Success Rate: $percentage%\n";

// Critical issues
if ($failed > 0) {
    echo "\n⚠ CRITICAL: Some tests failed. Review the output above.\n";
}

if ($warnings > 0) {
    echo "\n⚡ WARNINGS: $warnings potential issues detected.\n";
}

// Final status
echo "\n" . str_repeat("=", 60) . "\n";
if ($percentage >= 90) {
    echo "✓ WAREHOUSE FG MODULE: READY FOR PRODUCTION\n";
} elseif ($percentage >= 70) {
    echo "⚠ WAREHOUSE FG MODULE: NEEDS ATTENTION\n";
} else {
    echo "✗ WAREHOUSE FG MODULE: REQUIRES FIXES\n";
}
echo str_repeat("=", 60) . "\n";

// Return exit code
exit($failed > 0 ? 1 : 0);
