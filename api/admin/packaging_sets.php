<?php
/** Admin API for reusable Packaging Sets. */
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../helpers/packaging_sets.php';

$currentUser = Auth::requireRole(['general_manager', 'admin']);
$db = Database::getInstance()->getConnection();
ensureIngredientPackagingRoleSupport($db);
ensureSkuPackagingBomTable($db);
ensureProductPrimaryContainerSupport($db);
ensurePackagingSetTables($db);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$action = trim((string) ($_GET['action'] ?? ''));

try {
    if ($method === 'GET') {
        sendSuccess([
            'sets' => getPackagingSets($db, !empty($_GET['include_inactive'])),
            'available_materials' => getAvailablePackagingMaterials($db),
        ]);
    }

    if ($method === 'POST' && $action === 'apply') {
        $data = getRequestBody();
        $setId = (int) ($data['packaging_set_id'] ?? 0);
        $productId = (int) ($data['product_id'] ?? 0);
        $sets = array_values(array_filter(getPackagingSets($db, false), static fn($set) => (int) $set['id'] === $setId));
        if (!$sets || $productId <= 0) {
            sendValidationError(['packaging_set_id' => 'Choose an active packaging set and SKU']);
        }
        $set = $sets[0];
        if (empty($set['is_ready'])) {
            sendValidationError(['packaging_set_id' => 'Complete the set first: ' . implode(', ', $set['missing_components'])]);
        }
        $stmt = $db->prepare('SELECT id, unit_size, unit_measure, base_unit FROM products WHERE id = ?');
        $stmt->execute([$productId]);
        $sku = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$sku) {
            sendError('Active SKU not found', 404);
        }
        $skuSize = packagingCanonicalSize($sku['unit_size'], $sku['unit_measure']);
        $setSize = packagingCanonicalSize($set['capacity_value'], $set['capacity_unit']);
        if (!$skuSize || !$setSize || $skuSize['family'] !== $setSize['family']
            || abs((float) $skuSize['value'] - (float) $setSize['value']) > 0.01) {
            sendValidationError(['packaging_set_id' => sprintf(
                '%s is for %s; this SKU is %s',
                $set['set_name'],
                formatPackagingCanonicalSize($setSize ?: ['family' => 'volume', 'value' => 0]),
                formatPackagingCanonicalSize($skuSize ?: ['family' => 'volume', 'value' => 0])
            )]);
        }
        $items = array_map(static fn($item) => [
            'ingredient_id' => (int) $item['ingredient_id'],
            'quantity_per_unit' => (float) $item['quantity_per_unit'],
            'units_per_stock_unit' => isRollPackagingUnit($item['unit_of_measure'] ?? '')
                && (float) $item['quantity_per_unit'] > 0
                ? (int) round(1 / (float) $item['quantity_per_unit'])
                : null,
            'waste_percent' => (float) $item['waste_percent'],
        ], $set['items']);
        $container = current(array_filter($set['items'], static fn($item) => ($item['packaging_role'] ?? '') === 'container'));
        $db->beginTransaction();
        try {
            if ($container) {
                $db->prepare('UPDATE products SET primary_container_id = ? WHERE id = ?')->execute([(int) $container['ingredient_id'], $productId]);
            }
            $result = replaceSkuPackagingBom($db, $productId, $items);
            if (!$result['success']) {
                $db->rollBack();
                sendValidationError($result['errors']);
            }
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
        sendSuccess(['product_id' => $productId, 'packaging_set_id' => $setId, 'items' => $result['items']], 'Packaging set applied to SKU');
    }

    if ($method === 'POST') {
        $newId = savePackagingSet($db, getRequestBody(), null, (int) ($currentUser['id'] ?? 0));
        sendSuccess(['packaging_set_id' => $newId], 'Packaging set created', 201);
    }
    if ($method === 'PUT' && $id) {
        savePackagingSet($db, getRequestBody(), $id, (int) ($currentUser['id'] ?? 0));
        sendSuccess(['packaging_set_id' => $id], 'Packaging set updated');
    }
    if ($method === 'DELETE' && $id) {
        $stmt = $db->prepare('UPDATE packaging_sets SET is_active = 0 WHERE id = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            sendError('Packaging set not found', 404);
        }
        sendSuccess(['packaging_set_id' => $id], 'Packaging set archived');
    }
    sendError('Method not allowed', 405);
} catch (PDOException $e) {
    if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
        sendError('A packaging set with that name already exists', 409);
    }
    error_log('Packaging sets database error: ' . $e->getMessage());
    sendError('Packaging sets could not be saved. Please try again.', 500);
} catch (Throwable $e) {
    error_log('Packaging sets error: ' . $e->getMessage());
    sendError('Packaging sets could not be processed. Please try again.', 500);
}
