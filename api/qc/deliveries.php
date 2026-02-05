<?php
/**
 * Highland Fresh System - Milk Receiving API
 * 
 * UPDATED: Uses milk_receiving table (revised schema)
 * 
 * Endpoints:
 * GET  - List all milk receiving records
 * POST - Record new milk receiving
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Require QC or Warehouse Raw role
$currentUser = Auth::requireRole(['qc_officer', 'general_manager', 'warehouse_raw']);

try {
    $db = Database::getInstance()->getConnection();
    
    switch ($requestMethod) {
        case 'GET':
            // List receiving records
            $farmerId = getParam('farmer_id');
            $milkTypeId = getParam('milk_type_id');
            $status = getParam('status');
            $dateFrom = getParam('date_from');
            $dateTo = getParam('date_to');
            $page = (int) getParam('page', 1);
            $limit = (int) getParam('limit', 20);
            $offset = ($page - 1) * $limit;
            
            $where = "WHERE 1=1";
            $params = [];
            
            if ($farmerId) {
                $where .= " AND mr.farmer_id = ?";
                $params[] = $farmerId;
            }
            
            if ($milkTypeId) {
                $where .= " AND mr.milk_type_id = ?";
                $params[] = $milkTypeId;
            }
            
            if ($status && in_array($status, ['pending_qc', 'in_testing', 'accepted', 'rejected', 'partial'])) {
                $where .= " AND mr.status = ?";
                $params[] = $status;
            }
            
            if ($dateFrom) {
                $where .= " AND mr.receiving_date >= ?";
                $params[] = $dateFrom;
            }
            
            if ($dateTo) {
                $where .= " AND mr.receiving_date <= ?";
                $params[] = $dateTo;
            }
            
            // Get total count
            $countStmt = $db->prepare("
                SELECT COUNT(*) as total 
                FROM milk_receiving mr 
                {$where}
            ");
            $countStmt->execute($params);
            $total = $countStmt->fetch()['total'];
            
            // Get receiving records with farmer info and QC test results
            $stmt = $db->prepare("
                SELECT mr.*, 
                       mr.receiving_code as delivery_code,
                       mr.receiving_date as delivery_date,
                       mr.receiving_time as delivery_time,
                       f.farmer_code, 
                       COALESCE(f.first_name, '') as farmer_first_name,
                       COALESCE(f.last_name, '') as farmer_last_name,
                       CONCAT(COALESCE(f.first_name, ''), ' ', COALESCE(f.last_name, '')) as farmer_name,
                       f.membership_type,
                       mt.type_code as milk_type_code, mt.type_name as milk_type_name,
                       u.first_name as receiver_first_name, u.last_name as receiver_last_name,
                       qmt.test_code, qmt.grade, qmt.fat_percentage, qmt.titratable_acidity,
                       qmt.final_price_per_liter, qmt.total_amount
                FROM milk_receiving mr
                LEFT JOIN farmers f ON mr.farmer_id = f.id
                LEFT JOIN milk_types mt ON mr.milk_type_id = mt.id
                LEFT JOIN users u ON mr.received_by = u.id
                LEFT JOIN qc_milk_tests qmt ON mr.id = qmt.receiving_id
                {$where}
                ORDER BY mr.receiving_date DESC, mr.receiving_time DESC
                LIMIT ? OFFSET ?
            ");
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            $records = $stmt->fetchAll();
            
            Response::paginated($records, $total, $page, $limit, 'Receiving records retrieved successfully');
            break;
            
        case 'POST':
            // Record new milk receiving
            $farmerId = getParam('farmer_id');
            $milkTypeId = getParam('milk_type_id');
            $volumeLiters = getParam('volume_liters');
            $receivingDate = getParam('receiving_date', date('Y-m-d'));
            $receivingTime = getParam('receiving_time', date('H:i:s'));
            $temperatureCelsius = getParam('temperature_celsius');
            $transportContainer = getParam('transport_container');
            $visualInspection = getParam('visual_inspection', 'pending');
            $visualNotes = trim(getParam('visual_notes', ''));
            $notes = trim(getParam('notes', ''));
            
            // Validation
            $errors = [];
            if (empty($farmerId)) $errors['farmer_id'] = 'Farmer is required';
            if (empty($volumeLiters) || $volumeLiters <= 0) {
                $errors['volume_liters'] = 'Valid volume is required';
            }
            if (!empty($visualInspection) && !in_array($visualInspection, ['pass', 'fail', 'pending'])) {
                $errors['visual_inspection'] = 'Visual inspection must be pass, fail, or pending';
            }
            
            // Check if farmer exists and is active
            $farmer = null;
            if ($farmerId) {
                $farmerStmt = $db->prepare("SELECT id, is_active, milk_type_id FROM farmers WHERE id = ?");
                $farmerStmt->execute([$farmerId]);
                $farmer = $farmerStmt->fetch();
                
                if (!$farmer) {
                    $errors['farmer_id'] = 'Farmer not found';
                } elseif (!$farmer['is_active']) {
                    $errors['farmer_id'] = 'Farmer is inactive';
                }
            }
            
            // Use farmer's milk type if not specified
            if (empty($milkTypeId) && $farmer && $farmer['milk_type_id']) {
                $milkTypeId = $farmer['milk_type_id'];
            }
            
            // Default to COW milk (id=1) if still not set
            if (empty($milkTypeId)) {
                $milkTypeId = 1;
            }
            
            if (!empty($errors)) {
                Response::validationError($errors);
            }
            
            // Generate receiving code (RCV-YYYYMMDD-NNN)
            $codePrefix = 'RCV-' . date('Ymd', strtotime($receivingDate)) . '-';
            $codeStmt = $db->prepare("
                SELECT MAX(CAST(SUBSTRING(receiving_code, 14) AS UNSIGNED)) as max_num 
                FROM milk_receiving 
                WHERE receiving_code LIKE ?
            ");
            $codeStmt->execute([$codePrefix . '%']);
            $maxNum = $codeStmt->fetch()['max_num'] ?? 0;
            $receivingCode = $codePrefix . str_pad($maxNum + 1, 3, '0', STR_PAD_LEFT);
            
            // Generate RMR number (sequential)
            $rmrStmt = $db->query("SELECT MAX(CAST(rmr_number AS UNSIGNED)) as max_rmr FROM milk_receiving WHERE rmr_number REGEXP '^[0-9]+$'");
            $maxRmr = $rmrStmt->fetch()['max_rmr'] ?? 66172;
            $rmrNumber = $maxRmr + 1;
            
            // Insert receiving record
            $stmt = $db->prepare("
                INSERT INTO milk_receiving (
                    receiving_code, rmr_number, farmer_id, milk_type_id, 
                    receiving_date, receiving_time, volume_liters,
                    temperature_celsius, transport_container,
                    visual_inspection, visual_notes, status, received_by, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_qc', ?, ?)
            ");
            $stmt->execute([
                $receivingCode, $rmrNumber, $farmerId, $milkTypeId,
                $receivingDate, $receivingTime, $volumeLiters,
                $temperatureCelsius ?: null, $transportContainer ?: null,
                $visualInspection, $visualNotes, $currentUser['user_id'], $notes
            ]);
            
            $receivingId = $db->lastInsertId();
            
            // Log audit
            logAudit($currentUser['user_id'], 'CREATE', 'milk_receiving', $receivingId, null, [
                'receiving_code' => $receivingCode,
                'rmr_number' => $rmrNumber,
                'farmer_id' => $farmerId,
                'volume_liters' => $volumeLiters
            ]);
            
            // Get created record with farmer info
            $stmt = $db->prepare("
                SELECT mr.*, 
                       f.farmer_code, COALESCE(f.first_name, '') as farmer_name,
                       f.membership_type, f.base_price_per_liter,
                       mt.type_code as milk_type_code, mt.type_name as milk_type_name
                FROM milk_receiving mr
                LEFT JOIN farmers f ON mr.farmer_id = f.id
                LEFT JOIN milk_types mt ON mr.milk_type_id = mt.id
                WHERE mr.id = ?
            ");
            $stmt->execute([$receivingId]);
            $record = $stmt->fetch();
            
            Response::success($record, 'Milk receiving recorded successfully', 201);
            break;
            
        default:
            Response::error('Method not allowed', 405);
    }
    
} catch (Exception $e) {
    error_log("Deliveries API error: " . $e->getMessage());
    Response::error('An error occurred: ' . $e->getMessage(), 500);
}
