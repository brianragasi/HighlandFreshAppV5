<?php
/**
 * Highland Fresh System - Farmers API
 * 
 * Endpoints:
 * GET  - List all farmers
 * POST - Create new farmer
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Require QC or GM role
$currentUser = Auth::requireRole(['qc_officer', 'general_manager', 'finance_officer']);

try {
    $db = Database::getInstance()->getConnection();
    
    switch ($requestMethod) {
        case 'GET':
            // List farmers
            $search = getParam('search', '');
            $membership = getParam('membership', '');
            $status = getParam('status', '');
            $page = (int) getParam('page', 1);
            $limit = (int) getParam('limit', 20);
            $offset = ($page - 1) * $limit;
            
            $where = "WHERE 1=1";
            $params = [];
            
            if (!empty($search)) {
                $where .= " AND (f.farmer_code LIKE ? OR f.first_name LIKE ? OR f.last_name LIKE ? OR f.contact_number LIKE ?)";
                $searchTerm = "%{$search}%";
                $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            }
            
            if (!empty($membership) && in_array($membership, ['member', 'non_member'])) {
                $where .= " AND f.membership_type = ?";
                $params[] = $membership;
            }
            
            if ($status !== '') {
                $where .= " AND f.is_active = ?";
                $params[] = $status === 'active' ? 1 : 0;
            }
            
            // Get total count
            $countStmt = $db->prepare("SELECT COUNT(*) as total FROM farmers f {$where}");
            $countStmt->execute($params);
            $total = $countStmt->fetch()['total'];
            
            // Get farmers (with milk_type_id - revised schema)
            $stmt = $db->prepare("
                SELECT f.id, f.farmer_code, f.first_name, f.last_name, f.contact_number, f.address,
                       f.membership_type, f.milk_type_id, f.base_price_per_liter, f.bank_name, 
                       f.bank_account_number, f.is_active, f.created_at, f.updated_at,
                       mt.type_code as milk_type_code, mt.type_name as milk_type_name
                FROM farmers f
                LEFT JOIN milk_types mt ON f.milk_type_id = mt.id
                {$where}
                ORDER BY f.farmer_code ASC
                LIMIT ? OFFSET ?
            ");
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            $farmers = $stmt->fetchAll();
            
            Response::paginated($farmers, $total, $page, $limit, 'Farmers retrieved successfully');
            break;
            
        case 'POST':
            // Create farmer (with milk_type_id - revised schema)
            $firstName = trim(getParam('first_name', ''));
            $lastName = trim(getParam('last_name', ''));
            $contactNumber = trim(getParam('contact_number', ''));
            $address = trim(getParam('address', ''));
            $membershipType = getParam('membership_type', 'non_member');
            $milkTypeId = getParam('milk_type_id', 1); // Default to COW (1)
            $bankName = trim(getParam('bank_name', ''));
            $bankAccount = trim(getParam('bank_account_number', ''));
            
            // Validation
            $errors = [];
            if (empty($firstName)) $errors['first_name'] = 'First name is required';
            if (!in_array($membershipType, ['member', 'non_member'])) {
                $errors['membership_type'] = 'Invalid membership type';
            }
            
            if (!empty($errors)) {
                Response::validationError($errors);
            }
            
            // Generate farmer code
            $codeStmt = $db->query("SELECT MAX(CAST(SUBSTRING(farmer_code, 5) AS UNSIGNED)) as max_num FROM farmers WHERE farmer_code LIKE 'FRM-%'");
            $maxNum = $codeStmt->fetch()['max_num'] ?? 0;
            $farmerCode = 'FRM-' . str_pad($maxNum + 1, 3, '0', STR_PAD_LEFT);
            
            // Set base price based on membership
            $basePrice = $membershipType === 'member' ? MEMBER_PRICE : NON_MEMBER_PRICE;
            
            // Insert farmer (with milk_type_id)
            $stmt = $db->prepare("
                INSERT INTO farmers (farmer_code, first_name, last_name, contact_number, address,
                                    membership_type, milk_type_id, base_price_per_liter, bank_name, bank_account_number)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $farmerCode, $firstName, $lastName ?: '', $contactNumber, $address,
                $membershipType, $milkTypeId, $basePrice, $bankName, $bankAccount
            ]);
            
            $farmerId = $db->lastInsertId();
            
            // Log audit
            logAudit($currentUser['user_id'], 'CREATE', 'farmers', $farmerId, null, [
                'farmer_code' => $farmerCode,
                'name' => "$firstName $lastName"
            ]);
            
            // Get created farmer with milk type info
            $stmt = $db->prepare("
                SELECT f.*, mt.type_code as milk_type_code, mt.type_name as milk_type_name
                FROM farmers f
                LEFT JOIN milk_types mt ON f.milk_type_id = mt.id
                WHERE f.id = ?
            ");
            $stmt->execute([$farmerId]);
            $farmer = $stmt->fetch();
            
            Response::success($farmer, 'Farmer created successfully', 201);
            break;
            
        default:
            Response::error('Method not allowed', 405);
    }
    
} catch (Exception $e) {
    error_log("Farmers API error: " . $e->getMessage());
    Response::error('An error occurred', 500);
}
