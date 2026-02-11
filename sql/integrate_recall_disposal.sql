-- =====================================================
-- BATCH RECALL & DISPOSAL INTEGRATION UPGRADE
-- Highland Fresh Quality Control System
-- Created: 2026-02-08
-- =====================================================
-- This script updates the recall and disposal modules
-- to fully integrate with existing sales and delivery
-- tracking systems
-- =====================================================

-- =====================================================
-- PART 1: ADD BATCH TRACKING TO DELIVERY ITEMS
-- =====================================================

-- Add batch_id to delivery_items if not exists
ALTER TABLE delivery_items 
ADD COLUMN IF NOT EXISTS batch_id INT NULL AFTER inventory_id,
ADD INDEX IF NOT EXISTS idx_batch_id (batch_id);

-- =====================================================
-- PART 2: CREATE VIEW FOR BATCH DISPATCH TRACKING
-- =====================================================

-- This view shows where each batch was dispatched
DROP VIEW IF EXISTS vw_batch_dispatch_tracking;
CREATE VIEW vw_batch_dispatch_tracking AS
SELECT 
    fgi.batch_id,
    pb.batch_code,
    p.name as product_name,
    p.id as product_id,
    d.id as delivery_id,
    d.dr_number,
    d.customer_id,
    d.customer_name,
    d.customer_type,
    d.delivery_address,
    d.contact_number,
    fc.email as customer_email,
    fc.contact_person,
    di.quantity_dispatched,
    di.quantity_accepted,
    di.quantity_rejected,
    d.status as delivery_status,
    d.dispatched_at,
    d.delivered_at
FROM delivery_items di
JOIN finished_goods_inventory fgi ON di.inventory_id = fgi.id
JOIN production_batches pb ON fgi.batch_id = pb.id
JOIN products p ON fgi.product_id = p.id
JOIN deliveries d ON di.delivery_id = d.id
LEFT JOIN fg_customers fc ON d.customer_id = fc.id
WHERE di.quantity_dispatched > 0;

-- =====================================================
-- PART 3: CREATE FUNCTION TO POPULATE RECALL LOCATIONS
-- =====================================================

DELIMITER //

-- Procedure to populate affected locations for a recall from delivery data
DROP PROCEDURE IF EXISTS sp_populate_recall_locations//
CREATE PROCEDURE sp_populate_recall_locations(IN p_recall_id INT, IN p_batch_id INT)
BEGIN
    -- Insert affected locations from delivery tracking
    INSERT INTO recall_affected_locations 
        (recall_id, location_type, location_id, location_name, location_address,
         contact_person, contact_phone, contact_email, dispatch_date, 
         dispatch_reference, units_dispatched)
    SELECT 
        p_recall_id,
        CASE d.customer_type 
            WHEN 'supermarket' THEN 'store'
            WHEN 'school' THEN 'store'
            WHEN 'feeding_program' THEN 'distributor'
            ELSE 'direct_customer'
        END as location_type,
        d.customer_id,
        d.customer_name,
        d.delivery_address,
        fc.contact_person,
        d.contact_number,
        fc.email,
        DATE(d.dispatched_at),
        d.dr_number,
        SUM(di.quantity_dispatched) as units_dispatched
    FROM delivery_items di
    JOIN deliveries d ON di.delivery_id = d.id
    JOIN finished_goods_inventory fgi ON di.inventory_id = fgi.id
    LEFT JOIN fg_customers fc ON d.customer_id = fc.id
    WHERE fgi.batch_id = p_batch_id
      AND d.status IN ('dispatched', 'in_transit', 'delivered')
      AND di.quantity_dispatched > 0
    GROUP BY d.customer_id, d.customer_name
    ON DUPLICATE KEY UPDATE 
        units_dispatched = units_dispatched + VALUES(units_dispatched);
    
    -- If no delivery records found, add a placeholder
    IF ROW_COUNT() = 0 THEN
        INSERT INTO recall_affected_locations 
            (recall_id, location_type, location_name, units_dispatched, notes)
        VALUES 
            (p_recall_id, 'internal', 'Dispatch Records Unavailable', 
             (SELECT total_dispatched FROM batch_recalls WHERE id = p_recall_id),
             'Manual tracking required - delivery records not linked to batch');
    END IF;
    
    -- Update recall with total from affected locations
    UPDATE batch_recalls 
    SET total_dispatched = (
        SELECT COALESCE(SUM(units_dispatched), 0) 
        FROM recall_affected_locations 
        WHERE recall_id = p_recall_id
    )
    WHERE id = p_recall_id;
END//

-- =====================================================
-- PART 4: TRIGGER TO AUTO-POPULATE BATCH_ID
-- =====================================================

-- Trigger to auto-populate batch_id when delivery item is created
DROP TRIGGER IF EXISTS tr_delivery_items_set_batch_id//
CREATE TRIGGER tr_delivery_items_set_batch_id
BEFORE INSERT ON delivery_items
FOR EACH ROW
BEGIN
    IF NEW.batch_id IS NULL AND NEW.inventory_id IS NOT NULL THEN
        SET NEW.batch_id = (
            SELECT batch_id FROM finished_goods_inventory 
            WHERE id = NEW.inventory_id
        );
    END IF;
END//

DELIMITER ;

-- =====================================================
-- PART 5: DISPOSAL INTEGRATION WITH INVENTORY
-- =====================================================

-- Add recall_id to disposals for linking
ALTER TABLE disposals 
ADD COLUMN IF NOT EXISTS recall_id INT NULL AFTER notes,
ADD INDEX IF NOT EXISTS idx_recall_id (recall_id);

-- =====================================================
-- PART 6: UPDATE EXISTING DELIVERY ITEMS WITH BATCH_ID
-- =====================================================

-- Backfill batch_id for existing delivery items
UPDATE delivery_items di
JOIN finished_goods_inventory fgi ON di.inventory_id = fgi.id
SET di.batch_id = fgi.batch_id
WHERE di.batch_id IS NULL AND fgi.batch_id IS NOT NULL;

-- =====================================================
-- PART 7: VIEW FOR QC DISPOSAL SOURCES
-- =====================================================

-- View to show all possible disposal sources
DROP VIEW IF EXISTS vw_disposal_sources;
CREATE VIEW vw_disposal_sources AS
-- From Finished Goods Inventory (expired, damaged, etc.)
SELECT 
    'finished_goods' as source_type,
    fgi.id as source_id,
    CONCAT('FG-', fgi.id) as source_reference,
    fgi.product_id,
    fgi.product_name,
    fgi.quantity_available as available_quantity,
    'pcs' as unit,
    fgi.unit_price,
    fgi.expiry_date,
    CASE 
        WHEN fgi.expiry_date < CURDATE() THEN 'expired'
        WHEN fgi.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 'near_expiry'
        ELSE 'active'
    END as status
FROM finished_goods_inventory fgi
WHERE fgi.quantity_available > 0

UNION ALL

-- From Raw Milk Inventory
SELECT 
    'raw_milk' as source_type,
    rmi.id as source_id,
    CONCAT('RM-', rmi.id) as source_reference,
    NULL as product_id,
    CONCAT('Raw Milk - ', mt.name) as product_name,
    rmi.quantity as available_quantity,
    'liters' as unit,
    rmi.total_payment / NULLIF(rmi.quantity, 0) as unit_price,
    NULL as expiry_date,
    rmi.status
FROM raw_milk_inventory rmi
LEFT JOIN milk_types mt ON rmi.milk_type_id = mt.id
WHERE rmi.quantity > 0 AND rmi.status = 'stored'

UNION ALL

-- From Milk Receiving (rejected)
SELECT 
    'milk_receiving' as source_type,
    mr.id as source_id,
    CONCAT('MR-', mr.id) as source_reference,
    NULL as product_id,
    CONCAT('Milk Receiving - ', f.farm_name) as product_name,
    mr.quantity as available_quantity,
    'liters' as unit,
    mr.price_per_liter,
    NULL as expiry_date,
    mr.status
FROM milk_receiving mr
LEFT JOIN farmers f ON mr.farmer_id = f.id
WHERE mr.status = 'rejected';

-- =====================================================
-- PART 8: VIEW FOR BATCH RECALL CANDIDATES
-- =====================================================

-- View to show batches that could be recalled
DROP VIEW IF EXISTS vw_recall_candidates;
CREATE VIEW vw_recall_candidates AS
SELECT 
    pb.id as batch_id,
    pb.batch_code,
    p.id as product_id,
    p.name as product_name,
    pb.manufacturing_date,
    pb.expiry_date,
    pb.qc_status,
    pb.actual_yield as total_produced,
    COALESCE(SUM(di.quantity_dispatched), 0) as total_dispatched,
    COALESCE(SUM(fgi.quantity_available), 0) as in_warehouse,
    pb.qc_notes,
    pb.created_at
FROM production_batches pb
JOIN products p ON pb.product_id = p.id
LEFT JOIN finished_goods_inventory fgi ON fgi.batch_id = pb.id
LEFT JOIN delivery_items di ON di.batch_id = pb.id
WHERE pb.qc_status = 'released'
  AND NOT EXISTS (
      SELECT 1 FROM batch_recalls br 
      WHERE br.batch_id = pb.id 
      AND br.status NOT IN ('completed', 'cancelled')
  )
GROUP BY pb.id
ORDER BY pb.manufacturing_date DESC;

-- =====================================================
-- PART 9: STATISTICS VIEWS
-- =====================================================

-- Update disposal summary view
DROP VIEW IF EXISTS vw_disposal_summary;
CREATE VIEW vw_disposal_summary AS
SELECT 
    d.id,
    d.disposal_code,
    d.source_type,
    d.source_reference,
    d.product_name,
    d.quantity,
    d.unit,
    d.total_value,
    d.disposal_category,
    d.disposal_method,
    d.status,
    d.initiated_at,
    d.approved_at,
    d.disposed_at,
    CONCAT(ui.first_name, ' ', ui.last_name) as initiated_by_name,
    CONCAT(ua.first_name, ' ', ua.last_name) as approved_by_name,
    CONCAT(ud.first_name, ' ', ud.last_name) as disposed_by_name,
    br.recall_code,
    br.recall_class
FROM disposals d
LEFT JOIN users ui ON d.initiated_by = ui.id
LEFT JOIN users ua ON d.approved_by = ua.id
LEFT JOIN users ud ON d.disposed_by = ud.id
LEFT JOIN batch_recalls br ON d.recall_id = br.id;

-- =====================================================
-- DONE
-- =====================================================
SELECT 'Batch Recall & Disposal Integration Complete' as status;
