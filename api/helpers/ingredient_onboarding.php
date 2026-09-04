<?php

/**
 * One-time routing state for a newly registered ingredient.
 *
 * Admin records only whether stock is known to exist. Warehouse remains the
 * owner of every physical quantity, lot, and expiry fact.
 */
function ensureIngredientOnboardingSupport(PDO $db): void {
    if (!ingredientOnboardingColumnExists($db, 'ingredients', 'initial_stock_route')) {
        $db->exec("ALTER TABLE ingredients
            ADD COLUMN initial_stock_route VARCHAR(30) NULL AFTER current_stock");
    }
    if (!ingredientOnboardingColumnExists($db, 'ingredients', 'onboarding_status')) {
        $db->exec("ALTER TABLE ingredients
            ADD COLUMN onboarding_status VARCHAR(30) NOT NULL DEFAULT 'not_required'
            AFTER initial_stock_route");
    }
}

function ingredientOnboardingColumnExists(PDO $db, string $table, string $column): bool {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) {
        return false;
    }
    $stmt = $db->query("SHOW COLUMNS FROM `{$table}` LIKE " . $db->quote($column));
    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

function normalizeIngredientInitialStockRoute($value): string {
    $route = strtolower(trim((string) $value));
    return in_array($route, ['purchase_required', 'opening_stock'], true) ? $route : '';
}

function nextNewMaterialDemandNumber(): string {
    return 'NEW-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
}

function markIngredientOnboardingStatus(PDO $db, int $ingredientId, string $status): void {
    $allowed = ['not_required', 'routed_to_purchasing', 'pending_count', 'under_review', 'completed'];
    if ($ingredientId <= 0 || !in_array($status, $allowed, true)) {
        throw new InvalidArgumentException('Invalid ingredient onboarding state');
    }
    if (!ingredientOnboardingColumnExists($db, 'ingredients', 'initial_stock_route')
        || !ingredientOnboardingColumnExists($db, 'ingredients', 'onboarding_status')) {
        return;
    }
    $expectedPrevious = [
        'under_review' => 'pending_count',
        'pending_count' => 'under_review',
        'completed' => 'under_review',
    ][$status] ?? null;
    $sql = "UPDATE ingredients
        SET onboarding_status = ?, updated_at = NOW()
        WHERE id = ? AND initial_stock_route = 'opening_stock'";
    $params = [$status, $ingredientId];
    if ($expectedPrevious !== null) {
        $sql .= ' AND onboarding_status = ?';
        $params[] = $expectedPrevious;
    }
    $db->prepare($sql)->execute($params);
}
