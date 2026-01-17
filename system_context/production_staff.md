# Production Staff - System Context

Based on the discussion, the **Production Staff** are the "Engine" of Highland Fresh. Their function is to execute the physical manufacturing process while providing the system with the "Actual" data needed to track inventory consumption and food safety.

---

## 1. Fresh Milk Process Flow Chart

The Production Staff follows this standardized process flow:

```
RAW MILK RECEIVING
        ↓
   [Acceptance Criteria]
   • APT: Negative
   • Titratable Acidity: 0.11 - 0.18%
   • Lactodensimeter: 1.025 and above
        ↓
    CHILLING
   Temperature: 4°C
        ↓
   PRE-HEATING
   Temperature: 65°C
        ↓
    COOLING
   Temperature: 4°C
        ↓
  HOMOGENIZATION
   Pressure: 1000-1500 psi
        ↓
    COOLING
   Temperature: 4°C
        ↓
 PASTEURIZATION (HTST)
   Temperature: 75°C for 15 seconds
        ↓
    COOLING
   Temperature: 4°C
        ↓
    SEALING
   Packaging: 1000ml, 500ml, 200ml
        ↓
    STORING
   Temperature: 4°C
```

---

## 2. Critical Control Points (CCPs)

### Temperature Requirements:
| Stage | Temperature | Duration/Notes |
|-------|-------------|----------------|
| **Chilling** | 4°C | Upon receiving |
| **Pre-heating** | 65°C | Before homogenization |
| **Cooling (all stages)** | 4°C | Between processes |
| **Homogenization** | N/A | Pressure: 1000-1500 psi |
| **Pasteurization (HTST)** | **75°C** | **15 seconds** |
| **Storage** | 4°C | Final storage |

### Important Note:
The pasteurization uses **HTST (High Temperature Short Time)** method:
- **75°C for 15 seconds** (NOT 81°C for 15 minutes)

---

## 3. Execution of Manufacturing Workflows

The Production Staff are responsible for moving the raw milk through specific stages:

### Initial Processing:
- Execute Pasteurization (75°C/15sec HTST)
- Execute Homogenization (1000-1500 psi)
- Execute Cooling phases (maintain 4°C)

### Product Diversification:
Split the raw milk into different "streams" based on the day's plan:

| Product | Key Process |
|---------|-------------|
| **Bottled Milk** | Choco Milk, Melon Milk, Plain variants (1000ml, 500ml, 200ml) |
| **Cheese** | Managing the "Cheese Vat," stirring, 2-hour "Pressing" time |
| **Butter** | 24-hour cream storage, 45-60 minute "Turning" process |
| **Yogurt** | Fermentation process |
| **Milk Bars** | Ice candy style with plastic film |

---

## 4. Ingredient & Recipe Consumption

The Production Staff are the primary "consumers" of raw materials:

### Ingredient Pulling:
Initiate requests for ingredients from the Warehouse:
- Sugar
- Cocoa powder
- Milk powder
- Flavorings (Melon, Strawberry, etc.)
- Salt
- Rennet (for cheese)

### Recipe Variance Logging:
- Log **actual amounts used** vs. Master Recipe
- If a batch requires more/less than template, record the difference
- Ensures inventory accuracy

### Packaging Management:
- Track use of bottles (1000ml, 500ml, 200ml)
- Track caps usage
- Track **Kilos of plastic film** for Milk Bars

---

## 5. Safety & Compliance Logging (CCP Tracking)

First line of defense for food safety by logging critical data points:

| Log Type | Requirement |
|----------|-------------|
| **Temperature Logging** | Record that milk reached **75°C** during pasteurization |
| **Homogenization Pressure** | Record pressure maintained at **1000-1500 psi** |
| **Cooling Verification** | Confirm **4°C** achieved at each cooling stage |
| **Time Tracking** | Input start/end times for all processes |
| **Batch Identification** | Assign Batch ID for traceability |

---

## 6. Yield & Output Reporting

Production Staff provide the "Finished" numbers to the system:

### Quantity Entry (Multi-Unit):
Output must be recorded in the appropriate unit with automatic conversion:

| Product | Primary Unit | Secondary Unit | Conversion |
|---------|--------------|----------------|------------|
| **Bottled Milk** | Bottles | Crates | 1 Crate = 24 Bottles |
| **Milk Bars** | Pieces | Boxes | 1 Box = 50 Pieces |
| **Cheese** | Blocks | Cases | 1 Case = 12 Blocks |
| **Butter** | Packs | Cases | 1 Case = 20 Packs |

### Output Entry UI:
```
Batch #2024-001 Output:
Product: Milk Bar
Quantity: [ 1250 ]  Unit: [ Pieces ▼ ]
System calculates: 25 Boxes + 0 Pieces

Product: Fresh Milk 200ml
Quantity: [ 312 ]  Unit: [ Bottles ▼ ]
System calculates: 13 Crates + 0 Bottles
```

### Example Recording:
- "Expected 300 bottles; Produced 298 bottles"
- System shows: "12 Crates + 10 Bottles (298 total)"

### Byproduct Recording:
| Byproduct | Source | Action |
|-----------|--------|--------|
| **Buttermilk** | Butter production | Move to inventory |
| **Whey** | Cheese production | Move to inventory |
| **Cream** | Separation | Move to inventory |
| **Skim Milk** | Separation | Move to inventory |

---

## 7. Waste Prevention (The "Yogurt Logic")

### Transformation Process:
When QC identifies bottled milk nearing expiry:
1. Production Staff physically take that milk
2. "Less" it from finished goods inventory
3. Use it as raw ingredient for new batch of **Yogurt**

### Documentation:
- Log as "Transformation" (NOT "Waste" or "Spillage")
- Protect company's profit margins

---

## 8. Internal Requisition & "Evidence"

The Production Staff must participate in the formal warehouse flow:

### Requisition Workflow:
1. Cannot simply take ingredients
2. Input a **Digital Requisition**
3. Wait for **General Manager's approval**
4. Receive item from **Warehouse Custodian**

This provides the "Evidence" required for all stock movements.

---

## 9. Packaging Sizes

Standard packaging sizes for bottled milk:
| Size | Volume |
|------|--------|
| Large | 1000ml (1 liter) |
| Medium | 500ml |
| Small | 200ml |

---

## Summary

In the Highland Fresh system, the **Production Staff's function is "Operational Accountability."** They are responsible for turning raw milk into high-value products while meticulously documenting **what** they used, **how long** it took, and **how much** they produced—all following strict temperature and pressure parameters for food safety.
