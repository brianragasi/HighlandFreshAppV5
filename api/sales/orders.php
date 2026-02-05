<?php
/**
 * Highland Fresh System - Sales Orders API
 * 
 * Sales Order/PO processing for Sales Custodian
 * 
 * GET actions: list, detail, pending, by_customer
 * POST actions: create, add_item
 * PUT actions: update, approve, cancel, update_status
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Require Sales Custodian or GM role
$currentUser = Auth::requireRole(['sales_custodian', 'general_manager']);

$action = getParam('action', 'list');

// Valid order statuses
$validStatuses = ['draft', 'pending', 'approved', 'preparing', 'partially_fulfilled', 'fulfilled', 'cancelled'];

try {
    $db = Database::getInstance()->getConnection();
    
    switch ($requestMethod) {
        case 'GET':
            handleGet($db, $action, $validStatuses);
            break;
        case 'POST':
            handlePost($db, $action, $currentUser);
            break;
        case 'PUT':
            handlePut($db, $action, $currentUser, $validStatuses);
            break;
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Sales Orders API Error: " . $e->getMessage());
    Response::error('Server error: ' . $e->getMessage(), 500);
}

/**
 * Generate order number: SO-YYYYMMDD-XXX
 */
function generateOrderNumber($db) {
    $datePrefix = 'SO-' . date('Ymd') . '-';
    
    $stmt = $db->prepare("
        SELECT MAX(CAST(SUBSTRING(order_number, -3) AS UNSIGNED)) as max_seq 
        FROM sales_orders 
        WHERE order_number LIKE ?
    ");
    $stmt->execute([$datePrefix . '%']);
    $maxSeq = $stmt->fetch()['max_seq'] ?? 0;
    
    return $datePrefix . str_pad($maxSeq + 1, 3, '0', STR_PAD_LEFT);
}

/**
 * Handle GET requests
 */
function handleGet($db, $action, $validStatuses) {
    switch ($action) {
        case 'list':
            $status = getParam('status');
            $customerId = getParam('customer_id');
            $dateFrom = getParam('date_from');
            $dateTo = getParam('date_to');
            $search = getParam('search');
            $page = (int) getParam('page', 1);
            $limit = (int) getParam('limit', 20);
            $offset = ($page - 1) * $limit;
            
            $sql = "
                SELECT o.*, c.name as customer_name, c.customer_type, c.customer_code
                FROM sales_orders o
                LEFT JOIN customers c ON o.customer_id = c.id
                WHERE 1=1
            ";
            $params = [];
            
            if ($status && in_array($status, $validStatuses)) {
                $sql .= " AND o.status = ?";
                $params[] = $status;
            }
            
            if ($customerId) {
                $sql .= " AND o.customer_id = ?";
                $params[] = $customerId;
            }
            
            if ($dateFrom) {
                $sql .= " AND DATE(o.created_at) >= ?";
                $params[] = $dateFrom;
            }
            
            if ($dateTo) {
                $sql .= " AND DATE(o.created_at) <= ?";
                $params[] = $dateTo;
            }
            
            if ($search) {
                $sql .= " AND (o.order_number LIKE ? OR o.customer_po_number LIKE ? OR c.name LIKE ?)";
                $searchTerm = "%$search%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            // Get total count
            $countSql = str_replace("SELECT o.*, c.name as customer_name, c.customer_type, c.customer_code", "SELECT COUNT(*) as total", $sql);
            $countStmt = $db->prepare($countSql);
            $countStmt->execute($params);
            $result = $countStmt->fetch();
            $total = $result ? $result['total'] : 0;
            
            $sql .= " ORDER BY o.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $orders = $stmt->fetchAll();
            
            Response::paginated($orders, $total, $page, $limit, 'Orders retrieved');
            break;
            
        case 'detail':
            $id = getParam('id');
            if (!$id) {
                Response::error('Order ID required', 400);
            }
            
            $stmt = $db->prepare("
                SELECT o.*, o.payment_type as payment_mode,
                       c.name as customer_name, c.customer_type, c.customer_code, c.address as customer_address, 
                       c.contact_number as customer_phone, c.default_payment_type
                FROM sales_orders o
                LEFT JOIN customers c ON o.customer_id = c.id
                WHERE o.id = ?
            ");
            $stmt->execute([$id]);
            $order = $stmt->fetch();
            
            if (!$order) {
                Response::notFound('Order not found');
            }
            
            // Get order items
            $itemsStmt = $db->prepare("
                SELECT oi.*, 
                       oi.quantity_ordered as quantity,
                       oi.line_total as subtotal,
                       p.product_name, p.product_code, p.unit_measure, p.base_unit
                FROM sales_order_items oi
                LEFT JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = ?
                ORDER BY oi.id ASC
            ");
            $itemsStmt->execute([$id]);
            $order['items'] = $itemsStmt->fetchAll();
            
            // Get related invoices
            $invoicesStmt = $db->prepare("
                SELECT id, csi_number, invoice_date, total_amount, balance_due, payment_status
                FROM sales_invoices
                WHERE order_id = ? AND status = 'active'
                ORDER BY created_at DESC
            ");
            $invoicesStmt->execute([$id]);
            $order['invoices'] = $invoicesStmt->fetchAll();
            
            // Get status history
            $historyStmt = $db->prepare("
                SELECT sh.*, u.username as changed_by_name
                FROM sales_order_status_history sh
                LEFT JOIN users u ON sh.changed_by = u.id
                WHERE sh.order_id = ?
                ORDER BY sh.created_at DESC
            ");
            $historyStmt->execute([$id]);
            $order['status_history'] = $historyStmt->fetchAll();
            
            Response::success($order, 'Order details retrieved');
            break;
            
        case 'pending':
            // Get orders that need attention (draft, pending, approved, preparing)
            $stmt = $db->prepare("
                SELECT o.*, c.name as customer_name, c.customer_type, c.customer_code
                FROM sales_orders o
                LEFT JOIN customers c ON o.customer_id = c.id
                WHERE o.status IN ('draft', 'pending', 'approved', 'preparing', 'partially_fulfilled')
                ORDER BY 
                    CASE o.status 
                        WHEN 'pending' THEN 1 
                        WHEN 'approved' THEN 2 
                        WHEN 'preparing' THEN 3 
                        WHEN 'partially_fulfilled' THEN 4
                        WHEN 'draft' THEN 5
                    END,
                    o.delivery_date ASC,
                    o.created_at ASC
            ");
            $stmt->execute();
            $orders = $stmt->fetchAll();
            
            Response::success($orders, 'Pending orders retrieved');
            break;
            
        case 'by_customer':
            $customerId = getParam('customer_id');
            if (!$customerId) {
                Response::error('Customer ID required', 400);
            }
            
            $page = (int) getParam('page', 1);
            $limit = (int) getParam('limit', 20);
            $offset = ($page - 1) * $limit;
            
            // Get total count
            $countStmt = $db->prepare("SELECT COUNT(*) as total FROM sales_orders WHERE customer_id = ?");
            $countStmt->execute([$customerId]);
            $total = $countStmt->fetch()['total'];
            
            $stmt = $db->prepare("
                SELECT o.*
                FROM sales_orders o
                WHERE o.customer_id = ?
                ORDER BY o.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$customerId, $limit, $offset]);
            $orders = $stmt->fetchAll();
            
            Response::paginated($orders, $total, $page, $limit, 'Customer orders retrieved');
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

/**
 * Handle POST requests
 */
function handlePost($db, $action, $currentUser) {
    $data = getRequestBody();
    
    switch ($action) {
        case 'create':
            // Validation
            $errors = [];
            
            if (empty($data['customer_id'])) {
                $errors['customer_id'] = 'Customer ID is required';
            }
            
            if (!empty($errors)) {
                Response::validationError($errors);
            }
            
            // Verify customer exists
            $customerStmt = $db->prepare("SELECT * FROM customers WHERE id = ? AND status = 'active'");
            $customerStmt->execute([$data['customer_id']]);
            $customer = $customerStmt->fetch();
            
            if (!$customer) {
                Response::error('Customer not found or inactive', 400);
            }
            
            // Generate order number
            $orderNumber = generateOrderNumber($db);
            
            // Determine payment type (can be overridden per order)
            $paymentType = $data['payment_type'] ?? $customer['default_payment_type'] ?? 'cash';
            
            $db->beginTransaction();
            
            try {
                // Create order
                $stmt = $db->prepare("
                    INSERT INTO sales_orders 
                    (order_number, customer_id, customer_po_number, delivery_date, 
                     payment_type, delivery_address, notes, sub_account_id,
                     subtotal, discount_amount, discount_percent, tax_amount, total_amount,
                     status, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 0, 0, 'draft', ?)
                ");
                
                $stmt->execute([
                    $orderNumber,
                    $data['customer_id'],
                    $data['customer_po_number'] ?? null,
                    $data['delivery_date'] ?? null,
                    $paymentType,
                    $data['delivery_address'] ?? $customer['address'],
                    $data['notes'] ?? $data['special_instructions'] ?? null,
                    $data['sub_account_id'] ?? null,
                    $currentUser['user_id']
                ]);
                
                $orderId = $db->lastInsertId();
                
                // Add items if provided
                if (!empty($data['items']) && is_array($data['items'])) {
                    $subtotal = 0;
                    
                    $itemStmt = $db->prepare("
                        INSERT INTO sales_order_items 
                        (order_id, product_id, product_name, size_value, size_unit, 
                         quantity_ordered, quantity_boxes, quantity_pieces, 
                         unit_type, unit_price, line_total)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    foreach ($data['items'] as $item) {
                        if (empty($item['product_id'])) {
                            continue;
                        }
                        
                        // Get product info
                        $prodStmt = $db->prepare("SELECT product_name, unit_size, unit_measure, selling_price FROM products WHERE id = ?");
                        $prodStmt->execute([$item['product_id']]);
                        $product = $prodStmt->fetch();
                        
                        if (!$product) {
                            continue; // Skip invalid product
                        }
                        
                        // Calculate total quantity from boxes and pieces
                        $boxes = (int)($item['quantity_boxes'] ?? 0);
                        $pieces = (int)($item['quantity_pieces'] ?? 0);
                        $quantity = (int)($item['quantity'] ?? ($boxes + $pieces)); // Fallback if not provided
                        
                        if ($quantity <= 0) {
                            continue;
                        }
                        
                        // Determine unit type
                        $unitType = $item['unit_type'] ?? 'piece';
                        if ($boxes > 0 && $pieces > 0) {
                            $unitType = 'mixed';
                        } elseif ($boxes > 0) {
                            $unitType = 'box';
                        }
                        
                        // Get product price if not provided
                        $unitPrice = $item['unit_price'] ?? $product['selling_price'] ?? 0;
                        
                        $lineTotal = ($quantity * $unitPrice);
                        $subtotal += $lineTotal;
                        
                        $itemStmt->execute([
                            $orderId,
                            $item['product_id'],
                            $product['product_name'],
                            $product['unit_size'] ?? 0,
                            $product['unit_measure'] ?? 'ml',
                            $quantity,
                            $boxes,
                            $pieces,
                            $unitType,
                            $unitPrice,
                            $lineTotal
                        ]);
                    }
                    
                    // Update order totals
                    $discountPercent = $data['discount_percent'] ?? 0;
                    $orderDiscount = $data['discount_amount'] ?? ($subtotal * ($discountPercent / 100));
                    $taxAmount = $data['tax_amount'] ?? 0;
                    $totalAmount = $subtotal - $orderDiscount + $taxAmount;
                    
                    $updateStmt = $db->prepare("
                        UPDATE sales_orders SET
                            subtotal = ?,
                            discount_amount = ?,
                            discount_percent = ?,
                            tax_amount = ?,
                            total_amount = ?
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$subtotal, $orderDiscount, $discountPercent, $taxAmount, $totalAmount, $orderId]);
                }
                
                // Record status history
                $historyStmt = $db->prepare("
                    INSERT INTO sales_order_status_history (order_id, status, notes, changed_by)
                    VALUES (?, 'draft', 'Order created', ?)
                ");
                $historyStmt->execute([$orderId, $currentUser['user_id']]);
                
                $db->commit();
                
                logAudit($currentUser['user_id'], 'CREATE', 'sales_orders', $orderId, null, $data);
                
                // Get created order
                $getStmt = $db->prepare("
                    SELECT o.*, c.name as customer_name 
                    FROM sales_orders o 
                    LEFT JOIN customers c ON o.customer_id = c.id 
                    WHERE o.id = ?
                ");
                $getStmt->execute([$orderId]);
                $order = $getStmt->fetch();
                
                Response::created($order, 'Order created successfully');
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'add_item':
            $orderId = $data['order_id'] ?? getParam('id');
            
            if (!$orderId) {
                Response::error('Order ID required', 400);
            }
            
            // Verify order exists and is editable
            $orderStmt = $db->prepare("SELECT * FROM sales_orders WHERE id = ?");
            $orderStmt->execute([$orderId]);
            $order = $orderStmt->fetch();
            
            if (!$order) {
                Response::notFound('Order not found');
            }
            
            if (!in_array($order['status'], ['draft', 'pending'])) {
                Response::error('Cannot add items to order in ' . $order['status'] . ' status', 400);
            }
            
            // Validation
            if (empty($data['product_id']) || empty($data['quantity'])) {
                Response::validationError(['product_id' => 'Product and quantity are required']);
            }
            
            // Get product price
            $unitPrice = $data['unit_price'] ?? 0;
            if (!$unitPrice) {
                $prodStmt = $db->prepare("SELECT selling_price FROM products WHERE id = ?");
                $prodStmt->execute([$data['product_id']]);
                $product = $prodStmt->fetch();
                $unitPrice = $product ? $product['selling_price'] : 0;
            }
            
            $discountAmount = $data['discount_amount'] ?? 0;
            $lineTotal = ($data['quantity'] * $unitPrice) - $discountAmount;
            
            $db->beginTransaction();
            
            try {
                // Add item
                $stmt = $db->prepare("
                    INSERT INTO sales_order_items 
                    (order_id, product_id, quantity, unit_price, discount_amount, line_total)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $orderId,
                    $data['product_id'],
                    $data['quantity'],
                    $unitPrice,
                    $discountAmount,
                    $lineTotal
                ]);
                
                $itemId = $db->lastInsertId();
                
                // Recalculate order totals
                $totalsStmt = $db->prepare("
                    SELECT COALESCE(SUM(line_total), 0) as subtotal FROM sales_order_items WHERE order_id = ?
                ");
                $totalsStmt->execute([$orderId]);
                $subtotal = $totalsStmt->fetch()['subtotal'];
                
                $orderDiscount = $order['discount_percent'] > 0 
                    ? $subtotal * ($order['discount_percent'] / 100) 
                    : $order['discount_amount'];
                $totalAmount = $subtotal - $orderDiscount + $order['tax_amount'];
                
                $updateStmt = $db->prepare("
                    UPDATE sales_orders SET
                        subtotal = ?,
                        discount_amount = ?,
                        total_amount = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([$subtotal, $orderDiscount, $totalAmount, $orderId]);
                
                $db->commit();
                
                // Get created item
                $getStmt = $db->prepare("
                    SELECT oi.*, p.product_name, p.product_code
                    FROM sales_order_items oi
                    LEFT JOIN products p ON oi.product_id = p.id
                    WHERE oi.id = ?
                ");
                $getStmt->execute([$itemId]);
                $item = $getStmt->fetch();
                
                Response::created($item, 'Item added successfully');
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}

/**
 * Handle PUT requests
 */
function handlePut($db, $action, $currentUser, $validStatuses) {
    $data = getRequestBody();
    $id = getParam('id') ?? ($data['id'] ?? null);
    
    if (!$id) {
        Response::error('Order ID required', 400);
    }
    
    // Get current order
    $check = $db->prepare("SELECT * FROM sales_orders WHERE id = ?");
    $check->execute([$id]);
    $current = $check->fetch();
    
    if (!$current) {
        Response::notFound('Order not found');
    }
    
    switch ($action) {
        case 'update':
            // Only allow updates on draft or pending orders
            if (!in_array($current['status'], ['draft', 'pending'])) {
                Response::error('Cannot update order in ' . $current['status'] . ' status', 400);
            }
            
            $stmt = $db->prepare("
                UPDATE sales_orders SET
                    customer_po_number = COALESCE(?, customer_po_number),
                    delivery_date = COALESCE(?, delivery_date),
                    delivery_address = COALESCE(?, delivery_address),
                    notes = COALESCE(?, notes),
                    payment_type = COALESCE(?, payment_type),
                    sub_account_id = COALESCE(?, sub_account_id),
                    discount_amount = COALESCE(?, discount_amount),
                    discount_percent = COALESCE(?, discount_percent),
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $data['customer_po_number'] ?? null,
                $data['delivery_date'] ?? null,
                $data['delivery_address'] ?? null,
                $data['notes'] ?? $data['special_instructions'] ?? null,
                $data['payment_type'] ?? null,
                $data['sub_account_id'] ?? null,
                $data['discount_amount'] ?? null,
                $data['discount_percent'] ?? null,
                $id
            ]);
            
            // Recalculate totals if discount changed
            if (isset($data['discount_amount']) || isset($data['discount_percent'])) {
                $updatedStmt = $db->prepare("SELECT * FROM sales_orders WHERE id = ?");
                $updatedStmt->execute([$id]);
                $updated = $updatedStmt->fetch();
                
                $orderDiscount = $updated['discount_percent'] > 0 
                    ? $updated['subtotal'] * ($updated['discount_percent'] / 100) 
                    : $updated['discount_amount'];
                $totalAmount = $updated['subtotal'] - $orderDiscount + $updated['tax_amount'];
                
                $totalStmt = $db->prepare("UPDATE sales_orders SET discount_amount = ?, total_amount = ? WHERE id = ?");
                $totalStmt->execute([$orderDiscount, $totalAmount, $id]);
            }
            
            logAudit($currentUser['user_id'], 'UPDATE', 'sales_orders', $id, $current, $data);
            
            Response::success(null, 'Order updated successfully');
            break;
            
        case 'approve':
            if ($current['status'] !== 'pending') {
                Response::error('Only pending orders can be approved', 400);
            }
            
            // Check if order has items
            $itemsStmt = $db->prepare("SELECT COUNT(*) as count FROM sales_order_items WHERE order_id = ?");
            $itemsStmt->execute([$id]);
            if ($itemsStmt->fetch()['count'] == 0) {
                Response::error('Cannot approve order with no items', 400);
            }
            
            $db->beginTransaction();
            
            try {
                $stmt = $db->prepare("
                    UPDATE sales_orders SET
                        status = 'approved',
                        approved_by = ?,
                        approved_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$currentUser['user_id'], $id]);
                
                // Record status history
                $historyStmt = $db->prepare("
                    INSERT INTO sales_order_status_history (order_id, status, notes, changed_by)
                    VALUES (?, 'approved', ?, ?)
                ");
                $historyStmt->execute([$id, $data['notes'] ?? 'Order approved', $currentUser['user_id']]);
                
                $db->commit();
                
                logAudit($currentUser['user_id'], 'APPROVE', 'sales_orders', $id, 
                    ['status' => $current['status']], 
                    ['status' => 'approved']
                );
                
                Response::success(null, 'Order approved successfully');
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'cancel':
            if (in_array($current['status'], ['fulfilled', 'cancelled'])) {
                Response::error('Cannot cancel order in ' . $current['status'] . ' status', 400);
            }
            
            if (empty($data['cancellation_reason'])) {
                Response::validationError(['cancellation_reason' => 'Cancellation reason is required']);
            }
            
            $db->beginTransaction();
            
            try {
                $stmt = $db->prepare("
                    UPDATE sales_orders SET
                        status = 'cancelled',
                        cancellation_reason = ?,
                        cancelled_by = ?,
                        cancelled_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$data['cancellation_reason'], $currentUser['user_id'], $id]);
                
                // Record status history
                $historyStmt = $db->prepare("
                    INSERT INTO sales_order_status_history (order_id, status, notes, changed_by)
                    VALUES (?, 'cancelled', ?, ?)
                ");
                $historyStmt->execute([$id, $data['cancellation_reason'], $currentUser['user_id']]);
                
                $db->commit();
                
                logAudit($currentUser['user_id'], 'CANCEL', 'sales_orders', $id, 
                    ['status' => $current['status']], 
                    ['status' => 'cancelled', 'reason' => $data['cancellation_reason']]
                );
                
                Response::success(null, 'Order cancelled successfully');
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'update_status':
            $newStatus = $data['status'] ?? null;
            
            if (!$newStatus || !in_array($newStatus, $validStatuses)) {
                Response::validationError(['status' => 'Valid status is required: ' . implode(', ', $validStatuses)]);
            }
            
            // Define valid status transitions
            $validTransitions = [
                'draft' => ['pending', 'cancelled'],
                'pending' => ['approved', 'cancelled'],
                'approved' => ['preparing', 'cancelled'],
                'preparing' => ['partially_fulfilled', 'fulfilled', 'cancelled'],
                'partially_fulfilled' => ['fulfilled', 'cancelled']
            ];
            
            if (!isset($validTransitions[$current['status']]) || 
                !in_array($newStatus, $validTransitions[$current['status']])) {
                Response::error("Cannot transition from {$current['status']} to {$newStatus}", 400);
            }
            
            $db->beginTransaction();
            
            try {
                $stmt = $db->prepare("
                    UPDATE sales_orders SET
                        status = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$newStatus, $id]);
                
                // Record status history
                $historyStmt = $db->prepare("
                    INSERT INTO sales_order_status_history (order_id, status, notes, changed_by)
                    VALUES (?, ?, ?, ?)
                ");
                $historyStmt->execute([$id, $newStatus, $data['notes'] ?? null, $currentUser['user_id']]);
                
                $db->commit();
                
                logAudit($currentUser['user_id'], 'UPDATE_STATUS', 'sales_orders', $id, 
                    ['status' => $current['status']], 
                    ['status' => $newStatus]
                );
                
                Response::success(['status' => $newStatus], 'Order status updated successfully');
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        default:
            Response::error('Invalid action', 400);
    }
}
