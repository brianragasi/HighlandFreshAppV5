<?php

/**
 * User accounts and supplier contacts have different jobs, but both represent
 * an accountable identity in the system. Reusing either email makes login,
 * password recovery, and final-PO delivery ambiguous.
 */

if (!function_exists('hfNormalizeIdentityEmail')) {
    function hfNormalizeIdentityEmail($value): ?string
    {
        $email = strtolower(trim((string) $value));
        return $email === '' ? null : $email;
    }
}

if (!function_exists('hfFindUserSupplierEmailConflicts')) {
    function hfFindUserSupplierEmailConflicts(
        PDO $db,
        $email,
        ?int $excludeUserId = null,
        ?int $excludeSupplierId = null
    ): array {
        $normalized = hfNormalizeIdentityEmail($email);
        if ($normalized === null) {
            return [];
        }

        $userSql = "
            SELECT 'user' AS record_type,
                   id,
                   COALESCE(NULLIF(TRIM(full_name), ''), username, 'Unnamed user') AS display_name
            FROM users
            WHERE LOWER(TRIM(email)) = ?
        ";
        $userParams = [$normalized];
        if ($excludeUserId !== null) {
            $userSql .= ' AND id <> ?';
            $userParams[] = $excludeUserId;
        }

        $supplierSql = "
            SELECT 'supplier' AS record_type,
                   id,
                   COALESCE(NULLIF(TRIM(supplier_name), ''), supplier_code, 'Unnamed supplier') AS display_name
            FROM suppliers
            WHERE LOWER(TRIM(email)) = ?
        ";
        $supplierParams = [$normalized];
        if ($excludeSupplierId !== null) {
            $supplierSql .= ' AND id <> ?';
            $supplierParams[] = $excludeSupplierId;
        }

        $userStmt = $db->prepare($userSql);
        $userStmt->execute($userParams);
        $supplierStmt = $db->prepare($supplierSql);
        $supplierStmt->execute($supplierParams);

        return array_merge(
            $userStmt->fetchAll(PDO::FETCH_ASSOC),
            $supplierStmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }
}

if (!function_exists('hfEmailIdentityConflictMessage')) {
    function hfEmailIdentityConflictMessage(array $conflicts): ?string
    {
        if (!$conflicts) {
            return null;
        }

        $types = array_values(array_unique(array_column($conflicts, 'record_type')));
        sort($types);

        if ($types === ['supplier', 'user']) {
            $owner = 'a user account and a supplier record';
        } elseif (in_array('user', $types, true)) {
            $owner = 'another user account';
        } else {
            $owner = 'another supplier record';
        }

        return "This email is already assigned to {$owner}. Use a different email; user and supplier identities cannot share an address.";
    }
}

if (!function_exists('hfValidateUserSupplierEmailOwnership')) {
    function hfValidateUserSupplierEmailOwnership(
        PDO $db,
        $email,
        ?int $excludeUserId = null,
        ?int $excludeSupplierId = null
    ): ?string {
        return hfEmailIdentityConflictMessage(
            hfFindUserSupplierEmailConflicts($db, $email, $excludeUserId, $excludeSupplierId)
        );
    }
}

if (!function_exists('hfAcquireEmailIdentityLock')) {
    function hfAcquireEmailIdentityLock(PDO $db, $email): ?string
    {
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            return null;
        }

        $normalized = hfNormalizeIdentityEmail($email);
        if ($normalized === null) {
            return null;
        }

        $lockName = 'hf_email_identity_' . substr(hash('sha256', $normalized), 0, 40);
        $stmt = $db->prepare('SELECT GET_LOCK(?, 5)');
        $stmt->execute([$lockName]);
        if ((int) $stmt->fetchColumn() !== 1) {
            throw new RuntimeException('Email validation is busy. Please try saving again.');
        }

        return $lockName;
    }
}

if (!function_exists('hfReleaseEmailIdentityLock')) {
    function hfReleaseEmailIdentityLock(PDO $db, ?string $lockName): void
    {
        if ($lockName === null || $db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            return;
        }

        try {
            $stmt = $db->prepare('SELECT RELEASE_LOCK(?)');
            $stmt->execute([$lockName]);
        } catch (Throwable $ignored) {
            // MySQL also releases named locks when this request's connection closes.
        }
    }
}
