<?php
/**
 * Confirms that retired user roles cannot return accidentally.
 *
 * Run:
 *   php tests/retired_roles_test.php
 */

define('HIGHLAND_FRESH', true);
require_once __DIR__ . '/../api/config/config.php';
require_once __DIR__ . '/../api/config/database.php';

$root = dirname(__DIR__);
$failures = [];
$checks = 0;

function checkRemoval($condition, $message) {
    global $checks, $failures;
    $checks++;
    if ($condition) {
        echo "PASS: {$message}\n";
        return;
    }
    $failures[] = $message;
    echo "FAIL: {$message}\n";
}

$retiredRoles = ['maintenance_head', 'bookkeeper'];
$runtimeFiles = [
    'api/admin/users.php',
    'api/admin/dashboard.php',
    'api/auth/invite.php',
    'api/purchasing/purchase_orders.php',
    'api/purchasing/purchase_requests.php',
    'api/warehouse/raw/mro.php',
    'api/warehouse/raw/requisitions.php',
    'html/admin/users.html',
    'html/login.html',
    'html/change-password.html',
    'html/set-password.html',
    'js/config/auth.js',
];

foreach ($runtimeFiles as $relativePath) {
    $contents = file_get_contents($root . '/' . $relativePath);
    foreach ($retiredRoles as $retiredRole) {
        checkRemoval(
            stripos($contents, $retiredRole) === false,
            "{$retiredRole} is absent from {$relativePath}"
        );
    }
}

$equipmentPermissionFiles = [
    'api/maintenance/dashboard.php',
    'api/maintenance/machines.php',
    'api/maintenance/repairs.php',
];
foreach ($equipmentPermissionFiles as $relativePath) {
    $contents = file_get_contents($root . '/' . $relativePath);
    checkRemoval(
        strpos($contents, "Auth::requireRole(['warehouse_raw', 'general_manager'])") !== false,
        "Warehouse Raw and GM retain equipment access in {$relativePath}"
    );
}

$allowedRoles = [
    'general_manager',
    'qc_officer',
    'production_staff',
    'warehouse_raw',
    'warehouse_fg',
    'sales_custodian',
    'cashier',
    'purchaser',
    'finance_officer',
];

try {
    $db = Database::getInstance()->getConnection();
    $roleColumn = $db->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch(PDO::FETCH_ASSOC);
    foreach ($retiredRoles as $retiredRole) {
        checkRemoval(
            $roleColumn && stripos((string) $roleColumn['Type'], $retiredRole) === false,
            "Database no longer accepts {$retiredRole}"
        );

        $remaining = $db->prepare('SELECT COUNT(*) FROM users WHERE role = ?');
        $remaining->execute([$retiredRole]);
        checkRemoval((int) $remaining->fetchColumn() === 0, "No user still has {$retiredRole}");
    }

    $activeRoles = $db->query("SELECT DISTINCT role FROM users WHERE is_active = 1 ORDER BY role")
        ->fetchAll(PDO::FETCH_COLUMN);
    checkRemoval(
        count(array_diff($activeRoles, $allowedRoles)) === 0,
        'Every active account still has a supported role'
    );

    $admin = $db->query("SELECT role, is_active FROM users WHERE username = 'admin' LIMIT 1")
        ->fetch(PDO::FETCH_ASSOC);
    checkRemoval(
        $admin && $admin['role'] === 'general_manager' && (int) $admin['is_active'] === 1,
        'Main admin account remains active as General Manager'
    );

    $retired = $db->query("SELECT id, role, is_active FROM users WHERE username LIKE 'retired_equipment_account_%' LIMIT 1")
        ->fetch(PDO::FETCH_ASSOC);
    checkRemoval(
        $retired && $retired['role'] === 'warehouse_raw' && (int) $retired['is_active'] === 0,
        'Old maintenance account is preserved but cannot sign in'
    );

    $historyCount = 0;
    if ($retired) {
        $history = $db->prepare('SELECT COUNT(*) FROM maintenance_requisitions WHERE requested_by = ?');
        $history->execute([(int) $retired['id']]);
        $historyCount = (int) $history->fetchColumn();
    }
    checkRemoval($historyCount > 0, 'Historical requisition still points to the preserved user record');
} catch (Throwable $e) {
    $failures[] = 'Database checks could not run: ' . $e->getMessage();
    echo 'FAIL: Database checks could not run: ' . $e->getMessage() . "\n";
}

echo "\n{$checks} checks, " . count($failures) . " failure(s).\n";
exit($failures ? 1 : 0);
