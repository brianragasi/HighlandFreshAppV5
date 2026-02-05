# Highland Fresh - Production Requirements

**Document Purpose:** This document captures the detailed production logic and requirements from the client requirements gathering meeting (January 21, 2026). It defines how the software handles inventory flow, recipe management, and step-by-step production logic for all product lines.

> **Key Insight:** Production is NOT simply "input ingredients → output product." Each product has unique workflows with intermediate stages, byproducts, and quality checkpoints.

---

## 1. System Architecture & Inventory Flow

### Warehouse vs. Production Separation

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           INVENTORY ZONES                                │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  ┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐   │
│  │   WAREHOUSE     │     │  WORK IN        │     │   FINISHED      │   │
│  │   (Raw Storage) │ ──► │  PROCESS (WIP)  │ ──► │   GOODS (FG)    │   │
│  │                 │     │  (Factory Floor)│     │                 │   │
│  │ • Dry Ingredients│     │ • Active Batches│     │ • Bottled Milk  │   │
│  │ • Packaging     │     │ • Processing    │     │ • Butter Blocks │   │
│  │ • MRO Supplies  │     │ • Quality Checks│     │ • Cheese Wheels │   │
│  └─────────────────┘     └─────────────────┘     └─────────────────┘   │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

### The "Recipe" Conflict

| Recipe Type | Purpose | Behavior |
|-------------|---------|----------|
| **Finance Recipe** | Cost calculation, budgeting | Fixed theoretical ratios (e.g., 1L milk = 0.5kg butter) |
| **Production Recipe** | Actual floor usage | **EDITABLE** - Staff can adjust quantities in real-time |

> **Requirement:** The system MUST allow production staff to edit ingredient quantities during batch entry to reflect actual usage (e.g., adjusting cocoa powder amounts based on taste tests).

---

## 2. Raw Milk Intake & Pre-Processing

### Volume Tracking Pipeline

```
RAW MILK RECEIVING
      │
      ▼
┌─────────────────┐
│  Record Volume  │ ◄── Initial liters received from farmers
│  (e.g., 500L)   │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ PASTEURIZATION  │ ◄── Track temperature (75°C), duration (15 sec)
│                 │
│ Output: ~498L   │ ◄── Account for 0.5-1% shrinkage
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ HOMOGENIZATION  │ ◄── Track pressure (1000-1500 psi)
│                 │
│ Output: ~497L   │ ◄── Account for processing loss
└────────┬────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────┐
│                    ALLOCATION DECISION                       │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│    ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐  │
│    │ Bottling │  │  Yogurt  │  │  Cheese  │  │  Butter  │  │
│    │  200L    │  │   100L   │  │   150L   │  │   47L    │  │
│    └──────────┘  └──────────┘  └──────────┘  └──────────┘  │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

### Data Entry Requirements
- **Volume (Liters)**: Track at each stage
- **Temperature (°C)**: Pasteurization temp
- **Duration (seconds/minutes)**: Processing time
- **Shrinkage**: Auto-calculate or manual entry for losses

---

## 3. Butter Production

### Critical Logic: Separation Required

> ⚠️ **Butter is NOT produced directly from whole milk.**

```
RAW MILK
    │
    ▼
┌─────────────────────┐
│  SEPARATOR MACHINE  │
│                     │
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
     ▼             ▼
┌─────────┐ ┌─────────────┐
│ CHURNING│ │  BYPRODUCT  │
│         │ │  INVENTORY  │
└────┬────┘ │  (Yogurt,   │
     │      │  Surplus)   │
     ▼      └─────────────┘
┌─────────┐
│ BUTTER  │
│ BLOCKS  │
└─────────┘
```

### System Requirements
1. **Separation Logging**: Record input milk volume → output cream + skim milk volumes
2. **Cream Weighing**: Weigh cream separately before churning
3. **Byproduct Tracking**: Skim milk goes to byproduct inventory (can be used for yogurt or sold)
4. **Yield Calculation**: Track butter yield from cream (typically 40-45% of cream weight)

### Data Fields Needed
| Field | Type | Purpose |
|-------|------|---------|
| `input_milk_liters` | Decimal | Raw milk into separator |
| `output_cream_kg` | Decimal | Cream produced |
| `output_skim_milk_liters` | Decimal | Skim milk byproduct |
| `butter_yield_kg` | Decimal | Final butter output |
| `buttermilk_liters` | Decimal | Churning byproduct |

---

## 4. Yogurt Production

### Critical Logic: Pasteurized Milk Required

> ⚠️ **Yogurt CANNOT draw inventory from Raw Milk directly.**

```
                    ┌─────────────────┐
                    │    RAW MILK     │
                    │  (CANNOT USE)   │ ──── ✗ WRONG PATH
                    └─────────────────┘
                    
CORRECT WORKFLOW:

┌─────────────┐     ┌─────────────────┐     ┌─────────────────┐
│  RAW MILK   │ ──► │ PASTEURIZATION  │ ──► │  PASTEURIZED    │
│             │     │   (75°C/15s)    │     │  MILK INVENTORY │
└─────────────┘     └─────────────────┘     └────────┬────────┘
                                                     │
                                                     ▼
                                            ┌─────────────────┐
                                            │ YOGURT          │
                                            │ PRODUCTION      │
                                            │                 │
                                            │ + Culture       │◄── Deduct from Warehouse
                                            │ + Flavorings    │◄── Deduct from Warehouse
                                            │ + Sugar         │◄── Deduct from Warehouse
                                            └────────┬────────┘
                                                     │
                                                     ▼
                                            ┌─────────────────┐
                                            │ FINISHED YOGURT │
                                            │ (Cups/Bottles)  │
                                            └─────────────────┘
```

### Ingredient Deductions
When creating a yogurt batch, the system must deduct:
1. **Pasteurized Milk** (from WIP/Pasteurized Milk Inventory)
2. **Culture** (from Warehouse - Ingredients)
3. **Flavorings** (from Warehouse - Ingredients)
4. **Sugar** (from Warehouse - Ingredients)
5. **Cups/Packaging** (from Warehouse - Packaging)

---

## 5. Fresh & Flavored Milk (Milk Bars)

### Product Variants
- Fresh Milk (Unflavored)
- Choco
- Melon
- Pandan
- Ube
- Strawberry

### Triple Deduction Logic

```
┌─────────────────────────────────────────────────────────────┐
│              MILK BAR BATCH CREATION                         │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  MUST DEDUCT THREE COMPONENTS:                               │
│                                                              │
│  1. LIQUID BASE                                              │
│     └── Pasteurized Milk (Liters)                           │
│                                                              │
│  2. ADDITIVES                                                │
│     ├── Sugar (Kilograms)                                   │
│     ├── Flavorings/Powder (Grams)                           │
│     └── Colorings (Grams - if applicable)                   │
│                                                              │
│  3. PACKAGING                                                │
│     ├── Bottles (Units) - 200ml, 500ml, 1L                  │
│     └── Caps (Units)                                         │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

### System Requirements
- **Per-Flavor Recipes**: Each flavor has different additive quantities
- **Real-Time Adjustment**: Allow staff to adjust quantities based on actual usage
- **Packaging Unit Tracking**: Match bottle count to actual output

---

## 6. Cheese Production (Gouda & White)

### Base Ingredient Difference

| Cheese Type | Uses | Batch Size |
|-------------|------|------------|
| **White Cheese** | Raw Milk directly | Small (10-15 Liters) |
| **Gouda Cheese** | Raw Milk directly | Large (~300 Liters) |

> Unlike Yogurt, Cheese uses **RAW MILK** directly (not pasteurized).

### Gouda Production States (Timeline Tracking)

The system must record each state with timestamps and parameters:

```
STATE 1: COOKING/STEAMING
├── Temperature: 80°C - 87°C (MUST LOG)
├── Duration: Log time
└── Additives: Salt, Vinegar, Rennet (DEDUCT)
         │
         ▼
STATE 2: STIRRING & PRE-PRESSING
├── Duration: 20-30 minutes
└── Action: Whey is drained (track whey volume as byproduct)
         │
         ▼
STATE 3: PRESSING
├── Duration: 1 hour active pressing
└── Equipment: Cheese press
         │
         ▼
STATE 4: RESTING
├── Duration: 24 hours (overnight)
└── Storage: Controlled temperature
         │
         ▼
STATE 5: TURNING & MOLDING
├── Action: Cheese is turned, cut, placed in molds
└── Mold Size: Record dimensions
         │
         ▼
STATE 6: FINAL WEIGHING
├── Yield: Specific weight (e.g., 240g blocks)
└── Status: Logged as FINISHED GOODS
```

### Data Fields for Cheese
| Field | Type | Purpose |
|-------|------|---------|
| `cooking_temp` | Decimal | Temperature during cooking (80-87°C) |
| `cooking_duration_mins` | Integer | How long cooked |
| `is_salted` | Boolean | Salted vs Unsalted variant |
| `stirring_duration_mins` | Integer | Pre-pressing time |
| `pressing_duration_mins` | Integer | Active pressing time |
| `resting_hours` | Integer | Overnight resting duration |
| `actual_yield_kg` | Decimal | Final cheese weight |
| `theoretical_yield_kg` | Decimal | Expected yield (for efficiency calc) |
| `whey_liters` | Decimal | Byproduct tracking |

---

## 7. Data Entry Requirements Summary

### New Fields Required in Production Interface

| Field Name | Data Type | Applies To | Purpose |
|------------|-----------|------------|---------|
| `temperature` | Decimal (°C) | Pasteurization, Cheese | Log processing temperature |
| `duration_minutes` | Integer | All processes | Log processing time |
| `batch_status` | Enum | Cheese | Track state (cooking, pressing, resting, etc.) |
| `is_salted` | Boolean | Cheese, Butter | Salt variant indicator |
| `actual_yield` | Decimal | All | Actual output quantity |
| `theoretical_yield` | Decimal | All | Expected output per recipe |
| `efficiency_percent` | Calculated | All | `(actual / theoretical) * 100` |
| `byproduct_quantity` | Decimal | Butter, Cheese | Track skim milk, whey, etc. |
| `ingredient_adjustments` | JSON | All | Log any recipe deviations |

### Efficiency Tracking Formula

```
Efficiency % = (Actual Yield / Theoretical Yield) × 100

Example:
- Recipe says 100L milk should produce 95 bottles
- Actual production: 92 bottles
- Efficiency: (92 / 95) × 100 = 96.8%
```

---

## 8. Implementation Status

### ✅ COMPLETED (January 21, 2026)

| Feature | Status | Implementation Details |
|---------|--------|----------------------|
| **Recipe Editability** | ✅ Done | Editable ingredient quantities in production batch form. Staff can adjust actual usage vs recipe defaults. Data stored as `ingredient_adjustments` JSON. |
| **Temperature/Duration Fields** | ✅ Done | Added `process_temperature` (°C) and `process_duration_mins` fields with product-type specific hints (e.g., "Pasteurization: 72-75°C" for milk, "Cooking: 80-87°C" for cheese). |
| **Butter Separation Logic** | ✅ Done | Added `cream_output_kg` and `skim_milk_output_liters` fields. Auto-creates byproduct record for skim milk when butter run is created. |
| **Cheese State Tracking** | ✅ Done | Added `cheese_state` dropdown (cooking→stirring→pressing→resting→molding→weighing) and `is_salted` checkbox. |
| **Yogurt Inventory Source** | ✅ Done | Yogurt production now validates against `pasteurized_milk_inventory` table. API blocks raw milk usage for yogurt. UI shows pasteurized milk availability with FIFO batch allocation. |
| **Pasteurization Run UI** | ✅ Done | New `pasteurization.html` page with full UI. Create runs to convert raw milk to pasteurized milk. Complete runs to add to `pasteurized_milk_inventory`. Stats, table, and modals. |

### 🔄 IN PROGRESS

| Feature | Status | Notes |
|---------|--------|-------|
| **Byproduct Tracking** | 🔄 Partial | Skim milk auto-recorded for butter; need whey tracking for cheese |

### 📋 PENDING

| Priority | Feature | Complexity | Notes |
|----------|---------|------------|-------|
| 🟡 Medium | **Byproduct Transfer UI** | Medium | Allow transferring byproducts to warehouse inventory |
| 🟢 Low | **Efficiency Reports** | Medium | Compare actual vs theoretical yield with analytics |


---

## 9. Database Schema Updates

### Production Runs Enhancements
```sql
ALTER TABLE production_runs 
ADD COLUMN process_temperature DECIMAL(5,2) DEFAULT NULL,
ADD COLUMN process_duration_mins INT DEFAULT NULL,
ADD COLUMN ingredient_adjustments JSON DEFAULT NULL,
ADD COLUMN cream_output_kg DECIMAL(10,3) DEFAULT NULL,
ADD COLUMN skim_milk_output_liters DECIMAL(10,3) DEFAULT NULL,
ADD COLUMN cheese_state ENUM('cooking','stirring','pressing','resting','molding','weighing') DEFAULT NULL,
ADD COLUMN is_salted TINYINT(1) DEFAULT 0,
ADD COLUMN milk_source_type ENUM('raw', 'pasteurized') DEFAULT 'raw',
ADD COLUMN pasteurized_milk_batch_id INT DEFAULT NULL;
```

### Pasteurized Milk Inventory (NEW)
```sql
CREATE TABLE pasteurized_milk_inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_code VARCHAR(50) NOT NULL UNIQUE,
    quantity_liters DECIMAL(10,3) NOT NULL,
    remaining_liters DECIMAL(10,3) NOT NULL,
    pasteurization_temp DECIMAL(5,2) DEFAULT 75.0,
    pasteurization_duration_mins INT DEFAULT 15,
    pasteurized_at DATETIME NOT NULL,
    expiry_date DATE NOT NULL,
    status ENUM('available', 'reserved', 'exhausted', 'expired') DEFAULT 'available'
);

-- View for FIFO allocation
CREATE VIEW v_available_pasteurized_milk AS
SELECT id, batch_code, remaining_liters, expiry_date
FROM pasteurized_milk_inventory
WHERE status = 'available' AND remaining_liters > 0 AND expiry_date >= CURDATE()
ORDER BY pasteurized_at ASC;
```

---

**Document Version:** 1.3  
**Created:** January 21, 2026  
**Last Updated:** January 21, 2026 (15:09)  
**Based On:** Client Requirements Meeting Recording
