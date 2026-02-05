<?php
/**
 * Admin Users API
 * Handles CRUD operations for user management
 * CRITICAL: Requires General Manager role
 */

require_once __DIR__ . '/../bootstrap.php';

// SECURITY: Require GM or Admin role for user management
$currentUser = Auth::requireRole(['general_manager', 'admin']);

// Get database connection
$pdo = Database::getInstance()->getConnection();

// Handle request
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                getUser($_GET['id']);
            } else {
                getUsers();
            }
            break;
            
        case 'POST':
            createUser();
            break;
            
        case 'PUT':
            if (!isset($_GET['id'])) {
                Response::error('User ID is required', 400);
            }
            updateUser($_GET['id']);
            break;
            
        case 'DELETE':
            if (!isset($_GET['id'])) {
                Response::error('User ID is required', 400);
            }
            deleteUser($_GET['id']);
            break;
            
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    Response::error($e->getMessage(), 500);
}

/**
 * Get all users with optional filtering
 */
function getUsers() {
    global $pdo;
    
    $query = "SELECT id, username, full_name, first_name, last_name, email, 
                     employee_id, role, is_active, created_at, updated_at 
              FROM users WHERE 1=1";
    $params = [];
    
    // Apply filters
    if (isset($_GET['role']) && !empty($_GET['role'])) {
        $query .= " AND role = ?";
        $params[] = $_GET['role'];
    }
    
    if (isset($_GET['is_active']) && $_GET['is_active'] !== '') {
        $query .= " AND is_active = ?";
        $params[] = $_GET['is_active'];
    }
    
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search = '%' . $_GET['search'] . '%';
        $query .= " AND (full_name LIKE ? OR username LIKE ? OR email LIKE ?)";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
    }
    
    $query .= " ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    Response::success($users);
}

/**
 * Get single user by ID
 */
function getUser($id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT id, username, full_name, first_name, last_name, email, 
                                  employee_id, role, is_active, created_at, updated_at 
                           FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        Response::error('User not found', 404);
    }
    
    Response::success($user);
}

/**
 * Create new user
 */
function createUser() {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['username', 'role', 'password'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            Response::error("Field '$field' is required", 400);
        }
    }
    
    // Check if username already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$data['username']]);
    if ($stmt->fetch()) {
        Response::error('Username already exists', 400);
    }
    
    // Check if email already exists (if provided)
    if (!empty($data['email'])) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            Response::error('Email already exists', 400);
        }
    }
    
    // Hash password
    $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
    
    // Insert user
    $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, first_name, last_name, 
                                              email, employee_id, role, is_active, created_at) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    
    $stmt->execute([
        $data['username'],
        $hashedPassword,
        $data['full_name'] ?? null,
        $data['first_name'] ?? null,
        $data['last_name'] ?? null,
        $data['email'] ?? null,
        $data['employee_id'] ?? null,
        $data['role'],
        $data['is_active'] ?? 1
    ]);
    
    $userId = $pdo->lastInsertId();
    
    Response::success(['id' => $userId], 'User created successfully');
}

/**
 * Update existing user
 */
function updateUser($id) {
    global $pdo;
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existingUser) {
        Response::error('User not found', 404);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Check if username is being changed and already exists
    if (!empty($data['username']) && $data['username'] !== $existingUser['username']) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$data['username'], $id]);
        if ($stmt->fetch()) {
            Response::error('Username already exists', 400);
        }
    }
    
    // Check if email is being changed and already exists
    if (!empty($data['email']) && $data['email'] !== $existingUser['email']) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$data['email'], $id]);
        if ($stmt->fetch()) {
            Response::error('Email already exists', 400);
        }
    }
    
    // Build update query dynamically
    $updates = [];
    $params = [];
    
    $allowedFields = ['username', 'full_name', 'first_name', 'last_name', 'email', 
                      'employee_id', 'role', 'is_active'];
    
    foreach ($allowedFields as $field) {
        if (array_key_exists($field, $data)) {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
        }
    }
    
    // Handle password update
    if (!empty($data['password'])) {
        $updates[] = "password = ?";
        $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
    }
    
    if (empty($updates)) {
        Response::error('No fields to update', 400);
    }
    
    $updates[] = "updated_at = NOW()";
    $params[] = $id;
    
    $query = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    Response::success(null, 'User updated successfully');
}

/**
 * Delete user (soft delete by deactivating)
 */
function deleteUser($id) {
    global $pdo;
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        Response::error('User not found', 404);
    }
    
    // Soft delete by deactivating
    $stmt = $pdo->prepare("UPDATE users SET is_active = 0, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$id]);
    
    Response::success(null, 'User deactivated successfully');
}
