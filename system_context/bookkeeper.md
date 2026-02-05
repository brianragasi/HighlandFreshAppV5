# Bookkeeper - DEPRECATED (OUT OF SCOPE)

> ⚠️ **IMPORTANT: This module has been REMOVED from the Highland Fresh System scope.**

---

## Scope Decision (February 2026)

After comprehensive review with the advisor, it was determined that **bookkeeping and accounting functions are OUT OF SCOPE** for this operations system.

### Rationale

The Highland Fresh System is an **OPERATIONS SYSTEM**, not an Accounting System. Its primary purpose is to:
- Count physical items (inventory)
- Track production processes
- Process payments and disbursements
- Generate operational reports

Bookkeeping functions (journal entries, ledgers, trial balance, financial statements) require specialized accounting software and professional accountants.

---

## Recommended External Tools

For bookkeeping and accounting needs, Highland Fresh should use dedicated accounting software such as:

| Software | Purpose |
|----------|---------|
| **QuickBooks** | Full accounting suite with journal entries, financial statements |
| **Xero** | Cloud-based accounting with multi-user access |
| **MYOB** | Accounting and payroll management |
| **Wave** | Free accounting software for small businesses |

---

## What the Operations System Provides

The Highland Fresh system provides **SOURCE DATA** that can be exported or manually entered into external accounting software:

| Data Available | Purpose for Accounting |
|----------------|------------------------|
| **Daily Sales Totals** | Revenue entries |
| **Collections Report** | Cash receipts journal input |
| **Disbursement Records** | Cash disbursements journal input |
| **Inventory Counts** | Asset valuation |
| **Farmer Payout Summary** | Expense entries |
| **Purchase Records** | Cost of goods purchased |

---

## Historical Reference

The content below is kept for historical reference only. These functions are NOT implemented in the system.

<details>
<summary>Click to view deprecated bookkeeper specifications</summary>

### Farmer Payout Pricing Reference (Now in Finance Officer Module)

The farmer payout calculations based on milk quality have been moved to the **Finance Officer** module, which handles:
- Milk pricing based on fat %, acidity, and sediment grades
- Bi-monthly payment computation
- Supplier payment processing

See [finance_officer.md](finance_officer.md) for current payment processing specifications.

</details>

---

## Summary

| Status | Out of Scope |
|--------|--------------|
| **Module** | Bookkeeper |
| **Reason** | Accounting functions handled externally |
| **Alternative** | QuickBooks, Xero, or similar accounting software |
| **Related Module** | Finance Officer (handles disbursements only) |
