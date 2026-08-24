<?php

function ensureSupplierIngredientCatalog(PDO $db): void {
    $hadSupplierOfferColumns = auditColumnExists($db, 'supplier_ingredients', 'purchase_format');
    $db->exec("
        CREATE TABLE IF NOT EXISTS supplier_ingredients (
            id INT NOT NULL AUTO_INCREMENT,
            supplier_id INT NOT NULL,
            ingredient_id INT NOT NULL,
            reference_unit_price DECIMAL(12,6) DEFAULT NULL,
            purchase_format VARCHAR(20) NOT NULL DEFAULT 'direct_unit',
            package_type VARCHAR(30) DEFAULT NULL,
            package_size_value DECIMAL(12,3) DEFAULT NULL,
            package_size_unit VARCHAR(20) DEFAULT NULL,
            package_quantity_in_stock_unit DECIMAL(12,6) DEFAULT NULL,
            quoted_price DECIMAL(12,6) DEFAULT NULL,
            price_basis VARCHAR(20) NOT NULL DEFAULT 'stock_unit',
            offer_label VARCHAR(120) DEFAULT NULL,
            enforce_whole_packages TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by INT DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_supplier_ingredient (supplier_id, ingredient_id),
            KEY idx_supplier_ingredient_supplier (supplier_id, is_active),
            KEY idx_supplier_ingredient_item (ingredient_id, is_active),
            CONSTRAINT fk_supplier_ingredient_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
            CONSTRAINT fk_supplier_ingredient_item FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $offerColumns = [
        'purchase_format' => "ALTER TABLE supplier_ingredients ADD COLUMN purchase_format VARCHAR(20) NOT NULL DEFAULT 'direct_unit' AFTER reference_unit_price",
        'package_type' => "ALTER TABLE supplier_ingredients ADD COLUMN package_type VARCHAR(30) NULL AFTER purchase_format",
        'package_size_value' => "ALTER TABLE supplier_ingredients ADD COLUMN package_size_value DECIMAL(12,3) NULL AFTER package_type",
        'package_size_unit' => "ALTER TABLE supplier_ingredients ADD COLUMN package_size_unit VARCHAR(20) NULL AFTER package_size_value",
        'package_quantity_in_stock_unit' => "ALTER TABLE supplier_ingredients ADD COLUMN package_quantity_in_stock_unit DECIMAL(12,6) NULL AFTER package_size_unit",
        'quoted_price' => "ALTER TABLE supplier_ingredients ADD COLUMN quoted_price DECIMAL(12,6) NULL AFTER package_quantity_in_stock_unit",
        'price_basis' => "ALTER TABLE supplier_ingredients ADD COLUMN price_basis VARCHAR(20) NOT NULL DEFAULT 'stock_unit' AFTER quoted_price",
        'offer_label' => "ALTER TABLE supplier_ingredients ADD COLUMN offer_label VARCHAR(120) NULL AFTER price_basis",
        'enforce_whole_packages' => "ALTER TABLE supplier_ingredients ADD COLUMN enforce_whole_packages TINYINT(1) NOT NULL DEFAULT 0 AFTER offer_label",
    ];
    foreach ($offerColumns as $column => $sql) {
        if (!auditColumnExists($db, 'supplier_ingredients', $column)) {
            $db->exec($sql);
        }
    }
    $db->exec("ALTER TABLE supplier_ingredients MODIFY reference_unit_price DECIMAL(12,6) NULL");
    $db->exec("ALTER TABLE supplier_ingredients MODIFY quoted_price DECIMAL(12,6) NULL");

    // Keep the readable offer description aligned with the exact package type.
    // This also repairs legacy rows such as a package saved as "container" but
    // previously described as a "crate".
    $db->exec("
        UPDATE supplier_ingredients
        SET offer_label = CONCAT(
            TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM CAST(package_size_value AS CHAR))),
            ' ', package_size_unit, ' ', package_type
        )
        WHERE purchase_format = 'packaged'
          AND package_size_value IS NOT NULL
          AND package_size_unit IS NOT NULL
          AND package_type IS NOT NULL
          AND (
              offer_label IS NULL OR offer_label = ''
              OR LOWER(SUBSTRING_INDEX(offer_label, ' ', -1)) <> LOWER(package_type)
          )
    ");

    // One-time compatibility move: the old design stored one buying format on
    // the ingredient. Copy that best-known format to every existing supplier
    // offer, then return the ingredient master to stock-unit-only behavior.
    if (!$hadSupplierOfferColumns && auditColumnExists($db, 'ingredients', 'purchase_format')) {
        $db->exec("
            UPDATE supplier_ingredients si
            JOIN ingredients i ON i.id = si.ingredient_id
            SET si.purchase_format = CASE WHEN i.purchase_format = 'packaged' AND i.pack_size_value > 0 THEN 'packaged' ELSE 'direct_unit' END,
                si.package_type = CASE
                    WHEN i.purchase_format = 'packaged' AND i.pack_size_value > 0
                    THEN COALESCE(NULLIF(i.purchase_package_type, ''), NULLIF(i.container_type, ''), 'package')
                    ELSE NULL
                END,
                si.package_size_value = CASE WHEN i.purchase_format = 'packaged' AND i.pack_size_value > 0 THEN i.pack_size_value ELSE NULL END,
                si.package_size_unit = CASE WHEN i.purchase_format = 'packaged' AND i.pack_size_value > 0 THEN i.unit_of_measure ELSE NULL END,
                si.package_quantity_in_stock_unit = CASE WHEN i.purchase_format = 'packaged' AND i.pack_size_value > 0 THEN i.pack_size_value ELSE 1 END,
                si.quoted_price = CASE
                    WHEN si.reference_unit_price IS NULL THEN NULL
                    WHEN i.purchase_format = 'packaged' AND i.pack_size_value > 0 THEN si.reference_unit_price * i.pack_size_value
                    ELSE si.reference_unit_price
                END,
                si.price_basis = CASE WHEN i.purchase_format = 'packaged' AND i.pack_size_value > 0 THEN 'package' ELSE 'stock_unit' END,
                si.offer_label = CASE
                    WHEN i.purchase_format = 'packaged' AND i.pack_size_value > 0
                    THEN COALESCE(NULLIF(i.pack_label, ''), CONCAT(i.pack_size_value, ' ', i.unit_of_measure, ' package'))
                    ELSE CONCAT('Direct per ', i.unit_of_measure)
                END,
                si.enforce_whole_packages = CASE WHEN i.purchase_format = 'packaged' AND i.pack_size_value > 0 THEN 1 ELSE 0 END
        ");

        $db->exec("
            UPDATE ingredients
            SET purchase_format = 'direct_unit',
                container_type = NULL,
                container_size_value = NULL,
                container_size_unit = NULL,
                purchase_package_type = NULL,
                containers_per_purchase_package = NULL,
                purchase_price_basis = 'stock_unit',
                purchase_price = NULL,
                pack_size_value = NULL,
                pack_size_unit = NULL,
                pack_label = NULL,
                enforce_whole_packs = 0
        ");
    }

    // Preserve relationships already demonstrated by completed canvasses and POs.
    if (auditTableExists($db, 'price_canvass') && auditTableExists($db, 'canvass_quotes')) {
        $db->exec("
            INSERT IGNORE INTO supplier_ingredients
                (supplier_id, ingredient_id, reference_unit_price, is_active, created_by)
            SELECT DISTINCT q.supplier_id, c.ingredient_id, NULL, 1, NULL
            FROM price_canvass c
            JOIN canvass_quotes q ON q.canvass_id = c.id
            WHERE c.ingredient_id IS NOT NULL
        ");
    }

    if (auditTableExists($db, 'purchase_orders') && auditTableExists($db, 'purchase_order_items')) {
        $db->exec("
            INSERT IGNORE INTO supplier_ingredients
                (supplier_id, ingredient_id, reference_unit_price, is_active, created_by)
            SELECT DISTINCT po.supplier_id, poi.ingredient_id, NULL, 1, NULL
            FROM purchase_orders po
            JOIN purchase_order_items poi ON poi.po_id = po.id
            WHERE poi.ingredient_id IS NOT NULL
        ");
    }
}

function supplierCatalogNormalizeIngredientLinks($rawLinks): array {
    if (!is_array($rawLinks)) {
        return [];
    }

    $links = [];
    foreach ($rawLinks as $raw) {
        $ingredientId = is_array($raw) ? (int) ($raw['ingredient_id'] ?? 0) : (int) $raw;
        if ($ingredientId <= 0) {
            continue;
        }

        $legacyPrice = is_array($raw) && array_key_exists('reference_unit_price', $raw)
            ? $raw['reference_unit_price'] : null;
        $quotedPrice = is_array($raw) && array_key_exists('quoted_price', $raw)
            ? $raw['quoted_price'] : $legacyPrice;
        $packageSize = is_array($raw) ? ($raw['package_size_value'] ?? null) : null;
        $purchaseFormat = is_array($raw) ? strtolower(trim((string) ($raw['purchase_format'] ?? 'direct_unit'))) : 'direct_unit';

        $links[$ingredientId] = [
            'ingredient_id' => $ingredientId,
            'purchase_format' => $purchaseFormat,
            'package_type' => is_array($raw) ? strtolower(trim((string) ($raw['package_type'] ?? ''))) : '',
            'package_size_value' => $packageSize,
            '_package_size_raw' => ($packageSize === null || $packageSize === '') ? null : trim((string) $packageSize),
            'package_size_unit' => is_array($raw) ? trim((string) ($raw['package_size_unit'] ?? '')) : '',
            'quoted_price' => ($quotedPrice === null || $quotedPrice === '') ? null : (float) $quotedPrice,
            '_quoted_price_raw' => ($quotedPrice === null || $quotedPrice === '') ? null : trim((string) $quotedPrice),
            'reference_unit_price' => ($legacyPrice === null || $legacyPrice === '') ? null : (float) $legacyPrice,
        ];
    }

    return array_values($links);
}

function supplierCatalogNormalizeSupplierIds($rawSuppliers): array {
    if (!is_array($rawSuppliers)) {
        return [];
    }

    $ids = [];
    foreach ($rawSuppliers as $raw) {
        $supplierId = is_array($raw) ? (int) ($raw['supplier_id'] ?? 0) : (int) $raw;
        if ($supplierId > 0) {
            $ids[$supplierId] = $supplierId;
        }
    }
    return array_values($ids);
}

function supplierCatalogUnitKey($unit): string {
    $key = strtolower(trim((string) $unit));
    $aliases = [
        'l' => 'liter', 'liters' => 'liter', 'litre' => 'liter', 'litres' => 'liter',
        'kilogram' => 'kg', 'kilograms' => 'kg', 'gram' => 'g', 'grams' => 'g',
        'milliliter' => 'ml', 'milliliters' => 'ml',
        'pc' => 'pcs', 'piece' => 'pcs', 'pieces' => 'pcs',
    ];
    return $aliases[$key] ?? $key;
}

function supplierCatalogConvertToStockUnit(float $amount, string $fromUnit, string $stockUnit): ?float {
    $from = supplierCatalogUnitKey($fromUnit);
    $to = supplierCatalogUnitKey($stockUnit);
    if ($from === $to) {
        return $amount;
    }
    $factors = [
        'kg:g' => 1000.0,
        'g:kg' => 0.001,
        'liter:ml' => 1000.0,
        'ml:liter' => 0.001,
    ];
    return isset($factors["{$from}:{$to}"]) ? $amount * $factors["{$from}:{$to}"] : null;
}

function supplierCatalogIsPlainDecimal($value, int $maximumDecimals): bool {
    if (!is_scalar($value)) {
        return false;
    }
    $text = trim((string) $value);
    return $text !== '' && preg_match('/^\d+(?:\.\d{1,' . $maximumDecimals . '})?$/D', $text) === 1;
}

function supplierCatalogMaximumPackageSize(string $unit): float {
    $normalized = supplierCatalogUnitKey($unit);
    if (in_array($normalized, ['kg', 'liter'], true)) {
        return 100000.0;
    }
    if (in_array($normalized, ['g', 'ml'], true)) {
        return 100000000.0;
    }
    return 1000000.0;
}

function supplierCatalogIsCountUnit(string $unit): bool {
    return in_array(supplierCatalogUnitKey($unit), ['pcs', 'pack', 'packet', 'roll', 'bottle', 'unit', 'units'], true);
}

function supplierCatalogNumber($value): string {
    return rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');
}

function supplierCatalogValidateIngredientLinks(PDO $db, array &$links, bool $required, bool $pricesRequired = false): void {
    if ($required && !$links) {
        sendValidationError(['ingredients' => 'Choose at least one item supplied by this accredited supplier']);
    }

    $ids = array_column($links, 'ingredient_id');
    if (!$ids) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT id, ingredient_name, unit_of_measure FROM ingredients WHERE id IN ($placeholders) AND is_active = 1");
    $stmt->execute($ids);
    $ingredients = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $ingredient) {
        $ingredients[(int) $ingredient['id']] = $ingredient;
    }
    if (count($ingredients) !== count($ids)) {
        sendValidationError(['ingredients' => 'One or more selected ingredients are inactive or unavailable']);
    }

    $allowedPackageTypes = ['sack', 'bag', 'box', 'case', 'crate', 'bottle', 'jug', 'drum', 'pail', 'tank', 'container', 'sachet', 'packet', 'roll', 'bundle', 'package'];
    foreach ($links as &$link) {
        $ingredient = $ingredients[(int) $link['ingredient_id']];
        $stockUnit = supplierCatalogUnitKey($ingredient['unit_of_measure']);
        $format = $link['purchase_format'] ?? 'direct_unit';
        if (!in_array($format, ['direct_unit', 'packaged'], true)) {
            sendValidationError(['ingredients' => 'Choose whether the supplier price is per Warehouse unit or per whole package for ' . $ingredient['ingredient_name']]);
        }
        $quotedPrice = $link['quoted_price'];
        $quotedPriceRaw = $link['_quoted_price_raw'] ?? null;
        $maximumPriceDecimals = $format === 'packaged' ? 2 : 6;
        if ($quotedPriceRaw !== null && !supplierCatalogIsPlainDecimal($quotedPriceRaw, $maximumPriceDecimals)) {
            $message = $format === 'packaged'
                ? 'Enter whole-package prices with no more than two decimal places'
                : 'Enter supplier prices as ordinary numbers without e, E, +, or -';
            sendValidationError(['ingredients' => $message]);
        }
        if ($quotedPrice !== null && (!is_finite((float) $quotedPrice) || (float) $quotedPrice > 999999.99)) {
            sendValidationError(['ingredients' => 'Supplier prices must not exceed PHP 999,999.99']);
        }
        if ($pricesRequired && ($quotedPrice === null || $quotedPrice <= 0)) {
            sendValidationError(['ingredients' => 'Enter the standard supplier price for ' . $ingredient['ingredient_name']]);
        }
        if ($quotedPrice !== null && $quotedPrice <= 0) {
            sendValidationError(['ingredients' => 'Standard supplier prices must be greater than zero']);
        }

        if ($format === 'direct_unit') {
            $link = array_merge($link, [
                'package_type' => null,
                'package_size_value' => null,
                'package_size_unit' => null,
                'package_quantity_in_stock_unit' => 1.0,
                'quoted_price' => $quotedPrice,
                'price_basis' => 'stock_unit',
                'reference_unit_price' => $quotedPrice,
                'offer_label' => 'Direct per ' . $stockUnit,
                'enforce_whole_packages' => 0,
            ]);
            continue;
        }

        $packageType = strtolower(trim((string) ($link['package_type'] ?? '')));
        $packageSize = $link['package_size_value'];
        $packageSizeRaw = $link['_package_size_raw'] ?? null;
        $packageUnit = supplierCatalogUnitKey($link['package_size_unit'] ?? '');
        if (!in_array($packageType, $allowedPackageTypes, true)) {
            sendValidationError(['ingredients' => 'Choose a valid supplier package type for ' . $ingredient['ingredient_name']]);
        }
        if ($packageSizeRaw === null || !supplierCatalogIsPlainDecimal($packageSizeRaw, 3)
            || !is_numeric($packageSize) || !is_finite((float) $packageSize)
            || (float) $packageSize <= 0 || $packageUnit === '') {
            sendValidationError(['ingredients' => 'Enter how much ' . $ingredient['ingredient_name'] . ' is inside one supplier package']);
        }
        $maximumPackageSize = supplierCatalogMaximumPackageSize($packageUnit);
        if ((float) $packageSize > $maximumPackageSize) {
            sendValidationError([
                'ingredients' => 'The package quantity for ' . $ingredient['ingredient_name'] . ' must not exceed ' .
                    number_format($maximumPackageSize, 0) . ' ' . $packageUnit
            ]);
        }
        if (supplierCatalogIsCountUnit($packageUnit) && floor((float) $packageSize) !== (float) $packageSize) {
            sendValidationError(['ingredients' => 'Use a whole-number package quantity for ' . $ingredient['ingredient_name'] . ' when it is counted in ' . $packageUnit]);
        }
        $stockQuantity = supplierCatalogConvertToStockUnit((float) $packageSize, $packageUnit, $stockUnit);
        if ($stockQuantity === null || $stockQuantity <= 0) {
            sendValidationError(['ingredients' => 'The supplier package unit for ' . $ingredient['ingredient_name'] . ' must match its stock unit (' . $stockUnit . ')']);
        }
        $link = array_merge($link, [
            'package_type' => $packageType,
            'package_size_value' => round((float) $packageSize, 3),
            'package_size_unit' => $packageUnit,
            'package_quantity_in_stock_unit' => round($stockQuantity, 6),
            'quoted_price' => $quotedPrice,
            'price_basis' => 'package',
            'reference_unit_price' => $quotedPrice === null ? null : round($quotedPrice / $stockQuantity, 6),
            'offer_label' => supplierCatalogNumber($packageSize) . ' ' . $packageUnit . ' ' . $packageType,
            'enforce_whole_packages' => 1,
        ]);
    }
    unset($link);
}

function supplierCatalogValidateSupplierIds(PDO $db, array $supplierIds, bool $required): void {
    if ($required && count($supplierIds) < 1) {
        sendValidationError([
            'supplier_ids' => 'Choose at least one accredited supplier for this ingredient'
        ]);
    }
    if (!$supplierIds) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($supplierIds), '?'));
    $stmt = $db->prepare("SELECT id FROM suppliers WHERE id IN ($placeholders) AND is_active = 1");
    $stmt->execute($supplierIds);
    $validIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    if (count($validIds) !== count($supplierIds)) {
        sendValidationError(['supplier_ids' => 'One or more selected suppliers are not accredited']);
    }
}

function supplierCatalogValidateSupplierCoverageAfterChange(
    PDO $db,
    int $supplierId,
    array $nextLinks,
    bool $supplierWillBeActive
): void {
    $nextIngredientIds = array_map('intval', array_column($nextLinks, 'ingredient_id'));

    $currentStmt = $db->prepare("SELECT ingredient_id FROM supplier_ingredients WHERE supplier_id = ? AND is_active = 1");
    $currentStmt->execute([$supplierId]);
    $currentIngredientIds = array_map('intval', $currentStmt->fetchAll(PDO::FETCH_COLUMN));
    $affectedIngredientIds = array_values(array_unique(array_merge($currentIngredientIds, $nextIngredientIds)));
    if (!$affectedIngredientIds) {
        return;
    }

    $ingredientStmt = $db->prepare("SELECT ingredient_name, is_active FROM ingredients WHERE id = ?");
    $otherSupplierCountStmt = $db->prepare("
        SELECT COUNT(DISTINCT si.supplier_id)
        FROM supplier_ingredients si
        JOIN suppliers s ON s.id = si.supplier_id AND s.is_active = 1
        WHERE si.ingredient_id = ?
          AND si.is_active = 1
          AND si.supplier_id <> ?
    ");

    foreach ($affectedIngredientIds as $ingredientId) {
        $ingredientStmt->execute([$ingredientId]);
        $ingredient = $ingredientStmt->fetch(PDO::FETCH_ASSOC);
        if (!$ingredient || (int) $ingredient['is_active'] !== 1) {
            continue;
        }

        $otherSupplierCountStmt->execute([$ingredientId, $supplierId]);
        $remainingCount = (int) $otherSupplierCountStmt->fetchColumn();
        if ($supplierWillBeActive && in_array($ingredientId, $nextIngredientIds, true)) {
            $remainingCount++;
        }

        if ($remainingCount < 1) {
            sendValidationError([
                'ingredients' => sprintf(
                    '%s would have no accredited supplier. Link another supplier before removing this one.',
                    $ingredient['ingredient_name']
                )
            ]);
        }
    }
}

function supplierCatalogSyncSupplier(PDO $db, int $supplierId, array $links, ?int $userId): void {
    $db->prepare("UPDATE supplier_ingredients SET is_active = 0 WHERE supplier_id = ?")
        ->execute([$supplierId]);

    $stmt = $db->prepare("
        INSERT INTO supplier_ingredients
            (supplier_id, ingredient_id, reference_unit_price, purchase_format,
             package_type, package_size_value, package_size_unit, package_quantity_in_stock_unit,
             quoted_price, price_basis, offer_label, enforce_whole_packages, is_active, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
        ON DUPLICATE KEY UPDATE
            reference_unit_price = VALUES(reference_unit_price),
            purchase_format = VALUES(purchase_format),
            package_type = VALUES(package_type),
            package_size_value = VALUES(package_size_value),
            package_size_unit = VALUES(package_size_unit),
            package_quantity_in_stock_unit = VALUES(package_quantity_in_stock_unit),
            quoted_price = VALUES(quoted_price),
            price_basis = VALUES(price_basis),
            offer_label = VALUES(offer_label),
            enforce_whole_packages = VALUES(enforce_whole_packages),
            is_active = 1,
            updated_at = CURRENT_TIMESTAMP
    ");
    foreach ($links as $link) {
        $stmt->execute([
            $supplierId, $link['ingredient_id'], $link['reference_unit_price'], $link['purchase_format'],
            $link['package_type'], $link['package_size_value'], $link['package_size_unit'],
            $link['package_quantity_in_stock_unit'], $link['quoted_price'], $link['price_basis'],
            $link['offer_label'], $link['enforce_whole_packages'], $userId
        ]);
    }
}

function supplierCatalogSyncIngredient(PDO $db, int $ingredientId, array $supplierIds, ?int $userId): void {
    $db->prepare("UPDATE supplier_ingredients SET is_active = 0 WHERE ingredient_id = ?")
        ->execute([$ingredientId]);

    $stmt = $db->prepare("
        INSERT INTO supplier_ingredients
            (supplier_id, ingredient_id, reference_unit_price, is_active, created_by)
        VALUES (?, ?, NULL, 1, ?)
        ON DUPLICATE KEY UPDATE
            is_active = 1,
            updated_at = CURRENT_TIMESTAMP
    ");
    foreach ($supplierIds as $supplierId) {
        $stmt->execute([$supplierId, $ingredientId, $userId]);
    }
}

function supplierCatalogGetSupplierIngredients(PDO $db, int $supplierId): array {
    $stmt = $db->prepare("
        SELECT si.ingredient_id, si.reference_unit_price, si.purchase_format,
               si.package_type, si.package_size_value, si.package_size_unit,
               si.package_quantity_in_stock_unit, si.quoted_price, si.price_basis,
               si.offer_label, si.enforce_whole_packages,
               i.ingredient_code, i.ingredient_name, i.unit_of_measure, i.category_id, c.category_name
        FROM supplier_ingredients si
        JOIN ingredients i ON i.id = si.ingredient_id
        LEFT JOIN ingredient_categories c ON c.id = i.category_id
        WHERE si.supplier_id = ? AND si.is_active = 1 AND i.is_active = 1
        ORDER BY i.ingredient_name
    ");
    $stmt->execute([$supplierId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function supplierCatalogGetIngredientSuppliers(PDO $db, int $ingredientId): array {
    $stmt = $db->prepare("
        SELECT si.supplier_id AS id, si.supplier_id, si.reference_unit_price,
               si.purchase_format, si.package_type, si.package_size_value, si.package_size_unit,
               si.package_quantity_in_stock_unit, si.quoted_price, si.price_basis,
               si.offer_label, si.enforce_whole_packages,
               s.supplier_code, s.supplier_name, s.payment_terms
        FROM supplier_ingredients si
        JOIN ingredients i ON i.id = si.ingredient_id AND i.is_active = 1
        JOIN suppliers s ON s.id = si.supplier_id
        WHERE si.ingredient_id = ? AND si.is_active = 1 AND s.is_active = 1
        ORDER BY s.supplier_name
    ");
    $stmt->execute([$ingredientId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
