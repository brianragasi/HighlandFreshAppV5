<?php
/** PDF/CSV rendering only; no database writes or browser-provided report totals. */
require_once __DIR__ . '/purchase_order_pdf.php';

function hfSalesExportDatesValid(string $start, string $end): bool {
    $a = DateTimeImmutable::createFromFormat('!Y-m-d', $start);
    $b = DateTimeImmutable::createFromFormat('!Y-m-d', $end);
    return $a && $b && $a->format('Y-m-d') === $start && $b->format('Y-m-d') === $end
        && $a <= $b && $a->diff($b)->days < 366;
}

function hfSalesExportLabel($value): string {
    $labels = ['walk_in' => 'Small Business / Retailer', 'distributor' => 'Wholesaler',
        'feeding_program' => 'Feeding Program', 'bank_transfer' => 'Bank Transfer'];
    return $labels[$value ?? ''] ?? ucwords(str_replace('_', ' ', (string) ($value ?: '-')));
}

/** Plain report sections used by both PDF and CSV, preserving full names. */
function hfSalesExportSections(array $report): array {
    $summary = $report['summary'];
    return [
        ['Summary', ['Measure', 'Value'], [
            ['Total Sales', hfPdfMoney($summary['total_sales'] ?? 0)],
            ['Orders', (string) ($summary['total_orders'] ?? 0)],
            ['Average Order Value', hfPdfMoney($summary['avg_order_value'] ?? 0)],
            ['Collections', hfPdfMoney($summary['collections'] ?? 0)],
        ], [373, 373]],
        ['Sales by Customer Type', ['Customer Type', 'Amount (PHP)'], array_map(fn($row) => [
            hfSalesExportLabel($row['type'] ?? ''), hfPdfMoney($row['amount'] ?? 0),
        ], $summary['by_type'] ?? []), [373, 373]],
        [empty($report['compact_trend']) ? 'Daily Sales Trend' : 'Daily Sales Trend - Dates with Orders', ['Date', 'Orders', 'Sales (PHP)'], array_map(fn($row) => [
            $row['date'], (string) ($row['orders'] ?? 0), hfPdfMoney($row['total'] ?? 0),
        ], $report['trend'] ?? []), [250, 246, 250]],
        ['Top Selling Products', ['Product / SKU', 'Qty Sold', 'Revenue (PHP)'], array_map(fn($row) => [
            ($row['product_name'] ?? '-') . ' / ' . ($row['sku'] ?? '-'),
            number_format((float) ($row['quantity_sold'] ?? 0), 0), hfPdfMoney($row['revenue'] ?? 0),
        ], $report['products'] ?? []), [436, 120, 190]],
        ['Top Customers', ['Customer', 'Orders', 'Total (PHP)'], array_map(fn($row) => [
            $row['customer_name'] ?? '-', (string) ($row['order_count'] ?? 0), hfPdfMoney($row['total_amount'] ?? 0),
        ], $report['customers'] ?? []), [436, 120, 190]],
        ['Sales Details - All Matching Orders', ['Date', 'Order #', 'Customer', 'Type', 'Amount (PHP)', 'Payment', 'Status'], array_map(fn($row) => [
            substr($row['created_at'] ?? '', 0, 10), $row['order_number'] ?? 'ORD-' . $row['id'],
            $row['customer_name'] ?? 'Unknown', hfSalesExportLabel($row['customer_type'] ?? ''),
            hfPdfMoney($row['total_amount'] ?? 0), hfSalesExportLabel($row['payment_type'] ?? 'credit'),
            hfSalesExportLabel($row['status'] ?? ''),
        ], $report['orders'] ?? []), [62, 102, 186, 113, 94, 75, 114]],
    ];
}

function hfBuildSalesReportCsv(array $report): string {
    $stream = fopen('php://temp', 'w+');
    fwrite($stream, "\xEF\xBB\xBF");
    $write = static function (array $cells) use ($stream): void {
        $cells = array_map(static function ($value): string {
            $text = (string) $value;
            // Spreadsheet formula prevention, including names with leading whitespace.
            return preg_match('/^[\s]*[=+@-]/u', $text) ? "'" . $text : $text;
        }, $cells);
        fputcsv($stream, $cells, ',', '"', '');
    };
    $write(['HIGHLAND FRESH - SALES REPORT']);
    $write(['From', $report['start'], 'To', $report['end']]);
    $write(['Generated', $report['generated_at']]);
    $write(['Summary excludes cancelled/voided orders; details include all statuses.']);
    foreach (hfSalesExportSections($report) as [$title, $headers, $rows]) {
        $write([]);
        $write([$title]);
        $write($headers);
        foreach ($rows as $row) $write($row);
        if (!$rows) $write(['No records for this period.']);
    }
    rewind($stream);
    $csv = stream_get_contents($stream);
    fclose($stream);
    return $csv;
}

function hfSalesPdfWrapped($text, float $width, float $size): array {
    // Conservative width estimate keeps even wide Helvetica letters inside cells.
    $limit = max(1, (int) floor(($width - 12) / ($size * 0.78)));
    $ascii = trim(preg_replace('/\s+/', ' ', hfPdfAscii($text)));
    return explode("\n", wordwrap($ascii !== '' ? $ascii : '-', $limit, "\n", true));
}

function hfBuildSalesReportPdf(array $report): string {
    // Keep the PDF useful for long periods; CSV retains every calendar day.
    $report['compact_trend'] = true;
    $report['trend'] = array_values(array_filter($report['trend'] ?? [], static fn($row) =>
        (int) ($row['orders'] ?? 0) > 0 || (float) ($row['total'] ?? 0) != 0));
    $streams = [];
    $commands = '';
    $y = 0;
    $green = [0.06, 0.38, 0.24];
    $newPage = static function () use (&$streams, &$commands, &$y, $report, $green): void {
        if ($commands !== '') $streams[] = $commands;
        $commands = hfPdfRectCommand(48, 517, 746, 42, $green);
        $commands .= hfPdfTextCommand(60, 541, 'HIGHLAND FRESH - SALES REPORT', 14, 'F2', [1, 1, 1]);
        $commands .= hfPdfTextCommand(60, 527, $report['start'] . ' to ' . $report['end'], 9, 'F1', [1, 1, 1]);
        $commands .= hfPdfTextCommand(48, 498, 'Generated: ' . $report['generated_at'] . ' | Currency: PHP', 8);
        $y = 476;
    };
    $heading = static function ($title, array $headers, array $widths) use (&$commands, &$y, $green): void {
        $commands .= hfPdfTextCommand(48, $y, $title, 11, 'F2', $green);
        $y -= 12;
        $commands .= hfPdfRectCommand(48, $y - 22, 746, 22, [0.91, 0.95, 0.92]);
        $x = 48;
        foreach ($headers as $column => $header) {
            $commands .= hfPdfTextCommand($x + 6, $y - 15, $header, 8, 'F2');
            $x += $widths[$column];
        }
        $y -= 22;
    };
    $newPage();
    foreach (hfSalesExportSections($report) as [$title, $headers, $rows, $widths]) {
        if ($y < 120) $newPage();
        $heading($title, $headers, $widths);
        $rows = $rows ?: [array_merge(['No records for this period.'], array_fill(0, count($headers) - 1, '-'))];
        foreach ($rows as $index => $row) {
            $wrapped = [];
            foreach ($row as $column => $value) $wrapped[] = hfSalesPdfWrapped($value, $widths[$column], 8);
            $height = max(array_map('count', $wrapped)) * 11 + 12;
            if ($y - $height < 66) {
                $newPage();
                $heading($title . ' (continued)', $headers, $widths);
            }
            if ($index % 2 === 0) $commands .= hfPdfRectCommand(48, $y - $height, 746, $height, [0.97, 0.98, 0.97]);
            $x = 48;
            foreach ($wrapped as $column => $lines) {
                foreach ($lines as $line => $text) $commands .= hfPdfTextCommand($x + 6, $y - 14 - $line * 11, $text, 8);
                $x += $widths[$column];
            }
            $commands .= hfPdfLineCommand(48, $y - $height, 794, $y - $height);
            $y -= $height;
        }
        $y -= 28;
    }
    $streams[] = $commands;
    $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>',
        3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>'];
    $refs = [];
    $next = 5;
    foreach ($streams as $index => $stream) {
        $stream .= hfPdfTextCommand(48, 40, 'Summary excludes cancelled/voided orders; details include all statuses.', 7);
        $stream .= hfPdfTextCommand(48, 29, 'PDF trend omits dates without orders. CSV includes every calendar day.', 7);
        $stream .= hfPdfTextCommand(713, 40, 'Page ' . ($index + 1) . ' of ' . count($streams), 7);
        $page = $next++;
        $content = $next++;
        $refs[] = "$page 0 R";
        $objects[$page] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents $content 0 R >>";
        $objects[$content] = '<< /Length ' . strlen($stream) . ">>\nstream\n" . $stream . "endstream";
    }
    $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $refs) . '] /Count ' . count($refs) . ' >>';
    ksort($objects);
    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [0];
    foreach ($objects as $id => $body) {
        $offsets[$id] = strlen($pdf);
        $pdf .= "$id 0 obj\n$body\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= 'xref' . "\n0 " . ($next) . "\n0000000000 65535 f \n";
    for ($id = 1; $id < $next; $id++) $pdf .= sprintf('%010d 00000 n ', $offsets[$id]) . "\n";
    return $pdf . "trailer\n<< /Size $next /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
}
