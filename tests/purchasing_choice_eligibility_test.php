<?php

$root = dirname(__DIR__);

$sources = [
    'supplier_api' => file_get_contents($root . '/api/purchasing/suppliers.php'),
    'canvassing_api' => file_get_contents($root . '/api/purchasing/canvassing.php'),
    'po_api' => file_get_contents($root . '/api/purchasing/purchase_orders.php'),
    'pr_api' => file_get_contents($root . '/api/purchasing/purchase_requests.php'),
    'catalog' => file_get_contents($root . '/api/helpers/supplier_ingredient_catalog.php'),
    'po_page' => file_get_contents($root . '/html/purchasing/purchase_orders.html'),
    'canvassing_page' => file_get_contents($root . '/html/purchasing/canvassing.html'),
];

foreach ($sources as $name => $source) {
    if ($source === false) {
        fwrite(STDERR, "Unable to read {$name}.\n");
        exit(1);
    }
}

$checks = [
    'Direct PO supplier list requests orderable suppliers' =>
        substr_count($sources['po_page'], "orderable: 1") >= 2,
    'Orderable supplier query requires an active ingredient link' =>
        str_contains($sources['supplier_api'], 'if ($orderable)')
        && str_contains($sources['supplier_api'], 'JOIN ingredients i ON i.id = si.ingredient_id AND i.is_active = 1'),
    'Supplier directory can still filter to matching accredited ingredients for other screens' =>
        str_contains($sources['supplier_api'], "getParam('ingredient_ids', '')")
        && str_contains($sources['supplier_api'], 'matching_ingredient_count')
        && str_contains($sources['supplier_api'], 'si.ingredient_id IN ($matchingPlaceholders)'),
    'Canvassing ignores archived suppliers' =>
        str_contains($sources['canvassing_api'], 'JOIN suppliers s ON q.supplier_id = s.id AND s.is_active = 1'),
    'Canvassing ignores removed ingredient links' =>
        str_contains($sources['canvassing_api'], 'active_link.is_active = 1')
        && str_contains($sources['canvassing_api'], 'active_link.id IS NOT NULL'),
    'Adding a quote requires an active ingredient' =>
        str_contains($sources['canvassing_api'], 'JOIN ingredients i ON i.id = si.ingredient_id AND i.is_active = 1'),
    'Final PO creation rechecks the active supplier' =>
        str_contains($sources['po_api'], 'JOIN suppliers s ON s.id = q.supplier_id AND s.is_active = 1'),
    'Catalog returns only active ingredients and suppliers' =>
        str_contains($sources['catalog'], 'JOIN ingredients i ON i.id = si.ingredient_id AND i.is_active = 1')
        && str_contains($sources['catalog'], 's.is_active = 1'),
    'Purchaser builds one supplier PO from confirmed warehouse shortages' =>
        str_contains($sources['po_page'], 'Selected supplier items')
        && str_contains($sources['po_page'], 'appendSupplierRequestedItemRow')
        && str_contains($sources['po_page'], 'toggleSupplierRequestedItem')
        && str_contains($sources['po_page'], 'eligiblePrItems')
        && str_contains($sources['po_page'], 'stock_validation_item_id')
        && str_contains($sources['po_api'], "case 'create_supplier_po':"),
    'Old automatic canvassing page forwards to supplier-first PO builder' =>
        str_contains($sources['canvassing_page'], 'purchase_orders.html?action=new')
        && !str_contains($sources['po_page'], "window.location.replace('canvassing.html')"),
    'Partially ordered PRS records remain in the Purchaser inbox' =>
        str_contains($sources['pr_api'], "'partially_converted'")
        && str_contains($sources['pr_api'], "AS allocated_quantity")
        && str_contains($sources['pr_api'], "['remaining_quantity']"),
    'Server checks remaining quantity and locks PRS lines against double ordering' =>
        str_contains($sources['po_api'], 'lockAndValidateRemainingPRSQuantities')
        && str_contains($sources['po_api'], 'FOR UPDATE')
        && str_contains($sources['po_api'], "po.status NOT IN ('cancelled', 'rejected')"),
    'Exact active PO duplicates are blocked independently of remaining PRS quantity' =>
        str_contains($sources['po_api'], 'findExactActiveDuplicatePO')
        && str_contains($sources['po_api'], 'An identical active Purchase Order already exists')
        && str_contains($sources['po_api'], 'buildPurchaseOrderLineSignature'),
    'Browser prevents repeated clicks while PO creation is in flight' =>
        str_contains($sources['po_page'], 'createPOInFlight')
        && str_contains($sources['po_page'], 'id="createPOButton"')
        && str_contains($sources['po_page'], 'createButton.disabled = true'),
    'Manual supplier choice still enforces accreditation and agreed price' =>
        str_contains($sources['po_api'], 'validateManualSupplierAssignments')
        && str_contains($sources['po_api'], 'supplier_ingredients')
        && str_contains($sources['po_api'], 'saved agreed price'),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "Failed: {$label}.\n");
        exit(1);
    }
}

echo "Purchasing supplier-first confirmation and eligibility tests passed.\n";
