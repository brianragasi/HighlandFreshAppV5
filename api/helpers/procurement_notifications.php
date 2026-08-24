<?php
/**
 * Procurement notification recipient rules.
 *
 * Each notification represents a specific next action. Keep the recipient map
 * here so unrelated roles cannot receive procurement work by mistake.
 */

if (!function_exists('procurementNotificationRecipients')) {
    function procurementNotificationRecipients() {
        return [
            'prs_submitted_for_supplier_review' => ['purchaser'],
            'po_pending_approval' => ['general_manager'],
            'po_approved_pending_delivery' => ['warehouse_raw'],
            'po_approved_prepare_funds' => ['finance_officer'],
            'rr_ready_for_verification' => ['purchaser'],
            'po_partially_received' => ['purchaser'],
            'rr_verified_transaction_closed' => ['finance_officer'],
            'fg_disposal_review' => ['qc_officer'],
        ];
    }
}
if (!function_exists('isProcurementNotificationRecipientAllowed')) {
    function isProcurementNotificationRecipientAllowed($targetRole, $type) {
        $recipients = procurementNotificationRecipients();
        return isset($recipients[$type])
            && in_array($targetRole, $recipients[$type], true);
    }
}

if (!function_exists('writeProcurementNotification')) {
    function writeProcurementNotification($db, $targetRole, $type, $title, $message, $referenceType = null, $referenceId = null) {
        if (!isProcurementNotificationRecipientAllowed($targetRole, $type)) {
            error_log(sprintf(
                'Blocked procurement notification recipient: type=%s target_role=%s reference=%s:%s',
                (string) $type,
                (string) $targetRole,
                (string) $referenceType,
                (string) $referenceId
            ));
            throw new InvalidArgumentException('Invalid notification recipient for this action.');
        }

        $stmt = $db->prepare("
            INSERT INTO procurement_notifications
                (target_role, notification_type, title, message, reference_type, reference_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$targetRole, $type, $title, $message, $referenceType, $referenceId]);
    }
}

if (!function_exists('closeMisroutedProcurementNotifications')) {
    function closeMisroutedProcurementNotifications($db) {
        $allowedPairs = [];
        foreach (procurementNotificationRecipients() as $type => $roles) {
            foreach ($roles as $role) {
                $allowedPairs[] = [$type, $role];
            }
        }

        if (!$allowedPairs) {
            return 0;
        }

        $clauses = [];
        $params = [];
        foreach ($allowedPairs as [$type, $role]) {
            $clauses[] = '(notification_type = ? AND target_role = ?)';
            $params[] = $type;
            $params[] = $role;
        }

        $sql = "
            UPDATE procurement_notifications
            SET is_read = 1
            WHERE is_read = 0
              AND NOT (" . implode(' OR ', $clauses) . ")
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }
}
