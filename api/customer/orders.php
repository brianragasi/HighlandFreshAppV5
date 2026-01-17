<?php
/**
 * Highland Fresh System - Customer Orders API
 * 
 * GET - List customer orders, get order details
 * POST - Place new order
 * PUT - Cancel order (only pending orders)
 * 
 * Requires customer authentication
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Get customer from token
$customer = getCustomerFromToken();
$action = getParam('action', 'list');

try {
    $db = Database::getInstance()->getConnection();
    
    switch ($requestMethod) {
        case 'GET':
            handleGet($db, $action, $customer);
            break;
        case 'POST':
            handlePost($db, $action, $customer);
            break;
        case 'PUT':
            handlePut($db, $action, $customer);
            break;
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Customer Orders API Error: " . $e->getMessage());
    Response::error('Server error: ' . $e->getMessage(), 500);
}

function getCustomerFromToken() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    if (empty($authHeader)) {
        Response::unauthorized('Please login to continue');
    }
    
    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $token = $matches[1];
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            Response::unauthorized('Invalid token');
        }
        
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        
        if (!isset($payload['exp']) || $payload['exp'] < time()) {
            Response::unauthorized('Token expired');
        }
        
        if (!isset($payload['customer_id'])) {
            Response::unauthorized('Invalid customer token');
        }
        
        return $payload;
    }
    
    Response::unauthorized('Invalid authorization header');
}

function handleGet($db, $action, $customer) {
    switch ($action) {
        case 'list':
            getOrderList($db, $customer);
            break;
        case 'detail':
            getOrderDetail($db, $customer);
            break;
        case 'track':
            trackOrder($db, $customer);
            break;
        case 'reorder':
            getReorderItems($db, $customer);
            break;
        default:
            Response::error('Invalid action', 400);
    }
}

function handlePost($db, $action, $customer) {
    switch ($action) {
        case 'place':
            placeOrder($db, $customer);
            break;
        default:
            Response::error('Invalid action', 400);
    }
}

function handlePut($db, $action, $customer) {
    switch ($action) {
        case 'cancel':
            cancelOrder($db, $customer);
            break;
        default:
            Response::error('Invalid action', 400);
    }
}

function getOrderList($db, $customer) {
    $status = getParam('status');
    $limit = min((int)getParam('limit', 20), 50);
    $offset = (int)getParam('offset', 0);
    
    $sql = "
        SELECT 
            co.id,
            co.order_number,
            co.status,
            co.payment_method,
            co.payment_status,
            co.subtotal,
            co.delivery_fee,
            co.total_amount,
            co.delivery_address,
            co.delivery_date,
            co.notes,
            co.created_at,
            co.updated_at,
            (SELECT COUNT(*) FROM customer_order_items WHERE order_id = co.id) as item_count
        FROM customer_orders co
        WHERE co.customer_id = ?
    ";
    $params = [$customer['customer_id']];
    
    if ($status) {
        $sql .= " AND co.status = ?";
        $params[] = $status;
    }
    
    $sql .= " ORDER BY co.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
    
    // Get total count
    $countSql = "SELECT COUNT(*) FROM customer_orders WHERE customer_id = ?";
    $countParams = [$customer['customer_id']];
    if ($status) {
        $countSql .= " AND status = ?";
        $countParams[] = $status;
    }
    
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($countParams);
    $total = $countStmt->fetchColumn();
    
    Response::success([
        'orders' => $orders,
        'pagination' => [
            'total' => (int)$total,
            'limit' => $limit,
            'offset' => $offset
        ]
    ], 'Orders retrieved');
}

function getOrderDetail($db, $customer) {
    $orderId = getParam('id');
    
    if (!$orderId) {
        Response::error('Order ID required', 400);
    }
    
    // Get order header
    $stmt = $db->prepare("
        SELECT 
            co.id,
            co.order_number,
            co.status,
            co.payment_method,
            co.payment_status,
            co.subtotal,
            co.delivery_fee,
            co.total_amount,
            co.delivery_address,
            co.delivery_date,
            co.notes,
            co.created_at,
            co.updated_at,
            co.confirmed_at,
            co.delivered_at
        FROM customer_orders co
        WHERE co.id = ? AND co.customer_id = ?
    ");
    $stmt->execute([$orderId, $customer['customer_id']]);
    $order = $stmt->fetch();
    
    if (!$order) {
        Response::error('Order not found', 404);
    }
    
    // Get order items
    $itemsStmt = $db->prepare("
        SELECT 
            coi.id,
            coi.product_id,
            p.name as product_name,
            p.product_code,
            p.image_url,
            coi.quantity,
            coi.unit_price,
            coi.total_price
        FROM customer_order_items coi
        JOIN products p ON p.id = coi.product_id
        WHERE coi.order_id = ?
    ");
    $itemsStmt->execute([$orderId]);
    $items = $itemsStmt->fetchAll();
    
    $order['items'] = $items;
    
    // Get order status history
    $historyStmt = $db->prepare("
        SELECT status, notes, created_at
        FROM customer_order_history
        WHERE order_id = ?
        ORDER BY created_at ASC
    ");
    $historyStmt->execute([$orderId]);
    $history = $historyStmt->fetchAll();
    
    $order['history'] = $history;
    
    Response::success($order, 'Order details retrieved');
}

function trackOrder($db, $customer) {
    $orderNumber = getParam('order_number');
    
    if (!$orderNumber) {
        Response::error('Order number required', 400);
    }
    
    $stmt = $db->prepare("
        SELECT 
            co.id,
            co.order_number,
            co.status,
            co.delivery_date,
            co.delivery_address,
            co.created_at
        FROM customer_orders co
        WHERE co.order_number = ? AND co.customer_id = ?
    ");
    $stmt->execute([$orderNumber, $customer['customer_id']]);
    $order = $stmt->fetch();
    
    if (!$order) {
        Response::error('Order not found', 404);
    }
    
    // Get tracking history
    $historyStmt = $db->prepare("
        SELECT status, notes, created_at
        FROM customer_order_history
        WHERE order_id = ?
        ORDER BY created_at ASC
    ");
    $historyStmt->execute([$order['id']]);
    $history = $historyStmt->fetchAll();
    
    $order['tracking'] = $history;
    
    Response::success($order, 'Order tracking info');
}

function getReorderItems($db, $customer) {
    // Get items from last completed order
    $stmt = $db->prepare("
        SELECT 
            coi.product_id,
            p.name as product_name,
            p.product_code,
            p.price as current_price,
            p.image_url,
            coi.quantity as last_quantity
        FROM customer_order_items coi
        JOIN products p ON p.id = coi.product_id
        JOIN customer_orders co ON co.id = coi.order_id
        WHERE co.customer_id = ?
        AND co.status = 'delivered'
        AND p.is_active = 1
        ORDER BY co.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$customer['customer_id']]);
    $items = $stmt->fetchAll();
    
    Response::success($items, 'Reorder items retrieved');
}

function placeOrder($db, $customer) {
    $items = getParam('items');
    $deliveryAddress = getParam('delivery_address');
    $deliveryDate = getParam('delivery_date');
    $paymentMethod = getParam('payment_method', 'cod'); // cod, online
    $notes = getParam('notes');
    
    // Validate
    if (empty($items) || !is_array($items)) {
        Response::error('Order items required', 400);
    }
    
    if (empty($deliveryAddress)) {
        Response::error('Delivery address required', 400);
    }
    
    $db->beginTransaction();
    
    try {
        // Calculate totals and validate stock
        $subtotal = 0;
        $orderItems = [];
        
        foreach ($items as $item) {
            if (empty($item['product_id']) || empty($item['quantity'])) {
                throw new Exception('Invalid item data');
            }
            
            // Get product price and check availability
            $productStmt = $db->prepare("
                SELECT id, name, price, is_active
                FROM products
                WHERE id = ?
            ");
            $productStmt->execute([$item['product_id']]);
            $product = $productStmt->fetch();
            
            if (!$product || !$product['is_active']) {
                throw new Exception("Product {$item['product_id']} not available");
            }
            
            $quantity = (int)$item['quantity'];
            $unitPrice = (float)$product['price'];
            $totalPrice = $unitPrice * $quantity;
            $subtotal += $totalPrice;
            
            $orderItems[] = [
                'product_id' => $product['id'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $totalPrice
            ];
        }
        
        // Calculate delivery fee (can be customized)
        $deliveryFee = 0; // Free delivery for now
        $totalAmount = $subtotal + $deliveryFee;
        
        // Generate order number
        $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
        
        // Create order
        $orderStmt = $db->prepare("
            INSERT INTO customer_orders (
                customer_id, order_number, status, payment_method, payment_status,
                subtotal, delivery_fee, total_amount, delivery_address, 
                delivery_date, notes, created_at
            ) VALUES (?, ?, 'pending', ?, 'unpaid', ?, ?, ?, ?, ?, ?, NOW())
        ");
        $orderStmt->execute([
            $customer['customer_id'],
            $orderNumber,
            $paymentMethod,
            $subtotal,
            $deliveryFee,
            $totalAmount,
            $deliveryAddress,
            $deliveryDate,
            $notes
        ]);
        
        $orderId = $db->lastInsertId();
        
        // Insert order items
        $itemStmt = $db->prepare("
            INSERT INTO customer_order_items (order_id, product_id, quantity, unit_price, total_price)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        foreach ($orderItems as $item) {
            $itemStmt->execute([
                $orderId,
                $item['product_id'],
                $item['quantity'],
                $item['unit_price'],
                $item['total_price']
            ]);
        }
        
        // Add to order history
        $historyStmt = $db->prepare("
            INSERT INTO customer_order_history (order_id, status, notes, created_at)
            VALUES (?, 'pending', 'Order placed by customer', NOW())
        ");
        $historyStmt->execute([$orderId]);
        
        $db->commit();
        
        Response::success([
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'total_amount' => $totalAmount,
            'status' => 'pending'
        ], 'Order placed successfully');
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function cancelOrder($db, $customer) {
    $orderId = getParam('id');
    $reason = getParam('reason', 'Cancelled by customer');
    
    if (!$orderId) {
        Response::error('Order ID required', 400);
    }
    
    // Check if order exists and belongs to customer
    $checkStmt = $db->prepare("
        SELECT id, status FROM customer_orders
        WHERE id = ? AND customer_id = ?
    ");
    $checkStmt->execute([$orderId, $customer['customer_id']]);
    $order = $checkStmt->fetch();
    
    if (!$order) {
        Response::error('Order not found', 404);
    }
    
    // Only allow cancellation of pending orders
    if ($order['status'] !== 'pending') {
        Response::error('Only pending orders can be cancelled', 400);
    }
    
    $db->beginTransaction();
    
    try {
        // Update order status
        $updateStmt = $db->prepare("
            UPDATE customer_orders
            SET status = 'cancelled', updated_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$orderId]);
        
        // Add to history
        $historyStmt = $db->prepare("
            INSERT INTO customer_order_history (order_id, status, notes, created_at)
            VALUES (?, 'cancelled', ?, NOW())
        ");
        $historyStmt->execute([$orderId, $reason]);
        
        $db->commit();
        
        Response::success(['order_id' => $orderId], 'Order cancelled successfully');
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}
