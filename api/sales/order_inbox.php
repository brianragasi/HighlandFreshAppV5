<?php

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/helpers/customer_order_import.php';
require_once dirname(__DIR__) . '/helpers/pop3_mailbox.php';

$currentUser = Auth::requireRole(['sales_custodian', 'general_manager']);
$db = Database::getInstance()->getConnection();
hfEnsureManualCustomerOrderSchema($db);
$action = getParam('action', 'list');

try {
    if ($requestMethod === 'GET') {
        handleCustomerOrderInboxGet($db, $action);
    } elseif ($requestMethod === 'POST') {
        handleCustomerOrderInboxPost($db, $action, $currentUser);
    } else {
        Response::error('Method not allowed', 405);
    }
} catch (InvalidArgumentException $e) {
    Response::error($e->getMessage(), 422);
} catch (RuntimeException $e) {
    Response::error($e->getMessage(), 400);
} catch (Throwable $e) {
    error_log('Customer Order Inbox Error: ' . $e->getMessage());
    Response::error('Could not process the customer order inbox.', 500);
}

function hfResolveCustomerOrderAttachment(PDO $db, int $id): array
{
    if ($id <= 0) {
        throw new InvalidArgumentException('Choose a customer PO email.');
    }

    $stmt = $db->prepare('SELECT attachment_original_name, attachment_path FROM customer_order_imports WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || trim((string)($row['attachment_path'] ?? '')) === '') {
        throw new RuntimeException('The original customer attachment is not available.');
    }

    $uploadRoot = realpath(dirname(__DIR__, 2) . '/uploads/customer_orders');
    $path = realpath(dirname(__DIR__, 2) . '/' . ltrim(str_replace('\\', '/', (string)$row['attachment_path']), '/'));
    if ($uploadRoot === false || $path === false || !is_file($path)) {
        throw new RuntimeException('The original customer attachment could not be found.');
    }
    $rootPrefix = rtrim(str_replace('\\', '/', $uploadRoot), '/') . '/';
    if (!str_starts_with(str_replace('\\', '/', $path), $rootPrefix)) {
        throw new RuntimeException('The attachment path is not allowed.');
    }

    $filename = basename((string)($row['attachment_original_name'] ?? 'customer-po'));
    $filename = preg_replace('/[^A-Za-z0-9._() -]/', '_', $filename) ?: 'customer-po';
    return [
        'path' => $path,
        'filename' => $filename,
        'extension' => strtolower(pathinfo($filename, PATHINFO_EXTENSION)),
    ];
}

/**
 * Suggest only clear header values. These suggestions never create order lines
 * and Sales must still compare them with the original attachment.
 */
function hfSuggestCustomerOrderHeaders(array $import): array
{
    $subject = trim((string)($import['subject'] ?? ''));
    $body = trim((string)($import['email_body'] ?? ''));
    $filename = trim((string)($import['attachment_original_name'] ?? ''));
    $text = mb_substr($subject . "\n" . $body, 0, 12000);
    $poNumber = null;
    $deliveryDate = null;

    $poPatterns = [
        '/(?:customer\s+)?po\s*(?:number|no\.?|#)\s*[:\-]\s*([A-Z0-9][A-Z0-9._\/-]{1,79})/i',
        '/purchase\s+order\s+(?:number|no\.?|#)\s*[:\-]?\s*([A-Z0-9][A-Z0-9._\/-]{1,79})/i',
    ];
    foreach ($poPatterns as $pattern) {
        if (preg_match($pattern, $text, $match)) {
            $poNumber = mb_substr(trim($match[1]), 0, 80);
            break;
        }
    }
    if ($poNumber === null && preg_match('/(?:^|[^A-Z0-9])(?:C?PO)[-_ ]([A-Z0-9][A-Z0-9._-]{1,70})(?:\.[A-Z0-9]+)?$/i', pathinfo($filename, PATHINFO_FILENAME), $match)) {
        $poNumber = mb_substr(trim($match[1]), 0, 80);
    }

    if (preg_match('/(?:requested\s+)?delivery\s+date\s*[:\-]\s*([^\r\n]{6,40})/i', $text, $match)) {
        $timestamp = strtotime(trim($match[1]));
        if ($timestamp !== false) {
            $deliveryDate = date('Y-m-d', $timestamp);
        }
    }

    $internalReference = false;
    if ($poNumber === null
        && trim((string)($import['attachment_path'] ?? '')) === ''
        && (int)($import['id'] ?? 0) > 0) {
        $received = strtotime((string)($import['received_at'] ?? $import['created_at'] ?? 'now')) ?: time();
        $poNumber = 'EMAIL-' . date('Ymd', $received) . '-' . str_pad((string)(int)$import['id'], 4, '0', STR_PAD_LEFT);
        $internalReference = true;
    }

    return [
        'po_number' => $poNumber,
        'delivery_date' => $deliveryDate,
        'source' => $internalReference
            ? 'internal_email_reference'
            : (($poNumber !== null || $deliveryDate !== null) ? 'email' : null),
    ];
}

function handleCustomerOrderInboxGet(PDO $db, string $action): void
{
    if ($action === 'download') {
        $id = (int) getParam('id', 0);
        $attachment = hfResolveCustomerOrderAttachment($db, $id);
        $path = $attachment['path'];
        $filename = $attachment['filename'];
        header_remove('Content-Type');
        $extension = $attachment['extension'];
        $contentTypes = [
            'pdf' => 'application/pdf',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'csv' => 'text/csv; charset=UTF-8',
        ];
        header('Content-Type: ' . ($contentTypes[$extension] ?? 'application/octet-stream'));
        $disposition = getParam('view', '') === '1' && $extension === 'pdf' ? 'inline' : 'attachment';
        header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    if ($action === 'preview') {
        $id = (int)getParam('id', 0);
        $attachment = hfResolveCustomerOrderAttachment($db, $id);
        $content = file_get_contents($attachment['path']);
        if ($content === false) {
            throw new RuntimeException('The original attachment could not be read.');
        }
        if ($attachment['extension'] === 'xlsx') {
            Response::success(hfPreviewCustomerOrderWorkbook($content), 'Excel preview prepared.');
        }
        if ($attachment['extension'] === 'csv') {
            Response::success(hfPreviewCustomerOrderCsv($content), 'CSV preview prepared.');
        }
        Response::success([
            'kind' => in_array($attachment['extension'], ['pdf', 'jpg', 'jpeg', 'png'], true)
                ? $attachment['extension']
                : 'unavailable',
            'rows' => [],
            'suggested_po_number' => null,
            'suggested_delivery_date' => null,
        ], 'Attachment preview information retrieved.');
    }

    if ($action === 'config') {
        Response::success([
            'mailbox_enabled' => defined('ORDER_MAILBOX_ENABLED') && ORDER_MAILBOX_ENABLED,
            'mailbox_address' => defined('ORDER_MAILBOX_USERNAME')
                ? ORDER_MAILBOX_USERNAME
                : '',
            'supported_format' => 'Order details written in the email or supplied in an attached document',
        ], 'Customer order inbox configuration');
    }

    if ($action === 'summary') {
        $summary = $db->query("
            SELECT
                SUM(status IN ('received', 'for_encoding')
                    OR (status IN ('customer_confirmed', 'ready_to_create', 'ready') AND source_verified_at IS NULL)) AS for_encoding,
                SUM(status = 'needs_customer_confirmation') AS needs_confirmation,
                SUM(status IN ('customer_confirmed', 'ready_to_create', 'ready')
                    AND source_verified_at IS NOT NULL) AS ready_to_create,
                SUM(status = 'rejected') AS rejected,
                SUM(status IN ('received', 'for_encoding', 'draft_order', 'needs_customer_confirmation',
                    'customer_confirmed', 'ready_to_create', 'needs_review', 'ready')) AS needs_action
            FROM customer_order_imports
            WHERE status <> 'archived'
        ")->fetch(PDO::FETCH_ASSOC) ?: [];
        Response::success([
            'for_encoding' => (int)($summary['for_encoding'] ?? 0),
            'needs_confirmation' => (int)($summary['needs_confirmation'] ?? 0),
            'ready_to_create' => (int)($summary['ready_to_create'] ?? 0),
            'rejected' => (int)($summary['rejected'] ?? 0),
            'needs_action' => (int)($summary['needs_action'] ?? 0),
        ], 'Customer order inbox summary retrieved');
    }

    if ($action === 'detail') {
        $id = (int) getParam('id', 0);
        $stmt = $db->prepare("
            SELECT coi.*,
                   c.name AS customer_name,
                   c.customer_code,
                   c.customer_type,
                   c.contact_person AS customer_contact_person,
                   c.contact_number AS customer_contact_number,
                   c.address AS customer_address,
                   c.default_payment_type,
                   c.payment_terms_days,
                   c.credit_limit,
                   c.current_balance,
                   (SELECT COALESCE(SUM(dr.total_amount - dr.amount_paid), 0)
                    FROM delivery_receipts dr
                    WHERE dr.customer_id = c.id
                      AND dr.payment_status != 'paid'
                      AND dr.status NOT IN ('cancelled', 'draft')) AS outstanding_balance,
                   so.order_number,
                   so.status AS sales_order_status
            FROM customer_order_imports coi
            LEFT JOIN customers c ON c.id = coi.customer_id
            LEFT JOIN sales_orders so ON so.id = coi.sales_order_id
            WHERE coi.id = ?
        ");
        $stmt->execute([$id]);
        $import = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$import) {
            Response::notFound('Imported customer PO not found');
        }
        $lines = $db->prepare("
            SELECT l.*, p.product_code, p.product_name, p.base_unit,
                   p.box_unit, p.pieces_per_box
            FROM customer_order_import_lines l
            LEFT JOIN products p ON p.id = l.product_id
            WHERE l.import_id = ?
            ORDER BY l.row_number
        ");
        $lines->execute([$id]);
        $import['lines'] = $lines->fetchAll(PDO::FETCH_ASSOC);
        $import['has_encoding_changes'] = hfManualOrderHasChanges($import['lines']);
        hfEnsureCustomerOrderAdjustmentTable($db);
        $adjustments = $db->prepare("\n            SELECT id, import_line_id, adjustment_type, reason, contact_name,\n                   call_datetime, note, adjusted_by, created_at, original_data, adjusted_data\n            FROM customer_order_adjustments\n            WHERE import_id = ?\n            ORDER BY id\n        ");
        $adjustments->execute([$id]);
        $import['adjustments'] = $adjustments->fetchAll(PDO::FETCH_ASSOC);
        $calls = $db->prepare("SELECT id, change_summary, reason, contact_name, confirmation_method,
                confirmed_at, note, recorded_by, created_at
            FROM customer_order_call_confirmations
            WHERE import_id = ?
            ORDER BY id");
        $calls->execute([$id]);
        $import['call_confirmations'] = $calls->fetchAll(PDO::FETCH_ASSOC);
        $import['effective_delivery_date'] = null;
        $rawFirst = json_decode((string)($import['lines'][0]['raw_data'] ?? '{}'), true) ?: [];
        if (!empty($rawFirst['delivery_date'])) {
            $import['effective_delivery_date'] = $rawFirst['delivery_date'];
        }
        if (!empty($import['entered_delivery_date'])) {
            $import['effective_delivery_date'] = $import['entered_delivery_date'];
        }
        foreach (array_reverse($import['adjustments']) as $adjustment) {
            $adjustedData = json_decode((string)($adjustment['adjusted_data'] ?? '{}'), true) ?: [];
            if (!empty($adjustedData['delivery_date'])) {
                $import['effective_delivery_date'] = $adjustedData['delivery_date'];
                break;
            }
        }
        $import['trusted_reference'] = hfTrustedCustomerOrderReferenceForImport($db, $import);
        $import['header_suggestions'] = hfSuggestCustomerOrderHeaders($import);
        Response::success($import, 'Imported customer PO retrieved');
    }

    $status = trim((string) getParam('status', ''));
    $search = trim((string) getParam('search', ''));
    $view = trim((string) getParam('view', 'action'));
    if (!in_array($view, ['action', 'recent', 'all'], true)) {
        $view = 'action';
    }
    $sql = "
        SELECT coi.id, coi.source_uid, coi.message_id, coi.sender_email, coi.subject,
               coi.received_at, coi.customer_id, coi.customer_po_number,
               coi.attachment_original_name, coi.attachment_path, coi.status,
               coi.issue_count, coi.warning_count, coi.error_message,
               coi.sales_order_id, coi.imported_by, coi.reviewed_by, coi.reviewed_at,
                coi.created_at, coi.updated_at, coi.entered_delivery_date,
                coi.entry_saved_by, coi.entry_saved_at, coi.source_verified_at,
               c.name AS customer_name,
               c.customer_code,
               so.order_number,
               so.status AS sales_order_status,
               (SELECT COUNT(*) FROM customer_order_import_lines l WHERE l.import_id = coi.id) AS line_count
        FROM customer_order_imports coi
        LEFT JOIN customers c ON c.id = coi.customer_id
        LEFT JOIN sales_orders so ON so.id = coi.sales_order_id
        WHERE coi.status <> 'archived'
    ";
    $params = [];
    if ($status !== '') {
        $sql .= ' AND coi.status = ?';
        $params[] = $status;
    } elseif ($view === 'action') {
        $sql .= " AND coi.status IN ('received', 'for_encoding', 'draft_order', 'needs_customer_confirmation',
            'customer_confirmed', 'ready_to_create', 'needs_review', 'ready')";
    } elseif ($view === 'recent') {
        $sql .= " AND coi.status IN ('order_created', 'converted', 'duplicate', 'rejected')";
    }
    if ($search !== '') {
        $sql .= " AND (
            coi.customer_po_number LIKE ?
            OR coi.sender_email LIKE ?
            OR coi.subject LIKE ?
            OR c.name LIKE ?
        )";
        $term = '%' . $search . '%';
        array_push($params, $term, $term, $term, $term);
    }
    $sql .= " ORDER BY
        COALESCE(coi.received_at, coi.created_at) DESC,
        coi.id DESC
        LIMIT 100";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    Response::success($stmt->fetchAll(PDO::FETCH_ASSOC), 'Customer order inbox retrieved');
}

function handleCustomerOrderInboxPost(PDO $db, string $action, array $currentUser): void
{
    $userId = (int) $currentUser['user_id'];

    if ($action === 'sync') {
        $summary = hfSyncCustomerOrderMailbox($db, $userId);
        Response::success($summary, 'Customer order mailbox checked.');
    }

    if ($action === 'confirm') {
        $id = (int) getParam('id', 0);
        $acceptWarnings = filter_var(getParam('accept_warnings', false), FILTER_VALIDATE_BOOLEAN);
        $creditOverrideReason = trim((string) getParam('credit_override_reason', ''));
        $order = hfConvertCustomerOrderImport($db, $id, $userId, $acceptWarnings, $creditOverrideReason);
        Response::success($order, 'Sales Order created from the reviewed customer PO.', 201);
    }

    if ($action === 'save_details') {
        $importId = (int)getParam('id', 0);
        if ($importId <= 0) {
            Response::validationError(['id' => 'Choose an emailed customer PO.']);
        }
        $saved = hfSaveManualCustomerOrder($db, $importId, getParams(), $userId);
        Response::success($saved, 'Customer order details saved.');
    }

    if ($action === 'record_call') {
        $importId = (int)getParam('id', 0);
        if ($importId <= 0) {
            Response::validationError(['id' => 'Choose an emailed customer PO.']);
        }
        $call = hfRecordCustomerOrderCall($db, $importId, getParams(), $userId);
        Response::success($call, 'Phone confirmation recorded.');
    }

    if ($action === 'correct_encoding') {
        $importId = (int)getParam('id', 0);
        if ($importId <= 0) {
            Response::validationError(['id' => 'Choose an emailed customer PO.']);
        }
        $corrected = hfCorrectManualCustomerOrderEncoding(
            $db,
            $importId,
            (string)getParam('reason', ''),
            $userId
        );
        Response::success($corrected, 'The encoding was corrected against the original attachment.');
    }

    if ($action === 'reject') {
        $importId = (int)getParam('id', 0);
        if ($importId <= 0) {
            Response::validationError(['id' => 'Choose an emailed customer PO.']);
        }
        hfRejectCustomerOrderEmail($db, $importId, (string)getParam('reason', ''), $userId);
        Response::success(null, 'Customer PO email rejected.');
    }

    if ($action === 'adjust') {
        $importId = (int)getParam('id', 0);
        $lineId = (int)getParam('line_id', 0);
        if ($importId <= 0 || $lineId <= 0) {
            Response::validationError(['line_id' => 'Choose a PO line to adjust.']);
        }
        $adjustment = hfAdjustCustomerOrderImportLine($db, $importId, $lineId, getParams(), $userId);
        Response::success($adjustment, 'Customer-approved adjustment recorded. The original emailed PO was not changed.');
    }

    if ($action === 'approved_products') {
        Response::success(hfListApprovedCustomerOrderProducts($db), 'Approved customer products retrieved.');
    }

    Response::error('Invalid action', 400);
}
