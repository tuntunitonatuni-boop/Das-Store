<?php
// migrate.php — Phase 2 Migration: All features
require_once 'config.php';
require_once 'includes/db.php';

echo "<div style='font-family:sans-serif; padding:20px; line-height:1.8; max-width:900px;'>";
echo "<h2 style='color:#15803d;'>🚀 Database Migration Tool — Phase 2</h2>";
echo "<hr>";

$clean_queries = [
    // ─── Phase 1 Legacy ───────────────────────────────────────────────
    "ALTER TABLE `customers` ADD COLUMN `password` VARCHAR(255) NULL AFTER `email`",
    "ALTER TABLE `customers` ADD COLUMN `username` VARCHAR(60) NULL UNIQUE AFTER `name`",
    "ALTER TABLE `sales` MODIFY COLUMN `status` ENUM('pending','packaging','delivering','completed','cancelled','refunded','partial') DEFAULT 'completed'",
    "ALTER TABLE `products` ADD COLUMN `dealer_id` INT UNSIGNED NULL AFTER `company_id`",
    "ALTER TABLE `products` ADD COLUMN `weight_size` VARCHAR(100) NULL AFTER `unit`",
    "ALTER TABLE `products` ADD COLUMN `mfg_date` DATE NULL AFTER `track_expiry`",
    "ALTER TABLE `products` ADD COLUMN `expiry_date` DATE NULL AFTER `mfg_date`",
    "ALTER TABLE `categories` ADD COLUMN `image` VARCHAR(255) NULL AFTER `icon`",
    "ALTER TABLE `sales` ADD COLUMN `shipping_address` TEXT NULL AFTER `status`",
    "ALTER TABLE `dealers` ADD COLUMN `balance` DECIMAL(10,2) DEFAULT 0 AFTER `email`",

    "CREATE TABLE IF NOT EXISTS `expenses` (
        `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `category`     VARCHAR(100) NOT NULL,
        `amount`       DECIMAL(10,2) NOT NULL,
        `expense_date` DATE NOT NULL,
        `notes`        TEXT,
        `user_id`      INT UNSIGNED,
        `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS `supplier_ledger` (
        `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `dealer_id`   INT UNSIGNED NOT NULL,
        `po_id`       INT UNSIGNED,
        `type`        ENUM('debit','credit') NOT NULL,
        `amount`      DECIMAL(10,2) NOT NULL,
        `description` VARCHAR(255),
        `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`dealer_id`) REFERENCES `dealers`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`po_id`)     REFERENCES `purchase_orders`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS `supplier_payments` (
        `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `supplier_id` INT UNSIGNED NOT NULL,
        `amount`      DECIMAL(10,2) NOT NULL,
        `method`      ENUM('cash','card','mobile','bank') DEFAULT 'cash',
        `notes`       TEXT,
        `user_id`     INT UNSIGNED,
        `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`supplier_id`) REFERENCES `dealers`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`)   ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS `returns` (
        `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `type`          ENUM('customer_to_shop','shop_to_supplier') NOT NULL,
        `product_id`    INT UNSIGNED NOT NULL,
        `customer_id`   INT UNSIGNED,
        `dealer_id`     INT UNSIGNED,
        `sale_id`       INT UNSIGNED,
        `invoice_ref`   VARCHAR(100),
        `qty`           DECIMAL(10,3) NOT NULL,
        `refund_amount` DECIMAL(10,2) DEFAULT 0,
        `reason`        TEXT,
        `user_id`       INT UNSIGNED,
        `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`product_id`)  REFERENCES `products`(`id`)  ON DELETE CASCADE,
        FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
        FOREIGN KEY (`dealer_id`)   REFERENCES `dealers`(`id`)   ON DELETE SET NULL,
        FOREIGN KEY (`sale_id`)     REFERENCES `sales`(`id`)     ON DELETE SET NULL,
        FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`)     ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // ─── Phase 2A: Product Enhancements ──────────────────────────────
    "ALTER TABLE `products` ADD COLUMN `is_published`      TINYINT(1) DEFAULT 0 AFTER `is_active`",
    "ALTER TABLE `products` ADD COLUMN `name_bn`           VARCHAR(255) NULL AFTER `name`",
    "ALTER TABLE `products` ADD COLUMN `description_bn`    TEXT NULL AFTER `description`",
    "ALTER TABLE `products` ADD COLUMN `allow_custom_qty`  TINYINT(1) DEFAULT 0 AFTER `weight_size`",
    "ALTER TABLE `products` ADD COLUMN `min_order_amount`  DECIMAL(10,2) DEFAULT 0 AFTER `allow_custom_qty`",
    "ALTER TABLE `products` ADD COLUMN `badge`             ENUM('none','new','popular','sale','hot') DEFAULT 'none' AFTER `is_published`",

    // ─── Phase 2B: Product Media ──────────────────────────────────────
    "CREATE TABLE IF NOT EXISTS `product_media` (
        `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `product_id`  INT UNSIGNED NOT NULL,
        `file_path`   VARCHAR(500) NOT NULL,
        `media_type`  ENUM('image','video','gif') DEFAULT 'image',
        `sort_order`  INT DEFAULT 0,
        `is_primary`  TINYINT(1) DEFAULT 0,
        `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // ─── Phase 2C: Reviews & Wishlist ────────────────────────────────
    "CREATE TABLE IF NOT EXISTS `product_reviews` (
        `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `product_id`   INT UNSIGNED NOT NULL,
        `customer_id`  INT UNSIGNED,
        `rating`       TINYINT(1) NOT NULL DEFAULT 5,
        `review_text`  TEXT,
        `is_approved`  TINYINT(1) DEFAULT 0,
        `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`product_id`)  REFERENCES `products`(`id`)  ON DELETE CASCADE,
        FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS `wishlists` (
        `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `customer_id` INT UNSIGNED NOT NULL,
        `product_id`  INT UNSIGNED NOT NULL,
        `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY   `unique_wishlist` (`customer_id`,`product_id`),
        FOREIGN KEY  (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE,
        FOREIGN KEY  (`product_id`)  REFERENCES `products`(`id`)  ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // ─── Phase 2D: Coupons ────────────────────────────────────────────
    "CREATE TABLE IF NOT EXISTS `coupons` (
        `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `code`            VARCHAR(50) NOT NULL UNIQUE,
        `type`            ENUM('percent','fixed') DEFAULT 'percent',
        `value`           DECIMAL(10,2) NOT NULL,
        `min_order`       DECIMAL(10,2) DEFAULT 0,
        `max_discount`    DECIMAL(10,2) DEFAULT 0,
        `usage_limit`     INT DEFAULT 0,
        `used_count`      INT DEFAULT 0,
        `expires_at`      DATE,
        `is_active`       TINYINT(1) DEFAULT 1,
        `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // ─── Phase 2E: Loyalty Points ─────────────────────────────────────
    "ALTER TABLE `customers` ADD COLUMN `total_points` INT DEFAULT 0 AFTER `balance`",
    "CREATE TABLE IF NOT EXISTS `loyalty_points` (
        `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `customer_id` INT UNSIGNED NOT NULL,
        `sale_id`     INT UNSIGNED,
        `points`      INT NOT NULL,
        `type`        ENUM('earn','redeem','expire','manual') DEFAULT 'earn',
        `description` VARCHAR(255),
        `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`sale_id`)     REFERENCES `sales`(`id`)     ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // ─── Phase 2F: Sales Enhancements ────────────────────────────────
    "ALTER TABLE `sales` ADD COLUMN `coupon_id`        INT UNSIGNED NULL AFTER `payment_method`",
    "ALTER TABLE `sales` ADD COLUMN `discount_amount`  DECIMAL(10,2) DEFAULT 0 AFTER `coupon_id`",
    "ALTER TABLE `sales` ADD COLUMN `points_earned`    INT DEFAULT 0 AFTER `discount_amount`",
    "ALTER TABLE `sales` ADD COLUMN `points_redeemed`  INT DEFAULT 0 AFTER `points_earned`",
    "ALTER TABLE `sales` ADD COLUMN `delivery_charge`  DECIMAL(10,2) DEFAULT 0 AFTER `points_redeemed`",

    // ─── Phase 2G: Feature Settings ──────────────────────────────────
    "CREATE TABLE IF NOT EXISTS `feature_settings` (
        `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `feature_key` VARCHAR(100) NOT NULL UNIQUE,
        `is_enabled`  TINYINT(1) DEFAULT 1,
        `value`       VARCHAR(500) DEFAULT NULL,
        `label`       VARCHAR(200),
        `updated_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // ─── Phase 2H: Cash Register ─────────────────────────────────────
    "CREATE TABLE IF NOT EXISTS `cash_register` (
        `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `date`            DATE NOT NULL UNIQUE,
        `opening_cash`    DECIMAL(10,2) DEFAULT 0,
        `closing_cash`    DECIMAL(10,2),
        `total_sales`     DECIMAL(10,2) DEFAULT 0,
        `total_cash`      DECIMAL(10,2) DEFAULT 0,
        `total_mobile`    DECIMAL(10,2) DEFAULT 0,
        `total_expenses`  DECIMAL(10,2) DEFAULT 0,
        `notes`           TEXT,
        `opened_by`       INT UNSIGNED,
        `closed_by`       INT UNSIGNED,
        `status`          ENUM('open','closed') DEFAULT 'open',
        `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`opened_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
        FOREIGN KEY (`closed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    // ─── Phase 2I: Fix feature key names (if old keys exist) ─────────
    "UPDATE `feature_settings` SET feature_key='product_reviews'    WHERE feature_key='customer_reviews'",
    "UPDATE `feature_settings` SET feature_key='customer_wishlist'  WHERE feature_key='wishlist'",
    "UPDATE `feature_settings` SET feature_key='discount_coupons'   WHERE feature_key='coupons'",
    "UPDATE `feature_settings` SET feature_key='whatsapp_order_btn' WHERE feature_key='whatsapp_order'",

    // ─── Phase 2J: Fix product_reviews (remove old fk/col if they exist) ────
    "ALTER TABLE `product_reviews` DROP FOREIGN KEY IF EXISTS `product_reviews_ibfk_2`",
    "ALTER TABLE `product_reviews` DROP COLUMN IF EXISTS `name`",

    // ─── Phase 2K: Add expiry_date to purchase_order_items ───────────
    "ALTER TABLE `purchase_order_items` ADD COLUMN `expiry_date` DATE NULL AFTER `subtotal`",
];

$success = 0; $skipped = 0; $errors = 0;

foreach ($clean_queries as $sql) {
    try {
        $pdo->exec($sql);
        $preview = trim(substr($sql, 0, 60));
        echo "<p style='color:green; margin:2px 0;'>✅ <code>$preview...</code></p>";
        $success++;
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        $preview = trim(substr($sql, 0, 55));
        if (
            strpos($msg, '1060') !== false || // Column exists
            strpos($msg, '1061') !== false || // Duplicate key
            strpos($msg, '1022') !== false || // Can't write duplicate key
            strpos($msg, '121')  !== false || // Duplicate key on CREATE
            strpos($msg, 'Duplicate') !== false
        ) {
            echo "<p style='color:orange; margin:2px 0;'>ℹ️ Already exists: <code>$preview...</code></p>";
            $skipped++;
        } else {
            echo "<p style='color:red; margin:2px 0;'>❌ Error: ".htmlspecialchars($msg)."<br><small><code>".htmlspecialchars(substr($sql,0,100))."</code></small></p>";
            $errors++;
        }
    }
}

// Seed default feature settings
$features = [
    ['online_store',          1, null,  'Online Store (Customer Website)'],
    ['product_reviews',       1, null,  'Product Reviews & Ratings'],
    ['customer_wishlist',     1, null,  'Customer Wishlist'],
    ['discount_coupons',      1, null,  'Coupon / Discount Codes'],
    ['loyalty_points',        1, null,  'Loyalty Points System'],
    ['whatsapp_order_btn',    1, null,  'WhatsApp Order Button'],
    ['whatsapp_number',       1, '',    'WhatsApp Phone Number'],
    ['custom_qty',            1, null,  'Custom Qty / Partial Selling'],
    ['free_delivery',         1, '500', 'Free Delivery Above (amount)'],
    ['low_stock_alert',       1, null,  'Low Stock Alert on Dashboard'],
    ['order_tracking',        1, null,  'Customer Order Tracking Page'],
    ['product_zoom',          1, null,  'Product Image Zoom'],
    ['multiple_media',        1, null,  'Multiple Product Images/Video/GIF'],
    ['cash_register',         1, null,  'Daily Cash Register'],
    ['bangla_product_names',  1, null,  'Bangla Product Name & Description'],
    ['loyalty_points_per_tk', 1, '1',   'Loyalty Points Per Taka Spent'],
    ['points_redeem_value',   1, '1',   'Points Value (1 point = X Taka)'],
    ['delivery_charge',       1, '50',  'Default Delivery Charge'],
    ['pos_ic',                1, null,  'IC Wholesale POS'],
];

echo "<hr><h3 style='color:#1d4ed8;'>📌 Seeding Feature Settings...</h3>";
foreach ($features as [$key, $enabled, $val, $label]) {
    try {
        $pdo->prepare("INSERT IGNORE INTO `feature_settings` (feature_key, is_enabled, value, label) VALUES (?,?,?,?)")
            ->execute([$key, $enabled, $val, $label]);
        echo "<p style='color:#1d4ed8; margin:2px 0;'>✅ Feature: <strong>$label</strong></p>";
    } catch (Exception $e) {
        echo "<p style='color:orange; margin:2px 0;'>ℹ️ Feature already seeded: $label</p>";
    }
}

echo "<hr>";
echo "<div style='background:#f0fdf4; border:1px solid #86efac; border-radius:8px; padding:16px; margin-top:12px;'>";
echo "<h3 style='color:#15803d; margin:0 0 8px;'>🎉 Migration Complete!</h3>";
echo "<p>✅ <strong>$success</strong> applied &nbsp;|&nbsp; ℹ️ <strong>$skipped</strong> skipped &nbsp;|&nbsp; ❌ <strong>$errors</strong> errors</p>";
echo "</div>";
echo "<div style='margin-top:16px; display:flex; gap:12px;'>";
echo "<a href='dashboard.php' style='padding:12px 24px; background:#16a34a; color:white; text-decoration:none; border-radius:10px; font-weight:bold;'>🏠 Go to Dashboard</a>";
echo "<a href='settings.php' style='padding:12px 24px; background:#2563eb; color:white; text-decoration:none; border-radius:10px; font-weight:bold;'>⚙️ Go to Settings</a>";
echo "</div>";
echo "</div>";
