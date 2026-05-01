<?php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login(); // only logged-in admin can run this

header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Inventory Debug</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#111;color:#eee;font-size:13px;line-height:1.7}
h2{color:#22c55e;margin-top:20px}h3{color:#60a5fa}
.ok{color:#22c55e}.err{color:#f87171}.warn{color:#fbbf24}
table{border-collapse:collapse;width:100%;margin:8px 0}
th,td{border:1px solid #444;padding:6px 10px;text-align:left}
th{background:#1f2937;color:#93c5fd}
tr:hover{background:#1f2937}
</style></head><body>";

echo "<h2>🔍 Das Store — Inventory + Purchases Debug</h2>";

// ── 1. inventory table structure ─────────────────────────────────────────────
echo "<h3>1. INVENTORY TABLE STRUCTURE</h3><table><tr><th>Field</th><th>Type</th><th>Default</th><th>Key</th></tr>";
foreach ($pdo->query("DESCRIBE inventory")->fetchAll() as $c)
    echo "<tr><td>{$c['Field']}</td><td>{$c['Type']}</td><td>{$c['Default']}</td><td>{$c['Key']}</td></tr>";
echo "</table>";

// ── 2. purchase_order_items columns ──────────────────────────────────────────
echo "<h3>2. purchase_order_items COLUMNS</h3><table><tr><th>Field</th><th>Type</th><th>Null</th></tr>";
foreach ($pdo->query("DESCRIBE purchase_order_items")->fetchAll() as $c) {
    $highlight = $c['Field'] === 'expiry_date' ? ' class="ok"' : '';
    echo "<tr{$highlight}><td>{$c['Field']}</td><td>{$c['Type']}</td><td>{$c['Null']}</td></tr>";
}
$has_expiry = $pdo->query("SHOW COLUMNS FROM purchase_order_items LIKE 'expiry_date'")->fetch();
echo "</table>";
echo $has_expiry
    ? "<p class='ok'>✅ expiry_date column EXISTS</p>"
    : "<p class='err'>❌ expiry_date column MISSING — migrate.php চালান!</p>";

// ── 3. feature_settings table ────────────────────────────────────────────────
echo "<h3>3. FEATURE SETTINGS</h3>";
try {
    $features = $pdo->query("SELECT feature_key, is_enabled, value FROM feature_settings ORDER BY feature_key")->fetchAll();
    echo "<table><tr><th>Key</th><th>Enabled</th><th>Value</th></tr>";
    foreach ($features as $f)
        echo "<tr><td>{$f['feature_key']}</td><td>".($f['is_enabled']?"<span class='ok'>✅</span>":"<span class='err'>❌</span>")."</td><td>".htmlspecialchars($f['value']??'')."</td></tr>";
    echo "</table>";
} catch (Exception $e) {
    echo "<p class='err'>❌ feature_settings table নেই! migrate.php চালান।</p>";
}

// ── 4. Current inventory rows ─────────────────────────────────────────────────
echo "<h3>4. CURRENT INVENTORY ROWS</h3>";
$inv_rows = $pdo->query("SELECT i.*, p.name FROM inventory i JOIN products p ON p.id=i.product_id ORDER BY i.id DESC LIMIT 20")->fetchAll();
if (empty($inv_rows)) {
    echo "<p class='warn'>⚠️ inventory table একদম খালি!</p>";
} else {
    echo "<table><tr><th>Product</th><th>warehouse_qty</th><th>display_qty</th><th>reorder_level</th></tr>";
    foreach ($inv_rows as $r)
        echo "<tr><td>{$r['name']} (id={$r['product_id']})</td><td class='ok'>{$r['warehouse_qty']}</td><td>{$r['display_qty']}</td><td>{$r['reorder_level']}</td></tr>";
    echo "</table>";
}

// ── 5. Last 10 Purchase Orders ───────────────────────────────────────────────
echo "<h3>5. LAST 10 PURCHASE ORDERS</h3>";
$pos = $pdo->query("SELECT po.*, d.name as dealer FROM purchase_orders po LEFT JOIN dealers d ON d.id=po.dealer_id ORDER BY po.id DESC LIMIT 10")->fetchAll();
if (empty($pos)) {
    echo "<p class='warn'>⚠️ কোনো Purchase Order নেই।</p>";
} else {
    echo "<table><tr><th>ID</th><th>PO#</th><th>Dealer</th><th>Status</th><th>Total</th><th>Created</th></tr>";
    foreach ($pos as $o) {
        $cls = $o['status']==='received' ? 'ok' : 'warn';
        echo "<tr><td>{$o['id']}</td><td>{$o['po_number']}</td><td>".htmlspecialchars($o['dealer']??'—')."</td><td class='{$cls}'>{$o['status']}</td><td>৳{$o['total']}</td><td>{$o['created_at']}</td></tr>";
    }
    echo "</table>";
}

// ── 6. Last 10 PO Items ───────────────────────────────────────────────────────
echo "<h3>6. LAST 10 purchase_order_items</h3>";
$items = $pdo->query("SELECT poi.*, p.name FROM purchase_order_items poi LEFT JOIN products p ON p.id=poi.product_id ORDER BY poi.id DESC LIMIT 10")->fetchAll();
if (empty($items)) {
    echo "<p class='warn'>⚠️ কোনো PO item নেই।</p>";
} else {
    echo "<table><tr><th>PO id</th><th>Product</th><th>Qty</th><th>Cost</th><th>Expiry</th></tr>";
    foreach ($items as $it)
        echo "<tr><td>{$it['po_id']}</td><td>".htmlspecialchars($it['name']??'?')."</td><td>{$it['qty']}</td><td>৳{$it['cost_price']}</td><td>".($it['expiry_date']??'—')."</td></tr>";
    echo "</table>";
}

// ── 7. Last 10 Stock Movements ────────────────────────────────────────────────
echo "<h3>7. LAST 10 STOCK MOVEMENTS</h3>";
$mvs = $pdo->query("SELECT sm.*, p.name FROM stock_movements sm LEFT JOIN products p ON p.id=sm.product_id ORDER BY sm.id DESC LIMIT 10")->fetchAll();
if (empty($mvs)) {
    echo "<p class='warn'>⚠️ কোনো stock movement নেই।</p>";
} else {
    echo "<table><tr><th>Product</th><th>Type</th><th>Qty Change</th><th>Location</th><th>Ref ID</th><th>Date</th></tr>";
    foreach ($mvs as $m)
        echo "<tr><td>".htmlspecialchars($m['name']??'?')."</td><td>{$m['type']}</td><td class='ok'>+{$m['qty_change']}</td><td>{$m['location']}</td><td>{$m['reference_id']}</td><td>{$m['created_at']}</td></tr>";
    echo "</table>";
}

// ── 8. LIVE UPSERT TEST ───────────────────────────────────────────────────────
echo "<h3>8. LIVE UPSERT TEST (1 unit তে যোগ হবে একটা random product এ)</h3>";
if (isset($_GET['test_upsert'])) {
    $pid = $pdo->query("SELECT id, name FROM products WHERE is_active=1 LIMIT 1")->fetch();
    if ($pid) {
        try {
            $pdo->beginTransaction();
            // Check if row exists
            $check = $pdo->prepare("SELECT id, warehouse_qty FROM inventory WHERE product_id = ?");
            $check->execute([$pid['id']]);
            $inv = $check->fetch();

            if ($inv) {
                $pdo->prepare("UPDATE inventory SET warehouse_qty = warehouse_qty + 1 WHERE product_id = ?")
                    ->execute([$pid['id']]);
                echo "<p class='ok'>✅ UPDATE done — {$pid['name']} (id={$pid['id']}) warehouse_qty was {$inv['warehouse_qty']}, now +1</p>";
            } else {
                $pdo->prepare("INSERT INTO inventory (product_id, warehouse_qty, display_qty) VALUES (?, 1, 0)")
                    ->execute([$pid['id']]);
                echo "<p class='ok'>✅ INSERT done — {$pid['name']} (id={$pid['id']}) নতুন inventory row তৈরি হয়েছে</p>";
            }
            $pdo->commit();
            echo "<p>🔄 <a href='debug_inventory.php' style='color:#60a5fa'>Refresh করুন</a> inventory দেখতে</p>";
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "<p class='err'>❌ UPSERT Error: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    } else {
        echo "<p class='err'>❌ কোনো active product নেই!</p>";
    }
} else {
    echo "<p><a href='?test_upsert=1' style='background:#16a34a;color:white;padding:8px 16px;border-radius:6px;text-decoration:none'>▶ Test UPSERT করুন</a></p>";
}

echo "<br><hr><p style='color:#6b7280'>Debug complete। <a href='purchases.php' style='color:#60a5fa'>purchases.php এ যান</a> | <a href='migrate.php' style='color:#f59e0b'>migrate.php চালান</a></p>";
echo "</body></html>";
?>
