<?php
/**
 * Admin Dashboard API
 * Returns summary statistics for the admin panel
 */

require_once __DIR__ . '/../bootstrap.php';

// SECURITY: Restrict admin dashboard to GM/Admin roles
$currentUser = Auth::requireRole(['general_manager', 'admin']);

// Get database connection
$pdo = Database::getInstance()->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    Response::error('Method not allowed', 405);
}

try {
    $stats = [];
    
    // Users count
    $stmt = $pdo->query("SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active
                         FROM users");
    $stats['users'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Farmers count
    $stmt = $pdo->query("SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
                            SUM(CASE WHEN membership_type = 'member' THEN 1 ELSE 0 END) as members
                         FROM farmers");
    $stats['farmers'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Customers count
    $stmt = $pdo->query("SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                            SUM(CASE WHEN customer_type = 'wholesale' THEN 1 ELSE 0 END) as wholesale,
                            SUM(CASE WHEN customer_type = 'retail' THEN 1 ELSE 0 END) as retail,
                            SUM(CASE WHEN default_payment_type = 'credit' THEN 1 ELSE 0 END) as credit
                         FROM customers");
    $stats['customers'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Products count
    $stmt = $pdo->query("SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active
                         FROM products");
    $stats['products'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Recipes count  
    $stmt = $pdo->query("SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active
                         FROM master_recipes");
    $stats['recipes'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Ingredients count
    $stmt = $pdo->query("SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active
                         FROM ingredients");
    $stats['ingredients'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Storage tanks
    $stmt = $pdo->query("SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
                            SUM(capacity_liters) as total_capacity,
                            SUM(current_volume) as total_volume
                         FROM storage_tanks");
    $stats['tanks'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Chillers
    $stmt = $pdo->query("SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available
                         FROM chiller_locations");
    $stats['chillers'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // QC Standards (grading standards count)
    $stmt = $pdo->query("SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active
                         FROM milk_grading_standards");
    $stats['qc_standards'] = $stmt->fetch(PDO::FETCH_ASSOC);

    // Recent users (last 5) — kept for backward compatibility
    $stmt = $pdo->query("SELECT id, username, full_name, role, is_active, created_at 
                         FROM users ORDER BY created_at DESC LIMIT 5");
    $stats['recent_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent system activity (audit trail with actor names)
    $stats['recent_activity'] = fetchGmRecentActivity($pdo);
    
    // Pending approvals / action center (GM workflow)
    $stats['pending_actions'] = fetchGmPendingActions($pdo);
    $stats['pending_actions_count'] = count($stats['pending_actions']);
    
    // Role distribution
    $stmt = $pdo->query("SELECT role, COUNT(*) as count 
                         FROM users WHERE is_active = 1 
                         GROUP BY role ORDER BY count DESC");
    $stats['role_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stats['system_info'] = [
        'database' => defined('DB_NAME') ? DB_NAME : 'highland_fresh',
        'version' => 'v4.0.0',
        'last_schema_update' => '2026-07-10',
        'last_schema_update_label' => 'July 10, 2026',
        'api_status' => 'online',
    ];

      // Low stock alerts (ingredients + MRO) using reorder point
      $ingredientReorderExpr = auditColumnExists($pdo, 'ingredients', 'reorder_point')
         ? 'COALESCE(i.reorder_point, i.minimum_stock * 1.5)'
         : 'i.minimum_stock * 1.5';
      $mroReorderExpr = auditColumnExists($pdo, 'mro_items', 'reorder_point')
         ? 'COALESCE(m.reorder_point, m.minimum_stock * 1.5)'
         : 'm.minimum_stock * 1.5';

      $lowStockSql = "
         SELECT * FROM (
            SELECT
               'ingredient' as item_type,
               i.ingredient_code as item_code,
               i.ingredient_name as item_name,
               i.current_stock,
               i.minimum_stock,
               {$ingredientReorderExpr} as reorder_point,
               i.unit_of_measure,
               CASE
                  WHEN i.current_stock <= 0 THEN 'OUT_OF_STOCK'
                  WHEN i.current_stock <= {$ingredientReorderExpr} THEN 'LOW'
                  ELSE 'OK'
               END as stock_status,
               (i.current_stock / NULLIF({$ingredientReorderExpr}, 0)) as stock_ratio
            FROM ingredients i
            WHERE i.is_active = 1
              AND i.current_stock <= {$ingredientReorderExpr}

            UNION ALL

            SELECT
               'mro' as item_type,
               m.item_code as item_code,
               m.item_name as item_name,
               m.current_stock,
               m.minimum_stock,
               {$mroReorderExpr} as reorder_point,
               m.unit_of_measure,
               CASE
                  WHEN m.current_stock <= 0 THEN 'OUT_OF_STOCK'
                  WHEN m.current_stock <= {$mroReorderExpr} THEN 'LOW'
                  ELSE 'OK'
               END as stock_status,
               (m.current_stock / NULLIF({$mroReorderExpr}, 0)) as stock_ratio
            FROM mro_items m
            WHERE m.is_active = 1
              AND m.current_stock <= {$mroReorderExpr}
         ) as low_stock
         ORDER BY
            CASE stock_status
               WHEN 'OUT_OF_STOCK' THEN 1
               WHEN 'LOW' THEN 2
               ELSE 3
            END,
            stock_ratio ASC,
            item_name ASC
         LIMIT 15
      ";

      $lowStockStmt = $pdo->prepare($lowStockSql);
      $lowStockStmt->execute();
      $stats['low_stock_alerts'] = $lowStockStmt->fetchAll(PDO::FETCH_ASSOC);
      $stats['low_stock_count'] = count($stats['low_stock_alerts']);
    
    Response::success($stats);
    
} catch (Exception $e) {
    Response::error($e->getMessage(), 500);
}

/**
 * Build GM Action Center rows from live pending records.
 */
function fetchGmPendingActions(PDO $pdo): array {
    $actions = [];

    // Pending disposals awaiting signature
    try {
        $stmt = $pdo->query("
            SELECT id, disposal_code, product_name, total_value, disposal_reason, initiated_at, status
            FROM disposals
            WHERE status = 'pending'
            ORDER BY initiated_at ASC
            LIMIT 5
        ");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $code = $row['disposal_code'] ?: ('#' . $row['id']);
            $actions[] = [
                'id' => 'disp-' . $row['id'],
                'source_id' => (int)$row['id'],
                'type' => 'disposal',
                'category' => 'disposal',
                'priority' => 'high',
                'title' => 'Disposal Request ' . $code . ' — Pending Signature',
                'detail' => trim(($row['product_name'] ?? 'Inventory') . ' · ' . ($row['disposal_reason'] ?? 'Awaiting GM approval')),
                'meta' => $row['total_value'] !== null ? '₱' . number_format((float)$row['total_value'], 2) : null,
                'href' => 'gm_approvals.html',
                'requested_at' => $row['initiated_at'],
            ];
        }
    } catch (Exception $e) { /* optional table */ }

    // Pending purchase orders
    try {
        $stmt = $pdo->query("
            SELECT po.id, po.po_number, po.total_amount, po.created_at, s.supplier_name
            FROM purchase_orders po
            LEFT JOIN suppliers s ON s.id = po.supplier_id
            WHERE po.status = 'pending'
            ORDER BY po.created_at ASC
            LIMIT 5
        ");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $po = $row['po_number'] ?: ('PO-' . $row['id']);
            $actions[] = [
                'id' => 'po-' . $row['id'],
                'source_id' => (int)$row['id'],
                'type' => 'purchase_order',
                'category' => 'procurement',
                'priority' => 'high',
                'title' => 'Purchase Order #' . $po . ' — Awaiting Approval',
                'detail' => ($row['supplier_name'] ? $row['supplier_name'] . ' · ' : '') . 'Purchasing submission for GM sign-off',
                'meta' => '₱' . number_format((float)$row['total_amount'], 2),
                'href' => 'gm_approvals.html',
                'requested_at' => $row['created_at'],
            ];
        }
    } catch (Exception $e) { /* ignore */ }

    // Sales orders needing credit / GM attention (pending)
    try {
        $stmt = $pdo->query("
            SELECT o.id, o.order_number, o.total_amount, o.created_at, o.notes,
                   COALESCE(c.name, o.customer_name) as customer_name
            FROM sales_orders o
            LEFT JOIN customers c ON c.id = o.customer_id
            WHERE o.status = 'pending'
            ORDER BY o.created_at ASC
            LIMIT 5
        ");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $on = $row['order_number'] ?: ('SO-' . $row['id']);
            $credit = (stripos((string)$row['notes'], 'CREDIT') !== false || stripos((string)$row['notes'], 'OVERRIDE') !== false);
            $actions[] = [
                'id' => 'so-' . $row['id'],
                'source_id' => (int)$row['id'],
                'type' => $credit ? 'credit_override' : 'sales_order',
                'category' => 'credit',
                'priority' => $credit ? 'critical' : 'medium',
                'title' => $credit
                    ? 'Order #' . $on . ' — Requires Credit Override'
                    : 'Order #' . $on . ' — Pending GM Approval',
                'detail' => ($row['customer_name'] ?: 'Customer') . ' · Credit / order authorization required',
                'meta' => '₱' . number_format((float)$row['total_amount'], 2),
                'href' => '../sales/orders.html?status=pending',
                'requested_at' => $row['created_at'],
            ];
        }
    } catch (Exception $e) { /* ignore */ }

    // Pending material requisitions
    try {
        $stmt = $pdo->query("
            SELECT id, requisition_code, purpose, priority, created_at, department
            FROM material_requisitions
            WHERE status = 'pending'
            ORDER BY FIELD(priority, 'urgent', 'high', 'normal', 'low'), created_at ASC
            LIMIT 3
        ");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $code = $row['requisition_code'] ?: ('MR-' . $row['id']);
            $actions[] = [
                'id' => 'mr-' . $row['id'],
                'source_id' => (int)$row['id'],
                'type' => 'requisition',
                'category' => 'requisition',
                'priority' => in_array($row['priority'], ['urgent', 'high'], true) ? 'high' : 'medium',
                'title' => 'Material Requisition #' . $code . ' — Pending Approval',
                'detail' => trim(($row['department'] ?: 'Production') . ' · ' . ($row['purpose'] ?: 'Materials request')),
                'meta' => null,
                'href' => 'gm_approvals.html',
                'requested_at' => $row['created_at'],
            ];
        }
    } catch (Exception $e) { /* ignore */ }

    // Priority sort: critical > high > medium
    $rank = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
    usort($actions, function ($a, $b) use ($rank) {
        $pa = $rank[$a['priority']] ?? 9;
        $pb = $rank[$b['priority']] ?? 9;
        if ($pa !== $pb) return $pa <=> $pb;
        return strcmp((string)($b['requested_at'] ?? ''), (string)($a['requested_at'] ?? ''));
    });

    return array_slice($actions, 0, 6);
}

/**
 * Human-readable audit trail for the GM dashboard.
 */
function fetchGmRecentActivity(PDO $pdo): array {
    $rows = [];
    try {
        $stmt = $pdo->query("
            SELECT a.id, a.user_id, a.action, a.table_name, a.record_id,
                   a.new_values, a.created_at,
                   u.full_name, u.role, u.first_name, u.last_name
            FROM audit_logs a
            LEFT JOIN users u ON u.id = a.user_id
            ORDER BY a.created_at DESC
            LIMIT 40
        ");
        $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $raw = [];
    }

    foreach ($raw as $row) {
        $summary = null;
        if (!empty($row['new_values'])) {
            $decoded = json_decode($row['new_values'], true);
            if (is_array($decoded) && !empty($decoded['summary'])) {
                $summary = $decoded['summary'];
            }
        }
        $name = $row['full_name']
            ?: trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))
            ?: 'System';
        $role = $row['role'] ? formatRoleLabel($row['role']) : 'System';
        $message = $summary ?: describeAuditAction($name, $row['action'], $row['table_name'], $row['record_id']);

        // Prefer operational actions over pure LOGIN spam for executive view
        $actionUpper = strtoupper((string)$row['action']);
        if (in_array($actionUpper, ['LOGIN', 'LOGOUT'], true) && !$summary) {
            continue;
        }

        $rows[] = [
            'id' => $row['id'],
            'actor' => $name,
            'role' => $role,
            'action' => $row['action'],
            'message' => $message,
            'created_at' => $row['created_at'],
        ];
        if (count($rows) >= 8) break;
    }

    return $rows;
}

function formatRoleLabel(?string $role): string {
    $map = [
        'general_manager' => 'General Manager',
        'sales_custodian' => 'Sales Custodian',
        'warehouse_fg' => 'Warehouse FG',
        'warehouse_raw' => 'Warehouse Raw',
        'qc_officer' => 'QC Officer',
        'purchaser' => 'Purchaser',
        'cashier' => 'Cashier',
        'finance_officer' => 'Finance Officer',
        'production_staff' => 'Production',
        'bookkeeper' => 'Bookkeeper',
        'admin' => 'Administrator',
    ];
    if (!$role) return 'User';
    return $map[$role] ?? ucwords(str_replace('_', ' ', $role));
}

function describeAuditAction(string $name, ?string $action, ?string $table, $recordId): string {
    $a = strtoupper((string)$action);
    $t = str_replace('_', ' ', (string)$table);
    $ref = $recordId ? " #{$recordId}" : '';

    $verbs = [
        'CREATE' => 'created',
        'INSERT' => 'created',
        'UPDATE' => 'updated',
        'DELETE' => 'deleted',
        'APPROVE' => 'approved',
        'SUBMIT' => 'submitted',
        'DISPATCH' => 'dispatched',
        'RECEIVE' => 'received',
        'UPDATE_CREDIT_LIMIT' => 'updated credit limit on',
    ];

    if ($a === 'UPDATE_CREDIT_LIMIT') {
        return "{$name} updated a Customer Credit Limit";
    }
    if (isset($verbs[$a])) {
        return "{$name} {$verbs[$a]} {$t}{$ref}";
    }
    if ($a === 'LOGIN') return "{$name} signed in";
    if ($a === 'LOGOUT') return "{$name} signed out";
    return "{$name} performed {$action} on {$t}{$ref}";
}
