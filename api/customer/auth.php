<?php
/**
 * Highland Fresh System - Customer Authentication API
 * 
 * POST /api/customer/auth.php?action=login - Customer login
 * POST /api/customer/auth.php?action=register - Customer registration
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

$action = getParam('action', 'login');

try {
    $db = Database::getInstance()->getConnection();
    
    switch ($requestMethod) {
        case 'POST':
            if ($action === 'login') {
                handleLogin($db);
            } elseif ($action === 'register') {
                handleRegister($db);
            } else {
                Response::error('Invalid action', 400);
            }
            break;
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Customer Auth Error: " . $e->getMessage());
    Response::error('Server error: ' . $e->getMessage(), 500);
}

function handleLogin($db) {
    $email = getParam('email');
    $password = getParam('password');
    
    if (empty($email) || empty($password)) {
        Response::validationError([
            'email' => empty($email) ? 'Email is required' : null,
            'password' => empty($password) ? 'Password is required' : null
        ]);
    }
    
    // Find customer by email
    $stmt = $db->prepare("
        SELECT id, name, email, password, phone, address, 
               customer_type, credit_limit, status
        FROM customers
        WHERE email = ? AND status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $customer = $stmt->fetch();
    
    if (!$customer) {
        Response::error('Invalid email or password', 401);
    }
    
    // Verify password
    if (!password_verify($password, $customer['password'])) {
        Response::error('Invalid email or password', 401);
    }
    
    // Generate token for customer
    $token = generateCustomerToken($customer);
    
    // Update last login
    try {
        $updateStmt = $db->prepare("UPDATE customers SET updated_at = NOW() WHERE id = ?");
        $updateStmt->execute([$customer['id']]);
    } catch (Exception $e) {
        // Ignore update errors
    }
    
    // Remove password from response
    unset($customer['password']);
    
    Response::success([
        'customer' => $customer,
        'token' => $token,
        'expires_in' => JWT_EXPIRY
    ], 'Login successful');
}

function handleRegister($db) {
    $name = getParam('name');
    $email = getParam('email');
    $password = getParam('password');
    $phone = getParam('phone');
    $address = getParam('address');
    $storeName = getParam('store_name');
    $customerType = getParam('customer_type', 'sari_sari');
    
    // Validate required fields
    $errors = [];
    if (empty($name)) $errors['name'] = 'Name is required';
    if (empty($email)) $errors['email'] = 'Email is required';
    if (empty($password)) $errors['password'] = 'Password is required';
    if (strlen($password) < 6) $errors['password'] = 'Password must be at least 6 characters';
    if (empty($phone)) $errors['phone'] = 'Phone is required';
    if (empty($address)) $errors['address'] = 'Address is required';
    
    // Validate email format
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    }
    
    if (!empty($errors)) {
        Response::validationError($errors);
    }
    
    // Check if email already exists
    $checkStmt = $db->prepare("SELECT id FROM customers WHERE email = ?");
    $checkStmt->execute([$email]);
    if ($checkStmt->fetch()) {
        Response::error('Email already registered', 409);
    }
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    
    // Generate customer code
    $customerCode = 'CUST-' . strtoupper(substr(md5(uniqid()), 0, 8));
    
    // Insert customer
    $stmt = $db->prepare("
        INSERT INTO customers (
            customer_code, name, contact_person, email, password, phone, 
            address, customer_type, status, credit_limit, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', 0, NOW())
    ");
    $stmt->execute([
        $customerCode,
        $storeName ?: $name,
        $name,
        $email,
        $hashedPassword,
        $phone,
        $address,
        $customerType
    ]);
    
    $customerId = $db->lastInsertId();
    
    // Get the created customer
    $getStmt = $db->prepare("SELECT id, name, email, phone, address, customer_type, status FROM customers WHERE id = ?");
    $getStmt->execute([$customerId]);
    $customer = $getStmt->fetch();
    
    // Generate token
    $token = generateCustomerToken($customer);
    
    Response::success([
        'customer' => $customer,
        'token' => $token,
        'expires_in' => JWT_EXPIRY
    ], 'Registration successful');
}

function generateCustomerToken($customer) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    
    $payload = json_encode([
        'iss' => APP_NAME,
        'iat' => time(),
        'exp' => time() + JWT_EXPIRY,
        'customer_id' => $customer['id'],
        'email' => $customer['email'],
        'name' => $customer['name'],
        'role' => 'customer',
        'customer_type' => $customer['customer_type'] ?? 'sari_sari'
    ]);
    
    $base64Header = base64UrlEncode($header);
    $base64Payload = base64UrlEncode($payload);
    
    $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, JWT_SECRET, true);
    $base64Signature = base64UrlEncode($signature);
    
    return $base64Header . "." . $base64Payload . "." . $base64Signature;
}

function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
