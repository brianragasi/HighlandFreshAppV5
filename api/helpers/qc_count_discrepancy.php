<?php

if (defined('QC_COUNT_DISCREPANCY_HELPER_LOADED')) {
    return;
}
define('QC_COUNT_DISCREPANCY_HELPER_LOADED', true);

function ensureQcCountDiscrepancyTables(PDO $db)
{
    static $ensured = false;
    if ($ensured) return;

    try {
        $db->query('SELECT 1 FROM qc_batch_count_discrepancies LIMIT 0');
        $db->query('SELECT 1 FROM qc_batch_count_discrepancy_lines LIMIT 0');
        $ensured = true;
        return;
    } catch (Throwable $e) {
        // Install below only when no business transaction is active.
    }

    if ($db->inTransaction()) {
        throw new RuntimeException('QC count-discrepancy storage must be initialized before a transaction');
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS qc_batch_count_discrepancies (
            id INT NOT NULL AUTO_INCREMENT,
            batch_id INT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'open',
            reason_category VARCHAR(40) NOT NULL,
            reason_notes TEXT NOT NULL,
            expected_total INT NOT NULL DEFAULT 0,
            counted_total INT NOT NULL DEFAULT 0,
            variance INT NOT NULL DEFAULT 0,
            resolution_type VARCHAR(40) NULL,
            resolution_notes TEXT NULL,
            disposal_id INT NULL,
            opened_by INT NOT NULL,
            opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            resolved_by INT NULL,
            resolved_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY idx_qc_count_batch_status (batch_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $db->exec("
        CREATE TABLE IF NOT EXISTS qc_batch_count_discrepancy_lines (
            id INT NOT NULL AUTO_INCREMENT,
            discrepancy_id INT NOT NULL,
            packaging_run_item_id INT NOT NULL,
            product_id INT NULL,
            product_name VARCHAR(150) NOT NULL,
            size_ml DECIMAL(10,2) NULL,
            expected_quantity INT NOT NULL DEFAULT 0,
            counted_quantity INT NOT NULL DEFAULT 0,
            variance INT NOT NULL DEFAULT 0,
            released_quantity INT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_qc_count_discrepancy_line (discrepancy_id, packaging_run_item_id),
            KEY idx_qc_count_line_packaging (packaging_run_item_id),
            CONSTRAINT fk_qc_count_line_discrepancy
                FOREIGN KEY (discrepancy_id) REFERENCES qc_batch_count_discrepancies (id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $ensured = true;
}
function qcGetBatchPackagingLines(PDO $db, $batchId, $runId)
{
    $stmt = $db->prepare("
        SELECT pri.id AS packaging_run_item_id, pri.product_id,
               COALESCE(NULLIF(pri.product_name, ''), p.product_name) AS product_name,
               COALESCE(NULLIF(pri.product_variant, ''), p.variant) AS product_variant,
               COALESCE(pri.size_ml, p.unit_size) AS size_ml,
               COALESCE(NULLIF(pri.unit_measure, ''), p.unit_measure, 'ml') AS unit_measure,
               p.product_code,
               pri.quantity
        FROM packaging_run_items pri
        JOIN packaging_runs pr ON pri.packaging_run_id = pr.id
        LEFT JOIN products p ON p.id = pri.product_id
        WHERE pr.batch_id = ? OR pr.production_run_id = ?
        ORDER BY pri.size_ml DESC, pri.id ASC
    ");
    $stmt->execute([(int) $batchId, (int) $runId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function qcBuildCountSnapshot(array $packagingLines, array $submittedLines)
{
    $submitted = [];
    foreach ($submittedLines as $line) {
        $id = (int) ($line['packaging_run_item_id'] ?? 0);
        $counted = $line['counted_quantity'] ?? null;
        if ($id > 0 && $counted !== null && is_numeric($counted) && (int) $counted >= 0) {
            $submitted[$id] = (int) $counted;
        }
    }

    $errors = [];
    $snapshot = [];
    $expectedTotal = 0;
    $countedTotal = 0;
    foreach ($packagingLines as $idx => $line) {
        $id = (int) $line['packaging_run_item_id'];
        if (!array_key_exists($id, $submitted)) {
            $errors["count_lines.$idx"] = 'Enter the physical count for every packaging SKU';
            continue;
        }
        $expected = (int) ($line['quantity'] ?? 0);
        $counted = $submitted[$id];
        $snapshot[] = [
            'packaging_run_item_id' => $id,
            'product_id' => !empty($line['product_id']) ? (int) $line['product_id'] : null,
            'product_name' => $line['product_name'] ?: 'Finished product',
            'product_variant' => $line['product_variant'] ?? null,
            'size_ml' => $line['size_ml'] !== null ? (float) $line['size_ml'] : null,
            'unit_measure' => $line['unit_measure'] ?: 'ml',
            'expected_quantity' => $expected,
            'counted_quantity' => $counted,
            'variance' => $counted - $expected,
        ];
        $expectedTotal += $expected;
        $countedTotal += $counted;
    }

    return [
        'success' => empty($errors) && !empty($snapshot),
        'errors' => $errors ?: (empty($snapshot) ? ['count_lines' => 'No packaging lines were available to count'] : []),
        'lines' => $snapshot,
        'expected_total' => $expectedTotal,
        'counted_total' => $countedTotal,
        'variance' => $countedTotal - $expectedTotal,
    ];
}

function qcGetLatestCountDiscrepancy(PDO $db, $batchId)
{
    ensureQcCountDiscrepancyTables($db);
    $stmt = $db->prepare('SELECT * FROM qc_batch_count_discrepancies WHERE batch_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([(int) $batchId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;

    $lines = $db->prepare('SELECT * FROM qc_batch_count_discrepancy_lines WHERE discrepancy_id = ? ORDER BY id');
    $lines->execute([(int) $row['id']]);
    $row['lines'] = $lines->fetchAll(PDO::FETCH_ASSOC) ?: [];
    return $row;
}

function qcGetEffectiveReleasedPackagingLines(PDO $db, $batchId, $runId)
{
    $lines = qcGetBatchPackagingLines($db, $batchId, $runId);
    $latest = qcGetLatestCountDiscrepancy($db, $batchId);
    if (!$latest || $latest['status'] !== 'resolved') return $lines;

    $releasedByItem = [];
    foreach ($latest['lines'] as $line) {
        if ($line['released_quantity'] !== null) {
            $releasedByItem[(int) $line['packaging_run_item_id']] = (int) $line['released_quantity'];
        }
    }
    foreach ($lines as &$line) {
        $id = (int) $line['packaging_run_item_id'];
        if (array_key_exists($id, $releasedByItem)) {
            $line['production_quantity'] = (int) $line['quantity'];
            $line['quantity'] = $releasedByItem[$id];
            $line['qc_adjusted'] = true;
        }
    }
    unset($line);
    return $lines;
}
