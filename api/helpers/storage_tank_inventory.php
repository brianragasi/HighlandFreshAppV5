<?php

/**
 * Shared storage-tank occupancy helpers.
 *
 * Tank volume is an inventory result, not editable master data. Raw and
 * pasteurized inventory ledgers are the authoritative sources.
 */

if (!defined('HIGHLAND_FRESH')) {
    http_response_code(403);
    exit('Direct access not allowed');
}
function storageTankRawVolumeSql(string $tankIdSql, string $alias = 'tank_raw'): string {
    return "COALESCE((
        SELECT SUM({$alias}.remaining_liters)
        FROM raw_milk_inventory {$alias}
        WHERE {$alias}.tank_id = {$tankIdSql}
          AND {$alias}.status IN ('available', 'reserved')
          AND {$alias}.remaining_liters > 0
    ), 0)";
}

function storageTankPasteurizedVolumeSql(string $tankIdSql, string $alias = 'tank_pasteurized'): string {
    return "COALESCE((
        SELECT SUM({$alias}.remaining_liters)
        FROM pasteurized_milk_inventory {$alias}
        WHERE {$alias}.storage_tank_id = {$tankIdSql}
          AND {$alias}.status IN ('available', 'reserved')
          AND {$alias}.remaining_liters > 0
    ), 0)";
}

function storageTankLedgerVolumeSql(string $tankIdSql): string {
    return '(' . storageTankRawVolumeSql($tankIdSql) . ' + '
        . storageTankPasteurizedVolumeSql($tankIdSql) . ')';
}

function storageTankDisplayStatusSql(string $tankAlias = 'st'): string {
    $volume = storageTankLedgerVolumeSql("{$tankAlias}.id");
    return "CASE
        WHEN {$volume} > 0 THEN 'in_use'
        WHEN {$tankAlias}.status = 'in_use' THEN 'available'
        ELSE {$tankAlias}.status
    END";
}

function storageTankLedgerVolume(PDO $db, int $tankId): float {
    $raw = storageTankRawVolumeSql('st.id');
    $pasteurized = storageTankPasteurizedVolumeSql('st.id');
    $stmt = $db->prepare("SELECT {$raw} + {$pasteurized} FROM storage_tanks st WHERE st.id = ?");
    $stmt->execute([$tankId]);
    return (float) ($stmt->fetchColumn() ?: 0);
}

/** Reconcile cached fields used by operational capacity checks. */
function reconcileStorageTankInventory(PDO $db, ?int $tankId = null): int {
    $volume = storageTankLedgerVolumeSql('st.id');
    $sql = "UPDATE storage_tanks st
        SET st.current_volume = {$volume},
            st.status = CASE
                WHEN {$volume} > 0 THEN 'in_use'
                WHEN st.status = 'in_use' THEN 'available'
                ELSE st.status
            END,
            st.updated_at = CURRENT_TIMESTAMP
        WHERE st.is_active = 1";
    $params = [];
    if ($tankId !== null) {
        $sql .= ' AND st.id = ?';
        $params[] = $tankId;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}
