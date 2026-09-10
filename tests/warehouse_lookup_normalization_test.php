<?php

require_once dirname(__DIR__) . '/api/helpers/lookup_normalization.php';

function assertLookupSame($expected, $actual, $message)
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . "\nExpected: " . var_export($expected, true) .
            "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

assertLookupSame(
    'Metro Gaisano',
    hfNormalizeLookupText("  Metro\t  Gaisano\u{00A0} "),
    'API customer search must trim and collapse Unicode whitespace.'
);
assertLookupSame(
    'BATCH-20260907-0059',
    hfNormalizeBarcodeLookup(" BATCH-20260907-0059\r\n"),
    'API barcode lookup must remove scanner terminators.'
);
assertLookupSame(
    'PKG-123',
    hfNormalizeBarcodeLookup("PKG-\u{200B}123 "),
    'API barcode lookup must remove invisible pasted characters.'
);

echo "Warehouse lookup normalization API tests passed.\n";
