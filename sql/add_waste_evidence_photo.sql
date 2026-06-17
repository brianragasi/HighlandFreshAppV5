-- ============================================
-- Highland Fresh - Waste / Rejection Photo Evidence
-- Adds evidence_photo_path columns for supervisor review.
-- Safe to run multiple times.
-- ============================================

-- Add raw_material_waste.evidence_photo_path if missing
SET @has_col := (
	SELECT COUNT(*)
	FROM INFORMATION_SCHEMA.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE()
		AND TABLE_NAME = 'raw_material_waste'
		AND COLUMN_NAME = 'evidence_photo_path'
);
SET @sql := IF(
	@has_col = 0,
	'ALTER TABLE `raw_material_waste` ADD COLUMN `evidence_photo_path` VARCHAR(500) DEFAULT NULL AFTER `reason`',
	'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add supplier_rejections.evidence_photo_path if missing
SET @has_col := (
	SELECT COUNT(*)
	FROM INFORMATION_SCHEMA.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE()
		AND TABLE_NAME = 'supplier_rejections'
		AND COLUMN_NAME = 'evidence_photo_path'
);
SET @sql := IF(
	@has_col = 0,
	'ALTER TABLE `supplier_rejections` ADD COLUMN `evidence_photo_path` VARCHAR(500) DEFAULT NULL AFTER `notes`',
	'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
