<?php
/**
 * Highland Fresh - Production Byproducts API
 *
 * Manual tracking for whey, skim milk, buttermilk, cream, and other usable
 * byproducts. Waste/disposal is routed into the normal Disposal Report flow.
 */

require_once dirname(__DIR__) . '/bootstrap.php';

$currentUser = Auth::requireRole(['production_staff', 'general_manager', 'qc_officer']);

try {
    $db = Database::getInstance()->getConnection();
    ensureProductionByproductTables($db);

    switch ($requestMethod) {
        case 'GET':
            handleGet($db);
            break;
        case 'POST':
            handlePost($db, $currentUser);
            break;
        case 'PUT':
            handlePut($db, $currentUser);
            break;
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log('Production byproducts API error: ' . $e->getMessage());
    Response::error('An error occurred: ' . $e->getMessage(), 500);
}

function ensureProductionByproductTables(PDO $db): void
{
    $db->exec("
        CREATE TABLE IF NOT EXISTS production_byproducts (
            id INT NOT NULL AUTO_INCREMENT,
            run_id INT NOT NULL,
            byproduct_type ENUM('buttermilk','whey','cream','skim_milk','other') DEFAULT 'other',
            quantity DECIMAL(10,2) NOT NULL,
            unit VARCHAR(20) DEFAULT 'liters',
            status ENUM('pending','stored','used','disposed') DEFAULT 'pending',
            destination VARCHAR(100) DEFAULT NULL,
            storage_location VARCHAR(100) DEFAULT NULL,
            recorded_by INT NOT NULL,
            notes TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_run (run_id),
            KEY idx_type (byproduct_type),
            KEY idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

function handleGet(PDO $db): void
{
    $id = getParam('id');
    if ($id) {
        $stmt = $db->prepare(byproductSelectSql() . " WHERE pb.id = ?");
        $stmt->execute([(int) $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            Response::notFound('Byproduct record not found');
        }
        Response::success($row, 'Byproduct retrieved');
    }

    $page = max(1, (int) getParam('page', 1));
    $limit = min(100, max(1, (int) getParam('limit', 20)));
    $offset = ($page - 1) * $limit;

    $where = ['1=1'];
    $params = [];

    $runId = getParam('run_id');
    $type = getParam('byproduct_type');
    $status = getParam('status');
    $search = trim((string) getParam('search', ''));

    if ($runId) {
        $where[] = 'pb.run_id = ?';
        $params[] = (int) $runId;
    }
    if ($type) {
        $where[] = 'pb.byproduct_type = ?';
        $params[] = $type;
    }
    if ($status) {
        $where[] = 'pb.status = ?';
        $params[] = $status;
    }
    if ($search !== '') {
        $where[] = "(pr.run_code LIKE ? OR mr.product_name LIKE ? OR pb.notes LIKE ?)";
        $needle = "%{$search}%";
        array_push($params, $needle, $needle, $needle);
    }

    $whereSql = implode(' AND ', $where);

    $countStmt = $db->prepare("
        SELECT COUNT(*)
        FROM production_byproducts pb
        LEFT JOIN production_runs pr ON pr.id = pb.run_id
        LEFT JOIN master_recipes mr ON mr.id = pr.recipe_id
        WHERE {$whereSql}
    ");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $stmt = $db->prepare(byproductSelectSql() . "
        WHERE {$whereSql}
        ORDER BY pb.created_at DESC, pb.id DESC
        LIMIT ? OFFSET ?
    ");
    $listParams = array_merge($params, [$limit, $offset]);
    $stmt->execute($listParams);

    Response::paginated($stmt->fetchAll(PDO::FETCH_ASSOC), $total, $page, $limit, 'Byproducts retrieved');
}

function handlePost(PDO $db, array $currentUser): void
{
    $runId = (int) getParam('run_id', 0);
    $type = getParam('byproduct_type');
    $quantity = (float) getParam('quantity', 0);
    $unit = trim((string) getParam('unit', 'liters'));
    $destination = trim((string) getParam('destination', ''));
    $storageLocation = trim((string) getParam('storage_location', ''));
    $notes = trim((string) getParam('notes', ''));

    validateByproductInput($runId, $type, $quantity, $unit, $destination);

    $run = fetchRun($db, $runId);
    if (!$run) {
        Response::notFound('Production run not found');
    }

    $status = 'pending';
    if ($destination === 'warehouse') {
        $status = 'stored';
    } elseif ($destination === 'reprocess' || $destination === 'sale') {
        $status = 'used';
    } elseif ($destination === 'dispose') {
        $status = 'disposed';
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("
            INSERT INTO production_byproducts
                (run_id, byproduct_type, quantity, unit, status, destination, storage_location, recorded_by, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $runId,
            $type,
            $quantity,
            $unit,
            $status,
            $destination ?: null,
            $storageLocation ?: null,
            (int) $currentUser['user_id'],
            $notes ?: null,
        ]);
        $id = (int) $db->lastInsertId();

        $disposal = null;
        if ($destination === 'dispose') {
            $disposal = createByproductDisposal($db, $id, $run, $type, $quantity, $unit, $notes, $currentUser);
        }

        $db->commit();

        Response::success([
            'id' => $id,
            'status' => $status,
            'disposal' => $disposal,
        ], $destination === 'dispose' ? 'Byproduct logged and sent to Disposal Report' : 'Byproduct recorded', 201);
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function handlePut(PDO $db, array $currentUser): void
{
    $id = (int) getParam('id', 0);
    $action = getParam('action', 'update');

    if (!$id) {
        Response::validationError(['id' => 'Byproduct ID is required']);
    }

    $stmt = $db->prepare(byproductSelectSql() . " WHERE pb.id = ?");
    $stmt->execute([$id]);
    $bp = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$bp) {
        Response::notFound('Byproduct record not found');
    }

    $db->beginTransaction();
    try {
        if ($action === 'store' || $action === 'transfer_to_warehouse') {
            $location = trim((string) getParam('storage_location', 'Warehouse byproduct hold'));
            $db->prepare("
                UPDATE production_byproducts
                SET status = 'stored', destination = 'warehouse', storage_location = ?
                WHERE id = ?
            ")->execute([$location, $id]);
            $payload = ['id' => $id, 'status' => 'stored'];
            $message = 'Byproduct marked as stored for Warehouse';
        } elseif ($action === 'mark_used') {
            $db->prepare("
                UPDATE production_byproducts
                SET status = 'used', destination = COALESCE(NULLIF(destination, ''), 'reprocess')
                WHERE id = ?
            ")->execute([$id]);
            $payload = ['id' => $id, 'status' => 'used'];
            $message = 'Byproduct marked as used';
        } elseif ($action === 'send_to_disposal' || $action === 'dispose') {
            $reason = trim((string) getParam('reason', 'Byproduct recorded as production waste'));
            $disposal = createByproductDisposal(
                $db,
                $id,
                $bp,
                $bp['byproduct_type'],
                (float) $bp['quantity'],
                $bp['unit'],
                $reason,
                $currentUser
            );
            $db->prepare("
                UPDATE production_byproducts
                SET status = 'disposed', destination = 'dispose', notes = CONCAT(COALESCE(notes, ''), ?)
                WHERE id = ?
            ")->execute(["\nSent to disposal: {$disposal['disposal_code']}", $id]);
            $payload = ['id' => $id, 'status' => 'disposed', 'disposal' => $disposal];
            $message = 'Byproduct sent to Disposal Report';
        } else {
            Response::error('Invalid action', 400);
        }

        $db->commit();
        Response::success($payload, $message);
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function byproductSelectSql(): string
{
    return "
        SELECT
            pb.*,
            pr.run_code,
            pr.status AS run_status,
            mr.product_name AS source_product,
            mr.product_type AS source_product_type,
            TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS recorded_by_name
        FROM production_byproducts pb
        LEFT JOIN production_runs pr ON pr.id = pb.run_id
        LEFT JOIN master_recipes mr ON mr.id = pr.recipe_id
        LEFT JOIN users u ON u.id = pb.recorded_by
    ";
}

function validateByproductInput(int $runId, ?string $type, float $quantity, string $unit, string $destination): void
{
    $errors = [];
    if ($runId <= 0) {
        $errors['run_id'] = 'Production run is required';
    }
    if (!in_array($type, ['buttermilk', 'whey', 'cream', 'skim_milk', 'other'], true)) {
        $errors['byproduct_type'] = 'Choose a valid byproduct type';
    }
    if ($quantity <= 0) {
        $errors['quantity'] = 'Quantity must be greater than zero';
    }
    if (!in_array($unit, ['liters', 'kg', 'grams', 'pieces'], true)) {
        $errors['unit'] = 'Choose a valid unit';
    }
    if ($destination !== '' && !in_array($destination, ['warehouse', 'reprocess', 'sale', 'dispose'], true)) {
        $errors['destination'] = 'Choose a valid destination';
    }
    if ($errors) {
        Response::validationError($errors);
    }
}

function fetchRun(PDO $db, int $runId): ?array
{
    $stmt = $db->prepare("
        SELECT pr.*, mr.product_name, mr.product_type
        FROM production_runs pr
        LEFT JOIN master_recipes mr ON mr.id = pr.recipe_id
        WHERE pr.id = ?
    ");
    $stmt->execute([$runId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function createByproductDisposal(PDO $db, int $byproductId, array $run, string $type, float $quantity, string $unit, string $reason, array $currentUser): array
{
    ensureDisposalsTableForByproducts($db);
    $code = generateByproductDisposalCode($db);
    $label = ucwords(str_replace('_', ' ', $type));
    $runId = (int) ($run['run_id'] ?? $run['id'] ?? 0);
    $runCode = $run['run_code'] ?? ('Run #' . $runId);
    $productName = "{$label} from {$runCode}";

    $stmt = $db->prepare("
        INSERT INTO disposals (
            disposal_code, source_type, source_id, source_reference,
            product_id, product_name, quantity, unit,
            unit_cost, total_value, disposal_category, disposal_reason,
            disposal_method, status, initiated_by, initiated_at, notes
        ) VALUES (?, 'production_batch', ?, ?, NULL, ?, ?, ?, 0, 0, 'production_waste', ?, 'other', 'pending', ?, NOW(), ?)
    ");
    $stmt->execute([
        $code,
        $runId > 0 ? $runId : $byproductId,
        $runCode . ' / byproduct #' . $byproductId,
        $productName,
        $quantity,
        $unit,
        $reason ?: 'Byproduct recorded as production waste',
        (int) $currentUser['user_id'],
        'Auto-created from Production Byproducts',
    ]);

    return [
        'id' => (int) $db->lastInsertId(),
        'disposal_code' => $code,
        'status' => 'pending',
    ];
}

function ensureDisposalsTableForByproducts(PDO $db): void
{
    $db->exec("
        CREATE TABLE IF NOT EXISTS disposals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            disposal_code VARCHAR(30) NOT NULL UNIQUE,
            source_type ENUM('raw_milk','finished_goods','ingredients','production_batch','milk_receiving') NOT NULL,
            source_id INT NOT NULL,
            source_reference VARCHAR(100) NULL,
            product_id INT NULL,
            product_name VARCHAR(200) NOT NULL,
            quantity DECIMAL(12,2) NOT NULL,
            unit VARCHAR(20) NOT NULL,
            unit_cost DECIMAL(12,2) DEFAULT 0,
            total_value DECIMAL(12,2) DEFAULT 0,
            disposal_category ENUM('qc_failed','expired','spoiled','contaminated','damaged','rejected_receipt','production_waste','other') NOT NULL,
            disposal_reason TEXT NOT NULL,
            disposal_method ENUM('drain','incinerate','animal_feed','compost','special_waste','other') NOT NULL DEFAULT 'other',
            status ENUM('pending','approved','rejected','completed','cancelled') NOT NULL DEFAULT 'pending',
            initiated_by INT NOT NULL,
            initiated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            notes TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

function generateByproductDisposalCode(PDO $db): string
{
    $prefix = 'DSP-' . date('Ymd') . '-';
    $stmt = $db->prepare("SELECT COUNT(*) FROM disposals WHERE disposal_code LIKE ?");
    $stmt->execute([$prefix . '%']);
    return $prefix . str_pad(((int) $stmt->fetchColumn()) + 1, 4, '0', STR_PAD_LEFT);
}
