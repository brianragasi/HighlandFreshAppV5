<?php
/**
 * Highland Fresh System - Warehouse Raw Ingredients API
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

// Require Warehouse Raw role
$currentUser = Auth::requireRole(['warehouse_raw', 'general_manager', 'production_staff', 'purchaser']);

try {
    $db = Database::getInstance()->getConnection();
    
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
                     AND ib.status IN ('available', 'partially_used')) as batch_count,
                    (SELECT MIN(expiry_date) FROM ingredient_batches ib 
                     WHERE ib.ingredient_id = i.id 
                     AND ib.status IN ('available', 'partially_used')
                     AND ib.expiry_date IS NOT NULL) as earliest_expiry
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
                
                $available = $ing ? (float)$ing['available_quantity'] : 0;
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
            // Receive new ingredient batch
            $ingredientId = getParam('ingredient_id');
            $quantity = getParam('quantity');
            $unitCost = getParam('unit_cost');
            $supplierId = getParam('supplier');  // Can be ID or name
            $supplierBatchNo = getParam('supplier_batch_no') ?? getParam('batch_number');
            $expiryDate = getParam('expiry_date');
            $manufactureDate = getParam('manufacture_date');
            $notes = getParam('notes');
            
            // Resolve supplier name from ID if numeric
            $supplierName = $supplierId;
            if ($supplierId && is_numeric($supplierId)) {
                $supplierStmt = $db->prepare("SELECT supplier_name FROM suppliers WHERE id = ?");
                $supplierStmt->execute([$supplierId]);
                $supplierData = $supplierStmt->fetch();
                $supplierName = $supplierData ? $supplierData['supplier_name'] : null;
            }
            
            if (!$ingredientId || !$quantity || $quantity <= 0) {
                Response::error('Ingredient ID and valid quantity are required', 400);
            }
            
            $db->beginTransaction();
            
            try {
                // Verify ingredient exists
                $ingredient = $db->prepare("SELECT * FROM ingredients WHERE id = ? AND is_active = 1");
                $ingredient->execute([$ingredientId]);
                $ingredientData = $ingredient->fetch();
                
                if (!$ingredientData) {
                    throw new Exception('Ingredient not found');
                }
                
                // Generate batch code
                $batchCode = generateCode('IB');
                
                // Create batch record
                $stmt = $db->prepare("
                    INSERT INTO ingredient_batches 
                    (batch_code, ingredient_id, quantity, remaining_quantity, unit_cost,
                     supplier_name, supplier_batch_no, received_date, expiry_date, received_by, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, ?)
                ");
                $stmt->execute([
                    $batchCode,
                    $ingredientId,
                    $quantity,
                    $quantity,
                    $unitCost,
                    $supplierName,
                    $supplierBatchNo,
                    $expiryDate,
                    $currentUser['user_id'],
                    $notes
                ]);
                $batchId = $db->lastInsertId();
                
                // Update ingredient current stock
                $stmt = $db->prepare("
                    UPDATE ingredients 
                    SET current_stock = current_stock + ?,
                        unit_cost = COALESCE(?, unit_cost),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$quantity, $unitCost, $ingredientId]);
                
                // Create transaction record
                $txCode = generateCode('TX');
                $stmt = $db->prepare("
                    INSERT INTO inventory_transactions 
                    (transaction_code, transaction_type, item_type, item_id, batch_id,
                     quantity, unit_of_measure, reference_type, to_location, performed_by, reason)
                    VALUES (?, 'receive', 'ingredient', ?, ?, ?, ?, 'purchase', ?, ?, ?)
                ");
                $stmt->execute([
                    $txCode,
                    $ingredientId,
                    $batchId,
                    $quantity,
                    $ingredientData['unit_of_measure'],
                    $ingredientData['storage_location'],
                    $currentUser['user_id'],
                    "Received from supplier: " . ($supplierName ?? 'Unknown')
                ]);
                
                // Log audit
                logAudit($currentUser['user_id'], 'receive_ingredient', 'ingredient_batches', $batchId, null, [
                    'ingredient_id' => $ingredientId,
                    'quantity' => $quantity,
                    'supplier' => $supplierName
                ]);
                
                $db->commit();
                
                Response::success([
                    'batch_id' => $batchId,
                    'batch_code' => $batchCode,
                    'transaction_code' => $txCode
                ], 'Ingredient batch received successfully');
                
            } catch (Exception $e) {
                $db->rollBack();
                Response::error($e->getMessage(), 400);
            }
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
            $storageLocation = getParam('storage_location');
            $storageRequirements = getParam('storage_requirements');
            $shelfLifeDays = getParam('shelf_life_days');
            
            if (!$ingredientCode || !$ingredientName || !$unitOfMeasure) {
                Response::error('Ingredient code, name, and unit of measure are required', 400);
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
                 minimum_stock, storage_location, storage_requirements, shelf_life_days)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $ingredientCode,
                $ingredientName,
                $categoryId,
                $unitOfMeasure,
                $minimumStock,
                $storageLocation,
                $storageRequirements,
                $shelfLifeDays
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
                $ingredient = $db->prepare("SELECT * FROM ingredients WHERE id = ? AND is_active = 1");
                $ingredient->execute([$ingredientId]);
                $ingredientData = $ingredient->fetch();
                
                if (!$ingredientData) {
                    throw new Exception('Ingredient not found');
                }
                
                // Get available batches (FIFO)
                $batches = $db->prepare("
                    SELECT * FROM ingredient_batches
                    WHERE ingredient_id = ?
                    AND status IN ('available', 'partially_used')
                    AND remaining_quantity > 0
                    ORDER BY expiry_date ASC, received_date ASC, id ASC
                ");
                $batches->execute([$ingredientId]);
                $batchList = $batches->fetchAll();
                
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
                        VALUES (?, 'issue', 'ingredient', ?, ?, ?, ?, 'requisition', ?, ?, ?, ?)
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
                    SET current_stock = current_stock - ?, updated_at = NOW()
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
            
            $db->beginTransaction();
            
            try {
                // Get current stock
                $ingredient = $db->prepare("SELECT * FROM ingredients WHERE id = ? AND is_active = 1");
                $ingredient->execute([$ingredientId]);
                $ingredientData = $ingredient->fetch();
                
                if (!$ingredientData) {
                    throw new Exception('Ingredient not found');
                }
                
                $oldQuantity = $ingredientData['current_stock'];
                $difference = $newQuantity - $oldQuantity;
                
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
                    VALUES (?, 'adjust', 'ingredient', ?, ?, ?, ?, ?)
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
                    'transaction_code' => $txCode
                ], 'Stock adjusted successfully');
                
            } catch (Exception $e) {
                $db->rollBack();
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
            
            $allowedFields = ['ingredient_name', 'category_id', 'minimum_stock', 
                             'storage_location', 'storage_requirements', 'shelf_life_days'];
            
            foreach ($allowedFields as $field) {
                $value = getParam($field);
                if ($value !== null) {
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
            
        default:
            Response::error('Invalid action', 400);
    }
}
