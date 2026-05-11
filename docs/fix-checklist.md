# Fix Checklist

Here is a prioritized, step-by-step checklist of what you need to fix. To keep
it from being overwhelming, focus on one category at a time.

## Phase 1: Fix the Purchasing Workflow (The most critical issue)

- [ ] Correct the Approval Flow: Hardcode this exact sequence:
  1. Warehouse makes a Purchase Request (PR).
  2. General Manager approves the PR.
  3. Purchaser canvases suppliers and creates a Purchase Order (PO).
  4. General Manager approves the PO.
- [ ] Create a Printable PO: Build a specific page or PDF generator for the
  Purchase Order. It must look like a real business document with the company
  name, item list, and blank lines for the Manager and Supplier to sign.

## Phase 1 Implementation Checklist (PR -> GM -> PO -> GM)

- [ ] Limit PR creation to Warehouse Raw only.
- [ ] Ensure PR approval is GM-only (Warehouse cannot approve).
- [ ] Require PO creation to be Purchaser-only.
- [ ] Require PO approval to be GM-only.
- [ ] Enforce that every PO references an approved PR.
- [ ] Ensure PR status transitions are strict (pending -> approved/rejected only).
- [ ] Ensure PO status transitions are strict (draft -> pending -> approved only).
- [ ] Add clear error messages for role and status violations.

## Phase 1 Test Guide (Quick Manual Checks)

### Setup

- [ ] Create test users for roles: warehouse_raw, purchaser, general_manager.
- [ ] Prepare at least one raw material item for PR line items.

### PR Creation (Warehouse Raw Only)

- [ ] Login as Warehouse Raw and create a PR.
- [ ] Login as Production and confirm PR creation is blocked.
- [ ] Login as Purchaser and confirm PR creation is blocked.

### PR Approval (GM Only)

- [ ] Login as GM and approve the PR.
- [ ] Login as Warehouse Raw and confirm PR approval is blocked.

### PO Creation (Purchaser Only, From Approved PR)

- [ ] Login as Purchaser and create a PO linked to the approved PR.
- [ ] Try creating a PO without PR linkage and confirm it is blocked.
- [ ] Try creating a PO from a pending PR and confirm it is blocked.
- [ ] Login as GM and confirm PO creation is blocked (GM should only approve).

### PO Approval (GM Only)

- [ ] Login as GM and approve the PO.
- [ ] Login as Purchaser and confirm PO approval is blocked.

### Status and Audit Sanity

- [ ] Verify PR status history shows pending -> approved.
- [ ] Verify PO status history shows draft -> pending -> approved.
- [ ] Confirm error messages clearly describe why an action is blocked.

## Phase 2: Fix Receiving & Inventory Logic

- [ ] Add a "Reject" Feature: When a delivery arrives, change the receiving form
  so the user can input how many items were Accepted and how many were Rejected
  (spoiled/defective). Save the rejected items in a separate database log.
- [ ] Remove the "Plus (+)" Button: Delete any button that lets users randomly
  add stock. Stock should only increase when a delivery is officially received
  through a PO.
- [ ] Segregate Expired Goods: Create a separate tab or table for
  "Expired/Spoiled Items" so they don't mix with the clean, usable inventory.
- [ ] Fix Threshold Alerts: Ensure the system automatically sends a
  notification/alert to the GM and Warehouse when an item drops below its
  minimum stock level.

## Phase 3: Data Integrity & UI/UX (Quick Wins)

- [ ] Use Dropdown Menus: Stop making users type the names of raw materials.
  Change text inputs to dropdown menus (pulled from your database) to prevent
  typos.
- [ ] Add "Create New Product": Make sure the Admin has a simple form to add a
  completely new raw material or new finished product to the system.
- [ ] Clean Up the Design: Adjust your color combinations and layout so it looks
  more professional and utilizes the whole screen (remove the unnecessary chat
  box if it's taking up space).

## Phase 4: Production & Pricing (Do this last)
- [ ] Add a Quality Control (QC) Log: Create a simple form for production staff
  to input machine temperatures and production data, replacing their manual
  pen-and-paper tracking.

Tip to avoid overwhelm: Start entirely with Phase 1. Do not even look at the
 design or the inventory until the Purchasing workflow (PR -> GM -> PO -> GM) is
 working perfectly. That was the evaluator's biggest concern.
