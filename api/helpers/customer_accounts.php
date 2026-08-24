<?php

/**
 * Shared customer-account rules.
 *
 * Delivery Receipts are the single source of truth for Accounts Receivable.
 * The customers.current_balance column is retained only for compatibility with
 * older reports and is synchronized from unpaid Delivery Receipts.
 */

function hfCustomerPaymentTermChoices(): array
{
    // Keep every term already used by the three customer-maintenance screens.
    return [0, 7, 14, 15, 30, 45, 60, 90];
}

function hfCustomerOutstandingSql(string $customerExpression = 'c.id'): string
{
    return "COALESCE((
        SELECT SUM(GREATEST(COALESCE(dr.total_amount, 0) - COALESCE(dr.amount_paid, 0), 0))
        FROM delivery_receipts dr
        WHERE dr.customer_id = {$customerExpression}
          AND dr.payment_status <> 'paid'
          AND dr.status NOT IN ('cancelled', 'draft')
    ), 0)";
}

function hfCustomerOutstandingBalance(PDO $db, int $customerId): float
{
    $stmt = $db->prepare("SELECT " . hfCustomerOutstandingSql('?') . " AS outstanding_balance");
    $stmt->execute([$customerId]);
    return round(max(0, (float) $stmt->fetchColumn()), 2);
}

function hfSyncCustomerBalance(PDO $db, int $customerId): float
{
    $outstanding = hfCustomerOutstandingBalance($db, $customerId);
    $stmt = $db->prepare('UPDATE customers SET current_balance = ? WHERE id = ?');
    $stmt->execute([$outstanding, $customerId]);
    return $outstanding;
}

function hfCustomerUsesPoInbox(string $customerType): bool
{
    return in_array($customerType, [
        'institutional', 'supermarket', 'feeding_program', 'distributor', 'restaurant'
    ], true);
}

function hfCustomerNormalizePayload(array $data, array $existing = []): array
{
    if (array_key_exists('phone', $data) && !array_key_exists('contact_number', $data)) {
        $data['contact_number'] = $data['phone'];
    }
    if (array_key_exists('payment_terms', $data) && !array_key_exists('payment_terms_days', $data)) {
        $data['payment_terms_days'] = $data['payment_terms'];
    }
    if (array_key_exists('default_payment_mode', $data) && !array_key_exists('default_payment_type', $data)) {
        $data['default_payment_type'] = $data['default_payment_mode'];
    }

    $effective = array_merge($existing, $data);
    $paymentType = strtolower(trim((string) ($effective['default_payment_type'] ?? 'cash')));
    $data['default_payment_type'] = $paymentType;

    if ($paymentType === 'cash') {
        $data['payment_terms_days'] = 0;
        $data['credit_limit'] = 0;
    }
    if (($effective['status'] ?? 'active') !== 'blocked') {
        $data['blocked_reason'] = null;
    } elseif (!array_key_exists('blocked_reason', $data)) {
        $data['blocked_reason'] = $effective['blocked_reason'] ?? null;
    }

    return $data;
}

function hfValidateCustomerAccountPayload(array $data, array $existing = [], bool $creating = false): array
{
    $effective = array_merge($existing, $data);
    $errors = [];

    $required = ['name' => 'Customer/company name', 'customer_type' => 'Account type',
        'contact_person' => 'Contact person', 'contact_number' => 'Contact number', 'address' => 'Address'];
    foreach ($required as $field => $label) {
        if (trim((string) ($effective[$field] ?? '')) === '') {
            $errors[$field] = "{$label} is required.";
        }
    }

    $types = ['walk_in', 'institutional', 'supermarket', 'feeding_program', 'distributor', 'restaurant'];
    if (!in_array((string) ($effective['customer_type'] ?? ''), $types, true)) {
        $errors['customer_type'] = 'Choose a valid account type.';
    }

    $paymentType = strtolower(trim((string) ($effective['default_payment_type'] ?? 'cash')));
    if (!in_array($paymentType, ['cash', 'credit'], true)) {
        $errors['default_payment_type'] = 'Choose Cash or Credit.';
    } elseif ($paymentType === 'credit') {
        $rawTerms = $effective['payment_terms_days'] ?? '';
        $rawLimit = $effective['credit_limit'] ?? '';
        $terms = filter_var($rawTerms, FILTER_VALIDATE_INT);
        $limitIsPlain = is_int($rawLimit) || is_float($rawLimit)
            || preg_match('/^\d+(?:\.\d{1,2})?$/', trim((string) $rawLimit));
        $limit = $limitIsPlain ? (float) $rawLimit : 0;
        if ($terms === false || $terms <= 0 || !in_array($terms, hfCustomerPaymentTermChoices(), true)) {
            $errors['payment_terms_days'] = 'Credit customers need an approved payment term greater than zero.';
        }
        if (!$limitIsPlain || $limit <= 0) {
            $errors['credit_limit'] = 'Credit customers need a positive credit limit.';
        } elseif (!is_finite($limit) || $limit > 9999999999.99) {
            $errors['credit_limit'] = 'Credit limit must not exceed PHP 9,999,999,999.99.';
        }
    }

    $status = (string) ($effective['status'] ?? 'active');
    if (!in_array($status, ['active', 'inactive', 'blocked'], true)) {
        $errors['status'] = 'Choose Active, Inactive, or Blocked.';
    }
    if ($status === 'blocked' && mb_strlen(trim((string) ($effective['blocked_reason'] ?? ''))) < 10) {
        $errors['blocked_reason'] = 'Explain why this customer is blocked (at least 10 characters).';
    }

    $email = trim((string) ($effective['email'] ?? ''));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if (hfCustomerUsesPoInbox((string) ($effective['customer_type'] ?? '')) && $email === '') {
        $errors['email'] = 'Email is required for customers who can use the PO Inbox.';
    }

    return $errors;
}

function hfCustomerDueDate(?string $documentDate, string $paymentType, int $termsDays): ?string
{
    if ($paymentType !== 'credit' || $termsDays <= 0) {
        return $documentDate;
    }
    $base = $documentDate ?: date('Y-m-d');
    return date('Y-m-d', strtotime($base . " + {$termsDays} days"));
}

function hfEnsureCustomerAccountSchema(PDO $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $customerColumns = [];
    foreach ($db->query('SHOW COLUMNS FROM customers')->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $customerColumns[$column['Field']] = true;
    }
    if (!isset($customerColumns['blocked_reason'])) {
        $db->exec('ALTER TABLE customers ADD COLUMN blocked_reason VARCHAR(500) NULL AFTER status');
    }

    // Orders must preserve Institutional rather than silently relabeling it.
    $orderType = $db->query("SHOW COLUMNS FROM sales_orders LIKE 'customer_type'")->fetch(PDO::FETCH_ASSOC);
    if ($orderType && stripos((string) $orderType['Type'], "'institutional'") === false) {
        $db->exec("ALTER TABLE sales_orders MODIFY customer_type ENUM('supermarket','institutional','school','feeding_program','restaurant','distributor','walk_in','other') NOT NULL DEFAULT 'other'");
    }

    $drColumns = [];
    foreach ($db->query('SHOW COLUMNS FROM delivery_receipts')->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $drColumns[$column['Field']] = true;
    }
    $additions = [
        'payment_type' => "ALTER TABLE delivery_receipts ADD COLUMN payment_type ENUM('cash','credit') NOT NULL DEFAULT 'cash' AFTER amount_paid",
        'payment_terms_days' => 'ALTER TABLE delivery_receipts ADD COLUMN payment_terms_days INT NOT NULL DEFAULT 0 AFTER payment_type',
        'due_date' => 'ALTER TABLE delivery_receipts ADD COLUMN due_date DATE NULL AFTER payment_terms_days',
        'sub_account_id' => 'ALTER TABLE delivery_receipts ADD COLUMN sub_account_id INT NULL AFTER customer_id',
        'sub_location' => 'ALTER TABLE delivery_receipts ADD COLUMN sub_location VARCHAR(200) NULL AFTER customer_name',
        'notes' => 'ALTER TABLE delivery_receipts ADD COLUMN notes TEXT NULL AFTER remarks',
    ];
    foreach ($additions as $name => $sql) {
        if (!isset($drColumns[$name])) {
            $db->exec($sql);
        }
    }

    $db->exec("CREATE TABLE IF NOT EXISTS sales_customer_sub_accounts (
        id INT NOT NULL AUTO_INCREMENT,
        customer_id INT NOT NULL,
        sub_name VARCHAR(200) NOT NULL,
        address TEXT NULL,
        contact_person VARCHAR(100) NULL,
        contact_number VARCHAR(20) NULL,
        status ENUM('active','inactive') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id), KEY idx_customer (customer_id),
        CONSTRAINT fk_customer_location_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Snapshot existing order agreements onto their Delivery Receipts once.
    $db->exec("UPDATE delivery_receipts dr
        JOIN sales_orders so ON so.id = dr.order_id
        SET dr.payment_type = so.payment_type,
            dr.payment_terms_days = CASE WHEN so.payment_type = 'credit' THEN so.payment_terms_days ELSE 0 END,
            dr.due_date = COALESCE(dr.due_date, so.due_date, DATE(dr.created_at)),
            dr.sub_account_id = COALESCE(dr.sub_account_id, so.sub_account_id)
        WHERE dr.due_date IS NULL");

    $db->exec("UPDATE delivery_receipts dr
        LEFT JOIN sales_orders so ON so.id = dr.order_id
        LEFT JOIN customers c ON c.id = dr.customer_id
        SET dr.payment_type = COALESCE(so.payment_type, c.default_payment_type, 'cash'),
            dr.payment_terms_days = CASE
                WHEN COALESCE(so.payment_type, c.default_payment_type, 'cash') = 'credit'
                THEN COALESCE(so.payment_terms_days, c.payment_terms_days, 0) ELSE 0 END,
            dr.due_date = CASE
                WHEN COALESCE(so.payment_type, c.default_payment_type, 'cash') = 'credit'
                THEN DATE_ADD(DATE(dr.created_at), INTERVAL COALESCE(so.payment_terms_days, c.payment_terms_days, 0) DAY)
                ELSE DATE(dr.created_at) END
        WHERE dr.due_date IS NULL");
}

function hfCustomerLocations(PDO $db, int $customerId, bool $includeInactive = false): array
{
    $sql = 'SELECT id, customer_id, sub_name, address, contact_person, contact_number, status
            FROM sales_customer_sub_accounts WHERE customer_id = ?';
    if (!$includeInactive) {
        $sql .= " AND status = 'active'";
    }
    $sql .= ' ORDER BY sub_name';
    $stmt = $db->prepare($sql);
    $stmt->execute([$customerId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function hfSaveCustomerLocations(PDO $db, int $customerId, array $locations): void
{
    $db->prepare("UPDATE sales_customer_sub_accounts SET status = 'inactive' WHERE customer_id = ?")
        ->execute([$customerId]);
    $find = $db->prepare('SELECT id FROM sales_customer_sub_accounts WHERE customer_id = ? AND LOWER(TRIM(sub_name)) = LOWER(TRIM(?)) LIMIT 1');
    $update = $db->prepare("UPDATE sales_customer_sub_accounts SET address = ?, contact_person = ?, contact_number = ?, status = 'active' WHERE id = ?");
    $insert = $db->prepare("INSERT INTO sales_customer_sub_accounts (customer_id, sub_name, address, contact_person, contact_number, status) VALUES (?, ?, ?, ?, ?, 'active')");
    foreach ($locations as $location) {
        if (!is_array($location)) {
            continue;
        }
        $rawName = $location['sub_name'] ?? $location['name'] ?? '';
        $rawAddress = $location['address'] ?? '';
        $name = function_exists('hfPlainText') ? hfPlainText($rawName, 200, false) : trim(strip_tags((string) $rawName));
        $address = function_exists('hfPlainText') ? hfPlainText($rawAddress, 500, true) : trim(strip_tags((string) $rawAddress));
        if ($name === '' || $address === '') {
            continue;
        }
        $find->execute([$customerId, $name]);
        $id = $find->fetchColumn();
        $contactPerson = function_exists('hfPlainText') ? hfPlainText($location['contact_person'] ?? '', 100, false) : trim(strip_tags((string) ($location['contact_person'] ?? '')));
        $params = [$address, $contactPerson ?: null,
            trim((string) ($location['contact_number'] ?? '')) ?: null];
        if ($id) {
            $update->execute(array_merge($params, [(int) $id]));
        } else {
            $insert->execute(array_merge([$customerId, $name], $params));
        }
    }
}
