<?php

$root = dirname(__DIR__);
$ingredientsApi = file_get_contents($root . '/api/warehouse/raw/ingredients.php');
$ingredientsPage = file_get_contents($root . '/html/warehouse/raw/ingredients.html');
$alertsPage = file_get_contents($root . '/html/warehouse/raw/reorder_alerts.html');
$gmApi = file_get_contents($root . '/api/admin/gm_approvals.php');
$gmPage = file_get_contents($root . '/html/admin/gm_approvals.html');
$gmDashboardApi = file_get_contents($root . '/api/admin/dashboard.php');
$gmDashboardPage = file_get_contents($root . '/html/admin/dashboard.html');
$purchasingApi = file_get_contents($root . '/api/purchasing/dashboard.php');
$purchasingPage = file_get_contents($root . '/html/purchasing/dashboard.html');
$qcApi = file_get_contents($root . '/api/qc/dashboard.php');
$qcPage = file_get_contents($root . '/html/qc/dashboard.html');
$earlyReorderHelper = file_get_contents($root . '/api/helpers/early_reorder.php');
$rawWarehouseService = file_get_contents($root . '/js/warehouse/raw.service.js');

$checks = [
    'Warehouse has a separate unrecorded-delivery exception instead of mixing it with a stock count' =>
        str_contains($ingredientsApi, "case 'request_opening_stock'")
        && str_contains($ingredientsPage, 'Report Unrecorded Supplier Delivery')
        && str_contains($ingredientsPage, 'Send Delivery for Checks'),
    'Perishable found stock requires traceable lot and expiry data' =>
        str_contains($ingredientsApi, 'Perishable stock requires an expiry date')
        && str_contains($ingredientsPage, 'openingStockLot')
        && str_contains($ingredientsPage, 'openingStockExpiry'),
    'Only an open saved order can add an unrecorded delivery again' =>
        str_contains($ingredientsApi, "po.status IN ('approved', 'ordered', 'partial_received')")
        && str_contains($ingredientsApi, 'HAVING ? > 0 OR remaining_quantity > 0.0005')
        && str_contains($ingredientsApi, 'validateUnrecordedDeliveryPoMatch')
        && str_contains($ingredientsPage, 'Which saved order matches this delivery?')
        && str_contains($ingredientsPage, 'Document not listed')
        && str_contains($ingredientsPage, 'still open'),
    'Opening counts receive automatic references but perishables cannot invent internal supplier lots' =>
        str_contains($ingredientsApi, "'Opening count ' . \$requestCode")
        && !str_contains($ingredientsApi, "'INTERNAL-' . \$requestCode")
        && !str_contains($ingredientsPage, 'No lot number is printed on the package')
        && str_contains($ingredientsPage, 'Missing or unreadable lots must be held and reported to QC.'),
    'Low-stock validation routes a higher count directly to the GM stock-count flow' =>
        str_contains($alertsPage, 'recordStockCountFromAudit')
        && str_contains($alertsPage, 'Send count to GM')
        && str_contains($alertsPage, "url.searchParams.set('adjust_stock'")
        && !str_contains($alertsPage, "url.searchParams.set('record_unlisted'"),
    'GM queue can review and decide an unrecorded supplier delivery' =>
        str_contains($gmApi, "case 'ingredient_opening_stock'")
        && str_contains($gmApi, 'decideIngredientOpeningStock')
        && str_contains($gmPage, 'Unrecorded Delivery Review'),
    'GM dashboard cannot falsely look clear while found stock awaits approval' =>
        str_contains($gmDashboardApi, "'type' => 'ingredient_opening_stock'")
        && str_contains($gmDashboardApi, "price_status IN ('matched_po', 'verified', 'not_required')")
        && str_contains($gmDashboardApi, "qc_status IN ('approved', 'not_required')")
        && str_contains($gmDashboardPage, "gm_approvals.html?queue=inventory"),
    'Warehouse shows the exact reviewer currently holding the request' =>
        str_contains($ingredientsApi, 'osr.held_batch_id')
        && str_contains($ingredientsApi, 'osr.price_status, osr.qc_status')
        && str_contains($ingredientsPage, 'openingStockReviewStep')
        && str_contains($ingredientsPage, 'Ready for GM'),
    'Different held batches of the same ingredient can be reviewed separately' =>
        str_contains($ingredientsApi, 'WHERE ingredient_id = ? AND held_batch_id = ?')
        && str_contains($ingredientsPage, "String(request.held_batch_id || '') === String(heldBatchId)"),
    'Warehouse does not enter inventory valuation and sees only mapped suppliers' =>
        !str_contains($ingredientsPage, 'id="openingStockUnitCost"')
        && str_contains($ingredientsApi, 'FROM supplier_ingredients si')
        && str_contains($ingredientsApi, 'That supplier is not approved to provide this ingredient'),
    'Price and QC checks happen before final GM approval' =>
        str_contains(file_get_contents($root . '/api/helpers/ingredient_opening_stock.php'), 'Purchasing must verify the unit cost before GM approval')
        && str_contains($gmApi, "qc_status IN ('approved', 'not_required')"),
    'Internal stock-count corrections go directly from Warehouse to GM' =>
        str_contains($ingredientsApi, "'stock_adjustment'")
        && str_contains($ingredientsApi, "'not_required', 'not_required'")
        && str_contains($ingredientsPage, 'Send to GM')
        && str_contains($ingredientsPage, 'Full Stock Count')
        && str_contains($ingredientsPage, 'No Purchasing or QC step is required.')
        && str_contains($gmPage, 'Warehouse → GM only.')
        && str_contains($gmApi, "price_status IN ('matched_po', 'verified', 'not_required')"),
    'Warehouse can count one known batch without silently changing another lot' =>
        str_contains($ingredientsPage, 'Count Batch')
        && str_contains($ingredientsPage, 'openBatchCountModal')
        && str_contains($ingredientsApi, "'batch'")
        && str_contains($ingredientsApi, 'adjustment_scope')
        && str_contains(file_get_contents($root . '/api/helpers/ingredient_opening_stock.php'), "\$adjustmentScope === 'batch'"),
    'Stock-count mode banners stay hidden unless their mode is active' =>
        str_contains($ingredientsPage, '.pro-banner.hidden')
        && str_contains($ingredientsPage, '.pro-banner[hidden]')
        && str_contains($ingredientsPage, "classList.toggle('hidden', !batchContext)"),
    'A higher physical count goes directly from Warehouse to GM even for a perishable item' =>
        str_contains($ingredientsPage, 'Higher and lower counts both go directly from Warehouse to the GM')
        && !str_contains($ingredientsPage, 'id="adjustSourceBatch"')
        && !str_contains($ingredientsPage, 'adjustNoSourceBatch')
        && !str_contains($ingredientsApi, 'Choose the original lot for the recovered or returned stock.')
        && str_contains(file_get_contents($root . '/api/warehouse/raw/ingredient_stock_helpers.php'), "generateIngredientBatchCode(\$db, 'IB-COUNT')")
        && str_contains(file_get_contents($root . '/api/warehouse/raw/ingredient_stock_helpers.php'), 'warehouse_count_increase'),
    'An overall lower count previews and records its automatic lot allocation' =>
        str_contains($ingredientsPage, 'adjustAllocationPreview')
        && str_contains($gmPage, 'batch_allocation_preview')
        && str_contains(file_get_contents($root . '/api/warehouse/raw/ingredient_stock_helpers.php'), 'previewIngredientBatchReduction')
        && str_contains(file_get_contents($root . '/api/helpers/ingredient_opening_stock.php'), 'batch_allocation_json'),
    'Production releases cannot bypass the approved requisition flow' =>
        str_contains($ingredientsApi, 'Direct ingredient issues are disabled')
        && str_contains($ingredientsApi, 'Requisitions → Fulfill')
        && !str_contains($ingredientsPage, 'Manual Issue')
        && !str_contains($ingredientsPage, 'issueModal')
        && !str_contains($rawWarehouseService, 'async issueIngredient(')
        && str_contains($earlyReorderHelper, "COALESCE(it.reference_type, '') <> 'manual_issue'"),
    'Everyday navigation is separated from rare ingredient actions' =>
        str_contains($ingredientsPage, 'Fulfill Request')
        && str_contains($ingredientsPage, 'Receive Delivery')
        && str_contains($ingredientsPage, 'More Actions')
        && str_contains($ingredientsPage, 'Rare corrections and configuration')
        && str_contains($ingredientsPage, 'Full Stock Count'),
    'Only the GM can change stock settings while Warehouse can still see the levels' =>
        str_contains($ingredientsApi, 'Only the General Manager can change stock settings')
        && str_contains($ingredientsPage, "currentRole === 'general_manager'")
        && str_contains($ingredientsPage, 'Start Buying At')
        && str_contains($ingredientsPage, 'Safety Buffer')
        && str_contains($ingredientsPage, 'Restocking Target'),
    'Count and waste remain attached to individual batches' =>
        str_contains($ingredientsPage, 'Count Batch')
        && str_contains($ingredientsPage, 'Record Waste')
        && str_contains($ingredientsPage, 'remaining > 0 && !traceabilityIssue'),
    'Purchasing has a reachable price verification queue' =>
        str_contains($purchasingApi, "case 'found_stock_price_checks'")
        && str_contains($purchasingApi, "verify_found_stock_price")
        && str_contains($purchasingPage, 'Verify Found-Stock Cost'),
    'QC has a reachable perishable found-stock queue' =>
        str_contains($qcApi, "approve_found_stock")
        && str_contains($qcApi, "reject_found_stock")
        && str_contains($qcPage, 'Inspect Found Perishable Stock'),
    'A held legacy batch is corrected instead of duplicated' =>
        str_contains($ingredientsApi, "'traceability_correction'")
        && str_contains($ingredientsApi, 'requestedHeldBatchId')
        && str_contains($ingredientsPage, 'openingStockHeldBatchId')
        && str_contains($ingredientsPage, 'Record Lot Details')
        && str_contains(file_get_contents($root . '/api/helpers/ingredient_opening_stock.php'), "UPDATE ingredient_batches SET"),
    'Restocking targets are not mistaken for physical storage limits' =>
        !str_contains($ingredientsApi, 'storage maximum')
        && !str_contains(file_get_contents($root . '/api/helpers/ingredient_opening_stock.php'), 'storage maximum')
        && str_contains($ingredientsPage, 'restocking target'),
    'A held batch uses lot correction without a duplicate adjustment warning' =>
        str_contains($ingredientsApi, 'unexplained_batch_surplus')
        && str_contains($ingredientsPage, 'batchStockSurplus - restrictedStock')
        && str_contains($ingredientsPage, 'canRecordLot = traceabilityIssue')
        && str_contains($ingredientsPage, 'Record Lot Details'),
];
foreach ($checks as $label => $passed) {
    if (!$passed) throw new RuntimeException("Failed: {$label}");
}

define('HIGHLAND_FRESH', true);
if (!function_exists('logAudit')) {
    function logAudit($userId, $action, $tableName, $recordId, $oldValues = null, $newValues = null) {
        // Integration assertions below inspect the durable inventory movement.
    }
}
require_once $root . '/api/config/config.php';
require_once $root . '/api/config/database.php';
require_once $root . '/api/warehouse/raw/ingredient_stock_helpers.php';
require_once $root . '/api/helpers/ingredient_opening_stock.php';

$fullyReceivedBlocked = false;
try {
    validateUnrecordedDeliveryPoMatch([
        'po_number' => 'PO-FULL',
        'remaining_quantity' => 0,
        'matched_receiving_reference' => false,
    ], 1, false);
} catch (RuntimeException $error) {
    $fullyReceivedBlocked = str_contains($error->getMessage(), 'already fully received');
}
if (!$fullyReceivedBlocked) {
    throw new RuntimeException('A fully received PO could be reused for an unrecorded delivery');
}

$overDeliveryBlocked = false;
try {
    validateUnrecordedDeliveryPoMatch([
        'po_number' => 'PO-PARTIAL',
        'remaining_quantity' => 2,
        'matched_receiving_reference' => false,
    ], 3, false);
} catch (RuntimeException $error) {
    $overDeliveryBlocked = str_contains($error->getMessage(), 'only 2 still open');
}
if (!$overDeliveryBlocked) {
    throw new RuntimeException('An unrecorded delivery could exceed the PO balance');
}

$recordedDocumentBlocked = false;
try {
    validateUnrecordedDeliveryPoMatch([
        'po_number' => 'PO-PARTIAL',
        'remaining_quantity' => 2,
        'matched_receiving_reference' => true,
        'matched_rr_number' => 'RR-TEST-001',
    ], 1, false);
} catch (RuntimeException $error) {
    $recordedDocumentBlocked = str_contains($error->getMessage(), 'already recorded under receiving report RR-TEST-001');
}
if (!$recordedDocumentBlocked) {
    throw new RuntimeException('A recorded delivery reference could be submitted as unrecorded stock again');
}

// Completing the lot details of stock already recorded does not add stock,
// so its historical PO remains valid traceability evidence.
validateUnrecordedDeliveryPoMatch([
    'po_number' => 'PO-HISTORICAL',
    'remaining_quantity' => 0,
    'matched_receiving_reference' => true,
], 10, true);

$db = Database::getInstance()->getConnection();
ensureIngredientOpeningStockSupport($db);
$schema = str_replace('`', '``', DB_NAME);
$tables = ['ingredients', 'ingredient_batches', 'inventory_transactions', 'ingredient_opening_stock_requests'];
foreach ($tables as $table) {
    $safe = str_replace('`', '``', $table);
    $db->exec("CREATE TEMPORARY TABLE `{$safe}` AS SELECT * FROM `{$schema}`.`{$safe}` WHERE 1=0");
}

$seed = random_int(100000, 900000);
$ingredientId = $seed;
$requestId = $seed + 1;
$requester = 4;
$gm = 1;
$db->prepare("INSERT INTO ingredients
    (id, ingredient_code, ingredient_name, unit_of_measure, minimum_stock,
     current_stock, unit_cost, maximum_stock, storage_location, shelf_life_days,
     is_perishable, is_active)
    VALUES (?, ?, 'Opening Stock Test', 'kg', 1, 0, 25, 20, 'Cold Storage', 30, 1, 1)")
    ->execute([$ingredientId, "OPEN-{$seed}"]);

// Legacy perishable rows without the supplier's real lot stay visible for
// reconciliation but must never count as usable stock.
$db->prepare("INSERT INTO ingredient_batches
    (batch_code, ingredient_id, quantity, remaining_quantity, unit_cost, received_date,
     expiry_date, qc_status, received_by, status, notes)
    VALUES (?, ?, 2, 2, 25, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 10 DAY),
            'approved', ?, 'available', 'Legacy row without supplier lot')")
    ->execute(["LEGACY-NO-LOT-{$seed}", $ingredientId, $requester]);
if (abs(getUsableIngredientBatchStock($db, $ingredientId)) > 0.0005
    || abs(getAccountedIngredientBatchStock($db, $ingredientId) - 2) > 0.0005) {
    throw new RuntimeException('A perishable batch without a supplier lot was still treated as usable');
}
$db->prepare('DELETE FROM ingredient_batches WHERE ingredient_id = ?')->execute([$ingredientId]);

$db->prepare("INSERT INTO ingredient_opening_stock_requests
    (id, request_code, ingredient_id, system_quantity, counted_quantity, quantity_to_add,
     unit, source_type, source_reference, supplier_batch_no, received_date, expiry_date,
     unit_cost, price_status, qc_status, reason, status, requested_by)
    VALUES (?, ?, ?, 0, 10, 10, 'kg', 'opening_balance', 'COUNT-SHEET-1', 'LOT-TEST',
            CURDATE(), DATE_ADD(CURDATE(), INTERVAL 10 DAY), 25, 'pending', 'pending',
            'Verified opening count', 'pending', ?)")
    ->execute([$requestId, "REQ-{$seed}", $ingredientId, $requester]);

$priceBlocked = false;
$db->beginTransaction();
try {
    decideIngredientOpeningStock($db, $requestId, 'approve', $gm, 'Price not checked');
} catch (RuntimeException $error) {
    $priceBlocked = str_contains($error->getMessage(), 'Purchasing must verify');
}
$db->rollBack();
if (!$priceBlocked) throw new RuntimeException('GM could approve before Purchasing verified the cost');

$db->prepare("UPDATE ingredient_opening_stock_requests SET price_status = 'verified' WHERE id = ?")
    ->execute([$requestId]);
$qcBlocked = false;
$db->beginTransaction();
try {
    decideIngredientOpeningStock($db, $requestId, 'approve', $gm, 'QC not checked');
} catch (RuntimeException $error) {
    $qcBlocked = str_contains($error->getMessage(), 'QC must approve');
}
$db->rollBack();
if (!$qcBlocked) throw new RuntimeException('GM could approve perishable found stock before QC');

$db->prepare("UPDATE ingredient_opening_stock_requests
    SET qc_status = 'approved', qc_verified_by = ?, qc_verified_at = NOW() WHERE id = ?")
    ->execute([$gm, $requestId]);

$db->beginTransaction();
$result = decideIngredientOpeningStock($db, $requestId, 'approve', $gm, 'Verified documents');
if (($result['status'] ?? '') !== 'approved') throw new RuntimeException('Found stock was not approved');
$stock = (float) $db->query("SELECT current_stock FROM ingredients WHERE id = {$ingredientId}")->fetchColumn();
$batch = $db->query("SELECT quantity, remaining_quantity, supplier_batch_no, status FROM ingredient_batches WHERE ingredient_id = {$ingredientId}")->fetch(PDO::FETCH_ASSOC);
$txCount = (int) $db->query("SELECT COUNT(*) FROM inventory_transactions WHERE reference_type = 'ingredient_opening_stock' AND reference_id = {$requestId}")->fetchColumn();
if (abs($stock - 10) > 0.0005 || !$batch || $batch['supplier_batch_no'] !== 'LOT-TEST' || $batch['status'] !== 'available' || $txCount !== 1) {
    throw new RuntimeException('GM approval did not create one traceable batch and matching stock movement');
}
$db->rollBack();

// A delivery or another correction after the shelf count makes that count
// stale. GM approval must stop rather than add the old difference twice.
$db->prepare("INSERT INTO ingredient_batches
    (batch_code, ingredient_id, quantity, remaining_quantity, unit_cost, received_date,
     expiry_date, supplier_batch_no, qc_status, received_by, status, notes)
    VALUES (?, ?, 1, 1, 25, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 10 DAY), ?,
            'approved', ?, 'available', 'Concurrent stock change')")
    ->execute(["CONCURRENT-{$seed}", $ingredientId, "LOT-CONCURRENT-{$seed}", $requester]);
$staleBlocked = false;
$db->beginTransaction();
try {
    decideIngredientOpeningStock($db, $requestId, 'approve', $gm, 'Should be blocked');
} catch (RuntimeException $error) {
    $staleBlocked = str_contains($error->getMessage(), 'Stock changed after Warehouse counted it');
}
$db->rollBack();
if (!$staleBlocked) throw new RuntimeException('A stale shelf count could still be approved');

// Correcting an old held batch must update that same batch. It must not add a
// second physical batch for stock that was already on the shelf.
$heldIngredientId = $seed + 10;
$heldBatchId = $seed + 11;
$heldRequestId = $seed + 12;
$db->prepare("INSERT INTO ingredients
    (id, ingredient_code, ingredient_name, unit_of_measure, minimum_stock,
     current_stock, unit_cost, maximum_stock, storage_location, shelf_life_days,
     is_perishable, is_active)
    VALUES (?, ?, 'Held Sugar Test', 'kg', 1, 0, 25, 20, 'Dry Storage', 30, 1, 1)")
    ->execute([$heldIngredientId, "HELD-{$seed}"]);
$db->prepare("INSERT INTO ingredient_batches
    (id, batch_code, ingredient_id, quantity, remaining_quantity, unit_cost, received_date,
     expiry_date, qc_status, received_by, status, notes)
    VALUES (?, ?, ?, 5, 5, 25, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 10 DAY),
            'approved', ?, 'available', 'Old row with missing lot')")
    ->execute([$heldBatchId, "HELD-BATCH-{$seed}", $heldIngredientId, $requester]);
$db->prepare("INSERT INTO ingredient_opening_stock_requests
    (id, request_code, ingredient_id, system_quantity, counted_quantity, quantity_to_add,
     unit, source_type, source_reference, supplier_batch_no, received_date, expiry_date,
     unit_cost, price_status, qc_status, qc_verified_by, qc_verified_at, reason, status,
     requested_by, request_purpose, held_batch_id)
    VALUES (?, ?, ?, 0, 5, 5, 'kg', 'opening_balance', 'COUNT-SHEET-HELD', 'REAL-LOT-5',
            CURDATE(), DATE_ADD(CURDATE(), INTERVAL 20 DAY), 25, 'verified', 'approved', ?, NOW(),
            'Supplier document found', 'pending', ?, 'traceability_correction', ?)")
    ->execute([$heldRequestId, "REQ-HELD-{$seed}", $heldIngredientId, $gm, $requester, $heldBatchId]);
$db->beginTransaction();
decideIngredientOpeningStock($db, $heldRequestId, 'approve', $gm, 'Lot checked');
$heldRows = $db->query("SELECT id, supplier_batch_no FROM ingredient_batches WHERE ingredient_id = {$heldIngredientId}")->fetchAll(PDO::FETCH_ASSOC);
$heldSummary = (float) $db->query("SELECT current_stock FROM ingredients WHERE id = {$heldIngredientId}")->fetchColumn();
if (count($heldRows) !== 1 || (int) $heldRows[0]['id'] !== $heldBatchId
    || $heldRows[0]['supplier_batch_no'] !== 'REAL-LOT-5' || abs($heldSummary - 5) > 0.0005) {
    throw new RuntimeException('Correcting a held batch created duplicate physical stock');
}
$db->rollBack();

// Two different held batches of the same ingredient may be submitted and
// approved independently. Finishing the first must not make the second stale.
$secondHeldBatchId = $seed + 13;
$secondHeldRequestId = $seed + 14;
$db->prepare("INSERT INTO ingredient_batches
    (id, batch_code, ingredient_id, quantity, remaining_quantity, unit_cost, received_date,
     expiry_date, qc_status, received_by, status, notes)
    VALUES (?, ?, ?, 3, 3, 25, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 12 DAY),
            'approved', ?, 'available', 'Second held row with missing lot')")
    ->execute([$secondHeldBatchId, "HELD-BATCH-2-{$seed}", $heldIngredientId, $requester]);
$db->prepare("INSERT INTO ingredient_opening_stock_requests
    (id, request_code, ingredient_id, system_quantity, counted_quantity, quantity_to_add,
     unit, source_type, source_reference, supplier_batch_no, received_date, expiry_date,
     unit_cost, price_status, qc_status, qc_verified_by, qc_verified_at, reason, status,
     requested_by, request_purpose, held_batch_id)
    VALUES (?, ?, ?, 0, 3, 3, 'kg', 'opening_balance', 'COUNT-SHEET-HELD-2', 'REAL-LOT-3',
            CURDATE(), DATE_ADD(CURDATE(), INTERVAL 12 DAY), 25, 'verified', 'approved', ?, NOW(),
            'Second supplier document found', 'pending', ?, 'traceability_correction', ?)")
    ->execute([$secondHeldRequestId, "REQ-HELD-2-{$seed}", $heldIngredientId, $gm, $requester, $secondHeldBatchId]);
$db->beginTransaction();
decideIngredientOpeningStock($db, $heldRequestId, 'approve', $gm, 'First lot checked');
decideIngredientOpeningStock($db, $secondHeldRequestId, 'approve', $gm, 'Second lot checked');
$usableAfterBoth = getUsableIngredientBatchStock($db, $heldIngredientId);
$approvedBoth = (int) $db->query("SELECT COUNT(*) FROM ingredient_opening_stock_requests
    WHERE id IN ({$heldRequestId}, {$secondHeldRequestId}) AND status = 'approved'")->fetchColumn();
if (abs($usableAfterBoth - 8) > 0.0005 || $approvedBoth !== 2) {
    throw new RuntimeException('Approving one held batch stranded another batch of the same ingredient');
}
$db->rollBack();

// Professor-recommended internal variance: Warehouse records 15 -> 16, no
// stock changes while pending, and one GM approval changes both summary and
// batch ledger. Purchasing and QC are deliberately not part of this route.
$adjustIngredientId = $seed + 20;
$adjustRequestId = $seed + 21;
$db->prepare("INSERT INTO ingredients
    (id, ingredient_code, ingredient_name, unit_of_measure, minimum_stock,
     current_stock, unit_cost, maximum_stock, storage_location, shelf_life_days,
     is_perishable, is_active)
    VALUES (?, ?, 'Internal Count Test', 'pcs', 1, 15, 2, 100, 'Dry Storage', NULL, 0, 1)")
    ->execute([$adjustIngredientId, "ADJUST-{$seed}"]);
$db->prepare("INSERT INTO ingredient_batches
    (batch_code, ingredient_id, quantity, remaining_quantity, unit_cost, received_date,
     expiry_date, qc_status, received_by, status, notes)
    VALUES (?, ?, 15, 15, 2, CURDATE(), NULL, 'approved', ?, 'available', 'Existing stock')")
    ->execute(["ADJUST-BATCH-{$seed}", $adjustIngredientId, $requester]);
$db->prepare("INSERT INTO ingredient_opening_stock_requests
    (id, request_code, ingredient_id, system_quantity, counted_quantity, quantity_to_add,
     unit, source_type, source_reference, received_date, unit_cost, price_status, qc_status,
     reason, status, requested_by, request_purpose)
    VALUES (?, ?, ?, 15, 16, 1, 'pcs', 'internal_adjustment', 'Warehouse physical count',
            CURDATE(), 2, 'not_required', 'not_required', 'Misplaced stock recovered',
            'pending', ?, 'stock_adjustment')")
    ->execute([$adjustRequestId, "REQ-ADJUST-{$seed}", $adjustIngredientId, $requester]);

$stockBeforeDecision = (float) $db->query("SELECT current_stock FROM ingredients WHERE id = {$adjustIngredientId}")->fetchColumn();
if (abs($stockBeforeDecision - 15) > 0.0005) {
    throw new RuntimeException('Warehouse submission changed stock before the GM decision');
}

$db->beginTransaction();
$adjustResult = decideIngredientOpeningStock($db, $adjustRequestId, 'approve', $gm, 'Count and reason accepted');
$adjustedStock = (float) $db->query("SELECT current_stock FROM ingredients WHERE id = {$adjustIngredientId}")->fetchColumn();
$adjustedBatchStock = getUsableIngredientBatchStock($db, $adjustIngredientId);
$adjustedTx = $db->query("SELECT quantity, quantity_before, quantity_after, performed_by, approved_by
    FROM inventory_transactions WHERE reference_type = 'ingredient_opening_stock' AND reference_id = {$adjustRequestId}")
    ->fetch(PDO::FETCH_ASSOC);
if (($adjustResult['status'] ?? '') !== 'approved'
    || abs($adjustedStock - 16) > 0.0005
    || abs($adjustedBatchStock - 16) > 0.0005
    || !$adjustedTx
    || abs((float) $adjustedTx['quantity'] - 1) > 0.0005
    || (int) $adjustedTx['performed_by'] !== $requester
    || (int) $adjustedTx['approved_by'] !== $gm) {
    throw new RuntimeException('The Warehouse -> GM adjustment did not update stock and its audit trail correctly');
}
$db->rollBack();

// The same professor-recommended direct-GM path also accepts a higher count
// for a perishable item with no earlier usable lot. Approval creates an
// auditable internal Warehouse count batch instead of routing to Purchasing/QC.
$perishableAdjustIngredientId = $seed + 25;
$perishableAdjustRequestId = $seed + 26;
$db->prepare("INSERT INTO ingredients
    (id, ingredient_code, ingredient_name, unit_of_measure, minimum_stock,
     current_stock, unit_cost, maximum_stock, storage_location, shelf_life_days,
     is_perishable, is_active)
    VALUES (?, ?, 'Perishable Direct Count Test', 'kg', 1, 0, 30, 100, 'Cold Storage', 14, 1, 1)")
    ->execute([$perishableAdjustIngredientId, "PERISH-ADJUST-{$seed}"]);
$db->prepare("INSERT INTO ingredient_opening_stock_requests
    (id, request_code, ingredient_id, system_quantity, counted_quantity, quantity_to_add,
     unit, source_type, source_reference, received_date, unit_cost, price_status, qc_status,
     reason, status, requested_by, request_purpose, adjustment_scope)
    VALUES (?, ?, ?, 0, 5, 5, 'kg', 'internal_adjustment', 'Warehouse physical count',
            CURDATE(), 30, 'not_required', 'not_required', 'Misplaced stock recovered',
            'pending', ?, 'stock_adjustment', 'ingredient')")
    ->execute([$perishableAdjustRequestId, "REQ-PERISH-ADJUST-{$seed}", $perishableAdjustIngredientId, $requester]);
$db->beginTransaction();
$perishableAdjustResult = decideIngredientOpeningStock(
    $db,
    $perishableAdjustRequestId,
    'approve',
    $gm,
    'Warehouse count accepted directly'
);
$perishableAdjustedStock = (float) $db->query("SELECT current_stock FROM ingredients WHERE id = {$perishableAdjustIngredientId}")->fetchColumn();
$perishableAdjustedUsable = getUsableIngredientBatchStock($db, $perishableAdjustIngredientId);
$perishableAdjustedBatch = $db->query("SELECT batch_code, supplier_batch_no, remaining_quantity, expiry_date, qc_status
    FROM ingredient_batches WHERE ingredient_id = {$perishableAdjustIngredientId}")->fetch(PDO::FETCH_ASSOC);
if (($perishableAdjustResult['status'] ?? '') !== 'approved'
    || abs($perishableAdjustedStock - 5) > 0.0005
    || abs($perishableAdjustedUsable - 5) > 0.0005
    || !$perishableAdjustedBatch
    || !str_starts_with((string) $perishableAdjustedBatch['batch_code'], 'IB-COUNT-')
    || !str_starts_with((string) $perishableAdjustedBatch['supplier_batch_no'], 'WAREHOUSE-IB-COUNT-')
    || (string) $perishableAdjustedBatch['expiry_date'] <= date('Y-m-d')
    || (string) $perishableAdjustedBatch['qc_status'] !== 'approved') {
    throw new RuntimeException('A higher perishable count did not complete through the direct Warehouse -> GM path');
}
$db->rollBack();

// A perishable return stays in its original QC-approved supplier lot; it does
// not create a new anonymous lot or require QC to repeat the intake test.
$returnIngredientId = $seed + 30;
$returnBatchId = $seed + 31;
$returnRequestId = $seed + 32;
$db->prepare("INSERT INTO ingredients
    (id, ingredient_code, ingredient_name, unit_of_measure, minimum_stock,
     current_stock, unit_cost, maximum_stock, storage_location, shelf_life_days,
     is_perishable, is_active)
    VALUES (?, ?, 'Returned Perishable Test', 'kg', 1, 5, 20, 100, 'Cold Storage', 30, 1, 1)")
    ->execute([$returnIngredientId, "RETURN-{$seed}"]);
$db->prepare("INSERT INTO ingredient_batches
    (id, batch_code, ingredient_id, quantity, remaining_quantity, unit_cost, received_date,
     expiry_date, supplier_batch_no, qc_status, received_by, status, notes)
    VALUES (?, ?, ?, 10, 5, 20, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 10 DAY), ?,
            'approved', ?, 'partially_used', 'Original approved lot')")
    ->execute([$returnBatchId, "RETURN-BATCH-{$seed}", $returnIngredientId, "SUP-LOT-{$seed}", $requester]);
$db->prepare("INSERT INTO ingredient_opening_stock_requests
    (id, request_code, ingredient_id, system_quantity, counted_quantity, quantity_to_add,
     unit, source_type, source_reference, supplier_batch_no, received_date, expiry_date,
     unit_cost, price_status, qc_status, reason, status, requested_by, request_purpose, source_batch_id)
    VALUES (?, ?, ?, 5, 6, 1, 'kg', 'internal_adjustment', 'Warehouse physical count', ?,
            CURDATE(), DATE_ADD(CURDATE(), INTERVAL 10 DAY), 20, 'not_required', 'not_required',
            'Unused material returned by Production', 'pending', ?, 'stock_adjustment', ?)")
    ->execute([$returnRequestId, "REQ-RETURN-{$seed}", $returnIngredientId, "SUP-LOT-{$seed}", $requester, $returnBatchId]);
$db->beginTransaction();
decideIngredientOpeningStock($db, $returnRequestId, 'approve', $gm, 'Return matched to original lot');
$returnBatch = $db->query("SELECT quantity, remaining_quantity, supplier_batch_no FROM ingredient_batches WHERE id = {$returnBatchId}")
    ->fetch(PDO::FETCH_ASSOC);
if (!$returnBatch
    || abs((float) $returnBatch['quantity'] - 10) > 0.0005
    || abs((float) $returnBatch['remaining_quantity'] - 6) > 0.0005
    || $returnBatch['supplier_batch_no'] !== "SUP-LOT-{$seed}") {
    throw new RuntimeException('A returned perishable item did not remain in its original approved lot');
}
$db->rollBack();

// When Warehouse identifies the exact batch, GM approval must update only
// that batch. Another lot for the same ingredient must remain untouched.
$batchCountIngredientId = $seed + 40;
$batchCountAId = $seed + 41;
$batchCountBId = $seed + 42;
$batchCountRequestId = $seed + 43;
$db->prepare("INSERT INTO ingredients
    (id, ingredient_code, ingredient_name, unit_of_measure, minimum_stock,
     current_stock, unit_cost, maximum_stock, storage_location, shelf_life_days,
     is_perishable, is_active)
    VALUES (?, ?, 'Batch Count Test', 'kg', 1, 30, 20, 100, 'Dry Storage', 30, 1, 1)")
    ->execute([$batchCountIngredientId, "BATCH-COUNT-{$seed}"]);
$batchInsert = $db->prepare("INSERT INTO ingredient_batches
    (id, batch_code, ingredient_id, quantity, remaining_quantity, unit_cost, received_date,
     expiry_date, supplier_batch_no, qc_status, received_by, status, notes)
    VALUES (?, ?, ?, ?, ?, 20, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 20 DAY), ?,
            'approved', ?, 'available', 'Batch-specific count test')");
$batchInsert->execute([$batchCountAId, "COUNT-A-{$seed}", $batchCountIngredientId, 10, 10, "LOT-A-{$seed}", $requester]);
$batchInsert->execute([$batchCountBId, "COUNT-B-{$seed}", $batchCountIngredientId, 20, 20, "LOT-B-{$seed}", $requester]);
$db->prepare("INSERT INTO ingredient_opening_stock_requests
    (id, request_code, ingredient_id, system_quantity, counted_quantity, quantity_to_add,
     unit, source_type, source_reference, supplier_batch_no, received_date, expiry_date,
     unit_cost, price_status, qc_status, reason, status, requested_by, request_purpose,
     source_batch_id, adjustment_scope, ingredient_quantity_at_request)
    VALUES (?, ?, ?, 20, 17, -3, 'kg', 'internal_adjustment', 'Warehouse physical count', ?,
            CURDATE(), DATE_ADD(CURDATE(), INTERVAL 20 DAY), 20, 'not_required', 'not_required',
            'Counting discrepancy on identified lot', 'pending', ?, 'stock_adjustment', ?, 'batch', 30)")
    ->execute([$batchCountRequestId, "REQ-BATCH-{$seed}", $batchCountIngredientId, "LOT-B-{$seed}", $requester, $batchCountBId]);

$db->beginTransaction();
$batchCountResult = decideIngredientOpeningStock($db, $batchCountRequestId, 'approve', $gm, 'Exact lot was recounted');
$batchCountA = (float) $db->query("SELECT remaining_quantity FROM ingredient_batches WHERE id = {$batchCountAId}")->fetchColumn();
$batchCountB = (float) $db->query("SELECT remaining_quantity FROM ingredient_batches WHERE id = {$batchCountBId}")->fetchColumn();
$batchCountSummary = (float) $db->query("SELECT current_stock FROM ingredients WHERE id = {$batchCountIngredientId}")->fetchColumn();
$batchCountTxBatch = (int) $db->query("SELECT batch_id FROM inventory_transactions
    WHERE reference_type = 'ingredient_opening_stock' AND reference_id = {$batchCountRequestId}")->fetchColumn();
$storedAllocation = $db->query("SELECT batch_allocation_json FROM ingredient_opening_stock_requests WHERE id = {$batchCountRequestId}")->fetchColumn();
if (($batchCountResult['status'] ?? '') !== 'approved'
    || abs($batchCountA - 10) > 0.0005
    || abs($batchCountB - 17) > 0.0005
    || abs($batchCountSummary - 27) > 0.0005
    || $batchCountTxBatch !== $batchCountBId
    || !str_contains((string) $storedAllocation, "COUNT-B-{$seed}")) {
    throw new RuntimeException('A batch-specific count changed the wrong lot or lost its audit allocation');
}
$db->rollBack();

// If the selected batch changes after Warehouse counted it, the GM cannot
// approve stale numbers even when the ingredient total still looks plausible.
$db->beginTransaction();
$db->prepare('UPDATE ingredient_batches SET remaining_quantity = 19 WHERE id = ?')->execute([$batchCountBId]);
$batchStaleBlocked = false;
try {
    decideIngredientOpeningStock($db, $batchCountRequestId, 'approve', $gm, 'Should be stale');
} catch (RuntimeException $error) {
    $batchStaleBlocked = str_contains($error->getMessage(), 'batch changed after Warehouse counted it');
}
$db->rollBack();
if (!$batchStaleBlocked) {
    throw new RuntimeException('GM could approve a stale batch-specific physical count');
}

echo "Ingredient stock-review tests passed (supplier stock keeps price/QC checks; internal count goes Warehouse -> GM; isolated temporary tables).\n";
