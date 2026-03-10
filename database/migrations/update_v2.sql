-- Migration to add customer login and order tracking statuses
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Update customers table to support logins
ALTER TABLE `customers` 
ADD COLUMN `password` VARCHAR(255) NULL AFTER `email`,
ADD COLUMN `username` VARCHAR(60) NULL UNIQUE AFTER `name`;

-- 2. Update sales table status ENUM to support online order tracking
-- Because ENUM modification can be tricky in some SQL versions, we'll change it to VARCHAR or update the ENUM.
-- We'll use VARCHAR for more flexibility in the future, or just update the ENUM.
-- Let's update the ENUM to: 'pending', 'packaging', 'delivering', 'completed', 'cancelled', 'refunded', 'partial'
ALTER TABLE `sales` 
MODIFY COLUMN `status` ENUM('pending', 'packaging', 'delivering', 'completed', 'cancelled', 'refunded', 'partial') DEFAULT 'completed';

-- Note: POS sales will still default to 'completed' or we can update POS logic to specify 'completed'
-- Online orders will explicitly use 'pending' as seen in checkout.php

SET FOREIGN_KEY_CHECKS = 1;
