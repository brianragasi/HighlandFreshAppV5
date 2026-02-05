<?php
/**
 * Admin Farmers API
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
                getFarmer($conn, $id);
            } elseif ($action === 'statistics') {
                getFarmerStatistics($conn);
            } elseif ($action === 'export') {
                exportFarmers($conn);
            } else {
                getFarmers($conn);
            }
            break;
        case 'POST':
            createFarmer($conn);
            break;
        case 'PUT':
            if ($id) {
                updateFarmer($conn, $id);
            } else {
                sendError('Farmer ID required', 400);
            }
            break;
        case 'DELETE':
            if ($id) {
                deleteFarmer($conn, $id);
            } else {
                sendError('Farmer ID required', 400);
            }
            break;
        default:
            sendError('Method not allowed', 405);
    }
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}

/**
 * Get all farmers with pagination and filters
 */
function getFarmers($conn) {
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;
    $offset = ($page - 1) * $limit;
    
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $milkTypeId = isset($_GET['milk_type_id']) ? intval($_GET['milk_type_id']) : null;
    $membershipType = isset($_GET['membership_type']) ? $_GET['membership_type'] : '';
    $isActive = isset($_GET['is_active']) ? $_GET['is_active'] : '';
    
    // Build WHERE clause
    $where = [];
    $params = [];
    
    if ($search) {
        $where[] = "(f.first_name LIKE ? OR f.last_name LIKE ? OR f.farmer_code LIKE ? OR f.contact_number LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if ($milkTypeId) {
        $where[] = "f.milk_type_id = ?";
        $params[] = $milkTypeId;
    }
    
    if ($membershipType && in_array($membershipType, ['member', 'non_member'])) {
        $where[] = "f.membership_type = ?";
        $params[] = $membershipType;
    }
    
    if ($isActive !== '') {
        $where[] = "f.is_active = ?";
        $params[] = intval($isActive);
    }
    
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM farmers f $whereClause";
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get farmers with milk type info
    $sql = "SELECT 
                f.id,
                f.farmer_code,
                f.first_name,
                f.last_name,
                CONCAT(f.first_name, ' ', COALESCE(f.last_name, '')) as full_name,
                f.contact_number,
                f.address,
                f.milk_type_id,
                mt.type_code as milk_type_code,
                mt.type_name as milk_type_name,
                f.membership_type,
                f.base_price_per_liter,
                f.bank_name,
                f.bank_account_number,
                f.is_active,
                f.created_at,
                f.updated_at,
                (SELECT COUNT(*) FROM milk_receiving mr WHERE mr.farmer_id = f.id) as total_deliveries,
                (SELECT COALESCE(SUM(mr.accepted_liters), 0) FROM milk_receiving mr WHERE mr.farmer_id = f.id) as total_liters_delivered
            FROM farmers f
            LEFT JOIN milk_types mt ON f.milk_type_id = mt.id
            $whereClause
            ORDER BY f.farmer_code ASC
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $farmers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendSuccess([
        'farmers' => $farmers,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total' => intval($total),
            'total_pages' => ceil($total / $limit)
        ]
    ]);
}

/**
 * Get single farmer by ID
 */
function getFarmer($conn, $id) {
    $sql = "SELECT 
                f.id,
                f.farmer_code,
                f.first_name,
                f.last_name,
                CONCAT(f.first_name, ' ', COALESCE(f.last_name, '')) as full_name,
                f.contact_number,
                f.address,
                f.milk_type_id,
                mt.type_code as milk_type_code,
                mt.type_name as milk_type_name,
                f.membership_type,
                f.base_price_per_liter,
                f.bank_name,
                f.bank_account_number,
                f.is_active,
                f.created_at,
                f.updated_at
            FROM farmers f
            LEFT JOIN milk_types mt ON f.milk_type_id = mt.id
            WHERE f.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id]);
    $farmer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$farmer) {
        sendError('Farmer not found', 404);
        return;
    }
    
    // Get recent deliveries (milk_receiving)
    $deliverySql = "SELECT 
                        mr.id,
                        mr.receiving_code,
                        mr.rmr_number,
                        mr.receiving_date,
                        mr.volume_liters,
                        mr.accepted_liters,
                        mr.rejected_liters,
                        mr.status,
                        mt.type_name as milk_type
                    FROM milk_receiving mr
                    LEFT JOIN milk_types mt ON mr.milk_type_id = mt.id
                    WHERE mr.farmer_id = ?
                    ORDER BY mr.receiving_date DESC
                    LIMIT 10";
    
    $deliveryStmt = $conn->prepare($deliverySql);
    $deliveryStmt->execute([$id]);
    $farmer['recent_deliveries'] = $deliveryStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get delivery statistics
    $statsSql = "SELECT 
                    COUNT(*) as total_deliveries,
                    COALESCE(SUM(volume_liters), 0) as total_volume,
                    COALESCE(SUM(accepted_liters), 0) as total_accepted,
                    COALESCE(SUM(rejected_liters), 0) as total_rejected,
                    COALESCE(AVG(accepted_liters), 0) as avg_delivery_volume
                 FROM milk_receiving 
                 WHERE farmer_id = ?";
    
    $statsStmt = $conn->prepare($statsSql);
    $statsStmt->execute([$id]);
    $farmer['statistics'] = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    sendSuccess(['farmer' => $farmer]);
}

/**
 * Create new farmer
 */
function createFarmer($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['first_name', 'milk_type_id'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            sendError("Field '$field' is required", 400);
            return;
        }
    }
    
    // Generate farmer code if not provided
    if (empty($data['farmer_code'])) {
        $prefix = 'FRM';
        $stmt = $conn->query("SELECT MAX(CAST(SUBSTRING(farmer_code, 4) AS UNSIGNED)) as max_num FROM farmers WHERE farmer_code LIKE 'FRM%'");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $nextNum = ($row['max_num'] ?? 0) + 1;
        $data['farmer_code'] = $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
    }
    
    // Check for duplicate farmer code
    $checkStmt = $conn->prepare("SELECT id FROM farmers WHERE farmer_code = ?");
    $checkStmt->execute([$data['farmer_code']]);
    if ($checkStmt->fetch()) {
        sendError('Farmer code already exists', 409);
        return;
    }
    
    $sql = "INSERT INTO farmers (
                farmer_code, first_name, last_name, contact_number, address,
                milk_type_id, membership_type, base_price_per_liter,
                bank_name, bank_account_number, is_active
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $data['farmer_code'],
        $data['first_name'],
        $data['last_name'] ?? null,
        $data['contact_number'] ?? null,
        $data['address'] ?? null,
        $data['milk_type_id'],
        $data['membership_type'] ?? 'member',
        $data['base_price_per_liter'] ?? 40.00,
        $data['bank_name'] ?? null,
        $data['bank_account_number'] ?? null,
        $data['is_active'] ?? 1
    ]);
    
    $farmerId = $conn->lastInsertId();
    
    sendSuccess([
        'message' => 'Farmer created successfully',
        'farmer_id' => $farmerId,
        'farmer_code' => $data['farmer_code']
    ], 201);
}

/**
 * Update farmer
 */
function updateFarmer($conn, $id) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Check if farmer exists
    $checkStmt = $conn->prepare("SELECT id, farmer_code FROM farmers WHERE id = ?");
    $checkStmt->execute([$id]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existing) {
        sendError('Farmer not found', 404);
        return;
    }
    
    // Build dynamic update
    $updates = [];
    $params = [];
    
    $allowedFields = [
        'farmer_code', 'first_name', 'last_name', 'contact_number', 'address',
        'milk_type_id', 'membership_type', 'base_price_per_liter',
        'bank_name', 'bank_account_number', 'is_active'
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
    
    // Check for duplicate farmer code if changing
    if (isset($data['farmer_code']) && $data['farmer_code'] !== $existing['farmer_code']) {
        $dupStmt = $conn->prepare("SELECT id FROM farmers WHERE farmer_code = ? AND id != ?");
        $dupStmt->execute([$data['farmer_code'], $id]);
        if ($dupStmt->fetch()) {
            sendError('Farmer code already exists', 409);
            return;
        }
    }
    
    $params[] = $id;
    $sql = "UPDATE farmers SET " . implode(', ', $updates) . " WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    sendSuccess(['message' => 'Farmer updated successfully']);
}

/**
 * Delete (deactivate) farmer
 */
function deleteFarmer($conn, $id) {
    // Check if farmer exists
    $checkStmt = $conn->prepare("SELECT id FROM farmers WHERE id = ?");
    $checkStmt->execute([$id]);
    
    if (!$checkStmt->fetch()) {
        sendError('Farmer not found', 404);
        return;
    }
    
    // Check for related deliveries
    $relatedStmt = $conn->prepare("SELECT COUNT(*) as count FROM milk_receiving WHERE farmer_id = ?");
    $relatedStmt->execute([$id]);
    $related = $relatedStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($related['count'] > 0) {
        // Soft delete - deactivate
        $stmt = $conn->prepare("UPDATE farmers SET is_active = 0 WHERE id = ?");
        $stmt->execute([$id]);
        sendSuccess(['message' => 'Farmer deactivated (has related deliveries)']);
    } else {
        // Hard delete if no related records
        $stmt = $conn->prepare("DELETE FROM farmers WHERE id = ?");
        $stmt->execute([$id]);
        sendSuccess(['message' => 'Farmer deleted successfully']);
    }
}

/**
 * Get farmer statistics
 */
function getFarmerStatistics($conn) {
    $stats = [];
    
    // Total farmers by status
    $stmt = $conn->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
    FROM farmers");
    $stats['totals'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // By milk type
    $stmt = $conn->query("SELECT 
        mt.type_name, 
        COUNT(f.id) as count
    FROM farmers f
    LEFT JOIN milk_types mt ON f.milk_type_id = mt.id
    WHERE f.is_active = 1
    GROUP BY f.milk_type_id, mt.type_name");
    $stats['by_milk_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // By membership type
    $stmt = $conn->query("SELECT 
        membership_type, 
        COUNT(*) as count
    FROM farmers
    WHERE is_active = 1
    GROUP BY membership_type");
    $stats['by_membership'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Monthly deliveries trend (last 6 months)
    $stmt = $conn->query("SELECT 
        DATE_FORMAT(receiving_date, '%Y-%m') as month,
        COUNT(*) as delivery_count,
        SUM(accepted_liters) as total_liters
    FROM milk_receiving
    WHERE receiving_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(receiving_date, '%Y-%m')
    ORDER BY month DESC");
    $stats['monthly_deliveries'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top 10 farmers by delivery volume
    $stmt = $conn->query("SELECT 
        f.id,
        f.farmer_code,
        CONCAT(f.first_name, ' ', COALESCE(f.last_name, '')) as full_name,
        COUNT(mr.id) as delivery_count,
        SUM(mr.accepted_liters) as total_liters
    FROM farmers f
    JOIN milk_receiving mr ON f.id = mr.farmer_id
    WHERE f.is_active = 1
    GROUP BY f.id
    ORDER BY total_liters DESC
    LIMIT 10");
    $stats['top_farmers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendSuccess(['statistics' => $stats]);
}

/**
 * Export farmers to CSV
 */
function exportFarmers($conn) {
    $sql = "SELECT 
                f.farmer_code,
                f.first_name,
                f.last_name,
                f.contact_number,
                f.address,
                mt.type_name as milk_type,
                f.membership_type,
                f.base_price_per_liter,
                f.bank_name,
                f.bank_account_number,
                CASE WHEN f.is_active = 1 THEN 'Active' ELSE 'Inactive' END as status,
                f.created_at
            FROM farmers f
            LEFT JOIN milk_types mt ON f.milk_type_id = mt.id
            ORDER BY f.farmer_code";
    
    $stmt = $conn->query($sql);
    $farmers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendSuccess(['export_data' => $farmers]);
}
