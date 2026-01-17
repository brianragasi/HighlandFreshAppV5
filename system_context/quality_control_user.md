# Quality Control (QC) Officer - System Context

Based on the comprehensive discussion between "Ma'am" (the General Manager) and the students, the **Quality Control (QC)** function acts as the "Safety and Financial Gatekeeper" of the company. 

Its function is not just to check for spoilage, but to integrate production standards with the financial payout system. Here are the specific functions of QC as discussed:

---

## 1. Inbound Milk Grading (The "Gatekeeper" Function)

The most critical function is the testing of raw milk as it arrives from the farmers using a **Milkosonic SL50 Milk Analyzer**:

### Analyzer Parameters (from receipt printout):
| Parameter | Description |
|-----------|-------------|
| **Fat** | Butter fat content (%) |
| **SNF** | Solids-Not-Fat (%) |
| **Density** | Milk density |
| **Lactose** | Lactose content (%) |
| **Salts** | Salt content (%) |
| **Protein** | Protein content (%) |
| **Total Solids** | Total solid content (%) |
| **Added Water** | Detection of adulteration (%) |
| **Temp. Sample** | Sample temperature |
| **Freez. Point** | Freezing point |

### Milk Acceptance Criteria (from Flow Chart):
| Test | Standard | Action |
|------|----------|--------|
| **APT (Alcohol Precipitation Test)** | Negative | Reject if positive |
| **Titratable Acidity** | 0.11 - 0.18% | Accept if within range |
| **Lactodensimeter (Specific Gravity)** | 1.025 and above | Accept if meets threshold |

---

## 2. Daily Testing Schedule (Milk Plant Standards)

### A. Organoleptic Tests (Everyday)
| Test | Standard | Remarks |
|------|----------|---------|
| Smell | Pleasant | Observe/Note |
| Appearance | No visible dirt | Bulk Samples |
| Taste | Good | Taste the sample |

### B. Physical/Chemical Tests (Everyday)
| Test | Standard | Remarks |
|------|----------|---------|
| Clot-on-boiling Test | No clot | For APT (+) milk only |
| Specific Gravity | 1.025 - 1.032 | Use Quevenne lactometer, no cooling of milk |
| Fat Analysis | 3.5% - 4.0% | Basis of milk payment |
| Acidity (% lactic acid) | 0.15 - 0.18% | All bulk samples |

### C. Hygiene & Sanitation
| Test | Schedule | Remarks |
|------|----------|---------|
| Sanitation Check | Once a month / Spot check | Use of detergents and sanitizers |

---

## 3. Microbiological Analyses Schedule

| Test | Schedule | Standard | Applies To |
|------|----------|----------|------------|
| **Methylene Blue Reduction Test (MBRT)** | Daily | > 3 hours | Raw milk |
| **Total Bacterial Count** | 2x per month | Refer to attached standard | All dairy products; swab/rinse |
| **Coliform Count** | 2x per month | < 10/ml | All dairy products; swab and rinse |
| **Yeasts and Molds Count** | 2x per month | < 10/ml | Cheeses; fermented dairy products |

---

## 4. Titratable Acidity Interpretation

### Formula:
```
Titratable Acidity (% lactic acid) = (N_NaOH × V_NaOH × MW of Lactic acid × 100) / (1000 × Vol. of sample)
                                   = (0.1 × V × 90 × 100) / (1000 × 9)
                                   = V / 10
```

### Interpretation:
| Acidity Range | Interpretation | Action |
|---------------|----------------|--------|
| **0.15 - 0.18%** | Normal fresh cow's milk | Accept |
| **Less than 0.25%** | Can stand pasteurization | Accept (unless other issues) |
| **0.25% and above** | Will clot in pasteurizer | **REJECT** |

---

## 5. Automated Financial Logic (Pricing Adjustments)

QC's data entry directly controls the **Farmer Payouts**. Based on ANNEX "B" Agreed Rates:

### Base Pricing:
- **Base Price/liter:** ₱25.00
- **Production Incentive/liter:** ₱5.00
- **Total Price/liter:** ₱30.00

### 5.1 Butter Fat Content Adjustments:

| Fat Content (%) | Deduction/Addition per Liter | Net Price/Liter |
|-----------------|------------------------------|-----------------|
| 1.5 - 1.9 | -₱1.00 | ₱29.00 |
| 2.0 - 2.4 | -₱0.75 | ₱29.25 |
| 2.5 - 2.9 | -₱0.50 | ₱29.50 |
| 3.0 - 3.4 | -₱0.25 | ₱29.75 |
| **3.5 - 4.0** | **No deduction/addition** | **₱30.00** |
| 4.1 - 4.5 | +₱0.25 | ₱30.25 |
| 4.6 - 5.0 | +₱0.50 | ₱30.50 |
| 5.1 - 5.5 | +₱0.75 | ₱30.75 |
| 5.6 - 6.0 | +₱1.00 | ₱32.00 |
| 6.1 - 6.5 | +₱1.25 | ₱32.25 |
| 6.6 - 7.0 | +₱1.50 | ₱32.50 |
| 7.1 - 7.5 | +₱1.75 | ₱32.75 |
| 7.6 - 8.0 | +₱2.00 | ₱32.00 |
| 8.1 - 8.5 | +₱2.25 | ₱32.25 |

### 5.2 Titratable Acidity Deductions:

| Acidity (%) | Price Deduction/Liter | Net Price/Liter |
|-------------|----------------------|-----------------|
| **0.14 - 0.18** | **No deduction** | **₱30.00** |
| 0.19 | -₱0.25 | ₱29.75 |
| 0.20 | -₱0.50 | ₱29.50 |
| 0.21 | -₱0.75 | ₱29.25 |
| 0.22 | -₱1.00 | ₱29.00 |
| 0.23 | -₱1.25 | ₱28.75 |
| 0.24 | -₱1.50 | ₱28.50 |

### 5.3 Sediment Grades:

| Grade | Deduction |
|-------|-----------|
| **Grade 1** | No deduction |
| **Grade 2** | -₱0.50 per liter |
| **Grade 3** | -₱1.00 per liter |

---

## 6. Production Safety Monitoring (CCP Tracking)

QC is responsible for auditing the **Critical Control Points (CCPs)** in the production module:

- **Pasteurization Verification:** Confirming the milk reached **75°C for 15 seconds** (HTST method)
- **Cooling Verification:** Ensuring milk is cooled to **≤4°C** after processing
- **Time & Temperature Logs:** Monitoring the "Waiting Time" and temperatures during all production phases

---

## 7. Traceability & Expiry Management

QC is responsible for the "Timeline" of the product:
- **Calculate Expiry Dates:** Define shelf life for each product type
- **Barcode Generation:** Ensure each batch is labeled with manufacturing date for traceability
- **Batch ID Assignment:** Every run must have a unique Batch ID linked to source milk

---

## 7A. Batch Release Unit Verification

When releasing a batch to Finished Goods, QC must verify the unit counts are accurate:

### Output Verification:
| Check Point | Verification |
|-------------|--------------|
| **Total Pieces** | Count matches Production's reported output |
| **Box/Case Count** | Full containers properly sealed |
| **Loose Pieces** | Any partial containers accounted for |
| **Packaging Integrity** | All units properly sealed/labeled |

### Unit Conversion Accuracy:
QC verifies Production's unit conversions are correct:
```
Production reports: 1,250 pieces of Milk Bar
QC verifies: 25 Boxes × 50 pcs/box = 1,250 ✓
```

### Batch Release Documentation:
| Field | Required Entry |
|-------|----------------|
| Batch ID | System-generated |
| Product | Product name + variant |
| **Total Pieces** | Exact count |
| **Full Boxes/Cases** | Count of complete containers |
| **Loose Pieces** | Pieces not in full container |
| Mfg Date | Production date |
| Expiry Date | Calculated shelf life |
| QC Officer | Approval signature |

### Why Piece-Level Release Matters:
- **Inventory Accuracy:** Warehouse receives exact piece count, not estimated boxes
- **FIFO Tracking:** Each piece can be traced to specific batch
- **Waste Prevention:** No "hidden" pieces lost in rounding

---

## 8. Inventory Transformation (The "Yogurt Logic")

When **Bottled Milk** is nearing its expiry date:
1. **Safety Verification:** Verify the milk is still safe for reprocessing
2. **Transformation Approval:** Authorize the "Yogurt Transformation" 
3. Move finished milk back to production as raw ingredient for yogurt

---

## 9. Compliance Auditing (The "One-Way" Rule)

QC ensures the facility maintains a **"One-Way Flow"**:
- Receiving → Production → Releasing
- No cross-contamination between raw materials and finished goods
- FDA compliance enforcement

---

## Summary

In the Highland Fresh system, **Quality Control is the bridge between the Farmer and the Customer.** Their function is to ensure that the company only pays for high-quality milk, only produces safe food, and can track every single bottle back to its origin.
