<?php
/**
 * Highland Fresh System - Milk Deliveries API
 * 
 * Endpoints:
 * GET  - List all milk deliveries
 * POST - Record new milk delivery
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Require QC role
$currentUser = Auth::requireRole(['qc_officer', 'general_manager']);

try {
    $db = Database::getInstance()->getConnection();
    
    switch ($requestMethod) {
        case 'GET':
            // List deliveries
            $farmerId = getParam('farmer_id');
            $status = getParam('status');
            $dateFrom = getParam('date_from');
            $dateTo = getParam('date_to');
            $page = (int) getParam('page', 1);
            $limit = (int) getParam('limit', 20);
            $offset = ($page - 1) * $limit;
            
            $where = "WHERE 1=1";
            $params = [];
            
            if ($farmerId) {
                $where .= " AND md.farmer_id = ?";
                $params[] = $farmerId;
            }
            
            if ($status && in_array($status, ['pending_test', 'accepted', 'rejected', 'partial'])) {
                $where .= " AND md.status = ?";
                $params[] = $status;
            }
            
            if ($dateFrom) {
                $where .= " AND md.delivery_date >= ?";
                $params[] = $dateFrom;
            }
            
            if ($dateTo) {
                $where .= " AND md.delivery_date <= ?";
                $params[] = $dateTo;
            }
            
            // Get total count
            $countStmt = $db->prepare("
                SELECT COUNT(*) as total 
                FROM milk_deliveries md 
                {$where}
            ");
            $countStmt->execute($params);
            $total = $countStmt->fetch()['total'];
            
            // Get deliveries with farmer info
            $stmt = $db->prepare("
                SELECT md.*, 
                       f.farmer_code, f.first_name as farmer_first_name, f.last_name as farmer_last_name,
                       f.membership_type,
                       u.first_name as receiver_first_name, u.last_name as receiver_last_name,
                       qmt.test_code, qmt.grade, qmt.fat_percentage, qmt.final_price_per_liter, qmt.total_amount
                FROM milk_deliveries md
                LEFT JOIN farmers f ON md.farmer_id = f.id
                LEFT JOIN users u ON md.received_by = u.id
                LEFT JOIN qc_milk_tests qmt ON md.id = qmt.delivery_id
                {$where}
                ORDER BY md.delivery_date DESC, md.delivery_time DESC
                LIMIT ? OFFSET ?
            ");
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            $deliveries = $stmt->fetchAll();
            
            Response::paginated($deliveries, $total, $page, $limit, 'Deliveries retrieved successfully');
            break;
            
        case 'POST':
            // Record new delivery
            $farmerId = getParam('farmer_id');
            $volumeLiters = getParam('volume_liters');
            $deliveryDate = getParam('delivery_date', date('Y-m-d'));
            $deliveryTime = getParam('delivery_time', date('H:i:s'));
            $aptResult = getParam('apt_result'); // ANNEX B: Alcohol Precipitation Test
            $notes = trim(getParam('notes', ''));
            
            // Validation
            $errors = [];
            if (empty($farmerId)) $errors['farmer_id'] = 'Farmer is required';
            if (empty($volumeLiters) || $volumeLiters <= 0) {
                $errors['volume_liters'] = 'Valid volume is required';
            }
            if (!empty($aptResult) && !in_array($aptResult, ['positive', 'negative'])) {
                $errors['apt_result'] = 'APT result must be positive or negative';
            }
            
            // Check if farmer exists and is active
            if ($farmerId) {
                $farmerStmt = $db->prepare("SELECT id, is_active FROM farmers WHERE id = ?");
                $farmerStmt->execute([$farmerId]);
                $farmer = $farmerStmt->fetch();
                
                if (!$farmer) {
                    $errors['farmer_id'] = 'Farmer not found';
                } elseif (!$farmer['is_active']) {
                    $errors['farmer_id'] = 'Farmer is inactive';
                }
            }
            
            if (!empty($errors)) {
                Response::validationError($errors);
            }
            
            // Generate delivery code
            $codeStmt = $db->query("SELECT MAX(CAST(SUBSTRING(delivery_code, 5) AS UNSIGNED)) as max_num FROM milk_deliveries WHERE delivery_code LIKE 'DEL-%'");
            $maxNum = $codeStmt->fetch()['max_num'] ?? 0;
            $deliveryCode = 'DEL-' . str_pad($maxNum + 1, 6, '0', STR_PAD_LEFT);
            
            // Insert delivery
            $stmt = $db->prepare("
                INSERT INTO milk_deliveries (delivery_code, farmer_id, delivery_date, delivery_time, 
                                            volume_liters, received_by, status, apt_result, notes)
                VALUES (?, ?, ?, ?, ?, ?, 'pending_test', ?, ?)
            ");
            $stmt->execute([
                $deliveryCode, $farmerId, $deliveryDate, $deliveryTime,
                $volumeLiters, $currentUser['user_id'], $aptResult ?: null, $notes
            ]);
            
            $deliveryId = $db->lastInsertId();
            
            // Log audit
            logAudit($currentUser['user_id'], 'CREATE', 'milk_deliveries', $deliveryId, null, [
                'delivery_code' => $deliveryCode,
                'farmer_id' => $farmerId,
                'volume_liters' => $volumeLiters
            ]);
            
            // Get created delivery with farmer info
            $stmt = $db->prepare("
                SELECT md.*, 
                       f.farmer_code, f.first_name as farmer_first_name, f.last_name as farmer_last_name,
                       f.membership_type, f.base_price_per_liter
                FROM milk_deliveries md
                LEFT JOIN farmers f ON md.farmer_id = f.id
                WHERE md.id = ?
            ");
            $stmt->execute([$deliveryId]);
            $delivery = $stmt->fetch();
            
            Response::success($delivery, 'Delivery recorded successfully', 201);
            break;
            
        default:
            Response::error('Method not allowed', 405);
    }
    
} catch (Exception $e) {
    error_log("Deliveries API error: " . $e->getMessage());
    Response::error('An error occurred', 500);
}
