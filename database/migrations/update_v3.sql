-- Migration to fix products table columns
SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `products` 
ADD COLUMN `dealer_id` INT UNSIGNED NULL AFTER `company_id`,
ADD COLUMN `weight_size` VARCHAR(100) NULL AFTER `unit`,
ADD COLUMN `mfg_date` DATE NULL AFTER `track_expiry`,
ADD COLUMN `expiry_date` DATE NULL AFTER `mfg_date`,
ADD CONSTRAINT `products_dealer_fk` FOREIGN KEY (`dealer_id`) REFERENCES `dealers`(`id`) ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS = 1;
