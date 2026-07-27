<?php
/**
 * Highland Fresh — Product Conversions / Reprocessing API
 *
 * Manages the production-floor workflow for yogurt transformations:
 * 1. QC Safety Verification  → status = 'pending' (awaiting production)
 * 2. Production starts run   → status = 'in_progress'
 * 3. Production completes    → status = 'completed'
 *
 * GET  ?action=pending           — List QC-approved batches awaiting production start
 * GET  ?action=active            — List in-progress conversion runs
 * GET  ?action=history           — List completed/cancelled conversions
 * GET  ?action=summary           — Counts for sidebar badges
 * GET  ?action=detail&id=X       — Single conversion detail
 * POST action=start              — Start conversion (creates/links production run)
 * POST action=complete           — Mark conversion + run as completed
 * POST action=cancel             — Cancel conversion (restores inventory)
 *
 * @package HighlandFresh
 * @version 4.0
 */

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Fatal PHP error',
            'error_detail' => $error['message'],
            'error_file' => basename($error['file']),
            'error_line' => $error['line'],
        ]);
    }
});

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/bootstrap.php';

$currentUser = Auth::requireRole(['production_staff', 'general_manager', 'qc_officer']);

try {
    $db = Database::getInstance()->getConnection();
    global $requestMethod;

    switch ($requestMethod) {
        case 'GET':
            handleGet($db, $currentUser);
            break;
        case 'POST':
            handlePost($db, $currentUser);
            break;
        default:
            Response::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("Conversions API Error: " . $e->getMessage());
    Response::error('An error occurred: ' . $e->getMessage(), 500);
}

/* ──────────────────────────── GET ──────────────────────────── */

function handleGet($db, $currentUser)
{
    $action = getParam('action', 'summary');

    switch ($action) {
        case 'summary':
            getSummary($db);
            break;
        case 'pending':
            getPendingQueue($db);
            break;
        case 'active':
            getActiveRuns($db);
            break;
        case 'history':
            getHistory($db);
            break;
        case 'detail':
            getDetail($db);
            break;
        default:
            Response::error('Invalid action', 400);
    }
}

function getSummary($db)
{
    $stmt = $db->query("
        SELECT
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS active_count,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_count,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count,
            COUNT(*) AS total
        FROM yogurt_transformations
    ");
    $row = $stmt->fetch();

    Response::success([
        'pending'    => (int) ($row['pending_count'] ?? 0),
        'active'     => (int) ($row['active_count'] ?? 0),
        'completed'  => (int) ($row['completed_count'] ?? 0),
        'cancelled'  => (int) ($row['cancelled_count'] ?? 0),
        'total'      => (int) ($row['total'] ?? 0),
    ]);
}

function getPendingQueue($db)
{
    $stmt = $db->query("
        SELECT
            yt.id,
            yt.transformation_code,
            yt.source_quantity,
            yt.source_volume_liters,
            yt.target_product,
            yt.target_quantity,
            yt.transformation_date,
            yt.notes,
            yt.safety_verified,
            yt.status,
            p.product_name AS source_product_name,
            p.unit_measure,
            mr.recipe_code,
            mr.product_name AS recipe_name,
            u.first_name AS initiated_by_name
        FROM yogurt_transformations yt
        LEFT JOIN finished_goods_inventory fgi ON yt.source_inventory_id = fgi.id
        LEFT JOIN products p ON fgi.product_id = p.id
        LEFT JOIN master_recipes mr ON yt.target_recipe_id = mr.id
        LEFT JOIN users u ON yt.initiated_by = u.id
        WHERE yt.status = 'pending'
        ORDER BY yt.transformation_date ASC, yt.id ASC
    ");
    $rows = $stmt->fetchAll();

    Response::success(['pending' => $rows]);
}

function getActiveRuns($db)
{
    $stmt = $db->query("
        SELECT
            yt.id,
            yt.transformation_code,
            yt.source_quantity,
            yt.source_volume_liters,
            yt.target_product,
            yt.target_quantity,
            yt.transformation_date,
            yt.production_run_id,
            yt.status,
            pr.run_code,
            pr.status AS run_status,
            pr.initial_volume_ml,
            pr.actual_quantity AS run_actual_quantity,
            p.product_name AS source_product_name,
            mr.recipe_code,
            mr.product_name AS recipe_name,
            u.first_name AS started_by_name,
            TIMESTAMPDIFF(MINUTE, yt.approval_datetime, NOW()) AS minutes_active
        FROM yogurt_transformations yt
        LEFT JOIN production_runs pr ON yt.production_run_id = pr.id
        LEFT JOIN finished_goods_inventory fgi ON yt.source_inventory_id = fgi.id
        LEFT JOIN products p ON fgi.product_id = p.id
        LEFT JOIN master_recipes mr ON yt.target_recipe_id = mr.id
        LEFT JOIN users u ON yt.approved_by = u.id
        WHERE yt.status = 'in_progress'
        ORDER BY yt.transformation_date ASC, yt.id ASC
    ");
    $rows = $stmt->fetchAll();

    Response::success(['active' => $rows]);
}

function getHistory($db)
{
    $page  = max(1, (int) getParam('page', 1));
    $limit = min(50, max(1, (int) getParam('limit', 20)));
    $offset = ($page - 1) * $limit;

    $countStmt = $db->query("SELECT COUNT(*) AS total FROM yogurt_transformations WHERE status IN ('completed','cancelled')");
    $total = (int) $countStmt->fetch()['total'];

    $stmt = $db->prepare("
        SELECT
            yt.id,
            yt.transformation_code,
            yt.source_quantity,
            yt.source_volume_liters,
            yt.target_product,
            yt.target_quantity,
            yt.transformation_date,
            yt.status,
            yt.completed_at,
            p.product_name AS source_product_name,
            mr.recipe_code,
            u.first_name AS completed_by_name
        FROM yogurt_transformations yt
        LEFT JOIN finished_goods_inventory fgi ON yt.source_inventory_id = fgi.id
        LEFT JOIN products p ON fgi.product_id = p.id
        LEFT JOIN master_recipes mr ON yt.target_recipe_id = mr.id
        LEFT JOIN users u ON yt.completed_by = u.id
        WHERE yt.status IN ('completed','cancelled')
        ORDER BY yt.completed_at DESC, yt.id DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$limit, $offset]);
    $rows = $stmt->fetchAll();

    Response::success([
        'history' => $rows,
        'total'   => $total,
        'page'    => $page,
        'limit'   => $limit,
        'pages'   => max(1, ceil($total / $limit)),
    ]);
}

function getDetail($db)
{
    $id = (int) getParam('id', 0);
    if (!$id) Response::validationError(['id' => 'Conversion ID is required']);

    $stmt = $db->prepare("
        SELECT
            yt.*,
            p.product_name AS source_product_name,
            p.unit_measure AS source_unit_measure,
            mr.recipe_code,
            mr.product_name AS recipe_name,
            mr.base_milk_liters,
            mr.expected_yield,
            pr.run_code,
            pr.status AS run_status,
            pr.initial_volume_ml,
            pr.actual_quantity AS run_actual_quantity,
            u1.first_name AS initiated_by_name,
            u2.first_name AS approved_by_name,
            u3.first_name AS completed_by_name
        FROM yogurt_transformations yt
        LEFT JOIN finished_goods_inventory fgi ON yt.source_inventory_id = fgi.id
        LEFT JOIN products p ON fgi.product_id = p.id
        LEFT JOIN master_recipes mr ON yt.target_recipe_id = mr.id
        LEFT JOIN production_runs pr ON yt.production_run_id = pr.id
        LEFT JOIN users u1 ON yt.initiated_by = u1.id
        LEFT JOIN users u2 ON yt.approved_by = u2.id
        LEFT JOIN users u3 ON yt.completed_by = u3.id
        WHERE yt.id = ?
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row) Response::error('Conversion not found', 404);
    Response::success($row);
}

/* ──────────────────────────── POST ──────────────────────────── */

function handlePost($db, $currentUser)
{
    $action = getParam('action');

    switch ($action) {
        case 'start':
            startConversion($db, $currentUser);
            break;
        case 'complete':
            completeConversion($db, $currentUser);
            break;
        case 'cancel':
            cancelConversion($db, $currentUser);
            break;
        default:
            Response::error('Invalid action. Use start, complete, or cancel.', 400);
    }
}

function startConversion($db, $currentUser)
{
    $conversionId = (int) getParam('conversion_id', 0);
    $initialVolumeMl = (float) getParam('initial_volume_ml', 0);

    if (!$conversionId) Response::validationError(['conversion_id' => 'Conversion ID is required']);

    // Fetch the conversion
    $stmt = $db->prepare("SELECT * FROM yogurt_transformations WHERE id = ? AND status = 'pending'");
    $stmt->execute([$conversionId]);
    $conversion = $stmt->fetch();

    if (!$conversion) {
        Response::error('Conversion not found or already started', 404);
    }

    // Resolve recipe
    $recipeId = $conversion['target_recipe_id'];
    if (!$recipeId) {
        // Try to find a yogurt recipe
        $rStmt = $db->query("SELECT id FROM master_recipes WHERE product_type = 'yogurt' AND is_active = 1 LIMIT 1");
        $recipe = $rStmt->fetch();
        if (!$recipe) Response::error('No yogurt recipe available', 400);
        $recipeId = $recipe['id'];
    }

    // Get recipe details for volume calculation
    $recipeStmt = $db->prepare("SELECT base_milk_liters, expected_yield FROM master_recipes WHERE id = ?");
    $recipeStmt->execute([$recipeId]);
    $recipe = $recipeStmt->fetch();

    $volumeMl = $initialVolumeMl > 0
        ? $initialVolumeMl
        : ($conversion['source_volume_liters'] ?? 0) * 1000;

    // Generate run code
    $today = date('Ymd');
    $runCodeStmt = $db->prepare("SELECT COUNT(*) as cnt FROM production_runs WHERE run_code LIKE ?");
    $runCodeStmt->execute(["PRD-{$today}-%"]);
    $runCount = $runCodeStmt->fetch()['cnt'] + 1;
    $runCode = "PRD-{$today}-" . str_pad($runCount, 3, '0', STR_PAD_LEFT);

    $db->beginTransaction();

    try {
        // 1. Create production run
        $runStmt = $db->prepare("
            INSERT INTO production_runs (
                run_code, recipe_id, milk_type_id, planned_quantity, actual_quantity,
                initial_volume_ml, milk_liters_used, status, notes, started_by
            ) VALUES (?, ?, 1, ?, 0, ?, ?, 'in_progress', ?, ?)
        ");
        $runStmt->execute([
            $runCode,
            $recipeId,
            $conversion['source_volume_liters'] ?? 0,
            $volumeMl,
            $volumeMl / 1000,
            "Reprocessing conversion {$conversion['transformation_code']}",
            $currentUser['user_id'],
        ]);
        $runId = $db->lastInsertId();

        // 2. Update yogurt_transformations
        $updateStmt = $db->prepare("
            UPDATE yogurt_transformations
            SET status = 'in_progress',
                production_run_id = ?,
                approved_by = ?,
                approval_datetime = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$runId, $currentUser['user_id'], $conversionId]);

        $db->commit();

        Response::success([
            'conversion_id' => $conversionId,
            'run_id'        => $runId,
            'run_code'      => $runCode,
            'status'        => 'in_progress',
            'message'       => "Conversion started. Production run {$runCode} created.",
        ], "Conversion {$conversion['transformation_code']} started as run {$runCode}");

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function completeConversion($db, $currentUser)
{
    $conversionId = (int) getParam('conversion_id', 0);
    $actualYield = (float) getParam('actual_yield', 0);

    if (!$conversionId) Response::validationError(['conversion_id' => 'Conversion ID is required']);

    $stmt = $db->prepare("SELECT * FROM yogurt_transformations WHERE id = ? AND status = 'in_progress'");
    $stmt->execute([$conversionId]);
    $conversion = $stmt->fetch();

    if (!$conversion) {
        Response::error('Conversion not found or not in progress', 404);
    }

    $db->beginTransaction();

    try {
        // 1. Update conversion record
        $updateStmt = $db->prepare("
            UPDATE yogurt_transformations
            SET status = 'completed',
                completed_by = ?,
                completed_at = NOW(),
                target_quantity = ?
            WHERE id = ?
        ");
        $updateStmt->execute([
            $currentUser['user_id'],
            $actualYield > 0 ? $actualYield : null,
            $conversionId,
        ]);

        // 2. Complete the linked production run if exists
        if ($conversion['production_run_id']) {
            $runUpdate = $db->prepare("
                UPDATE production_runs
                SET status = 'completed',
                    actual_quantity = ?,
                    completed_at = NOW()
                WHERE id = ? AND status = 'in_progress'
            ");
            $runUpdate->execute([
                $actualYield > 0 ? $actualYield : 0,
                $conversion['production_run_id'],
            ]);
        }

        $db->commit();

        Response::success([
            'conversion_id' => $conversionId,
            'status'        => 'completed',
            'actual_yield'  => $actualYield,
        ], 'Conversion completed successfully');

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function cancelConversion($db, $currentUser)
{
    $conversionId = (int) getParam('conversion_id', 0);

    if (!$conversionId) Response::validationError(['conversion_id' => 'Conversion ID is required']);

    $stmt = $db->prepare("SELECT * FROM yogurt_transformations WHERE id = ? AND status IN ('pending','in_progress')");
    $stmt->execute([$conversionId]);
    $conversion = $stmt->fetch();

    if (!$conversion) {
        Response::error('Conversion not found or already completed', 404);
    }

    $db->beginTransaction();

    try {
        // 1. Restore inventory
        if ($conversion['source_inventory_id']) {
            // Determine if raw milk or finished goods
            $fgiCheck = $db->prepare("SELECT id FROM finished_goods_inventory WHERE id = ?");
            $fgiCheck->execute([$conversion['source_inventory_id']]);
            $isFG = $fgiCheck->fetch();

            if ($isFG) {
                $restoreStmt = $db->prepare("
                    UPDATE finished_goods_inventory
                    SET quantity_available = quantity_available + ?,
                        remaining_quantity = remaining_quantity + ?,
                        status = 'available'
                    WHERE id = ?
                ");
                $restoreStmt->execute([
                    $conversion['source_quantity'],
                    $conversion['source_quantity'],
                    $conversion['source_inventory_id'],
                ]);
            } else {
                $restoreStmt = $db->prepare("
                    UPDATE raw_milk_inventory
                    SET remaining_liters = remaining_liters + ?,
                        status = 'available'
                    WHERE id = ?
                ");
                $restoreStmt->execute([
                    $conversion['source_volume_liters'],
                    $conversion['source_inventory_id'],
                ]);
            }
        }

        // 2. Cancel linked production run
        if ($conversion['production_run_id']) {
            $runCancel = $db->prepare("UPDATE production_runs SET status = 'cancelled' WHERE id = ?");
            $runCancel->execute([$conversion['production_run_id']]);
        }

        // 3. Update conversion
        $updateStmt = $db->prepare("
            UPDATE yogurt_transformations SET status = 'cancelled' WHERE id = ?
        ");
        $updateStmt->execute([$conversionId]);

        $db->commit();

        Response::success([
            'conversion_id' => $conversionId,
            'status'        => 'cancelled',
        ], 'Conversion cancelled and inventory restored');

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}
