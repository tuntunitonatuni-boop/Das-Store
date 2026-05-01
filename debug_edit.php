<?php
require_once 'config.php';
require_once 'includes/db.php';

echo "<pre style='font-family:monospace;font-size:13px;padding:20px'>";

// 1. Show all columns in products table
echo "=== PRODUCTS TABLE COLUMNS ===\n";
$cols = $pdo->query("SHOW COLUMNS FROM products")->fetchAll();
foreach ($cols as $c) {
    echo sprintf("  %-25s %-20s %s\n", $c['Field'], $c['Type'], $c['Null']==='NO'?'NOT NULL':'NULL');
}

// 2. Try a dummy UPDATE on product id=1 (or first available)
echo "\n=== TEST UPDATE ===\n";
$first = $pdo->query("SELECT * FROM products LIMIT 1")->fetch();
if ($first) {
    echo "Testing update on product ID: " . $first['id'] . " — " . $first['name'] . "\n";
    try {
        $sql = "UPDATE products SET name=?,name_bn=?,sku=?,barcode=?,category_id=?,company_id=?,dealer_id=?,unit=?,weight_size=?,cost_price=?,sale_price=?,mrp=?,description=?,description_bn=?,is_active=?,is_published=?,track_expiry=?,allow_custom_qty=?,min_order_amount=?,badge=?,mfg_date=?,expiry_date=? WHERE id=?";
        $params = [
            $first['name'], $first['name_bn'], $first['sku'], $first['barcode'],
            $first['category_id'], $first['company_id'], $first['dealer_id'],
            $first['unit'], $first['weight_size'],
            $first['cost_price'], $first['sale_price'], $first['mrp'],
            $first['description'], $first['description_bn'],
            $first['is_active'], $first['is_published'], $first['track_expiry'],
            $first['allow_custom_qty'], $first['min_order_amount'],
            $first['badge'], $first['mfg_date'], $first['expiry_date'],
            $first['id']
        ];
        $pdo->prepare($sql)->execute($params);
        echo "✅ UPDATE SUCCESS — No DB error found!\n";
        echo "\nPossible issue: form is not submitting correctly (JS/redirect problem).\n";
    } catch (PDOException $e) {
        echo "❌ UPDATE FAILED!\n";
        echo "Error Code: " . $e->getCode() . "\n";
        echo "Error Message: " . $e->getMessage() . "\n";
    }
} else {
    echo "No products found in DB.\n";
}

// 3. Check if 'allow_custom_qty' column exists
echo "\n=== COLUMN CHECK ===\n";
$needed = ['allow_custom_qty','min_order_amount','is_published','badge','name_bn','description_bn','mfg_date','expiry_date'];
$existing = array_column($cols, 'Field');
foreach ($needed as $col) {
    echo "  " . ($col) . ": " . (in_array($col, $existing) ? "✅ EXISTS" : "❌ MISSING") . "\n";
}

echo "</pre>";
