<?php
/**
 * QC Standards Management API
 * Handles CRUD operations for grading standards, test parameters, and CCP standards
 */

require_once __DIR__ . '/../bootstrap.php';

// Get database connection
$pdo = Database::getInstance()->getConnection();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$type = $_GET['type'] ?? 'parameters'; // parameters, grading, ccp

try {
    switch ($type) {
        case 'grading':
            handleGrading($method);
            break;
        case 'ccp':
            handleCcp($method);
            break;
        default:
            handleParameters($method);
    }
} catch (Exception $e) {
    Response::error($e->getMessage(), 500);
}

// ==================== GRADING STANDARDS ====================

function handleGrading($method) {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                getGradingStandard($_GET['id']);
            } else {
                getGradingStandards();
            }
            break;
        case 'POST':
            createGradingStandard();
            break;
        case 'PUT':
            if (!isset($_GET['id'])) {
                Response::error('ID required', 400);
            }
            updateGradingStandard($_GET['id']);
            break;
        case 'DELETE':
            if (!isset($_GET['id'])) {
                Response::error('ID required', 400);
            }
            deleteGradingStandard($_GET['id']);
            break;
        default:
            Response::error('Method not allowed', 405);
    }
}

function getGradingStandards() {
    global $pdo;
    
    $sql = "SELECT * FROM milk_grading_standards ORDER BY grade_name";
    $stmt = $pdo->query($sql);
    $standards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    Response::success($standards);
}

function getGradingStandard($id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM milk_grading_standards WHERE id = ?");
    $stmt->execute([$id]);
    $standard = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$standard) {
        Response::error('Grading standard not found', 404);
    }
    
    Response::success($standard);
}

function createGradingStandard() {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['grade_name']) || !isset($data['price_per_liter'])) {
        Response::error('Grade name and price per liter are required', 400);
    }
    
    // Check for duplicate
    $checkStmt = $pdo->prepare("SELECT id FROM milk_grading_standards WHERE grade_name = ?");
    $checkStmt->execute([$data['grade_name']]);
    if ($checkStmt->fetch()) {
        Response::error('Grade name already exists', 400);
    }
    
    $sql = "INSERT INTO milk_grading_standards (grade_name, description, fat_min, fat_max, snf_min, snf_max, 
            temperature_max, price_per_liter, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $data['grade_name'],
        $data['description'] ?? null,
        $data['fat_min'] ?? null,
        $data['fat_max'] ?? null,
        $data['snf_min'] ?? null,
        $data['snf_max'] ?? null,
        $data['temperature_max'] ?? null,
        $data['price_per_liter'],
        $data['status'] ?? 'active'
    ]);
    
    Response::success(['message' => 'Grading standard created', 'id' => $pdo->lastInsertId()], 201);
}

function updateGradingStandard($id) {
    global $pdo;
    
    $checkStmt = $pdo->prepare("SELECT id FROM milk_grading_standards WHERE id = ?");
    $checkStmt->execute([$id]);
    if (!$checkStmt->fetch()) {
        Response::error('Grading standard not found', 404);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Check for duplicate name (excluding current)
    if (!empty($data['grade_name'])) {
        $dupStmt = $pdo->prepare("SELECT id FROM milk_grading_standards WHERE grade_name = ? AND id != ?");
        $dupStmt->execute([$data['grade_name'], $id]);
        if ($dupStmt->fetch()) {
            Response::error('Grade name already exists', 400);
        }
    }
    
    $sql = "UPDATE milk_grading_standards SET 
            grade_name = COALESCE(?, grade_name),
            description = ?,
            fat_min = ?,
            fat_max = ?,
            snf_min = ?,
            snf_max = ?,
            temperature_max = ?,
            price_per_liter = COALESCE(?, price_per_liter),
            status = COALESCE(?, status),
            updated_at = NOW()
            WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $data['grade_name'] ?? null,
        $data['description'] ?? null,
        $data['fat_min'] ?? null,
        $data['fat_max'] ?? null,
        $data['snf_min'] ?? null,
        $data['snf_max'] ?? null,
        $data['temperature_max'] ?? null,
        $data['price_per_liter'] ?? null,
        $data['status'] ?? null,
        $id
    ]);
    
    Response::success(['message' => 'Grading standard updated']);
}

function deleteGradingStandard($id) {
    global $pdo;
    
    // Check if used in any milk deliveries
    $usageStmt = $pdo->prepare("SELECT COUNT(*) as count FROM daily_raw_milk_inventory WHERE milk_grade_id = ?");
    $usageStmt->execute([$id]);
    $usage = $usageStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($usage['count'] > 0) {
        // Soft delete
        $stmt = $pdo->prepare("UPDATE milk_grading_standards SET status = 'inactive', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        Response::success(['message' => 'Grading standard deactivated (has usage history)']);
    } else {
        $stmt = $pdo->prepare("DELETE FROM milk_grading_standards WHERE id = ?");
        $stmt->execute([$id]);
        Response::success(['message' => 'Grading standard deleted']);
    }
}

// ==================== TEST PARAMETERS ====================

function handleParameters($method) {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                getParameter($_GET['id']);
            } else {
                getParameters();
            }
            break;
        case 'POST':
            createParameter();
            break;
        case 'PUT':
            if (!isset($_GET['id'])) {
                Response::error('ID required', 400);
            }
            updateParameter($_GET['id']);
            break;
        case 'DELETE':
            if (!isset($_GET['id'])) {
                Response::error('ID required', 400);
            }
            deleteParameter($_GET['id']);
            break;
        default:
            Response::error('Method not allowed', 405);
    }
}

function getParameters() {
    global $pdo;
    
    $where = ['1=1'];
    $params = [];
    
    if (!empty($_GET['category'])) {
        $where[] = 'category = ?';
        $params[] = $_GET['category'];
    }
    
    $whereClause = implode(' AND ', $where);
    
    $sql = "SELECT * FROM qc_test_parameters WHERE $whereClause ORDER BY category, parameter_name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $parameters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    Response::success($parameters);
}

function getParameter($id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM qc_test_parameters WHERE id = ?");
    $stmt->execute([$id]);
    $parameter = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$parameter) {
        Response::error('Parameter not found', 404);
    }
    
    Response::success($parameter);
}

function createParameter() {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['parameter_name'])) {
        Response::error('Parameter name is required', 400);
    }
    
    $sql = "INSERT INTO qc_test_parameters (parameter_name, category, unit, min_value, max_value, 
            target_value, test_method, description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $data['parameter_name'],
        $data['category'] ?? 'raw_milk',
        $data['unit'] ?? null,
        $data['min_value'] ?? null,
        $data['max_value'] ?? null,
        $data['target_value'] ?? null,
        $data['test_method'] ?? null,
        $data['description'] ?? null
    ]);
    
    Response::success(['message' => 'Parameter created', 'id' => $pdo->lastInsertId()], 201);
}

function updateParameter($id) {
    global $pdo;
    
    $checkStmt = $pdo->prepare("SELECT id FROM qc_test_parameters WHERE id = ?");
    $checkStmt->execute([$id]);
    if (!$checkStmt->fetch()) {
        Response::error('Parameter not found', 404);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $sql = "UPDATE qc_test_parameters SET 
            parameter_name = COALESCE(?, parameter_name),
            category = COALESCE(?, category),
            unit = ?,
            min_value = ?,
            max_value = ?,
            target_value = ?,
            test_method = ?,
            description = ?,
            updated_at = NOW()
            WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $data['parameter_name'] ?? null,
        $data['category'] ?? null,
        $data['unit'] ?? null,
        $data['min_value'] ?? null,
        $data['max_value'] ?? null,
        $data['target_value'] ?? null,
        $data['test_method'] ?? null,
        $data['description'] ?? null,
        $id
    ]);
    
    Response::success(['message' => 'Parameter updated']);
}

function deleteParameter($id) {
    global $pdo;
    
    $stmt = $pdo->prepare("DELETE FROM qc_test_parameters WHERE id = ?");
    $stmt->execute([$id]);
    
    Response::success(['message' => 'Parameter deleted']);
}

// ==================== CCP STANDARDS ====================

function handleCcp($method) {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                getCcpStandard($_GET['id']);
            } else {
                getCcpStandards();
            }
            break;
        case 'POST':
            createCcpStandard();
            break;
        case 'PUT':
            if (!isset($_GET['id'])) {
                Response::error('ID required', 400);
            }
            updateCcpStandard($_GET['id']);
            break;
        case 'DELETE':
            if (!isset($_GET['id'])) {
                Response::error('ID required', 400);
            }
            deleteCcpStandard($_GET['id']);
            break;
        default:
            Response::error('Method not allowed', 405);
    }
}

function getCcpStandards() {
    global $pdo;
    
    $where = ['1=1'];
    $params = [];
    
    if (!empty($_GET['category'])) {
        $where[] = 'category = ?';
        $params[] = $_GET['category'];
    }
    
    if (!empty($_GET['status'])) {
        $where[] = 'status = ?';
        $params[] = $_GET['status'];
    }
    
    $whereClause = implode(' AND ', $where);
    
    $sql = "SELECT * FROM ccp_standards WHERE $whereClause ORDER BY category, ccp_name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $standards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    Response::success($standards);
}

function getCcpStandard($id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM ccp_standards WHERE id = ?");
    $stmt->execute([$id]);
    $standard = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$standard) {
        Response::error('CCP standard not found', 404);
    }
    
    Response::success($standard);
}

function createCcpStandard() {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['ccp_name']) || empty($data['critical_limit'])) {
        Response::error('CCP name and critical limit are required', 400);
    }
    
    $sql = "INSERT INTO ccp_standards (ccp_name, category, critical_limit, target_value, 
            monitoring_frequency, corrective_action, hazard_description, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $data['ccp_name'],
        $data['category'] ?? 'pasteurization',
        $data['critical_limit'],
        $data['target_value'] ?? null,
        $data['monitoring_frequency'] ?? null,
        $data['corrective_action'] ?? null,
        $data['hazard_description'] ?? null,
        $data['status'] ?? 'active'
    ]);
    
    Response::success(['message' => 'CCP standard created', 'id' => $pdo->lastInsertId()], 201);
}

function updateCcpStandard($id) {
    global $pdo;
    
    $checkStmt = $pdo->prepare("SELECT id FROM ccp_standards WHERE id = ?");
    $checkStmt->execute([$id]);
    if (!$checkStmt->fetch()) {
        Response::error('CCP standard not found', 404);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $sql = "UPDATE ccp_standards SET 
            ccp_name = COALESCE(?, ccp_name),
            category = COALESCE(?, category),
            critical_limit = COALESCE(?, critical_limit),
            target_value = ?,
            monitoring_frequency = ?,
            corrective_action = ?,
            hazard_description = ?,
            status = COALESCE(?, status),
            updated_at = NOW()
            WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $data['ccp_name'] ?? null,
        $data['category'] ?? null,
        $data['critical_limit'] ?? null,
        $data['target_value'] ?? null,
        $data['monitoring_frequency'] ?? null,
        $data['corrective_action'] ?? null,
        $data['hazard_description'] ?? null,
        $data['status'] ?? null,
        $id
    ]);
    
    Response::success(['message' => 'CCP standard updated']);
}

function deleteCcpStandard($id) {
    global $pdo;
    
    // Check if used in CCP logs
    $usageStmt = $pdo->prepare("SELECT COUNT(*) as count FROM ccp_logs WHERE ccp_id = ?");
    $usageStmt->execute([$id]);
    $usage = $usageStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($usage['count'] > 0) {
        // Soft delete
        $stmt = $pdo->prepare("UPDATE ccp_standards SET status = 'inactive', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        Response::success(['message' => 'CCP standard deactivated (has log history)']);
    } else {
        $stmt = $pdo->prepare("DELETE FROM ccp_standards WHERE id = ?");
        $stmt->execute([$id]);
        Response::success(['message' => 'CCP standard deleted']);
    }
}
