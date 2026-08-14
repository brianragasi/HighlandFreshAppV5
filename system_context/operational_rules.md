# Highland Fresh - Critical Operational Rules

**Document Purpose:** This document captures the critical operational rules and business logic clarified during the client discussion (January 2026). These rules are fundamental to how the system must handle inventory, production, and waste management.

> **Key Reminder:** Highland Fresh is an **OPERATIONS SYSTEM** (Inventory/Production), NOT an Accounting System. The system's job is to **count physical items**, not balance financial ledgers.

> **Related Documents:**
> - `production_requirements.md` - Detailed production workflows for each product line (Fresh Milk, Butter, Yogurt, Cheese)
> - `production_staff.md` - Production Staff role and responsibilities

---

## 1. Milk Tank "Mixing" Logic (Pre-Tank QC)

### The Problem
When new fresh milk is poured into a tank that still contains milk from a previous delivery, the milk physically "mixes." This creates a tracking and quality control challenge.

### The Critical Rule
> **QC MUST happen BEFORE milk enters the main storage tank.**
> 
> You **cannot** mix untested milk with good milk. If the new batch is bad, it spoils the entire tank.

### Process Flow
```
┌─────────────────────┐
│  Supplier Arrives   │
│   with Fresh Milk   │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│  TEST SAMPLE FIRST  │◄── QC takes sample BEFORE any tank loading
│  (APT, Acidity, SG) │
└──────────┬──────────┘
           │
     ┌─────┴─────┐
     │           │
     ▼           ▼
┌─────────┐  ┌─────────┐
│  PASS   │  │  FAIL   │
└────┬────┘  └────┬────┘
     │            │
     ▼            ▼
┌─────────┐  ┌─────────────────┐
│ Load to │  │ REJECT IMMEDIATELY │
│  Tank   │  │ (Milk NEVER enters │
└─────────┘  │  the tank)         │
             └─────────────────┘
```

### Perishability Constraint
- Fresh milk has a **~3 hour window** during transport before quality risk increases
- This is why QC testing must be done immediately upon arrival
- Rejected milk is returned to supplier or disposed—never stored

### Supplier Tracking (Digital Traceability)
Even though milk **physically mixes** in the tank, the system **MUST digitally record**:
- Which supplier delivered which batch
- Volume delivered per supplier
- Quality metrics per delivery
- Timestamp of delivery

**Purpose:** If a customer complains about a specific bottle, the system can trace back to which suppliers provided the milk for that production run.

---

## 2. Reorder Point / Threshold Logic

### The Problem (Initial Wrong Assumption)
> ❌ **Wrong:** "Order new stock when inventory hits zero."

### The Correction
> ✅ **Correct:** The system must trigger an alert **BEFORE stock runs out** to account for **Lead Time** (shipping time from suppliers).

### How It Works

```
                    Reorder Point
                         │
                         ▼
Stock Level: ████████████│░░░░░░░░░░░░░░░░░░░
             100%        │                  0%
                         │
                         │◄─── Lead Time ───►│
                         │    (buffer zone)   │
                         │                    │
                    Alert fires         Stock depleted
                    here                 (preventable!)
```

### Configuration Per Material

| Material | Unit | Reorder Point | Lead Time | Example Scenario |
|----------|------|---------------|-----------|------------------|
| **Sugar** | Sacks | 5 sacks | 1-2 weeks | Alert when 5 sacks remain; takes 2 weeks to deliver |
| **Bottles 200ml** | Pieces | 500 pcs | 3-5 days | Alert when 500 pieces remain |
| **Caps** | Pieces | 1,000 pcs | 3-5 days | Alert when 1,000 pieces remain |
| **Cocoa Powder** | Bags | 3 bags | 1 week | Alert when 3 bags remain |
| **Rennet** | Bottles | 2 bottles | 2 weeks | Alert when 2 bottles remain (specialized item) |

### Alert Workflow

1. **System Detects:** Stock level reaches or falls below threshold
2. **Warehouse Check:** Warehouse Raw confirms the physical stock condition
3. **Manual PRS:** Warehouse Raw submits a Purchase Request Slip
4. **Purchaser Action:** Canvass at least three suppliers and prepare the PO
5. **GM Approval:** Approve or reject the PO
6. **Supplier Delivery:** The system sends the approved PO; supplier ships and delivers
7. **Receiving Report:** Warehouse Raw receives the items and generates the RR
8. **Final Verification:** Purchaser verifies the RR against the approved PO

### Dashboard Indicator Example

| Material | Current Stock | Reorder Point | Lead Time | Status |
|----------|---------------|---------------|-----------|--------|
| Sugar | 3 sacks | 5 sacks | 1 week | ⚠️ **BELOW THRESHOLD** |
| Bottles 200ml | 500 pcs | 200 pcs | 3 days | ✅ OK |
| Caps | 150 pcs | 300 pcs | 5 days | ⚠️ **BELOW THRESHOLD** |
| Cocoa Powder | 10 bags | 3 bags | 1 week | ✅ OK |

---

## 3. Lead Time Concept

### Definition
**Lead Time** is the total time from when a Purchase Order is placed until the goods physically arrive at the warehouse.

### Why It Matters
- Highland Fresh cannot produce if raw materials run out
- Some suppliers are far away (shipping could take **days or weeks**)
- Without lead time planning, production stops and revenue is lost

### Lead Time Components

```
┌─────────────────────────── TOTAL LEAD TIME ───────────────────────────┐
│                                                                        │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐ │
│  │ PO       │  │ Supplier │  │ Shipping/│  │ Receiving│  │ Stock-In │ │
│  │ Processing│  │ Processing│  │ Transit  │  │ & QC     │  │ Complete │ │
│  │ (1 day)  │  │ (1-2 days)│  │ (varies) │  │ (1 day)  │  │ (done)   │ │
│  └──────────┘  └──────────┘  └──────────┘  └──────────┘  └──────────┘ │
│       ▲            ▲              ▲             ▲             ▲       │
└───────┴────────────┴──────────────┴─────────────┴─────────────┴───────┘
```

### Lead Time by Ingredient Type

| Ingredient Type | Typical Lead Time | Reason |
|-----------------|-------------------|--------|
| **Fresh Milk** | Daily/Fixed Schedule | High perishability (~3 hours transport window) |
| **Sugar, Powder** | 1-2 weeks | Bulk items, distant suppliers |
| **Bottles, Caps** | 3-5 days | Local packaging suppliers |
| **Rennet, Cultures** | 2-4 weeks | Specialized imports |
| **MRO (Spare Parts)** | 1-4 weeks | Varies by part availability |

---

## 4. Material Balance Rule (The "Balancing the System" Principle)

### The Fundamental Rule
> **Raw Materials Used** = **Finished Goods Produced** + **Waste/Disposed Items**

### The "Black Hole" Error (What We're Preventing)

**Wrong Assumption:**
> ❌ "If a product fails QC during production, it just disappears or isn't counted."

**The Problem:**
- If 100 units of raw materials are consumed, but only 90 finished goods are produced...
- Where did the other 10 go?
- Without tracking, they become a "black hole"—money spent with no trace

### The Correction

```
┌────────────────────────────────────────────────────────────────┐
│                    MATERIAL BALANCE EQUATION                    │
├────────────────────────────────────────────────────────────────┤
│                                                                 │
│   Raw Materials     =    Finished Goods    +    Waste/Disposed │
│   Consumed               Produced               Items           │
│                                                                 │
│   Example:                                                      │
│   100 liters milk   =    90 bottles         +    10 liters      │
│   + ingredients          produced                (spillage,     │
│                                                   failed QC,    │
│                                                   samples)      │
│                                                                 │
│   ✅ BALANCED: The equation MUST always balance!               │
│                                                                 │
└────────────────────────────────────────────────────────────────┘
```

### Why This Matters

1. **Financial Accountability ("Gansi"):** Disposed items represent money spent on ingredients that generated **no revenue**. Without tracking, the company cannot calculate this loss.

2. **Audit Trail:** Every item that enters the system must have a destination—either sold or disposed.

3. **Quality Insights:** Tracking waste helps identify patterns (e.g., "Batch A has 15% waste—why?")

---

## 5. Disposal / Spoilage Module

### Purpose
The system must **explicitly track** all disposed items. Items cannot simply "disappear."

### Disposal Categories

| Category | Description | Example |
|----------|-------------|---------|
| **QC Rejection (Pre-Production)** | Raw milk failed QC testing | Milk with acidity ≥0.25% |
| **Production Failure** | Batch failed during manufacturing | Pasteurization equipment failure |
| **Post-Production QC Fail** | Finished goods failed final QC | Organoleptic test failure (bad taste) |
| **Expired Inventory** | Products past expiry date | Near-expiry milk not transformed to yogurt |
| **Damaged Goods** | Physical damage during storage/transport | Broken bottles, crushed packaging |
| **Returns - Disposed** | Customer returns deemed unfit for resale | Quality complaints, contamination |
| **Samples/Testing** | Items consumed for quality testing | Lab samples, taste tests |

### Disposal Workflow

```
┌─────────────────────┐
│  1. IDENTIFY        │
│  Item identified    │
│  for disposal by:   │
│  - QC Officer       │
│  - Warehouse Staff  │
│  - Production Staff │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│  2. DOCUMENT        │
│  Record in Disposal │
│  Log:               │
│  - Date/Time        │
│  - Item & Quantity  │
│  - Batch ID         │
│  - Disposal Reason  │
│  - Officer Name     │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│  3. APPROVE         │
│  GM or designated   │
│  approver confirms  │
│  the disposal       │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│  4. EXECUTE         │
│  Physical disposal  │
│  performed          │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│  5. RECONCILE       │
│  Disposal quantity  │
│  balances the       │
│  Material Balance   │
│  equation           │
└─────────────────────┘
```

---

## 6. Disposal Report

### Purpose
The Disposal/Spoilage Report is a **core system report** that allows management to:
- Calculate financial loss from wasted materials
- Identify patterns and root causes
- Make decisions to reduce waste

### Report Contents

| Section | Data Shown |
|---------|------------|
| **Summary** | Total items disposed, total estimated cost |
| **By Category** | Breakdown by disposal reason (QC fail, expired, damaged, etc.) |
| **By Product** | Which products have highest disposal rates |
| **By Batch** | Link to specific production batches for traceability |
| **By Supplier** | If raw materials were rejected, which supplier? |
| **Trends** | Daily/Weekly/Monthly trends over time |

### Example Report Output

```
═══════════════════════════════════════════════════════════════════
                    DISPOSAL/SPOILAGE REPORT
                    January 1-15, 2026
═══════════════════════════════════════════════════════════════════

SUMMARY
───────────────────────────────────────────────────────────────────
Total Items Disposed:           150 units
Estimated Cost of Disposed:     ₱12,500.00
Disposal Rate:                  3.2% of production

BY CATEGORY
───────────────────────────────────────────────────────────────────
Category                        Quantity        Cost
───────────────────────────────────────────────────────────────────
QC Rejection (Pre-Production)   50 liters       ₱1,500.00
Production Failure              20 bottles      ₱200.00
Expired Inventory               30 bottles      ₱300.00
Damaged Goods                   25 bottles      ₱250.00
Customer Returns (Disposed)     15 bottles      ₱150.00
Samples/Testing                 10 bottles      ₱100.00
─────────────────────────────────────────────────────────
TOTAL                           150 units       ₱2,500.00

BY SUPPLIER (Raw Milk Rejections)
───────────────────────────────────────────────────────────────────
Supplier                        Rejected Vol    Reason
───────────────────────────────────────────────────────────────────
Juan dela Cruz                  30 liters       Acidity 0.26%
Maria Santos                    20 liters       APT Positive
───────────────────────────────────────────────────────────────────

TRACEABILITY
───────────────────────────────────────────────────────────────────
Batch #2026-01-10-001:  5 bottles disposed (organoleptic fail)
Batch #2026-01-12-003:  8 bottles disposed (damaged in storage)

═══════════════════════════════════════════════════════════════════
Report Generated: 2026-01-16 08:00:00
═══════════════════════════════════════════════════════════════════
```

---

## 7. Hybrid Customer Rules

### The Problem
The initial system design assumed customers are either "cash customers" OR "credit customers" exclusively. In reality, businesses often have **flexible payment arrangements**.

### The Hybrid Customer Principle
> **A single customer can have BOTH cash and credit transactions.**
> 
> The system tracks payment mode **per transaction**, NOT per customer.

### Default Payment Mode vs Per-Transaction Override

```
┌─────────────────────────────────────────────────────────────────────────┐
│                      CUSTOMER PAYMENT FLEXIBILITY                        │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  Customer Profile stores:      │  But each transaction can:              │
│  ─────────────────────────────│──────────────────────────────────────   │
│  • Default Payment Mode        │  • Override the default                 │
│  • Credit Limit (if credit)    │  • Use cash OR credit                   │
│  • PO Requirements             │  • Based on order circumstances         │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

### Customer Type Defaults

| Customer Type | Default Payment Mode | Override Scenarios |
|---------------|---------------------|-------------------|
| **Individual Customers** | Cash | Can request credit (requires approval) |
| **Institutional Customers** | Credit (PO-based) | Can pay cash for urgent/small orders |

### Individual Customer Credit Flow

```
┌─────────────────────┐
│  Individual Customer│
│  (Default: Cash)    │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│  Requests Credit    │
│  for this order     │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│  APPROVAL REQUIRED  │◄── Sales Custodian or GM must approve
│  Check credit-      │
│  worthiness         │
└──────────┬──────────┘
           │
     ┌─────┴─────┐
     │           │
     ▼           ▼
┌─────────┐  ┌─────────┐
│ APPROVED│  │ DENIED  │
│ (Credit)│  │ (Cash   │
│         │  │ only)   │
└─────────┘  └─────────┘
```

### Institutional Customer Cash Flow

```
┌─────────────────────────┐
│  Institutional Customer │
│  (Default: Credit/PO)   │
└──────────┬──────────────┘
           │
           ▼
┌─────────────────────────┐
│  Urgent Order OR        │
│  Small Order Amount     │
└──────────┬──────────────┘
           │
           ▼
┌─────────────────────────┐
│  Customer opts for      │
│  CASH PAYMENT           │◄── No approval needed; faster processing
└──────────┬──────────────┘
           │
           ▼
┌─────────────────────────┐
│  Process as Cash Sale   │
│  SI generated, paid now │
└─────────────────────────┘
```

### Key Rules

1. **Customer Profile:** Stores "Default Payment Mode" but this is NOT restrictive
2. **Transaction-Level Tracking:** Each sale records its actual payment mode
3. **Individual → Credit:** Requires explicit approval before proceeding
4. **Institutional → Cash:** No approval needed; simply process as cash
5. **Reporting:** System can report by customer AND by payment mode separately

### Institutional Customer PO Email Intake

Large customer orders start with the customer's own purchase order. The system
keeps the original request as evidence, while Sales enters the order details in
a clear, checked form.

1. The customer emails its order to the company order address. The order may
   be written clearly in the message or supplied as an attached PO document.
2. The system saves the original email and any attached PO and matches the
   sender to a customer.
3. The inbox shows the email as **For Encoding**. It does not automatically
   create product lines from free-form writing or different document layouts.
4. Sales reads the original email or opens the attachment and enters the customer PO number,
   requested delivery date, product, quantity, order unit, customer price when
   shown, and optional remarks.
5. The system checks the entered customer, product, unit, price, and available
   stock. Unclear or missing details stay visible for Sales to resolve.
6. A stock shortage does not silently change the customer's requested quantity.
   Sales may adjust it only after customer approval is recorded. Without that
   approval, the full demand remains and picking stays locked.
7. If the customer agrees by phone to a different quantity, product, unit,
   delivery date, or removal, Sales records what changed, why it changed, the
   person contacted, **Phone call** as the confirmation method, the date/time,
   and optional notes. Sales then saves the agreed order details.
8. The original email and any attachment are never changed. The saved order keeps
   both the original requested values and the final agreed values.
9. Picking and Delivery Receipt creation stay locked until Production finishes
   the required batches, QC releases them, and Warehouse FG receives enough
   stock for the saved order.
10. A repeated email or repeated customer PO must not create a second order.
11. The sender address must match exactly one active customer record. Unknown
   or ambiguous senders are rejected and cannot be manually reassigned by
   Sales. The customer field shown in the inbox is controlled by the sender.
12. An attachment's contents must match its filename type. Renaming an unsafe
   or unrelated file to PDF, Excel, Word, or an image extension does not make
   it an accepted customer PO.
13. Confirmed orders continue through the normal approval, warehouse, delivery,
   invoice, aging, and Cashier collection flow.

The customer does not need to understand internal product codes, base units, or
pack-size columns. Sales selects an active Finished Goods product from the
system and enters the unit shown on the customer's PO. The original customer
file is kept unchanged for checking later.

### Direct Wholesaler and Small Business Orders

The email rule applies to large PO-based customers. It does not prevent a
registered wholesaler or small business from ordering directly through Sales.

1. Supermarkets, feeding programs, and large institutions use the Customer PO
   Inbox.
2. Registered wholesalers and small business customers may order in person or
   by phone through **Direct Order**.
3. Sales selects only released Finished Goods and records box plus loose-unit
   quantities.
4. The system uses the registered customer, official product price, available
   stock, payment terms, and credit limit.
5. The Direct Order is sent to the GM approval queue before Warehouse FG can
   prepare it.
6. Ordinary retail walk-ins are processed by the Cashier through Quick Cash,
   not through the Sales Custodian's Direct Order screen.

---

## 8. Transaction-to-Finance Integration Rules

### The Problem (Bookkeeping Module Removed)
The standalone bookkeeping/accounting module has been removed. However, the Finance Officer still needs visibility into the company's financial position.

### The Solution
> **Finance module receives AUTOMATIC summary updates from operational transactions.**
> 
> No manual data entry needed in Finance module—all updates flow from source transactions.

### Automatic Integration Flows

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    TRANSACTION → FINANCE AUTOMATION                      │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  OPERATIONAL EVENT              │  FINANCE MODULE UPDATE                 │
│  ──────────────────────────────│──────────────────────────────────────  │
│                                                                          │
│  Cashier records Cash Sale      │  → Cash Position INCREASES             │
│                                 │                                        │
│  Sales Custodian creates CSI    │  → Receivable INCREASES                │
│  (Credit Sale)                  │    (New AR entry created)              │
│                                                                          │
│  Cashier collects on credit     │  → Receivable DECREASES                │
│  account (payment received)     │    Cash Position INCREASES             │
│                                                                          │
│  Finance releases payment       │  → Payable DECREASES                   │
│  (to supplier)                  │    Cash Position DECREASES             │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

### Integration Flow Diagram

```
┌─────────────┐        ┌─────────────┐        ┌─────────────┐
│   CASHIER   │        │    SALES    │        │   FINANCE   │
│   MODULE    │        │  CUSTODIAN  │        │   MODULE    │
└──────┬──────┘        └──────┬──────┘        └──────┬──────┘
       │                      │                      │
       │ Cash Sale            │                      │
       ├──────────────────────┼─────────────────────►│ Cash ↑
       │                      │                      │
       │                      │ Credit Sale (CSI)    │
       │                      ├─────────────────────►│ Receivable ↑
       │                      │                      │
       │ Collection (OR)      │                      │
       ├──────────────────────┼─────────────────────►│ Cash ↑
       │                      │                      │ Receivable ↓
       │                      │                      │
       │                      │                      │ Payment Release
       │                      │                      ├────────────────►
       │                      │                      │ Cash ↓
       │                      │                      │ Payable ↓
       │                      │                      │
```

### Finance Dashboard Visibility

| Metric | Source | Auto-Updated When |
|--------|--------|-------------------|
| **Cash Position** | Sum of cash sales + collections - payments released | Any cash transaction |
| **Total Receivables** | Sum of outstanding CSI balances | CSI created or payment received |
| **Total Payables** | Sum of outstanding supplier invoices | PO received or payment released |
| **Daily Sales (Cash)** | Sum of today's SI amounts | Cash sale recorded |
| **Daily Sales (Credit)** | Sum of today's CSI amounts | Credit sale recorded |
| **Collections Today** | Sum of today's OR amounts | Collection recorded |

### Key Rules

1. **Single Source of Truth:** Transactions are recorded ONCE at the operational module
2. **Automatic Propagation:** Finance views are calculated/updated automatically
3. **No Double Entry:** Finance Officer does NOT re-enter operational data
4. **Real-Time Updates:** Finance dashboard reflects current state after each transaction
5. **Audit Trail:** Every financial update can be traced to its source transaction

---

## 9. Document Generation Rules

### Purpose
Ensure proper documentation for all sales transactions with consistent, sequential numbering.

### Document Types by Transaction

```
┌────────────────────────────────────────────────────────────────────────┐
│                    DOCUMENT GENERATION MATRIX                           │
├────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  TRANSACTION TYPE        │  DOCUMENTS GENERATED                        │
│  ───────────────────────│─────────────────────────────────────────    │
│                                                                         │
│  Cash Sale               │  Sales Invoice (SI)                         │
│                          │  ➤ Generated IMMEDIATELY upon payment       │
│                          │                                             │
│  Credit Sale             │  Charge Sales Invoice (CSI)                 │
│                          │  + Delivery Receipt (DR)                    │
│                          │  ➤ Both generated when goods dispatched     │
│                          │                                             │
│  Payment Received        │  Official Receipt (OR)                      │
│  (for credit account)    │  ➤ Generated when collection processed      │
│                                                                         │
└────────────────────────────────────────────────────────────────────────┘
```

### Document Workflows

#### Cash Sale Document Flow
```
┌─────────────────────┐
│  Customer pays cash │
│  at point of sale   │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│  Cashier records    │
│  cash sale          │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│  SYSTEM GENERATES   │
│  ┌────────────────┐ │
│  │ Sales Invoice  │ │
│  │ (SI)           │ │
│  │ SI-2026-00001  │ │
│  └────────────────┘ │
│  Given to customer  │
└─────────────────────┘
```

#### Credit Sale Document Flow
```
┌─────────────────────┐
│  Credit sale        │
│  approved           │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│  Sales Custodian    │
│  processes order    │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────────────────────────┐
│  SYSTEM GENERATES (TOGETHER)            │
│  ┌────────────────┐  ┌────────────────┐ │
│  │ Charge Sales   │  │ Delivery       │ │
│  │ Invoice (CSI)  │  │ Receipt (DR)   │ │
│  │ CSI-2026-00001 │  │ DR-2026-00001  │ │
│  └────────────────┘  └────────────────┘ │
│  DR accompanies goods; CSI for billing  │
└─────────────────────────────────────────┘
```

#### Collection Document Flow
```
┌─────────────────────┐
│  Customer pays on   │
│  credit account     │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│  Cashier processes  │
│  collection         │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│  SYSTEM GENERATES   │
│  ┌────────────────┐ │
│  │ Official       │ │
│  │ Receipt (OR)   │ │
│  │ OR-2026-00001  │ │
│  └────────────────┘ │
│  Given to customer  │
└─────────────────────┘
```

### Sequential Numbering Rules

| Rule | Description |
|------|-------------|
| **No Gaps** | Document numbers must be sequential with NO gaps (1, 2, 3... not 1, 3, 5) |
| **Year-Based Reset** | Numbers reset at start of each year (SI-2026-00001, SI-2027-00001) |
| **Prefix by Type** | Each document type has unique prefix (SI-, CSI-, DR-, OR-) |
| **Zero-Padded** | Numbers are zero-padded for consistent length (00001, not 1) |
| **Auto-Generated** | System generates numbers automatically—users cannot edit |
| **Void Tracking** | If document voided, number is NOT reused; void reason recorded |

### Document Number Format

```
┌────────────────────────────────────────────────────────────────────────┐
│                    DOCUMENT NUMBER FORMAT                               │
├────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│    [PREFIX]-[YEAR]-[SEQUENCE]                                          │
│                                                                         │
│    Examples:                                                            │
│    SI-2026-00001    First Sales Invoice of 2026                        │
│    CSI-2026-00042   42nd Charge Sales Invoice of 2026                  │
│    DR-2026-00042    42nd Delivery Receipt of 2026 (paired with CSI)    │
│    OR-2026-00015    15th Official Receipt of 2026                      │
│                                                                         │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 10. Collection Rules

### The Problem
Credit sales create accounts receivable. There must be a structured process for collecting these receivables and tracking payment status.

### The Collection Principle
> **ALL collections are processed through the Cashier.**
> 
> The Cashier is the only role authorized to receive payments and issue Official Receipts.

### Collection Process Flow

```
┌─────────────────────┐
│  Customer arrives   │
│  to pay credit      │
│  account            │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│  Cashier searches   │
│  by DR NUMBER       │◄── DR number is the primary lookup key
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│  System displays:   │
│  • Customer name    │
│  • CSI details      │
│  • Total amount     │
│  • Amount paid      │
│  • Outstanding bal  │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│  Enter payment      │
│  amount received    │
└──────────┬──────────┘
           │
     ┌─────┴─────┐
     │           │
     ▼           ▼
┌─────────┐  ┌─────────────┐
│ PARTIAL │  │  FULL       │
│ PAYMENT │  │  PAYMENT    │
└────┬────┘  └──────┬──────┘
     │              │
     ▼              ▼
┌─────────────────────────────────────────┐
│  System generates Official Receipt (OR) │
│  Updates account balance                │
└──────────┬──────────────────────────────┘
           │
     ┌─────┴─────┐
     │           │
     ▼           ▼
┌─────────────┐  ┌─────────────┐
│ Balance > 0 │  │ Balance = 0 │
│ Account     │  │ Account     │
│ OPEN        │  │ SETTLED     │
└─────────────┘  └─────────────┘
```

### Partial Payment Handling

```
┌────────────────────────────────────────────────────────────────────────┐
│                     PARTIAL PAYMENT EXAMPLE                             │
├────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  Customer: ABC Restaurant                                               │
│  DR Number: DR-2026-00042                                              │
│  CSI Amount: ₱5,000.00                                                 │
│                                                                         │
│  Payment History:                                                       │
│  ─────────────────────────────────────────────────────────────         │
│  Date        │ OR Number      │ Amount    │ Balance                    │
│  ─────────────────────────────────────────────────────────────         │
│  2026-01-15  │ OR-2026-00020  │ ₱2,000.00 │ ₱3,000.00                  │
│  2026-01-22  │ OR-2026-00035  │ ₱1,500.00 │ ₱1,500.00                  │
│  2026-01-29  │ OR-2026-00048  │ ₱1,500.00 │ ₱0.00 ✓ SETTLED           │
│                                                                         │
└────────────────────────────────────────────────────────────────────────┘
```

### Key Collection Rules

| Rule | Description |
|------|-------------|
| **Cashier Only** | ONLY Cashier can receive payments and issue ORs |
| **DR Lookup** | Collections are searched/linked by Delivery Receipt number |
| **Partial Allowed** | Customer can pay any amount ≤ outstanding balance |
| **Running Balance** | System maintains running balance per DR/CSI |
| **Settlement** | When balance reaches ₱0, status changes to SETTLED |
| **OR per Payment** | Each payment (full or partial) receives its own OR |
| **Cannot Overpay** | System prevents payment amount > outstanding balance |

### Collection Status Tracking

| Status | Condition | Action Required |
|--------|-----------|-----------------|
| **OPEN** | Balance > ₱0 | Follow up for remaining payment |
| **SETTLED** | Balance = ₱0 | No action; account closed |
| **OVERDUE** | Balance > ₱0 AND past due date | Urgent follow-up required |

### Collection Dashboard (Cashier View)

```
═══════════════════════════════════════════════════════════════════
                     COLLECTIONS DASHBOARD
═══════════════════════════════════════════════════════════════════

SUMMARY
───────────────────────────────────────────────────────────────────
Total Open Accounts:            45
Total Outstanding Balance:      ₱125,500.00
Overdue Accounts:               8
Overdue Amount:                 ₱32,000.00

RECENT COLLECTIONS TODAY
───────────────────────────────────────────────────────────────────
Time     │ DR Number      │ Customer          │ Amount     │ Status
───────────────────────────────────────────────────────────────────
08:30    │ DR-2026-00042  │ ABC Restaurant    │ ₱1,500.00  │ SETTLED
09:15    │ DR-2026-00038  │ XYZ Cafe          │ ₱3,000.00  │ Partial
10:00    │ DR-2026-00050  │ DEF Store         │ ₱2,500.00  │ SETTLED

SEARCH COLLECTION
───────────────────────────────────────────────────────────────────
Enter DR Number: [________________] [Search]

═══════════════════════════════════════════════════════════════════
```

---

## Summary: The 10 Critical Operational Rules

| # | Rule | Key Point |
|---|------|-----------|
| 1 | **Pre-Tank QC Rule** | Test milk BEFORE it enters the tank. Never mix untested with tested. |
| 2 | **Threshold/Reorder Rule** | Alert BEFORE stock runs out, accounting for lead time. |
| 3 | **Lead Time Planning** | Understand how long supplies take to arrive and plan accordingly. |
| 4 | **Material Balance Rule** | Raw Materials = Finished Goods + Waste. Nothing can "disappear." |
| 5 | **Disposal Tracking** | All waste MUST be explicitly recorded with reason and authorization. |
| 6 | **Disposal Reporting** | Management needs visibility into waste costs and patterns. |
| 7 | **Hybrid Customer Rule** | Payment mode is per transaction, not per customer. Both cash and credit allowed. |
| 8 | **Finance Integration** | Operational transactions auto-update Finance—no manual double-entry. |
| 9 | **Document Generation** | SI for cash, CSI+DR for credit, OR for collections. Sequential numbering, no gaps. |
| 10 | **Collection Rules** | All collections through Cashier. DR lookup, partial payments allowed, balance tracking. |

---

## Implementation Status

> **Last Updated:** January 21, 2026

### ✅ Completed

| Rule | Feature | Implementation Details |
|------|---------|------------------------|
| **Reorder Point / Threshold Logic** | Database Schema | Added `lead_time_days` column to `ingredients` and `mro_items` tables (`sql/add_reorder_lead_time.sql`) |
| **Reorder Point / Threshold Logic** | API Endpoint | Added `reorder_alerts` action to `api/warehouse/raw/ingredients.php` - returns items below threshold with lead time info |
| **Reorder Point / Threshold Logic** | Reorder Alerts Report | Created `html/warehouse/raw/reorder_alerts.html` - full report page with filtering, status badges, and CSV export |
| **Reorder Point / Threshold Logic** | Dashboard Integration | Updated `html/warehouse/raw/dashboard.html` - added Reorder Alerts link in sidebar and updated Low Stock card link |
| **Pre-Tank QC Rule** | Milk Grading | Already implemented in QC module (`html/qc/milk_grading.html`) - milk tested before storage |
| **Production → QC Workflow** | Batch Creation for QC | Fixed `api/production/runs.php` - when production completes a run, it now creates a `production_batches` record with `qc_status='pending'` so QC can verify in `batch_release.html` |
| **Date Formatting Bug** | UI Fix | Fixed `formatDate()` and `formatDateTime()` functions in `batch_release.html` to handle null/invalid dates (0000-00-00) gracefully |
| **Lead Time Planning** | Supplier Lead Time UI | Added Settings modal in `ingredients.html` to edit lead time, minimum stock, and reorder point per ingredient. API endpoint `update_settings` added to `ingredients.php` |
| **Recipe Editability** | Production Interface | Added editable ingredient quantities in production batch creation. Staff can adjust actual usage vs recipe defaults. |
| **Temperature/Duration Fields** | Production Interface | Added temperature (°C) and duration (mins) fields to production batch form with product-type hints. |
| **Butter Separation Logic** | Production Interface | Added cream output (kg) and skim milk output (L) fields for butter production. Auto-creates skim milk byproduct record. |
| **Cheese State Tracking** | Production Interface | Added cheese state dropdown (cooking→stirring→pressing→resting→molding→weighing) and salted variant checkbox. |
| **Yogurt Inventory Source** | Pasteurized Milk Validation | Created `pasteurized_milk_inventory` table. Yogurt production validates against pasteurized milk (not raw). UI shows FIFO batch availability. API blocks raw milk for yogurt. |
| **Pasteurization Run UI** | Raw → Pasteurized Milk | New `pasteurization.html` page. Convert raw milk to pasteurized. Complete runs add to inventory. Stats dashboard, runs table, create/complete modals. |

### 🔄 In Progress

| Rule | Feature | Status |
|------|---------|--------|
| **Byproduct Tracking** | Byproduct Management | Skim milk auto-recorded for butter; need whey tracking and transfer UI |

### 📋 Pending Implementation

#### Operational Rules (This Document)
| Rule | Feature | Priority | Notes |
|------|---------|----------|-------|
| **Material Balance Rule** | Production Tracking | 🔴 High | Need to ensure Raw Materials = Finished Goods + Waste equation is enforced |
| **Disposal Tracking** | Disposal Module | 🔴 High | Create dedicated `disposals` table, API endpoints, and UI for recording disposed items |
| **Disposal Tracking** | Disposal Report | 🟡 Medium | Create report page showing disposal summary by category, product, supplier |
| **Supplier Tracking** | Digital Traceability | 🟡 Medium | Already tracking supplier per milk batch; need enhanced reporting |

#### Production Requirements (See `production_requirements.md`)
| Feature | Priority | Notes |
|---------|----------|-------|
| **Byproduct Transfer UI** | 🟡 Medium | Transfer byproducts (skim milk, whey) to warehouse inventory |
| **Efficiency Reporting** | 🟢 Low | Compare actual yield vs theoretical yield |

### Files Created/Modified

```
Created (January 2026):
├── sql/add_reorder_lead_time.sql              # Database migration for lead_time_days
├── sql/add_production_enhancements.sql        # Database: temp, duration, cheese_state, butter fields
├── sql/create_pasteurized_milk.sql            # Pasteurized milk inventory table for yogurt
├── html/warehouse/raw/reorder_alerts.html     # Reorder Alerts Report page
├── html/production/pasteurization.html        # Pasteurization Run UI page
├── api/production/pasteurization.php          # Pasteurization API (raw → pasteurized milk)
├── system_context/production_requirements.md  # Detailed production workflows

Modified:
├── api/warehouse/raw/ingredients.php          # Added reorder_alerts and update_settings actions  
├── api/production/runs.php                    # Enhanced: yogurt pasteurized milk validation, temp/duration, butter/cheese
├── api/production/recipes.php                 # Returns ingredients for editable recipe feature
├── js/production/production.service.js        # Added getAvailablePasteurizedMilk() for yogurt
├── html/warehouse/raw/dashboard.html          # Added sidebar link & updated Low Stock card
├── html/warehouse/raw/ingredients.html        # Added Settings modal for lead time editing
├── html/qc/batch_release.html                 # Fixed date formatting functions
├── html/production/batches.html               # Enhanced: editable ingredients, yogurt milk check, butter/cheese UI
├── html/production/dashboard.html             # Added Pasteurization link to sidebar
```

---

**Document Version:** 1.8  
**Created:** January 20, 2026  
**Last Updated:** February 1, 2026  
**Based On:** Client Discussion Recordings (January 2026)
