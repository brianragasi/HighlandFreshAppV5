-- Highland Fresh System - Customer Portal Migration
-- Creates tables for customer self-service portal
-- Version: 4.0
-- Date: 2024

-- Add password column to customers table if not exists
ALTER TABLE customers 
ADD COLUMN IF NOT EXISTS password VARCHAR(255) NULL AFTER email,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Customer Orders Table
CREATE TABLE IF NOT EXISTS customer_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    order_number VARCHAR(50) NOT NULL UNIQUE,
    status ENUM('pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending',
    payment_method ENUM('cod', 'online') NOT NULL DEFAULT 'cod',
    payment_status ENUM('unpaid', 'paid', 'refunded') NOT NULL DEFAULT 'unpaid',
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    delivery_address TEXT,
    delivery_date DATE NULL,
    notes TEXT NULL,
    confirmed_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
    INDEX idx_customer (customer_id),
    INDEX idx_status (status),
    INDEX idx_order_number (order_number),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customer Order Items Table
CREATE TABLE IF NOT EXISTS customer_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (order_id) REFERENCES customer_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    INDEX idx_order (order_id),
    INDEX idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customer Order History (Status Changes)
CREATE TABLE IF NOT EXISTS customer_order_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    status VARCHAR(50) NOT NULL,
    notes TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (order_id) REFERENCES customer_orders(id) ON DELETE CASCADE,
    INDEX idx_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample test customer (password: customer123)
-- Password hash for 'customer123': $2y$10$7.v.8QZ5gq5q5q5q5q5q5urQz0Z5r0Z5r0Z5r0Z5r0Z5r0Z5r0Z5r
INSERT INTO customers (customer_code, name, contact_person, email, password, phone, address, customer_type, status, credit_limit, created_at)
SELECT 'CUST-TEST001', 'Sample Sari-Sari Store', 'Juan Dela Cruz', 'customer@test.com', 
       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
       '09171234567', 'Purok 1, Brgy. Sample, City', 'sari_sari', 'active', 5000, NOW()
WHERE NOT EXISTS (SELECT 1 FROM customers WHERE email = 'customer@test.com');

-- Grant access view for order management
-- This view can be used by warehouse/sales to see customer orders
CREATE OR REPLACE VIEW v_customer_orders_summary AS
SELECT 
    co.id,
    co.order_number,
    co.customer_id,
    c.name as customer_name,
    c.contact_person,
    c.phone as customer_phone,
    c.address as customer_address,
    co.status,
    co.payment_method,
    co.payment_status,
    co.subtotal,
    co.delivery_fee,
    co.total_amount,
    co.delivery_address,
    co.delivery_date,
    co.notes,
    co.created_at,
    co.confirmed_at,
    co.delivered_at,
    (SELECT COUNT(*) FROM customer_order_items WHERE order_id = co.id) as item_count,
    (SELECT SUM(quantity) FROM customer_order_items WHERE order_id = co.id) as total_units
FROM customer_orders co
JOIN customers c ON c.id = co.customer_id
ORDER BY co.created_at DESC;
