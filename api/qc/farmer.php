<?php
/**
 * Highland Fresh System - Single Farmer API
 * 
 * UPDATED: Uses milk_receiving instead of milk_deliveries (revised schema)
 * 
 * Endpoints:
 * GET    - Get farmer details
 * PUT    - Update farmer
 * DELETE - Deactivate farmer
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Require QC or GM role
$currentUser = Auth::requireRole(['qc_officer', 'general_manager', 'finance_officer']);

// Get farmer ID from URL
$farmerId = getParam('id');

if (!$farmerId || !is_numeric($farmerId)) {
    Response::error('Invalid farmer ID', 400);
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Check if farmer exists (with milk_type info - revised schema)
    $checkStmt = $db->prepare("
        SELECT f.*, mt.type_code as milk_type_code, mt.type_name as milk_type_name
        FROM farmers f
        LEFT JOIN milk_types mt ON f.milk_type_id = mt.id
        WHERE f.id = ?
    ");
    $checkStmt->execute([$farmerId]);
    $farmer = $checkStmt->fetch();
    
    if (!$farmer) {
        Response::notFound('Farmer not found');
    }
    
    switch ($requestMethod) {
        case 'GET':
            // Get farmer with receiving statistics (using milk_receiving - revised schema)
            $statsStmt = $db->prepare("
                SELECT 
                    COUNT(DISTINCT mr.id) as total_deliveries,
                    SUM(CASE WHEN mr.status = 'accepted' THEN mr.accepted_liters ELSE 0 END) as total_liters_accepted,
                    SUM(CASE WHEN mr.status = 'rejected' THEN mr.volume_liters ELSE 0 END) as total_liters_rejected,
                    AVG(qmt.fat_percentage) as avg_fat_percentage,
                    MAX(mr.receiving_date) as last_delivery_date
                FROM milk_receiving mr
                LEFT JOIN qc_milk_tests qmt ON mr.id = qmt.receiving_id
                WHERE mr.farmer_id = ?
            ");
            $statsStmt->execute([$farmerId]);
            $stats = $statsStmt->fetch();
            
            $farmer['statistics'] = [
                'total_deliveries' => (int) ($stats['total_deliveries'] ?? 0),
                'total_liters_accepted' => (float) ($stats['total_liters_accepted'] ?? 0),
                'total_liters_rejected' => (float) ($stats['total_liters_rejected'] ?? 0),
                'avg_fat_percentage' => $stats['avg_fat_percentage'] ? round($stats['avg_fat_percentage'], 2) : null,
                'last_delivery_date' => $stats['last_delivery_date']
            ];
            
            Response::success($farmer, 'Farmer retrieved successfully');
            break;
            
        case 'PUT':
            // Update farmer (with milk_type_id - revised schema)
            $firstName = trim(getParam('first_name', $farmer['first_name']));
            $lastName = trim(getParam('last_name', $farmer['last_name'] ?? ''));
            $contactNumber = trim(getParam('contact_number', $farmer['contact_number'] ?? ''));
            $address = trim(getParam('address', $farmer['address'] ?? ''));
            $membershipType = getParam('membership_type', $farmer['membership_type']);
            $milkTypeId = getParam('milk_type_id', $farmer['milk_type_id'] ?? 1);
            $bankName = trim(getParam('bank_name', $farmer['bank_name'] ?? ''));
            $bankAccount = trim(getParam('bank_account_number', $farmer['bank_account_number'] ?? ''));
            $isActive = getParam('is_active', $farmer['is_active']);
            
            // Validation
            $errors = [];
            if (empty($firstName)) $errors['first_name'] = 'First name is required';
            if (!in_array($membershipType, ['member', 'non_member'])) {
                $errors['membership_type'] = 'Invalid membership type';
            }
            
            if (!empty($errors)) {
                Response::validationError($errors);
            }
            
            // Update base price if membership changed
            $basePrice = $membershipType === 'member' ? MEMBER_PRICE : NON_MEMBER_PRICE;
            
            $oldValues = $farmer;
            
            // Update farmer (with milk_type_id - revised schema)
            $stmt = $db->prepare("
                UPDATE farmers SET
                    first_name = ?,
                    last_name = ?,
                    contact_number = ?,
                    address = ?,
                    membership_type = ?,
                    milk_type_id = ?,
                    base_price_per_liter = ?,
                    bank_name = ?,
                    bank_account_number = ?,
                    is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $firstName, $lastName, $contactNumber, $address,
                $membershipType, $milkTypeId, $basePrice, $bankName, $bankAccount,
                $isActive, $farmerId
            ]);
            
            // Log audit
            logAudit($currentUser['user_id'], 'UPDATE', 'farmers', $farmerId, $oldValues, [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'membership_type' => $membershipType
            ]);
            
            // Get updated farmer with milk type info
            $stmt = $db->prepare("
                SELECT f.*, mt.type_code as milk_type_code, mt.type_name as milk_type_name
                FROM farmers f
                LEFT JOIN milk_types mt ON f.milk_type_id = mt.id
                WHERE f.id = ?
            ");
            $stmt->execute([$farmerId]);
            $updatedFarmer = $stmt->fetch();
            
            Response::success($updatedFarmer, 'Farmer updated successfully');
            break;
            
        case 'DELETE':
            // Soft delete (deactivate)
            $stmt = $db->prepare("UPDATE farmers SET is_active = 0 WHERE id = ?");
            $stmt->execute([$farmerId]);
            
            // Log audit
            logAudit($currentUser['user_id'], 'DEACTIVATE', 'farmers', $farmerId, ['is_active' => 1], ['is_active' => 0]);
            
            Response::success(null, 'Farmer deactivated successfully');
            break;
            
        default:
            Response::error('Method not allowed', 405);
    }
    
} catch (Exception $e) {
    error_log("Farmer API error: " . $e->getMessage());
    Response::error('An error occurred: ' . $e->getMessage(), 500);
}
