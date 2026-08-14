<?php
/**
 * Reconcile finished_goods_inventory booked base qty vs multi-unit (boxes/pieces).
 *
 * Rules (demo / operational truth):
 *  A) If multi-unit total > 0 → multi is physical on-hand.
 *     Sync quantity_available + remaining_quantity (+ quantity if not lower) to multi.
 *  B) If multi-unit total = 0 and booked > 0 → multi never populated.
 *     Split booked into boxes + loose using product pieces_per_box.
 *
 * Safe to re-run. Logs every change.
 */
define('HIGHLAND_FRESH', true);
require dirname(__DIR__, 2) . '/api/config/config.php';
require dirname(__DIR__, 2) . '/api/config/database.php';

$db = Database::getInstance()->getConnection();
$dryRun = in_array('--dry-run', $argv ?? [], true);

echo $dryRun ? "=== DRY RUN (no writes) ===\n" : "=== APPLYING FG QTY SYNC ===\n";

$rows = $db->query("
    SELECT fgi.id, fgi.product_id, fgi.product_name, pb.batch_code,
           fgi.quantity, fgi.remaining_quantity, fgi.quantity_available,
           fgi.quantity_boxes, fgi.quantity_pieces,
           fgi.boxes_available, fgi.pieces_available,
           fgi.chiller_id, fgi.status, fgi.notes,
           COALESCE(NULLIF(p.pieces_per_box, 0), 1) AS pieces_per_box,
           COALESCE(p.base_unit, 'piece') AS base_unit,
           COALESCE(p.box_unit, 'box') AS box_unit
    FROM finished_goods_inventory fgi
    LEFT JOIN production_batches pb ON pb.id = fgi.batch_id
    LEFT JOIN products p ON p.id = fgi.product_id
    WHERE COALESCE(fgi.status, 'available') IN ('available', 'low_stock', 'reserved')
    ORDER BY fgi.id
")->fetchAll(PDO::FETCH_ASSOC);

$upd = $db->prepare("
    UPDATE finished_goods_inventory
    SET quantity = ?,
        remaining_quantity = ?,
        quantity_available = ?,
        quantity_boxes = ?,
        quantity_pieces = ?,
        boxes_available = ?,
        pieces_available = ?,
        last_movement_at = NOW(),
        notes = CONCAT(COALESCE(notes, ''), ?)
    WHERE id = ?
");

$fixed = 0;
$skipped = 0;

foreach ($rows as $r) {
    $id = (int) $r['id'];
    $ppb = max(1, (int) $r['pieces_per_box']);
    $boxes = (int) ($r['boxes_available'] ?? 0);
    $loose = (int) ($r['pieces_available'] ?? 0);
    // Prefer available multi-unit; fall back to quantity_* if available cols empty
    if ($boxes === 0 && $loose === 0) {
        $boxes = (int) ($r['quantity_boxes'] ?? 0);
        $loose = (int) ($r['quantity_pieces'] ?? 0);
    }
    $multi = ($boxes * $ppb) + $loose;

    $avail = (int) ($r['quantity_available'] ?? 0);
    $rem = (int) ($r['remaining_quantity'] ?? 0);
    $qty = (int) ($r['quantity'] ?? 0);
    $booked = $avail > 0 ? $avail : ($rem > 0 ? $rem : $qty);

    $newBoxes = $boxes;
    $newLoose = $loose;
    $newBooked = $booked;
    $newQty = $qty;
    $mode = null;

    if ($multi > 0 && $multi !== $booked) {
        // A) Multi-unit is source of truth for on-hand
        $mode = 'sync_booked_to_multi';
        $newBooked = $multi;
        $newBoxes = $boxes;
        $newLoose = $loose;
        // Original qty: keep if larger (historical receive); else match on-hand
        $newQty = max($qty, $multi);
    } elseif ($multi === 0 && $booked > 0) {
        // B) Populate multi-unit from booked
        $mode = 'split_booked_to_multi';
        $newBooked = $booked;
        if ($ppb > 1) {
            $newBoxes = intdiv($booked, $ppb);
            $newLoose = $booked % $ppb;
        } else {
            $newBoxes = 0;
            $newLoose = $booked;
        }
        $newQty = max($qty, $booked);
    } elseif ($multi > 0 && $multi === $booked) {
        // Ensure all four multi columns aligned even if booked already matches
        $qBoxes = (int) ($r['quantity_boxes'] ?? 0);
        $qLoose = (int) ($r['quantity_pieces'] ?? 0);
        $bBoxes = (int) ($r['boxes_available'] ?? 0);
        $bLoose = (int) ($r['pieces_available'] ?? 0);
        if ($qBoxes !== $bBoxes || $qLoose !== $bLoose || $avail !== $rem) {
            $mode = 'align_columns';
            $newBooked = $multi;
            $newBoxes = $bBoxes > 0 || $bLoose > 0 ? $bBoxes : $qBoxes;
            $newLoose = $bBoxes > 0 || $bLoose > 0 ? $bLoose : $qLoose;
            if ($newBoxes === 0 && $newLoose === 0) {
                $newBoxes = $ppb > 1 ? intdiv($multi, $ppb) : 0;
                $newLoose = $ppb > 1 ? ($multi % $ppb) : $multi;
            }
            $newQty = max($qty, $multi);
        }
    }

    // Also fix rem/avail mismatch when multi empty and booked derived from rem only
    if ($mode === null && $avail !== $rem && max($avail, $rem) > 0) {
        $mode = 'align_avail_rem';
        $newBooked = max($avail, $rem);
        if ($multi > 0) {
            $newBoxes = $boxes;
            $newLoose = $loose;
            $newBooked = $multi;
        } elseif ($ppb > 1) {
            $newBoxes = intdiv($newBooked, $ppb);
            $newLoose = $newBooked % $ppb;
        } else {
            $newBoxes = 0;
            $newLoose = $newBooked;
        }
        $newQty = max($qty, $newBooked);
    }

    if ($mode === null) {
        $skipped++;
        continue;
    }

    $note = sprintf(
        "\n[qty-sync %s] %s: booked %d → %d; multi %d boxes + %d loose (ppb=%d)",
        date('Y-m-d H:i:s'),
        $mode,
        $booked,
        $newBooked,
        $newBoxes,
        $newLoose,
        $ppb
    );

    echo sprintf(
        "id=%d %s %s | %s | booked %d → %d | multi %d=%dx%d+%d\n",
        $id,
        $r['product_name'],
        $r['batch_code'] ?: '(no batch)',
        $mode,
        $booked,
        $newBooked,
        ($newBoxes * $ppb) + $newLoose,
        $newBoxes,
        $ppb,
        $newLoose
    );

    if (!$dryRun) {
        $upd->execute([
            $newQty,
            $newBooked, // remaining
            $newBooked, // available
            $newBoxes,
            $newLoose,
            $newBoxes,
            $newLoose,
            $note,
            $id,
        ]);
    }
    $fixed++;
}

// Refresh chiller current_count from assigned FG stock
if (!$dryRun) {
    try {
        $db->exec("
            UPDATE chiller_locations c
            SET current_count = (
                SELECT COALESCE(SUM(
                    GREATEST(
                        COALESCE(fgi.quantity_available, 0),
                        (COALESCE(fgi.boxes_available, 0) * COALESCE(p.pieces_per_box, 1))
                            + COALESCE(fgi.pieces_available, 0)
                    )
                ), 0)
                FROM finished_goods_inventory fgi
                LEFT JOIN products p ON p.id = fgi.product_id
                WHERE fgi.chiller_id = c.id
                  AND COALESCE(fgi.status, 'available') IN ('available', 'low_stock', 'reserved')
            )
        ");
        echo "Chiller current_count refreshed.\n";
    } catch (Throwable $e) {
        echo "Chiller refresh skipped: " . $e->getMessage() . "\n";
    }
}

echo "\nDone. Fixed={$fixed} already_ok={$skipped}\n";
