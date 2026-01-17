# Finance Officer - System Context

Based on the discussion, the **Finance Officer** manages the financial aspects of milk procurement and supplier payments, using the quality data from the lab to calculate accurate payment rates.

---

## 1. Raw Milk Pricing Structure (ANNEX "B" - Agreed Rates)

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

## Summary

The **Finance Officer's** primary role in the Highland Fresh system is to **accurately calculate supplier payments** based on the quality metrics provided by the QC lab. The pricing structure incentivizes suppliers to deliver high-quality milk (higher fat content, lower acidity, cleaner milk) while protecting the company from paying premium prices for substandard raw materials.
