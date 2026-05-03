<?php
/**
 * Highland Fresh System - Purchase Requests API
 * 
 * Phase 1 Purchasing Workflow:
 *   Warehouse Raw creates PR → GM approves → Purchaser creates PO
 * 
 * POST   - Create PR (warehouse_raw only)
 * GET    - List PRs, get details
 * PUT    - Approve/Reject PR (GM only)
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';

// Allowed roles: warehouse_raw creates, purchaser views approved, GM approves
$currentUser = Auth::requireRole(['warehouse_raw', 'purchaser', 'general_manager']);

$action = getParam('action', 'list');

try {
    $db = Database::getInstance()->getConnection();
    ensurePRTables($db);

    switch ($requestMethod) {
        case 'GET':
            handleGet($db, $action, $currentUser);
            break;
        case 'POST':
            handlePost($db, $action, $currentUser);
            break;
        case 'PUT':
            handlePut($db, $action, $currentUser);
            break;
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Purchase Requests API Error: " . $e->getMessage());
    Response::error('Server error: ' . $e->getMessage(), 500);
}

/**
 * Ensure PR tables exist (auto-migration)
 */
function ensurePRTables($db) {
    // Create purchase_requests table if not exists
    $db->exec("
        CREATE TABLE IF NOT EXISTS `purchase_requests` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `pr_number` VARCHAR(30) NOT NULL,
            `requested_by` INT(11) NOT NULL,
            `department` VARCHAR(50) NOT NULL DEFAULT 'warehouse_raw',
            `priority` ENUM('low','normal','high','urgent') DEFAULT 'normal',
            `needed_by_date` DATE DEFAULT NULL,
            `purpose` VARCHAR(255) DEFAULT NULL,
            `notes` TEXT DEFAULT NULL,
            `status` ENUM('pending','approved','rejected') DEFAULT 'pending',
            `approved_by` INT(11) DEFAULT NULL,
            `approved_at` DATETIME DEFAULT NULL,
            `rejection_reason` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_pr_number` (`pr_number`),
            KEY `idx_pr_status` (`status`),
            KEY `idx_pr_requested_by` (`requested_by`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    // Create purchase_request_items table if not exists
    $db->exec("
        CREATE TABLE IF NOT EXISTS `purchase_request_items` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `purchase_request_id` INT(11) NOT NULL,
            `ingredient_id` INT(11) DEFAULT NULL,
            `mro_item_id` INT(11) DEFAULT NULL,
            `item_description` VARCHAR(200) NOT NULL,
            `quantity` DECIMAL(12,2) NOT NULL,
            `unit` VARCHAR(20) NOT NULL DEFAULT 'units',
            `estimated_unit_price` DECIMAL(12,2) DEFAULT NULL,
            `estimated_total` DECIMAL(12,2) DEFAULT NULL,
            `notes` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_pri_pr_id` (`purchase_request_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    // Add purchase_request_id to purchase_orders if missing
    if (!auditColumnExists($db, 'purchase_orders', 'purchase_request_id')) {
        $db->exec("ALTER TABLE `purchase_orders` ADD COLUMN `purchase_request_id` INT(11) DEFAULT NULL AFTER `requisition_id`");
    }
}

/**
 * Handle GET requests
 */
function handleGet($db, $action, $currentUser) {
    switch ($action) {
        case 'list':
            $status = getParam('status');
            $search = getParam('search');
            $date_from = getParam('date_from');
            $date_to = getParam('date_to');
            $page = max(1, (int) getParam('page', 1));
            $limit = min(50, max(10, (int) getParam('limit', 20)));
            $offset = ($page - 1) * $limit;

            $where = "1=1";
            $params = [];

            if ($status) {
                $where .= " AND pr.status = ?";
                $params[] = $status;
            }

            if ($search) {
                $where .= " AND (pr.pr_number LIKE ? OR pr.purpose LIKE ?)";
                $searchTerm = "%$search%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            if ($date_from) {
                $where .= " AND DATE(pr.created_at) >= ?";
                $params[] = $date_from;
            }

            if ($date_to) {
                $where .= " AND DATE(pr.created_at) <= ?";
                $params[] = $date_to;
            }

            // Warehouse Raw sees their own PRs; Purchaser sees approved only; GM sees all
            if ($currentUser['role'] === 'warehouse_raw') {
                $where .= " AND pr.requested_by = ?";
                $params[] = $currentUser['user_id'];
            } elseif ($currentUser['role'] === 'purchaser') {
                $where .= " AND pr.status = 'approved'";
            }

            // Count total
            $countStmt = $db->prepare("SELECT COUNT(*) as total FROM purchase_requests pr WHERE $where");
            $countStmt->execute($params);
            $total = (int) $countStmt->fetch()['total'];

            // Paginated results
            $params[] = $limit;
            $params[] = $offset;

            $stmt = $db->prepare("
                SELECT 
                    pr.*,
                    u.full_name as requested_by_name,
                    ua.full_name as approved_by_name,
                    (SELECT COUNT(*) FROM purchase_request_items WHERE purchase_request_id = pr.id) as item_count,
                    (SELECT COALESCE(SUM(estimated_total), 0) FROM purchase_request_items WHERE purchase_request_id = pr.id) as estimated_total,
                    (SELECT COUNT(*) FROM purchase_orders WHERE purchase_request_id = pr.id AND status != 'cancelled') as po_count
                FROM purchase_requests pr
                LEFT JOIN users u ON pr.requested_by = u.id
                LEFT JOIN users ua ON pr.approved_by = ua.id
                WHERE $where
                ORDER BY 
                    CASE pr.status WHEN 'pending' THEN 1 WHEN 'approved' THEN 2 ELSE 3 END,
                    CASE pr.priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END,
                    pr.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute($params);
            $requests = $stmt->fetchAll();

            Response::paginated($requests, $total, $page, $limit, 'Purchase requests retrieved');
            break;

        case 'detail':
            $id = getParam('id');
            if (!$id) {
                Response::error('PR ID required', 400);
            }

            $stmt = $db->prepare("
                SELECT 
                    pr.*,
                    u.full_name as requested_by_name,
                    ua.full_name as approved_by_name
                FROM purchase_requests pr
                LEFT JOIN users u ON pr.requested_by = u.id
                LEFT JOIN users ua ON pr.approved_by = ua.id
                WHERE pr.id = ?
            ");
            $stmt->execute([$id]);
            $request = $stmt->fetch();

            if (!$request) {
                Response::error('Purchase request not found', 404);
            }

            // Get items
            $itemsStmt = $db->prepare("
                SELECT 
                    pri.*,
                    i.ingredient_name,
                    i.ingredient_code,
                    i.current_stock as ingredient_current_stock,
                    i.reorder_point as ingredient_reorder_point,
                    m.item_name as mro_item_name,
                    m.item_code as mro_item_code,
                    m.current_stock as mro_current_stock,
                    m.minimum_stock as mro_minimum_stock
                FROM purchase_request_items pri
                LEFT JOIN ingredients i ON pri.ingredient_id = i.id
                LEFT JOIN mro_items m ON pri.mro_item_id = m.id
                WHERE pri.purchase_request_id = ?
                ORDER BY pri.id ASC
            ");
            $itemsStmt->execute([$id]);
            $request['items'] = $itemsStmt->fetchAll();

            // Get linked POs
            $posStmt = $db->prepare("
                SELECT po.id, po.po_number, po.status, po.total_amount, po.created_at,
                       s.supplier_name
                FROM purchase_orders po
                LEFT JOIN suppliers s ON po.supplier_id = s.id
                WHERE po.purchase_request_id = ?
                ORDER BY po.created_at DESC
            ");
            $posStmt->execute([$id]);
            $request['linked_pos'] = $posStmt->fetchAll();

            Response::success($request, 'Purchase request details retrieved');
            break;

        case 'next_number':
            $today = date('Ymd');
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM purchase_requests 
                WHERE pr_number LIKE ?
            ");
            $stmt->execute(["PR-{$today}-%"]);
            $count = (int) $stmt->fetch()['count'] + 1;
            $nextNumber = "PR-{$today}-" . str_pad($count, 3, '0', STR_PAD_LEFT);

            Response::success(['next_number' => $nextNumber], 'Next PR number');
            break;

        case 'approved_for_po':
            // Get approved PRs that don't have a completed PO yet
            // Used by Purchaser when creating a PO
            requireActionRole($currentUser, ['purchaser', 'general_manager'], 'Only Purchaser can view approved PRs for PO creation');

            $stmt = $db->query("
                SELECT 
                    pr.*,
                    u.full_name as requested_by_name,
                    (SELECT COUNT(*) FROM purchase_request_items WHERE purchase_request_id = pr.id) as item_count,
                    (SELECT COALESCE(SUM(estimated_total), 0) FROM purchase_request_items WHERE purchase_request_id = pr.id) as estimated_total
                FROM purchase_requests pr
                LEFT JOIN users u ON pr.requested_by = u.id
                WHERE pr.status = 'approved'
                AND pr.id NOT IN (
                    SELECT COALESCE(purchase_request_id, 0) 
                    FROM purchase_orders 
                    WHERE status NOT IN ('cancelled')
                    AND purchase_request_id IS NOT NULL
                )
                ORDER BY 
                    CASE pr.priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END,
                    pr.created_at ASC
            ");
            $requests = $stmt->fetchAll();

            // Include items for each
            foreach ($requests as &$req) {
                $itemsStmt = $db->prepare("
                    SELECT pri.*, 
                        i.ingredient_name, i.ingredient_code,
                        m.item_name as mro_item_name, m.item_code as mro_item_code
                    FROM purchase_request_items pri
                    LEFT JOIN ingredients i ON pri.ingredient_id = i.id
                    LEFT JOIN mro_items m ON pri.mro_item_id = m.id
                    WHERE pri.purchase_request_id = ?
                ");
                $itemsStmt->execute([$req['id']]);
                $req['items'] = $itemsStmt->fetchAll();
            }

            Response::success($requests, 'Approved PRs for PO creation');
            break;

        default:
            Response::error('Invalid action', 400);
    }
}

/**
 * Handle POST requests - Create Purchase Request
 */
function handlePost($db, $action, $currentUser) {
    switch ($action) {
        case 'create':
            // ===== WAREHOUSE RAW ONLY =====
            requireActionRole($currentUser, ['warehouse_raw'], 'Only Warehouse Raw staff can create Purchase Requests');

            $data = getRequestBody();

            // Validate required fields
            if (empty($data['items']) || !is_array($data['items']) || count($data['items']) === 0) {
                Response::error('At least one item is required', 400);
            }

            $priority = $data['priority'] ?? 'normal';
            if (!in_array($priority, ['low', 'normal', 'high', 'urgent'])) {
                Response::error('Invalid priority', 400);
            }

            $db->beginTransaction();

            try {
                // Generate PR number
                $today = date('Ymd');
                $codeStmt = $db->prepare("SELECT COUNT(*) as count FROM purchase_requests WHERE pr_number LIKE ?");
                $codeStmt->execute(["PR-{$today}-%"]);
                $count = (int) $codeStmt->fetch()['count'] + 1;
                $prNumber = "PR-{$today}-" . str_pad($count, 3, '0', STR_PAD_LEFT);

                // Insert PR
                $stmt = $db->prepare("
                    INSERT INTO purchase_requests 
                    (pr_number, requested_by, department, priority, needed_by_date, purpose, notes, status)
                    VALUES (?, ?, 'warehouse_raw', ?, ?, ?, ?, 'pending')
                ");
                $stmt->execute([
                    $prNumber,
                    $currentUser['user_id'],
                    $priority,
                    $data['needed_by_date'] ?? null,
                    $data['purpose'] ?? null,
                    $data['notes'] ?? null
                ]);

                $prId = $db->lastInsertId();

                // Insert items
                $itemStmt = $db->prepare("
                    INSERT INTO purchase_request_items 
                    (purchase_request_id, ingredient_id, mro_item_id, item_description, quantity, unit, 
                     estimated_unit_price, estimated_total, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                foreach ($data['items'] as $item) {
                    $qty = (float) ($item['quantity'] ?? 0);
                    $unitPrice = (float) ($item['estimated_unit_price'] ?? 0);
                    $lineTotal = $qty * $unitPrice;

                    $itemStmt->execute([
                        $prId,
                        $item['ingredient_id'] ?? null,
                        $item['mro_item_id'] ?? null,
                        $item['item_description'] ?? '',
                        $qty,
                        $item['unit'] ?? 'units',
                        $unitPrice > 0 ? $unitPrice : null,
                        $lineTotal > 0 ? $lineTotal : null,
                        $item['notes'] ?? null
                    ]);
                }

                $db->commit();

                logAudit($currentUser['user_id'], 'CREATE', 'purchase_requests', $prId, null, [
                    'pr_number' => $prNumber,
                    'items_count' => count($data['items']),
                    'priority' => $priority
                ]);

                Response::success([
                    'id' => $prId,
                    'pr_number' => $prNumber,
                    'status' => 'pending'
                ], 'Purchase Request created — awaiting GM approval', 201);

            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;

        default:
            Response::error('Invalid action', 400);
    }
}

/**
 * Handle PUT requests - Approve / Reject PR
 */
function handlePut($db, $action, $currentUser) {
    $data = getRequestBody();
    $id = getParam('id') ?? ($data['id'] ?? null);

    if (!$id) {
        Response::error('PR ID required', 400);
    }

    // Get current PR
    $check = $db->prepare("SELECT * FROM purchase_requests WHERE id = ?");
    $check->execute([$id]);
    $current = $check->fetch();

    if (!$current) {
        Response::error('Purchase Request not found', 404);
    }

    switch ($action) {
        case 'approve':
            // ===== GM ONLY =====
            requireActionRole($currentUser, ['general_manager'], 'Only the General Manager can approve Purchase Requests');

            if ($current['status'] !== 'pending') {
                Response::error('Only pending Purchase Requests can be approved. Current status: ' . $current['status'], 400);
            }

            $stmt = $db->prepare("
                UPDATE purchase_requests 
                SET status = 'approved',
                    approved_by = ?,
                    approved_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$currentUser['user_id'], $id]);

            logAudit($currentUser['user_id'], 'APPROVE', 'purchase_requests', $id,
                ['status' => 'pending'],
                ['status' => 'approved', 'approved_by' => $currentUser['user_id']]
            );

            Response::success(['id' => $id, 'status' => 'approved'], 'Purchase Request approved');
            break;

        case 'reject':
            // ===== GM ONLY =====
            requireActionRole($currentUser, ['general_manager'], 'Only the General Manager can reject Purchase Requests');

            if ($current['status'] !== 'pending') {
                Response::error('Only pending Purchase Requests can be rejected. Current status: ' . $current['status'], 400);
            }

            $reason = $data['reason'] ?? 'No reason provided';

            $stmt = $db->prepare("
                UPDATE purchase_requests 
                SET status = 'rejected',
                    approved_by = ?,
                    approved_at = NOW(),
                    rejection_reason = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$currentUser['user_id'], $reason, $id]);

            logAudit($currentUser['user_id'], 'REJECT', 'purchase_requests', $id,
                ['status' => 'pending'],
                ['status' => 'rejected', 'reason' => $reason]
            );

            Response::success(['id' => $id, 'status' => 'rejected'], 'Purchase Request rejected');
            break;

        default:
            Response::error('Invalid action', 400);
    }
}
