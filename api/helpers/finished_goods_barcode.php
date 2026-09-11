<?php

/**
 * Build the same SKU token used by js/utils/product-display.js.
 */
if (!function_exists('hfFinishedGoodsSkuBarcodeToken')) {
    function hfFinishedGoodsSkuBarcodeToken($productCode, int $productId = 0): string
    {
        $source = trim((string) ($productCode ?? ''));
        if ($source === '') {
            $source = $productId > 0 ? 'SKU' . $productId : 'SKU';
        }

        $token = preg_replace('/[^A-Z0-9]+/', '-', strtoupper($source));
        return trim((string) $token, '-') ?: 'SKU';
    }
}

/**
 * Parse the compact phone-friendly CODE128 value printed by QC:
 *   HF4-{production batch id}-{product id}-{unit sequence}
 */
if (!function_exists('hfParseCompactFinishedGoodsLabel')) {
    function hfParseCompactFinishedGoodsLabel($scannedValue): ?array
    {
        $scan = strtoupper((string) ($scannedValue ?? ''));
        $scan = preg_replace('/[\p{Z}\p{C}\s]+/u', '', $scan);
        if (preg_match('/^HF4-(\d+)-(\d+)-(\d+)$/', trim((string) $scan), $matches) !== 1) {
            return null;
        }

        $batchId = (int) $matches[1];
        $productId = (int) $matches[2];
        $sequence = (int) $matches[3];
        if ($batchId < 1 || $productId < 1 || $sequence < 1) return null;

        return [
            'batch_id' => $batchId,
            'product_id' => $productId,
            'sequence' => $sequence,
        ];
    }
}

/**
 * Build the short CODE128 value printed on the outside of a wholesale pack.
 * The B marker keeps a box scan unambiguous from an individual-unit scan.
 *
 * Format: HFB-{production batch id}-{product id}-{units per box}-{box sequence}
 */
if (!function_exists('hfBuildCompactFinishedGoodsBoxLabel')) {
    function hfBuildCompactFinishedGoodsBoxLabel(int $batchId, int $productId, int $unitsPerPack, int $sequence): string
    {
        if ($batchId < 1 || $productId < 1 || $unitsPerPack < 2 || $sequence < 1) {
            throw new InvalidArgumentException('A box label needs a valid batch, product, pack size, and sequence.');
        }

        return "HFB-{$batchId}-{$productId}-{$unitsPerPack}-" . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}

/**
 * Parse the outside-box label without confusing it with HF4 individual labels.
 */
if (!function_exists('hfParseCompactFinishedGoodsBoxLabel')) {
    function hfParseCompactFinishedGoodsBoxLabel($scannedValue): ?array
    {
        $scan = strtoupper((string) ($scannedValue ?? ''));
        $scan = preg_replace('/[\p{Z}\p{C}\s]+/u', '', $scan);
        if (preg_match('/^HFB-(\d+)-(\d+)-(\d+)-(\d+)$/', trim((string) $scan), $matches) !== 1) {
            return null;
        }

        $batchId = (int) $matches[1];
        $productId = (int) $matches[2];
        $unitsPerPack = (int) $matches[3];
        $sequence = (int) $matches[4];
        if ($batchId < 1 || $productId < 1 || $unitsPerPack < 2 || $sequence < 1) return null;

        return [
            'label_code' => hfBuildCompactFinishedGoodsBoxLabel($batchId, $productId, $unitsPerPack, $sequence),
            'batch_id' => $batchId,
            'product_id' => $productId,
            'units_per_pack' => $unitsPerPack,
            'sequence' => $sequence,
        ];
    }
}

/**
 * Match one serialized label printed by QC:
 *   {batch barcode or batch code}-{SKU token}-{unit sequence}
 *
 * Returns the positive unit sequence when the label belongs to the supplied
 * batch/SKU, otherwise null. The sequence identifies the physical label; the
 * inventory row remains the authoritative stock record.
 */
if (!function_exists('hfMatchSerializedQcLabel')) {
    function hfMatchSerializedQcLabel(
        $scannedValue,
        $batchBarcode,
        $batchCode,
        $productCode,
        int $productId = 0
    ): ?int {
        $scan = strtoupper((string) ($scannedValue ?? ''));
        $scan = preg_replace('/[\p{Z}\p{C}\s]+/u', '', $scan);
        $scan = trim((string) $scan);
        if ($scan === '') {
            return null;
        }

        $skuToken = hfFinishedGoodsSkuBarcodeToken($productCode, $productId);
        $batchCandidates = array_values(array_unique(array_filter([
            $batchBarcode,
            $batchCode,
        ], static fn($value) => trim((string) $value) !== '')));

        foreach ($batchCandidates as $batchValue) {
            $batchToken = strtoupper((string) $batchValue);
            $batchToken = preg_replace('/[\p{Z}\p{C}\s]+/u', '', $batchToken);
            $prefix = trim((string) $batchToken) . '-' . $skuToken . '-';
            if (preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $scan, $matches) !== 1) {
                continue;
            }

            $sequence = (int) $matches[1];
            return $sequence > 0 ? $sequence : null;
        }

        return null;
    }
}
