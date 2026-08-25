<?php

$page = file_get_contents(__DIR__ . '/../html/purchasing/purchase_orders.html');
$service = file_get_contents(__DIR__ . '/../js/purchasing/purchasing.service.js');
$api = file_get_contents(__DIR__ . '/../api/purchasing/purchase_orders.php');
$pdf = file_get_contents(__DIR__ . '/../api/helpers/purchase_order_pdf.php');
$dashboard = file_get_contents(__DIR__ . '/../html/purchasing/dashboard.html');
$gmApi = file_get_contents(__DIR__ . '/../api/admin/gm_approvals.php');
$gmPage = file_get_contents(__DIR__ . '/../html/admin/gm_approvals.html');

if ($page === false || $service === false || $api === false || $pdf === false || $dashboard === false || $gmApi === false || $gmPage === false) {
    fwrite(STDERR, "Unable to load supplier-first PO implementation files.\n");
    exit(1);
}

$checks = [
    'Purchaser starts from a clearly named New PO button' =>
        str_contains($page, '<span class="hidden md:inline">New PO</span>')
        && str_contains($page, '<i class="fas fa-file-circle-plus"></i> New PO'),
    'Visible form starts with supplier and removes the Warehouse PRS field' =>
        str_contains($page, '>Supplier *</span>')
        && str_contains($page, 'Choose a supplier for the confirmed needs')
        && !str_contains($page, '<span class="label-text">Warehouse PRS *</span>')
        && !str_contains($page, 'id="poPurchaseRequest"'),
    'Dashboard opens the supplier-first workbench without a PRS query field' =>
        substr_count($dashboard, "const prLink = 'purchase_orders.html?action=new';") >= 2
        && !str_contains($dashboard, 'purchase_orders.html?action=new&pr_id='),
    'Supplier selection filters outstanding lines across Warehouse requests' =>
        str_contains($page, 'getScopedWarehouseRequests().flatMap(pr =>')
        && str_contains($page, 'source_purchase_request_id: Number(pr.id)')
        && str_contains($page, 'suppliedByIngredientId.has(Number(item.ingredient_id))'),
    'Relevant suppliers are ranked and every matching line starts selected' =>
        str_contains($page, 'function rankRelevantSuppliers')
        && str_contains($page, 'Best supplier coverage is shown first')
        && str_contains($page, 'covers ${Number(supplier.confirmed_coverage_count || 0)} of ${needCount}')
        && str_contains($page, 'checked aria-label="Include')
        && !str_contains($page, 'focusSupplierForStockItem'),
    'Uncovered materials use a compact decision table with supplier help' =>
        str_contains($page, '<th>Still needed</th>')
        && str_contains($page, '<th>Why it is here</th>')
        && str_contains($page, 'focusSuppliersForConfirmedItem')
        && str_contains($page, 'Find supplier')
        && !str_contains($page, '<article class="rounded-lg border border-base-300 bg-base-100 p-3'),
    'Every selected item keeps its Warehouse confirmation link' =>
        str_contains($page, 'source_pr_number: pr.validation_number')
        && str_contains($page, 'source_purchase_request_id: Number(pr.id)')
        && str_contains($page, 'Confirmed shortage: ${escapeHtml(item.source_pr_number)}'),
    'Supplier filtering explains shown, total, omitted, repeated, and source counts' =>
        str_contains($page, 'shown for this supplier')
        && str_contains($page, 'total open')
        && str_contains($page, 'not shown (other supplier or unlinked)')
        && str_contains($page, 'repeated product record')
        && !str_contains($page, 'if (!summary || !supplier || !eligiblePrItems.length)'),
    'Browser saves through the consolidated supplier-first endpoint' =>
        str_contains($page, 'createSupplierPurchaseOrder(data)')
        && str_contains($service, 'action=create_supplier_po'),
    'Successful supplier PO refreshes all remaining validated demand' =>
        str_contains($page, 'async function continueSupplierFirstAfterPO(po)')
        && str_contains($page, "supplierSelect.value = ''")
        && str_contains($page, 'Choose the next supplier to continue purchasing validated low-stock demand.')
        && str_contains($page, 'await continueSupplierFirstAfterPO(po);'),
    'Server accepts one supplier PO with line-level stock-confirmation links' =>
        str_contains($api, "case 'create_supplier_po':")
        && str_contains($api, 'loadAndValidateSupplierFirstWarehouseItems')
        && str_contains($api, 'insertStockValidationItemPOAllocation')
        && str_contains($api, 'source_stock_validation_ids'),
    'Relevant supplier lookup covers ingredients and MRO materials' =>
        str_contains(file_get_contents(__DIR__ . '/../api/purchasing/suppliers.php'), "getParam('mro_ids', '')")
        && str_contains(file_get_contents(__DIR__ . '/../api/purchasing/suppliers.php'), 'matching_ingredient_ids')
        && str_contains(file_get_contents(__DIR__ . '/../api/purchasing/suppliers.php'), 'matching_mro_ids'),
    'Consolidated PO keeps one header while updating every stock confirmation' =>
        str_contains($api, "insertPOHeaderFromSplit(\n                    \$db,\n                    \$poNumber,\n                    \$supplierId,\n                    null,")
        && str_contains($api, 'foreach ($sourceValidationIds as $sourceValidationId)')
        && str_contains($api, 'recomputeStockValidationState'),
    'Duplicate supplier commitments are rejected' =>
        str_contains($api, 'findExactActiveSupplierFirstPO')
        && str_contains($api, 'An identical active Purchase Order already exists'),
    'Purchaser can add a separately controlled early-reorder line' =>
        str_contains($page, 'id="addForecastItemButton"')
        && str_contains($page, 'Add early-reorder item')
        && str_contains($page, 'Only products sold by the selected supplier appear here.')
        && str_contains($page, 'function addForecastLineItem()')
        && str_contains($page, 'forecast_reason: reason')
        && str_contains($api, 'loadAndValidateSupplierForecastItems')
        && str_contains($api, "'procurement_source' => 'purchaser_forecast'")
        && str_contains($api, 'explain the buffer in 10 to 500 characters'),
    'Forecast lines are clearly visible to the GM' =>
        str_contains($gmApi, 'poi.procurement_source, poi.forecast_reason, poi.notes')
        && str_contains($gmPage, 'Purchasing early-reorder exception')
        && str_contains($gmPage, 'Confirmed Warehouse shortage')
        && str_contains($gmPage, 'System early reorder · Warehouse confirmed'),
    'Purchasing decisions are visually outside the PO document' =>
        str_contains($page, 'id="purchasingWorkspaceTitle"')
        && str_contains($page, 'id="purchaseOrderDocument"')
        && str_contains($page, 'Remaining materials need another decision')
        && strpos($page, 'id="purchasingDecisionPanel"') < strpos($page, 'id="purchaseOrderDocument"')
        && strpos($page, 'id="addForecastItemButton"') < strpos($page, 'id="purchaseOrderDocument"'),
    'PO document uses a formal compact business layout' =>
        str_contains($page, '>PURCHASE ORDER</h2>')
        && str_contains($page, '<th class="min-w-64">Item</th><th>Order Quantity</th><th>Unit</th><th class="text-right">Unit Price</th><th class="text-right">Amount</th>')
        && str_contains($page, 'id="poSubtotal"')
        && str_contains($page, 'Move out of this PO')
        && str_contains($page, 'function renderExcludedSupplierItems'),
    'PO saves both supplier packages and the converted Warehouse quantity' =>
        str_contains($api, "'supplier_order_quantity' => \"ALTER TABLE `purchase_order_items`")
        && str_contains($api, 'function applySupplierOrderTerms')
        && str_contains($api, 'stock_quantity_per_supplier_unit')
        && str_contains($api, 'supplier_order_unit_price'),
    'Computed stock-unit prices retain package-conversion precision' =>
        str_contains($api, "\$prItem['item_description'] . ' unit price',\n                0.000001,\n                999999.999999,\n                6")
        && !str_contains($api, 'if ($rowQty * $unitPrice > 9999999999.99)'),
    'Formal supplier PDF uses the supplier-facing quantity and price' =>
        str_contains($pdf, "\$item['supplier_order_quantity']")
        && str_contains($pdf, "\$item['supplier_order_unit']")
        && str_contains($pdf, "\$item['supplier_order_unit_price']"),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "Failed: {$label}.\n");
        exit(1);
    }
}

echo "Supplier-first Purchase Order flow tests passed.\n";
