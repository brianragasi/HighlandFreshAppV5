<?php

/**
 * Resolve the current finished-goods balance before a SKU pack conversion.
 * The calculation mirrors the warehouse inventory model, but remains free of
 * HTTP/database dependencies so product updates can be regression tested.
 */
if (!function_exists('hfProductPackBaseUnits')) {
    function hfProductPackBaseUnits(array $row, int $piecesPerPack): int
    {
        $ppp = max(1, $piecesPerPack);
        $boxes = max(0, (int) ($row['boxes_available'] ?? 0));
        $loose = max(0, (int) ($row['pieces_available'] ?? 0));

        if ($boxes > 0 || $loose > 0) {
            return ($boxes * $ppp) + $loose;
        }

        $available = max(0, (int) ($row['quantity_available'] ?? 0));
        $remaining = max(0, (int) ($row['remaining_quantity'] ?? 0));
        if ($available > 0 || $remaining > 0) {
            return max($available, $remaining);
        }

        $legacyBoxes = max(0, (int) ($row['quantity_boxes'] ?? 0));
        $legacyPieces = max(0, (int) ($row['quantity_pieces'] ?? 0));
        return ($legacyBoxes * $ppp) + $legacyPieces;
    }
}

/**
 * Convert a base-unit balance into its configured dispatch pack and loose unit
 * columns. A pack size of one means there is no secondary pack configuration.
 * In that case, stock belongs in the loose/base-unit column rather than being
 * presented as one-item "boxes".
 *
 * @return array{boxes:int,pieces:int,total:int,pieces_per_pack:int}
 */
if (!function_exists('hfProductPackSplit')) {
    function hfProductPackSplit(int $baseTotal, int $piecesPerPack): array
    {
        $total = max(0, $baseTotal);
        $ppp = max(1, $piecesPerPack);

        if ($ppp === 1) {
            return [
                'boxes' => 0,
                'pieces' => $total,
                'total' => $total,
                'pieces_per_pack' => 1,
            ];
        }

        return [
            'boxes' => intdiv($total, $ppp),
            'pieces' => $total % $ppp,
            'total' => $total,
            'pieces_per_pack' => $ppp,
        ];
    }
}
