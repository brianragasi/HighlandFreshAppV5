<?php
/**
 * Highland Fresh System - Delivery Returns API
 * 
 * Handles returns processing for delivery receipts
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(dirname(__DIR__)) . '/bootstrap.php';

// Only Warehouse FG and GM can process returns
$currentUser = Auth::requireRole(['warehouse_fg', 'general_manager']);

$action = getParam('action', 'list');

try {
    $db = Database::getInstance()->getConnection();
    
    switch ($requestMethod) {
        case 'GET':
            handleGet($db, $action);
            break;
        case 'POST':
            handlePost($db, $action, $currentUser);
            break;
        case 'PUT':
            handlePut($db, $action, $currentUser);
            break;
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Delivery Returns API Error: " . $e->getMessage());
    Response::error('Server error: ' . $e->getMessage(), 500);
}

function handleGet($db, $action) {
    switch ($action) {
        case 'list':
            // Get all returns for a DR
            $drId = getParam('dr_id');
            if (!$drId) {
                Response::error('DR ID required', 400);
            }
            
            $stmt = $db->prepare("
                SELECT 
                    dr.*,
                    p.product_name,
                    pb.batch_code,
                    u.first_name as created_by_name
                FROM delivery_returns dr
                LEFT JOIN products p ON dr.product_id = p.id
                LEFT JOIN production_batches pb ON dr.batch_id = pb.id
                LEFT JOIN users u ON dr.created_by = u.id
                WHERE dr.delivery_receipt_id = ?
                ORDER BY dr.created_at DESC
            ");
            $stmt->execute([$drId]);
            $returns = $stmt->fetchAll();
            
            Response::success($returns, 'Returns retrieved');
            break;
            
        case 'summary':
            // Get return summary for a DR
            $drId = getParam('dr_id');
            if (!$drId) {
                Response::error('DR ID required', 400);
            }
            
            $stmt = $db->prepare("
                SELECT 
                    COUNT(*) as total_returns,
                    SUM(quantity_returned) as total_quantity_returned,
                    GROUP_CONCAT(DISTINCT return_reason) as reasons
                FROM delivery_returns
                WHERE delivery_receipt_id = ?
            ");
            $stmt->execute([$drId]);
            $summary = $stmt->fetch();
            
            Response::success($summary, 'Return summary retrieved');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

function handlePost($db, $action, $currentUser) {
    $data = getRequestBody();
    
    switch ($action) {
        case 'record_returns':
            // Record returns for a DR
            $drId = $data['dr_id'] ?? null;
            $returns = $data['returns'] ?? [];
            
            if (!$drId || empty($returns)) {
                Response::error('DR ID and returns data required', 400);
            }
            
            // Verify DR is dispatched
            $drCheck = $db->prepare("SELECT status FROM delivery_receipts WHERE id = ?");
            $drCheck->execute([$drId]);
            $dr = $drCheck->fetch();
            
            if (!$dr) {
                Response::error('Delivery receipt not found', 404);
            }
            
            if ($dr['status'] !== 'dispatched') {
                Response::error('Can only record returns for dispatched deliveries', 400);
            }
            
            $db->beginTransaction();
            
            try {
                foreach ($returns as $return) {
                    $stmt = $db->prepare("
                        INSERT INTO delivery_returns
                        (delivery_receipt_id, dr_item_id, product_id, batch_id, quantity_returned, 
                         return_reason, `condition`, disposition, notes, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $stmt->execute([
                        $drId,
                        $return['dr_item_id'],
                        $return['product_id'],
                        $return['batch_id'] ?? null,
                        $return['quantity_returned'],
                        $return['return_reason'],
                        $return['condition'] ?? 'resellable',
                        $return['disposition'] ?? 'pending',
                        $return['notes'] ?? null,
                        $currentUser['user_id']
                    ]);
                    
                    $returnId = $db->lastInsertId();
                    
                    // Update DR item delivered quantity (subtract returns)
                    $updateItem = $db->prepare("
                        UPDATE delivery_receipt_items
                        SET quantity_delivered = quantity_packed - ?
                        WHERE id = ?
                    ");
                    $updateItem->execute([
                        $return['quantity_returned'],
                        $return['dr_item_id']
                    ]);
                    
                    // If resellable, add back to inventory
                    if ($return['disposition'] === 'return_to_inventory') {
                        // Find the original inventory record
                        $invStmt = $db->prepare("
                            SELECT id FROM finished_goods_inventory
                            WHERE product_id = ? AND batch_id = ?
                            LIMIT 1
                        ");
                        $invStmt->execute([$return['product_id'], $return['batch_id']]);
                        $inv = $invStmt->fetch();
                        
                        if ($inv) {
                            $returnQty = $return['quantity_returned'];
                            $updateInv = $db->prepare("
                                UPDATE finished_goods_inventory
                                SET quantity = quantity + ?,
                                    quantity_available = quantity_available + ?,
                                    pieces_available = pieces_available + ?,
                                    last_movement_at = NOW()
                                WHERE id = ?
                            ");
                            $updateInv->execute([$returnQty, $returnQty, $returnQty, $inv['id']]);
                        }
                    }
                    // If dispose, create a disposal request for GM approval
                    elseif ($return['disposition'] === 'dispose') {
                        // Get product details
                        $prodStmt = $db->prepare("SELECT product_name, selling_price FROM products WHERE id = ?");
                        $prodStmt->execute([$return['product_id']]);
                        $product = $prodStmt->fetch();
                        
                        // Get batch reference
                        $batchRef = null;
                        if (!empty($return['batch_id'])) {
                            $batchStmt = $db->prepare("SELECT batch_code FROM production_batches WHERE id = ?");
                            $batchStmt->execute([$return['batch_id']]);
                            $batch = $batchStmt->fetch();
                            $batchRef = $batch ? $batch['batch_code'] : null;
                        }
                        
                        // Generate disposal code
                        $disposalCode = generateDisposalCode($db);
                        
                        // Map condition to disposal category
                        $categoryMap = [
                            'damaged' => 'damaged',
                            'expired' => 'expired',
                            'spoiled' => 'spoiled',
                            'contaminated' => 'contaminated'
                        ];
                        $category = $categoryMap[$return['condition']] ?? 'other';
                        
                        // Default disposal method based on category
                        $methodMap = [
                            'damaged' => 'special_waste',
                            'expired' => 'animal_feed',
                            'spoiled' => 'animal_feed',
                            'contaminated' => 'incinerate'
                        ];
                        $method = $methodMap[$return['condition']] ?? 'other';
                        
                        $unitCost = $product['selling_price'] ?? 0;
                        $totalValue = $return['quantity_returned'] * $unitCost;
                        
                        $dispStmt = $db->prepare("
                            INSERT INTO disposals (
                                disposal_code, source_type, source_id, source_reference,
                                product_id, product_name, quantity, unit,
                                unit_cost, total_value, disposal_category, disposal_reason,
                                disposal_method, status, initiated_by, initiated_at, notes
                            ) VALUES (?, 'finished_goods', ?, ?, ?, ?, ?, 'pcs', ?, ?, ?, ?, ?, 'pending', ?, NOW(), ?)
                        ");
                        
                        $dispStmt->execute([
                            $disposalCode,
                            $returnId,  // source_id links to the delivery_returns record
                            $batchRef,
                            $return['product_id'],
                            $product['product_name'] ?? 'Unknown Product',
                            $return['quantity_returned'],
                            $unitCost,
                            $totalValue,
                            $category,
                            'Delivery return: ' . ($return['return_reason'] ?? 'Unknown reason') . ' - ' . ($return['notes'] ?? 'No notes'),
                            $method,
                            $currentUser['user_id'],
                            'Auto-created from delivery return. DR ID: ' . $drId
                        ]);
                    }
                }
                
                // Mark DR as returns processed
                $markProcessed = $db->prepare("
                    UPDATE delivery_receipts
                    SET returns_processed = 1,
                        returns_processed_at = NOW(),
                        returns_processed_by = ?
                    WHERE id = ?
                ");
                $markProcessed->execute([$currentUser['user_id'], $drId]);
                
                $db->commit();
                
                Response::success(null, 'Returns recorded successfully', 201);
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'no_returns':
            // Mark that there are no returns (all delivered successfully)
            $drId = $data['dr_id'] ?? null;
            
            if (!$drId) {
                Response::error('DR ID required', 400);
            }
            
            $stmt = $db->prepare("
                UPDATE delivery_receipts
                SET returns_processed = 1,
                    returns_processed_at = NOW(),
                    returns_processed_by = ?
                WHERE id = ? AND status = 'dispatched'
            ");
            $stmt->execute([$currentUser['user_id'], $drId]);
            
            if ($stmt->rowCount() === 0) {
                Response::error('DR not found or not dispatched', 404);
            }
            
            Response::success(null, 'Marked as no returns');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

function handlePut($db, $action, $currentUser) {
    $data = getRequestBody();
    
    switch ($action) {
        case 'update_disposition':
            // Update disposition of a return
            $id = $data['id'] ?? null;
            $disposition = $data['disposition'] ?? null;
            
            if (!$id || !$disposition) {
                Response::error('Return ID and disposition required', 400);
            }
            
            $stmt = $db->prepare("
                UPDATE delivery_returns
                SET disposition = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$disposition, $id]);
            
            Response::success(null, 'Disposition updated');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

/**
 * Generate unique disposal code
 */
function generateDisposalCode($db) {
    $date = date('Ymd');
    
    // Get today's count
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM disposals 
        WHERE DATE(created_at) = CURDATE()
    ");
    $stmt->execute();
    $count = $stmt->fetchColumn() + 1;
    
    return 'DSP-' . $date . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
}
