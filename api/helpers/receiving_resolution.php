<?php

/**
 * Build a quantity-only summary for final Receiving Report resolution.
 *
 * Rejections are historical evidence. They do not remain an unresolved
 * mismatch after replacement stock brings the accepted quantity up to the
 * approved PO quantity.
 */
if (!function_exists('buildReceivingResolutionPlan')) {
    function buildReceivingResolutionPlan(array $items): array {
        $lines = [];
        $totalOrdered = 0.0;
        $totalAccepted = 0.0;
        $totalRejected = 0.0;
        $totalShort = 0.0;

        foreach ($items as $item) {
            $ordered = max(0.0, (float) ($item['quantity'] ?? $item['po_quantity'] ?? $item['quantity_ordered'] ?? 0));
            $accepted = max(0.0, (float) ($item['quantity_received'] ?? 0));
            $rejected = max(0.0, (float) ($item['quantity_rejected'] ?? 0));
            $short = max(0.0, $ordered - $accepted);

            $totalOrdered += $ordered;
            $totalAccepted += $accepted;
            $totalRejected += $rejected;
            $totalShort += $short;
            $lines[] = [
                'po_item_id' => (int) ($item['id'] ?? $item['po_item_id'] ?? 0),
                'purchase_request_item_id' => isset($item['purchase_request_item_id'])
                    ? (int) $item['purchase_request_item_id']
                    : null,
                'item_description' => (string) ($item['item_description'] ?? 'PO item'),
                'unit' => (string) ($item['unit'] ?? $item['po_unit'] ?? ''),
                'ordered' => $ordered,
                'accepted' => $accepted,
                'rejected' => $rejected,
                'short' => $short,
            ];
        }

        $isComplete = $totalShort <= 0.0001 && count($lines) > 0;
        return [
            'lines' => $lines,
            'total_ordered' => $totalOrdered,
            'total_accepted' => $totalAccepted,
            'total_rejected' => $totalRejected,
            'total_short' => $totalShort,
            'has_accepted' => $totalAccepted > 0.0001,
            'has_historical_rejections' => $totalRejected > 0.0001,
            'is_complete' => $isComplete,
            'verification_outcome' => $isComplete
                ? ($totalRejected > 0.0001 ? 'replacement_completed' : 'exact_match')
                : 'short_closed',
        ];
    }
}
