-- Adds supplier pack-size metadata to ingredients so the production requisition
-- flow can auto-compute "0.11 L of cultures = 2 packets (1 packet = 100 mL)"
-- without forcing the user to do the math by hand.
--
-- All three columns are NULL-able. Existing ingredients get NULL = no pack size
-- configured = current behavior. Backfill can happen via UPDATE per ingredient
-- once the cataloguer fills in the supplier's pack format.

ALTER TABLE `ingredients`
  ADD COLUMN IF NOT EXISTS `pack_size_value` DECIMAL(10,3) NULL
    COMMENT 'Numeric size of one supplier pack, in the same unit family as unit_of_measure'
    AFTER `unit_of_measure`,
  ADD COLUMN IF NOT EXISTS `pack_size_unit` VARCHAR(20) NULL
    COMMENT 'Unit of pack_size_value (kg/g/L/ml/pcs/pack/bottle). Should match unit_of_measure family.'
    AFTER `pack_size_value`,
  ADD COLUMN IF NOT EXISTS `pack_label` VARCHAR(50) NULL
    COMMENT 'Display label, e.g. "100 mL packet", "1 L bottle", "5 kg sack"'
    AFTER `pack_size_unit`;

-- Same migration for older MySQL that doesn't support IF NOT EXISTS on ADD COLUMN.
-- Wrapped in a stored-procedure-style conditional so it is a no-op if already applied.
DROP PROCEDURE IF EXISTS `_add_ingredient_pack_size_columns`;
DELIMITER $$
CREATE PROCEDURE `_add_ingredient_pack_size_columns`()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE()
                     AND TABLE_NAME = 'ingredients'
                     AND COLUMN_NAME = 'pack_size_value') THEN
        ALTER TABLE `ingredients`
            ADD COLUMN `pack_size_value` DECIMAL(10,3) NULL
                COMMENT 'Numeric size of one supplier pack, in the same unit family as unit_of_measure'
                AFTER `unit_of_measure`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE()
                     AND TABLE_NAME = 'ingredients'
                     AND COLUMN_NAME = 'pack_size_unit') THEN
        ALTER TABLE `ingredients`
            ADD COLUMN `pack_size_unit` VARCHAR(20) NULL
                COMMENT 'Unit of pack_size_value (kg/g/L/ml/pcs/pack/bottle). Should match unit_of_measure family.'
                AFTER `pack_size_value`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE()
                     AND TABLE_NAME = 'ingredients'
                     AND COLUMN_NAME = 'pack_label') THEN
        ALTER TABLE `ingredients`
            ADD COLUMN `pack_label` VARCHAR(50) NULL
                COMMENT 'Display label, e.g. "100 mL packet", "1 L bottle", "5 kg sack"'
                AFTER `pack_size_unit`;
    END IF;
END$$
DELIMITER ;

CALL `_add_ingredient_pack_size_columns`();
DROP PROCEDURE `_add_ingredient_pack_size_columns`;
