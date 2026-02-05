<?php
/**
 * Highland Fresh System - Warehouse Raw Tanks API
 *
 * REVISED: Updated for new schema (Feb 2026)
 * - Uses raw_milk_inventory instead of tank_milk_batches
 * - Uses milk_receiving instead of milk_deliveries
 * - Tanks now referenced via tank_id in raw_milk_inventory
 *
 * Manages storage tanks and raw milk batches
 *
 * GET    - List tanks or get single tank details
 * POST   - Assign milk to tank (from QC approved inventory)
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
            $milkTypeId = getParam('milk_type_id');

            $sql = "
                SELECT
                    st.*,
                    mt.type_code as milk_type_code,
                    mt.type_name as milk_type_name,
                    (SELECT COALESCE(SUM(remaining_liters), 0)
                     FROM raw_milk_inventory rmi
                     WHERE rmi.tank_id = st.id
                     AND rmi.status IN ('available', 'reserved')) as stored_liters,
                    (SELECT COUNT(*)
                     FROM raw_milk_inventory rmi
                     WHERE rmi.tank_id = st.id
                     AND rmi.status IN ('available', 'reserved')) as batch_count,
                    (SELECT MIN(expiry_date)
                     FROM raw_milk_inventory rmi
                     WHERE rmi.tank_id = st.id
                     AND rmi.status IN ('available', 'reserved')) as earliest_expiry
                FROM storage_tanks st
                LEFT JOIN milk_types mt ON st.milk_type_id = mt.id
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
            if ($milkTypeId) {
                $sql .= " AND st.milk_type_id = ?";
                $params[] = $milkTypeId;
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
                SELECT st.*, mt.type_code as milk_type_code, mt.type_name as milk_type_name
                FROM storage_tanks st
                LEFT JOIN milk_types mt ON st.milk_type_id = mt.id
                WHERE st.id = ? AND st.is_active = 1
            ");
            $tank->execute([$id]);
            $tankData = $tank->fetch();

            if (!$tankData) {
                Response::error('Tank not found', 404);
            }

            // Get milk batches in this tank (from raw_milk_inventory - revised schema)
            $batches = $db->prepare("
                SELECT
                    rmi.*,
                    mr.receiving_code,
                    mr.rmr_number,
                    f.farmer_code,
                    CONCAT(COALESCE(f.first_name, ''), ' ', COALESCE(f.last_name, '')) as farmer_name,
                    mt.type_code as milk_type_code,
                    mt.type_name as milk_type_name,
                    u.first_name as received_by_first,
                    u.last_name as received_by_last,
                    DATEDIFF(rmi.expiry_date, CURDATE()) as days_until_expiry
                FROM raw_milk_inventory rmi
                JOIN milk_receiving mr ON rmi.receiving_id = mr.id
                JOIN farmers f ON mr.farmer_id = f.id
                LEFT JOIN milk_types mt ON rmi.milk_type_id = mt.id
                LEFT JOIN users u ON rmi.received_by = u.id
                WHERE rmi.tank_id = ?
                AND rmi.status IN ('available', 'reserved')
                ORDER BY rmi.expiry_date ASC, rmi.received_date ASC, rmi.id ASC
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
            $milkTypeId = getParam('milk_type_id');

            $sql = "
                SELECT
                    rmi.id,
                    rmi.batch_code,
                    rmi.tank_id,
                    st.tank_code,
                    st.tank_name,
                    rmi.milk_type_id,
                    mt.type_code as milk_type_code,
                    mt.type_name as milk_type_name,
                    rmi.remaining_liters,
                    rmi.received_date,
                    rmi.expiry_date,
                    rmi.grade,
                    DATEDIFF(rmi.expiry_date, CURDATE()) as days_until_expiry
                FROM raw_milk_inventory rmi
                LEFT JOIN storage_tanks st ON rmi.tank_id = st.id
                LEFT JOIN milk_types mt ON rmi.milk_type_id = mt.id
                WHERE rmi.status IN ('available', 'reserved')
                AND rmi.remaining_liters > 0
            ";
            $params = [];

            if ($milkTypeId) {
                $sql .= " AND rmi.milk_type_id = ?";
                $params[] = $milkTypeId;
            }

            $sql .= " ORDER BY rmi.expiry_date ASC, rmi.received_date ASC, rmi.id ASC";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $availableMilk = $stmt->fetchAll();

            // Calculate totals
            $totalLiters = array_sum(array_column($availableMilk, 'remaining_liters'));

            // Group by milk type
            $byMilkType = [];
            foreach ($availableMilk as $milk) {
                $typeCode = $milk['milk_type_code'] ?? 'UNKNOWN';
                if (!isset($byMilkType[$typeCode])) {
                    $byMilkType[$typeCode] = [
                        'type_code' => $typeCode,
                        'type_name' => $milk['milk_type_name'] ?? 'Unknown',
                        'total_liters' => 0,
                        'batch_count' => 0
                    ];
                }
                $byMilkType[$typeCode]['total_liters'] += $milk['remaining_liters'];
                $byMilkType[$typeCode]['batch_count']++;
            }

            Response::success([
                'available_milk' => $availableMilk,
                'total_liters' => (float) $totalLiters,
                'by_milk_type' => array_values($byMilkType)
            ], 'Available milk retrieved successfully');
            break;

        case 'pending_storage':
            // Get QC-approved milk not yet assigned to storage tanks
            $stmt = $db->prepare("
                SELECT
                    rmi.id,
                    rmi.batch_code,
                    rmi.volume_liters,
                    rmi.remaining_liters,
                    rmi.received_date,
                    rmi.expiry_date,
                    rmi.grade,
                    rmi.fat_percentage,
                    rmi.milk_type_id,
                    mt.type_code as milk_type_code,
                    mt.type_name as milk_type_name,
                    mr.receiving_code,
                    mr.rmr_number,
                    f.farmer_code,
                    CONCAT(COALESCE(f.first_name, ''), ' ', COALESCE(f.last_name, '')) as farmer_name
                FROM raw_milk_inventory rmi
                JOIN milk_receiving mr ON rmi.receiving_id = mr.id
                JOIN farmers f ON mr.farmer_id = f.id
                LEFT JOIN milk_types mt ON rmi.milk_type_id = mt.id
                WHERE rmi.status = 'available'
                AND rmi.tank_id IS NULL
                ORDER BY rmi.received_date ASC
            ");
            $stmt->execute();
            $pendingMilk = $stmt->fetchAll();

            Response::success([
                'pending_milk' => $pendingMilk,
                'total_liters' => (float) array_sum(array_column($pendingMilk, 'remaining_liters'))
            ], 'Pending milk for storage retrieved successfully');
            break;

        default:
            Response::error('Invalid action', 400);
    }
}

/**
 * Handle POST requests - Assign milk to tank
 */
function handlePost($db, $currentUser) {
    // Only warehouse_raw can assign milk
    if (!in_array($currentUser['role'], ['warehouse_raw', 'general_manager'])) {
        Response::error('Permission denied', 403);
    }

    $action = getParam('action', 'assign');

    switch ($action) {
        case 'assign':
        case 'receive':  // Frontend uses 'receive', backend uses 'assign' - both should work
            // Assign QC-approved milk to a tank
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

                // Verify raw milk inventory exists and hasn't been assigned yet
                $rawMilk = $db->prepare("
                    SELECT rmi.*, mt.type_code as milk_type_code
                    FROM raw_milk_inventory rmi
                    LEFT JOIN milk_types mt ON rmi.milk_type_id = mt.id
                    WHERE rmi.id = ? AND rmi.status = 'available' AND rmi.tank_id IS NULL
                ");
                $rawMilk->execute([$rawMilkInventoryId]);
                $rawMilkData = $rawMilk->fetch();

                if (!$rawMilkData) {
                    throw new Exception('Raw milk inventory not found or already assigned to a tank');
                }

                // Check milk type compatibility (if tank has dedicated milk type)
                if ($tankData['milk_type_id'] && $tankData['milk_type_id'] != $rawMilkData['milk_type_id']) {
                    throw new Exception('Milk type mismatch. Tank is dedicated for ' . ($tankData['milk_type_code'] ?? 'different milk type'));
                }

                // Check tank capacity
                $newVolume = $tankData['current_volume'] + $rawMilkData['remaining_liters'];
                if ($newVolume > $tankData['capacity_liters']) {
                    throw new Exception('Tank capacity exceeded. Available: ' .
                        ($tankData['capacity_liters'] - $tankData['current_volume']) . 'L');
                }

                // Update raw_milk_inventory to assign tank
                $stmt = $db->prepare("
                    UPDATE raw_milk_inventory
                    SET tank_id = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$tankId, $rawMilkInventoryId]);

                // Update tank current volume and status
                $stmt = $db->prepare("
                    UPDATE storage_tanks
                    SET current_volume = current_volume + ?,
                        status = 'in_use',
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$rawMilkData['remaining_liters'], $tankId]);

                // Create transaction record
                $txCode = generateCode('TX');
                $stmt = $db->prepare("
                    INSERT INTO inventory_transactions
                    (transaction_code, transaction_type, item_type, item_id, batch_id,
                     quantity, unit_of_measure, reference_type, reference_id,
                     to_location, performed_by, reason)
                    VALUES (?, 'transfer', 'raw_milk', ?, ?, ?, 'L', 'tank_assignment', ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $txCode,
                    $rawMilkInventoryId,
                    $rawMilkInventoryId,
                    $rawMilkData['remaining_liters'],
                    $rawMilkInventoryId,
                    $tankData['tank_code'],
                    $currentUser['user_id'],
                    $notes ?? 'Assigned to storage tank'
                ]);

                // Log audit
                logAudit($currentUser['user_id'], 'assign_milk_to_tank', 'raw_milk_inventory', $rawMilkInventoryId, null, [
                    'tank_id' => $tankId,
                    'tank_code' => $tankData['tank_code'],
                    'volume_liters' => $rawMilkData['remaining_liters']
                ]);

                $db->commit();

                Response::success([
                    'raw_milk_inventory_id' => $rawMilkInventoryId,
                    'batch_code' => $rawMilkData['batch_code'],
                    'transaction_code' => $txCode,
                    'tank_code' => $tankData['tank_code'],
                    'volume_stored' => $rawMilkData['remaining_liters']
                ], 'Milk assigned to tank successfully');

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
            $milkTypeId = getParam('milk_type_id');

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
                (tank_code, tank_name, capacity_liters, location, tank_type, milk_type_id, status)
                VALUES (?, ?, ?, ?, ?, ?, 'available')
            ");
            $stmt->execute([$tankCode, $tankName, $capacityLiters, $location, $tankType, $milkTypeId]);

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
            // Issue milk from inventory for production (FIFO)
            $tankId = getParam('tank_id');
            $litersNeeded = getParam('liters');
            $requisitionId = getParam('requisition_id');
            $milkTypeId = getParam('milk_type_id');

            if (!$litersNeeded || $litersNeeded <= 0) {
                Response::error('Valid liters amount is required', 400);
            }

            $db->beginTransaction();

            try {
                // Get available batches from raw_milk_inventory (FIFO - oldest/expiring first)
                $batchesQuery = "
                    SELECT rmi.*, st.tank_code
                    FROM raw_milk_inventory rmi
                    LEFT JOIN storage_tanks st ON rmi.tank_id = st.id
                    WHERE rmi.status IN ('available', 'reserved')
                    AND rmi.remaining_liters > 0
                ";
                $params = [];

                if ($tankId) {
                    $batchesQuery .= " AND rmi.tank_id = ?";
                    $params[] = $tankId;
                }

                if ($milkTypeId) {
                    $batchesQuery .= " AND rmi.milk_type_id = ?";
                    $params[] = $milkTypeId;
                }

                $batchesQuery .= " ORDER BY rmi.expiry_date ASC, rmi.received_date ASC, rmi.id ASC";

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
                    $newStatus = $newRemaining > 0 ? 'reserved' : 'in_production';

                    // Update raw_milk_inventory
                    $stmt = $db->prepare("
                        UPDATE raw_milk_inventory
                        SET remaining_liters = ?, status = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$newRemaining, $newStatus, $batch['id']]);

                    // Update tank volume if milk was in a tank
                    if ($batch['tank_id']) {
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
                            $db->prepare("UPDATE storage_tanks SET status = 'available', current_volume = 0 WHERE id = ?")->execute([$batch['tank_id']]);
                        }
                    }

                    // Create transaction record
                    $txCode = generateCode('TX');
                    $stmt = $db->prepare("
                        INSERT INTO inventory_transactions
                        (transaction_code, transaction_type, item_type, item_id, batch_id,
                         quantity, unit_of_measure, reference_type, reference_id,
                         from_location, performed_by, reason)
                        VALUES (?, 'production_issue', 'raw_milk', ?, ?, ?, 'L', 'requisition', ?, ?, ?, 'Issued for production')
                    ");
                    $stmt->execute([
                        $txCode,
                        $batch['id'],
                        $batch['id'],
                        $issueFromBatch,
                        $requisitionId,
                        $batch['tank_code'] ?? 'UNASSIGNED',
                        $currentUser['user_id']
                    ]);

                    $issuedBatches[] = [
                        'batch_id' => $batch['id'],
                        'batch_code' => $batch['batch_code'],
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

                // Check milk type compatibility
                if ($toTankData['milk_type_id'] && $fromTankData['milk_type_id'] && $toTankData['milk_type_id'] != $fromTankData['milk_type_id']) {
                    throw new Exception('Cannot transfer - milk type mismatch between tanks');
                }

                // Get batches to transfer from raw_milk_inventory (FIFO)
                $batches = $db->prepare("
                    SELECT * FROM raw_milk_inventory
                    WHERE tank_id = ? AND status IN ('available', 'reserved')
                    ORDER BY expiry_date ASC, received_date ASC, id ASC
                ");
                $batches->execute([$fromTankId]);
                $batchList = $batches->fetchAll();

                $remainingToTransfer = $liters;

                foreach ($batchList as $batch) {
                    if ($remainingToTransfer <= 0) break;

                    $transferAmount = min($batch['remaining_liters'], $remainingToTransfer);

                    if ($transferAmount >= $batch['remaining_liters']) {
                        // Transfer entire batch - just update tank_id
                        $stmt = $db->prepare("
                            UPDATE raw_milk_inventory SET tank_id = ?, updated_at = NOW() WHERE id = ?
                        ");
                        $stmt->execute([$toTankId, $batch['id']]);
                    } else {
                        // Split batch - reduce original, no need to create new (just partial transfer)
                        // For simplicity, we'll just move the entire batch if any portion needs to move
                        $stmt = $db->prepare("
                            UPDATE raw_milk_inventory SET tank_id = ?, updated_at = NOW() WHERE id = ?
                        ");
                        $stmt->execute([$toTankId, $batch['id']]);
                        $transferAmount = $batch['remaining_liters']; // Transfer entire batch
                    }

                    $remainingToTransfer -= $transferAmount;
                }

                $actualTransferred = $liters - $remainingToTransfer;

                // Update tank volumes
                $stmt = $db->prepare("
                    UPDATE storage_tanks SET current_volume = current_volume - ?, updated_at = NOW() WHERE id = ?
                ");
                $stmt->execute([$actualTransferred, $fromTankId]);

                $stmt = $db->prepare("
                    UPDATE storage_tanks SET current_volume = current_volume + ?, status = 'in_use', updated_at = NOW() WHERE id = ?
                ");
                $stmt->execute([$actualTransferred, $toTankId]);

                // Check if source tank is now empty
                $checkTank = $db->prepare("SELECT current_volume FROM storage_tanks WHERE id = ?");
                $checkTank->execute([$fromTankId]);
                $tankVol = $checkTank->fetch();
                if ($tankVol && $tankVol['current_volume'] <= 0) {
                    $db->prepare("UPDATE storage_tanks SET status = 'available', current_volume = 0 WHERE id = ?")->execute([$fromTankId]);
                }

                // Create transaction record
                $txCode = generateCode('TX');
                $stmt = $db->prepare("
                    INSERT INTO inventory_transactions
                    (transaction_code, transaction_type, item_type,
                     quantity, unit_of_measure, from_location, to_location, performed_by, reason)
                    VALUES (?, 'transfer', 'raw_milk', ?, 'L', ?, ?, ?, 'Tank transfer')
                ");
                $stmt->execute([
                    $txCode, $actualTransferred, $fromTankData['tank_code'], $toTankData['tank_code'], $currentUser['user_id']
                ]);

                $db->commit();

                Response::success([
                    'transferred_liters' => $actualTransferred,
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
