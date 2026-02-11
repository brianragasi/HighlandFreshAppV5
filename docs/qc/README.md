# QC Module Documentation

This folder contains detailed documentation for each sub-module within the Quality Control (QC) system.

## Module Documentation

| Document | Description | Status |
|----------|-------------|--------|
| [BATCH_RECALL.md](./BATCH_RECALL.md) | Product recall management for contaminated/defective batches | ✅ Implemented |
| [DISPOSAL.md](./DISPOSAL.md) | Write-off management with GM approval workflow | ✅ Implemented |

## Quick Reference

### Who Does What?

| Role | Primary Functions |
|------|-------------------|
| **QC Officer** | Test milk, release batches, initiate disposals/recalls |
| **General Manager** | Approve/reject disposals and recalls |
| **Warehouse Staff** | Execute approved disposals, receive recall returns |
| **Finance** | View loss reports from disposals |

### Module Relationships

```
┌─────────────────────────────────────────────────────────────────┐
│                    QC MODULE ECOSYSTEM                          │
└─────────────────────────────────────────────────────────────────┘

                    ┌──────────────────┐
                    │   MILK RECEIVING │
                    │   (From Farmers) │
                    └────────┬─────────┘
                             │
                    ┌────────▼─────────┐
                    │   MILK GRADING   │──────► REJECTED ──────┐
                    │   (QC Testing)   │                       │
                    └────────┬─────────┘                       │
                             │ ACCEPTED                        │
                    ┌────────▼─────────┐                       │
                    │   RAW INVENTORY  │                       │
                    └────────┬─────────┘                       │
                             │                                 │
                    ┌────────▼─────────┐                       │
                    │   PRODUCTION     │                       │
                    └────────┬─────────┘                       │
                             │                                 │
                    ┌────────▼─────────┐                       │
                    │  BATCH RELEASE   │──────► REJECTED ──────┤
                    │  (QC Inspection) │                       │
                    └────────┬─────────┘                       │
                             │ RELEASED                        │
                    ┌────────▼─────────┐                       │
                    │   FG INVENTORY   │                       │
                    └────────┬─────────┘                       │
                             │                                 │
            ┌────────────────┼────────────────┐                │
            │                │                │                │
    ┌───────▼───────┐ ┌──────▼──────┐ ┌───────▼───────┐        │
    │  DISPATCHED   │ │   EXPIRING  │ │ CONTAMINATION │        │
    │  (To Stores)  │ │ (Transform) │ │   DETECTED    │        │
    └───────┬───────┘ └──────┬──────┘ └───────┬───────┘        │
            │                │                │                │
            │         ┌──────▼──────┐         │                │
            │         │ YOGURT RULE │         │                │
            │         │ (Transform) │         │                │
            │         └──────┬──────┘         │                │
            │                │                │                │
            │    Can't Transform              │                │
            │         ┌──────▼──────┐         │                │
            │         │  DISPOSAL   │◄────────┴────────────────┘
            │         │ (Write-off) │
            │         └─────────────┘
            │
    ┌───────▼───────┐
    │ BATCH RECALL  │ (If contamination found after dispatch)
    │ (Get it back) │
    └───────────────┘
```

## Files by Module

### Disposal Module
- `sql/create_disposals_module.sql`
- `api/qc/disposals.php`
- `js/qc/disposal.service.js`
- `html/qc/disposals.html`

### Batch Recall Module
- `sql/create_batch_recall_module.sql`
- `api/qc/recalls.php`
- `js/qc/recall.service.js`
- `html/qc/recalls.html` (TODO)

## Approval Workflows

Both Disposal and Recall modules share a common approval pattern:

1. **QC Officer initiates** → Status: `pending`
2. **GM reviews** → Status: `approved` or `rejected`
3. **Staff executes** → Status: `completed`

This ensures:
- Financial accountability
- Audit trail
- Fraud prevention
- Proper documentation
