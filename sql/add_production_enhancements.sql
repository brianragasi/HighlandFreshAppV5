-- ============================================================
-- Highland Fresh - Production Module Enhancement
-- Created: January 21, 2026
-- Purpose: Add new fields for temperature, duration, ingredients, and product-specific data
-- ============================================================

-- Add new columns to production_runs table
ALTER TABLE production_runs 
ADD COLUMN IF NOT EXISTS process_temperature DECIMAL(5,2) DEFAULT NULL COMMENT 'Processing temperature in Celsius',
ADD COLUMN IF NOT EXISTS process_duration_mins INT DEFAULT NULL COMMENT 'Processing duration in minutes',
ADD COLUMN IF NOT EXISTS ingredient_adjustments JSON DEFAULT NULL COMMENT 'JSON of adjusted ingredient quantities',
ADD COLUMN IF NOT EXISTS cream_output_kg DECIMAL(10,3) DEFAULT NULL COMMENT 'Butter: cream output in kg',
ADD COLUMN IF NOT EXISTS skim_milk_output_liters DECIMAL(10,3) DEFAULT NULL COMMENT 'Butter: skim milk byproduct in liters',
ADD COLUMN IF NOT EXISTS cheese_state ENUM('cooking','stirring','pressing','resting','molding','weighing') DEFAULT NULL COMMENT 'Cheese: current production state',
ADD COLUMN IF NOT EXISTS is_salted TINYINT(1) DEFAULT 0 COMMENT 'Cheese/Butter: salted variant flag';

-- Create index for cheese state tracking
CREATE INDEX IF NOT EXISTS idx_cheese_state ON production_runs(cheese_state);

-- Update production_batches to include additional yield info
ALTER TABLE production_batches
ADD COLUMN IF NOT EXISTS theoretical_yield INT DEFAULT NULL COMMENT 'Expected yield from recipe',
ADD COLUMN IF NOT EXISTS efficiency_percent DECIMAL(5,2) DEFAULT NULL COMMENT 'Actual/Theoretical * 100';

-- ============================================================
-- Note: Run this script manually if columns don't exist
-- ============================================================
