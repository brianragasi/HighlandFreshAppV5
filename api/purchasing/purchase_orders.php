<?php
/**
 * Highland Fresh System - Purchase Orders API
 *
 * GET - List POs, get details, get items
 * POST - Create PO, add items
 * PUT - Update PO, update status, approve
 *
 * @package HighlandFresh
 * @version 4.0
 */

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/helpers/purchase_order_pdf.php';

// Require Purchaser or GM role
$currentUser = Auth::requireRole(['purchaser', 'general_manager', 'warehouse_raw']);

$action = getParam('action', 'list');

try {
    $db = Database::getInstance()->getConnection();
    ensurePRConversionSupport($db);

    switch ($requestMethod) {
        case 'GET':
            handleGet($db, $action);
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
    error_log("Purchase Orders API Error: " . $e->getMessage());
    Response::error('Server error: ' . $e->getMessage(), 500);
}

function handleGet($db, $action) {
    switch ($action) {
        case 'list':
            $status = getParam('status');
            $supplier_id = getParam('supplier_id');
            $payment_status = getParam('payment_status');
            $search = getParam('search');
            $date_from = getParam('date_from');
            $date_to = getParam('date_to');
            $page = max(1, (int) getParam('page', 1));
            $limit = min(50, max(10, (int) getParam('limit', 20)));
            $offset = ($page - 1) * $limit;

            $where = "1=1";
            $params = [];

            if (getParam('receiving_pending')) {
                $where .= " AND po.status IN ('approved', 'ordered', 'partial_received')";
            } elseif ($status) {
                $where .= " AND po.status = ?";
                $params[] = $status;
            }

            if ($supplier_id) {
                $where .= " AND po.supplier_id = ?";
                $params[] = $supplier_id;
            }

            if ($payment_status) {
                $where .= " AND po.payment_status = ?";
                $params[] = $payment_status;
            }

            if ($search) {
                $where .= " AND (po.po_number LIKE ? OR s.supplier_name LIKE ?)";
                $searchTerm = "%$search%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            if ($date_from) {
                $where .= " AND po.order_date >= ?";
                $params[] = $date_from;
            }

            if ($date_to) {
                $where .= " AND po.order_date <= ?";
                $params[] = $date_to;
            }

            // Get total count
            $countStmt = $db->prepare("
                SELECT COUNT(*) as total
                FROM purchase_orders po
                JOIN suppliers s ON po.supplier_id = s.id
                WHERE $where
            ");
            $countStmt->execute($params);
            $total = (int) $countStmt->fetch()['total'];

            // Get paginated results
            $params[] = $limit;
            $params[] = $offset;

            $stmt = $db->prepare("
                SELECT
                    po.*,
                    s.supplier_name,
                    s.supplier_code,
                    u.full_name as created_by_name,
                    ua.full_name as approved_by_name,
                    mr.requisition_code,
                    pr.pr_number,
                    pr.purpose as pr_purpose,
                    (SELECT COUNT(*) FROM purchase_order_items WHERE po_id = po.id) as item_count,
                    (SELECT COUNT(*)
                     FROM purchase_orders grouped_po
                     WHERE grouped_po.purchase_request_id = po.purchase_request_id
                       AND grouped_po.status = 'draft') as draft_group_count,
                    (SELECT SUM(quantity_received) FROM purchase_order_items WHERE po_id = po.id) as total_received
                FROM purchase_orders po
                JOIN suppliers s ON po.supplier_id = s.id
                LEFT JOIN users u ON po.created_by = u.id
                LEFT JOIN users ua ON po.approved_by = ua.id
                LEFT JOIN material_requisitions mr ON po.requisition_id = mr.id
                LEFT JOIN purchase_requests pr ON po.purchase_request_id = pr.id
                WHERE $where
                ORDER BY po.order_date DESC, po.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute($params);
            $orders = $stmt->fetchAll();

            Response::paginated($orders, $total, $page, $limit, 'Purchase orders retrieved');
            break;

        case 'detail':
            $id = getParam('id');
            if (!$id) {
                Response::error('PO ID required', 400);
            }

            $stmt = $db->prepare("
                SELECT
                    po.*,
                    s.supplier_name,
                    s.supplier_code,
                    s.contact_person as supplier_contact,
                    s.phone as supplier_phone,
                    s.email as supplier_email,
                    s.address as supplier_address,
                    s.payment_terms as supplier_terms,
                    u.full_name as created_by_name,
                    ua.full_name as approved_by_name,
                    pr.pr_number,
                    pr.purpose as pr_purpose,
                    pr.requested_by as pr_requested_by,
                    pr.approved_by as pr_approved_by,
                    pr.approver_name as pr_approver_name,
                    ur.full_name as pr_requested_by_name,
                    ug.full_name as pr_approved_by_name
                FROM purchase_orders po
                JOIN suppliers s ON po.supplier_id = s.id
                LEFT JOIN users u ON po.created_by = u.id
                LEFT JOIN users ua ON po.approved_by = ua.id
                LEFT JOIN purchase_requests pr ON po.purchase_request_id = pr.id
                LEFT JOIN users ur ON pr.requested_by = ur.id
                LEFT JOIN users ug ON pr.approved_by = ug.id
                WHERE po.id = ?
            ");
            $stmt->execute([$id]);
            $order = $stmt->fetch();

            if (!$order) {
                Response::error('Purchase order not found', 404);
            }

            // Get items
            $itemsStmt = $db->prepare("
                SELECT
                    poi.*,
                    i.ingredient_name,
                    i.ingredient_code,
                    COALESCE(i.is_perishable, 1) as ingredient_is_perishable,
                    m.item_name as mro_item_name
                    ,m.item_code as mro_item_code
                    ,COALESCE(m.is_perishable, 0) as mro_is_perishable
                FROM purchase_order_items poi
                LEFT JOIN ingredients i ON poi.ingredient_id = i.id
                LEFT JOIN mro_items m ON poi.mro_item_id = m.id
                WHERE poi.po_id = ?
                ORDER BY poi.id ASC
            ");
            $itemsStmt->execute([$id]);
            $order['items'] = $itemsStmt->fetchAll();
            $order['current_general_manager_name'] = getCurrentGeneralManagerName($db);

            if (tableExists($db, 'po_receiving_log')) {
                $receiverStmt = $db->prepare("
                    SELECT
                        u.full_name as received_by_name,
                        MIN(prl.received_at) as first_received_at,
                        MAX(prl.received_at) as latest_received_at
                    FROM po_receiving_log prl
                    LEFT JOIN users u ON prl.received_by = u.id
                    WHERE prl.po_id = ?
                    GROUP BY prl.received_by, u.full_name
                    ORDER BY latest_received_at DESC
                    LIMIT 1
                ");
                $receiverStmt->execute([$id]);
                $receiver = $receiverStmt->fetch();
                if ($receiver) {
                    $order['received_by_name'] = $receiver['received_by_name'];
                    $order['first_received_at'] = $receiver['first_received_at'];
                    $order['latest_received_at'] = $receiver['latest_received_at'];
                }
            }

            if (tableExists($db, 'receiving_reports')) {
                $rrStmt = $db->prepare("
                    SELECT
                        rr.*,
                        u.full_name as received_by_name,
                        uv.full_name as verified_by_name
                    FROM receiving_reports rr
                    LEFT JOIN users u ON rr.received_by = u.id
                    LEFT JOIN users uv ON rr.verified_by = uv.id
                    WHERE rr.po_id = ?
                    ORDER BY rr.received_at DESC, rr.id DESC
                    LIMIT 1
                ");
                $rrStmt->execute([$id]);
                $latestRR = $rrStmt->fetch();
                if ($latestRR) {
                    $order['latest_receiving_report'] = $latestRR;
                    $order['rr_number'] = $latestRR['rr_number'];
                    $order['delivery_reference'] = $latestRR['delivery_reference'] ?? null;
                    $order['invoice_number'] = $latestRR['invoice_number'] ?? null;
                    $order['vehicle_plate'] = $latestRR['vehicle_plate'] ?? null;
                    $order['driver_name'] = $latestRR['driver_name'] ?? null;
                    $order['driver_notes'] = $latestRR['driver_notes'] ?? null;
                    $order['delivery_condition'] = $latestRR['delivery_condition'] ?? null;
                    $order['received_by_name'] = $order['received_by_name'] ?? ($latestRR['received_by_name'] ?? null);
                    $order['latest_received_at'] = $order['latest_received_at'] ?? ($latestRR['received_at'] ?? null);
                }
            }

            Response::success($order, 'Purchase order details retrieved');
            break;

        case 'rr_detail':
            getReceivingReportDetailForPurchasing($db);
            break;

        case 'next_number':
            // Generate next PO number
            $stmt = $db->query("
                SELECT po_number FROM purchase_orders
                ORDER BY id DESC LIMIT 1
            ");
            $last = $stmt->fetch();

            if ($last) {
                $lastNum = (int) $last['po_number'];
                $nextNum = $lastNum + 1;
            } else {
                $nextNum = 5252;
            }

            Response::success(['next_number' => (string) $nextNum], 'Next PO number');
            break;

        default:
            Response::error('Invalid action', 400);
    }
}

function tableExists($db, $tableName) {
    $stmt = $db->prepare("
        SELECT 1
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
        LIMIT 1
    ");
    $stmt->execute([$tableName]);
    return (bool) $stmt->fetchColumn();
}

function ensurePRConversionSupport($db) {
    $db->exec("
        ALTER TABLE `purchase_orders`
        MODIFY COLUMN `status` ENUM('draft','pending','approved','rejected','partial_received','received','closed','ordered','cancelled') DEFAULT 'pending'
    ");

    if (!auditColumnExists($db, 'purchase_orders', 'approval_remarks')) {
        $db->exec("ALTER TABLE `purchase_orders` ADD COLUMN `approval_remarks` TEXT DEFAULT NULL AFTER `approved_at`");
    }

    if (!auditColumnExists($db, 'purchase_orders', 'internal_review_notes')) {
        $db->exec("ALTER TABLE `purchase_orders` ADD COLUMN `internal_review_notes` TEXT DEFAULT NULL AFTER `notes`");
    }

    if (!auditColumnExists($db, 'purchase_orders', 'rejection_reason')) {
        $db->exec("ALTER TABLE `purchase_orders` ADD COLUMN `rejection_reason` TEXT DEFAULT NULL AFTER `approval_remarks`");
    }

    // Add approver_name column to purchase_orders for permanent record
    if (!auditColumnExists($db, 'purchase_orders', 'approver_name')) {
        $db->exec("ALTER TABLE `purchase_orders` ADD COLUMN `approver_name` VARCHAR(100) DEFAULT NULL AFTER `approved_at`");
    }

    if (!auditColumnExists($db, 'purchase_orders', 'delivery_details')) {
        $db->exec("ALTER TABLE `purchase_orders` ADD COLUMN `delivery_details` TEXT DEFAULT NULL AFTER `expected_delivery`");
    }

    if (!auditColumnExists($db, 'purchase_orders', 'sent_to_supplier_at')) {
        $db->exec("ALTER TABLE `purchase_orders` ADD COLUMN `sent_to_supplier_at` DATETIME DEFAULT NULL AFTER `approved_at`");
    }

    if (!auditColumnExists($db, 'purchase_orders', 'sent_to_supplier_by')) {
        $db->exec("ALTER TABLE `purchase_orders` ADD COLUMN `sent_to_supplier_by` INT(11) DEFAULT NULL AFTER `sent_to_supplier_at`");
    }

    ensurePurchaseOrderEmailSupport($db);

    if (!auditColumnExists($db, 'purchase_order_items', 'purchase_request_item_id')) {
        $db->exec("ALTER TABLE `purchase_order_items` ADD COLUMN `purchase_request_item_id` INT(11) DEFAULT NULL AFTER `po_id`");
    }

    ensureWarehouseReceivingSupport($db);
    ensurePriceHistorySupport($db);
    ensureProcurementNotificationSupport($db);
    ensurePaymentTrackingSupport($db);

    if (!tableExists($db, 'purchase_requests')) {
        return;
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
}

function ensurePaymentTrackingSupport($db) {
    if (!auditColumnExists($db, 'purchase_orders', 'payment_terms')) {
        $db->exec("ALTER TABLE `purchase_orders` ADD COLUMN `payment_terms` ENUM('cash','credit_7','credit_15','credit_30','credit_45','credit_60') DEFAULT 'cash' AFTER `payment_status`");
    }

    if (!auditColumnExists($db, 'purchase_orders', 'due_date')) {
        $db->exec("ALTER TABLE `purchase_orders` ADD COLUMN `due_date` DATE DEFAULT NULL AFTER `payment_terms`");
    }

    if (!auditColumnExists($db, 'purchase_orders', 'amount_paid')) {
        $db->exec("ALTER TABLE `purchase_orders` ADD COLUMN `amount_paid` DECIMAL(12,2) DEFAULT 0.00 AFTER `total_amount`");
    }

    if (!auditColumnExists($db, 'purchase_orders', 'last_payment_date')) {
        $db->exec("ALTER TABLE `purchase_orders` ADD COLUMN `last_payment_date` DATE DEFAULT NULL AFTER `amount_paid`");
    }

    $db->exec("ALTER TABLE `purchase_orders` MODIFY COLUMN `payment_status` ENUM('unpaid','partial','paid','cancelled') DEFAULT 'unpaid'");
}

function ensureWarehouseReceivingSupport($db) {
    if (!auditColumnExists($db, 'purchase_order_items', 'quantity_rejected')) {
        $db->exec("ALTER TABLE `purchase_order_items` ADD COLUMN `quantity_rejected` DECIMAL(12,2) DEFAULT 0.00 AFTER `quantity_received`");
    }

    if (!auditColumnExists($db, 'purchase_order_items', 'rejection_reason')) {
        $db->exec("ALTER TABLE `purchase_order_items` ADD COLUMN `rejection_reason` VARCHAR(255) NULL AFTER `quantity_rejected`");
    }

    if (!auditColumnExists($db, 'ingredient_batches', 'rr_id')) {
        $db->exec("ALTER TABLE `ingredient_batches` ADD COLUMN `rr_id` INT(11) NULL AFTER `po_id`");
    }

    if (!auditColumnExists($db, 'ingredient_batches', 'supplier_batch_no')) {
        $db->exec("ALTER TABLE `ingredient_batches` ADD COLUMN `supplier_batch_no` VARCHAR(50) NULL AFTER `supplier_id`");
    }

    if (!auditColumnExists($db, 'ingredients', 'is_perishable')) {
        $db->exec("ALTER TABLE `ingredients` ADD COLUMN `is_perishable` TINYINT(1) NOT NULL DEFAULT 1 AFTER `shelf_life_days`");
        $db->exec("
            UPDATE `ingredients`
            SET `is_perishable` = CASE
                WHEN LOWER(CONCAT(COALESCE(ingredient_name, ''), ' ', COALESCE(storage_requirements, ''))) REGEXP 'bottle|cap|label|ribbon|cellophane|plastic|packaging' THEN 0
                ELSE 1
            END
        ");
    }

    if (!auditColumnExists($db, 'mro_items', 'is_perishable')) {
        $db->exec("ALTER TABLE `mro_items` ADD COLUMN `is_perishable` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_critical`");
    }

    if (!auditColumnExists($db, 'mro_inventory', 'po_id')) {
        $db->exec("ALTER TABLE `mro_inventory` ADD COLUMN `po_id` INT(11) NULL AFTER `mro_item_id`");
    }

    if (!auditColumnExists($db, 'mro_inventory', 'rr_id')) {
        $db->exec("ALTER TABLE `mro_inventory` ADD COLUMN `rr_id` INT(11) NULL AFTER `po_id`");
    }

    if (!auditColumnExists($db, 'mro_inventory', 'supplier_id')) {
        $db->exec("ALTER TABLE `mro_inventory` ADD COLUMN `supplier_id` INT(11) NULL AFTER `supplier_name`");
    }

    if (!auditColumnExists($db, 'mro_inventory', 'supplier_batch_no')) {
        $db->exec("ALTER TABLE `mro_inventory` ADD COLUMN `supplier_batch_no` VARCHAR(50) NULL AFTER `supplier_id`");
    }

    if (!auditColumnExists($db, 'mro_inventory', 'expiry_date')) {
        $db->exec("ALTER TABLE `mro_inventory` ADD COLUMN `expiry_date` DATE NULL AFTER `received_date`");
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS `po_receiving_log` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `po_id` INT(11) NOT NULL,
            `po_item_id` INT(11) NOT NULL,
            `quantity_accepted` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `quantity_rejected` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `rejection_reason` VARCHAR(255) DEFAULT NULL,
            `rejection_category` VARCHAR(50) DEFAULT NULL,
            `received_by` INT(11) NOT NULL,
            `received_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `notes` TEXT DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_po_receiving_log_po` (`po_id`),
            KEY `idx_po_receiving_log_item` (`po_item_id`),
            KEY `idx_po_receiving_log_received_at` (`received_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS `receiving_reports` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `rr_number` VARCHAR(30) NOT NULL,
            `po_id` INT(11) NOT NULL,
            `supplier_id` INT(11) NOT NULL,
            `received_by` INT(11) NOT NULL,
            `received_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `status` VARCHAR(40) NOT NULL DEFAULT 'pending_verification',
            `verified_by` INT(11) DEFAULT NULL,
            `verified_at` DATETIME DEFAULT NULL,
            `total_ordered` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `total_received` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `total_rejected` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `delivery_reference` VARCHAR(100) DEFAULT NULL,
            `invoice_number` VARCHAR(100) DEFAULT NULL,
            `vehicle_plate` VARCHAR(50) DEFAULT NULL,
            `driver_name` VARCHAR(100) DEFAULT NULL,
            `driver_notes` TEXT DEFAULT NULL,
            `delivery_condition` VARCHAR(100) DEFAULT NULL,
            `notes` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_receiving_reports_rr_number` (`rr_number`),
            KEY `idx_receiving_reports_po` (`po_id`),
            KEY `idx_receiving_reports_supplier` (`supplier_id`),
            KEY `idx_receiving_reports_received_at` (`received_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    foreach ([
        'delivery_reference' => "ALTER TABLE `receiving_reports` ADD COLUMN `delivery_reference` VARCHAR(100) DEFAULT NULL AFTER `total_rejected`",
        'invoice_number' => "ALTER TABLE `receiving_reports` ADD COLUMN `invoice_number` VARCHAR(100) DEFAULT NULL AFTER `delivery_reference`",
        'vehicle_plate' => "ALTER TABLE `receiving_reports` ADD COLUMN `vehicle_plate` VARCHAR(50) DEFAULT NULL AFTER `invoice_number`",
        'driver_name' => "ALTER TABLE `receiving_reports` ADD COLUMN `driver_name` VARCHAR(100) DEFAULT NULL AFTER `vehicle_plate`",
        'driver_notes' => "ALTER TABLE `receiving_reports` ADD COLUMN `driver_notes` TEXT DEFAULT NULL AFTER `driver_name`",
        'delivery_condition' => "ALTER TABLE `receiving_reports` ADD COLUMN `delivery_condition` VARCHAR(100) DEFAULT NULL AFTER `driver_notes`"
    ] as $column => $sql) {
        if (!auditColumnExists($db, 'receiving_reports', $column)) {
            $db->exec($sql);
        }
    }

    if (!auditColumnExists($db, 'receiving_reports', 'verified_by')) {
        $db->exec("ALTER TABLE `receiving_reports` ADD COLUMN `verified_by` INT(11) DEFAULT NULL AFTER `status`");
    }
    if (!auditColumnExists($db, 'receiving_reports', 'verified_at')) {
        $db->exec("ALTER TABLE `receiving_reports` ADD COLUMN `verified_at` DATETIME DEFAULT NULL AFTER `verified_by`");
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS `receiving_report_items` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `rr_id` INT(11) NOT NULL,
            `po_item_id` INT(11) NOT NULL,
            `ingredient_id` INT(11) DEFAULT NULL,
            `mro_item_id` INT(11) DEFAULT NULL,
            `item_description` VARCHAR(255) NOT NULL,
            `quantity_ordered` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `quantity_received` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `quantity_rejected` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `unit` VARCHAR(30) NOT NULL,
            `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `rejection_reason` VARCHAR(255) DEFAULT NULL,
            `rejection_notes` TEXT DEFAULT NULL,
            `batch_code` VARCHAR(50) DEFAULT NULL,
            `supplier_lot_number` VARCHAR(50) DEFAULT NULL,
            `expiry_date` DATE DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_receiving_report_items_rr` (`rr_id`),
            KEY `idx_receiving_report_items_po_item` (`po_item_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    if (!auditColumnExists($db, 'receiving_report_items', 'supplier_lot_number')) {
        $db->exec("ALTER TABLE `receiving_report_items` ADD COLUMN `supplier_lot_number` VARCHAR(50) NULL AFTER `batch_code`");
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS `supplier_rejections` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `rejection_code` VARCHAR(30) NOT NULL,
            `rr_id` INT(11) NOT NULL,
            `rr_item_id` INT(11) NOT NULL,
            `supplier_id` INT(11) NOT NULL,
            `ingredient_id` INT(11) DEFAULT NULL,
            `mro_item_id` INT(11) DEFAULT NULL,
            `item_description` VARCHAR(255) NOT NULL,
            `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `unit` VARCHAR(30) NOT NULL,
            `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `total_value` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `rejection_type` VARCHAR(50) NOT NULL DEFAULT 'other',
            `rejection_reason` TEXT DEFAULT NULL,
            `disposition` VARCHAR(50) DEFAULT 'return_to_supplier',
            `status` VARCHAR(50) DEFAULT 'pending',
            `created_by` INT(11) NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `resolved_at` DATETIME DEFAULT NULL,
            `resolved_by` INT(11) DEFAULT NULL,
            `notes` TEXT DEFAULT NULL,
            `evidence_photo_path` VARCHAR(500) DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_supplier_rejections_code` (`rejection_code`),
            KEY `idx_supplier_rejections_rr` (`rr_id`),
            KEY `idx_supplier_rejections_supplier` (`supplier_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    if (!auditColumnExists($db, 'supplier_rejections', 'evidence_photo_path')) {
        $db->exec("ALTER TABLE `supplier_rejections` ADD COLUMN `evidence_photo_path` VARCHAR(500) DEFAULT NULL AFTER `notes`");
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
    $stmt = $db->prepare("
        INSERT INTO procurement_notifications
        (target_role, notification_type, title, message, reference_type, reference_id)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$targetRole, $type, $title, $message, $referenceType, $referenceId]);
}

function ensurePurchaseOrderEmailSupport($db) {
    if (!auditColumnExists($db, 'purchase_orders', 'supplier_email_sent_to')) {
        $db->exec("ALTER TABLE `purchase_orders` ADD COLUMN `supplier_email_sent_to` VARCHAR(255) DEFAULT NULL AFTER `sent_to_supplier_by`");
    }
    if (!auditColumnExists($db, 'purchase_orders', 'last_send_attempt_at')) {
        $db->exec("ALTER TABLE `purchase_orders` ADD COLUMN `last_send_attempt_at` DATETIME DEFAULT NULL AFTER `supplier_email_sent_to`");
    }
    if (!auditColumnExists($db, 'purchase_orders', 'last_send_error')) {
        $db->exec("ALTER TABLE `purchase_orders` ADD COLUMN `last_send_error` TEXT DEFAULT NULL AFTER `last_send_attempt_at`");
    }
    if (!auditColumnExists($db, 'purchase_orders', 'email_send_in_progress')) {
        $db->exec("ALTER TABLE `purchase_orders` ADD COLUMN `email_send_in_progress` TINYINT(1) NOT NULL DEFAULT 0 AFTER `last_send_error`");
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS `purchase_order_email_attempts` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `po_id` INT(11) NOT NULL,
            `recipient_email` VARCHAR(255) NOT NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'sending',
            `error_message` TEXT DEFAULT NULL,
            `sent_by` INT(11) DEFAULT NULL,
            `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `sent_at` DATETIME DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_po_email_attempt_po` (`po_id`),
            KEY `idx_po_email_attempt_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function loadPurchaseOrderForEmail($db, $poId) {
    $stmt = $db->prepare("
        SELECT
            po.*,
            s.supplier_name,
            s.supplier_code,
            s.contact_person AS supplier_contact,
            s.phone AS supplier_phone,
            s.email AS supplier_email,
            s.address AS supplier_address,
            s.payment_terms AS supplier_terms,
            u.full_name AS created_by_name,
            ua.full_name AS approved_by_name,
            pr.pr_number,
            pr.purpose AS pr_purpose,
            ur.full_name AS requested_by_name
        FROM purchase_orders po
        JOIN suppliers s ON po.supplier_id = s.id
        LEFT JOIN users u ON po.created_by = u.id
        LEFT JOIN users ua ON po.approved_by = ua.id
        LEFT JOIN purchase_requests pr ON po.purchase_request_id = pr.id
        LEFT JOIN users ur ON pr.requested_by = ur.id
        WHERE po.id = ?
        LIMIT 1
    ");
    $stmt->execute([$poId]);
    $po = $stmt->fetch();

    if (!$po) {
        return null;
    }

    $itemsStmt = $db->prepare("
        SELECT
            poi.*,
            i.ingredient_name,
            m.item_name AS mro_item_name
        FROM purchase_order_items poi
        LEFT JOIN ingredients i ON poi.ingredient_id = i.id
        LEFT JOIN mro_items m ON poi.mro_item_id = m.id
        WHERE poi.po_id = ?
        ORDER BY poi.id ASC
    ");
    $itemsStmt->execute([$poId]);
    $po['items'] = $itemsStmt->fetchAll();

    return $po;
}

function buildPurchaseOrderEmailBody(array $po) {
    $poNumber = htmlspecialchars((string) ($po['po_number'] ?? ''), ENT_QUOTES, 'UTF-8');
    $supplierName = htmlspecialchars((string) ($po['supplier_name'] ?? 'Supplier'), ENT_QUOTES, 'UTF-8');
    $expectedDelivery = htmlspecialchars((string) ($po['expected_delivery'] ?? 'To be confirmed'), ENT_QUOTES, 'UTF-8');
    $approverName = htmlspecialchars((string) ($po['approved_by_name'] ?? 'General Manager'), ENT_QUOTES, 'UTF-8');
    $total = number_format((float) ($po['total_amount'] ?? 0), 2);

    return <<<HTML
<div style="border-left:5px solid #0a6239;padding:2px 0 2px 16px;margin:0 0 20px;">
    <p style="margin:0;color:#0a6239;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">Approved Electronic Purchase Order</p>
    <p style="margin:4px 0 0;color:#17211b;font-size:22px;font-weight:700;">{$poNumber}</p>
</div>
<p style="margin:0 0 14px;color:#33443a;font-size:15px;line-height:1.65;">Dear {$supplierName},</p>
<p style="margin:0 0 16px;color:#33443a;font-size:15px;line-height:1.65;">
    Highland Fresh Dairy has approved the purchase order below. The digitally approved electronic PO is attached as a PDF and is the official document for fulfillment.
</p>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:20px 0;border-collapse:separate;border-spacing:0;border:1px solid #dce6df;border-radius:8px;overflow:hidden;">
    <tr>
        <td style="padding:11px 13px;background:#f2f7f4;color:#66736c;font-size:12px;border-bottom:1px solid #dce6df;">PO Number</td>
        <td style="padding:11px 13px;color:#17211b;font-weight:700;border-bottom:1px solid #dce6df;">{$poNumber}</td>
    </tr>
    <tr>
        <td style="padding:11px 13px;background:#f2f7f4;color:#66736c;font-size:12px;border-bottom:1px solid #dce6df;">Expected Delivery</td>
        <td style="padding:11px 13px;color:#17211b;font-weight:600;border-bottom:1px solid #dce6df;">{$expectedDelivery}</td>
    </tr>
    <tr>
        <td style="padding:11px 13px;background:#f2f7f4;color:#66736c;font-size:12px;border-bottom:1px solid #dce6df;">Approved By</td>
        <td style="padding:11px 13px;color:#17211b;font-weight:600;border-bottom:1px solid #dce6df;">{$approverName}</td>
    </tr>
    <tr>
        <td style="padding:11px 13px;background:#f2f7f4;color:#66736c;font-size:12px;">Order Total</td>
        <td style="padding:11px 13px;color:#0a6239;font-size:17px;font-weight:700;">PHP {$total}</td>
    </tr>
</table>
<p style="margin:0;padding:13px 15px;background:#f2f7f4;border:1px solid #dce6df;border-radius:8px;color:#33443a;font-size:14px;line-height:1.6;">
    Please confirm receipt with the Highland Fresh Purchaser and quote <strong>{$poNumber}</strong> on your delivery receipt and invoice.
</p>
HTML;
}

function recordPurchaseOrderEmailFailure($db, $poId, $attemptId, $errorMessage) {
    $safeError = substr((string) $errorMessage, 0, 2000);
    $db->prepare("
        UPDATE purchase_order_email_attempts
        SET status = 'failed', error_message = ?
        WHERE id = ?
    ")->execute([$safeError, $attemptId]);
    $db->prepare("
        UPDATE purchase_orders
        SET last_send_attempt_at = NOW(),
            last_send_error = ?,
            email_send_in_progress = 0,
            updated_at = NOW()
        WHERE id = ?
    ")->execute([$safeError, $poId]);
}

function getLatestReceivingReportForPO(PDO $db, int $poId) {
    if (!tableExists($db, 'receiving_reports')) {
        return null;
    }

    $stmt = $db->prepare("
        SELECT rr.*, u.full_name as received_by_name, uv.full_name as verified_by_name
        FROM receiving_reports rr
        LEFT JOIN users u ON rr.received_by = u.id
        LEFT JOIN users uv ON rr.verified_by = uv.id
        WHERE rr.po_id = ?
        ORDER BY rr.received_at DESC, rr.id DESC
        LIMIT 1
    ");
    $stmt->execute([$poId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function getReceivingReportDetailForPurchasing(PDO $db): void {
    $rrId = (int) (getParam('id') ?? 0);
    if ($rrId <= 0) {
        Response::error('RR ID required', 400);
    }

    $stmt = $db->prepare("
        SELECT
            rr.*,
            po.po_number,
            po.status as po_status,
            po.total_amount as po_total,
            po.order_date,
            po.expected_delivery,
            s.supplier_name,
            s.supplier_code,
            u.full_name as received_by_name,
            uv.full_name as verified_by_name
        FROM receiving_reports rr
        JOIN purchase_orders po ON rr.po_id = po.id
        JOIN suppliers s ON rr.supplier_id = s.id
        LEFT JOIN users u ON rr.received_by = u.id
        LEFT JOIN users uv ON rr.verified_by = uv.id
        WHERE rr.id = ?
    ");
    $stmt->execute([$rrId]);
    $rr = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$rr) {
        Response::error('Receiving Report not found', 404);
    }

    $itemsStmt = $db->prepare("
        SELECT
            poi.id as po_item_id,
            poi.ingredient_id,
            poi.mro_item_id,
            poi.item_description,
            poi.quantity as po_quantity,
            poi.unit as po_unit,
            poi.unit_price as po_unit_price,
            poi.total_amount as po_line_total,
            COALESCE(agg.quantity_received, 0) as quantity_received,
            COALESCE(agg.quantity_rejected, 0) as quantity_rejected,
            agg.rejection_reason,
            agg.rejection_notes
        FROM purchase_order_items poi
        LEFT JOIN (
            SELECT
                rri.po_item_id,
                SUM(rri.quantity_received) as quantity_received,
                SUM(rri.quantity_rejected) as quantity_rejected,
                GROUP_CONCAT(NULLIF(rri.rejection_reason, '') SEPARATOR '; ') as rejection_reason,
                GROUP_CONCAT(NULLIF(rri.rejection_notes, '') SEPARATOR '; ') as rejection_notes
            FROM receiving_report_items rri
            JOIN receiving_reports rr2 ON rr2.id = rri.rr_id
            WHERE rr2.po_id = ?
            GROUP BY rri.po_item_id
        ) agg ON agg.po_item_id = poi.id
        WHERE poi.po_id = ?
        ORDER BY poi.id ASC
    ");
    $itemsStmt->execute([$rr['po_id'], $rr['po_id']]);
    $rr['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    Response::success($rr, 'Receiving Report detail retrieved');
}

function verifyReceivingReportAgainstPO(PDO $db, array $currentUser, array $data): void {
    $poId = (int) (getParam('id') ?? 0);
    $rrId = (int) ($data['rr_id'] ?? 0);
    if ($poId <= 0) {
        Response::error('PO ID required', 400);
    }

    $poStmt = $db->prepare("SELECT * FROM purchase_orders WHERE id = ?");
    $poStmt->execute([$poId]);
    $po = $poStmt->fetch(PDO::FETCH_ASSOC);
    if (!$po) {
        Response::error('Purchase order not found', 404);
    }
    if ($po['status'] !== 'received') {
        Response::error('Only fully received POs can have their Receiving Report verified', 400);
    }

    $rr = $rrId > 0 ? getReceivingReportById($db, $rrId) : getLatestReceivingReportForPO($db, $poId);
    if (!$rr || (int)$rr['po_id'] !== $poId) {
        Response::error('Receiving Report not found for this PO', 404);
    }
    if (!in_array($rr['status'], ['pending_verification', 'discrepancy'], true)) {
        Response::error('This Receiving Report has already been verified or cannot be verified', 400);
    }

    $mismatch = findReceivingReportMismatch($db, (int)$rr['id'], $poId);
    if ($mismatch !== null) {
        Response::error($mismatch, 400);
    }

    $notes = trim((string)($data['notes'] ?? ''));
    $db->beginTransaction();
    try {
        $rrNotes = $rr['notes'] ?? null;
        if ($notes !== '') {
            $rrNotes = ($rrNotes ? $rrNotes . "\n" : '') . '[Purchaser Verification] ' . $notes;
        }

        $updRR = $db->prepare("
            UPDATE receiving_reports
            SET status = 'verified',
                verified_by = ?,
                verified_at = NOW(),
                notes = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $updRR->execute([$currentUser['user_id'], $rrNotes, $rr['id']]);

        $db->prepare("
            UPDATE receiving_reports
            SET status = 'verified',
                verified_by = COALESCE(verified_by, ?),
                verified_at = COALESCE(verified_at, NOW()),
                updated_at = NOW()
            WHERE po_id = ?
              AND status IN ('pending_verification', 'discrepancy')
        ")->execute([$currentUser['user_id'], $poId]);

        $updPO = $db->prepare("
            UPDATE purchase_orders
            SET status = 'closed',
                updated_at = NOW()
            WHERE id = ?
        ");
        $updPO->execute([$poId]);

        logAudit($currentUser['user_id'], 'VERIFY', 'receiving_reports', $rr['id'],
            ['status' => $rr['status']],
            ['status' => 'verified', 'po_status' => 'closed']
        );

        logAudit($currentUser['user_id'], 'CLOSE', 'purchase_orders', $poId,
            ['status' => $po['status']],
            ['status' => 'closed', 'reason' => 'rr_verified']
        );

        createProcurementNotification(
            $db,
            'finance_officer',
            'rr_verified_transaction_closed',
            'RR verified, transaction closed',
            'Purchasing verified RR ' . $rr['rr_number'] . ' against PO ' . $po['po_number'] . '. The transaction is closed and ready for payment processing.',
            'purchase_order',
            $poId
        );

        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

    Response::success([
        'id' => $poId,
        'rr_id' => (int)$rr['id'],
        'rr_status' => 'verified',
        'status' => 'closed'
    ], 'Receiving Report verified and PO closed');
}

function getReceivingReportById(PDO $db, int $rrId) {
    $stmt = $db->prepare("SELECT * FROM receiving_reports WHERE id = ?");
    $stmt->execute([$rrId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function findReceivingReportMismatch(PDO $db, int $rrId, int $poId): ?string {
    $poItemsStmt = $db->prepare("
        SELECT id, item_description, quantity, quantity_received, quantity_rejected, unit
        FROM purchase_order_items
        WHERE po_id = ?
        ORDER BY id ASC
    ");
    $poItemsStmt->execute([$poId]);
    $poItems = $poItemsStmt->fetchAll(PDO::FETCH_ASSOC);

    $rrItemsStmt = $db->prepare("
        SELECT
            rri.po_item_id,
            SUM(rri.quantity_received) as quantity_received,
            SUM(rri.quantity_rejected) as quantity_rejected
        FROM receiving_report_items rri
        JOIN receiving_reports rr ON rr.id = rri.rr_id
        WHERE rr.po_id = ?
        GROUP BY rri.po_item_id
    ");
    $rrItemsStmt->execute([$poId]);
    $rrItems = [];
    foreach ($rrItemsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rrItems[(int)$row['po_item_id']] = $row;
    }

    foreach ($poItems as $poItem) {
        $ordered = (float)$poItem['quantity'];
        $received = (float)$poItem['quantity_received'];
        $rejected = (float)$poItem['quantity_rejected'];
        $rrReceived = isset($rrItems[(int)$poItem['id']]) ? (float)$rrItems[(int)$poItem['id']]['quantity_received'] : 0.0;
        $rrRejected = isset($rrItems[(int)$poItem['id']]) ? (float)$rrItems[(int)$poItem['id']]['quantity_rejected'] : 0.0;
        $name = $poItem['item_description'] ?: ('PO item #' . $poItem['id']);

        if ($received + 0.0001 < $ordered) {
            return $name . ' is not fully received yet.';
        }
        if ($rejected > 0.0001) {
            return $name . ' has rejected quantity. Resolve the discrepancy before closing this transaction.';
        }
        if (!isset($rrItems[(int)$poItem['id']])) {
            return $name . ' is missing from the Receiving Report.';
        }
        if ($rrReceived + 0.0001 < $ordered) {
            return $name . ' does not fully match the Receiving Report quantities yet.';
        }
        if ($rrRejected > 0.0001) {
            return $name . ' has rejected quantity in the Receiving Report. Resolve the discrepancy before closing this transaction.';
        }
    }

    return null;
}

function ensurePriceHistorySupport($db) {
    if (!auditColumnExists($db, 'ingredients', 'market_price')) {
        $db->exec("ALTER TABLE `ingredients` ADD COLUMN `market_price` DECIMAL(12,2) NULL AFTER `unit_cost`");
    }

    if (!auditColumnExists($db, 'ingredients', 'last_price_update')) {
        $db->exec("ALTER TABLE `ingredients` ADD COLUMN `last_price_update` DATE NULL AFTER `market_price`");
    }

    if (!auditColumnExists($db, 'mro_items', 'market_price')) {
        $db->exec("ALTER TABLE `mro_items` ADD COLUMN `market_price` DECIMAL(12,2) NULL AFTER `unit_cost`");
    }

    if (!auditColumnExists($db, 'mro_items', 'last_price_update')) {
        $db->exec("ALTER TABLE `mro_items` ADD COLUMN `last_price_update` DATE NULL AFTER `market_price`");
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS `ingredient_price_history` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `ingredient_id` INT(11) NOT NULL,
            `old_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `new_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `po_id` INT(11) DEFAULT NULL,
            `supplier_id` INT(11) DEFAULT NULL,
            `reason` VARCHAR(255) DEFAULT NULL,
            `updated_by` INT(11) NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_ingredient_price_history_item` (`ingredient_id`),
            KEY `idx_ingredient_price_history_po` (`po_id`),
            KEY `idx_ingredient_price_history_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS `mro_price_history` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `mro_item_id` INT(11) NOT NULL,
            `old_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `new_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `po_id` INT(11) DEFAULT NULL,
            `supplier_id` INT(11) DEFAULT NULL,
            `reason` VARCHAR(255) DEFAULT NULL,
            `updated_by` INT(11) NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_mro_price_history_item` (`mro_item_id`),
            KEY `idx_mro_price_history_po` (`po_id`),
            KEY `idx_mro_price_history_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

function addPRStatusHistory($db, $prId, $fromStatus, $toStatus, $userId, $notes = null) {
    $stmt = $db->prepare("
        INSERT INTO purchase_request_status_history
        (purchase_request_id, from_status, to_status, notes, changed_by)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$prId, $fromStatus, $toStatus, $notes, $userId]);
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

function getSubmittedPurchaseRequestSlipForPO($db, $purchaseRequestId) {
    $stmt = $db->prepare("
        SELECT pr.*, u.full_name as requested_by_name
        FROM purchase_requests pr
        LEFT JOIN users u ON pr.requested_by = u.id
        WHERE pr.id = ?
        LIMIT 1
    ");
    $stmt->execute([$purchaseRequestId]);
    $pr = $stmt->fetch();

    if (!$pr) {
        Response::error('Purchase Request not found', 404);
    }

    if (!in_array($pr['status'], ['pending', 'approved'], true)) {
        Response::error('Only submitted Purchase Request Slips are available for PO creation. Current PRS status: ' . $pr['status'], 400);
    }

    return $pr;
}

function getPurchaseRequestItemsForPO($db, $purchaseRequestId) {
    $stmt = $db->prepare("
        SELECT
            pri.*,
            i.ingredient_name,
            i.unit_of_measure as ingredient_unit,
            m.item_name as mro_item_name,
            m.unit_of_measure as mro_unit
        FROM purchase_request_items pri
        LEFT JOIN ingredients i ON pri.ingredient_id = i.id
        LEFT JOIN mro_items m ON pri.mro_item_id = m.id
        WHERE pri.purchase_request_id = ?
        ORDER BY pri.id ASC
    ");
    $stmt->execute([$purchaseRequestId]);
    $items = $stmt->fetchAll();

    if (empty($items)) {
        Response::error('The selected PRS has no line items to convert into a PO', 400);
    }

    return $items;
}

function indexSubmittedPOItemsByPRItemId($submittedItems) {
    $indexed = [];

    if (!is_array($submittedItems)) {
        return $indexed;
    }

    foreach ($submittedItems as $item) {
        if (!is_array($item) || empty($item['purchase_request_item_id'])) {
            continue;
        }

        $indexed[(int) $item['purchase_request_item_id']] = $item;
    }

    return $indexed;
}

function buildPOItemsFromSubmittedPRS($prItems, $submittedItems) {
    $submittedByPRItemId = indexSubmittedPOItemsByPRItemId($submittedItems);
    $approvedIds = array_map(fn($item) => (int) $item['id'], $prItems);

    foreach (array_keys($submittedByPRItemId) as $submittedId) {
        if (!in_array((int) $submittedId, $approvedIds, true)) {
            Response::error('PO lines can only come from the selected Purchase Request Slip', 400);
        }
    }

    $poItems = [];
    foreach ($prItems as $prItem) {
        $submitted = $submittedByPRItemId[(int) $prItem['id']] ?? [];
        $unitPrice = $submitted['unit_price'] ?? $prItem['estimated_unit_price'] ?? 0;

        if (!is_numeric($unitPrice) || (float) $unitPrice <= 0) {
            Response::error('Current unit price is required for ' . $prItem['item_description'], 400);
        }

        $qty = (float) $prItem['quantity'];
        $price = (float) $unitPrice;

        $poItems[] = [
            'purchase_request_item_id' => (int) $prItem['id'],
            'ingredient_id' => $prItem['ingredient_id'] ?? null,
            'mro_item_id' => $prItem['mro_item_id'] ?? null,
            'item_description' => $prItem['item_description'],
            'quantity' => $qty,
            'unit' => $prItem['unit'] ?: 'units',
            'unit_price' => $price,
            'total_amount' => $qty * $price,
            'is_vat_item' => !empty($submitted['is_vat_item']) ? 1 : 0,
            'notes' => $submitted['notes'] ?? $prItem['notes'] ?? null
        ];
    }

    return $poItems;
}

function recordPOCreationPriceHistory($db, $poItems, $poId, $supplierId, $currentUser) {
    foreach ($poItems as $item) {
        if (!empty($item['ingredient_id'])) {
            processItemPriceUpdate($db, [
                'item_type' => 'ingredient',
                'item_id' => $item['ingredient_id'],
                'new_price' => $item['unit_price'],
                'reason' => 'Current PO price entered during PO creation'
            ], $poId, $supplierId, $currentUser);
        } elseif (!empty($item['mro_item_id'])) {
            processItemPriceUpdate($db, [
                'item_type' => 'mro',
                'item_id' => $item['mro_item_id'],
                'new_price' => $item['unit_price'],
                'reason' => 'Current PO price entered during PO creation'
            ], $poId, $supplierId, $currentUser);
        }
    }
}

function handlePost($db, $action, $currentUser) {
    $data = getRequestBody();

    switch ($action) {
        case 'create':
            // ===== PURCHASER ONLY =====
            requireActionRole($currentUser, ['purchaser'], 'Only the Purchaser can create Purchase Orders.');

            $required = ['supplier_id', 'purchase_request_id'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    Response::error("$field is required", 400);
                }
            }

            // Require a submitted PRS before a formal PO can be drafted.
            $purchaseRequestId = $data['purchase_request_id'];
            $prData = getSubmittedPurchaseRequestSlipForPO($db, $purchaseRequestId);
            $prItems = getPurchaseRequestItemsForPO($db, $purchaseRequestId);
            $poItems = buildPOItemsFromSubmittedPRS($prItems, $data['items'] ?? []);
            $limitedMarketNotes = validateAssignmentsAgainstSelectedCanvassQuotes($db, array_map(function ($item) use ($data) {
                return [
                    'pr_item_id' => (int) $item['purchase_request_item_id'],
                    'supplier_id' => (int) $data['supplier_id'],
                    'unit_price' => (float) $item['unit_price'],
                    'item_description' => $item['item_description'] ?? 'PRS item'
                ];
            }, $poItems));
            $data['internal_review_notes'] = buildLimitedMarketReviewNotes($limitedMarketNotes);

            // Check if this PR already has an active PO
            $existingPO = $db->prepare("SELECT id, po_number FROM purchase_orders WHERE purchase_request_id = ? AND status NOT IN ('cancelled', 'rejected')");
            $existingPO->execute([$purchaseRequestId]);
            $existingPOData = $existingPO->fetch();
            if ($existingPOData) {
                Response::error('Purchase Request ' . $prData['pr_number'] . ' already has an active PO: ' . $existingPOData['po_number'], 400);
            }

            // Verify supplier exists
            $supplierCheck = $db->prepare("SELECT id, supplier_name FROM suppliers WHERE id = ? AND is_active = 1");
            $supplierCheck->execute([$data['supplier_id']]);
            if (!$supplierCheck->fetch()) {
                Response::error('Invalid or inactive supplier', 400);
            }

            $db->beginTransaction();

            try {
                // Generate PO number
                $stmt = $db->query("SELECT po_number FROM purchase_orders ORDER BY id DESC LIMIT 1");
                $last = $stmt->fetch();
                $poNumber = $last ? (string)((int)$last['po_number'] + 1) : '5252';

                // Calculate totals
                $subtotal = 0;
                $vatAmount = 0;
                foreach ($poItems as $item) {
                    $subtotal += $item['total_amount'];
                    if (!empty($item['is_vat_item'])) {
                        $vatAmount += $item['total_amount'];
                    }
                }
                $totalAmount = $subtotal;

                // Calculate due date based on payment terms
                $paymentTerms = $data['payment_terms'] ?? 'cash';
                $orderDate = $data['order_date'] ?? date('Y-m-d');
                $dueDate = null;
                if ($paymentTerms !== 'cash') {
                    $days = (int) str_replace('credit_', '', $paymentTerms);
                    $dueDate = date('Y-m-d', strtotime($orderDate . " + $days days"));
                }

                // Create PO with purchase_request_id link
                $stmt = $db->prepare("
                    INSERT INTO purchase_orders
                    (po_number, supplier_id, order_date, expected_delivery, delivery_details, status,
                     subtotal, vat_amount, total_amount, payment_status, payment_terms, due_date,
                     notes, internal_review_notes, requisition_id, purchase_request_id, created_by)
                    VALUES (?, ?, ?, ?, ?, 'draft', ?, ?, ?, 'unpaid', ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $poNumber,
                    $data['supplier_id'],
                    $orderDate,
                    $data['expected_delivery'] ?? null,
                    $data['delivery_details'] ?? null,
                    $subtotal,
                    $vatAmount,
                    $totalAmount,
                    $paymentTerms,
                    $dueDate,
                    $data['notes'] ?? null,
                    $data['internal_review_notes'] ?? null,
                    $data['requisition_id'] ?? null,
                    $purchaseRequestId,
                    $currentUser['user_id']
                ]);

                $poId = $db->lastInsertId();

                // Insert items
                $itemStmt = $db->prepare("
                    INSERT INTO purchase_order_items
                    (po_id, purchase_request_item_id, ingredient_id, mro_item_id, item_description, quantity, unit,
                     unit_price, total_amount, is_vat_item, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                foreach ($poItems as $item) {
                    $itemStmt->execute([
                        $poId,
                        $item['purchase_request_item_id'],
                        $item['ingredient_id'] ?? null,
                        $item['mro_item_id'] ?? null,
                        $item['item_description'],
                        $item['quantity'],
                        $item['unit'],
                        $item['unit_price'],
                        $item['total_amount'],
                        $item['is_vat_item'] ?? 0,
                        $item['notes'] ?? null
                    ]);
                }

                recordPOCreationPriceHistory($db, $poItems, $poId, $data['supplier_id'], $currentUser);

                $oldPrStatus = $prData['status'];
                $prUpdate = $db->prepare("
                    UPDATE purchase_requests
                    SET status = 'converted',
                        updated_at = NOW()
                    WHERE id = ?
                      AND status IN ('pending', 'approved')
                ");
                $prUpdate->execute([$purchaseRequestId]);
                addPRStatusHistory($db, $purchaseRequestId, $oldPrStatus, 'converted', $currentUser['user_id'], 'Converted to PO ' . $poNumber);

                $db->commit();

                logAudit($currentUser['user_id'], 'CREATE', 'purchase_orders', $poId, null, [
                    'po_number' => $poNumber,
                    'supplier_id' => $data['supplier_id'],
                    'purchase_request_id' => $purchaseRequestId,
                    'pr_number' => $prData['pr_number'],
                    'total_amount' => $totalAmount,
                    'payment_terms' => $paymentTerms,
                    'items_count' => count($poItems)
                ]);

                Response::success([
                    'id' => $poId,
                    'po_number' => $poNumber,
                    'purchase_request_id' => $purchaseRequestId,
                    'pr_number' => $prData['pr_number'],
                    'total_amount' => $totalAmount,
                    'payment_terms' => $paymentTerms,
                    'due_date' => $dueDate
                ], 'Purchase order created from PRS ' . $prData['pr_number'], 201);

            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;

        case 'create_from_pr':
            // ===== PURCHASER ONLY =====
            requireActionRole($currentUser, ['purchaser'], 'Only the Purchaser can generate POs from Purchase Requests.');

            if (empty($data['purchase_request_id'])) {
                Response::error('purchase_request_id is required', 400);
            }

            $validated = loadAndValidateSubmittedPRSForSplit($db, (int) $data['purchase_request_id'], $data);
            $prData      = $validated['pr'];
            $prItems     = $validated['prItems'];
            $assignments = $validated['assignments'];
            $prItemsById = [];
            foreach ($prItems as $pi) { $prItemsById[(int) $pi['id']] = $pi; }

            $supplierIds = array_values(array_unique(array_map(fn($a) => (int) $a['supplier_id'], $assignments)));
            $suppliers   = loadActiveSuppliersForSplit($db, $supplierIds);
            foreach ($supplierIds as $sid) {
                if (!isset($suppliers[$sid])) {
                    Response::error("Supplier $sid is invalid or inactive", 400);
                }
            }
            $limitedMarketNotes = validateAssignmentsAgainstSelectedCanvassQuotes($db, $assignments);
            $groups = groupAssignmentsBySupplier($assignments);
            $submitForApproval = filter_var($data['submit_for_approval'] ?? false, FILTER_VALIDATE_BOOLEAN);

            $paymentTerms = $data['payment_terms'] ?? 'cash';
            $orderDate    = $data['order_date'] ?? date('Y-m-d');
            $dueDate      = null;
            if ($paymentTerms !== 'cash') {
                $days = (int) str_replace('credit_', '', $paymentTerms);
                $dueDate = date('Y-m-d', strtotime($orderDate . " + $days days"));
            }

            $db->beginTransaction();
            try {
                $createdPOs = [];
                foreach ($groups as $supplierId => $groupAssignments) {
                    $supplierId = (int) $supplierId;
                    $poNumber   = generateNextPONumberForSplit($db);
                    $groupReviewNotes = [];
                    foreach ($groupAssignments as $assignment) {
                        $prItemId = (int) $assignment['pr_item_id'];
                        if (isset($limitedMarketNotes[$prItemId])) {
                            $groupReviewNotes[$prItemId] = $limitedMarketNotes[$prItemId];
                        }
                    }
                    $poData = $data;
                    $poData['internal_review_notes'] = buildLimitedMarketReviewNotes($groupReviewNotes);

                    $subtotal = 0; $vatAmount = 0;
                    foreach ($groupAssignments as $a) {
                        $lineTotal = (float) $a['quantity'] * (float) $a['unit_price'];
                        $subtotal += $lineTotal;
                        if (!empty($a['is_vat_item'])) { $vatAmount += $lineTotal; }
                    }
                    $totalAmount = $subtotal;

                    $poId = insertPOHeaderFromSplit(
                        $db, $poNumber, $supplierId, (int) $prData['id'], $poData,
                        $paymentTerms, $orderDate, $dueDate,
                        $subtotal, $vatAmount, $totalAmount, $currentUser
                    );

                    $lineSummaries = [];
                    foreach ($groupAssignments as $a) {
                        $prItem = $prItemsById[$a['pr_item_id']];
                        insertPOItemFromSplit($db, $poId, $a, $prItem);
                        insertPRItemPOAllocation($db, (int) $a['pr_item_id'], $poId, (float) $a['quantity'], $currentUser);
                        stampPRItemSupplier($db, (int) $a['pr_item_id'], $supplierId, $currentUser);
                        $lineSummaries[] = [
                            'pr_item_id'        => (int) $a['pr_item_id'],
                            'item_description'  => $prItem['item_description'],
                            'quantity'          => (float) $a['quantity'],
                            'unit'              => $prItem['unit'] ?: 'units',
                            'unit_price'        => (float) $a['unit_price'],
                            'total_amount'      => (float) $a['quantity'] * (float) $a['unit_price'],
                        ];
                    }

                    $priceHistoryPayload = array_map(function($a) {
                        return [
                            'ingredient_id' => null, 'mro_item_id' => null,
                            'unit_price'    => $a['unit_price'],
                        ];
                    }, $groupAssignments);
                    recordPOCreationPriceHistory($db, $priceHistoryPayload, $poId, $supplierId, $currentUser);

                    logAudit($currentUser['user_id'], 'CREATE', 'purchase_orders', $poId, null, [
                        'po_number'           => $poNumber,
                        'supplier_id'         => $supplierId,
                        'supplier_name'       => $suppliers[$supplierId]['supplier_name'] ?? null,
                        'purchase_request_id' => (int) $prData['id'],
                        'pr_number'           => $prData['pr_number'],
                        'total_amount'        => $totalAmount,
                        'payment_terms'       => $paymentTerms,
                        'items_count'         => count($groupAssignments),
                        'source'              => 'create_from_pr_supplier_grouped'
                    ]);

                    $createdPOs[] = [
                        'po_id'         => $poId,
                        'po_number'     => $poNumber,
                        'supplier_id'   => $supplierId,
                        'supplier_name' => $suppliers[$supplierId]['supplier_name'] ?? null,
                        'supplier_code' => $suppliers[$supplierId]['supplier_code'] ?? null,
                        'subtotal'      => $subtotal,
                        'vat_amount'    => $vatAmount,
                        'total_amount'  => $totalAmount,
                        'status'        => 'draft',
                        'item_count'    => count($groupAssignments),
                        'items'         => $lineSummaries,
                    ];
                }

                if ($submitForApproval) {
                    submitPurchaseOrdersForGM($db, $createdPOs, $currentUser);
                    foreach ($createdPOs as &$createdPO) {
                        $createdPO['status'] = 'pending';
                    }
                    unset($createdPO);
                }

                recomputeAndStampPRConversionState($db, (int) $prData['id'], $currentUser);

                $db->commit();

                $finalPrStatusStmt = $db->prepare("SELECT status FROM purchase_requests WHERE id = ?");
                $finalPrStatusStmt->execute([(int) $prData['id']]);
                $finalPrStatus = $finalPrStatusStmt->fetchColumn();

                $responseMessage = count($createdPOs) . ' purchase order(s) generated from PRS ' . $prData['pr_number'];
                if ($submitForApproval) {
                    $responseMessage .= ' and sent to the General Manager for approval';
                }

                Response::success([
                    'pr_id'           => (int) $prData['id'],
                    'pr_number'       => $prData['pr_number'],
                    'pr_status'       => $finalPrStatus,
                    'po_count'        => count($createdPOs),
                    'purchase_orders' => $createdPOs,
                ], $responseMessage, 201);

            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;

        default:
            Response::error('Invalid action', 400);
    }
}

function handlePut($db, $action, $currentUser) {
    $data = getRequestBody();
    $id = getParam('id') ?? ($data['id'] ?? null);

    if (!$id) {
        Response::error('PO ID required', 400);
    }

    // Get current PO
    $check = $db->prepare("SELECT * FROM purchase_orders WHERE id = ?");
    $check->execute([$id]);
    $current = $check->fetch();

    if (!$current) {
        Response::error('Purchase order not found', 404);
    }

    switch ($action) {
        case 'submit_pr_group':
            requireActionRole($currentUser, ['purchaser'], 'Only the Purchaser can submit purchase orders for approval');

            if (empty($current['purchase_request_id'])) {
                Response::error('This PO is not part of a Warehouse PRS group. Submit it individually.', 400);
            }

            $groupStmt = $db->prepare("
                SELECT id, po_number, total_amount, status
                FROM purchase_orders
                WHERE purchase_request_id = ? AND status = 'draft'
                ORDER BY id ASC
            ");
            $groupStmt->execute([(int) $current['purchase_request_id']]);
            $draftOrders = $groupStmt->fetchAll();
            if (!$draftOrders) {
                Response::error('No draft POs from this PRS are waiting for submission', 400);
            }

            $db->beginTransaction();
            try {
                $submitted = submitPurchaseOrdersForGM($db, $draftOrders, $currentUser);
                $db->commit();
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $e;
            }

            Response::success([
                'purchase_request_id' => (int) $current['purchase_request_id'],
                'submitted_count' => $submitted,
                'purchase_orders' => array_map(static fn($po) => [
                    'po_id' => (int) $po['id'],
                    'po_number' => $po['po_number'],
                    'status' => 'pending',
                ], $draftOrders),
            ], $submitted . ' purchase order(s) sent to the General Manager for approval');
            break;

        case 'submit':
            requireActionRole($currentUser, ['purchaser'], 'Only the Purchaser can submit purchase orders for approval');

            // V4.1: Submit PO for GM approval (draft -> pending).
            // The Purchaser no longer self-approves. The GM must sign off.
            if ($current['status'] !== 'draft') {
                Response::error('Only draft POs can be submitted for approval', 400);
            }

            submitPurchaseOrdersForGM($db, [$current], $currentUser);

            Response::success(null, 'Purchase order submitted for GM approval');
            break;

        case 'approve':
            requireActionRole($currentUser, ['general_manager'], 'Only the General Manager can approve purchase orders');

            // Only GM can approve
            if ($currentUser['role'] !== 'general_manager') {
                Response::error('Only the General Manager can approve purchase orders', 403);
            }

            $stepUpToken = $data['step_up_token'] ?? getParam('step_up_token');
            Auth::requireStepUp($currentUser, 'po_approval', $stepUpToken);

            if ($current['status'] !== 'pending') {
                Response::error('Only pending POs can be approved', 400);
            }
            $approvalRemarks = trim((string)($data['approval_remarks'] ?? $data['remarks'] ?? ''));

            // Get the approver's full name for permanent record
            $approverStmt = $db->prepare("SELECT full_name FROM users WHERE id = ?");
            $approverStmt->execute([$currentUser['user_id']]);
            $approverName = $approverStmt->fetchColumn() ?: 'General Manager';
            $approvedAt = date('Y-m-d H:i:s');

            $stmt = $db->prepare("
                UPDATE purchase_orders
                SET status = 'approved',
                    approved_by = ?,
                    approved_at = ?,
                    approver_name = ?,
                    approval_remarks = ?,
                    sent_to_supplier_at = NULL,
                    sent_to_supplier_by = NULL,
                    supplier_email_sent_to = NULL,
                    last_send_attempt_at = NULL,
                    last_send_error = NULL,
                    email_send_in_progress = 0,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$currentUser['user_id'], $approvedAt, $approverName, $approvalRemarks !== '' ? $approvalRemarks : null, $id]);

            logAudit($currentUser['user_id'], 'APPROVE', 'purchase_orders', $id,
                ['status' => 'pending'],
                ['status' => 'approved', 'approved_by' => $currentUser['user_id'], 'approver_name' => $approverName, 'approved_at' => $approvedAt, 'approval_remarks' => $approvalRemarks, 'step_up_verified' => true]
            );

            createProcurementNotification(
                $db,
                'warehouse_raw',
                'po_approved_pending_delivery',
                'Approved PO pending delivery',
                'PO ' . $current['po_number'] . ' has been approved by GM and is ready for Warehouse receiving.',
                'purchase_order',
                $id
            );

            // V4.1: Notify Finance so they can prepare funds for the upcoming bill
            createProcurementNotification(
                $db,
                'finance_officer',
                'po_approved_prepare_funds',
                'PO approved — prepare funds',
                'PO ' . $current['po_number'] . ' (' . number_format($current['total_amount'] ?? 0, 2) . ') was approved by GM. Payment terms: ' . ($current['payment_terms'] ?? 'N/A') . '. Please prepare funds for the upcoming supplier bill.',
                'purchase_order',
                $id
            );

            Response::success([
                'id' => $id,
                'po_number' => $current['po_number'],
                'status' => 'approved',
                'approved_by' => $currentUser['user_id'],
                'approver_name' => $approverName,
                'approved_at' => $approvedAt
            ], 'Purchase order approved');
            break;

        case 'reject':
            requireActionRole($currentUser, ['general_manager'], 'Only the General Manager can reject purchase orders');

            if ($currentUser['role'] !== 'general_manager') {
                Response::error('Only the General Manager can reject purchase orders', 403);
            }

            if ($current['status'] !== 'pending') {
                Response::error('Only pending POs can be rejected', 400);
            }
            $reason = trim((string)($data['reason'] ?? $data['remarks'] ?? ''));
            if ($reason === '') {
                Response::error('Rejection reason is required', 400);
            }

            // Get the rejector's full name for permanent record
            $rejectorStmt = $db->prepare("SELECT full_name FROM users WHERE id = ?");
            $rejectorStmt->execute([$currentUser['user_id']]);
            $rejectorName = $rejectorStmt->fetchColumn() ?: 'General Manager';
            $rejectedAt = date('Y-m-d H:i:s');

            $stmt = $db->prepare("
                UPDATE purchase_orders
                SET status = 'rejected',
                    approved_by = ?,
                    approved_at = ?,
                    approver_name = ?,
                    rejection_reason = ?,
                    notes = CONCAT(COALESCE(notes, ''), '\n[REJECTED: ', ?, ']'),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$currentUser['user_id'], $rejectedAt, $rejectorName, $reason, $reason, $id]);

            if (!empty($current['purchase_request_id']) && tableExists($db, 'purchase_requests')) {
                $prUpdate = $db->prepare("
                    UPDATE purchase_requests
                    SET status = 'pending',
                        updated_at = NOW()
                    WHERE id = ?
                      AND status = 'converted'
                ");
                $prUpdate->execute([$current['purchase_request_id']]);
                addPRStatusHistory($db, $current['purchase_request_id'], 'converted', 'pending', $currentUser['user_id'], 'Linked PO rejected: ' . $reason);
            }

            logAudit($currentUser['user_id'], 'REJECT', 'purchase_orders', $id,
                ['status' => 'pending'],
                ['status' => 'rejected', 'rejected_by' => $currentUser['user_id'], 'rejector_name' => $rejectorName, 'rejected_at' => $rejectedAt, 'reason' => $reason]);

            Response::success([
                'id' => $id,
                'po_number' => $current['po_number'],
                'status' => 'rejected',
                'rejected_by' => $currentUser['user_id'],
                'rejector_name' => $rejectorName,
                'rejected_at' => $rejectedAt,
                'rejection_reason' => $reason
            ], 'Purchase order rejected');
            break;

        case 'mark_ordered':
            requireActionRole($currentUser, ['purchaser'], 'Only the Purchaser can send final purchase orders to suppliers');

            if (!in_array($current['status'], ['approved'])) {
                Response::error('Only approved POs can be sent to the supplier', 400);
            }

            if (!empty($current['sent_to_supplier_at'])) {
                Response::error('Final PO has already been sent to the supplier', 400);
            }

            $emailPO = loadPurchaseOrderForEmail($db, (int) $id);
            $recipientEmail = hfNormalizeContactEmail($emailPO['supplier_email'] ?? null);
            if ($recipientEmail === null) {
                Response::validationError([
                    'supplier_email' => 'Add the supplier email address before sending this PO.'
                ]);
            }
            if (!hfIsValidContactEmail($recipientEmail)) {
                Response::validationError([
                    'supplier_email' => 'The supplier email address is incomplete or invalid. Correct it before sending.'
                ]);
            }

            $lockStmt = $db->prepare("
                UPDATE purchase_orders
                SET email_send_in_progress = 1,
                    last_send_attempt_at = NOW(),
                    last_send_error = NULL,
                    updated_at = NOW()
                WHERE id = ?
                  AND status = 'approved'
                  AND sent_to_supplier_at IS NULL
                  AND (
                      email_send_in_progress = 0
                      OR last_send_attempt_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                  )
            ");
            $lockStmt->execute([$id]);
            if ($lockStmt->rowCount() !== 1) {
                Response::error('This PO email is already being sent. Wait a moment before trying again.', 409);
            }

            $attemptStmt = $db->prepare("
                INSERT INTO purchase_order_email_attempts
                    (po_id, recipient_email, status, sent_by, attempted_at)
                VALUES (?, ?, 'sending', ?, NOW())
            ");
            $attemptStmt->execute([$id, $recipientEmail, $currentUser['user_id']]);
            $attemptId = (int) $db->lastInsertId();

            try {
                $pdfContent = hfBuildPurchaseOrderPdf($emailPO, $emailPO['items'] ?? []);
                $poNumber = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) ($emailPO['po_number'] ?? $id));
                $subject = 'Approved Purchase Order ' . ($emailPO['po_number'] ?? $id) . ' - Highland Fresh Dairy';
                $bodyHtml = Mailer::buildTemplate(
                    'Approved Purchase Order ' . (string) ($emailPO['po_number'] ?? ''),
                    buildPurchaseOrderEmailBody($emailPO),
                    'This approved purchase order was sent automatically by Highland Fresh Dairy.'
                );

                Mailer::send(
                    $recipientEmail,
                    $subject,
                    $bodyHtml,
                    '',
                    [[
                        'filename' => 'PO-' . $poNumber . '.pdf',
                        'content_type' => 'application/pdf',
                        'content' => $pdfContent,
                    ]]
                );
            } catch (Throwable $emailError) {
                error_log('PO email failed for PO ' . $id . ': ' . $emailError->getMessage());
                $isNotConfigured = stripos($emailError->getMessage(), 'not configured') !== false;
                $storedError = $isNotConfigured
                    ? 'Email service is not configured.'
                    : 'Supplier email delivery failed.';
                recordPurchaseOrderEmailFailure($db, (int) $id, $attemptId, $storedError);
                logAudit($currentUser['user_id'], 'PO_EMAIL_FAILED', 'purchase_orders', $id,
                    ['status' => $current['status']],
                    ['recipient_email' => $recipientEmail, 'attempt_id' => $attemptId]
                );

                $friendlyMessage = $isNotConfigured
                    ? 'The email account is not configured yet. The PO remains approved and can be retried after email setup.'
                    : 'The supplier email could not be sent. The PO remains approved; check the address or connection and try again.';
                Response::error($friendlyMessage, 502, [
                    'can_retry' => true,
                    'supplier_email' => $recipientEmail,
                ]);
            }

            $sentAt = date('Y-m-d H:i:s');
            $db->prepare("
                UPDATE purchase_order_email_attempts
                SET status = 'sent', sent_at = ?, error_message = NULL
                WHERE id = ?
            ")->execute([$sentAt, $attemptId]);

            $stmt = $db->prepare("
                UPDATE purchase_orders
                SET status = 'ordered',
                    sent_to_supplier_at = ?,
                    sent_to_supplier_by = ?,
                    supplier_email_sent_to = ?,
                    last_send_attempt_at = ?,
                    last_send_error = NULL,
                    email_send_in_progress = 0,
                    updated_at = NOW()
                WHERE id = ?
                  AND status = 'approved'
                  AND sent_to_supplier_at IS NULL
            ");
            $stmt->execute([$sentAt, $currentUser['user_id'], $recipientEmail, $sentAt, $id]);

            if ($stmt->rowCount() !== 1) {
                Response::error('The email was sent, but the PO status could not be updated. Contact the administrator before retrying.', 409);
            }

            logAudit($currentUser['user_id'], 'UPDATE', 'purchase_orders', $id,
                ['status' => $current['status'], 'sent_to_supplier_at' => $current['sent_to_supplier_at'] ?? null],
                [
                    'status' => 'ordered',
                    'sent_to_supplier_at' => $sentAt,
                    'sent_to_supplier_by' => $currentUser['user_id'],
                    'supplier_email_sent_to' => $recipientEmail,
                    'email_attempt_id' => $attemptId,
                ]);

            Response::success([
                'id' => (int) $id,
                'status' => 'ordered',
                'supplier_email_sent_to' => $recipientEmail,
                'sent_to_supplier_at' => $sentAt,
            ], 'Final PO emailed to ' . $recipientEmail);
            break;

        case 'mark_received':
            requireActionRole($currentUser, ['warehouse_raw'], 'Only Warehouse Raw can receive purchase orders');
            Response::error('Use detailed PO receiving so accepted, rejected, and delivery details are recorded.', 400);

        case 'cancel':
            requireActionRole($currentUser, ['purchaser', 'general_manager'], 'Only Purchaser or General Manager can cancel purchase orders');

            if (in_array($current['status'], ['received', 'closed', 'rejected', 'cancelled'])) {
                Response::error('This PO cannot be cancelled', 400);
            }

            $stmt = $db->prepare("
                UPDATE purchase_orders
                SET status = 'cancelled',
                    payment_status = 'cancelled',
                    notes = CONCAT(COALESCE(notes, ''), '\n[CANCELLED: ', ?, ']'),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$data['reason'] ?? 'No reason provided', $id]);

            logAudit($currentUser['user_id'], 'CANCEL', 'purchase_orders', $id,
                ['status' => $current['status']], ['status' => 'cancelled']);

            Response::success(null, 'Purchase order cancelled');
            break;

        case 'update_payment':
            requireActionRole($currentUser, ['finance_officer'], 'Only the Finance Officer can update payment status');

            $newPaymentStatus = $data['payment_status'] ?? null;
            if (!in_array($newPaymentStatus, ['unpaid', 'partial', 'paid'])) {
                Response::error('Invalid payment status', 400);
            }

            $stmt = $db->prepare("
                UPDATE purchase_orders
                SET payment_status = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$newPaymentStatus, $id]);

            logAudit($currentUser['user_id'], 'UPDATE', 'purchase_orders', $id,
                ['payment_status' => $current['payment_status']],
                ['payment_status' => $newPaymentStatus]);

            Response::success(null, 'Payment status updated');
            break;

        case 'close':
            requireActionRole($currentUser, ['purchaser', 'general_manager'], 'Only Purchaser or General Manager can close purchase orders');

            if ($current['status'] !== 'received') {
                Response::error('Only fully received POs can be closed', 400);
            }

            $verifiedRR = getLatestReceivingReportForPO($db, $id);
            if (!$verifiedRR || $verifiedRR['status'] !== 'verified') {
                Response::error('The Purchaser must verify the Receiving Report before this PO can be closed', 400);
            }

            $stmt = $db->prepare("
                UPDATE purchase_orders
                SET status = 'closed',
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$id]);

            logAudit($currentUser['user_id'], 'CLOSE', 'purchase_orders', $id,
                ['status' => $current['status']],
                ['status' => 'closed']
            );

            Response::success(['id' => $id, 'status' => 'closed'], 'Purchase order closed');
            break;

        case 'verify_rr':
            requireActionRole($currentUser, ['purchaser'], 'Only the Purchaser can verify Receiving Reports');
            verifyReceivingReportAgainstPO($db, $currentUser, $data);
            break;

        case 'receive_with_prices':
            requireActionRole($currentUser, ['warehouse_raw'], 'Only Warehouse Raw can receive purchase orders');

            // Enhanced receiving: per-item accept/reject + price updates + auto stock-in
            if (!in_array($current['status'], ['ordered', 'partial_received', 'approved'])) {
                Response::error('This PO cannot be marked as received', 400);
            }

            $db->beginTransaction();
            try {
                // Get receiving_items from request body: [{item_id, accepted, rejected, rejection_reason, rejection_category, new_price, lot_number, expiry_date, condition}]
                $receivingItems = decodeJsonField($data['receiving_items'] ?? null, []);
                $receivingMeta = decodeJsonField($data['receiving_meta'] ?? null, []);

                if (empty($receivingItems) || !is_array($receivingItems)) {
                    Response::error('Receiving requires per-item accepted and rejected quantities.', 400);
                }

                // --- New per-item receiving logic ---
                $totalAccepted = 0;
                $totalRejected = 0;
                $stockInResults = [];
                $allFullyAccepted = true;
                $hasReceivedQuantity = false;

                // Get all PO items
                $poItemsStmt = $db->prepare("
                    SELECT
                        poi.*,
                        po.po_number,
                        po.order_date,
                        i.ingredient_code,
                        i.ingredient_name,
                        COALESCE(i.is_perishable, 1) as ingredient_is_perishable,
                        m.item_code as mro_item_code,
                        m.item_name as mro_item_name,
                        COALESCE(m.is_perishable, 0) as mro_is_perishable
                    FROM purchase_order_items poi
                    JOIN purchase_orders po ON poi.po_id = po.id
                    LEFT JOIN ingredients i ON poi.ingredient_id = i.id
                    LEFT JOIN mro_items m ON poi.mro_item_id = m.id
                    WHERE poi.po_id = ?
                ");
                $poItemsStmt->execute([$id]);
                $allPOItems = $poItemsStmt->fetchAll();

                // Index receiving data by item_id
                $receivingMap = [];
                foreach ($receivingItems as $ri) {
                    if (!empty($ri['item_id'])) {
                        $receivingMap[(int)$ri['item_id']] = $ri;
                    }
                }

                $reservedBatchCodes = [];
                foreach ($allPOItems as $poItem) {
                    $itemData = $receivingMap[(int)$poItem['id']] ?? null;
                    if (!$itemData) {
                        // Item not in this receiving batch; check if already fully received.
                        $prevAccepted = (float)$poItem['quantity_received'];
                        if ($prevAccepted < (float)$poItem['quantity'] - 0.001) {
                            $allFullyAccepted = false;
                        }
                        continue;
                    }

                    $accepted = (float)($itemData['accepted'] ?? 0);
                    $rejected = (float)($itemData['rejected'] ?? 0);
                    $rejectionReason = $itemData['rejection_reason'] ?? null;

                    // Validate quantities
                    $orderedQty = (float)$poItem['quantity'];
                    $prevAccepted = (float)$poItem['quantity_received'];
                    $remaining = max(0, $orderedQty - $prevAccepted);

                    if (($accepted + $rejected) > $remaining + 0.001) {
                        throw new Exception("Item '{$poItem['item_description']}': accepted ({$accepted}) + rejected ({$rejected}) exceeds remaining accepted quantity needed ({$remaining})");
                    }

                    if ($accepted < 0 || $rejected < 0) {
                        throw new Exception("Item '{$poItem['item_description']}': quantities cannot be negative");
                    }

                    if ($rejected > 0 && empty(trim((string)$rejectionReason))) {
                        throw new Exception("Item '{$poItem['item_description']}': rejection reason is required");
                    }

                    $isPerishable = isPOItemPerishable($poItem);
                    if ($accepted > 0 && $isPerishable && empty($itemData['expiry_date'])) {
                        throw new Exception("Item '{$poItem['item_description']}': expiry date is required because this item is marked perishable");
                    }
                    if (!$isPerishable) {
                        $itemData['expiry_date'] = null;
                    }
                    if ($accepted > 0) {
                        $itemData['batch_code'] = generateItemLotBatchCode($db, getPOItemCodeForBatch($poItem), $reservedBatchCodes);
                    }
                    $receivingMap[(int)$poItem['id']] = $itemData;

                    if ($accepted > 0 || $rejected > 0) {
                        $hasReceivedQuantity = true;
                    }

                    $totalAccepted += $accepted;
                    $totalRejected += $rejected;

                    // Rejected quantity does not complete the PO; only accepted quantity counts.
                    $newAcceptedTotal = $prevAccepted + $accepted;
                    if ($newAcceptedTotal < $orderedQty - 0.001) {
                        $allFullyAccepted = false;
                    }
                }

                if (!$hasReceivedQuantity) {
                    Response::error('No received quantities were entered.', 400);
                }

                $rr = createReceivingReportFromPOItems($db, $current, $allPOItems, $receivingMap, $receivingMeta, $currentUser, $totalAccepted, $totalRejected);

                foreach ($allPOItems as $poItem) {
                    $itemData = $receivingMap[(int)$poItem['id']] ?? null;
                    if (!$itemData) {
                        continue;
                    }

                    $accepted = (float)($itemData['accepted'] ?? 0);
                    $rejected = (float)($itemData['rejected'] ?? 0);
                    if ($accepted <= 0 && $rejected <= 0) {
                        continue;
                    }

                    $rejectionReason = $itemData['rejection_reason'] ?? null;
                    $rejectionCategory = $itemData['rejection_category'] ?? null;
                    $receivingNotes = buildReceivingNotes($receivingMeta, $itemData);

                    // Update PO item
                    $db->prepare("
                        UPDATE purchase_order_items
                        SET quantity_received = quantity_received + ?,
                            quantity_rejected = quantity_rejected + ?,
                            rejection_reason = CASE WHEN ? > 0 THEN ? ELSE rejection_reason END
                        WHERE id = ?
                    ")->execute([$accepted, $rejected, $rejected, $rejectionReason, $poItem['id']]);

                    // Insert receiving log
                    $db->prepare("
                        INSERT INTO po_receiving_log
                        (po_id, po_item_id, quantity_accepted, quantity_rejected, rejection_reason, rejection_category, received_by, notes)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ")->execute([
                        $id, $poItem['id'], $accepted, $rejected,
                        $rejectionReason, $rejectionCategory,
                        $currentUser['user_id'], $receivingNotes
                    ]);

                    // Process price update if provided
                    if (!empty($itemData['new_price']) && $itemData['new_price'] > 0) {
                        $itemType = $poItem['ingredient_id'] ? 'ingredient' : ($poItem['mro_item_id'] ? 'mro' : null);
                        $itemRefId = $poItem['ingredient_id'] ?: $poItem['mro_item_id'];
                        if ($itemType && $itemRefId) {
                            processItemPriceUpdate($db, [
                                'item_id' => $itemRefId,
                                'item_type' => $itemType,
                                'new_price' => $itemData['new_price'],
                                'reason' => 'Price update on receiving'
                            ], $id, $current['supplier_id'], $currentUser);
                        }
                    }

                    // Stock-in only the ACCEPTED quantity
                    if ($accepted > 0) {
                        $result = stockInSingleItem($db, $poItem, $accepted, $current['supplier_id'], $currentUser, $itemData['new_price'] ?? null, $itemData, $rr['id'] ?? null);
                        if ($result) $stockInResults[] = $result;
                    }

                    // Track rejected
                    if ($rejected > 0) {
                        $stockInResults[] = [
                            'type' => 'rejected',
                            'item' => $poItem['item_description'],
                            'quantity' => $rejected,
                            'reason' => $rejectionReason ?? 'No reason',
                            'category' => $rejectionCategory
                        ];
                    }
                }

                // Update PO status
                $newStatus = $allFullyAccepted ? 'received' : 'partial_received';
                $stmt = $db->prepare("
                    UPDATE purchase_orders
                    SET status = ?,
                        received_at = CASE WHEN ? = 'received' THEN NOW() ELSE received_at END,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$newStatus, $newStatus, $id]);

                $db->commit();

                logAudit($currentUser['user_id'], 'RECEIVE', 'purchase_orders', $id,
                    ['status' => $current['status']],
                    ['status' => $newStatus, 'accepted' => $totalAccepted, 'rejected' => $totalRejected,
                     'stocked_in_items' => count($stockInResults)]);

                createProcurementNotification(
                    $db,
                    'purchaser',
                    $newStatus === 'received' ? 'rr_ready_for_verification' : 'po_partially_received',
                    $newStatus === 'received' ? 'Receiving Report ready for verification' : 'PO partially received',
                    'Warehouse Raw recorded delivery for PO ' . $current['po_number'] . '. ' . ($newStatus === 'received' ? 'Please verify the RR against the approved PO.' : 'Some items are still pending delivery.'),
                    'purchase_order',
                    $id
                );

                $msg = "PO receiving recorded: {$totalAccepted} accepted, {$totalRejected} rejected.";
                if ($newStatus === 'partial_received') {
                    $msg .= ' Some items still pending delivery.';
                }

                Response::success([
                    'status' => $newStatus,
                    'rr_id' => $rr['id'] ?? null,
                    'rr_number' => $rr['rr_number'] ?? null,
                    'total_accepted' => $totalAccepted,
                    'total_rejected' => $totalRejected,
                    'stocked_in' => $stockInResults
                ], $msg);
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
 * Auto stock-in PO items to warehouse inventory
 * Creates ingredient_batches or mro_inventory records for each PO line item
 * and updates the current_stock in the respective item tables.
 */
function stockInPOItems($db, $poId, $supplierId, $currentUser) {
    // Get all PO items
    $items = $db->prepare("
        SELECT poi.*, po.po_number, po.order_date
        FROM purchase_order_items poi
        JOIN purchase_orders po ON poi.po_id = po.id
        WHERE poi.po_id = ?
        ORDER BY poi.id ASC
    ");
    $items->execute([$poId]);
    $itemList = $items->fetchAll();

    $results = [];

    foreach ($itemList as $item) {
        $qty = (float) $item['quantity'];
        $unitPrice = (float) $item['unit_price'];

        // Update quantity_received on the PO item
        $db->prepare("
            UPDATE purchase_order_items SET quantity_received = ? WHERE id = ?
        ")->execute([$qty, $item['id']]);

        if ($item['ingredient_id']) {
            // --- INGREDIENT: Create batch + update stock ---
            $batchCode = 'IB-PO' . $item['po_id'] . '-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);

            // Get ingredient info for shelf life
            $ingStmt = $db->prepare("SELECT * FROM ingredients WHERE id = ?");
            $ingStmt->execute([$item['ingredient_id']]);
            $ingData = $ingStmt->fetch();

            $shelfLife = $ingData ? ($ingData['shelf_life_days'] ?? 365) : 365;
            $expiryDate = date('Y-m-d', strtotime("+{$shelfLife} days"));

            $db->prepare("
                INSERT INTO ingredient_batches
                (batch_code, ingredient_id, po_id, quantity, remaining_quantity, unit_cost,
                 supplier_id, received_date, expiry_date, qc_status, status, received_by, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, 'approved', 'available', ?, ?)
            ")->execute([
                $batchCode,
                $item['ingredient_id'],
                $poId,
                $qty,
                $qty,
                $unitPrice,
                $supplierId,
                $expiryDate,
                $currentUser['user_id'],
                'Auto stocked-in from PO#' . $item['po_number']
            ]);
            $batchId = $db->lastInsertId();

            // Update ingredient current stock
            $db->prepare("
                UPDATE ingredients
                SET current_stock = current_stock + ?,
                    unit_cost = COALESCE(?, unit_cost),
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([$qty, $unitPrice, $item['ingredient_id']]);

            // Create inventory transaction
            $txCode = generateCode('TX');
            $db->prepare("
                INSERT INTO inventory_transactions
                (transaction_code, transaction_type, item_type, item_id, batch_id,
                 quantity, unit_of_measure, reference_type, reference_id,
                 to_location, performed_by, reason)
                VALUES (?, 'po_receive', 'ingredient', ?, ?, ?, ?, 'purchase_order', ?, ?, ?, ?)
            ")->execute([
                $txCode,
                $item['ingredient_id'],
                $batchId,
                $qty,
                $item['unit'],
                $poId,
                $ingData['storage_location'] ?? 'Warehouse Raw',
                $currentUser['user_id'],
                'Received from PO#' . $item['po_number']
            ]);

            $results[] = [
                'type' => 'ingredient',
                'item' => $ingData['ingredient_name'] ?? $item['item_description'],
                'quantity' => $qty,
                'batch_code' => $batchCode,
                'transaction_code' => $txCode
            ];

        } elseif ($item['mro_item_id']) {
            // --- MRO: Create inventory record + update stock ---
            $batchCode = 'MRO-PO' . $item['po_id'] . '-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);

            $mroStmt = $db->prepare("SELECT * FROM mro_items WHERE id = ?");
            $mroStmt->execute([$item['mro_item_id']]);
            $mroData = $mroStmt->fetch();

            // Get supplier name for the record
            $supplierNameForMRO = null;
            if ($supplierId) {
                $supStmt = $db->prepare("SELECT supplier_name FROM suppliers WHERE id = ?");
                $supStmt->execute([$supplierId]);
                $supRow = $supStmt->fetch();
                $supplierNameForMRO = $supRow ? $supRow['supplier_name'] : null;
            }

            $db->prepare("
                INSERT INTO mro_inventory
                (batch_code, mro_item_id, po_id, quantity, remaining_quantity, unit_cost,
                 supplier_name, supplier_id, received_date, received_by, status, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, 'available', ?)
            ")->execute([
                $batchCode,
                $item['mro_item_id'],
                $poId,
                $qty,
                $qty,
                $unitPrice,
                $supplierNameForMRO,
                $supplierId,
                $currentUser['user_id'],
                'Auto stocked-in from PO#' . $item['po_number']
            ]);
            $batchId = $db->lastInsertId();

            // Update MRO current stock
            $db->prepare("
                UPDATE mro_items
                SET current_stock = current_stock + ?,
                    unit_cost = COALESCE(?, unit_cost),
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([$qty, $unitPrice, $item['mro_item_id']]);

            // Create inventory transaction
            $txCode = generateCode('TX');
            $db->prepare("
                INSERT INTO inventory_transactions
                (transaction_code, transaction_type, item_type, item_id, batch_id,
                 quantity, unit_of_measure, reference_type, reference_id,
                 to_location, performed_by, reason)
                VALUES (?, 'po_receive', 'mro', ?, ?, ?, ?, 'purchase_order', ?, ?, ?, ?)
            ")->execute([
                $txCode,
                $item['mro_item_id'],
                $batchId,
                $qty,
                $item['unit'],
                $poId,
                $mroData['storage_location'] ?? 'Warehouse Raw',
                $currentUser['user_id'],
                'Received from PO#' . $item['po_number']
            ]);

            $results[] = [
                'type' => 'mro',
                'item' => $mroData['item_name'] ?? $item['item_description'],
                'quantity' => $qty,
                'batch_code' => $batchCode,
                'transaction_code' => $txCode
            ];

        } else {
            // Unlinked item (legacy PO items without ingredient_id or mro_item_id)
            $results[] = [
                'type' => 'unlinked',
                'item' => $item['item_description'],
                'quantity' => $qty,
                'note' => 'Not auto-stocked (no ingredient_id or mro_item_id linked)'
            ];
        }
    }

    return $results;
}

/**
 * Process a price update for a single ingredient or MRO item.
 * Records price history and updates the item's current price.
 */
function processItemPriceUpdate($db, $update, $poId, $supplierId, $currentUser) {
    if (empty($update['item_id']) || empty($update['new_price'])) return;

    $itemType = $update['item_type'] ?? 'ingredient';
    $newPrice = (float) $update['new_price'];

    if ($itemType === 'ingredient') {
        $priceCheck = $db->prepare("SELECT unit_cost FROM ingredients WHERE id = ?");
        $priceCheck->execute([$update['item_id']]);
        $oldPrice = (float) $priceCheck->fetchColumn();

        if ($oldPrice != $newPrice) {
            $db->prepare("
                INSERT INTO ingredient_price_history
                (ingredient_id, old_price, new_price, po_id, supplier_id, reason, updated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $update['item_id'], $oldPrice, $newPrice, $poId,
                $supplierId, $update['reason'] ?? 'Price update on receiving',
                $currentUser['user_id']
            ]);

            $db->prepare("
                UPDATE ingredients SET unit_cost = ?, market_price = ?, last_price_update = CURDATE() WHERE id = ?
            ")->execute([$newPrice, $newPrice, $update['item_id']]);
        }
    } elseif ($itemType === 'mro') {
        $priceCheck = $db->prepare("SELECT unit_cost FROM mro_items WHERE id = ?");
        $priceCheck->execute([$update['item_id']]);
        $oldPrice = (float) $priceCheck->fetchColumn();

        if ($oldPrice != $newPrice) {
            $db->prepare("
                INSERT INTO mro_price_history
                (mro_item_id, old_price, new_price, po_id, supplier_id, reason, updated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $update['item_id'], $oldPrice, $newPrice, $poId,
                $supplierId, $update['reason'] ?? 'Price update on receiving',
                $currentUser['user_id']
            ]);

            $db->prepare("
                UPDATE mro_items SET unit_cost = ?, market_price = ?, last_price_update = CURDATE() WHERE id = ?
            ")->execute([$newPrice, $newPrice, $update['item_id']]);
        }
    }
}

function isPOItemPerishable($poItem) {
    if (!empty($poItem['ingredient_id'])) {
        return (int)($poItem['ingredient_is_perishable'] ?? 1) === 1;
    }
    if (!empty($poItem['mro_item_id'])) {
        return (int)($poItem['mro_is_perishable'] ?? 0) === 1;
    }
    return false;
}

function getPOItemCodeForBatch($poItem) {
    $code = $poItem['ingredient_code'] ?? $poItem['mro_item_code'] ?? null;
    if (!$code) {
        $code = 'ITEM' . (int)($poItem['id'] ?? 0);
    }

    $code = strtoupper(preg_replace('/[^A-Z0-9]+/i', '', (string)$code));
    return $code !== '' ? $code : 'ITEM';
}

function generateItemLotBatchCode($db, $itemCode, &$reservedBatchCodes = []) {
    $itemCode = strtoupper(preg_replace('/[^A-Z0-9]+/i', '', (string)$itemCode));
    if ($itemCode === '') {
        $itemCode = 'ITEM';
    }

    $prefix = $itemCode . '-Lot-';
    $maxSeq = 0;

    foreach ([
        ['ingredient_batches', 'batch_code'],
        ['mro_inventory', 'batch_code']
    ] as $source) {
        [$table, $column] = $source;
        $stmt = $db->prepare("SELECT `$column` FROM `$table` WHERE `$column` LIKE ? ORDER BY id DESC LIMIT 50");
        $stmt->execute([$prefix . '%']);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $existingCode) {
            if (preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $existingCode, $matches)) {
                $maxSeq = max($maxSeq, (int)$matches[1]);
            }
        }
    }

    foreach ($reservedBatchCodes as $reservedCode) {
        if (preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $reservedCode, $matches)) {
            $maxSeq = max($maxSeq, (int)$matches[1]);
        }
    }

    $next = $maxSeq + 1;
    $batchCode = $prefix . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
    $reservedBatchCodes[] = $batchCode;

    return $batchCode;
}

/**
 * Stock in a specific quantity for a single PO item.
 * Used by the per-item receiving flow (only accepted qty gets stocked in).
 */
function stockInSingleItem($db, $poItem, $qty, $supplierId, $currentUser, $overridePrice = null, $receivingData = [], $rrId = null) {
    $unitPrice = $overridePrice ? (float)$overridePrice : (float)$poItem['unit_price'];
    $lotNumber = trim((string)($receivingData['lot_number'] ?? ''));
    $condition = trim((string)($receivingData['condition'] ?? ''));
    $itemNotes = trim((string)($receivingData['notes'] ?? ''));

    if ($poItem['ingredient_id']) {
        $batchCode = $receivingData['batch_code'] ?? generateItemLotBatchCode($db, getPOItemCodeForBatch($poItem));

        $ingStmt = $db->prepare("SELECT * FROM ingredients WHERE id = ?");
        $ingStmt->execute([$poItem['ingredient_id']]);
        $ingData = $ingStmt->fetch();

        $isPerishable = (int)($ingData['is_perishable'] ?? 1) === 1;
        $expiryDate = $isPerishable && !empty($receivingData['expiry_date']) ? $receivingData['expiry_date'] : null;
        $batchNotes = 'Received from PO#' . $poItem['po_number'];
        if ($lotNumber !== '') $batchNotes .= " | Supplier lot: {$lotNumber}";
        if ($condition !== '') $batchNotes .= " | Condition: {$condition}";
        if ($itemNotes !== '') $batchNotes .= " | Notes: {$itemNotes}";

        $db->prepare("
            INSERT INTO ingredient_batches
            (batch_code, ingredient_id, po_id, rr_id, quantity, remaining_quantity, unit_cost,
             supplier_id, supplier_batch_no, received_date, expiry_date, qc_status, status, received_by, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, 'approved', 'available', ?, ?)
        ")->execute([
            $batchCode, $poItem['ingredient_id'], $poItem['po_id'], $rrId,
            $qty, $qty, $unitPrice, $supplierId, $lotNumber !== '' ? $lotNumber : null, $expiryDate,
            $currentUser['user_id'],
            $batchNotes
        ]);
        $batchId = $db->lastInsertId();

        $db->prepare("
            UPDATE ingredients SET current_stock = current_stock + ?, unit_cost = COALESCE(?, unit_cost), updated_at = NOW() WHERE id = ?
        ")->execute([$qty, $unitPrice, $poItem['ingredient_id']]);

        $txCode = generateCode('TX');
        $db->prepare("
            INSERT INTO inventory_transactions
            (transaction_code, transaction_type, item_type, item_id, batch_id,
             quantity, unit_of_measure, reference_type, reference_id,
             to_location, performed_by, reason)
            VALUES (?, 'po_receive', 'ingredient', ?, ?, ?, ?, 'purchase_order', ?, ?, ?, ?)
        ")->execute([
            $txCode, $poItem['ingredient_id'], $batchId, $qty, $poItem['unit'],
            $poItem['po_id'], $ingData['storage_location'] ?? 'Warehouse Raw',
            $currentUser['user_id'], 'Received from PO#' . $poItem['po_number']
        ]);

        return [
            'type' => 'ingredient', 'item' => $ingData['ingredient_name'] ?? $poItem['item_description'],
            'quantity' => $qty, 'batch_code' => $batchCode, 'transaction_code' => $txCode
        ];

    } elseif ($poItem['mro_item_id']) {
        $batchCode = $receivingData['batch_code'] ?? generateItemLotBatchCode($db, getPOItemCodeForBatch($poItem));

        $mroStmt = $db->prepare("SELECT * FROM mro_items WHERE id = ?");
        $mroStmt->execute([$poItem['mro_item_id']]);
        $mroData = $mroStmt->fetch();

        $supplierName = null;
        if ($supplierId) {
            $supStmt = $db->prepare("SELECT supplier_name FROM suppliers WHERE id = ?");
            $supStmt->execute([$supplierId]);
            $supRow = $supStmt->fetch();
            $supplierName = $supRow ? $supRow['supplier_name'] : null;
        }

        $batchNotes = 'Received from PO#' . $poItem['po_number'];
        if ($lotNumber !== '') $batchNotes .= " | Supplier lot: {$lotNumber}";
        if ($condition !== '') $batchNotes .= " | Condition: {$condition}";
        if ($itemNotes !== '') $batchNotes .= " | Notes: {$itemNotes}";
        $isPerishable = (int)($mroData['is_perishable'] ?? 0) === 1;
        $expiryDate = $isPerishable && !empty($receivingData['expiry_date']) ? $receivingData['expiry_date'] : null;

        $db->prepare("
            INSERT INTO mro_inventory
            (batch_code, mro_item_id, po_id, rr_id, quantity, remaining_quantity, unit_cost,
             supplier_name, supplier_id, supplier_batch_no, received_date, expiry_date, received_by, status, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, 'available', ?)
        ")->execute([
            $batchCode, $poItem['mro_item_id'], $poItem['po_id'], $rrId,
            $qty, $qty, $unitPrice, $supplierName, $supplierId, $lotNumber !== '' ? $lotNumber : null, $expiryDate,
            $currentUser['user_id'], $batchNotes
        ]);
        $batchId = $db->lastInsertId();

        $db->prepare("
            UPDATE mro_items SET current_stock = current_stock + ?, unit_cost = COALESCE(?, unit_cost), updated_at = NOW() WHERE id = ?
        ")->execute([$qty, $unitPrice, $poItem['mro_item_id']]);

        $txCode = generateCode('TX');
        $db->prepare("
            INSERT INTO inventory_transactions
            (transaction_code, transaction_type, item_type, item_id, batch_id,
             quantity, unit_of_measure, reference_type, reference_id,
             to_location, performed_by, reason)
            VALUES (?, 'po_receive', 'mro', ?, ?, ?, ?, 'purchase_order', ?, ?, ?, ?)
        ")->execute([
            $txCode, $poItem['mro_item_id'], $batchId, $qty, $poItem['unit'],
            $poItem['po_id'], $mroData['storage_location'] ?? 'Warehouse Raw',
            $currentUser['user_id'], 'Received from PO#' . $poItem['po_number']
        ]);

        return [
            'type' => 'mro', 'item' => $mroData['item_name'] ?? $poItem['item_description'],
            'quantity' => $qty, 'batch_code' => $batchCode, 'transaction_code' => $txCode
        ];
    }

    return [
        'type' => 'unlinked', 'item' => $poItem['item_description'],
        'quantity' => $qty, 'note' => 'Not auto-stocked (no ingredient_id or mro_item_id linked)'
    ];
}

function createReceivingReportFromPOItems($db, $po, $allPOItems, $receivingMap, $receivingMeta, $currentUser, $totalAccepted, $totalRejected) {
    $rrNumber = generateReceivingReportNumber($db);
    $status = $totalRejected > 0 ? 'discrepancy' : 'pending_verification';
    $totalOrdered = 0;

    foreach ($allPOItems as $poItem) {
        $itemData = $receivingMap[(int)$poItem['id']] ?? null;
        if (!$itemData) {
            continue;
        }

        $accepted = (float)($itemData['accepted'] ?? 0);
        $rejected = (float)($itemData['rejected'] ?? 0);
        if ($accepted > 0 || $rejected > 0) {
            $totalOrdered += (float)$poItem['quantity'];
        }
    }

    $stmt = $db->prepare("
        INSERT INTO receiving_reports
        (rr_number, po_id, supplier_id, received_by, status, total_ordered, total_received,
         total_rejected, delivery_reference, invoice_number, vehicle_plate, driver_name,
         driver_notes, delivery_condition, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $rrNumber,
        $po['id'],
        $po['supplier_id'],
        $currentUser['user_id'],
        $status,
        $totalOrdered,
        $totalAccepted,
        $totalRejected,
        cleanOptionalText($receivingMeta['delivery_reference'] ?? $receivingMeta['delivery_doc_number'] ?? null),
        cleanOptionalText($receivingMeta['invoice_number'] ?? null),
        cleanOptionalText($receivingMeta['vehicle_plate'] ?? null),
        cleanOptionalText($receivingMeta['driver_name'] ?? null),
        cleanOptionalText($receivingMeta['driver_notes'] ?? null),
        cleanOptionalText($receivingMeta['received_condition'] ?? null),
        cleanOptionalText($receivingMeta['notes'] ?? null)
    ]);
    $rrId = (int)$db->lastInsertId();

    foreach ($allPOItems as $poItem) {
        $itemData = $receivingMap[(int)$poItem['id']] ?? null;
        if (!$itemData) {
            continue;
        }

        $accepted = (float)($itemData['accepted'] ?? 0);
        $rejected = (float)($itemData['rejected'] ?? 0);
        if ($accepted <= 0 && $rejected <= 0) {
            continue;
        }

        $rejectionCategory = mapReceivingRejectionCategory($itemData['rejection_category'] ?? null);
        $rejectionReason = cleanOptionalText($itemData['rejection_reason'] ?? null);
        $lineNotes = buildReceivingNotes($receivingMeta, $itemData);

        $itemStmt = $db->prepare("
            INSERT INTO receiving_report_items
            (rr_id, po_item_id, ingredient_id, mro_item_id, item_description, quantity_ordered,
             quantity_received, quantity_rejected, unit, unit_price, rejection_reason,
             rejection_notes, batch_code, supplier_lot_number, expiry_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $itemStmt->execute([
            $rrId,
            $poItem['id'],
            $poItem['ingredient_id'] ?? null,
            $poItem['mro_item_id'] ?? null,
            $poItem['item_description'],
            (float)$poItem['quantity'],
            $accepted,
            $rejected,
            $poItem['unit'],
            (float)($itemData['new_price'] ?? $poItem['unit_price']),
            $rejected > 0 ? $rejectionCategory : null,
            $rejected > 0 ? $rejectionReason : null,
            cleanOptionalText($itemData['batch_code'] ?? null),
            cleanOptionalText($itemData['lot_number'] ?? null),
            !empty($itemData['expiry_date']) ? $itemData['expiry_date'] : null
        ]);
        $rrItemId = (int)$db->lastInsertId();

        if ($rejected > 0) {
            createSupplierRejectionFromReceipt($db, $rrId, $rrItemId, $po, $poItem, $itemData, $rejected, $currentUser);
        }
    }

    logAudit($currentUser['user_id'], 'CREATE', 'receiving_reports', $rrId, null, [
        'rr_number' => $rrNumber,
        'po_id' => $po['id'],
        'total_received' => $totalAccepted,
        'total_rejected' => $totalRejected
    ]);

    return ['id' => $rrId, 'rr_number' => $rrNumber];
}

function createSupplierRejectionFromReceipt($db, $rrId, $rrItemId, $po, $poItem, $itemData, $quantity, $currentUser) {
    $unitPrice = (float)($itemData['new_price'] ?? $poItem['unit_price']);
    $rejectionCode = generateUniqueRejectionCode($db);
    $category = mapReceivingRejectionCategory($itemData['rejection_category'] ?? null);

    $evidencePath = null;
    $pendingFile = consumePendingRejectionEvidence($itemData);
    if ($pendingFile !== null) {
        $saved = saveRejectionEvidenceFile($pendingFile, $category);
        if ($saved === false) {
            throw new Exception("Item '{$poItem['item_description']}': invalid photo upload. Use JPEG, PNG, WebP, or HEIC up to 5 MB.");
        }
        $evidencePath = $saved;
    }

    $stmt = $db->prepare("
        INSERT INTO supplier_rejections
        (rejection_code, rr_id, rr_item_id, supplier_id, ingredient_id, mro_item_id,
         item_description, quantity, unit, unit_price, total_value, rejection_type,
         rejection_reason, created_by, evidence_photo_path)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $rejectionCode,
        $rrId,
        $rrItemId,
        $po['supplier_id'],
        $poItem['ingredient_id'] ?? null,
        $poItem['mro_item_id'] ?? null,
        $poItem['item_description'],
        $quantity,
        $poItem['unit'],
        $unitPrice,
        $quantity * $unitPrice,
        $category,
        cleanOptionalText($itemData['rejection_reason'] ?? null),
        $currentUser['user_id'],
        $evidencePath
    ]);
    $rejectionId = (int)$db->lastInsertId();

    if ($evidencePath !== null) {
        $finalName = 'rej_' . $rejectionId . '_' . substr(bin2hex(random_bytes(3)), 0, 6) . '.jpg';
        $finalRel = dirname($evidencePath) . '/' . $finalName;
        $projectRoot = dirname(dirname(__DIR__));
        $finalAbs = $projectRoot . '/' . $finalRel;
        if (!@rename($projectRoot . '/' . $evidencePath, $finalAbs)) {
            throw new Exception("Item '{$poItem['item_description']}': failed to finalize evidence file");
        }
        $db->prepare("UPDATE supplier_rejections SET evidence_photo_path = ? WHERE id = ?")
            ->execute([$finalRel, $rejectionId]);
        $evidencePath = $finalRel;

        logAudit($currentUser['user_id'], 'UPLOAD_EVIDENCE', 'supplier_rejections', $rejectionId, null, [
            'rejection_code' => $rejectionCode,
            'rr_id' => $rrId,
            'item_description' => $poItem['item_description'],
            'evidence_photo_path' => $finalRel,
            'rejection_category' => $category
        ]);
    }
}

/**
 * Pull the next pending evidence file from the global $_FILES queue.
 * Returns the file array, or null if none remain. The index is removed
 * from the queue so each rejected line consumes exactly one file.
 */
function consumePendingRejectionEvidence(&$itemData) {
    if (!isset($_FILES['evidence_photos']) || !is_array($_FILES['evidence_photos']['name'])) {
        return null;
    }
    $nameKey = $_FILES['evidence_photos']['name'] ?? [];
    if (empty($nameKey) || current($nameKey) === '' || (int)($_FILES['evidence_photos']['error'][0] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = [
        'name' => $_FILES['evidence_photos']['name'][0],
        'type' => $_FILES['evidence_photos']['type'][0],
        'tmp_name' => $_FILES['evidence_photos']['tmp_name'][0],
        'error' => $_FILES['evidence_photos']['error'][0],
        'size' => $_FILES['evidence_photos']['size'][0]
    ];

    array_shift($_FILES['evidence_photos']['name']);
    array_shift($_FILES['evidence_photos']['type']);
    array_shift($_FILES['evidence_photos']['tmp_name']);
    array_shift($_FILES['evidence_photos']['error']);
    array_shift($_FILES['evidence_photos']['size']);
    if (empty($_FILES['evidence_photos']['name'])) {
        unset($_FILES['evidence_photos']);
    }

    return (int)$file['error'] === UPLOAD_ERR_NO_FILE ? null : $file;
}

function saveRejectionEvidenceFile($file, $reasonCategory) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return false;
    }
    $maxBytes = 5 * 1024 * 1024;
    if ((int)$file['size'] > $maxBytes) {
        return false;
    }
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];
    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
    $detectedMime = $finfo ? finfo_file($finfo, $file['tmp_name']) : ($file['type'] ?? '');
    if ($finfo) finfo_close($finfo);
    if (!in_array($detectedMime, $allowedMimes, true)) {
        return false;
    }

    $projectRoot = dirname(dirname(__DIR__));
    $year = date('Y');
    $month = date('m');
    $targetDir = $projectRoot . '/uploads/rejections/' . $year . '/' . $month;
    if (!is_dir($targetDir)) {
        if (!@mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            return false;
        }
        $htaccess = $targetDir . '/.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar|cgi|pl|py|sh)$\">\n  Require all denied\n</FilesMatch>\n");
        }
    }

    $tempName = 'rej_tmp_' . bin2hex(random_bytes(6)) . '.jpg';
    $tempAbs = $targetDir . '/' . $tempName;
    $tempRel = 'uploads/rejections/' . $year . '/' . $month . '/' . $tempName;

    $saved = false;
    if (extension_loaded('gd') && function_exists('getimagesize')) {
        $image = @imagecreatefromstring(file_get_contents($file['tmp_name']));
        if ($image !== false) {
            $w = imagesx($image);
            $h = imagesy($image);
            $maxSide = 1600;
            if ($w > $maxSide || $h > $maxSide) {
                $scale = $maxSide / max($w, $h);
                $newW = (int)round($w * $scale);
                $newH = (int)round($h * $scale);
                $resized = imagecreatetruecolor($newW, $newH);
                imagecopyresampled($resized, $image, 0, 0, 0, 0, $newW, $newH, $w, $h);
                imagedestroy($image);
                $image = $resized;
            }
            if (function_exists('imagejpeg')) {
                $saved = @imagejpeg($image, $tempAbs, 82);
            }
            imagedestroy($image);
        }
    }
    if (!$saved) {
        if ((int)$file['size'] > $maxBytes) {
            return false;
        }
        $saved = @move_uploaded_file($file['tmp_name'], $tempAbs);
    }
    if (!$saved || !file_exists($tempAbs)) {
        return false;
    }
    return $tempRel;
}

function generateReceivingReportNumber($db) {
    $prefix = 'RR-' . date('Ym') . '-';
    $stmt = $db->prepare("
        SELECT rr_number
        FROM receiving_reports
        WHERE rr_number LIKE ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();
    $seq = 1;

    if ($last && preg_match('/-(\d+)$/', $last, $matches)) {
        $seq = ((int)$matches[1]) + 1;
    }

    return $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
}

function generateUniqueRejectionCode($db) {
    do {
        $code = 'REJ-' . date('Ymd') . '-' . str_pad((string)mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $stmt = $db->prepare("SELECT 1 FROM supplier_rejections WHERE rejection_code = ? LIMIT 1");
        $stmt->execute([$code]);
    } while ($stmt->fetchColumn());

    return $code;
}

function mapReceivingRejectionCategory($category) {
    $category = strtolower(trim((string)$category));
    $map = [
        'spoiled' => 'quality_issue',
        'defective' => 'damaged',
        'damaged' => 'damaged',
        'damaged_packaging' => 'damaged',
        'wrong_item' => 'wrong_item',
        'short_delivery' => 'shortage',
        'shortage' => 'shortage',
        'expired' => 'expired',
        'quality_issue' => 'quality_issue'
    ];

    return $map[$category] ?? 'other';
}

function cleanOptionalText($value) {
    $value = trim((string)($value ?? ''));
    return $value === '' ? null : $value;
}

/**
 * Decode a JSON-encoded form field into its native type.
 * Multipart uploads (FormData on the client) put fields into $_POST as strings,
 * so the receiving_items / receiving_meta / price_updates arrays arrive as
 * JSON strings rather than decoded arrays. This helper normalizes both shapes.
 */
function decodeJsonField($value, $default = null) {
    if (is_array($value) || is_object($value)) {
        return $value;
    }
    if (is_string($value)) {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return $default;
        }
        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }
    }
    return $default;
}

function buildReceivingNotes($receivingMeta, $itemData) {
    $parts = [];

    if (!empty($receivingMeta['delivery_reference']) || !empty($receivingMeta['delivery_doc_number'])) {
        $parts[] = 'Delivery ref: ' . ($receivingMeta['delivery_reference'] ?? $receivingMeta['delivery_doc_number']);
    }
    if (!empty($receivingMeta['invoice_number'])) {
        $parts[] = 'Invoice: ' . $receivingMeta['invoice_number'];
    }
    if (!empty($receivingMeta['vehicle_plate'])) {
        $parts[] = 'Vehicle: ' . $receivingMeta['vehicle_plate'];
    }
    if (!empty($receivingMeta['driver_name'])) {
        $parts[] = 'Driver: ' . $receivingMeta['driver_name'];
    }
    if (!empty($receivingMeta['driver_notes'])) {
        $parts[] = 'Driver notes: ' . $receivingMeta['driver_notes'];
    }
    if (!empty($receivingMeta['received_condition'])) {
        $parts[] = 'Delivery condition: ' . $receivingMeta['received_condition'];
    }
    if (!empty($receivingMeta['notes'])) {
        $parts[] = 'Receiving notes: ' . $receivingMeta['notes'];
    }
    if (!empty($itemData['condition'])) {
        $parts[] = 'Item condition: ' . $itemData['condition'];
    }
    if (!empty($itemData['batch_code'])) {
        $parts[] = 'System batch: ' . $itemData['batch_code'];
    }
    if (!empty($itemData['lot_number'])) {
        $parts[] = 'Supplier lot: ' . $itemData['lot_number'];
    }
    if (!empty($itemData['expiry_date'])) {
        $parts[] = 'Expiry: ' . $itemData['expiry_date'];
    }
    if (!empty($itemData['notes'])) {
        $parts[] = $itemData['notes'];
    }

    return empty($parts) ? null : implode(' | ', $parts);
}


/**
 * ============================================================
 * PRS -> PO Supplier Consolidation (create_from_pr)
 * ============================================================
 * Allows a single submitted Purchase Request Slip to be split into
 * multiple POs (one per distinct supplier). Items that share a
 * supplier are consolidated into a single PO. The PRS status
 * becomes `converted` only when every line is fully allocated;
 * otherwise it becomes `partially_converted`.
 */

/**
 * Validate the assignments payload submitted to create_from_pr.
 * Returns the indexed list of submitted PRS items + the validated
 * assignments array. On error, calls Response::error() which
 * short-circuits the request.
 */
function loadAndValidateSubmittedPRSForSplit($db, $purchaseRequestId, $data) {
    $prData = getSubmittedPurchaseRequestSlipForPO($db, $purchaseRequestId);
    $prItems = getPurchaseRequestItemsForPO($db, $purchaseRequestId);

    if (empty($data['items']) || !is_array($data['items'])) {
        Response::error('items[] is required and must be a non-empty array', 400);
    }

    $prItemsById = [];
    foreach ($prItems as $i) {
        $prItemsById[(int) $i['id']] = $i;
    }

    $assignments = [];
    $seenPrItemQty = [];

    foreach ($data['items'] as $idx => $row) {
        if (!is_array($row) || empty($row['purchase_request_item_id'])) {
            Response::error("items[$idx].purchase_request_item_id is required", 400);
        }
        $prItemId = (int) $row['purchase_request_item_id'];
        if (!isset($prItemsById[$prItemId])) {
            Response::error("items[$idx] references PR line $prItemId which does not belong to PR $purchaseRequestId", 400);
        }
        if (empty($row['supplier_id'])) {
            Response::error("items[$idx].supplier_id is required (PR line $prItemId: " . $prItemsById[$prItemId]['item_description'] . ')', 400);
        }
        $supplierId = (int) $row['supplier_id'];

        $unitPrice = $row['unit_price'] ?? null;
        if (!is_numeric($unitPrice) || (float) $unitPrice <= 0) {
            Response::error("items[$idx].unit_price must be a positive number (PR line $prItemId)", 400);
        }
        $unitPrice = (float) $unitPrice;

        $prItemQty = (float) $prItemsById[$prItemId]['quantity'];
        $rowQty = isset($row['quantity']) && is_numeric($row['quantity'])
            ? (float) $row['quantity']
            : $prItemQty;
        if ($rowQty <= 0) {
            Response::error("items[$idx].quantity must be positive (PR line $prItemId)", 400);
        }

        $alreadyAssigned = $seenPrItemQty[$prItemId] ?? 0;
        if ($alreadyAssigned + $rowQty > $prItemQty + 0.0001) {
            Response::error(
                "Over-allocation on PR line $prItemId: requested " .
                number_format($alreadyAssigned + $rowQty, 2) . ' ' .
                $prItemsById[$prItemId]['unit'] . ', only ' . number_format($prItemQty, 2) . ' available',
                400
            );
        }
        $seenPrItemQty[$prItemId] = $alreadyAssigned + $rowQty;

        $assignments[] = [
            'pr_item_id'     => $prItemId,
            'supplier_id'    => $supplierId,
            'unit_price'     => $unitPrice,
            'quantity'       => $rowQty,
            'is_vat_item'    => !empty($row['is_vat_item']) ? 1 : 0,
            'notes'          => isset($row['notes']) ? trim((string) $row['notes']) : null,
            'pr_item_qty'    => $prItemQty,
            'item_description' => $prItemsById[$prItemId]['item_description'] ?? ('PRS line ' . $prItemId),
        ];
    }

    return ['pr' => $prData, 'prItems' => $prItems, 'assignments' => $assignments];
}

function validateAssignmentsAgainstSelectedCanvassQuotes(PDO $db, array $assignments): array {
    if (!tableExists($db, 'price_canvass') || !tableExists($db, 'canvass_quotes')) {
        Response::error('Complete supplier canvassing before creating a PO.', 400);
    }

    $reviewNotes = [];
    foreach ($assignments as $assignment) {
        $prItemId = (int) ($assignment['pr_item_id'] ?? 0);
        $supplierId = (int) ($assignment['supplier_id'] ?? 0);
        $unitPrice = (float) ($assignment['unit_price'] ?? 0);
        $itemName = $assignment['item_description'] ?? ('PRS line ' . $prItemId);

        if ($prItemId <= 0) {
            Response::error('Every PO line must come from a PRS item', 400);
        }

        $stmt = $db->prepare("
            SELECT
                pc.id as canvass_id,
                pc.status,
                pc.ingredient_id,
                pc.selection_method,
                pc.selection_reason,
                q.id as quote_id,
                q.supplier_id,
                q.unit_price,
                s.supplier_name
            FROM price_canvass pc
            JOIN canvass_quotes q ON q.id = pc.selected_quote_id
            JOIN suppliers s ON s.id = q.supplier_id
            WHERE pc.purchase_request_item_id = ?
              AND pc.status = 'completed'
              AND q.is_selected = 1
            ORDER BY pc.id DESC
            LIMIT 1
        ");
        $stmt->execute([$prItemId]);
        $selected = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$selected) {
            Response::error('Finish the supplier review for ' . $itemName . ' before creating a PO.', 400);
        }

        if ((int) $selected['supplier_id'] !== $supplierId) {
            Response::error($itemName . ' must use the reviewed supplier: ' . $selected['supplier_name'], 400);
        }

        if (abs((float) $selected['unit_price'] - $unitPrice) > 0.0001) {
            Response::error($itemName . ' must use the reviewed quote price: ' . number_format((float) $selected['unit_price'], 2), 400);
        }

        if (($selected['selection_method'] ?? '') === 'limited_market') {
            $reason = trim((string) ($selected['selection_reason'] ?? ''));
            if ($reason === '') {
                Response::error('A limited-supplier explanation is required for ' . $itemName, 400);
            }

            if (!empty($selected['ingredient_id'])) {
                $eligibleStmt = $db->prepare("
                    SELECT COUNT(DISTINCT si.supplier_id)
                    FROM supplier_ingredients si
                    JOIN suppliers s ON s.id = si.supplier_id AND s.is_active = 1
                    WHERE si.ingredient_id = ? AND si.is_active = 1
                ");
                $eligibleStmt->execute([(int) $selected['ingredient_id']]);
                $eligibleCount = (int) $eligibleStmt->fetchColumn();
                if ($eligibleCount >= 3) {
                    Response::error('Three suppliers are now available for ' . $itemName . '. Complete the normal three-quote canvass.', 400);
                }

                $quotedStmt = $db->prepare('SELECT COUNT(DISTINCT supplier_id) FROM canvass_quotes WHERE canvass_id = ?');
                $quotedStmt->execute([(int) $selected['canvass_id']]);
                if ((int) $quotedStmt->fetchColumn() < $eligibleCount) {
                    Response::error('Quote every available supplier for ' . $itemName . ' before creating the PO.', 400);
                }
            }
            $reviewNotes[$prItemId] = $itemName . ' - ' . $reason;
        } else {
            $countStmt = $db->prepare('SELECT COUNT(DISTINCT supplier_id) FROM canvass_quotes WHERE canvass_id = ?');
            $countStmt->execute([(int) $selected['canvass_id']]);
            if ((int) $countStmt->fetchColumn() < 3) {
                Response::error('Three quotes are required for ' . $itemName . ' unless a limited-supplier reason is recorded.', 400);
            }
        }
    }

    return $reviewNotes;
}

function buildLimitedMarketReviewNotes(array $reviewNotes): ?string {
    if (!$reviewNotes) {
        return null;
    }

    return "LIMITED SUPPLIER MARKET\n" . implode("\n", array_values($reviewNotes));
}

function groupAssignmentsBySupplier($assignments) {
    $groups = [];
    foreach ($assignments as $a) {
        $sid = (int) $a['supplier_id'];
        if (!isset($groups[$sid])) {
            $groups[$sid] = [];
        }
        $groups[$sid][] = $a;
    }
    return $groups;
}

function submitPurchaseOrdersForGM(PDO $db, array $orders, array $currentUser): int {
    $submitted = 0;
    $check = $db->prepare("
        SELECT po_number, total_amount, status
        FROM purchase_orders
        WHERE id = ?
        FOR UPDATE
    ");
    $itemCount = $db->prepare("SELECT COUNT(*) FROM purchase_order_items WHERE po_id = ?");
    $update = $db->prepare("
        UPDATE purchase_orders
        SET status = 'pending',
            updated_at = NOW()
        WHERE id = ? AND status = 'draft'
    ");

    foreach ($orders as $order) {
        $poId = (int) ($order['id'] ?? $order['po_id'] ?? 0);
        if ($poId <= 0) {
            throw new RuntimeException('A valid draft PO is required for GM submission');
        }

        $check->execute([$poId]);
        $currentPO = $check->fetch();
        if (!$currentPO || $currentPO['status'] !== 'draft') {
            throw new RuntimeException('Only valid draft POs can be submitted for GM approval');
        }
        $itemCount->execute([$poId]);
        if ((int) $itemCount->fetchColumn() < 1 || (float) $currentPO['total_amount'] <= 0) {
            throw new RuntimeException('PO ' . $currentPO['po_number'] . ' has no valid order items and cannot be sent to GM');
        }

        $update->execute([$poId]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('A purchase order changed before it could be submitted. Refresh and try again.');
        }

        logAudit($currentUser['user_id'], 'SUBMIT', 'purchase_orders', $poId,
            ['status' => 'draft'],
            ['status' => 'pending', 'submitted_by' => $currentUser['user_id']]
        );

        createProcurementNotification(
            $db,
            'general_manager',
            'po_pending_approval',
            'PO awaiting your approval',
            'PO ' . $currentPO['po_number'] . ' (' . number_format((float) $currentPO['total_amount'], 2) . ') has been submitted by Purchasing and needs your approval.',
            'purchase_order',
            $poId
        );
        $submitted++;
    }

    return $submitted;
}

function loadActiveSuppliersForSplit($db, array $supplierIds) {
    if (empty($supplierIds)) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($supplierIds), '?'));
    $stmt = $db->prepare("SELECT id, supplier_name, supplier_code FROM suppliers WHERE id IN ($placeholders) AND is_active = 1");
    $stmt->execute(array_values($supplierIds));
    $rows = $stmt->fetchAll();
    $byId = [];
    foreach ($rows as $r) {
        $byId[(int) $r['id']] = $r;
    }
    return $byId;
}

function generateNextPONumberForSplit($db) {
    $stmt = $db->query("SELECT po_number FROM purchase_orders ORDER BY id DESC LIMIT 1");
    $last = $stmt->fetch();
    return $last ? (string) ((int) $last['po_number'] + 1) : '5252';
}

function insertPOHeaderFromSplit($db, $poNumber, $supplierId, $prId, $data, $paymentTerms, $orderDate, $dueDate, $subtotal, $vatAmount, $totalAmount, $currentUser) {
    $stmt = $db->prepare("
        INSERT INTO purchase_orders
        (po_number, supplier_id, order_date, expected_delivery, delivery_details, status,
         subtotal, vat_amount, total_amount, payment_status, payment_terms, due_date,
         notes, internal_review_notes, purchase_request_id, created_by)
        VALUES (?, ?, ?, ?, ?, 'draft', ?, ?, ?, 'unpaid', ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $poNumber,
        $supplierId,
        $orderDate,
        $data['expected_delivery'] ?? null,
        $data['delivery_details'] ?? null,
        $subtotal,
        $vatAmount,
        $totalAmount,
        $paymentTerms,
        $dueDate,
        $data['notes'] ?? null,
        $data['internal_review_notes'] ?? null,
        $prId,
        $currentUser['user_id'],
    ]);
    return (int) $db->lastInsertId();
}

function insertPOItemFromSplit($db, $poId, $assignment, $prItem) {
    $stmt = $db->prepare("
        INSERT INTO purchase_order_items
        (po_id, purchase_request_item_id, ingredient_id, mro_item_id, item_description, quantity, unit,
         unit_price, total_amount, is_vat_item, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $poId,
        $assignment['pr_item_id'],
        $prItem['ingredient_id'] ?? null,
        $prItem['mro_item_id'] ?? null,
        $prItem['item_description'],
        $assignment['quantity'],
        $prItem['unit'] ?: 'units',
        $assignment['unit_price'],
        $assignment['quantity'] * $assignment['unit_price'],
        $assignment['is_vat_item'] ?? 0,
        $assignment['notes'] ?? $prItem['notes'] ?? null,
    ]);
    return (int) $db->lastInsertId();
}

function insertPRItemPOAllocation($db, $prItemId, $poId, $quantity, $currentUser) {
    $stmt = $db->prepare("
        INSERT INTO purchase_request_item_po
        (purchase_request_item_id, po_id, quantity, created_by)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$prItemId, $poId, $quantity, $currentUser['user_id']]);
    return (int) $db->lastInsertId();
}

function stampPRItemSupplier($db, $prItemId, $supplierId, $currentUser) {
    $stmt = $db->prepare("
        UPDATE purchase_request_items
        SET supplier_id = ?,
            supplier_assigned_by = ?,
            supplier_assigned_at = NOW()
        WHERE id = ?
          AND (supplier_id IS NULL OR supplier_id <> ?)
    ");
    $stmt->execute([$supplierId, $currentUser['user_id'], $prItemId, $supplierId]);
}

function recomputeAndStampPRConversionState($db, $prId, $currentUser) {
    $stmt = $db->prepare("SELECT status FROM purchase_requests WHERE id = ?");
    $stmt->execute([$prId]);
    $pr = $stmt->fetch();
    if (!$pr) {
        return;
    }
    $oldStatus = $pr['status'];

    $allocStmt = $db->prepare("
        SELECT
            pri.id              AS pr_item_id,
            pri.quantity        AS pr_item_qty,
            COALESCE(SUM(prip.quantity), 0) AS allocated_qty
        FROM purchase_request_items pri
        LEFT JOIN purchase_request_item_po prip ON prip.purchase_request_item_id = pri.id
        WHERE pri.purchase_request_id = ?
        GROUP BY pri.id, pri.quantity
    ");
    $allocStmt->execute([$prId]);
    $rows = $allocStmt->fetchAll();

    $totalLines   = count($rows);
    $fullLines    = 0;
    $partialLines = 0;
    foreach ($rows as $r) {
        if ((float) $r['allocated_qty'] + 0.0001 >= (float) $r['pr_item_qty']) {
            $fullLines++;
        } elseif ((float) $r['allocated_qty'] > 0) {
            $partialLines++;
        }
    }

    if ($totalLines > 0 && $fullLines === $totalLines) {
        $newStatus = 'converted';
    } elseif ($partialLines > 0 || $fullLines > 0) {
        $newStatus = 'partially_converted';
    } else {
        $newStatus = 'approved';
    }

    if ($newStatus === $oldStatus) {
        return;
    }

    $upd = $db->prepare("
        UPDATE purchase_requests
        SET status = ?, updated_at = NOW()
        WHERE id = ? AND status = ?
    ");
    $upd->execute([$newStatus, $prId, $oldStatus]);

    addPRStatusHistory(
        $db,
        $prId,
        $oldStatus,
        $newStatus,
        $currentUser['user_id'],
        'Recomputed after supplier-grouped PO creation'
    );

    logAudit($currentUser['user_id'], 'STATUS_CHANGE', 'purchase_requests', $prId,
        ['status' => $oldStatus],
        ['status' => $newStatus, 'reason' => 'supplier_grouped_po_creation']
    );
}
