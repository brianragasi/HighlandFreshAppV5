<?php

function ensureSupplierIngredientCatalog(PDO $db): void {
    $db->exec("
        CREATE TABLE IF NOT EXISTS supplier_ingredients (
            id INT NOT NULL AUTO_INCREMENT,
            supplier_id INT NOT NULL,
            ingredient_id INT NOT NULL,
            reference_unit_price DECIMAL(12,2) DEFAULT NULL,
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

        $price = is_array($raw) && array_key_exists('reference_unit_price', $raw)
            ? $raw['reference_unit_price']
            : null;
        $price = ($price === null || $price === '') ? null : (float) $price;

        $links[$ingredientId] = [
            'ingredient_id' => $ingredientId,
            'reference_unit_price' => $price
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

function supplierCatalogValidateIngredientLinks(PDO $db, array $links, bool $required): void {
    if ($required && !$links) {
        sendValidationError(['ingredients' => 'Choose at least one ingredient supplied by this accredited supplier']);
    }

    foreach ($links as $link) {
        if ($link['reference_unit_price'] !== null && $link['reference_unit_price'] <= 0) {
            sendValidationError(['ingredients' => 'Reference prices must be greater than zero']);
        }
    }

    $ids = array_column($links, 'ingredient_id');
    if (!$ids) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT id FROM ingredients WHERE id IN ($placeholders) AND is_active = 1");
    $stmt->execute($ids);
    $validIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    if (count($validIds) !== count($ids)) {
        sendValidationError(['ingredients' => 'One or more selected ingredients are inactive or unavailable']);
    }
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
            (supplier_id, ingredient_id, reference_unit_price, is_active, created_by)
        VALUES (?, ?, ?, 1, ?)
        ON DUPLICATE KEY UPDATE
            reference_unit_price = VALUES(reference_unit_price),
            is_active = 1,
            updated_at = CURRENT_TIMESTAMP
    ");
    foreach ($links as $link) {
        $stmt->execute([$supplierId, $link['ingredient_id'], $link['reference_unit_price'], $userId]);
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
        SELECT si.ingredient_id, si.reference_unit_price,
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
               s.supplier_code, s.supplier_name, s.payment_terms
        FROM supplier_ingredients si
        JOIN suppliers s ON s.id = si.supplier_id
        WHERE si.ingredient_id = ? AND si.is_active = 1 AND s.is_active = 1
        ORDER BY s.supplier_name
    ");
    $stmt->execute([$ingredientId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
