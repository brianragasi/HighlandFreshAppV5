<?php
// Standalone calc to verify the API math
$baseMilk = 100;
$expectedYield = 180;
$culturesPerBatch = 0.010;  // kg
$culturesPackSize = 1.0;    // kg
$sugarPerBatch = 0.100;     // kg
$sugarPackSize = 25.0;      // kg

echo "Plain Yogurt (id=15) — base_milk_liters={$baseMilk}, expected_yield={$expectedYield} cups\n";
echo "Recipe ingredients (per batch = {$expectedYield} cups):\n";
printf("  Cultures: %.4f kg/batch  | pack_size=%.1f kg (1 packet)\n", $culturesPerBatch, $culturesPackSize);
printf("  Sugar:    %.4f kg/batch  | pack_size=%.1f kg (1 sack)\n", $sugarPerBatch, $sugarPackSize);
echo "\n=== Verifying the actual API math ===\n\n";

foreach ([1, 10, 100, 180, 1000, 5000, 18000, 45000] as $pq) {
    $scale = $expectedYield > 0 ? $pq / $expectedYield : 1;
    $milk = round($baseMilk * $scale, 3);
    $culturesBase = round($culturesPerBatch * $scale, 3);
    $sugarBase = round($sugarPerBatch * $scale, 3);
    $culturesPacks = (int) ceil($culturesBase / $culturesPackSize);
    $sugarPacks = (int) ceil($sugarBase / $sugarPackSize);
    printf("planned_qty=%-6d  scale=%.4fx  milk=%.2fL | cultures: base=%.4f kg, pack_count=%d  | sugar: base=%.4f kg, pack_count=%d\n",
        $pq, $scale, $milk, $culturesBase, $culturesPacks, $sugarBase, $sugarPacks);
}
