# Finance Officer - System Context

Based on the discussion, the **Finance Officer** manages all **fund disbursements** and **payment processing** for the company. This includes supplier payments, farmer payouts, and coordination with the Cashier for collections tracking.

> **CRITICAL SCOPE CLARIFICATION:**
> The Finance Officer handles **DISBURSEMENTS**, not **ACCOUNTING**.
> - ✅ Releases funds to suppliers
> - ✅ Processes farmer payouts
> - ✅ Tracks payables (what company owes)
> - ✅ Coordinates with Cashier for collection visibility
> - ❌ Does NOT create journal entries
> - ❌ Does NOT maintain general ledger
> - ❌ Does NOT generate financial statements

---

## 1. Core Responsibilities

### 1.1 Fund Disbursement Management

The Finance Officer is the **only role authorized to release company funds**.

| Activity | Description |
|----------|-------------|
| **Supplier Payments** | Release payment for approved Purchase Orders |
| **Farmer Payouts** | Weekly/bi-monthly payments based on milk quality |
| **Utility & Operations** | Process approved operational expenses |
| **Staggered Payments** | Manage partial payment schedules for large amounts |

### 1.2 Payment Processing Workflow

```
┌─────────────────────┐
│  Purchase Order     │
│  (Approved by GM)   │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│  Goods Received     │
│  (Warehouse confirms)│
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│  FINANCE OFFICER    │
│  Verifies:          │
│  • PO is approved   │
│  • Goods received   │
│  • Invoice matches  │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│  RELEASE PAYMENT    │
│  Record:            │
│  • Payment method   │
│  • Check number     │
│  • Bank details     │
└─────────────────────┘
```

> **Key Principle:** Finance does NOT check the quality of goods (that's Warehouse's job). Finance only verifies the paperwork is complete and approved before releasing funds.

---

## 2. Raw Milk Pricing Structure (ANNEX "B" - Agreed Rates)

### Base Pricing:
| Component | Amount |
|-----------|--------|
| **Base Price per Liter** | ₱25.00 |
| **Production Incentive** | ₱5.00 |
| **Standard Total** | **₱30.00** |

---

## 2. Butter Fat Content Adjustments

The price per liter is adjusted based on the fat percentage measured by the lab:

| Fat % Range | Adjustment | Final Price/Liter |
|-------------|------------|-------------------|
| 1.5 - 1.9% | -₱1.00 | ₱29.00 |
| 2.0 - 2.4% | -₱0.75 | ₱29.25 |
| 2.5 - 2.9% | -₱0.50 | ₱29.50 |
| 3.0 - 3.4% | -₱0.25 | ₱29.75 |
| **3.5 - 4.0%** | **No deduction** | **₱30.00** |
| 4.1 - 4.5% | +₱0.25 | ₱30.25 |
| 4.6 - 5.0% | +₱0.50 | ₱30.50 |
| 5.1 - 5.5% | +₱0.75 | ₱30.75 |
| 5.6 - 6.0% | +₱1.00 | ₱31.00 |
| 6.1 - 6.5% | +₱1.25 | ₱31.25 |
| 6.6 - 7.0% | +₱1.50 | ₱31.50 |
| 7.1 - 7.5% | +₱1.75 | ₱31.75 |
| 7.6 - 8.0% | +₱2.00 | ₱32.00 |
| 8.1 - 8.5% | +₱2.25 | ₱32.25 |

**Note:** Standard milk fat content is **3.5-4.0%**. Below this range results in deductions; above this range earns bonuses.

---

## 3. Titratable Acidity Adjustments

Deductions are applied based on acidity percentage (lactic acid):

| Acidity % | Adjustment | Final Price/Liter |
|-----------|------------|-------------------|
| **0.14 - 0.18%** | **No deduction** | **₱30.00** |
| 0.19% | -₱0.25 | ₱29.75 |
| 0.20% | -₱0.50 | ₱29.50 |
| 0.21% | -₱0.75 | ₱29.25 |
| 0.22% | -₱1.00 | ₱29.00 |
| 0.23% | -₱1.25 | ₱28.75 |
| 0.24% | -₱1.50 | ₱28.50 |
| **0.25% and above** | **REJECTED** | Milk not accepted |

**Note:** Acidity at 0.25% and above will cause milk to clot in the pasteurizer; such milk is rejected.

---

## 4. Sediment Grade Deductions

Deductions are applied based on sediment test results:

| Sediment Grade | Deduction |
|----------------|-----------|
| **Grade 1** | No deduction |
| **Grade 2** | -₱0.50 per liter |
| **Grade 3** | -₱1.00 per liter |

---

## 5. Payment Calculation Formula

### For Each Supplier Delivery:

```
Final Payment = Volume (Liters) × Adjusted Price per Liter

Where:
Adjusted Price = Base Price (₱30.00)
                 + Fat Adjustment
                 - Acidity Deduction
                 - Sediment Deduction
```

### Example Calculation:
| Parameter | Value |
|-----------|-------|
| **Volume** | 100 liters |
| **Fat Content** | 3.8% (no adjustment) |
| **Acidity** | 0.16% (no deduction) |
| **Sediment** | Grade 1 (no deduction) |
| **Adjusted Price** | ₱30.00 |
| **Total Payment** | 100 × ₱30.00 = **₱3,000.00** |

### Example with Adjustments:
| Parameter | Value |
|-----------|-------|
| **Volume** | 100 liters |
| **Fat Content** | 2.8% (-₱0.50) |
| **Acidity** | 0.20% (-₱0.50) |
| **Sediment** | Grade 2 (-₱0.50) |
| **Adjusted Price** | ₱30.00 - ₱0.50 - ₱0.50 - ₱0.50 = **₱28.50** |
| **Total Payment** | 100 × ₱28.50 = **₱2,850.00** |

---

## 6. Data Sources for Payment

The Finance Officer relies on data from:

| Data Point | Source | Frequency |
|------------|--------|-----------|
| **Volume (Liters)** | Warehouse Raw receiving | Daily |
| **Fat Content (%)** | QC Lab / Milkosonic SL50 | Every delivery |
| **Acidity (%)** | QC Lab / Titratable Acidity Test | Every delivery |
| **Sediment Grade** | QC Lab / Visual inspection | Every delivery |
| **APT Result** | QC Lab | Every delivery |

---

## 7. Payment Schedule

### Typical Payment Cycle:
- **Daily Deliveries**: Recorded and graded individually
- **Payment Period**: Every 15 days (bi-monthly)
- **Payment Computation**: Aggregate all deliveries within period
- **Approval**: General Manager reviews before release

---

## 8. Financial Reports

The Finance Officer generates:

| Report | Content | Frequency |
|--------|---------|-----------|
| **Supplier Payment Summary** | Total per supplier for period | Bi-monthly |
| **Quality-Based Adjustments** | Deductions/bonuses breakdown | Per payment cycle |
| **Milk Procurement Cost** | Total raw milk cost | Monthly |
| **Supplier Performance** | Quality trends per supplier | Monthly |

---

## 9. Supplier Categories

### Cooperative Members:
- Priority payment processing
- May receive advances
- Eligible for incentive programs

### Non-Member Suppliers:
- Standard payment terms
- Same quality-based pricing

---

---

## 10. Staggered Payments Management

### The Problem
Large suppliers or institutional purchases may have significant outstanding amounts that cannot be paid in a single transaction.

### Staggered Payment Tracking

```
┌────────────────────────────────────────────────────────────────────────┐
│                     STAGGERED PAYMENT EXAMPLE                           │
├────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  Supplier: ABC Packaging Co.                                           │
│  Total Invoice: ₱3,000,000.00                                          │
│                                                                         │
│  Payment Schedule:                                                      │
│  ─────────────────────────────────────────────────────────────         │
│  Date        │ Amount        │ Balance      │ Status                   │
│  ─────────────────────────────────────────────────────────────         │
│  2026-01-15  │ ₱1,000,000.00 │ ₱2,000,000.00│ Partial paid            │
│  2026-02-15  │ ₱1,000,000.00 │ ₱1,000,000.00│ Partial paid            │
│  2026-03-15  │ ₱1,000,000.00 │ ₱0.00        │ ✓ FULLY PAID            │
│                                                                         │
└────────────────────────────────────────────────────────────────────────┘
```

### UI Requirements
- Dashboard shows **Running Balance** for each supplier
- Visual indicator when balance decreases as payments are made
- Alert when payment is due based on agreed schedule

---

## 11. Coordination with Cashier (Collections Visibility)

### The Question: "Who receives the payment?"
> **Answer:** The **Cashier** receives ALL incoming payments, even for credit accounts.

### Integration Flow

```
┌─────────────┐        ┌─────────────┐        ┌─────────────┐
│   CASHIER   │        │   SYSTEM    │        │   FINANCE   │
│   MODULE    │        │   AUTO      │        │   VIEW      │
└──────┬──────┘        └──────┬──────┘        └──────┬──────┘
       │                      │                      │
       │ 1. Records           │                      │
       │    Collection        │                      │
       │    (OR issued)       │                      │
       ├─────────────────────►│                      │
       │                      │ 2. System updates:   │
       │                      │    • AR decreases    │
       │                      │    • Cash increases  │
       │                      ├─────────────────────►│
       │                      │                      │ 3. Finance sees
       │                      │                      │    updated position
       │                      │                      │
```

### Finance Dashboard Shows
| Metric | Source | Updated When |
|--------|--------|--------------|
| **Total Receivables** | Sum of outstanding CSI | CSI created or payment received |
| **Today's Collections** | Sum of today's OR | Collection recorded by Cashier |
| **Daily Cash Position** | Collections - Disbursements | Any transaction |
| **Overdue Accounts** | AR past due date | Daily calculation |

### Key Rule
> **Finance does NOT receive payments directly.** Finance only has **visibility** into collection data entered by the Cashier.

---

## 12. Non-Cash Payment Recording

### The Problem
Institutional customers often pay via check or bank transfer, not cash. The system must track all payment metadata.

### Required Fields for Non-Cash Payments

| Field | Purpose |
|-------|---------|
| **Payment Mode** | Cash / Check / Bank Transfer |
| **Bank Name** | Which bank issued the check |
| **Check Number** | For check payments |
| **Check Owner** | Name on the check |
| **Check Date** | Date on the check |
| **Maturity Date** | When check can be deposited |

### Check Payment Flow

```
┌─────────────────────┐
│  Customer pays by   │
│  POST-DATED CHECK   │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│  Cashier records:   │
│  • Check Number     │
│  • Bank Name        │
│  • Maturity Date    │
│  • Amount           │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│  OR issued with     │
│  "CHECK RECEIVED"   │
│  notation           │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────────────────────────┐
│  Finance sees:                          │
│  • Collection pending (PDC)             │
│  • Maturity date alert                  │
│  • Deposit reminder when date arrives   │
└─────────────────────────────────────────┘
```

---

## 13. Finance Dashboard

### Dashboard Sections

#### 13.1 Disbursements Overview
| Section | Content |
|---------|---------|
| **Pending Payments** | Suppliers/farmers awaiting payment |
| **Today's Disbursements** | Payments released today |
| **Weekly Farmer Payout** | Total farmer payments this period |
| **Overdue Payables** | Suppliers past payment terms |

#### 13.2 Collections Visibility (Read-Only)
| Section | Content |
|---------|---------|
| **Today's Collections** | Cash + checks received (from Cashier) |
| **Outstanding Receivables** | Total unpaid credit sales |
| **Aging Summary** | 0-30, 31-60, 61-90, 91+ days breakdown |
| **PDC Reminders** | Post-dated checks maturing soon |

#### 13.3 Cash Position
| Section | Content |
|---------|---------|
| **Opening Balance** | Start of day cash |
| **+ Collections** | Money received |
| **- Disbursements** | Money released |
| **= Closing Balance** | End of day position |

---

## 14. Finance Reports

| Report | Content | Frequency |
|--------|---------|-----------|
| **Supplier Payment Summary** | Payments per supplier for period | Per request |
| **Farmer Payout Report** | Individual farmer payments with quality breakdown | Bi-monthly |
| **Disbursement History** | All payments released | Daily/Weekly |
| **Outstanding Payables** | What company owes and when | Weekly |
| **Collections Summary** | What was collected (from Cashier data) | Daily |
| **Aging of Receivables** | Overdue accounts analysis | Weekly |
| **Cash Position Report** | Net cash movement | Daily |

---

## Summary

The **Finance Officer's** primary role in the Highland Fresh system is:

1. **Disbursements:** Release funds for approved purchases and farmer payouts
2. **Payables Management:** Track what the company owes and manage payment schedules
3. **Collections Coordination:** Visibility into receivables (data entered by Cashier)
4. **Cash Position Awareness:** Monitor daily cash flow
5. **Farmer Payouts:** Calculate and process milk supplier payments based on QC data

### What Finance Does NOT Do
- ❌ Receive payments directly (Cashier's job)
- ❌ Create journal entries (use external accounting software)
- ❌ Maintain general ledger (use external accounting software)
- ❌ Generate financial statements (use external accounting software)
- ❌ Check quality of received goods (Warehouse's job)
