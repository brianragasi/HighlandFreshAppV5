<?php
/**
 * Debug specific QC endpoints
 * DELETE THIS FILE AFTER DEBUGGING
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

$results = [];

// Test bootstrap
try {
    define('HIGHLAND_FRESH', true);
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../config/response.php';
    require_once __DIR__ . '/../config/auth.php';
    $results['bootstrap'] = 'OK';
} catch (Exception $e) {
    $results['bootstrap'] = 'FAILED: ' . $e->getMessage();
}

// Check database connection
try {
    $db = Database::getInstance()->getConnection();
    $results['database'] = 'OK';
} catch (Exception $e) {
    $results['database'] = 'FAILED: ' . $e->getMessage();
}

// Check if Auth token exists in request
$results['auth_header'] = isset($_SERVER['HTTP_AUTHORIZATION']) ? 'Present' : 'Missing';
$results['token_in_storage'] = 'Check browser localStorage for highland_token';

// Test the specific queries from dashboard.php
try {
    $today = date('Y-m-d');
    
    // Test milk_receiving query
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM milk_receiving WHERE receiving_date = ?");
    $stmt->execute([$today]);
    $results['milk_receiving_query'] = 'OK - ' . $stmt->fetch()['count'] . ' records today';
} catch (Exception $e) {
    $results['milk_receiving_query'] = 'FAILED: ' . $e->getMessage();
}

// Test qc_milk_tests query
try {
    $weekStart = date('Y-m-d', strtotime('monday this week'));
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM qc_milk_tests WHERE DATE(test_datetime) >= ?");
    $stmt->execute([$weekStart]);
    $results['qc_milk_tests_query'] = 'OK - ' . $stmt->fetch()['count'] . ' records this week';
} catch (Exception $e) {
    $results['qc_milk_tests_query'] = 'FAILED: ' . $e->getMessage();
}

// Test production_batches query
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM production_batches WHERE qc_status = 'pending'");
    $results['production_batches_query'] = 'OK - ' . $stmt->fetch()['count'] . ' pending';
} catch (Exception $e) {
    $results['production_batches_query'] = 'FAILED: ' . $e->getMessage();
}

// Test finished_goods_inventory query
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM finished_goods_inventory WHERE status = 'available'");
    $results['finished_goods_query'] = 'OK - ' . $stmt->fetch()['count'] . ' available';
} catch (Exception $e) {
    $results['finished_goods_query'] = 'FAILED: ' . $e->getMessage();
}

// Check if Auth::requireRole would fail
try {
    // Simulate what requireRole does
    $token = null;
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $token = $matches[1];
    }
    $results['token_extracted'] = $token ? 'Yes (length: ' . strlen($token) . ')' : 'No';
    
    if ($token) {
        // Try to decode JWT
        $parts = explode('.', $token);
        if (count($parts) === 3) {
            $payload = json_decode(base64_decode($parts[1]), true);
            $results['token_payload'] = [
                'user_id' => $payload['user_id'] ?? 'missing',
                'role' => $payload['role'] ?? 'missing',
                'exp' => isset($payload['exp']) ? date('Y-m-d H:i:s', $payload['exp']) : 'missing'
            ];
        }
    }
} catch (Exception $e) {
    $results['token_check'] = 'Error: ' . $e->getMessage();
}

// Check PHP error log
$results['php_error_log'] = ini_get('error_log') ?: 'default';
$results['display_errors'] = ini_get('display_errors');

echo json_encode($results, JSON_PRETTY_PRINT);
