# Production & Yield/Loss Calculation Module — Implementation Plan

## Problem Statement

The current production system lacks a unified mechanism for tracking material losses during production. Staff cannot explicitly record evaporation/processing losses, there is no real-time net yield calculation, and no dynamic packaging unit estimator exists. This leads to unaccounted material ("kalas") in the production pipeline.

**Goal:** Zero unaccounted waste — every milliliter of input must be accounted for as either finished product, recorded loss, or byproduct.

---

## Current State vs Required State

| Feature | Current State | Required State |
|---------|--------------|----------------|
| Evaporation/Loss Input | Only in pasteurization (shrinkage %) | At every production stage |
| Net Yield Calculation | Post-hoc variance only | Real-time: `Initial − Loss = Net Yield` |
| Packaging Unit Estimator | Manual/none | Auto-calculate units per packaging size |
| Material Reconciliation | Partial (byproducts tracked) | Full: `input = output + loss + byproduct` |
| Loss Categories | Only "shrinkage" | Evaporation, spillage, sampling, equipment retention, other |

---

## Implementation Plan

### Phase 1: Database Schema Changes

#### 1.1 New Table: `production_losses`

```sql
CREATE TABLE production_losses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    production_run_id INT NOT NULL,
    stage ENUM('pasteurization', 'processing', 'cooling', 'packaging') NOT NULL,
    loss_type ENUM('evaporation', 'spillage', 'sampling', 'equipment_retention', 'other') NOT NULL,
    loss_volume_ml DECIMAL(10,2) NOT NULL,
    loss_percentage DECIMAL(5,2) DEFAULT NULL,
    recorded_by INT NOT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT DEFAULT NULL,
    FOREIGN KEY (production_run_id) REFERENCES production_runs(id),
    FOREIGN KEY (recorded_by) REFERENCES users(id)
);
```

#### 1.2 New Table: `yield_calculations`

```sql
CREATE TABLE yield_calculations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    production_run_id INT NOT NULL,
    stage ENUM('pasteurization', 'processing', 'cooling', 'packaging') NOT NULL,
    input_volume_ml DECIMAL(10,2) NOT NULL,
    total_loss_ml DECIMAL(10,2) NOT NULL DEFAULT 0,
    byproduct_volume_ml DECIMAL(10,2) NOT NULL DEFAULT 0,
    net_yield_ml DECIMAL(10,2) NOT NULL,
    yield_efficiency_percent DECIMAL(5,2) NOT NULL,
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (production_run_id) REFERENCES production_runs(id)
);
```

#### 1.3 New Table: `packaging_rules` (Preset Packaging Conversion Rules)

```sql
CREATE TABLE packaging_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    packaging_size_ml INT NOT NULL,
    packaging_label VARCHAR(50) NOT NULL,
    priority_order INT NOT NULL DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id)
);
```

> **Purpose:** These are the preset conversion rules the teacher mentioned — "the system already knows" how to convert volume into packaging units. Each product has predefined packaging options stored here. Workers never manually calculate.

#### 1.4 New Table: `packaging_estimates`

```sql
CREATE TABLE packaging_estimates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    production_run_id INT NOT NULL,
    estimate_type ENUM('initial', 'revised') NOT NULL,
    basis_volume_ml DECIMAL(10,2) NOT NULL COMMENT 'Volume used for this estimate (initial_volume or net_yield)',
    packaging_size_ml INT NOT NULL,
    packaging_label VARCHAR(50) NOT NULL,
    estimated_units INT NOT NULL,
    actual_units INT DEFAULT NULL,
    remainder_ml DECIMAL(10,2) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (production_run_id) REFERENCES production_runs(id)
);
```

> **Two estimate types:**
> - `initial` — Generated automatically the moment raw milk volume is entered (Stage 1: at receipt)
> - `revised` — Recalculated automatically after each loss is recorded, reflecting current net yield (Stage 2: after processing)

#### 1.5 Alter `production_runs` Table

```sql
ALTER TABLE production_runs
    ADD COLUMN initial_volume_ml DECIMAL(10,2) DEFAULT NULL AFTER planned_quantity,
    ADD COLUMN total_loss_ml DECIMAL(10,2) DEFAULT 0 AFTER initial_volume_ml,
    ADD COLUMN total_byproduct_ml DECIMAL(10,2) DEFAULT 0 AFTER total_loss_ml,
    ADD COLUMN net_yield_ml DECIMAL(10,2) DEFAULT NULL AFTER total_byproduct_ml,
    ADD COLUMN material_reconciled TINYINT(1) DEFAULT 0 AFTER net_yield_ml,
    ADD COLUMN reconciliation_notes TEXT DEFAULT NULL AFTER material_reconciled;
```

---

### Phase 2: API Endpoints

#### 2.1 Loss Recording Endpoint

**File:** `api/production/losses.php`

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/production/losses` | Record a loss event |
| GET | `/api/production/losses?run_id={id}` | Get all losses for a run |
| GET | `/api/production/losses?run_id={id}&stage={stage}` | Get losses by stage |
| DELETE | `/api/production/losses/{id}` | Remove erroneous loss entry |

**POST Request Body:**
```json
{
    "production_run_id": 42,
    "stage": "processing",
    "loss_type": "evaporation",
    "loss_volume_ml": 250.00,
    "notes": "Normal boiling loss during yogurt heating"
}
```

**Response includes updated net yield:**
```json
{
    "success": true,
    "loss_id": 15,
    "updated_yield": {
        "initial_volume_ml": 5000.00,
        "total_loss_ml": 450.00,
        "total_byproduct_ml": 200.00,
        "net_yield_ml": 4350.00,
        "yield_efficiency_percent": 87.00
    }
}
```

#### 2.2 Yield Calculation Endpoint

**File:** `api/production/yield.php`

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/production/yield?run_id={id}` | Get current yield calculation |
| POST | `/api/production/yield/calculate` | Trigger recalculation |
| GET | `/api/production/yield/summary?run_id={id}` | Full reconciliation summary |

**Yield Calculation Formula:**
```
Net Yield = Initial Volume − Total Losses − Total Byproducts
Yield Efficiency % = (Net Yield / Initial Volume) × 100
Unaccounted Volume = Initial Volume − (Net Yield + Total Losses + Total Byproducts)
```

**Reconciliation Summary Response:**
```json
{
    "production_run_id": 42,
    "initial_volume_ml": 5000.00,
    "breakdown": {
        "finished_product_ml": 4200.00,
        "losses": {
            "evaporation": 250.00,
            "spillage": 50.00,
            "equipment_retention": 100.00
        },
        "byproducts": {
            "whey": 200.00,
            "cream": 150.00
        },
        "total_accounted_ml": 4950.00,
        "unaccounted_ml": 50.00
    },
    "reconciled": false,
    "reconciliation_status": "50ml unaccounted — needs investigation"
}
```

#### 2.3 Packaging Unit Estimator Endpoint (Two-Stage Auto-Estimation)

**File:** `api/production/packaging-estimate.php`

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/production/packaging-estimate` | Generate or recalculate packaging estimate |
| GET | `/api/production/packaging-estimate?run_id={id}` | Get all estimates (initial + revised) for a run |
| GET | `/api/production/packaging-estimate?run_id={id}&type=initial` | Get initial estimate only |
| GET | `/api/production/packaging-estimate?run_id={id}&type=revised` | Get latest revised estimate |
| PUT | `/api/production/packaging-estimate/{id}` | Update with actual packaged units |

**Auto-Trigger Behavior (workers never call this manually):**

| Trigger Point | Estimate Type | Volume Basis | When |
|---------------|---------------|--------------|------|
| **Stage 1: Raw milk receipt** | `initial` | `initial_volume_ml` | Automatically when initial volume is entered/confirmed on Start Run screen |
| **Stage 2: After any loss** | `revised` | Current `net_yield_ml` | Automatically after every loss entry is saved (pasteurization, processing, cooling, packaging) |

> **Key principle:** The system pre-knows packaging rules from `packaging_rules` table. Workers NEVER manually calculate how many bottles they can produce — the system does it automatically at both stages.

**POST Request Body (internal — triggered by system, not by user):**
```json
{
    "production_run_id": 42,
    "estimate_type": "initial",
    "basis_volume_ml": 10000.00
}
```

> Note: `packaging_sizes` and `priority_order` are fetched automatically from `packaging_rules` table based on the run's product. No user input required.

**Response — Stage 1 (Initial Estimate at Receipt):**
```json
{
    "success": true,
    "estimate_type": "initial",
    "basis_volume_ml": 10000.00,
    "estimates": [
        { "packaging_size_ml": 1000, "label": "1L Bottle", "estimated_units": 10, "volume_used_ml": 10000.00 },
        { "packaging_size_ml": 500, "label": "500mL Bottle", "estimated_units": 20, "volume_used_ml": 10000.00 }
    ],
    "note": "Showing all packaging options — worker selects preferred size at packaging stage"
}
```

**Response — Stage 2 (Revised Estimate After Losses):**
```json
{
    "success": true,
    "estimate_type": "revised",
    "basis_volume_ml": 7000.00,
    "previous_basis_ml": 10000.00,
    "reduction_ml": 3000.00,
    "estimates": [
        { "packaging_size_ml": 1000, "label": "1L Bottle", "estimated_units": 7, "volume_used_ml": 7000.00 },
        { "packaging_size_ml": 500, "label": "500mL Bottle", "estimated_units": 14, "volume_used_ml": 7000.00 }
    ],
    "note": "Revised after 3,000 mL total losses (evaporation + processing)"
}
```

**Estimation Algorithm (Greedy by priority):**
```
remaining = net_yield_ml
for each size in priority_order:
    units = floor(remaining / size)
    remaining = remaining - (units * size)
    record(size, units)
remainder = remaining
```

---

### Phase 3: Integration Points

#### 3.1 Modify `runs.php` — Status Transitions & Auto-Estimation Triggers

When a production run advances through stages, require loss reporting:

- **planned → in_progress:** Set initial volume → **AUTO-TRIGGER Stage 1 packaging estimate** (system fetches `packaging_rules` for this product and calculates all packaging options)
- **in_progress → processing:** Prompt for pasteurization losses (already partially exists as shrinkage)
- **processing → cooling:** Require loss input for processing stage
- **cooling → packaging:** Calculate net yield, show final revised packaging estimate
- **packaging → completed:** Reconcile — verify `input = output + loss + byproduct`

**Auto-recalculation trigger on every loss entry:**
```php
// In losses.php — after successfully recording any loss:
// 1. Recalculate net yield
// 2. Auto-generate revised packaging estimate
$this->recalculatePackagingEstimate($runId, 'revised', $newNetYield);
// The persistent Packaging Estimate Widget on the frontend auto-refreshes
```

#### 3.2 Modify `pasteurization.php` — Connect Shrinkage to Loss Table

Bridge existing shrinkage calculation to the new `production_losses` table:
```php
// After existing shrinkage calculation
$lossData = [
    'production_run_id' => $runId,
    'stage' => 'pasteurization',
    'loss_type' => 'evaporation',
    'loss_volume_ml' => $inputMl - $outputMl,
    'loss_percentage' => $shrinkage
];
// Insert into production_losses
```

#### 3.3 Modify `packaging.php` — Validate Against Estimates

Before allowing packaging to proceed:
1. Fetch the net yield for the run
2. Validate that total packaging request does not exceed net yield
3. Track actual vs estimated units

#### 3.4 Reconciliation Gate on Completion

A production run cannot move to `completed` status unless:
```
|unaccounted_volume| <= tolerance_ml (configurable, default: 50ml)
```

If unaccounted volume exceeds tolerance, force the user to either:
- Record additional loss entries to explain the discrepancy
- Add a reconciliation note with supervisor approval

---

### Phase 4: Business Logic & Validation Rules

| Rule | Implementation |
|------|---------------|
| Loss cannot exceed remaining volume | `loss_volume_ml <= (initial_volume_ml - already_recorded_losses)` |
| Net yield cannot be negative | Reject loss entries that would make yield < 0 |
| Packaging cannot exceed net yield | `SUM(packaged_ml) <= net_yield_ml` |
| Reconciliation tolerance | Configurable per product type (default 50ml or 1%) |
| Loss percentage alert | Warn if stage loss > expected (e.g., >5% for pasteurization) |
| Audit trail | All loss entries are immutable once production run is completed |

---

### Phase 5: Expected Yield from Master Recipe

Leverage the existing `expected_yield` field in the recipes system:

```php
// From master recipe
$expectedYieldRatio = $recipe['expected_yield']; // e.g., 0.92 = 92% yield expected
$expectedNetYield = $initialVolume * $expectedYieldRatio;
$acceptableLoss = $initialVolume * (1 - $expectedYieldRatio);

// Alert if actual loss exceeds expected
if ($actualTotalLoss > $acceptableLoss * 1.1) { // 10% buffer
    // Flag for supervisor review
}
```

---

### Phase 6: Reporting Queries

#### Daily Production Reconciliation Report
```sql
SELECT 
    pr.id AS run_id,
    pr.batch_number,
    pr.initial_volume_ml,
    pr.total_loss_ml,
    pr.total_byproduct_ml,
    pr.net_yield_ml,
    (pr.initial_volume_ml - pr.net_yield_ml - pr.total_loss_ml - pr.total_byproduct_ml) AS unaccounted_ml,
    pr.material_reconciled
FROM production_runs pr
WHERE DATE(pr.created_at) = CURDATE()
ORDER BY pr.created_at DESC;
```

#### Loss Breakdown by Type
```sql
SELECT 
    loss_type,
    SUM(loss_volume_ml) AS total_ml,
    COUNT(*) AS occurrences,
    AVG(loss_percentage) AS avg_percent
FROM production_losses
WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY loss_type
ORDER BY total_ml DESC;
```

---

## File Structure (New Files)

```
api/production/
├── losses.php              # Loss recording CRUD
├── yield.php               # Net yield calculation & reconciliation
├── packaging-estimate.php  # Dynamic packaging unit estimator
└── ...existing files...

sql/
├── add_yield_loss_tables.sql   # Migration script
```

---

## Implementation Priority

1. **Database migration** — add tables and columns
2. **Loss recording endpoint** — `losses.php`
3. **Yield calculation endpoint** — `yield.php` with auto-recalculation
4. **Packaging estimator** — `packaging-estimate.php`
5. **Integration with existing `runs.php`** — gate status transitions
6. **Integration with `pasteurization.php`** — bridge shrinkage to losses table
7. **Reconciliation enforcement** — block completion if unreconciled
8. **Reporting queries** — daily reconciliation dashboard data

---

## Phase 7: UI/UX Correct Workflow

### 7.1 Overview — Production Staff Journey

The production workflow follows a linear, stage-gated process. At each stage, the operator must **record losses before advancing**. The system provides real-time feedback on yield efficiency and blocks progression if data is incomplete.

```
┌─────────────┐     ┌──────────────────┐     ┌─────────────────┐     ┌─────────────┐     ┌──────────────┐
│  Start Run  │────▶│  Pasteurization  │────▶│   Processing    │────▶│   Cooling   │────▶│  Packaging   │
│ (Set Volume)│     │ (Record Losses)  │     │ (Record Losses) │     │(Record Loss)│     │(Estimate+Pack)│
└─────────────┘     └──────────────────┘     └─────────────────┘     └─────────────┘     └──────────────┘
                                                                                                  │
                                                                                                  ▼
                                                                                         ┌──────────────┐
                                                                                         │ Reconcile &  │
                                                                                         │   Complete   │
                                                                                         └──────────────┘
```

---

### 7.2 Screen-by-Screen Workflow

#### Screen 1: Start Production Run (Modified)

**Current state:** User selects recipe, batch number, and planned quantity.

**Required changes:**
- Add **"Initial Volume (mL)"** input field — auto-populated from material requisition if available, otherwise manual entry
- Display **Expected Yield** from master recipe (e.g., "Expected: 92% → ~4,600 mL from 5,000 mL input")
- Show a **Material Balance Card** that updates throughout the run
- **CRITICAL:** Immediately display the **Packaging Pre-Estimate Panel** — as soon as volume is entered, the system auto-calculates packaging outputs using preset rules from `packaging_rules` table. Workers never calculate manually.

```
┌─────────────────────────────────────────┐
│  MATERIAL BALANCE — Batch #B20260712-01 │
├─────────────────────────────────────────┤
│  Initial Volume:        10,000 mL       │
│  Total Losses:               0 mL       │
│  Total Byproducts:           0 mL       │
│  ─────────────────────────────────       │
│  Net Yield:             10,000 mL       │
│  Yield Efficiency:         100%         │
│  Status: ● Starting                     │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────────────────┐
│  📦 PACKAGING PRE-ESTIMATE (Auto-calculated)        │
│  Based on: 10,000 mL initial volume                 │
├─────────────────────────────────────────────────────┤
│                                                     │
│  If you choose 500mL:  → 20 pieces                  │
│  If you choose 1 Liter: → 10 pieces                 │
│  If you choose 200mL:  → 50 pieces                  │
│                                                     │
│  ℹ️ This is a pre-estimate. It will auto-update     │
│     as losses are recorded during production.       │
│                                                     │
│  Packaging rules preset for: [Yogurt - Plain]       │
└─────────────────────────────────────────────────────┘
```

> **Two-Stage Estimation — Stage 1:** This panel appears IMMEDIATELY when initial volume is confirmed. The system fetches packaging rules from the database for this product and shows all possible outputs. No manual calculation by workers.

---

#### Screen 2: Pasteurization Stage (Modified)

**Current state:** Has shrinkage % input between input/output volumes.

**Required changes:**
- Keep existing input/output volume fields and shrinkage calculation
- **Auto-record** the shrinkage as a loss entry in `production_losses` table (type: `evaporation`, stage: `pasteurization`)
- Add **"Additional Losses"** expandable section below shrinkage:

```
┌─────────────────────────────────────────────────┐
│  PASTEURIZATION — Loss Recording                │
├─────────────────────────────────────────────────┤
│  Input Volume:    5,000 mL                      │
│  Output Volume:   4,750 mL                      │
│  Shrinkage:       250 mL (5.0%) ← auto-saved   │
│                                                 │
│  ┌─ Additional Losses (optional) ────────────┐  │
│  │  + Add Loss Entry                         │  │
│  │                                           │  │
│  │  [Spillage    ▼] [___50___] mL            │  │
│  │  Notes: [Overflow during transfer_______] │  │
│  │                              [Save Loss]  │  │
│  └───────────────────────────────────────────┘  │
│                                                 │
│  Stage Total Loss: 300 mL (6.0%)                │
│  ⚠️ Above expected (5% typical) — review?       │
│                                                 │
│  [← Back]                    [Continue → Processing]│
└─────────────────────────────────────────────────┘
```

**Validation:**
- Cannot proceed without input/output volume recorded
- Warning (non-blocking) if loss > expected percentage from recipe
- Alert (blocking) if loss > 15% — requires supervisor note

---

#### Screen 3: Processing Stage (New Loss Input Section)

**Current state:** No explicit loss tracking.

**Required changes:**
- Add a **Loss Recording Form** at the processing stage
- This appears when the run enters "processing" status
- Multiple loss entries allowed per stage

```
┌─────────────────────────────────────────────────┐
│  PROCESSING — Record Losses                     │
├─────────────────────────────────────────────────┤
│  Input to this stage: 4,700 mL                  │
│  (from pasteurization output minus byproducts)  │
│                                                 │
│  Recorded Losses:                               │
│  ┌───────────────────────────────────────────┐  │
│  │ #1  Evaporation    │ 120 mL │ Boiling     │  │
│  │ #2  Equipment Ret. │  80 mL │ Tank walls  │  │
│  │                           [+ Add Loss]    │  │
│  └───────────────────────────────────────────┘  │
│                                                 │
│  Stage Total Loss: 200 mL (4.3%)                │
│  Running Net Yield: 4,500 mL                    │
│  Overall Efficiency: 90.0%                      │
│                                                 │
│  ☐ No additional losses to record for this stage│
│                                                 │
│  [← Back]                      [Continue → Cooling]│
└─────────────────────────────────────────────────┘
```

**Loss Entry Form (Modal/Inline):**
```
┌───────────────────────────────────────┐
│  Record Loss                          │
├───────────────────────────────────────┤
│  Loss Type:  [Evaporation         ▼] │
│              Options:                 │
│              - Evaporation            │
│              - Spillage               │
│              - Sampling               │
│              - Equipment Retention    │
│              - Other                  │
│                                       │
│  Volume:     [________] mL            │
│  Notes:      [________________________│
│              ________________________]│
│                                       │
│  [Cancel]              [Record Loss]  │
└───────────────────────────────────────┘
```

---

#### Screen 4: Cooling Stage (New Loss Input Section)

**Current state:** No loss tracking.

**Required changes:**
- Same loss recording form as Processing stage
- Typically lower losses here (mainly equipment retention, minor evaporation)

```
┌─────────────────────────────────────────────────┐
│  COOLING — Record Losses                        │
├─────────────────────────────────────────────────┤
│  Input to this stage: 4,500 mL                  │
│                                                 │
│  Recorded Losses:                               │
│  ┌───────────────────────────────────────────┐  │
│  │ #1  Equipment Ret. │  30 mL │ Pipe residue│  │
│  │                           [+ Add Loss]    │  │
│  └───────────────────────────────────────────┘  │
│                                                 │
│  Stage Total Loss: 30 mL (0.7%)                 │
│  Running Net Yield: 4,470 mL                    │
│  Overall Efficiency: 89.4%                      │
│                                                 │
│  ☐ No additional losses to record for this stage│
│                                                 │
│  [← Back]                    [Continue → Packaging]│
└─────────────────────────────────────────────────┘
```

---

#### Screen 5: Packaging Stage (Modified — Final Revised Estimate + Execution)

**Current state:** Manual packaging with conversion tracking.

**Required changes:**
- Show the **final revised packaging estimate** (this is NOT the first time the worker sees estimates — they've been visible since Screen 1 and updating throughout)
- Worker selects which packaging size to use — system already shows exact unit counts
- Track actual vs estimated during packing
- Add loss recording for packaging stage (breakage, spillage during filling)

> **Two-Stage Context:** By this screen, the worker has already seen packaging estimates since raw milk receipt (Stage 1). The numbers here reflect the FINAL recalculated estimate after all losses from pasteurization, processing, and cooling have been deducted (Stage 2 final).

```
┌─────────────────────────────────────────────────────┐
│  PACKAGING — Unit Estimator & Execution             │
├─────────────────────────────────────────────────────┤
│  Net Yield Available: 4,470 mL                      │
│                                                     │
│  ┌─ Packaging Estimate ──────────────────────────┐  │
│  │  Priority  │  Size    │  Units  │  Volume     │  │
│  │─────────────────────────────────────────────── │  │
│  │  1st       │  1L      │  4      │  4,000 mL   │  │
│  │  2nd       │  500mL   │  0      │      0 mL   │  │
│  │  3rd       │  200mL   │  2      │    400 mL   │  │
│  │─────────────────────────────────────────────── │  │
│  │  Total Packaged: 4,400 mL                     │  │
│  │  Remainder: 70 mL                             │  │
│  │                                               │  │
│  │  [Recalculate] [Accept Estimate]              │  │
│  └───────────────────────────────────────────────┘  │
│                                                     │
│  ┌─ Actual Packaging ────────────────────────────┐  │
│  │  1L Bottles:  Estimated: 4  │  Actual: [__4__]│  │
│  │  200mL Pouch: Estimated: 2  │  Actual: [__2__]│  │
│  │                                               │  │
│  │  Actual Total: 4,400 mL                       │  │
│  └───────────────────────────────────────────────┘  │
│                                                     │
│  ┌─ Packaging Losses ────────────────────────────┐  │
│  │  [Spillage ▼] [___20___] mL [Filling station] │  │
│  │                               [Record Loss]   │  │
│  └───────────────────────────────────────────────┘  │
│                                                     │
│  Remainder after packing: 50 mL                     │
│  Route remainder: [Byproduct ▼] [Next Batch ▼]     │
│                                                     │
│  [← Back]                    [Continue → Reconcile] │
└─────────────────────────────────────────────────────┘
```

---

#### Screen 6: Reconciliation & Completion (New Screen)

**Current state:** Run moves to "completed" with no reconciliation check.

**Required changes:**
- New **Reconciliation Summary** screen appears before final completion
- Shows full material balance breakdown
- Flags unaccounted volume
- Blocks completion if unaccounted > tolerance

```
┌─────────────────────────────────────────────────────────┐
│  MATERIAL RECONCILIATION — Batch #B20260712-01          │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  Initial Volume:               5,000 mL                 │
│                                                         │
│  ── Finished Product ──────────────────────────────     │
│  │  4× 1L Bottle              4,000 mL                  │
│  │  2× 200mL Pouch              400 mL                  │
│  │                    Subtotal: 4,400 mL                │
│                                                         │
│  ── Recorded Losses ───────────────────────────────     │
│  │  Evaporation (Pasteur.)       250 mL                 │
│  │  Spillage (Pasteur.)           50 mL                 │
│  │  Evaporation (Processing)     120 mL                 │
│  │  Equipment Ret. (Processing)   80 mL                 │
│  │  Equipment Ret. (Cooling)      30 mL                 │
│  │  Spillage (Packaging)          20 mL                 │
│  │                    Subtotal:   550 mL                │
│                                                         │
│  ── Byproducts ────────────────────────────────────     │
│  │  Whey → Byproduct tank         0 mL                  │
│  │  Cream → Cold storage           0 mL                 │
│  │                    Subtotal:     0 mL                │
│                                                         │
│  ══════════════════════════════════════════════════      │
│  Total Accounted:              4,950 mL                 │
│  Unaccounted:                     50 mL (1.0%)          │
│  Tolerance:                       50 mL ✅               │
│                                                         │
│  Status: ✅ WITHIN TOLERANCE — Ready to complete        │
│                                                         │
│  Reconciliation Notes (optional):                       │
│  [Minor residue in cooling tank — within acceptable___] │
│                                                         │
│  [← Back to Packaging]            [✓ Complete Run]      │
└─────────────────────────────────────────────────────────┘
```

**If OVER tolerance:**
```
┌─────────────────────────────────────────────────────────┐
│  ❌ RECONCILIATION FAILED                               │
│                                                         │
│  Unaccounted: 150 mL (3.0%) — exceeds 50 mL tolerance  │
│                                                         │
│  Options:                                               │
│  [Record Additional Loss]  — explain the discrepancy    │
│  [Request Supervisor Override]  — with justification    │
│  [← Go Back & Investigate]                              │
└─────────────────────────────────────────────────────────┘
```

---

### 7.3 Persistent UI Elements (Visible Throughout Production)

#### Material Balance Sidebar/Card

A persistent widget visible on all production stage screens:

```
┌──────────────────────────────┐
│  📊 Live Material Balance    │
├──────────────────────────────┤
│  Input:     10,000 mL        │
│  Losses:     3,000 mL (30%)  │
│  Byproduct:      0 mL  (0%) │
│  ──────────────────────       │
│  Net Yield:  7,000 mL (70%)  │
│                              │
│  Expected:   9,200 mL (92%)  │
│  Variance:  -2,200 mL        │
│                              │
│  ███████░░░░░░░ 70% eff.     │
│  [View Breakdown →]          │
└──────────────────────────────┘
```

#### Packaging Estimate Widget (Persistent — Auto-Updates at Every Stage)

A persistent widget that **dynamically recalculates** packaging outputs whenever losses reduce net yield. This is the core of the two-stage auto-estimation:

```
┌──────────────────────────────────────┐
│  📦 Packaging Estimate (LIVE)        │
├──────────────────────────────────────┤
│  Based on current net yield: 7,000mL │
│                                      │
│  500mL Bottle:  14 pieces            │
│  1L Bottle:      7 pieces            │
│  200mL Pouch:   35 pieces            │
│                                      │
│  ↓ Changed from initial estimate:    │
│    500mL: 20 → 14 pieces (-6)        │
│    1L:    10 →  7 pieces (-3)        │
│                                      │
│  ⚠️ Losses reduced output by 30%     │
└──────────────────────────────────────┘
```

> **Two-Stage Estimation — Stage 2 (continuous):** Every time a loss is recorded at ANY stage, this widget auto-recalculates. The worker always sees up-to-date packaging limits without calculating anything.

#### Stage Progress Indicator

Horizontal progress bar showing which stage is active and loss status:

```
[✅ Start] ─── [✅ Pasteur. -300mL] ─── [✅ Process. -200mL] ─── [● Cooling] ─── [○ Packaging] ─── [○ Complete]
```

---

### 7.4 Changes to Existing Screens

| Screen | Current Behavior | Required Change |
|--------|-----------------|-----------------|
| **Production Run List** | Shows status, batch, dates | Add column: "Yield %" with color indicator (green ≥90%, yellow 80-90%, red <80%) |
| **Production Run Detail** | Shows status progression | Add Material Balance card, loss history timeline |
| **Pasteurization Form** | Has shrinkage input | Auto-save to `production_losses`; add "Additional Losses" section |
| **Packaging Form** | Manual unit entry | Add Packaging Estimator before manual entry; track actual vs estimate |
| **Run Status Transition** | Free progression (mostly) | Gate: require loss confirmation at each stage before advancing |
| **Production Dashboard** | Summary stats | Add daily reconciliation summary widget, loss trending chart |

---

### 7.5 New Forms Summary

| Form | Location | Purpose |
|------|----------|---------|
| **Loss Entry Form** | Modal/inline at each stage | Record loss type, volume, notes |
| **Packaging Estimator** | Packaging stage (pre-fill) | Calculate units from net yield by priority |
| **Actual vs Estimate Input** | Packaging stage | Record actual packed units alongside estimates |
| **Reconciliation Summary** | Pre-completion gate | Full balance review with approve/reject |
| **Supervisor Override** | Reconciliation (failed) | Allow completion with justification when over tolerance |

---

### 7.6 Mobile / Factory Floor Considerations

Production staff use the system **on the factory floor**, often with wet/gloved hands. UI must be:

- **Large touch targets** — buttons minimum 48×48px, input fields with generous padding
- **Minimal keyboard input** — use dropdown selectors for loss type, numeric stepper or large number pad for volumes
- **Single-column layout** — no side-by-side forms on small screens
- **High contrast** — loss amounts in red, yield in green, warnings in amber
- **Offline tolerance** — loss entries should queue locally if connection drops, sync when restored
- **Quick-add shortcuts** — "Common losses" preset buttons based on product type (e.g., "Yogurt typical: 5% evaporation" pre-fills the value)

---

### 7.7 User Flow Diagram — Complete Path

```
OPERATOR STARTS RUN
        │
        ▼
┌───────────────────┐
│ Enter Initial Vol │──── Auto-fill from material requisition if linked
└───────┬───────────┘
        │
        ▼
┌─────────────────────────────────────────────────────────────┐
│ ★ STAGE 1: INITIAL PACKAGING PRE-ESTIMATE (AUTO)           │
│   System immediately calculates:                            │
│   "10L received → 20 pcs of 500mL OR 10 pcs of 1L"        │
│   (from preset packaging_rules — zero manual calculation)   │
└───────┬─────────────────────────────────────────────────────┘
        │
        ▼
┌───────────────────┐     ┌─────────────────────┐
│ PASTEURIZATION    │────▶│ Record shrinkage    │ (existing flow enhanced)
│                   │     │ + additional losses  │
└───────┬───────────┘     └─────────────────────┘
        │                       │
        │  ★ ESTIMATE AUTO-REVISES (e.g., 9.5L → 19 pcs of 500mL)
        │
        │ ← Must confirm "losses recorded" or "no additional losses"
        ▼
┌───────────────────┐     ┌─────────────────────┐
│ PROCESSING        │────▶│ Record losses       │ (new)
│                   │     │ (evap, equipment...) │
└───────┬───────────┘     └─────────────────────┘
        │                       │
        │  ★ ESTIMATE AUTO-REVISES (e.g., 7L → 14 pcs of 500mL)
        │
        │ ← Must confirm "losses recorded" or "no additional losses"
        ▼
┌───────────────────┐     ┌─────────────────────┐
│ COOLING           │────▶│ Record losses       │ (new)
│                   │     │ (equipment ret.)    │
└───────┬───────────┘     └─────────────────────┘
        │                       │
        │  ★ ESTIMATE AUTO-REVISES (final revised estimate ready)
        │
        │ ← Must confirm "losses recorded" or "no additional losses"
        ▼
┌───────────────────┐     ┌─────────────────────────────────────────┐
│ PACKAGING         │────▶│ ★ STAGE 2 FINAL: Worker sees final     │
│                   │     │   revised estimate, selects size,       │
│                   │     │   packs actual units, records losses    │
└───────┬───────────┘     │   Route remainder                      │
        │                 └─────────────────────────────────────────┘
        ▼
┌───────────────────┐
│ RECONCILIATION    │──── Auto-calculates full balance
│                   │──── If within tolerance → allow completion
│                   │──── If over tolerance → block, require action
└───────┬───────────┘
        │
        ▼
┌───────────────────┐
│ ✅ RUN COMPLETED  │──── Material fully accounted
└───────────────────┘
```

---

### 7.8 Confirmation Checkpoints (Stage Gates)

At each stage transition, the system enforces:

| Transition | Gate Requirement |
|-----------|-----------------|
| Start → Pasteurization | Initial volume must be set |
| Pasteurization → Processing | Shrinkage recorded OR "no additional losses" checked |
| Processing → Cooling | At least one loss entry OR "no losses" confirmation |
| Cooling → Packaging | At least one loss entry OR "no losses" confirmation |
| Packaging → Reconciliation | Actual units recorded, remainder routed |
| Reconciliation → Completed | Unaccounted ≤ tolerance OR supervisor override |

**"No losses" confirmation** — a checkbox: "☐ I confirm no measurable losses occurred at this stage" — prevents operators from accidentally skipping loss recording by requiring an explicit acknowledgment.

---

## Summary Formula

```
Initial Volume (from material requisition)
  − Evaporation Loss (recorded per stage)
  − Spillage Loss (recorded per stage)
  − Equipment Retention (recorded per stage)
  − Sampling Loss (recorded per stage)
  − Byproducts (whey, cream, buttermilk, skim)
  ─────────────────────────────────────────
  = Net Yield (available for packaging)

Net Yield ÷ Packaging Size = Estimated Units (per size)
Net Yield − Total Packaged = Remainder (must be ≤ tolerance OR routed)
```

**End goal:** `Unaccounted Volume = 0` for every production run.

---

## Phase 8: Two-Stage Automatic Packaging Pre-Estimation (Critical Feature)

> **Teacher's requirement:** "The system already knows" — workers must NEVER manually calculate packaging outputs. The system pre-estimates at receipt and automatically recalculates after every loss.

### 8.1 Concept Summary

| Stage | Trigger | Volume Basis | Example (10L milk, 500mL bottles) |
|-------|---------|--------------|-----------------------------------|
| **Stage 1 — At Receipt** | Initial volume entered on Start Run screen | `initial_volume_ml` | 10L → **20 pieces** of 500mL |
| **Stage 2 — After Losses** | Every time a loss is recorded (any stage) | Current `net_yield_ml` | 7L (after 3L evaporation) → **14 pieces** of 500mL |

### 8.2 How It Works (System Behavior)

1. **Admin pre-configures** `packaging_rules` table: each product has available packaging sizes (e.g., Yogurt → 200mL, 500mL, 1L)
2. **Worker receives milk from warehouse** and enters initial volume (e.g., 10,000 mL)
3. **System IMMEDIATELY** fetches `packaging_rules` for this product and calculates: `volume ÷ size = units`
   - 10,000 ÷ 500 = 20 pieces (500mL option)
   - 10,000 ÷ 1000 = 10 pieces (1L option)
4. **Worker sees estimates on Screen 1** — no calculation needed
5. **As production proceeds**, every recorded loss triggers auto-recalculation:
   - After pasteurization loss (500mL): net yield = 9,500 → 19 pieces of 500mL
   - After processing loss (2,500mL): net yield = 7,000 → 14 pieces of 500mL
6. **At packaging stage**, worker sees the FINAL revised estimate and selects their preferred packaging size — the system already shows exact unit counts

### 8.3 Database: `packaging_rules` (Preset Configuration)

```sql
-- Example data: what packaging options exist for each product
INSERT INTO packaging_rules (product_id, packaging_size_ml, packaging_label, priority_order) VALUES
(1, 200, '200mL Pouch', 3),
(1, 500, '500mL Bottle', 1),
(1, 1000, '1L Bottle', 2);
-- Product 1 (Yogurt) can be packaged in 200mL, 500mL, or 1L containers
-- System uses these rules to auto-calculate at every stage
```

### 8.4 Worker Experience (Zero Manual Calculation)

```
WORKER RECEIVES 10L FROM WAREHOUSE
    │
    └──▶ System immediately shows:
         "You can produce: 20× 500mL OR 10× 1L OR 50× 200mL"
         │
         ├── Loss recorded: 1L evaporation
         │   └──▶ System auto-updates: "19× 500mL OR 9× 1L OR 45× 200mL"
         │
         ├── Loss recorded: 2L processing
         │   └──▶ System auto-updates: "14× 500mL OR 7× 1L OR 35× 200mL"
         │
         └── At packaging: Worker sees final "14× 500mL OR 7× 1L"
             and simply selects their preferred size and packs.
```

### 8.5 Key Design Principles

1. **Zero manual math** — The system handles all volume-to-unit conversion
2. **Preset rules** — Packaging options are configured by admin, not entered by workers each time
3. **Instant visibility** — Estimates appear the moment volume is known (not only at packaging stage)
4. **Continuous updates** — Every loss automatically revises the estimate in real-time
5. **All options shown** — Worker sees all available packaging sizes and their unit counts simultaneously
