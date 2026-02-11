# Highland Fresh — QC Workflows Guide

> **Module:** Quality Control  
> **Version:** 4.0  
> **Last Updated:** February 9, 2026

This document explains the three main QC workflows in plain English with step-by-step scenarios.

---

## Table of Contents

1. [Yogurt Transformation (The "Yogurt Rule")](#1-yogurt-transformation-the-yogurt-rule)
2. [Disposal Workflow](#2-disposal-workflow)
3. [Batch Recall Workflow](#3-batch-recall-workflow)
4. [How The Three Workflows Relate](#4-how-the-three-workflows-relate)

---

## 1. Yogurt Transformation (The "Yogurt Rule")

### What Is It?

Highland Fresh has a core business rule: **near-expiry bottled milk must be transformed into Yogurt to prevent financial loss.** This is NOT disposal — it's value recovery. Instead of throwing away milk that's about to expire, the production team converts it into yogurt, which has a longer shelf life.

### Who Can Do This?

- **QC Officer** (e.g., Maria Santos)
- **General Manager**
- **Production Staff**

### Step-by-Step Scenario

#### Scenario: Fresh Milk 1L is expiring in 2 days

**Step 1 — QC Officer spots the problem**

Maria logs into the QC Dashboard. She sees the sidebar badge: "Expiry Management (3)". She clicks on it.

**Step 2 — Review expiring products**

The Expiry Management page shows:
- **3 Critical items** (expiring within 1-2 days) — Fresh Milk 1L (82 units), Fresh Milk 500ml (99 units), Chocolate Milk 1L (99 units)
- **3 Warning items** (expiring within 3-7 days) — more Fresh Milk batches and Fresh Cream

Each card shows: product name, batch number, available quantity, expiry date, days remaining, and location.

**Step 3 — Initiate transformation**

Maria clicks **"Transform to Yogurt"** on the Fresh Milk 1L card. A modal opens showing:
- Source product: Fresh Milk 1L
- Available quantity: 82 units
- Batch #: BATCH-20260203-001
- Expiry date: Feb 10, 2026

She enters:
- **Quantity**: 82 (all of it)
- **Notes**: "Near-expiry milk, converting to Plain Yogurt per Yogurt Rule"

She clicks **"Process Transformation"**.

**Step 4 — What happens behind the scenes**

1. System generates a transformation code: `YTF-000001`
2. 82 units are **deducted from finished goods inventory** immediately (the "less it" step)
3. Volume is calculated: 82 units × 1L = 82 liters
4. A **production run** is automatically created (e.g., `PRD-20260208-001`) for a yogurt recipe
5. The transformation status is set to `in_progress`

**Step 5 — Production makes the yogurt**

The Production Staff sees the new production run. They take the 82L of milk and follow the yogurt recipe. When the yogurt batch is complete, they update the run status.

**Step 6 — QC marks transformation as complete**

Maria goes to the "Yogurt Transformations" tab and finds `YTF-000001`. She clicks **Complete** and enters the actual yield quantity (e.g., 73.8L — about 90% yield). The transformation is now `completed`.

### Status Flow

```
pending ──→ in_progress (production run linked) ──→ completed ✓
   │
   └──→ cancelled (inventory restored if still pending)
```

### Important Rules

| Rule | Detail |
|------|--------|
| **Only milk products** | Only categories `pasteurized_milk`, `flavored_milk`, or `milk` can be transformed |
| **Cannot exceed stock** | Quantity entered must not exceed available quantity |
| **Immediate deduction** | Unlike disposal, inventory is deducted immediately on creation |
| **Cancellation restores stock** | If cancelled while still `pending`, the quantity goes back to inventory |
| **Not waste** | This is tracked separately from disposal — it's value recovery |

---

## 2. Disposal Workflow

### What Is It?

When products cannot be saved (expired, contaminated, spoiled, failed QC), they must be formally disposed of. Highland Fresh requires **General Manager approval** before any disposal to prevent fraud and ensure financial accountability.

### Who Can Do This?

- **QC Officer** — creates the disposal request, executes disposal
- **General Manager** — approves or rejects
- **Warehouse Raw Staff** — reports issues to QC, executes approved disposals for raw materials
- **Warehouse FG Staff** — reports issues to QC, executes approved disposals for finished goods

> **Internal Control Note:** Warehouse staff cannot initiate disposals themselves. When they discover damaged or spoiled items, they must notify QC who validates and creates the disposal request. This separation of duties prevents the custodian of inventory from authorizing its removal.

### Step-by-Step Scenario

#### Scenario: Expired raw milk needs disposal

**Step 1 — QC Officer identifies the problem**

Maria sees raw milk ID #5 in the Raw Milk Inventory tab. It has 25L remaining but expired on Feb 7 (1 day ago). It cannot be transformed into yogurt because it's already expired, not just near-expiry.

**Step 2 — Create disposal request**

Maria clicks the **red trash icon** on the expired item. She fills in the disposal form:
- **Source Type**: Raw Milk
- **Source ID**: 5
- **Quantity**: 25 liters
- **Disposal Category**: `expired`
- **Disposal Reason**: "Expired raw milk - past shelf life, not safe for processing"
- **Disposal Method**: Drain (for liquids)
- **Notes**: "Disposal via drain per SOP"

She submits. A disposal code is generated: `DSP-20260208-0001`. Status: **`pending`**.

**Step 3 — General Manager reviews**

The GM logs in and sees a pending disposal notification. He reviews:
- What product? Raw milk, 25L
- Why? Expired (1 day past shelf life)
- Value at risk? ₱750.00 (25L × ₱30/L)
- Who requested? Maria Santos

He clicks **"Approve"** and adds a note: "Confirmed expired milk, approved for drain disposal."

Status moves to: **`approved`**.

**Step 4 — Execution**

An authorized person (QC or Warehouse) physically disposes of the milk. They can complete the disposal from:
- **QC Module**: QC > Disposals > Click "Complete Disposal" on the approved item
- **Warehouse Raw Module**: Dashboard > "Approved Disposals - Ready for Execution" section
- **Warehouse FG Module**: Dashboard > "Approved Disposals - Ready for Execution" section

When completing, they can optionally record:
- Witness name
- Disposal location
- Additional notes

**Step 5 — Inventory is deducted**

On completion, the system automatically:
- Deducts 25L from `raw_milk_inventory.remaining_liters`
- Sets the disposd fields (`disposal_id`, `disposed_at`, `disposed_liters`)
- If remaining = 0, status changes to `consumed`

Status: **`completed`** ✓

### Status Flow

```
pending ──→ approved (GM approves) ──→ completed (physically disposed) ✓
   │            
   ├──→ rejected (GM rejects — terminal, must create new request)
   │
   └──→ cancelled (initiator or GM cancels)
```

### Disposal Categories

| Category | When to Use |
|----------|-------------|
| `expired` | Product is past its expiry date |
| `spoiled` | Product has gone bad before expiry (temperature issue, etc.) |
| `contaminated` | Foreign material, chemical contamination |
| `qc_failed` | Failed quality control tests |
| `damaged` | Physical damage to packaging |
| `rejected_receipt` | Incoming material rejected at receiving |
| `production_waste` | Normal waste from production process |
| `other` | Anything else (explain in notes) |

### Disposal Methods

| Method | For |
|--------|-----|
| Drain | Liquids (milk, cream) |
| Incineration | Solids that cannot be composted |
| Composting | Organic waste |
| Return to Supplier | Defective incoming materials |
| Third-party Collection | Hazardous waste |

### Important Rules

| Rule | Detail |
|------|--------|
| **GM approval required** | No disposal can be executed without General Manager approval |
| **Only initiator or GM can cancel** | Prevents unauthorized cancellation |
| **Inventory deducted on completion only** | Stock remains "available" until disposal is physically done |
| **Value is tracked** | Every disposal records the financial loss (`quantity × unit_cost`) |
| **Material Balance** | Raw Materials = Finished Goods + Waste. Nothing can "disappear" |

---

## 3. Batch Recall Workflow

### What Is It?

When a product batch is found to have a safety or quality issue **after it has been dispatched to stores/customers**, a recall is initiated. This is the most serious QC action. Highland Fresh follows FDA recall classification.

### Who Can Do This?

- **QC Officer** — creates the recall request, sends notifications, logs returns
- **General Manager** — approves or rejects the recall
- **Sales Custodian** — sends notifications to affected locations (from Sales dashboard)
- **Warehouse FG Staff** — logs product returns (from Warehouse FG dashboard)

### Step-by-Step Scenario

#### Scenario: A customer reports illness from BATCH-20260203-001

**Step 1 — QC Officer receives complaint and investigates**

Maria gets a report that a customer purchased Fresh Milk 1L from Store ABC and became ill. She traces it to `BATCH-20260203-001`.

**Step 2 — Create recall request**

Maria goes to **Batch Recalls** in the sidebar. She clicks **"New Recall"** and fills in:
- **Batch**: BATCH-20260203-001 (Fresh Milk 1L)
- **Recall Class**: `Class I` (Dangerous — potential health hazard)
- **Reason**: "Customer reported illness after consumption. Investigating possible pathogen contamination."
- **Evidence Notes**: "Customer complaint #12345, sample sent to lab"

She submits. A recall code is generated: `RCL-20260208-001`. Status: **`pending_approval`**.

**Step 3 — System auto-populates affected locations**

The system automatically checks delivery records and finds:
- Store ABC — 20 units dispatched on Feb 4
- Store XYZ — 15 units dispatched on Feb 5
- Store DEF — 10 units dispatched on Feb 6

These are recorded as **affected locations** with contact details.

**Step 4 — General Manager reviews and approves**

The GM sees the urgent recall request. Given it's Class I (dangerous), he immediately approves:
- Reviews the batch details, quantity produced, quantity dispatched
- Clicks **"Approve"**
- Adds notes: "Approved — Class I recall, contact all stores immediately"

Status: **`approved`**

**Step 5 — Notify affected locations**

Maria (or the sales team) contacts each affected location:
- Calls Store ABC, emails Store XYZ, visits Store DEF
- For each, she clicks **"Send Notification"** in the system to record that they were notified
- The system tracks: notification method, timestamp, who sent it

**Step 6 — Log returns**

As stores return products:
- Store ABC returns 18 of 20 units (2 already sold)
- Store XYZ returns all 15 units
- Store DEF returns 8 of 10 units

For each return, the warehouse logs:
- Which location returned
- How many units
- Condition of returned product (sealed/opened/damaged)
- Date received

On the first return logged, status changes to: **`in_progress`**

**Step 7 — Dispose returned products**

The returned products need to be disposed of. Maria creates a **disposal request** linked to the recall (this connects the recall and disposal workflows).

**Step 8 — Complete the recall**

Once all reasonable recovery efforts are done, Maria completes the recall:
- Total produced: 100 units
- Total dispatched: 45 units
- Total recovered: 41 units
- **Recovery rate: 91.1%**

She clicks **"Complete Recall"** and adds completion notes:
- "Lab results confirmed E. coli contamination. Root cause: post-pasteurization cross-contamination from chiller unit #3. Corrective action: chiller sanitized and re-validated."

Status: **`completed`** ✓

### Status Flow

```
pending_approval ──→ approved ──→ in_progress (first return logged) ──→ completed ✓
       │
       ├──→ cancelled (GM rejects)
       │
       └──→ cancelled (QC cancels before approval)
```

### Recall Classes (FDA Standard)

| Class | Severity | Example | Response Time |
|-------|----------|---------|---------------|
| **Class I** | **Dangerous** — could cause serious health problems or death | Pathogen contamination, undeclared allergens | Immediate (same day) |
| **Class II** | **Moderate** — may cause temporary or reversible health problems | Mislabeled allergens, incorrect storage instructions | Within 24-48 hours |
| **Class III** | **Minor** — unlikely to cause health problems | Minor labeling errors, cosmetic defects | Within 1 week |

### Important Rules

| Rule | Detail |
|------|--------|
| **GM approval required** | No recall can proceed without General Manager approval |
| **One recall per batch** | Cannot create a second active recall for the same batch |
| **Class I is highest priority** | System always sorts Class I recalls first |
| **Auto-populated locations** | System finds affected stores from delivery records |
| **Recovery rate tracked** | System calculates what % of dispatched products were recovered |
| **Full audit trail** | Every action (create, approve, notify, return, complete) is logged |

---

## 4. How The Three Workflows Relate

```
                    ┌─────────────────────────┐
                    │   Expiry Management      │
                    │   (Monitor all expiries) │
                    └─────────┬───────┬────────┘
                              │       │
              Product is      │       │    Product is
              near-expiry     │       │    expired/bad
              (can save)      │       │    (cannot save)
                              ▼       ▼
                 ┌────────────────┐  ┌────────────────┐
                 │   TRANSFORM    │  │    DISPOSE      │
                 │   to Yogurt    │  │    (Waste)      │
                 │                │  │                  │
                 │ Value Recovery │  │ Financial Loss   │
                 └────────────────┘  └────────────────┘
                                            ▲
                                            │
                                     Returned products
                                     need disposal
                                            │
                              ┌──────────────────────────┐
                              │      BATCH RECALL         │
                              │  (Product already sold    │
                              │   to customers/stores)    │
                              └──────────────────────────┘
```

### Decision Tree for QC Officer

```
Is the product expiring soon?
├── YES
│   ├── Is it still within shelf life (not yet expired)?
│   │   ├── YES → Is it a milk product?
│   │   │   ├── YES → TRANSFORM TO YOGURT ✓
│   │   │   └── NO  → Monitor / sell quickly / DISPOSE if no other option
│   │   └── NO (already expired)
│   │       └── DISPOSE ✗
│   └── Has it been dispatched to customers?
│       ├── YES → BATCH RECALL
│       └── NO  → Handle in warehouse (transform or dispose)
└── NO
    └── Is there a quality/safety issue?
        ├── YES → Has it been dispatched?
        │   ├── YES → BATCH RECALL
        │   └── NO  → DISPOSE
        └── NO → All good ✓
```

### Summary Comparison

| Feature | Yogurt Transformation | Disposal | Batch Recall |
|---------|----------------------|----------|--------------|
| **Purpose** | Save value | Remove unsafe product | Retrieve dispatched product |
| **GM Approval** | No | Yes | Yes |
| **Inventory Impact** | Immediate deduction | On completion only | On return logging |
| **Financial** | Value recovery | Financial loss | Financial loss + logistics cost |
| **Scope** | Internal (warehouse) | Internal (warehouse) | External (stores/customers) |
| **Severity** | Low | Medium | High |
| **Typical Products** | Near-expiry milk only | Any product | Any dispatched batch |
| **Creates** | Production run | Disposal record | Recall + returns + disposal |

---

## Quick Reference: Where To Find Things

| Action | Where |
|--------|-------|
| See what's expiring | QC > Expiry Management > "Expiring Soon" tab |
| Check raw milk expiry | QC > Expiry Management > "Raw Milk Inventory" tab |
| Transform milk to yogurt | Click "Transform to Yogurt" on an expiring milk card |
| See transformation history | QC > Expiry Management > "Yogurt Transformations" tab |
| Report damaged items (Warehouse) | Notify QC Officer directly (phone/email/in-person) |
| Create a disposal | QC > Disposals > "New Disposal" (QC role only) |
| Approve a disposal | GM Dashboard > Pending Disposals (GM role only) |
| Complete a disposal (QC) | QC > Disposals > Click "Complete Disposal" on approved item |
| Complete a disposal (WH Raw) | Warehouse Raw > Dashboard > "Approved Disposals" section |
| Complete a disposal (WH FG) | Warehouse FG > Dashboard > "Approved Disposals" section |
| Create a recall | QC > Batch Recalls > "New Recall" |
| Approve a recall | GM Dashboard > Pending Recalls (GM role only) |
| Send recall notifications | QC > Batch Recalls > View recall > Click notification buttons, OR Sales > Dashboard > "Pending Notifications" section |
| Log a return (QC) | QC > Batch Recalls > Open recall > "Log Return" |
| Log a return (WH FG) | Warehouse FG > Dashboard > "Active Recalls" section > "Log Return" |
| View audit trail | Each recall detail page has an activity log |
