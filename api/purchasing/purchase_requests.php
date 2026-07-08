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
            `status` ENUM('draft','pending','approved','rejected','converted') DEFAULT 'pending',
            `approved_by` INT(11) DEFAULT NULL,
            `approved_at` DATETIME DEFAULT NULL,
            `approver_name` VARCHAR(100) DEFAULT NULL,
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
            `purpose` VARCHAR(255) DEFAULT NULL,
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

    if (!auditColumnExists($db, 'purchase_request_items', 'purpose')) {
        $db->exec("ALTER TABLE `purchase_request_items` ADD COLUMN `purpose` VARCHAR(255) DEFAULT NULL AFTER `estimated_total`");
    }

    // Add approver_name column if missing (stores GM name at time of approval)
    if (!auditColumnExists($db, 'purchase_requests', 'approver_name')) {
        $db->exec("ALTER TABLE `purchase_requests` ADD COLUMN `approver_name` VARCHAR(100) DEFAULT NULL AFTER `approved_at`");
    }

    if (!auditColumnExists($db, 'purchase_requests', 'request_fingerprint')) {
        $db->exec("ALTER TABLE `purchase_requests` ADD COLUMN `request_fingerprint` VARCHAR(64) DEFAULT NULL AFTER `notes`");
    }

    $db->exec("
        ALTER TABLE `purchase_requests`
        MODIFY COLUMN `status` ENUM('draft','pending','approved','rejected','converted') DEFAULT 'pending'
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS `purchase_request_status_history` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `purchase_request_id` INT(11) NOT NULL,
            `from_status` VARCHAR(30) DEFAULT NULL,
            `to_status` VARCHAR(30) NOT NULL,
            `notes` TEXT DEFAULT NULL,
            `changed_by` INT(11) DEFAULT NULL,
            `changed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_pr_history_pr` (`purchase_request_id`),
            KEY `idx_pr_history_status` (`to_status`),
            KEY `idx_pr_history_changed_at` (`changed_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
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
                $where .= " AND pr.status = 'approved' AND pr.approved_by IS NOT NULL AND pr.approved_at IS NOT NULL";
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
                    (SELECT COUNT(*) FROM purchase_orders WHERE purchase_request_id = pr.id AND status != 'cancelled') as po_count,
                    (SELECT MAX(changed_at) FROM purchase_request_status_history WHERE purchase_request_id = pr.id) as status_changed_at
                FROM purchase_requests pr
                LEFT JOIN users u ON pr.requested_by = u.id
                LEFT JOIN users ua ON pr.approved_by = ua.id
                WHERE $where
                ORDER BY 
                    CASE pr.status WHEN 'draft' THEN 0 WHEN 'pending' THEN 1 WHEN 'approved' THEN 2 WHEN 'converted' THEN 3 ELSE 4 END,
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
                    i.minimum_stock as ingredient_minimum_stock,
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
            $request['current_general_manager_name'] = getCurrentGeneralManagerName($db);

            $historyStmt = $db->prepare("
                SELECT h.*, u.full_name as changed_by_name
                FROM purchase_request_status_history h
                LEFT JOIN users u ON h.changed_by = u.id
                WHERE h.purchase_request_id = ?
                ORDER BY h.changed_at ASC, h.id ASC
            ");
            $historyStmt->execute([$id]);
            $request['status_history'] = $historyStmt->fetchAll();

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
                AND pr.approved_by IS NOT NULL
                AND pr.approved_at IS NOT NULL
                AND pr.id NOT IN (
                    SELECT COALESCE(purchase_request_id, 0) 
                    FROM purchase_orders 
                    WHERE status NOT IN ('cancelled', 'rejected')
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

        case 'requested_items':
            requireActionRole($currentUser, ['warehouse_raw', 'general_manager'], 'Only Warehouse Raw staff can view requested PR items');

            $where = "pr.department = 'warehouse_raw'";
            $params = [];

            if ($currentUser['role'] === 'warehouse_raw') {
                $where .= " AND pr.requested_by = ?";
                $params[] = $currentUser['user_id'];
            }

            $stmt = $db->prepare("
                SELECT
                    pri.id AS item_row_id,
                    pri.purchase_request_id AS pr_id,
                    pr.pr_number,
                    pr.status,
                    pr.priority,
                    pr.created_at,
                    pr.needed_by_date,
                    pr.purpose AS pr_purpose,
                    pri.item_description,
                    pri.quantity,
                    pri.unit,
                    pri.purpose AS item_purpose,
                    pri.notes,
                    pri.ingredient_id,
                    pri.mro_item_id,
                    i.ingredient_code,
                    m.item_code AS mro_item_code,
                    (SELECT COUNT(*) FROM purchase_orders po WHERE po.purchase_request_id = pr.id AND po.status != 'cancelled') AS po_count
                FROM purchase_request_items pri
                JOIN purchase_requests pr ON pr.id = pri.purchase_request_id
                LEFT JOIN ingredients i ON pri.ingredient_id = i.id
                LEFT JOIN mro_items m ON pri.mro_item_id = m.id
                WHERE {$where}
                ORDER BY
                    CASE pr.status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 WHEN 'converted' THEN 2 WHEN 'draft' THEN 3 ELSE 4 END,
                    pr.created_at DESC,
                    pri.id ASC
            ");
            $stmt->execute($params);

            Response::success($stmt->fetchAll(), 'Requested PR items retrieved');
            break;

        case 'pending_item_refs':
            requireActionRole($currentUser, ['warehouse_raw', 'general_manager'], 'Only Warehouse Raw staff can view pending PR item references');

            $stmt = $db->query("
                SELECT
                    CASE
                        WHEN pri.ingredient_id IS NOT NULL THEN 'ingredient'
                        WHEN pri.mro_item_id IS NOT NULL THEN 'mro'
                        ELSE 'unknown'
                    END AS item_type,
                    COALESCE(pri.ingredient_id, pri.mro_item_id) AS item_id,
                    pr.id AS pr_id,
                    pr.pr_number,
                    pr.status,
                    pr.created_at
                FROM purchase_requests pr
                JOIN purchase_request_items pri ON pri.purchase_request_id = pr.id
                WHERE pr.department = 'warehouse_raw'
                  AND pr.status = 'pending'
                  AND (pri.ingredient_id IS NOT NULL OR pri.mro_item_id IS NOT NULL)
                ORDER BY pr.created_at DESC, pr.id DESC
            ");

            Response::success($stmt->fetchAll(), 'Pending PR item references retrieved');
            break;

        default:
            Response::error('Invalid action', 400);
    }
}

function getCurrentGeneralManagerName($db) {
        $stmt = $db->query("
                SELECT full_name
                FROM users
                WHERE is_active = 1
                    AND REPLACE(LOWER(role), ' ', '_') = 'general_manager'
                ORDER BY updated_at DESC, created_at DESC, id DESC
                LIMIT 1
        ");
    $name = $stmt->fetchColumn();
    return $name ?: null;
}

function addPRStatusHistory($db, $prId, $fromStatus, $toStatus, $userId, $notes = null) {
    $stmt = $db->prepare("
        INSERT INTO purchase_request_status_history
        (purchase_request_id, from_status, to_status, notes, changed_by)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$prId, $fromStatus, $toStatus, $notes, $userId]);
}

function validatePRCreateData($data) {
    if (empty($data['items']) || !is_array($data['items']) || count($data['items']) === 0) {
        Response::error('At least one item is required', 400);
    }

    foreach ($data['items'] as $index => $item) {
        $lineNo = $index + 1;
        if (!is_array($item)) {
            Response::error("Line {$lineNo}: item data is incomplete", 400);
        }

        $hasIngredient = !empty($item['ingredient_id']);
        $hasMro = !empty($item['mro_item_id']);
        if (!$hasIngredient && !$hasMro) {
            Response::error("Line {$lineNo}: select an approved item", 400);
        }

        if (trim((string)($item['item_description'] ?? '')) === '') {
            Response::error("Line {$lineNo}: item description is required", 400);
        }

        if (!isset($item['quantity']) || !is_numeric($item['quantity']) || (float)$item['quantity'] <= 0) {
            Response::error("Line {$lineNo}: requested quantity must be greater than zero", 400);
        }

        if (trim((string)($item['unit'] ?? '')) === '') {
            Response::error("Line {$lineNo}: unit is required", 400);
        }

        if (trim((string)($item['purpose'] ?? $data['purpose'] ?? '')) === '') {
            Response::error("Line {$lineNo}: purpose/reason is required", 400);
        }
    }
}

function buildPRFingerprint($items) {
    $entries = [];
    foreach ($items as $item) {
        $type = !empty($item['ingredient_id']) ? 'ingredient' : (!empty($item['mro_item_id']) ? 'mro' : 'unknown');
        $id = $item['ingredient_id'] ?? ($item['mro_item_id'] ?? '');
        $qty = number_format((float) ($item['quantity'] ?? 0), 4, '.', '');
        $unit = strtolower(trim((string) ($item['unit'] ?? '')));
        $entries[] = "{$type}:{$id}|qty:{$qty}|unit:{$unit}";
    }

    sort($entries, SORT_STRING);
    return hash('sha256', implode(';', $entries));
}

function getPRFingerprintFromDb($db, $prId) {
    $items = getRequestItemsById($db, $prId);
    if (!$items) {
        return null;
    }

    return buildPRFingerprint($items);
}

function getRequestItemsById($db, $prId) {
    $stmt = $db->prepare("SELECT ingredient_id, mro_item_id, quantity, unit FROM purchase_request_items WHERE purchase_request_id = ?");
    $stmt->execute([$prId]);
    return $stmt->fetchAll();
}

function findDuplicatePendingPR($db, $department, $fingerprint, $excludeId = null) {
    if (!$fingerprint) {
        return null;
    }

    $sql = "SELECT id, pr_number FROM purchase_requests WHERE department = ? AND status = 'pending' AND request_fingerprint = ?";
    $params = [$department, $fingerprint];
    if ($excludeId) {
        $sql .= " AND id != ?";
        $params[] = $excludeId;
    }
    $sql .= " LIMIT 1";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
}

function findPendingPRWithOverlappingItems($db, $department, $items, $excludeId = null) {
    if (empty($items) || !is_array($items)) {
        return null;
    }

    $ingredientIds = [];
    $mroIds = [];

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        if (!empty($item['ingredient_id'])) {
            $ingredientIds[] = (int) $item['ingredient_id'];
        }
        if (!empty($item['mro_item_id'])) {
            $mroIds[] = (int) $item['mro_item_id'];
        }
    }

    $ingredientIds = array_values(array_unique(array_filter($ingredientIds)));
    $mroIds = array_values(array_unique(array_filter($mroIds)));

    if (empty($ingredientIds) && empty($mroIds)) {
        return null;
    }

    $conditions = [];
    $params = [$department];

    if (!empty($ingredientIds)) {
        $placeholders = implode(',', array_fill(0, count($ingredientIds), '?'));
        $conditions[] = "(pri.ingredient_id IS NOT NULL AND pri.ingredient_id IN ($placeholders))";
        $params = array_merge($params, $ingredientIds);
    }

    if (!empty($mroIds)) {
        $placeholders = implode(',', array_fill(0, count($mroIds), '?'));
        $conditions[] = "(pri.mro_item_id IS NOT NULL AND pri.mro_item_id IN ($placeholders))";
        $params = array_merge($params, $mroIds);
    }

    $sql = "
        SELECT pr.id, pr.pr_number
        FROM purchase_requests pr
        JOIN purchase_request_items pri ON pri.purchase_request_id = pr.id
        WHERE pr.department = ?
          AND pr.status = 'pending'
          AND (" . implode(' OR ', $conditions) . ")
    ";

    if ($excludeId) {
        $sql .= " AND pr.id != ?";
        $params[] = (int) $excludeId;
    }

    $sql .= " ORDER BY pr.created_at DESC LIMIT 1";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
}

function countActivePOsForPR($db, $prId) {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM purchase_orders
        WHERE purchase_request_id = ?
          AND status NOT IN ('cancelled', 'rejected')
    ");
    $stmt->execute([$prId]);
    return (int) $stmt->fetchColumn();
}

function replacePRItems($db, $prId, $items, $defaultPurpose = null) {
    $deleteStmt = $db->prepare("DELETE FROM purchase_request_items WHERE purchase_request_id = ?");
    $deleteStmt->execute([$prId]);

    $itemStmt = $db->prepare("
        INSERT INTO purchase_request_items
        (purchase_request_id, ingredient_id, mro_item_id, item_description, quantity, unit,
         estimated_unit_price, estimated_total, purpose, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($items as $item) {
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
            $item['purpose'] ?? $defaultPurpose,
            $item['notes'] ?? null
        ]);
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

            rejectSupplierFieldsInPR($data);

            validatePRCreateData($data);

            $fingerprint = buildPRFingerprint($data['items']);

            $priority = $data['priority'] ?? 'normal';
            if (!in_array($priority, ['low', 'normal', 'high', 'urgent'])) {
                Response::error('Invalid priority', 400);
            }

            $status = $data['status'] ?? 'pending';
            if (!in_array($status, ['draft', 'pending'])) {
                Response::error('Invalid Purchase Request status', 400);
            }

            if ($status === 'pending') {
                $duplicate = findDuplicatePendingPR($db, 'warehouse_raw', $fingerprint);
                if ($duplicate) {
                    Response::error('Duplicate pending Purchase Request already exists (' . $duplicate['pr_number'] . '). Please update that request instead.', 409, [
                        'duplicate_pr_id' => (int) $duplicate['id'],
                        'duplicate_pr_number' => $duplicate['pr_number']
                    ]);
                }

                $overlap = findPendingPRWithOverlappingItems($db, 'warehouse_raw', $data['items']);
                if ($overlap) {
                    Response::error('Pending Purchase Request already exists for one or more items (' . $overlap['pr_number'] . '). Please update that request instead.', 409, [
                        'duplicate_pr_id' => (int) $overlap['id'],
                        'duplicate_pr_number' => $overlap['pr_number']
                    ]);
                }
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
                    (pr_number, requested_by, department, priority, needed_by_date, purpose, notes, status, request_fingerprint)
                    VALUES (?, ?, 'warehouse_raw', ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $prNumber,
                    $currentUser['user_id'],
                    $priority,
                    $data['needed_by_date'] ?? null,
                    $data['purpose'] ?? null,
                    $data['notes'] ?? null,
                    $status,
                    $fingerprint
                ]);

                $prId = $db->lastInsertId();
                addPRStatusHistory($db, $prId, null, $status, $currentUser['user_id'], $status === 'draft' ? 'Draft saved' : 'Submitted for GM approval');

                replacePRItems($db, $prId, $data['items'], $data['purpose'] ?? null);

                $db->commit();

                logAudit($currentUser['user_id'], 'CREATE', 'purchase_requests', $prId, null, [
                    'pr_number' => $prNumber,
                    'items_count' => count($data['items']),
                    'priority' => $priority,
                    'status' => $status
                ]);

                Response::success([
                    'id' => $prId,
                    'pr_number' => $prNumber,
                    'status' => $status
                ], $status === 'draft' ? 'Purchase Request draft saved' : 'Purchase Request created — awaiting GM approval', 201);

            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;

        default:
            Response::error('Invalid action', 400);
    }
}

function rejectSupplierFieldsInPR($data) {
    $supplierFields = ['supplier_id', 'supplier_name', 'supplier_code', 'supplier', 'supplier_contact'];
    foreach ($supplierFields as $field) {
        if (array_key_exists($field, $data) && $data[$field] !== null && $data[$field] !== '') {
            Response::error('Purchase Requests are internal requests only. Supplier selection is allowed only when creating a Purchase Order.', 400);
        }
    }

    if (empty($data['items']) || !is_array($data['items'])) {
        return;
    }

    foreach ($data['items'] as $item) {
        if (!is_array($item)) {
            continue;
        }
        foreach ($supplierFields as $field) {
            if (array_key_exists($field, $item) && $item[$field] !== null && $item[$field] !== '') {
                Response::error('Purchase Request line items cannot include supplier information. Assign suppliers in the Purchase Order module.', 400);
            }
        }
    }
}

/**
 * Handle PUT requests - Update / Approve / Reject PR
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
        case 'update':
            requireActionRole($currentUser, ['warehouse_raw'], 'Only Warehouse Raw staff can edit Purchase Request drafts');

            if ($current['requested_by'] != $currentUser['user_id']) {
                Response::error('You can only edit your own Purchase Request drafts', 403);
            }

            if ($current['status'] === 'converted') {
                Response::error('Converted Purchase Requests are locked. A General Manager must reopen the PR before it can be edited.', 400);
            }

            if ($current['status'] !== 'draft') {
                Response::error('Only draft Purchase Requests can be edited. Current status: ' . $current['status'], 400);
            }

            rejectSupplierFieldsInPR($data);
            validatePRCreateData($data);

            $fingerprint = buildPRFingerprint($data['items']);

            $priority = $data['priority'] ?? $current['priority'];
            if (!in_array($priority, ['low', 'normal', 'high', 'urgent'])) {
                Response::error('Invalid priority', 400);
            }

            $db->beginTransaction();
            try {
                $stmt = $db->prepare("
                    UPDATE purchase_requests
                    SET priority = ?,
                        needed_by_date = ?,
                        purpose = ?,
                        notes = ?,
                        request_fingerprint = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $priority,
                    $data['needed_by_date'] ?? null,
                    $data['purpose'] ?? null,
                    $data['notes'] ?? null,
                    $fingerprint,
                    $id
                ]);

                replacePRItems($db, $id, $data['items'], $data['purpose'] ?? null);
                addPRStatusHistory($db, $id, 'draft', 'draft', $currentUser['user_id'], 'Draft updated');

                $db->commit();
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }

            logAudit($currentUser['user_id'], 'UPDATE', 'purchase_requests', $id,
                ['status' => $current['status']],
                ['status' => 'draft', 'items_count' => count($data['items'])]
            );

            Response::success(['id' => $id, 'status' => 'draft'], 'Purchase Request draft updated');
            break;

        case 'submit':
            requireActionRole($currentUser, ['warehouse_raw'], 'Only Warehouse Raw staff can submit Purchase Requests');

            if ($current['requested_by'] != $currentUser['user_id']) {
                Response::error('You can only submit your own Purchase Request drafts', 403);
            }

            if ($current['status'] !== 'draft') {
                Response::error('Only draft Purchase Requests can be submitted. Current status: ' . $current['status'], 400);
            }

            $fingerprint = $current['request_fingerprint'] ?? null;
            if (!$fingerprint) {
                $fingerprint = getPRFingerprintFromDb($db, $id);
                if ($fingerprint) {
                    $fpStmt = $db->prepare("UPDATE purchase_requests SET request_fingerprint = ? WHERE id = ?");
                    $fpStmt->execute([$fingerprint, $id]);
                }
            }

            $duplicate = findDuplicatePendingPR($db, $current['department'], $fingerprint, $id);
            if ($duplicate) {
                Response::error('Duplicate pending Purchase Request already exists (' . $duplicate['pr_number'] . '). Please update that request instead.', 409, [
                    'duplicate_pr_id' => (int) $duplicate['id'],
                    'duplicate_pr_number' => $duplicate['pr_number']
                ]);
            }

            $pendingItems = getRequestItemsById($db, $id);
            $overlap = findPendingPRWithOverlappingItems($db, $current['department'], $pendingItems, $id);
            if ($overlap) {
                Response::error('Pending Purchase Request already exists for one or more items (' . $overlap['pr_number'] . '). Please update that request instead.', 409, [
                    'duplicate_pr_id' => (int) $overlap['id'],
                    'duplicate_pr_number' => $overlap['pr_number']
                ]);
            }

            $stmt = $db->prepare("
                UPDATE purchase_requests
                SET status = 'pending',
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$id]);
            addPRStatusHistory($db, $id, 'draft', 'pending', $currentUser['user_id'], 'Submitted for GM approval');

            logAudit($currentUser['user_id'], 'SUBMIT', 'purchase_requests', $id,
                ['status' => 'draft'],
                ['status' => 'pending']
            );

            Response::success(['id' => $id, 'status' => 'pending'], 'Purchase Request submitted for GM approval');
            break;

        case 'gm_update':
            requireActionRole($currentUser, ['general_manager'], 'Only the General Manager can edit Purchase Requests');

            if ($current['status'] !== 'pending') {
                Response::error('Only pending Purchase Requests can be edited. Current status: ' . $current['status'], 400);
            }

            rejectSupplierFieldsInPR($data);
            validatePRCreateData($data);

            $fingerprint = buildPRFingerprint($data['items']);

            $overlap = findPendingPRWithOverlappingItems($db, $current['department'], $data['items'], $id);
            if ($overlap) {
                Response::error('Pending Purchase Request already exists for one or more items (' . $overlap['pr_number'] . '). Please update that request instead.', 409, [
                    'duplicate_pr_id' => (int) $overlap['id'],
                    'duplicate_pr_number' => $overlap['pr_number']
                ]);
            }

            $priority = $data['priority'] ?? $current['priority'];
            if (!in_array($priority, ['low', 'normal', 'high', 'urgent'])) {
                Response::error('Invalid priority', 400);
            }

            $db->beginTransaction();
            try {
                $stmt = $db->prepare("
                    UPDATE purchase_requests
                    SET priority = ?,
                        needed_by_date = ?,
                        purpose = ?,
                        notes = ?,
                        request_fingerprint = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $priority,
                    $data['needed_by_date'] ?? null,
                    $data['purpose'] ?? null,
                    $data['notes'] ?? null,
                    $fingerprint,
                    $id
                ]);

                replacePRItems($db, $id, $data['items'], $data['purpose'] ?? null);

                $gmNotes = trim((string) ($data['gm_notes'] ?? ''));
                $historyNote = $gmNotes !== '' ? 'GM review: ' . $gmNotes : 'GM reviewed and updated request';
                addPRStatusHistory($db, $id, 'pending', 'pending', $currentUser['user_id'], $historyNote);

                $db->commit();
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }

            logAudit($currentUser['user_id'], 'UPDATE', 'purchase_requests', $id,
                ['status' => $current['status']],
                ['status' => 'pending', 'items_count' => count($data['items'])]
            );

            Response::success(['id' => $id, 'status' => 'pending'], 'Purchase Request updated by GM');
            break;

        case 'reopen':
            requireActionRole($currentUser, ['general_manager'], 'Only the General Manager can reopen converted Purchase Requests');

            if ($current['status'] !== 'converted') {
                Response::error('Only converted Purchase Requests can be reopened. Current status: ' . $current['status'], 400);
            }

            if (countActivePOsForPR($db, $id) > 0) {
                Response::error('This converted PR still has an active Purchase Order. Cancel or close the linked PO before reopening the PR.', 400);
            }

            $reason = trim((string)($data['reason'] ?? 'Reopened by GM'));
            if ($reason === '') {
                Response::error('Reopen reason is required', 400);
            }

            $stmt = $db->prepare("
                UPDATE purchase_requests
                SET status = 'draft',
                    approved_by = NULL,
                    approved_at = NULL,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$id]);
            addPRStatusHistory($db, $id, 'converted', 'draft', $currentUser['user_id'], $reason);

            logAudit($currentUser['user_id'], 'REOPEN', 'purchase_requests', $id,
                ['status' => 'converted'],
                ['status' => 'draft', 'reason' => $reason]
            );

            Response::success(['id' => $id, 'status' => 'draft'], 'Purchase Request reopened as draft');
            break;

        case 'approve':
            // ===== GM ONLY =====
            requireActionRole($currentUser, ['general_manager'], 'Only the General Manager can approve Purchase Requests');

            if ($current['status'] !== 'pending') {
                Response::error('Only pending Purchase Requests can be approved. Current status: ' . $current['status'], 400);
            }

            // Get the approver's full name for permanent record
            $approverStmt = $db->prepare("SELECT full_name FROM users WHERE id = ?");
            $approverStmt->execute([$currentUser['user_id']]);
            $approverName = $approverStmt->fetchColumn() ?: 'General Manager';
            $approvedAt = date('Y-m-d H:i:s');

            $stmt = $db->prepare("
                UPDATE purchase_requests 
                SET status = 'approved',
                    approved_by = ?,
                    approved_at = ?,
                    approver_name = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$currentUser['user_id'], $approvedAt, $approverName, $id]);
            $approvalRemarks = trim((string)($data['approval_remarks'] ?? $data['remarks'] ?? $data['notes'] ?? ''));
            addPRStatusHistory($db, $id, $current['status'], 'approved', $currentUser['user_id'], $approvalRemarks !== '' ? $approvalRemarks : 'Approved by GM');

            logAudit($currentUser['user_id'], 'APPROVE', 'purchase_requests', $id,
                ['status' => 'pending'],
                ['status' => 'approved', 'approved_by' => $currentUser['user_id'], 'approver_name' => $approverName, 'approved_at' => $approvedAt, 'approval_remarks' => $approvalRemarks]
            );

            Response::success([
                'id' => $id,
                'status' => 'approved',
                'approved_by' => $currentUser['user_id'],
                'approver_name' => $approverName,
                'approved_at' => $approvedAt
            ], 'Purchase Request approved');
            break;

        case 'reject':
            // ===== GM ONLY =====
            requireActionRole($currentUser, ['general_manager'], 'Only the General Manager can reject Purchase Requests');

            if ($current['status'] !== 'pending') {
                Response::error('Only pending Purchase Requests can be rejected. Current status: ' . $current['status'], 400);
            }

            $reason = $data['reason'] ?? 'No reason provided';

            // Get the rejector's full name for permanent record
            $rejectorStmt = $db->prepare("SELECT full_name FROM users WHERE id = ?");
            $rejectorStmt->execute([$currentUser['user_id']]);
            $rejectorName = $rejectorStmt->fetchColumn() ?: 'General Manager';
            $rejectedAt = date('Y-m-d H:i:s');

            $stmt = $db->prepare("
                UPDATE purchase_requests 
                SET status = 'rejected',
                    approved_by = ?,
                    approved_at = ?,
                    approver_name = ?,
                    rejection_reason = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$currentUser['user_id'], $rejectedAt, $rejectorName, $reason, $id]);
            addPRStatusHistory($db, $id, $current['status'], 'rejected', $currentUser['user_id'], $reason);

            logAudit($currentUser['user_id'], 'REJECT', 'purchase_requests', $id,
                ['status' => 'pending'],
                ['status' => 'rejected', 'rejected_by' => $currentUser['user_id'], 'rejector_name' => $rejectorName, 'rejected_at' => $rejectedAt, 'reason' => $reason]
            );

            Response::success([
                'id' => $id,
                'status' => 'rejected',
                'rejected_by' => $currentUser['user_id'],
                'rejector_name' => $rejectorName,
                'rejected_at' => $rejectedAt,
                'rejection_reason' => $reason
            ], 'Purchase Request rejected');
            break;

        default:
            Response::error('Invalid action', 400);
    }
}
