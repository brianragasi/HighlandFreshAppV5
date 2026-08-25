<?php
/**
 * Highland Fresh System - Purchase Request Slips API
 * 
 * Phase 1 Purchasing Workflow:
 *   Warehouse Raw creates PR → GM approves → Purchaser creates PO
 * 
 * POST   - Create PRS
 * GET    - List PRS records, get details
 * PUT    - Edit/submit drafts; legacy GM PR approval actions are retained
 * 
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/warehouse/raw/ingredient_stock_helpers.php';
require_once dirname(__DIR__) . '/helpers/procurement_notifications.php';

// Allowed roles: warehouse_raw creates PRS, purchaser converts PRS to PO, GM approves POs.
$currentUser = Auth::requireRole(['warehouse_raw', 'purchaser', 'general_manager', 'production_staff']);

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
            `status` ENUM('draft','pending','approved','rejected','converted','partially_converted') DEFAULT 'pending',
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
            `system_stock_before` DECIMAL(12,3) DEFAULT NULL,
            `audited_stock` DECIMAL(12,3) DEFAULT NULL,
            `stock_variance` DECIMAL(12,3) DEFAULT NULL,
            `audit_reason` VARCHAR(255) DEFAULT NULL,
            `audited_by` INT(11) DEFAULT NULL,
            `audited_at` DATETIME DEFAULT NULL,
            `target_stock_at_request` DECIMAL(12,3) DEFAULT NULL,
            `calculated_quantity` DECIMAL(12,2) DEFAULT NULL,
            `calculation_basis` VARCHAR(255) DEFAULT NULL,
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

    $auditColumns = [
        'system_stock_before' => "DECIMAL(12,3) DEFAULT NULL AFTER `notes`",
        'audited_stock' => "DECIMAL(12,3) DEFAULT NULL AFTER `system_stock_before`",
        'stock_variance' => "DECIMAL(12,3) DEFAULT NULL AFTER `audited_stock`",
        'audit_reason' => "VARCHAR(255) DEFAULT NULL AFTER `stock_variance`",
        'audited_by' => "INT(11) DEFAULT NULL AFTER `audit_reason`",
        'audited_at' => "DATETIME DEFAULT NULL AFTER `audited_by`",
        'target_stock_at_request' => "DECIMAL(12,3) DEFAULT NULL AFTER `audited_at`",
        'calculated_quantity' => "DECIMAL(12,2) DEFAULT NULL AFTER `target_stock_at_request`",
        'calculation_basis' => "VARCHAR(255) DEFAULT NULL AFTER `calculated_quantity`",
    ];
    foreach ($auditColumns as $column => $definition) {
        if (!auditColumnExists($db, 'purchase_request_items', $column)) {
            $db->exec("ALTER TABLE `purchase_request_items` ADD COLUMN `{$column}` {$definition}");
        }
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
        MODIFY COLUMN `status` ENUM('draft','pending','approved','rejected','converted','partially_converted') DEFAULT 'pending'
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

    ensurePurchaseRequestItemUniquenessGuards($db);
    ensureProcurementNotificationSupport($db);
}

/**
 * Block new duplicate inventory lines inside one PRS while preserving any
 * historical duplicates for review. Application validation provides the
 * friendly message; these triggers are the final concurrency safeguard.
 */
function ensurePurchaseRequestItemUniquenessGuards($db) {
    $databaseName = $db->query('SELECT DATABASE()')->fetchColumn();
    if (!$databaseName) {
        return;
    }

    $definitions = [
        'trg_pri_no_duplicate_insert' => "
            CREATE TRIGGER `trg_pri_no_duplicate_insert`
            BEFORE INSERT ON `purchase_request_items`
            FOR EACH ROW
            BEGIN
                IF NEW.ingredient_id IS NOT NULL AND EXISTS (
                    SELECT 1 FROM purchase_request_items existing
                    WHERE existing.purchase_request_id = NEW.purchase_request_id
                      AND existing.ingredient_id = NEW.ingredient_id
                ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Duplicate ingredient in this purchase request';
                END IF;
                IF NEW.mro_item_id IS NOT NULL AND EXISTS (
                    SELECT 1 FROM purchase_request_items existing
                    WHERE existing.purchase_request_id = NEW.purchase_request_id
                      AND existing.mro_item_id = NEW.mro_item_id
                ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Duplicate MRO item in this purchase request';
                END IF;
            END
        ",
        'trg_pri_no_duplicate_update_v2' => "
            CREATE TRIGGER `trg_pri_no_duplicate_update_v2`
            BEFORE UPDATE ON `purchase_request_items`
            FOR EACH ROW
            BEGIN
                IF NEW.ingredient_id IS NOT NULL
                   AND (NOT (NEW.purchase_request_id <=> OLD.purchase_request_id)
                        OR NOT (NEW.ingredient_id <=> OLD.ingredient_id))
                   AND EXISTS (
                    SELECT 1 FROM purchase_request_items existing
                    WHERE existing.purchase_request_id = NEW.purchase_request_id
                      AND existing.ingredient_id = NEW.ingredient_id
                      AND existing.id <> OLD.id
                ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Duplicate ingredient in this purchase request';
                END IF;
                IF NEW.mro_item_id IS NOT NULL
                   AND (NOT (NEW.purchase_request_id <=> OLD.purchase_request_id)
                        OR NOT (NEW.mro_item_id <=> OLD.mro_item_id))
                   AND EXISTS (
                    SELECT 1 FROM purchase_request_items existing
                    WHERE existing.purchase_request_id = NEW.purchase_request_id
                      AND existing.mro_item_id = NEW.mro_item_id
                      AND existing.id <> OLD.id
                ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Duplicate MRO item in this purchase request';
                END IF;
            END
        ",
    ];

    $existsStmt = $db->prepare("
        SELECT COUNT(*)
        FROM information_schema.TRIGGERS
        WHERE TRIGGER_SCHEMA = ? AND TRIGGER_NAME = ?
    ");

    // Replace the first trigger version, which also blocked harmless quantity
    // or note edits on a historical duplicate row.
    $existsStmt->execute([$databaseName, 'trg_pri_no_duplicate_update']);
    if ((int) $existsStmt->fetchColumn()) {
        try {
            $db->exec('DROP TRIGGER `trg_pri_no_duplicate_update`');
        } catch (PDOException $e) {
            error_log('Could not replace legacy PRS duplicate update trigger: ' . $e->getMessage());
        }
    }

    foreach ($definitions as $triggerName => $sql) {
        $existsStmt->execute([$databaseName, $triggerName]);
        if (!(int) $existsStmt->fetchColumn()) {
            try {
                $db->exec($sql);
            } catch (PDOException $e) {
                // Some hosted databases deny TRIGGER privilege. Keep the
                // existing transaction-level duplicate checks operational.
                error_log("Could not install {$triggerName}: " . $e->getMessage());
            }
        }
    }
}

function ensureProcurementNotificationSupport($db) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `procurement_notifications` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `target_role` VARCHAR(50) NOT NULL,
            `notification_type` VARCHAR(50) NOT NULL,
            `title` VARCHAR(150) NOT NULL,
            `message` TEXT NOT NULL,
            `reference_type` VARCHAR(50) DEFAULT NULL,
            `reference_id` INT(11) DEFAULT NULL,
            `is_read` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_procurement_notifications_role` (`target_role`, `is_read`),
            KEY `idx_procurement_notifications_reference` (`reference_type`, `reference_id`),
            KEY `idx_procurement_notifications_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

function createProcurementNotification($db, $targetRole, $type, $title, $message, $referenceType = null, $referenceId = null) {
    writeProcurementNotification($db, $targetRole, $type, $title, $message, $referenceType, $referenceId);
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

            // Warehouse Raw sees their own PRS records; Purchaser sees submitted PRS inbox items.
            if ($currentUser['role'] === 'warehouse_raw') {
                $where .= " AND pr.requested_by = ?";
                $params[] = $currentUser['user_id'];
            } elseif ($currentUser['role'] === 'purchaser') {
                $where .= " AND pr.status IN ('pending', 'approved', 'converted', 'partially_converted')";
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
                    m.minimum_stock as mro_minimum_stock,
                    auditor.full_name as audited_by_name
                FROM purchase_request_items pri
                LEFT JOIN ingredients i ON pri.ingredient_id = i.id
                LEFT JOIN mro_items m ON pri.mro_item_id = m.id
                LEFT JOIN users auditor ON pri.audited_by = auditor.id
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
            $stmt->execute(["PRS-{$today}-%"]);
            $count = (int) $stmt->fetch()['count'] + 1;
            $nextNumber = "PRS-{$today}-" . str_pad($count, 3, '0', STR_PAD_LEFT);

            Response::success(['next_number' => $nextNumber], 'Next PRS number');
            break;

        case 'prs_inbox':
        case 'approved_for_po':
            // Purchaser inbox: submitted PRS records that still need PO creation.
            // Legacy 'approved' PR rows are included for backward compatibility.
            requireActionRole($currentUser, ['purchaser', 'general_manager'], 'Only Purchaser can view PRS records for PO creation');

            $stmt = $db->query("
                SELECT 
                    pr.*,
                    u.full_name as requested_by_name,
                    (SELECT COUNT(*) FROM purchase_request_items WHERE purchase_request_id = pr.id) as item_count,
                    (SELECT COALESCE(SUM(estimated_total), 0) FROM purchase_request_items WHERE purchase_request_id = pr.id) as estimated_total
                FROM purchase_requests pr
                LEFT JOIN users u ON pr.requested_by = u.id
                WHERE pr.status IN ('pending', 'approved', 'partially_converted')
                  AND EXISTS (
                      SELECT 1
                      FROM purchase_request_items remaining_pri
                      WHERE remaining_pri.purchase_request_id = pr.id
                        AND remaining_pri.quantity > COALESCE((
                            SELECT SUM(prip.quantity)
                            FROM purchase_request_item_po prip
                            JOIN purchase_orders linked_po ON linked_po.id = prip.po_id
                            WHERE prip.purchase_request_item_id = remaining_pri.id
                              AND linked_po.status NOT IN ('cancelled', 'rejected')
                        ), 0) + 0.0001
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
                        m.item_name as mro_item_name, m.item_code as mro_item_code,
                        COALESCE((
                            SELECT SUM(prip.quantity)
                            FROM purchase_request_item_po prip
                            JOIN purchase_orders linked_po ON linked_po.id = prip.po_id
                            WHERE prip.purchase_request_item_id = pri.id
                              AND linked_po.status NOT IN ('cancelled', 'rejected')
                        ), 0) AS allocated_quantity,
                        pc.id as reviewed_canvass_id,
                        pc.canvass_code as reviewed_canvass_code,
                        pc.selection_method as reviewed_selection_method,
                        pc.selection_reason as reviewed_selection_reason,
                        cq.supplier_id as reviewed_supplier_id,
                        cq.unit_price as reviewed_unit_price,
                        s.supplier_name as reviewed_supplier_name,
                        s.supplier_code as reviewed_supplier_code
                    FROM purchase_request_items pri
                    LEFT JOIN ingredients i ON pri.ingredient_id = i.id
                    LEFT JOIN mro_items m ON pri.mro_item_id = m.id
                    LEFT JOIN price_canvass pc ON pc.id = (
                        SELECT pc2.id
                        FROM price_canvass pc2
                        WHERE pc2.purchase_request_item_id = pri.id
                          AND pc2.status = 'completed'
                          AND pc2.selected_quote_id IS NOT NULL
                        ORDER BY pc2.id DESC
                        LIMIT 1
                    )
                    LEFT JOIN canvass_quotes cq ON cq.id = pc.selected_quote_id
                    LEFT JOIN suppliers s ON s.id = cq.supplier_id
                    WHERE pri.purchase_request_id = ?
                    ORDER BY pri.id ASC
                ");
                $itemsStmt->execute([$req['id']]);
                $req['items'] = array_values(array_filter(array_map(function ($item) {
                    $item['allocated_quantity'] = (float) ($item['allocated_quantity'] ?? 0);
                    $item['remaining_quantity'] = max(0, (float) $item['quantity'] - $item['allocated_quantity']);
                    return $item;
                }, $itemsStmt->fetchAll()), function ($item) {
                    return (float) $item['remaining_quantity'] > 0.0001;
                }));
                $req['remaining_item_count'] = count($req['items']);
            }

            Response::success($requests, 'PRS inbox retrieved for PO creation');
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
                WHERE pr.status IN ('pending', 'approved', 'partially_converted')
                  AND (pri.ingredient_id IS NOT NULL OR pri.mro_item_id IS NOT NULL)
                  AND pri.quantity > COALESCE((
                      SELECT SUM(prip.quantity)
                      FROM purchase_request_item_po prip
                      JOIN purchase_orders linked_po ON linked_po.id = prip.po_id
                      WHERE prip.purchase_request_item_id = pri.id
                        AND linked_po.status NOT IN ('cancelled', 'rejected')
                  ), 0) + 0.0001
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

function validatePRCreateData($data, $requirePhysicalAudit = false) {
    if (empty($data['items']) || !is_array($data['items']) || count($data['items']) === 0) {
        Response::error('At least one item is required', 400);
    }

    $documentPurpose = trim((string) ($data['purpose'] ?? ''));
    if ($documentPurpose === '') {
        Response::error('Purpose/reason is required', 400);
    }

    $seenItems = [];
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

        $itemKey = $hasIngredient
            ? 'ingredient:' . (int) $item['ingredient_id']
            : 'mro:' . (int) $item['mro_item_id'];
        if (isset($seenItems[$itemKey])) {
            $firstLine = $seenItems[$itemKey];
            $itemName = trim((string) ($item['item_description'] ?? 'This item')) ?: 'This item';
            Response::error("Line {$lineNo}: {$itemName} is already on line {$firstLine}. Keep one row and update its quantity.", 400);
        }
        $seenItems[$itemKey] = $lineNo;

        if (trim((string)($item['item_description'] ?? '')) === '') {
            Response::error("Line {$lineNo}: item description is required", 400);
        }

        try {
            // One PR line is deliberately capped below the DECIMAL column limit.
            // Values above this point are planning mistakes and must be split or
            // reviewed instead of silently creating a multi-million-unit request.
            $validatedQty = hfParseBusinessDecimal(
                $item['quantity'] ?? null,
                "Line {$lineNo} requested quantity",
                0.01,
                1000000.00,
                2
            );
        } catch (InvalidArgumentException $error) {
            Response::error($error->getMessage(), 400);
        }

        $hasEstimatedPrice = array_key_exists('estimated_unit_price', $item)
            && $item['estimated_unit_price'] !== ''
            && $item['estimated_unit_price'] !== null;
        if ($hasEstimatedPrice) {
            try {
                $validatedPrice = hfParseBusinessDecimal(
                    $item['estimated_unit_price'],
                    "Line {$lineNo} estimated unit price",
                    0.00,
                    999999.99,
                    2
                );
            } catch (InvalidArgumentException $error) {
                Response::error($error->getMessage(), 400);
            }
            $estimatedTotal = $validatedQty * $validatedPrice;
            if (!is_finite($estimatedTotal) || $estimatedTotal > 9999999999.99) {
                Response::error("Line {$lineNo}: estimated total must not exceed PHP 9,999,999,999.99", 400);
            }
        }

        if (trim((string)($item['unit'] ?? '')) === '') {
            Response::error("Line {$lineNo}: unit is required", 400);
        }

        $linePurpose = trim((string) ($item['purpose'] ?? $documentPurpose));
        if ($linePurpose === '') {
            Response::error("Line {$lineNo}: purpose/reason is required", 400);
        }
        if ($linePurpose !== $documentPurpose) {
            Response::error("Line {$lineNo}: its reason does not match the request reason. Refresh the form and review the calculated quantity.", 409);
        }

        $hasAudit = array_key_exists('audited_stock', $item) && $item['audited_stock'] !== '' && $item['audited_stock'] !== null;
        if ($requirePhysicalAudit && !$hasAudit) {
            Response::error("Line {$lineNo}: enter the actual quantity counted on the shelf", 400);
        }
        if ($hasAudit) {
            try {
                hfParseBusinessDecimal(
                    $item['audited_stock'],
                    "Line {$lineNo} physical count",
                    0.00,
                    9999999.999,
                    3
                );
            } catch (InvalidArgumentException $error) {
                Response::error($error->getMessage(), 400);
            }
        }
    }
}

function applyDocumentPurposeToItems(&$items, $documentPurpose, $rejectMismatch = true) {
    $documentPurpose = trim((string) $documentPurpose);
    foreach ($items as $index => &$item) {
        $linePurpose = trim((string) ($item['purpose'] ?? ''));
        if ($rejectMismatch && $linePurpose !== '' && $linePurpose !== $documentPurpose) {
            $lineNo = $index + 1;
            throw new InvalidArgumentException("Line {$lineNo}: its reason does not match the request reason. Refresh the form and review the calculated quantity.");
        }
        $item['purpose'] = $documentPurpose;
    }
    unset($item);
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
    $stmt = $db->prepare("
        SELECT id, ingredient_id, mro_item_id, item_description, quantity, unit,
               estimated_unit_price, purpose, notes, audited_stock, audit_reason,
               target_stock_at_request, calculated_quantity, calculation_basis
        FROM purchase_request_items
        WHERE purchase_request_id = ?
        ORDER BY id
    ");
    $stmt->execute([$prId]);
    return $stmt->fetchAll();
}

/**
 * Save the shelf count and lower inventory when physical stock is below the
 * saved balance. Stock increases must still go through receiving.
 */
function applyPRPhysicalAudit($db, &$items, $currentUser) {
    foreach ($items as $index => &$item) {
        $lineNo = $index + 1;
        if (!array_key_exists('audited_stock', $item) || $item['audited_stock'] === '' || $item['audited_stock'] === null) {
            throw new InvalidArgumentException("Line {$lineNo}: enter the actual quantity counted on the shelf");
        }

        $auditedStock = (float) $item['audited_stock'];
        if ($auditedStock < 0) {
            throw new InvalidArgumentException("Line {$lineNo}: physical count cannot be negative");
        }

        $isIngredient = !empty($item['ingredient_id']);
        $table = $isIngredient ? 'ingredients' : 'mro_items';
        $itemId = $isIngredient ? (int) $item['ingredient_id'] : (int) ($item['mro_item_id'] ?? 0);
        if (!$itemId) {
            throw new InvalidArgumentException("Line {$lineNo}: select an approved inventory item");
        }

        $stockStmt = $db->prepare("SELECT * FROM {$table} WHERE id = ? AND is_active = 1 FOR UPDATE");
        $stockStmt->execute([$itemId]);
        $stockItem = $stockStmt->fetch();
        if (!$stockItem) {
            throw new InvalidArgumentException("Line {$lineNo}: inventory item is no longer active");
        }

        $systemStock = (float) $stockItem['current_stock'];
        $variance = $auditedStock - $systemStock;
        $reason = trim((string) ($item['audit_reason'] ?? ''));
        $unit = (string) ($stockItem['unit_of_measure'] ?? $item['unit'] ?? 'units');
        $countedLabel = rtrim(rtrim(number_format($auditedStock, 3, '.', ','), '0'), '.');
        $savedLabel = rtrim(rtrim(number_format($systemStock, 3, '.', ','), '0'), '.');

        if ($variance > 0.0005) {
            throw new InvalidArgumentException("Line {$lineNo}: counted {$countedLabel} {$unit}, but the saved balance is {$savedLabel} {$unit}. Record the missing stock and its batch details before continuing.");
        }
        if (abs($variance) > 0.0005 && $reason === '') {
            throw new InvalidArgumentException("Line {$lineNo}: explain why the shelf count differs from the saved balance of {$savedLabel} {$unit}");
        }

        $stockBasedPurposes = ['Stock below reorder point', 'Safety stock replenishment', 'Emergency replacement'];
        $maximumStock = (float) ($stockItem['maximum_stock'] ?? 0);
        $reorderPoint = (float) ($stockItem['reorder_point'] ?? 0);
        $minimumStock = (float) ($stockItem['minimum_stock'] ?? 0);
        $effectiveReorder = max($reorderPoint, $minimumStock);
        $targetStock = $maximumStock > $effectiveReorder
            ? $maximumStock
            : ($effectiveReorder > 0 ? $effectiveReorder * 2 : 0);
        if (in_array((string) ($item['purpose'] ?? ''), $stockBasedPurposes, true)) {
            $adjustedRequest = $targetStock - $auditedStock;
            if ($adjustedRequest <= 0.0005) {
                throw new InvalidArgumentException("Line {$lineNo}: the physical count is already at or above the replenishment target. Remove this item from the PRS or correct its stock settings.");
            }
            if ($adjustedRequest > 1000000.00) {
                throw new InvalidArgumentException(
                    "Line {$lineNo}: the calculated replenishment quantity exceeds 1,000,000. Check the item's maximum-stock setting before submitting this PRS."
                );
            }
            $calculatedQuantity = round($adjustedRequest, 2);
            $submittedQuantity = round((float) ($item['quantity'] ?? 0), 2);
            if (abs($submittedQuantity - $calculatedQuantity) > 0.005) {
                $calculatedLabel = rtrim(rtrim(number_format($calculatedQuantity, 2, '.', ','), '0'), '.');
                throw new InvalidArgumentException(
                    "Line {$lineNo}: the entered quantity does not match the stock calculation. Target {$targetStock} minus counted {$countedLabel} equals {$calculatedLabel} {$unit}. Refresh the form and submit the displayed value."
                );
            }
            $item['quantity'] = $calculatedQuantity;
            $item['target_stock_at_request'] = $targetStock;
            $item['calculated_quantity'] = $calculatedQuantity;
            $item['calculation_basis'] = "Target {$targetStock} {$unit} - counted {$auditedStock} {$unit}";
        } else {
            $baseline = max($maximumStock, $reorderPoint, $minimumStock, $systemStock, $auditedStock);
            $manualLimit = min(100000.00, max(1000.00, $baseline * 10));
            $submittedQuantity = (float) ($item['quantity'] ?? 0);
            if ($submittedQuantity > $manualLimit + 0.0005) {
                $limitLabel = rtrim(rtrim(number_format($manualLimit, 2, '.', ','), '0'), '.');
                throw new InvalidArgumentException(
                    "Line {$lineNo}: {$submittedQuantity} {$unit} is above the current review limit of {$limitLabel} {$unit}. Update the item's stock plan first if this larger forecast is legitimate."
                );
            }
            $item['target_stock_at_request'] = $targetStock > 0 ? $targetStock : null;
            $item['calculated_quantity'] = null;
            $item['calculation_basis'] = "Manual forecast; review limit {$manualLimit} {$unit}";
        }

        if ($variance < -0.0005) {
            if ($isIngredient) {
                reduceIngredientBatchesToQuantity($db, $stockItem, $auditedStock, $currentUser, $reason);
            }

            $updateStmt = $db->prepare("UPDATE {$table} SET current_stock = ?, updated_at = NOW() WHERE id = ?");
            $updateStmt->execute([$auditedStock, $itemId]);

            $transactionType = 'physical_adjust';
            $itemType = $isIngredient ? 'ingredient' : 'mro';
            $transactionCode = generateCode('TX');
            $transactionStmt = $db->prepare("
                INSERT INTO inventory_transactions
                (transaction_code, transaction_type, item_type, item_id, quantity,
                 unit_of_measure, quantity_before, quantity_after, reference_type,
                 performed_by, reason)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'purchase_request_audit', ?, ?)
            ");
            $transactionStmt->execute([
                $transactionCode,
                $transactionType,
                $itemType,
                $itemId,
                $variance,
                $stockItem['unit_of_measure'],
                $systemStock,
                $auditedStock,
                $currentUser['user_id'],
                "PRS physical count: {$reason} (Saved: {$systemStock}, Counted: {$auditedStock})"
            ]);

            logAudit(
                $currentUser['user_id'],
                'prs_physical_count',
                $table,
                $itemId,
                ['current_stock' => $systemStock],
                ['current_stock' => $auditedStock, 'reason' => $reason]
            );
        }

        $item['system_stock_before'] = $systemStock;
        $item['audited_stock'] = $auditedStock;
        $item['stock_variance'] = $variance;
        $item['audit_reason'] = $reason !== '' ? $reason : null;
        $item['audited_by'] = $currentUser['user_id'];
        $item['audited_at'] = date('Y-m-d H:i:s');

        if (!empty($item['id'])) {
            $auditStmt = $db->prepare("
                UPDATE purchase_request_items
                SET quantity = ?, estimated_total = CASE
                        WHEN estimated_unit_price IS NULL THEN NULL
                        ELSE ROUND(? * estimated_unit_price, 2)
                    END,
                    system_stock_before = ?, audited_stock = ?, stock_variance = ?,
                    audit_reason = ?, audited_by = ?, audited_at = ?,
                    target_stock_at_request = ?, calculated_quantity = ?, calculation_basis = ?
                WHERE id = ?
            ");
            $auditStmt->execute([
                $item['quantity'],
                $item['quantity'],
                $item['system_stock_before'],
                $item['audited_stock'],
                $item['stock_variance'],
                $item['audit_reason'],
                $item['audited_by'],
                $item['audited_at'],
                $item['target_stock_at_request'],
                $item['calculated_quantity'],
                $item['calculation_basis'],
                $item['id']
            ]);
        }
    }
    unset($item);
}

function findDuplicatePendingPR($db, $department, $fingerprint, $excludeId = null) {
    if (!$fingerprint) {
        return null;
    }

    $sql = "SELECT id, pr_number FROM purchase_requests WHERE department = ? AND status IN ('pending', 'approved', 'partially_converted') AND request_fingerprint = ?";
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
          AND pr.status IN ('pending', 'approved', 'partially_converted')
          AND (" . implode(' OR ', $conditions) . ")
          AND pri.quantity > COALESCE((
              SELECT SUM(prip.quantity)
              FROM purchase_request_item_po prip
              JOIN purchase_orders linked_po ON linked_po.id = prip.po_id
              WHERE prip.purchase_request_item_id = pri.id
                AND linked_po.status NOT IN ('cancelled', 'rejected')
          ), 0) + 0.0001
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
        SELECT COUNT(DISTINCT po.id)
        FROM purchase_orders po
        WHERE po.status NOT IN ('cancelled', 'rejected')
          AND (
              po.purchase_request_id = ?
              OR EXISTS (
                  SELECT 1
                  FROM purchase_order_items poi
                  JOIN purchase_request_items pri ON pri.id = poi.purchase_request_item_id
                  WHERE poi.po_id = po.id AND pri.purchase_request_id = ?
              )
              OR EXISTS (
                  SELECT 1
                  FROM purchase_request_item_po prip
                  JOIN purchase_request_items pri ON pri.id = prip.purchase_request_item_id
                  WHERE prip.po_id = po.id AND pri.purchase_request_id = ?
              )
          )
    ");
    $stmt->execute([$prId, $prId, $prId]);
    return (int) $stmt->fetchColumn();
}

function replacePRItems($db, $prId, $items, $defaultPurpose = null) {
    $deleteStmt = $db->prepare("DELETE FROM purchase_request_items WHERE purchase_request_id = ?");
    $deleteStmt->execute([$prId]);

    $itemStmt = $db->prepare("
        INSERT INTO purchase_request_items
        (purchase_request_id, ingredient_id, mro_item_id, item_description, quantity, unit,
         estimated_unit_price, estimated_total, purpose, notes, system_stock_before,
         audited_stock, stock_variance, audit_reason, audited_by, audited_at,
         target_stock_at_request, calculated_quantity, calculation_basis)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
            $item['notes'] ?? null,
            $item['system_stock_before'] ?? null,
            $item['audited_stock'] ?? null,
            $item['stock_variance'] ?? null,
            $item['audit_reason'] ?? null,
            $item['audited_by'] ?? null,
            $item['audited_at'] ?? null,
            $item['target_stock_at_request'] ?? null,
            $item['calculated_quantity'] ?? null,
            $item['calculation_basis'] ?? null
        ]);
    }
}

/**
 * Handle POST requests - Create Purchase Request
 */
function handlePost($db, $action, $currentUser) {
    switch ($action) {
        case 'create':
            requireActionRole($currentUser, ['warehouse_raw', 'general_manager'], 'Only Warehouse Raw can create Purchase Request Slips');
            Response::error('New Purchase Request Slips are retired. Use Low Stock Validation to confirm the shelf count and send the shortage directly to Purchasing.', 410);

            $data = getRequestBody();

            rejectSupplierFieldsInPR($data);

            $priority = $data['priority'] ?? 'normal';
            if (!in_array($priority, ['low', 'normal', 'high', 'urgent'])) {
                Response::error('Invalid priority', 400);
            }

            $status = $data['status'] ?? 'pending';
            if (!in_array($status, ['draft', 'pending'])) {
                Response::error('Invalid Purchase Request status', 400);
            }

            validatePRCreateData($data, $status === 'pending');
            applyDocumentPurposeToItems($data['items'], $data['purpose'] ?? '', false);
            $fingerprint = $status === 'draft' ? buildPRFingerprint($data['items']) : null;

            // V4.1: Department derived from the creator's role
            $departmentMap = [
                'warehouse_raw' => 'warehouse_raw',
                'general_manager'  => 'general',
            ];
            $department = $departmentMap[$currentUser['role']] ?? 'warehouse_raw';

            if ($status === 'pending') {
                $overlap = findPendingPRWithOverlappingItems($db, $department, $data['items']);
                if ($overlap) {
                    Response::error('Pending Purchase Request already exists for one or more items (' . $overlap['pr_number'] . '). Please update that request instead.', 409, [
                        'duplicate_pr_id' => (int) $overlap['id'],
                        'duplicate_pr_number' => $overlap['pr_number']
                    ]);
                }
            }

            $db->beginTransaction();

            try {
                if ($status === 'pending') {
                    applyPRPhysicalAudit($db, $data['items'], $currentUser);
                    $fingerprint = buildPRFingerprint($data['items']);
                    $duplicate = findDuplicatePendingPR($db, $department, $fingerprint);
                    if ($duplicate) {
                        $db->rollBack();
                        Response::error('Duplicate pending Purchase Request already exists (' . $duplicate['pr_number'] . '). Please update that request instead.', 409, [
                            'duplicate_pr_id' => (int) $duplicate['id'],
                            'duplicate_pr_number' => $duplicate['pr_number']
                        ]);
                    }
                }

                // Generate PRS number
                $today = date('Ymd');
                $codeStmt = $db->prepare("SELECT COUNT(*) as count FROM purchase_requests WHERE pr_number LIKE ?");
                $codeStmt->execute(["PRS-{$today}-%"]);
                $count = (int) $codeStmt->fetch()['count'] + 1;
                $prNumber = "PRS-{$today}-" . str_pad($count, 3, '0', STR_PAD_LEFT);

                // Insert PR
                $stmt = $db->prepare("
                    INSERT INTO purchase_requests 
                    (pr_number, requested_by, department, priority, needed_by_date, purpose, notes, status, request_fingerprint)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $prNumber,
                    $currentUser['user_id'],
                    $department,
                    $priority,
                    $data['needed_by_date'] ?? null,
                    $data['purpose'] ?? null,
                    $data['notes'] ?? null,
                    $status,
                    $fingerprint
                ]);

                $prId = $db->lastInsertId();
                addPRStatusHistory($db, $prId, null, $status, $currentUser['user_id'], $status === 'draft' ? 'PRS draft saved' : 'Submitted to Purchaser inbox');

                replacePRItems($db, $prId, $data['items'], $data['purpose'] ?? null);

                if ($status === 'pending') {
                    createProcurementNotification(
                        $db,
                        'purchaser',
                        'prs_submitted_for_supplier_review',
                        'New PRS for supplier review',
                        'Warehouse Raw submitted ' . $prNumber . '. Review the registered supplier prices and prepare the formal PO.',
                        'purchase_request',
                        $prId
                    );
                }

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
                ], $status === 'draft' ? 'Purchase Request Slip draft saved' : 'Purchase Request Slip submitted to Purchaser', 201);

            } catch (InvalidArgumentException $e) {
                $db->rollBack();
                Response::error($e->getMessage(), 400);
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
    if (in_array($action, ['update', 'submit', 'reopen'], true)) {
        Response::error('Old Purchase Request Slips are read-only history. Confirm current shortages through Low Stock Validation.', 410);
    }
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
            applyDocumentPurposeToItems($data['items'], $data['purpose'] ?? '', false);

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
            requireActionRole($currentUser, ['warehouse_raw'], 'Only Warehouse Raw staff can submit Purchase Request Slips');

            if ($current['requested_by'] != $currentUser['user_id']) {
                Response::error('You can only submit your own Purchase Request drafts', 403);
            }

            if ($current['status'] !== 'draft') {
                Response::error('Only draft Purchase Request Slips can be submitted. Current status: ' . $current['status'], 400);
            }

            $pendingItems = getRequestItemsById($db, $id);
            if (!empty($data['items']) && is_array($data['items'])) {
                $submittedAudits = [];
                foreach ($data['items'] as $auditItem) {
                    if (!empty($auditItem['id'])) {
                        $submittedAudits[(int) $auditItem['id']] = $auditItem;
                    }
                }
                foreach ($pendingItems as &$pendingItem) {
                    $itemId = (int) ($pendingItem['id'] ?? 0);
                    if (isset($submittedAudits[$itemId])) {
                        $pendingItem['audited_stock'] = $submittedAudits[$itemId]['audited_stock'] ?? null;
                        $pendingItem['audit_reason'] = $submittedAudits[$itemId]['audit_reason'] ?? null;
                    }
                }
                unset($pendingItem);
            }
            applyDocumentPurposeToItems($pendingItems, $current['purpose'] ?? '', false);
            validatePRCreateData([
                'items' => $pendingItems,
                'purpose' => $current['purpose'] ?? null
            ], true);
            $overlap = findPendingPRWithOverlappingItems($db, $current['department'], $pendingItems, $id);
            if ($overlap) {
                Response::error('Pending Purchase Request already exists for one or more items (' . $overlap['pr_number'] . '). Please update that request instead.', 409, [
                    'duplicate_pr_id' => (int) $overlap['id'],
                    'duplicate_pr_number' => $overlap['pr_number']
                ]);
            }

            $db->beginTransaction();
            try {
                applyPRPhysicalAudit($db, $pendingItems, $currentUser);
                $fingerprint = buildPRFingerprint($pendingItems);
                $duplicate = findDuplicatePendingPR($db, $current['department'], $fingerprint, $id);
                if ($duplicate) {
                    $db->rollBack();
                    Response::error('Duplicate pending Purchase Request already exists (' . $duplicate['pr_number'] . '). Please update that request instead.', 409, [
                        'duplicate_pr_id' => (int) $duplicate['id'],
                        'duplicate_pr_number' => $duplicate['pr_number']
                    ]);
                }

                $stmt = $db->prepare("
                    UPDATE purchase_requests
                    SET status = 'pending',
                        request_fingerprint = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$fingerprint, $id]);
                addPRStatusHistory($db, $id, 'draft', 'pending', $currentUser['user_id'], 'Physical count completed and submitted to Purchaser inbox');
                createProcurementNotification(
                    $db,
                    'purchaser',
                    'prs_submitted_for_supplier_review',
                    'New PRS for supplier review',
                    'Warehouse Raw submitted ' . ($current['pr_number'] ?? ('PRS #' . $id)) . ' after a physical stock count. Review the registered supplier prices and prepare the formal PO.',
                    'purchase_request',
                    $id
                );

                $db->commit();
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                Response::error($e->getMessage(), 400);
            }

            logAudit($currentUser['user_id'], 'SUBMIT', 'purchase_requests', $id,
                ['status' => 'draft'],
                ['status' => 'pending']
            );

            Response::success(['id' => $id, 'status' => 'pending'], 'Purchase Request Slip submitted to Purchaser');
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

        default:
            Response::error('Invalid action', 400);
    }
}
