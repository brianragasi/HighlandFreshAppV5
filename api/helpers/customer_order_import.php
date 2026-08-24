<?php

require_once __DIR__ . '/pack_uom.php';

function hfEnsureManualCustomerOrderSchema(PDO $db): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    $db->exec("ALTER TABLE customer_order_imports
        MODIFY COLUMN status ENUM(
            'received', 'for_encoding', 'draft_order', 'needs_customer_confirmation',
            'customer_confirmed', 'ready_to_create', 'order_created', 'needs_review',
            'ready', 'converted', 'duplicate', 'rejected', 'archived'
        ) NOT NULL DEFAULT 'received'");

    $importColumns = [];
    foreach ($db->query('SHOW COLUMNS FROM customer_order_imports')->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $importColumns[$column['Field']] = true;
    }
    foreach ([
        'email_body' => 'MEDIUMTEXT NULL',
        'entered_delivery_date' => 'DATE NULL',
        'entry_saved_by' => 'INT NULL',
        'entry_saved_at' => 'DATETIME NULL',
        'source_verified_by' => 'INT NULL',
        'source_verified_at' => 'DATETIME NULL',
    ] as $column => $definition) {
        if (!isset($importColumns[$column])) {
            $db->exec("ALTER TABLE customer_order_imports ADD COLUMN {$column} {$definition}");
        }
    }

    $lineColumns = [];
    foreach ($db->query('SHOW COLUMNS FROM customer_order_import_lines')->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $lineColumns[$column['Field']] = true;
    }
    foreach ([
        'original_customer_product_code' => 'VARCHAR(100) NULL',
        'original_description' => 'VARCHAR(255) NULL',
        'original_product_id' => 'INT NULL',
        'original_quantity_entered' => 'DECIMAL(12,3) NULL',
        'original_unit_entered' => 'VARCHAR(40) NULL',
        'original_po_unit_price' => 'DECIMAL(12,2) NULL',
    ] as $column => $definition) {
        if (!isset($lineColumns[$column])) {
            $db->exec("ALTER TABLE customer_order_import_lines ADD COLUMN {$column} {$definition}");
        }
    }

    $db->exec("UPDATE customer_order_imports
        SET status = 'received'
        WHERE customer_id IS NULL AND status = 'for_encoding'");

    $db->exec("CREATE TABLE IF NOT EXISTS customer_order_call_confirmations (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        import_id BIGINT UNSIGNED NOT NULL,
        change_summary TEXT NOT NULL,
        reason VARCHAR(500) NOT NULL,
        contact_name VARCHAR(150) NOT NULL,
        confirmation_method VARCHAR(30) NOT NULL DEFAULT 'phone_call',
        confirmed_at DATETIME NOT NULL,
        note TEXT NULL,
        approved_snapshot LONGTEXT NOT NULL,
        recorded_by INT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_customer_order_calls_import (import_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $ready = true;
}

function hfImportCustomerOrderAttachment(
    PDO $db,
    array $source,
    string $filename,
    string $content,
    ?int $userId
): array {
    hfEnsureManualCustomerOrderSchema($db);
    $sender = strtolower(trim((string) ($source['sender_email'] ?? '')));
    if (!filter_var($sender, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('A valid customer sender email is required.');
    }
    if (!hfIsKnownCustomerOrderSender($db, $sender)) {
        throw new InvalidArgumentException('Sender email is not registered to an active customer.');
    }
    if (strlen($content) === 0 || strlen($content) > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('The customer PO attachment must be between 1 byte and 5 MB.');
    }
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($extension, ['pdf', 'xlsx', 'csv', 'doc', 'docx', 'jpg', 'jpeg', 'png'], true)) {
        throw new InvalidArgumentException('Attach the customer purchase order as a PDF or another common document file.');
    }
    hfValidateCustomerOrderAttachmentContent($extension, $content);

    $hash = hash('sha256', $content);
    $sourceUid = trim((string) ($source['uid'] ?? ''));
    if ($sourceUid === '') {
        $sourceUid = 'upload:' . hash('sha256', $sender . ':' . $hash);
    } else {
        $sourceUid = 'mailbox:' . hash('sha256', $sourceUid);
    }

    $duplicateStmt = $db->prepare("
        SELECT id, status, sales_order_id
        FROM customer_order_imports
        WHERE source_uid = ?
           OR (sender_email = ? AND attachment_sha256 = ?)
        ORDER BY id ASC
        LIMIT 1
    ");
    $duplicateStmt->execute([$sourceUid, $sender, $hash]);
    $duplicate = $duplicateStmt->fetch(PDO::FETCH_ASSOC);
    if ($duplicate) {
        return [
            'duplicate' => true,
            'id' => (int) $duplicate['id'],
            'status' => $duplicate['status'],
            'sales_order_id' => $duplicate['sales_order_id']
                ? (int) $duplicate['sales_order_id']
                : null,
        ];
    }

    // The attachment is evidence from the customer. It is deliberately not parsed here:
    // PDF layouts and customer item names are not reliable enough to create order lines safely.
    $customer = hfFindImportedOrderCustomer($db, $sender, '');

    $storageDir = dirname(__DIR__, 2) . '/uploads/customer_orders';
    if (!is_dir($storageDir) && !mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
        throw new RuntimeException('Could not create the customer order storage folder.');
    }
    $storedName = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $absolutePath = $storageDir . '/' . $storedName;
    if (file_put_contents($absolutePath, $content, LOCK_EX) === false) {
        throw new RuntimeException('Could not save the customer PO attachment.');
    }
    $relativePath = 'uploads/customer_orders/' . $storedName;

    $db->beginTransaction();
    try {
        $initialStatus = $customer ? 'for_encoding' : 'received';
        $insert = $db->prepare("
            INSERT INTO customer_order_imports (
                source_uid, message_id, sender_email, subject, received_at, email_body,
                customer_id, customer_po_number, attachment_original_name,
                attachment_path, attachment_sha256, status, imported_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?)
        ");
        $insert->execute([
            $sourceUid,
            $source['message_id'] ?? null,
            $sender,
            trim((string) ($source['subject'] ?? '')) ?: null,
            $source['received_at'] ?? date('Y-m-d H:i:s'),
            trim((string) ($source['body'] ?? '')) ?: null,
            $customer['id'] ?? null,
            basename($filename),
            $relativePath,
            $hash,
            $initialStatus,
            $userId,
        ]);
        $importId = (int) $db->lastInsertId();
        $headerIssues = $customer ? null : 'Sender received, but no active customer matches this email. Choose the customer before saving the order details.';
        $update = $db->prepare("
            UPDATE customer_order_imports
            SET status = ?, issue_count = ?, warning_count = ?, error_message = ?
            WHERE id = ?
        ");
        $update->execute([$initialStatus, 0, 0, $headerIssues, $importId]);

        $db->commit();
        return [
            'duplicate' => false,
            'id' => $importId,
            'status' => $initialStatus,
            'issue_count' => 0,
            'warning_count' => 0,
        ];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        @unlink($absolutePath);
        throw $e;
    }
}

function hfValidateCustomerOrderAttachmentContent(string $extension, string $content): void
{
    $prefix = substr($content, 0, 1024);
    $valid = match ($extension) {
        'pdf' => strpos($prefix, '%PDF-') !== false,
        'png' => str_starts_with($content, "\x89PNG\r\n\x1a\n"),
        'jpg', 'jpeg' => str_starts_with($content, "\xFF\xD8\xFF"),
        'doc' => str_starts_with($content, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1"),
        'xlsx' => hfOfficeArchiveContains($content, 'xl/workbook.xml'),
        'docx' => hfOfficeArchiveContains($content, 'word/document.xml'),
        'csv' => !str_contains($content, "\0"),
        default => false,
    };

    if (!$valid) {
        throw new InvalidArgumentException('The attachment contents do not match its file type. Ask the customer to resend the original document.');
    }
}

function hfOfficeArchiveContains(string $content, string $requiredEntry): bool
{
    if (!str_starts_with($content, 'PK')) {
        return false;
    }

    $temp = tempnam(sys_get_temp_dir(), 'hf_po_');
    if ($temp === false || file_put_contents($temp, $content, LOCK_EX) === false) {
        if ($temp !== false) {
            @unlink($temp);
        }
        return false;
    }

    if (!class_exists('ZipArchive') && !class_exists('PharData')) {
        @unlink($temp);
        return false;
    }

    try {
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($temp) !== true) {
                return false;
            }
            try {
                return $zip->locateName('[Content_Types].xml', ZipArchive::FL_NODIR) !== false
                    && $zip->locateName($requiredEntry) !== false;
            } finally {
                $zip->close();
            }
        }

        $archive = new PharData($temp);
        return isset($archive['[Content_Types].xml']) && isset($archive[$requiredEntry]);
    } catch (Throwable $e) {
        return false;
    } finally {
        if (isset($archive)) {
            unset($archive);
        }
        @unlink($temp);
    }
}

function hfMailboxMessageSourceUid(array $source): string
{
    $uid = trim((string)($source['uid'] ?? ''));
    if ($uid === '') {
        $uid = trim((string)($source['message_id'] ?? ''));
    }
    if ($uid === '') {
        $uid = strtolower(trim((string)($source['sender_email'] ?? '')))
            . ':' . trim((string)($source['subject'] ?? ''))
            . ':' . trim((string)($source['body'] ?? ''));
    }
    return 'mailbox:' . hash('sha256', $uid);
}

function hfHasUsableCustomerOrderEmailBody(array $message): bool
{
    $body = trim((string)($message['body'] ?? ''));
    if (mb_strlen($body) < 10) {
        return false;
    }

    $text = trim((string)($message['subject'] ?? '')) . "\n" . $body;
    $hasExplicitOrderReference = (bool)preg_match(
        '/\b(?:purchase\s+order|customer\s+po|po\s*(?:number|no\.?|#)\s*[:#-]?\s*[A-Z0-9][A-Z0-9._\/-]*)\b/i',
        $text
    );
    $hasOrderFields = (bool)preg_match('/\b(?:product|item|quantity|qty|delivery\s+date)\b/i', $text);
    $hasOrderLine = (bool)preg_match(
        '/\b\d+(?:\.\d+)?\s*(?:(?:ka|nga|sa\s+ka)\s+)?(?:box(?:es)?|case(?:s)?|crate(?:s)?|bottle(?:s)?|block(?:s)?|pack(?:s)?|piece(?:s)?|pcs?|units?)\b/i',
        $body
    );

    return $hasOrderLine || ($hasExplicitOrderReference && $hasOrderFields && preg_match('/\d/', $body));
}

/**
 * A registered customer may write an informal order that cannot be parsed.
 * Keep any readable message for Sales to review instead of rejecting it early.
 */
function hfHasReviewableCustomerOrderEmailBody(array $message): bool
{
    $body = preg_replace('/\s+/u', ' ', trim((string)($message['body'] ?? ''))) ?? '';
    return mb_strlen($body) >= 3;
}

function hfImportCustomerOrderEmailBody(PDO $db, array $source, ?int $userId): array
{
    hfEnsureManualCustomerOrderSchema($db);
    $sender = strtolower(trim((string)($source['sender_email'] ?? '')));
    if (!filter_var($sender, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('A valid customer sender email is required.');
    }
    if (!hfIsKnownCustomerOrderSender($db, $sender)) {
        throw new InvalidArgumentException('Sender email is not registered to an active customer.');
    }
    $clearOrderDetails = hfHasUsableCustomerOrderEmailBody($source);
    $reviewableCustomerMessage = hfIsKnownCustomerOrderSender($db, $sender)
        && hfHasReviewableCustomerOrderEmailBody($source);
    if (!$clearOrderDetails && !$reviewableCustomerMessage) {
        throw new InvalidArgumentException('The email does not contain enough order information for Sales to review.');
    }

    $sourceUid = hfMailboxMessageSourceUid($source);
    $duplicateStmt = $db->prepare("SELECT id, status, sales_order_id
        FROM customer_order_imports WHERE source_uid = ? LIMIT 1");
    $duplicateStmt->execute([$sourceUid]);
    $duplicate = $duplicateStmt->fetch(PDO::FETCH_ASSOC);
    if ($duplicate) {
        return [
            'duplicate' => true,
            'id' => (int)$duplicate['id'],
            'status' => $duplicate['status'],
            'sales_order_id' => $duplicate['sales_order_id'] ? (int)$duplicate['sales_order_id'] : null,
        ];
    }

    $body = trim((string)$source['body']);
    $customer = hfFindImportedOrderCustomer($db, $sender, '');
    $initialStatus = $customer ? 'for_encoding' : 'received';
    $customerIssue = $customer
        ? ($clearOrderDetails
            ? null
            : 'Some order details are unclear. Enter what is written, then contact the customer for the missing information.')
        : 'Sender received, but no active customer matches this email. Choose the customer before saving the order details.';

    $insert = $db->prepare("INSERT INTO customer_order_imports (
            source_uid, message_id, sender_email, subject, received_at, email_body,
            customer_id, customer_po_number, attachment_original_name,
            attachment_path, attachment_sha256, status, issue_count,
            warning_count, error_message, imported_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?, '', ?, ?, 0, 0, ?, ?)");
    $insert->execute([
        $sourceUid,
        $source['message_id'] ?? null,
        $sender,
        trim((string)($source['subject'] ?? '')) ?: null,
        $source['received_at'] ?? date('Y-m-d H:i:s'),
        $body,
        $customer['id'] ?? null,
        'Order written in email',
        hash('sha256', $body),
        $initialStatus,
        $customerIssue,
        $userId,
    ]);

    return [
        'duplicate' => false,
        'id' => (int)$db->lastInsertId(),
        'status' => $initialStatus,
        'issue_count' => 0,
        'warning_count' => 0,
        'source_type' => 'email_body',
    ];
}

function hfImportCustomerOrderCsv(
    PDO $db,
    array $source,
    string $filename,
    string $content,
    ?int $userId
): array {
    return hfImportCustomerOrderAttachment($db, $source, $filename, $content, $userId);
}

function hfListCustomerOrderTemplateCustomers(PDO $db): array
{
    $stmt = $db->query("
        SELECT id, customer_code, name, customer_type, email, address
        FROM customers
        WHERE status = 'active'
          AND customer_type IN ('institutional', 'supermarket', 'school', 'feeding_program')
        ORDER BY name
    ");
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($customers as &$customer) {
        $customer['id'] = (int)$customer['id'];
        $customer['approved_product_count'] = count(hfApprovedCustomerProductCodes());
        $customer['template_ready'] = filter_var($customer['email'] ?? '', FILTER_VALIDATE_EMAIL) !== false;
        $customer['template_issue'] = $customer['template_ready']
            ? null
            : 'Add the customer email before preparing its PO form.';
    }
    unset($customer);
    return $customers;
}

function hfApprovedCustomerProductCodes(): array
{
    return [
        'FMK-1L',
        'FMK-500',
        'CHO-1L',
        'YOG-500',
        'YOG-STR',
        'CHE-250',
        'BUT-250',
        'CRM-1L',
    ];
}

function hfBuildCustomerOrderWorkbook(PDO $db, int $customerId, string $templatePath): array
{
    if (!is_file($templatePath)) {
        throw new RuntimeException('Customer Purchase Order workbook not found.');
    }

    $customerStmt = $db->prepare("
        SELECT id, customer_code, name, customer_type, email, address
        FROM customers
        WHERE id = ?
          AND status = 'active'
          AND customer_type IN ('institutional', 'supermarket', 'school', 'feeding_program')
        LIMIT 1
    ");
    $customerStmt->execute([$customerId]);
    $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);
    if (!$customer) {
        throw new InvalidArgumentException('Choose an active institutional customer.');
    }
    if (!filter_var($customer['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Add a valid email to this customer before preparing its PO form.');
    }

    $base = tempnam(sys_get_temp_dir(), 'hf_customer_po_form_');
    if ($base === false) {
        throw new RuntimeException('Could not prepare the customer Purchase Order form.');
    }
    @unlink($base);
    $path = $base . '.xlsx';
    if (!copy($templatePath, $path)) {
        throw new RuntimeException('Could not prepare the customer Purchase Order form.');
    }

    try {
        $archive = new PharData($path);
        $sheetPaths = hfXlsxWorkbookSheetPaths($archive);
        if (empty($sheetPaths['Purchase Order']) || empty($sheetPaths['System Data'])) {
            throw new RuntimeException('The customer Purchase Order workbook is incomplete.');
        }

        hfXlsxSetCellValues($archive, $sheetPaths['Purchase Order'], [
            'B4' => '',
            'B5' => '',
            'B6' => trim((string)$customer['name']),
            'E4' => trim((string)($customer['address'] ?? '')),
            'A29' => 'Prepared by ' . trim((string)$customer['name']) . ' Purchasing',
        ]);
        hfXlsxSetCellValues($archive, $sheetPaths['System Data'], [
            'O2' => trim((string)($customer['customer_code'] ?? '')),
        ]);
        unset($archive);

        $content = file_get_contents($path);
        if ($content === false || $content === '') {
            throw new RuntimeException('Could not finish the customer Purchase Order form.');
        }
        $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '_', trim((string)$customer['name']));
        $safeName = trim((string)$safeName, '_') ?: 'Customer';
        return [
            'filename' => 'HighlandFresh_' . $safeName . '_Purchase_Order.xlsx',
            'content' => $content,
            'customer' => $customer,
        ];
    } catch (UnexpectedValueException $e) {
        throw new RuntimeException('Could not prepare the customer Purchase Order form.');
    } finally {
        if (isset($archive)) {
            unset($archive);
        }
        @unlink($path);
    }
}

function hfReadCustomerOrderWorkbook(string $content): array
{
    if (!class_exists('PharData')) {
        throw new RuntimeException('Excel Purchase Order reading is not available on this server.');
    }

    $base = tempnam(sys_get_temp_dir(), 'hf_customer_po_');
    if ($base === false) {
        throw new RuntimeException('Could not prepare the Excel Purchase Order for reading.');
    }
    @unlink($base);
    $path = $base . '.xlsx';
    if (file_put_contents($path, $content, LOCK_EX) === false) {
        throw new RuntimeException('Could not prepare the Excel Purchase Order for reading.');
    }

    try {
        $archive = new PharData($path);
        $sharedStrings = hfXlsxSharedStrings($archive);
        $sheetPaths = hfXlsxWorkbookSheetPaths($archive);

        if (empty($sheetPaths['Purchase Order'])) {
            throw new InvalidArgumentException('Use the official Highland Fresh Purchase Order workbook.');
        }

        $purchaseOrder = hfXlsxSheetCells($archive, $sheetPaths['Purchase Order'], $sharedStrings);
        $systemData = !empty($sheetPaths['System Data'])
            ? hfXlsxSheetCells($archive, $sheetPaths['System Data'], $sharedStrings)
            : [];

        $templateVersion = trim((string)($systemData['O3'] ?? ''));
        if ($templateVersion !== 'HF-CPO-2') {
            throw new InvalidArgumentException('Use the latest Highland Fresh Excel Purchase Order workbook.');
        }

        $poNumber = trim((string)($purchaseOrder['B4'] ?? ''));
        if ($poNumber === '') {
            throw new InvalidArgumentException('Enter the PO Number in the Purchase Order workbook.');
        }
        $deliveryDate = hfXlsxDateValue($purchaseOrder['B5'] ?? null);
        if ($deliveryDate === null) {
            throw new InvalidArgumentException('Choose a valid Requested Delivery Date in the Purchase Order workbook.');
        }

        $customerCode = trim((string)($systemData['O2'] ?? ''));
        $customerName = trim((string)($purchaseOrder['B6'] ?? ''));
        $deliveryAddress = trim((string)($purchaseOrder['E4'] ?? ''));
        $rows = [];
        for ($sheetRow = 11; $sheetRow <= 25; $sheetRow++) {
            $selection = trim((string)($purchaseOrder['A' . $sheetRow] ?? ''));
            $quantity = trim((string)($purchaseOrder['B' . $sheetRow] ?? ''));
            $unit = trim((string)($purchaseOrder['C' . $sheetRow] ?? ''));
            $remarks = trim((string)($purchaseOrder['G' . $sheetRow] ?? ''));
            if ($selection === '' && $quantity === '' && $unit === '' && $remarks === '') {
                continue;
            }

            $productCode = '';
            $description = $selection;
            if (preg_match('/^([A-Z0-9][A-Z0-9_-]*)\s+[—-]\s+(.+)$/u', $selection, $match)) {
                $productCode = trim($match[1]);
                $description = trim($match[2]);
            } elseif (preg_match('/^([A-Z0-9][A-Z0-9_-]*)/i', $selection, $match)) {
                $productCode = trim($match[1]);
            }

            $rows[] = [
                'customer_po_number' => $poNumber,
                'customer_code' => $customerCode,
                'customer_name' => $customerName,
                'delivery_date' => $deliveryDate,
                'delivery_address' => $deliveryAddress,
                'product_code' => $productCode,
                'description' => $description,
                'quantity' => $quantity,
                'unit' => $unit,
                'remarks' => $remarks,
                '_source_row' => $sheetRow,
            ];
        }

        if (count($rows) > 500) {
            throw new InvalidArgumentException('A customer PO may contain at most 500 lines.');
        }
        return $rows;
    } catch (UnexpectedValueException $e) {
        throw new InvalidArgumentException('The Excel Purchase Order file is damaged or is not a valid .xlsx workbook.');
    } finally {
        if (isset($archive)) {
            unset($archive);
        }
        @unlink($path);
    }
}

/**
 * Build a small read-only preview of the first useful worksheet.
 * This never creates order lines; it only helps Sales read the attachment.
 */
function hfPreviewCustomerOrderWorkbook(string $content): array
{
    if (!class_exists('PharData')) {
        throw new RuntimeException('Excel preview is not available on this server.');
    }

    $base = tempnam(sys_get_temp_dir(), 'hf_po_preview_');
    if ($base === false) {
        throw new RuntimeException('Could not prepare the Excel attachment preview.');
    }
    @unlink($base);
    $path = $base . '.xlsx';
    if (file_put_contents($path, $content, LOCK_EX) === false) {
        throw new RuntimeException('Could not prepare the Excel attachment preview.');
    }

    try {
        $archive = new PharData($path);
        $sharedStrings = hfXlsxSharedStrings($archive);
        $sheetPaths = hfXlsxWorkbookSheetPaths($archive);
        if (!$sheetPaths) {
            throw new InvalidArgumentException('The Excel attachment has no readable worksheets.');
        }

        $sheetName = isset($sheetPaths['Purchase Order'])
            ? 'Purchase Order'
            : (string)array_key_first($sheetPaths);
        $cells = hfXlsxSheetCells($archive, $sheetPaths[$sheetName], $sharedStrings);
        $previewRows = hfBuildSpreadsheetPreviewRows($cells, 80, 14);

        $poNumber = null;
        $deliveryDate = null;
        if ($sheetName === 'Purchase Order') {
            $poNumber = trim((string)($cells['B4'] ?? '')) ?: null;
            $deliveryDate = hfXlsxDateValue($cells['B5'] ?? null);
        }
        $labels = hfFindSpreadsheetHeaderValues($cells);
        $poNumber = $poNumber ?: ($labels['po_number'] ?? null);
        $deliveryDate = $deliveryDate ?: ($labels['delivery_date'] ?? null);

        return [
            'kind' => 'spreadsheet',
            'sheet_name' => $sheetName,
            'rows' => $previewRows,
            'suggested_po_number' => $poNumber,
            'suggested_delivery_date' => $deliveryDate,
        ];
    } catch (UnexpectedValueException $e) {
        throw new InvalidArgumentException('The Excel attachment is damaged or unreadable.');
    } finally {
        if (isset($archive)) {
            unset($archive);
        }
        @unlink($path);
    }
}

function hfPreviewCustomerOrderCsv(string $content): array
{
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
    $lines = preg_split('/\r\n|\n|\r/', $content) ?: [];
    $firstLine = (string)($lines[0] ?? '');
    $delimiters = [',' => substr_count($firstLine, ','), "\t" => substr_count($firstLine, "\t"), ';' => substr_count($firstLine, ';')];
    arsort($delimiters);
    $delimiter = (string)array_key_first($delimiters);
    $rows = [];
    foreach (array_slice($lines, 0, 80) as $index => $line) {
        if ($line === '') {
            continue;
        }
        $cells = array_slice(str_getcsv($line, $delimiter), 0, 14);
        $rows[] = ['row_number' => $index + 1, 'cells' => array_map(static fn($value) => mb_substr(trim((string)$value), 0, 300), $cells)];
    }

    $flat = [];
    foreach ($rows as $row) {
        foreach ($row['cells'] as $column => $value) {
            $flat[hfXlsxColumnLetters($column + 1) . $row['row_number']] = $value;
        }
    }
    $labels = hfFindSpreadsheetHeaderValues($flat);
    return [
        'kind' => 'spreadsheet',
        'sheet_name' => 'CSV attachment',
        'rows' => $rows,
        'suggested_po_number' => $labels['po_number'] ?? null,
        'suggested_delivery_date' => $labels['delivery_date'] ?? null,
    ];
}

/**
 * Read a Highland Fresh workbook as a comparison reference only. The returned
 * values never create order lines automatically.
 */
function hfTrustedCustomerOrderReference(array $import): ?array
{
    $relativePath = trim((string)($import['attachment_path'] ?? ''));
    $filename = trim((string)($import['attachment_original_name'] ?? ''));
    if ($relativePath === '' || strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'xlsx') {
        return null;
    }

    $root = realpath(dirname(__DIR__, 2));
    $allowed = realpath(dirname(__DIR__, 2) . '/uploads/customer_orders');
    $path = realpath(dirname(__DIR__, 2) . '/' . ltrim(str_replace('\\', '/', $relativePath), '/'));
    if ($root === false || $allowed === false || $path === false || !is_file($path)) {
        return null;
    }
    $allowedPrefix = rtrim(str_replace('\\', '/', $allowed), '/') . '/';
    if (!str_starts_with(str_replace('\\', '/', $path), $allowedPrefix)) {
        return null;
    }

    try {
        $rows = hfReadCustomerOrderWorkbook((string)file_get_contents($path));
    } catch (Throwable $e) {
        return null;
    }
    if (!$rows) {
        return null;
    }

    return [
        'customer_po_number' => (string)($rows[0]['customer_po_number'] ?? ''),
        'delivery_date' => $rows[0]['delivery_date'] ?? null,
        'lines' => array_map(static fn(array $row): array => [
            'row_number' => (int)($row['_source_row'] ?? 0),
            'product_code' => strtoupper(trim((string)($row['product_code'] ?? ''))),
            'description' => trim((string)($row['description'] ?? '')),
            'quantity' => (int)($row['quantity'] ?? 0),
            'unit' => strtolower(trim((string)($row['unit'] ?? ''))),
        ], $rows),
    ];
}

/**
 * Build a narrow comparison reference from a clearly written email order.
 * This does not try to understand arbitrary prose. It only returns a reference
 * when one quantity/unit and one best matching active product are unambiguous.
 */
function hfTrustedCustomerOrderEmailReference(PDO $db, array $import): ?array
{
    $subject = trim((string)($import['subject'] ?? ''));
    $body = trim((string)($import['email_body'] ?? ''));
    $text = trim($subject . "\n" . $body);
    if ($text === '') {
        return null;
    }

    $quantityPattern = '/\b(\d+(?:\.\d+)?)\s*(?:(?:sa\s+ka|ka|nga)\s+)?'
        . '(box(?:es)?|case(?:s)?|crate(?:s)?|bottle(?:s)?|block(?:s)?|piece(?:s)?|pcs?|pack(?:s)?|packet(?:s)?|cup(?:s)?|tub(?:s)?|jar(?:s)?)\b/i';
    preg_match_all($quantityPattern, $text, $quantityMatches, PREG_SET_ORDER);
    if (count($quantityMatches) !== 1) {
        return null;
    }

    $quantity = (float)$quantityMatches[0][1];
    $unit = hfCanonicalCustomerOrderUnit((string)$quantityMatches[0][2]);
    if ($quantity <= 0 || $unit === '') {
        return null;
    }

    $normalize = static function (string $value): string {
        $value = preg_replace('/([a-z])([A-Z])/', '$1 $2', $value) ?? $value;
        $value = mb_strtolower($value);
        return trim(preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? '');
    };
    $sourceCompact = str_replace(' ', '', $normalize($text));
    if ($sourceCompact === '') {
        return null;
    }

    $products = $db->query("SELECT id, product_code, product_name, variant, unit_size,
            unit_measure, base_unit, box_unit, pieces_per_box
        FROM products
        WHERE is_active = 1
          AND product_code IS NOT NULL
          AND TRIM(product_code) <> ''")->fetchAll(PDO::FETCH_ASSOC);

    $ranked = [];
    foreach ($products as $product) {
        $name = trim((string)($product['product_name'] ?? ''));
        $variant = trim((string)($product['variant'] ?? ''));
        $label = $normalize($name . ' ' . $variant);
        $tokens = array_values(array_unique(array_filter(
            explode(' ', $label),
            static fn(string $token): bool => strlen($token) >= 3 && !is_numeric($token)
        )));
        $score = 0;
        foreach ($tokens as $token) {
            if (str_contains($sourceCompact, $token)) {
                $score++;
            }
        }
        if ($score > 0) {
            $ranked[] = ['score' => $score, 'product' => $product];
        }
    }
    if (!$ranked) {
        return null;
    }
    usort($ranked, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
    if (isset($ranked[1]) && $ranked[0]['score'] === $ranked[1]['score']) {
        return null;
    }

    $product = $ranked[0]['product'];
    $description = trim((string)$product['product_name']);
    if (trim((string)($product['variant'] ?? '')) !== '') {
        $description .= ' - ' . trim((string)$product['variant']);
    }
    return [
        'source' => 'email_text',
        'customer_po_number' => '',
        'delivery_date' => null,
        'lines' => [[
            'row_number' => 1,
            'product_code' => strtoupper(trim((string)$product['product_code'])),
            'description' => $description,
            'quantity' => $quantity,
            'unit' => $unit,
        ]],
    ];
}

function hfTrustedCustomerOrderReferenceForImport(PDO $db, array $import): ?array
{
    return hfTrustedCustomerOrderReference($import)
        ?? hfTrustedCustomerOrderEmailReference($db, $import);
}

function hfCanonicalCustomerOrderUnit(string $unit): string
{
    $unit = strtolower(trim($unit));
    if (in_array($unit, ['box', 'boxes', 'case', 'cases', 'crate', 'crates'], true)) {
        return 'box';
    }
    $map = [
        'pc' => 'piece', 'pcs' => 'piece', 'pieces' => 'piece',
        'bottles' => 'bottle', 'packs' => 'pack', 'packets' => 'packet',
        'blocks' => 'block', 'cups' => 'cup', 'tubs' => 'tub', 'jars' => 'jar',
    ];
    return $map[$unit] ?? rtrim($unit, 's');
}

function hfTrustedCustomerOrderErrors(
    array $reference,
    string $customerPoNumber,
    ?string $deliveryDate,
    array $submittedLines
): array {
    $errors = [];
    if (!empty($reference['customer_po_number'])
        && strcasecmp(trim((string)$reference['customer_po_number']), trim($customerPoNumber)) !== 0) {
        $errors[] = "The original customer request shows PO number {$reference['customer_po_number']}.";
    }
    if (!empty($reference['delivery_date']) && $reference['delivery_date'] !== $deliveryDate) {
        $errors[] = "The original customer request shows requested delivery date {$reference['delivery_date']}.";
    }

    $actual = [];
    foreach ($submittedLines as $line) {
        $code = strtoupper(trim((string)($line['product_code'] ?? $line['customer_product_code'] ?? '')));
        $quantity = (int)($line['quantity'] ?? $line['quantity_entered'] ?? 0);
        $unit = hfCanonicalCustomerOrderUnit((string)($line['unit'] ?? $line['unit_entered'] ?? ''));
        if ($code !== '' || $quantity > 0 || $unit !== '') {
            $actual[$code] = ['quantity' => $quantity, 'unit' => $unit];
        }
    }

    $expectedCodes = [];
    foreach (($reference['lines'] ?? []) as $line) {
        $code = strtoupper(trim((string)($line['product_code'] ?? '')));
        $expectedCodes[$code] = true;
        $description = trim((string)($line['description'] ?? $code));
        if (!isset($actual[$code])) {
            $errors[] = "The original customer request includes {$description}, but it is missing from the entry.";
            continue;
        }
        $expectedQuantity = (int)($line['quantity'] ?? 0);
        $expectedUnit = hfCanonicalCustomerOrderUnit((string)($line['unit'] ?? ''));
        if ($actual[$code]['quantity'] !== $expectedQuantity || $actual[$code]['unit'] !== $expectedUnit) {
            $errors[] = "The original customer request shows {$description}: {$expectedQuantity} {$expectedUnit}.";
        }
    }
    foreach ($actual as $code => $line) {
        if (!isset($expectedCodes[$code])) {
            $errors[] = "{$code} is not listed in the original customer request.";
        }
    }
    return $errors;
}

function hfBuildSpreadsheetPreviewRows(array $cells, int $rowLimit, int $columnLimit): array
{
    $matrix = [];
    foreach ($cells as $reference => $value) {
        if (!preg_match('/^([A-Z]+)(\d+)$/', strtoupper((string)$reference), $match)) {
            continue;
        }
        $row = (int)$match[2];
        $column = hfXlsxColumnNumber($match[1]);
        if ($row < 1 || $row > $rowLimit || $column < 1 || $column > $columnLimit) {
            continue;
        }
        $matrix[$row][$column] = mb_substr(trim((string)$value), 0, 300);
    }
    if (!$matrix) {
        return [];
    }

    ksort($matrix);
    $maxColumn = 1;
    foreach ($matrix as $columns) {
        $maxColumn = max($maxColumn, max(array_keys($columns)));
    }
    $rows = [];
    foreach ($matrix as $rowNumber => $columns) {
        $values = [];
        $hasValue = false;
        for ($column = 1; $column <= $maxColumn; $column++) {
            $value = (string)($columns[$column] ?? '');
            $values[] = $value;
            $hasValue = $hasValue || $value !== '';
        }
        if ($hasValue) {
            $rows[] = ['row_number' => $rowNumber, 'cells' => $values];
        }
    }
    return $rows;
}

function hfFindSpreadsheetHeaderValues(array $cells): array
{
    $result = [];
    foreach ($cells as $reference => $value) {
        $label = strtolower(trim((string)$value));
        if ($label === '') {
            continue;
        }
        $nextReference = hfXlsxNextCellReference((string)$reference);
        $nextValue = $nextReference ? trim((string)($cells[$nextReference] ?? '')) : '';
        if ($nextValue === '') {
            continue;
        }
        if (!isset($result['po_number']) && preg_match('/^(?:customer\s+)?po\s*(?:number|no\.?|#)?$/i', $label)) {
            $result['po_number'] = mb_substr($nextValue, 0, 80);
        }
        if (!isset($result['delivery_date']) && preg_match('/^(?:requested\s+)?delivery\s+date$/i', $label)) {
            $result['delivery_date'] = hfXlsxDateValue($nextValue);
        }
    }
    return $result;
}

function hfXlsxNextCellReference(string $reference): ?string
{
    if (!preg_match('/^([A-Z]+)(\d+)$/', strtoupper($reference), $match)) {
        return null;
    }
    return hfXlsxColumnLetters(hfXlsxColumnNumber($match[1]) + 1) . $match[2];
}

function hfXlsxColumnNumber(string $letters): int
{
    $number = 0;
    foreach (str_split(strtoupper($letters)) as $letter) {
        $number = $number * 26 + (ord($letter) - 64);
    }
    return $number;
}

function hfXlsxColumnLetters(int $number): string
{
    $letters = '';
    while ($number > 0) {
        $number--;
        $letters = chr(65 + ($number % 26)) . $letters;
        $number = intdiv($number, 26);
    }
    return $letters;
}

function hfXlsxArchiveXml(PharData $archive, string $entry): DOMDocument
{
    if (!isset($archive[$entry])) {
        throw new InvalidArgumentException('The Excel Purchase Order file is incomplete.');
    }
    $document = new DOMDocument();
    $loaded = $document->loadXML(
        $archive[$entry]->getContent(),
        LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_NOERROR | LIBXML_NOWARNING
    );
    if (!$loaded) {
        throw new InvalidArgumentException('The Excel Purchase Order file contains unreadable data.');
    }
    return $document;
}

function hfXlsxWorkbookSheetPaths(PharData $archive): array
{
    $workbookXml = hfXlsxArchiveXml($archive, 'xl/workbook.xml');
    $relationshipXml = hfXlsxArchiveXml($archive, 'xl/_rels/workbook.xml.rels');
    $workbookXpath = new DOMXPath($workbookXml);
    $workbookXpath->registerNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $relationshipXpath = new DOMXPath($relationshipXml);
    $relationshipXpath->registerNamespace('p', 'http://schemas.openxmlformats.org/package/2006/relationships');

    $targets = [];
    foreach ($relationshipXpath->query('//p:Relationship') as $relationship) {
        $targets[$relationship->getAttribute('Id')] = $relationship->getAttribute('Target');
    }

    $sheetPaths = [];
    foreach ($workbookXpath->query('//m:sheets/m:sheet') as $sheet) {
        $name = $sheet->getAttribute('name');
        $relationshipId = $sheet->getAttributeNS(
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
            'id'
        );
        if (!isset($targets[$relationshipId])) {
            continue;
        }
        $target = ltrim(str_replace('\\', '/', $targets[$relationshipId]), '/');
        $sheetPaths[$name] = str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
    }
    return $sheetPaths;
}

function hfXlsxSetCellValues(PharData $archive, string $entry, array $values): void
{
    $document = hfXlsxArchiveXml($archive, $entry);
    $xpath = new DOMXPath($document);
    $namespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    $xpath->registerNamespace('m', $namespace);

    foreach ($values as $reference => $value) {
        $cell = $xpath->query('//m:c[@r="' . strtoupper((string)$reference) . '"]')->item(0);
        if (!$cell instanceof DOMElement) {
            throw new RuntimeException("The Purchase Order workbook is missing cell {$reference}.");
        }
        while ($cell->firstChild) {
            $cell->removeChild($cell->firstChild);
        }
        $cell->setAttribute('t', 'str');
        $valueNode = $document->createElementNS($namespace, 'x:v');
        $valueNode->appendChild($document->createTextNode((string)$value));
        $cell->appendChild($valueNode);
    }

    $archive[$entry] = $document->saveXML();
}

function hfXlsxSharedStrings(PharData $archive): array
{
    if (!isset($archive['xl/sharedStrings.xml'])) {
        return [];
    }
    $document = hfXlsxArchiveXml($archive, 'xl/sharedStrings.xml');
    $xpath = new DOMXPath($document);
    $xpath->registerNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $strings = [];
    foreach ($xpath->query('//m:si') as $item) {
        $text = '';
        foreach ($xpath->query('.//m:t', $item) as $textNode) {
            $text .= $textNode->textContent;
        }
        $strings[] = $text;
    }
    return $strings;
}

function hfXlsxSheetCells(PharData $archive, string $entry, array $sharedStrings): array
{
    $document = hfXlsxArchiveXml($archive, $entry);
    $xpath = new DOMXPath($document);
    $xpath->registerNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $cells = [];
    foreach ($xpath->query('//m:sheetData/m:row/m:c') as $cell) {
        $reference = strtoupper($cell->getAttribute('r'));
        $type = $cell->getAttribute('t');
        $valueNode = $xpath->query('./m:v', $cell)->item(0);
        if ($type === 'inlineStr') {
            $parts = [];
            foreach ($xpath->query('.//m:is//m:t', $cell) as $textNode) {
                $parts[] = $textNode->textContent;
            }
            $value = implode('', $parts);
        } elseif ($type === 's') {
            $index = $valueNode ? (int)$valueNode->textContent : -1;
            $value = $sharedStrings[$index] ?? '';
        } elseif ($type === 'b') {
            $value = $valueNode && $valueNode->textContent === '1';
        } else {
            $raw = $valueNode ? $valueNode->textContent : '';
            $value = is_numeric($raw) ? (float)$raw : $raw;
        }
        $cells[$reference] = $value;
    }
    return $cells;
}

function hfXlsxDateValue($value): ?string
{
    if (is_numeric($value)) {
        $base = new DateTimeImmutable('1899-12-30', new DateTimeZone('UTC'));
        return $base->modify('+' . (int)floor((float)$value) . ' days')->format('Y-m-d');
    }
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }
    $timestamp = strtotime($value);
    return $timestamp === false ? null : date('Y-m-d', $timestamp);
}

function hfReadCustomerOrderCsv(string $content): array
{
    $handle = fopen('php://temp', 'w+');
    fwrite($handle, preg_replace('/^\xEF\xBB\xBF/', '', $content));
    rewind($handle);
    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        return [];
    }

    $aliases = [
        'po_number' => 'customer_po_number',
        'customer_po' => 'customer_po_number',
        'sku' => 'product_code',
        'item_code' => 'product_code',
        'qty' => 'quantity',
        'uom' => 'unit',
        'price' => 'unit_price',
        'unit_price_per_base_unit' => 'unit_price',
    ];
    $keys = array_map(function ($value) use ($aliases) {
        $key = strtolower(trim((string) $value));
        $key = preg_replace('/[^a-z0-9]+/', '_', $key);
        $key = trim($key, '_');
        return $aliases[$key] ?? $key;
    }, $header);

    foreach (['customer_po_number', 'product_code', 'quantity', 'unit'] as $required) {
        if (!in_array($required, $keys, true)) {
            fclose($handle);
            throw new InvalidArgumentException("The CSV is missing the {$required} column.");
        }
    }

    $rows = [];
    while (($values = fgetcsv($handle)) !== false) {
        if (count(array_filter($values, fn($v) => trim((string) $v) !== '')) === 0) {
            continue;
        }
        $values = array_pad($values, count($keys), '');
        $row = array_combine($keys, array_slice($values, 0, count($keys)));
        if ($row !== false) {
            $rows[] = array_map(
                fn($value) => is_string($value) ? trim($value) : $value,
                $row
            );
        }
        if (count($rows) > 500) {
            fclose($handle);
            throw new InvalidArgumentException('A customer PO may contain at most 500 lines.');
        }
    }
    fclose($handle);
    return $rows;
}

function hfFindImportedOrderCustomer(PDO $db, string $sender, string $customerCode): ?array
{
    $stmt = $db->prepare("
        SELECT *
        FROM customers
        WHERE status = 'active'
          AND LOWER(email) = LOWER(?)
          AND (? = '' OR customer_code = ?)
        ORDER BY id
        LIMIT 2
    ");
    $stmt->execute([$sender, $customerCode, $customerCode]);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // An email address must identify exactly one active customer. Duplicate
    // addresses are treated as ambiguous instead of guessing the customer.
    return count($customers) === 1 ? $customers[0] : null;
}

function hfCustomerOrderParseQuantity($value): ?int
{
    $text = trim((string)$value);
    if (preg_match('/^\d+(?:\.0+)?$/D', $text) !== 1) {
        return null;
    }
    $quantity = (int)$text;
    return $quantity >= 1 && $quantity <= 1000000 ? $quantity : null;
}

function hfCustomerOrderParseMoney($value): array
{
    $text = trim((string)$value);
    if ($text === '') {
        return ['valid' => true, 'value' => null];
    }
    if (preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $text) !== 1) {
        return ['valid' => false, 'value' => null];
    }
    $amount = (float)$text;
    if ($amount > 99999999.99) {
        return ['valid' => false, 'value' => null];
    }
    return ['valid' => true, 'value' => $amount];
}

function hfMatchCustomerOrderLine(PDO $db, array $row): array
{
    $code = trim((string) ($row['product_code'] ?? ''));
    $quantityEntered = hfCustomerOrderParseQuantity($row['quantity'] ?? null);
    $unitEntered = strtolower(trim((string) ($row['unit'] ?? '')));
    $priceUnit = strtolower(trim((string)($row['price_unit'] ?? $unitEntered)));
    $parsedPrice = hfCustomerOrderParseMoney($row['unit_price'] ?? null);
    $enteredPoPrice = $parsedPrice['value'];
    $priceInvalid = !$parsedPrice['valid'];
    $poPrice = $enteredPoPrice;

    $productStmt = $db->prepare("
        SELECT id, product_code, product_name, variant, selling_price,
               COALESCE(base_unit, 'piece') AS base_unit,
               COALESCE(box_unit, 'box') AS box_unit,
               COALESCE(NULLIF(pieces_per_box, 0), 1) AS pieces_per_box
        FROM products
        WHERE is_active = 1
          AND UPPER(product_code) = UPPER(?)
        LIMIT 1
    ");
    $productStmt->execute([$code]);
    $product = $productStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $issues = [];
    $warnings = [];
    $quantityBase = null;
    $boxes = 0;
    $pieces = 0;
    if (!$product) {
        $issues[] = "Unknown or inactive Finished Goods product code '{$code}'.";
    }
    if ($quantityEntered === null) {
        $issues[] = 'Quantity must be a positive whole number no greater than 1,000,000.';
    }

    if ($product && !$issues) {
        $quantityEntered = (int) $quantityEntered;
        $baseAliases = hfUnitAliases($product['base_unit']);
        $boxAliases = array_unique(array_merge(
            hfUnitAliases($product['box_unit']),
            ['box', 'boxes', 'case', 'cases', 'crate', 'crates']
        ));
        if (in_array($unitEntered, $baseAliases, true)) {
            $quantityBase = $quantityEntered;
            $pieces = $quantityEntered;
        } elseif (in_array($unitEntered, $boxAliases, true)) {
            $boxes = $quantityEntered;
            $quantityBase = $quantityEntered * (int) $product['pieces_per_box'];
        } else {
            $issues[] = "Unit '{$unitEntered}' does not match {$product['base_unit']} or {$product['box_unit']}.";
        }

        if ($enteredPoPrice !== null) {
            if (in_array($priceUnit, $boxAliases, true)) {
                $poPrice = $enteredPoPrice / (int)$product['pieces_per_box'];
                $priceUnit = strtolower((string)$product['box_unit']);
            } elseif (in_array($priceUnit, $baseAliases, true)) {
                $poPrice = $enteredPoPrice;
                $priceUnit = strtolower((string)$product['base_unit']);
            } else {
                $issues[] = "Price unit '{$priceUnit}' does not match {$product['base_unit']} or {$product['box_unit']}.";
            }
        }
    }

    $systemPrice = $product ? (float) $product['selling_price'] : null;
    if ($priceInvalid) {
        $issues[] = 'Customer price must be a normal amount up to 99,999,999.99 with no more than two decimal places.';
        $poPrice = null;
    } elseif ($product && $poPrice !== null && abs((float) $poPrice - $systemPrice) > 0.009) {
        $warnings[] = sprintf(
            'Customer price %s per %s equals %.2f per %s; the current Highland Fresh price is %.2f per %s.',
            number_format((float)$enteredPoPrice, 2),
            $priceUnit,
            (float)$poPrice,
            strtolower((string)$product['base_unit']),
            $systemPrice,
            strtolower((string)$product['base_unit'])
        );
    }

    $raw = $row;
    $raw['price_unit'] = $priceUnit;
    $raw['entered_unit_price'] = $enteredPoPrice;

    return [
        'customer_product_code' => $code,
        'description' => trim((string) ($row['description'] ?? ($product['product_name'] ?? ''))),
        'product_id' => $product ? (int) $product['id'] : null,
        'quantity_entered' => $quantityEntered,
        'unit_entered' => $unitEntered,
        'quantity_base' => $quantityBase,
        'quantity_boxes' => $boxes,
        'quantity_pieces' => $pieces,
        'po_unit_price' => $poPrice === null ? null : (float) $poPrice,
        'system_unit_price' => $systemPrice,
        'line_status' => $issues ? 'blocked' : ($warnings ? 'warning' : 'matched'),
        'issue_text' => implode(' ', array_merge($issues, $warnings)) ?: null,
        'raw' => $raw,
    ];
}

function hfUnitAliases(string $unit): array
{
    $unit = strtolower(trim($unit));
    $aliases = [$unit];
    if ($unit !== '') {
        $aliases[] = rtrim($unit, 's');
        $aliases[] = rtrim($unit, 's') . 's';
    }
    $common = [
        'piece' => ['pc', 'pcs', 'piece', 'pieces'],
        'bottle' => ['bottle', 'bottles'],
        'pack' => ['pack', 'packs', 'packet', 'packets'],
        'block' => ['block', 'blocks'],
        'box' => ['box', 'boxes'],
    ];
    foreach ($common as $canonical => $values) {
        if (in_array($unit, $values, true) || $unit === $canonical) {
            $aliases = array_merge($aliases, $values);
        }
    }
    return array_values(array_unique(array_filter($aliases)));
}

function hfImportedOrderAvailableStock(PDO $db, array $productIds): array
{
    if (!$productIds) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $stmt = $db->prepare("
        SELECT p.id,
               GREATEST(
                   0,
                   COALESCE(stock.on_hand, 0) - COALESCE(reserved.qty, 0)
               ) AS available_qty
        FROM products p
        LEFT JOIN (
            SELECT product_id, SUM(GREATEST(0, quantity_available)) AS on_hand
            FROM finished_goods_inventory
            WHERE product_id IN ($placeholders)
              AND status = 'available'
              AND (expiry_date IS NULL OR expiry_date > CURDATE())
            GROUP BY product_id
        ) stock ON stock.product_id = p.id
        LEFT JOIN (
            SELECT soi.product_id, SUM(soi.quantity_ordered) AS qty
            FROM sales_order_items soi
            JOIN sales_orders so ON so.id = soi.order_id
            WHERE soi.product_id IN ($placeholders)
              AND so.status IN ('pending', 'approved', 'picking', 'preparing')
            GROUP BY soi.product_id
        ) reserved ON reserved.product_id = p.id
        WHERE p.id IN ($placeholders)
    ");
    $stmt->execute(array_merge($productIds, $productIds, $productIds));
    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $map[(int) $row['id']] = (int) $row['available_qty'];
    }
    return $map;
}

function hfListApprovedCustomerOrderProducts(PDO $db): array
{
    $stmt = $db->query("\n        SELECT id, product_code, product_name, variant, unit_size, unit_measure,\n               selling_price, base_unit, box_unit, pieces_per_box\n        FROM products\n        WHERE is_active = 1\n          AND product_code IS NOT NULL\n          AND TRIM(product_code) <> ''\n        ORDER BY product_name, unit_size, product_code\n    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function hfManualLineSnapshot(array $line, bool $original = false): array
{
    if ($original) {
        $code = $line['original_customer_product_code']
            ?? $line['customer_product_code']
            ?? $line['product_code']
            ?? null;
        $productId = $line['original_product_id'] ?? $line['product_id'] ?? null;
        $quantity = $line['original_quantity_entered'] ?? $line['quantity_entered'] ?? $line['quantity'] ?? null;
        $unit = $line['original_unit_entered'] ?? $line['unit_entered'] ?? $line['unit'] ?? null;
        $price = $line['original_po_unit_price'] ?? $line['po_unit_price'] ?? $line['unit_price'] ?? null;
    } else {
        $code = $line['customer_product_code'] ?? $line['product_code'] ?? null;
        $productId = $line['product_id'] ?? null;
        $quantity = $line['quantity_entered'] ?? $line['quantity'] ?? null;
        $unit = $line['unit_entered'] ?? $line['unit'] ?? null;
        $price = $line['po_unit_price'] ?? $line['unit_price'] ?? null;
    }

    return [
        'product_code' => strtoupper(trim((string)($code ?? ''))),
        'product_id' => $productId === null || $productId === '' ? null : (int)$productId,
        'quantity' => $quantity === null || $quantity === '' ? null : (int)$quantity,
        'unit' => strtolower(trim((string)($unit ?? ''))),
        'unit_price' => $price === null || $price === '' ? null : round((float)$price, 2),
    ];
}

function hfManualLineHasOriginal(array $line): bool
{
    return array_key_exists('original_quantity_entered', $line)
        && $line['original_quantity_entered'] !== null;
}

function hfManualLineChanged(array $line): bool
{
    if (!hfManualLineHasOriginal($line)) {
        return false;
    }
    return hfManualLineSnapshot($line) !== hfManualLineSnapshot($line, true);
}

function hfManualOrderHasChanges(array $lines): bool
{
    foreach ($lines as $line) {
        if (hfManualLineChanged($line)) {
            return true;
        }
    }
    return false;
}

function hfManualOrderSnapshot(
    int $customerId,
    string $customerPoNumber,
    ?string $deliveryDate,
    array $lines
): array {
    $snapshotLines = [];
    foreach ($lines as $line) {
        $snapshotLines[] = [
            'row_number' => (int)($line['row_number'] ?? 0),
            'line' => hfManualLineSnapshot($line),
        ];
    }
    usort($snapshotLines, static fn(array $a, array $b): int => $a['row_number'] <=> $b['row_number']);
    return [
        'customer_id' => $customerId,
        'customer_po_number' => $customerPoNumber,
        'delivery_date' => $deliveryDate,
        'lines' => $snapshotLines,
    ];
}

function hfManualSnapshotMatches(array $left, array $right): bool
{
    return json_encode($left, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        === json_encode($right, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function hfManualDate(string $value, string $label): string
{
    $date = DateTime::createFromFormat('!Y-m-d', trim($value));
    $errors = DateTime::getLastErrors();
    if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        throw new InvalidArgumentException("Enter a valid {$label}.");
    }
    return $date->format('Y-m-d');
}

function hfManualDateTime(string $value, string $label): string
{
    $date = DateTime::createFromFormat('Y-m-d\\TH:i', trim($value))
        ?: DateTime::createFromFormat('Y-m-d H:i', trim($value));
    if (!$date) {
        throw new InvalidArgumentException("Enter a valid {$label}.");
    }
    $date->setTime((int)$date->format('H'), (int)$date->format('i'), 0);
    if ($date > new DateTime('+5 minutes')) {
        throw new InvalidArgumentException("The {$label} cannot be in the future.");
    }
    return $date->format('Y-m-d H:i:s');
}

function hfManualCheckedLines(PDO $db, array $submittedLines): array
{
    if (count($submittedLines) > 500) {
        throw new InvalidArgumentException('A customer order may contain at most 500 product lines.');
    }
    $checked = [];
    foreach ($submittedLines as $index => $submitted) {
        if (!is_array($submitted)) {
            continue;
        }
        $productCode = trim((string)($submitted['product_code'] ?? ''));
        $quantity = trim((string)($submitted['quantity'] ?? ''));
        $unit = trim((string)($submitted['unit'] ?? ''));
        $price = trim((string)($submitted['unit_price'] ?? ''));
        $priceUnit = trim((string)($submitted['price_unit'] ?? $unit));
        $remarks = trim((string)($submitted['remarks'] ?? ''));
        if ($productCode === '' && $quantity === '' && $unit === '' && $price === '' && $remarks === '') {
            continue;
        }
        $line = hfMatchCustomerOrderLine($db, [
            'product_code' => $productCode,
            'description' => trim((string)($submitted['description'] ?? '')),
            'quantity' => $quantity,
            'unit' => $unit,
            'unit_price' => $price,
            'price_unit' => $priceUnit,
        ]);
        $line['line_id'] = (int)($submitted['line_id'] ?? 0);
        $line['row_number'] = (int)($submitted['row_number'] ?? ($index + 1));
        $line['remarks'] = $remarks;
        $line['raw'] = [
            'source' => 'manual_entry',
            'product_code' => $productCode,
            'quantity' => $quantity,
            'unit' => $unit,
            'unit_price' => $price,
            'price_unit' => $priceUnit,
            'entered_unit_price' => $line['raw']['entered_unit_price'] ?? null,
            'remarks' => $remarks,
        ];
        $checked[] = $line;
    }
    if (!$checked) {
        throw new InvalidArgumentException('Enter at least one product line before saving the order.');
    }

    $seenProductUnits = [];
    foreach ($checked as $line) {
        if (empty($line['product_id']) || (int)($line['quantity_base'] ?? 0) <= 0) {
            continue;
        }
        $unitType = (int)($line['quantity_boxes'] ?? 0) > 0 ? 'box' : 'base';
        $key = (int)$line['product_id'] . ':' . $unitType;
        if (isset($seenProductUnits[$key])) {
            $label = trim((string)($line['description'] ?? $line['customer_product_code'] ?? 'This product'));
            throw new InvalidArgumentException("{$label} is entered more than once with the same order unit. Combine it into one line.");
        }
        $seenProductUnits[$key] = true;
    }

    $requested = [];
    foreach ($checked as $line) {
        if (!empty($line['product_id']) && (int)($line['quantity_base'] ?? 0) > 0) {
            $productId = (int)$line['product_id'];
            $requested[$productId] = ($requested[$productId] ?? 0) + (int)$line['quantity_base'];
        }
    }
    $stock = hfImportedOrderAvailableStock($db, array_keys($requested));
    foreach ($checked as &$line) {
        if (empty($line['product_id']) || $line['line_status'] === 'blocked') {
            continue;
        }
        $productId = (int)$line['product_id'];
        $needed = (int)($requested[$productId] ?? 0);
        $available = (int)($stock[$productId] ?? 0);
        if ($needed > $available) {
            $line['line_status'] = 'warning';
            $line['issue_text'] = trim(
                ((string)($line['issue_text'] ?? '') !== '' ? $line['issue_text'] . ' ' : '')
                . "Only {$available} individual items are currently ready; this saved order needs {$needed}."
            );
        }
    }
    unset($line);
    return $checked;
}

function hfManualLatestCall(PDO $db, int $importId): ?array
{
    $stmt = $db->prepare("SELECT * FROM customer_order_call_confirmations
        WHERE import_id = ? AND confirmation_method = 'phone_call'
        ORDER BY id DESC LIMIT 1");
    $stmt->execute([$importId]);
    $call = $stmt->fetch(PDO::FETCH_ASSOC);
    return $call ?: null;
}

function hfSaveManualCustomerOrder(PDO $db, int $importId, array $data, int $userId): array
{
    hfEnsureManualCustomerOrderSchema($db);
    $sourceVerified = filter_var($data['source_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
    if (!$sourceVerified) {
        throw new InvalidArgumentException('Confirm that you compared the entered order with the original customer PO.');
    }
    $customerPoNumber = trim((string)($data['customer_po_number'] ?? ''));
    if ($customerPoNumber === '' || strlen($customerPoNumber) > 80) {
        throw new InvalidArgumentException('Enter the customer PO number shown in the original customer request.');
    }
    $deliveryDate = hfManualDate((string)($data['delivery_date'] ?? ''), 'requested delivery date');
    $customerId = (int)($data['customer_id'] ?? 0);
    $submittedLines = $data['lines'] ?? [];
    if (!is_array($submittedLines)) {
        throw new InvalidArgumentException('Enter the order lines before saving.');
    }

    $db->beginTransaction();
    try {
        $importStmt = $db->prepare('SELECT * FROM customer_order_imports WHERE id = ? FOR UPDATE');
        $importStmt->execute([$importId]);
        $import = $importStmt->fetch(PDO::FETCH_ASSOC);
        if (!$import) {
            throw new RuntimeException('The emailed customer PO was not found.');
        }
        if (!empty($import['sales_order_id']) || in_array($import['status'], ['order_created', 'converted'], true)) {
            throw new RuntimeException('This customer PO already has a Sales Order. Open the existing order instead of saving it again.');
        }
        if ($import['status'] === 'rejected') {
            throw new RuntimeException('Rejected emails cannot be edited.');
        }
        if (empty($import['entry_saved_at'])) {
            $trustedReference = hfTrustedCustomerOrderReferenceForImport($db, $import);
            if ($trustedReference) {
                $referenceErrors = hfTrustedCustomerOrderErrors(
                    $trustedReference,
                    $customerPoNumber,
                    $deliveryDate,
                    $submittedLines
                );
                if ($referenceErrors) {
                    throw new InvalidArgumentException(
                        implode(' ', $referenceErrors)
                        . ' Enter the original request exactly first. Customer-approved changes must be recorded afterward.'
                    );
                }
            }
        }
        $senderCustomer = hfFindImportedOrderCustomer(
            $db,
            strtolower(trim((string)($import['sender_email'] ?? ''))),
            ''
        );
        if (!$senderCustomer) {
            throw new InvalidArgumentException('This sender email is not registered to one active customer. Ask an administrator to register the official customer email first.');
        }
        $matchedCustomerId = (int)$senderCustomer['id'];
        if ($customerId > 0 && $customerId !== $matchedCustomerId) {
            throw new InvalidArgumentException('The selected customer does not match the email sender. Customer identity cannot be changed in the inbox.');
        }
        if (!empty($import['customer_id']) && (int)$import['customer_id'] !== $matchedCustomerId) {
            throw new InvalidArgumentException('This inbox record is linked to the wrong customer. It cannot be processed.');
        }
        $customerId = $matchedCustomerId;
        $customerStmt = $db->prepare("SELECT * FROM customers WHERE id = ? AND status = 'active' LIMIT 1");
        $customerStmt->execute([$customerId]);
        $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);
        if (!$customer) {
            throw new InvalidArgumentException('Choose the active customer that sent this email.');
        }

        $duplicateImport = $db->prepare("SELECT id, customer_po_number FROM customer_order_imports
            WHERE customer_po_number = ? AND id <> ? AND status NOT IN ('rejected', 'duplicate') LIMIT 1");
        $duplicateImport->execute([$customerPoNumber, $importId]);
        if ($duplicate = $duplicateImport->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException("This PO number is already used by inbox record #{$duplicate['id']}. Continue that record, or enter the correct PO number from this customer request.");
        }
        $duplicateOrder = $db->prepare("SELECT order_number FROM sales_orders
            WHERE customer_po_number = ? AND status <> 'cancelled' LIMIT 1");
        $duplicateOrder->execute([$customerPoNumber]);
        if ($existingOrder = $duplicateOrder->fetchColumn()) {
            throw new RuntimeException("This PO number already created Sales Order {$existingOrder}. Open that order instead of creating a duplicate.");
        }

        $currentStmt = $db->prepare('SELECT * FROM customer_order_import_lines WHERE import_id = ? ORDER BY row_number, id FOR UPDATE');
        $currentStmt->execute([$importId]);
        $currentLines = $currentStmt->fetchAll(PDO::FETCH_ASSOC);
        $currentById = [];
        foreach ($currentLines as $currentLine) {
            $currentById[(int)$currentLine['id']] = $currentLine;
        }
        $checkedLines = hfManualCheckedLines($db, $submittedLines);
        $usedIds = [];
        $nextRow = 0;
        foreach ($currentLines as $currentLine) {
            $nextRow = max($nextRow, (int)$currentLine['row_number']);
        }

        $updateLine = $db->prepare("UPDATE customer_order_import_lines SET
            row_number = ?, customer_product_code = ?, description = ?, product_id = ?,
            quantity_entered = ?, unit_entered = ?, quantity_base = ?, quantity_boxes = ?, quantity_pieces = ?,
            po_unit_price = ?, system_unit_price = ?, line_status = ?, issue_text = ?, raw_data = ?,
            original_customer_product_code = ?, original_description = ?, original_product_id = ?,
            original_quantity_entered = ?, original_unit_entered = ?, original_po_unit_price = ?
            WHERE id = ? AND import_id = ?");
        $insertLine = $db->prepare("INSERT INTO customer_order_import_lines (
            import_id, row_number, customer_product_code, description, product_id,
            quantity_entered, unit_entered, quantity_base, quantity_boxes, quantity_pieces,
            po_unit_price, system_unit_price, line_status, issue_text, raw_data,
            original_customer_product_code, original_description, original_product_id,
            original_quantity_entered, original_unit_entered, original_po_unit_price
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($checkedLines as $line) {
            $lineId = (int)($line['line_id'] ?? 0);
            $currentLine = $currentById[$lineId] ?? null;
            if ($currentLine) {
                $usedIds[$lineId] = true;
                $rowNumber = (int)$currentLine['row_number'];
                $original = hfManualLineHasOriginal($currentLine)
                    ? hfManualLineSnapshot($currentLine, true)
                    : hfManualLineSnapshot($currentLine);
                $originalDescription = $currentLine['original_description'] ?? $currentLine['description'];
            } else {
                $rowNumber = ++$nextRow;
                $original = hfManualLineSnapshot($line);
                $originalDescription = $line['description'];
            }
            $rawData = json_encode($line['raw'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $values = [
                $rowNumber,
                $line['customer_product_code'],
                $line['description'],
                $line['product_id'],
                $line['quantity_entered'],
                $line['unit_entered'],
                $line['quantity_base'],
                $line['quantity_boxes'],
                $line['quantity_pieces'],
                $line['po_unit_price'],
                $line['system_unit_price'],
                $line['line_status'],
                $line['issue_text'],
                $rawData,
                $original['product_code'],
                $originalDescription,
                $original['product_id'],
                $original['quantity'],
                $original['unit'],
                $original['unit_price'],
            ];
            if ($currentLine) {
                $updateLine->execute([...$values, $lineId, $importId]);
            } else {
                $insertLine->execute([$importId, ...$values]);
            }
        }

        $removeLine = $db->prepare("UPDATE customer_order_import_lines SET
            quantity_entered = 0, quantity_base = 0, quantity_boxes = 0, quantity_pieces = 0,
            line_status = 'warning', issue_text = 'Removed from the saved order during manual entry.'
            WHERE id = ? AND import_id = ?");
        foreach ($currentLines as $currentLine) {
            $currentId = (int)$currentLine['id'];
            if (!isset($usedIds[$currentId])) {
                $removeLine->execute([$currentId, $importId]);
            }
        }

        $savedStmt = $db->prepare('SELECT * FROM customer_order_import_lines WHERE import_id = ? ORDER BY row_number, id');
        $savedStmt->execute([$importId]);
        $savedLines = $savedStmt->fetchAll(PDO::FETCH_ASSOC);
        $activeLines = array_values(array_filter($savedLines, static fn(array $line): bool => (int)($line['quantity_base'] ?? 0) > 0));
        if (!$activeLines) {
            throw new InvalidArgumentException('Keep at least one product line in the saved order.');
        }
        $headerChanged = !empty($import['entry_saved_at']) && (
            (int)($import['customer_id'] ?? 0) !== $customerId
            || trim((string)($import['customer_po_number'] ?? '')) !== $customerPoNumber
            || (string)($import['entered_delivery_date'] ?? '') !== (string)$deliveryDate
        );
        $changed = hfManualOrderHasChanges($savedLines) || $headerChanged;
        $currentSnapshot = hfManualOrderSnapshot($customerId, $customerPoNumber, $deliveryDate, $savedLines);
        $latestCall = hfManualLatestCall($db, $importId);
        $callMatches = false;
        if ($latestCall) {
            $approvedSnapshot = json_decode((string)$latestCall['approved_snapshot'], true) ?: [];
            $callMatches = hfManualSnapshotMatches($approvedSnapshot, $currentSnapshot);
        }
        $issueCount = 0;
        $warningCount = 0;
        foreach ($activeLines as $line) {
            if ($line['line_status'] === 'blocked') {
                $issueCount++;
            } elseif ($line['line_status'] === 'warning') {
                $warningCount++;
            }
        }
        $requiresCustomerConfirmation = $changed || $warningCount > 0;
        if ($issueCount > 0) {
            $status = 'draft_order';
            $errorMessage = 'Fix the blocked product lines before creating the Sales Order.';
        } elseif ($requiresCustomerConfirmation && !$callMatches) {
            $status = 'needs_customer_confirmation';
            $errorMessage = $changed
                ? 'The saved order differs from the encoded customer request. Record the customer phone call, or correct the encoding against the original attachment.'
                : 'The customer request has a price or stock warning. Contact the customer and record the call before creating the Sales Order.';
        } elseif ($requiresCustomerConfirmation && $callMatches) {
            $status = 'customer_confirmed';
            $errorMessage = null;
        } else {
            $status = 'ready_to_create';
            $errorMessage = null;
        }
        $updateImport = $db->prepare("UPDATE customer_order_imports SET
            customer_id = ?, customer_po_number = ?, entered_delivery_date = ?,
            status = ?, issue_count = ?, warning_count = ?, error_message = ?,
            entry_saved_by = ?, entry_saved_at = NOW(), source_verified_by = ?,
            source_verified_at = NOW(), reviewed_by = ?, reviewed_at = NOW()
            WHERE id = ?");
        $updateImport->execute([
            $customerId,
            $customerPoNumber,
            $deliveryDate,
            $status,
            $issueCount,
            $warningCount,
            $errorMessage,
            $userId,
            $userId,
            $userId,
            $importId,
        ]);
        $db->commit();
        if (function_exists('logAudit')) {
            logAudit($userId, 'SAVE_CUSTOMER_PO_DETAILS', 'customer_order_imports', $importId, null, [
                'status' => $status,
                'customer_po_number' => $customerPoNumber,
                'line_count' => count($activeLines),
            ]);
        }
        return [
            'import_id' => $importId,
            'status' => $status,
            'issue_count' => $issueCount,
            'warning_count' => $warningCount,
            'line_count' => count($activeLines),
            'requires_customer_call' => $changed && !$callMatches,
        ];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function hfCorrectManualCustomerOrderEncoding(PDO $db, int $importId, string $reason, int $userId): array
{
    hfEnsureManualCustomerOrderSchema($db);
    hfEnsureCustomerOrderAdjustmentTable($db);
    $reason = trim($reason);
    if (strlen($reason) < 10 || strlen($reason) > 1000) {
        throw new InvalidArgumentException('Explain what was typed incorrectly and what the attachment actually shows.');
    }

    $db->beginTransaction();
    try {
        $importStmt = $db->prepare('SELECT * FROM customer_order_imports WHERE id = ? FOR UPDATE');
        $importStmt->execute([$importId]);
        $import = $importStmt->fetch(PDO::FETCH_ASSOC);
        if (!$import) {
            throw new RuntimeException('The emailed customer PO was not found.');
        }
        if (!empty($import['sales_order_id']) || in_array($import['status'], ['order_created', 'converted'], true)) {
            throw new RuntimeException('The encoding cannot be corrected after a Sales Order has been created.');
        }
        if ($import['status'] !== 'needs_customer_confirmation') {
            throw new RuntimeException('Encoding correction is only available when the saved entry differs from the original encoding.');
        }

        $callStmt = $db->prepare("SELECT COUNT(*) FROM customer_order_call_confirmations
            WHERE import_id = ? AND confirmation_method = 'phone_call'");
        $callStmt->execute([$importId]);
        $adjustmentStmt = $db->prepare('SELECT COUNT(*) FROM customer_order_adjustments WHERE import_id = ?');
        $adjustmentStmt->execute([$importId]);
        if ((int)$callStmt->fetchColumn() > 0 || (int)$adjustmentStmt->fetchColumn() > 0) {
            throw new RuntimeException('This order already has a customer confirmation record and must keep its original request history.');
        }

        $linesStmt = $db->prepare('SELECT * FROM customer_order_import_lines WHERE import_id = ? ORDER BY row_number, id FOR UPDATE');
        $linesStmt->execute([$importId]);
        $beforeLines = $linesStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!hfManualOrderHasChanges($beforeLines)) {
            throw new RuntimeException('There is no encoding difference to correct.');
        }
        $meaningfulLines = array_values(array_filter(
            $beforeLines,
            static fn(array $line): bool => (float)($line['quantity_entered'] ?? 0) > 0
        ));
        if (!$meaningfulLines) {
            throw new RuntimeException('Enter at least one valid order line before correcting the encoding.');
        }

        $trustedReference = hfTrustedCustomerOrderReferenceForImport($db, $import);
        if ($trustedReference) {
            $referenceErrors = hfTrustedCustomerOrderErrors(
                $trustedReference,
                (string)($import['customer_po_number'] ?? ''),
                $import['entered_delivery_date'] ?: null,
                $meaningfulLines
            );
            if ($referenceErrors) {
                throw new RuntimeException(
                    implode(' ', $referenceErrors)
                    . ' This is a customer change, not an encoding correction. Record the customer phone call.'
                );
            }
        }

        $deleteRemoved = $db->prepare("DELETE FROM customer_order_import_lines
            WHERE import_id = ?
              AND quantity_entered = 0
              AND issue_text = 'Removed from the saved order during manual entry.'");
        $deleteRemoved->execute([$importId]);

        $resetOriginal = $db->prepare("UPDATE customer_order_import_lines SET
            original_customer_product_code = customer_product_code,
            original_description = description,
            original_product_id = product_id,
            original_quantity_entered = quantity_entered,
            original_unit_entered = unit_entered,
            original_po_unit_price = po_unit_price
            WHERE import_id = ?");
        $resetOriginal->execute([$importId]);

        $linesStmt->execute([$importId]);
        $correctedLines = $linesStmt->fetchAll(PDO::FETCH_ASSOC);
        $issueCount = 0;
        $warningCount = 0;
        foreach ($correctedLines as $line) {
            if ((float)($line['quantity_entered'] ?? 0) <= 0) {
                continue;
            }
            if ($line['line_status'] === 'blocked') {
                $issueCount++;
            } elseif ($line['line_status'] === 'warning') {
                $warningCount++;
            }
        }
        if ($issueCount > 0) {
            $status = 'draft_order';
            $errorMessage = 'Fix the blocked product lines before creating the Sales Order.';
        } elseif ($warningCount > 0) {
            $status = 'needs_customer_confirmation';
            $errorMessage = 'The corrected customer request still has a price or stock warning. Contact the customer and record the call.';
        } else {
            $status = 'ready_to_create';
            $errorMessage = null;
        }

        $updateImport = $db->prepare('UPDATE customer_order_imports SET
            status = ?, issue_count = ?, warning_count = ?, error_message = ?,
            entry_saved_by = ?, entry_saved_at = NOW(), reviewed_by = ?, reviewed_at = NOW()
            WHERE id = ?');
        $updateImport->execute([
            $status,
            $issueCount,
            $warningCount,
            $errorMessage,
            $userId,
            $userId,
            $importId,
        ]);
        $db->commit();

        if (function_exists('logAudit')) {
            logAudit($userId, 'CORRECT_CUSTOMER_PO_ENCODING', 'customer_order_imports', $importId, [
                'status' => $import['status'],
                'lines' => array_map(static fn(array $line): array => hfManualLineSnapshot($line, true), $beforeLines),
            ], [
                'reason' => $reason,
                'status' => $status,
                'lines' => array_map(static fn(array $line): array => hfManualLineSnapshot($line, true), $correctedLines),
            ]);
        }

        return [
            'import_id' => $importId,
            'status' => $status,
            'issue_count' => $issueCount,
            'warning_count' => $warningCount,
            'removed_typing_rows' => $deleteRemoved->rowCount(),
        ];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function hfRecordCustomerOrderCall(PDO $db, int $importId, array $data, int $userId): array
{
    hfEnsureManualCustomerOrderSchema($db);
    $clarificationOnly = filter_var($data['clarification_only'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $contactName = trim((string)($data['contact_name'] ?? ''));
    $changeSummary = trim((string)($data['change_summary'] ?? ''));
    $reason = trim((string)($data['reason'] ?? ''));
    $note = trim((string)($data['note'] ?? ''));
    if ($contactName === '' || strlen($contactName) > 150 || preg_match('/\p{L}/u', $contactName) !== 1) {
        throw new InvalidArgumentException('Enter the name of the customer representative contacted.');
    }
    if ($changeSummary === '' || strlen($changeSummary) > 5000) {
        throw new InvalidArgumentException('Describe what the customer agreed to change.');
    }
    if ($reason === '' || strlen($reason) > 500) {
        throw new InvalidArgumentException('Enter why the customer order needed a change.');
    }
    if (strlen($note) > 5000) {
        throw new InvalidArgumentException('The phone call note is too long.');
    }
    $confirmedAt = hfManualDateTime((string)($data['confirmed_at'] ?? ''), 'phone confirmation date and time');

    $db->beginTransaction();
    try {
        $importStmt = $db->prepare('SELECT * FROM customer_order_imports WHERE id = ? FOR UPDATE');
        $importStmt->execute([$importId]);
        $import = $importStmt->fetch(PDO::FETCH_ASSOC);
        if (!$import) {
            throw new RuntimeException('The emailed customer PO was not found.');
        }
        if (!empty($import['sales_order_id']) || in_array($import['status'], ['order_created', 'converted'], true)) {
            throw new RuntimeException('This customer PO already has a Sales Order.');
        }
        if (empty($import['entry_saved_at']) && !$clarificationOnly) {
            throw new RuntimeException('Save the entered order details before recording a customer call.');
        }
        if ($clarificationOnly) {
            $snapshot = [];
        } else {
            $lineStmt = $db->prepare('SELECT * FROM customer_order_import_lines WHERE import_id = ? ORDER BY row_number, id');
            $lineStmt->execute([$importId]);
            $lines = $lineStmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$lines) {
                throw new RuntimeException('Enter the customer order details before recording a customer call.');
            }
            $snapshot = hfManualOrderSnapshot(
                (int)$import['customer_id'],
                (string)$import['customer_po_number'],
                $import['entered_delivery_date'] ?: null,
                $lines
            );
        }
        $confirmationMethod = $clarificationOnly ? 'phone_clarification' : 'phone_call';
        $insert = $db->prepare("INSERT INTO customer_order_call_confirmations (
            import_id, change_summary, reason, contact_name, confirmation_method,
            confirmed_at, note, approved_snapshot, recorded_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insert->execute([
            $importId,
            $changeSummary,
            $reason,
            $contactName,
            $confirmationMethod,
            $confirmedAt,
            $note !== '' ? $note : null,
            json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $userId,
        ]);
        $newStatus = (string)$import['status'];
        if ($clarificationOnly) {
            if (in_array($newStatus, ['received', 'ready_to_create', 'ready'], true) && empty($import['entry_saved_at'])) {
                $newStatus = 'for_encoding';
            }
        } else {
            $newStatus = 'customer_confirmed';
        }
        $db->prepare('UPDATE customer_order_imports SET status = ?, error_message = NULL WHERE id = ?')
            ->execute([$newStatus, $importId]);
        $db->commit();
        if (function_exists('logAudit')) {
            logAudit($userId, 'RECORD_CUSTOMER_PHONE_CONFIRMATION', 'customer_order_imports', $importId, null, [
                'contact_name' => $contactName,
                'change_summary' => $changeSummary,
                'confirmed_at' => $confirmedAt,
                'clarification_only' => $clarificationOnly,
            ]);
        }
        return [
            'import_id' => $importId,
            'status' => $newStatus,
            'confirmed_at' => $confirmedAt,
            'clarification_only' => $clarificationOnly,
        ];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function hfRejectCustomerOrderEmail(PDO $db, int $importId, string $reason, int $userId): void
{
    $reason = trim($reason);
    if ($reason === '' || strlen($reason) > 2000) {
        throw new InvalidArgumentException('Enter why this email is being rejected.');
    }
    $stmt = $db->prepare("UPDATE customer_order_imports SET status = 'rejected', error_message = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ? AND sales_order_id IS NULL");
    $stmt->execute([$reason, $userId, $importId]);
    if ($stmt->rowCount() < 1) {
        throw new RuntimeException('This email cannot be rejected after a Sales Order has been created.');
    }
    if (function_exists('logAudit')) {
        logAudit($userId, 'REJECT_CUSTOMER_PO_EMAIL', 'customer_order_imports', $importId, null, ['reason' => $reason]);
    }
}

function hfEnsureCustomerOrderAdjustmentTable(PDO $db): void
{
    $db->exec("\n        CREATE TABLE IF NOT EXISTS customer_order_adjustments (\n            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n            import_id BIGINT UNSIGNED NOT NULL,\n            import_line_id BIGINT UNSIGNED NOT NULL,\n            adjustment_type VARCHAR(30) NOT NULL,\n            original_data LONGTEXT NOT NULL,\n            adjusted_data LONGTEXT NOT NULL,\n            reason VARCHAR(500) NOT NULL,\n            contact_name VARCHAR(150) NOT NULL,\n            call_datetime DATETIME NOT NULL,\n            note TEXT NOT NULL,\n            adjusted_by INT NOT NULL,\n            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,\n            PRIMARY KEY (id),\n            KEY idx_customer_order_adjustments_import (import_id),\n            KEY idx_customer_order_adjustments_line (import_line_id)\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\n    ");
}

function hfCustomerOrderAdjustmentSnapshot(array $line): array
{
    return [
        'product_code' => $line['customer_product_code'] ?? null,
        'description' => $line['description'] ?? null,
        'product_id' => $line['product_id'] ?? null,
        'quantity_entered' => $line['quantity_entered'] ?? null,
        'unit_entered' => $line['unit_entered'] ?? null,
        'quantity_base' => $line['quantity_base'] ?? null,
        'quantity_boxes' => $line['quantity_boxes'] ?? null,
        'quantity_pieces' => $line['quantity_pieces'] ?? null,
        'po_unit_price' => $line['po_unit_price'] ?? null,
        'system_unit_price' => $line['system_unit_price'] ?? null,
        'line_status' => $line['line_status'] ?? null,
        'issue_text' => $line['issue_text'] ?? null,
    ];
}

function hfAdjustCustomerOrderImportLine(
    PDO $db,
    int $importId,
    int $lineId,
    array $data,
    int $userId
): array {
    hfEnsureManualCustomerOrderSchema($db);
    hfEnsureCustomerOrderAdjustmentTable($db);

    $contactName = trim((string)($data['contact_name'] ?? ''));
    $reason = trim((string)($data['reason'] ?? ''));
    $note = trim((string)($data['note'] ?? ''));
    $callValue = trim((string)($data['call_datetime'] ?? ''));
    $deliveryValue = trim((string)($data['delivery_date'] ?? ''));
    $remove = filter_var($data['remove'] ?? false, FILTER_VALIDATE_BOOLEAN);
    if ($contactName === '' || strlen($contactName) > 150) {
        throw new InvalidArgumentException('Enter the name of the customer representative contacted by phone.');
    }
    if ($reason === '' || strlen($reason) > 500) {
        throw new InvalidArgumentException('Enter why the customer-approved change was needed.');
    }
    if ($note === '' || strlen($note) > 5000) {
        throw new InvalidArgumentException('Enter what the customer agreed to.');
    }
    $callDate = DateTime::createFromFormat('Y-m-d\\TH:i', $callValue)
        ?: DateTime::createFromFormat('Y-m-d H:i', $callValue);
    if (!$callDate) {
        throw new InvalidArgumentException('Enter a valid call date and time.');
    }
    $callDate->setTime(
        (int)$callDate->format('H'),
        (int)$callDate->format('i'),
        0
    );
    if ($callDate > new DateTime('+5 minutes')) {
        throw new InvalidArgumentException('The phone call time cannot be in the future.');
    }
    $callDateSql = $callDate->format('Y-m-d H:i:s');
    $deliveryDate = null;
    if ($deliveryValue !== '') {
        $deliveryDateObject = DateTime::createFromFormat('!Y-m-d', $deliveryValue);
        $deliveryErrors = DateTime::getLastErrors();
        if (!$deliveryDateObject || ($deliveryErrors !== false && ($deliveryErrors['warning_count'] > 0 || $deliveryErrors['error_count'] > 0))) {
            throw new InvalidArgumentException('Enter a valid requested delivery date.');
        }
        if ($deliveryDateObject < new DateTime('today')) {
            throw new InvalidArgumentException('The requested delivery date cannot be in the past.');
        }
        $deliveryDate = $deliveryDateObject->format('Y-m-d');
    }

    $db->beginTransaction();
    try {
        $importStmt = $db->prepare("SELECT * FROM customer_order_imports WHERE id = ? FOR UPDATE");
        $importStmt->execute([$importId]);
        $import = $importStmt->fetch(PDO::FETCH_ASSOC);
        if (!$import) {
            throw new RuntimeException('Imported customer PO was not found.');
        }
        if (!empty($import['sales_order_id']) || $import['status'] === 'converted') {
            throw new RuntimeException('This customer PO already created a Sales Order and cannot be changed here.');
        }
        if ($import['status'] === 'rejected') {
            throw new RuntimeException('Rejected customer POs cannot be adjusted.');
        }

        $lineStmt = $db->prepare("\n            SELECT l.*, p.product_name, p.unit_size, p.unit_measure\n            FROM customer_order_import_lines l\n            LEFT JOIN products p ON p.id = l.product_id\n            WHERE l.id = ? AND l.import_id = ?\n            FOR UPDATE\n        ");
        $lineStmt->execute([$lineId, $importId]);
        $line = $lineStmt->fetch(PDO::FETCH_ASSOC);
        if (!$line) {
            throw new RuntimeException('The selected PO line was not found.');
        }

        $original = hfCustomerOrderAdjustmentSnapshot($line);
        $originalRaw = json_decode((string)($line['raw_data'] ?? '{}'), true) ?: [];
        $original['delivery_date'] = $originalRaw['delivery_date'] ?? null;
        if ($remove) {
            $adjusted = [
                'customer_product_code' => $line['customer_product_code'],
                'description' => $line['description'],
                'product_id' => $line['product_id'],
                'quantity_entered' => 0,
                'unit_entered' => $line['unit_entered'],
                'quantity_base' => 0,
                'quantity_boxes' => 0,
                'quantity_pieces' => 0,
                'po_unit_price' => $line['po_unit_price'],
                'system_unit_price' => $line['system_unit_price'],
                'line_status' => 'warning',
                'issue_text' => 'Removed after customer phone approval.',
                'delivery_date' => $deliveryDate,
            ];
            $adjustmentType = 'remove_line';
        } else {
            $newCode = trim((string)($data['product_code'] ?? $line['customer_product_code'] ?? ''));
            $quantity = filter_var($data['quantity'] ?? null, FILTER_VALIDATE_INT);
            $unit = strtolower(trim((string)($data['unit'] ?? '')));
            if ($quantity === false || $quantity <= 0) {
                throw new InvalidArgumentException('Enter a positive whole-number quantity, or choose Remove this line.');
            }
            if ($unit === '') {
                throw new InvalidArgumentException('Choose the order unit.');
            }
            if ($newCode === '') {
                throw new InvalidArgumentException('Choose a replacement product or keep the current product.');
            }
            $matched = hfMatchCustomerOrderLine($db, [
                'product_code' => $newCode,
                'quantity' => $quantity,
                'unit' => $unit,
            ]);
            if ($matched['line_status'] === 'blocked' || empty($matched['product_id'])) {
                throw new InvalidArgumentException($matched['issue_text'] ?: 'The adjusted product or unit is not valid.');
            }
            $wasReplacement = strtoupper($newCode) !== strtoupper((string)$line['customer_product_code']);
            $matched['po_unit_price'] = $wasReplacement ? null : $line['po_unit_price'];
            $originalPriceIssue = stripos((string)($line['issue_text'] ?? ''), 'PO price') !== false;
            $stock = hfImportedOrderAvailableStock($db, [(int)$matched['product_id']]);
            $available = (int)($stock[(int)$matched['product_id']] ?? 0);
            if ((int)$matched['quantity_base'] > $available) {
                $matched['line_status'] = 'warning';
                $matched['issue_text'] = trim(
                    ((string)($matched['issue_text'] ?? '') !== '' ? $matched['issue_text'] . ' ' : '')
                    . "Waiting for production: {$matched['quantity_base']} individual items requested, {$available} currently ready."
                );
            }
            if (!$wasReplacement && $originalPriceIssue) {
                $matched['line_status'] = 'warning';
                $matched['issue_text'] = trim(
                    ((string)($matched['issue_text'] ?? '') !== '' ? $matched['issue_text'] . ' ' : '')
                    . 'The emailed price differs from the current system price.'
                );
            }
            $adjusted = [
                'customer_product_code' => $newCode,
                'description' => $matched['description'],
                'product_id' => $matched['product_id'],
                'quantity_entered' => $matched['quantity_entered'],
                'unit_entered' => $matched['unit_entered'],
                'quantity_base' => $matched['quantity_base'],
                'quantity_boxes' => $matched['quantity_boxes'],
                'quantity_pieces' => $matched['quantity_pieces'],
                'po_unit_price' => $matched['po_unit_price'],
                'system_unit_price' => $matched['system_unit_price'],
                'line_status' => $matched['line_status'],
                'issue_text' => $matched['issue_text'],
                'delivery_date' => $deliveryDate,
            ];
            $adjustmentType = $wasReplacement ? 'replace_product' : 'change_quantity';
        }

        $lineUpdate = $db->prepare("\n            UPDATE customer_order_import_lines\n            SET customer_product_code = ?, description = ?, product_id = ?,\n                quantity_entered = ?, unit_entered = ?, quantity_base = ?,\n                quantity_boxes = ?, quantity_pieces = ?, po_unit_price = ?,\n                system_unit_price = ?, line_status = ?, issue_text = ?\n            WHERE id = ? AND import_id = ?\n        ");
        $lineUpdate->execute([
            $adjusted['customer_product_code'],
            $adjusted['description'],
            $adjusted['product_id'],
            $adjusted['quantity_entered'],
            $adjusted['unit_entered'],
            $adjusted['quantity_base'],
            $adjusted['quantity_boxes'],
            $adjusted['quantity_pieces'],
            $adjusted['po_unit_price'],
            $adjusted['system_unit_price'],
            $adjusted['line_status'],
            $adjusted['issue_text'],
            $lineId,
            $importId,
        ]);

        $adjustmentInsert = $db->prepare("\n            INSERT INTO customer_order_adjustments (\n                import_id, import_line_id, adjustment_type, original_data, adjusted_data,\n                reason, contact_name, call_datetime, note, adjusted_by\n            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)\n        ");
        $adjustmentInsert->execute([
            $importId,
            $lineId,
            $adjustmentType,
            json_encode($original, JSON_UNESCAPED_UNICODE),
            json_encode($adjusted, JSON_UNESCAPED_UNICODE),
            $reason,
            $contactName,
            $callDateSql,
            $note,
            $userId,
        ]);

        $counts = $db->prepare("\n            SELECT SUM(line_status = 'blocked') AS issue_count,\n                   SUM(line_status = 'warning' AND COALESCE(quantity_base, 0) > 0) AS warning_count\n            FROM customer_order_import_lines\n            WHERE import_id = ?\n        ");
        $counts->execute([$importId]);
        $summary = $counts->fetch(PDO::FETCH_ASSOC) ?: [];
        $issueCount = (int)($summary['issue_count'] ?? 0);
        $warningCount = (int)($summary['warning_count'] ?? 0);
        $status = ($issueCount > 0 || $warningCount > 0) ? 'needs_review' : 'ready';
        $db->prepare("\n            UPDATE customer_order_imports\n            SET status = ?, issue_count = ?, warning_count = ?, error_message = NULL\n            WHERE id = ?\n        ")->execute([$status, $issueCount, $warningCount, $importId]);

        $db->commit();
        if (function_exists('logAudit')) {
            logAudit($userId, 'ADJUST_CUSTOMER_PO', 'customer_order_imports', $importId, $original, $adjusted);
        }
        return [
            'import_id' => $importId,
            'line_id' => $lineId,
            'adjustment_type' => $adjustmentType,
            'adjusted' => $adjusted,
            'contact_name' => $contactName,
            'call_datetime' => $callDateSql,
            'delivery_date' => $deliveryDate,
        ];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

/**
 * Return a base-item price and an exact line total while preserving whether the
 * customer quoted a box price or an individual-item price.
 */
function hfCustomerOrderLinePricing(array $line): array
{
    $baseQuantity = max(0, (int)($line['quantity_base'] ?? 0));
    $piecesPerBox = max(1, (int)($line['pieces_per_box'] ?? 1));
    $baseUnit = strtolower(trim((string)($line['base_unit'] ?? 'piece')));
    $boxUnit = strtolower(trim((string)($line['box_unit'] ?? 'box')));
    $raw = json_decode((string)($line['raw_data'] ?? '{}'), true) ?: [];
    $enteredPrice = $raw['entered_unit_price'] ?? $raw['unit_price'] ?? null;
    $priceUnit = strtolower(trim((string)($raw['price_unit'] ?? $line['unit_entered'] ?? $baseUnit)));

    if ($enteredPrice !== null && $enteredPrice !== '' && is_numeric($enteredPrice)) {
        $enteredPrice = (float)$enteredPrice;
        if ($priceUnit === $boxUnit || in_array($priceUnit, ['box', 'boxes', 'case', 'cases', 'crate', 'crates'], true)) {
            return [
                'base_price' => $enteredPrice / $piecesPerBox,
                'line_total' => ($baseQuantity / $piecesPerBox) * $enteredPrice,
                'price_unit' => $boxUnit,
            ];
        }
        return [
            'base_price' => $enteredPrice,
            'line_total' => $baseQuantity * $enteredPrice,
            'price_unit' => $baseUnit,
        ];
    }

    $basePrice = (float)($line['po_unit_price'] ?? $line['system_unit_price'] ?? 0);
    return [
        'base_price' => $basePrice,
        'line_total' => $baseQuantity * $basePrice,
        'price_unit' => $baseUnit,
    ];
}

function hfConvertCustomerOrderImport(
    PDO $db,
    int $importId,
    int $userId,
    bool $acceptWarnings,
    string $creditOverrideReason = ''
): array {
    require_once __DIR__ . '/customer_accounts.php';
    hfEnsureCustomerAccountSchema($db);
    hfEnsureManualCustomerOrderSchema($db);
    hfEnsureCustomerOrderAdjustmentTable($db);
    $db->beginTransaction();
    try {
        $stmt = $db->prepare("
            SELECT coi.*,
                   c.name AS customer_name,
                   c.email AS customer_email,
                   c.customer_type,
                   c.contact_person,
                   c.contact_number,
                   c.address,
                   c.default_payment_type,
                   c.payment_terms_days,
                   c.credit_limit,
                   c.current_balance,
                   (SELECT COALESCE(SUM(dr.total_amount - dr.amount_paid), 0)
                    FROM delivery_receipts dr
                    WHERE dr.customer_id = c.id
                      AND dr.payment_status != 'paid'
                      AND dr.status NOT IN ('cancelled', 'draft')) AS outstanding_balance,
                   c.status AS customer_status
            FROM customer_order_imports coi
            LEFT JOIN customers c ON c.id = coi.customer_id
            WHERE coi.id = ?
            FOR UPDATE
        ");
        $stmt->execute([$importId]);
        $source = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$source) {
            throw new RuntimeException('Imported customer PO was not found.');
        }
        if (!empty($source['sales_order_id'])) {
            throw new RuntimeException('This customer PO already created a Sales Order.');
        }
        if (($source['status'] ?? '') === 'rejected') {
            throw new RuntimeException('Rejected customer PO cannot be converted.');
        }
        if (empty($source['customer_id'])) {
            throw new RuntimeException('This sender email is not registered to an active customer.');
        }
        if (($source['customer_status'] ?? '') !== 'active') {
            throw new RuntimeException('This customer is archived. Ask an administrator to reactivate the customer before creating the Sales Order.');
        }
        $senderEmail = strtolower(trim((string)($source['sender_email'] ?? '')));
        $customerEmail = strtolower(trim((string)($source['customer_email'] ?? '')));
        if ($senderEmail === '' || $customerEmail === '' || $senderEmail !== $customerEmail) {
            throw new RuntimeException('The customer no longer matches the email sender. The Sales Order cannot be created.');
        }
        if (trim((string)($source['customer_po_number'] ?? '')) === '') {
            throw new RuntimeException('Save the customer PO number from the emailed attachment before creating the order.');
        }
        if (in_array($source['status'], ['for_encoding', 'needs_customer_confirmation'], true)) {
            throw new RuntimeException('Finish entering the order and record the customer phone confirmation before creating the Sales Order.');
        }
        if (empty($source['entry_saved_at']) && !in_array($source['status'], ['ready', 'needs_review'], true)) {
            throw new RuntimeException('Save the entered customer order details before creating the Sales Order.');
        }
        if (empty($source['source_verified_at'])) {
            throw new RuntimeException('Compare the entered order with the original customer PO before creating the Sales Order.');
        }

        $linesStmt = $db->prepare("
            SELECT l.*, p.product_name, p.unit_size, p.unit_measure,
                   p.base_unit, p.box_unit, p.pieces_per_box
            FROM customer_order_import_lines l
            LEFT JOIN products p ON p.id = l.product_id
            WHERE l.import_id = ?
            ORDER BY l.row_number
        ");
        $linesStmt->execute([$importId]);
        $lines = $linesStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$lines) {
            throw new RuntimeException('Imported customer PO has no order lines.');
        }
        if (hfManualOrderHasChanges($lines) && $source['status'] !== 'customer_confirmed') {
            throw new RuntimeException('Record the customer phone confirmation for the changed order before creating the Sales Order.');
        }
        $activeLines = array_values(array_filter(
            $lines,
            static fn($line) => (int)($line['quantity_base'] ?? 0) > 0
        ));
        if (!$activeLines) {
            throw new RuntimeException('All PO lines were removed. There is nothing left to create as a Sales Order.');
        }
        foreach ($lines as $line) {
            if ((int)($line['quantity_base'] ?? 0) <= 0) {
                continue;
            }
            if ($line['line_status'] === 'blocked' || empty($line['product_id'])) {
                throw new RuntimeException('Resolve all blocked PO lines before creating the order.');
            }
            if ($line['line_status'] === 'warning' && !$acceptWarnings) {
                throw new RuntimeException('Review and accept the price warnings before creating the order.');
            }
        }

        $duplicate = $db->prepare("
            SELECT order_number
            FROM sales_orders
            WHERE customer_po_number = ?
              AND status <> 'cancelled'
            LIMIT 1
        ");
        $duplicate->execute([$source['customer_po_number']]);
        if ($existing = $duplicate->fetchColumn()) {
            throw new RuntimeException("Customer PO already exists as {$existing}.");
        }

        $requested = [];
        foreach ($lines as $line) {
            if ((int)($line['quantity_base'] ?? 0) <= 0) {
                continue;
            }
            $pid = (int) $line['product_id'];
            $requested[$pid] = ($requested[$pid] ?? 0) + (int) $line['quantity_base'];
        }
        $stock = hfImportedOrderAvailableStock($db, array_keys($requested));
        $stockWarnings = [];
        foreach ($requested as $pid => $qty) {
            if ($qty > (int) ($stock[$pid] ?? 0)) {
                $stockWarnings[] = "Product #{$pid}: ordered {$qty}, currently ready " . (int)($stock[$pid] ?? 0);
            }
        }

        $orderNumber = hfGenerateImportedOrderNumber($db);
        $deliveryDate = $source['entered_delivery_date'] ?: null;
        $rawFirst = json_decode((string) ($lines[0]['raw_data'] ?? '{}'), true) ?: [];
        if (!$deliveryDate && !empty($rawFirst['delivery_date'])) {
            $date = DateTime::createFromFormat('Y-m-d', $rawFirst['delivery_date']);
            $deliveryDate = $date ? $date->format('Y-m-d') : null;
        }
        $adjustmentDateStmt = $db->prepare("\n            SELECT adjusted_data\n            FROM customer_order_adjustments\n            WHERE import_id = ?\n            ORDER BY id DESC\n        ");
        $adjustmentDateStmt->execute([$importId]);
        foreach ($adjustmentDateStmt->fetchAll(PDO::FETCH_COLUMN) as $adjustedJson) {
            $adjustedData = json_decode((string)$adjustedJson, true) ?: [];
            if (!empty($adjustedData['delivery_date'])) {
                $adjustedDate = DateTime::createFromFormat('!Y-m-d', (string)$adjustedData['delivery_date']);
                if ($adjustedDate) {
                    $deliveryDate = $adjustedDate->format('Y-m-d');
                    break;
                }
            }
        }
        $deliveryAddress = trim((string)($rawFirst['delivery_address'] ?? ''));
        if ($deliveryAddress === '') {
            $deliveryAddress = (string)$source['address'];
        }
        if (strlen($deliveryAddress) > 500) {
            throw new RuntimeException('The delivery address is too long.');
        }
        $paymentType = $source['default_payment_type'] ?? 'credit';
        $terms = (int) ($source['payment_terms_days'] ?? 0);
        if ($paymentType === 'cash') {
            $terms = 0;
        }
        $allowedOrderCustomerTypes = [
            'supermarket',
            'institutional',
            'school',
            'feeding_program',
            'restaurant',
            'distributor',
            'walk_in',
            'other',
        ];
        $orderCustomerType = in_array($source['customer_type'], $allowedOrderCustomerTypes, true)
            ? $source['customer_type']
            : 'other';
        $dueDate = ($paymentType === 'credit' && $terms > 0)
            ? date('Y-m-d', strtotime('+' . $terms . ' days'))
            : null;

        $orderInsert = $db->prepare("
            INSERT INTO sales_orders (
                order_number, customer_id, customer_name, customer_type,
                customer_po_number, source_type, source_import_id,
                payment_type, payment_terms_days, contact_person,
                contact_number, delivery_address, delivery_date,
                total_items, total_quantity, subtotal, total_amount,
                balance_due, due_date, status, notes, created_by
            ) VALUES (
                ?, ?, ?, ?, ?, 'customer_po_email', ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, 'draft', ?, ?
            )
        ");

        $subtotal = 0.0;
        $totalQty = 0;
        foreach ($lines as $line) {
            if ((int)($line['quantity_base'] ?? 0) <= 0) {
                continue;
            }
            $pricing = hfCustomerOrderLinePricing($line);
            $subtotal += $pricing['line_total'];
            $totalQty += (int) $line['quantity_base'];
        }
        $creditLimit = (float) ($source['credit_limit'] ?? 0);
        $currentBalance = max(0, (float) ($source['outstanding_balance'] ?? $source['current_balance'] ?? 0));
        $creditExceeded = $paymentType === 'credit'
            && ($currentBalance + $subtotal) > $creditLimit;
        if ($creditExceeded && !$acceptWarnings) {
            throw new RuntimeException('This order exceeds the customer credit limit. Review and accept the warning first.');
        }
        $creditOverrideReason = trim($creditOverrideReason);
        if ($creditExceeded && mb_strlen($creditOverrideReason) < 10) {
            throw new RuntimeException('Enter a written reason for the General Manager credit review (at least 10 characters).');
        }
        if (mb_strlen($creditOverrideReason) > 500) {
            throw new RuntimeException('The credit review reason must be 500 characters or fewer.');
        }
        $notes = 'Entered from customer email by Highland Fresh. Original attachment retained. Source PO: ' . $source['customer_po_number'];
        if ($creditExceeded) {
            $notes .= sprintf(
                '. GM credit review required: existing unpaid balance %.2f plus this draft %.2f exceeds the %.2f credit limit. Reason: %s',
                $currentBalance,
                $subtotal,
                $creditLimit,
                $creditOverrideReason
            );
        }
        if ($stockWarnings) {
            $notes .= '. Waiting for production/QC: ' . implode('; ', $stockWarnings);
        }
        $adjustmentStmt = $db->prepare("\n            SELECT adjustment_type, contact_name, call_datetime, note\n            FROM customer_order_adjustments\n            WHERE import_id = ?\n            ORDER BY id\n        ");
        $adjustmentStmt->execute([$importId]);
        $adjustmentNotes = [];
        foreach ($adjustmentStmt->fetchAll(PDO::FETCH_ASSOC) as $adjustment) {
            $adjustmentNotes[] = sprintf(
                '%s approved by %s on %s: %s',
                str_replace('_', ' ', (string)$adjustment['adjustment_type']),
                $adjustment['contact_name'],
                $adjustment['call_datetime'],
                $adjustment['note']
            );
        }
        if ($adjustmentNotes) {
            $notes .= '. Customer-approved phone adjustments: ' . implode(' | ', $adjustmentNotes);
        }
        $callStmt = $db->prepare("SELECT contact_name, confirmed_at, change_summary, reason, note
            FROM customer_order_call_confirmations WHERE import_id = ? ORDER BY id");
        $callStmt->execute([$importId]);
        $callNotes = [];
        foreach ($callStmt->fetchAll(PDO::FETCH_ASSOC) as $call) {
            $callNotes[] = sprintf(
                'Phone call with %s on %s: %s (%s)%s',
                $call['contact_name'],
                $call['confirmed_at'],
                $call['change_summary'],
                $call['reason'],
                trim((string)$call['note']) !== '' ? ' Note: ' . $call['note'] : ''
            );
        }
        if ($callNotes) {
            $notes .= '. Customer call record: ' . implode(' | ', $callNotes);
        }
        $orderInsert->execute([
            $orderNumber,
            $source['customer_id'],
            $source['customer_name'],
            $orderCustomerType,
            $source['customer_po_number'],
            $importId,
            $paymentType,
            $terms,
            $source['contact_person'],
            $source['contact_number'],
            $deliveryAddress,
            $deliveryDate,
                count($activeLines),
            $totalQty,
            $subtotal,
            $subtotal,
            $subtotal,
            $dueDate,
            $notes,
            $userId,
        ]);
        $orderId = (int) $db->lastInsertId();

        $itemInsert = $db->prepare("
            INSERT INTO sales_order_items (
                order_id, product_id, product_name, size_value, size_unit,
                quantity_ordered, quantity_boxes, quantity_pieces,
                unit_type, unit_price, line_total, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($lines as $line) {
            if ((int)($line['quantity_base'] ?? 0) <= 0) {
                continue;
            }
            $pricing = hfCustomerOrderLinePricing($line);
            $price = $pricing['base_price'];
            $qty = (int) $line['quantity_base'];
            $boxes = (int) $line['quantity_boxes'];
            $pieces = (int) $line['quantity_pieces'];
            $unitType = $boxes > 0 && $pieces > 0
                ? 'mixed'
                : ($boxes > 0 ? 'box' : 'piece');
            $raw = json_decode((string)($line['raw_data'] ?? '{}'), true) ?: [];
            $remarks = trim((string)($raw['remarks'] ?? ''));
            $itemInsert->execute([
                $orderId,
                $line['product_id'],
                $line['product_name'],
                $line['unit_size'] ?? 0,
                $line['unit_measure'] ?? 'ml',
                $qty,
                $boxes,
                $pieces,
                $unitType,
                $price,
                $pricing['line_total'],
                $remarks !== '' ? $remarks : null,
            ]);
        }

        $db->prepare("
            UPDATE customer_order_imports
            SET status = 'order_created', sales_order_id = ?, reviewed_by = ?,
                reviewed_at = NOW()
            WHERE id = ?
        ")->execute([$orderId, $userId, $importId]);

        $db->commit();
        if (function_exists('logAudit')) {
            logAudit($userId, 'IMPORT_CUSTOMER_PO', 'sales_orders', $orderId, null, [
                'source_import_id' => $importId,
                'customer_po_number' => $source['customer_po_number'],
                'order_number' => $orderNumber,
            ]);
        }

        return [
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'status' => 'draft',
            'stock_warnings' => $stockWarnings,
        ];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function hfGenerateImportedOrderNumber(PDO $db): string
{
    $prefix = 'SO-' . date('Ymd') . '-';
    $stmt = $db->prepare("
        SELECT MAX(CAST(SUBSTRING(order_number, -3) AS UNSIGNED))
        FROM sales_orders
        WHERE order_number LIKE ?
        FOR UPDATE
    ");
    $stmt->execute([$prefix . '%']);
    return $prefix . str_pad(((int) $stmt->fetchColumn()) + 1, 3, '0', STR_PAD_LEFT);
}

function hfSyncCustomerOrderMailbox(PDO $db, ?int $userId = null): array
{
    if (!function_exists('hfPop3FetchNewMessages')) {
        require_once __DIR__ . '/pop3_mailbox.php';
    }

    $knownSourceUids = $db->query("SELECT source_uid FROM customer_order_imports WHERE source_uid LIKE 'mailbox:%'")
        ->fetchAll(PDO::FETCH_COLUMN);
    $messages = hfPop3FetchNewMessages($knownSourceUids);
    $summary = [
        'checked' => count($messages),
        'imported' => 0,
        'duplicates' => 0,
        'rejected' => 0,
        'ignored' => 0,
        'results' => [],
    ];

    foreach ($messages as $message) {
        $sender = strtolower(trim((string)($message['sender_email'] ?? '')));
        $mailboxAddress = defined('ORDER_MAILBOX_USERNAME')
            ? strtolower(trim((string)ORDER_MAILBOX_USERNAME))
            : '';
        if ($sender !== '' && $mailboxAddress !== '' && $sender === $mailboxAddress
            && !hfIsKnownCustomerOrderSender($db, $sender)) {
            $summary['ignored']++;
            continue;
        }

        $orderAttachment = null;
        foreach (['pdf', 'docx', 'doc', 'xlsx', 'csv', 'jpg', 'jpeg', 'png'] as $preferredExtension) {
            foreach (($message['attachments'] ?? []) as $attachment) {
                if (strtolower(pathinfo($attachment['filename'] ?? '', PATHINFO_EXTENSION)) === $preferredExtension) {
                    $orderAttachment = $attachment;
                    break 2;
                }
            }
        }

        $knownCustomerSender = hfIsKnownCustomerOrderSender($db, $sender);
        $hasEmailOrder = hfHasUsableCustomerOrderEmailBody($message)
            || ($knownCustomerSender && hfHasReviewableCustomerOrderEmailBody($message));
        if (!$orderAttachment && !$hasEmailOrder && !hfShouldRecordMailboxMessage($db, $message)) {
            $summary['ignored']++;
            continue;
        }

        if (!empty($message['parse_error']) || empty($message['sender_email'])) {
            $summary['rejected']++;
            hfRecordRejectedMailboxOrder(
                $db,
                $message,
                $userId,
                $message['parse_error'] ?? 'Sender email could not be read.'
            );
            continue;
        }

        if (!$knownCustomerSender) {
            $summary['rejected']++;
            hfRecordRejectedMailboxOrder(
                $db,
                $message,
                $userId,
                'Sender email is not registered to an active customer. Ask an administrator to register the official customer email before accepting orders.'
            );
            continue;
        }

        try {
            if ($orderAttachment) {
                $result = hfImportCustomerOrderAttachment(
                    $db,
                    $message,
                    $orderAttachment['filename'],
                    $orderAttachment['content'],
                    $userId
                );
            } elseif ($hasEmailOrder) {
                $result = hfImportCustomerOrderEmailBody($db, $message, $userId);
            } else {
                $summary['rejected']++;
                hfRecordRejectedMailboxOrder(
                    $db,
                    $message,
                    $userId,
                    'The email contains no readable order details and no supported purchase order attachment.'
                );
                continue;
            }
            if (!empty($result['duplicate'])) {
                $summary['duplicates']++;
            } else {
                $summary['imported']++;
            }
            $summary['results'][] = $result;
        } catch (Throwable $e) {
            $summary['rejected']++;
            hfRecordRejectedMailboxOrder($db, $message, $userId, $e->getMessage());
        }
    }

    return $summary;
}

function hfShouldRecordMailboxMessage(PDO $db, array $message): bool
{
    $sender = strtolower(trim((string)($message['sender_email'] ?? '')));
    if (hfIsKnownCustomerOrderSender($db, $sender)) {
        return true;
    }

    $subject = trim((string)($message['subject'] ?? ''));
    return (bool)preg_match('/\b(?:purchase\s+order|customer\s+po|po\s*#|order\s*#)\b/i', $subject);
}

function hfIsKnownCustomerOrderSender(PDO $db, string $sender): bool
{
    return hfFindImportedOrderCustomer($db, strtolower(trim($sender)), '') !== null;
}

function hfRecordRejectedMailboxOrder(
    PDO $db,
    array $message,
    ?int $userId,
    string $error
): void {
    $uid = trim((string) ($message['uid'] ?? bin2hex(random_bytes(12))));
    $sourceUid = 'mailbox:' . hash('sha256', $uid);
    $check = $db->prepare('SELECT id FROM customer_order_imports WHERE source_uid = ? LIMIT 1');
    $check->execute([$sourceUid]);
    if ($check->fetchColumn()) {
        return;
    }

    $sender = filter_var($message['sender_email'] ?? '', FILTER_VALIDATE_EMAIL)
        ? strtolower(trim((string)$message['sender_email']))
        : 'unknown@invalid.local';
    $customer = hfFindImportedOrderCustomer($db, $sender, '');
    $stmt = $db->prepare("
        INSERT INTO customer_order_imports (
            source_uid, message_id, sender_email, subject, received_at, email_body,
            customer_id, attachment_original_name, attachment_path, attachment_sha256,
            status, error_message, imported_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, '', ?, 'rejected', ?, ?)
    ");
    $stmt->execute([
        $sourceUid,
        $message['message_id'] ?? null,
        $sender,
        $message['subject'] ?? null,
        $message['received_at'] ?? date('Y-m-d H:i:s'),
        trim((string) ($message['body'] ?? '')) ?: null,
        $customer['id'] ?? null,
        'No supported attachment',
        hash('sha256', $uid . ':' . $error),
        mb_substr($error, 0, 2000),
        $userId,
    ]);
}
