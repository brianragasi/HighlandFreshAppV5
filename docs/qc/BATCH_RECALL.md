# Batch Recall Module

## Overview

The Batch Recall module enables Highland Fresh to quickly identify and recall contaminated or defective product batches that have already been released to the market. This is a **critical food safety feature** required for compliance with FDA and PFDA regulations.

---

## Business Logic

### Why Does QC Need Batch Recall?

| Scenario | Example | Action Required |
|----------|---------|-----------------|
| **Post-release contamination** | Lab results show pathogen in batch after dispatch | Recall all units |
| **Customer complaint** | Multiple stores report off taste/smell | Investigate + potential recall |
| **Regulatory alert** | FDA issues ingredient recall notice | Check affected batches |
| **Production error discovered** | Wrong ingredient used, found later | Recall affected batches |

### Who Is Involved?

| Role | Responsibility |
|------|----------------|
| **QC Officer** | Initiates recall, identifies affected batches |
| **General Manager** | Approves recall (required) |
| **Warehouse** | Receives returned units, manages inventory |
| **Sales/Dispatch** | Identifies which stores received affected batches |
| **Finance** | Tracks financial impact of recall |

---

## Workflow

```
┌─────────────────────────────────────────────────────────────────┐
│                    BATCH RECALL WORKFLOW                        │
└─────────────────────────────────────────────────────────────────┘

1. CONTAMINATION DETECTED
   └── Source: Lab test, customer complaint, or regulatory alert
   
2. QC CREATES RECALL REQUEST
   ├── Identify Batch ID(s)
   ├── Specify reason
   ├── Set severity level (Class I, II, III)
   └── Document evidence
   
3. GM APPROVAL REQUIRED
   ├── Review batch details
   ├── Review dispatch records
   └── Approve/Reject recall
   
4. SYSTEM GENERATES RECALL LIST
   ├── Query all dispatched units from batch
   ├── Identify affected stores/locations
   ├── Calculate total units in circulation
   └── Generate recall notifications
   
5. NOTIFY AFFECTED LOCATIONS
   ├── Auto-generate recall notices
   ├── Include: Batch ID, product name, reason
   └── Provide return instructions
   
6. TRACK RETURNED UNITS
   ├── Warehouse receives returns
   ├── Log quantity returned per location
   └── Update recall recovery rate
   
7. DISPOSE OF RECALLED PRODUCTS
   ├── Link to Disposal module
   ├── Document destruction
   └── Complete audit trail

Status Flow: INITIATED → APPROVED → IN_PROGRESS → COMPLETED
```

---

## Recall Classification (FDA Standard)

| Class | Severity | Description | Example |
|-------|----------|-------------|---------|
| **Class I** | 🔴 Dangerous | Could cause serious health problems or death | Pathogen contamination |
| **Class II** | 🟡 May Cause | Might cause temporary health problems | Mislabeled allergens |
| **Class III** | 🟢 Unlikely | Not likely to cause health problems | Minor labeling error |

---

## Database Schema

### `batch_recalls` Table

```sql
CREATE TABLE IF NOT EXISTS batch_recalls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recall_code VARCHAR(30) NOT NULL UNIQUE,
    
    -- Batch identification
    batch_id INT NOT NULL,
    batch_code VARCHAR(50) NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    
    -- Recall details
    recall_class ENUM('class_i', 'class_ii', 'class_iii') NOT NULL,
    reason TEXT NOT NULL,
    evidence_notes TEXT NULL,
    
    -- Quantities
    total_produced INT NOT NULL DEFAULT 0,
    total_dispatched INT NOT NULL DEFAULT 0,
    total_in_warehouse INT NOT NULL DEFAULT 0,
    total_recovered INT NOT NULL DEFAULT 0,
    recovery_rate DECIMAL(5,2) GENERATED ALWAYS AS 
        (CASE WHEN total_dispatched > 0 
              THEN (total_recovered / total_dispatched) * 100 
              ELSE 0 END) STORED,
    
    -- Workflow
    status ENUM('initiated', 'pending_approval', 'approved', 'in_progress', 
                'completed', 'cancelled') NOT NULL DEFAULT 'initiated',
    initiated_by INT NOT NULL,
    initiated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_by INT NULL,
    approved_at DATETIME NULL,
    completed_by INT NULL,
    completed_at DATETIME NULL,
    
    -- Audit
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (batch_id) REFERENCES production_batches(id),
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (initiated_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### `recall_affected_locations` Table

```sql
CREATE TABLE IF NOT EXISTS recall_affected_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recall_id INT NOT NULL,
    
    -- Location details (from dispatch records)
    location_type ENUM('store', 'distributor', 'customer') NOT NULL,
    location_id INT NULL,
    location_name VARCHAR(255) NOT NULL,
    contact_person VARCHAR(255) NULL,
    contact_phone VARCHAR(50) NULL,
    
    -- Quantities
    units_dispatched INT NOT NULL DEFAULT 0,
    units_returned INT NOT NULL DEFAULT 0,
    units_destroyed_onsite INT NOT NULL DEFAULT 0,
    
    -- Tracking
    notification_sent BOOLEAN NOT NULL DEFAULT FALSE,
    notification_sent_at DATETIME NULL,
    acknowledged BOOLEAN NOT NULL DEFAULT FALSE,
    acknowledged_at DATETIME NULL,
    return_status ENUM('pending', 'partial', 'complete', 'none') NOT NULL DEFAULT 'pending',
    
    notes TEXT NULL,
    
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (recall_id) REFERENCES batch_recalls(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### `recall_returns` Table

```sql
CREATE TABLE IF NOT EXISTS recall_returns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recall_id INT NOT NULL,
    affected_location_id INT NOT NULL,
    
    -- Return details
    return_date DATE NOT NULL,
    units_returned INT NOT NULL,
    condition_notes TEXT NULL,
    received_by INT NOT NULL,
    
    -- Link to disposal
    disposal_id INT NULL,
    
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (recall_id) REFERENCES batch_recalls(id) ON DELETE CASCADE,
    FOREIGN KEY (affected_location_id) REFERENCES recall_affected_locations(id),
    FOREIGN KEY (received_by) REFERENCES users(id),
    FOREIGN KEY (disposal_id) REFERENCES disposals(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## API Endpoints

### `api/qc/recalls.php`

| Method | Action | Description | Role |
|--------|--------|-------------|------|
| `GET` | list | List all recalls with filters | QC, GM |
| `GET` | `?id=X` | Get recall details with affected locations | QC, GM |
| `GET` | `?action=stats` | Get recall statistics | QC, GM |
| `POST` | create | Initiate new recall | QC |
| `PUT` | `action=approve` | Approve recall (triggers notification) | GM |
| `PUT` | `action=reject` | Reject recall request | GM |
| `PUT` | `action=log_return` | Log returned units | Warehouse |
| `PUT` | `action=complete` | Mark recall as completed | QC |
| `DELETE` | cancel | Cancel initiated recall | QC |

### Request/Response Examples

#### Create Recall Request
```json
POST /api/qc/recalls.php
{
    "batch_id": 123,
    "recall_class": "class_ii",
    "reason": "Lab test detected elevated coliform levels",
    "evidence_notes": "Test report #LAB-2026-0208 attached"
}
```

#### Response
```json
{
    "success": true,
    "message": "Recall initiated",
    "data": {
        "recall_code": "RCL-20260208-001",
        "batch_code": "BATCH-20260205-001",
        "total_dispatched": 450,
        "affected_locations": 12,
        "status": "pending_approval"
    }
}
```

---

## UI Location

**Path:** `html/qc/recalls.html`

### Features

1. **Recall Dashboard**
   - Active recalls with status
   - Recovery rate progress bars
   - Urgent Class I alerts

2. **Create Recall Wizard**
   - Search/select batch
   - Auto-populate dispatch data
   - Upload evidence documents

3. **Affected Locations List**
   - Location name and contact
   - Notification status
   - Return tracking

4. **Return Logging**
   - Quick entry form
   - Link to disposal

---

## Integration Points

| Module | Integration |
|--------|-------------|
| **Production Batches** | Get batch details, production date |
| **Dispatch/Sales** | Query dispatched units by batch |
| **Disposal Module** | Create disposal for returned units |
| **Notifications** | Send recall alerts to locations |
| **Audit Trail** | Log all recall actions |

---

## Compliance Notes

1. **Record Retention**: Recall records must be kept for minimum 3 years
2. **Traceability**: Must be able to trace batch from production → dispatch → customer
3. **Response Time**: Class I recalls should be initiated within 24 hours
4. **Documentation**: All communications must be documented
5. **Regulatory Reporting**: Certain recalls require FDA/PFDA notification

---

## Files Created

| File | Purpose |
|------|---------|
| `sql/create_batch_recall_module.sql` | Database migration |
| `api/qc/recalls.php` | REST API endpoints |
| `js/qc/recall.service.js` | JavaScript service |
| `html/qc/recalls.html` | User interface |

---

## Next Steps

1. Run SQL migration
2. Test API endpoints
3. Integrate with dispatch/sales data
4. Add notification system
