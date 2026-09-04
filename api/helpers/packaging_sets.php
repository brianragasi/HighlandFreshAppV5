<?php
/**
 * Reusable packaging sets (for example: 250 mL bottle + cap + label).
 *
 * Sets are master-data templates only. Applying a set copies its active items
 * into the SKU BOM so Production continues to use the existing audited BOM and
 * stock-deduction flow.
 */

if (defined('PACKAGING_SET_HELPERS_LOADED')) {
    return;
}
define('PACKAGING_SET_HELPERS_LOADED', true);

require_once __DIR__ . '/sku_packaging_bom.php';

function ensurePackagingSetTables(PDO $db): void
{
    static $ensuredConnections = [];
    $key = function_exists('spl_object_id') ? spl_object_id($db) : spl_object_hash($db);
    if (isset($ensuredConnections[$key])) {
        return;
    }

    try {
        $db->query('SELECT 1 FROM packaging_sets LIMIT 0');
        $db->query('SELECT 1 FROM packaging_set_items LIMIT 0');
        $ensuredConnections[$key] = true;
        return;
    } catch (Throwable $e) {
        // Missing on an older installation. Create before any business transaction.
    }

    if ($db->inTransaction()) {
        throw new RuntimeException('Packaging-set storage must be initialized before starting a transaction');
    }

    $db->exec("CREATE TABLE IF NOT EXISTS packaging_sets (
        id INT NOT NULL AUTO_INCREMENT,
        set_name VARCHAR(120) NOT NULL,
        capacity_value DECIMAL(12,3) NOT NULL,
        capacity_unit VARCHAR(20) NOT NULL,
        description VARCHAR(500) NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_by INT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_packaging_set_name (set_name),
        KEY idx_packaging_set_capacity (capacity_value, capacity_unit, is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS packaging_set_items (
        id INT NOT NULL AUTO_INCREMENT,
        packaging_set_id INT NOT NULL,
        ingredient_id INT NOT NULL,
        quantity_per_unit DECIMAL(12,6) NOT NULL,
        waste_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_packaging_set_material (packaging_set_id, ingredient_id),
        KEY idx_packaging_set_item_material (ingredient_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $ensuredConnections[$key] = true;
}

function getPackagingSets(PDO $db, bool $includeInactive = false): array
{
    ensurePackagingSetTables($db);
    ensureIngredientPackagingRoleSupport($db);
    $where = $includeInactive ? '' : 'WHERE ps.is_active = 1';
    $sets = $db->query("SELECT ps.* FROM packaging_sets ps $where ORDER BY ps.capacity_value, ps.set_name")
        ->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (!$sets) {
        return [];
    }

    $ids = array_map(static fn($row) => (int) $row['id'], $sets);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT psi.packaging_set_id, psi.ingredient_id,
            psi.quantity_per_unit, psi.waste_percent,
            i.ingredient_code, i.ingredient_name, i.unit_of_measure,
            i.packaging_role, i.packaging_capacity_value, i.packaging_capacity_unit
        FROM packaging_set_items psi
        JOIN ingredients i ON i.id = psi.ingredient_id
        WHERE psi.is_active = 1 AND i.is_active = 1
          AND psi.packaging_set_id IN ($placeholders)
        ORDER BY FIELD(i.packaging_role, 'container', 'closure', 'label', 'secondary', 'other'), i.ingredient_name");
    $stmt->execute($ids);
    $itemsBySet = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $item) {
        $itemsBySet[(int) $item['packaging_set_id']][] = $item;
    }

    foreach ($sets as &$set) {
        $set['items'] = $itemsBySet[(int) $set['id']] ?? [];
        $roles = array_values(array_filter(array_map(
            static fn($item) => normalizeIngredientPackagingRole($item['packaging_role'] ?? null),
            $set['items']
        )));
        $set['missing_components'] = [];
        foreach (['container' => 'bottle/container', 'closure' => 'cap/closure', 'label' => 'label'] as $role => $label) {
            if (!in_array($role, $roles, true)) {
                $set['missing_components'][] = $label;
            }
        }
        $set['is_ready'] = empty($set['missing_components']);
        $canonical = packagingCanonicalSize($set['capacity_value'], $set['capacity_unit']);
        $set['canonical_capacity'] = $canonical ? $canonical['value'] : null;
    }
    unset($set);
    return $sets;
}

function normalizePackagingSetPayload(PDO $db, array $data): array
{
    $name = trim((string) ($data['set_name'] ?? ''));
    $capacityValue = filter_var($data['capacity_value'] ?? null, FILTER_VALIDATE_FLOAT);
    $capacityUnit = normalizeIngredientPackagingCapacityUnit($data['capacity_unit'] ?? null);
    $errors = [];
    if ($name === '' || mb_strlen($name) > 120) {
        $errors['set_name'] = 'Enter a packaging-set name up to 120 characters';
    }
    if ($capacityValue === false || $capacityValue <= 0) {
        $errors['capacity_value'] = 'Enter the finished package size';
    }
    if (!in_array($capacityUnit, ['ml', 'L', 'g', 'kg'], true)) {
        $errors['capacity_unit'] = 'Choose mL, L, g, or kg';
    }

    $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];
    if (!$items) {
        $errors['items'] = 'Add at least one packaging material';
    }
    $normalizedItems = [];
    $seen = [];
    $materialLookup = $db->prepare("SELECT i.id, i.ingredient_name, i.unit_of_measure, i.packaging_role,
            i.packaging_capacity_value, i.packaging_capacity_unit, i.is_active,
            c.category_name, c.category_code
        FROM ingredients i
        LEFT JOIN ingredient_categories c ON c.id = i.category_id
        WHERE i.id = ?");
    foreach ($items as $index => $item) {
        $ingredientId = (int) ($item['ingredient_id'] ?? 0);
        $quantity = filter_var($item['quantity_per_unit'] ?? null, FILTER_VALIDATE_FLOAT);
        $waste = filter_var($item['waste_percent'] ?? 0, FILTER_VALIDATE_FLOAT);
        if ($ingredientId <= 0 || isset($seen[$ingredientId])) {
            $errors["items.$index.ingredient_id"] = $ingredientId <= 0
                ? 'Choose a packaging material'
                : 'Each packaging material may appear only once';
            continue;
        }
        $seen[$ingredientId] = true;
        $materialLookup->execute([$ingredientId]);
        $material = $materialLookup->fetch(PDO::FETCH_ASSOC);
        if (!$material || (int) ($material['is_active'] ?? 0) !== 1
            || !isPackagingIngredientCategory($material['category_name'] ?? '', $material['category_code'] ?? '')) {
            $errors["items.$index.ingredient_id"] = 'Choose an active packaging material';
            continue;
        }
        if ($quantity === false || $quantity <= 0 || $quantity > 1000) {
            $errors["items.$index.quantity_per_unit"] = 'Usage must be greater than zero and no more than 1,000';
        }
        if ($waste === false || $waste < 0 || $waste > 100) {
            $errors["items.$index.waste_percent"] = 'Waste allowance must be from 0% to 100%';
        }
        if (isRollPackagingUnit($material['unit_of_measure'] ?? '') && $quantity !== false && $quantity > 0) {
            $coverage = 1 / (float) $quantity;
            if ($coverage < 1 || $coverage > 100 || abs($coverage - round($coverage)) > 0.000001) {
                $errors["items.$index.quantity_per_unit"] = 'For a roll, enter a usage such as 0.01 when one roll covers 100 products (maximum coverage: 100)';
            }
        }
        if (packagingMaterialMustBeOnePerFinishedUnit($material['packaging_role'] ?? null, $material['unit_of_measure'] ?? '')
            && abs((float) $quantity - 1.0) > 0.000001) {
            $errors["items.$index.quantity_per_unit"] = ($material['ingredient_name'] ?? 'This component') . ' must use 1 per finished product';
        }
        $normalizedItems[] = [
            'ingredient_id' => $ingredientId,
            'quantity_per_unit' => (float) $quantity,
            'waste_percent' => (float) $waste,
            'unit' => $material['unit_of_measure'] ?? 'piece',
            'packaging_role' => $material['packaging_role'] ?? null,
            'ingredient_name' => $material['ingredient_name'] ?? '',
            'packaging_capacity_value' => $material['packaging_capacity_value'] ?? null,
            'packaging_capacity_unit' => $material['packaging_capacity_unit'] ?? null,
        ];
    }

    if (!$errors && $capacityValue !== false && $capacityUnit) {
        $sizeErrors = validateSkuPackagingBomSizes('bottle', $capacityValue, $capacityUnit, $normalizedItems);
        if ($sizeErrors) {
            $errors['items.capacity'] = implode('; ', $sizeErrors);
        }
    }
    return [[
        'set_name' => $name,
        'capacity_value' => (float) $capacityValue,
        'capacity_unit' => $capacityUnit,
        'description' => trim((string) ($data['description'] ?? '')) ?: null,
        'is_active' => isset($data['is_active']) ? (int) ((bool) $data['is_active']) : 1,
        'items' => $normalizedItems,
    ], $errors];
}

function savePackagingSet(PDO $db, array $payload, ?int $id, ?int $userId): int
{
    ensurePackagingSetTables($db);
    [$data, $errors] = normalizePackagingSetPayload($db, $payload);
    if ($errors) {
        sendValidationError($errors);
    }

    $db->beginTransaction();
    try {
        if ($id) {
            $exists = $db->prepare('SELECT id FROM packaging_sets WHERE id = ? FOR UPDATE');
            $exists->execute([$id]);
            if (!$exists->fetchColumn()) {
                $db->rollBack();
                sendError('Packaging set not found', 404);
            }
            $stmt = $db->prepare('UPDATE packaging_sets SET set_name = ?, capacity_value = ?, capacity_unit = ?, description = ?, is_active = ? WHERE id = ?');
            $stmt->execute([$data['set_name'], $data['capacity_value'], $data['capacity_unit'], $data['description'], $data['is_active'], $id]);
        } else {
            $stmt = $db->prepare('INSERT INTO packaging_sets (set_name, capacity_value, capacity_unit, description, is_active, created_by) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$data['set_name'], $data['capacity_value'], $data['capacity_unit'], $data['description'], $data['is_active'], $userId]);
            $id = (int) $db->lastInsertId();
        }

        $db->prepare('UPDATE packaging_set_items SET is_active = 0 WHERE packaging_set_id = ?')->execute([$id]);
        $upsert = $db->prepare("INSERT INTO packaging_set_items
            (packaging_set_id, ingredient_id, quantity_per_unit, waste_percent, is_active)
            VALUES (?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE quantity_per_unit = VALUES(quantity_per_unit),
                waste_percent = VALUES(waste_percent), is_active = 1");
        foreach ($data['items'] as $item) {
            $upsert->execute([$id, $item['ingredient_id'], $item['quantity_per_unit'], $item['waste_percent']]);
        }
        $db->commit();
        return $id;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}
