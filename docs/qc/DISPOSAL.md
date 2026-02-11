# Disposal/Write-Off Module

## Overview

The Disposal Module manages the write-off and proper disposal of rejected, expired, spoiled, or damaged products. All disposals require **General Manager approval** before execution to prevent fraud and ensure proper documentation.

---

## Business Logic

### Why Does QC Handle Disposals?

**QC doesn't "discover" all disposals.** Instead, QC serves as the **quality verification gatekeeper**:

| Who Discovers the Issue | QC's Role | Example |
|------------------------|-----------|---------|
| **QC Officer** | Initiates directly | Milk fails receiving test |
| **Warehouse Staff** | Notifies QC → QC verifies & creates | Damaged packaging found |
| **Expiry Module** | QC reviews → creates disposal | Near-expiry milk can't be transformed |
| **Production Staff** | Reports to QC → QC verifies | Batch contamination detected |

### Why GM Approval is Required

1. **Prevents fraud** - Someone can't just "dispose" of products and take them home
2. **Financial accountability** - Tracks loss values for accounting
3. **Audit trail** - Documents who, what, when, why for compliance
4. **Proper method** - Ensures hazardous items are disposed correctly

---

## Workflow

```
┌─────────────────────────────────────────────────────────────────┐
│                    DISPOSAL WORKFLOW                            │
└─────────────────────────────────────────────────────────────────┘

1. ISSUE IDENTIFIED
   └── Source: QC test failure, expiry, warehouse damage report
   
2. QC CREATES DISPOSAL REQUEST
   ├── Source type & ID
   ├── Quantity to dispose
   ├── Category (reason)
   ├── Method (how to dispose)
   └── Detailed reason notes
   
3. STATUS: PENDING
   └── Request awaits GM review
   
4. GM REVIEWS & DECIDES
   ┌───┴───┐
   │       │
Approve   Reject
   │       │
   ↓       ↓
STATUS:  STATUS:
APPROVED REJECTED
   ↓     (end)
   
5. QC/WAREHOUSE EXECUTES
   ├── Witness name (optional)
   ├── Disposal location
   └── Execution notes
   
6. SYSTEM UPDATES INVENTORY
   ├── Deduct from source inventory
   ├── Create inventory transaction
   └── Log audit trail
   
7. STATUS: COMPLETED
```

---

## Disposal Categories

| Category | Description | Typical Source |
|----------|-------------|----------------|
| **qc_failed** | Failed quality control test | Milk receiving, batch inspection |
| **expired** | Past expiry date | Finished goods inventory |
| **spoiled** | Deteriorated during storage | Raw milk, finished goods |
| **contaminated** | Foreign matter or cross-contamination | Any inventory |
| **damaged** | Physical or packaging damage | Finished goods |
| **rejected_receipt** | Rejected at farmer receiving | Milk receiving |
| **production_waste** | Line waste, overruns, samples | Production batches |
| **other** | Other reasons (specify in notes) | Any source |

---

## Disposal Methods

| Method | Use Case | Safety Notes |
|--------|----------|--------------|
| **drain** | Liquid disposal (rejected milk) | Into approved drain only |
| **incinerate** | Contaminated/hazardous materials | Professional incinerator |
| **animal_feed** | Safe but unsellable products | Must be fit for animal consumption |
| **compost** | Organic waste, plant-based | No contaminated items |
| **landfill** | General waste | Last resort |
| **special_waste** | Hazardous materials | Licensed contractor required |

---

## Database Schema

### `disposals` Table

```sql
CREATE TABLE IF NOT EXISTS disposals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    disposal_code VARCHAR(30) NOT NULL UNIQUE,
    
    -- Source identification
    source_type ENUM('raw_milk', 'finished_goods', 'ingredients', 
                     'milk_receiving', 'production_batch') NOT NULL,
    source_id INT NOT NULL,
    source_reference VARCHAR(100) NULL,
    
    -- Product details
    product_id INT NULL,
    product_name VARCHAR(255) NULL,
    
    -- Quantity and value
    quantity DECIMAL(10,2) NOT NULL,
    unit VARCHAR(20) NOT NULL DEFAULT 'pcs',
    unit_cost DECIMAL(10,2) NULL,
    total_value DECIMAL(12,2) NULL,
    
    -- Disposal details
    disposal_category ENUM('qc_failed', 'expired', 'spoiled', 'contaminated',
                           'damaged', 'rejected_receipt', 'production_waste', 'other'),
    disposal_method ENUM('drain', 'incinerate', 'animal_feed', 'compost', 
                         'landfill', 'special_waste'),
    disposal_reason TEXT NOT NULL,
    
    -- Workflow
    status ENUM('pending', 'approved', 'rejected', 'completed', 'cancelled'),
    initiated_by INT NOT NULL,
    approved_by INT NULL,
    disposed_by INT NULL,
    
    -- Execution details
    witness_name VARCHAR(255) NULL,
    disposal_location VARCHAR(255) NULL,
    photo_evidence JSON NULL,
    
    -- Timestamps
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    approved_at DATETIME NULL,
    disposed_at DATETIME NULL
);
```

---

## API Endpoints

### `api/qc/disposals.php`

| Method | Action | Description | Role |
|--------|--------|-------------|------|
| `GET` | list | List all disposals with filters | QC, GM, Warehouse |
| `GET` | `?id=X` | Get disposal details | QC, GM, Warehouse |
| `GET` | `?action=stats` | Get disposal statistics | QC, GM |
| `POST` | create | Create new disposal request | QC |
| `PUT` | `action=approve` | Approve disposal | GM only |
| `PUT` | `action=reject` | Reject disposal | GM only |
| `PUT` | `action=complete` | Execute disposal, update inventory | QC, Warehouse |
| `DELETE` | cancel | Cancel pending disposal | QC |

---

## UI Location

**Path:** `html/qc/disposals.html`

### Features

1. **Dashboard Stats**
   - Pending approvals
   - Approved (awaiting execution)
   - Completed
   - Total loss value

2. **Disposals Table**
   - Filter by status, category, date
   - View details
   - Action buttons based on role

3. **Create Disposal Form**
   - Source type selection
   - Auto-populate product details
   - Category and method dropdowns
   - Reason textarea

4. **Approval Modal** (GM only)
   - Show disposal details
   - Approve/Reject buttons
   - Notes field

5. **Execution Modal**
   - Witness name
   - Disposal location
   - Final notes

---

## Integration Points

| Module | Integration |
|--------|-------------|
| **Milk Receiving** | Dispose rejected milk |
| **Expiry Management** | Dispose expired products |
| **Batch Release** | Dispose failed batches |
| **Batch Recall** | Dispose returned recalled products |
| **Inventory** | Auto-deduct on completion |
| **Audit Trail** | Log all actions |

---

## Files

| File | Purpose |
|------|---------|
| `sql/create_disposals_module.sql` | Database migration |
| `api/qc/disposals.php` | REST API endpoints |
| `js/qc/disposal.service.js` | JavaScript service |
| `html/qc/disposals.html` | User interface |
