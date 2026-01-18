<?php
/**
 * Highland Fresh System - Warehouse FG API Integration Test
 * 
 * Comprehensive API-level integration test for all Warehouse FG functionalities
 * Tests actual API endpoints via HTTP calls to verify production readiness
 * 
 * @version 4.0
 */

// Configuration
$baseUrl = 'http://localhost/HighlandFreshAppV4/api';
$authToken = null;

// Test results tracking
$testResults = [];
$passed = 0;
$failed = 0;
$currentSection = '';

function test($name, $condition, $details = '') {
    global $testResults, $passed, $failed, $currentSection;
    
    $status = $condition ? 'PASS' : 'FAIL';
    if ($condition) {
        $passed++;
    } else {
        $failed++;
    }
    
    $testResults[] = [
        'section' => $currentSection,
        'name' => $name,
        'status' => $status,
        'details' => $details
    ];
    
    $icon = $condition ? '✅' : '❌';
    echo "$icon [$status] $name" . ($details && !$condition ? " - $details" : "") . "\n";
}

function section($name) {
    global $currentSection;
    $currentSection = $name;
    echo "\n" . str_repeat('=', 70) . "\n";
    echo "📦 $name\n";
    echo str_repeat('=', 70) . "\n";
}

/**
 * Make HTTP request to API
 */
function apiRequest($method, $endpoint, $data = null, $token = null) {
    global $baseUrl;
    
    $url = $baseUrl . $endpoint;
    
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => array_filter([
            'Content-Type: application/json',
            $token ? "Authorization: Bearer $token" : null
        ])
    ]);
    
    if ($data && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'body' => json_decode($response, true),
        'raw' => $response,
        'error' => $error
    ];
}

/**
 * Login to get auth token
 */
function login($username, $password) {
    $response = apiRequest('POST', '/auth/login.php', [
        'username' => $username,
        'password' => $password
    ]);
    
    if ($response['code'] == 200 && isset($response['body']['data']['token'])) {
        return $response['body']['data']['token'];
    }
    return null;
}

echo "\n🚀 Highland Fresh - Warehouse FG API Integration Test\n";
echo str_repeat('=', 70) . "\n";

// ============================================================================
// SECTION 1: AUTHENTICATION
// ============================================================================
section("1. Authentication");

// Login as warehouse_fg user
$authToken = login('warehouse_fg', 'password');
test("Login as warehouse_fg user", $authToken !== null, "Token: " . ($authToken ? substr($authToken, 0, 20) . '...' : 'FAILED'));

if (!$authToken) {
    // Try GM login
    $authToken = login('gm', 'password');
    test("Fallback login as GM", $authToken !== null);
}

if (!$authToken) {
    die("\n❌ FATAL: Cannot authenticate. Please verify users exist in database.\n");
}

// Verify token works
$meResponse = apiRequest('GET', '/auth/me.php', null, $authToken);
test("Token validation (GET /auth/me)", $meResponse['code'] == 200);

// ============================================================================
// SECTION 2: DASHBOARD API
// ============================================================================
section("2. Dashboard API");

$dashResponse = apiRequest('GET', '/warehouse/fg/dashboard.php', null, $authToken);
test("Dashboard API returns 200", $dashResponse['code'] == 200, "Code: {$dashResponse['code']}");
test("Dashboard has inventory stats", isset($dashResponse['body']['data']['inventory']));
test("Dashboard has chillers stats", isset($dashResponse['body']['data']['chillers']));
test("Dashboard has expiring stats", isset($dashResponse['body']['data']['expiring']));
test("Dashboard has receiving stats", isset($dashResponse['body']['data']['receiving']));
test("Dashboard has delivery stats", isset($dashResponse['body']['data']['delivery']));

if (isset($dashResponse['body']['data'])) {
    $dash = $dashResponse['body']['data'];
    echo "\n  📊 Dashboard Stats:\n";
    echo "    - Total FG Units: " . ($dash['inventory']['total_units'] ?? 0) . "\n";
    echo "    - Products: " . ($dash['inventory']['product_count'] ?? 0) . "\n";
    echo "    - Chillers: " . ($dash['chillers']['total'] ?? 0) . "\n";
    echo "    - Pending Receiving: " . ($dash['receiving']['pending'] ?? 0) . "\n";
}

// ============================================================================
// SECTION 3: CHILLERS API
// ============================================================================
section("3. Chillers API");

// List chillers
$chillersResponse = apiRequest('GET', '/warehouse/fg/chillers.php?action=list', null, $authToken);
test("Chillers list API returns 200", $chillersResponse['code'] == 200);
test("Chillers list returns array", isset($chillersResponse['body']['data']) && is_array($chillersResponse['body']['data']));

$chillerCount = count($chillersResponse['body']['data'] ?? []);
test("At least one chiller exists", $chillerCount > 0, "Count: $chillerCount");

if ($chillerCount > 0) {
    $firstChiller = $chillersResponse['body']['data'][0];
    test("Chiller has chiller_code field", isset($firstChiller['chiller_code']));
    test("Chiller has chiller_name field", isset($firstChiller['chiller_name']));
    test("Chiller has capacity field", isset($firstChiller['capacity']));
    test("Chiller has current_count field", isset($firstChiller['current_count']));
    test("Chiller has temperature_celsius field", isset($firstChiller['temperature_celsius']));
    
    // Get chiller detail
    $chillerDetailResponse = apiRequest('GET', "/warehouse/fg/chillers.php?action=detail&id={$firstChiller['id']}", null, $authToken);
    test("Chiller detail API returns 200", $chillerDetailResponse['code'] == 200);
    
    echo "\n  🧊 Chillers Summary:\n";
    foreach ($chillersResponse['body']['data'] as $c) {
        $usage = $c['capacity'] > 0 ? round(($c['current_count'] / $c['capacity']) * 100, 1) : 0;
        echo "    - {$c['chiller_code']}: {$c['chiller_name']} ({$c['current_count']}/{$c['capacity']}) {$usage}%\n";
    }
}

// Chiller summary
$chillerSummaryResponse = apiRequest('GET', '/warehouse/fg/chillers.php?action=summary', null, $authToken);
test("Chiller summary API returns 200", $chillerSummaryResponse['code'] == 200);

// ============================================================================
// SECTION 4: INVENTORY API
// ============================================================================
section("4. Inventory API");

// List inventory
$inventoryResponse = apiRequest('GET', '/warehouse/fg/inventory.php?action=list', null, $authToken);
test("Inventory list API returns 200", $inventoryResponse['code'] == 200, "Code: {$inventoryResponse['code']} - " . ($inventoryResponse['body']['message'] ?? ''));
test("Inventory list returns array", isset($inventoryResponse['body']['data']) && is_array($inventoryResponse['body']['data']));

$invCount = count($inventoryResponse['body']['data'] ?? []);
echo "\n  📦 Inventory Count: $invCount items\n";

if ($invCount > 0) {
    $firstInv = $inventoryResponse['body']['data'][0];
    test("Inventory item has product_name", isset($firstInv['product_name']));
    test("Inventory item has quantity_available", isset($firstInv['quantity_available']));
    test("Inventory item has expiry_date", isset($firstInv['expiry_date']));
    test("Inventory item has chiller_id", isset($firstInv['chiller_id']));
    
    // Detail
    $invDetailResponse = apiRequest('GET', "/warehouse/fg/inventory.php?action=detail&id={$firstInv['id']}", null, $authToken);
    test("Inventory detail API returns 200", $invDetailResponse['code'] == 200);
}

// Available inventory
$availableResponse = apiRequest('GET', '/warehouse/fg/inventory.php?action=available', null, $authToken);
test("Available inventory API returns 200", $availableResponse['code'] == 200);

// Expiring items
$expiringResponse = apiRequest('GET', '/warehouse/fg/inventory.php?action=expiring&days=7', null, $authToken);
test("Expiring inventory API returns 200", $expiringResponse['code'] == 200);

// Inventory summary
$summaryResponse = apiRequest('GET', '/warehouse/fg/inventory.php?action=summary', null, $authToken);
test("Inventory summary API returns 200", $summaryResponse['code'] == 200);

// ============================================================================
// SECTION 5: PENDING BATCHES & TRANSACTIONS
// ============================================================================
section("5. Pending Batches & Transactions");

// Pending batches from production
$pendingResponse = apiRequest('GET', '/warehouse/fg/inventory.php?action=pending_batches', null, $authToken);
test("Pending batches API returns 200", $pendingResponse['code'] == 200, "Code: {$pendingResponse['code']} - " . ($pendingResponse['body']['message'] ?? ''));
test("Pending batches has batches array", isset($pendingResponse['body']['data']['batches']));
test("Pending batches has count", isset($pendingResponse['body']['data']['count']));

$pendingCount = $pendingResponse['body']['data']['count'] ?? 0;
echo "\n  📋 Pending batches from production: $pendingCount\n";

// Transaction history
$transResponse = apiRequest('GET', '/warehouse/fg/inventory.php?action=transactions&limit=20', null, $authToken);
test("Transactions API returns 200", $transResponse['code'] == 200, "Code: {$transResponse['code']} - " . ($transResponse['body']['message'] ?? ''));
test("Transactions has transactions array", isset($transResponse['body']['data']['transactions']));

$txCount = count($transResponse['body']['data']['transactions'] ?? []);
echo "  📜 Recent transactions: $txCount\n";

// Filter transactions by type
$receiveTransResponse = apiRequest('GET', '/warehouse/fg/inventory.php?action=transactions&type=receive&limit=10', null, $authToken);
test("Filtered transactions API returns 200", $receiveTransResponse['code'] == 200);

// ============================================================================
// SECTION 6: DELIVERY RECEIPTS API
// ============================================================================
section("6. Delivery Receipts API");

// List DRs
$drResponse = apiRequest('GET', '/warehouse/fg/delivery_receipts.php?action=list', null, $authToken);
test("Delivery receipts list API returns 200", $drResponse['code'] == 200);

$drData = $drResponse['body']['data'] ?? [];
$drList = $drData['delivery_receipts'] ?? $drData;
$drCount = is_array($drList) ? count($drList) : 0;
echo "\n  🚚 Delivery Receipts: $drCount\n";

if ($drCount > 0 && is_array($drList)) {
    $firstDr = $drList[0];
    test("DR has dr_number field", isset($firstDr['dr_number']));
    test("DR has status field", isset($firstDr['status']));
    test("DR has customer info", isset($firstDr['customer_name']) || isset($firstDr['customer_id']));
    
    // DR detail
    $drDetailResponse = apiRequest('GET', "/warehouse/fg/delivery_receipts.php?action=detail&id={$firstDr['id']}", null, $authToken);
    test("DR detail API returns 200", $drDetailResponse['code'] == 200);
}

// Pending DRs
$pendingDrResponse = apiRequest('GET', '/warehouse/fg/delivery_receipts.php?action=pending', null, $authToken);
test("Pending DRs API returns 200 or valid response", in_array($pendingDrResponse['code'], [200, 400]));

// ============================================================================
// SECTION 7: CUSTOMERS API
// ============================================================================
section("7. Customers API");

$customersResponse = apiRequest('GET', '/warehouse/fg/customers.php?action=list', null, $authToken);
test("Customers list API returns 200", $customersResponse['code'] == 200);

$custData = $customersResponse['body']['data'] ?? [];
$custCount = is_array($custData) ? count($custData) : 0;
test("Customers exist in system", $custCount > 0, "Count: $custCount");

if ($custCount > 0) {
    $firstCust = $custData[0];
    test("Customer has name field", isset($firstCust['customer_name']) || isset($firstCust['name']));
    test("Customer has customer_type field", isset($firstCust['customer_type']) || isset($firstCust['type']));
}

echo "\n  👥 Customers: $custCount\n";

// ============================================================================
// SECTION 8: DISPATCH API
// ============================================================================
section("8. Dispatch API");

$dispatchResponse = apiRequest('GET', '/warehouse/fg/dispatch.php?action=available_inventory', null, $authToken);
test("Dispatch available inventory API responds", in_array($dispatchResponse['code'], [200, 400]));

// Pending dispatches
$pendingDispatchResponse = apiRequest('GET', '/warehouse/fg/dispatch.php?action=pending', null, $authToken);
test("Pending dispatch API responds", in_array($pendingDispatchResponse['code'], [200, 400]));

// ============================================================================
// SECTION 9: UNIT CONVERSION
// ============================================================================
section("9. Multi-Unit Support");

// Test conversion to boxes (pieces -> boxes)
$convertResponse = apiRequest('GET', '/warehouse/fg/inventory.php?action=convert&product_id=1&pieces=100&direction=to_boxes', null, $authToken);
test("Unit conversion API responds", in_array($convertResponse['code'], [200, 400]));

if ($convertResponse['code'] == 200 && isset($convertResponse['body']['data'])) {
    $conv = $convertResponse['body']['data'];
    test("Conversion returns boxes", isset($conv['boxes']));
    test("Conversion returns pieces", isset($conv['pieces']));
    $boxes = $conv['boxes'] ?? 0;
    $pieces = $conv['pieces'] ?? 0;
    echo "\n  📐 100 pieces = {$boxes} boxes + {$pieces} pieces\n";
} else {
    // API didn't return expected format - mark as skipped
    test("Conversion returns boxes", false, "API response: " . ($convertResponse['body']['message'] ?? 'unknown'));
    test("Conversion returns pieces", false);
}

// ============================================================================
// SECTION 10: ERROR HANDLING
// ============================================================================
section("10. Error Handling");

// Invalid action
$invalidResponse = apiRequest('GET', '/warehouse/fg/inventory.php?action=invalid_action', null, $authToken);
test("Invalid action returns 400", $invalidResponse['code'] == 400);

// Missing required parameter
$missingParamResponse = apiRequest('GET', '/warehouse/fg/inventory.php?action=detail', null, $authToken);
test("Missing required param returns 400", $missingParamResponse['code'] == 400);

// Non-existent record
$notFoundResponse = apiRequest('GET', '/warehouse/fg/inventory.php?action=detail&id=999999', null, $authToken);
test("Non-existent record returns 404", $notFoundResponse['code'] == 404);

// ============================================================================
// SECTION 11: DATA INTEGRITY CHECKS (Direct DB)
// ============================================================================
section("11. Data Integrity (Direct DB)");

// Connect directly to DB for integrity checks
try {
    $db = new PDO(
        "mysql:host=localhost;dbname=highland_fresh;charset=utf8mb4",
        "root",
        "",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    
    // Check chiller counts match inventory
    $chillerCheck = $db->query("
        SELECT c.id, c.chiller_code, c.current_count,
               COALESCE(SUM(fg.quantity_available), 0) as calc_count
        FROM chiller_locations c
        LEFT JOIN finished_goods_inventory fg ON c.id = fg.chiller_id AND fg.status = 'available'
        WHERE c.is_active = 1
        GROUP BY c.id
        HAVING ABS(c.current_count - calc_count) > 0
    ")->fetchAll();
    test("Chiller counts match inventory totals", count($chillerCheck) == 0, count($chillerCheck) . " mismatches");
    
    // Check no negative inventory
    $negativeInv = $db->query("SELECT COUNT(*) FROM finished_goods_inventory WHERE quantity_available < 0")->fetchColumn();
    test("No negative inventory quantities", $negativeInv == 0, "$negativeInv negative records");
    
    // Check all FG items have product_id
    $noProductId = $db->query("SELECT COUNT(*) FROM finished_goods_inventory WHERE product_id IS NULL")->fetchColumn();
    test("All inventory items have product_id", $noProductId == 0, "$noProductId without product_id");
    
    // Check all available inventory has chiller assignment
    $noChiller = $db->query("
        SELECT COUNT(*) FROM finished_goods_inventory 
        WHERE status = 'available' AND quantity_available > 0 AND chiller_id IS NULL
    ")->fetchColumn();
    test("All available inventory assigned to chillers", $noChiller == 0, "$noChiller unassigned");
    
    // Check production_batches qc_status values
    $invalidStatus = $db->query("
        SELECT COUNT(*) FROM production_batches 
        WHERE qc_status NOT IN ('pending', 'released', 'rejected', 'on_hold')
    ")->fetchColumn();
    test("All production batches have valid qc_status", $invalidStatus == 0, "$invalidStatus invalid");
    
} catch (Exception $e) {
    test("Database integrity check", false, $e->getMessage());
}

// ============================================================================
// FINAL SUMMARY
// ============================================================================
echo "\n" . str_repeat('=', 70) . "\n";
echo "📊 TEST SUMMARY\n";
echo str_repeat('=', 70) . "\n";

$total = $passed + $failed;
$percentage = $total > 0 ? round(($passed / $total) * 100, 1) : 0;

echo "\n✅ Passed: $passed\n";
echo "❌ Failed: $failed\n";
echo "📈 Total:  $total\n";
echo "📊 Score:  $percentage%\n\n";

if ($failed > 0) {
    echo "❌ FAILED TESTS:\n";
    foreach ($testResults as $result) {
        if ($result['status'] === 'FAIL') {
            echo "  - [{$result['section']}] {$result['name']}";
            if ($result['details']) echo " - {$result['details']}";
            echo "\n";
        }
    }
}

echo "\n" . str_repeat('=', 70) . "\n";
if ($percentage == 100) {
    echo "🎉 ALL TESTS PASSED! Warehouse FG API is production ready.\n";
} elseif ($percentage >= 90) {
    echo "✨ EXCELLENT! Minor issues to address.\n";
} elseif ($percentage >= 70) {
    echo "⚠️ GOOD, but some issues need attention.\n";
} else {
    echo "🔧 NEEDS WORK - Multiple issues detected.\n";
}
echo str_repeat('=', 70) . "\n";
