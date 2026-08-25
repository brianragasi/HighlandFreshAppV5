<?php

function ensureSupplierMroCatalog(PDO $db): void {
    $db->exec("
        CREATE TABLE IF NOT EXISTS supplier_mro_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            supplier_id INT NOT NULL,
            mro_item_id INT NOT NULL,
            reference_unit_price DECIMAL(12,6) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by INT DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_supplier_mro_item (supplier_id, mro_item_id),
            KEY idx_supplier_mro_active (supplier_id, is_active),
            KEY idx_mro_supplier_active (mro_item_id, is_active),
            CONSTRAINT fk_supplier_mro_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
            CONSTRAINT fk_supplier_mro_item FOREIGN KEY (mro_item_id) REFERENCES mro_items(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function supplierMroNormalizeLinks($rawLinks): array {
    if (!is_array($rawLinks)) return [];
    $links = [];
    foreach ($rawLinks as $raw) {
        if (!is_array($raw)) continue;
        $mroItemId = (int) ($raw['mro_item_id'] ?? $raw['id'] ?? 0);
        if ($mroItemId <= 0) continue;
        $links[$mroItemId] = [
            'mro_item_id' => $mroItemId,
            'reference_unit_price' => $raw['reference_unit_price'] ?? $raw['unit_price'] ?? null,
        ];
    }
    return array_values($links);
}

function supplierMroIsPlainDecimal($value, int $maximumDecimals): bool {
    if (!is_scalar($value)) return false;
    $text = trim((string) $value);
    return $text !== '' && preg_match('/^\d+(?:\.\d{1,' . $maximumDecimals . '})?$/D', $text) === 1;
}

function supplierMroValidateLinks(PDO $db, array &$links, bool $pricesRequired): void {
    if (!$links) return;
    $ids = array_column($links, 'mro_item_id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT id, item_name FROM mro_items WHERE id IN ($placeholders) AND is_active = 1");
    $stmt->execute($ids);
    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) $items[(int) $item['id']] = $item;
    if (count($items) !== count($ids)) {
        sendValidationError(['mro_items' => 'One or more selected MRO items are inactive or unavailable']);
    }

    foreach ($links as &$link) {
        $rawPrice = $link['reference_unit_price'];
        $name = $items[(int) $link['mro_item_id']]['item_name'];
        if ($rawPrice === null || $rawPrice === '') {
            if ($pricesRequired) sendValidationError(['mro_items' => 'Enter the standard supplier price for ' . $name]);
            continue;
        }
        if (!supplierMroIsPlainDecimal($rawPrice, 6)) {
            sendValidationError(['mro_items' => $name . ' price must be an ordinary number with no more than 6 decimal places']);
        }
        $price = (float) $rawPrice;
        if ($price <= 0 || $price > 999999.999999) {
            sendValidationError(['mro_items' => $name . ' price must be greater than zero and no more than PHP 999,999.999999']);
        }
        $link['reference_unit_price'] = $price;
    }
    unset($link);
}

function supplierMroSyncSupplier(PDO $db, int $supplierId, array $links, ?int $userId): void {
    $db->prepare("UPDATE supplier_mro_items SET is_active = 0 WHERE supplier_id = ?")->execute([$supplierId]);
    if (!$links) return;
    $stmt = $db->prepare("
        INSERT INTO supplier_mro_items
            (supplier_id, mro_item_id, reference_unit_price, is_active, created_by)
        VALUES (?, ?, ?, 1, ?)
        ON DUPLICATE KEY UPDATE
            reference_unit_price = VALUES(reference_unit_price),
            is_active = 1,
            updated_at = CURRENT_TIMESTAMP
    ");
    foreach ($links as $link) {
        $stmt->execute([$supplierId, $link['mro_item_id'], $link['reference_unit_price'], $userId]);
    }
}

function supplierMroGetSupplierItems(PDO $db, int $supplierId): array {
    $stmt = $db->prepare("
        SELECT smi.*, m.item_code, m.item_name, m.unit_of_measure, m.is_critical
        FROM supplier_mro_items smi
        JOIN mro_items m ON m.id = smi.mro_item_id
        WHERE smi.supplier_id = ? AND smi.is_active = 1 AND m.is_active = 1
        ORDER BY m.item_name
    ");
    $stmt->execute([$supplierId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function supplierMroGetCatalog(PDO $db): array {
    return $db->query("
        SELECT id, item_code, item_name, unit_of_measure, is_critical
        FROM mro_items
        WHERE is_active = 1
        ORDER BY item_name
    ")->fetchAll(PDO::FETCH_ASSOC);
}
