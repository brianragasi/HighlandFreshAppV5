<?php

/**
 * Prepare a small, clearly labelled inventory set for the school panel demo.
 *
 * This intentionally leaves the Bottle Cap low, Raw Material A empty,
 * Chocolate Powder partly held, and Stabilizer expired so the team can show
 * those workflows. It only supplies traceability to four existing opening
 * balance examples that already have matching stock totals.
 */

$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
require_once dirname(__DIR__) . '/api/bootstrap.php';

$db = Database::getInstance()->getConnection();
$gmUserId = 1;
$qcUserId = 2;
$marker = 'PANEL DEMO OPENING BALANCE: traceability supplied for classroom demonstration.';

$demoBatches = [
    ['batch_code' => 'IB-ADJ-20260813-105', 'supplier_id' => 3, 'lot' => 'PANEL-DEMO-SUGAR-20260813'],
    ['batch_code' => 'IB-ADJ-20260705-716', 'supplier_id' => 6, 'lot' => 'PANEL-DEMO-VANILLA-20260705-A'],
    ['batch_code' => 'IB-ADJ-20260728-711', 'supplier_id' => 6, 'lot' => 'PANEL-DEMO-VANILLA-20260728-B'],
    ['batch_code' => 'IB-ADJ-20260813-890', 'supplier_id' => 3, 'lot' => 'PANEL-DEMO-SALT-20260813'],
    ['batch_code' => 'IB-ADJ-20260705-290', 'supplier_id' => 2, 'lot' => 'PANEL-DEMO-RENNET-20260705-A'],
    ['batch_code' => 'IB-ADJ-20260813-338', 'supplier_id' => 2, 'lot' => 'PANEL-DEMO-RENNET-20260813-B'],
];

$selectBatch = $db->prepare("SELECT ib.*, i.ingredient_name
    FROM ingredient_batches ib
    JOIN ingredients i ON i.id = ib.ingredient_id
    WHERE ib.batch_code = ? FOR UPDATE");
$supplierCheck = $db->prepare("SELECT COUNT(*)
    FROM supplier_ingredients si
    JOIN suppliers s ON s.id = si.supplier_id
    WHERE si.ingredient_id = ? AND si.supplier_id = ?
      AND si.is_active = 1 AND s.is_active = 1");
$updateBatch = $db->prepare("UPDATE ingredient_batches SET
    supplier_id = ?, supplier_batch_no = ?, qc_status = 'approved',
    qc_tested_by = ?, qc_tested_at = NOW(),
    notes = CASE
        WHEN LOCATE(?, COALESCE(notes, '')) > 0 THEN notes
        WHEN COALESCE(notes, '') = '' THEN ?
        ELSE CONCAT(notes, '\n', ?)
    END,
    updated_at = NOW()
    WHERE id = ?");

$db->beginTransaction();
try {
    $changed = [];
    foreach ($demoBatches as $demo) {
        $selectBatch->execute([$demo['batch_code']]);
        $batch = $selectBatch->fetch(PDO::FETCH_ASSOC);
        if (!$batch) {
            throw new RuntimeException("Demo batch {$demo['batch_code']} was not found");
        }
        if ((float) $batch['remaining_quantity'] <= 0) {
            throw new RuntimeException("Demo batch {$demo['batch_code']} no longer has stock");
        }
        if (empty($batch['expiry_date']) || $batch['expiry_date'] <= date('Y-m-d')) {
            throw new RuntimeException("Demo batch {$demo['batch_code']} is not safely inside its expiry date");
        }

        $supplierCheck->execute([(int) $batch['ingredient_id'], $demo['supplier_id']]);
        if ((int) $supplierCheck->fetchColumn() !== 1) {
            throw new RuntimeException("The chosen demo supplier is not linked to {$batch['ingredient_name']}");
        }

        if ((int) ($batch['supplier_id'] ?? 0) === $demo['supplier_id']
            && (string) ($batch['supplier_batch_no'] ?? '') === $demo['lot']) {
            continue;
        }

        $oldValues = [
            'supplier_id' => $batch['supplier_id'],
            'supplier_batch_no' => $batch['supplier_batch_no'],
            'qc_status' => $batch['qc_status'],
        ];
        $updateBatch->execute([
            $demo['supplier_id'],
            $demo['lot'],
            $qcUserId,
            $marker,
            $marker,
            $marker,
            (int) $batch['id'],
        ]);
        logAudit($gmUserId, 'PANEL_DEMO_TRACEABILITY', 'ingredient_batches', (int) $batch['id'], $oldValues, [
            'supplier_id' => $demo['supplier_id'],
            'supplier_batch_no' => $demo['lot'],
            'qc_status' => 'approved',
            'note' => $marker,
        ]);
        $changed[] = $batch['ingredient_name'] . ' / ' . $demo['batch_code'];
    }

    $db->commit();
    echo $changed
        ? "Prepared panel-demo inventory:\n- " . implode("\n- ", $changed) . "\n"
        : "Panel-demo inventory was already prepared. No changes were needed.\n";
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, "Panel-demo inventory was not changed: {$error->getMessage()}\n");
    exit(1);
}
