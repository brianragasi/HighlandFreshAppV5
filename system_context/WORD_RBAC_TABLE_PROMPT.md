# BEST PROMPT FOR WORD (Office 365) - RBAC Table Conversion

This guide provides prompts for converting RBAC (Role-Based Access Control) content into clean table structures that Microsoft Word understands perfectly.

---

## 🎯 Primary Prompt (HTML Table Output)

```
Convert the text below into a structured table suitable for Microsoft Word (Office 365).

Rules:
- Infer columns if they are not explicitly stated
- Use clear RBAC-related column headers
- Each role or module should be ONE ROW
- Keep descriptions concise
- Output the result as an HTML table only (no explanation)

---BEGIN CONTENT---
<<PASTE YOUR RBAC / MODULE DESCRIPTION HERE>>
---END CONTENT---
```

> **Note:** Word will automatically convert the HTML into a real Word table when pasted.

---

## 🔑 Suggested Column Headers for RBAC Modules

If your description is messy or paragraph-based, force these columns for consistency:

| Column | Purpose |
|--------|---------|
| Module Name | The system module or feature |
| Role | User role (Admin, Staff, User, etc.) |
| Permissions | CRUD operations allowed |
| Access Level | Full / Limited / Read / No Access |
| Description | Brief explanation |

---

## 📋 Forced Column Prompt

```
Convert the text below into an HTML table for Microsoft Word (Office 365).

Use these exact columns:
- Module Name
- Role
- Permissions
- Access Level
- Description

Each role-per-module should be a separate row.
Output ONLY the HTML table.

---BEGIN CONTENT---
<<PASTE YOUR TEXT HERE>>
---END CONTENT---
```

---

## 🧩 Example Input

**What you paste:**
```
User Management module allows Admin to create, update, and delete users.
Staff can only view users.
Guest has no access.

Inventory module allows Admin full access.
Staff can add and update items.
```

---

## 🧾 Result in Word (After Paste)

| Module Name | Role | Permissions | Access Level | Description |
|-------------|------|-------------|--------------|-------------|
| User Management | Admin | Create, Update, Delete | Full | Manage all users |
| User Management | Staff | View | Read | Can only view users |
| User Management | Guest | None | No Access | Access denied |
| Inventory | Admin | All | Full | Full inventory control |
| Inventory | Staff | Add, Update | Limited | Manage inventory items |

> Word turns this into a native table instantly.

---

## ⚡ Fastest Manual Alternative (No HTML)

If you're in a hurry:

1. **Ask ChatGPT:**
   ```
   Convert this into a table using tabs between columns and new lines between rows.
   ```

2. **Paste into Word**

3. **Convert to Table:**
   - Highlight text
   - Go to: `Insert` → `Table` → `Convert Text to Table`
   - Separator: **Tabs**

---

## 💡 Pro Tips for School / System Documentation

For RBAC documentation, professors LOVE tables with:

- ✅ Consistent roles (Admin / Staff / User)
- ✅ Clear permissions (CRUD)
- ✅ No paragraphs - just clean data
- ✅ One role-per-module per row

---

## 🛠️ Additional Prompt Variations

### Admin vs Staff vs User Comparison Table
```
Create a comparison table showing what Admin, Staff, and User can do in each module.
Columns: Module | Admin | Staff | User
Use checkmarks (✓) for allowed and X for denied.
Output as HTML table.
```

### Full RBAC Matrix
```
Generate a complete RBAC permission matrix for the following system modules.
Rows = Modules, Columns = Roles
Cell values = Permission level (Full/Read/None)
Output as HTML table.
```

### Permission Legend Table
```
Create a legend table explaining permission levels:
- Full Access
- Read/Write
- Read Only
- No Access
Output as HTML table.
```

---

## 📎 Highland Fresh System - Quick Reference

For the Highland Fresh system, use these standard roles:

| Role | System Level |
|------|--------------|
| General Manager (GM) | Master Administrator |
| QC Officer | Safety Gatekeeper |
| Production Staff | Manufacturing User |
| Warehouse (Raw) | Inventory Custodian |
| Warehouse (FG) | Inventory Custodian |
| Sales Custodian | Account Manager |
| Cashier | POS / Collection User |
| Purchaser | Procurement User |
| Finance Officer | Disbursement Manager |
| Maintenance Head | Internal Requester |

---

*Last Updated: February 2026*
