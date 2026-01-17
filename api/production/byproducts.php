<?php
/**
 * Highland Fresh System - Production Byproducts API
 * 
 * Track byproducts generated during production:
 * - Buttermilk from butter production
 * - Whey from cheese production
 * 
 * GET  - List byproducts / Get single byproduct
 * POST - Record new byproduct
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Require Production role
$currentUser = Auth::requireRole(['production_staff', 'general_manager', 'qc_officer']);

try {
    $db = Database::getInstance()->getConnection();
    
    switch ($requestMethod) {
        case 'GET':
            $byproductId = getParam('id');
            $runId = getParam('run_id');
            
            if ($byproductId) {
                // Get single byproduct
                $stmt = $db->prepare("
                    SELECT pb.*, 
                           pr.run_code,
                           mr.product_name as source_product,
                           u.first_name, u.last_name
                    FROM production_byproducts pb
                    JOIN production_runs pr ON pb.run_id = pr.id
                    JOIN master_recipes mr ON pr.recipe_id = mr.id
                    LEFT JOIN users u ON pb.recorded_by = u.id
                    WHERE pb.id = ?
                ");
                $stmt->execute([$byproductId]);
                $byproduct = $stmt->fetch();
                
                if (!$byproduct) {
                    Response::notFound('Byproduct record not found');
                }
                
                Response::success($byproduct, 'Byproduct record retrieved successfully');
            }
            
            // List byproducts
            $byproductType = getParam('byproduct_type');
            $status = getParam('status');
            $dateFrom = getParam('date_from');
            $dateTo = getParam('date_to');
            $page = (int) getParam('page', 1);
            $limit = (int) getParam('limit', 20);
            $offset = ($page - 1) * $limit;
            
            $where = "WHERE 1=1";
            $params = [];
            
            if ($runId) {
                $where .= " AND pb.run_id = ?";
                $params[] = $runId;
            }
            
            if ($byproductType) {
                $where .= " AND pb.byproduct_type = ?";
                $params[] = $byproductType;
            }
            
            if ($status) {
                $where .= " AND pb.status = ?";
                $params[] = $status;
            }
            
            if ($dateFrom) {
                $where .= " AND DATE(pb.created_at) >= ?";
                $params[] = $dateFrom;
            }
            
            if ($dateTo) {
                $where .= " AND DATE(pb.created_at) <= ?";
                $params[] = $dateTo;
            }
            
            // Get total count
            $countStmt = $db->prepare("SELECT COUNT(*) as total FROM production_byproducts pb {$where}");
            $countStmt->execute($params);
            $total = $countStmt->fetch()['total'];
            
            // Get byproducts
            $stmt = $db->prepare("
                SELECT pb.id, pb.run_id, pb.byproduct_type, pb.quantity, pb.unit,
                       pb.status, pb.destination, pb.notes, pb.created_at,
                       pr.run_code,
                       mr.product_name as source_product,
                       u.first_name, u.last_name
                FROM production_byproducts pb
                JOIN production_runs pr ON pb.run_id = pr.id
                JOIN master_recipes mr ON pr.recipe_id = mr.id
                LEFT JOIN users u ON pb.recorded_by = u.id
                {$where}
                ORDER BY pb.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            $byproducts = $stmt->fetchAll();
            
            Response::paginated($byproducts, $total, $page, $limit, 'Byproducts retrieved successfully');
            break;
            
        case 'POST':
            // Record new byproduct
            $runId = getParam('run_id');
            $byproductType = getParam('byproduct_type');
            $quantity = getParam('quantity');
            $unit = getParam('unit', 'liters');
            $destination = getParam('destination');
            $notes = trim(getParam('notes', ''));
            
            // Validation
            $errors = [];
            if (!$runId) $errors['run_id'] = 'Run ID is required';
            if (!$byproductType) $errors['byproduct_type'] = 'Byproduct type is required';
            if (!is_numeric($quantity) || $quantity <= 0) $errors['quantity'] = 'Valid quantity is required';
            
            $validTypes = ['buttermilk', 'whey', 'cream', 'skim_milk', 'other'];
            if (!in_array($byproductType, $validTypes)) {
                $errors['byproduct_type'] = 'Invalid byproduct type. Valid types: ' . implode(', ', $validTypes);
            }
            
            $validDestinations = ['warehouse', 'reprocess', 'dispose', 'sale'];
            if ($destination && !in_array($destination, $validDestinations)) {
                $errors['destination'] = 'Invalid destination. Valid: ' . implode(', ', $validDestinations);
            }
            
            if (!empty($errors)) {
                Response::validationError($errors);
            }
            
            // Verify run exists
            $runStmt = $db->prepare("SELECT id FROM production_runs WHERE id = ?");
            $runStmt->execute([$runId]);
            if (!$runStmt->fetch()) {
                Response::notFound('Production run not found');
            }
            
            // Insert byproduct record
            $stmt = $db->prepare("
                INSERT INTO production_byproducts (
                    run_id, byproduct_type, quantity, unit, 
                    destination, status, recorded_by, notes
                ) VALUES (?, ?, ?, ?, ?, 'pending', ?, ?)
            ");
            
            $stmt->execute([
                $runId, $byproductType, $quantity, $unit,
                $destination, $currentUser['user_id'], $notes
            ]);
            
            $byproductId = $db->lastInsertId();
            
            Response::created([
                'id' => $byproductId,
                'byproduct_type' => $byproductType,
                'quantity' => $quantity,
                'unit' => $unit,
                'status' => 'pending'
            ], 'Byproduct recorded successfully');
            break;
            
        case 'PUT':
            $byproductId = getParam('id');
            
            if (!$byproductId) {
                Response::validationError(['id' => 'Byproduct ID is required']);
            }
            
            // Get current byproduct
            $stmt = $db->prepare("SELECT * FROM production_byproducts WHERE id = ?");
            $stmt->execute([$byproductId]);
            $byproduct = $stmt->fetch();
            
            if (!$byproduct) {
                Response::notFound('Byproduct record not found');
            }
            
            $action = getParam('action', 'update');
            
            switch ($action) {
                case 'transfer_to_warehouse':
                    $stmt = $db->prepare("
                        UPDATE production_byproducts 
                        SET status = 'transferred', destination = 'warehouse'
                        WHERE id = ?
                    ");
                    $stmt->execute([$byproductId]);
                    Response::success(['status' => 'transferred'], 'Byproduct transferred to warehouse');
                    break;
                    
                case 'dispose':
                    $stmt = $db->prepare("
                        UPDATE production_byproducts 
                        SET status = 'disposed', destination = 'dispose'
                        WHERE id = ?
                    ");
                    $stmt->execute([$byproductId]);
                    Response::success(['status' => 'disposed'], 'Byproduct marked as disposed');
                    break;
                    
                default:
                    // General update
                    $destination = getParam('destination');
                    $notes = getParam('notes');
                    
                    $updates = [];
                    $params = [];
                    
                    if ($destination) {
                        $updates[] = "destination = ?";
                        $params[] = $destination;
                    }
                    
                    if ($notes !== null) {
                        $updates[] = "notes = ?";
                        $params[] = $notes;
                    }
                    
                    if (!empty($updates)) {
                        $params[] = $byproductId;
                        $stmt = $db->prepare("UPDATE production_byproducts SET " . implode(', ', $updates) . " WHERE id = ?");
                        $stmt->execute($params);
                    }
                    
                    Response::success(null, 'Byproduct updated');
            }
            break;
            
        default:
            Response::error('Method not allowed', 405);
    }
    
} catch (Exception $e) {
    error_log("Byproducts API error: " . $e->getMessage());
    Response::error('An error occurred: ' . $e->getMessage(), 500);
}
