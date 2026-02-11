# Quality Control (QC) Module Documentation

## Highland Fresh Dairy System - Version 4.0

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [Module Overview](#module-overview)
3. [Core Functions](#core-functions)
4. [System Architecture](#system-architecture)
5. [User Interface Components](#user-interface-components)
6. [Business Logic & Calculations](#business-logic--calculations)
7. [API Reference](#api-reference)
8. [Database Schema](#database-schema)
9. [Workflow Diagrams](#workflow-diagrams)
10. [Standards & Compliance](#standards--compliance)

---

## Executive Summary

The **Quality Control (QC) Module** is a critical component of the Highland Fresh Dairy System that serves as the **"Safety and Financial Gatekeeper"** of the company. It bridges the gap between farmers and customers by ensuring:

- Only high-quality milk is accepted and paid for
- All products meet food safety standards
- Complete traceability from farm to customer
- Automated pricing based on quality metrics

### Key Responsibilities

| Area | Description |
|------|-------------|
| **Inbound Milk Testing** | Grading raw milk from farmers using Milkosonic SL50 analyzer |
| **Farmer Payout Calculation** | Automated pricing based on ANNEX B standards |
| **Batch Release Authorization** | Verifying production batches before warehouse transfer |
| **Expiry Management** | Tracking product shelf life and authorizing transformations |
| **CCP Monitoring** | Auditing Critical Control Points in production |

---

## Module Overview

### Access Roles

The QC module is accessible to the following user roles:

| Role | Access Level |
|------|--------------|
| `qc_officer` | Full access to all QC functions |
| `general_manager` | Full access + administrative oversight |

### Navigation Structure

```
QC Dashboard
├── Main Menu
│   └── Dashboard (Overview statistics)
├── Inbound Milk
│   ├── Milk Receiving (Record farmer deliveries)
│   ├── Milk Grading (QC testing & pricing)
│   └── Farmers (Farmer management)
├── Production QC
│   └── Batch Release (Verify & release batches)
├── Inventory QC
│   ├── Expiry Management (Track expiring products)
│   └── Disposals (Write-off with GM approval)
└── Reports
    ├── Print Labels
    └── Farmer Summary
```

---

## Core Functions

### 1. Inbound Milk Grading (The "Gatekeeper" Function)

The most critical function that tests raw milk as it arrives from farmers using a **Milkosonic SL50 Milk Analyzer**.

#### Analyzer Parameters (from receipt printout)

| Parameter | Description | Unit |
|-----------|-------------|------|
| **Fat** | Butter fat content | % |
| **SNF** | Solids-Not-Fat | % |
| **Density** | Milk density (Specific Gravity) | g/ml |
| **Lactose** | Lactose content | % |
| **Salts** | Salt content | % |
| **Protein** | Protein content | % |
| **Total Solids** | Total solid content | % |
| **Added Water** | Detection of adulteration | % |
| **Temp. Sample** | Sample temperature | °C |
| **Freez. Point** | Freezing point | °C |

#### Acceptance/Rejection Criteria

| Test | Standard | Action if Failed |
|------|----------|------------------|
| **APT (Alcohol Precipitation Test)** | Must be Negative | **REJECT** |
| **Titratable Acidity** | 0.11% - 0.18% (≤0.24% max) | Deduction or **REJECT if ≥0.25%** |
| **Specific Gravity** | ≥ 1.025 | **REJECT** if below |

---

### 2. Milk Grading & Pricing (ANNEX B Implementation)

The QC module automatically calculates farmer payouts based on the **ANNEX B Agreed Rates**.

#### Base Pricing Structure

| Component | Amount (₱/L) |
|-----------|-------------|
| Base Price | ₱25.00 |
| Production Incentive | ₱5.00 |
| **Standard Total** | **₱30.00** |

#### Quality Grade Assignment

Milk is automatically graded A, B, C, or D based on test results:

| Grade | Criteria | Description |
|-------|----------|-------------|
| **Grade A** | Fat ≥ 4.0%, Acidity ≤ 0.16%, Sediment = 1 | Premium quality |
| **Grade B** | Fat ≥ 3.5%, Acidity ≤ 0.18%, Sediment ≤ 2 | Good quality |
| **Grade C** | Fat ≥ 3.0%, Acidity ≤ 0.20%, Sediment ≤ 2 | Standard quality |
| **Grade D** | All other passing milk | Basic quality |

---

### 3. Batch Release Authorization

QC verifies production batches before they are released to Finished Goods Warehouse.

#### Verification Checklist

| Check Point | Verification |
|-------------|--------------|
| **Total Pieces** | Count matches Production's reported output |
| **Box/Case Count** | Full containers properly sealed |
| **Loose Pieces** | Any partial containers accounted for |
| **Packaging Integrity** | All units properly sealed/labeled |
| **Organoleptic: Taste** | Pleasant, characteristic taste |
| **Organoleptic: Appearance** | Proper color, no separation |
| **Organoleptic: Smell** | Pleasant, no off-odors |

#### Batch Release Workflow

```
Production Batch (status: pending)
        ↓
QC Officer Reviews
        ↓
    ┌───┴───┐
    │       │
  Release  Reject
    │       │
    ↓       ↓
fg_received=0  Status: rejected
barcode generated  Reason logged
Status: released
        ↓
FG Warehouse Receives
        ↓
fg_received=1
```

---

### 4. Expiry Management (The "Yogurt Rule")

QC monitors product shelf life and authorizes transformations for near-expiry products.

#### Expiry Alert Categories

| Category | Days to Expiry | Action |
|----------|----------------|--------|
| **OK** | >7 days | Normal inventory |
| **Warning** | 4-7 days | Monitor closely |
| **Critical** | 0-3 days | Consider transformation |
| **Expired** | <0 days | Dispose or transform |

#### Yogurt Transformation Process

When bottled milk approaches expiry, it can be transformed into yogurt:

```
Near-Expiry Milk Identified
        ↓
QC Verifies Safety (still fit for processing)
        ↓
Transformation Initiated
        ↓
Deduct from FG Inventory
        ↓
Create Production Run (Yogurt recipe)
        ↓
Link to Transformation Record
        ↓
Production Completes Yogurt Batch
        ↓
Mark Transformation Complete
```

**Key Principle**: This is documented as "Transformation" NOT "Waste" to prevent financial loss.

---

### 5. CCP Monitoring (Critical Control Points)

QC audits production Critical Control Points:

| CCP | Standard | Method |
|-----|----------|--------|
| **Pasteurization Temperature** | ≥72°C for 15 seconds (HTST) | Automated monitoring |
| **Cooling Temperature** | ≤4°C after processing | Temperature logs |
| **Time & Temperature Logs** | Documented at all phases | Digital records |

---

### 6. Disposal/Write-Off Module (GM Approval Workflow)

QC initiates disposal requests for rejected milk or products, which require General Manager approval before execution.

#### Disposal Categories

| Category | Description | Typical Source |
|----------|-------------|----------------|
| **QC Failed** | Failed quality test | Milk receiving, batch inspection |
| **Expired** | Past expiry date | Finished goods inventory |
| **Spoiled** | Deteriorated during storage | Raw milk, finished goods |
| **Contaminated** | Cross-contamination detected | Any inventory |
| **Damaged** | Physical/packaging damage | Finished goods |
| **Rejected at Receiving** | Farmer milk rejected | Milk receiving |
| **Production Waste** | Line waste, overruns | Production batches |

#### Disposal Methods

| Method | Use Case |
|--------|----------|
| **Drain** | Liquid disposal (rejected milk) |
| **Incinerate** | Contaminated materials |
| **Animal Feed** | Safe but unsellable products |
| **Compost** | Organic waste |
| **Special Waste** | Hazardous materials (contractor) |

#### Approval Workflow

```
QC Officer Identifies Item for Disposal
        ↓
Create Disposal Request
(source, quantity, category, reason)
        ↓
    Status: PENDING
        ↓
GM Reviews Request
        ↓
    ┌───┴───┐
    │       │
 Approve   Reject
    │       │
    ↓       ↓
Status:   Status:
APPROVED  REJECTED
    ↓
QC/Warehouse Executes
(witness, location, notes)
        ↓
Inventory Deducted
        ↓
Status: COMPLETED
```

#### API Endpoints

| Method | Action | Description |
|--------|--------|-------------|
| `GET` | list | List all disposals with filters |
| `GET` | stats | Get disposal statistics |
| `GET` | pending | Get pending approvals (GM) |
| `POST` | create | Create new disposal request |
| `PUT` | approve | GM approves disposal |
| `PUT` | reject | GM rejects disposal |
| `PUT` | complete | Execute approved disposal |
| `DELETE` | cancel | Cancel pending disposal |

---

## System Architecture

### File Structure

```
HighlandFreshAppV4/
├── api/
│   ├── qc/
│   │   ├── dashboard.php        # Dashboard statistics API
│   │   ├── milk_grading.php     # Milk testing & grading API
│   │   ├── batch_release.php    # Batch verification API
│   │   ├── expiry_management.php # Expiry & transformation API
│   │   ├── disposals.php        # Disposal/write-off API (NEW)
│   │   ├── deliveries.php       # Milk receiving API
│   │   ├── farmers.php          # Farmer management API
│   │   └── farmer.php           # Single farmer details
│   └── admin/
│       └── qc-standards.php     # QC standards management API
├── html/
│   └── qc/
│       ├── dashboard.html       # QC Dashboard UI
│       ├── milk_grading.html    # Milk grading interface
│       ├── batch_release.html   # Batch release interface
│       ├── expiry_management.html # Expiry management UI
│       ├── disposals.html       # Disposal management UI (NEW)
│       ├── milk_receiving.html  # Receiving interface
│       ├── farmers.html         # Farmer management UI
│       └── print-labels.html    # Label printing
├── sql/
│   └── create_disposals_module.sql # Disposal tables migration (NEW)
└── js/
    └── qc/
        ├── dashboard.service.js      # Dashboard API calls
        ├── milk_grading.service.js   # Grading service + calculations
        ├── batch_release.service.js  # Batch release service
        ├── expiry.service.js         # Expiry management service
        ├── disposal.service.js       # Disposal service (NEW)
        ├── deliveries.service.js     # Deliveries service
        └── farmers.service.js        # Farmers service
```

---

## User Interface Components

### QC Dashboard

The dashboard provides real-time overview of QC activities:

#### Statistics Cards

| Card | Metric |
|------|--------|
| Today's Deliveries | Total milk receiving count today |
| Pending Tests | Deliveries awaiting QC testing |
| Accepted/Rejected | Today's test results |
| Liters Accepted | Volume of approved milk |
| Pending Batch Releases | Production batches awaiting QC |
| Expiry Alerts | Products expiring within 3 days |

#### Data Tables

- **Top Farmers This Week**: Ranked by volume delivered
- **Recent Tests**: Latest 10 QC test results
- **Week's Grade Summary**: Breakdown by grade (A, B, C, D)

---

## Business Logic & Calculations

### ANNEX B Pricing Formulas

#### Fat Content Adjustment

```javascript
// Fat adjustment per liter (positive = bonus, negative = deduction)
function calculateFatAdjustment(fatPercentage) {
    if (fat >= 1.5 && fat < 2.0) return -1.00;
    if (fat >= 2.0 && fat < 2.5) return -0.75;
    if (fat >= 2.5 && fat < 3.0) return -0.50;
    if (fat >= 3.0 && fat < 3.5) return -0.25;
    if (fat >= 3.5 && fat <= 4.0) return 0.00;  // Standard
    if (fat > 4.0 && fat <= 4.5) return +0.25;
    if (fat > 4.5 && fat <= 5.0) return +0.50;
    if (fat > 5.0 && fat <= 5.5) return +0.75;
    if (fat > 5.5 && fat <= 6.0) return +1.00;
    if (fat > 6.0 && fat <= 6.5) return +1.25;
    if (fat > 6.5 && fat <= 7.0) return +1.50;
    if (fat > 7.0 && fat <= 7.5) return +1.75;
    if (fat > 7.5 && fat <= 8.0) return +2.00;
    if (fat > 8.0 && fat <= 8.5) return +2.25;
    return 0.00;
}
```

#### Titratable Acidity Deduction

```javascript
// Acidity deduction per liter (always negative or zero)
function calculateAcidityDeduction(titratableAcidity) {
    if (acidity >= 0.25) return REJECT;  // Auto-reject
    if (acidity <= 0.18) return 0.00;    // Standard range
    if (acidity >= 0.19 && acidity < 0.20) return 0.25;
    if (acidity >= 0.20 && acidity < 0.21) return 0.50;
    if (acidity >= 0.21 && acidity < 0.22) return 0.75;
    if (acidity >= 0.22 && acidity < 0.23) return 1.00;
    if (acidity >= 0.23 && acidity < 0.24) return 1.25;
    if (acidity >= 0.24 && acidity < 0.25) return 1.50;
    return 0.00;
}
```

#### Sediment Grade Deduction

```javascript
// Sediment deduction per liter
function calculateSedimentDeduction(sedimentGrade) {
    switch (grade) {
        case 1: return 0.00;  // Clean
        case 2: return 0.50;  // Slight
        case 3: return 1.00;  // Dirty
    }
}
```

#### Final Price Calculation

```javascript
Final Price per Liter = 
    ₱30.00 (Base + Incentive)
    + Fat Adjustment
    - Acidity Deduction
    - Sediment Deduction

Total Amount = Volume (L) × Final Price per Liter
```

### Example Calculation

| Parameter | Value |
|-----------|-------|
| Volume | 50 L |
| Fat % | 4.2% |
| Acidity % | 0.19% |
| Sediment | Grade 1 |

**Calculation:**
- Base Price: ₱30.00
- Fat Adjustment: +₱0.25 (4.2% earns bonus)
- Acidity Deduction: -₱0.25 (0.19% incurs deduction)
- Sediment Deduction: ₱0.00 (Grade 1)
- **Final Price/L**: ₱30.00 + ₱0.25 - ₱0.25 - ₱0.00 = **₱30.00**
- **Total Amount**: 50L × ₱30.00 = **₱1,500.00**

---

## API Reference

### 1. Dashboard API (`/api/qc/dashboard.php`)

#### GET - Dashboard Statistics

**Response Data:**
```json
{
    "today": {
        "date": "2026-02-08",
        "total_deliveries": 15,
        "pending_tests": 3,
        "accepted": 10,
        "rejected": 2,
        "accepted_liters": 750.5,
        "rejected_liters": 45.0
    },
    "week_grades": [
        { "grade": "A", "count": 5, "avg_fat": 4.2, "avg_ta": 0.16 },
        { "grade": "B", "count": 8, "avg_fat": 3.7, "avg_ta": 0.17 }
    ],
    "pending_batch_releases": 4,
    "expiry_alerts": { "count": 12, "quantity": 340 },
    "top_farmers": [...],
    "recent_tests": [...]
}
```

---

### 2. Milk Grading API (`/api/qc/milk_grading.php`)

#### GET - List Tests

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `id` | int | Get single test |
| `receiving_id` | int | Filter by receiving record |
| `farmer_id` | int | Filter by farmer |
| `status` | string | 'accepted' or 'rejected' |
| `date_from` | date | Start date filter |
| `date_to` | date | End date filter |
| `page` | int | Page number |
| `limit` | int | Items per page |

#### POST - Create QC Test (Grade Milk)

**Request Body:**
```json
{
    "receiving_id": 123,
    "fat_percentage": 3.8,
    "titratable_acidity": 0.17,
    "temperature_celsius": 5,
    "sediment_grade": 1,
    "density": 1.028,
    "protein_percentage": 3.2,
    "lactose_percentage": 4.5,
    "snf_percentage": 8.5,
    "notes": "Good quality milk"
}
```

**Response:** Includes calculated grade, pricing breakdown, and total amount.

---

### 3. Batch Release API (`/api/qc/batch_release.php`)

#### GET - List Batches

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `batch_id` | int | Get single batch |
| `status` | string | 'pending', 'released', 'rejected' |
| `action` | string | 'stats' for statistics |

#### PUT - Release/Reject Batch

**Request Body:**
```json
{
    "batch_id": 456,
    "action": "release",
    "organoleptic_taste": true,
    "organoleptic_appearance": true,
    "organoleptic_smell": true,
    "qc_notes": "All checks passed"
}
```

---

### 4. Expiry Management API (`/api/qc/expiry_management.php`)

#### GET Actions

| Action | Description |
|--------|-------------|
| `expiring` | Products expiring within X days |
| `raw_milk` | Raw milk inventory with expiry status |
| `finished_goods` | FG inventory by ID |
| `transformations` | Transformation history |
| `yogurt_products` | Available yogurt recipes |

#### POST - Initiate Yogurt Transformation

**Request Body:**
```json
{
    "inventory_id": 789,
    "quantity": 100,
    "notes": "Near expiry - transforming to yogurt",
    "create_production_run": true
}
```

---

### 5. Milk Receiving API (`/api/qc/deliveries.php`)

#### GET - List Receiving Records

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `farmer_id` | int | Filter by farmer |
| `milk_type_id` | int | Filter by milk type |
| `status` | string | 'pending_qc', 'accepted', 'rejected' |
| `date_from` | date | Start date |
| `date_to` | date | End date |

#### POST - Record New Receiving

**Request Body:**
```json
{
    "farmer_id": 5,
    "milk_type_id": 1,
    "volume_liters": 50,
    "receiving_date": "2026-02-08",
    "receiving_time": "06:30:00",
    "temperature_celsius": 6,
    "transport_container": "Stainless steel can",
    "visual_inspection": "pass",
    "notes": "Morning delivery"
}
```

---

## Database Schema

### Core QC Tables

#### `qc_milk_tests`

Primary table for milk quality test results.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT | Primary key |
| `test_code` | VARCHAR | Unique test code (QCT-XXXXXX) |
| `receiving_id` | INT | FK to milk_receiving |
| `test_datetime` | DATETIME | Test timestamp |
| `milk_type_id` | INT | FK to milk_types |
| `fat_percentage` | DECIMAL(5,2) | Fat content % |
| `titratable_acidity` | DECIMAL(5,3) | Acidity % |
| `temperature_celsius` | DECIMAL(4,1) | Sample temp |
| `specific_gravity` | DECIMAL(6,4) | Density |
| `sediment_grade` | TINYINT | 1, 2, or 3 |
| `grade` | CHAR(1) | A, B, C, or D |
| `base_price_per_liter` | DECIMAL(10,2) | ₱30.00 |
| `fat_adjustment` | DECIMAL(10,2) | Price adjustment |
| `acidity_deduction` | DECIMAL(10,2) | Price deduction |
| `sediment_deduction` | DECIMAL(10,2) | Price deduction |
| `final_price_per_liter` | DECIMAL(10,2) | Calculated price |
| `total_amount` | DECIMAL(12,2) | Total payout |
| `is_accepted` | BOOLEAN | Accept/Reject flag |
| `rejection_reason` | TEXT | Reason if rejected |
| `tested_by` | INT | FK to users |

#### `milk_receiving`

Farmer milk delivery records.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT | Primary key |
| `receiving_code` | VARCHAR | RCV-YYYYMMDD-NNN |
| `rmr_number` | INT | Raw Milk Receipt number |
| `farmer_id` | INT | FK to farmers |
| `milk_type_id` | INT | FK to milk_types |
| `volume_liters` | DECIMAL(10,2) | Volume delivered |
| `receiving_date` | DATE | Delivery date |
| `receiving_time` | TIME | Delivery time |
| `status` | ENUM | pending_qc, in_testing, accepted, rejected |
| `accepted_liters` | DECIMAL(10,2) | Volume accepted |
| `rejected_liters` | DECIMAL(10,2) | Volume rejected |

#### `production_batches`

Production batch records with QC status.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT | Primary key |
| `batch_code` | VARCHAR | Batch identifier |
| `recipe_id` | INT | FK to master_recipes |
| `qc_status` | ENUM | pending, released, rejected, on_hold |
| `qc_released_at` | DATETIME | Release timestamp |
| `released_by` | INT | FK to users |
| `barcode` | VARCHAR | Generated barcode |
| `organoleptic_taste` | BOOLEAN | Taste check |
| `organoleptic_appearance` | BOOLEAN | Appearance check |
| `organoleptic_smell` | BOOLEAN | Smell check |
| `qc_notes` | TEXT | QC officer notes |

#### `yogurt_transformations`

Near-expiry to yogurt transformation records.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT | Primary key |
| `transformation_code` | VARCHAR | YTF-XXXXXX |
| `source_inventory_id` | INT | FK to finished_goods_inventory |
| `source_quantity` | INT | Units transformed |
| `source_volume_liters` | DECIMAL | Volume in liters |
| `target_recipe_id` | INT | FK to master_recipes |
| `production_run_id` | INT | FK to production_runs |
| `status` | ENUM | pending, in_progress, completed, cancelled |
| `initiated_by` | INT | FK to users |
| `completed_by` | INT | FK to users |

---

## Workflow Diagrams

### Milk Receiving to Payment Flow

```
   FARMER DELIVERS MILK
            ↓
   ┌─────────────────────┐
   │   WAREHOUSE RAW     │
   │   Records Delivery  │
   │   (milk_receiving)  │
   └─────────┬───────────┘
             ↓
   Status: pending_qc
             ↓
   ┌─────────────────────┐
   │      QC OFFICER     │
   │   Tests with        │
   │   Milkosonic SL50   │
   └─────────┬───────────┘
             ↓
        APT Test?
      ┌────┴────┐
   Positive   Negative
      ↓          ↓
   REJECT    Acidity OK?
              ┌───┴───┐
           ≥0.25%   <0.25%
              ↓        ↓
           REJECT   Density OK?
                    ┌───┴───┐
                 <1.025   ≥1.025
                    ↓        ↓
                 REJECT   ACCEPT
                            ↓
                    Calculate Pricing
                    (ANNEX B Rules)
                            ↓
                    Assign Grade
                    (A, B, C, D)
                            ↓
                    ┌──────────────┐
                    │ Record Total │
                    │   Amount     │
                    └──────┬───────┘
                           ↓
                    Add to Raw Milk
                      Inventory
                           ↓
                    FINANCE PAYOUT
                    (Summarized monthly)
```

### Production Batch Release Flow

```
   PRODUCTION COMPLETES BATCH
            ↓
   Status: qc_status = 'pending'
            ↓
   ┌─────────────────────┐
   │      QC OFFICER     │
   │   Reviews Batch     │
   └─────────┬───────────┘
             ↓
   Organoleptic Checks:
   • Taste ✓/✗
   • Appearance ✓/✗
   • Smell ✓/✗
             ↓
      All Passed?
      ┌────┴────┐
     NO         YES
      ↓          ↓
   REJECT    RELEASE
      ↓          ↓
   qc_status   qc_status
   ='rejected' ='released'
   Log reason  Generate barcode
      ↓          ↓
   NOTIFY     fg_received=0
   Production       ↓
              FG Warehouse
                Receives
                    ↓
              fg_received=1
```

---

## Standards & Compliance

### Daily Testing Schedule

#### A. Organoleptic Tests (Everyday)

| Test | Standard | Method |
|------|----------|--------|
| Smell | Pleasant, characteristic | Observe/Note |
| Appearance | No visible dirt | Visual inspection |
| Taste | Good, fresh | Taste sample |

#### B. Physical/Chemical Tests (Everyday)

| Test | Standard | Remarks |
|------|----------|---------|
| Clot-on-boiling | No clot | For APT (+) milk only |
| Specific Gravity | 1.025 - 1.032 | Quevenne lactometer |
| Fat Analysis | 3.5% - 4.0% | Basis for payment |
| Acidity (% lactic acid) | 0.15 - 0.18% | All bulk samples |

#### C. Microbiological Analyses

| Test | Schedule | Standard |
|------|----------|----------|
| Methylene Blue Reduction Test (MBRT) | Daily | >3 hours |
| Total Bacterial Count | 2x/month | Per standard |
| Coliform Count | 2x/month | <10/ml |
| Yeasts and Molds Count | 2x/month | <10/ml |

### Titratable Acidity Interpretation

| Acidity Range | Interpretation | Action |
|---------------|----------------|--------|
| **0.15 - 0.18%** | Normal fresh cow's milk | Accept |
| **Less than 0.25%** | Can stand pasteurization | Accept (with deduction if >0.18%) |
| **0.25% and above** | Will clot in pasteurizer | **REJECT** |

### FDA Compliance - One-Way Flow Rule

QC ensures the facility maintains "One-Way Flow":

```
RECEIVING → PRODUCTION → RELEASING

No cross-contamination between raw materials and finished goods
```

---

## "What Happens If" Scenario Analysis (Socratic Method)

This section analyzes critical real-world scenarios using the Socratic Method to identify gaps and ensure deployment readiness.

---

### ✅ COVERED SCENARIOS (Currently Implemented)

#### 1. What Happens If a Farmer Delivers Low-Quality Milk?

| Scenario | System Behavior | Status |
|----------|-----------------|--------|
| APT Test Positive | Automatic rejection | ✅ Covered |
| Acidity ≥ 0.25% | Automatic rejection | ✅ Covered |
| Specific Gravity < 1.025 | Automatic rejection (suspected adulteration) | ✅ Covered |
| Fat % below 3.5% | Deduction applied (₱0.25 to ₱1.00/L) | ✅ Covered |
| Sediment Grade 2 or 3 | Deduction applied (₱0.50 or ₱1.00/L) | ✅ Covered |

**Implementation:** `milk_grading.php` → `calculateFatAdjustment()`, `calculateAcidityDeduction()`, `calculateSedimentDeduction()`

---

#### 2. What Happens If a Production Batch Fails Organoleptic Tests?

| Scenario | System Behavior | Status |
|----------|-----------------|--------|
| Bad taste/smell/appearance | QC can reject with reason | ✅ Covered |
| Batch marked as rejected | Status updated, reason logged | ✅ Covered |
| Production notified | Via system status | ✅ Covered |

**Implementation:** `batch_release.php` → PUT with `action: 'reject'`

---

#### 3. What Happens If Products Are Nearing Expiry?

| Scenario | System Behavior | Status |
|----------|-----------------|--------|
| Alert for 0-3 day expiry | Dashboard shows "critical" | ✅ Covered |
| Alert for 4-7 day expiry | Dashboard shows "warning" | ✅ Covered |
| Yogurt transformation | Automated workflow | ✅ Covered |
| Transformation linked to production | Creates production run | ✅ Covered |
| Inventory deducted | Automatic upon transformation | ✅ Covered |

**Implementation:** `expiry_management.php` → POST with `create_production_run: true`

---

#### 4. What Happens If a Farmer Is Inactive?

| Scenario | System Behavior | Status |
|----------|-----------------|--------|
| Inactive farmer attempts delivery | System blocks with error "Farmer is inactive" | ✅ Covered |
| Farmer deactivation | Soft delete (is_active = 0) | ✅ Covered |
| Historical data preserved | All past deliveries remain | ✅ Covered |

**Implementation:** `deliveries.php` → Validates `farmer['is_active']` before accepting delivery

---

#### 5. What Happens If Visual Inspection Fails Before Testing?

| Scenario | System Behavior | Status |
|----------|-----------------|--------|
| Visual inspection = 'fail' | Recorded in milk_receiving | ✅ Covered |
| Subsequent QC test | Can still be rejected | ✅ Covered |

**Implementation:** `milk_grading.php` → Checks `visual_inspection === 'fail'` as rejection criteria

---

### ⚠️ GAPS IDENTIFIED (Required for Deployment)

#### 🔴 CRITICAL GAPS

##### 1. No QC Test Correction/Amendment Capability

**Scenario:** What happens if a QC officer makes a data entry error (e.g., enters 0.35 acidity instead of 0.15)?

| Current State | Risk | Recommendation |
|---------------|------|----------------|
| ❌ No PUT method in `milk_grading.php` | Wrong pricing, farmer disputes | Add `PUT` method for test amendments with audit trail |
| ❌ Cannot correct test results | Financial errors | Require supervisor approval for corrections |
| ❌ No test void capability | Orphan records | Add void/cancel with reason |

**Required API Addition:**
```php
case 'PUT':
    // Amend an existing QC test (with audit trail)
    // Requires supervisor approval for financial changes
    // Log old values, new values, and reason for amendment
```

---

##### 2. No Equipment Calibration Tracking

**Scenario:** What happens if the Milkosonic SL50 analyzer is out of calibration?

| Current State | Risk | Recommendation |
|---------------|------|----------------|
| ❌ No calibration records | FDA compliance failure | Add `equipment_calibrations` table |
| ❌ No calibration alerts | Test validity in question | Add calibration due alerts on dashboard |
| ❌ No calibration verification | Audit failure | Block testing if equipment not calibrated |

**Required Table:**
```sql
CREATE TABLE equipment_calibrations (
    id INT PRIMARY KEY,
    equipment_name VARCHAR(100),
    equipment_code VARCHAR(50),
    calibration_date DATE,
    next_calibration_date DATE,
    calibrated_by INT,
    certificate_reference VARCHAR(100),
    status ENUM('valid', 'expired', 'pending')
);
```

---

##### 3. No Retest/Second Opinion Workflow

**Scenario:** What happens if a farmer disputes the test results?

| Current State | Risk | Recommendation |
|---------------|------|----------------|
| ❌ No retest capability | Farmer disputes, legal issues | Add retest workflow with fresh sample |
| ❌ No second QC officer verification | Single point of failure | Add verification by second officer option |
| ❌ No dispute resolution log | No paper trail | Add `qc_disputes` table |

**Required Workflow:**
```
Farmer Disputes Result
        ↓
Request Retest (within 30 min)
        ↓
Fresh Sample Taken (witnessed)
        ↓
Different QC Officer Tests
        ↓
Results Compared
        ↓
Final Decision + Documentation
```

---

##### 4. No Sample Retention Tracking

**Scenario:** What happens if there's a quality complaint days later?

| Current State | Risk | Recommendation |
|---------------|------|----------------|
| ❌ No sample retention records | Cannot verify past tests | Add sample retention log |
| ❌ No retention period policy | FDA compliance | Store retain samples 48-72 hours |
| ❌ No sample disposal log | Audit gap | Document sample disposal |

---

##### 5. No Disposal/Write-Off Module for QC

**Scenario:** What happens if milk is rejected or products fail QC?

| Current State | Risk | Recommendation |
|---------------|------|----------------|
| ❌ No disposal tracking in QC | Inventory discrepancy | Add disposal API and tracking |
| ⚠️ Only transformation exists | Not all rejects can transform | Add disposal for untransformable items |
| ❌ No disposal approval workflow | Fraud risk | Require GM approval for disposals |

**The PRD mentions this requirement but it's NOT implemented in QC module.**

---

##### 6. No Batch Recall Capability

**Scenario:** What happens if a released batch is later found to be contaminated?

| Current State | Risk | Recommendation |
|---------------|------|----------------|
| ❌ No recall workflow | Public safety risk | Add batch recall API |
| ❌ No delivered batch tracking | Cannot identify affected products | Link to sales/dispatch records |
| ❌ No recall notification | Customer safety | Add alert system |

**Required Workflow:**
```
Contamination Detected
        ↓
Identify Batch ID
        ↓
Query All Dispatched Units
        ↓
Generate Recall List
        ↓
Notify Affected Locations
        ↓
Track Returned Units
        ↓
Record Disposal
```

---

##### 7. No Partial Acceptance (Split Delivery)

**Scenario:** What happens if only part of a farmer's delivery is acceptable?

| Current State | Risk | Recommendation |
|---------------|------|----------------|
| ⚠️ Partial status exists in enum | But no workflow implemented | Implement partial acceptance |
| ❌ Cannot accept 30L and reject 20L | Unfair to farmer | Split delivery capability |

**Implementation Note:** The status 'partial' exists in `deliveries.php` filter but there's no POST logic to create partial acceptances.

---

#### 🟡 MEDIUM PRIORITY GAPS

##### 8. No QC Reports/Analytics

**Scenario:** What happens when management needs quality trend reports?

| Current State | Risk | Recommendation |
|---------------|------|----------------|
| ❌ No QC reports API | Manual reporting | Add reports endpoint |
| ❌ No farmer quality scores | Cannot track farmer performance | Calculate rolling quality scores |
| ❌ No rejection rate analysis | Cannot identify patterns | Add rejection analytics |

**Required Reports:**
- Daily QC Summary Report
- Farmer Quality Performance Report
- Rejection Analysis Report
- Grade Distribution Report

---

##### 9. No Antibiotic Testing Record

**Scenario:** FDA requires antibiotic testing records. Where are they?

| Current State | Risk | Recommendation |
|---------------|------|----------------|
| ❌ Not in qc_milk_tests table | Compliance gap | Add antibiotic test fields |
| ❌ No MBRT tracking | Required daily test | Add MBRT results field |

**Missing Fields:**
```sql
ALTER TABLE qc_milk_tests ADD COLUMN antibiotic_test ENUM('negative', 'positive');
ALTER TABLE qc_milk_tests ADD COLUMN mbrt_hours DECIMAL(3,1);
ALTER TABLE qc_milk_tests ADD COLUMN clot_on_boiling BOOLEAN;
```

---

##### 10. No Ingredient Batch QC Verification

**Scenario:** What happens when ingredients arrive at warehouse?

| Current State | Risk | Recommendation |
|---------------|------|----------------|
| ⚠️ Quarantine status exists | In ingredients table | QC approval workflow needed |
| ❌ No QC inspection form | No COA verification | Add ingredient QC API |

**Note:** The `raw_milk_inventory` has QC approval but `ingredient_batches` lacks a dedicated QC approval endpoint.

---

##### 11. No Temperature Excursion Logging

**Scenario:** What happens if storage temperature goes out of range?

| Current State | Risk | Recommendation |
|---------------|------|----------------|
| ❌ No real-time temp monitoring | Spoilage risk | Add temp exception API |
| ❌ No temp deviation alerts | Product loss | Integrate with IoT sensors |
| ❌ No temp log for stored milk | FDA requires | Add automated temp logging |

---

##### 12. No CCP Deviation Workflow for QC

**Scenario:** Production logs a CCP failure (temp didn't reach 72°C). What does QC do?

| Current State | Risk | Recommendation |
|---------------|------|----------------|
| ✅ CCP logs exist | In production_ccp_logs | Need QC review workflow |
| ❌ No QC notification of failures | May release unsafe batch | Auto-flag batches with CCP failures |
| ❌ No deviation investigation form | No root cause analysis | Add deviation investigation |

---

### 📊 DEPLOYMENT READINESS SCORECARD

| Category | Covered | Gaps | Score |
|----------|---------|------|-------|
| **Milk Grading & Pricing** | 8/8 | 0 | ✅ 100% |
| **Batch Release** | 5/6 | 1 (no recall) | ⚠️ 83% |
| **Expiry Management** | 5/6 | 1 (no disposal) | ⚠️ 83% |
| **Farmer Management** | 4/4 | 0 | ✅ 100% |
| **Error Handling** | 1/5 | 4 (no corrections) | 🔴 20% |
| **Compliance/Audit** | 2/8 | 6 | 🔴 25% |
| **Reporting** | 1/5 | 4 | 🔴 20% |

**Overall Deployment Readiness: 70%**

---

### 🎯 RECOMMENDED IMPLEMENTATION PRIORITY

#### Phase 1 (Before Go-Live) - CRITICAL

| Item | Effort | Impact |
|------|--------|--------|
| QC Test Amendment/Correction API | 2 days | High |
| Disposal Module Integration | 3 days | High |
| Partial Acceptance Workflow | 2 days | Medium |
| Basic QC Reports | 3 days | High |

#### Phase 2 (Within 30 Days) - IMPORTANT

| Item | Effort | Impact |
|------|--------|--------|
| Equipment Calibration Tracking | 3 days | High |
| Retest/Dispute Workflow | 4 days | Medium |
| Sample Retention Logging | 2 days | Medium |
| Antibiotic/MBRT Fields | 1 day | High |

#### Phase 3 (Within 90 Days) - ENHANCEMENT

| Item | Effort | Impact |
|------|--------|--------|
| Batch Recall System | 5 days | Critical for FDA |
| Temperature Monitoring Integration | 5 days | Medium |
| CCP Deviation Investigation | 3 days | Medium |
| Farmer Quality Scorecards | 3 days | Low |

---

## Summary

The Quality Control Module is the **critical link between the Farmer and the Customer** in the Highland Fresh dairy operation. It ensures:

1. **Financial Integrity** - Farmers are paid accurately based on milk quality
2. **Food Safety** - All products meet safety standards before release
3. **Traceability** - Every product can be traced back to its source
4. **Waste Prevention** - Near-expiry products are transformed, not wasted
5. **Compliance** - FDA and food safety regulations are enforced

### Current State Assessment

| Aspect | Status |
|--------|--------|
| **Core Functionality** | ✅ Fully Implemented |
| **ANNEX B Pricing** | ✅ Fully Automated |
| **Batch Release** | ✅ Working |
| **Error Correction** | 🔴 Missing |
| **Compliance Features** | 🔴 Gaps Exist |
| **Reporting** | 🟡 Basic Only |

### Recommendation

The QC module is **70% deployment-ready**. Before production deployment:

1. Implement QC test corrections with audit trail
2. Add disposal/write-off tracking
3. Create basic QC reports
4. Add antibiotic test fields for compliance

---

*Documentation Generated: February 8, 2026*  
*Highland Fresh Dairy System v4.0*
