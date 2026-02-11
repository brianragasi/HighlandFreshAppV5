<?php
/**
 * Highland Fresh System - Warehouse FG Delivery Receipts API
 * 
 * GET - List DRs, get details, pending DRs
 * POST - Create DR
 * PUT - Update DR, release DR
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(dirname(__DIR__)) . '/bootstrap.php';

// Different roles for different operations:
// - GET: Sales can view DRs (to track delivery status)
// - POST: Only Warehouse FG can create DRs
// - PUT deliver: Only Warehouse FG can mark as delivered (after driver returns)
$currentUser = Auth::requireRole(['warehouse_fg', 'general_manager', 'sales_custodian']);

$action = getParam('action', 'list');

try {
    $db = Database::getInstance()->getConnection();
    
    switch ($requestMethod) {
        case 'GET':
            // All allowed roles can view
            handleGet($db, $action);
            break;
        case 'POST':
            // Only Warehouse FG and GM can create DRs
            if (!in_array($currentUser['role'], ['warehouse_fg', 'general_manager'])) {
                Response::error('Only Warehouse FG can create Delivery Receipts', 403);
            }
            handlePost($db, $action, $currentUser);
            break;
        case 'PUT':
            handlePut($db, $action, $currentUser);
            break;
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Delivery Receipts API Error: " . $e->getMessage());
    Response::error('Server error: ' . $e->getMessage(), 500);
}

function handleGet($db, $action) {
    switch ($action) {
        case 'list':
            $status = getParam('status');
            $customerId = getParam('customer_id');
            $fromDate = getParam('from_date');
            $toDate = getParam('to_date');
            
            $sql = "
                SELECT 
                    dr.*,
                    c.customer_type,
                    c.name as customer_name_ref,
                    u.first_name as prepared_by_name,
                    u.last_name as prepared_by_lastname,
                    d.first_name as dispatched_by_name,
                    (SELECT COUNT(*) FROM delivery_receipt_items WHERE delivery_receipt_id = dr.id) as item_count,
                    (SELECT COALESCE(SUM(quantity_ordered), 0) FROM delivery_receipt_items WHERE delivery_receipt_id = dr.id) as total_quantity
                FROM delivery_receipts dr
                LEFT JOIN customers c ON dr.customer_id = c.id
                LEFT JOIN users u ON dr.created_by = u.id
                LEFT JOIN users d ON dr.dispatched_by = d.id
                WHERE 1=1
            ";
            
            $params = [];
            
            if ($status) {
                $sql .= " AND dr.status = ?";
                $params[] = $status;
            }
            
            if ($customerId) {
                $sql .= " AND dr.customer_id = ?";
                $params[] = $customerId;
            }
            
            if ($fromDate) {
                $sql .= " AND DATE(dr.created_at) >= ?";
                $params[] = $fromDate;
            }
            
            if ($toDate) {
                $sql .= " AND DATE(dr.created_at) <= ?";
                $params[] = $toDate;
            }
            
            $sql .= " ORDER BY dr.created_at DESC LIMIT 100";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $drs = $stmt->fetchAll();
            
            Response::success($drs, 'Delivery receipts retrieved');
            break;
            
        case 'detail':
            $id = getParam('id');
            if (!$id) {
                Response::error('DR ID required', 400);
            }
            
            $stmt = $db->prepare("
                SELECT dr.*,
                    u.first_name as prepared_by_name,
                    d.first_name as dispatched_by_name
                FROM delivery_receipts dr
                LEFT JOIN users u ON dr.created_by = u.id
                LEFT JOIN users d ON dr.dispatched_by = d.id
                WHERE dr.id = ?
            ");
            $stmt->execute([$id]);
            $dr = $stmt->fetch();
            
            if (!$dr) {
                Response::error('Delivery receipt not found', 404);
            }
            
            // Get line items with batch info
            $itemsStmt = $db->prepare("
                SELECT dri.*,
                       dri.quantity_ordered as quantity,
                       p.product_name,
                       p.product_code as product_sku,
                       pb.batch_code,
                       pb.barcode as batch_barcode
                FROM delivery_receipt_items dri
                LEFT JOIN products p ON dri.product_id = p.id
                LEFT JOIN production_batches pb ON dri.batch_id = pb.id
                WHERE dri.delivery_receipt_id = ?
            ");
            $itemsStmt->execute([$id]);
            $dr['items'] = $itemsStmt->fetchAll();
            
            Response::success($dr, 'Delivery receipt details retrieved');
            break;
            
        case 'pending':
            $stmt = $db->prepare("
                SELECT 
                    dr.*,
                    u.first_name as prepared_by_name,
                    (SELECT COUNT(*) FROM delivery_receipt_items dri WHERE dri.delivery_receipt_id = dr.id AND dri.quantity_picked > 0) as items_picked,
                    (SELECT COUNT(*) FROM delivery_receipt_items dri WHERE dri.delivery_receipt_id = dr.id) as total_items_count
                FROM delivery_receipts dr
                LEFT JOIN users u ON dr.created_by = u.id
                WHERE dr.status IN ('pending', 'picking', 'preparing', 'draft')
                ORDER BY dr.picking_started_at ASC, dr.created_at ASC
            ");
            $stmt->execute();
            $drs = $stmt->fetchAll();
            
            Response::success($drs, 'Pending DRs retrieved');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

function handlePost($db, $action, $currentUser) {
    $data = getRequestBody();
    
    if ($action === 'create_from_order') {
        // Create DR from an approved sales order
        $orderId = $data['order_id'] ?? null;
        if (!$orderId) {
            Response::error('Order ID required', 400);
        }
        
        // Get the order
        $orderStmt = $db->prepare("
            SELECT o.*, c.name as customer_name, c.customer_type, c.address, c.contact_number
            FROM sales_orders o
            LEFT JOIN customers c ON o.customer_id = c.id
            WHERE o.id = ?
        ");
        $orderStmt->execute([$orderId]);
        $order = $orderStmt->fetch();
        
        if (!$order) {
            Response::error('Order not found', 404);
        }
        
        if ($order['status'] !== 'approved') {
            Response::error('Only approved orders can be converted to delivery receipts', 400);
        }
        
        // Check if DR already exists for this order
        $existCheck = $db->prepare("SELECT id, dr_number FROM delivery_receipts WHERE order_id = ?");
        $existCheck->execute([$orderId]);
        $existing = $existCheck->fetch();
        if ($existing) {
            Response::error("DR already exists for this order: {$existing['dr_number']}", 400);
        }
        
        // Get order items
        $itemsStmt = $db->prepare("
            SELECT oi.*, p.product_name, COALESCE(p.pieces_per_box, 12) as pieces_per_box
            FROM sales_order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $itemsStmt->execute([$orderId]);
        $orderItems = $itemsStmt->fetchAll();
        
        // =====================================================
        // STOCK VALIDATION: Check if FG inventory has enough stock
        // =====================================================
        $stockWarnings = [];
        foreach ($orderItems as $item) {
            // Get available stock for this product
            $stockStmt = $db->prepare("
                SELECT 
                    COALESCE(SUM((fgi.boxes_available * COALESCE(p.pieces_per_box, 12)) + fgi.pieces_available), 0) as total_available
                FROM finished_goods_inventory fgi
                LEFT JOIN products p ON fgi.product_id = p.id
                LEFT JOIN production_batches pb ON fgi.batch_id = pb.id
                WHERE fgi.product_id = ?
                  AND pb.qc_status = 'released'
                  AND (pb.expiry_date IS NULL OR pb.expiry_date >= CURDATE())
            ");
            $stockStmt->execute([$item['product_id']]);
            $stock = $stockStmt->fetch();
            
            $available = (int)($stock['total_available'] ?? 0);
            $ordered = (int)$item['quantity_ordered'];
            
            if ($available < $ordered) {
                $stockWarnings[] = "{$item['product_name']}: need {$ordered}, only {$available} in FG";
            }
        }
        
        // If insufficient stock, return error
        if (!empty($stockWarnings)) {
            Response::error(
                "⚠️ INSUFFICIENT FG INVENTORY:\n" . 
                implode("\n", $stockWarnings) . 
                "\n\nPlease ensure products are produced, QC-released, and received in FG warehouse before creating DR.",
                400
            );
        }
        
        $db->beginTransaction();
        
        try {
            // Create PICKING TICKET (not DR yet!)
            // DR number will be assigned when picking is complete (finalize_picking action)
            $pickingNumber = 'PICK-' . $orderId . '-' . date('His');
            
            // Status = 'picking' - items must be scanned/picked before DR is generated
            $stmt = $db->prepare("
                INSERT INTO delivery_receipts 
                (dr_number, order_id, customer_id, customer_name, delivery_address, 
                 contact_number, total_items, total_amount, status, picking_started_at, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'picking', NOW(), ?)
            ");
            
            $stmt->execute([
                $pickingNumber,
                $orderId,
                $order['customer_id'],
                $order['customer_name'],
                $order['delivery_address'] ?? $order['address'],
                $order['contact_number'],
                count($orderItems),
                $order['total_amount'],
                $currentUser['user_id']
            ]);
            
            $drId = $db->lastInsertId();
            
            // Create DR items from order items
            $itemStmt = $db->prepare("
                INSERT INTO delivery_receipt_items 
                (delivery_receipt_id, product_id, quantity_ordered, unit_price, total_price)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($orderItems as $item) {
                $itemStmt->execute([
                    $drId,
                    $item['product_id'],
                    $item['quantity_ordered'],
                    $item['unit_price'],
                    $item['line_total']
                ]);
            }
            
            // Update order status to picking
            $updateOrder = $db->prepare("UPDATE sales_orders SET status = 'picking' WHERE id = ?");
            $updateOrder->execute([$orderId]);
            
            $db->commit();
            
            // Redirect to dispatch page with picking ticket
            Response::success([
                'id' => $drId, 
                'picking_number' => $pickingNumber,
                'redirect_url' => '/html/warehouse/fg/dispatch.html?pick=' . $drId
            ], 'Picking ticket created. Proceed to scan items.', 201);
            
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
    
    if ($action === 'create') {
        $required = ['customer_type', 'customer_name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                Response::error("$field is required", 400);
            }
        }
        
        $db->beginTransaction();
        
        try {
            // Generate DR number
            $drNumber = 'DR-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
            
            $stmt = $db->prepare("
                INSERT INTO delivery_receipts 
                (dr_number, customer_type, customer_name, sub_location, contact_number, 
                 delivery_address, status, created_by, notes)
                VALUES (?, ?, ?, ?, ?, ?, 'draft', ?, ?)
            ");
            
            $stmt->execute([
                $drNumber,
                $data['customer_type'],
                $data['customer_name'],
                $data['sub_location'] ?? null,
                $data['contact_number'] ?? null,
                $data['delivery_address'] ?? null,
                $currentUser['user_id'],
                $data['notes'] ?? null
            ]);
            
            $drId = $db->lastInsertId();
            
            $db->commit();
            
            Response::success(['id' => $drId, 'dr_number' => $drNumber], 'Delivery receipt created', 201);
            
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
    
    Response::error('Invalid action', 400);
}

function handlePut($db, $action, $currentUser) {
    $data = getRequestBody();
    $id = getParam('id') ?? ($data['id'] ?? null);
    
    if (!$id) {
        Response::error('DR ID required', 400);
    }
    
    // Get current DR
    $check = $db->prepare("SELECT * FROM delivery_receipts WHERE id = ?");
    $check->execute([$id]);
    $current = $check->fetch();
    
    if (!$current) {
        Response::error('Delivery receipt not found', 404);
    }
    
    switch ($action) {
        case 'update':
            if (!in_array($current['status'], ['draft', 'pending'])) {
                Response::error('Cannot update DR in current status', 400);
            }
            
            $stmt = $db->prepare("
                UPDATE delivery_receipts SET
                    customer_name = COALESCE(?, customer_name),
                    sub_location = COALESCE(?, sub_location),
                    contact_number = COALESCE(?, contact_number),
                    delivery_address = COALESCE(?, delivery_address),
                    notes = COALESCE(?, notes)
                WHERE id = ?
            ");
            
            $stmt->execute([
                $data['customer_name'] ?? null,
                $data['sub_location'] ?? null,
                $data['contact_number'] ?? null,
                $data['delivery_address'] ?? null,
                $data['notes'] ?? null,
                $id
            ]);
            
            Response::success(null, 'Delivery receipt updated');
            break;
            
        case 'finalize_picking':
            // GENERATE DR NUMBER - This is called AFTER all items have been scanned/picked
            // Only Warehouse FG can finalize picking
            if (!in_array($currentUser['role'], ['warehouse_fg', 'general_manager'])) {
                Response::error('Only Warehouse FG can finalize picking', 403);
            }
            
            // Must be in 'picking' status
            if ($current['status'] !== 'picking') {
                Response::error('This picking ticket is not in picking status', 400);
            }
            
            // Process scanned items from frontend
            $scannedItems = $data['items'] ?? [];
            if (empty($scannedItems)) {
                Response::error('No items have been scanned. Please scan items first.', 400);
            }
            
            $db->beginTransaction();
            try {
                // Update delivery_receipt_items with picked quantities
                foreach ($scannedItems as $scanned) {
                    // Get the product_id from the inventory item
                    $invStmt = $db->prepare("SELECT product_id FROM finished_goods_inventory WHERE id = ?");
                    $invStmt->execute([$scanned['inventory_id']]);
                    $inv = $invStmt->fetch();
                    
                    if ($inv) {
                        // Update the delivery_receipt_item for this product
                        $updateItem = $db->prepare("
                            UPDATE delivery_receipt_items 
                            SET quantity_picked = COALESCE(quantity_picked, 0) + ?,
                                inventory_id = ?,
                                picked_at = NOW()
                            WHERE delivery_receipt_id = ? AND product_id = ?
                        ");
                        $updateItem->execute([
                            $scanned['total_pieces'] ?? $scanned['quantity'] ?? 0,
                            $scanned['inventory_id'],
                            $id,
                            $inv['product_id']
                        ]);
                    }
                }
                
                // Re-check pick status after updating
                $pickedCheck = $db->prepare("
                    SELECT COUNT(*) as item_count, 
                           COALESCE(SUM(quantity_picked), 0) as total_picked,
                           COALESCE(SUM(quantity_ordered), 0) as total_ordered
                    FROM delivery_receipt_items 
                    WHERE delivery_receipt_id = ?
                ");
                $pickedCheck->execute([$id]);
                $pickStatus = $pickedCheck->fetch();
            
                if ($pickStatus['total_picked'] <= 0) {
                    $db->rollBack();
                    Response::error('Failed to record picked items.', 400);
                }
            
                // Check if all items are fully picked
                $allPicked = $pickStatus['total_picked'] >= $pickStatus['total_ordered'];
            
                // Generate SEQUENTIAL DR number (DR-YYYYMMDD-XXX)
                $datePrefix = 'DR-' . date('Ymd') . '-';
                $seqStmt = $db->prepare("
                    SELECT MAX(CAST(SUBSTRING(dr_number, 13) AS UNSIGNED)) as max_seq 
                    FROM delivery_receipts 
                    WHERE dr_number LIKE ?
                ");
                $seqStmt->execute([$datePrefix . '%']);
                $maxSeq = $seqStmt->fetch()['max_seq'] ?? 0;
                $drNumber = $datePrefix . str_pad($maxSeq + 1, 3, '0', STR_PAD_LEFT);
                
                // Update the record with final DR number
                $updateDR = $db->prepare("
                    UPDATE delivery_receipts SET
                        dr_number = ?,
                        status = 'ready',
                        prepared_by = ?,
                        prepared_at = NOW()
                    WHERE id = ?
                ");
                $updateDR->execute([$drNumber, $currentUser['user_id'], $id]);
                
                // DEDUCT INVENTORY - This is where stock is officially released
                $itemsStmt = $db->prepare("
                    SELECT dri.*, p.product_name 
                    FROM delivery_receipt_items dri
                    LEFT JOIN products p ON dri.product_id = p.id
                    WHERE dri.delivery_receipt_id = ?
                ");
                $itemsStmt->execute([$id]);
                $items = $itemsStmt->fetchAll();
                
                foreach ($items as $item) {
                    if ($item['quantity_picked'] > 0) {
                        // Deduct from FG inventory using FIFO (oldest batches first)
                        $remaining = $item['quantity_picked'];
                        
                        $batchesStmt = $db->prepare("
                            SELECT fgi.id, fgi.batch_id, fgi.pieces_available, 
                                   COALESCE(p.pieces_per_box, 12) as pieces_per_box
                            FROM finished_goods_inventory fgi
                            LEFT JOIN production_batches pb ON fgi.batch_id = pb.id
                            LEFT JOIN products p ON fgi.product_id = p.id
                            WHERE fgi.product_id = ?
                              AND (fgi.pieces_available > 0 OR fgi.boxes_available > 0)
                              AND pb.qc_status = 'released'
                            ORDER BY pb.manufacturing_date ASC, pb.created_at ASC
                        ");
                        $batchesStmt->execute([$item['product_id']]);
                        $batches = $batchesStmt->fetchAll();
                        
                        foreach ($batches as $batch) {
                            if ($remaining <= 0) break;
                            
                            $totalAvailable = $batch['pieces_available'];
                            $toDeduct = min($remaining, $totalAvailable);
                            
                            $deductStmt = $db->prepare("
                                UPDATE finished_goods_inventory 
                                SET pieces_available = pieces_available - ?,
                                    boxes_available = FLOOR((pieces_available - ?) / ?),
                                    last_movement_at = NOW()
                                WHERE id = ?
                            ");
                            $deductStmt->execute([$toDeduct, $toDeduct, $batch['pieces_per_box'], $batch['id']]);
                            
                            $remaining -= $toDeduct;
                        }
                    }
                }
                
                // Update linked Sales Order status
                if (!empty($current['order_id'])) {
                    $updateOrder = $db->prepare("
                        UPDATE sales_orders SET status = 'ready', updated_at = NOW()
                        WHERE id = ?
                    ");
                    $updateOrder->execute([$current['order_id']]);
                }
                
                $db->commit();
                
                Response::success([
                    'id' => $id,
                    'dr_number' => $drNumber,
                    'status' => 'ready',
                    'items_picked' => $pickStatus['total_picked'],
                    'all_items_picked' => $allPicked
                ], "Picking complete! DR {$drNumber} generated. Inventory has been deducted.");
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'release':
        case 'dispatch':
            // Only Warehouse FG can dispatch
            if (!in_array($currentUser['role'], ['warehouse_fg', 'general_manager'])) {
                Response::error('Only Warehouse FG can dispatch deliveries', 403);
            }
            
            // Must be 'ready' status (items have been picked) to dispatch
            if ($current['status'] !== 'ready') {
                if ($current['status'] === 'pending' || $current['status'] === 'preparing') {
                    Response::error('Items must be picked first. Use Dispatch page to scan and release items before dispatching.', 400);
                }
                Response::error('DR must be in ready status to dispatch (was: ' . $current['status'] . ')', 400);
            }
            
            // Check if DR has items to dispatch
            $itemsCheck = $db->prepare("
                SELECT COUNT(*) as item_count, COALESCE(SUM(quantity_ordered), 0) as total_qty
                FROM delivery_receipt_items WHERE delivery_receipt_id = ?
            ");
            $itemsCheck->execute([$id]);
            $itemsInfo = $itemsCheck->fetch();
            
            if ($itemsInfo['item_count'] == 0 || $itemsInfo['total_qty'] == 0) {
                Response::error('Cannot dispatch - no items have been added to this delivery receipt.', 400);
            }
            
            $db->beginTransaction();
            try {
                // Note: Inventory was already deducted during the picking process in dispatch.html
                // when status changed from pending -> ready. 
                // This dispatch action just updates status to mark it as physically dispatched.
                
                // Update DR status
                $stmt = $db->prepare("
                    UPDATE delivery_receipts SET
                        status = 'dispatched',
                        dispatched_at = NOW(),
                        dispatched_by = ?
                    WHERE id = ?
                ");
                $stmt->execute([$currentUser['user_id'], $id]);
                
                // Also update linked Sales Order if exists
                if (!empty($current['order_id'])) {
                    $updateOrder = $db->prepare("
                        UPDATE sales_orders SET status = 'dispatched', updated_at = NOW()
                        WHERE id = ? AND status IN ('approved', 'preparing', 'partially_fulfilled', 'ready')
                    ");
                    $updateOrder->execute([$current['order_id']]);
                }
                
                $db->commit();
                Response::success(null, 'Delivery receipt dispatched successfully');
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'deliver':
            // Only Warehouse FG can confirm delivery
            if (!in_array($currentUser['role'], ['warehouse_fg', 'general_manager'])) {
                Response::error('Only Warehouse FG can confirm delivery', 403);
            }
            
            if ($current['status'] !== 'dispatched') {
                Response::error('DR must be dispatched first', 400);
            }
            
            // Check if returns have been processed (required before marking delivered)
            if (!$current['returns_processed']) {
                Response::error(
                    'Cannot mark as delivered: Returns processing not completed. ' .
                    'Please process returns (or mark "No Returns") before confirming delivery.',
                    400
                );
            }
            
            // Returns have been processed, so any shortfall is already accounted for
            // (the returns recording process updated quantity_delivered accordingly)
            
            $db->beginTransaction();
            try {
                $stmt = $db->prepare("
                    UPDATE delivery_receipts SET
                        status = 'delivered',
                        delivered_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$id]);
                
                // Also update linked Sales Order to delivered
                if (!empty($current['order_id'])) {
                    $updateOrder = $db->prepare("
                        UPDATE sales_orders SET status = 'delivered', updated_at = NOW()
                        WHERE id = ? AND status = 'dispatched'
                    ");
                    $updateOrder->execute([$current['order_id']]);
                }
                
                $db->commit();
                Response::success(null, 'Delivery confirmed');
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'cancel':
            if (in_array($current['status'], ['delivered', 'cancelled'])) {
                Response::error('Cannot cancel DR in current status', 400);
            }
            
            $stmt = $db->prepare("UPDATE delivery_receipts SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$id]);
            
            Response::success(null, 'Delivery receipt cancelled');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}
