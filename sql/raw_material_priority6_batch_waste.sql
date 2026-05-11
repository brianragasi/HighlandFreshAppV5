-- Priority 6: Batch, lot, expiry, and waste handling.

ALTER TABLE `ingredients`
  ADD COLUMN IF NOT EXISTS `is_perishable` TINYINT(1) NOT NULL DEFAULT 1 AFTER `shelf_life_days`;

UPDATE `ingredients`
SET `is_perishable` = CASE
  WHEN LOWER(CONCAT(COALESCE(ingredient_name, ''), ' ', COALESCE(storage_requirements, ''))) REGEXP 'bottle|cap|label|ribbon|cellophane|plastic|packaging' THEN 0
  ELSE 1
END;

ALTER TABLE `mro_items`
  ADD COLUMN IF NOT EXISTS `is_perishable` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_critical`;

ALTER TABLE `ingredient_batches`
  ADD COLUMN IF NOT EXISTS `rr_id` INT(11) NULL AFTER `po_id`,
  ADD COLUMN IF NOT EXISTS `supplier_batch_no` VARCHAR(50) NULL AFTER `supplier_id`;

ALTER TABLE `mro_inventory`
  ADD COLUMN IF NOT EXISTS `po_id` INT(11) NULL AFTER `mro_item_id`,
  ADD COLUMN IF NOT EXISTS `rr_id` INT(11) NULL AFTER `po_id`,
  ADD COLUMN IF NOT EXISTS `supplier_id` INT(11) NULL AFTER `supplier_name`,
  ADD COLUMN IF NOT EXISTS `expiry_date` DATE NULL AFTER `received_date`;

ALTER TABLE `receiving_report_items`
  ADD COLUMN IF NOT EXISTS `supplier_lot_number` VARCHAR(50) NULL AFTER `batch_code`;

CREATE TABLE IF NOT EXISTS `raw_material_waste` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `waste_code` VARCHAR(30) NOT NULL,
  `item_type` ENUM('ingredient','mro') NOT NULL,
  `item_id` INT(11) NOT NULL,
  `batch_id` INT(11) DEFAULT NULL,
  `rr_id` INT(11) DEFAULT NULL,
  `po_id` INT(11) DEFAULT NULL,
  `supplier_id` INT(11) DEFAULT NULL,
  `batch_code` VARCHAR(50) DEFAULT NULL,
  `item_name` VARCHAR(255) NOT NULL,
  `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `unit` VARCHAR(30) NOT NULL,
  `unit_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_value` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `reason_category` VARCHAR(50) NOT NULL,
  `reason` TEXT NOT NULL,
  `waste_date` DATE NOT NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'approved',
  `recorded_by` INT(11) NOT NULL,
  `recorded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_by` INT(11) DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_raw_material_waste_code` (`waste_code`),
  KEY `idx_raw_material_waste_item` (`item_type`, `item_id`),
  KEY `idx_raw_material_waste_batch` (`item_type`, `batch_id`),
  KEY `idx_raw_material_waste_date` (`waste_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
