# Production Module Documentation

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

The **Production Module** is the operational heart of the Highland Fresh Dairy System, serving as the **"Engine"** that transforms raw materials into finished products. It manages the complete manufacturing lifecycle from raw milk intake through pasteurization, processing, and final packaging.

> **Key Insight:** Production is NOT simply "input ingredients → output product." Each product has unique workflows with intermediate stages, byproducts, and quality checkpoints.

### Key Responsibilities

| Area | Description |
|------|-------------|
| **Recipe Management** | Maintaining production recipes with editable ingredient quantities |
| **Production Runs** | Creating, tracking, and completing manufacturing batches |
| **Pasteurization** | Converting raw milk to pasteurized milk for bottling/yogurt |
| **CCP Logging** | Recording Critical Control Points for food safety compliance |
| **Material Requisitions** | Requesting ingredients from Warehouse with approval workflow |
| **Byproduct Tracking** | Managing secondary outputs (buttermilk, whey, skim milk) |

---

## Module Overview

### Access Roles

The Production module is accessible to the following user roles:

| Role | Access Level |
|------|--------------|
| `production_staff` | Full access to production functions |
| `general_manager` | Full access + recipe creation + approval authority |
| `qc_officer` | Read-only access for quality verification |

### Navigation Structure

```
Production Dashboard
├── Main Menu
│   └── Dashboard (Overview statistics)
├── Production
│   ├── Batches (Create & manage production runs)
│   ├── Recipes (View/manage product recipes)
│   ├── Requisitions (Request materials from warehouse)
│   └── Pasteurization (Convert raw → pasteurized milk)
└── Quality
    ├── CCP Logging (Critical Control Point records)
    └── Byproducts (Track secondary outputs)
```

---

## Core Functions

### 1. Recipe Management

Recipes define the "blueprint" for each product, including ingredients, quantities, and processing parameters.

#### Product Types

| Type | Description | Special Requirements |
|------|-------------|---------------------|
| `bottled_milk` | Fresh/flavored milk in bottles (200ml, 500ml, 1L) | Pasteurization required |
| `yogurt` | Fermented milk products | Must use PASTEURIZED milk (not raw) |
| `cheese` | Gouda, White cheese | Uses RAW milk directly |
| `butter` | Churned cream product | Requires separation (cream extraction) |
| `milk_bar` | Ice candy style products | Uses pasteurized milk + flavorings |

#### Recipe Editability Principle

> **Requirement:** The system MUST allow production staff to edit ingredient quantities during batch entry to reflect actual usage (e.g., adjusting cocoa powder amounts based on taste tests).

| Recipe Type | Purpose | Behavior |
|-------------|---------|----------|
| **Finance Recipe** | Cost calculation, budgeting | Fixed theoretical ratios |
| **Production Recipe** | Actual floor usage | **EDITABLE** - Staff can adjust quantities |

---

### 2. Production Runs (The "Engine" Function)

Production runs track the complete lifecycle of a manufacturing batch.

#### Run Status Flow

```
planned → in_progress → pasteurization → processing → cooling → packaging → completed
    │                                                                           │
    └─────────────────────────────► cancelled ◄─────────────────────────────────┘
```

| Status | Description |
|--------|-------------|
| `planned` | Run created, awaiting start |
| `in_progress` | Production has begun |
| `pasteurization` | Milk being pasteurized (75°C/15s) |
| `processing` | Product-specific processing (churning, fermentation, etc.) |
| `cooling` | Products cooling to 4°C |
| `packaging` | Filling bottles/containers |
| `completed` | Run finished, sent to QC for release |
| `cancelled` | Run cancelled |

#### Milk Source Validation

The system enforces strict milk source rules per product type:

| Product Type | Required Milk Source | Validation |
|--------------|---------------------|------------|
| Yogurt | **Pasteurized Milk Only** | ❌ Cannot use raw milk |
| Cheese, Butter | Raw Milk | ✅ Uses raw milk directly |
| Bottled Milk, Milk Bar | Via Pasteurization | ✅ Raw → Pasteurized → Bottled |

```
⚠️ YOGURT CANNOT DRAW INVENTORY FROM RAW MILK DIRECTLY

CORRECT WORKFLOW:
Raw Milk → Pasteurization (75°C/15s) → Pasteurized Milk Inventory → Yogurt Production
```

---

### 3. Pasteurization Module

Converts raw milk to pasteurized milk using HTST (High Temperature Short Time) method.

#### HTST Parameters

| Parameter | Standard Value | Notes |
|-----------|---------------|-------|
| Temperature | 75°C | Must reach minimum |
| Duration | 15 seconds | Hold time |
| Method | HTST | High Temperature Short Time |

#### Pasteurization Workflow

```
RAW MILK (QC-Approved)
        ↓
   ┌─────────────────────┐
   │  PASTEURIZATION     │
   │  Run Created        │
   │  - Input: X Liters  │
   │  - Temp: 75°C       │
   │  - Duration: 15s    │
   └─────────┬───────────┘
             ↓
   ┌─────────────────────┐
   │  COMPLETE RUN       │
   │  - Output Liters    │
   │  - Shrinkage: ~1%   │
   └─────────┬───────────┘
             ↓
   ┌─────────────────────┐
   │  PASTEURIZED MILK   │
   │  INVENTORY          │
   │  - Available for    │
   │    Yogurt/Bottling  │
   │  - FIFO Allocation  │
   │  - Expiry Tracked   │
   └─────────────────────┘
```

---

### 4. Material Requisitions

Production requests materials from Warehouse through a formal requisition process.

#### Requisition Workflow

```
Production Staff Creates Requisition
        ↓
    Status: PENDING
        ↓
GM Reviews & Approves/Rejects
        ↓
    ┌───┴───┐
    │       │
 Approve   Reject
    │       │
    ↓       ↓
Status:   Status:
APPROVED  REJECTED
    ↓
Warehouse Fulfills
(Issues materials)
    ↓
Status: FULFILLED
    ↓
Production Can Use Materials
```

#### Priority Levels

| Priority | Description | Use Case |
|----------|-------------|----------|
| `urgent` | Immediate need | Production stopped |
| `high` | Same day required | Running low |
| `normal` | Standard request | Planned production |
| `low` | When available | Future planning |

#### Item Types

| Item Type | Description | Source |
|-----------|-------------|--------|
| `raw_milk` | QC-approved raw milk | Warehouse Raw |
| `ingredient` | Sugar, flavorings, cultures, etc. | Warehouse Ingredients |
| `packaging` | Bottles, caps, labels | Warehouse Packaging |

---

### 5. CCP Logging (Critical Control Points)

Records temperature, pressure, and time measurements at each critical processing stage for food safety compliance.

#### CCP Check Types

| Check Type | Target | Tolerance | Unit | Purpose |
|------------|--------|-----------|------|---------|
| `chilling` | 4°C | ±1°C | °C | Upon receiving |
| `preheating` | 65°C | ±2°C | °C | Before homogenization |
| `homogenization` | 1000-1500 | - | psi | Pressure check |
| `pasteurization` | 75°C | ±2°C | °C | Kill pathogens |
| `cooling` | 4°C | ±1°C | °C | Post-process cooling |
| `storage` | 4°C | ±1°C | °C | Final storage |
| `intermediate` | 4°C | ±2°C | °C | Between processes |

#### Pass/Fail Logic

```javascript
// Temperature Checks (most types)
if (check.is_max === true) {
    // Must be BELOW target + tolerance (e.g., chilling ≤ 5°C)
    status = temp <= (target + tolerance) ? 'pass' : 'fail';
} else {
    // Must be ABOVE target - tolerance (e.g., pasteurization ≥ 73°C)
    status = temp >= (target - tolerance) ? 'pass' : 'fail';
}

// Homogenization Check
if (pressure >= 1000 && pressure <= 1500) {
    status = 'pass';
} else {
    status = 'fail';
}
```

---

### 6. Byproduct Tracking

Records and manages secondary outputs from production processes.

#### Byproduct Types

| Byproduct | Source Process | Typical Use |
|-----------|----------------|-------------|
| `buttermilk` | Butter churning | Sale, yogurt production |
| `whey` | Cheese production | Animal feed, sale |
| `cream` | Milk separation | Butter production |
| `skim_milk` | Milk separation (for butter) | Yogurt, sale |

#### Byproduct Destinations

| Destination | Description |
|-------------|-------------|
| `warehouse` | Transfer to warehouse inventory |
| `reprocess` | Use in another production run |
| `dispose` | Waste disposal (requires approval) |
| `sale` | Direct sale as byproduct |

---

## System Architecture

### File Structure

```
HighlandFreshAppV4/
├── api/
│   └── production/
│       ├── dashboard.php        # Dashboard statistics API
│       ├── runs.php             # Production runs CRUD + milk checks
│       ├── recipes.php          # Recipe management API
│       ├── requisitions.php     # Material requisition API
│       ├── pasteurization.php   # Pasteurization run API
│       ├── ccp_logs.php         # CCP logging API
│       └── byproducts.php       # Byproduct tracking API
├── html/
│   └── production/
│       ├── dashboard.html       # Production Dashboard UI
│       ├── batches.html         # Production runs interface
│       ├── recipes.html         # Recipe management UI
│       ├── requisitions.html    # Requisition interface
│       ├── pasteurization.html  # Pasteurization UI
│       ├── ccp_logging.html     # CCP log interface
│       └── byproducts.html      # Byproduct tracking UI
├── js/
│   └── production/
│       └── production.service.js # All production API calls
└── sql/
    ├── add_production_enhancements.sql
    └── create_pasteurized_milk.sql
```

---

## User Interface Components

### Production Dashboard

The dashboard provides real-time overview of production activities:

#### Statistics Cards

| Card | Metric |
|------|--------|
| Today's Runs | Total production runs today |
| Planned | Runs awaiting start |
| In Progress | Active runs |
| Completed | Finished runs today |
| Total Yield | Units produced today |
| Available Milk | QC-approved liters for production |

#### Data Tables

- **Recent Runs**: Latest 10 production runs with status
- **Week's Production by Type**: Batches and yield by product type
- **Pending Requisitions**: Material requests awaiting approval

### Batches Interface

| Feature | Description |
|---------|-------------|
| **Create Run** | Select recipe, quantity, log milk usage |
| **Run List** | Filter by status, date, product type |
| **Status Updates** | Progress through production stages |
| **Complete Run** | Record actual yield, variance |
| **CCP Quick Log** | Record temperature/pressure readings |

---

## Business Logic & Calculations

### Yield Variance Calculation

```javascript
// Calculate yield efficiency
yieldVariance = ((actualQuantity - plannedQuantity) / plannedQuantity) * 100;

// Example:
// Planned: 100 bottles
// Actual: 95 bottles
// Variance: ((95 - 100) / 100) * 100 = -5%
```

### Milk Allocation (FIFO for Pasteurized)

For yogurt production, pasteurized milk is allocated using FIFO (First In, First Out):

```sql
-- Get oldest available pasteurized milk first
SELECT id, batch_code, remaining_liters, expiry_date
FROM pasteurized_milk_inventory
WHERE status = 'available' 
  AND remaining_liters > 0
  AND expiry_date >= CURDATE()
ORDER BY pasteurized_at ASC  -- FIFO: oldest first
```

### Butter Production: Separation Logic

```
INPUT: 100L Raw Milk
        ↓
    SEPARATOR
        ↓
┌───────┴───────┐
│               │
20% CREAM       80% SKIM MILK
(~20kg)         (~80L)
    │               │
    ↓           [BYPRODUCT]
CHURNING           ↓
    │           Yogurt or Sale
    ↓
40-45% BUTTER
(~8-9kg)
    │
    ↓
BUTTERMILK
[BYPRODUCT]
(~11-12L)
```

### Shrinkage Calculations

| Process | Typical Shrinkage |
|---------|------------------|
| Pasteurization | 0.5-1% |
| Homogenization | 0.5% |
| Bottling | 2-3% (spillage) |
| Cheese (whey loss) | 85-90% (only 10-15% becomes cheese) |

---

## API Reference

### 1. Dashboard API (`/api/production/dashboard.php`)

#### GET - Dashboard Statistics

**Response Data:**
```json
{
    "today": {
        "total": 15,
        "planned": 3,
        "in_progress": 5,
        "completed": 7,
        "total_yield": 450
    },
    "production_by_type": [
        { "product_type": "bottled_milk", "batches": 5, "total_produced": 200 },
        { "product_type": "yogurt", "batches": 3, "total_produced": 150 }
    ],
    "pending_requisitions": 4,
    "ccp_alerts": 1,
    "active_recipes": 12,
    "available_milk": {
        "total_liters": 500,
        "source_count": 3
    }
}
```

---

### 2. Production Runs API (`/api/production/runs.php`)

#### GET - List Runs

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `id` | int | Get single run |
| `action` | string | `available_milk` or `available_pasteurized_milk` |
| `status` | string | Filter by status |
| `recipe_id` | int | Filter by recipe |
| `product_type` | string | Filter by product type |
| `date_from` | date | Start date filter |
| `date_to` | date | End date filter |
| `page` | int | Page number |
| `limit` | int | Items per page |

#### GET - Available Milk

**Action:** `?action=available_milk`

**Response:**
```json
{
    "total_available_liters": 250.5,
    "milk_sources": [...],
    "total_issued": 300,
    "total_used": 49.5,
    "source": "requisition_based",
    "milk_type": "raw",
    "message": "You have 250.5L of milk available"
}
```

#### GET - Available Pasteurized Milk

**Action:** `?action=available_pasteurized_milk`

**Response:**
```json
{
    "total_available_liters": 180,
    "batches": [
        {
            "id": 1,
            "batch_code": "PAST-20260209-001",
            "remaining_liters": 100,
            "expiry_date": "2026-02-11",
            "days_until_expiry": 2
        }
    ],
    "batch_count": 2,
    "source": "pasteurized_inventory",
    "milk_type": "pasteurized"
}
```

#### POST - Create Production Run

**Request Body:**
```json
{
    "recipe_id": 5,
    "planned_quantity": 100,
    "milk_liters_used": 50,
    "notes": "Morning batch",
    "process_temperature": 75,
    "process_duration_mins": 15,
    "ingredient_adjustments": "{\"sugar_kg\": 2.5}",
    "cheese_state": "cooking",
    "is_salted": 1
}
```

**Response:**
```json
{
    "id": 123,
    "run_code": "PRD-20260209-001",
    "status": "planned"
}
```

#### PUT - Update Run Status

**Request Body (Start):**
```json
{
    "id": 123,
    "action": "start"
}
```

**Request Body (Update Status):**
```json
{
    "id": 123,
    "action": "update_status",
    "status": "pasteurization"
}
```

**Request Body (Complete):**
```json
{
    "id": 123,
    "action": "complete",
    "actual_quantity": 95,
    "variance_reason": "Minor spillage during filling",
    "output_breakdown": {
        "total_pieces": 95,
        "boxes": 9,
        "loose": 5
    }
}
```

---

### 3. Recipes API (`/api/production/recipes.php`)

#### GET - List Recipes

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `id` | int | Get single recipe with ingredients |
| `product_type` | string | Filter by type |
| `status` | string | `active` or `inactive` |
| `search` | string | Search in name, code, variant |
| `page` | int | Page number |
| `limit` | int | Items per page |

#### POST - Create Recipe (GM Only)

**Request Body:**
```json
{
    "recipe_code": "RCP-001",
    "product_name": "Chocolate Milk 500ml",
    "product_type": "bottled_milk",
    "variant": "Chocolate",
    "size_ml": 500,
    "base_milk_liters": 100,
    "expected_yield": 200,
    "yield_unit": "bottles",
    "shelf_life_days": 7,
    "pasteurization_temp": 75,
    "pasteurization_time_mins": 15,
    "cooling_temp": 4,
    "ingredients": [
        {
            "ingredient_name": "Cocoa Powder",
            "ingredient_category": "flavoring",
            "quantity": 2.5,
            "unit": "kg"
        },
        {
            "ingredient_name": "Sugar",
            "ingredient_category": "sweetener",
            "quantity": 5,
            "unit": "kg"
        }
    ]
}
```

---

### 4. Requisitions API (`/api/production/requisitions.php`)

#### GET - List Requisitions

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `id` | int | Get single requisition with items |
| `status` | string | Filter by status |
| `run_id` | int | Filter by production run |
| `date_from` | date | Start date filter |
| `date_to` | date | End date filter |

#### POST - Create Requisition

**Request Body:**
```json
{
    "production_run_id": 123,
    "priority": "high",
    "needed_by": "2026-02-10",
    "purpose": "Morning yogurt production",
    "items": [
        {
            "item_type": "raw_milk",
            "item_name": "Raw Milk",
            "requested_quantity": 100,
            "unit_of_measure": "liters"
        },
        {
            "item_type": "ingredient",
            "item_id": 5,
            "item_name": "Yogurt Culture",
            "requested_quantity": 500,
            "unit_of_measure": "grams"
        }
    ]
}
```

#### PUT - Approve/Reject (GM Only)

**Approve:**
```json
{
    "id": 456,
    "action": "approve"
}
```

**Reject:**
```json
{
    "id": 456,
    "action": "reject",
    "rejection_reason": "Insufficient inventory"
}
```

---

### 5. Pasteurization API (`/api/production/pasteurization.php`)

#### GET - Available Raw Milk

**Action:** `?action=available_raw_milk`

**Response:**
```json
{
    "available_liters": 200,
    "total_issued": 300,
    "used_in_production": 50,
    "used_in_pasteurization": 50
}
```

#### GET - List Pasteurization Runs

**Action:** `?action=runs`

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `status` | string | Filter by status |
| `page` | int | Page number |
| `limit` | int | Items per page |

#### POST - Create Pasteurization Run

**Request Body:**
```json
{
    "input_liters": 100,
    "temperature": 75,
    "duration_mins": 15,
    "notes": "Morning pasteurization"
}
```

#### PUT - Complete Pasteurization Run

**Request Body:**
```json
{
    "action": "complete",
    "id": 789,
    "output_liters": 99,
    "expiry_date": "2026-02-11"
}
```

---

### 6. CCP Logs API (`/api/production/ccp_logs.php`)

#### GET - List CCP Logs

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `id` | int | Get single log |
| `run_id` | int | Filter by production run |
| `check_type` | string | Filter by check type |
| `status` | string | `pass` or `fail` |
| `date_from` | date | Start date filter |
| `date_to` | date | End date filter |

#### POST - Create CCP Log

**Request Body:**
```json
{
    "run_id": 123,
    "check_type": "pasteurization",
    "temperature": 75.5,
    "hold_time_secs": 15,
    "notes": "Verified with calibrated thermometer"
}
```

**For Homogenization:**
```json
{
    "run_id": 123,
    "check_type": "homogenization",
    "pressure_psi": 1250,
    "notes": "Pressure within acceptable range"
}
```

---

### 7. Byproducts API (`/api/production/byproducts.php`)

#### GET - List Byproducts

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `id` | int | Get single byproduct record |
| `run_id` | int | Filter by production run |
| `byproduct_type` | string | Filter by type |
| `status` | string | Filter by status |
| `date_from` | date | Start date filter |
| `date_to` | date | End date filter |

#### POST - Record Byproduct

**Request Body:**
```json
{
    "run_id": 123,
    "byproduct_type": "skim_milk",
    "quantity": 80,
    "unit": "liters",
    "destination": "warehouse",
    "notes": "From butter separation"
}
```

---

## Database Schema

### Core Production Tables

#### `production_runs`

Primary table for production batch tracking.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT | Primary key |
| `run_code` | VARCHAR | Unique run code (PRD-YYYYMMDD-NNN) |
| `recipe_id` | INT | FK to master_recipes |
| `milk_type_id` | INT | FK to milk_types |
| `planned_quantity` | INT | Expected output |
| `actual_quantity` | INT | Actual output (after completion) |
| `output_breakdown` | JSON | Unit breakdown (pieces, boxes, loose) |
| `milk_liters_used` | DECIMAL | Raw/pasteurized milk consumed |
| `milk_source_type` | ENUM | 'raw' or 'pasteurized' |
| `pasteurized_milk_batch_id` | INT | FK for yogurt traceability |
| `status` | ENUM | Run status |
| `start_datetime` | DATETIME | Production start |
| `end_datetime` | DATETIME | Production end |
| `yield_variance` | DECIMAL | % difference from planned |
| `process_temperature` | DECIMAL | Processing temp (°C) |
| `process_duration_mins` | INT | Processing time |
| `ingredient_adjustments` | JSON | Recipe deviations |
| `cheese_state` | ENUM | Cheese-specific state tracking |
| `is_salted` | BOOLEAN | Salted variant flag |
| `cream_output_kg` | DECIMAL | Butter: cream from separation |
| `skim_milk_output_liters` | DECIMAL | Butter: skim milk byproduct |
| `started_by` | INT | FK to users |
| `completed_by` | INT | FK to users |

#### `master_recipes`

Product recipe definitions.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT | Primary key |
| `recipe_code` | VARCHAR | Unique recipe code |
| `product_name` | VARCHAR | Product name |
| `product_type` | ENUM | bottled_milk, cheese, butter, yogurt, milk_bar |
| `variant` | VARCHAR | Flavor/variant name |
| `size_ml` | INT | Container size (ml) |
| `size_grams` | INT | Product weight (g) |
| `base_milk_liters` | DECIMAL | Milk required per batch |
| `expected_yield` | INT | Expected output quantity |
| `yield_unit` | VARCHAR | Unit of yield (bottles, kg, blocks) |
| `shelf_life_days` | INT | Product shelf life |
| `pasteurization_temp` | DECIMAL | Required pasteurization temp |
| `pasteurization_time_mins` | INT | Required pasteurization time |
| `cooling_temp` | DECIMAL | Required cooling temp |
| `special_instructions` | TEXT | Processing notes |
| `is_active` | BOOLEAN | Recipe active status |
| `created_by` | INT | FK to users |

#### `recipe_ingredients`

Ingredients for each recipe.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT | Primary key |
| `recipe_id` | INT | FK to master_recipes |
| `ingredient_name` | VARCHAR | Ingredient name |
| `ingredient_category` | VARCHAR | Category (flavoring, sweetener, etc.) |
| `quantity` | DECIMAL | Required quantity |
| `unit` | VARCHAR | Unit of measure |
| `is_optional` | BOOLEAN | Optional ingredient flag |
| `notes` | TEXT | Preparation notes |

#### `pasteurized_milk_inventory`

Tracks pasteurized milk available for production.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT | Primary key |
| `batch_code` | VARCHAR | Unique batch code |
| `quantity_liters` | DECIMAL | Initial quantity |
| `remaining_liters` | DECIMAL | Current available quantity |
| `pasteurization_temp` | DECIMAL | Actual pasteurization temp |
| `pasteurization_duration_mins` | INT | Actual duration |
| `pasteurized_at` | DATETIME | Pasteurization timestamp |
| `expiry_date` | DATE | Expiry date |
| `status` | ENUM | available, reserved, exhausted, expired |

#### `pasteurization_runs`

Pasteurization run records.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT | Primary key |
| `run_code` | VARCHAR | Unique run code (PAST-YYYYMMDD-NNN) |
| `input_milk_liters` | DECIMAL | Raw milk input |
| `output_milk_liters` | DECIMAL | Pasteurized milk output |
| `temperature` | DECIMAL | Pasteurization temperature |
| `duration_mins` | INT | Hold time |
| `status` | ENUM | in_progress, completed |
| `performed_by` | INT | FK to users |

#### `production_ccp_logs`

Critical Control Point records.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT | Primary key |
| `run_id` | INT | FK to production_runs |
| `check_type` | ENUM | CCP check type |
| `temperature` | DECIMAL | Recorded temperature |
| `pressure_psi` | DECIMAL | Recorded pressure (homogenization) |
| `hold_time_mins` | INT | Hold time in minutes |
| `hold_time_secs` | INT | Hold time in seconds |
| `target_temp` | DECIMAL | Target temperature |
| `temp_tolerance` | DECIMAL | Allowed tolerance |
| `status` | ENUM | pass, fail |
| `check_datetime` | DATETIME | Check timestamp |
| `verified_by` | INT | FK to users |
| `notes` | TEXT | Additional notes |

#### `material_requisitions`

Material request records.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT | Primary key |
| `requisition_code` | VARCHAR | Unique code (REQ-YYYYMMDD-NNN) |
| `production_run_id` | INT | FK to production_runs (optional) |
| `department` | VARCHAR | Requesting department |
| `requested_by` | INT | FK to users |
| `priority` | ENUM | low, normal, high, urgent |
| `needed_by_date` | DATE | Required by date |
| `purpose` | TEXT | Requisition purpose |
| `total_items` | INT | Number of items |
| `status` | ENUM | draft, pending, partial, fulfilled, cancelled (legacy: approved, rejected) |
| `stock_override_acknowledged` | TINYINT | 1 = requester self-acknowledged a stock shortage on submit (V4.0) |
| `stock_override_by` | INT | FK to users — who acknowledged the shortage (V4.0) |
| `stock_override_reason` | VARCHAR | Free-text reason for the override (V4.0) |
| `stock_override_at` | DATETIME | When the override was acknowledged (V4.0) |
| `approved_by` | INT | FK to users (legacy — empty for new rows) |
| `fulfilled_by` | INT | FK to users |

#### `requisition_items`

Individual items in a requisition.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT | Primary key |
| `requisition_id` | INT | FK to material_requisitions |
| `item_type` | VARCHAR | raw_milk, ingredient, packaging |
| `item_id` | INT | FK to ingredient/inventory |
| `item_name` | VARCHAR | Item name |
| `requested_quantity` | DECIMAL | Quantity requested (in base units, e.g. kg, L) |
| `requested_quantity_in_packs` | DECIMAL | Pack count the requester asked for, NULL if requested in base units (V4.0) |
| `pack_size_at_submit` | DECIMAL | Snapshot of `ingredients.pack_size_value` at submit time, used to convert the pack count to base (V4.0) |
| `break_pack_acknowledged` | TINYINT | 1 = requester explicitly chose to submit a fractional pack count and acknowledged the break (V4.0 Option B) |
| `break_pack_acknowledged_reason` | VARCHAR | Free-text reason for the pack break; surfaced to the warehouse in the fulfill modal (V4.0 Option B) |
| `issued_quantity` | DECIMAL | Quantity actually issued |
| `unit_of_measure` | VARCHAR | Unit |
| `fulfilled_at` | DATETIME | Fulfillment timestamp |
| `notes` | TEXT | Item notes |

#### `requisition_stock_warnings` (V4.0)

Per-item audit trail of every stock-validation decision made when a production
requisition is submitted. Written when the requester submits a request that
exceeds available warehouse stock (and acknowledges the override), or when the
server blocks a submit attempt entirely.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT | Primary key |
| `requisition_id` | INT | FK to material_requisitions.id |
| `requisition_item_id` | INT | FK to requisition_items.id (nullable for pre-submit block attempts) |
| `ingredient_id` | INT | FK to ingredients.id (null for raw_milk and MRO rows) |
| `item_name` | VARCHAR | Denormalized for audit readability even if ingredient is renamed |
| `requested_qty` | DECIMAL | What production asked for |
| `available_qty` | DECIMAL | What the warehouse had at submit time |
| `shortage` | DECIMAL | requested - available (always >= 0) |
| `decision` | ENUM | `blocked` (422 returned, requester did not acknowledge) or `overridden` (acknowledged and submitted) |
| `decided_by` | INT | FK to users — who made the decision |
| `decided_role` | VARCHAR | Role at time of decision — production_staff, general_manager, etc. |
| `override_reason` | VARCHAR | Free-text reason when decision = `overridden` |
| `created_at` | DATETIME | Timestamp |

Query examples for the prof's review:

```sql
-- All overridden requests in the last 30 days, with who/why
SELECT ir.requisition_code, ir.created_at,
       CONCAT(u.first_name,' ',u.last_name) AS by_user, w.decided_role,
       w.item_name, w.requested_qty, w.available_qty, w.shortage, w.override_reason
FROM requisition_stock_warnings w
JOIN material_requisitions ir ON ir.id = w.requisition_id
LEFT JOIN users u ON u.id = w.decided_by
WHERE w.decision = 'overridden'
  AND w.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
ORDER BY w.created_at DESC;

-- Most-overridden ingredients
SELECT w.item_name, COUNT(*) AS override_count, SUM(w.shortage) AS total_shortage
FROM requisition_stock_warnings w
WHERE w.decision = 'overridden'
GROUP BY w.item_name
ORDER BY override_count DESC
LIMIT 10;
```

### Pack Integrity (V4.0 Option B)

Some ingredients ship from suppliers in sealed packs (e.g., Chocolate Powder
in 1-kg bags, Cellophane Wrap in 100-mL packets) that physically cannot be
broken open and partially used without inventory loss. The system enforces
whole-pack submission for any ingredient the warehouse team has flagged
with `enforce_whole_packs = 1`, so the requested qty on a requisition always
matches a whole number of physical packs sitting on the shelf.

#### The math

For each ingredient flagged with `enforce_whole_packs = 1`, the server
computes the effective pack count for every submission:

```
effective_packs = quantity_in_packs          (if requester used pack mode)
                 = quantity / pack_size_value  (if they used base mode)
```

`pack_count = (int) ceil(effective_packs)` is what the recipe auto-fill
locks the requester's qty input to, so the recipe never runs short of the
ingredient. (We use `ceil`, not `floor` or `round`, because rounding down
or to-nearest could yield zero bags for a non-zero recipe need — the
prof's "AFTER" requirement is "system must force exact, whole packets".)

The cost of `ceil` is a small amount of excess per recipe, which is
unavoidable when working with sealed physical packaging.

#### Per-ingredient opt-in (the flag)

| Column on `ingredients` | Type | Default | What it means |
|---|---|---|---|
| `enforce_whole_packs` | TINYINT(1) | 0 | When 1, the server gates any fractional submission for this ingredient. When 0, today's behavior (any qty accepted). |

The Warehouse Raw team flips this on per ingredient once the
`pack_size_value` is configured. There is no global setting — each
ingredient decides its own policy.

#### The override path

If a requester (typically `production_staff`) needs a fractional pack count
for a legitimate reason (half-batch test, exact recipe match, recipe
needs less than a full pack), they can:

  1. Click **Override** in the form to unlock the qty field
  2. Type the fractional value
  3. Submit — the server returns `HTTP 422` with `error_code: pack_fractional`
  4. The UI opens a "Pack Integrity Check" modal with one card per offender:
     - **Round up to N packs** — the form rewrites qty to `ceil_packs` and
       re-submits. Clean: no audit row written, no break flag set.
     - **Acknowledge pack break** — requester ticks the box, types a
       reason, re-submits with `break_pack_acknowledged = true` and
       `break_pack_acknowledged_reason = "..."`. The server accepts.
       An audit row is **not** written by this flow; the row's own
       `break_pack_acknowledged` + `break_pack_acknowledged_reason`
       columns are the audit trail.

The form's submit button in the modal is disabled until every offender
has been resolved (rounded up OR acked with a non-empty reason).

#### The 422 payload shape

```json
{
  "success": false,
  "message": "One or more ingredients require whole packs. Round up or acknowledge the pack break per item.",
  "errors": {
    "error_code": "pack_fractional",
    "pack_check": {
      "fractional_count": 1,
      "unacked_count": 1,
      "items": [{
        "item_index": 0,
        "item_id": 11,
        "item_name": "Chocolate Powder X",
        "kind": "fractional",
        "pack_size": 1,
        "pack_size_unit": "kg",
        "pack_label": "1 kg bag",
        "enforce_whole_packs": true,
        "effective_packs": 1.5,
        "ceil_packs": 2,
        "ceil_base": 2,
        "extra_packs_to_round_up": 0.5,
        "unit": "kg",
        "message": "Chocolate Powder X requests 1.5 packs but each pack is 1 kg. Round up to 2 packs (2 kg) or acknowledge the pack break."
      }],
      "all_offenders": [/* same shape, includes 'misconfigured' kind for catalog misconfig */]
    }
  }
}
```

#### Audit trail (for prof / auditor review)

Per row, the columns `break_pack_acknowledged` and
`break_pack_acknowledged_reason` are the record of who made the override
decision and why. There is currently no separate `pack_overrides` audit
table — the row itself is the audit row.

Query examples for review:

```sql
-- All pack breaks in the last 30 days, with who / when / why
SELECT ir.requisition_code, ir.created_at AS req_created,
       CONCAT(req.first_name, ' ', req.last_name) AS requester,
       ri.item_name, ri.requested_quantity_in_packs AS packs_asked,
       ri.requested_quantity AS base_asked,
       ri.pack_size_at_submit AS pack_size,
       ri.break_pack_acknowledged_reason AS reason
FROM requisition_items ri
JOIN material_requisitions ir ON ir.id = ri.requisition_id
JOIN users req ON req.id = ir.requested_by
WHERE ri.break_pack_acknowledged = 1
  AND ir.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
ORDER BY ir.created_at DESC;

-- Most-broken ingredients
SELECT item_name, COUNT(*) AS break_count
FROM requisition_items
WHERE break_pack_acknowledged = 1
GROUP BY item_name
ORDER BY break_count DESC
LIMIT 10;

-- Misconfigured ingredients (enforce_whole_packs on but no pack_size_value)
SELECT id, ingredient_name, pack_size_value, enforce_whole_packs
FROM ingredients
WHERE enforce_whole_packs = 1
  AND (pack_size_value IS NULL OR pack_size_value <= 0);
```

#### Known limitation (logged for a follow-up task)

The current design lets the requester themselves acknowledge a pack break
in the same form submission. For tighter segregation of duties, a
follow-up task should require the break to be confirmed by
`warehouse_raw` or `general_manager` as a separate workflow step
(production submits a fractional request as `pending_warehouse_confirmation`,
the warehouse sees it in their queue and either rounds up or confirms
the break with their own reason). The schema already supports this
(the `decided_by` / `decided_role` fields in `requisition_stock_warnings`
could be extended to a new `requisition_pack_breaks` audit table); only
the role-gating in `checkRequisitionPackIntegrity` and the corresponding
UI changes are pending.

---

#### `production_byproducts`

Byproduct tracking records.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT | Primary key |
| `run_id` | INT | FK to production_runs |
| `byproduct_type` | ENUM | buttermilk, whey, cream, skim_milk, other |
| `quantity` | DECIMAL | Quantity produced |
| `unit` | VARCHAR | Unit of measure |
| `status` | ENUM | pending, stored, processed, disposed |
| `destination` | ENUM | warehouse, reprocess, dispose, sale |
| `recorded_by` | INT | FK to users |
| `notes` | TEXT | Additional notes |

---

## Workflow Diagrams

### Complete Production Flow

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           PRODUCTION FLOW                                │
├─────────────────────────────────────────────────────────────────────────┤

   QC-APPROVED RAW MILK
   (from milk_receiving)
            │
            ▼
   ┌─────────────────────┐
   │  MATERIAL           │
   │  REQUISITION        │ ◄── Production submits request for milk
   │  (Pending)          │     + ingredients. Warehouse Raw sees it
   └─────────┬───────────┘     immediately — no GM gate.
             │
             │   Server-side stock validation runs on every submit:
             │   • If every line has enough stock → 201 Created.
             │   • If any line is short and client did not send
             │     stock_override_acknowledged=true → 422 with the
             │     stock_check payload. UI shows a confirmation modal
             │     that lists the actual shortages.
             │   • If client acknowledges the override → 201 Created,
             │     audit row written to requisition_stock_warnings
             │     with decision='overridden'.
             ▼
   ┌─────────────────────┐
   │  WAREHOUSE FULFILLS │ ◄── Warehouse Raw fulfills directly from
   │  (Issues materials) │     'pending' (or 'partial' for top-up).
   └─────────┬───────────┘     No more 'approve' step in this flow.
             │
    ┌────────┴────────┐
    │                 │
    ▼                 ▼
BOTTLING           YOGURT
CHEESE             PRODUCTION                                             
BUTTER                 │                                                  
    │                  │                                                  
    │                  ▼                                                  
    │         ┌─────────────────┐                                         
    │         │ PASTEURIZATION  │ ◄── Raw → Pasteurized (75°C/15s)       
    │         │ RUN REQUIRED    │                                         
    │         └────────┬────────┘                                         
    │                  │                                                  
    │                  ▼                                                  
    │         ┌─────────────────┐                                         
    │         │ PASTEURIZED     │                                         
    │         │ MILK INVENTORY  │                                         
    │         └────────┬────────┘                                         
    │                  │                                                  
    └──────────┬───────┘                                                  
               │                                                          
               ▼                                                          
   ┌─────────────────────┐                                                
   │  PRODUCTION RUN     │                                                
   │  Created (planned)  │                                                
   └─────────┬───────────┘                                                
             │                                                             
             ▼                                                             
   ┌─────────────────────┐                                                
   │  CCP LOGGING        │ ◄── Log temp, pressure, time at each stage    
   │  At each stage      │                                                
   └─────────┬───────────┘                                                
             │                                                             
             ▼                                                             
   ┌─────────────────────┐                                                
   │  COMPLETE RUN       │                                                
   │  - Actual yield     │                                                
   │  - Variance calc    │                                                
   │  - Byproducts       │                                                
   └─────────┬───────────┘                                                
             │                                                             
             ▼                                                             
   ┌─────────────────────┐                                                
   │  QC BATCH RELEASE   │ ◄── Pending QC verification                   
   │  (From QC Module)   │                                                
   └─────────┬───────────┘                                                
             │                                                             
             ▼                                                             
   ┌─────────────────────┐                                                
   │  FINISHED GOODS     │                                                
   │  WAREHOUSE          │                                                
   └─────────────────────┘                                                
```

### Butter Production Specific Flow

```
RAW MILK
    │
    ▼
┌─────────────────────┐
│  SEPARATOR MACHINE  │ ◄── Must log separation %
└─────────┬───────────┘
          │
    ┌─────┴─────┐
    │           │
    ▼           ▼
┌─────────┐ ┌─────────────┐
│  CREAM  │ │  SKIM MILK  │
│  (20%)  │ │   (80%)     │
└────┬────┘ └──────┬──────┘
     │             │
     │             ▼
     │     ┌─────────────┐
     │     │  BYPRODUCT  │ ◄── Record: type=skim_milk, dest=warehouse
     │     │  RECORD     │
     │     └─────────────┘
     │
  24hr storage
     │
     ▼
┌─────────────┐
│  CHURNING   │ ◄── 45-60 minute process
│  (Turning)  │
└──────┬──────┘
       │
   ┌───┴───┐
   │       │
   ▼       ▼
┌───────┐ ┌────────────┐
│BUTTER │ │ BUTTERMILK │
│ BLOCKS│ │ (Byproduct)│
└───────┘ └────────────┘
```

### Cheese Production State Machine

```
STATE 1: COOKING/STEAMING ──────────────────────┐
├── Temperature: 80°C - 87°C                    │
├── Duration: Log time                          │ cheese_state
└── Additives: Salt, Vinegar, Rennet            │ = 'cooking'
         │                                      │
         ▼                                      │
STATE 2: STIRRING & PRE-PRESSING ───────────────┤
├── Duration: 20-30 minutes                     │ cheese_state
└── Action: Whey drained (→ byproduct)          │ = 'stirring'
         │                                      │
         ▼                                      │
STATE 3: PRESSING ──────────────────────────────┤
├── Duration: 1 hour active pressing            │ cheese_state
└── Equipment: Cheese press                     │ = 'pressing'
         │                                      │
         ▼                                      │
STATE 4: RESTING ───────────────────────────────┤
├── Duration: 24 hours (overnight)              │ cheese_state
└── Storage: Controlled temperature             │ = 'resting'
         │                                      │
         ▼                                      │
STATE 5: TURNING & MOLDING ─────────────────────┤
├── Action: Cheese turned, cut, placed in molds │ cheese_state
└── Mold Size: Record dimensions                │ = 'molding'
         │                                      │
         ▼                                      │
STATE 6: FINAL WEIGHING ────────────────────────┤
├── Yield: Specific weight (e.g., 240g blocks)  │ cheese_state
└── Status: Logged as FINISHED GOODS            │ = 'weighing'
```

---

## Standards & Compliance

### Production Process Flow (FDA/HACCP)

#### Fresh Milk Standard Process

```
RAW MILK RECEIVING
        ↓
   [Acceptance Criteria]
   • APT: Negative
   • Titratable Acidity: 0.11 - 0.18%
   • Lactodensimeter: 1.025 and above
        ↓
    CHILLING
   Temperature: 4°C ──────────────── CCP LOG
        ↓
   PRE-HEATING
   Temperature: 65°C ─────────────── CCP LOG
        ↓
    COOLING
   Temperature: 4°C ──────────────── CCP LOG
        ↓
  HOMOGENIZATION
   Pressure: 1000-1500 psi ───────── CCP LOG
        ↓
    COOLING
   Temperature: 4°C ──────────────── CCP LOG
        ↓
 PASTEURIZATION (HTST)
   Temperature: 75°C for 15 seconds ─ CCP LOG (CRITICAL)
        ↓
    COOLING
   Temperature: 4°C ──────────────── CCP LOG
        ↓
    SEALING
   Packaging: 1000ml, 500ml, 200ml
        ↓
    STORING
   Temperature: 4°C ──────────────── CCP LOG
```

### Temperature Requirements Summary

| Stage | Temperature | Duration/Notes | CCP? |
|-------|-------------|----------------|------|
| **Chilling** | 4°C | Upon receiving | Yes |
| **Pre-heating** | 65°C | Before homogenization | Yes |
| **Homogenization** | N/A | Pressure: 1000-1500 psi | Yes |
| **Pasteurization (HTST)** | **75°C** | **15 seconds** | **CRITICAL** |
| **Cooling (all stages)** | 4°C | Between processes | Yes |
| **Storage** | 4°C | Final storage | Yes |

---

## "What Happens If" Scenario Analysis

### ✅ COVERED SCENARIOS (Currently Implemented)

#### 1. What Happens If Production Tries to Make Yogurt Without Pasteurized Milk?

| Scenario | System Behavior | Status |
|----------|-----------------|--------|
| No pasteurized milk available | API blocks with error: "YOGURT requires PASTEURIZED MILK" | ✅ Covered |
| Insufficient pasteurized milk | API shows shortage: "Required: XL, Available: YL" | ✅ Covered |
| Raw milk selected for yogurt | System forces pasteurized milk source | ✅ Covered |

---

#### 2. What Happens If No Materials Are Available?

| Scenario | System Behavior | Status |
|----------|-----------------|--------|
| No issued milk via requisitions | Error: "Please submit a requisition to Warehouse Raw" | ✅ Covered |
| Requisition not approved | Cannot fulfill, must wait for GM approval | ✅ Covered |
| Partial fulfillment | Shows available vs required quantities | ✅ Covered |

---

#### 3. What Happens If CCP Check Fails?

| Scenario | System Behavior | Status |
|----------|-----------------|--------|
| Temperature out of range | Status marked as 'fail' | ✅ Covered |
| Pressure out of range | Status marked as 'fail' | ✅ Covered |
| CCP failure logged | Record stored with notes | ✅ Covered |

---

#### 4. What Happens If Yield Variance Exceeds Threshold?

| Scenario | System Behavior | Status |
|----------|-----------------|--------|
| Actual < Planned | Variance calculated as negative % | ✅ Covered |
| Variance reason required | Staff can provide explanation | ✅ Covered |
| Variance recorded | Stored for efficiency analysis | ✅ Covered |

---

### ⚠️ GAPS IDENTIFIED (Required for Deployment)

#### 🔴 CRITICAL GAPS

##### 1. ~~No Automatic Inventory Deduction on Completion~~ ✅ FIXED (Feb 2026)

**Scenario:** When a production run completes, raw materials should be automatically deducted.

| Current State | Risk | Recommendation |
|---------------|------|----------------|
| ✅ Auto-records ingredient consumption | Resolved | `runs.php` now creates `ingredient_consumption` records on completion |
| ✅ Scales based on actual output | Resolved | Uses scale factor: actual_qty / planned_qty |

**Implementation:** In `api/production/runs.php` (line ~720), on run completion:
- Queries `recipe_ingredients` for the recipe
- Inserts scaled quantities into `ingredient_consumption` table
- Records batch_code for traceability

---

##### 2. No Finished Goods Auto-Creation

**Scenario:** Completed batches should automatically create FG inventory records.

| Current State | Risk | Recommendation |
|---------------|------|----------------|
| ⚠️ Completed runs create batch | Partially done | QC Release triggers FG creation - WORKING |
| ✅ QC module creates FG | Working | Confirmed via test_production_gaps.php |

---

##### 3. No Equipment Maintenance Tracking

**Scenario:** Pasteurizer or separator needs maintenance - should block runs.

| Current State | Risk | Recommendation |
|---------------|------|----------------|
| ❌ No equipment status tracking | Safety risk | Add `equipment` table with maintenance schedule |
| ❌ No CCP equipment calibration | Compliance issue | Link CCP logs to calibrated equipment |

---

##### 4. No Production Planning/Scheduling

**Scenario:** Plan tomorrow's production based on orders.

| Current State | Risk | Recommendation |
|---------------|------|----------------|
| ❌ No production schedule | Reactive only | Add production planning module |
| ❌ No order-driven production | Waste potential | Link to sales orders for demand |

---

##### 5. ~~Whey Byproduct Not Auto-Recorded for Cheese~~ ✅ PARTIALLY FIXED (Feb 2026)

**Scenario:** Cheese production generates whey, butter generates skim milk and buttermilk.

| Current State | Risk | Recommendation |
|---------------|------|----------------|
| ✅ Butter: skim_milk on run creation | Fixed | Records from separation process |
| ✅ Butter: buttermilk on completion | Fixed | Records ~55% of cream used |
| ❌ Cheese: whey still manual | Data gaps | TODO: Auto-record whey for cheese runs |

**Implementation:** In `api/production/runs.php`:
- Line ~435: Creates `skim_milk` byproduct when butter run is created
- Line ~770: Creates `buttermilk` byproduct when butter run completes

---

##### 6. No Recipe Versioning

**Scenario:** Recipe ingredients change but historical run data references old recipe.

| Current State | Risk | Recommendation |
|---------------|------|----------------|
| ❌ No recipe versions | Historical confusion | Add `recipe_version` column |
| ❌ Changes overwrite | Audit trail lost | Store snapshots on run creation |

---

#### 🟡 MEDIUM PRIORITY GAPS

##### 7. No Production Reports/Analytics

| Current State | Risk | Recommendation |
|---------------|------|----------------|
| ❌ No yield efficiency reports | No insights | Add efficiency report API |
| ❌ No waste analysis | Cannot optimize | Track shrinkage patterns |
| ❌ No CCP compliance reports | Audit difficulty | Add CCP summary reports |

---

##### 8. No Multi-Stage Status Persistence

**Scenario:** Run is at "cooling" but user accidentally clicks "start" again.

| Current State | Risk | Recommendation |
|---------------|------|----------------|
| ⚠️ Some validation exists | Edge cases possible | Add strict state machine validation |
| ❌ No undo capability | Mistakes permanent | Add status correction with audit trail |

---

##### 9. No Batch Splitting/Combining

**Scenario:** Need to split a large batch or combine small batches.

| Current State | Risk | Recommendation |
|---------------|------|----------------|
| ❌ No batch split function | Inflexible | Add split capability |
| ❌ No batch combine function | Cannot consolidate | Add combine with traceability |

---

### 📊 DEPLOYMENT READINESS SCORECARD

| Category | Covered | Gaps | Score |
|----------|---------|------|-------|
| **Recipe Management** | 5/5 | 0 | ✅ 100% |
| **Production Runs** | 8/10 | 2 | ⚠️ 80% |
| **Pasteurization** | 4/4 | 0 | ✅ 100% |
| **Milk Source Validation** | 4/4 | 0 | ✅ 100% |
| **CCP Logging** | 5/6 | 1 | ⚠️ 83% |
| **Requisitions** | 4/5 | 1 | ⚠️ 80% |
| **Byproducts** | 3/5 | 2 | 🔴 60% |
| **Inventory Integration** | 1/4 | 3 | 🔴 25% |
| **Reporting** | 1/5 | 4 | 🔴 20% |

**Overall Deployment Readiness: 75%**

---

### 🎯 RECOMMENDED IMPLEMENTATION PRIORITY

#### Phase 1 (Before Go-Live) - CRITICAL

| Item | Effort | Impact |
|------|--------|--------|
| Auto-deduct inventory on completion | 3 days | Critical |
| Auto-create FG on QC release | 2 days | Critical |
| Whey byproduct for cheese | 1 day | Medium |
| Production run corrections/void | 2 days | High |

#### Phase 2 (Within 30 Days) - IMPORTANT

| Item | Effort | Impact |
|------|--------|--------|
| Equipment maintenance tracking | 3 days | High |
| Recipe versioning | 2 days | Medium |
| Production efficiency reports | 3 days | High |
| CCP compliance reports | 2 days | High |

#### Phase 3 (Within 90 Days) - ENHANCEMENT

| Item | Effort | Impact |
|------|--------|--------|
| Production planning/scheduling | 5 days | High |
| Order-driven production | 5 days | High |
| Batch split/combine | 3 days | Medium |
| IoT temperature integration | 5 days | Medium |

---

## Summary

The Production Module is the **operational engine** of Highland Fresh, transforming raw materials into finished dairy products. It ensures:

1. **Recipe Compliance** - Products made according to defined recipes
2. **Milk Source Integrity** - Yogurt uses pasteurized, other products use raw
3. **Process Control** - CCP logging at every critical stage
4. **Traceability** - Complete audit trail from milk to product
5. **Material Control** - Formal requisition workflow for all inputs
6. **Byproduct Recovery** - Secondary outputs tracked and utilized

### Current State Assessment

| Aspect | Status |
|--------|--------|
| **Core Production Functions** | ✅ Fully Implemented |
| **Pasteurization Module** | ✅ Working |
| **CCP Logging** | ✅ Working |
| **Material Requisitions** | ✅ Working |
| **Byproduct Tracking** | 🟡 Partial (butter complete, cheese manual) |
| **Inventory Auto-Sync** | 🔴 Missing |
| **Reporting** | 🔴 Basic Only |

### Recommendation

The Production module is **75% deployment-ready**. Before production deployment:

1. Implement automatic inventory deduction on run completion
2. Ensure FG auto-creation on QC release
3. Add whey byproduct auto-tracking for cheese
4. Create basic production efficiency reports

---

*Documentation Generated: February 9, 2026*  
*Highland Fresh Dairy System v4.0*
