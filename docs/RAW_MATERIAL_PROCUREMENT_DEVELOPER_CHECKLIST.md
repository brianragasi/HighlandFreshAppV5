# Raw Material Procurement Flow - Developer Checklist

Use this checklist to convert the panel feedback into concrete development tasks for the Raw Material, General Manager, Purchaser, Finance, and Warehouse flow.

## Target Workflow

Raw Material/Warehouse identifies low stock -> creates a formal Purchase Request (PR) -> General Manager reviews and approves/rejects PR -> Purchaser converts approved PR into Purchase Order (PO) and assigns supplier -> General Manager approves/rejects PO -> Warehouse receives delivery against approved PO -> Finance verifies complete documents before payment.

## Priority 1 - Printable Business Forms

- [x] Create a printable Purchase Request (PR) document view.
- [x] Create a printable Purchase Order (PO) document view.
- [x] Create a printable Receiving Report (RR) document view.
- [x] Add Highland Fresh logo/company header to printable documents.
- [x] Add document numbers, dates, prepared by, reviewed by, approved by, and status fields.
- [x] Add formal item tables with item name, description, unit, requested quantity, approved quantity, price fields where applicable, and remarks.
- [x] Add signature lines for required roles.
- [x] Display signatory names from the database instead of hardcoding names.
- [x] Ensure printed forms still show the correct current General Manager if the GM account changes.
- [x] Add print/download buttons only for roles allowed to print each document.
- [x] Make the printed layout look like an actual business form, not a normal web table.

## Priority 2 - Purchase Request (PR) Module

- [x] Redesign PR as a formal internal request document, not a simple notification button.
- [x] Allow one PR to contain multiple requested raw materials/items.
- [x] Remove supplier selection from PR creation.
- [x] Enforce rule: PR is an internal wishlist only; supplier is assigned later in the PO.
- [x] Add PR line items with item, unit, requested quantity, purpose/reason, and remarks.
- [x] Allow users to start a PR directly from the Critical/Low Stock list.
- [x] Auto-fill PR item details when the user clicks a low-stock item.
- [x] Prioritize or highlight low-stock items in the PR item dropdown.
- [x] Show current stock and minimum stock beside each selectable item.
- [x] Validate that requested quantities are positive and complete.
- [x] Save PR status history and timestamps.
- [x] Support PR statuses: Draft, Pending GM Approval, Approved, Rejected, Converted to PO.
- [x] Prevent converted PRs from being edited unless explicitly reopened by an authorized role.

## Priority 3 - General Manager Module

- [x] Add a GM dashboard section for pending PR approvals.
- [x] Add a GM dashboard section for pending PO approvals.
- [x] Let GM approve or reject PRs with remarks.
- [x] Let GM approve or reject POs with remarks.
- [x] Ensure GM approval records the approver user ID, approver name, date, and time.
- [x] Ensure rejected documents cannot continue to the next workflow step.
- [x] Show printable document previews before or after approval.
- [x] Restrict approval buttons to General Manager role only.
- [x] Ensure the system fetches the active/current GM name dynamically for signatures.

## Priority 4 - Purchaser Module

- [x] Show only GM-approved PRs as available for PO creation.
- [x] Convert approved PRs into POs without retyping all item details.
- [x] Allow supplier assignment only during PO creation.
- [x] Allow PO items to be grouped or filtered by supplier.
- [x] Add supplier details to the PO form: supplier name, address/contact, terms, and delivery details.
- [x] Add current price entry per PO line item.
- [x] Preserve historical prices using batch/price history records.
- [x] Do not overwrite old batch prices when a new price is entered.
- [x] Support price changes, such as sugar being Php 110 last week and Php 150 now.
- [x] Add PO statuses: Draft, Pending GM Approval, Approved, Rejected, Partially Received, Fully Received, Closed.
- [x] Block printing/sending final PO until GM approves it.
- [x] Notify Warehouse when a PO is GM-approved and pending delivery.
- [x] Notify Finance when a PO has been approved and later received.

## Priority 5 - Warehouse Receiving Module

- [x] Add a Pending Deliveries dashboard based on GM-approved POs.
- [x] Let Warehouse open a pending PO and receive actual delivered items.
- [x] Display ordered quantity beside received quantity.
- [x] Add Accepted Quantity input per item.
- [x] Add Rejected Quantity input per item.
- [x] Add rejection reason per rejected item.
- [x] Allow partial deliveries when only some items arrive.
- [x] Keep the PO open when delivery is partial.
- [x] Mark PO as fully received only when all accepted quantities complete the order.
- [x] Prevent stock from increasing for rejected quantities.
- [x] Record received by, date/time received, supplier, delivery reference, and driver/delivery notes where needed.
- [x] Generate a Receiving Report after receiving.
- [x] Ensure stock increases only through official receiving against an approved PO.

## Priority 6 - Batch, Lot, Expiry, and Waste Handling

- [x] Auto-generate lot/batch numbers instead of requiring manual typing.
- [x] Use a consistent batch format per item, such as ITEMCODE-Lot-001 or similar.
- [x] Link received stock to PO, supplier, receiving report, batch/lot number, and price.
- [x] Require expiry date only for perishable items.
- [x] Hide or disable expiry date for non-perishable items such as plastic bottles and cellophane.
- [x] Add item master setting for perishable vs non-perishable.
- [x] Add Spoilage/Waste module for items damaged after receiving.
- [x] Allow Warehouse or authorized users to record waste quantity, reason, date, and responsible user.
- [x] Deduct approved waste from active inventory.
- [x] Keep waste records separate from rejected delivery records.
- [x] Add reports for expired, spoiled, rejected, and wasted raw materials.

## Priority 7 - Finance Module

- [ ] Show Finance only POs that are GM-approved and received/partially received.
- [ ] Require Finance to verify PO, Receiving Report, and invoice before payment.
- [ ] Record payment status: Unpaid, Partially Paid, Paid, Cancelled.
- [ ] Support cash and credit/utang payment terms.
- [ ] Support partial or staggered supplier payments.
- [ ] Record payment method, reference/check number, payment date, amount paid, and remarks.
- [ ] Prevent payment release for rejected or unapproved POs.
- [ ] Keep Finance focused on disbursement/payment tracking, not inventory receiving or quality checking.

## Priority 8 - UI/UX Improvements

- [ ] Make the overall interface more professional and business-like.
- [ ] Reduce oversized fonts where they look too large.
- [ ] Improve spacing, negative space, and text balance.
- [ ] Replace plain web tables with document-like views where forms are required.
- [ ] Ensure important workflow status is visible without opening too many pages.
- [ ] Use clear action labels: Create PR, Submit for GM Approval, Convert to PO, Approve PO, Receive Delivery, Record Payment.
- [ ] Add helpful empty states for no pending PRs, no pending POs, and no pending deliveries.
- [ ] Keep low-stock indicators visually clear in item lists and dropdowns.

## Priority 9 - Security and Role-Based Access Control

- [ ] Verify login sessions expire or invalidate correctly.
- [ ] Ensure each module checks the logged-in role before showing actions.
- [ ] Warehouse can create PRs and receive deliveries, but cannot approve PRs/POs.
- [ ] General Manager can approve/reject PRs and POs, but should not perform warehouse receiving.
- [ ] Purchaser can create POs from approved PRs, but cannot approve final POs.
- [ ] Finance can record payments, but cannot create PRs, approve POs, or receive stock.
- [ ] Hide unauthorized buttons in the UI.
- [ ] Also enforce authorization in backend/API endpoints, not only in the frontend.
- [ ] Add audit logs for create, submit, approve, reject, convert, receive, waste, and payment actions.

## End-to-End Test Checklist

- [ ] Create a low-stock raw material alert.
- [ ] Click the low-stock item and confirm it auto-fills a PR line item.
- [ ] Add multiple items to one PR.
- [ ] Submit PR for GM approval.
- [ ] Login as GM and approve the PR.
- [ ] Login as Purchaser and convert the approved PR into a PO.
- [ ] Confirm supplier selection happens in PO, not PR.
- [ ] Enter current item prices and confirm older batch prices remain unchanged.
- [ ] Submit PO for GM approval.
- [ ] Login as GM and approve the PO.
- [ ] Confirm Warehouse sees the approved PO under Pending Deliveries.
- [ ] Receive a partial delivery with accepted and rejected quantities.
- [ ] Confirm only accepted quantities increase inventory.
- [ ] Confirm the PO remains open if not fully delivered.
- [ ] Receive the remaining quantities and confirm the PO becomes fully received.
- [ ] Record spoilage/waste after stock is already in inventory.
- [ ] Confirm active inventory decreases after waste is recorded.
- [ ] Login as Finance and record payment only after required documents are complete.
- [ ] Print PR, PO, and Receiving Report and confirm document layout, logo, item table, and signatures are correct.
- [ ] Test unauthorized roles and confirm restricted actions are hidden and blocked.

## Definition of Done

- [ ] PR, PO, RR, receiving, waste, and payment records are connected in one traceable workflow.
- [ ] PR never asks for supplier.
- [ ] PO always references an approved PR.
- [ ] PO cannot be finalized without GM approval.
- [ ] Warehouse receives only from approved POs.
- [ ] Finance pays only after approval and receiving documents are complete.
- [ ] Signatory names are dynamic from the database.
- [ ] Printable forms are ready for panel demonstration.
- [ ] Role restrictions work in both UI and backend/API.
- [ ] Manual testing covers the full Raw Material -> GM -> Purchaser -> Warehouse -> Finance flow.
