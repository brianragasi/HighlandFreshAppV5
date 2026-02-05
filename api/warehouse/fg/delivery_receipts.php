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

// Require Warehouse FG role
$currentUser = Auth::requireRole(['warehouse_fg', 'general_manager', 'sales_custodian']);

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
            
            // Get line items
            $itemsStmt = $db->prepare("
                SELECT dri.*,
                       p.product_name,
                       p.product_code as product_sku
                FROM delivery_receipt_items dri
                LEFT JOIN products p ON dri.product_id = p.id
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
                    u.first_name as prepared_by_name
                FROM delivery_receipts dr
                LEFT JOIN users u ON dr.created_by = u.id
                WHERE dr.status IN ('pending', 'preparing', 'draft')
                ORDER BY dr.created_at ASC
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
            SELECT oi.*, p.product_name
            FROM sales_order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $itemsStmt->execute([$orderId]);
        $orderItems = $itemsStmt->fetchAll();
        
        $db->beginTransaction();
        
        try {
            // Generate DR number
            $drNumber = 'DR-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            $stmt = $db->prepare("
                INSERT INTO delivery_receipts 
                (dr_number, order_id, customer_id, customer_name, delivery_address, 
                 contact_number, total_items, total_amount, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)
            ");
            
            $stmt->execute([
                $drNumber,
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
            
            // Update order status to preparing
            $updateOrder = $db->prepare("UPDATE sales_orders SET status = 'preparing' WHERE id = ?");
            $updateOrder->execute([$orderId]);
            
            $db->commit();
            
            Response::success(['id' => $drId, 'dr_number' => $drNumber], 'Delivery receipt created from order', 201);
            
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
            
        case 'release':
        case 'dispatch':
            if ($current['status'] !== 'pending' && $current['status'] !== 'preparing') {
                Response::error('DR must be in pending or preparing status to dispatch', 400);
            }
            
            $db->beginTransaction();
            try {
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
                        WHERE id = ? AND status IN ('approved', 'preparing', 'partially_fulfilled')
                    ");
                    $updateOrder->execute([$current['order_id']]);
                }
                
                $db->commit();
                Response::success(null, 'Delivery receipt dispatched');
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'deliver':
            if ($current['status'] !== 'dispatched') {
                Response::error('DR must be dispatched first', 400);
            }
            
            $stmt = $db->prepare("
                UPDATE delivery_receipts SET
                    status = 'delivered',
                    delivered_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$id]);
            
            Response::success(null, 'Delivery confirmed');
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
