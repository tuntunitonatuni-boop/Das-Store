<?php
require_once 'config.php';
require_once 'includes/db.php';
try {
    // Modify SKUs of soft-deleted products to free them up
    $stmt = $pdo->query("UPDATE products SET sku = CONCAT(sku, '_del_', id) WHERE is_active = 0 AND sku IS NOT NULL AND sku NOT LIKE '%_del_%'");
    echo "Fixed soft-deleted SKUs: " . $stmt->rowCount() . "\n";
    
    // Modify Barcodes of soft-deleted products
    $stmt2 = $pdo->query("UPDATE products SET barcode = CONCAT(barcode, '_del_', id) WHERE is_active = 0 AND barcode IS NOT NULL AND barcode != '' AND barcode NOT LIKE '%_del_%'");
    echo "Fixed soft-deleted Barcodes: " . $stmt2->rowCount() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
