<?php

function financeIntegrityAssert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Finance integrity test failed: {$message}\n");
        exit(1);
    }
}

$payablesApi = file_get_contents(dirname(__DIR__) . '/api/finance/payables.php');
$dashboardApi = file_get_contents(dirname(__DIR__) . '/api/finance/dashboard.php');
$purchasingApi = file_get_contents(dirname(__DIR__) . '/api/purchasing/purchase_orders.php');
$payablesUi = file_get_contents(dirname(__DIR__) . '/html/finance/payables.html');
$dashboardUi = file_get_contents(dirname(__DIR__) . '/html/finance/dashboard.html');
$receivingUi = file_get_contents(dirname(__DIR__) . '/html/warehouse/raw/receive_deliveries.html');
$financeService = file_get_contents(dirname(__DIR__) . '/js/finance/finance.service.js');
$purchasingService = file_get_contents(dirname(__DIR__) . '/js/purchasing/purchasing.service.js');

financeIntegrityAssert(
    str_contains($payablesApi, "case 'record_rr_invoice_number'")
        && str_contains($payablesApi, "RECORD_LATE_SUPPLIER_INVOICE")
        && str_contains($payablesApi, "FOR UPDATE"),
    'late invoice recovery must be routed, audited, and locked'
);
financeIntegrityAssert(
    str_contains($payablesApi, 'This Receiving Report already has an invoice number')
        && str_contains($payablesApi, 'already linked to PO'),
    'late invoice recovery must not replace or silently reuse an invoice number'
);
financeIntegrityAssert(
    str_contains($payablesApi, 'was already paid under PO')
        && str_contains($payablesApi, 'confirmed_release = 1'),
    'supplier payment must stop an invoice number already paid for the same supplier'
);
financeIntegrityAssert(
    str_contains($payablesApi, 'Combine every invoice page into one PDF before payment')
        && str_contains($payablesUi, 'Combined Supplier Invoice PDF'),
    'multi-invoice POs must require an explicit combined evidence packet'
);
financeIntegrityAssert(
    str_contains($dashboardApi, "FROM po_payments")
        && str_contains($dashboardApi, "confirmed_release = 1")
        && str_contains($dashboardApi, "pp.amount_paid AS total_amount")
        && str_contains($dashboardApi, "pp.payment_date"),
    'dashboard disbursements must use released payment ledger rows and dates'
);
financeIntegrityAssert(
    str_contains($dashboardApi, "po.status IN ('received', 'partial_received', 'closed')")
        && str_contains($dashboardApi, 'po.approved_by IS NOT NULL')
        && str_contains($dashboardApi, 'balance_due > 0'),
    'dashboard payables must use the canonical approved/received remaining balance'
);
financeIntegrityAssert(
    str_contains($dashboardApi, 'legacy_payment_records')
        && str_contains($dashboardUi, 'legacyFinanceAlert'),
    'legacy evidence gaps must be disclosed instead of counted as verified disbursements'
);
financeIntegrityAssert(
    str_contains($receivingUi, 'invoiceNotSupplied')
        && str_contains($purchasingApi, 'invoice_not_supplied'),
    'Warehouse must explicitly enter an invoice number or acknowledge it was not supplied'
);
financeIntegrityAssert(
    str_contains($payablesUi, 'invoiceRecoveryModal')
        && str_contains($financeService, 'recordReceivingInvoiceNumber'),
    'Finance UI and service must expose the audited missing-invoice recovery action'
);
financeIntegrityAssert(
    str_contains($payablesApi, 'rr_verified_count')
        && str_contains($payablesApi, 'rr_invoice_count')
        && str_contains($payablesUi, 'RR recorded:')
        && str_contains($payablesUi, 'Purchaser verification:')
        && !str_contains($payablesUi, 'report${reports.length === 1 ? \'\' : \'s\'} checked'),
    'an existing RR must be shown separately from Purchaser verification and invoice readiness'
);
financeIntegrityAssert(
    !str_contains($purchasingApi, "case 'update_payment'")
        && !str_contains($purchasingService, 'updatePaymentStatus'),
    'Purchasing must not expose a shortcut that changes supplier payment status without evidence'
);

echo "Finance payables integrity checks passed.\n";
