-- Phase 1 Migration: Finance & Ledger
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Expenses Table
CREATE TABLE IF NOT EXISTS `expenses` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `category`     VARCHAR(100) NOT NULL, -- e.g. Rent, Electricity, Salary
  `amount`       DECIMAL(10,2) NOT NULL,
  `expense_date` DATE NOT NULL,
  `notes`        TEXT,
  `user_id`      INT UNSIGNED,
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Supplier Ledger (Baki with Dealers)
CREATE TABLE IF NOT EXISTS `supplier_ledger` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `dealer_id`   INT UNSIGNED NOT NULL,
  `po_id`       INT UNSIGNED,
  `type`        ENUM('debit','credit') NOT NULL, -- credit = we owe dealer, debit = we paid dealer
  `amount`      DECIMAL(10,2) NOT NULL,
  `description` VARCHAR(255),
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`dealer_id`) REFERENCES `dealers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`po_id`)     REFERENCES `purchase_orders`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Supplier Payments (Payments to Dealers)
CREATE TABLE IF NOT EXISTS `supplier_payments` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `dealer_id`   INT UNSIGNED NOT NULL,
  `amount`      DECIMAL(10,2) NOT NULL,
  `method`      ENUM('cash','card','mobile','bank') DEFAULT 'cash',
  `notes`       TEXT,
  `user_id`     INT UNSIGNED,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`dealer_id`) REFERENCES `dealers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Adding balance to dealers table if not exists
-- (Checks manually or just run, but safer to check)
ALTER TABLE `dealers` ADD COLUMN `balance` DECIMAL(10,2) DEFAULT 0 AFTER `email`;

SET FOREIGN_KEY_CHECKS = 1;
