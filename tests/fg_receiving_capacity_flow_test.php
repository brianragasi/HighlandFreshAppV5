<?php

function fgReceivingCapacityAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$api = file_get_contents(__DIR__ . '/../api/warehouse/fg/inventory.php');
$page = file_get_contents(__DIR__ . '/../html/warehouse/fg/receiving.html');

fgReceivingCapacityAssert(
    str_contains($api, 'class FgChillerCapacityException')
    && str_contains($api, 'SELECT id, chiller_name, capacity, status, is_active')
    && str_contains($api, 'FOR UPDATE'),
    'Batch receiving must lock and validate the destination chiller before insertion.'
);

$preflightPosition = strpos($api, '$currentOccupancy = fgChillerAuthoritativeCount');
$insertPosition = strpos($api, '// Create FG inventory row', $preflightPosition ?: 0);
fgReceivingCapacityAssert(
    $preflightPosition !== false && $insertPosition !== false && $preflightPosition < $insertPosition,
    'Capacity preflight must happen before Finished Goods inventory rows are created.'
);

fgReceivingCapacityAssert(
    str_contains($api, 'Response::error($e->getMessage(), 422)')
    && str_contains($api, 'It is short by '),
    'Capacity failures must return an actionable validation response rather than HTTP 500.'
);

fgReceivingCapacityAssert(
    str_contains($page, 'function getBatchRequiredCapacity(batch)')
    && str_contains($page, 'function renderReceiveChillerOptions(batch)')
    && str_contains($page, 'option.disabled = cannotFit')
    && str_contains($page, 'submitButton.disabled = required <= 0 || fittingCount === 0')
    && str_contains($page, 'No available chiller can hold all'),
    'The receiving modal must prevent selection of chillers that cannot fit the complete batch.'
);

echo "Finished Goods receiving capacity flow checks passed.\n";
