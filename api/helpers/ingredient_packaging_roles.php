<?php
/**
 * Explicit packaging-component classification for ingredient master records.
 *
 * "Packaging Materials" is only a broad inventory category. The role tells
 * SKU BOM validation whether a material is the container, closure, label, or
 * optional secondary packaging. Runtime workflows must use this field instead
 * of guessing from user-entered material names.
 */

if (defined('INGREDIENT_PACKAGING_ROLES_LOADED')) {
    return;
}
define('INGREDIENT_PACKAGING_ROLES_LOADED', true);

function ingredientPackagingRoleValues(): array
{
    return ['container', 'closure', 'label', 'secondary', 'other'];
}

/**
 * The physical packaging component selected by Admin.  `packaging_role`
 * remains the accounting/BOM role; this field prevents a pouch, bottle, and
 * tub from becoming indistinguishable just because all three are primary
 * containers.
 */
function ingredientPackagingFormValues(): array
{
    return [
        'bottle', 'printed_pouch', 'plain_pouch', 'cup_tub', 'wrapper',
        'cap_lid', 'label', 'carton_case', 'other',
    ];
}

function normalizeIngredientPackagingForm($value): ?string
{
    $form = strtolower(trim((string) $value));
    $aliases = [
        'pouch' => 'plain_pouch',
        'sachet' => 'plain_pouch',
        'printed_sachet' => 'printed_pouch',
        'cup' => 'cup_tub',
        'tub' => 'cup_tub',
        'cap' => 'cap_lid',
        'lid' => 'cap_lid',
        'case' => 'carton_case',
        'carton' => 'carton_case',
    ];
    $form = $aliases[$form] ?? $form;
    return in_array($form, ingredientPackagingFormValues(), true) ? $form : null;
}

function ingredientPackagingFormRole($value): ?string
{
    $form = normalizeIngredientPackagingForm($value);
    $roles = [
        'bottle' => 'container',
        'printed_pouch' => 'container',
        'plain_pouch' => 'container',
        'cup_tub' => 'container',
        'wrapper' => 'container',
        'cap_lid' => 'closure',
        'label' => 'label',
        'carton_case' => 'secondary',
        'other' => 'other',
    ];
    return $form !== null ? $roles[$form] : null;
}

function ingredientPackagingFormLabel($value): string
{
    $labels = [
        'bottle' => 'Bottle / Container',
        'printed_pouch' => 'Printed Pouch / Sachet',
        'plain_pouch' => 'Plain Pouch / Sachet',
        'cup_tub' => 'Cup / Tub',
        'wrapper' => 'Primary Wrapper',
        'cap_lid' => 'Cap / Lid',
        'label' => 'Separate Label',
        'carton_case' => 'Carton / Case',
        'other' => 'Other Packaging',
    ];
    $form = normalizeIngredientPackagingForm($value);
    return $form !== null ? $labels[$form] : 'Unclassified Packaging';
}

function normalizeIngredientPackagingRole($value): ?string
{
    $role = strtolower(trim((string) $value));
    $aliases = [
        'bottle' => 'container',
        'bottle_container' => 'container',
        'cap' => 'closure',
        'cap_closure' => 'closure',
        'lid' => 'closure',
        'wrap' => 'secondary',
        'secondary_packaging' => 'secondary',
    ];
    $role = $aliases[$role] ?? $role;
    return in_array($role, ingredientPackagingRoleValues(), true) ? $role : null;
}

function ingredientPackagingRoleLabel($value): string
{
    $labels = [
        'container' => 'Bottle / Container',
        'closure' => 'Cap / Closure',
        'label' => 'Label',
        'secondary' => 'Wrap / Secondary Packaging',
        'other' => 'Other Packaging',
    ];
    $role = normalizeIngredientPackagingRole($value);
    return $role !== null ? $labels[$role] : 'Unclassified Packaging';
}

function ingredientPackagingRoleColumnExists(PDO $db, string $column): bool
{
    $stmt = $db->prepare("
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'ingredients'
          AND COLUMN_NAME = ?
        LIMIT 1
    ");
    $stmt->execute([$column]);
    return (bool) $stmt->fetchColumn();
}

function inferIngredientPackagingCapacityFromName($name): ?array
{
    if (preg_match(
        '/(\d+(?:\.\d+)?)\s*(millilit(?:er|re)s?|ml|lit(?:er|re)s?|lt|l|kilograms?|kg|grams?|g)\b/i',
        (string) $name,
        $match
    ) !== 1) {
        return null;
    }

    $unit = strtolower($match[2]);
    if (in_array($unit, ['milliliter', 'milliliters', 'millilitre', 'millilitres', 'ml'], true)) {
        $unit = 'ml';
    } elseif (in_array($unit, ['liter', 'liters', 'litre', 'litres', 'lt', 'l'], true)) {
        $unit = 'L';
    } elseif (in_array($unit, ['kilogram', 'kilograms', 'kg'], true)) {
        $unit = 'kg';
    } else {
        $unit = 'g';
    }

    return ['value' => (float) $match[1], 'unit' => $unit];
}

function normalizeIngredientPackagingCapacityUnit($value): ?string
{
    $unit = strtolower(trim((string) $value));
    $aliases = [
        'milliliter' => 'ml', 'milliliters' => 'ml', 'millilitre' => 'ml', 'millilitres' => 'ml',
        'liter' => 'L', 'liters' => 'L', 'litre' => 'L', 'litres' => 'L', 'lt' => 'L', 'l' => 'L',
        'gram' => 'g', 'grams' => 'g',
        'kilogram' => 'kg', 'kilograms' => 'kg', 'kg' => 'kg',
    ];
    return $aliases[$unit] ?? ($unit === 'ml' || $unit === 'g' ? $unit : null);
}

/**
 * Add the field and classify imported legacy records once. The closure and
 * label checks intentionally run before container, so names such as "Bottle
 * Cap" and "Bottle Label" receive the correct role.
 */
function ensureIngredientPackagingRoleSupport(PDO $db): void
{
    static $ensuredConnections = [];
    $connectionKey = function_exists('spl_object_id') ? spl_object_id($db) : spl_object_hash($db);
    if (isset($ensuredConnections[$connectionKey])) {
        return;
    }

    if (!ingredientPackagingRoleColumnExists($db, 'packaging_role')) {
        if ($db->inTransaction()) {
            throw new RuntimeException(
                'Packaging roles must be initialized before starting a database transaction'
            );
        }
        $db->exec("ALTER TABLE `ingredients`
            ADD COLUMN `packaging_role` VARCHAR(30) NULL AFTER `physical_state`");
    }

    if (!ingredientPackagingRoleColumnExists($db, 'packaging_form')) {
        if ($db->inTransaction()) {
            throw new RuntimeException('Packaging form must be initialized before starting a database transaction');
        }
        $db->exec("ALTER TABLE `ingredients`
            ADD COLUMN `packaging_form` VARCHAR(30) NULL AFTER `packaging_role`");
    }

    if (!ingredientPackagingRoleColumnExists($db, 'packaging_capacity_value')) {
        if ($db->inTransaction()) {
            throw new RuntimeException('Packaging capacity must be initialized before starting a database transaction');
        }
        $db->exec("ALTER TABLE `ingredients`
            ADD COLUMN `packaging_capacity_value` DECIMAL(12,3) NULL AFTER `packaging_role`");
    }
    if (!ingredientPackagingRoleColumnExists($db, 'packaging_capacity_unit')) {
        if ($db->inTransaction()) {
            throw new RuntimeException('Packaging capacity must be initialized before starting a database transaction');
        }
        $db->exec("ALTER TABLE `ingredients`
            ADD COLUMN `packaging_capacity_unit` VARCHAR(20) NULL AFTER `packaging_capacity_value`");
    }
    if (!ingredientPackagingRoleColumnExists($db, 'packaging_capacity_confirmed')) {
        if ($db->inTransaction()) {
            throw new RuntimeException('Packaging capacity confirmation must be initialized before starting a transaction');
        }
        $db->exec("ALTER TABLE `ingredients`
            ADD COLUMN `packaging_capacity_confirmed` TINYINT(1) NOT NULL DEFAULT 0 AFTER `packaging_capacity_unit`");
        $db->exec("UPDATE ingredients
            SET packaging_capacity_confirmed = 1
            WHERE packaging_capacity_unit <> 'L' OR packaging_capacity_value < 20");
    }

    // This is migration/backfill logic only. All new and edited packaging
    // materials must provide an explicit role through the Admin form/API.
    $db->exec("
        UPDATE ingredients i
        JOIN ingredient_categories c ON c.id = i.category_id
        SET i.packaging_role = CASE
            WHEN LOWER(CONCAT(COALESCE(i.ingredient_name, ''), ' ', COALESCE(i.ingredient_code, '')))
                 REGEXP 'label|sticker' THEN 'label'
            WHEN LOWER(CONCAT(COALESCE(i.ingredient_name, ''), ' ', COALESCE(i.ingredient_code, '')))
                 REGEXP 'cap|lid|closure|stopper|seal' THEN 'closure'
            WHEN LOWER(CONCAT(COALESCE(i.ingredient_name, ''), ' ', COALESCE(i.ingredient_code, '')))
                 REGEXP 'bottle|container|jar|cup|tub|pouch|carton' THEN 'container'
            WHEN LOWER(CONCAT(COALESCE(i.ingredient_name, ''), ' ', COALESCE(i.ingredient_code, '')))
                 REGEXP 'wrap|film|cellophane|box|case|crate|tray|bundle' THEN 'secondary'
            ELSE 'other'
        END
        WHERE (LOWER(COALESCE(c.category_name, '')) LIKE '%packag%'
               OR LOWER(COALESCE(c.category_code, '')) LIKE '%pack%'
               OR LOWER(COALESCE(c.category_name, '')) LIKE '%container%')
          AND (i.packaging_role IS NULL
               OR i.packaging_role NOT IN ('container', 'closure', 'label', 'secondary', 'other'))
    ");

    // Preserve imported records with a conservative one-time classification.
    // New records never depend on their name: the Admin wizard sends the form.
    $db->exec("
        UPDATE ingredients i
        JOIN ingredient_categories c ON c.id = i.category_id
        SET i.packaging_form = CASE
            WHEN i.packaging_role = 'label' THEN 'label'
            WHEN i.packaging_role = 'closure' THEN 'cap_lid'
            WHEN i.packaging_role = 'secondary' THEN 'carton_case'
            WHEN LOWER(COALESCE(i.ingredient_name, '')) REGEXP 'printed.*(pouch|sachet)|(pouch|sachet).*printed' THEN 'printed_pouch'
            WHEN LOWER(COALESCE(i.ingredient_name, '')) REGEXP 'pouch|sachet' THEN 'plain_pouch'
            WHEN LOWER(COALESCE(i.ingredient_name, '')) REGEXP 'cup|tub' THEN 'cup_tub'
            WHEN LOWER(COALESCE(i.ingredient_name, '')) REGEXP 'wrapper|film|cellophane' THEN 'wrapper'
            WHEN i.packaging_role = 'container' THEN 'bottle'
            ELSE 'other'
        END
        WHERE (LOWER(COALESCE(c.category_name, '')) LIKE '%packag%'
               OR LOWER(COALESCE(c.category_code, '')) LIKE '%pack%'
               OR LOWER(COALESCE(c.category_name, '')) LIKE '%container%')
          AND (i.packaging_form IS NULL
               OR i.packaging_form NOT IN ('bottle','printed_pouch','plain_pouch','cup_tub','wrapper','cap_lid','label','carton_case','other'))
    ");

    // One-time compatibility backfill for imported bottle/label records. New
    // master records must provide these fields explicitly in the Admin form.
    $rows = $db->query("
        SELECT id, ingredient_name
        FROM ingredients
        WHERE packaging_role IN ('container', 'label')
          AND (packaging_capacity_value IS NULL OR packaging_capacity_value <= 0
               OR packaging_capacity_unit IS NULL OR packaging_capacity_unit = '')
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if ($rows) {
        $update = $db->prepare("
            UPDATE ingredients
            SET packaging_capacity_value = ?, packaging_capacity_unit = ?
            WHERE id = ?
        ");
        foreach ($rows as $row) {
            $capacity = inferIngredientPackagingCapacityFromName($row['ingredient_name'] ?? '');
            if ($capacity) {
                $update->execute([$capacity['value'], $capacity['unit'], (int) $row['id']]);
            }
        }
    }

    $db->exec("
        UPDATE ingredients
        SET packaging_capacity_value = NULL, packaging_capacity_unit = NULL
        WHERE packaging_role IS NULL
           OR packaging_role NOT IN ('container', 'label')
    ");

    // A role has no meaning outside the packaging inventory category.
    $db->exec("
        UPDATE ingredients i
        LEFT JOIN ingredient_categories c ON c.id = i.category_id
        SET i.packaging_role = NULL,
            i.packaging_form = NULL,
            i.packaging_capacity_value = NULL,
            i.packaging_capacity_unit = NULL
        WHERE i.packaging_role IS NOT NULL
          AND NOT (LOWER(COALESCE(c.category_name, '')) LIKE '%packag%'
                   OR LOWER(COALESCE(c.category_code, '')) LIKE '%pack%'
                   OR LOWER(COALESCE(c.category_name, '')) LIKE '%container%')
    ");

    $ensuredConnections[$connectionKey] = true;
}

function productPrimaryContainerColumnExists(PDO $db): bool
{
    $stmt = $db->query("
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'products'
          AND COLUMN_NAME = 'primary_container_id'
        LIMIT 1
    ");
    return (bool) $stmt->fetchColumn();
}

function ensureProductPrimaryContainerSupport(PDO $db): void
{
    static $ensuredConnections = [];
    $connectionKey = function_exists('spl_object_id') ? spl_object_id($db) : spl_object_hash($db);
    if (isset($ensuredConnections[$connectionKey])) {
        return;
    }
    if (!productPrimaryContainerColumnExists($db)) {
        if ($db->inTransaction()) {
            throw new RuntimeException('Primary container support must be initialized before starting a database transaction');
        }
        $db->exec("ALTER TABLE `products`
            ADD COLUMN `primary_container_id` INT NULL AFTER `unit_measure`,
            ADD KEY `idx_products_primary_container` (`primary_container_id`)");
    }

    // Preserve legacy SKU/BOM links when a clearly classified container is
    // already present. Ambiguous legacy SKUs remain unset until Admin chooses.
    try {
        $db->exec("
            UPDATE products p
            JOIN (
                SELECT spbi.product_id, MIN(spbi.ingredient_id) AS ingredient_id
                FROM sku_packaging_bom_items spbi
                JOIN ingredients i ON i.id = spbi.ingredient_id
                WHERE spbi.is_active = 1 AND i.packaging_role = 'container'
                GROUP BY spbi.product_id
                HAVING COUNT(*) = 1
            ) linked ON linked.product_id = p.id
            SET p.primary_container_id = linked.ingredient_id
            WHERE p.primary_container_id IS NULL
              AND LOWER(COALESCE(p.base_unit, '')) IN
                  ('bottle', 'bottles', 'printed_pouch', 'plain_pouch', 'tub', 'jar', 'wrapped_block', 'bulk_container')
        ");
    } catch (Throwable $e) {
        // The BOM table is optional on older installations; it is created by
        // the product endpoint immediately after this schema check.
    }

    $ensuredConnections[$connectionKey] = true;
}
