<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$_SERVER['REQUEST_METHOD'] = 'CLI';
require_once dirname(__DIR__) . '/api/bootstrap.php';

$apply = in_array('--apply', $argv, true);
$db = Database::getInstance()->getConnection();

$actorId = null;
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--actor-id=')) {
        $actorId = (int) substr($argument, strlen('--actor-id='));
    }
}
if ($actorId === null) {
    $actorStmt = $db->query("
        SELECT id
        FROM users
        WHERE is_active = 1
          AND username = 'admin'
          AND REPLACE(LOWER(role), ' ', '_') = 'general_manager'
        ORDER BY id
        LIMIT 1
    ");
    $actorId = (int) ($actorStmt->fetchColumn() ?: 0);
}
if ($apply && $actorId <= 0) {
    fwrite(STDERR, "An active oversight account is required. Supply --actor-id=<user id>.\n");
    exit(1);
}

$candidateSql = "
    SELECT pr.id, pr.pr_number, pr.status
    FROM purchase_requests pr
    WHERE pr.status IN ('converted', 'partially_converted')
      AND NOT EXISTS (
          SELECT 1
          FROM purchase_orders po
          WHERE po.purchase_request_id = pr.id
            AND po.status NOT IN ('cancelled', 'rejected')
      )
      AND NOT EXISTS (
          SELECT 1
          FROM purchase_request_items pri
          JOIN purchase_request_item_po prip ON prip.purchase_request_item_id = pri.id
          JOIN purchase_orders po ON po.id = prip.po_id
          WHERE pri.purchase_request_id = pr.id
            AND po.status NOT IN ('cancelled', 'rejected')
      )
    ORDER BY pr.id
";

$candidates = $db->query($candidateSql)->fetchAll(PDO::FETCH_ASSOC);
if (!$candidates) {
    echo "No stranded Purchase Request Slips found.\n";
    exit(0);
}

echo ($apply ? 'Applying' : 'Dry run for') . ' ' . count($candidates) . " stranded Purchase Request Slip(s):\n";
foreach ($candidates as $candidate) {
    echo sprintf("- #%d %s: %s -> approved\n", $candidate['id'], $candidate['pr_number'], $candidate['status']);
}

if (!$apply) {
    echo "No records changed. Run again with --apply after reviewing this list.\n";
    exit(0);
}

$db->beginTransaction();
try {
    $update = $db->prepare("
        UPDATE purchase_requests
        SET status = 'approved', updated_at = NOW()
        WHERE id = ? AND status = ?
    ");
    $history = $db->prepare("
        INSERT INTO purchase_request_status_history
        (purchase_request_id, from_status, to_status, notes, changed_by)
        VALUES (?, ?, 'approved', ?, NULL)
    ");

    foreach ($candidates as $candidate) {
        $update->execute([(int) $candidate['id'], $candidate['status']]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('PRS #' . $candidate['id'] . ' changed during repair; no partial repair was saved.');
        }

        $reason = 'Automated integrity repair: no active PO remains after linked POs were rejected or cancelled.';
        $history->execute([(int) $candidate['id'], $candidate['status'], $reason]);
        logAudit(
            $actorId,
            'STATUS_REPAIR',
            'purchase_requests',
            (int) $candidate['id'],
            ['status' => $candidate['status']],
            ['status' => 'approved', 'reason' => $reason]
        );
    }

    $db->commit();
    echo "Repair complete. The PRSs are available to Purchasing again.\n";
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, 'Repair failed: ' . $error->getMessage() . "\n");
    exit(1);
}
