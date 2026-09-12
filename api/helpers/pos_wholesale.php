<?php

/**
 * Cashier retail/wholesale helpers.
 *
 * Finished-goods inventory remains authoritative in base units (bottles,
 * pieces, blocks, etc.). A wholesale box is only a selling and display unit.
 */

function hfEnsurePosWholesaleSchema(PDO $db): void
{
    static $done = false;
    if ($done || $db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
        return;
    }

    $columnExists = static function (string $table, string $column) use ($db): bool {
        $stmt = $db->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
        $stmt->execute([$table, $column]);
        return (bool) $stmt->fetchColumn();
    };

    if (!$columnExists('products', 'wholesale_box_price')) {
        $db->exec("ALTER TABLE products
            ADD COLUMN wholesale_box_price DECIMAL(12,2) NULL AFTER selling_price");
    }
    if (!$columnExists('sales_transactions', 'sale_mode')) {
        $db->exec("ALTER TABLE sales_transactions
            ADD COLUMN sale_mode ENUM('retail','wholesale') NOT NULL DEFAULT 'retail' AFTER transaction_type");
    }
    if (!$columnExists('sales_transaction_items', 'sale_unit')) {
        $db->exec("ALTER TABLE sales_transaction_items
            ADD COLUMN sale_unit ENUM('piece','box') NOT NULL DEFAULT 'piece' AFTER quantity");
    }
    if (!$columnExists('sales_transaction_items', 'sale_quantity')) {
        $db->exec("ALTER TABLE sales_transaction_items
            ADD COLUMN sale_quantity INT NOT NULL DEFAULT 0 AFTER sale_unit");
    }
    if (!$columnExists('sales_transaction_items', 'pieces_per_box_snapshot')) {
        $db->exec("ALTER TABLE sales_transaction_items
            ADD COLUMN pieces_per_box_snapshot INT NOT NULL DEFAULT 1 AFTER sale_quantity");
    }

    $db->exec("CREATE TABLE IF NOT EXISTS pos_sold_box_labels (
        label_code VARCHAR(80) NOT NULL,
        batch_id INT NOT NULL,
        product_id INT NOT NULL,
        inventory_id INT NOT NULL,
        transaction_id INT NOT NULL,
        sold_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (label_code),
        KEY idx_pos_box_label_transaction (transaction_id),
        KEY idx_pos_box_label_batch_product (batch_id, product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS pos_opened_box_labels (
        label_code VARCHAR(80) NOT NULL,
        batch_id INT NOT NULL,
        product_id INT NOT NULL,
        inventory_id INT NOT NULL,
        opened_by INT NOT NULL,
        opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (label_code),
        KEY idx_pos_opened_box_inventory (inventory_id),
        KEY idx_pos_opened_box_batch_product (batch_id, product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Existing receipts were retail transactions. Preserve their visible count.
    $db->exec("UPDATE sales_transaction_items
        SET sale_quantity = quantity
        WHERE sale_quantity = 0");

    $done = true;
}

function hfPosSaleMode($value): string
{
    return strtolower(trim((string) $value)) === 'wholesale' ? 'wholesale' : 'retail';
}

/**
 * Explain and validate the price of one full wholesale pack.
 *
 * @return array{applicable:bool,valid:bool,retail_pack_value:float,savings:float,discount_percent:float,message:string}
 */
function hfWholesaleDiscountSummary($retailUnitPrice, $unitsPerPack, $wholesalePackPrice): array
{
    $retailPrice = round((float) $retailUnitPrice, 2);
    $units = max(1, (int) $unitsPerPack);
    $wholesalePrice = round((float) $wholesalePackPrice, 2);
    $retailPackValue = round($retailPrice * $units, 2);
    $applicable = $units > 1;

    if (!$applicable) {
        return [
            'applicable' => false,
            'valid' => true,
            'retail_pack_value' => $retailPackValue,
            'savings' => 0.0,
            'discount_percent' => 0.0,
            'message' => '',
        ];
    }

    if ($retailPrice <= 0) {
        $message = 'Enter the retail unit price before setting the wholesale price.';
        $valid = false;
    } elseif ($wholesalePrice <= 0) {
        $message = 'Enter the approved wholesale price for one full pack.';
        $valid = false;
    } elseif ($wholesalePrice >= $retailPackValue) {
        $message = sprintf(
            'Wholesale price must be below ₱%s (%d units at ₱%s each) so the full pack has a discount.',
            number_format($retailPackValue, 2),
            $units,
            number_format($retailPrice, 2)
        );
        $valid = false;
    } else {
        $message = '';
        $valid = true;
    }

    $savings = $valid ? round($retailPackValue - $wholesalePrice, 2) : 0.0;
    $discountPercent = $valid && $retailPackValue > 0
        ? round(($savings / $retailPackValue) * 100, 2)
        : 0.0;

    return [
        'applicable' => true,
        'valid' => $valid,
        'retail_pack_value' => $retailPackValue,
        'savings' => $savings,
        'discount_percent' => $discountPercent,
        'message' => $message,
    ];
}

/**
 * Convert the selected selling unit to the authoritative base-unit quantity.
 *
 * @return array{sale_mode:string,sale_unit:string,sale_quantity:int,base_quantity:int,pieces_per_box:int,unit_price:float,line_total:float}
 */
function hfPosPriceLine(array $product, string $saleMode, int $saleQuantity): array
{
    if ($saleQuantity < 1) {
        throw new InvalidArgumentException('Sale quantity must be at least 1.');
    }

    $mode = hfPosSaleMode($saleMode);
    $piecesPerBox = max(1, (int) ($product['pieces_per_box'] ?? 1));
    if ($mode === 'wholesale') {
        $boxPrice = (float) ($product['wholesale_box_price'] ?? 0);
        if ($piecesPerBox < 2) {
            throw new InvalidArgumentException('This product has no wholesale box configuration.');
        }
        if ($boxPrice <= 0) {
            throw new InvalidArgumentException('This product has no wholesale box price.');
        }
        $discount = hfWholesaleDiscountSummary(
            $product['selling_price'] ?? $product['unit_price'] ?? 0,
            $piecesPerBox,
            $boxPrice
        );
        if (!$discount['valid']) {
            throw new InvalidArgumentException($discount['message']);
        }
        $baseQuantity = $saleQuantity * $piecesPerBox;
        $unitPrice = round($boxPrice, 2);
        $saleUnit = 'box';
    } else {
        $savedPrice = $product['selling_price'] ?? $product['unit_price'] ?? 0;
        $unitPrice = round((float) $savedPrice, 2);
        if ($unitPrice <= 0) {
            throw new InvalidArgumentException('This product has no retail selling price.');
        }
        $baseQuantity = $saleQuantity;
        $saleUnit = 'piece';
    }

    $lineTotal = round($saleQuantity * $unitPrice, 2);
    if ($baseQuantity > 1000000 || !is_finite($lineTotal) || $lineTotal > 9999999999.99) {
        throw new InvalidArgumentException('The quantity or line total is outside the supported sales range.');
    }

    return [
        'sale_mode' => $mode,
        'sale_unit' => $saleUnit,
        'sale_quantity' => $saleQuantity,
        'base_quantity' => $baseQuantity,
        'pieces_per_box' => $piecesPerBox,
        'unit_price' => $unitPrice,
        'line_total' => $lineTotal,
    ];
}
