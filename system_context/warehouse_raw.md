# Warehouse Raw - System Context

Based on the discussion, the **Warehouse Raw** custodian manages the receiving, storage, and issuance of raw milk and production ingredients while maintaining proper temperature control.

---

## 1. Raw Milk Receiving

### Acceptance Criteria (from QC):
Before raw milk enters the warehouse, QC must verify:

| Test | Requirement | Method |
|------|-------------|--------|
| **Alcohol Precipitation Test (APT)** | Negative | Quick screening |
| **Titratable Acidity** | 0.11 - 0.18% | Titration |
| **Specific Gravity** | 1.025 and above | Lactodensimeter/Quevenne |
| **Organoleptic** | Pleasant smell, no visible dirt | Sensory |

### Rejection Criteria:
| Condition | Action |
|-----------|--------|
| APT Positive | Reject immediately |
| Acidity ≥ 0.25% | Reject (will clot in pasteurizer) |
| Specific Gravity < 1.025 | Reject (suspected adulteration) |
| Off-smell/visible dirt | Reject |

---

## 2. Storage Temperature Requirements

### Critical Temperature: 4°C

All raw milk must be stored at **4°C** immediately upon receiving.

| Storage Point | Temperature |
|---------------|-------------|
| **Receiving Area** | Cool immediately to 4°C |
| **Chilling Tank** | Maintain at 4°C |
| **Before Production** | Verify 4°C before release |

### Temperature Logging:
- Record temperature at receiving
- Record chiller temperature twice daily (AM/PM)
- Flag any deviation above 4°C

---

## 3. Receiving Documentation

### For Each Delivery:

| Field | Description |
|-------|-------------|
| **Date/Time** | Exact receiving timestamp |
| **Supplier ID** | Registered supplier code |
| **Supplier Name** | Farmer/cooperative name |
| **Volume (Liters)** | Measured quantity |
| **Container Type** | Milk cans, tanker, etc. |
| **Temperature at Receiving** | Must be cool |
| **QC Clearance** | APT, Acidity, SG results |
| **Received By** | Warehouse staff signature |

---

## 4. Inventory Management

### Raw Milk Tracking:
| Tracking Point | Purpose |
|----------------|---------|
| **Incoming Volume** | Total liters received |
| **Current Stock** | Available for production |
| **Issued to Production** | Released for processing |
| **Losses** | Spillage, spoilage |

### FIFO (First In, First Out):
- Oldest milk processed first
- Track receiving date/time
- Maximum holding: Follow QC guidelines

---

## 5. Issuance to Production

### Requisition Workflow:
1. **Production Staff** submits digital requisition
2. **General Manager** approves (for significant quantities)
3. **Warehouse Raw** releases milk
4. **Record**: Date, Time, Volume, Batch destination

### Release Documentation:
| Field | Required |
|-------|----------|
| Requisition Number | Yes |
| Approved By | Yes |
| Volume Released (Liters) | Yes |
| Temperature at Release | Yes |
| Destination Batch | Yes |
| Released By | Yes |
| Received By (Production) | Yes |

---

## 6. Other Raw Materials Storage

### Production Ingredients:
The Warehouse Raw also stores ingredients for dairy products:

| Category | Items |
|----------|-------|
| **Sweeteners** | Sugar |
| **Powders** | Cocoa powder, Milk powder |
| **Flavorings** | Melon, Strawberry, etc. |
| **Cheese Making** | Rennet, Salt |
| **Cultures** | Yogurt cultures |

### Storage Requirements:
| Type | Condition |
|------|-----------|
| **Dry Goods** | Cool, dry, pest-free |
| **Cultures** | Refrigerated (per supplier spec) |
| **Rennet** | Refrigerated |

---

## 6A. Multi-Unit Inventory for Packaging & Ingredients

### Packaging Materials (Box vs. Piece Tracking):
Packaging supplies are tracked at both bulk and individual unit levels:

| Item | Primary Unit | Secondary Unit | Conversion |
|------|--------------|----------------|------------|
| **Bottles (1000ml)** | Pieces | Cases | 1 Case = 12 Bottles |
| **Bottles (500ml)** | Pieces | Cases | 1 Case = 24 Bottles |
| **Bottles (200ml)** | Pieces | Crates | 1 Crate = 24 Bottles |
| **Caps** | Pieces | Bags | 1 Bag = 500 Caps |
| **Plastic Film** | Kilos | Rolls | 1 Roll = 5 Kilos |
| **Labels** | Pieces | Rolls | 1 Roll = 1000 Labels |

### Requisition Entry (Multi-Unit):
When Production requests packaging materials:
```
Requisition #REQ-2024-001
Item: Bottles 200ml
Quantity: [ 288 ]  Unit: [ Pieces ▼ ]
System shows: 12 Crates

Item: Caps
Quantity: [ 300 ]  Unit: [ Pieces ▼ ]
System shows: 0 Bags + 300 Pieces
```

### Ingredients (Weight vs. Package):
| Ingredient | Primary Unit | Secondary Unit | Notes |
|------------|--------------|----------------|-------|
| **Sugar** | Kilos | Sacks | 1 Sack = 50 Kg |
| **Cocoa Powder** | Kilos | Bags | 1 Bag = 25 Kg |
| **Milk Powder** | Kilos | Bags | 1 Bag = 25 Kg |
| **Rennet** | ml | Bottles | 1 Bottle = 100 ml |
| **Salt** | Kilos | Sacks | 1 Sack = 25 Kg |

### Inventory Display:
System always shows both units:
- **Bottles 200ml:** "15 Crates + 8 Pieces" (368 total)
- **Sugar:** "4 Sacks + 12 Kg" (212 Kg total)
- **Plastic Film:** "3 Rolls + 2.5 Kg" (17.5 Kg total)

---

## 7. Quality Metrics from Lab

### Data Received from QC:
For each milk delivery, the warehouse records the QC results:

| Metric | Standard Range | Notes |
|--------|----------------|-------|
| **Fat %** | 3.5 - 4.0% | Affects supplier payment |
| **Acidity %** | 0.14 - 0.18% | Normal fresh milk |
| **Specific Gravity** | 1.025 - 1.032 | Quevenne lactometer |
| **SNF** | Per analyzer | Milkosonic SL50 |
| **Total Solids** | Per analyzer | Milkosonic SL50 |

---

## 8. Milkosonic SL50 Analyzer Parameters

The QC lab uses the Milkosonic SL50 analyzer (Serial: 035558) which provides:

| Parameter | Unit |
|-----------|------|
| Fat | % |
| SNF (Solids-Not-Fat) | % |
| Density | g/cm³ |
| Lactose | % |
| Salts | % |
| Protein | % |
| Total Solids | % |
| Added Water | % |
| Temperature | °C |
| Freezing Point | °C |

---

## 9. Daily Operations Checklist

### Morning:
- [ ] Check chiller temperature (target: 4°C)
- [ ] Review expected deliveries
- [ ] Verify inventory levels
- [ ] Clean receiving area

### Upon Each Delivery:
- [ ] Receive milk from supplier
- [ ] Coordinate with QC for testing
- [ ] Wait for QC clearance
- [ ] Record volume and metrics
- [ ] Transfer to chilling tank immediately

### Afternoon:
- [ ] Check chiller temperature
- [ ] Process production requisitions
- [ ] Update inventory records
- [ ] Report any issues to supervisor

---

## 10. Reporting

### Daily Reports:
| Report | Content |
|--------|---------|
| **Receiving Summary** | All deliveries with volumes |
| **Inventory Status** | Current stock levels |
| **Temperature Log** | All temperature readings |
| **Issuance Log** | All releases to production |

### Exception Reports:
| Report | Trigger |
|--------|---------|
| **Rejection Report** | Any milk rejected |
| **Temperature Deviation** | Above 4°C recorded |
| **Shortage Report** | Inventory below threshold |

---

## Summary

The **Warehouse Raw** custodian is the gatekeeper for all raw milk entering Highland Fresh. The primary responsibility is ensuring that only QC-approved milk enters storage and that it is maintained at **4°C** at all times. Accurate documentation of volumes, temperatures, and quality metrics is essential for supplier payments, inventory tracking, and production planning.
