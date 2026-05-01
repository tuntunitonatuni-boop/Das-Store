<?php
require_once 'config.php';
require_once 'includes/db.php';
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'price_history'");
    if ($stmt->rowCount() > 0) {
        echo "price_history exists";
    } else {
        echo "price_history DOES NOT EXIST";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
