<?php
/**
 * Shared helpers for farmer milk payment/disbursement tracking.
 */

function financeTableExists($db, $tableName) {
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

function ensureFarmerPaymentTables($db) {
    if (!financeTableExists($db, 'farmer_payments')) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `farmer_payments` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `payment_code` VARCHAR(30) NOT NULL,
                `farmer_id` INT(11) NOT NULL,
                `covered_from` DATE DEFAULT NULL,
                `covered_to` DATE DEFAULT NULL,
                `delivery_count` INT(11) NOT NULL DEFAULT 0,
                `total_liters` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `gross_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `amount_paid` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `payment_date` DATE NOT NULL,
                `payment_method` VARCHAR(50) DEFAULT NULL,
                `reference_number` VARCHAR(100) DEFAULT NULL,
                `remarks` TEXT DEFAULT NULL,
                `created_by` INT(11) DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_farmer_payments_code` (`payment_code`),
                KEY `idx_farmer_payments_farmer` (`farmer_id`),
                KEY `idx_farmer_payments_date` (`payment_date`),
                CONSTRAINT `fk_farmer_payments_farmer` FOREIGN KEY (`farmer_id`) REFERENCES `farmers`(`id`),
                CONSTRAINT `fk_farmer_payments_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }

    if (!financeTableExists($db, 'farmer_payment_receipts')) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `farmer_payment_receipts` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `farmer_payment_id` INT(11) NOT NULL,
                `receiving_id` INT(11) NOT NULL,
                `qc_test_id` INT(11) NOT NULL,
                `amount_paid` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_farmer_payment_receipt_receiving` (`receiving_id`),
                UNIQUE KEY `uk_farmer_payment_receipt_qc_test` (`qc_test_id`),
                KEY `idx_farmer_payment_receipts_payment` (`farmer_payment_id`),
                CONSTRAINT `fk_farmer_payment_receipts_payment` FOREIGN KEY (`farmer_payment_id`) REFERENCES `farmer_payments`(`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_farmer_payment_receipts_receiving` FOREIGN KEY (`receiving_id`) REFERENCES `milk_receiving`(`id`),
                CONSTRAINT `fk_farmer_payment_receipts_qc` FOREIGN KEY (`qc_test_id`) REFERENCES `qc_milk_tests`(`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }
}

function farmerPaymentLatestAcceptedTestJoin() {
    return "
        JOIN (
            SELECT receiving_id, MAX(id) as latest_test_id
            FROM qc_milk_tests
            WHERE is_accepted = 1
            GROUP BY receiving_id
        ) latest_qc ON latest_qc.receiving_id = mr.id
        JOIN qc_milk_tests qmt ON qmt.id = latest_qc.latest_test_id
    ";
}

function getUnpaidFarmerPaymentRows($db, $farmerId, $coveredFrom = null, $coveredTo = null, $forUpdate = false) {
    $where = [
        "mr.status = 'accepted'",
        "mr.farmer_id = ?",
        "NOT EXISTS (
            SELECT 1
            FROM farmer_payment_receipts fpr
            WHERE fpr.receiving_id = mr.id
        )"
    ];
    $params = [(int) $farmerId];

    if ($coveredFrom) {
        $where[] = "mr.receiving_date >= ?";
        $params[] = $coveredFrom;
    }

    if ($coveredTo) {
        $where[] = "mr.receiving_date <= ?";
        $params[] = $coveredTo;
    }

    $lockClause = $forUpdate ? " FOR UPDATE" : "";
    $stmt = $db->prepare("
        SELECT
            mr.id as receiving_id,
            qmt.id as qc_test_id,
            mr.receiving_code,
            mr.rmr_number,
            mr.farmer_id,
            mr.receiving_date,
            mr.accepted_liters,
            qmt.fat_percentage,
            qmt.titratable_acidity,
            qmt.sediment_grade,
            qmt.final_price_per_liter,
            qmt.total_amount
        FROM milk_receiving mr
        " . farmerPaymentLatestAcceptedTestJoin() . "
        WHERE " . implode(' AND ', $where) . "
        ORDER BY mr.receiving_date ASC, mr.id ASC
        {$lockClause}
    ");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function summarizeFarmerPaymentRows($rows) {
    $summary = [
        'delivery_count' => 0,
        'total_liters' => 0.0,
        'total_amount' => 0.0,
        'covered_from' => null,
        'covered_to' => null,
        'avg_fat' => 0.0,
        'avg_acidity' => 0.0,
        'avg_price_per_liter' => 0.0
    ];

    $fatTotal = 0.0;
    $acidityTotal = 0.0;
    $priceTotal = 0.0;

    foreach ($rows as $row) {
        $summary['delivery_count']++;
        $summary['total_liters'] += (float) ($row['accepted_liters'] ?? 0);
        $summary['total_amount'] += (float) ($row['total_amount'] ?? 0);
        $fatTotal += (float) ($row['fat_percentage'] ?? 0);
        $acidityTotal += (float) ($row['titratable_acidity'] ?? 0);
        $priceTotal += (float) ($row['final_price_per_liter'] ?? 0);

        $date = $row['receiving_date'] ?? null;
        if ($date && (!$summary['covered_from'] || $date < $summary['covered_from'])) {
            $summary['covered_from'] = $date;
        }
        if ($date && (!$summary['covered_to'] || $date > $summary['covered_to'])) {
            $summary['covered_to'] = $date;
        }
    }

    if ($summary['delivery_count'] > 0) {
        $summary['avg_fat'] = $fatTotal / $summary['delivery_count'];
        $summary['avg_acidity'] = $acidityTotal / $summary['delivery_count'];
        $summary['avg_price_per_liter'] = $priceTotal / $summary['delivery_count'];
    }

    return $summary;
}

function getFarmerPaymentSummaryRows($db) {
    $stmt = $db->query("
        SELECT
            f.id as farmer_id,
            f.farmer_code,
            CONCAT(f.first_name, ' ', f.last_name) as farmer_name,
            f.membership_type,
            f.bank_name,
            f.bank_account_number,
            mt.type_name as milk_type,
            COALESCE(unpaid.delivery_count, 0) as delivery_count,
            COALESCE(unpaid.total_liters, 0) as total_liters,
            COALESCE(unpaid.avg_fat, 0) as avg_fat,
            COALESCE(unpaid.avg_acidity, 0) as avg_acidity,
            COALESCE(unpaid.avg_price_per_liter, 0) as avg_price_per_liter,
            COALESCE(unpaid.total_amount, 0) as total_amount,
            unpaid.first_delivery,
            unpaid.last_delivery,
            (
                SELECT fp.payment_date
                FROM farmer_payments fp
                WHERE fp.farmer_id = f.id
                ORDER BY fp.payment_date DESC, fp.id DESC
                LIMIT 1
            ) as last_payment_date,
            (
                SELECT fp.amount_paid
                FROM farmer_payments fp
                WHERE fp.farmer_id = f.id
                ORDER BY fp.payment_date DESC, fp.id DESC
                LIMIT 1
            ) as last_payment_amount
        FROM farmers f
        LEFT JOIN (
            SELECT
                mr.farmer_id,
                COUNT(mr.id) as delivery_count,
                COALESCE(SUM(mr.accepted_liters), 0) as total_liters,
                COALESCE(AVG(qmt.fat_percentage), 0) as avg_fat,
                COALESCE(AVG(qmt.titratable_acidity), 0) as avg_acidity,
                COALESCE(AVG(qmt.final_price_per_liter), 0) as avg_price_per_liter,
                COALESCE(SUM(qmt.total_amount), 0) as total_amount,
                MIN(mr.receiving_date) as first_delivery,
                MAX(mr.receiving_date) as last_delivery
            FROM milk_receiving mr
            " . farmerPaymentLatestAcceptedTestJoin() . "
            WHERE mr.status = 'accepted'
              AND NOT EXISTS (
                  SELECT 1
                  FROM farmer_payment_receipts fpr
                  WHERE fpr.receiving_id = mr.id
              )
            GROUP BY mr.farmer_id
        ) unpaid ON unpaid.farmer_id = f.id
        LEFT JOIN milk_types mt ON f.milk_type_id = mt.id
        WHERE f.is_active = 1
        ORDER BY total_amount DESC, f.farmer_code ASC
    ");

    return $stmt->fetchAll();
}

function generateFarmerPaymentCode($db, $paymentDate) {
    $prefix = 'FPY-' . date('Ymd', strtotime($paymentDate)) . '-';
    $stmt = $db->prepare("
        SELECT MAX(CAST(SUBSTRING(payment_code, 14) AS UNSIGNED)) as max_num
        FROM farmer_payments
        WHERE payment_code LIKE ?
    ");
    $stmt->execute([$prefix . '%']);
    $next = ((int) ($stmt->fetchColumn() ?? 0)) + 1;
    return $prefix . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
}

function normalizePaymentDate($value, $fieldName) {
    if ($value === null || $value === '') {
        return null;
    }

    $value = trim((string) $value);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        Response::error("Invalid {$fieldName}", 400);
    }

    return $value;
}
