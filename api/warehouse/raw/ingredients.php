<?php
/**
 * Highland Fresh System - Warehouse Raw Ingredients API
 *
 * REVISED: Updated for new schema (Feb 2026)
 * - Updated transaction types: po_receive, production_issue, physical_adjust, dispose
 * - Added QC status handling for ingredient batches (quarantine flow)
 * - Added po_id reference when receiving batches
 * - Added supplier_id reference
 *
 * Manages ingredients inventory (sugar, powder, flavors, rennet, salt, packaging)
 *
 * GET    - List ingredients, get details, check stock
 * POST   - Receive new ingredient batch
 * PUT    - Issue ingredients, adjust stock
 *
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(dirname(__DIR__)) . '/bootstrap.php';
require_once __DIR__ . '/ingredient_stock_helpers.php';

// Require Warehouse Raw role
$currentUser = Auth::requireRole(['warehouse_raw', 'general_manager', 'production_staff', 'purchaser']);

try {
    $db = Database::getInstance()->getConnection();
    ensureIngredientPerishabilitySupport($db);
    
    switch ($requestMethod) {
        case 'GET':
            handleGet($db, $currentUser);
            break;
        case 'POST':
            handlePost($db, $currentUser);
            break;
        case 'PUT':
            handlePut($db, $currentUser);
            break;
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Warehouse Raw Ingredients API error: " . $e->getMessage());
    Response::error('An error occurred: ' . $e->getMessage(), 500);
}

function ensureIngredientPerishabilitySupport($db) {
    if (!auditColumnExists($db, 'ingredients', 'is_perishable')) {
        $db->exec("ALTER TABLE `ingredients` ADD COLUMN `is_perishable` TINYINT(1) NOT NULL DEFAULT 1 AFTER `shelf_life_days`");
    }

    if (!auditColumnExists($db, 'ingredients', 'maximum_stock')) {
        $db->exec("ALTER TABLE `ingredients` ADD COLUMN `maximum_stock` DECIMAL(10,2) DEFAULT NULL COMMENT 'Par level / order-up-to stock' AFTER `reorder_point`");
    }
}

/**
 * Handle GET requests
 */
function handleGet($db, $currentUser) {
    $action = getParam('action', 'list');
    $id = getParam('id');
    
    switch ($action) {
        case 'list':
            // Get all ingredients with stock info
            $categoryId = getParam('category_id');
            $lowStockOnly = getParam('low_stock') === '1';
            $search = getParam('search');
            
            $sql = "
                SELECT 
                    i.*,
                    ic.category_name,
                    CASE 
                        WHEN i.current_stock <= 0 THEN 'out_of_stock'
                        WHEN i.current_stock <= i.minimum_stock THEN 'low_stock'
                        ELSE 'ok'
                    END as stock_status,
                    (SELECT COUNT(*) FROM ingredient_batches ib
                     WHERE ib.ingredient_id = i.id
                     AND ib.status IN ('available', 'partially_used')
                     AND (ib.expiry_date IS NULL OR ib.expiry_date >= CURDATE())) as batch_count,
                    (SELECT MIN(expiry_date) FROM ingredient_batches ib
                     WHERE ib.ingredient_id = i.id
                     AND ib.status IN ('available', 'partially_used')
                     AND ib.expiry_date IS NOT NULL
                     AND ib.expiry_date >= CURDATE()) as earliest_expiry
                FROM ingredients i
                LEFT JOIN ingredient_categories ic ON i.category_id = ic.id
                WHERE i.is_active = 1
            ";
            $params = [];
            
            if ($categoryId) {
                $sql .= " AND i.category_id = ?";
                $params[] = $categoryId;
            }
            
            if ($lowStockOnly) {
                $sql .= " AND i.current_stock <= i.minimum_stock";
            }
            
            if ($search) {
                $sql .= " AND (i.ingredient_code LIKE ? OR i.ingredient_name LIKE ?)";
                $params[] = "%{$search}%";
                $params[] = "%{$search}%";
            }
            
            $sql .= " ORDER BY i.ingredient_name ASC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $ingredients = $stmt->fetchAll();
            
            Response::success(['ingredients' => $ingredients], 'Ingredients retrieved successfully');
            break;
            
        case 'detail':
            if (!$id) {
                Response::error('Ingredient ID is required', 400);
            }
            
            // Get ingredient details
            $ingredient = $db->prepare("
                SELECT i.*, ic.category_name
                FROM ingredients i
                LEFT JOIN ingredient_categories ic ON i.category_id = ic.id
                WHERE i.id = ? AND i.is_active = 1
            ");
            $ingredient->execute([$id]);
            $ingredientData = $ingredient->fetch();
            
            if (!$ingredientData) {
                Response::error('Ingredient not found', 404);
            }
            
            // Get batches (FIFO order)
            $batches = $db->prepare("
                SELECT 
                    ib.*,
                    u.first_name as received_by_first,
                    u.last_name as received_by_last,
                    DATEDIFF(ib.expiry_date, CURDATE()) as days_until_expiry
                FROM ingredient_batches ib
                JOIN users u ON ib.received_by = u.id
                WHERE ib.ingredient_id = ?
                AND ib.status IN ('available', 'partially_used')
                AND (ib.expiry_date IS NULL OR ib.expiry_date >= CURDATE())
                ORDER BY ib.expiry_date ASC, ib.received_date ASC, ib.id ASC
            ");
            $batches->execute([$id]);
            $batchList = $batches->fetchAll();
            
            // Get recent transactions
            $transactions = $db->prepare("
                SELECT 
                    it.*,
                    u.first_name,
                    u.last_name
                FROM inventory_transactions it
                JOIN users u ON it.performed_by = u.id
                WHERE it.item_type = 'ingredient' AND it.item_id = ?
                ORDER BY it.created_at DESC
                LIMIT 20
            ");
            $transactions->execute([$id]);
            $txList = $transactions->fetchAll();
            
            Response::success([
                'ingredient' => $ingredientData,
                'batches' => $batchList,
                'transactions' => $txList
            ], 'Ingredient details retrieved successfully');
            break;
            
        case 'categories':
            // Get all categories
            $stmt = $db->prepare("
                SELECT ic.*, 
                    (SELECT COUNT(*) FROM ingredients WHERE category_id = ic.id AND is_active = 1) as item_count
                FROM ingredient_categories ic
                WHERE ic.is_active = 1
                ORDER BY ic.category_name ASC
            ");
            $stmt->execute();
            $categories = $stmt->fetchAll();
            
            Response::success(['categories' => $categories], 'Categories retrieved successfully');
            break;
            
        case 'expiring':
            // Get ingredients expiring within specified days
            $days = getParam('days', 7);
            
            $stmt = $db->prepare("
                SELECT 
                    ib.id as batch_id,
                    ib.batch_code,
                    i.ingredient_code,
                    i.ingredient_name,
                    ic.category_name,
                    ib.remaining_quantity,
                    i.unit_of_measure,
                    ib.expiry_date,
                    DATEDIFF(ib.expiry_date, CURDATE()) as days_until_expiry
                FROM ingredient_batches ib
                JOIN ingredients i ON ib.ingredient_id = i.id
                LEFT JOIN ingredient_categories ic ON i.category_id = ic.id
                WHERE ib.status IN ('available', 'partially_used')
                AND ib.expiry_date IS NOT NULL
                AND ib.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
                ORDER BY ib.expiry_date ASC
            ");
            $stmt->execute([$days]);
            $expiring = $stmt->fetchAll();
            
            Response::success(['expiring_ingredients' => $expiring], 'Expiring ingredients retrieved successfully');
            break;

        case 'expired':
            // Get expired batches that are still in stock
            $stmt = $db->prepare("
                SELECT 
                    ib.id as batch_id,
                    ib.batch_code,
                    ib.expiry_date,
                    ib.received_date,
                    ib.remaining_quantity,
                    ib.status,
                    ib.qc_status,
                    DATEDIFF(CURDATE(), ib.expiry_date) as days_expired,
                    i.ingredient_code,
                    i.ingredient_name,
                    i.unit_of_measure,
                    ic.category_name
                FROM ingredient_batches ib
                JOIN ingredients i ON ib.ingredient_id = i.id
                LEFT JOIN ingredient_categories ic ON i.category_id = ic.id
                WHERE ib.expiry_date IS NOT NULL
                AND ib.expiry_date < CURDATE()
                AND ib.remaining_quantity > 0
                AND ib.status IN ('available', 'partially_used', 'quarantine')
                ORDER BY ib.expiry_date ASC, ib.received_date ASC, ib.id ASC
            ");
            $stmt->execute();
            $expired = $stmt->fetchAll();

            Response::success(['expired_batches' => $expired], 'Expired batches retrieved successfully');
            break;
            
        case 'check_stock':
            // Check if sufficient stock available for a list of items
            $items = getParam('items'); // Array of {ingredient_id, quantity}
            
            if (!$items || !is_array($items)) {
                Response::error('Items array is required', 400);
            }
            
            $stockCheck = [];
            $allAvailable = true;
            
            foreach ($items as $item) {
                $stmt = $db->prepare("
                    SELECT i.*, 
                        (SELECT COALESCE(SUM(remaining_quantity), 0) 
                         FROM ingredient_batches ib 
                         WHERE ib.ingredient_id = i.id 
                         AND ib.status IN ('available', 'partially_used')) as available_quantity
                    FROM ingredients i
                    WHERE i.id = ?
                ");
                $stmt->execute([$item['ingredient_id']]);
                $ing = $stmt->fetch();
                
                $available = $ing ? max((float)$ing['available_quantity'], (float)$ing['current_stock']) : 0;
                $needed = (float)$item['quantity'];
                $sufficient = $available >= $needed;
                
                if (!$sufficient) $allAvailable = false;
                
                $stockCheck[] = [
                    'ingredient_id' => $item['ingredient_id'],
                    'ingredient_name' => $ing ? $ing['ingredient_name'] : 'Unknown',
                    'needed' => $needed,
                    'available' => $available,
                    'sufficient' => $sufficient,
                    'shortage' => $sufficient ? 0 : ($needed - $available)
                ];
            }
            
            Response::success([
                'all_available' => $allAvailable,
                'items' => $stockCheck
            ], 'Stock check completed');
            break;
            
        case 'reorder_alerts':
            // Get all items below reorder threshold (for Reorder Alert Report)
            $includeOk = getParam('include_ok') === '1';
            
            $sql = "
                SELECT 
                    'ingredient' AS item_type,
                    i.id AS item_id,
                    i.ingredient_code AS item_code,
                    i.ingredient_name AS item_name,
                    ic.category_name,
                    i.unit_of_measure,
                    i.current_stock,
                    i.minimum_stock,
                    COALESCE(i.reorder_point, i.minimum_stock * 1.5) AS reorder_point,
                    i.maximum_stock,
                    COALESCE(i.lead_time_days, 7) AS lead_time_days,
                    i.unit_cost,
                    CASE 
                        WHEN i.current_stock <= 0 THEN 'OUT_OF_STOCK'
                        WHEN i.current_stock <= i.minimum_stock THEN 'CRITICAL'
                        WHEN i.current_stock <= COALESCE(i.reorder_point, i.minimum_stock * 1.5) THEN 'LOW'
                        ELSE 'OK'
                    END AS stock_status,
                    CASE 
                        WHEN i.current_stock <= 0 THEN 0
                        ELSE ROUND((i.current_stock / NULLIF(i.minimum_stock, 0)) * 100, 1)
                    END AS stock_percentage,
                    GREATEST(0, COALESCE(i.maximum_stock, COALESCE(i.reorder_point, i.minimum_stock * 1.5)) - i.current_stock) AS qty_to_reorder
                FROM ingredients i
                LEFT JOIN ingredient_categories ic ON i.category_id = ic.id
                WHERE i.is_active = 1
            ";
            
            if (!$includeOk) {
                $sql .= " AND i.current_stock <= COALESCE(i.reorder_point, i.minimum_stock * 1.5)";
            }
            
            $sql .= " ORDER BY 
                CASE 
                    WHEN i.current_stock <= 0 THEN 1
                    WHEN i.current_stock <= i.minimum_stock THEN 2
                    WHEN i.current_stock <= COALESCE(i.reorder_point, i.minimum_stock * 1.5) THEN 3
                    ELSE 4
                END,
                i.ingredient_name ASC
            ";
            
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $alerts = $stmt->fetchAll();
            
            // Summary counts
            $summary = [
                'out_of_stock' => 0,
                'critical' => 0,
                'low' => 0,
                'ok' => 0,
                'total_alerts' => 0
            ];
            
            foreach ($alerts as $alert) {
                $status = strtolower($alert['stock_status']);
                if (isset($summary[$status])) {
                    $summary[$status]++;
                }
                if ($status !== 'ok') {
                    $summary['total_alerts']++;
                }
            }
            
            Response::success([
                'alerts' => $alerts,
                'summary' => $summary
            ], 'Reorder alerts retrieved successfully');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

/**
 * Handle POST requests - Receive new batch, create ingredient
 */
function handlePost($db, $currentUser) {
    // Only warehouse_raw and GM can receive stock
    if (!in_array($currentUser['role'], ['warehouse_raw', 'general_manager', 'purchaser'])) {
        Response::error('Permission denied', 403);
    }
    
    $action = getParam('action', 'receive');
    
    switch ($action) {
        case 'receive':
            Response::error('Manual receiving is disabled. Use the PO receiving workflow.', 403);
            break;
            
        case 'create':
            // Create new ingredient (GM/Purchaser only)
            if (!in_array($currentUser['role'], ['general_manager', 'purchaser'])) {
                Response::error('Only GM or Purchaser can create ingredients', 403);
            }

            $ingredientCode = getParam('ingredient_code');
            $ingredientName = getParam('ingredient_name');
            $categoryId = getParam('category_id');
            $unitOfMeasure = getParam('unit_of_measure');
            $minimumStock = getParam('minimum_stock', 0);
            $maximumStock = getParam('maximum_stock');
            $storageLocation = getParam('storage_location');
            $storageRequirements = getParam('storage_requirements');
            $shelfLifeDays = getParam('shelf_life_days');
            $isPerishable = getParam('is_perishable', 1);
            $packSizeValue = getParam('pack_size_value');
            $packSizeUnit = getParam('pack_size_unit');
            $packLabel = getParam('pack_label');

            if (!$ingredientCode || !$ingredientName || !$unitOfMeasure) {
                Response::error('Ingredient code, name, and unit of measure are required', 400);
            }

            // Pack size sanity: if any pack field is provided, value+unit must both be present and positive.
            $hasAnyPack = ($packSizeValue !== null && $packSizeValue !== '')
                       || ($packSizeUnit !== null && $packSizeUnit !== '')
                       || ($packLabel !== null && $packLabel !== '');
            $hasAllPack = $packSizeValue !== null && $packSizeValue !== ''
                       && $packSizeUnit !== null && $packSizeUnit !== '';
            if ($hasAnyPack && !$hasAllPack) {
                Response::error('Pack size requires both a value and a unit', 400);
            }
            if ($hasAllPack && floatval($packSizeValue) <= 0) {
                Response::error('Pack size value must be greater than 0', 400);
            }

            // Check duplicate
            $check = $db->prepare("SELECT id FROM ingredients WHERE ingredient_code = ?");
            $check->execute([$ingredientCode]);
            if ($check->fetch()) {
                Response::error('Ingredient code already exists', 400);
            }

            $stmt = $db->prepare("
                INSERT INTO ingredients
                (ingredient_code, ingredient_name, category_id, unit_of_measure,
                 pack_size_value, pack_size_unit, pack_label,
                 minimum_stock, maximum_stock, storage_location, storage_requirements, shelf_life_days, is_perishable)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $ingredientCode,
                $ingredientName,
                $categoryId,
                $unitOfMeasure,
                $hasAllPack ? floatval($packSizeValue) : null,
                $hasAllPack ? $packSizeUnit : null,
                $packLabel ?: null,
                $minimumStock,
                $maximumStock !== null && $maximumStock !== '' ? $maximumStock : null,
                $storageLocation,
                $storageRequirements,
                $shelfLifeDays,
                $isPerishable ? 1 : 0
            ]);

            Response::success(['id' => $db->lastInsertId()], 'Ingredient created successfully');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

/**
 * Handle PUT requests - Issue, adjust stock
 */
function handlePut($db, $currentUser) {
    $action = getParam('action');
    
    if (!$action) {
        Response::error('Action is required', 400);
    }
    
    switch ($action) {
        case 'issue':
            // Issue ingredients (FIFO)
            $ingredientId = getParam('ingredient_id');
            $quantity = getParam('quantity');
            $requisitionId = getParam('requisition_id');
            $reason = getParam('reason', 'Issued for production');
            
            if (!$ingredientId || !$quantity || $quantity <= 0) {
                Response::error('Ingredient ID and valid quantity are required', 400);
            }
            
            $db->beginTransaction();
            
            try {
                // Get ingredient info
                $ingredient = $db->prepare("SELECT * FROM ingredients WHERE id = ? AND is_active = 1 FOR UPDATE");
                $ingredient->execute([$ingredientId]);
                $ingredientData = $ingredient->fetch();
                
                if (!$ingredientData) {
                    throw new Exception('Ingredient not found');
                }
                
                ensureIngredientBatchesForIssue($db, $ingredientData, $quantity, $currentUser);
                $batchList = getUsableIngredientBatches($db, $ingredientId, true);
                
                $totalAvailable = array_sum(array_column($batchList, 'remaining_quantity'));
                
                if ($totalAvailable < $quantity) {
                    throw new Exception("Insufficient stock. Available: {$totalAvailable} {$ingredientData['unit_of_measure']}, Needed: {$quantity}");
                }
                
                $remainingToIssue = $quantity;
                $issuedBatches = [];
                
                foreach ($batchList as $batch) {
                    if ($remainingToIssue <= 0) break;
                    
                    $issueFromBatch = min($batch['remaining_quantity'], $remainingToIssue);
                    $newRemaining = $batch['remaining_quantity'] - $issueFromBatch;
                    $newStatus = $newRemaining > 0 ? 'partially_used' : 'consumed';
                    
                    // Update batch
                    $stmt = $db->prepare("
                        UPDATE ingredient_batches 
                        SET remaining_quantity = ?, status = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$newRemaining, $newStatus, $batch['id']]);
                    
                    // Create transaction record
                    $txCode = generateCode('TX');
                    $stmt = $db->prepare("
                        INSERT INTO inventory_transactions 
                        (transaction_code, transaction_type, item_type, item_id, batch_id,
                         quantity, unit_of_measure, reference_type, reference_id,
                         from_location, performed_by, reason)
                        VALUES (?, 'production_issue', 'ingredient', ?, ?, ?, ?, 'requisition', ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $txCode,
                        $ingredientId,
                        $batch['id'],
                        $issueFromBatch,
                        $ingredientData['unit_of_measure'],
                        $requisitionId,
                        $ingredientData['storage_location'],
                        $currentUser['user_id'],
                        $reason
                    ]);
                    
                    $issuedBatches[] = [
                        'batch_id' => $batch['id'],
                        'batch_code' => $batch['batch_code'],
                        'quantity_issued' => $issueFromBatch,
                        'transaction_code' => $txCode
                    ];
                    
                    $remainingToIssue -= $issueFromBatch;
                }
                
                // Update ingredient current stock
                $stmt = $db->prepare("
                    UPDATE ingredients 
                    SET current_stock = GREATEST(current_stock - ?, 0), updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$quantity, $ingredientId]);
                
                $db->commit();
                
                Response::success([
                    'ingredient_code' => $ingredientData['ingredient_code'],
                    'total_issued' => $quantity,
                    'batches' => $issuedBatches
                ], 'Ingredients issued successfully');
                
            } catch (Exception $e) {
                $db->rollBack();
                Response::error($e->getMessage(), 400);
            }
            break;
            
        case 'adjust':
            // Adjust stock (physical count correction)
            if (!in_array($currentUser['role'], ['warehouse_raw', 'general_manager'])) {
                Response::error('Only Warehouse Raw or GM can adjust stock', 403);
            }
            
            $ingredientId = getParam('ingredient_id');
            $newQuantity = getParam('new_quantity');
            $reason = getParam('reason');
            
            if (!$ingredientId || $newQuantity === null || !$reason) {
                Response::error('Ingredient ID, new quantity, and reason are required', 400);
            }
            
            try {
                $db->beginTransaction();
                // Get current stock
                $ingredient = $db->prepare("SELECT * FROM ingredients WHERE id = ? AND is_active = 1");
                $ingredient->execute([$ingredientId]);
                $ingredientData = $ingredient->fetch();
                
                if (!$ingredientData) {
                    throw new Exception('Ingredient not found');
                }
                
                $oldQuantity = (float) $ingredientData['current_stock'];
                $newQuantity = (float) $newQuantity;
                $difference = $newQuantity - $oldQuantity;

                if ($newQuantity < 0) {
                    throw new Exception('New quantity cannot be negative');
                }

                if ($difference > 0) {
                    throw new Exception('Stock increases must come from PO receiving. Use the receiving workflow.');
                }

                $adjustedBatches = reduceIngredientBatchesToQuantity($db, $ingredientData, $newQuantity, $currentUser, $reason);
                
                // Update ingredient stock
                $stmt = $db->prepare("
                    UPDATE ingredients SET current_stock = ?, updated_at = NOW() WHERE id = ?
                ");
                $stmt->execute([$newQuantity, $ingredientId]);
                
                // Create adjustment transaction
                $txCode = generateCode('TX');
                $stmt = $db->prepare("
                    INSERT INTO inventory_transactions 
                    (transaction_code, transaction_type, item_type, item_id,
                     quantity, unit_of_measure, performed_by, reason)
                    VALUES (?, 'physical_adjust', 'ingredient', ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $txCode,
                    $ingredientId,
                    $difference,
                    $ingredientData['unit_of_measure'],
                    $currentUser['user_id'],
                    "Stock adjustment: $reason (Old: $oldQuantity, New: $newQuantity)"
                ]);
                
                // Log audit
                logAudit($currentUser['user_id'], 'adjust_stock', 'ingredients', $ingredientId, 
                    ['current_stock' => $oldQuantity], 
                    ['current_stock' => $newQuantity, 'reason' => $reason]
                );
                
                $db->commit();
                
                Response::success([
                    'old_quantity' => $oldQuantity,
                    'new_quantity' => $newQuantity,
                    'difference' => $difference,
                    'adjusted_batches' => $adjustedBatches,
                    'transaction_code' => $txCode
                ], 'Stock adjusted successfully');
                
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                Response::error($e->getMessage(), 400);
            }
            break;
            
        case 'update':
            // Update ingredient details (GM/Purchaser only)
            if (!in_array($currentUser['role'], ['general_manager', 'purchaser'])) {
                Response::error('Only GM or Purchaser can update ingredients', 403);
            }
            
            $id = getParam('id');
            if (!$id) {
                Response::error('Ingredient ID is required', 400);
            }
            
            $updateFields = [];
            $params = [];
            
            $allowedFields = ['ingredient_name', 'category_id', 'minimum_stock', 'maximum_stock',
                             'storage_location', 'storage_requirements', 'shelf_life_days', 'is_perishable',
                             'pack_size_value', 'pack_size_unit', 'pack_label'];

            foreach ($allowedFields as $field) {
                $value = getParam($field);
                if ($value !== null) {
                    if ($field === 'is_perishable') {
                        $value = $value ? 1 : 0;
                    }
                    if ($field === 'pack_size_value') {
                        // Empty string clears the pack size; positive number sets it.
                        $value = ($value === '' || $value === null) ? null : floatval($value);
                        if ($value !== null && $value <= 0) {
                            Response::error('Pack size value must be greater than 0', 400);
                        }
                    }
                    if ($field === 'pack_size_unit' && $value === '') {
                        $value = null;
                    }
                    if ($field === 'pack_label' && $value === '') {
                        $value = null;
                    }
                    $updateFields[] = "$field = ?";
                    $params[] = $value;
                }
            }
            
            if (empty($updateFields)) {
                Response::error('No fields to update', 400);
            }
            
            $updateFields[] = "updated_at = NOW()";
            $params[] = $id;
            
            $stmt = $db->prepare("
                UPDATE ingredients 
                SET " . implode(', ', $updateFields) . "
                WHERE id = ?
            ");
            $stmt->execute($params);
            
            Response::success(null, 'Ingredient updated successfully');
            break;
            
        case 'dispose':
            // Dispose expired or damaged batch
            $batchId = getParam('batch_id');
            $reason = getParam('reason');
            
            if (!$batchId || !$reason) {
                Response::error('Batch ID and reason are required', 400);
            }
            
            $db->beginTransaction();
            
            try {
                // Get batch info
                $batch = $db->prepare("
                    SELECT ib.*, i.unit_of_measure, i.ingredient_code
                    FROM ingredient_batches ib
                    JOIN ingredients i ON ib.ingredient_id = i.id
                    WHERE ib.id = ?
                ");
                $batch->execute([$batchId]);
                $batchData = $batch->fetch();
                
                if (!$batchData) {
                    throw new Exception('Batch not found');
                }
                
                if ($batchData['status'] === 'consumed') {
                    throw new Exception('Batch already consumed');
                }
                
                $disposedQuantity = $batchData['remaining_quantity'];
                
                // Update batch status
                $stmt = $db->prepare("
                    UPDATE ingredient_batches 
                    SET status = 'returned', remaining_quantity = 0, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$batchId]);
                
                // Update ingredient stock
                $stmt = $db->prepare("
                    UPDATE ingredients 
                    SET current_stock = current_stock - ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$disposedQuantity, $batchData['ingredient_id']]);
                
                // Create transaction
                $txCode = generateCode('TX');
                $stmt = $db->prepare("
                    INSERT INTO inventory_transactions 
                    (transaction_code, transaction_type, item_type, item_id, batch_id,
                     quantity, unit_of_measure, performed_by, reason)
                    VALUES (?, 'dispose', 'ingredient', ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $txCode,
                    $batchData['ingredient_id'],
                    $batchId,
                    $disposedQuantity,
                    $batchData['unit_of_measure'],
                    $currentUser['user_id'],
                    $reason
                ]);
                
                $db->commit();
                
                Response::success([
                    'disposed_quantity' => $disposedQuantity,
                    'transaction_code' => $txCode
                ], 'Batch disposed successfully');
                
            } catch (Exception $e) {
                $db->rollBack();
                Response::error($e->getMessage(), 400);
            }
            break;
            
        case 'update_settings':
            // Update ingredient settings (min stock, lead time, reorder point)
            $ingredientId = getParam('ingredient_id');
            $minimumStock = getParam('minimum_stock');
            $leadTimeDays = getParam('lead_time_days');
            $reorderPoint = getParam('reorder_point');
            $maximumStock = getParam('maximum_stock');
            
            if (!$ingredientId) {
                Response::error('Ingredient ID is required', 400);
            }
            
            // Verify ingredient exists
            $checkStmt = $db->prepare("SELECT * FROM ingredients WHERE id = ?");
            $checkStmt->execute([$ingredientId]);
            $ingredient = $checkStmt->fetch();
            
            if (!$ingredient) {
                Response::notFound('Ingredient not found');
            }
            
            // Build dynamic update query
            $updates = [];
            $params = [];
            
            if ($minimumStock !== null && $minimumStock !== '') {
                $updates[] = "minimum_stock = ?";
                $params[] = floatval($minimumStock);
            }
            
            if ($leadTimeDays !== null && $leadTimeDays !== '') {
                $updates[] = "lead_time_days = ?";
                $params[] = intval($leadTimeDays);
            }
            
            if ($reorderPoint !== null && $reorderPoint !== '') {
                $updates[] = "reorder_point = ?";
                $params[] = floatval($reorderPoint);
            }

            if ($maximumStock !== null && $maximumStock !== '') {
                $updates[] = "maximum_stock = ?";
                $params[] = floatval($maximumStock);
            }
            
            if (empty($updates)) {
                Response::error('No settings provided to update', 400);
            }
            
            $params[] = $ingredientId;
            
            $sql = "UPDATE ingredients SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            // Log the update
            logAudit($currentUser['user_id'], 'UPDATE_SETTINGS', 'ingredients', $ingredientId, null, [
                'minimum_stock' => $minimumStock,
                'lead_time_days' => $leadTimeDays,
                'reorder_point' => $reorderPoint,
                'maximum_stock' => $maximumStock
            ]);
            
            // Get updated ingredient
            $stmt = $db->prepare("SELECT * FROM ingredients WHERE id = ?");
            $stmt->execute([$ingredientId]);
            $updated = $stmt->fetch();
            
            Response::success([
                'id' => $ingredientId,
                'minimum_stock' => $updated['minimum_stock'],
                'lead_time_days' => $updated['lead_time_days'],
                'reorder_point' => $updated['reorder_point'],
                'maximum_stock' => $updated['maximum_stock']
            ], 'Ingredient settings updated successfully');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}
