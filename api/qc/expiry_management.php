<?php
/**
 * Highland Fresh System - Expiry Management API
 * 
 * Endpoints:
 * GET  - List expiring/expired products
 * POST - Initiate yogurt transformation
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
            $filter = getParam('filter', 'all'); // all, warning, critical, expired
            $productId = getParam('product_id');
            $page = (int) getParam('page', 1);
            $limit = (int) getParam('limit', 20);
            $offset = ($page - 1) * $limit;
            
            $where = "WHERE fgi.status = 'available' AND fgi.quantity_available > 0";
            $params = [];
            
            switch ($filter) {
                case 'warning':
                    $where .= " AND fgi.expiry_date BETWEEN DATE_ADD(CURDATE(), INTERVAL 4 DAY) AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
                    break;
                case 'critical':
                    $where .= " AND fgi.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)";
                    break;
                case 'expired':
                    $where .= " AND fgi.expiry_date < CURDATE()";
                    break;
            }
            
            if ($productId) {
                $where .= " AND fgi.product_id = ?";
                $params[] = $productId;
            }
            
            // Get total count
            $countStmt = $db->prepare("
                SELECT COUNT(*) as total 
                FROM finished_goods_inventory fgi
                {$where}
            ");
            $countStmt->execute($params);
            $total = $countStmt->fetch()['total'];
            
            // Get inventory
            $stmt = $db->prepare("
                SELECT fgi.*,
                       pb.batch_code,
                       p.product_code, p.product_name, p.category, p.variant,
                       DATEDIFF(fgi.expiry_date, CURDATE()) as days_until_expiry,
                       CASE 
                           WHEN fgi.expiry_date < CURDATE() THEN 'expired'
                           WHEN DATEDIFF(fgi.expiry_date, CURDATE()) <= 3 THEN 'critical'
                           WHEN DATEDIFF(fgi.expiry_date, CURDATE()) <= 7 THEN 'warning'
                           ELSE 'ok'
                       END as alert_status
                FROM finished_goods_inventory fgi
                LEFT JOIN production_batches pb ON fgi.batch_id = pb.id
                LEFT JOIN products p ON fgi.product_id = p.id
                {$where}
                ORDER BY fgi.expiry_date ASC
                LIMIT ? OFFSET ?
            ");
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            $inventory = $stmt->fetchAll();
            
            Response::paginated($inventory, $total, $page, $limit, 'Expiry data retrieved successfully');
            break;
            
        case 'POST':
            // Initiate yogurt transformation
            $inventoryId = getParam('inventory_id');
            $quantity = getParam('quantity');
            $notes = trim(getParam('notes', ''));
            
            // Validation
            $errors = [];
            if (!$inventoryId) $errors['inventory_id'] = 'Inventory ID is required';
            if (!$quantity || $quantity <= 0) $errors['quantity'] = 'Valid quantity is required';
            
            // Get inventory item
            if ($inventoryId) {
                $invStmt = $db->prepare("
                    SELECT fgi.*, p.category, p.size_value, p.size_unit
                    FROM finished_goods_inventory fgi
                    LEFT JOIN products p ON fgi.product_id = p.id
                    WHERE fgi.id = ? AND fgi.status = 'available'
                ");
                $invStmt->execute([$inventoryId]);
                $inventory = $invStmt->fetch();
                
                if (!$inventory) {
                    $errors['inventory_id'] = 'Inventory not found or not available';
                } elseif ($inventory['category'] !== 'milk') {
                    $errors['inventory_id'] = 'Only milk products can be transformed to yogurt';
                } elseif ($quantity > $inventory['quantity_available']) {
                    $errors['quantity'] = 'Quantity exceeds available stock';
                }
            }
            
            if (!empty($errors)) {
                Response::validationError($errors);
            }
            
            // Calculate volume in liters
            $volumeLiters = $quantity * $inventory['size_value'];
            if ($inventory['size_unit'] === 'ml') {
                $volumeLiters = $volumeLiters / 1000;
            }
            
            // Generate transformation code
            $codeStmt = $db->query("SELECT MAX(CAST(SUBSTRING(transformation_code, 5) AS UNSIGNED)) as max_num FROM yogurt_transformations WHERE transformation_code LIKE 'YTF-%'");
            $maxNum = $codeStmt->fetch()['max_num'] ?? 0;
            $transformCode = 'YTF-' . str_pad($maxNum + 1, 6, '0', STR_PAD_LEFT);
            
            // Begin transaction
            $db->beginTransaction();
            
            try {
                // Create transformation record
                $stmt = $db->prepare("
                    INSERT INTO yogurt_transformations (
                        transformation_code, source_inventory_id, source_quantity,
                        source_volume_liters, approved_by, approval_datetime,
                        safety_verified, status, notes
                    ) VALUES (?, ?, ?, ?, ?, NOW(), 1, 'approved', ?)
                ");
                $stmt->execute([
                    $transformCode, $inventoryId, $quantity,
                    $volumeLiters, $currentUser['user_id'], $notes
                ]);
                
                $transformId = $db->lastInsertId();
                
                // Update inventory quantity
                $updateStmt = $db->prepare("
                    UPDATE finished_goods_inventory 
                    SET quantity_available = quantity_available - ?,
                        status = CASE WHEN quantity_available - ? <= 0 THEN 'transformed' ELSE status END
                    WHERE id = ?
                ");
                $updateStmt->execute([$quantity, $quantity, $inventoryId]);
                
                $db->commit();
                
                // Log audit
                logAudit($currentUser['user_id'], 'CREATE', 'yogurt_transformations', $transformId, null, [
                    'transformation_code' => $transformCode,
                    'source_inventory_id' => $inventoryId,
                    'quantity' => $quantity
                ]);
                
                Response::success([
                    'transformation_id' => $transformId,
                    'transformation_code' => $transformCode,
                    'source_quantity' => $quantity,
                    'volume_liters' => $volumeLiters
                ], 'Yogurt transformation initiated', 201);
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        default:
            Response::error('Method not allowed', 405);
    }
    
} catch (Exception $e) {
    error_log("Expiry Management API error: " . $e->getMessage());
    Response::error('An error occurred', 500);
}
