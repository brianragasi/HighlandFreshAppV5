-- Delivery Returns Table
-- Tracks returned items from delivery runs

CREATE TABLE IF NOT EXISTS `delivery_returns` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `delivery_receipt_id` INT NOT NULL,
    `dr_item_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `batch_id` INT NULL,
    `quantity_returned` DECIMAL(10,2) NOT NULL,
    `return_reason` ENUM(
        'damaged_in_transit',
        'customer_rejection',
        'wrong_order',
        'expired_near_expiry',
        'quality_issue',
        'customer_not_available',
        'wrong_address',
        'other'
    ) NOT NULL,
    `condition` ENUM('resellable', 'damaged', 'expired', 'qc_hold') NOT NULL DEFAULT 'resellable',
    `disposition` ENUM('return_to_inventory', 'dispose', 'qc_review', 'pending') NOT NULL DEFAULT 'pending',
    `notes` TEXT NULL,
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_dr_id` (`delivery_receipt_id`),
    INDEX `idx_product_id` (`product_id`),
    INDEX `idx_return_reason` (`return_reason`),
    INDEX `idx_disposition` (`disposition`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add returns_processed flag to delivery_receipts
ALTER TABLE `delivery_receipts` 
ADD COLUMN IF NOT EXISTS `returns_processed` TINYINT(1) DEFAULT 0 AFTER `delivered_at`,
ADD COLUMN IF NOT EXISTS `returns_processed_at` TIMESTAMP NULL AFTER `returns_processed`,
ADD COLUMN IF NOT EXISTS `returns_processed_by` INT NULL AFTER `returns_processed_at`;
