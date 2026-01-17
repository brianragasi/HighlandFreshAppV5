# Bookkeeper - System Context

Based on the discussion, the **Bookkeeper** handles the detailed recording of financial transactions, supplier payment tracking, and maintains the ledger for all milk procurement activities.

---

## 1. Daily Recording Responsibilities

### Raw Milk Delivery Records:
For each supplier delivery, the Bookkeeper records:

| Field | Description |
|-------|-------------|
| **Date** | Delivery date |
| **Supplier ID** | Unique supplier identifier |
| **Supplier Name** | Farmer/cooperative name |
| **Volume (Liters)** | Quantity delivered |
| **Fat %** | From QC lab analysis |
| **Acidity %** | From QC lab analysis |
| **Sediment Grade** | From QC inspection |
| **APT Result** | Pass/Fail |
| **Computed Price/Liter** | Based on quality adjustments |
| **Total Amount** | Volume × Adjusted Price |

---

## 2. Payment Computation Workflow

### Step-by-Step Process:

1. **Receive Quality Data** from QC Lab
2. **Apply Fat Adjustment**:
   - Standard range: 3.5-4.0% (no adjustment)
   - Below standard: Deduct ₱0.25 to ₱1.00
   - Above standard: Add ₱0.25 to ₱2.25

3. **Apply Acidity Deduction**:
   - Standard range: 0.14-0.18% (no deduction)
   - Above standard: Deduct ₱0.25 to ₱1.50

4. **Apply Sediment Deduction**:
   - Grade 1: No deduction
   - Grade 2: -₱0.50/liter
   - Grade 3: -₱1.00/liter

5. **Calculate Final Amount**:
   ```
   Final Amount = Volume × (₱30.00 + Fat Adj - Acidity Ded - Sediment Ded)
   ```

---

## 3. Pricing Reference Table (Quick Reference)

### Base Rate:
| Component | Amount |
|-----------|--------|
| Base Price | ₱25.00 |
| Incentive | ₱5.00 |
| **Standard Total** | **₱30.00** |

### Fat Content Adjustments:
| Fat % | Adjustment |
|-------|------------|
| 1.5-1.9% | -₱1.00 |
| 2.0-2.4% | -₱0.75 |
| 2.5-2.9% | -₱0.50 |
| 3.0-3.4% | -₱0.25 |
| 3.5-4.0% | ₱0.00 |
| 4.1-4.5% | +₱0.25 |
| 4.6-5.0% | +₱0.50 |
| 5.1-5.5% | +₱0.75 |
| 5.6-6.0% | +₱1.00 |
| 6.1-6.5% | +₱1.25 |
| 6.6-7.0% | +₱1.50 |
| 7.1-7.5% | +₱1.75 |
| 7.6-8.0% | +₱2.00 |
| 8.1-8.5% | +₱2.25 |

### Acidity Deductions:
| Acidity % | Deduction |
|-----------|-----------|
| 0.14-0.18% | ₱0.00 |
| 0.19% | -₱0.25 |
| 0.20% | -₱0.50 |
| 0.21% | -₱0.75 |
| 0.22% | -₱1.00 |
| 0.23% | -₱1.25 |
| 0.24% | -₱1.50 |

### Sediment Deductions:
| Grade | Deduction |
|-------|-----------|
| Grade 1 | ₱0.00 |
| Grade 2 | -₱0.50 |
| Grade 3 | -₱1.00 |

---

## 4. Supplier Ledger Management

### Per-Supplier Tracking:
| Field | Purpose |
|-------|---------|
| **Total Deliveries** | Count of deliveries in period |
| **Total Volume** | Sum of all liters delivered |
| **Average Fat %** | Quality indicator |
| **Average Acidity %** | Quality indicator |
| **Total Adjustments** | Sum of all price adjustments |
| **Gross Amount Due** | Total before any advances |
| **Advances Given** | Cash advances already paid |
| **Net Amount Due** | Final payment amount |

---

## 5. Payment Period Management

### Standard Payment Cycle:
| Period | Start | End |
|--------|-------|-----|
| 1st Half | 1st of month | 15th of month |
| 2nd Half | 16th of month | End of month |

### Payment Processing:
1. **Cut-off Date**: Last day of period
2. **Computation Period**: 1-2 days after cut-off
3. **Review**: Finance Officer and GM approval
4. **Release Date**: Within 3-5 days after approval

---

## 6. Sample Ledger Entry

### Individual Delivery Record:
```
Date: January 15, 2025
Supplier: Juan dela Cruz (SUPP-001)
Volume: 50 liters
Fat: 3.2% → Adjustment: -₱0.25
Acidity: 0.17% → Deduction: ₱0.00
Sediment: Grade 1 → Deduction: ₱0.00
Base Price: ₱30.00
Adjusted Price: ₱29.75
Total: 50 × ₱29.75 = ₱1,487.50
```

### Period Summary (January 1-15):
```
Supplier: Juan dela Cruz
Total Deliveries: 10
Total Volume: 520 liters
Gross Amount: ₱15,340.00
Less: Advances: ₱2,000.00
Net Payable: ₱13,340.00
```

---

## 7. Reconciliation Tasks

### Daily Reconciliation:
- Match QC lab reports with delivery records
- Verify volume matches Warehouse Raw receiving

### Period-End Reconciliation:
- Verify all deliveries recorded
- Cross-check with supplier delivery slips
- Confirm all quality adjustments applied correctly

---

## 8. Documentation Requirements

### Required Documents:
| Document | Purpose | Retention |
|----------|---------|-----------|
| **Delivery Receipt** | Proof of delivery | 5 years |
| **QC Lab Report** | Quality test results | 5 years |
| **Payment Voucher** | Payment authorization | 5 years |
| **Supplier Acknowledgment** | Proof of payment | 5 years |

---

## 9. System Integration Points

### Data Entry Sources:
| Data | Source System |
|------|---------------|
| Delivery Volume | Warehouse Raw Module |
| Quality Metrics | QC Module / Lab Results |
| Supplier Info | Supplier Management |
| Payment Release | Finance Module |

### Data Outputs:
| Report | Recipient |
|--------|-----------|
| Supplier Statement | Supplier |
| Payment Summary | Finance Officer |
| Procurement Report | General Manager |

---

## Summary

The **Bookkeeper's** role in Highland Fresh is to maintain accurate financial records for all raw milk transactions. This involves daily recording of deliveries with quality-based price calculations, managing supplier ledgers, processing bi-monthly payment cycles, and ensuring all documentation is complete for audit purposes. The quality-based pricing system requires careful attention to fat content, acidity, and sediment grades to compute accurate payments.
