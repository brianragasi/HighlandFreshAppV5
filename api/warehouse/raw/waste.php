<?php
/**
 * Raw material spoilage and waste handling.
 */

require_once dirname(dirname(__DIR__)) . '/bootstrap.php';

$currentUser = Auth::requireRole(['warehouse_raw', 'general_manager', 'purchaser']);
$action = getParam('action', 'list');

try {
    $db = Database::getInstance()->getConnection();
    ensureRawMaterialWasteSupport($db);

    if ($requestMethod === 'GET') {
        handleWasteGet($db, $action, $currentUser);
    } elseif ($requestMethod === 'POST') {
        requireActionRole($currentUser, ['warehouse_raw', 'general_manager'], 'Only Warehouse Raw or GM can record waste');
        createWasteRecord($db, $currentUser);
    } else {
        Response::error('Method not allowed', 405);
    }
} catch (Throwable $e) {
    error_log('Raw material waste API error: ' . $e->getMessage());
    Response::error($e->getMessage(), 500);
}

function ensureRawMaterialWasteSupport($db) {
    if (!auditColumnExists($db, 'ingredient_batches', 'rr_id')) {
        $db->exec("ALTER TABLE `ingredient_batches` ADD COLUMN `rr_id` INT(11) NULL AFTER `po_id`");
    }
    if (!auditColumnExists($db, 'ingredient_batches', 'supplier_batch_no')) {
        $db->exec("ALTER TABLE `ingredient_batches` ADD COLUMN `supplier_batch_no` VARCHAR(50) NULL AFTER `supplier_id`");
    }
    if (!auditColumnExists($db, 'mro_inventory', 'po_id')) {
        $db->exec("ALTER TABLE `mro_inventory` ADD COLUMN `po_id` INT(11) NULL AFTER `mro_item_id`");
    }
    if (!auditColumnExists($db, 'mro_inventory', 'rr_id')) {
        $db->exec("ALTER TABLE `mro_inventory` ADD COLUMN `rr_id` INT(11) NULL AFTER `po_id`");
    }
    if (!auditColumnExists($db, 'mro_inventory', 'supplier_id')) {
        $db->exec("ALTER TABLE `mro_inventory` ADD COLUMN `supplier_id` INT(11) NULL AFTER `supplier_name`");
    }
    if (!auditColumnExists($db, 'mro_inventory', 'expiry_date')) {
        $db->exec("ALTER TABLE `mro_inventory` ADD COLUMN `expiry_date` DATE NULL AFTER `received_date`");
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS `raw_material_waste` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `waste_code` VARCHAR(30) NOT NULL,
            `item_type` ENUM('ingredient','mro') NOT NULL,
            `item_id` INT(11) NOT NULL,
            `batch_id` INT(11) DEFAULT NULL,
            `rr_id` INT(11) DEFAULT NULL,
            `po_id` INT(11) DEFAULT NULL,
            `supplier_id` INT(11) DEFAULT NULL,
            `batch_code` VARCHAR(50) DEFAULT NULL,
            `item_name` VARCHAR(255) NOT NULL,
            `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `unit` VARCHAR(30) NOT NULL,
            `unit_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `total_value` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `reason_category` VARCHAR(50) NOT NULL,
            `reason` TEXT NOT NULL,
            `evidence_photo_path` VARCHAR(500) DEFAULT NULL,
            `waste_date` DATE NOT NULL,
            `status` VARCHAR(30) NOT NULL DEFAULT 'approved',
            `recorded_by` INT(11) NOT NULL,
            `recorded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `approved_by` INT(11) DEFAULT NULL,
            `approved_at` DATETIME DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_raw_material_waste_code` (`waste_code`),
            KEY `idx_raw_material_waste_item` (`item_type`, `item_id`),
            KEY `idx_raw_material_waste_batch` (`item_type`, `batch_id`),
            KEY `idx_raw_material_waste_date` (`waste_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    if (!auditColumnExists($db, 'raw_material_waste', 'evidence_photo_path')) {
        $db->exec("ALTER TABLE `raw_material_waste` ADD COLUMN `evidence_photo_path` VARCHAR(500) DEFAULT NULL AFTER `reason`");
    }
}

function wasteTableExists($db, $tableName) {
    $stmt = $db->prepare("
        SELECT 1
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
        LIMIT 1
    ");
    $stmt->execute([$tableName]);
    return (bool)$stmt->fetchColumn();
}

function handleWasteGet($db, $action, $currentUser) {
    switch ($action) {
        case 'available_items':
            getWasteAvailableItems($db);
            break;
        case 'batches':
            getWasteBatches($db);
            break;
        case 'reports':
            getWasteReports($db);
            break;
        case 'evidence':
            streamEvidencePhoto($db, $currentUser);
            break;
        case 'list':
        default:
            getWasteRecords($db);
            break;
    }
}

function getWasteAvailableItems($db) {
    $ingredients = $db->query("
        SELECT 'ingredient' AS item_type, id AS item_id, ingredient_code AS item_code,
               ingredient_name AS item_name, unit_of_measure AS unit, current_stock
        FROM ingredients
        WHERE is_active = 1 AND current_stock > 0
    ")->fetchAll();

    $mro = $db->query("
        SELECT 'mro' AS item_type, id AS item_id, item_code, item_name,
               unit_of_measure AS unit, current_stock
        FROM mro_items
        WHERE is_active = 1 AND current_stock > 0
    ")->fetchAll();

    Response::success(['items' => array_merge($ingredients, $mro)], 'Available raw materials retrieved');
}

function getWasteBatches($db) {
    $itemType = getParam('item_type');
    $itemId = (int)getParam('item_id');
    if (!$itemType || !$itemId) {
        Response::error('Item type and item ID are required', 400);
    }

    if ($itemType === 'ingredient') {
        $stmt = $db->prepare("
            SELECT ib.id AS batch_id, ib.batch_code, ib.remaining_quantity, ib.unit_cost,
                   ib.expiry_date, ib.received_date, ib.rr_id, ib.po_id, ib.supplier_id,
                   i.unit_of_measure AS unit
            FROM ingredient_batches ib
            JOIN ingredients i ON i.id = ib.ingredient_id
            WHERE ib.ingredient_id = ? AND ib.remaining_quantity > 0
              AND ib.status IN ('available','partially_used','quarantine')
            ORDER BY ib.expiry_date ASC, ib.received_date ASC, ib.id ASC
        ");
    } else {
        $stmt = $db->prepare("
            SELECT mi.id AS batch_id, mi.batch_code, mi.remaining_quantity, mi.unit_cost,
                   mi.expiry_date, mi.received_date, mi.rr_id, mi.po_id, mi.supplier_id,
                   m.unit_of_measure AS unit
            FROM mro_inventory mi
            JOIN mro_items m ON m.id = mi.mro_item_id
            WHERE mi.mro_item_id = ? AND mi.remaining_quantity > 0
              AND mi.status IN ('available','partially_used')
            ORDER BY mi.expiry_date ASC, mi.received_date ASC, mi.id ASC
        ");
    }

    $stmt->execute([$itemId]);
    Response::success(['batches' => $stmt->fetchAll()], 'Available batches retrieved');
}

function getWasteRecords($db) {
    $stmt = $db->query("
        SELECT w.id, w.waste_code, w.item_type, w.item_id, w.batch_id, w.rr_id, w.po_id,
               w.supplier_id, w.batch_code, w.item_name, w.quantity, w.unit, w.unit_cost,
               w.total_value, w.reason_category, w.reason, w.evidence_photo_path,
               w.waste_date, w.status, w.recorded_by, w.recorded_at, w.approved_by, w.approved_at,
               u.full_name AS recorded_by_name, po.po_number, rr.rr_number, s.supplier_name
        FROM raw_material_waste w
        LEFT JOIN users u ON u.id = w.recorded_by
        LEFT JOIN purchase_orders po ON po.id = w.po_id
        LEFT JOIN receiving_reports rr ON rr.id = w.rr_id
        LEFT JOIN suppliers s ON s.id = w.supplier_id
        ORDER BY w.waste_date DESC, w.id DESC
        LIMIT 100
    ");

    Response::success(['waste_records' => $stmt->fetchAll()], 'Waste records retrieved');
}

function getWasteReports($db) {
    $expired = $db->query("
        SELECT 'ingredient' AS item_type, i.ingredient_name AS item_name, ib.batch_code,
               ib.remaining_quantity AS quantity, i.unit_of_measure AS unit, ib.expiry_date
        FROM ingredient_batches ib
        JOIN ingredients i ON i.id = ib.ingredient_id
        WHERE ib.expiry_date IS NOT NULL AND ib.expiry_date < CURDATE()
          AND ib.remaining_quantity > 0
        UNION ALL
        SELECT 'mro' AS item_type, m.item_name, mi.batch_code,
               mi.remaining_quantity, m.unit_of_measure, mi.expiry_date
        FROM mro_inventory mi
        JOIN mro_items m ON m.id = mi.mro_item_id
        WHERE mi.expiry_date IS NOT NULL AND mi.expiry_date < CURDATE()
          AND mi.remaining_quantity > 0
        ORDER BY expiry_date ASC
    ")->fetchAll();

    $spoiled = $db->query("SELECT id, waste_code, item_type, item_name, batch_code, quantity, unit, reason_category, reason, evidence_photo_path, waste_date, status, recorded_by, recorded_at FROM raw_material_waste WHERE reason_category = 'spoiled' ORDER BY waste_date DESC LIMIT 100")->fetchAll();
    $wasted = $db->query("SELECT id, waste_code, item_type, item_name, batch_code, quantity, unit, reason_category, reason, evidence_photo_path, waste_date, status, recorded_by, recorded_at FROM raw_material_waste ORDER BY waste_date DESC, id DESC LIMIT 100")->fetchAll();
    $rejected = [];
    if (wasteTableExists($db, 'supplier_rejections')) {
        $hasReceivingReports = wasteTableExists($db, 'receiving_reports');
        $hasPurchaseOrders = wasteTableExists($db, 'purchase_orders');
        $hasSuppliers = wasteTableExists($db, 'suppliers');

        $select = "SELECT sr.*";
        $from = " FROM supplier_rejections sr";
        if ($hasReceivingReports) {
            $select .= ", rr.rr_number";
            $from .= " LEFT JOIN receiving_reports rr ON rr.id = sr.rr_id";
        } else {
            $select .= ", NULL AS rr_number";
        }
        if ($hasPurchaseOrders && $hasReceivingReports) {
            $select .= ", po.po_number";
            $from .= " LEFT JOIN purchase_orders po ON po.id = rr.po_id";
        } else {
            $select .= ", NULL AS po_number";
        }
        if ($hasSuppliers) {
            $select .= ", s.supplier_name";
            $from .= " LEFT JOIN suppliers s ON s.id = sr.supplier_id";
        } else {
            $select .= ", NULL AS supplier_name";
        }

        $rejected = $db->query($select . $from . " ORDER BY sr.created_at DESC LIMIT 100")->fetchAll();
    }

    Response::success([
        'expired' => $expired,
        'spoiled' => $spoiled,
        'wasted' => $wasted,
        'rejected' => $rejected
    ], 'Raw material reports retrieved');
}

function createWasteRecord($db, $currentUser) {
    $data = getParams();
    $itemType = $data['item_type'] ?? '';
    $itemId = (int)($data['item_id'] ?? 0);
    $batchId = (int)($data['batch_id'] ?? 0);
    $quantity = (float)($data['quantity'] ?? 0);
    $reasonCategory = trim((string)($data['reason_category'] ?? 'other'));
    $reason = trim((string)($data['reason'] ?? ''));
    $wasteDate = $data['waste_date'] ?? date('Y-m-d');

    if (!in_array($itemType, ['ingredient', 'mro'], true) || !$itemId || $quantity <= 0 || $reason === '') {
        Response::error('Item, quantity, and reason are required', 400);
    }

    $photoRequires = ['damaged', 'contaminated', 'spoiled', 'handling_loss'];
    $photoRequired = in_array($reasonCategory, $photoRequires, true);
    $evidenceFile = isset($_FILES['evidence_photo']) ? $_FILES['evidence_photo'] : null;
    $hasEvidence = $evidenceFile && (int)$evidenceFile['error'] !== UPLOAD_ERR_NO_FILE;

    if ($photoRequired && !$hasEvidence) {
        Response::error('Photo evidence is required for damage / contamination / spoilage / handling loss reasons', 400);
    }

    $evidencePath = null;
    $savedFileAbs = null;

    if ($hasEvidence) {
        $evidencePath = saveWasteEvidenceFile($evidenceFile, $reasonCategory);
        if ($evidencePath === false) {
            Response::error('Invalid photo upload. Use JPEG, PNG, WebP, or HEIC up to 5 MB.', 400);
        }
        $savedFileAbs = dirname(dirname(__DIR__)) . '/' . $evidencePath;
    }

    $db->beginTransaction();
    try {
        $batch = $batchId > 0
            ? loadWasteBatchForUpdate($db, $itemType, $itemId, $batchId)
            : loadWasteItemForUpdate($db, $itemType, $itemId);
        if (!$batch) {
            throw new Exception($batchId > 0 ? 'Batch not found or unavailable' : 'Item not found or has no available stock');
        }
        if ((float)$batch['remaining_quantity'] < $quantity) {
            throw new Exception('Waste quantity exceeds available stock');
        }

        $newRemaining = (float)$batch['remaining_quantity'] - $quantity;
        $newStatus = $newRemaining > 0 ? 'partially_used' : 'consumed';
        $wasteCode = generateWasteCode($db);
        $unitCost = (float)($batch['unit_cost'] ?? 0);

        if ($itemType === 'ingredient') {
            if ($batchId > 0) {
                $db->prepare("UPDATE ingredient_batches SET remaining_quantity = ?, status = ?, updated_at = NOW() WHERE id = ?")
                    ->execute([$newRemaining, $newStatus, $batchId]);
            }
            $db->prepare("UPDATE ingredients SET current_stock = GREATEST(current_stock - ?, 0), updated_at = NOW() WHERE id = ?")
                ->execute([$quantity, $itemId]);
        } else {
            if ($batchId > 0) {
                $db->prepare("UPDATE mro_inventory SET remaining_quantity = ?, status = ?, updated_at = NOW() WHERE id = ?")
                    ->execute([$newRemaining, $newStatus, $batchId]);
            }
            $db->prepare("UPDATE mro_items SET current_stock = GREATEST(current_stock - ?, 0), updated_at = NOW() WHERE id = ?")
                ->execute([$quantity, $itemId]);
        }

        $db->prepare("
            INSERT INTO raw_material_waste
            (waste_code, item_type, item_id, batch_id, rr_id, po_id, supplier_id, batch_code,
             item_name, quantity, unit, unit_cost, total_value, reason_category, reason,
             evidence_photo_path,
             waste_date, status, recorded_by, approved_by, approved_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?, ?, NOW())
        ")->execute([
            $wasteCode, $itemType, $itemId, $batchId > 0 ? $batchId : null, $batch['rr_id'] ?? null, $batch['po_id'] ?? null,
            $batch['supplier_id'] ?? null, $batch['batch_code'] ?? null, $batch['item_name'],
            $quantity, $batch['unit'], $unitCost, $quantity * $unitCost, $reasonCategory, $reason,
            $evidencePath,
            $wasteDate, $currentUser['user_id'], $currentUser['user_id']
        ]);
        $wasteId = (int)$db->lastInsertId();

        if ($evidencePath !== null) {
            $finalName = 'wst_' . $wasteId . '_' . substr(bin2hex(random_bytes(3)), 0, 6) . '.jpg';
            $finalRel = dirname($evidencePath) . '/' . $finalName;
            $finalAbs = dirname(dirname(__DIR__)) . '/' . $finalRel;
            if (!@rename($savedFileAbs, $finalAbs)) {
                throw new Exception('Failed to finalize evidence file');
            }
            $db->prepare("UPDATE raw_material_waste SET evidence_photo_path = ? WHERE id = ?")
                ->execute([$finalRel, $wasteId]);
            $evidencePath = $finalRel;
            $savedFileAbs = $finalAbs;
        }

        $txCode = generateCode('TX');
        $db->prepare("
            INSERT INTO inventory_transactions
            (transaction_code, transaction_type, item_type, item_id, batch_id, quantity,
             unit_of_measure, reference_type, reference_id, from_location, unit_cost,
             total_cost, performed_by, approved_by, reason)
            VALUES (?, 'dispose', ?, ?, ?, ?, ?, 'raw_material_waste', ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $txCode, $itemType, $itemId, $batchId > 0 ? $batchId : null, $quantity, $batch['unit'], $wasteId,
            'Warehouse Raw', $unitCost, $quantity * $unitCost, $currentUser['user_id'],
            $currentUser['user_id'], 'Waste: ' . $reasonCategory . ' - ' . $reason
        ]);

        logAudit($currentUser['user_id'], 'WASTE', 'raw_material_waste', $wasteId, null, [
            'waste_code' => $wasteCode,
            'item_type' => $itemType,
            'item_id' => $itemId,
            'batch_id' => $batchId > 0 ? $batchId : null,
            'quantity' => $quantity,
            'reason_category' => $reasonCategory,
            'evidence_photo_path' => $evidencePath
        ]);
        if ($evidencePath !== null) {
            logAudit($currentUser['user_id'], 'UPLOAD_EVIDENCE', 'raw_material_waste', $wasteId, null, [
                'waste_code' => $wasteCode,
                'evidence_photo_path' => $evidencePath,
                'reason_category' => $reasonCategory
            ]);
        }

        $db->commit();
        Response::created([
            'id' => $wasteId,
            'waste_code' => $wasteCode,
            'transaction_code' => $txCode,
            'evidence_photo_path' => $evidencePath
        ], 'Waste recorded and deducted from inventory');
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        if ($savedFileAbs && file_exists($savedFileAbs)) {
            @unlink($savedFileAbs);
        }
        Response::error($e->getMessage(), 400);
    }
}

/**
 * Save an evidence photo for a waste record.
 * Returns the relative path on success, or false on validation failure.
 *
 * If the GD extension is available, the image is re-encoded as JPEG (max
 * 1600px, ~82% quality) and EXIF is stripped. Otherwise we fall back to a
 * raw move_uploaded_file() with a stricter 5 MB cap.
 */
function saveWasteEvidenceFile($file, $reasonCategory) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return false;
    }

    $maxBytes = 5 * 1024 * 1024;
    if ((int)$file['size'] > $maxBytes) {
        return false;
    }

    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];
    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
    $detectedMime = $finfo ? finfo_file($finfo, $file['tmp_name']) : ($file['type'] ?? '');
    if ($finfo) {
        finfo_close($finfo);
    }
    if (!in_array($detectedMime, $allowedMimes, true)) {
        return false;
    }

    $projectRoot = dirname(dirname(__DIR__));
    $year = date('Y');
    $month = date('m');
    $targetDir = $projectRoot . '/uploads/waste/' . $year . '/' . $month;
    if (!is_dir($targetDir)) {
        if (!@mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            return false;
        }
        $htaccess = $targetDir . '/.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar|cgi|pl|py|sh)$\">\n  Require all denied\n</FilesMatch>\n");
        }
    }

    $tempName = 'wst_tmp_' . bin2hex(random_bytes(6)) . '.jpg';
    $tempAbs = $targetDir . '/' . $tempName;
    $tempRel = 'uploads/waste/' . $year . '/' . $month . '/' . $tempName;

    $saved = false;
    if (extension_loaded('gd') && function_exists('getimagesize')) {
        $image = @imagecreatefromstring(file_get_contents($file['tmp_name']));
        if ($image !== false) {
            $w = imagesx($image);
            $h = imagesy($image);
            $maxSide = 1600;
            if ($w > $maxSide || $h > $maxSide) {
                $scale = $maxSide / max($w, $h);
                $newW = (int)round($w * $scale);
                $newH = (int)round($h * $scale);
                $resized = imagecreatetruecolor($newW, $newH);
                imagecopyresampled($resized, $image, 0, 0, 0, 0, $newW, $newH, $w, $h);
                imagedestroy($image);
                $image = $resized;
            }
            if (imageinterlace($image, true) && function_exists('imagejpeg')) {
                $saved = @imagejpeg($image, $tempAbs, 82);
            }
            imagedestroy($image);
        }
    }

    if (!$saved) {
        if ((int)$file['size'] > $maxBytes) {
            return false;
        }
        $saved = @move_uploaded_file($file['tmp_name'], $tempAbs);
    }

    if (!$saved || !file_exists($tempAbs)) {
        return false;
    }

    return $tempRel;
}

/**
 * Stream a stored evidence photo with the ?token= query fallback for <img src>.
 */
function streamEvidencePhoto($db, $currentUser) {
    $wasteId = (int)getParam('id', 0);
    if ($wasteId <= 0) {
        Response::notFound('Waste record not found');
    }
    $token = $_GET['token'] ?? null;
    if ($token && !Auth::getCurrentUser()) {
        $user = Auth::verifyToken($token);
        if (!$user) {
            Response::unauthorized('Invalid or expired token');
        }
        if (!in_array($user['role'] ?? '', ['warehouse_raw', 'general_manager', 'purchaser'], true)) {
            Response::forbidden('You do not have permission to view this evidence');
        }
    }

    $stmt = $db->prepare("SELECT evidence_photo_path FROM raw_material_waste WHERE id = ?");
    $stmt->execute([$wasteId]);
    $row = $stmt->fetch();
    if (!$row || empty($row['evidence_photo_path'])) {
        Response::notFound('No evidence photo for this record');
    }

    $projectRoot = dirname(dirname(__DIR__));
    $rel = ltrim($row['evidence_photo_path'], '/');
    $abs = $projectRoot . '/' . $rel;
    if (strpos($rel, 'uploads/waste/') !== 0 || !file_exists($abs)) {
        Response::notFound('Evidence file missing on disk');
    }

    $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
    $mime = 'image/jpeg';
    if ($ext === 'png') $mime = 'image/png';
    elseif ($ext === 'webp') $mime = 'image/webp';
    elseif ($ext === 'heic' || $ext === 'heif') $mime = 'image/heic';

    Response::file($abs, $mime, 'inline');
}

function loadWasteItemForUpdate($db, $itemType, $itemId) {
    if ($itemType === 'ingredient') {
        $stmt = $db->prepare("
            SELECT id AS item_id, ingredient_name AS item_name, unit_of_measure AS unit,
                   current_stock AS remaining_quantity, unit_cost,
                   NULL AS rr_id, NULL AS po_id, NULL AS supplier_id, NULL AS batch_code
            FROM ingredients
            WHERE id = ? AND is_active = 1 AND current_stock > 0
            FOR UPDATE
        ");
    } else {
        $stmt = $db->prepare("
            SELECT id AS item_id, item_name, unit_of_measure AS unit,
                   current_stock AS remaining_quantity, unit_cost,
                   NULL AS rr_id, NULL AS po_id, NULL AS supplier_id, NULL AS batch_code
            FROM mro_items
            WHERE id = ? AND is_active = 1 AND current_stock > 0
            FOR UPDATE
        ");
    }
    $stmt->execute([$itemId]);
    return $stmt->fetch();
}

function loadWasteBatchForUpdate($db, $itemType, $itemId, $batchId) {
    if ($itemType === 'ingredient') {
        $stmt = $db->prepare("
            SELECT ib.*, i.ingredient_name AS item_name, i.unit_of_measure AS unit
            FROM ingredient_batches ib
            JOIN ingredients i ON i.id = ib.ingredient_id
            WHERE ib.id = ? AND ib.ingredient_id = ? AND ib.remaining_quantity > 0
            FOR UPDATE
        ");
    } else {
        $stmt = $db->prepare("
            SELECT mi.*, m.item_name, m.unit_of_measure AS unit
            FROM mro_inventory mi
            JOIN mro_items m ON m.id = mi.mro_item_id
            WHERE mi.id = ? AND mi.mro_item_id = ? AND mi.remaining_quantity > 0
            FOR UPDATE
        ");
    }
    $stmt->execute([$batchId, $itemId]);
    return $stmt->fetch();
}

function generateWasteCode($db) {
    $prefix = 'WST-' . date('Ymd') . '-';
    $stmt = $db->prepare("SELECT waste_code FROM raw_material_waste WHERE waste_code LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();
    $seq = 1;
    if ($last && preg_match('/-(\d+)$/', $last, $matches)) {
        $seq = (int)$matches[1] + 1;
    }
    return $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
}
