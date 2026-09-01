<?php
/**
 * Finished-goods disposal balance helpers.
 *
 * `quantity_available` is the sellable/on-hand base-unit ledger. Pack columns
 * are display mirrors and must never resurrect stock after a sale or disposal.
 */

if (!function_exists('fgDisposalAvailableBaseUnits')) {
    function fgDisposalAvailableBaseUnits(array $row): int
    {
        if (array_key_exists('quantity_available', $row)) {
            return max(0, (int)$row['quantity_available']);
        }

        // Compatibility for callers that provide an older row shape only.
        return max(0, (int)($row['remaining_quantity'] ?? 0));
    }
}

if (!function_exists('fgDisposalSplitBaseUnits')) {
    /** @return array{boxes:int,pieces:int} */
    function fgDisposalSplitBaseUnits(int $baseUnits, int $piecesPerBox): array
    {
        $baseUnits = max(0, $baseUnits);
        $piecesPerBox = max(1, $piecesPerBox);

        return [
            'boxes' => intdiv($baseUnits, $piecesPerBox),
            'pieces' => $baseUnits % $piecesPerBox,
        ];
    }
}
