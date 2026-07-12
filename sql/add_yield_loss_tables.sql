-- ============================================================
-- Highland Fresh v4 — Production Yield/Loss Module Migration
-- Creates tables for loss tracking, yield calculation,
-- packaging rules, and two-stage packaging estimation.
-- ============================================================

-- 1. production_losses: per-stage loss entries
CREATE TABLE IF NOT EXISTS `production_losses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `production_run_id` INT NOT NULL,
  `stage` ENUM('pasteurization','processing','cooling','packaging') NOT NULL,
  `loss_type` ENUM('evaporation','spillage','sampling','equipment_retention','other') NOT NULL,
  `loss_volume_ml` DECIMAL(10,2) NOT NULL,
  `loss_percentage` DECIMAL(5,2) DEFAULT NULL,
  `recorded_by` INT NOT NULL,
  `recorded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `notes` TEXT DEFAULT NULL,
  FOREIGN KEY (`production_run_id`) REFERENCES `production_runs`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`),
  INDEX `idx_losses_run` (`production_run_id`),
  INDEX `idx_losses_stage` (`production_run_id`, `stage`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. yield_calculations: snapshot per stage
CREATE TABLE IF NOT EXISTS `yield_calculations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `production_run_id` INT NOT NULL,
  `stage` ENUM('pasteurization','processing','cooling','packaging') NOT NULL,
  `input_volume_ml` DECIMAL(10,2) NOT NULL,
  `total_loss_ml` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `byproduct_volume_ml` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `net_yield_ml` DECIMAL(10,2) NOT NULL,
  `yield_efficiency_percent` DECIMAL(5,2) NOT NULL,
  `calculated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`production_run_id`) REFERENCES `production_runs`(`id`) ON DELETE CASCADE,
  INDEX `idx_yield_run` (`production_run_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. packaging_rules: preset product → packaging size mappings
CREATE TABLE IF NOT EXISTS `packaging_rules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `packaging_size_ml` INT NOT NULL,
  `packaging_label` VARCHAR(50) NOT NULL,
  `priority_order` INT NOT NULL DEFAULT 1,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  INDEX `idx_rules_product` (`product_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. packaging_estimates: initial + revised estimates per run
CREATE TABLE IF NOT EXISTS `packaging_estimates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `production_run_id` INT NOT NULL,
  `estimate_type` ENUM('initial','revised') NOT NULL,
  `basis_volume_ml` DECIMAL(10,2) NOT NULL COMMENT 'Volume used for this estimate',
  `packaging_size_ml` INT NOT NULL,
  `packaging_label` VARCHAR(50) NOT NULL,
  `estimated_units` INT NOT NULL,
  `actual_units` INT DEFAULT NULL,
  `remainder_ml` DECIMAL(10,2) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`production_run_id`) REFERENCES `production_runs`(`id`) ON DELETE CASCADE,
  INDEX `idx_estimates_run` (`production_run_id`, `estimate_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5. Add yield/loss columns to production_runs
ALTER TABLE `production_runs`
  ADD COLUMN `initial_volume_ml` DECIMAL(10,2) DEFAULT NULL AFTER `planned_quantity`,
  ADD COLUMN `total_loss_ml` DECIMAL(10,2) DEFAULT 0 AFTER `initial_volume_ml`,
  ADD COLUMN `total_byproduct_ml` DECIMAL(10,2) DEFAULT 0 AFTER `total_loss_ml`,
  ADD COLUMN `net_yield_ml` DECIMAL(10,2) DEFAULT NULL AFTER `total_byproduct_ml`,
  ADD COLUMN `material_reconciled` TINYINT(1) DEFAULT 0 AFTER `net_yield_ml`,
  ADD COLUMN `reconciliation_notes` TEXT DEFAULT NULL AFTER `material_reconciled`;

-- 6. Seed packaging_rules from existing products
INSERT INTO `packaging_rules` (`product_id`, `packaging_size_ml`, `packaging_label`, `priority_order`) VALUES
-- Fresh Milk 1L (product 1) — unit_size 1000ml
(1, 1000, '1L Bottle', 1),
(1, 500, '500mL Bottle', 2),
-- Fresh Milk 500ml (product 2) — unit_size 500ml
(2, 500, '500mL Bottle', 1),
(2, 200, '200mL Pouch', 2),
-- Chocolate Milk 1L (product 3)
(3, 1000, '1L Bottle', 1),
(3, 500, '500mL Bottle', 2),
-- Plain Yogurt 500g (product 4) — unit_size 500g
(4, 500, '500mL Cup', 1),
(4, 200, '200mL Cup', 2),
(4, 1000, '1L Tub', 3),
-- Strawberry Yogurt 150g (product 5)
(5, 150, '150mL Cup', 1),
(5, 500, '500mL Cup', 2),
-- Kesong Puti 250g (product 6)
(6, 250, '250g Pack', 1),
-- Butter 250g (product 7)
(7, 250, '250g Block', 1),
-- Fresh Cream 1L (product 8)
(8, 1000, '1L Bottle', 1),
(8, 500, '500mL Bottle', 2);
