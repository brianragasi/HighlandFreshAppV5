<?php

$root = dirname(__DIR__);
$sources = [
    'history_api' => file_get_contents($root . '/api/admin/gm_approvals.php'),
    'po_api' => file_get_contents($root . '/api/purchasing/purchase_orders.php'),
    'dashboard' => file_get_contents($root . '/html/admin/dashboard.html'),
    'approvals_page' => file_get_contents($root . '/html/admin/gm_approvals.html'),
];

foreach ($sources as $name => $source) {
    if ($source === false) {
        fwrite(STDERR, "Unable to read {$name}.\n");
        exit(1);
    }
}

$checks = [
    'Decision history comes from immutable approval/rejection audit entries' =>
        str_contains($sources['history_api'], "case 'decision_history'")
        && str_contains($sources['history_api'], "a.action IN ('APPROVE', 'REJECT')")
        && str_contains($sources['history_api'], 'a.entry_hash AS audit_hash'),
    'PO detail exposes the decision proof without exposing arbitrary audit payloads' =>
        str_contains($sources['po_api'], 'approval_audit.id AS approval_audit_id')
        && str_contains($sources['po_api'], 'approval_audit.entry_hash AS approval_audit_hash')
        && str_contains($sources['po_api'], 'decision_by_name'),
    'Dashboard retains a clickable recent-decision list' =>
        str_contains($sources['dashboard'], 'Recent PO Decisions')
        && str_contains($sources['dashboard'], 'approvalDecisionHistoryList')
        && str_contains($sources['dashboard'], 'GM Decision Proof'),
    'Approval workspace provides full retained decision history' =>
        str_contains($sources['approvals_page'], 'id="decision-history"')
        && str_contains($sources['approvals_page'], 'Purchase Order Decision History')
        && str_contains($sources['approvals_page'], 'decisionHistoryList')
        && str_contains($sources['approvals_page'], 'GM Decision Proof'),
    'Historical PO modal is read-only' =>
        str_contains($sources['dashboard'], 'setPOModalDecisionMode(po.status ===')
        && str_contains($sources['dashboard'], "approveButton?.classList.toggle('hidden'")
        && str_contains($sources['approvals_page'], "rejectButton?.classList.toggle('hidden'"),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "Failed: {$label}.\n");
        exit(1);
    }
}

echo "GM approval decision-history evidence tests passed.\n";
