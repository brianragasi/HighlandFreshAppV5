<?php
/**
 * Highland Fresh System - Customer Profile API
 * 
 * GET - Get customer profile, dashboard stats
 * PUT - Update profile, change password
 * 
 * Requires customer authentication
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Get customer from token
$customer = getCustomerFromToken();
$action = getParam('action', 'me');

try {
    $db = Database::getInstance()->getConnection();
    
    switch ($requestMethod) {
        case 'GET':
            handleGet($db, $action, $customer);
            break;
        case 'PUT':
            handlePut($db, $action, $customer);
            break;
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Customer Profile API Error: " . $e->getMessage());
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
        case 'me':
            getProfile($db, $customer);
            break;
        case 'dashboard':
            getDashboardStats($db, $customer);
            break;
        case 'addresses':
            getAddresses($db, $customer);
            break;
        default:
            Response::error('Invalid action', 400);
    }
}

function handlePut($db, $action, $customer) {
    switch ($action) {
        case 'update':
            updateProfile($db, $customer);
            break;
        case 'password':
            changePassword($db, $customer);
            break;
        case 'address':
            updateAddress($db, $customer);
            break;
        default:
            Response::error('Invalid action', 400);
    }
}

function getProfile($db, $customer) {
    $stmt = $db->prepare("
        SELECT 
            id, customer_code, name, contact_person, email, phone,
            address, customer_type, credit_limit, status, created_at
        FROM customers
        WHERE id = ?
    ");
    $stmt->execute([$customer['customer_id']]);
    $profile = $stmt->fetch();
    
    if (!$profile) {
        Response::error('Customer not found', 404);
    }
    
    Response::success($profile, 'Profile retrieved');
}

function getDashboardStats($db, $customer) {
    $customerId = $customer['customer_id'];
    
    // Total orders
    $ordersStmt = $db->prepare("
        SELECT COUNT(*) as total_orders
        FROM customer_orders
        WHERE customer_id = ?
    ");
    $ordersStmt->execute([$customerId]);
    $ordersData = $ordersStmt->fetch();
    
    // Pending orders
    $pendingStmt = $db->prepare("
        SELECT COUNT(*) as pending_orders
        FROM customer_orders
        WHERE customer_id = ? AND status IN ('pending', 'confirmed', 'preparing', 'out_for_delivery')
    ");
    $pendingStmt->execute([$customerId]);
    $pendingData = $pendingStmt->fetch();
    
    // Total spent (delivered orders only)
    $spentStmt = $db->prepare("
        SELECT COALESCE(SUM(total_amount), 0) as total_spent
        FROM customer_orders
        WHERE customer_id = ? AND status = 'delivered' AND payment_status = 'paid'
    ");
    $spentStmt->execute([$customerId]);
    $spentData = $spentStmt->fetch();
    
    // Recent orders (last 5)
    $recentStmt = $db->prepare("
        SELECT 
            id, order_number, status, total_amount, 
            created_at, delivery_date,
            (SELECT COUNT(*) FROM customer_order_items WHERE order_id = co.id) as item_count
        FROM customer_orders co
        WHERE customer_id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $recentStmt->execute([$customerId]);
    $recentOrders = $recentStmt->fetchAll();
    
    // Frequently ordered products (top 5)
    $frequentStmt = $db->prepare("
        SELECT 
            p.id,
            p.name,
            p.product_code,
            p.price,
            p.image_url,
            COUNT(*) as order_count,
            SUM(coi.quantity) as total_quantity
        FROM customer_order_items coi
        JOIN customer_orders co ON co.id = coi.order_id
        JOIN products p ON p.id = coi.product_id
        WHERE co.customer_id = ? AND p.is_active = 1
        GROUP BY p.id
        ORDER BY order_count DESC, total_quantity DESC
        LIMIT 5
    ");
    $frequentStmt->execute([$customerId]);
    $frequentProducts = $frequentStmt->fetchAll();
    
    Response::success([
        'total_orders' => (int)$ordersData['total_orders'],
        'pending_orders' => (int)$pendingData['pending_orders'],
        'total_spent' => (float)$spentData['total_spent'],
        'recent_orders' => $recentOrders,
        'frequent_products' => $frequentProducts
    ], 'Dashboard stats retrieved');
}

function getAddresses($db, $customer) {
    // For now, return the main address
    // Can be extended to support multiple delivery addresses
    $stmt = $db->prepare("
        SELECT id, address as delivery_address, 'default' as address_type
        FROM customers
        WHERE id = ?
    ");
    $stmt->execute([$customer['customer_id']]);
    $addresses = $stmt->fetchAll();
    
    Response::success($addresses, 'Addresses retrieved');
}

function updateProfile($db, $customer) {
    $name = getParam('name');
    $contactPerson = getParam('contact_person');
    $phone = getParam('phone');
    $address = getParam('address');
    
    // Build update query dynamically
    $updates = [];
    $params = [];
    
    if ($name !== null) {
        $updates[] = "name = ?";
        $params[] = $name;
    }
    
    if ($contactPerson !== null) {
        $updates[] = "contact_person = ?";
        $params[] = $contactPerson;
    }
    
    if ($phone !== null) {
        $updates[] = "phone = ?";
        $params[] = $phone;
    }
    
    if ($address !== null) {
        $updates[] = "address = ?";
        $params[] = $address;
    }
    
    if (empty($updates)) {
        Response::error('No fields to update', 400);
    }
    
    $updates[] = "updated_at = NOW()";
    $params[] = $customer['customer_id'];
    
    $sql = "UPDATE customers SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    // Get updated profile
    getProfile($db, $customer);
}

function changePassword($db, $customer) {
    $currentPassword = getParam('current_password');
    $newPassword = getParam('new_password');
    
    if (empty($currentPassword) || empty($newPassword)) {
        Response::error('Current and new password required', 400);
    }
    
    if (strlen($newPassword) < 6) {
        Response::error('New password must be at least 6 characters', 400);
    }
    
    // Verify current password
    $stmt = $db->prepare("SELECT password FROM customers WHERE id = ?");
    $stmt->execute([$customer['customer_id']]);
    $customerData = $stmt->fetch();
    
    if (!password_verify($currentPassword, $customerData['password'])) {
        Response::error('Current password is incorrect', 400);
    }
    
    // Update password
    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 10]);
    $updateStmt = $db->prepare("UPDATE customers SET password = ?, updated_at = NOW() WHERE id = ?");
    $updateStmt->execute([$hashedPassword, $customer['customer_id']]);
    
    Response::success(null, 'Password changed successfully');
}

function updateAddress($db, $customer) {
    $address = getParam('address');
    
    if (empty($address)) {
        Response::error('Address required', 400);
    }
    
    $stmt = $db->prepare("UPDATE customers SET address = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$address, $customer['customer_id']]);
    
    Response::success(null, 'Address updated successfully');
}
