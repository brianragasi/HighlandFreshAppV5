<?php
/**
 * Highland Fresh — FG inventory multi-unit helpers (shared)
 *
 * Use from delivery_receipts / dispatch without loading inventory.php HTTP handlers.
 *
 * Canonical stock model (base units = bottles/pieces):
 *   base_total = (boxes_available * pieces_per_box) + pieces_available
 *   OR legacy quantity_available / remaining_quantity when multi-unit is empty.
 *
 * Pack size ALWAYS comes from products.pieces_per_box (never hardcode 12/24).
 *
 * After any deduction, always recompute:
 *   boxes = floor(base_total / pieces_per_box)
 *   loose = base_total % pieces_per_box
 * and write ALL columns in sync.
 */

require_once dirname(__DIR__) . '/helpers/pack_uom.php';

if (!function_exists('fgInventoryEffectiveBaseUnits')) {

    /**
     * Resolve total base units (bottles) from a finished_goods_inventory row.
     *
     * @param array $row Inventory row (optionally with pieces_per_box)
     * @param int|null $piecesPerBox Override pack size
     * @return int
     */
    function fgInventoryEffectiveBaseUnits(array $row, $piecesPerBox = null)
    {
        $ppb = (int)($piecesPerBox ?? $row['units_per_pack'] ?? $row['pieces_per_box'] ?? 1);
        if ($ppb < 1) {
            $ppb = 1;
        }

        $boxes = (int)($row['boxes_available'] ?? 0);
        $loose = (int)($row['pieces_available'] ?? 0);

        // Prefer multi-unit when either column has stock
        if ($boxes > 0 || $loose > 0) {
            return ($boxes * $ppb) + $loose;
        }

        // Multi-unit zeroed: trust only availability columns (never invent stock from
        // original put-away `quantity`, which often remains as historical total).
        $available = (int)($row['quantity_available'] ?? 0);
        $remaining = (int)($row['remaining_quantity'] ?? 0);
        $legacyBase = max($available, $remaining);
        if ($legacyBase > 0) {
            return $legacyBase;
        }

        // Mirror columns only if they represent on-hand multi-unit (not historical qty)
        $qBoxes = (int)($row['quantity_boxes'] ?? 0);
        $qPieces = (int)($row['quantity_pieces'] ?? 0);
        if ($qBoxes > 0 || $qPieces > 0) {
            return ($qBoxes * $ppb) + $qPieces;
        }

        return 0;
    }

    /**
     * Split base units into full packs + loose pieces.
     *
     * @return array{boxes:int,loose:int,base:int,pieces_per_box:int}
     */
    function fgInventorySplitBaseToPacks($baseTotal, $piecesPerBox)
    {
        $base = max(0, (int)$baseTotal);
        $ppb = max(1, (int)$piecesPerBox);
        return [
            'boxes' => (int)floor($base / $ppb),
            'loose' => (int)($base % $ppb),
            'base' => $base,
            'pieces_per_box' => $ppb,
        ];
    }

    /**
     * Deduct base units from a single FG inventory row (FOR UPDATE).
     * Recalculates packs + loose and keeps all quantity columns in sync.
     *
     * @param PDO $db
     * @param int $inventoryId
     * @param int $baseQty Base units to remove (e.g. 24 bottles)
     * @return array Before/after snapshot
     * @throws Exception on missing row or insufficient stock
     */
    function fgInventoryDeductBaseUnits(PDO $db, $inventoryId, $baseQty)
    {
        $inventoryId = (int)$inventoryId;
        $baseQty = (int)$baseQty;

        if ($inventoryId <= 0) {
            throw new Exception('Invalid inventory id for stock deduction.');
        }
        if ($baseQty <= 0) {
            throw new Exception('Deduction quantity must be greater than zero.');
        }

        $stmt = $db->prepare("
            SELECT fgi.*,
                   COALESCE(NULLIF(p.pieces_per_box, 0), 1) AS pieces_per_box,
                   p.product_name
            FROM finished_goods_inventory fgi
            LEFT JOIN products p ON fgi.product_id = p.id
            WHERE fgi.id = ?
            FOR UPDATE
        ");
        $stmt->execute([$inventoryId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new Exception("Inventory row #{$inventoryId} not found.");
        }

        $ppb = max(1, (int)$row['pieces_per_box']);
        $beforeBase = fgInventoryEffectiveBaseUnits($row, $ppb);

        if ($beforeBase < $baseQty) {
            $name = $row['product_name'] ?? ('Inventory #' . $inventoryId);
            throw new Exception(
                "Insufficient FG stock for '{$name}' (inventory #{$inventoryId}): " .
                "need {$baseQty} base units, have {$beforeBase}."
            );
        }

        $afterBase = $beforeBase - $baseQty;
        $split = fgInventorySplitBaseToPacks($afterBase, $ppb);

        // Bulletproof UPDATE: set multi-unit + legacy base columns together
        $update = $db->prepare("
            UPDATE finished_goods_inventory
            SET boxes_available = ?,
                pieces_available = ?,
                quantity_boxes = ?,
                quantity_pieces = ?,
                quantity_available = ?,
                remaining_quantity = ?,
                quantity = ?,
                last_movement_at = NOW()
            WHERE id = ?
              AND (
                    -- Concurrent guard: multi-unit path OR legacy availability path
                    ((COALESCE(boxes_available, 0) * ?) + COALESCE(pieces_available, 0)) >= ?
                    OR (
                        COALESCE(boxes_available, 0) = 0
                        AND COALESCE(pieces_available, 0) = 0
                        AND GREATEST(
                            COALESCE(quantity_available, 0),
                            COALESCE(remaining_quantity, 0)
                        ) >= ?
                    )
              )
        ");

        $update->execute([
            $split['boxes'],
            $split['loose'],
            $split['boxes'],
            $split['loose'],
            $afterBase,
            $afterBase,
            $afterBase,
            $inventoryId,
            $ppb,
            $baseQty,
            $baseQty,
        ]);

        if ($update->rowCount() < 1) {
            throw new Exception(
                "Failed to deduct {$baseQty} units from inventory #{$inventoryId} " .
                "(concurrent update or insufficient stock). Transaction aborted."
            );
        }

        return [
            'inventory_id' => $inventoryId,
            'product_id' => (int)$row['product_id'],
            'product_name' => $row['product_name'] ?? null,
            'pieces_per_box' => $ppb,
            'deducted' => $baseQty,
            'before_base' => $beforeBase,
            'after_base' => $afterBase,
            'after_boxes' => $split['boxes'],
            'after_loose' => $split['loose'],
        ];
    }

    /**
     * Resync packs/loose from effective base when multi-unit columns were zeroed incorrectly.
     * Does not change total base quantity — only repairs display columns.
     *
     * @return int Rows fixed
     */
    function fgInventoryResyncPackColumns(PDO $db, $inventoryId = null)
    {
        // Only repair when availability columns still hold stock but packs/loose were zeroed.
        // Do NOT treat historical `quantity` as on-hand (would resurrect depleted lots).
        $sql = "
            SELECT fgi.id, fgi.boxes_available, fgi.pieces_available,
                   fgi.quantity_available, fgi.remaining_quantity, fgi.quantity,
                   fgi.quantity_boxes, fgi.quantity_pieces,
                   COALESCE(NULLIF(p.pieces_per_box, 0), 1) AS pieces_per_box
            FROM finished_goods_inventory fgi
            LEFT JOIN products p ON fgi.product_id = p.id
            WHERE COALESCE(fgi.boxes_available, 0) = 0
              AND COALESCE(fgi.pieces_available, 0) = 0
              AND GREATEST(
                    COALESCE(fgi.quantity_available, 0),
                    COALESCE(fgi.remaining_quantity, 0)
                  ) > 0
        ";
        $params = [];
        if ($inventoryId !== null) {
            $sql .= " AND fgi.id = ?";
            $params[] = (int)$inventoryId;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $fixed = 0;

        $upd = $db->prepare("
            UPDATE finished_goods_inventory
            SET boxes_available = ?,
                pieces_available = ?,
                quantity_boxes = ?,
                quantity_pieces = ?,
                quantity_available = ?,
                remaining_quantity = ?,
                quantity = ?
            WHERE id = ?
        ");

        foreach ($rows as $row) {
            $ppb = max(1, (int)$row['pieces_per_box']);
            $base = fgInventoryEffectiveBaseUnits($row, $ppb);
            if ($base <= 0) {
                continue;
            }
            $split = fgInventorySplitBaseToPacks($base, $ppb);
            $upd->execute([
                $split['boxes'],
                $split['loose'],
                $split['boxes'],
                $split['loose'],
                $base,
                $base,
                $base,
                $row['id'],
            ]);
            $fixed++;
        }

        return $fixed;
    }

    /**
     * Restock base units onto a specific FG inventory row (resellable returns).
     * Recomputes packs + loose so UI stays in sync.
     *
     * @param PDO $db
     * @param int $inventoryId
     * @param int $baseQty Bottles/pieces to add back
     * @return array Snapshot
     */
    function fgInventoryRestockBaseUnits(PDO $db, $inventoryId, $baseQty)
    {
        $inventoryId = (int)$inventoryId;
        $baseQty = (int)$baseQty;

        if ($inventoryId <= 0) {
            throw new Exception('Invalid inventory id for restock.');
        }
        if ($baseQty <= 0) {
            throw new Exception('Restock quantity must be greater than zero.');
        }

        $stmt = $db->prepare("
            SELECT fgi.*,
                   COALESCE(NULLIF(p.pieces_per_box, 0), 1) AS pieces_per_box,
                   p.product_name
            FROM finished_goods_inventory fgi
            LEFT JOIN products p ON fgi.product_id = p.id
            WHERE fgi.id = ?
            FOR UPDATE
        ");
        $stmt->execute([$inventoryId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new Exception("Inventory row #{$inventoryId} not found for restock.");
        }

        $ppb = max(1, (int)$row['pieces_per_box']);
        $beforeBase = fgInventoryEffectiveBaseUnits($row, $ppb);
        $afterBase = $beforeBase + $baseQty;
        $split = fgInventorySplitBaseToPacks($afterBase, $ppb);

        $update = $db->prepare("
            UPDATE finished_goods_inventory
            SET boxes_available = ?,
                pieces_available = ?,
                quantity_boxes = ?,
                quantity_pieces = ?,
                quantity_available = ?,
                remaining_quantity = ?,
                quantity = ?,
                status = CASE
                    WHEN status IN ('dispatched', 'reserved') THEN 'available'
                    ELSE status
                END,
                last_movement_at = NOW()
            WHERE id = ?
        ");
        $update->execute([
            $split['boxes'],
            $split['loose'],
            $split['boxes'],
            $split['loose'],
            $afterBase,
            $afterBase,
            $afterBase,
            $inventoryId,
        ]);

        return [
            'inventory_id' => $inventoryId,
            'product_id' => (int)$row['product_id'],
            'product_name' => $row['product_name'] ?? null,
            'batch_id' => $row['batch_id'] ?? null,
            'pieces_per_box' => $ppb,
            'restocked' => $baseQty,
            'before_base' => $beforeBase,
            'after_base' => $afterBase,
            'after_boxes' => $split['boxes'],
            'after_loose' => $split['loose'],
        ];
    }

    /**
     * Resolve best FG inventory row for a product/batch (for restock).
     *
     * @return int|null inventory id
     */
    function fgInventoryFindRowForRestock(PDO $db, $productId, $batchId = null, $preferredInventoryId = null)
    {
        if ($preferredInventoryId) {
            $s = $db->prepare("SELECT id FROM finished_goods_inventory WHERE id = ? AND product_id = ? LIMIT 1");
            $s->execute([(int)$preferredInventoryId, (int)$productId]);
            $id = $s->fetchColumn();
            if ($id) {
                return (int)$id;
            }
        }

        if ($batchId) {
            $s = $db->prepare("
                SELECT id FROM finished_goods_inventory
                WHERE product_id = ? AND batch_id = ?
                ORDER BY id DESC
                LIMIT 1
            ");
            $s->execute([(int)$productId, (int)$batchId]);
            $id = $s->fetchColumn();
            if ($id) {
                return (int)$id;
            }
        }

        // Last resort: newest row for product (still better than silent drop)
        $s = $db->prepare("
            SELECT id FROM finished_goods_inventory
            WHERE product_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $s->execute([(int)$productId]);
        $id = $s->fetchColumn();
        return $id ? (int)$id : null;
    }
}
