<?php
/**
 * Highland Fresh System - Warehouse Raw MRO API
 * 
 * Manages MRO (Maintenance, Repair, Operations) inventory
 * Spare parts, tools, cleaning supplies, safety equipment
 * 
 * GET    - List MRO items, get details, check stock
 * POST   - Receive new MRO batch
 * PUT    - Issue items, adjust stock
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(dirname(__DIR__)) . '/bootstrap.php';

// Require appropriate roles
$currentUser = Auth::requireRole(['warehouse_raw', 'general_manager', 'maintenance_head', 'purchaser']);

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
    error_log("Warehouse Raw MRO API error: " . $e->getMessage());
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
            // Get all MRO items with stock info
            $categoryId = getParam('category_id');
            $lowStockOnly = getParam('low_stock') === '1';
            $criticalOnly = getParam('critical') === '1';
            $search = getParam('search');
            
            $sql = "
                SELECT 
                    m.*,
                    mc.category_name,
                    CASE 
                        WHEN m.current_stock <= 0 THEN 'out_of_stock'
                        WHEN m.current_stock <= m.minimum_stock THEN 'low_stock'
                        ELSE 'ok'
                    END as stock_status,
                    (SELECT COUNT(*) FROM mro_inventory mi 
                     WHERE mi.mro_item_id = m.id 
                     AND mi.status IN ('available', 'partially_used')) as batch_count
                FROM mro_items m
                LEFT JOIN mro_categories mc ON m.category_id = mc.id
                WHERE m.is_active = 1
            ";
            $params = [];
            
            if ($categoryId) {
                $sql .= " AND m.category_id = ?";
                $params[] = $categoryId;
            }
            
            if ($lowStockOnly) {
                $sql .= " AND m.current_stock <= m.minimum_stock";
            }
            
            if ($criticalOnly) {
                $sql .= " AND m.is_critical = 1";
            }
            
            if ($search) {
                $sql .= " AND (m.item_code LIKE ? OR m.item_name LIKE ?)";
                $params[] = "%{$search}%";
                $params[] = "%{$search}%";
            }
            
            $sql .= " ORDER BY m.is_critical DESC, m.item_name ASC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $items = $stmt->fetchAll();
            
            Response::success(['mro_items' => $items], 'MRO items retrieved successfully');
            break;
            
        case 'detail':
            if (!$id) {
                Response::error('Item ID is required', 400);
            }
            
            // Get item details
            $item = $db->prepare("
                SELECT m.*, mc.category_name
                FROM mro_items m
                LEFT JOIN mro_categories mc ON m.category_id = mc.id
                WHERE m.id = ? AND m.is_active = 1
            ");
            $item->execute([$id]);
            $itemData = $item->fetch();
            
            if (!$itemData) {
                Response::error('MRO item not found', 404);
            }
            
            // Get inventory batches (FIFO order)
            $batches = $db->prepare("
                SELECT 
                    mi.*,
                    u.first_name as received_by_first,
                    u.last_name as received_by_last
                FROM mro_inventory mi
                JOIN users u ON mi.received_by = u.id
                WHERE mi.mro_item_id = ?
                AND mi.status IN ('available', 'partially_used')
                ORDER BY mi.received_date ASC, mi.id ASC
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
                WHERE it.item_type = 'mro' AND it.item_id = ?
                ORDER BY it.created_at DESC
                LIMIT 20
            ");
            $transactions->execute([$id]);
            $txList = $transactions->fetchAll();
            
            Response::success([
                'mro_item' => $itemData,
                'inventory' => $batchList,
                'transactions' => $txList
            ], 'MRO item details retrieved successfully');
            break;
            
        case 'categories':
            // Get all MRO categories
            $stmt = $db->prepare("
                SELECT mc.*, 
                    (SELECT COUNT(*) FROM mro_items WHERE category_id = mc.id AND is_active = 1) as item_count
                FROM mro_categories mc
                WHERE mc.is_active = 1
                ORDER BY mc.category_name ASC
            ");
            $stmt->execute();
            $categories = $stmt->fetchAll();
            
            Response::success(['categories' => $categories], 'Categories retrieved successfully');
            break;
            
        case 'critical_stock':
            // Get critical items that are low on stock
            $stmt = $db->prepare("
                SELECT 
                    m.*,
                    mc.category_name,
                    CASE 
                        WHEN m.current_stock <= 0 THEN 'out_of_stock'
                        WHEN m.current_stock <= m.minimum_stock THEN 'low_stock'
                        ELSE 'ok'
                    END as stock_status
                FROM mro_items m
                LEFT JOIN mro_categories mc ON m.category_id = mc.id
                WHERE m.is_active = 1 
                AND m.is_critical = 1
                AND m.current_stock <= m.minimum_stock
                ORDER BY m.current_stock ASC
            ");
            $stmt->execute();
            $criticalItems = $stmt->fetchAll();
            
            Response::success(['critical_items' => $criticalItems], 'Critical items retrieved successfully');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

/**
 * Handle POST requests - Receive new batch, create item
 */
function handlePost($db, $currentUser) {
    // Only warehouse_raw, GM, purchaser can receive stock
    if (!in_array($currentUser['role'], ['warehouse_raw', 'general_manager', 'purchaser'])) {
        Response::error('Permission denied', 403);
    }
    
    $action = getParam('action', 'receive');
    
    switch ($action) {
        case 'receive':
            // Receive new MRO inventory
            $mroItemId = getParam('mro_item_id');
            $quantity = getParam('quantity');
            $unitCost = getParam('unit_cost');
            $supplierName = getParam('supplier_name');
            $notes = getParam('notes');
            
            if (!$mroItemId || !$quantity || $quantity <= 0) {
                Response::error('MRO Item ID and valid quantity are required', 400);
            }
            
            $db->beginTransaction();
            
            try {
                // Verify item exists
                $item = $db->prepare("SELECT * FROM mro_items WHERE id = ? AND is_active = 1");
                $item->execute([$mroItemId]);
                $itemData = $item->fetch();
                
                if (!$itemData) {
                    throw new Exception('MRO item not found');
                }
                
                // Generate batch code
                $batchCode = generateCode('MRO');
                
                // Create inventory record
                $stmt = $db->prepare("
                    INSERT INTO mro_inventory 
                    (batch_code, mro_item_id, quantity, remaining_quantity, unit_cost,
                     supplier_name, received_date, received_by, notes)
                    VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?, ?)
                ");
                $stmt->execute([
                    $batchCode,
                    $mroItemId,
                    $quantity,
                    $quantity,
                    $unitCost,
                    $supplierName,
                    $currentUser['user_id'],
                    $notes
                ]);
                $batchId = $db->lastInsertId();
                
                // Update item current stock
                $stmt = $db->prepare("
                    UPDATE mro_items 
                    SET current_stock = current_stock + ?,
                        unit_cost = COALESCE(?, unit_cost),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$quantity, $unitCost, $mroItemId]);
                
                // Create transaction record
                $txCode = generateCode('TX');
                $stmt = $db->prepare("
                    INSERT INTO inventory_transactions 
                    (transaction_code, transaction_type, item_type, item_id, batch_id,
                     quantity, unit_of_measure, reference_type, to_location, performed_by, reason)
                    VALUES (?, 'receive', 'mro', ?, ?, ?, ?, 'purchase', ?, ?, ?)
                ");
                $stmt->execute([
                    $txCode,
                    $mroItemId,
                    $batchId,
                    $quantity,
                    $itemData['unit_of_measure'],
                    $itemData['storage_location'],
                    $currentUser['user_id'],
                    "Received from supplier: " . ($supplierName ?? 'Unknown')
                ]);
                
                // Log audit
                logAudit($currentUser['user_id'], 'receive_mro', 'mro_inventory', $batchId, null, [
                    'mro_item_id' => $mroItemId,
                    'quantity' => $quantity,
                    'supplier' => $supplierName
                ]);
                
                $db->commit();
                
                Response::success([
                    'batch_id' => $batchId,
                    'batch_code' => $batchCode,
                    'transaction_code' => $txCode
                ], 'MRO inventory received successfully');
                
            } catch (Exception $e) {
                $db->rollBack();
                Response::error($e->getMessage(), 400);
            }
            break;
            
        case 'create':
            // Create new MRO item (GM/Purchaser only)
            if (!in_array($currentUser['role'], ['general_manager', 'purchaser'])) {
                Response::error('Only GM or Purchaser can create MRO items', 403);
            }
            
            $itemCode = getParam('item_code');
            $itemName = getParam('item_name');
            $categoryId = getParam('category_id');
            $unitOfMeasure = getParam('unit_of_measure', 'pcs');
            $minimumStock = getParam('minimum_stock', 0);
            $storageLocation = getParam('storage_location');
            $compatibleEquipment = getParam('compatible_equipment');
            $isCritical = getParam('is_critical', 0);
            
            if (!$itemCode || !$itemName) {
                Response::error('Item code and name are required', 400);
            }
            
            // Check duplicate
            $check = $db->prepare("SELECT id FROM mro_items WHERE item_code = ?");
            $check->execute([$itemCode]);
            if ($check->fetch()) {
                Response::error('Item code already exists', 400);
            }
            
            $stmt = $db->prepare("
                INSERT INTO mro_items 
                (item_code, item_name, category_id, unit_of_measure,
                 minimum_stock, storage_location, compatible_equipment, is_critical)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $itemCode,
                $itemName,
                $categoryId,
                $unitOfMeasure,
                $minimumStock,
                $storageLocation,
                $compatibleEquipment,
                $isCritical ? 1 : 0
            ]);
            
            Response::success(['id' => $db->lastInsertId()], 'MRO item created successfully');
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
            // Issue MRO items (FIFO)
            $mroItemId = getParam('mro_item_id');
            $quantity = getParam('quantity');
            $requisitionId = getParam('requisition_id');
            $reason = getParam('reason', 'Issued for maintenance');
            
            if (!$mroItemId || !$quantity || $quantity <= 0) {
                Response::error('MRO Item ID and valid quantity are required', 400);
            }
            
            $db->beginTransaction();
            
            try {
                // Get item info
                $item = $db->prepare("SELECT * FROM mro_items WHERE id = ? AND is_active = 1");
                $item->execute([$mroItemId]);
                $itemData = $item->fetch();
                
                if (!$itemData) {
                    throw new Exception('MRO item not found');
                }
                
                // Get available inventory (FIFO)
                $inventory = $db->prepare("
                    SELECT * FROM mro_inventory
                    WHERE mro_item_id = ?
                    AND status IN ('available', 'partially_used')
                    AND remaining_quantity > 0
                    ORDER BY received_date ASC, id ASC
                ");
                $inventory->execute([$mroItemId]);
                $inventoryList = $inventory->fetchAll();
                
                $totalAvailable = array_sum(array_column($inventoryList, 'remaining_quantity'));
                
                if ($totalAvailable < $quantity) {
                    throw new Exception("Insufficient stock. Available: {$totalAvailable} {$itemData['unit_of_measure']}, Needed: {$quantity}");
                }
                
                $remainingToIssue = $quantity;
                $issuedBatches = [];
                
                foreach ($inventoryList as $inv) {
                    if ($remainingToIssue <= 0) break;
                    
                    $issueFromBatch = min($inv['remaining_quantity'], $remainingToIssue);
                    $newRemaining = $inv['remaining_quantity'] - $issueFromBatch;
                    $newStatus = $newRemaining > 0 ? 'partially_used' : 'consumed';
                    
                    // Update inventory
                    $stmt = $db->prepare("
                        UPDATE mro_inventory 
                        SET remaining_quantity = ?, status = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$newRemaining, $newStatus, $inv['id']]);
                    
                    // Create transaction record
                    $txCode = generateCode('TX');
                    $stmt = $db->prepare("
                        INSERT INTO inventory_transactions 
                        (transaction_code, transaction_type, item_type, item_id, batch_id,
                         quantity, unit_of_measure, reference_type, reference_id,
                         from_location, performed_by, reason)
                        VALUES (?, 'issue', 'mro', ?, ?, ?, ?, 'requisition', ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $txCode,
                        $mroItemId,
                        $inv['id'],
                        $issueFromBatch,
                        $itemData['unit_of_measure'],
                        $requisitionId,
                        $itemData['storage_location'],
                        $currentUser['user_id'],
                        $reason
                    ]);
                    
                    $issuedBatches[] = [
                        'batch_id' => $inv['id'],
                        'batch_code' => $inv['batch_code'],
                        'quantity_issued' => $issueFromBatch,
                        'transaction_code' => $txCode
                    ];
                    
                    $remainingToIssue -= $issueFromBatch;
                }
                
                // Update item current stock
                $stmt = $db->prepare("
                    UPDATE mro_items 
                    SET current_stock = current_stock - ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$quantity, $mroItemId]);
                
                $db->commit();
                
                Response::success([
                    'item_code' => $itemData['item_code'],
                    'total_issued' => $quantity,
                    'batches' => $issuedBatches
                ], 'MRO items issued successfully');
                
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
            
            $mroItemId = getParam('mro_item_id');
            $newQuantity = getParam('new_quantity');
            $reason = getParam('reason');
            
            if (!$mroItemId || $newQuantity === null || !$reason) {
                Response::error('MRO Item ID, new quantity, and reason are required', 400);
            }
            
            $db->beginTransaction();
            
            try {
                // Get current stock
                $item = $db->prepare("SELECT * FROM mro_items WHERE id = ? AND is_active = 1");
                $item->execute([$mroItemId]);
                $itemData = $item->fetch();
                
                if (!$itemData) {
                    throw new Exception('MRO item not found');
                }
                
                $oldQuantity = $itemData['current_stock'];
                $difference = $newQuantity - $oldQuantity;
                
                // Update item stock
                $stmt = $db->prepare("
                    UPDATE mro_items SET current_stock = ?, updated_at = NOW() WHERE id = ?
                ");
                $stmt->execute([$newQuantity, $mroItemId]);
                
                // Create adjustment transaction
                $txCode = generateCode('TX');
                $stmt = $db->prepare("
                    INSERT INTO inventory_transactions 
                    (transaction_code, transaction_type, item_type, item_id,
                     quantity, unit_of_measure, performed_by, reason)
                    VALUES (?, 'adjust', 'mro', ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $txCode,
                    $mroItemId,
                    $difference,
                    $itemData['unit_of_measure'],
                    $currentUser['user_id'],
                    "Stock adjustment: $reason (Old: $oldQuantity, New: $newQuantity)"
                ]);
                
                // Log audit
                logAudit($currentUser['user_id'], 'adjust_stock', 'mro_items', $mroItemId, 
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
            // Update MRO item details (GM/Purchaser only)
            if (!in_array($currentUser['role'], ['general_manager', 'purchaser'])) {
                Response::error('Only GM or Purchaser can update MRO items', 403);
            }
            
            $id = getParam('id');
            if (!$id) {
                Response::error('Item ID is required', 400);
            }
            
            $updateFields = [];
            $params = [];
            
            $allowedFields = ['item_name', 'category_id', 'minimum_stock', 
                             'storage_location', 'compatible_equipment', 'is_critical'];
            
            foreach ($allowedFields as $field) {
                $value = getParam($field);
                if ($value !== null) {
                    if ($field === 'is_critical') {
                        $value = $value ? 1 : 0;
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
                UPDATE mro_items 
                SET " . implode(', ', $updateFields) . "
                WHERE id = ?
            ");
            $stmt->execute($params);
            
            Response::success(null, 'MRO item updated successfully');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}
