<?php
/**
 * Highland Fresh System - Warehouse Raw Tanks API
 * 
 * Manages storage tanks and raw milk batches
 * 
 * GET    - List tanks or get single tank details
 * POST   - Receive milk into tank (from QC approved inventory)
 * PUT    - Update tank status, transfer milk, issue milk
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(dirname(__DIR__)) . '/bootstrap.php';

// Require Warehouse Raw role
$currentUser = Auth::requireRole(['warehouse_raw', 'general_manager', 'production_staff']);

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
    error_log("Warehouse Raw Tanks API error: " . $e->getMessage());
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
            // Get all tanks with current status
            $status = getParam('status');
            $tankType = getParam('tank_type');
            
            $sql = "
                SELECT 
                    st.*,
                    (SELECT COALESCE(SUM(remaining_liters), 0) 
                     FROM tank_milk_batches tmb 
                     WHERE tmb.tank_id = st.id 
                     AND tmb.status IN ('available', 'partially_used')) as stored_liters,
                    (SELECT COUNT(*) 
                     FROM tank_milk_batches tmb 
                     WHERE tmb.tank_id = st.id 
                     AND tmb.status IN ('available', 'partially_used')) as batch_count,
                    (SELECT MIN(expiry_date) 
                     FROM tank_milk_batches tmb 
                     WHERE tmb.tank_id = st.id 
                     AND tmb.status IN ('available', 'partially_used')) as earliest_expiry
                FROM storage_tanks st
                WHERE st.is_active = 1
            ";
            $params = [];
            
            if ($status) {
                $sql .= " AND st.status = ?";
                $params[] = $status;
            }
            if ($tankType) {
                $sql .= " AND st.tank_type = ?";
                $params[] = $tankType;
            }
            
            $sql .= " ORDER BY st.tank_code ASC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $tanks = $stmt->fetchAll();
            
            Response::success(['tanks' => $tanks], 'Tanks retrieved successfully');
            break;
            
        case 'detail':
            if (!$id) {
                Response::error('Tank ID is required', 400);
            }
            
            // Get tank details
            $tank = $db->prepare("
                SELECT * FROM storage_tanks WHERE id = ? AND is_active = 1
            ");
            $tank->execute([$id]);
            $tankData = $tank->fetch();
            
            if (!$tankData) {
                Response::error('Tank not found', 404);
            }
            
            // Get milk batches in this tank
            $batches = $db->prepare("
                SELECT 
                    tmb.*,
                    rmi.tank_number as qc_tank_id,
                    md.delivery_code,
                    f.farmer_code,
                    CONCAT(f.first_name, ' ', f.last_name) as farmer_name,
                    u.first_name as received_by_first,
                    u.last_name as received_by_last
                FROM tank_milk_batches tmb
                JOIN raw_milk_inventory rmi ON tmb.raw_milk_inventory_id = rmi.id
                JOIN milk_deliveries md ON rmi.delivery_id = md.id
                JOIN farmers f ON md.farmer_id = f.id
                JOIN users u ON tmb.received_by = u.id
                WHERE tmb.tank_id = ?
                AND tmb.status IN ('available', 'partially_used')
                ORDER BY tmb.received_date ASC, tmb.id ASC
            ");
            $batches->execute([$id]);
            $batchList = $batches->fetchAll();
            
            // Get recent transactions for this tank
            $transactions = $db->prepare("
                SELECT 
                    it.*,
                    u.first_name,
                    u.last_name
                FROM inventory_transactions it
                JOIN users u ON it.performed_by = u.id
                WHERE it.item_type = 'raw_milk'
                AND (it.from_location = ? OR it.to_location = ?)
                ORDER BY it.created_at DESC
                LIMIT 20
            ");
            $transactions->execute([$tankData['tank_code'], $tankData['tank_code']]);
            $txList = $transactions->fetchAll();
            
            Response::success([
                'tank' => $tankData,
                'milk_batches' => $batchList,
                'transactions' => $txList
            ], 'Tank details retrieved successfully');
            break;
            
        case 'available_milk':
            // Get all available milk for production (FIFO order)
            $stmt = $db->prepare("
                SELECT 
                    tmb.id,
                    tmb.tank_id,
                    st.tank_code,
                    st.tank_name,
                    tmb.remaining_liters,
                    tmb.received_date,
                    tmb.expiry_date,
                    DATEDIFF(tmb.expiry_date, CURDATE()) as days_until_expiry
                FROM tank_milk_batches tmb
                JOIN storage_tanks st ON tmb.tank_id = st.id
                WHERE tmb.status IN ('available', 'partially_used')
                AND tmb.remaining_liters > 0
                ORDER BY tmb.expiry_date ASC, tmb.received_date ASC, tmb.id ASC
            ");
            $stmt->execute();
            $availableMilk = $stmt->fetchAll();
            
            // Calculate totals
            $totalLiters = array_sum(array_column($availableMilk, 'remaining_liters'));
            
            Response::success([
                'available_milk' => $availableMilk,
                'total_liters' => (float) $totalLiters
            ], 'Available milk retrieved successfully');
            break;
            
        case 'pending_storage':
            // Get QC-approved milk not yet stored in warehouse tanks
            $stmt = $db->prepare("
                SELECT 
                    rmi.id,
                    rmi.tank_number as qc_tank_id,
                    rmi.volume_liters,
                    rmi.received_date,
                    DATE_ADD(rmi.received_date, INTERVAL 3 DAY) as expiry_date,
                    md.delivery_code,
                    md.grade,
                    md.fat_percentage,
                    f.farmer_code,
                    CONCAT(f.first_name, ' ', f.last_name) as farmer_name
                FROM raw_milk_inventory rmi
                JOIN milk_deliveries md ON rmi.delivery_id = md.id
                JOIN farmers f ON md.farmer_id = f.id
                WHERE rmi.status = 'available'
                AND NOT EXISTS (
                    SELECT 1 FROM tank_milk_batches tmb 
                    WHERE tmb.raw_milk_inventory_id = rmi.id
                )
                ORDER BY rmi.received_date ASC
            ");
            $stmt->execute();
            $pendingMilk = $stmt->fetchAll();
            
            Response::success([
                'pending_milk' => $pendingMilk,
                'total_liters' => (float) array_sum(array_column($pendingMilk, 'volume_liters'))
            ], 'Pending milk for storage retrieved successfully');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

/**
 * Handle POST requests - Receive milk into tank
 */
function handlePost($db, $currentUser) {
    // Only warehouse_raw can receive milk
    if (!in_array($currentUser['role'], ['warehouse_raw', 'general_manager'])) {
        Response::error('Permission denied', 403);
    }
    
    $action = getParam('action', 'receive');
    
    switch ($action) {
        case 'receive':
            // Receive QC-approved milk into a tank
            $tankId = getParam('tank_id');
            $rawMilkInventoryId = getParam('raw_milk_inventory_id');
            $notes = getParam('notes');
            
            if (!$tankId || !$rawMilkInventoryId) {
                Response::error('Tank ID and Raw Milk Inventory ID are required', 400);
            }
            
            $db->beginTransaction();
            
            try {
                // Verify tank exists and is available
                $tank = $db->prepare("SELECT * FROM storage_tanks WHERE id = ? AND is_active = 1");
                $tank->execute([$tankId]);
                $tankData = $tank->fetch();
                
                if (!$tankData) {
                    throw new Exception('Tank not found');
                }
                
                if (!in_array($tankData['status'], ['available', 'in_use'])) {
                    throw new Exception('Tank is not available for receiving milk');
                }
                
                // Verify raw milk inventory exists and hasn't been stored yet
                $rawMilk = $db->prepare("
                    SELECT rmi.*
                    FROM raw_milk_inventory rmi
                    WHERE rmi.id = ? AND rmi.status = 'available'
                ");
                $rawMilk->execute([$rawMilkInventoryId]);
                $rawMilkData = $rawMilk->fetch();
                
                if (!$rawMilkData) {
                    throw new Exception('Raw milk inventory not found or not available');
                }
                
                // Check if already stored
                $alreadyStored = $db->prepare("
                    SELECT id FROM tank_milk_batches WHERE raw_milk_inventory_id = ?
                ");
                $alreadyStored->execute([$rawMilkInventoryId]);
                if ($alreadyStored->fetch()) {
                    throw new Exception('This milk batch has already been stored');
                }
                
                // Check tank capacity
                $newVolume = $tankData['current_volume'] + $rawMilkData['volume_liters'];
                if ($newVolume > $tankData['capacity_liters']) {
                    throw new Exception('Tank capacity exceeded. Available: ' . 
                        ($tankData['capacity_liters'] - $tankData['current_volume']) . 'L');
                }
                
                // Calculate expiry date (3 days from received date)
                $expiryDate = date('Y-m-d', strtotime($rawMilkData['received_date'] . ' +3 days'));
                
                // Create tank milk batch
                $stmt = $db->prepare("
                    INSERT INTO tank_milk_batches 
                    (tank_id, raw_milk_inventory_id, volume_liters, remaining_liters, 
                     received_date, expiry_date, received_by, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'available')
                ");
                $stmt->execute([
                    $tankId,
                    $rawMilkInventoryId,
                    $rawMilkData['volume_liters'],
                    $rawMilkData['volume_liters'],
                    date('Y-m-d'),
                    $expiryDate,
                    $currentUser['id']
                ]);
                $batchId = $db->lastInsertId();
                
                // Update tank current volume and status
                $stmt = $db->prepare("
                    UPDATE storage_tanks 
                    SET current_volume = current_volume + ?,
                        status = 'in_use',
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$rawMilkData['volume_liters'], $tankId]);
                
                // Update raw_milk_inventory status
                $stmt = $db->prepare("
                    UPDATE raw_milk_inventory 
                    SET status = 'in_production', updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$rawMilkInventoryId]);
                
                // Create transaction record
                $txCode = generateCode('TX');
                $stmt = $db->prepare("
                    INSERT INTO inventory_transactions 
                    (transaction_code, transaction_type, item_type, item_id, batch_id,
                     quantity, unit_of_measure, reference_type, reference_id,
                     to_location, performed_by, reason)
                    VALUES (?, 'receive', 'raw_milk', ?, ?, ?, 'L', 'qc_approval', ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $txCode,
                    $rawMilkInventoryId,
                    $batchId,
                    $rawMilkData['volume_liters'],
                    $rawMilkInventoryId,
                    $tankData['tank_code'],
                    $currentUser['id'],
                    $notes ?? 'Received from QC approved inventory'
                ]);
                
                // Log audit
                logAudit($currentUser['id'], 'receive_milk', 'tank_milk_batches', $batchId, null, [
                    'tank_id' => $tankId,
                    'raw_milk_inventory_id' => $rawMilkInventoryId,
                    'volume_liters' => $rawMilkData['volume_liters']
                ]);
                
                $db->commit();
                
                Response::success([
                    'batch_id' => $batchId,
                    'transaction_code' => $txCode,
                    'tank_code' => $tankData['tank_code'],
                    'volume_stored' => $rawMilkData['volume_liters']
                ], 'Milk received into tank successfully');
                
            } catch (Exception $e) {
                $db->rollBack();
                Response::error($e->getMessage(), 400);
            }
            break;
            
        case 'create_tank':
            // Create a new storage tank (GM only)
            if ($currentUser['role'] !== 'general_manager') {
                Response::error('Only General Manager can create tanks', 403);
            }
            
            $tankCode = getParam('tank_code');
            $tankName = getParam('tank_name');
            $capacityLiters = getParam('capacity_liters');
            $location = getParam('location');
            $tankType = getParam('tank_type', 'primary');
            
            if (!$tankCode || !$tankName || !$capacityLiters) {
                Response::error('Tank code, name and capacity are required', 400);
            }
            
            // Check duplicate
            $check = $db->prepare("SELECT id FROM storage_tanks WHERE tank_code = ?");
            $check->execute([$tankCode]);
            if ($check->fetch()) {
                Response::error('Tank code already exists', 400);
            }
            
            $stmt = $db->prepare("
                INSERT INTO storage_tanks 
                (tank_code, tank_name, capacity_liters, location, tank_type, status)
                VALUES (?, ?, ?, ?, ?, 'available')
            ");
            $stmt->execute([$tankCode, $tankName, $capacityLiters, $location, $tankType]);
            
            Response::success(['id' => $db->lastInsertId()], 'Tank created successfully');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

/**
 * Handle PUT requests - Update tank, transfer, issue milk
 */
function handlePut($db, $currentUser) {
    $action = getParam('action');
    $id = getParam('id');
    
    if (!$action) {
        Response::error('Action is required', 400);
    }
    
    switch ($action) {
        case 'update_status':
            // Update tank status (cleaning, maintenance, etc.)
            if (!$id) {
                Response::error('Tank ID is required', 400);
            }
            
            $newStatus = getParam('status');
            $notes = getParam('notes');
            
            if (!in_array($newStatus, ['available', 'in_use', 'cleaning', 'maintenance', 'offline'])) {
                Response::error('Invalid status', 400);
            }
            
            // Check if tank has milk before setting to cleaning/maintenance
            if (in_array($newStatus, ['cleaning', 'maintenance'])) {
                $check = $db->prepare("
                    SELECT current_volume FROM storage_tanks WHERE id = ?
                ");
                $check->execute([$id]);
                $tank = $check->fetch();
                
                if ($tank && $tank['current_volume'] > 0) {
                    Response::error('Cannot set tank to ' . $newStatus . ' while it contains milk', 400);
                }
            }
            
            $updateFields = ['status = ?', 'updated_at = NOW()'];
            $params = [$newStatus];
            
            if ($newStatus === 'available' && getParam('cleaned')) {
                $updateFields[] = 'last_cleaned_at = NOW()';
            }
            
            if ($notes) {
                $updateFields[] = 'notes = ?';
                $params[] = $notes;
            }
            
            $params[] = $id;
            
            $stmt = $db->prepare("
                UPDATE storage_tanks 
                SET " . implode(', ', $updateFields) . "
                WHERE id = ?
            ");
            $stmt->execute($params);
            
            Response::success(null, 'Tank status updated successfully');
            break;
            
        case 'issue_milk':
            // Issue milk from tank for production (FIFO)
            $tankId = getParam('tank_id');
            $litersNeeded = getParam('liters');
            $requisitionId = getParam('requisition_id');
            
            if (!$litersNeeded || $litersNeeded <= 0) {
                Response::error('Valid liters amount is required', 400);
            }
            
            $db->beginTransaction();
            
            try {
                // Get available batches (FIFO - oldest first, expiring first)
                $batchesQuery = "
                    SELECT tmb.*, st.tank_code
                    FROM tank_milk_batches tmb
                    JOIN storage_tanks st ON tmb.tank_id = st.id
                    WHERE tmb.status IN ('available', 'partially_used')
                    AND tmb.remaining_liters > 0
                ";
                $params = [];
                
                if ($tankId) {
                    $batchesQuery .= " AND tmb.tank_id = ?";
                    $params[] = $tankId;
                }
                
                $batchesQuery .= " ORDER BY tmb.expiry_date ASC, tmb.received_date ASC, tmb.id ASC";
                
                $batches = $db->prepare($batchesQuery);
                $batches->execute($params);
                $availableBatches = $batches->fetchAll();
                
                $totalAvailable = array_sum(array_column($availableBatches, 'remaining_liters'));
                
                if ($totalAvailable < $litersNeeded) {
                    throw new Exception("Insufficient milk. Available: {$totalAvailable}L, Needed: {$litersNeeded}L");
                }
                
                $remainingNeeded = $litersNeeded;
                $issuedBatches = [];
                
                foreach ($availableBatches as $batch) {
                    if ($remainingNeeded <= 0) break;
                    
                    $issueFromBatch = min($batch['remaining_liters'], $remainingNeeded);
                    $newRemaining = $batch['remaining_liters'] - $issueFromBatch;
                    $newStatus = $newRemaining > 0 ? 'partially_used' : 'consumed';
                    
                    // Update batch
                    $stmt = $db->prepare("
                        UPDATE tank_milk_batches 
                        SET remaining_liters = ?, status = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$newRemaining, $newStatus, $batch['id']]);
                    
                    // Update tank volume
                    $stmt = $db->prepare("
                        UPDATE storage_tanks 
                        SET current_volume = current_volume - ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$issueFromBatch, $batch['tank_id']]);
                    
                    // Check if tank is now empty
                    $checkTank = $db->prepare("SELECT current_volume FROM storage_tanks WHERE id = ?");
                    $checkTank->execute([$batch['tank_id']]);
                    $tankVol = $checkTank->fetch();
                    if ($tankVol && $tankVol['current_volume'] <= 0) {
                        $db->prepare("UPDATE storage_tanks SET status = 'available' WHERE id = ?")->execute([$batch['tank_id']]);
                    }
                    
                    // Create transaction record
                    $txCode = generateCode('TX');
                    $stmt = $db->prepare("
                        INSERT INTO inventory_transactions 
                        (transaction_code, transaction_type, item_type, item_id, batch_id,
                         quantity, unit_of_measure, reference_type, reference_id,
                         from_location, performed_by, reason)
                        VALUES (?, 'issue', 'raw_milk', ?, ?, ?, 'L', 'requisition', ?, ?, ?, 'Issued for production')
                    ");
                    $stmt->execute([
                        $txCode,
                        $batch['raw_milk_inventory_id'],
                        $batch['id'],
                        $issueFromBatch,
                        $requisitionId,
                        $batch['tank_code'],
                        $currentUser['id']
                    ]);
                    
                    $issuedBatches[] = [
                        'batch_id' => $batch['id'],
                        'tank_code' => $batch['tank_code'],
                        'liters_issued' => $issueFromBatch,
                        'transaction_code' => $txCode
                    ];
                    
                    $remainingNeeded -= $issueFromBatch;
                }
                
                $db->commit();
                
                Response::success([
                    'total_issued' => $litersNeeded,
                    'batches' => $issuedBatches
                ], 'Milk issued successfully');
                
            } catch (Exception $e) {
                $db->rollBack();
                Response::error($e->getMessage(), 400);
            }
            break;
            
        case 'transfer':
            // Transfer milk between tanks
            $fromTankId = getParam('from_tank_id');
            $toTankId = getParam('to_tank_id');
            $liters = getParam('liters');
            
            if (!$fromTankId || !$toTankId || !$liters) {
                Response::error('From tank, to tank, and liters are required', 400);
            }
            
            if ($fromTankId === $toTankId) {
                Response::error('Cannot transfer to the same tank', 400);
            }
            
            $db->beginTransaction();
            
            try {
                // Verify tanks
                $fromTank = $db->prepare("SELECT * FROM storage_tanks WHERE id = ? AND is_active = 1");
                $fromTank->execute([$fromTankId]);
                $fromTankData = $fromTank->fetch();
                
                $toTank = $db->prepare("SELECT * FROM storage_tanks WHERE id = ? AND is_active = 1");
                $toTank->execute([$toTankId]);
                $toTankData = $toTank->fetch();
                
                if (!$fromTankData || !$toTankData) {
                    throw new Exception('Invalid tank(s)');
                }
                
                // Check capacity
                $availableCapacity = $toTankData['capacity_liters'] - $toTankData['current_volume'];
                if ($liters > $availableCapacity) {
                    throw new Exception("Destination tank capacity exceeded. Available: {$availableCapacity}L");
                }
                
                // Check source has enough milk
                if ($fromTankData['current_volume'] < $liters) {
                    throw new Exception("Source tank doesn't have enough milk. Available: {$fromTankData['current_volume']}L");
                }
                
                // Get batches to transfer (FIFO)
                $batches = $db->prepare("
                    SELECT * FROM tank_milk_batches 
                    WHERE tank_id = ? AND status IN ('available', 'partially_used')
                    ORDER BY expiry_date ASC, received_date ASC, id ASC
                ");
                $batches->execute([$fromTankId]);
                $batchList = $batches->fetchAll();
                
                $remainingToTransfer = $liters;
                
                foreach ($batchList as $batch) {
                    if ($remainingToTransfer <= 0) break;
                    
                    $transferAmount = min($batch['remaining_liters'], $remainingToTransfer);
                    
                    if ($transferAmount >= $batch['remaining_liters']) {
                        // Transfer entire batch
                        $stmt = $db->prepare("
                            UPDATE tank_milk_batches SET tank_id = ?, updated_at = NOW() WHERE id = ?
                        ");
                        $stmt->execute([$toTankId, $batch['id']]);
                    } else {
                        // Split batch - reduce original, create new
                        $stmt = $db->prepare("
                            UPDATE tank_milk_batches 
                            SET remaining_liters = remaining_liters - ?, status = 'partially_used', updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([$transferAmount, $batch['id']]);
                        
                        // Create new batch in destination
                        $stmt = $db->prepare("
                            INSERT INTO tank_milk_batches 
                            (tank_id, raw_milk_inventory_id, volume_liters, remaining_liters,
                             received_date, expiry_date, received_by, status)
                            VALUES (?, ?, ?, ?, ?, ?, ?, 'available')
                        ");
                        $stmt->execute([
                            $toTankId,
                            $batch['raw_milk_inventory_id'],
                            $transferAmount,
                            $transferAmount,
                            $batch['received_date'],
                            $batch['expiry_date'],
                            $currentUser['id']
                        ]);
                    }
                    
                    $remainingToTransfer -= $transferAmount;
                }
                
                // Update tank volumes
                $stmt = $db->prepare("
                    UPDATE storage_tanks SET current_volume = current_volume - ?, updated_at = NOW() WHERE id = ?
                ");
                $stmt->execute([$liters, $fromTankId]);
                
                $stmt = $db->prepare("
                    UPDATE storage_tanks SET current_volume = current_volume + ?, status = 'in_use', updated_at = NOW() WHERE id = ?
                ");
                $stmt->execute([$liters, $toTankId]);
                
                // Check if source tank is now empty
                $checkTank = $db->prepare("SELECT current_volume FROM storage_tanks WHERE id = ?");
                $checkTank->execute([$fromTankId]);
                $tankVol = $checkTank->fetch();
                if ($tankVol && $tankVol['current_volume'] <= 0) {
                    $db->prepare("UPDATE storage_tanks SET status = 'available' WHERE id = ?")->execute([$fromTankId]);
                }
                
                // Create transaction record
                $txCode = generateCode('TX');
                $stmt = $db->prepare("
                    INSERT INTO inventory_transactions 
                    (transaction_code, transaction_type, item_type, item_id, 
                     quantity, unit_of_measure, from_location, to_location, performed_by, reason)
                    VALUES (?, 'transfer', 'raw_milk', 0, ?, 'L', ?, ?, ?, 'Tank transfer')
                ");
                $stmt->execute([
                    $txCode, $liters, $fromTankData['tank_code'], $toTankData['tank_code'], $currentUser['id']
                ]);
                
                $db->commit();
                
                Response::success([
                    'transferred_liters' => $liters,
                    'from_tank' => $fromTankData['tank_code'],
                    'to_tank' => $toTankData['tank_code'],
                    'transaction_code' => $txCode
                ], 'Milk transferred successfully');
                
            } catch (Exception $e) {
                $db->rollBack();
                Response::error($e->getMessage(), 400);
            }
            break;
            
        case 'update_temperature':
            // Update tank temperature reading
            if (!$id) {
                Response::error('Tank ID is required', 400);
            }
            
            $temperature = getParam('temperature');
            
            if ($temperature === null) {
                Response::error('Temperature is required', 400);
            }
            
            $stmt = $db->prepare("
                UPDATE storage_tanks 
                SET temperature_celsius = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$temperature, $id]);
            
            Response::success(null, 'Temperature updated successfully');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}
