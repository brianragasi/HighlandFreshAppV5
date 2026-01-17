<?php
/**
 * Highland Fresh System - Single Farmer API
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
    
    // Check if farmer exists
    $checkStmt = $db->prepare("SELECT * FROM farmers WHERE id = ?");
    $checkStmt->execute([$farmerId]);
    $farmer = $checkStmt->fetch();
    
    if (!$farmer) {
        Response::notFound('Farmer not found');
    }
    
    switch ($requestMethod) {
        case 'GET':
            // Get farmer with delivery statistics
            $statsStmt = $db->prepare("
                SELECT 
                    COUNT(DISTINCT md.id) as total_deliveries,
                    SUM(CASE WHEN md.status = 'accepted' THEN md.volume_liters ELSE 0 END) as total_liters_accepted,
                    SUM(CASE WHEN md.status = 'rejected' THEN md.volume_liters ELSE 0 END) as total_liters_rejected,
                    AVG(qmt.fat_percentage) as avg_fat_percentage,
                    MAX(md.delivery_date) as last_delivery_date
                FROM milk_deliveries md
                LEFT JOIN qc_milk_tests qmt ON md.id = qmt.delivery_id
                WHERE md.farmer_id = ?
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
            // Update farmer
            $firstName = trim(getParam('first_name', $farmer['first_name']));
            $lastName = trim(getParam('last_name', $farmer['last_name']));
            $contactNumber = trim(getParam('contact_number', $farmer['contact_number']));
            $address = trim(getParam('address', $farmer['address']));
            $membershipType = getParam('membership_type', $farmer['membership_type']);
            $bankName = trim(getParam('bank_name', $farmer['bank_name']));
            $bankAccount = trim(getParam('bank_account_number', $farmer['bank_account_number']));
            $isActive = getParam('is_active', $farmer['is_active']);
            
            // Validation
            $errors = [];
            if (empty($firstName)) $errors['first_name'] = 'First name is required';
            if (empty($lastName)) $errors['last_name'] = 'Last name is required';
            if (!in_array($membershipType, ['member', 'non_member'])) {
                $errors['membership_type'] = 'Invalid membership type';
            }
            
            if (!empty($errors)) {
                Response::validationError($errors);
            }
            
            // Update base price if membership changed
            $basePrice = $membershipType === 'member' ? MEMBER_PRICE : NON_MEMBER_PRICE;
            
            $oldValues = $farmer;
            
            // Update farmer
            $stmt = $db->prepare("
                UPDATE farmers SET
                    first_name = ?,
                    last_name = ?,
                    contact_number = ?,
                    address = ?,
                    membership_type = ?,
                    base_price_per_liter = ?,
                    bank_name = ?,
                    bank_account_number = ?,
                    is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $firstName, $lastName, $contactNumber, $address,
                $membershipType, $basePrice, $bankName, $bankAccount,
                $isActive, $farmerId
            ]);
            
            // Log audit
            logAudit($currentUser['user_id'], 'UPDATE', 'farmers', $farmerId, $oldValues, [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'membership_type' => $membershipType
            ]);
            
            // Get updated farmer
            $stmt = $db->prepare("SELECT * FROM farmers WHERE id = ?");
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
    Response::error('An error occurred', 500);
}
