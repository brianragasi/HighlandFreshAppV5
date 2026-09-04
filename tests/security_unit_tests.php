<?php
/**
 * Security unit tests for instructor demonstration.
 *
 * Run:
 *   php tests/security_unit_tests.php
 */

require_once __DIR__ . '/../api/helpers/security_unit_helpers.php';
require_once __DIR__ . '/../api/helpers/plain_text.php';

$results = [];

function recordResult(&$results, $id, $target, $passed, $expected, $actual) {
    $results[] = [
        'id' => $id,
        'target' => $target,
        'passed' => (bool) $passed,
        'expected' => $expected,
        'actual' => $actual,
    ];
}

recordResult(
    $results,
    'UT-101',
    'validateEmailFormat(email)',
    validateEmailFormat('test@com') === false && validateEmailFormat('@@domain.com') === false,
    'Return false for badly typed email addresses',
    'test@com => ' . (validateEmailFormat('test@com') ? 'true' : 'false') . ', @@domain.com => ' . (validateEmailFormat('@@domain.com') ? 'true' : 'false')
);

recordResult(
    $results,
    'UT-102',
    'validatePasswordStrength(pwd)',
    validatePasswordStrength('Short1!') === false && validatePasswordStrength('Password1') === false,
    'Return false for short password or password without special character',
    'Short1! => ' . (validatePasswordStrength('Short1!') ? 'true' : 'false') . ', Password1 => ' . (validatePasswordStrength('Password1') ? 'true' : 'false')
);

$password = 'StrongPass1!';
$hashOne = hashUserPassword($password);
$hashTwo = hashUserPassword($password);
recordResult(
    $results,
    'UT-103',
    'hashUserPassword(plainText)',
    is_string($hashOne) && strlen($hashOne) >= 60 && $hashOne !== $password && $hashOne !== $hashTwo,
    'Return secure 60+ character salted hash, different each time',
    'hash length = ' . strlen($hashOne) . ', unique hash = ' . ($hashOne !== $hashTwo ? 'yes' : 'no')
);

recordResult(
    $results,
    'UT-104',
    'verifyPasswordMatch(plain, hash)',
    verifyPasswordMatch($password, $hashOne) === true && verifyPasswordMatch($password . 'x', $hashOne) === false,
    'Return true for correct password and false for changed password',
    'correct => ' . (verifyPasswordMatch($password, $hashOne) ? 'true' : 'false') . ', changed => ' . (verifyPasswordMatch($password . 'x', $hashOne) ? 'true' : 'false')
);

$token = generateSessionToken(123);
$tokenParts = explode('.', $token);
recordResult(
    $results,
    'UT-105',
    'generateSessionToken(userId)',
    count($tokenParts) === 3 && strlen($token) > 80,
    'Return structured session token',
    'parts = ' . count($tokenParts) . ', length = ' . strlen($token)
);

recordResult(
    $results,
    'UT-106',
    'isTokenExpired(timestamp)',
    isTokenExpired(time() - 60) === true && isTokenExpired(time() + 60) === false,
    'Return true for old timestamp and false for future timestamp',
    'old => ' . (isTokenExpired(time() - 60) ? 'true' : 'false') . ', future => ' . (isTokenExpired(time() + 60) ? 'true' : 'false')
);

$unsafe = '<script>malicious()</script>';
$clean = sanitizeHtmlInput($unsafe);
recordResult(
    $results,
    'UT-107',
    'sanitizeHtmlInput(userInput)',
    strpos($clean, '<script>') === false && strpos($clean, '&lt;script&gt;') !== false,
    'Return encoded text with no active script tag',
    $clean
);

$storedAttack = '<img src=x onerror="alert(1)">Approved <b>after review</b>';
$plainText = hfPlainText($storedAttack, 1000, true);
recordResult(
    $results,
    'UT-108',
    'hfPlainText(userInput)',
    strpos($plainText, '<') === false
        && stripos($plainText, 'onerror') === false
        && $plainText === 'Approved after review',
    'Remove active HTML while preserving the words the user entered',
    $plainText
);

$gmPage = file_get_contents(__DIR__ . '/../html/admin/gm_approvals.html');
$gmDashboard = file_get_contents(__DIR__ . '/../html/admin/dashboard.html');
$unsafeGmFragments = [
    '${po.supplier_name}',
    '${item.item_description}',
    '${req.item_name}',
    '${req.notes}',
    '${alert.supplier_name',
    '<span>${message}</span>',
];
$unsafeGmFragmentsFound = array_values(array_filter(
    $unsafeGmFragments,
    static fn($fragment) => strpos($gmPage, $fragment) !== false
));
recordResult(
    $results,
    'UT-109',
    'GM approval output encoding',
    empty($unsafeGmFragmentsFound)
        && strpos($gmPage, 'escapeHtml(po.supplier_name)') !== false
        && strpos($gmPage, 'messageElement.textContent') !== false
        && strpos($gmDashboard, 'escapeHtml(String(sourceId))') === false
        && strpos($gmDashboard, '${formatQty(stock)}${escapeHtml(unit)}') !== false,
    'All database text shown on the GM approval screen is escaped or assigned as text',
    empty($unsafeGmFragmentsFound)
        ? 'No known unsafe GM output patterns found'
        : 'Unsafe patterns: ' . implode(', ', $unsafeGmFragmentsFound)
);

$adminOutputChecks = [
    'customers' => [
        'file' => __DIR__ . '/../html/admin/customers.html',
        'required' => ['messageElement.textContent', 'escapeHtml(item.name'],
        'forbidden' => ["deleteRecord(\${item.id}, '\${item.name}')", '<span>${message}</span>'],
    ],
    'ingredients' => [
        'file' => __DIR__ . '/../html/admin/ingredients.html',
        'required' => ['messageElement.textContent', 'escapeHtml(i.ingredient_name'],
        'forbidden' => ['<span>${message}</span>', '${i.ingredient_name}</'],
    ],
    'qc standards' => [
        'file' => __DIR__ . '/../html/admin/qc-standards.html',
        'required' => ['messageElement.textContent', "escapeHtml(item.description || '-')"],
        'forbidden' => ['<span>${msg}</span>', "deleteGrading(\${item.id}, '\${item.standard_name}')"],
    ],
    'recalls' => [
        'file' => __DIR__ . '/../html/admin/recalls.html',
        'required' => ['messageElement.textContent', 'escapeHtml(r.reason)'],
        'forbidden' => ['<span>${msg}</span>', '${r.reason}</'],
    ],
    'orders' => [
        'file' => __DIR__ . '/../html/admin/orders.html',
        'required' => ['messageElement.textContent', "escapeHtml(order.customer_name || '—')"],
        'forbidden' => ['<span>${message}</span>', '${order.customer_name || \'—\'}</'],
    ],
];
$adminOutputFailures = [];
foreach ($adminOutputChecks as $pageName => $check) {
    $page = file_get_contents($check['file']);
    foreach ($check['required'] as $fragment) {
        if (strpos($page, $fragment) === false) {
            $adminOutputFailures[] = "{$pageName}: missing {$fragment}";
        }
    }
    foreach ($check['forbidden'] as $fragment) {
        if (strpos($page, $fragment) !== false) {
            $adminOutputFailures[] = "{$pageName}: unsafe {$fragment}";
        }
    }
}
recordResult(
    $results,
    'UT-110',
    'GM management page output encoding',
    empty($adminOutputFailures),
    'Saved names, notes, reasons, and messages are displayed as text on GM pages',
    empty($adminOutputFailures)
        ? 'All checked GM management pages use safe text output'
        : implode('; ', $adminOutputFailures)
);

$farmerPayload = 'test<svg/onload=alert(document.domain)>';
$farmerPage = file_get_contents(__DIR__ . '/../html/admin/farmers.html');
$farmerOutputChecks = [
    [__DIR__ . '/../html/qc/farmers.html', 'escapeHtml([f.first_name, f.last_name]'],
    [__DIR__ . '/../html/qc/milk_receiving.html', 'escapeHtml([d.farmer_first_name, d.farmer_last_name]'],
    [__DIR__ . '/../html/qc/milk_receiving.html', 'openGradeModalForDelivery('],
    [__DIR__ . '/../html/qc/milk_grading.html', 'escapeHtml([t.farmer_first_name, t.farmer_last_name]'],
    [__DIR__ . '/../html/qc/dashboard.html', 'escapeHtml(test.farmer_name'],
    [__DIR__ . '/../html/qc/expiry_management.html', 'escapeHtml(item.farmer_name'],
    [__DIR__ . '/../html/warehouse/raw/dashboard.html', 'escapeHtml(milk.farmer_name'],
    [__DIR__ . '/../html/warehouse/raw/milk_storage.html', 'escapeHtml(milk.farmer_name'],
];
$cleanFarmerPayload = hfPlainText($farmerPayload, 100, false);
recordResult(
    $results,
    'UT-111',
    'Farmer stored XSS protection',
    $cleanFarmerPayload === 'test'
        && strpos($farmerPage, 'escapeHtml(item.full_name || item.first_name') !== false
        && strpos($farmerPage, "archiveRecord(\${item.id}, '\${item.full_name") === false
        && array_reduce(
            $farmerOutputChecks,
            static fn(bool $safe, array $check): bool => $safe
                && strpos(file_get_contents($check[0]), $check[1]) !== false,
            true
        ),
    'Strip active markup before saving and encode farmer values before displaying them',
    'stored value = ' . $cleanFarmerPayload
);

$ingredientPayload = '<svg onload=alert(1)>';
$cleanIngredientPayload = hfPlainText($ingredientPayload, 160, false);
$adminIngredientApi = file_get_contents(__DIR__ . '/../api/admin/ingredients.php');
$supplierCatalogHelper = file_get_contents(__DIR__ . '/../api/helpers/supplier_ingredient_catalog.php');
$warehouseIngredientApi = file_get_contents(__DIR__ . '/../api/warehouse/raw/ingredients.php');
$warehouseMroApi = file_get_contents(__DIR__ . '/../api/warehouse/raw/mro.php');
$warehouseMroPage = file_get_contents(__DIR__ . '/../html/warehouse/raw/mro.html');
recordResult(
    $results,
    'UT-112',
    'Ingredient display label and storage location XSS protection',
    $cleanIngredientPayload === ''
        && strpos($adminIngredientApi, "'storage_location' => [160, false]") !== false
        && strpos($supplierCatalogHelper, '$allowedPackageTypes') !== false
        && strpos($supplierCatalogHelper, "'offer_label' => supplierCatalogNumber(") !== false
        && strpos($warehouseIngredientApi, "'storage_location' => [160, false]") !== false
        && strpos($warehouseIngredientApi, "'pack_label' => [50, false]") !== false
        && strpos($warehouseIngredientApi, 'New ingredients must be configured in Admin') !== false
        && strpos($warehouseMroApi, "hfPlainText(getParam('storage_location'), 160, false)") !== false
        && strpos($warehouseMroPage, '${safeStorageLocation}') !== false
        && strpos($warehouseMroPage, '>${item.storage_location}</p>') === false,
    'Remove active SVG markup before saving and encode storage labels before displaying them',
    'stored value = ' . ($cleanIngredientPayload === '' ? '[empty]' : $cleanIngredientPayload)
);

$roleGuard = file_get_contents(__DIR__ . '/../js/security/role-guard.js');
$adminUsersApi = file_get_contents(__DIR__ . '/../api/admin/users.php');
$adminDashboardApi = file_get_contents(__DIR__ . '/../api/admin/dashboard.php');
$adminPageFailures = [];
foreach (glob(__DIR__ . '/../html/admin/*.html') as $adminPagePath) {
    $adminPage = file_get_contents($adminPagePath);
    if (strpos($adminPage, 'id="roleGuardPending"') === false) {
        $adminPageFailures[] = basename($adminPagePath) . ': missing hidden-until-verified state';
    }
    if (strpos($adminPage, 'role-guard.js') === false) {
        $adminPageFailures[] = basename($adminPagePath) . ': missing role guard';
    }
}
recordResult(
    $results,
    'UT-113',
    'Admin role access control',
    empty($adminPageFailures)
        && strpos($roleGuard, "script?.dataset.allowedRoles") !== false
        && strpos($roleGuard, 'AuthService.fetchCurrentUser()') !== false
        && strpos($roleGuard, 'allowedRoles.includes(serverUser.role)') !== false
        && strpos($roleGuard, 'URLSearchParams') === false
        && strpos($adminUsersApi, "Auth::requireRole(['general_manager', 'admin'])") !== false
        && strpos($adminDashboardApi, "Auth::requireRole(['general_manager', 'admin'])") !== false,
    'Ignore URL role claims, verify the signed-in role with the server, and block QC from Admin data and Add User',
    empty($adminPageFailures)
        ? 'All Admin pages and both critical Admin endpoints are protected'
        : implode('; ', $adminPageFailures)
);

$qcOutputChecks = [
    'dashboard' => [__DIR__ . '/../html/qc/dashboard.html', 'escapeHtml(test.farmer_name'],
    'farmers' => [__DIR__ . '/../html/qc/farmers.html', 'escapeHtml([f.first_name, f.last_name]'],
    'milk receiving' => [__DIR__ . '/../html/qc/milk_receiving.html', 'escapeHtml(d.delivery_code'],
    'milk grading' => [__DIR__ . '/../html/qc/milk_grading.html', 'escapeHtml(t.test_code'],
    'batch release' => [__DIR__ . '/../html/qc/batch_release.html', 'escapeHtml(b.qc_notes'],
    'expiry management' => [__DIR__ . '/../html/qc/expiry_management.html', 'escapeHtml(t.source_product_name'],
    'disposals' => [__DIR__ . '/../html/qc/disposals.html', 'escapeHtml(parsed.notes'],
    'recalls' => [__DIR__ . '/../html/qc/recalls.html', 'escapeHtml(r.reason'],
    'label printing' => [__DIR__ . '/../html/qc/print-labels.html', 'escapeHtml(product)'],
    'daily report' => [__DIR__ . '/../html/qc/reports/daily.html', 'escapeHtml(x.product_name'],
    'farmer report' => [__DIR__ . '/../html/qc/reports/farmer_summary.html', 'escapeHtml(f.full_name'],
];
$qcApiChecks = [
    'batch release notes' => [__DIR__ . '/../api/qc/batch_release.php', "hfPlainText(getParam('qc_notes'"],
    'receiving notes' => [__DIR__ . '/../api/qc/deliveries.php', "hfPlainText(getParam('notes'"],
    'disposal reason' => [__DIR__ . '/../api/qc/disposals.php', "hfPlainText(getParam('disposal_reason'"],
    'expiry notes' => [__DIR__ . '/../api/qc/expiry_management.php', "hfPlainText(getParam('notes'"],
    'grading notes' => [__DIR__ . '/../api/qc/milk_grading.php', "hfPlainText(getParam('notes'"],
    'recall reason' => [__DIR__ . '/../api/qc/recalls.php', "'reason' => [1000, true]"],
    'recall condition notes' => [__DIR__ . '/../api/qc/recalls.php', 'hfPlainText($data[\'condition_notes\''],
    'safety notes' => [__DIR__ . '/../api/qc/safety_verification.php', "hfPlainText(getParam('notes'"],
];
$qcSecurityFailures = [];
foreach ($qcOutputChecks as $label => $check) {
    if (strpos(file_get_contents($check[0]), $check[1]) === false) {
        $qcSecurityFailures[] = "{$label}: saved text is not visibly encoded";
    }
}
foreach ($qcApiChecks as $label => $check) {
    if (strpos(file_get_contents($check[0]), $check[1]) === false) {
        $qcSecurityFailures[] = "{$label}: saved text is not cleaned";
    }
}
recordResult(
    $results,
    'UT-114',
    'QC stored XSS protection',
    empty($qcSecurityFailures),
    'Clean QC notes and reasons before saving, then encode saved values on every QC workflow screen',
    empty($qcSecurityFailures)
        ? 'All checked QC entry and display paths use plain text protection'
        : implode('; ', $qcSecurityFailures)
);

$operationalRolePages = [
    'Production' => [__DIR__ . '/../html/production', 'production_staff'],
    'Warehouse Raw' => [__DIR__ . '/../html/warehouse/raw', 'warehouse_raw'],
    'Warehouse Finished Goods' => [__DIR__ . '/../html/warehouse/fg', 'warehouse_fg'],
    'Sales' => [__DIR__ . '/../html/sales', 'sales_custodian'],
    'Cashier / POS' => [__DIR__ . '/../html/pos', 'cashier'],
    'Purchasing' => [__DIR__ . '/../html/purchasing', 'purchaser'],
    'Finance' => [__DIR__ . '/../html/finance', 'finance_officer'],
];
$operationalRoleFailures = [];
$operationalHtmlFiles = [];
foreach ($operationalRolePages as $label => [$directory, $role]) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
    foreach ($iterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'html') {
            continue;
        }
        $page = file_get_contents($file->getPathname());
        $operationalHtmlFiles[] = $file->getPathname();
        if (strpos($page, 'role-guard.js') === false || strpos($page, $role) === false) {
            $operationalRoleFailures[] = $label . ': ' . $file->getFilename() . ' is missing its server-verified page guard';
        }
    }
}
recordResult(
    $results,
    'UT-115',
    'All nine role areas enforce page access control',
    empty($operationalRoleFailures),
    'Every operational page loads role-guard.js with its assigned role',
    empty($operationalRoleFailures)
        ? 'All seven non-GM/non-QC role areas are guarded'
        : implode('; ', $operationalRoleFailures)
);

$unsafeNotificationPatterns = [
    '/<span[^>]*>\s*\$\{(?:message|msg|error\.message|e\.message)[^}]*\}<\/span>/i',
    '/<p[^>]*>[^<]*\$\{(?:error\.message|e\.message)[^}]*\}[^<]*<\/p>/i',
];
$unsafeNotificationFiles = [];
foreach ($operationalHtmlFiles as $path) {
    $page = file_get_contents($path);
    foreach ($unsafeNotificationPatterns as $pattern) {
        if (preg_match($pattern, $page)) {
            $unsafeNotificationFiles[] = str_replace(__DIR__ . '/../', '', $path);
            break;
        }
    }
}
$apiScript = file_get_contents(__DIR__ . '/../js/config/api.js');
recordResult(
    $results,
    'UT-116',
    'Shared modal notification layer and text-only errors',
    empty($unsafeNotificationFiles)
        && strpos($apiScript, "dialog.matches(':modal')") !== false
        && strpos($apiScript, 'host.appendChild(container)') !== false
        && strpos($apiScript, 'text.textContent = String(message') !== false,
    'Error messages render as text inside the newest native dialog top layer',
    empty($unsafeNotificationFiles)
        ? 'No raw notification/error-message HTML sinks found in the seven role areas'
        : 'Unsafe notification sinks: ' . implode(', ', $unsafeNotificationFiles)
);

$roleOutputChecks = [
    'Production' => [__DIR__ . '/../html/production/recipes.html', 'escapeHtml(recipe.special_instructions)'],
    'Warehouse Raw' => [__DIR__ . '/../html/warehouse/raw/receive_deliveries.html', 'escapeHtml(po.supplier_name)'],
    'Warehouse Finished Goods' => [__DIR__ . '/../html/warehouse/fg/delivery_receipts.html', 'escapeHtml(dr.customer_name)'],
    'Sales' => [__DIR__ . '/../html/sales/orders.html', 'escapeHtml(order.notes)'],
    'Cashier / POS' => [__DIR__ . '/../html/pos/history.html', 'escapeHtml(tx.customer_name)'],
    'Purchasing' => [__DIR__ . '/../html/purchasing/purchase_orders.html', 'escapeHtml(po.rejection_reason)'],
    'Finance' => [__DIR__ . '/../html/finance/payables.html', 'escapeHtml(pay.remarks'],
];
$roleOutputFailures = [];
foreach ($roleOutputChecks as $label => [$path, $requiredFragment]) {
    if (strpos(file_get_contents($path), $requiredFragment) === false) {
        $roleOutputFailures[] = $label . ': missing ' . $requiredFragment;
    }
}
recordResult(
    $results,
    'UT-117',
    'Stored XSS output encoding across the remaining seven roles',
    empty($roleOutputFailures),
    'Each role encodes representative saved names, notes, reasons, and codes before HTML rendering',
    empty($roleOutputFailures)
        ? 'Representative stored-text paths are encoded in all seven role areas'
        : implode('; ', $roleOutputFailures)
);

$nativeDialogFailures = [];
$allHtmlIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../html'));
foreach ($allHtmlIterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'html') {
        continue;
    }
    $page = file_get_contents($file->getPathname());
    preg_match_all('/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/i', $page, $scriptMatches);
    $inlineScripts = implode("\n", $scriptMatches[1] ?? []);
    if (preg_match('/(?<![\w$.])(?:window\.)?(?:alert|confirm|prompt)\s*\(/', $inlineScripts)) {
        $nativeDialogFailures[] = str_replace(__DIR__ . '/../', '', $file->getPathname());
    }
}
$allJsIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../js'));
foreach ($allJsIterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'js') {
        continue;
    }
    $normalizedPath = str_replace('\\', '/', $file->getPathname());
    if (str_contains($normalizedPath, '/vendor/') || str_ends_with($normalizedPath, '/ui/dialogs.js')) {
        continue;
    }
    if (preg_match('/(?<![\w$.])(?:window\.)?(?:alert|confirm|prompt)\s*\(/', file_get_contents($file->getPathname()))) {
        $nativeDialogFailures[] = str_replace(__DIR__ . '/../', '', $file->getPathname());
    }
}
recordResult(
    $results,
    'UT-118',
    'No browser-native localhost dialogs',
    empty($nativeDialogFailures),
    'Use the branded AppDialogs or toast UI instead of alert, confirm, or prompt',
    empty($nativeDialogFailures)
        ? 'No application page can display a browser-native “localhost says” dialog'
        : 'Native dialogs found in: ' . implode(', ', array_unique($nativeDialogFailures))
);

$passedCount = 0;
echo "Security Unit Test Results\n";
echo str_repeat('=', 80) . "\n";
foreach ($results as $result) {
    if ($result['passed']) {
        $passedCount++;
    }
    echo $result['id'] . ' | ' . ($result['passed'] ? 'PASS' : 'FAIL') . ' | ' . $result['target'] . "\n";
    echo 'Expected: ' . $result['expected'] . "\n";
    echo 'Actual:   ' . $result['actual'] . "\n";
    echo str_repeat('-', 80) . "\n";
}

echo "Passed {$passedCount} of " . count($results) . " tests.\n";
exit($passedCount === count($results) ? 0 : 1);
