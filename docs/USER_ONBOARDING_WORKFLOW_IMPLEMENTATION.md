# User Onboarding Workflow: Current Status and Implementation Plan

Date checked: 2026-04-25

## Goal
Implement a secure new-worker onboarding workflow that supports:
- Email-first login
- No-email fallback (employee ID login)
- First-login password change
- Role-based module access
- Proper reset/offboarding/session revocation controls

## Workflow Check Against Current System

1. New worker is hired.
- Status: Process step (outside app), not system-enforced.

2. HR gives details to GM/Admin: full name, role, employee ID, email availability.
- Status: Supported by current user fields.
- Evidence: users include first/last/full name, role, employee_id, email.

3. GM/Admin creates account in the system.
- Status: Supported.
- Evidence: admin user CRUD exists in API and UI.

4. System decides login method:
- With email: login ID is email.
- Without email: login ID is employee ID.
- Status: Missing.
- Current behavior: login is username-only.

5. System generates first-time access:
- With email: invite link or temporary password.
- Without email: sealed manual handoff via HR.
- Status: Missing automation.
- Current behavior: admin manually sets password in user form; no invite token flow.

6. New worker logs in on first day.
- Status: Supported (username/password flow).

7. System forces Change Password before dashboard.
- Status: Missing.
- Current behavior: no must_change_password flag and no forced-change endpoint.

8. After password change, worker can only see modules for assigned role.
- Status: Supported.
- Evidence: API role checks are present across modules.

9. If worker forgets password:
- With email: reset via email.
- Without email: HR/GM verification then admin reset.
- Status: Partial.
- Current behavior: admin can reset password manually in users page; no email reset flow.

10. If worker resigns/transfers:
- Account deactivated or role changed immediately.
- Active sessions revoked.
- Status: Partial.
- Current behavior: deactivation/role update exists, but active sessions are not revoked automatically when account changes.

## Evidence Files Checked
- api/auth/login.php
- api/admin/users.php
- api/config/auth.php
- api/auth/logout.php
- js/config/auth.js
- html/login.html
- html/admin/users.html
- highland_fresh (8).sql
- highland_fresh_azure.sql

## What Is Already Strong
- Role-based authorization and per-module role checks.
- Session tracking and server-side session revocation for logout.
- Login lockout handling and unlock action.
- Admin user management UI and API.

## Gaps To Implement
1. Email-first + employee-ID fallback login identifiers.
2. First-login forced password change.
3. Invitation token flow (preferred) or controlled temporary credential issuance.
4. Self-service email reset flow.
5. Automatic revocation of all active sessions when:
- password is reset
- role changes
- account is deactivated

## Recommended Implementation (Phased)

### Phase 1: Core Identity and First Login
1. Add users table columns:
- login_identifier (nullable during migration)
- login_type enum('email','employee_id')
- must_change_password tinyint(1) default 1
- password_set_at datetime null
- last_login_at datetime null

2. Backfill existing users:
- If email exists, set login_type='email', login_identifier=lower(email)
- Else if employee_id exists, set login_type='employee_id', login_identifier=employee_id
- Else fallback to username temporarily

3. Update login endpoint:
- Accept identifier + password
- Resolve by login_identifier
- Keep temporary backward compatibility with username for migration window only

4. Add endpoint: POST /api/auth/change_password.php
- Requires authenticated user
- Validates current password
- Updates password hash
- Sets must_change_password=0 and password_set_at=NOW()
- Revokes all other active sessions for this user

5. Update login response:
- Include must_change_password

6. Frontend login redirect rule:
- If must_change_password=1, route to force-change-password page before role dashboard

### Phase 2: Onboarding Credential Delivery
1. Preferred path: invitation token flow
- Create table auth_invites(token_hash, user_id, expires_at, used_at, created_by)
- Send one-time invite link by email
- Link opens set-password page
- Token is single-use and short-expiry

2. No-email fallback
- Admin generates one-time temporary credential in system
- Credential is displayed once and handed via HR in sealed form
- User is forced to change password at first login

### Phase 3: Password Recovery and Offboarding Hardening
1. Email users: forgot-password flow
- Request reset by email
- Store reset token hash and expiry
- Single-use reset endpoint

2. No-email users: admin reset path
- Keep existing admin reset, but mark must_change_password=1
- Add mandatory reason field for audit

3. Offboarding/session revocation
- On is_active=0, revoke all active sessions for that user
- On role change, revoke all active sessions for that user

## Minimal API/DB Changes Needed
- DB migration SQL for new user/auth fields and invite/reset tables.
- Auth helper method: revokeAllSessionsByUserId(userId, reason).
- Update admin/users.php create/update logic to:
- normalize email
- derive login_type/login_identifier
- set must_change_password rules
- revoke sessions on deactivation/role change/password reset

## Suggested Policy for Class Defense
- System is email-first for usability and recovery.
- System supports controlled exception for users without email using employee ID.
- Security parity is maintained for both paths:
- forced first password change
- role-based access controls
- lockout and audit trail
- session revocation on sensitive account changes

## Quick Status Summary
- Supported now: account create/update/deactivate, role-based access, logout session revoke, lockout.
- Missing now: email-first login, employee-ID fallback login, forced first password change, invite/reset-email flow, automatic revoke-all-sessions on account changes.
