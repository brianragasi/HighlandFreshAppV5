# Security Unit Tests Guide

Use this when showing the instructor the security checks.

## What This Tests

These tests check seven small security rules:

- Bad email formats are rejected.
- Weak passwords are rejected.
- Passwords are stored as protected hashes, not plain text.
- Login password checking accepts the right password and rejects the wrong one.
- A session token is created after login.
- Expired tokens are detected.
- Unsafe HTML input is made safe before display.

## How To Run

Open a terminal in the project folder:

```bash
php tests/security_unit_tests.php
```

## What To Show

The result should show:

```text
UT-101 | PASS
UT-102 | PASS
UT-103 | PASS
UT-104 | PASS
UT-105 | PASS
UT-106 | PASS
UT-107 | PASS
Passed 7 of 7 tests.
```

## Plain Explanation

These are isolated tests. That means they check the security rules by themselves, without needing the database or the browser.

If all seven say PASS, it means the basic login safety rules are working: bad input is rejected, passwords are protected, tokens expire, and script tags are not allowed to run.
