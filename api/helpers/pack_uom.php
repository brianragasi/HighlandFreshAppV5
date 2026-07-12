<?php
/**
 * Highland Fresh — Unified pack / base Unit of Measure helpers
 *
 * Master data lives on `products`:
 *   base_unit       → bottle, cup, piece, …
 *   box_unit        → box, crate, case, tray  (pack name)
 *   pieces_per_box  → base units per pack
 *
 * Optional aliases (if migration applied): pack_name, units_per_pack
 *
 * NEVER hardcode 12/24/50 in feature modules — always resolve via product id.
 *
 * @package HighlandFresh
 */

if (!function_exists('hf_pack_config_from_row')) {

    /**
     * Normalize a products-row (or inventory join) into a pack config.
     *
     * @param array|null $row
     * @return array{base_unit:string,pack_name:string,units_per_pack:int,pieces_per_box:int,box_unit:string}
     */
    function hf_pack_config_from_row($row = null)
    {
        $row = is_array($row) ? $row : [];
        $units = (int)($row['units_per_pack'] ?? $row['pieces_per_box'] ?? 1);
        if ($units < 1) {
            $units = 1;
        }
        $pack = trim((string)($row['pack_name'] ?? $row['box_unit'] ?? 'box'));
        if ($pack === '') {
            $pack = 'box';
        }
        $base = trim((string)($row['base_unit'] ?? 'piece'));
        if ($base === '') {
            $base = 'piece';
        }
        return [
            'base_unit' => $base,
            'pack_name' => $pack,
            'box_unit' => $pack,
            'units_per_pack' => $units,
            'pieces_per_box' => $units,
        ];
    }

    /**
     * Load pack config for a product id (with simple static cache).
     *
     * @param PDO $db
     * @param int|null $productId
     * @return array pack config
     */
    function hf_get_product_pack_config(PDO $db, $productId)
    {
        static $cache = [];
        $productId = (int)$productId;
        if ($productId <= 0) {
            return hf_pack_config_from_row(null);
        }
        if (isset($cache[$productId])) {
            return $cache[$productId];
        }

        try {
            $stmt = $db->prepare("
                SELECT
                    COALESCE(NULLIF(TRIM(base_unit), ''), 'piece') AS base_unit,
                    COALESCE(NULLIF(TRIM(pack_name), ''), NULLIF(TRIM(box_unit), ''), 'box') AS pack_name,
                    COALESCE(NULLIF(TRIM(box_unit), ''), NULLIF(TRIM(pack_name), ''), 'box') AS box_unit,
                    COALESCE(NULLIF(units_per_pack, 0), NULLIF(pieces_per_box, 0), 1) AS units_per_pack,
                    COALESCE(NULLIF(pieces_per_box, 0), NULLIF(units_per_pack, 0), 1) AS pieces_per_box
                FROM products
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$productId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            // Fallback if pack_name/units_per_pack columns don't exist yet
            if ($row === false) {
                $stmt = $db->prepare("
                    SELECT
                        COALESCE(NULLIF(TRIM(base_unit), ''), 'piece') AS base_unit,
                        COALESCE(NULLIF(TRIM(box_unit), ''), 'box') AS pack_name,
                        COALESCE(NULLIF(TRIM(box_unit), ''), 'box') AS box_unit,
                        COALESCE(NULLIF(pieces_per_box, 0), 1) AS units_per_pack,
                        COALESCE(NULLIF(pieces_per_box, 0), 1) AS pieces_per_box
                    FROM products
                    WHERE id = ?
                    LIMIT 1
                ");
                $stmt->execute([$productId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            }
        } catch (Exception $e) {
            try {
                $stmt = $db->prepare("
                    SELECT base_unit, box_unit, pieces_per_box
                    FROM products WHERE id = ? LIMIT 1
                ");
                $stmt->execute([$productId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            } catch (Exception $e2) {
                $row = [];
            }
        }

        $cfg = hf_pack_config_from_row($row);
        $cache[$productId] = $cfg;
        return $cfg;
    }

    /**
     * Split total base units into whole packs + loose base units.
     *
     * @return array{packs:int,loose:int,total:int,units_per_pack:int}
     */
    function hf_split_base_to_pack($totalPieces, $unitsPerPack)
    {
        $total = max(0, (int)$totalPieces);
        $ppb = max(1, (int)$unitsPerPack);
        return [
            'packs' => (int)floor($total / $ppb),
            'loose' => (int)($total % $ppb),
            'total' => $total,
            'units_per_pack' => $ppb,
        ];
    }

    /**
     * Convert pack order (boxes + loose) into base units.
     */
    function hf_packs_to_base($packs, $loose, $unitsPerPack)
    {
        $ppb = max(1, (int)$unitsPerPack);
        return ((int)$packs * $ppb) + max(0, (int)$loose);
    }

    /**
     * Human-readable inventory quantity from total base pieces.
     *
     * Examples:
     *   format_inventory_qty(128, 12, 'box', 'bottle') → "10 boxes + 8 bottles (128 bottles)"
     *   format_inventory_qty(50, 1, 'box', 'piece')    → "50 pieces"
     *
     * @param int    $totalPieces
     * @param int    $unitsPerPack
     * @param string $packName
     * @param string $baseUnit
     * @param array  $options  compact=true omits parenthetical total
     * @return string
     */
    function format_inventory_qty($totalPieces, $unitsPerPack = 1, $packName = 'box', $baseUnit = 'piece', array $options = [])
    {
        $total = max(0, (int)$totalPieces);
        $ppb = max(1, (int)$unitsPerPack);
        $pack = strtolower(trim((string)$packName) ?: 'box');
        $base = strtolower(trim((string)$baseUnit) ?: 'piece');
        $compact = !empty($options['compact']);

        $packLabel = hf_pluralize_unit($pack, 2);
        $baseLabel = hf_pluralize_unit($base, $total === 1 ? 1 : 2);

        if ($ppb <= 1) {
            return number_format($total) . ' ' . $baseLabel;
        }

        $split = hf_split_base_to_pack($total, $ppb);
        $packs = $split['packs'];
        $loose = $split['loose'];

        if ($packs <= 0) {
            return number_format($loose) . ' ' . hf_pluralize_unit($base, $loose === 1 ? 1 : 2);
        }

        $packPart = number_format($packs) . ' ' . hf_pluralize_unit($pack, $packs === 1 ? 1 : 2);
        if ($loose <= 0) {
            if ($compact) {
                return $packPart;
            }
            return $packPart . ' (' . number_format($total) . ' ' . $baseLabel . ')';
        }

        $loosePart = number_format($loose) . ' ' . hf_pluralize_unit($base, $loose === 1 ? 1 : 2);
        if ($compact) {
            return $packPart . ' + ' . $loosePart;
        }
        return $packPart . ' + ' . $loosePart . ' (' . number_format($total) . ' ' . $baseLabel . ')';
    }

    /**
     * Convenience: format from a product/inventory row.
     */
    function format_inventory_qty_from_row($totalPieces, array $row, array $options = [])
    {
        $cfg = hf_pack_config_from_row($row);
        return format_inventory_qty(
            $totalPieces,
            $cfg['units_per_pack'],
            $cfg['pack_name'],
            $cfg['base_unit'],
            $options
        );
    }

    /**
     * Simple English pluralization for dairy UOM labels.
     */
    function hf_pluralize_unit($unit, $count)
    {
        $unit = strtolower(trim((string)$unit));
        if ($unit === '') {
            $unit = 'unit';
        }
        if ((int)$count === 1) {
            // singularize common plurals
            if (substr($unit, -3) === 'ies') {
                return substr($unit, 0, -3) . 'y';
            }
            if (substr($unit, -1) === 's' && substr($unit, -2) !== 'ss') {
                return substr($unit, 0, -1);
            }
            return $unit;
        }
        // plural
        if (substr($unit, -1) === 's' || substr($unit, -1) === 'x' || substr($unit, -2) === 'ch') {
            return $unit . 'es';
        }
        if (substr($unit, -1) === 'y' && !in_array(substr($unit, -2, 1), ['a', 'e', 'i', 'o', 'u'], true)) {
            return substr($unit, 0, -1) . 'ies';
        }
        if (substr($unit, -1) !== 's') {
            return $unit . 's';
        }
        return $unit;
    }

    /**
     * Pack formula line for UI badges: "1 crate = 24 bottles"
     */
    function format_pack_config_line(array $row)
    {
        $cfg = hf_pack_config_from_row($row);
        if ($cfg['units_per_pack'] <= 1) {
            return 'Stored as individual ' . hf_pluralize_unit($cfg['base_unit'], 2) . ' (no pack size)';
        }
        return '1 ' . hf_pluralize_unit($cfg['pack_name'], 1)
            . ' = ' . $cfg['units_per_pack'] . ' '
            . hf_pluralize_unit($cfg['base_unit'], $cfg['units_per_pack']);
    }
}
