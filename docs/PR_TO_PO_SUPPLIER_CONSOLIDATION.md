# PR ? PO Supplier Consolidation — Requirements & Recommendation

**Document type:** Functional / Technical Design Note
**Module:** Purchasing — Phase 1 PR-to-PO conversion
**Status:** Proposed
**Related code:** `api/purchasing/purchase_orders.php`, `api/purchasing/purchase_requests.php`, `html/purchasing/purchase_orders.html`

---

## 1. Background

Highland Fresh's purchasing workflow currently follows a strict three-stage pipeline:

```
Warehouse Raw -> Purchase Request (PR) -> GM Approval -> Purchase Order (PO) -> Receiving
```

In practice, a single warehouse PR can request ingredients, packaging, and MRO items that are normally sourced from **different suppliers** (e.g. raw milk from a dairy cooperative, cellophane packaging from a packaging vendor, cleaning chemicals from an MRO supplier). The concern raised is whether the system correctly handles this multi-supplier scenario.

## 2. Concern (raised)

> *"The developers need to adjust the system so that if multiple requested items come from the same supplier, they are consolidated into a single PO. If the items require different suppliers, the system should generate separate POs for each."*

## 3. Confirmation — Is the concern valid?

**Yes. The current implementation does not satisfy the requirement.** A code review of the PR-to-PO flow confirms three structural limitations.

### 3.1 PO is hard-bound to a single supplier
**File:** `api/purchasing/purchase_orders.php` (line ~779)

```php
$required = ['supplier_id', 'purchase_request_id'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        Response::error("$field is required", 400);
    }
}
```

The API **requires** exactly one `supplier_id` per PO. There is no path to create a PO with multiple suppliers, or to attach different `supplier_id` values to individual line items.

### 3.2 One PR can only produce one PO
**File:** `api/purchasing/purchase_orders.php` (line ~792)

```php
$existingPO = $db->prepare("SELECT id, po_number FROM purchase_orders
    WHERE purchase_request_id = ? AND status NOT IN ('cancelled', 'rejected')");
$existingPO->execute([$purchaseRequestId]);
$existingPOData = $existingPO->fetch();
if ($existingPOData) {
    Response::error('Purchase Request ' . $prData['pr_number'] .
        ' already has an active PO: ' . $existingPOData['po_number'], 400);
}
```

The system **explicitly blocks** generating more than one PO from a single PR. A purchaser cannot manually split a multi-supplier PR into multiple POs.

### 3.3 PR line items carry no supplier information
**File:** `sql/phase1_purchase_requests.sql` (line ~47, `purchase_request_items` table)

The `purchase_request_items` schema contains:
- `ingredient_id`, `mro_item_id` — what the item is
- `quantity`, `unit`, `estimated_unit_price` — how much
- `purpose`, `notes` — why

It does **not** contain a `supplier_id`, a `preferred_supplier_id`, or any field that tells the purchaser which supplier an item should come from. The supplier decision is made entirely at PO-creation time, *after* GM approval.

### 3.4 UI enforces a single supplier per PO
**File:** `html/purchasing/purchase_orders.html` (line ~245)

```html
<select id="poSupplier" ... required onchange="renderSelectedSupplierDetails()">
    <option value="">Select supplier...</option>
```

The PO form has **one** supplier dropdown for the entire order.

### 3.5 Practical impact today
Given the current state, when a warehouse PR contains items that come from 3 suppliers, the purchaser can only:
1. Pick one supplier and only PO that supplier's items, leaving the other items stranded (and the PR marked "converted"), **or**
2. Reject the PR and ask the warehouse to re-issue separate PRs per supplier.

Both paths are operationally wrong. They inflate PR volume, lose the GM's holistic approval context, and create reconciliation gaps between Finance (PR) and Purchasing (PO).

## 4. Recommended approach (professional)

The fix is to **decouple the PR from the PO** at the level of "one-to-one" and introduce a **supplier-grouping step** between approval and PO creation. This is the same pattern used by mainstream ERP systems (SAP MM, Oracle iProcurement, Odoo Purchase, Dynamics 365 SCM).

### 4.1 Conceptual model

```
+--------------+    1 PR   +---------------------+    N POs   +--------------+
|  PR (GM OK)  |---------->|  PR-to-PO grouping  |----------->|  POs (1 per  |
|  N items     |           |  by supplier        |            |  supplier)   |
+--------------+           +---------------------+            +--------------+
```

- **One approved PR can produce N POs**, where N is determined by the number of distinct suppliers the purchaser selects for the items.
- **Items that map to the same supplier are consolidated into a single PO** for that supplier.
- The PR's `status` transitions to `converted` only when **all** of its items are covered by generated POs.

### 4.2 Data model changes

#### 4.2.1 Add supplier resolution to PR items
Add a `supplier_id` column to `purchase_request_items`. The purchaser fills this in during the PR-to-PO grouping step (it is **not** requested at PR creation time, since the warehouse typically does not know the supplier at request time).

```sql
ALTER TABLE purchase_request_items
    ADD COLUMN supplier_id INT(11) DEFAULT NULL AFTER mro_item_id,
    ADD COLUMN supplier_assigned_by INT(11) DEFAULT NULL AFTER supplier_id,
    ADD COLUMN supplier_assigned_at DATETIME DEFAULT NULL AFTER supplier_assigned_by,
    ADD INDEX idx_pri_supplier (supplier_id),
    ADD CONSTRAINT fk_pri_supplier FOREIGN KEY (supplier_id)
        REFERENCES suppliers(id) ON DELETE RESTRICT;
```

> **Note:** We deliberately do **not** require a supplier on PR creation. That would over-burden warehouse staff. Supplier resolution stays in the purchasing workflow.

#### 4.2.2 Allow multiple POs per PR
Remove the "one PO per PR" uniqueness guard, or replace it with a per-line-item allocation model:

```sql
-- New table: which PR lines are covered by which PO
CREATE TABLE IF NOT EXISTS purchase_request_item_po (
    id INT(11) NOT NULL AUTO_INCREMENT,
    purchase_request_item_id INT(11) NOT NULL,
    po_id INT(11) NOT NULL,
    quantity DECIMAL(12,2) NOT NULL,  -- supports partial PR-to-PO conversion
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_pri_po (purchase_request_item_id, po_id),
    KEY idx_pripo_po (po_id),
    CONSTRAINT fk_pripo_pri FOREIGN KEY (purchase_request_item_id)
        REFERENCES purchase_request_items(id) ON DELETE CASCADE,
    CONSTRAINT fk_pripo_po FOREIGN KEY (po_id)
        REFERENCES purchase_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

A PR is `converted` when **every** line has at least one row in `purchase_request_item_po` whose quantities sum to the requested quantity. Otherwise it stays `partially_converted`.

### 4.3 API changes — `api/purchasing/purchase_orders.php`

#### 4.3.1 New endpoint: `POST action=create_from_pr` (the recommended primary path)

The purchaser selects an approved PR, then sees a **supplier-grouping screen**:

```
PR-2026-014 (approved)
+-------------------------+--------+------+----------------------------+
| Item                    | Qty    | Unit | Supplier (assign)          |
+-------------------------+--------+------+----------------------------+
| Raw milk                | 500.00 | L    | [Dairy Co-op        v]     |
| Cellophane wrap         |  20.00 | roll | [Packaging Plus     v]     |
| Detergent               |  10.00 | jug  | [MRO Supplies       v]     |
+-------------------------+--------+------+----------------------------+
         [ Generate 3 POs ]   (one per distinct supplier)
```

The server groups items by `supplier_id`, generates one PO per group, all in a **single transaction**, and returns the list of created `po_number`s.

```php
// Pseudocode -- final implementation belongs in purchase_orders.php
function createPOsFromApprovedPR($db, $prId, $assignments, $currentUser) {
    // $assignments = [ ['pr_item_id' => 1, 'supplier_id' => 7], ... ]

    $groups = groupItemsBySupplier($assignments);
    $createdPOs = [];

    $db->beginTransaction();
    foreach ($groups as $supplierId => $prItemIds) {
        $poNumber = generateNextPONumber($db);
        $poId = insertPO($db, $poNumber, $supplierId, $prId, ...);
        insertPOItems($db, $poId, $prItemIds);
        recordPOCreationPriceHistory($db, $poId, $supplierId, $currentUser);
        logAudit(...);
        $createdPOs[] = ['po_number' => $poNumber, 'supplier_id' => $supplierId, 'item_count' => count($prItemIds)];
    }
    updatePRStatusToConvertedOrPartial($db, $prId);
    $db->commit();

    return $createdPOs;
}
```

#### 4.3.2 Keep the legacy `action=create` path as a fallback
For backward compatibility (and ad-hoc single-supplier POs that don't come from a PR), the existing `action=create` can remain, but its 1:1 guard with a PR is relaxed. A PO may now be created from zero or one PR; if it is created from a PR, only the assigned lines are drawn from.

### 4.4 UI changes — `html/purchasing/purchase_orders.html`

1. Replace the single supplier dropdown on the "Create PO from PR" form with a **supplier picker per line item**.
2. After the purchaser picks suppliers, show a **live summary**: *"This PR will generate 3 POs (Suppliers: Dairy Co-op, Packaging Plus, MRO Supplies)."*
3. On submit, call `create_from_pr` and show all generated `po_number`s, with quick links to each PO.

### 4.5 Receiving side — minor adjustment

Receiving and supplier-rejection flows already operate on a **single PO** (`receiving_reports.po_id`). Since the new model still produces one PO per supplier, **no changes are required** to receiving. The accounting trail (PR -> POs -> RRs -> payments) becomes cleaner, not messier.

### 4.6 Finance side — minor adjustment

Finance `payables` already groups by `purchase_orders` for AP aging and payment scheduling. With multi-PO-per-PR, Finance continues to pay per PO, which is correct (you cannot pay one supplier against another supplier's PO). No change needed.

## 5. Edge cases & guardrails

| Case | Handling |
|------|----------|
| PR has 1 supplier only | 1 PO is generated (same as today). |
| Purchaser forgets to assign a supplier to a line | Server rejects with a clear error: *"All PR items must have a supplier before POs can be generated."* |
| Same item appears in two PRs that are both approved | Allowed. Each PR generates its own POs. Receiving ties deliveries to the PO. |
| Two suppliers requested for the same line (split delivery) | Supported via `purchase_request_item_po.quantity` -- a line can be split across multiple POs. |
| Supplier is later marked inactive | Block PO creation referencing it; existing POs are unaffected. |
| Audit / SOX | Every grouping, PO generation, and PR status change is written to `audit_logs` and `purchase_request_status_history`. |

## 6. Migration & rollout

1. **Schema migration:** Add the new column and table (idempotent `ALTER` / `CREATE TABLE IF NOT EXISTS`). Ships behind a versioned SQL file in `sql/`.
2. **API rollout:** Deploy `create_from_pr` as the preferred path. Keep `create` working in deprecated mode for one release, log a warning when used.
3. **UI rollout:** Make the supplier-per-line screen the default for the "Create PO" page. Hide the old "single supplier, full PR" mode behind an advanced toggle.
4. **Training:** Brief the purchasing team -- the new flow is *one PR -> many POs* instead of *one PR -> one PO*. No retraining is required for warehouse or GM roles.

## 7. Summary

| Aspect | Today | After fix |
|--------|-------|-----------|
| POs per PR | 1 | N (one per distinct supplier) |
| Supplier decision | PO-creation time, one supplier for whole PO | PO-creation time, per line item, grouped server-side |
| Items from same supplier | Forced into the same supplier's PO anyway | Explicitly consolidated server-side |
| Items from different suppliers | Purchaser has to abandon / reissue PRs | Auto-split into separate POs in one transaction |
| PR `status` | `converted` after first PO | `converted` only after all lines are allocated; `partially_converted` otherwise |
| Receiving / Finance | Unchanged | Unchanged (still per PO) |

The recommendation is fully aligned with the concern as stated. The two-line requirement -- *"consolidate same-supplier items into one PO, split different-supplier items into separate POs"* -- is implemented by **one server-side grouping operation** at the moment of PO generation, driven by a per-line supplier assignment that the purchaser enters in the existing PR-to-PO screen.
