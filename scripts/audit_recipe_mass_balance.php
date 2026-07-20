<?php
/**
 * Highland Fresh — Recipe mass-balance & configuration audit
 *
 * Usage (from project root):
 *   php scripts/audit_recipe_mass_balance.php
 *   php scripts/audit_recipe_mass_balance.php --codes=RCP-BUT-250,RCP-0021
 *   php scripts/audit_recipe_mass_balance.php --all-active
 *   php scripts/audit_recipe_mass_balance.php --json
 *
 * Checks per recipe:
 *  1) Mass balance: base_milk_liters + SUM(BOM qty) vs expected_yield (flag < 90%)
 *  2) Base milk: presence in BOM as "Raw Milk" vs legacy base_milk_liters column
 *  3) Unit risk: liquid yield with ml/g values that look like unconverted units
 */
define('HIGHLAND_FRESH', true);
require dirname(__DIR__) . '/api/config/config.php';
require dirname(__DIR__) . '/api/config/database.php';

$db = Database::getInstance()->getConnection();

$args = $argv ?? [];
$jsonOut = in_array('--json', $args, true);
$allActive = in_array('--all-active', $args, true);

$defaultCodes = [
    'RCP-BUT-250',
    'RCP-CHO',
    'RCP-0024',
    'RCP-FM',
    'RCP-CHE-250',
    'RCP-0021',
    'RCP-0023',
    'RCP-YOG-500',
];

$codes = $defaultCodes;
foreach ($args as $a) {
    if (strpos($a, '--codes=') === 0) {
        $codes = array_values(array_filter(array_map('trim', explode(',', substr($a, 8)))));
    }
}

$minRatio = 0.90;

// ---------------------------------------------------------------------------
// Load recipes
// ---------------------------------------------------------------------------
if ($allActive) {
    $stmt = $db->query("
        SELECT id, recipe_code, product_name, product_type, variant,
               base_milk_liters, expected_yield, bulk_yield_liters, yield_unit, is_active
        FROM master_recipes
        WHERE is_active = 1
        ORDER BY recipe_code
    ");
    $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $placeholders = implode(',', array_fill(0, count($codes), '?'));
    $stmt = $db->prepare("
        SELECT id, recipe_code, product_name, product_type, variant,
               base_milk_liters, expected_yield, bulk_yield_liters, yield_unit, is_active
        FROM master_recipes
        WHERE recipe_code IN ($placeholders)
           OR recipe_code LIKE ?
        ORDER BY recipe_code
    ");
    // Also catch variants like RCP-CHO-xxx if exact code missing
    $params = $codes;
    $params[] = 'RCP-%';
    $stmt->execute($params);
    $all = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Prefer exact target codes, then fuzzy matches for CHO/FM/YOG patterns
    $wanted = array_flip(array_map('strtoupper', $codes));
    $recipes = [];
    $seen = [];
    foreach ($all as $r) {
        $code = strtoupper((string) $r['recipe_code']);
        if (isset($wanted[$code])) {
            $recipes[] = $r;
            $seen[$code] = true;
        }
    }
    // Fuzzy: RCP-CHO*, RCP-FM*, RCP-YOG* if exact missing
    foreach ($codes as $c) {
        $c = strtoupper($c);
        if (isset($seen[$c])) continue;
        foreach ($all as $r) {
            $code = strtoupper((string) $r['recipe_code']);
            if (strpos($code, $c) === 0 || $code === $c) {
                $recipes[] = $r;
                $seen[$code] = true;
                break;
            }
        }
    }
    // If still empty, show every recipe matching any token
    if (empty($recipes)) {
        foreach ($all as $r) {
            $code = strtoupper((string) $r['recipe_code']);
            foreach ($codes as $c) {
                if (strpos($code, strtoupper($c)) !== false) {
                    $recipes[] = $r;
                    break;
                }
            }
        }
    }
}

// Also list codes not found
$foundCodes = array_map(function ($r) {
    return strtoupper($r['recipe_code']);
}, $recipes);
$missingCodes = [];
if (!$allActive) {
    foreach ($codes as $c) {
        $u = strtoupper($c);
        $hit = false;
        foreach ($foundCodes as $f) {
            if ($f === $u || strpos($f, $u) === 0) {
                $hit = true;
                break;
            }
        }
        if (!$hit) {
            $missingCodes[] = $c;
        }
    }
}

$ingStmt = $db->prepare("
    SELECT ri.id, ri.ingredient_id, ri.ingredient_name, ri.ingredient_category,
           ri.quantity, ri.unit, ri.notes,
           i.unit_of_measure AS master_unit, i.ingredient_code
    FROM recipe_ingredients ri
    LEFT JOIN ingredients i ON i.id = ri.ingredient_id
    WHERE ri.recipe_id = ?
    ORDER BY ri.id
");

function isLiquidUnit($unit) {
    $u = strtolower(trim((string) $unit));
    $u = rtrim($u, '.');
    return in_array($u, ['l', 'lt', 'liter', 'liters', 'litre', 'litres', 'ml', 'milliliter', 'milliliters'], true);
}

function isMlUnit($unit) {
    $u = strtolower(trim((string) $unit));
    return in_array($u, ['ml', 'milliliter', 'milliliters'], true);
}

function isGramUnit($unit) {
    $u = strtolower(trim((string) $unit));
    return in_array($u, ['g', 'gram', 'grams'], true);
}

function isKgUnit($unit) {
    $u = strtolower(trim((string) $unit));
    return in_array($u, ['kg', 'kgs', 'kilogram', 'kilograms'], true);
}

function looksLikeRawMilkName($name) {
    $n = strtolower(trim((string) $name));
    if ($n === '') return false;
    if (strpos($n, 'raw milk') !== false) return true;
    if ($n === 'milk' || $n === 'fresh milk') return true;
    if (preg_match('/\braw\b.*\bmilk\b/', $n)) return true;
    return false;
}

$report = [];
$flagCount = 0;

foreach ($recipes as $recipe) {
    $ingStmt->execute([(int) $recipe['id']]);
    $lines = $ingStmt->fetchAll(PDO::FETCH_ASSOC);

    $bomSum = 0.0;
    $bomMilkSum = 0.0;
    $rawMilkInBom = false;
    $unitFlags = [];
    $lineDetails = [];

    foreach ($lines as $line) {
        $qty = (float) $line['quantity'];
        $unit = $line['unit'] ?: ($line['master_unit'] ?: '');
        $name = $line['ingredient_name'] ?: '';
        $bomSum += $qty;

        $isMilk = looksLikeRawMilkName($name)
            || strtolower((string) ($line['ingredient_category'] ?? '')) === 'milk';
        if ($isMilk) {
            $rawMilkInBom = true;
            $bomMilkSum += $qty;
        }

        $flags = [];
        $yu = strtolower((string) ($recipe['yield_unit'] ?? 'liters'));
        $yieldIsLiquid = in_array($yu, ['liters', 'liter', 'l', 'lt', ''], true);

        // Unit risks
        if ($yieldIsLiquid && isMlUnit($unit) && $qty >= 100) {
            $flags[] = sprintf(
                "Likely unconverted ml: '%s' qty=%s %s (consider %s L)",
                $name,
                rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.'),
                $unit,
                rtrim(rtrim(number_format($qty / 1000, 3, '.', ''), '0'), '.')
            );
        }
        if ($yieldIsLiquid && isGramUnit($unit) && $qty >= 100) {
            $flags[] = sprintf(
                "Large g amount on liquid recipe: '%s' qty=%s %s (consider kg if bulk solid)",
                $name,
                rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.'),
                $unit
            );
        }
        if ($line['master_unit'] && $line['unit']
            && strtolower(trim($line['master_unit'])) !== strtolower(trim($line['unit']))) {
            $flags[] = sprintf(
                "Unit mismatch vs master: BOM='%s' master='%s' for '%s'",
                $line['unit'],
                $line['master_unit'],
                $name
            );
        }
        if ($qty <= 0) {
            $flags[] = "Zero/negative qty for '{$name}'";
        }

        $lineDetails[] = [
            'ingredient' => $name,
            'quantity' => $qty,
            'unit' => $unit,
            'is_milk' => $isMilk,
            'flags' => $flags,
        ];
        foreach ($flags as $f) {
            $unitFlags[] = $f;
        }
    }

    $baseMilk = (float) ($recipe['base_milk_liters'] ?? 0);
    $yield = (float) ($recipe['expected_yield'] ?? 0);
    $bulk = isset($recipe['bulk_yield_liters']) && $recipe['bulk_yield_liters'] !== null
        ? (float) $recipe['bulk_yield_liters']
        : null;

    // Mass balance: milk column + full BOM (BOM may or may not include milk)
    $processInput = $baseMilk + $bomSum;
    // If milk is also in BOM, processInput double-counts — prefer max of exclusive views
    $processInputExclusive = $baseMilk + ($bomSum - $bomMilkSum);
    // Use exclusive when milk appears in both places; else standard sum
    $inputForBalance = $rawMilkInBom && $baseMilk > 0
        ? $processInputExclusive
        : $processInput;

    $ratio = $yield > 0 ? $inputForBalance / $yield : null;
    $massFail = $yield > 0 && $inputForBalance + 1e-9 < $yield * $minRatio;
    $milkOnlyFail = $yield > 0
        && in_array(strtolower((string) ($recipe['yield_unit'] ?? 'liters')), ['liters', 'liter', 'l', 'lt', ''], true)
        && $baseMilk + 1e-9 < $yield * $minRatio
        && !$rawMilkInBom;

    $issues = [];
    if ($massFail) {
        $issues[] = sprintf(
            'MASS_BALANCE: input %.3f < 90%% of yield %.3f (%.1f%%)',
            $inputForBalance,
            $yield,
            ($ratio ?? 0) * 100
        );
        $flagCount++;
    }
    if ($milkOnlyFail) {
        $issues[] = sprintf(
            'BASE_MILK_LOW: base_milk_liters=%.3f is < 90%% of yield %.3f (legacy misconfiguration risk)',
            $baseMilk,
            $yield
        );
        $flagCount++;
    }
    if (!$rawMilkInBom && $baseMilk > 0) {
        // Informational only when mass balance already OK (milk lives in base_milk_liters by design)
        $issues[] = 'INFO: Raw milk is configured via base_milk_liters (not a BOM line) — normal for this system';
    }
    if (!$rawMilkInBom && $baseMilk <= 0) {
        $issues[] = 'BASE_MILK_MISSING: No base_milk_liters and no Raw Milk BOM line';
        $flagCount++;
    }
    if ($rawMilkInBom && $baseMilk > 0 && abs($baseMilk - $bomMilkSum) > 0.05) {
        $issues[] = sprintf(
            'BASE_MILK_DESYNC: column=%.3f vs BOM milk=%.3f',
            $baseMilk,
            $bomMilkSum
        );
        $flagCount++;
    }
    foreach ($unitFlags as $uf) {
        $issues[] = 'UNIT: ' . $uf;
    }

    // FAIL = mass/milk integrity; WARN = unit risks; INFO-only → OK
    $hardFail = false;
    $softWarn = false;
    foreach ($issues as $i) {
        if (strpos($i, 'MASS_') === 0
            || strpos($i, 'BASE_MILK_LOW') === 0
            || strpos($i, 'BASE_MILK_MISSING') === 0
            || strpos($i, 'BASE_MILK_DESYNC') === 0) {
            $hardFail = true;
        }
        if (strpos($i, 'UNIT:') === 0) {
            $softWarn = true;
        }
    }
    if ($hardFail) {
        $status = 'FAIL';
        $flagCount++;
    } elseif ($softWarn) {
        $status = 'WARN';
    } else {
        $status = 'OK';
    }

    $report[] = [
        'recipe_id' => (int) $recipe['id'],
        'recipe_code' => $recipe['recipe_code'],
        'product_name' => $recipe['product_name'],
        'product_type' => $recipe['product_type'],
        'is_active' => (int) $recipe['is_active'],
        'base_milk_liters' => $baseMilk,
        'expected_yield' => $yield,
        'bulk_yield_liters' => $bulk,
        'yield_unit' => $recipe['yield_unit'],
        'bom_line_count' => count($lines),
        'bom_qty_sum' => round($bomSum, 3),
        'bom_milk_sum' => round($bomMilkSum, 3),
        'raw_milk_in_bom' => $rawMilkInBom,
        'process_input' => round($inputForBalance, 3),
        'mass_ratio_pct' => $ratio !== null ? round($ratio * 100, 1) : null,
        'mass_balance_ok' => !$massFail && !$milkOnlyFail,
        'status' => $status,
        'issues' => $issues,
        'bom_lines' => $lineDetails,
    ];
}

if ($jsonOut) {
    echo json_encode([
        'min_ratio' => $minRatio,
        'missing_codes' => $missingCodes,
        'recipes' => $report,
        'summary' => [
            'audited' => count($report),
            'ok' => count(array_filter($report, function ($r) { return $r['status'] === 'OK'; })),
            'warn' => count(array_filter($report, function ($r) { return $r['status'] === 'WARN'; })),
            'fail' => count(array_filter($report, function ($r) { return $r['status'] === 'FAIL'; })),
        ],
    ], JSON_PRETTY_PRINT);
    exit(0);
}

// ---------------------------------------------------------------------------
// Human-readable table
// ---------------------------------------------------------------------------
echo "========================================================================\n";
echo " Highland Fresh — Recipe Mass Balance Audit\n";
echo " Rule: process_input = base_milk_liters + BOM (exclusive of double-counted milk)\n";
echo " FAIL if process_input < 90% of expected_yield\n";
echo "========================================================================\n\n";

if ($missingCodes) {
    echo "NOTE: codes not found in master_recipes: " . implode(', ', $missingCodes) . "\n";
    echo "      (try --all-active or check exact recipe_code)\n\n";
}

printf(
    "%-4s %-14s %-22s %8s %8s %8s %8s %7s %6s %s\n",
    'ST',
    'CODE',
    'PRODUCT',
    'MILK',
    'BOM',
    'INPUT',
    'YIELD',
    'RATIO%',
    'BOM?',
    'ISSUES'
);
echo str_repeat('-', 120) . "\n";

foreach ($report as $r) {
    $issueShort = $r['issues'] ? implode(' | ', array_slice($r['issues'], 0, 2)) : '—';
    if (count($r['issues']) > 2) {
        $issueShort .= ' (+' . (count($r['issues']) - 2) . ' more)';
    }
    printf(
        "%-4s %-14s %-22s %8.2f %8.2f %8.2f %8.2f %6s %6s %s\n",
        $r['status'],
        substr($r['recipe_code'], 0, 14),
        substr($r['product_name'], 0, 22),
        $r['base_milk_liters'],
        $r['bom_qty_sum'],
        $r['process_input'],
        $r['expected_yield'],
        $r['mass_ratio_pct'] !== null ? number_format($r['mass_ratio_pct'], 1) : '—',
        $r['raw_milk_in_bom'] ? 'YES' : 'NO',
        $issueShort
    );
}

echo "\n";
$ok = count(array_filter($report, function ($r) { return $r['status'] === 'OK'; }));
$warn = count(array_filter($report, function ($r) { return $r['status'] === 'WARN'; }));
$fail = count(array_filter($report, function ($r) { return $r['status'] === 'FAIL'; }));
echo "Summary: audited=" . count($report) . "  OK={$ok}  WARN={$warn}  FAIL={$fail}\n";

// Detail for non-OK
$detail = array_filter($report, function ($r) { return $r['status'] !== 'OK'; });
if ($detail) {
    echo "\n--- Detail (WARN / FAIL) ---\n";
    foreach ($detail as $r) {
        echo "\n[{$r['status']}] {$r['recipe_code']} — {$r['product_name']}\n";
        echo "  base_milk_liters={$r['base_milk_liters']}  expected_yield={$r['expected_yield']} {$r['yield_unit']}";
        if ($r['bulk_yield_liters'] !== null) {
            echo "  bulk_yield={$r['bulk_yield_liters']}";
        }
        echo "\n  BOM lines={$r['bom_line_count']} sum={$r['bom_qty_sum']}  process_input={$r['process_input']}  ratio={$r['mass_ratio_pct']}%\n";
        echo "  Raw milk in BOM: " . ($r['raw_milk_in_bom'] ? 'YES' : 'NO (uses legacy column only)') . "\n";
        foreach ($r['issues'] as $iss) {
            echo "  • {$iss}\n";
        }
        if ($r['bom_lines']) {
            echo "  BOM:\n";
            foreach ($r['bom_lines'] as $ln) {
                echo sprintf(
                    "    - %s: %s %s%s\n",
                    $ln['ingredient'],
                    rtrim(rtrim(number_format($ln['quantity'], 3, '.', ''), '0'), '.'),
                    $ln['unit'],
                    $ln['is_milk'] ? ' [milk]' : ''
                );
            }
        }
    }
}

echo "\nDone.\n";
exit($fail > 0 ? 1 : 0);
