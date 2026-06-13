-- Farmer payout tracking for accepted milk deliveries.
-- Each accepted receiving can appear in farmer_payment_receipts once, which keeps
-- paid deliveries out of the unpaid farmer payout summary.

CREATE TABLE IF NOT EXISTS `farmer_payments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `payment_code` VARCHAR(30) NOT NULL,
  `farmer_id` INT(11) NOT NULL,
  `covered_from` DATE DEFAULT NULL,
  `covered_to` DATE DEFAULT NULL,
  `delivery_count` INT(11) NOT NULL DEFAULT 0,
  `total_liters` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `gross_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `amount_paid` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `payment_date` DATE NOT NULL,
  `payment_method` VARCHAR(50) DEFAULT NULL,
  `reference_number` VARCHAR(100) DEFAULT NULL,
  `remarks` TEXT DEFAULT NULL,
  `created_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_farmer_payments_code` (`payment_code`),
  KEY `idx_farmer_payments_farmer` (`farmer_id`),
  KEY `idx_farmer_payments_date` (`payment_date`),
  CONSTRAINT `fk_farmer_payments_farmer` FOREIGN KEY (`farmer_id`) REFERENCES `farmers`(`id`),
  CONSTRAINT `fk_farmer_payments_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `farmer_payment_receipts` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `farmer_payment_id` INT(11) NOT NULL,
  `receiving_id` INT(11) NOT NULL,
  `qc_test_id` INT(11) NOT NULL,
  `amount_paid` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_farmer_payment_receipt_receiving` (`receiving_id`),
  UNIQUE KEY `uk_farmer_payment_receipt_qc_test` (`qc_test_id`),
  KEY `idx_farmer_payment_receipts_payment` (`farmer_payment_id`),
  CONSTRAINT `fk_farmer_payment_receipts_payment` FOREIGN KEY (`farmer_payment_id`) REFERENCES `farmer_payments`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_farmer_payment_receipts_receiving` FOREIGN KEY (`receiving_id`) REFERENCES `milk_receiving`(`id`),
  CONSTRAINT `fk_farmer_payment_receipts_qc` FOREIGN KEY (`qc_test_id`) REFERENCES `qc_milk_tests`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
