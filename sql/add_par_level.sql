-- ============================================
-- Highland Fresh - Par Level / Max Stock
-- Adds maximum_stock (par level) to ingredients and MRO items.
-- Safe to run multiple times.
-- ============================================

-- Ensure ingredients.reorder_point exists (needed for column ordering)
SET @has_col := (
	SELECT COUNT(*)
	FROM INFORMATION_SCHEMA.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE()
		AND TABLE_NAME = 'ingredients'
		AND COLUMN_NAME = 'reorder_point'
);
SET @sql := IF(
	@has_col = 0,
	'ALTER TABLE `ingredients` ADD COLUMN `reorder_point` DECIMAL(10,2) DEFAULT NULL COMMENT ''Alert threshold (if NULL, uses minimum_stock * 1.5)'' AFTER `minimum_stock`',
	'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add ingredients.maximum_stock if missing
SET @has_col := (
	SELECT COUNT(*)
	FROM INFORMATION_SCHEMA.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE()
		AND TABLE_NAME = 'ingredients'
		AND COLUMN_NAME = 'maximum_stock'
);
SET @sql := IF(
	@has_col = 0,
	'ALTER TABLE `ingredients` ADD COLUMN `maximum_stock` DECIMAL(10,2) DEFAULT NULL COMMENT ''Par level / order-up-to stock'' AFTER `reorder_point`',
	'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ensure mro_items.reorder_point exists (needed for column ordering)
SET @has_col := (
	SELECT COUNT(*)
	FROM INFORMATION_SCHEMA.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE()
		AND TABLE_NAME = 'mro_items'
		AND COLUMN_NAME = 'reorder_point'
);
SET @sql := IF(
	@has_col = 0,
	'ALTER TABLE `mro_items` ADD COLUMN `reorder_point` DECIMAL(10,2) DEFAULT NULL COMMENT ''Alert threshold (if NULL, uses minimum_stock * 1.5)'' AFTER `minimum_stock`',
	'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add mro_items.maximum_stock if missing
SET @has_col := (
	SELECT COUNT(*)
	FROM INFORMATION_SCHEMA.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE()
		AND TABLE_NAME = 'mro_items'
		AND COLUMN_NAME = 'maximum_stock'
);
SET @sql := IF(
	@has_col = 0,
	'ALTER TABLE `mro_items` ADD COLUMN `maximum_stock` DECIMAL(10,2) DEFAULT NULL COMMENT ''Par level / order-up-to stock'' AFTER `reorder_point`',
	'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
