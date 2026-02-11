-- Create yogurt_transformations table for Highland Fresh QC Module
-- Tracks the "Yogurt Rule": near-expiry milk transformed into yogurt

CREATE TABLE IF NOT EXISTS `yogurt_transformations` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `transformation_code` VARCHAR(30) NOT NULL,
    `source_inventory_id` INT(11) DEFAULT NULL COMMENT 'FK to finished_goods_inventory.id',
    `source_quantity` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `source_volume_liters` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `target_product` VARCHAR(100) DEFAULT 'Yogurt',
    `target_recipe_id` INT(11) DEFAULT NULL COMMENT 'FK to master_recipes.id',
    `target_quantity` DECIMAL(10,2) DEFAULT NULL COMMENT 'Actual yield after transformation',
    `production_run_id` INT(11) DEFAULT NULL COMMENT 'FK to production_runs.id',
    `transformation_date` DATE NOT NULL,
    `initiated_by` INT(11) DEFAULT NULL COMMENT 'FK to users.id',
    `approved_by` INT(11) DEFAULT NULL COMMENT 'FK to users.id',
    `approval_datetime` DATETIME DEFAULT NULL,
    `completed_by` INT(11) DEFAULT NULL COMMENT 'FK to users.id',
    `completed_at` DATETIME DEFAULT NULL,
    `safety_verified` TINYINT(1) NOT NULL DEFAULT 0,
    `status` ENUM('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_transformation_code` (`transformation_code`),
    KEY `idx_source_inventory` (`source_inventory_id`),
    KEY `idx_status` (`status`),
    KEY `idx_transformation_date` (`transformation_date`),
    KEY `idx_production_run` (`production_run_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
