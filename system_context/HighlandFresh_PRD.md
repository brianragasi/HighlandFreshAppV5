This is the **Definitive Comprehensive Product Requirements Document (PRD)**. It is a consolidated "Master Document" that extracts every business rule, user role, and technical detail mentioned in the 40+ minute discussion. 

No detail has been left out—from the $81^\circ C$ pasteurization requirement to the "bolts" in maintenance and the "sub-names" in the feeding program.

---

# Product Requirements Document (PRD): Highland Fresh System
**Project:** Integrated POS, Inventory, QC, and Production Operations System  
**Version:** 6.0 (Revised per Scope Clarification - February 2026)

---

## 1. Executive Summary
Highland Fresh requires a unified digital ecosystem to manage a highly complex dairy production lifecycle. The system must prevent inventory "leakage," ensure $100\%$ food safety compliance, automate complex farmer payouts based on milk quality, and provide accurate operational reports for management decision-making.

### 1.0.1 CRITICAL SCOPE DEFINITION
> **This is an OPERATIONS SYSTEM (Inventory/Production), NOT an Accounting System.**
> 
> The system's job is to **count physical items**, not balance financial ledgers.
> 
> **Explicitly EXCLUDED from Scope:**
> - **Bookkeeping/Accounting System** (use external software like QuickBooks, Xero, or similar)
> - Journal Entries & General Ledger
> - Trial Balance
> - Income Statements
> - Balance Sheets
> - Cash Flow Statements
> - Statement of Equity
> - Payroll Processing
> - Full double-entry accounting
> - Tax computation and filing

### 1.0.2 System Modules (In Scope)
| Module | Primary Users | Core Functions |
|--------|--------------|----------------|
| **Sales** | Cashier, Sales Custodian | POS, Collections, Institutional Sales, Aging Tracking |
| **Warehouse (Raw)** | Warehouse Raw Staff | Ingredients, Packaging, MRO, Raw Milk Storage |
| **Warehouse (Finished Goods)** | Warehouse FG Staff | Chillers, Dispatch, Delivery Receipts, FIFO Enforcement |
| **Production** | Production Staff | Batch Execution, Recipe Consumption, CCP Logging |
| **Quality Control** | QC Officer | Milk Grading, Batch Release, Expiry Management |
| **Purchasing** | Purchaser | Supplier Management, Price Trends, Purchase Orders |
| **Finance (Disbursements)** | Finance Officer | Fund Disbursements, Payment Processing, Payables Tracking |
| **General Manager** | GM | Approvals, Dashboards, Master Recipe Ownership |

### 1.1 Statement of Problems
The current operations at Highland Fresh suffer from:
1.  **Paper-Based Tracking:** Milk receiving logs, batch records, and delivery receipts are maintained on paper forms, causing delays in data availability and frequent transcription errors.
2.  **Excel File Dependency:** Financial reconciliation and inventory tracking rely on disconnected Excel spreadsheets, leading to version control issues and data inconsistencies between departments.
3.  **Delayed Payment Processing:** Manual calculation of farmer payouts from paper logs takes 2-3 days, delaying weekly payments and causing farmer dissatisfaction.
4.  **No Real-Time Visibility:** Management cannot view current inventory levels, sales figures, or production status without physically visiting each department.
5.  **Returns & Damages Untracked:** Returned products, damaged goods, and bad orders are logged inconsistently, making it impossible to identify patterns or trace issues to specific batches.
6.  **Disconnected Sales Channels:** Physical store sales do not reflect in any centralized system until end-of-day manual entry, preventing real-time stock accuracy.
7.  **No Customer Self-Service:** Small retail customers (sari-sari stores) must call or visit to place orders, creating bottlenecks during peak periods.

---

## 2. User Roles & Authority Matrix
| Role | System Permission Level | Critical Accountability |
| :--- | :--- | :--- |
| **General Manager (GM)** | Master Administrator | Final Approval on all spending; Master Recipe Ownership; Strategic Dashboards. |
| **QC Officer** | Safety Gatekeeper | Inbound Milk Grading; Batch Release (Safety Lock); Expiry Management. |
| **Production Staff** | Manufacturing User | Execution of Batches; Temperature/Time Logging; Recipe Consumption. |
| **Warehouse (Raw)** | Inventory Custodian | Storage of Ingredients, Packaging, and MRO (Maintenance) parts. |
| **Warehouse (FG)** | Inventory Custodian | Management of Chillers; Dispatching Finished Goods; DR Generation. |
| **Sales Custodian** | Account Manager | Supermarket/Institutional POs; Credit Aging Management; Feeding Programs. |
| **Cashier** | POS / Collection User | Walk-in Sales; Debt Collection via DR Search; 5 PM Reconciliation. |
| **Purchaser** | Procurement User | Sourcing; Price Trend Monitoring; Purchase Request Slips (PRS). |
| **Finance Officer** | Disbursement Manager | Fund Disbursements; Payment Processing; Payables Tracking; Farmer Payout Execution. |
| **Maintenance Head** | Internal Requester | Spare part requisitions; Machine health logging. |

### 2.0.1 Finance Officer Role Clarification
> **The Finance Officer handles DISBURSEMENTS, not ACCOUNTING.**

**Finance Officer DOES:**
- Process fund disbursements to suppliers
- Execute farmer payout processing
- Track accounts payable (what the company owes)
- Coordinate with Cashier for collections tracking
- Record payment metadata (check numbers, bank details, maturity dates)
- Manage staggered payment schedules for large supplier debts

**Finance Officer does NOT:**
- Create journal entries
- Maintain general ledger
- Generate financial statements (Income Statement, Balance Sheet, etc.)
- Perform bookkeeping/accounting tasks

> **Note:** All accounting functions (journal entries, ledgers, financial statements) are handled in external accounting software (e.g., QuickBooks).

### 2.1 Scope Clarification: External Parties (OUT OF SCOPE)
The following entities are **OUTSIDE the system scope** and do NOT have system UI access:

| External Party | Reason Out of Scope |
|---------------|---------------------|
| **Wholesalers** | Independent resellers - transactions handled via Cashier/Sales Custodian |
| **Retail Customers** | Sari-sari stores - orders handled via phone/in-person through Sales Custodian |
| **Drivers** | Delivery personnel - dispatch managed by Warehouse FG, no driver UI needed |

**How External Parties Are Handled:**
- **Wholesalers & Customers:** Managed as records in the customer database; orders entered by Sales Custodian or Cashier on their behalf.
- **Drivers:** Dispatch information printed on Delivery Receipts; no driver mobile app required.

---

## 3. Module 1: Quality Control (The Gatekeeper)
### 3.1 Inbound Milk Grading
*   **Milk Analyzer Integration:** Record Fat %, Acidity (pH), and Sediment for every farmer delivery.
*   **Pricing Engine:** Automatically calculate price based on Membership status (₱40 vs ₱38) and Quality Incentives/Deductions.
*   **Rejection Protocol:** A "Reject" action must document the reason and block the volume from entering the inventory.

### 3.1.1 The "Milk Tank" Logic (Pre-Tank QC)
> **Critical Rule:** QC must happen BEFORE milk enters the main storage tank.
> 
> **Rationale:** You cannot mix untested milk with good milk. If the new batch is bad, it spoils the entire tank.

*   **Process Flow:**
    1. Supplier arrives with fresh milk
    2. **Test Sample FIRST** (before any tank loading)
    3. If Pass → Load to Tank
    4. If Fail → **Reject immediately** (milk never enters tank)
*   **Perishability Constraint:** Fresh milk has a ~3 hour window during transport before quality risk increases.
*   **Supplier Tracking:** Even though milk physically mixes in the tank, the system **must digitally record** which supplier delivered which batch for traceability.

### 3.2 The "Batch Release" Safety Lock
*   A batch **cannot** be moved to "Finished Goods" or made "Visible" to Sales until QC checks:
    1.  **Pasteurization Verified:** Must confirm log shows $75^\circ C$ for 15 seconds (HTST method).
    2.  **CCP Check:** Confirmation that cooling reached $\le 4^\circ C$ within the safety window.
    3.  **Organoleptic Test:** Manual pass for Taste and Appearance.
*   **Barcode Generation:** Upon release, the system generates a unique barcode containing the Batch ID, Mfg Date, and Expiry Date.

---

## 4. Module 2: Production & "Dairy Logic"
### 4.1 Master Recipe Management
*   **Authority:** Only the GM can define the "Standard Ratios" for products.
*   **BOM Consumption:** Starting a batch must automatically deduct:
    *   **Milk:** From Raw Tanks.
    *   **Ingredients:** Sugar, Powder, Flavors, Rennet, Salt.
    *   **Packaging:** Bottles (pcs), Caps (pcs), or Plastic Film (Kilos).

### 4.2 Product-Specific Workflows
*   **Cheese:** Log Stirring Time, Pressing Time (2 hours), and Storage location.
*   **Butter:** Log Cream separation, 24-hour cold storage, and "Turning" duration (45-60 mins). Record "Buttermilk" as a byproduct.
*   **Yogurt Transformation:** Logic to deduct near-expiry bottled milk from FG and re-add it as a raw ingredient for Yogurt production.

---

## 5. Module 3: Inventory & Warehouse
### 5.1 Sub-Warehouse Segmentation
The system must maintain four distinct, non-overlapping inventory categories:
1.  **Raw Milk Storage:** Only accepts "QC Approved" milk.
2.  **Ingredients & Packaging:** Flavorings, Sugar, Bottles, Caps, Film.
3.  **MRO (Maintenance, Repair, Operations):** Bolts, Spare parts, Machine oil, Tools.
4.  **Finished Goods:** The "Chillers" containing products ready for sale.

### 5.1.1 Differentiating Ingredient Ordering Behavior
The system needs two different ordering behaviors based on ingredient type:

| Ingredient Type | Ordering Logic | Reason |
|-----------------|----------------|--------|
| **Fresh Milk** | Delivered daily or on fixed schedule | High perishability (~3 hours transport window) |
| **Dry Ingredients** (Sugar, Bottles, etc.) | Ordered based on stock levels with reorder alerts | Longer shelf life, requires lead time planning |

### 5.2 Multi-Unit Inventory System (Box vs. Piece)
In the Philippine sari-sari store market, retail sales happen "by piece" (tingi), not by box. The system must support **Multi-Unit Inventory** to ensure accurate tracking.

#### Unit Conversion Configuration
*   **Base Unit Definition:** Each product has a defined conversion (e.g., 1 Box = 24 Pieces).
*   **Product-Specific Ratios:** Different products may have different conversions:
    *   Fresh Milk 200ml: 1 Crate = 24 Bottles
    *   Milk Bar: 1 Box = 50 Pieces
    *   Cheese 250g: 1 Case = 12 Blocks
    *   Butter 250g: 1 Case = 20 Packs

#### Inventory Display & Entry
*   **Dual Display:** Inventory shows both: "8 Boxes and 14 Pieces" or "206 Pieces Total".
*   **Unit Selector UI:** Every quantity input includes:
    ```
    [ Input: 10 ] [ Dropdown: Pieces ▼ / Boxes ]
    ```
*   **Automatic Conversion:** System auto-converts between units for reporting.

#### "Box Opening" Logic for Deductions
*   **Scenario:** Warehouse has 10 full boxes. A Wholesaler orders 1 box + 10 pieces.
*   **System Actions:**
    1.  Deduct 1 full box (9 boxes remain).
    2.  "Open" the next box digitally (8 boxes + 24 pieces).
    3.  Deduct 10 pieces (8 boxes + 14 pieces remain).
*   **Result:** Inventory displays: "8 Boxes and 14 Pieces" or "206 Total Pieces".

#### Pricing at Piece Level
*   **Wholesale Price (Box):** ₱240 per box (24 pieces).
*   **Wholesale Price (Piece):** ₱10 per piece.
*   **Markup Tracking:** Wholesaler sells at ₱12/piece = ₱2 markup per piece.
*   **Sales Reports:** Show exact piece count sold, not approximations.

### 5.3 The "Evidence" Rule (Requisitions)
*   No item leaves the warehouse without a **Digital Requisition Slip**.
*   **Workflow:** Requestor $\rightarrow$ GM Approval $\rightarrow$ Warehouse Release $\rightarrow$ Stock Card Auto-Update.
*   **FIFO Enforcement:** System must force the Warehouse (FG) to pick the earliest expiry dates first.

### 5.4 Reorder Points & Threshold Alerts
> **Critical Correction:** The system must NOT wait until stock hits zero. It must trigger alerts BEFORE stock runs out.

*   **Reorder Point Definition:** Each ingredient/material has a configurable threshold (e.g., "Alert when 5 sacks of sugar remain").
*   **Lead Time Consideration:** Thresholds must account for supplier shipping time (could take days or weeks).
*   **Alert Workflow:**
    1. System detects stock at or below threshold
    2. Alert sent to Purchaser and GM
    3. Purchaser generates Purchase Order
    4. GM approves PO
    5. Supplier delivers
    6. Items "Stocked In" to Raw Materials Inventory

**Example Configuration:**
| Material | Current Stock | Reorder Point | Lead Time | Status |
|----------|---------------|---------------|-----------|--------|
| Sugar | 3 sacks | 5 sacks | 1 week | ⚠️ BELOW THRESHOLD |
| Bottles 200ml | 500 pcs | 200 pcs | 3 days | ✅ OK |
| Caps | 150 pcs | 300 pcs | 5 days | ⚠️ BELOW THRESHOLD |

---

## 6. Module 4: Sales & POS (The Two-Stream UI)
### 6.1 Cashier (Walk-in Stream)
*   **Quick Cash UI:** Optimized for speed; minimal clicks to sell a bottle of milk.
*   **Other Products:** Sale of Alcohol, CMT, and Jelly.
*   **Internal Credit:** Ability to charge staff meals ("Ulam") to an internal employee account.
*   **Search Function:** Global search bar for **DR (Delivery Receipt) Numbers** to pull up unpaid invoices for collection.

### 6.2 Sales Custodian (Institutional Stream)
*   **Feeding Program Logic:** Support for Customer (e.g., DepEd) + Sub-Name/Location (e.g., Specific School).
*   **Charge Sales Invoice (CSI):** Specifically for supermarket deliveries where payment is deferred.
*   **Aging Dashboard:** Automated tracking of debts at 0-30, 31-60, 61-90, and 91+ days.

### 6.3 Wholesaler Sales Channel
*   **Wholesale Pricing:** Products sold to Wholesalers at production/wholesale price (e.g., ₱10 per bottle).
*   **Markup Configuration:** System tracks the expected retail price and markup per product:
    *   Example: Wholesale Price ₱10 → Retail Price ₱12 = ₱2 Markup (20%)
*   **Product-Specific Markups:** Each product can have different markup configurations.
*   **Wholesaler Dashboard:** View of purchase history, current inventory taken, and outstanding balances.

### 6.4 Customer Self-Service Portal
*   **Web-Based Ordering:** Small stores access via browser (mobile-friendly).
*   **Real-Time Stock Display:** Shows available quantities from released batches only.
*   **Order Placement:** Shopping cart with quantity selection and delivery scheduling.
*   **Payment Integration:** Immediate payment reflection upon confirmation.
*   **Order Tracking:** Status updates from "Placed" → "Confirmed" → "Dispatched" → "Delivered".

### 6.5 Real-Time Sales Synchronization
*   **Immediate Updates:** Physical store (POS) transactions must update the central system in real-time.
*   **Inventory Sync:** Stock levels across all channels (POS, Portal, Wholesalers) reflect current availability.
*   **No Batch Processing:** Sales are recorded immediately, not at end-of-day.

---

## 7. Module 5: Returns & Bad Orders Management
### 7.1 Returns Processing
*   **Return Types:**
    1.  **Damaged Goods:** Products damaged during delivery or storage.
    2.  **Expired Products:** Items past expiry date returned from retailers.
    3.  **Quality Issues:** Customer complaints about taste, appearance, or contamination.
    4.  **Wrong Delivery:** Incorrect products delivered to customer.
*   **Return Workflow:** Customer/Wholesaler Report → Sales Verification → Warehouse Receipt → QC Inspection → Disposition.

### 7.2 Batch & Expiry Tracking for Returns
*   **Mandatory Batch Recording:** Every return must capture the original Batch ID.
*   **Expiry Validation:** System checks if return is within or past expiry date.
*   **Traceability:** Returns link back to original DR, Sales Invoice, and Production Batch.
*   **Pattern Analysis:** Dashboard to identify batches with high return rates.

### 7.3 Disposition of Returns
*   **Credit Memo:** Issue credit to customer account for valid returns.
*   **Destruction Log:** Record disposal of expired/contaminated products.
*   **Reprocessing:** Near-expiry returns eligible for Yogurt transformation (per Yogurt Rule).
*   **Inventory Adjustment:** Returns appropriately adjust inventory counts and disposal records.

---

## 8. Module 6: Finance & Disbursements (Payment Management Only)
> **Scope Reminder:** This module handles PAYMENT PROCESSING and DISBURSEMENTS only. 
> 
> **NOT in scope:** Journal entries, general ledger, trial balance, or financial statements. Use external accounting software (QuickBooks, Xero, etc.) for bookkeeping.

### 8.1 Disbursement & Payment Management
*   **Payment Metadata:** Mandatory recording of Bank Name, Check Number, Check Owner, and Maturity Date for all non-cash payments.
*   **Staggered Payments:** Tracking for large supplier debts (e.g., "Paid 1M of 3M; 2M remaining").
*   **Farmer Payouts:** Weekly statements based on QC-accepted liters and grading history.
*   **5 PM Cut-off:** Mandatory daily locking of transactions to ensure reconciliation.

### 8.2 Payables Tracking
*   **Supplier Payables:** Track outstanding amounts owed to suppliers.
*   **Payment Schedule:** Manage due dates and payment terms.
*   **Payment History:** Record of all disbursements made.

### 8.3 Coordination with Cashier
*   **Collections Tracking:** Finance Officer monitors collections reported by Cashier.
*   **Reconciliation Support:** Daily cash position based on sales collections and disbursements.
*   **Outstanding Receivables:** View of unpaid invoices and aging status.

---

## 8A. Module 6A: Disposal & Waste Tracking (The Material Balance Rule)

### 8A.1 The Material Balance Principle
> **Fundamental Rule:** Raw Materials Used **MUST ALWAYS EQUAL** Finished Goods Produced + Waste/Disposal.
>
> If 100 units of raw materials are consumed but only 90 finished goods are produced, the missing 10 **MUST** be recorded in a Disposal Report.

```
[Raw Materials Consumed] = [Finished Goods Produced] + [Waste/Disposed Items]
```

### 8A.2 The "Black Hole" Prevention
*   **Problem:** Without proper tracking, failed products simply "disappear" from the system.
*   **Solution:** The system must **explicitly track** all disposed items.
*   **Financial Implication ("Gansi"):** Disposed items represent money spent on ingredients that generated no revenue. Without a Disposal Report, the company cannot calculate this financial loss.

### 8A.3 Disposal Categories
| Category | Description | Example |
|----------|-------------|---------||
| **QC Rejection (Pre-Production)** | Raw milk failed QC testing | Milk with acidity ≥0.25% |
| **Production Failure** | Batch failed during manufacturing | Pasteurization failed, contamination |
| **Post-Production QC Fail** | Finished goods failed final QC | Organoleptic test failure |
| **Expired Inventory** | Products past expiry date | Near-expiry milk not transformed to yogurt |
| **Damaged Goods** | Physical damage during storage/transport | Broken bottles, crushed packaging |
| **Returns - Disposed** | Customer returns deemed unfit for resale | Quality complaints, contamination |

### 8A.4 Disposal Workflow
1. **Identify:** Item identified for disposal (by QC, Warehouse, or Production)
2. **Document:** Record in Disposal Log with:
   - Date/Time
   - Item description and quantity
   - Batch ID (for traceability)
   - Disposal reason (from categories above)
   - Authorizing officer
3. **Approve:** GM or designated approver confirms disposal
4. **Execute:** Physical disposal performed
5. **Reconcile:** Disposal quantity balances the Material Balance equation

### 8A.5 Disposal Report
The system must generate Disposal/Spoilage Reports showing:
- Total items disposed by category
- Estimated cost of disposed materials
- Trends over time (daily/weekly/monthly)
- Link to original batches/suppliers for root cause analysis

---

## 9. Module 7: Strategic & Maintenance
### 9.1 Purchasing & Price Trends
*   **Trend Monitoring:** Historical tracking of raw material prices (e.g., Sugar ₱8k $\rightarrow$ ₱9k).
*   **Canvassing:** Requirement to log at least 3 supplier prices before a PO is issued.

### 9.2 Maintenance
*   **Part Tracking:** Maintenance Head must request "bolts" and spare parts through the same requisition flow as production.
*   **Machine Logs:** Repair history for the Retort, Homogenizer, and Fill-Seal machines.

---

## 10. Module 8: Markup & Pricing Configuration
### 10.1 Product Pricing Tiers
*   **Production Cost:** Base cost of manufacturing (ingredients + labor + overhead).
*   **Wholesale Price:** Price charged to Wholesalers (production cost + company margin).
*   **Retail Price (Suggested):** Recommended retail price for end consumers.
*   **Institutional Price:** Special pricing for bulk institutional buyers (schools, hospitals).

### 10.2 Markup Configuration
*   **Per-Product Setup:** Each product has configurable markup percentages:
    ```
    Product: Fresh Milk 1L
    Production Cost: ₱8.00
    Wholesale Price: ₱10.00 (25% markup)
    Suggested Retail: ₱12.00 (20% wholesaler markup)
    ```
*   **Markup Tracking:** System records actual selling price vs. suggested to analyze pricing compliance.
*   **Margin Reports:** Dashboard showing margins by product, channel, and wholesaler.

### 10.3 Dynamic Pricing Rules
*   **Volume Discounts:** Automatic price adjustments for large orders.
*   **Near-Expiry Discounts:** Products within 3 days of expiry can have auto-applied discounts.
*   **Seasonal Adjustments:** GM can configure temporary price changes for promotions.

---

## 11. Non-Functional Requirements
*   **Security:** Role-Based Access Control (RBAC). No user sees data outside their module.
*   **Theme:** "Clean" Green and White professional interface.
*   **Integrity:** Period-end "Closing" that locks all previous month transactions.
*   **Hardware:** Integration with POS printers, Barcode Scanners, and Batch Label Printers.
*   **Traceability:** System must link `Farmer` $\rightarrow$ `QC Test` $\rightarrow$ `Batch ID` $\rightarrow$ `DR Number` $\rightarrow$ `Sales Invoice`.

---

## 12. System Reports (Final Report List)

> **Scope Clarity:** These reports allow the owner to verify if the physical stock in the warehouse matches the numbers in the computer. They are OPERATIONAL reports, not accounting statements.

### 12.1 Core Reports
| Report | Purpose | Generated By |
|--------|---------|-------------|
| **Inventory Report** | Current stock levels across all warehouses | Warehouse Raw/FG |
| **Sales Report** | Volume of goods sold by period/product/customer | Sales Custodian/Cashier |
| **Disposal/Spoilage Report** | Volume and cost of goods wasted | QC/Warehouse |
| **Farmer Payment Summary** | Payables based on milk quality and volume | Finance Officer |
| **Reorder Alert Report** | Items at or below threshold levels | Purchaser |

### 12.2 Operational Reports
| Report | Purpose |
|--------|---------|
| **Production Report** | Batches produced, yield vs. expected |
| **QC Report** | Milk grading results, pass/fail rates |
| **Delivery Report** | DRs issued, items dispatched |
| **Returns Report** | Returned items by reason and disposition |
| **Physical vs. System Count** | Variance between actual count and system records |

---

## 13. System Business Rules (The "Laws" of Highland Fresh)
1.  **Rule of One:** One-way flow from receiving to dispatch (FDA Compliance).
2.  **The Rejection Rule:** Rejected milk is never paid for and **never enters the tank**.
3.  **The Pre-Tank QC Rule:** Milk MUST be tested BEFORE entering the storage tank. Untested milk cannot mix with tested milk.
4.  **The Yogurt Rule:** Near-expiry milk must be transformed into Yogurt to prevent financial loss.
5.  **The Approval Rule:** The General Manager is the only person who can approve a Purchase Order or a Warehouse Requisition.
6.  **The 5 PM Rule:** Daily operations stop at 5:00 PM to ensure the "Cashier" and "Finance" numbers match perfectly.
7.  **The Returns Rule:** All returned products must be recorded with Batch ID, reason, and disposition within 24 hours of receipt.
8.  **The Real-Time Rule:** All sales transactions must reflect in the central system immediately—no batch processing or end-of-day uploads.
9.  **The Wholesaler Rule:** Wholesalers are external partners, not employees. Their downstream sales are outside system scope, but their purchases and markups are tracked.
10. **The Piece-Level Rule:** All inventory must be tracked down to the individual piece level. Box-only tracking is prohibited.
11. **The Material Balance Rule:** Raw Materials Used = Finished Goods + Waste. No item can "disappear" from the system.
12. **The Threshold Rule:** System must alert for low stock BEFORE items run out, accounting for supplier lead time.
13. **The Operations Scope Rule:** This system counts physical items and processes payments. It does NOT perform bookkeeping (no journal entries, no general ledger, no financial statements). Accounting is handled in external software.

---

## 14. Explicit Scope Exclusions

The following functions are **explicitly OUT OF SCOPE** for this system and should be handled by dedicated external software:

### 14.1 Bookkeeping & Accounting (Use QuickBooks, Xero, or similar)
| Excluded Function | Reason |
|-------------------|--------|
| Journal Entries | Double-entry bookkeeping not in scope |
| General Ledger | Ledger maintenance is accounting function |
| Trial Balance | Financial reconciliation tool for accountants |
| Chart of Accounts | Accounting configuration |

### 14.2 Financial Statements (Generated in Accounting Software)
| Excluded Report | Description |
|-----------------|-------------|
| Income Statement | Profit & Loss report |
| Balance Sheet | Assets, Liabilities, Equity snapshot |
| Cash Flow Statement | Cash inflows/outflows analysis |
| Statement of Equity | Owner's equity changes |

### 14.3 Payroll & HR
| Excluded Function | Reason |
|-------------------|--------|
| Payroll Processing | Employee salary computation |
| Tax Withholding | Government remittances |
| Benefits Administration | SSS, PhilHealth, Pag-IBIG |
| Leave Management | Employee attendance tracking |

### 14.4 Tax & Compliance Reporting
| Excluded Function | Reason |
|-------------------|--------|
| VAT Computation | Tax filing handled externally |
| BIR Form Generation | Tax authority compliance |
| Annual Tax Returns | Accountant responsibility |

> **Integration Note:** This operations system provides the SOURCE DATA (sales totals, inventory values, disbursements) that can be exported or manually entered into accounting software for bookkeeping purposes.