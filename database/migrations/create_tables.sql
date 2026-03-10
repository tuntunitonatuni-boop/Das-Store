-- ============================================================
--  Smart Grocery ERP — All 16 Tables (single migration file)
--  Run once via setup.php or import in phpMyAdmin
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. users
CREATE TABLE IF NOT EXISTS `users` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(100) NOT NULL,
  `username`   VARCHAR(60)  NOT NULL UNIQUE,
  `email`      VARCHAR(150) UNIQUE,
  `password`   VARCHAR(255) NOT NULL,
  `role`       ENUM('owner','staff') NOT NULL DEFAULT 'staff',
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. categories
CREATE TABLE IF NOT EXISTS `categories` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(100) NOT NULL,
  `slug`       VARCHAR(120) NOT NULL UNIQUE,
  `icon`       VARCHAR(10)  DEFAULT '🏷️',
  `is_active`  TINYINT(1)   DEFAULT 1,
  `sort_order` SMALLINT     DEFAULT 0,
  `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. companies (brands)
CREATE TABLE IF NOT EXISTS `companies` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(150) NOT NULL,
  `country`    VARCHAR(80),
  `is_active`  TINYINT(1)   DEFAULT 1,
  `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. dealers (suppliers)
CREATE TABLE IF NOT EXISTS `dealers` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(150) NOT NULL,
  `company`     VARCHAR(150),
  `phone`       VARCHAR(20),
  `whatsapp`    VARCHAR(20),
  `email`       VARCHAR(150),
  `address`     TEXT,
  `is_active`   TINYINT(1)   DEFAULT 1,
  `created_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. products
CREATE TABLE IF NOT EXISTS `products` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`          VARCHAR(200) NOT NULL,
  `sku`           VARCHAR(80)  UNIQUE,
  `barcode`       VARCHAR(80)  UNIQUE,
  `category_id`   INT UNSIGNED,
  `company_id`    INT UNSIGNED,
  `dealer_id`     INT UNSIGNED,
  `unit`          VARCHAR(30)  DEFAULT 'pcs',
  `weight_size`   VARCHAR(100),
  `cost_price`    DECIMAL(10,2) NOT NULL DEFAULT 0,
  `sale_price`    DECIMAL(10,2) NOT NULL DEFAULT 0,
  `mrp`           DECIMAL(10,2) DEFAULT 0,
  `description`   TEXT,
  `image`         VARCHAR(255),
  `is_active`     TINYINT(1)   DEFAULT 1,
  `track_expiry`  TINYINT(1)   DEFAULT 0,
  `mfg_date`      DATE NULL,
  `expiry_date`   DATE NULL,
  `created_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`company_id`)  REFERENCES `companies`(`id`)  ON DELETE SET NULL,
  FOREIGN KEY (`dealer_id`)   REFERENCES `dealers`(`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. inventory (dual-location: display / warehouse)
CREATE TABLE IF NOT EXISTS `inventory` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_id`      INT UNSIGNED NOT NULL UNIQUE,
  `display_qty`     DECIMAL(10,3) DEFAULT 0,
  `warehouse_qty`   DECIMAL(10,3) DEFAULT 0,
  `reorder_level`   DECIMAL(10,3) DEFAULT 10,
  `updated_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. product_batches (expiry tracking)
CREATE TABLE IF NOT EXISTS `product_batches` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_id`    INT UNSIGNED NOT NULL,
  `batch_no`      VARCHAR(80),
  `mfg_date`      DATE,
  `expiry_date`   DATE,
  `qty`           DECIMAL(10,3) DEFAULT 0,
  `cost_price`    DECIMAL(10,2) DEFAULT 0,
  `location`      ENUM('display','warehouse') DEFAULT 'warehouse',
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. customers
CREATE TABLE IF NOT EXISTS `customers` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`          VARCHAR(150) NOT NULL,
  `username`      VARCHAR(60)  NULL UNIQUE,
  `phone`         VARCHAR(20),
  `email`         VARCHAR(150),
  `password`      VARCHAR(255) NULL,
  `address`       TEXT,
  `credit_limit`  DECIMAL(10,2) DEFAULT 0,
  `balance`       DECIMAL(10,2) DEFAULT 0,  -- positive = customer owes (Baki)
  `is_active`     TINYINT(1)   DEFAULT 1,
  `created_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. sales
CREATE TABLE IF NOT EXISTS `sales` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `invoice_no`     VARCHAR(30)  NOT NULL UNIQUE,
  `customer_id`    INT UNSIGNED,
  `user_id`        INT UNSIGNED,
  `subtotal`       DECIMAL(10,2) DEFAULT 0,
  `discount`       DECIMAL(10,2) DEFAULT 0,
  `tax`            DECIMAL(10,2) DEFAULT 0,
  `total`          DECIMAL(10,2) DEFAULT 0,
  `amount_paid`    DECIMAL(10,2) DEFAULT 0,
  `change_given`   DECIMAL(10,2) DEFAULT 0,
  `payment_method` ENUM('cash','credit','card','mobile') DEFAULT 'cash',
  `status`         ENUM('pending', 'packaging', 'delivering', 'completed', 'cancelled', 'refunded', 'partial') DEFAULT 'completed',
  `notes`          TEXT,
  `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. sale_items
CREATE TABLE IF NOT EXISTS `sale_items` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `sale_id`     INT UNSIGNED NOT NULL,
  `product_id`  INT UNSIGNED,
  `product_name` VARCHAR(200),
  `qty`         DECIMAL(10,3) NOT NULL,
  `unit_price`  DECIMAL(10,2) NOT NULL,
  `discount`    DECIMAL(10,2) DEFAULT 0,
  `subtotal`    DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (`sale_id`)    REFERENCES `sales`(`id`)    ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. customer_ledger
CREATE TABLE IF NOT EXISTS `customer_ledger` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `customer_id` INT UNSIGNED NOT NULL,
  `sale_id`     INT UNSIGNED,
  `type`        ENUM('debit','credit') NOT NULL,
  `amount`      DECIMAL(10,2) NOT NULL,
  `description` VARCHAR(255),
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`sale_id`)     REFERENCES `sales`(`id`)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. customer_payments
CREATE TABLE IF NOT EXISTS `customer_payments` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `customer_id` INT UNSIGNED NOT NULL,
  `amount`      DECIMAL(10,2) NOT NULL,
  `method`      ENUM('cash','card','mobile','bank') DEFAULT 'cash',
  `notes`       TEXT,
  `user_id`     INT UNSIGNED,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. stock_transfers (display ↔ warehouse)
CREATE TABLE IF NOT EXISTS `stock_transfers` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_id`  INT UNSIGNED NOT NULL,
  `from_loc`    ENUM('display','warehouse') NOT NULL,
  `to_loc`      ENUM('display','warehouse') NOT NULL,
  `qty`         DECIMAL(10,3) NOT NULL,
  `notes`       TEXT,
  `user_id`     INT UNSIGNED,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 14. purchase_orders
CREATE TABLE IF NOT EXISTS `purchase_orders` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `po_number`   VARCHAR(30) NOT NULL UNIQUE,
  `dealer_id`   INT UNSIGNED,
  `user_id`     INT UNSIGNED,
  `total`       DECIMAL(10,2) DEFAULT 0,
  `status`      ENUM('draft','sent','received','cancelled') DEFAULT 'draft',
  `notes`       TEXT,
  `ordered_at`  DATE,
  `received_at` DATE,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`dealer_id`) REFERENCES `dealers`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 14b. purchase_order_items
CREATE TABLE IF NOT EXISTS `purchase_order_items` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `po_id`       INT UNSIGNED NOT NULL,
  `product_id`  INT UNSIGNED,
  `product_name` VARCHAR(200),
  `qty`         DECIMAL(10,3) NOT NULL,
  `cost_price`  DECIMAL(10,2) NOT NULL,
  `subtotal`    DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (`po_id`)       REFERENCES `purchase_orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`)  REFERENCES `products`(`id`)        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 15. price_history
CREATE TABLE IF NOT EXISTS `price_history` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_id`  INT UNSIGNED NOT NULL,
  `old_cost`    DECIMAL(10,2),
  `new_cost`    DECIMAL(10,2),
  `old_sale`    DECIMAL(10,2),
  `new_sale`    DECIMAL(10,2),
  `changed_by`  INT UNSIGNED,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 16. stock_movements (full audit log)
CREATE TABLE IF NOT EXISTS `stock_movements` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_id`  INT UNSIGNED NOT NULL,
  `type`        ENUM('sale','purchase','transfer_in','transfer_out','adjustment','return','opening') NOT NULL,
  `qty_change`  DECIMAL(10,3) NOT NULL,   -- positive = in, negative = out
  `location`    ENUM('display','warehouse','any') DEFAULT 'any',
  `reference_id` INT UNSIGNED,            -- sale_id, po_id, transfer_id etc.
  `notes`       TEXT,
  `user_id`     INT UNSIGNED,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
