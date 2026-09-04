<?php

/**
 * Confirmed shelf shortages. New Warehouse work stops here; it does not
 * create a Purchase Request Slip. Legacy PRS rows are copied once so open
 * work is not lost, while the original rows remain available as history.
 */
function ensureStockValidationSupport(PDO $db): void {
    $db->exec("
        CREATE TABLE IF NOT EXISTS stock_validations (
            id INT NOT NULL AUTO_INCREMENT,
            validation_number VARCHAR(45) NOT NULL,
            validated_by INT NOT NULL,
            source_type VARCHAR(30) NOT NULL DEFAULT 'warehouse_count',
            status ENUM('open','partially_ordered','ordered','cancelled') NOT NULL DEFAULT 'open',
            notes TEXT NULL,
            legacy_purchase_request_id INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_stock_validation_number (validation_number),
            UNIQUE KEY uq_stock_validation_legacy_pr (legacy_purchase_request_id),
            KEY idx_stock_validation_status (status, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    if (!auditColumnExists($db, 'stock_validations', 'source_type')) {
        $db->exec("ALTER TABLE stock_validations
            ADD COLUMN source_type VARCHAR(30) NOT NULL DEFAULT 'warehouse_count' AFTER validated_by");
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS stock_validation_items (
            id INT NOT NULL AUTO_INCREMENT,
            stock_validation_id INT NOT NULL,
            ingredient_id INT NULL,
            mro_item_id INT NULL,
            item_description VARCHAR(200) NOT NULL,
            unit VARCHAR(30) NOT NULL,
            system_stock_before DECIMAL(12,3) NOT NULL,
            physical_stock DECIMAL(12,3) NOT NULL,
            stock_variance DECIMAL(12,3) NOT NULL,
            variance_reason VARCHAR(255) NULL,
            reorder_point_at_validation DECIMAL(12,3) NOT NULL,
            target_stock_at_validation DECIMAL(12,3) NOT NULL,
            quantity_needed DECIMAL(12,3) NOT NULL,
            recommendation_type VARCHAR(30) NOT NULL DEFAULT 'low_stock',
            average_daily_issue_30d DECIMAL(12,6) NULL,
            supplier_lead_days INT NULL,
            on_order_quantity DECIMAL(12,3) NULL,
            projected_stock_at_delivery DECIMAL(12,3) NULL,
            forecast_reason VARCHAR(500) NULL,
            is_queue_active TINYINT(1) NOT NULL DEFAULT 1,
            purchasing_decision ENUM('pending','deferred','closed_without_order') NOT NULL DEFAULT 'pending',
            deferred_until DATE NULL,
            purchasing_decision_reason VARCHAR(500) NULL,
            purchasing_decided_by INT NULL,
            purchasing_decided_at DATETIME NULL,
            legacy_purchase_request_item_id INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_stock_validation_legacy_item (legacy_purchase_request_item_id),
            KEY idx_stock_validation_item_parent (stock_validation_id),
            KEY idx_stock_validation_ingredient (ingredient_id),
            KEY idx_stock_validation_mro (mro_item_id),
            CONSTRAINT fk_stock_validation_item_parent FOREIGN KEY (stock_validation_id)
                REFERENCES stock_validations(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    if (!auditColumnExists($db, 'stock_validation_items', 'is_queue_active')) {
        $db->exec("ALTER TABLE stock_validation_items ADD COLUMN is_queue_active TINYINT(1) NOT NULL DEFAULT 1 AFTER quantity_needed");
    }
    $forecastColumns = [
        'recommendation_type' => "VARCHAR(30) NOT NULL DEFAULT 'low_stock' AFTER quantity_needed",
        'average_daily_issue_30d' => 'DECIMAL(12,6) NULL AFTER recommendation_type',
        'supplier_lead_days' => 'INT NULL AFTER average_daily_issue_30d',
        'on_order_quantity' => 'DECIMAL(12,3) NULL AFTER supplier_lead_days',
        'projected_stock_at_delivery' => 'DECIMAL(12,3) NULL AFTER on_order_quantity',
        'forecast_reason' => 'VARCHAR(500) NULL AFTER projected_stock_at_delivery',
    ];
    foreach ($forecastColumns as $column => $definition) {
        if (!auditColumnExists($db, 'stock_validation_items', $column)) {
            $db->exec("ALTER TABLE stock_validation_items ADD COLUMN {$column} {$definition}");
        }
    }
    if (!auditColumnExists($db, 'stock_validation_items', 'purchasing_decision')) {
        $db->exec("ALTER TABLE stock_validation_items ADD COLUMN purchasing_decision ENUM('pending','deferred','closed_without_order') NOT NULL DEFAULT 'pending' AFTER is_queue_active");
    }
    if (!auditColumnExists($db, 'stock_validation_items', 'deferred_until')) {
        $db->exec("ALTER TABLE stock_validation_items ADD COLUMN deferred_until DATE NULL AFTER purchasing_decision");
    }
    if (!auditColumnExists($db, 'stock_validation_items', 'purchasing_decision_reason')) {
        $db->exec("ALTER TABLE stock_validation_items ADD COLUMN purchasing_decision_reason VARCHAR(500) NULL AFTER deferred_until");
    }
    if (!auditColumnExists($db, 'stock_validation_items', 'purchasing_decided_by')) {
        $db->exec("ALTER TABLE stock_validation_items ADD COLUMN purchasing_decided_by INT NULL AFTER purchasing_decision_reason");
    }
    if (!auditColumnExists($db, 'stock_validation_items', 'purchasing_decided_at')) {
        $db->exec("ALTER TABLE stock_validation_items ADD COLUMN purchasing_decided_at DATETIME NULL AFTER purchasing_decided_by");
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS stock_validation_item_po (
            id INT NOT NULL AUTO_INCREMENT,
            stock_validation_item_id INT NOT NULL,
            po_id INT NOT NULL,
            quantity DECIMAL(12,3) NOT NULL,
            created_by INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_stock_validation_item_po (stock_validation_item_id, po_id),
            KEY idx_stock_validation_po (po_id),
            CONSTRAINT fk_stock_validation_po_item FOREIGN KEY (stock_validation_item_id)
                REFERENCES stock_validation_items(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    if (auditTableExists($db, 'purchase_order_items')
        && !auditColumnExists($db, 'purchase_order_items', 'stock_validation_item_id')) {
        $db->exec("
            ALTER TABLE purchase_order_items
            ADD COLUMN stock_validation_item_id INT NULL AFTER purchase_request_item_id,
            ADD KEY idx_po_item_stock_validation (stock_validation_item_id)
        ");
    }

    migrateOpenLegacyPRSToStockValidations($db);
}

function migrateOpenLegacyPRSToStockValidations(PDO $db): void {
    if (!auditTableExists($db, 'purchase_requests') || !auditTableExists($db, 'purchase_request_items')) {
        return;
    }

    $db->exec("
        INSERT IGNORE INTO stock_validations
            (validation_number, validated_by, status, notes, legacy_purchase_request_id, created_at, updated_at)
        SELECT
            CONCAT('LEGACY-', pr.pr_number),
            COALESCE(pri_audit.audited_by, pr.requested_by),
            CASE WHEN pr.status = 'partially_converted' THEN 'partially_ordered' ELSE 'open' END,
            CONCAT('Carried forward from old PRS ', pr.pr_number, '. Original record kept as history.'),
            pr.id,
            pr.created_at,
            pr.updated_at
        FROM purchase_requests pr
        LEFT JOIN (
            SELECT purchase_request_id, MAX(audited_by) AS audited_by
            FROM purchase_request_items
            GROUP BY purchase_request_id
        ) pri_audit ON pri_audit.purchase_request_id = pr.id
        WHERE pr.status IN ('pending','approved','partially_converted')
    ");

    $db->exec("
        INSERT IGNORE INTO stock_validation_items
            (stock_validation_id, ingredient_id, mro_item_id, item_description, unit,
             system_stock_before, physical_stock, stock_variance, variance_reason,
             reorder_point_at_validation, target_stock_at_validation, quantity_needed,
             legacy_purchase_request_item_id, created_at)
        SELECT
            sv.id,
            pri.ingredient_id,
            pri.mro_item_id,
            pri.item_description,
            pri.unit,
            COALESCE(pri.system_stock_before, pri.audited_stock, 0),
            COALESCE(pri.audited_stock, pri.system_stock_before, 0),
            COALESCE(pri.stock_variance, 0),
            pri.audit_reason,
            CASE
                WHEN pri.ingredient_id IS NOT NULL THEN COALESCE(i.reorder_point, i.minimum_stock, 0)
                ELSE COALESCE(m.reorder_point, m.minimum_stock, 0)
            END,
            COALESCE(pri.target_stock_at_request, pri.audited_stock + pri.quantity, pri.quantity),
            pri.quantity,
            pri.id,
            pri.created_at
        FROM purchase_request_items pri
        JOIN purchase_requests pr ON pr.id = pri.purchase_request_id
        JOIN stock_validations sv ON sv.legacy_purchase_request_id = pr.id
        LEFT JOIN ingredients i ON i.id = pri.ingredient_id
        LEFT JOIN mro_items m ON m.id = pri.mro_item_id
        WHERE pr.status IN ('pending','approved','partially_converted')
    ");

    if (auditTableExists($db, 'purchase_request_item_po')) {
        $db->exec("
            INSERT IGNORE INTO stock_validation_item_po
                (stock_validation_item_id, po_id, quantity, created_by, created_at)
            SELECT svi.id, prip.po_id, prip.quantity, prip.created_by, prip.created_at
            FROM stock_validation_items svi
            JOIN purchase_request_item_po prip
              ON prip.purchase_request_item_id = svi.legacy_purchase_request_item_id
            JOIN purchase_orders po ON po.id = prip.po_id
            WHERE po.status NOT IN ('cancelled','rejected')
        ");
    }

    deduplicateLegacyStockValidationQueue($db);

    foreach ($db->query("
        SELECT id FROM stock_validations
        WHERE legacy_purchase_request_id IS NOT NULL AND status <> 'cancelled'
    ")->fetchAll(PDO::FETCH_COLUMN) as $validationId) {
        recomputeStockValidationState($db, (int) $validationId);
    }
}

function deduplicateLegacyStockValidationQueue(PDO $db): void {
    $rows = $db->query("
        SELECT
            svi.id,
            CASE WHEN svi.ingredient_id IS NOT NULL
                 THEN CONCAT('ingredient:', svi.ingredient_id)
                 ELSE CONCAT('mro:', svi.mro_item_id) END AS item_key,
            sv.created_at,
            svi.quantity_needed - COALESCE((
                SELECT SUM(svip.quantity)
                FROM stock_validation_item_po svip
                JOIN purchase_orders po ON po.id = svip.po_id
                WHERE svip.stock_validation_item_id = svi.id
                  AND po.status NOT IN ('cancelled','rejected')
            ), 0) AS remaining_quantity
        FROM stock_validation_items svi
        JOIN stock_validations sv ON sv.id = svi.stock_validation_id
        WHERE svi.legacy_purchase_request_item_id IS NOT NULL
          AND svi.is_queue_active = 1
          AND sv.status IN ('open','partially_ordered')
        ORDER BY item_key, sv.created_at DESC, svi.id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $seen = [];
    $hideStmt = $db->prepare("UPDATE stock_validation_items SET is_queue_active = 0 WHERE id = ?");
    foreach ($rows as $row) {
        if ((float) $row['remaining_quantity'] <= 0.0001) continue;
        $key = (string) $row['item_key'];
        if (!isset($seen[$key])) {
            $seen[$key] = (int) $row['id'];
            continue;
        }
        $hideStmt->execute([(int) $row['id']]);
    }
}

function nextStockValidationNumber(): string {
    return 'VAL-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
}

function recomputeStockValidationState(PDO $db, int $validationId): string {
    $stmt = $db->prepare("
        SELECT
            COUNT(*) AS item_count,
            SUM(CASE WHEN remaining_quantity <= 0.0001 OR purchasing_decision = 'closed_without_order' THEN 1 ELSE 0 END) AS covered_count,
            SUM(CASE WHEN allocated_quantity > 0.0001 THEN 1 ELSE 0 END) AS started_count
        FROM (
            SELECT svi.id, svi.purchasing_decision,
                   COALESCE(SUM(CASE WHEN po.status NOT IN ('cancelled','rejected') THEN svip.quantity ELSE 0 END), 0) AS allocated_quantity,
                   svi.quantity_needed - COALESCE(SUM(CASE WHEN po.status NOT IN ('cancelled','rejected') THEN svip.quantity ELSE 0 END), 0) AS remaining_quantity
            FROM stock_validation_items svi
            LEFT JOIN stock_validation_item_po svip ON svip.stock_validation_item_id = svi.id
            LEFT JOIN purchase_orders po ON po.id = svip.po_id
            WHERE svi.stock_validation_id = ? AND svi.is_queue_active = 1
            GROUP BY svi.id, svi.quantity_needed, svi.purchasing_decision
        ) item_progress
    ");
    $stmt->execute([$validationId]);
    $progress = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $count = (int) ($progress['item_count'] ?? 0);
    $covered = (int) ($progress['covered_count'] ?? 0);
    $started = (int) ($progress['started_count'] ?? 0);
    $status = $count > 0 && $covered >= $count
        ? ($started > 0 ? 'ordered' : 'cancelled')
        : ($started > 0 ? 'partially_ordered' : 'open');
    $db->prepare("UPDATE stock_validations SET status = ? WHERE id = ?")
        ->execute([$status, $validationId]);

    $legacyStmt = $db->prepare("SELECT legacy_purchase_request_id FROM stock_validations WHERE id = ?");
    $legacyStmt->execute([$validationId]);
    $legacyPrId = (int) $legacyStmt->fetchColumn();
    if ($legacyPrId > 0) {
        $legacyStatus = $status === 'ordered' ? 'converted' : ($status === 'cancelled' ? 'rejected' : ($status === 'partially_ordered' ? 'partially_converted' : 'pending'));
        $db->prepare("UPDATE purchase_requests SET status = ? WHERE id = ?")
            ->execute([$legacyStatus, $legacyPrId]);
    }
    return $status;
}
