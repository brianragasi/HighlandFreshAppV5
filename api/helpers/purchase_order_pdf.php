<?php

if (!function_exists('hfPdfAscii')) {
    function hfPdfAscii($value) {
        $text = (string) $value;
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if ($converted !== false) {
                $text = $converted;
            }
        }
        return preg_replace('/[^\x20-\x7E]/', '', $text);
    }
}

if (!function_exists('hfPdfText')) {
    function hfPdfText($value, $maxLength = 160) {
        $text = preg_replace('/\s+/', ' ', trim(hfPdfAscii($value)));
        if (strlen($text) > $maxLength) {
            $text = substr($text, 0, max(0, $maxLength - 3)) . '...';
        }
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}

if (!function_exists('hfPdfTextCommand')) {
    function hfPdfTextCommand($x, $y, $text, $size = 9, $font = 'F1', $color = [0.09, 0.13, 0.11]) {
        return sprintf(
            "%.3F %.3F %.3F rg BT /%s %.2F Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
            $color[0], $color[1], $color[2], $font, $size, $x, $y, hfPdfText($text)
        );
    }
}

if (!function_exists('hfPdfRectCommand')) {
    function hfPdfRectCommand($x, $y, $width, $height, $fill, $stroke = null, $lineWidth = 0.7) {
        $command = sprintf('%.3F %.3F %.3F rg ', $fill[0], $fill[1], $fill[2]);
        if ($stroke !== null) {
            $command .= sprintf('%.3F %.3F %.3F RG %.2F w ', $stroke[0], $stroke[1], $stroke[2], $lineWidth);
        }
        return $command . sprintf('%.2F %.2F %.2F %.2F re %s', $x, $y, $width, $height, $stroke === null ? "f\n" : "B\n");
    }
}

if (!function_exists('hfPdfLineCommand')) {
    function hfPdfLineCommand($x1, $y1, $x2, $y2, $color = [0.78, 0.82, 0.79], $lineWidth = 0.6) {
        return sprintf(
            '%.3F %.3F %.3F RG %.2F w %.2F %.2F m %.2F %.2F l S' . "\n",
            $color[0], $color[1], $color[2], $lineWidth, $x1, $y1, $x2, $y2
        );
    }
}

if (!function_exists('hfPdfMoney')) {
    function hfPdfMoney($value) {
        return 'PHP ' . number_format((float) $value, 2);
    }
}

if (!function_exists('hfPdfDate')) {
    function hfPdfDate($value) {
        if (!$value) return '-';
        $timestamp = strtotime((string) $value);
        return $timestamp ? date('M j, Y', $timestamp) : (string) $value;
    }
}

if (!function_exists('hfPdfLogoJpeg')) {
    function hfPdfLogoJpeg() {
        $jpgPath = dirname(__DIR__, 2) . '/highland_fresh_logo.jpg';
        if (is_file($jpgPath)) {
            $dimensions = @getimagesize($jpgPath);
            $data = @file_get_contents($jpgPath);
            if ($dimensions && $data !== false) {
                return ['data' => $data, 'width' => $dimensions[0], 'height' => $dimensions[1]];
            }
        }

        $path = dirname(__DIR__, 2) . '/highland_fresh_logo.png';
        if (!is_file($path) || !function_exists('imagecreatefrompng')) {
            return null;
        }

        $source = @imagecreatefrompng($path);
        if (!$source) return null;

        $size = 180;
        $canvas = imagecreatetruecolor($size, $size);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $size, $size, imagesx($source), imagesy($source));
        ob_start();
        imagejpeg($canvas, null, 88);
        $data = ob_get_clean();
        imagedestroy($canvas);
        imagedestroy($source);

        return $data ? ['data' => $data, 'width' => $size, 'height' => $size] : null;
    }
}

if (!function_exists('hfPdfHeader')) {
    function hfPdfHeader(array $po, $pageNumber, $pageCount, $withLogo) {
        $green = [0.04, 0.32, 0.18];
        $muted = [0.37, 0.44, 0.40];
        $command = hfPdfRectCommand(36, 764, 523, 54, $green);
        if ($withLogo) {
            $command .= "q 40 0 0 40 47 771 cm /Logo Do Q\n";
        }
        $titleX = $withLogo ? 96 : 50;
        $command .= hfPdfTextCommand($titleX, 797, 'HIGHLAND FRESH DAIRY CORPORATION', 13, 'F2', [1, 1, 1]);
        $command .= hfPdfTextCommand($titleX, 781, 'Purchasing Department', 8, 'F1', [0.86, 0.94, 0.89]);
        $command .= hfPdfTextCommand(405, 798, 'ELECTRONIC PURCHASE ORDER', 8.5, 'F2', [1, 1, 1]);
        $command .= hfPdfTextCommand(436, 782, 'PO: ' . ($po['po_number'] ?? '-'), 8, 'F1', [0.86, 0.94, 0.89]);
        if ($pageCount > 1) {
            $command .= hfPdfTextCommand(501, 746, "Page {$pageNumber} of {$pageCount}", 7, 'F1', $muted);
        }
        return $command;
    }
}

if (!function_exists('hfPdfInfoCard')) {
    function hfPdfInfoCard($x, $y, $width, $title, array $lines) {
        $command = hfPdfRectCommand($x, $y, $width, 91, [0.965, 0.975, 0.969], [0.78, 0.84, 0.80]);
        $command .= hfPdfTextCommand($x + 12, $y + 72, strtoupper($title), 7, 'F2', [0.20, 0.42, 0.30]);
        $lineY = $y + 55;
        foreach (array_slice($lines, 0, 4) as $line) {
            $command .= hfPdfTextCommand($x + 12, $lineY, $line, 8.2, 'F1');
            $lineY -= 15;
        }
        return $command;
    }
}

if (!function_exists('hfPdfItemsTable')) {
    function hfPdfItemsTable(array $items, $startY, $offset) {
        $x = 46;
        $widths = [24, 150, 44, 55, 72, 77, 79];
        $labels = ['#', 'ITEM', 'UNIT', 'QTY', 'UNIT PRICE', 'LINE TOTAL', 'REMARKS'];
        $green = [0.04, 0.32, 0.18];
        $command = hfPdfRectCommand($x, $startY, array_sum($widths), 23, $green);
        $cursor = $x;
        foreach ($labels as $index => $label) {
            $command .= hfPdfTextCommand($cursor + 5, $startY + 8, $label, 6.8, 'F2', [1, 1, 1]);
            $cursor += $widths[$index];
        }

        $rowY = $startY - 29;
        foreach ($items as $index => $item) {
            $fill = $index % 2 === 0 ? [0.985, 0.988, 0.986] : [1, 1, 1];
            $command .= hfPdfRectCommand($x, $rowY, array_sum($widths), 29, $fill, [0.84, 0.87, 0.85], 0.4);
            $description = $item['ingredient_name'] ?? $item['mro_item_name'] ?? $item['item_description'] ?? 'Item';
            $values = [
                (string) ($offset + $index + 1),
                $description,
                $item['unit'] ?? '-',
                number_format((float) ($item['quantity'] ?? 0), 2),
                hfPdfMoney($item['unit_price'] ?? 0),
                hfPdfMoney($item['total_amount'] ?? 0),
                'Approved'
            ];
            $limits = [4, 31, 8, 9, 15, 16, 15];
            $cursor = $x;
            foreach ($values as $column => $value) {
                $command .= hfPdfTextCommand($cursor + 5, $rowY + 11, substr(hfPdfAscii($value), 0, $limits[$column]), 7.3, $column === 1 ? 'F2' : 'F1');
                $cursor += $widths[$column];
            }
            $rowY -= 29;
        }

        return ['commands' => $command, 'bottom_y' => $rowY + 29];
    }
}

if (!function_exists('hfBuildPurchaseOrderPdf')) {
    function hfBuildPurchaseOrderPdf(array $po, array $items) {
        $itemPages = array_chunk($items ?: [[]], 7);
        $pageCount = count($itemPages);
        $logo = hfPdfLogoJpeg();
        $pageStreams = [];
        $offset = 0;

        foreach ($itemPages as $pageIndex => $pageItems) {
            if ($items === []) $pageItems = [];
            $isFirst = $pageIndex === 0;
            $isLast = $pageIndex === $pageCount - 1;
            $command = hfPdfHeader($po, $pageIndex + 1, $pageCount, $logo !== null);

            if ($isFirst) {
                $supplierLines = [
                    ($po['supplier_name'] ?? '-') . ' (' . ($po['supplier_code'] ?? '-') . ')',
                    'Contact: ' . ($po['supplier_contact'] ?? '-'),
                    'Email: ' . ($po['supplier_email'] ?? '-'),
                    'Phone: ' . ($po['supplier_phone'] ?? '-'),
                ];
                $orderLines = [
                    'PO Number: ' . ($po['po_number'] ?? '-'),
                    'Order Date: ' . hfPdfDate($po['order_date'] ?? null),
                    'Expected Delivery: ' . hfPdfDate($po['expected_delivery'] ?? null),
                    'Payment Terms: ' . ($po['payment_terms'] ?? $po['supplier_terms'] ?? '-'),
                ];
                $command .= hfPdfInfoCard(46, 650, 247, 'Supplier', $supplierLines);
                $command .= hfPdfInfoCard(308, 650, 241, 'Order Details', $orderLines);
                $controlLines = [
                    'Requested By: ' . ($po['requested_by_name'] ?? 'Warehouse Raw'),
                    'Prepared By: ' . ($po['created_by_name'] ?? 'Purchasing'),
                    'Approved By: ' . ($po['approved_by_name'] ?? 'General Manager'),
                    'Status: Approved',
                ];
                $referenceLines = [
                    'Linked PRS: ' . ($po['pr_number'] ?? '-'),
                    'Purpose: ' . ($po['pr_purpose'] ?? '-'),
                    'Department: Purchasing',
                    'Approved: ' . hfPdfDate($po['approved_at'] ?? null),
                ];
                $command .= hfPdfInfoCard(46, 545, 247, 'Document Control', $controlLines);
                $command .= hfPdfInfoCard(308, 545, 241, 'Reference', $referenceLines);
                $tableY = 507;
            } else {
                $command .= hfPdfTextCommand(46, 726, 'ORDER ITEMS - CONTINUED', 9, 'F2', [0.04, 0.32, 0.18]);
                $tableY = 695;
            }

            $table = hfPdfItemsTable($pageItems, $tableY, $offset);
            $command .= $table['commands'];
            $offset += count($pageItems);

            if ($isLast) {
                $totalsY = max(177, $table['bottom_y'] - 78);
                $command .= hfPdfRectCommand(354, $totalsY, 195, 62, [0.965, 0.975, 0.969], [0.72, 0.79, 0.75]);
                $command .= hfPdfTextCommand(367, $totalsY + 43, 'Subtotal', 8, 'F1');
                $command .= hfPdfTextCommand(465, $totalsY + 43, hfPdfMoney($po['subtotal'] ?? 0), 8, 'F1');
                $command .= hfPdfTextCommand(367, $totalsY + 28, 'VAT', 8, 'F1');
                $command .= hfPdfTextCommand(465, $totalsY + 28, hfPdfMoney($po['vat_amount'] ?? 0), 8, 'F1');
                $command .= hfPdfLineCommand(365, $totalsY + 20, 537, $totalsY + 20, [0.65, 0.72, 0.68]);
                $command .= hfPdfTextCommand(367, $totalsY + 7, 'TOTAL', 9, 'F2', [0.04, 0.32, 0.18]);
                $command .= hfPdfTextCommand(462, $totalsY + 7, hfPdfMoney($po['total_amount'] ?? 0), 9, 'F2', [0.04, 0.32, 0.18]);

                $approvalY = 104;
                $command .= hfPdfRectCommand(46, $approvalY, 503, 52, [0.94, 0.98, 0.95], [0.54, 0.76, 0.62]);
                $command .= hfPdfTextCommand(58, $approvalY + 35, 'DIGITALLY APPROVED', 8, 'F2', [0.04, 0.42, 0.20]);
                $approver = $po['approved_by_name'] ?? $po['approver_name'] ?? 'General Manager';
                $command .= hfPdfTextCommand(58, $approvalY + 19, 'Approved by: ' . $approver . ' | ' . hfPdfDate($po['approved_at'] ?? null), 8, 'F1');
                $remarks = trim((string) ($po['approval_remarks'] ?? ''));
                if ($remarks !== '') {
                    $command .= hfPdfTextCommand(290, $approvalY + 34, 'DECISION NOTE', 6.8, 'F2', [0.04, 0.42, 0.20]);
                    $command .= hfPdfTextCommand(290, $approvalY + 19, substr(hfPdfAscii($remarks), 0, 40), 7.2, 'F1');
                }

                $command .= hfPdfLineCommand(58, 72, 166, 72, [0.30, 0.36, 0.32]);
                $command .= hfPdfLineCommand(188, 72, 296, 72, [0.30, 0.36, 0.32]);
                $command .= hfPdfLineCommand(318, 72, 426, 72, [0.30, 0.36, 0.32]);
                $command .= hfPdfLineCommand(448, 72, 556, 72, [0.30, 0.36, 0.32]);
                $command .= hfPdfTextCommand(58, 59, ($po['requested_by_name'] ?? 'Warehouse Raw') . ' / Requested By', 6.8, 'F1');
                $command .= hfPdfTextCommand(188, 59, ($po['created_by_name'] ?? 'Purchasing') . ' / Prepared By', 6.8, 'F1');
                $command .= hfPdfTextCommand(318, 59, $approver . ' / Approved By', 6.8, 'F1');
                $command .= hfPdfTextCommand(448, 59, ($po['supplier_contact'] ?? 'Supplier') . ' / Supplier', 6.8, 'F1');
            }

            $command .= hfPdfTextCommand(46, 31, 'Official system-generated EPO. Quote the PO number on the delivery receipt and invoice.', 6.8, 'F1', [0.38, 0.44, 0.40]);
            $pageStreams[] = $command;
        }

        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
        ];
        $nextObject = 5;
        $logoObject = null;
        if ($logo !== null) {
            $logoObject = $nextObject++;
            $objects[$logoObject] = '<< /Type /XObject /Subtype /Image /Width ' . $logo['width']
                . ' /Height ' . $logo['height'] . ' /ColorSpace /DeviceRGB /BitsPerComponent 8'
                . ' /Filter /DCTDecode /Length ' . strlen($logo['data']) . ">>\nstream\n"
                . $logo['data'] . "\nendstream";
        }

        $pageRefs = [];
        foreach ($pageStreams as $stream) {
            $pageObject = $nextObject++;
            $contentObject = $nextObject++;
            $pageRefs[] = $pageObject . ' 0 R';
            $resources = '/Resources << /Font << /F1 3 0 R /F2 4 0 R >>';
            if ($logoObject !== null) {
                $resources .= ' /XObject << /Logo ' . $logoObject . ' 0 R >>';
            }
            $resources .= ' >>';
            $objects[$contentObject] = '<< /Length ' . strlen($stream) . ">>\nstream\n" . $stream . "endstream";
            $objects[$pageObject] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] '
                . $resources . ' /Contents ' . $contentObject . ' 0 R >>';
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $pageRefs) . '] /Count ' . count($pageRefs) . ' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0 => 0];
        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xrefOffset = strlen($pdf);
        $maxObject = max(array_keys($objects));
        $pdf .= "xref\n0 " . ($maxObject + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $maxObject; $i++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$i] ?? 0) . "\n";
        }
        $pdf .= "trailer\n<< /Size " . ($maxObject + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF";
        return $pdf;
    }
}
