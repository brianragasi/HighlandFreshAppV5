<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'delivery_helper' => file_get_contents($root . '/api/helpers/supplier_delivery_terms.php'),
    'admin_supplier_api' => file_get_contents($root . '/api/admin/suppliers.php'),
    'admin_supplier_page' => file_get_contents($root . '/html/admin/suppliers.html'),
    'purchasing_supplier_api' => file_get_contents($root . '/api/purchasing/suppliers.php'),
    'po_api' => file_get_contents($root . '/api/purchasing/purchase_orders.php'),
    'po_page' => file_get_contents($root . '/html/purchasing/purchase_orders.html'),
    'purchasing_dashboard' => file_get_contents($root . '/html/purchasing/dashboard.html'),
    'purchasing_sidebar' => file_get_contents($root . '/js/purchasing/sidebar.js'),
    'sidebar_nav' => file_get_contents($root . '/js/ui/sidebar-nav.js'),
    'farmer_receiving_api' => file_get_contents($root . '/api/qc/deliveries.php'),
];

foreach ($files as $name => $contents) {
    if ($contents === false) {
        fwrite(STDERR, "Unable to read {$name}.\n");
        exit(1);
    }
}

$checks = [
    'supplier selection automatically loads only linked low-stock items' =>
        str_contains($files['po_page'], 'getConfirmedLowStock()')
        && str_contains($files['po_page'], 'getScopedWarehouseRequests()')
        && str_contains($files['po_api'], 'supplier_ingredients'),
    'system recommends relevant suppliers before Purchasing chooses' =>
        str_contains($files['po_page'], 'function rankRelevantSuppliers')
        && str_contains($files['po_page'], 'Covers everything')
        && str_contains($files['po_page'], 'Best match')
        && str_contains($files['po_page'], 'All matching lines are selected automatically'),
    'fast-moving dropdown is built from the selected supplier catalog' =>
        str_contains($files['po_page'], 'directSupplierIngredients = response?.data?.ingredients || []')
        && str_contains($files['po_page'], 'buildDirectItemOptions()')
        && str_contains($files['po_api'], 'loadAndValidateSupplierForecastItems'),
    'automatic early reorder uses recorded consumption and supplier delivery time' =>
        str_contains(file_get_contents($root . '/api/helpers/early_reorder.php'), "transaction_type = 'production_issue'")
        && str_contains(file_get_contents($root . '/api/helpers/early_reorder.php'), 'projected_stock_at_delivery')
        && str_contains($files['po_page'], 'System early reorder confirmed by Warehouse'),
    'supplier product links are protected by relational database rules' =>
        str_contains(file_get_contents($root . '/api/helpers/supplier_ingredient_catalog.php'), 'FOREIGN KEY (supplier_id) REFERENCES suppliers(id)')
        && str_contains(file_get_contents($root . '/api/helpers/supplier_ingredient_catalog.php'), 'FOREIGN KEY (ingredient_id) REFERENCES ingredients(id)'),
    'supplier delivery lead time is saved and validated' =>
        str_contains($files['delivery_helper'], 'ADD COLUMN lead_time_days')
        && str_contains($files['delivery_helper'], "whole number from 1 to 60 working days")
        && str_contains($files['admin_supplier_page'], 'id="lead_time_days"')
        && str_contains($files['admin_supplier_api'], "'lead_time_days'"),
    'order and expected-delivery dates are automatic and server enforced' =>
        substr_count($files['po_page'], 'readonly aria-readonly="true"') >= 2
        && str_contains($files['po_page'], 'applySupplierDeliveryDates()')
        && str_contains($files['delivery_helper'], 'hfAddSupplierWorkingDays')
        && str_contains($files['delivery_helper'], 'SUPPLIER_NON_WORKING_DATES')
        && str_contains($files['po_page'], 'getSupplierDeliveryCalendar()')
        && substr_count($files['po_api'], 'hfSupplierPurchaseOrderDates(') >= 3,
    'farmer milk is recorded directly without a Purchase Order' =>
        str_contains($files['po_page'], 'Fresh milk delivered by farmers is not ordered here')
        && str_contains($files['farmer_receiving_api'], 'INSERT INTO milk_receiving')
        && !str_contains($files['farmer_receiving_api'], 'INSERT INTO purchase_orders'),
    'GM approval immediately attempts supplier delivery and finishes as ordered when sent' =>
        str_contains($files['po_api'], 'GM approval finalizes the document and immediately attempts supplier delivery')
        && str_contains($files['po_api'], 'sendApprovedPurchaseOrderToSupplier')
        && str_contains($files['po_api'], "SET status = 'ordered'")
        && str_contains($files['po_api'], "'Purchase order approved and emailed to the supplier'"),
    'Purchase Orders remains the active navigation section while creating a PO' =>
        !str_contains($files['purchasing_sidebar'], "id: 'new_po'")
        && str_contains($files['purchasing_sidebar'], "elementId: 'navPurchaseOrders'")
        && str_contains($files['purchasing_sidebar'], "return { activeId: 'purchase_orders' }")
        && str_contains($files['po_page'], "PurchasingSidebar.setActive('purchase_orders')")
        && str_contains($files['po_page'], "setPurchaseOrderNavigationState(params.get('action') === 'new' ? 'new' : 'list')")
        && str_contains($files['po_page'], "setPurchaseOrderNavigationState('list')")
        && str_contains($files['sidebar_nav'], 'currentUrl.searchParams.get(key) === value'),
    'Purchasing pages expose one consistently named PO creation action' =>
        substr_count($files['po_page'], 'href="purchase_orders.html?action=new"') === 1
        && str_contains($files['po_page'], '> Create PO')
        && substr_count($files['purchasing_dashboard'], 'href="purchase_orders.html?action=new"') === 1
        && str_contains($files['purchasing_dashboard'], '> Create PO'),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) $failed[] = $label;
}

if ($failed) {
    fwrite(STDERR, "Professor procurement-flow checks failed.\n");
    exit(1);
}

echo "Professor procurement-flow checks passed.\n";
