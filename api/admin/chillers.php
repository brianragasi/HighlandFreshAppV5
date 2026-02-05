<?php
/**
 * Admin Chiller Locations API
 * Follows highland_fresh_revised.sql schema
 * Table: chiller_locations (NOT chillers)
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
                getChiller($conn, $id);
            } elseif ($action === 'statistics') {
                getChillerStatistics($conn);
            } elseif ($action === 'available') {
                getAvailableChillers($conn);
            } else {
                getChillers($conn);
            }
            break;
        case 'POST':
            createChiller($conn);
            break;
        case 'PUT':
            if ($id) {
                updateChiller($conn, $id);
            } else {
                sendError('Chiller ID required', 400);
            }
            break;
        case 'DELETE':
            if ($id) {
                deleteChiller($conn, $id);
            } else {
                sendError('Chiller ID required', 400);
            }
            break;
        default:
            sendError('Method not allowed', 405);
    }
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}

/**
 * Get all chillers with pagination and filters
 */
function getChillers($conn) {
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;
    $offset = ($page - 1) * $limit;
    
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $status = isset($_GET['status']) ? $_GET['status'] : '';
    $isActive = isset($_GET['is_active']) ? $_GET['is_active'] : '';
    
    // Build WHERE clause
    $where = [];
    $params = [];
    
    if ($search) {
        $where[] = "(cl.chiller_code LIKE ? OR cl.chiller_name LIKE ? OR cl.location LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if ($status) {
        $where[] = "cl.status = ?";
        $params[] = $status;
    }
    
    if ($isActive !== '') {
        $where[] = "cl.is_active = ?";
        $params[] = intval($isActive);
    }
    
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM chiller_locations cl $whereClause";
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get chillers
    $sql = "SELECT 
                cl.id,
                cl.chiller_code,
                cl.chiller_name,
                cl.capacity,
                cl.current_count,
                (cl.capacity - cl.current_count) as available_space,
                ROUND((cl.current_count / cl.capacity) * 100, 1) as fill_percentage,
                cl.temperature_celsius,
                cl.min_temperature,
                cl.max_temperature,
                cl.location,
                cl.status,
                cl.is_active,
                cl.notes,
                cl.created_at,
                cl.updated_at,
                CASE 
                    WHEN cl.temperature_celsius < cl.min_temperature THEN 'too_cold'
                    WHEN cl.temperature_celsius > cl.max_temperature THEN 'too_warm'
                    ELSE 'normal'
                END as temperature_status
            FROM chiller_locations cl
            $whereClause
            ORDER BY cl.chiller_code ASC
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $chillers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendSuccess([
        'chillers' => $chillers,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total' => intval($total),
            'total_pages' => ceil($total / $limit)
        ]
    ]);
}

/**
 * Get single chiller by ID
 */
function getChiller($conn, $id) {
    $sql = "SELECT 
                cl.id,
                cl.chiller_code,
                cl.chiller_name,
                cl.capacity,
                cl.current_count,
                (cl.capacity - cl.current_count) as available_space,
                ROUND((cl.current_count / cl.capacity) * 100, 1) as fill_percentage,
                cl.temperature_celsius,
                cl.min_temperature,
                cl.max_temperature,
                cl.location,
                cl.status,
                cl.is_active,
                cl.notes,
                cl.created_at,
                cl.updated_at
            FROM chiller_locations cl
            WHERE cl.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id]);
    $chiller = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$chiller) {
        sendError('Chiller not found', 404);
        return;
    }
    
    // Get inventory stored in this chiller (finished_goods_inventory)
    $inventorySql = "SELECT 
                        fgi.id,
                        fgi.batch_id,
                        pb.batch_code,
                        fgi.product_name,
                        fgi.product_type,
                        fgi.variant,
                        fgi.remaining_quantity,
                        fgi.manufacturing_date,
                        fgi.expiry_date,
                        fgi.status
                     FROM finished_goods_inventory fgi
                     LEFT JOIN production_batches pb ON fgi.batch_id = pb.id
                     WHERE fgi.chiller_id = ?
                     ORDER BY fgi.expiry_date ASC
                     LIMIT 20";
    
    $inventoryStmt = $conn->prepare($inventorySql);
    $inventoryStmt->execute([$id]);
    $chiller['inventory'] = $inventoryStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get usage statistics
    $statsSql = "SELECT 
                    COUNT(*) as total_batches,
                    COALESCE(SUM(remaining_quantity), 0) as total_units,
                    MIN(expiry_date) as nearest_expiry
                 FROM finished_goods_inventory 
                 WHERE chiller_id = ? AND status = 'available'";
    
    $statsStmt = $conn->prepare($statsSql);
    $statsStmt->execute([$id]);
    $chiller['statistics'] = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    sendSuccess(['chiller' => $chiller]);
}

/**
 * Create new chiller location
 */
function createChiller($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['chiller_name', 'capacity'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            sendError("Field '$field' is required", 400);
            return;
        }
    }
    
    // Generate chiller code if not provided
    if (empty($data['chiller_code'])) {
        $prefix = 'CHL';
        $stmt = $conn->query("SELECT MAX(CAST(SUBSTRING(chiller_code, 4) AS UNSIGNED)) as max_num FROM chiller_locations WHERE chiller_code LIKE 'CHL%'");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $nextNum = ($row['max_num'] ?? 0) + 1;
        $data['chiller_code'] = $prefix . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
    }
    
    // Check for duplicate chiller code
    $checkStmt = $conn->prepare("SELECT id FROM chiller_locations WHERE chiller_code = ?");
    $checkStmt->execute([$data['chiller_code']]);
    if ($checkStmt->fetch()) {
        sendError('Chiller code already exists', 409);
        return;
    }
    
    $sql = "INSERT INTO chiller_locations (
                chiller_code, chiller_name, capacity, current_count,
                temperature_celsius, min_temperature, max_temperature,
                location, status, is_active, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $data['chiller_code'],
        $data['chiller_name'],
        $data['capacity'],
        $data['current_count'] ?? 0,
        $data['temperature_celsius'] ?? null,
        $data['min_temperature'] ?? 2.0,
        $data['max_temperature'] ?? 8.0,
        $data['location'] ?? null,
        $data['status'] ?? 'available',
        $data['is_active'] ?? 1,
        $data['notes'] ?? null
    ]);
    
    $chillerId = $conn->lastInsertId();
    
    sendSuccess([
        'message' => 'Chiller location created successfully',
        'chiller_id' => $chillerId,
        'chiller_code' => $data['chiller_code']
    ], 201);
}

/**
 * Update chiller location
 */
function updateChiller($conn, $id) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Check if chiller exists
    $checkStmt = $conn->prepare("SELECT id, chiller_code FROM chiller_locations WHERE id = ?");
    $checkStmt->execute([$id]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existing) {
        sendError('Chiller not found', 404);
        return;
    }
    
    // Build dynamic update
    $updates = [];
    $params = [];
    
    $allowedFields = [
        'chiller_code', 'chiller_name', 'capacity', 'current_count',
        'temperature_celsius', 'min_temperature', 'max_temperature',
        'location', 'status', 'is_active', 'notes'
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
    
    // Check for duplicate chiller code if changing
    if (isset($data['chiller_code']) && $data['chiller_code'] !== $existing['chiller_code']) {
        $dupStmt = $conn->prepare("SELECT id FROM chiller_locations WHERE chiller_code = ? AND id != ?");
        $dupStmt->execute([$data['chiller_code'], $id]);
        if ($dupStmt->fetch()) {
            sendError('Chiller code already exists', 409);
            return;
        }
    }
    
    $params[] = $id;
    $sql = "UPDATE chiller_locations SET " . implode(', ', $updates) . " WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    sendSuccess(['message' => 'Chiller updated successfully']);
}

/**
 * Delete (deactivate) chiller
 */
function deleteChiller($conn, $id) {
    // Check if chiller exists
    $checkStmt = $conn->prepare("SELECT id FROM chiller_locations WHERE id = ?");
    $checkStmt->execute([$id]);
    
    if (!$checkStmt->fetch()) {
        sendError('Chiller not found', 404);
        return;
    }
    
    // Check for related inventory
    $relatedStmt = $conn->prepare("SELECT COUNT(*) as count FROM finished_goods_inventory WHERE chiller_id = ?");
    $relatedStmt->execute([$id]);
    $related = $relatedStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($related['count'] > 0) {
        // Soft delete - deactivate
        $stmt = $conn->prepare("UPDATE chiller_locations SET is_active = 0, status = 'offline' WHERE id = ?");
        $stmt->execute([$id]);
        sendSuccess(['message' => 'Chiller deactivated (has inventory stored)']);
    } else {
        // Hard delete if no related records
        $stmt = $conn->prepare("DELETE FROM chiller_locations WHERE id = ?");
        $stmt->execute([$id]);
        sendSuccess(['message' => 'Chiller deleted successfully']);
    }
}

/**
 * Get available chillers (for dropdowns)
 */
function getAvailableChillers($conn) {
    $sql = "SELECT 
                cl.id,
                cl.chiller_code,
                cl.chiller_name,
                cl.capacity,
                cl.current_count,
                (cl.capacity - cl.current_count) as available_space,
                cl.temperature_celsius,
                cl.location
            FROM chiller_locations cl
            WHERE cl.is_active = 1 AND cl.status IN ('available', 'full')
            ORDER BY available_space DESC, cl.chiller_code";
    
    $stmt = $conn->query($sql);
    $chillers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendSuccess(['chillers' => $chillers]);
}

/**
 * Get chiller statistics
 */
function getChillerStatistics($conn) {
    $stats = [];
    
    // Total chillers by status
    $stmt = $conn->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive,
        SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
        SUM(CASE WHEN status = 'full' THEN 1 ELSE 0 END) as full,
        SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance,
        SUM(CASE WHEN status = 'offline' THEN 1 ELSE 0 END) as offline
    FROM chiller_locations");
    $stats['totals'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Capacity utilization
    $stmt = $conn->query("SELECT 
        SUM(capacity) as total_capacity,
        SUM(current_count) as total_stored,
        ROUND((SUM(current_count) / SUM(capacity)) * 100, 1) as utilization_percent
    FROM chiller_locations
    WHERE is_active = 1");
    $stats['utilization'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Temperature alerts
    $stmt = $conn->query("SELECT 
        id, chiller_code, chiller_name, 
        temperature_celsius, min_temperature, max_temperature,
        CASE 
            WHEN temperature_celsius < min_temperature THEN 'too_cold'
            WHEN temperature_celsius > max_temperature THEN 'too_warm'
        END as alert_type
    FROM chiller_locations
    WHERE is_active = 1 
    AND temperature_celsius IS NOT NULL
    AND (temperature_celsius < min_temperature OR temperature_celsius > max_temperature)");
    $stats['temperature_alerts'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Chillers by location
    $stmt = $conn->query("SELECT 
        COALESCE(location, 'Unspecified') as location, 
        COUNT(*) as count,
        SUM(capacity) as total_capacity
    FROM chiller_locations
    WHERE is_active = 1
    GROUP BY location");
    $stats['by_location'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Near-full chillers (>80% capacity)
    $stmt = $conn->query("SELECT 
        id, chiller_code, chiller_name,
        capacity, current_count,
        ROUND((current_count / capacity) * 100, 1) as fill_percentage
    FROM chiller_locations
    WHERE is_active = 1 
    AND (current_count / capacity) > 0.8
    ORDER BY fill_percentage DESC");
    $stats['near_full'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendSuccess(['statistics' => $stats]);
}
