-- Records the largest verified volume that Production may make in one run.
-- Older recipes safely fall back to their standard yield until this value is
-- confirmed from the actual cooking vessel.

ALTER TABLE master_recipes
    ADD COLUMN IF NOT EXISTS max_batch_liters DECIMAL(10,2) NULL AFTER bulk_yield_liters;

-- Do not invent a plant capacity. Leaving legacy values NULL makes the
-- application use one standard recipe batch as the temporary safe limit.
