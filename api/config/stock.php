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
 *     LOW           when current_stock <= COALESCE(reorder_point, minimum_stock * 1.5)
 *     OK            otherwise
 *
 *   par_level (the "order-up-to" target):
 *     COALESCE(maximum_stock, reorder_point * 2)
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
     * Returns COALESCE({reorderCol}, {minCol} * 1.5), tolerating a missing
     * reorder_point column by falling back to minimum_stock * 1.5. Callers
     * that already know the column exists can pass the qualified names;
     * caller is responsible for table-aliasing the column references
     * (e.g. "i.reorder_point", "i.minimum_stock").
     */
    public static function lowThresholdSql($reorderCol, $minCol)
    {
        return "COALESCE({$reorderCol}, {$minCol} * 1.5)";
    }

    /**
     * SQL fragment for the par / order-up-to level.
     *
     * COALESCE(maximum_stock, reorder_point * 2). The *2 fallback matters
     * because most rows have maximum_stock = NULL; ordering back *to* the
     * reorder_point would leave the item immediately LOW again.
     */
    public static function parLevelSql($reorderCol, $maxCol)
    {
        return "COALESCE({$maxCol}, {$reorderCol} * 2)";
    }

    /**
     * SQL fragment for the suggested reorder quantity.
     *
     * GREATEST(0, par_level - current_stock). Falls back through the same
     * par-level rule as parLevelSql() so qty is always >= 0 and only goes
     * to zero when stock is at/above par.
     */
    public static function reorderQtySql($currentCol, $reorderCol, $maxCol)
    {
        $par = self::parLevelSql($reorderCol, $maxCol);
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
    public static function parLevel($reorder, $max)
    {
        $reorder = (float) $reorder;
        $max = (float) $max;
        if ($max > 0) {
            return $max;
        }
        // 2x reorder point when no par/max is set.
        return $reorder * 2;
    }

    /**
     * PHP-side suggested reorder qty.
     */
    public static function qtyToReorder($current, $reorder, $max)
    {
        return max(0, self::parLevel($reorder, $max) - (float) $current);
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
