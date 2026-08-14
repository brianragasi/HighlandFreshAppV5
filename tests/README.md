# Tests Kept For Demonstration

This folder now keeps only tests that are useful to explain and repeat.

## Security Unit Tests

Run:

```bash
php tests/security_unit_tests.php
```

Use this when showing the instructor the security checks:

- email format rejection
- password strength rejection
- password hashing
- password matching
- session token creation
- token expiry
- unsafe HTML cleanup

The plain-English guide is in `tests/SECURITY_UNIT_TESTS_GUIDE.md`.

## Pack Preview Test

Run:

```bash
node tests/pack_preview.test.js
```

This checks the pack-size math used when the system shows how many supplier packs are needed for a recipe ingredient.

## Removed User Roles

Run:

```bash
php tests/retired_roles_test.php
```

This checks that the retired Maintenance Head and Bookkeeper roles are gone from account creation, permissions, and the database. It also confirms that the main admin login still works as General Manager and that historical maintenance records remain readable.

## Removed

The old live smoke scripts and one-time verify scripts were removed because they used demo logins, touched the local database, and were mainly temporary checks from earlier fixes.
