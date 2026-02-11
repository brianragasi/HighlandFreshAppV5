-- Add apt_result column to milk_receiving table
-- This stores the initial APT (Antibiotic Presence Test) result from delivery inspection

ALTER TABLE milk_receiving 
ADD COLUMN apt_result ENUM('positive', 'negative') DEFAULT 'negative' COMMENT 'Antibiotic Presence Test result' 
AFTER visual_notes;

-- Update index if needed
-- No index needed as this is not frequently queried alone
