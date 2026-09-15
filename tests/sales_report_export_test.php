<?php
require_once dirname(__DIR__) . '/api/helpers/sales_report_export.php';
function exportAssert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
exportAssert(hfSalesExportDatesValid('2026-09-01', '2026-09-16'), 'Valid period');
exportAssert(!hfSalesExportDatesValid('2026-02-30', '2026-03-01'), 'Invalid calendar date');
exportAssert(!hfSalesExportDatesValid('2026-09-16', '2026-09-01'), 'Reversed period');
exportAssert(!hfSalesExportDatesValid('2025-01-01', '2026-01-02'), 'Oversized period');
exportAssert(hfSalesExportDatesValid('2024-01-01', '2024-12-31'), 'Leap year supported');
exportAssert(hfSalesPdfWrapped('0', 120, 8) === ['0'], 'Zero must be printed as zero, not missing');
$report = ['start' => '2026-09-01', 'end' => '2026-09-16', 'generated_at' => '2026-09-16 12:00:00',
    'summary' => ['total_sales' => 800, 'total_orders' => 4, 'avg_order_value' => 200, 'collections' => 400, 'by_type' => []],
    'trend' => [], 'customers' => [], 'products' => [], 'orders' => []];
for ($id = 1; $id <= 65; $id++) {
    $report['orders'][] = ['id' => $id, 'created_at' => '2026-09-16 12:00:00', 'order_number' => 'SO-20260916-' . $id,
        'customer_name' => $id === 1 ? '=SUM(1,2)' : 'Professor Classroom Cooperative - Long Customer Name ' . $id,
        'customer_type' => 'feeding_program', 'total_amount' => 200, 'payment_type' => 'credit', 'status' => 'approved'];
}
$csv = hfBuildSalesReportCsv($report);
exportAssert(str_starts_with($csv, "\xEF\xBB\xBF"), 'CSV UTF-8 BOM');
exportAssert(str_contains($csv, "'=SUM(1,2)"), 'Formula-like customer names escaped');
exportAssert(str_contains($csv, 'SO-20260916-65'), 'CSV not limited to first 50');
exportAssert(str_contains($csv, 'Long Customer Name 65'), 'CSV keeps full customer names');
$pdf = hfBuildSalesReportPdf($report);
exportAssert(str_starts_with($pdf, '%PDF-1.4'), 'Real PDF header');
exportAssert(str_contains($pdf, '(SO-20260916-65)'), 'PDF not limited to first 50');
exportAssert(str_contains($pdf, '\\(continued\\)'), 'Multi-page repeat headings');
exportAssert(preg_match('/\/Type \/Pages .*\/Count ([2-9]|[1-9][0-9]+)/', $pdf) === 1, 'Multi-page document');
exportAssert(str_contains($pdf, 'PHP 800.00'), 'Summary included');
$empty = $report;
$empty['orders'] = [];
exportAssert(str_contains(hfBuildSalesReportPdf($empty), 'No records for this period.'), 'Empty period is explicit');
if (($argv[1] ?? '') === '--render-fixture') {
    $directory = dirname(__DIR__) . '/tmp/pdfs';
    if (!is_dir($directory)) mkdir($directory, 0777, true);
    file_put_contents($directory . '/sales-export-long.pdf', $pdf);
}
echo "PASS: PDF and CSV generation, 65-order pagination, full names, date validation, leap year, empty reports, and spreadsheet formula safety.\n";
