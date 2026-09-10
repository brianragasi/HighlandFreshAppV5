<?php

$root = dirname(__DIR__);
require_once $root . '/api/helpers/email_identity.php';

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, full_name TEXT, email TEXT)");
$db->exec("CREATE TABLE suppliers (id INTEGER PRIMARY KEY, supplier_code TEXT, supplier_name TEXT, email TEXT)");
$db->exec("INSERT INTO users (id, username, full_name, email) VALUES
    (1, 'sir.one', 'Sir One', 'Sir.One@Example.com'),
    (2, 'available.user', 'Available User', 'available@example.com')");
$db->exec("INSERT INTO suppliers (id, supplier_code, supplier_name, email) VALUES
    (10, 'SUP-0010', 'First Supplier', 'supplier@example.com')");

$cases = [
    'normalizes case and whitespace' =>
        hfNormalizeIdentityEmail('  Sir.One@Example.COM ') === 'sir.one@example.com',
    'blocks another user email' =>
        str_contains((string) hfValidateUserSupplierEmailOwnership($db, 'sir.one@example.com'), 'another user account'),
    'blocks another supplier email' =>
        str_contains((string) hfValidateUserSupplierEmailOwnership($db, 'SUPPLIER@example.com'), 'another supplier record'),
    'allows a new unclaimed email' =>
        hfValidateUserSupplierEmailOwnership($db, 'new@example.com') === null,
    'allows the current user to retain its email' =>
        hfValidateUserSupplierEmailOwnership($db, 'sir.one@example.com', 1, null) === null,
    'allows the current supplier to retain its email' =>
        hfValidateUserSupplierEmailOwnership($db, 'supplier@example.com', null, 10) === null,
];

$db->exec("INSERT INTO suppliers (id, supplier_code, supplier_name, email) VALUES
    (11, 'SUP-0011', 'Conflicting Supplier', 'sir.one@example.com')");
$combined = hfValidateUserSupplierEmailOwnership($db, 'sir.one@example.com');
$cases['reports a cross-type conflict'] =
    str_contains((string) $combined, 'a user account and a supplier record');

$usersSource = file_get_contents($root . '/api/admin/users.php');
$suppliersSource = file_get_contents($root . '/api/admin/suppliers.php');
$bootstrapSource = file_get_contents($root . '/api/bootstrap.php');
$usersPageSource = file_get_contents($root . '/html/admin/users.html');
$loginSource = file_get_contents($root . '/api/auth/login.php');
$forgotPasswordSource = file_get_contents($root . '/api/auth/forgot_password.php');
$cases['user create and edit use shared ownership validation'] =
    substr_count((string) $usersSource, 'hfValidateUserSupplierEmailOwnership') >= 2;
$cases['supplier create and edit use shared ownership validation'] =
    substr_count((string) $suppliersSource, 'hfValidateUserSupplierEmailOwnership') >= 2;
$cases['shared helper is loaded by the API bootstrap'] =
    str_contains((string) $bootstrapSource, "helpers/email_identity.php");
$cases['user form shows the specific email conflict'] =
    str_contains((string) $usersPageSource, 'firstFieldError')
    && str_contains((string) $usersPageSource, 'error?.errors?.email');
$cases['email login fails closed for historical duplicates'] =
    str_contains((string) $loginSource, '$emailIdentityAmbiguous')
    && str_contains((string) $loginSource, 'count($identifierMatches) === 1');
$cases['password recovery fails closed for historical duplicates'] =
    str_contains((string) $forgotPasswordSource, 'LIMIT 2')
    && str_contains((string) $forgotPasswordSource, 'count($matchingUsers) === 1');

foreach ($cases as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "Failed: {$label}.\n");
        exit(1);
    }
}

echo "Email identity integrity tests passed.\n";
