-- Highland Fresh - Purchase Orders Schema
-- Migration for PO data from Warehouse Raw

-- =====================================================
-- SUPPLIERS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_code VARCHAR(30) NOT NULL UNIQUE,
    supplier_name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(100),
    phone VARCHAR(30),
    email VARCHAR(100),
    address TEXT,
    payment_terms VARCHAR(50) DEFAULT '30 days',
    is_active TINYINT(1) DEFAULT 1,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =====================================================
-- PURCHASE ORDERS TABLE (Header)
-- =====================================================
CREATE TABLE IF NOT EXISTS purchase_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_number VARCHAR(30) NOT NULL UNIQUE,
    supplier_id INT NOT NULL,
    order_date DATE NOT NULL,
    expected_delivery DATE,
    status ENUM('draft', 'pending', 'approved', 'ordered', 'partial_received', 'received', 'cancelled') DEFAULT 'pending',
    subtotal DECIMAL(12,2) DEFAULT 0.00,
    vat_amount DECIMAL(12,2) DEFAULT 0.00,
    total_amount DECIMAL(12,2) DEFAULT 0.00,
    payment_status ENUM('unpaid', 'partial', 'paid') DEFAULT 'unpaid',
    notes TEXT,
    created_by INT,
    approved_by INT,
    approved_at DATETIME,
    received_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    INDEX idx_po_date (order_date),
    INDEX idx_po_status (status),
    INDEX idx_po_supplier (supplier_id)
);

-- =====================================================
-- PURCHASE ORDER ITEMS TABLE (Line Items)
-- =====================================================
CREATE TABLE IF NOT EXISTS purchase_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT NOT NULL,
    ingredient_id INT,
    item_description VARCHAR(200) NOT NULL,
    quantity DECIMAL(12,2) NOT NULL,
    unit VARCHAR(20) NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL,
    quantity_received DECIMAL(12,2) DEFAULT 0,
    is_vat_item TINYINT(1) DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(id),
    INDEX idx_poi_po (po_id)
);

-- =====================================================
-- INSERT SUPPLIERS
-- =====================================================
INSERT INTO suppliers (supplier_code, supplier_name, notes) VALUES
('SUP-LPC', 'LPC', 'Bottles and Caps supplier'),
('SUP-IANGAO', 'IAN GAO', 'Sugar supplier'),
('SUP-ELIXIR', 'ELIXIR', 'Ribbons, Inks, Solvents, Equipment'),
('SUP-AYACOM', 'AYA COMMERC', 'Alternative sugar supplier'),
('SUP-ANCO', 'ANCO MERCHA', 'Caustic Soda supplier'),
('SUP-KALIN', 'KALINISAN', 'Cleaning chemicals supplier')
ON DUPLICATE KEY UPDATE supplier_name = VALUES(supplier_name);

-- =====================================================
-- INSERT PURCHASE ORDERS AND ITEMS
-- =====================================================

-- PO 5231 - LPC - Jan 04, 2025
INSERT INTO purchase_orders (po_number, supplier_id, order_date, status, subtotal, total_amount)
SELECT '5231', id, '2025-01-04', 'received', 29750.00, 29750.00 FROM suppliers WHERE supplier_code = 'SUP-LPC';

INSERT INTO purchase_order_items (po_id, item_description, quantity, unit, unit_price, total_amount)
SELECT po.id, 'BOTTLES 1000ML', 5950, 'PCS', 4.38, 26061.00 FROM purchase_orders po WHERE po.po_number = '5231'
UNION ALL
SELECT po.id, 'CAPS', 5950, 'PCS', 0.62, 3689.00 FROM purchase_orders po WHERE po.po_number = '5231';

-- PO 5232 - IAN GAO - Jan 07, 2025
INSERT INTO purchase_orders (po_number, supplier_id, order_date, status, subtotal, total_amount)
SELECT '5232', id, '2025-01-07', 'received', 102000.00, 102000.00 FROM suppliers WHERE supplier_code = 'SUP-IANGAO';

INSERT INTO purchase_order_items (po_id, item_description, quantity, unit, unit_price, total_amount)
SELECT po.id, 'WHITE SUGAR', 30, 'SCKS', 3400.00, 102000.00 FROM purchase_orders po WHERE po.po_number = '5232';

-- PO 5233 - LPC - Jan 08, 2025
INSERT INTO purchase_orders (po_number, supplier_id, order_date, status, subtotal, total_amount)
SELECT '5233', id, '2025-01-08', 'received', 59500.00, 59500.00 FROM suppliers WHERE supplier_code = 'SUP-LPC';

INSERT INTO purchase_order_items (po_id, item_description, quantity, unit, unit_price, total_amount)
SELECT po.id, 'BOTTLES 1000ML', 11900, 'PCS', 4.38, 52122.00 FROM purchase_orders po WHERE po.po_number = '5233'
UNION ALL
SELECT po.id, 'CAPS', 11900, 'PCS', 0.62, 7378.00 FROM purchase_orders po WHERE po.po_number = '5233';

-- PO 5234 - IAN GAO - Jan 09, 2025
INSERT INTO purchase_orders (po_number, supplier_id, order_date, status, subtotal, total_amount)
SELECT '5234', id, '2025-01-09', 'received', 83400.00, 83400.00 FROM suppliers WHERE supplier_code = 'SUP-IANGAO';

INSERT INTO purchase_order_items (po_id, item_description, quantity, unit, unit_price, total_amount)
SELECT po.id, 'BROWN SUGAR', 30, 'SCKS', 2780.00, 83400.00 FROM purchase_orders po WHERE po.po_number = '5234';

-- PO 5235 - ELIXIR - Jan 14, 2025
INSERT INTO purchase_orders (po_number, supplier_id, order_date, status, subtotal, vat_amount, total_amount)
SELECT '5235', id, '2025-01-14', 'received', 13600.00, 1632.00, 15232.00 FROM suppliers WHERE supplier_code = 'SUP-ELIXIR';

INSERT INTO purchase_order_items (po_id, item_description, quantity, unit, unit_price, total_amount, is_vat_item)
SELECT po.id, 'RIBBON ROLL', 20, 'ROLL', 680.00, 13600.00, 0 FROM purchase_orders po WHERE po.po_number = '5235'
UNION ALL
SELECT po.id, 'VAT', 1, 'LOT', 1632.00, 1632.00, 1 FROM purchase_orders po WHERE po.po_number = '5235';

-- PO 5236 - LPC - Jan 11, 2025
INSERT INTO purchase_orders (po_number, supplier_id, order_date, status, subtotal, total_amount)
SELECT '5236', id, '2025-01-11', 'received', 29750.00, 29750.00 FROM suppliers WHERE supplier_code = 'SUP-LPC';

INSERT INTO purchase_order_items (po_id, item_description, quantity, unit, unit_price, total_amount)
SELECT po.id, 'BOTTLES 1000ML', 5950, 'PCS', 4.38, 26061.00 FROM purchase_orders po WHERE po.po_number = '5236'
UNION ALL
SELECT po.id, 'CAPS', 5950, 'PCS', 0.62, 3689.00 FROM purchase_orders po WHERE po.po_number = '5236';

-- PO 5237 - IAN GAO - Jan 15, 2025
INSERT INTO purchase_orders (po_number, supplier_id, order_date, status, subtotal, total_amount)
SELECT '5237', id, '2025-01-15', 'received', 105000.00, 105000.00 FROM suppliers WHERE supplier_code = 'SUP-IANGAO';

INSERT INTO purchase_order_items (po_id, item_description, quantity, unit, unit_price, total_amount)
SELECT po.id, 'WHITE SUGAR', 30, 'SCKS', 3500.00, 105000.00 FROM purchase_orders po WHERE po.po_number = '5237';

-- PO 5238 - ELIXIR - Jan 17, 2025
INSERT INTO purchase_orders (po_number, supplier_id, order_date, status, subtotal, total_amount)
SELECT '5238', id, '2025-01-17', 'received', 40388.25, 40388.25 FROM suppliers WHERE supplier_code = 'SUP-ELIXIR';

INSERT INTO purchase_order_items (po_id, item_description, quantity, unit, unit_price, total_amount)
SELECT po.id, 'LINX SOLVENT', 6, 'BOTS', 2315.25, 13891.50 FROM purchase_orders po WHERE po.po_number = '5238'
UNION ALL
SELECT po.id, 'LINX INK', 5, 'BOTS', 5299.35, 26496.75 FROM purchase_orders po WHERE po.po_number = '5238';

-- PO 5239 - LPC - Jan 15, 2025
INSERT INTO purchase_orders (po_number, supplier_id, order_date, status, subtotal, total_amount)
SELECT '5239', id, '2025-01-15', 'received', 59500.00, 59500.00 FROM suppliers WHERE supplier_code = 'SUP-LPC';

INSERT INTO purchase_order_items (po_id, item_description, quantity, unit, unit_price, total_amount)
SELECT po.id, 'BOTTLES 1000ML', 11900, 'PCS', 4.38, 52122.00 FROM purchase_orders po WHERE po.po_number = '5239'
UNION ALL
SELECT po.id, 'CAPS', 11900, 'PCS', 0.62, 7378.00 FROM purchase_orders po WHERE po.po_number = '5239';

-- PO 5240 - ELIXIR - Nov 19, 2024 (Equipment)
INSERT INTO purchase_orders (po_number, supplier_id, order_date, status, subtotal, total_amount)
SELECT '5240', id, '2024-11-19', 'received', 600000.00, 600000.00 FROM suppliers WHERE supplier_code = 'SUP-ELIXIR';

INSERT INTO purchase_order_items (po_id, item_description, quantity, unit, unit_price, total_amount)
SELECT po.id, 'TT500 THERMAL', 5, 'UNIT', 120000.00, 600000.00 FROM purchase_orders po WHERE po.po_number = '5240';

-- PO 5241 - AYA COMMERC - Jan 17, 2025
INSERT INTO purchase_orders (po_number, supplier_id, order_date, status, subtotal, total_amount)
SELECT '5241', id, '2025-01-17', 'received', 28000.00, 28000.00 FROM suppliers WHERE supplier_code = 'SUP-AYACOM';

INSERT INTO purchase_order_items (po_id, item_description, quantity, unit, unit_price, total_amount)
SELECT po.id, 'BROWN SUGAR', 10, 'SCKS', 2800.00, 28000.00 FROM purchase_orders po WHERE po.po_number = '5241';

-- PO 5242 - LPC - Jan 18, 2025
INSERT INTO purchase_orders (po_number, supplier_id, order_date, status, subtotal, total_amount)
SELECT '5242', id, '2025-01-18', 'received', 64796.00, 64796.00 FROM suppliers WHERE supplier_code = 'SUP-LPC';

INSERT INTO purchase_order_items (po_id, item_description, quantity, unit, unit_price, total_amount)
SELECT po.id, 'BOTTLES 1000ML', 5950, 'PCS', 4.38, 26061.00 FROM purchase_orders po WHERE po.po_number = '5242'
UNION ALL
SELECT po.id, 'BOTTLES 500ML', 6570, 'PCS', 2.38, 15636.60 FROM purchase_orders po WHERE po.po_number = '5242'
UNION ALL
SELECT po.id, 'BOTTLES 330ML', 5680, 'PCS', 2.08, 11814.40 FROM purchase_orders po WHERE po.po_number = '5242'
UNION ALL
SELECT po.id, 'CAPS', 18200, 'PCS', 0.62, 11284.00 FROM purchase_orders po WHERE po.po_number = '5242';

-- PO 5243 - LPC - Jan 21, 2025
INSERT INTO purchase_orders (po_number, supplier_id, order_date, status, subtotal, total_amount)
SELECT '5243', id, '2025-01-21', 'received', 49980.00, 49980.00 FROM suppliers WHERE supplier_code = 'SUP-LPC';

INSERT INTO purchase_order_items (po_id, item_description, quantity, unit, unit_price, total_amount)
SELECT po.id, 'BOTTLES 1000ML', 9996, 'PCS', 4.38, 43782.48 FROM purchase_orders po WHERE po.po_number = '5243'
UNION ALL
SELECT po.id, 'CAPS', 9996, 'PCS', 0.62, 6197.52 FROM purchase_orders po WHERE po.po_number = '5243';

-- PO 5244 - LPC - Jan 22, 2025
INSERT INTO purchase_orders (po_number, supplier_id, order_date, status, subtotal, total_amount)
SELECT '5244', id, '2025-01-22', 'received', 17850.00, 17850.00 FROM suppliers WHERE supplier_code = 'SUP-LPC';

INSERT INTO purchase_order_items (po_id, item_description, quantity, unit, unit_price, total_amount)
SELECT po.id, 'BOTTLES 1000ML', 3570, 'PCS', 4.38, 15636.60 FROM purchase_orders po WHERE po.po_number = '5244'
UNION ALL
SELECT po.id, 'CAPS', 3570, 'PCS', 0.62, 2213.40 FROM purchase_orders po WHERE po.po_number = '5244';

-- PO 5245 - ANCO MERCHA - Jan 24, 2025
INSERT INTO purchase_orders (po_number, supplier_id, order_date, status, subtotal, total_amount)
SELECT '5245', id, '2025-01-24', 'received', 56000.00, 56000.00 FROM suppliers WHERE supplier_code = 'SUP-ANCO';

INSERT INTO purchase_order_items (po_id, item_description, quantity, unit, unit_price, total_amount)
SELECT po.id, 'CAUSTIC SODA', 20, 'SCKS', 2800.00, 56000.00 FROM purchase_orders po WHERE po.po_number = '5245';

-- PO 5246 - KALINISAN - Jan 24, 2025
INSERT INTO purchase_orders (po_number, supplier_id, order_date, status, subtotal, total_amount)
SELECT '5246', id, '2025-01-24', 'received', 61000.00, 61000.00 FROM suppliers WHERE supplier_code = 'SUP-KALIN';

INSERT INTO purchase_order_items (po_id, item_description, quantity, unit, unit_price, total_amount)
SELECT po.id, 'CHLORINIX', 10, 'BOXES', 800.00, 8000.00 FROM purchase_orders po WHERE po.po_number = '5246'
UNION ALL
SELECT po.id, 'LINOL-LIQUID D', 10, 'BOXES', 1400.00, 14000.00 FROM purchase_orders po WHERE po.po_number = '5246'
UNION ALL
SELECT po.id, 'ADVACIP 200', 10, 'CAR', 3900.00, 39000.00 FROM purchase_orders po WHERE po.po_number = '5246';

-- PO 5247 - LPC - Jan 24, 2025
INSERT INTO purchase_orders (po_number, supplier_id, order_date, status, subtotal, total_amount)
SELECT '5247', id, '2025-01-24', 'received', 59500.00, 59500.00 FROM suppliers WHERE supplier_code = 'SUP-LPC';

INSERT INTO purchase_order_items (po_id, item_description, quantity, unit, unit_price, total_amount)
SELECT po.id, 'BOTTLES 1000ML', 11900, 'PCS', 4.38, 52122.00 FROM purchase_orders po WHERE po.po_number = '5247'
UNION ALL
SELECT po.id, 'CAPS', 11900, 'PCS', 0.62, 7378.00 FROM purchase_orders po WHERE po.po_number = '5247';

-- PO 5248 - IAN GAO - Jan 24, 2025
INSERT INTO purchase_orders (po_number, supplier_id, order_date, status, subtotal, total_amount)
SELECT '5248', id, '2025-01-24', 'received', 158500.00, 158500.00 FROM suppliers WHERE supplier_code = 'SUP-IANGAO';

INSERT INTO purchase_order_items (po_id, item_description, quantity, unit, unit_price, total_amount)
SELECT po.id, 'BROWN SUGAR', 30, 'SCKS', 2850.00, 85500.00 FROM purchase_orders po WHERE po.po_number = '5248'
UNION ALL
SELECT po.id, 'WHITE SUGAR', 20, 'SCKS', 3650.00, 73000.00 FROM purchase_orders po WHERE po.po_number = '5248';

-- PO 5249 - IAN GAO - Jan 27, 2025
INSERT INTO purchase_orders (po_number, supplier_id, order_date, status, subtotal, total_amount)
SELECT '5249', id, '2025-01-27', 'received', 87000.00, 87000.00 FROM suppliers WHERE supplier_code = 'SUP-IANGAO';

INSERT INTO purchase_order_items (po_id, item_description, quantity, unit, unit_price, total_amount)
SELECT po.id, 'BROWN SUGAR', 30, 'SCKS', 2900.00, 87000.00 FROM purchase_orders po WHERE po.po_number = '5249';

-- PO 5250 - LPC - Jan 29, 2025
INSERT INTO purchase_orders (po_number, supplier_id, order_date, status, subtotal, total_amount)
SELECT '5250', id, '2025-01-29', 'received', 44625.00, 44625.00 FROM suppliers WHERE supplier_code = 'SUP-LPC';

INSERT INTO purchase_order_items (po_id, item_description, quantity, unit, unit_price, total_amount)
SELECT po.id, 'BOTTLES 1000ML', 8925, 'PCS', 4.38, 39091.50 FROM purchase_orders po WHERE po.po_number = '5250'
UNION ALL
SELECT po.id, 'CAPS', 8925, 'PCS', 0.62, 5533.50 FROM purchase_orders po WHERE po.po_number = '5250';

-- PO 5251 - IAN GAO - Jan 31, 2025
INSERT INTO purchase_orders (po_number, supplier_id, order_date, status, subtotal, total_amount)
SELECT '5251', id, '2025-01-31', 'received', 112500.00, 112500.00 FROM suppliers WHERE supplier_code = 'SUP-IANGAO';

INSERT INTO purchase_order_items (po_id, item_description, quantity, unit, unit_price, total_amount)
SELECT po.id, 'WHITE SUGAR', 30, 'SCKS', 3750.00, 112500.00 FROM purchase_orders po WHERE po.po_number = '5251';

-- =====================================================
-- SUMMARY VIEW
-- =====================================================
SELECT 'PURCHASE ORDERS IMPORTED' as Status, COUNT(*) as Count FROM purchase_orders;
SELECT 'PURCHASE ORDER ITEMS IMPORTED' as Status, COUNT(*) as Count FROM purchase_order_items;
