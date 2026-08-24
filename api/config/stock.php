<?php
/**
 * Highland Fresh System - Stock Rule Helpers
 *
 * Single source of truth for the LOW-stock threshold and the suggested
 * reorder quantity, so every surface (Reorder Alerts, dashboards, reports,
 * admin, purchasing, maintenance, ingredients/MRO lists) agrees on what
 * "LOW" means and how much to order.
 *
 * Rule (applied consistently across the app):
 *
 *   stock_status:
 *     OUT_OF_STOCK  when current_stock <= 0
 *     LOW           when current_stock <= COALESCE(NULLIF(reorder_point, 0), minimum_stock * 1.5)
 *     OK            otherwise
 *
 *   par_level (the "order-up-to" target):
 *     configured maximum_stock when it is above the reorder point;
 *     otherwise 2x the effective reorder point
 *     When maximum_stock is unset (the common case for most ingredients),
 *     refill to 2x the reorder point instead of *to* the threshold — ordering
 *     to reorder_point would guarantee the item is immediately LOW again.
 *
 *   qty_to_reorder:
 *     GREATEST(0, par_level - current_stock)
 *
 * The `<=` boundary is intentional: the reorder point is the trigger to
 * reorder, so an item sitting exactly on it is LOW and needs an order placed.
 *
 * Used both as SQL fragment builders (for queries) and as plain PHP helpers
 * (for code-side status decisions).
 *
 * @package HighlandFresh
 * @version 4.0
 */

// Prevent direct access
if (!defined('HIGHLAND_FRESH')) {
    http_response_code(403);
    exit('Direct access not allowed');
}

class StockRule
{
    /**
     * SQL fragment for the LOW-stock threshold.
     *
     * Returns COALESCE(NULLIF({reorderCol}, 0), {minCol} * 1.5), tolerating a
     * missing or legacy-zero reorder point by falling back to minimum * 1.5. Callers
     * that already know the column exists can pass the qualified names;
     * caller is responsible for table-aliasing the column references
     * (e.g. "i.reorder_point", "i.minimum_stock").
     */
    public static function lowThresholdSql($reorderCol, $minCol)
    {
        return "COALESCE(NULLIF({$reorderCol}, 0), {$minCol} * 1.5)";
    }

    /**
     * SQL fragment for the par / order-up-to level.
     *
     * A configured maximum is used only when it is above the effective reorder
     * point. Otherwise the safe fallback is 2x reorder, so legacy invalid
     * records cannot be simultaneously "at par" and LOW.
     */
    public static function parLevelSql($reorderCol, $maxCol, $minCol = null)
    {
        $effectiveReorder = $minCol === null
            ? "NULLIF({$reorderCol}, 0)"
            : self::lowThresholdSql($reorderCol, $minCol);
        return "CASE "
            . "WHEN {$maxCol} > {$effectiveReorder} THEN {$maxCol} "
            . "ELSE {$effectiveReorder} * 2 "
            . "END";
    }

    /**
     * SQL fragment for the suggested reorder quantity.
     *
     * GREATEST(0, par_level - current_stock). Falls back through the same
     * par-level rule as parLevelSql() so qty is always >= 0 and only goes
     * to zero when stock is at/above par.
     */
    public static function reorderQtySql($currentCol, $reorderCol, $maxCol, $minCol = null)
    {
        $par = self::parLevelSql($reorderCol, $maxCol, $minCol);
        return "GREATEST(0, {$par} - {$currentCol})";
    }

    /**
     * SQL CASE expression yielding 'OUT_OF_STOCK' | 'LOW' | 'OK'.
     */
    public static function statusCaseSql($currentCol, $reorderCol, $minCol)
    {
        $threshold = self::lowThresholdSql($reorderCol, $minCol);
        return "CASE "
            . "WHEN {$currentCol} <= 0 THEN 'OUT_OF_STOCK' "
            . "WHEN {$currentCol} <= {$threshold} THEN 'LOW' "
            . "ELSE 'OK' "
            . "END";
    }

    /**
     * PHP-side par level for a single row.
     */
    public static function parLevel($reorder, $max, $min = 0)
    {
        $reorder = (float) $reorder > 0 ? (float) $reorder : (float) $min * 1.5;
        $max = (float) $max;
        if ($max > $reorder) {
            return $max;
        }
        // Invalid legacy par levels also fall back safely until corrected.
        return $reorder * 2;
    }

    /**
     * PHP-side suggested reorder qty.
     */
    public static function qtyToReorder($current, $reorder, $max, $min = 0)
    {
        return max(0, self::parLevel($reorder, $max, $min) - (float) $current);
    }

    /**
     * Validate the safety-stock, reorder, and order-up-to relationship.
     * Blank reorder/max values are represented by null and use safe defaults.
     */
    public static function thresholdValidationError($minimum, $reorder, $maximum)
    {
        $hasReorder = $reorder !== null && $reorder !== '';
        $hasMaximum = $maximum !== null && $maximum !== '';

        $parseStockLevel = static function ($value, $label, $allowBlank = false) {
            if ($allowBlank && ($value === null || $value === '')) {
                return [null, null];
            }
            if (is_array($value) || is_object($value) || is_bool($value) || $value === null) {
                return [null, "{$label} must be an ordinary number."];
            }
            $raw = trim((string) $value);
            if (!preg_match('/^(?:\d+|\d*\.\d{1,2})$/', $raw)) {
                return [null, "{$label} must be an ordinary number with no more than two decimal places; do not use e, E, +, or -."];
            }
            $number = (float) $raw;
            if (!is_finite($number) || $number > 99999999.99) {
                return [null, "{$label} must not exceed 99,999,999.99."];
            }
            return [$number, null];
        };

        [$minimum, $minimumError] = $parseStockLevel($minimum, 'Minimum stock');
        if ($minimumError !== null) {
            return $minimumError;
        }
        [$parsedReorder, $reorderError] = $parseStockLevel($reorder, 'Reorder point', true);
        if ($reorderError !== null) {
            return $reorderError;
        }
        [$parsedMaximum, $maximumError] = $parseStockLevel($maximum, 'Par level (maximum stock)', true);
        if ($maximumError !== null) {
            return $maximumError;
        }
        $reorder = $parsedReorder;
        $maximum = $parsedMaximum;

        if ($minimum < 0) {
            return 'Minimum stock cannot be negative.';
        }
        if ($hasReorder && (float) $reorder <= 0) {
            return 'Reorder point must be greater than zero, or left blank for automatic calculation.';
        }
        if ($hasReorder && (float) $reorder < $minimum) {
            return 'Reorder point must be at least the minimum stock level.';
        }

        $effectiveReorder = $hasReorder ? (float) $reorder : $minimum * 1.5;
        if ($hasMaximum && (float) $maximum <= $effectiveReorder) {
            return 'Par level (maximum stock) must be greater than the reorder point.';
        }
        return null;
    }

    /**
     * PHP-side stock status string.
     *
     * Returns 'OUT_OF_STOCK' | 'LOW' | 'OK'. Mirrors statusCaseSql() for
     * code paths that compute status per row in PHP (reports, maintenance).
     */
    public static function status($current, $reorder, $min)
    {
        $current = (float) $current;
        if ($current <= 0) {
            return 'OUT_OF_STOCK';
        }
        $threshold = ((float) $reorder) > 0
            ? (float) $reorder
            : ((float) $min) * 1.5;
        return $current <= $threshold ? 'LOW' : 'OK';
    }
}
