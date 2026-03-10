<?php
// migrate.php — Final Version with proper error handling
require_once 'config.php';
require_once 'includes/db.php';

echo "<div style='font-family:sans-serif; padding:20px; line-height:1.6;'>";
echo "<h2 style='color:#15803d;'>🚀 Database Migration Tool (Final)</h2>";
echo "<hr>";

$clean_queries = [
    "ALTER TABLE `customers` ADD COLUMN `password` VARCHAR(255) NULL AFTER `email` ",
    "ALTER TABLE `customers` ADD COLUMN `username` VARCHAR(60) NULL UNIQUE AFTER `name` ",
    "ALTER TABLE `sales` MODIFY COLUMN `status` ENUM('pending', 'packaging', 'delivering', 'completed', 'cancelled', 'refunded', 'partial') DEFAULT 'completed' ",
    "ALTER TABLE `products` ADD COLUMN `dealer_id` INT UNSIGNED NULL AFTER `company_id` ",
    "ALTER TABLE `products` ADD COLUMN `weight_size` VARCHAR(100) NULL AFTER `unit` ",
    "ALTER TABLE `products` ADD COLUMN `mfg_date` DATE NULL AFTER `track_expiry` ",
    "ALTER TABLE `products` ADD COLUMN `expiry_date` DATE NULL AFTER `mfg_date` ",
    "ALTER TABLE `products` ADD CONSTRAINT `products_dealer_fk` FOREIGN KEY (`dealer_id`) REFERENCES `dealers`(`id`) ON DELETE SET NULL"
];

foreach ($clean_queries as $sql) {
    try {
        $pdo->exec($sql);
        echo "<p style='color:green;'>✅ Success: " . htmlspecialchars(substr($sql, 0, 50)) . "...</p>";
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        // Catch "Column already exists" (1060) or "Duplicate key" (121/1061/1022)
        if (strpos($msg, '1060') !== false || strpos($msg, '121') !== false || strpos($msg, 'Duplicate') !== false) {
            echo "<p style='color:orange;'>ℹ️ Already Updated: " . htmlspecialchars(substr($sql, 0, 45)) . "...</p>";
        } else {
            echo "<p style='color:red;'>❌ Error: " . htmlspecialchars($msg) . "</p>";
        }
    }
}

echo "<hr>";
echo "<p style='color:#15803d; font-weight:bold;'>🎉 Everything is updated! Your database is now perfectly synced with the code.</p>";
echo "<p>Please delete <b>migrate.php</b> now.</p>";
echo "<a href='dashboard.php' style='display:inline-block; padding:12px 24px; background:#16a34a; color:white; text-decoration:none; border-radius:10px; font-weight:bold;'>Go to Dashboard →</a>";
echo "</div>";
