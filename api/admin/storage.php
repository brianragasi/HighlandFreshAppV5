<?php
/**
 * Admin Storage Tanks API
 * Follows highland_fresh_revised.sql schema
 */

require_once __DIR__ . '/../bootstrap.php';

// Require authentication
Auth::requireAuth();

// Get database connection
$conn = Database::getInstance()->getConnection();

// Get request method and handle routing
$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? intval($_GET['id']) : null;
$action = isset($_GET['action']) ? $_GET['action'] : null;

try {
    switch ($method) {
        case 'GET':
            if ($id) {
                getTank($conn, $id);
            } elseif ($action === 'statistics') {
                getTankStatistics($conn);
            } elseif ($action === 'available') {
                getAvailableTanks($conn);
            } else {
                getTanks($conn);
            }
            break;
        case 'POST':
            createTank($conn);
            break;
        case 'PUT':
            if ($id) {
                updateTank($conn, $id);
            } else {
                sendError('Tank ID required', 400);
            }
            break;
        case 'DELETE':
            if ($id) {
                deleteTank($conn, $id);
            } else {
                sendError('Tank ID required', 400);
            }
            break;
        default:
            sendError('Method not allowed', 405);
    }
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}

/**
 * Get all storage tanks with pagination and filters
 */
function getTanks($conn) {
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;
    $offset = ($page - 1) * $limit;
    
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $tankType = isset($_GET['tank_type']) ? $_GET['tank_type'] : '';
    $milkTypeId = isset($_GET['milk_type_id']) ? intval($_GET['milk_type_id']) : null;
    $status = isset($_GET['status']) ? $_GET['status'] : '';
    $isActive = isset($_GET['is_active']) ? $_GET['is_active'] : '';
    
    // Build WHERE clause
    $where = [];
    $params = [];
    
    if ($search) {
        $where[] = "(st.tank_code LIKE ? OR st.tank_name LIKE ? OR st.location LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if ($tankType) {
        $where[] = "st.tank_type = ?";
        $params[] = $tankType;
    }
    
    if ($milkTypeId) {
        $where[] = "st.milk_type_id = ?";
        $params[] = $milkTypeId;
    }
    
    if ($status) {
        $where[] = "st.status = ?";
        $params[] = $status;
    }
    
    if ($isActive !== '') {
        $where[] = "st.is_active = ?";
        $params[] = intval($isActive);
    }
    
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM storage_tanks st $whereClause";
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get tanks with milk type info
    $sql = "SELECT 
                st.id,
                st.tank_code,
                st.tank_name,
                st.milk_type_id,
                mt.type_code as milk_type_code,
                mt.type_name as milk_type_name,
                st.capacity_liters,
                st.current_volume,
                ROUND((st.current_volume / st.capacity_liters) * 100, 1) as fill_percentage,
                st.location,
                st.tank_type,
                st.temperature_celsius,
                st.last_cleaned_at,
                st.status,
                st.is_active,
                st.notes,
                st.created_at,
                st.updated_at
            FROM storage_tanks st
            LEFT JOIN milk_types mt ON st.milk_type_id = mt.id
            $whereClause
            ORDER BY st.tank_code ASC
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $tanks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendSuccess([
        'tanks' => $tanks,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total' => intval($total),
            'total_pages' => ceil($total / $limit)
        ]
    ]);
}

/**
 * Get single tank by ID
 */
function getTank($conn, $id) {
    $sql = "SELECT 
                st.id,
                st.tank_code,
                st.tank_name,
                st.milk_type_id,
                mt.type_code as milk_type_code,
                mt.type_name as milk_type_name,
                st.capacity_liters,
                st.current_volume,
                ROUND((st.current_volume / st.capacity_liters) * 100, 1) as fill_percentage,
                st.location,
                st.tank_type,
                st.temperature_celsius,
                st.last_cleaned_at,
                st.status,
                st.is_active,
                st.notes,
                st.created_at,
                st.updated_at
            FROM storage_tanks st
            LEFT JOIN milk_types mt ON st.milk_type_id = mt.id
            WHERE st.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id]);
    $tank = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tank) {
        sendError('Tank not found', 404);
        return;
    }
    
    // Get recent milk inventory stored in this tank
    $inventorySql = "SELECT 
                        rmi.id,
                        rmi.batch_code,
                        rmi.volume_liters,
                        rmi.remaining_liters,
                        rmi.received_date,
                        rmi.expiry_date,
                        rmi.grade,
                        rmi.status,
                        mt.type_name as milk_type
                     FROM raw_milk_inventory rmi
                     LEFT JOIN milk_types mt ON rmi.milk_type_id = mt.id
                     WHERE rmi.tank_id = ?
                     ORDER BY rmi.received_date DESC
                     LIMIT 10";
    
    $inventoryStmt = $conn->prepare($inventorySql);
    $inventoryStmt->execute([$id]);
    $tank['recent_inventory'] = $inventoryStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get usage statistics
    $statsSql = "SELECT 
                    COUNT(*) as total_batches,
                    COALESCE(SUM(volume_liters), 0) as total_volume_stored,
                    COALESCE(SUM(remaining_liters), 0) as current_stored
                 FROM raw_milk_inventory 
                 WHERE tank_id = ?";
    
    $statsStmt = $conn->prepare($statsSql);
    $statsStmt->execute([$id]);
    $tank['statistics'] = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    sendSuccess(['tank' => $tank]);
}

/**
 * Create new storage tank
 */
function createTank($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['tank_name', 'capacity_liters', 'tank_type'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            sendError("Field '$field' is required", 400);
            return;
        }
    }
    
    // Validate tank_type - must match schema ENUM
    $validTypes = ['receiving', 'primary', 'secondary', 'holding', 'chiller', 'pasteurized'];
    if (!in_array($data['tank_type'], $validTypes)) {
        sendError('Invalid tank type. Valid types: ' . implode(', ', $validTypes), 400);
        return;
    }
    
    // Generate tank code if not provided
    if (empty($data['tank_code'])) {
        $typePrefixes = [
            'receiving' => 'RCV',
            'primary' => 'PRI',
            'secondary' => 'SEC',
            'holding' => 'HLD',
            'chiller' => 'CHL',
            'pasteurized' => 'PST'
        ];
        $prefix = $typePrefixes[$data['tank_type']] ?? 'TNK';
        $stmt = $conn->query("SELECT MAX(CAST(SUBSTRING(tank_code, 4) AS UNSIGNED)) as max_num FROM storage_tanks WHERE tank_code LIKE '{$prefix}%'");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $nextNum = ($row['max_num'] ?? 0) + 1;
        $data['tank_code'] = $prefix . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
    }
    
    // Check for duplicate tank code
    $checkStmt = $conn->prepare("SELECT id FROM storage_tanks WHERE tank_code = ?");
    $checkStmt->execute([$data['tank_code']]);
    if ($checkStmt->fetch()) {
        sendError('Tank code already exists', 409);
        return;
    }
    
    $sql = "INSERT INTO storage_tanks (
                tank_code, tank_name, milk_type_id, capacity_liters, current_volume,
                location, tank_type, temperature_celsius, last_cleaned_at,
                status, is_active, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $data['tank_code'],
        $data['tank_name'],
        $data['milk_type_id'] ?? null,
        $data['capacity_liters'],
        $data['current_volume'] ?? 0.00,
        $data['location'] ?? null,
        $data['tank_type'],
        $data['temperature_celsius'] ?? null,
        $data['last_cleaned_at'] ?? null,
        $data['status'] ?? 'available',
        $data['is_active'] ?? 1,
        $data['notes'] ?? null
    ]);
    
    $tankId = $conn->lastInsertId();
    
    sendSuccess([
        'message' => 'Storage tank created successfully',
        'tank_id' => $tankId,
        'tank_code' => $data['tank_code']
    ], 201);
}

/**
 * Update storage tank
 */
function updateTank($conn, $id) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Check if tank exists
    $checkStmt = $conn->prepare("SELECT id, tank_code FROM storage_tanks WHERE id = ?");
    $checkStmt->execute([$id]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existing) {
        sendError('Tank not found', 404);
        return;
    }
    
    // Build dynamic update
    $updates = [];
    $params = [];
    
    $allowedFields = [
        'tank_code', 'tank_name', 'milk_type_id', 'capacity_liters', 'current_volume',
        'location', 'tank_type', 'temperature_celsius', 'last_cleaned_at',
        'status', 'is_active', 'notes'
    ];
    
    foreach ($allowedFields as $field) {
        if (array_key_exists($field, $data)) {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
        }
    }
    
    if (empty($updates)) {
        sendError('No fields to update', 400);
        return;
    }
    
    // Validate tank_type if provided
    if (isset($data['tank_type'])) {
        $validTypes = ['receiving', 'primary', 'secondary', 'holding', 'chiller', 'pasteurized'];
        if (!in_array($data['tank_type'], $validTypes)) {
            sendError('Invalid tank type', 400);
            return;
        }
    }
    
    // Check for duplicate tank code if changing
    if (isset($data['tank_code']) && $data['tank_code'] !== $existing['tank_code']) {
        $dupStmt = $conn->prepare("SELECT id FROM storage_tanks WHERE tank_code = ? AND id != ?");
        $dupStmt->execute([$data['tank_code'], $id]);
        if ($dupStmt->fetch()) {
            sendError('Tank code already exists', 409);
            return;
        }
    }
    
    $params[] = $id;
    $sql = "UPDATE storage_tanks SET " . implode(', ', $updates) . " WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    sendSuccess(['message' => 'Tank updated successfully']);
}

/**
 * Delete (deactivate) tank
 */
function deleteTank($conn, $id) {
    // Check if tank exists
    $checkStmt = $conn->prepare("SELECT id FROM storage_tanks WHERE id = ?");
    $checkStmt->execute([$id]);
    
    if (!$checkStmt->fetch()) {
        sendError('Tank not found', 404);
        return;
    }
    
    // Check for related inventory
    $relatedStmt = $conn->prepare("SELECT COUNT(*) as count FROM raw_milk_inventory WHERE tank_id = ?");
    $relatedStmt->execute([$id]);
    $related = $relatedStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($related['count'] > 0) {
        // Soft delete - deactivate
        $stmt = $conn->prepare("UPDATE storage_tanks SET is_active = 0, status = 'offline' WHERE id = ?");
        $stmt->execute([$id]);
        sendSuccess(['message' => 'Tank deactivated (has inventory records)']);
    } else {
        // Hard delete if no related records
        $stmt = $conn->prepare("DELETE FROM storage_tanks WHERE id = ?");
        $stmt->execute([$id]);
        sendSuccess(['message' => 'Tank deleted successfully']);
    }
}

/**
 * Get available tanks (for dropdowns)
 */
function getAvailableTanks($conn) {
    $milkTypeId = isset($_GET['milk_type_id']) ? intval($_GET['milk_type_id']) : null;
    $tankType = isset($_GET['tank_type']) ? $_GET['tank_type'] : null;
    
    $where = ["st.is_active = 1", "st.status = 'available'"];
    $params = [];
    
    if ($milkTypeId) {
        $where[] = "(st.milk_type_id = ? OR st.milk_type_id IS NULL)";
        $params[] = $milkTypeId;
    }
    
    if ($tankType) {
        $where[] = "st.tank_type = ?";
        $params[] = $tankType;
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $where);
    
    $sql = "SELECT 
                st.id,
                st.tank_code,
                st.tank_name,
                st.tank_type,
                st.capacity_liters,
                st.current_volume,
                (st.capacity_liters - st.current_volume) as available_capacity,
                mt.type_name as milk_type
            FROM storage_tanks st
            LEFT JOIN milk_types mt ON st.milk_type_id = mt.id
            $whereClause
            ORDER BY st.tank_code";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $tanks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendSuccess(['tanks' => $tanks]);
}

/**
 * Get tank statistics
 */
function getTankStatistics($conn) {
    $stats = [];
    
    // Total tanks by status
    $stmt = $conn->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive,
        SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
        SUM(CASE WHEN status = 'in_use' THEN 1 ELSE 0 END) as in_use,
        SUM(CASE WHEN status = 'cleaning' THEN 1 ELSE 0 END) as cleaning,
        SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance,
        SUM(CASE WHEN status = 'offline' THEN 1 ELSE 0 END) as offline
    FROM storage_tanks");
    $stats['totals'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // By tank type
    $stmt = $conn->query("SELECT 
        tank_type, 
        COUNT(*) as count,
        SUM(capacity_liters) as total_capacity,
        SUM(current_volume) as total_volume
    FROM storage_tanks
    WHERE is_active = 1
    GROUP BY tank_type");
    $stats['by_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // By milk type
    $stmt = $conn->query("SELECT 
        mt.type_name, 
        COUNT(st.id) as count,
        SUM(st.capacity_liters) as total_capacity,
        SUM(st.current_volume) as total_volume
    FROM storage_tanks st
    LEFT JOIN milk_types mt ON st.milk_type_id = mt.id
    WHERE st.is_active = 1 AND st.milk_type_id IS NOT NULL
    GROUP BY st.milk_type_id, mt.type_name");
    $stats['by_milk_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Capacity utilization
    $stmt = $conn->query("SELECT 
        SUM(capacity_liters) as total_capacity,
        SUM(current_volume) as total_volume,
        ROUND((SUM(current_volume) / SUM(capacity_liters)) * 100, 1) as utilization_percent
    FROM storage_tanks
    WHERE is_active = 1");
    $stats['utilization'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Tanks needing cleaning (not cleaned in 24 hours)
    $stmt = $conn->query("SELECT 
        id, tank_code, tank_name, last_cleaned_at
    FROM storage_tanks
    WHERE is_active = 1 
    AND (last_cleaned_at IS NULL OR last_cleaned_at < DATE_SUB(NOW(), INTERVAL 24 HOUR))
    ORDER BY last_cleaned_at ASC
    LIMIT 10");
    $stats['needs_cleaning'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendSuccess(['statistics' => $stats]);
}
