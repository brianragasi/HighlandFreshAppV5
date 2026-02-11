<?php
/**
 * Debug script for Production Staff access issues
 */

require_once 'api/bootstrap.php';

echo "<pre>";
$db = Database::getInstance()->getConnection();

echo "=== USERS AND ROLES ===\n";
$stmt = $db->query("SELECT id, username, role FROM users WHERE is_active=1");
foreach ($stmt->fetchAll() as $row) {
    echo $row['id'] . " | " . $row['username'] . " | " . $row['role'] . "\n";
}

echo "\n=== TEST TOKEN GENERATION ===\n";
// Get production_staff user
$user = $db->query("SELECT * FROM users WHERE username='production_staff'")->fetch();
if ($user) {
    $token = Auth::generateToken($user);
    echo "Token generated for production_staff\n";
    
    // Decode token
    $parts = explode('.', $token);
    $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
    echo "Token payload:\n";
    print_r($payload);
}

echo "\n=== INGREDIENTS TABLE ===\n";
try {
    $count = $db->query("SELECT COUNT(*) FROM ingredients")->fetchColumn();
    echo "Total ingredients: $count\n";
    
    // List a few
    $ingredients = $db->query("SELECT id, code, name, unit FROM ingredients LIMIT 10");
    foreach ($ingredients->fetchAll() as $ing) {
        echo "  - [{$ing['id']}] {$ing['code']}: {$ing['name']} ({$ing['unit']})\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== AUTH ROLE CHECK SIMULATION ===\n";
$testRoles = ['production_staff', 'general_manager', 'warehouse_raw'];
$userRole = $payload['role'] ?? 'unknown';

foreach (['production_staff', 'general_manager', 'qc_officer'] as $allowed) {
    $allowed_array = [$allowed];
    if (in_array($userRole, $allowed_array)) {
        echo "Role '$userRole' IS allowed in [$allowed]\n";
    }
}

$allowedRoles = ['production_staff', 'general_manager', 'qc_officer'];
if (in_array($userRole, $allowedRoles)) {
    echo "✓ User role '$userRole' IS in the allowed list for production/runs.php\n";
} else {
    echo "✗ User role '$userRole' is NOT in the allowed list\n";
}

echo "</pre>";
