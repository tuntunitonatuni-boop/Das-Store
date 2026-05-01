<?php
// quick_edit_test.php — Tests if product update works when submitted directly
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/flash.php';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id         = (int)$_POST['id'];
    $name       = trim($_POST['name'] ?? '');
    $sale_price = (float)$_POST['sale_price'];
    
    try {
        $pdo->prepare("UPDATE products SET name=?, sale_price=? WHERE id=?")
            ->execute([$name, $sale_price, $id]);
        $msg = "<div style='color:green;font-weight:bold;font-size:18px'>✅ SUCCESS! Product #$id updated to name='$name' price=$sale_price</div>";
    } catch (Exception $e) {
        $msg = "<div style='color:red'>❌ ERROR: " . $e->getMessage() . "</div>";
    }
}

// Load product 51 (পাকা তেঁতুল)
$product = $pdo->prepare("SELECT * FROM products WHERE id=51");
$product->execute();
$product = $product->fetch();

if (!$product) {
    // Try first product
    $product = $pdo->query("SELECT * FROM products LIMIT 1 OFFSET 5")->fetch();
}
?>
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Quick Edit Test</title></head>
<body style="font-family:sans-serif;padding:30px;max-width:500px">
    <h2>Quick Product Edit Test</h2>
    <?= $msg ?>
    <?php if ($product): ?>
    <form method="post" style="margin-top:20px">
        <input type="hidden" name="id" value="<?= $product['id'] ?>">
        <p><strong>Product ID:</strong> <?= $product['id'] ?></p>
        <p>
            <label>Name:<br>
            <input type="text" name="name" value="<?= htmlspecialchars($product['name']) ?>" style="width:100%;padding:8px;font-size:16px">
            </label>
        </p>
        <p>
            <label>Sale Price:<br>
            <input type="number" step="0.01" name="sale_price" value="<?= $product['sale_price'] ?>" style="width:100%;padding:8px;font-size:16px">
            </label>
        </p>
        <button type="submit" style="background:#0066cc;color:white;padding:12px 30px;font-size:16px;border:none;border-radius:8px;cursor:pointer">
            Save Changes
        </button>
    </form>
    <?php else: ?>
    <p style="color:red">Product not found!</p>
    <?php endif; ?>
    
    <hr style="margin-top:30px">
    <h3>Also — Check what happens in products.php form:</h3>
    <p>Go to: <a href="products.php?edit=<?= $product['id'] ?? 51 ?>">products.php?edit=<?= $product['id'] ?? 51 ?></a></p>
    <p>When modal opens, open browser console (F12) and paste this:</p>
    <pre style="background:#f0f0f0;padding:10px;font-size:12px">
// Run in console to force submit:
document.querySelector('form[method="post"]').submit();
    </pre>
</body>
</html>
