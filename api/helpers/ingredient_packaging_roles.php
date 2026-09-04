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

    // A role has no meaning outside the packaging inventory category.
    $db->exec("
        UPDATE ingredients i
        LEFT JOIN ingredient_categories c ON c.id = i.category_id
        SET i.packaging_role = NULL
        WHERE i.packaging_role IS NOT NULL
          AND NOT (LOWER(COALESCE(c.category_name, '')) LIKE '%packag%'
                   OR LOWER(COALESCE(c.category_code, '')) LIKE '%pack%'
                   OR LOWER(COALESCE(c.category_name, '')) LIKE '%container%')
    ");

    $ensuredConnections[$connectionKey] = true;
}
