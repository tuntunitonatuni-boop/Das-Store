<?php
// returns.php — Manage customer and supplier product returns (Phase 2: invoice_ref)
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/features.php';
require_once 'includes/flash.php';
require_login();
$page_title = 'Returns';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type        = $_POST['type'];
    $product_id  = (int)$_POST['product_id'];
    $qty         = (float)$_POST['qty'];
    $refund      = (float)$_POST['refund_amount'];
    $reason      = trim($_POST['reason'] ?? '');
    $invoice_ref = trim($_POST['invoice_ref'] ?? '');

    $customer_id = (int)($_POST['customer_id'] ?? 0) ?: null;
    $dealer_id   = (int)($_POST['dealer_id'] ?? 0) ?: null;
    $sale_id     = (int)($_POST['sale_id'] ?? 0) ?: null;

    if ($product_id && $qty > 0) {
        $pdo->beginTransaction();
        try {
            // Save Return Record
            $pdo->prepare("INSERT INTO returns (type, product_id, customer_id, dealer_id, sale_id, invoice_ref, qty, refund_amount, reason, user_id) VALUES (?,?,?,?,?,?,?,?,?,?)")
                ->execute([$type, $product_id, $customer_id, $dealer_id, $sale_id, $invoice_ref, $qty, $refund, $reason, current_user()['id']]);
            $return_id = $pdo->lastInsertId();

            if ($type === 'customer_to_shop') {
                // Increase Shelf/Display qty
                $pdo->prepare("UPDATE inventory SET display_qty = display_qty + ? WHERE product_id=?")->execute([$qty, $product_id]);
                // Movement log
                $pdo->prepare("INSERT INTO stock_movements (product_id, type, qty_change, location, reference_id, user_id) VALUES (?, 'return', ?, 'shelf', ?, ?)")
                    ->execute([$product_id, $qty, $return_id, current_user()['id']]);
                
                // Optional: Reduce customer balance if it was a credit sale return
                if ($customer_id && $refund > 0) {
                    $pdo->prepare("UPDATE customers SET balance = GREATEST(0, balance - ?) WHERE id=?")->execute([$refund, $customer_id]);
                    $pdo->prepare("INSERT INTO customer_ledger (customer_id, type, amount, description) VALUES (?, 'credit', ?, ?)")
                        ->execute([$customer_id, $refund, "Product Return Refund: #$return_id"]);
                }
            } else {
                // shop_to_supplier
                // Decrease Warehouse qty
                $pdo->prepare("UPDATE inventory SET warehouse_qty = warehouse_qty - ? WHERE product_id=?")->execute([$qty, $product_id]);
                // Movement log
                $pdo->prepare("INSERT INTO stock_movements (product_id, type, qty_change, location, reference_id, user_id) VALUES (?, 'return', ?, 'warehouse', ?, ?)")
                    ->execute([$product_id, -$qty, $return_id, current_user()['id']]);

                // Reduce dealer balance (we owe them less)
                if ($dealer_id && $refund > 0) {
                    $pdo->prepare("UPDATE dealers SET balance = GREATEST(0, balance - ?) WHERE id=?")->execute([$refund, $dealer_id]);
                    $pdo->prepare("INSERT INTO supplier_ledger (supplier_id, type, amount, description) VALUES (?, 'credit', ?, ?)")
                        ->execute([$dealer_id, $refund, "Product Return to Supplier: #$return_id"]);
                }
            }

            $pdo->commit();
            set_flash('success', __('record_return') . ' ' . __('success'));
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('error', $e->getMessage());
        }
        header('Location: returns.php'); exit;
    }
}

$returns = $pdo->query("
    SELECT r.*, p.name as product_name, c.name as customer_name, d.name as dealer_name, u.name as user_name 
    FROM returns r 
    JOIN products p ON p.id=r.product_id 
    LEFT JOIN customers c ON c.id=r.customer_id 
    LEFT JOIN dealers d ON d.id=r.dealer_id 
    LEFT JOIN users u ON u.id=r.user_id 
    ORDER BY r.created_at DESC LIMIT 50
")->fetchAll();

$products = $pdo->query("SELECT id, name, unit FROM products WHERE is_active=1 ORDER BY name")->fetchAll();
$customers = $pdo->query("SELECT id, name FROM customers ORDER BY name")->fetchAll();
$dealers = $pdo->query("SELECT id, name FROM dealers WHERE is_active=1 ORDER BY name")->fetchAll();

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="page-header flex items-center justify-between">
    <div>
        <h1 class="dark:text-white"><?= __('returns') ?></h1>
        <p class="dark:text-gray-400"><?= __('manage_catalogue') ?></p>
    </div>
    <button onclick="document.getElementById('return-modal').classList.remove('hidden')" class="btn btn-primary shadow-lg shadow-brand-500/20">
        + <?= __('record_return') ?>
    </button>
</div>

<div class="card dark:bg-gray-800 dark:border-gray-700 p-0 overflow-hidden shadow-2xl">
    <div class="overflow-x-auto">
        <table class="data-table dark:text-gray-300">
            <thead class="dark:bg-gray-900/50">
                <tr>
                    <th class="dark:text-gray-400"><?= __('date') ?></th>
                    <th class="dark:text-gray-400"><?= __('return_type') ?></th>
                    <th class="dark:text-gray-400"><?= __('item') ?></th>
                    <th class="dark:text-gray-400"><?= __('qty') ?></th>
                    <th class="dark:text-gray-400"><?= __('customer') ?> / <?= __('suppliers') ?></th>
                    <th class="dark:text-gray-400">Invoice Ref</th>
                    <th class="text-right dark:text-gray-400"><?= __('refund') ?></th>
                    <th class="dark:text-gray-400"><?= __('reason') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
                <?php foreach ($returns as $r): ?>
                <tr class="dark:hover:bg-gray-700/50 transition-colors">
                    <td class="text-xs text-gray-400 dark:text-gray-500"><?= date('d M Y H:i', strtotime($r['created_at'])) ?></td>
                    <td>
                        <span class="badge <?= $r['type'] === 'customer_to_shop' ? 'badge-blue' : 'badge-orange' ?>">
                            <?= __($r['type']) ?>
                        </span>
                    </td>
                    <td class="font-semibold dark:text-white"><?= htmlspecialchars($r['product_name']) ?></td>
                    <td class="text-gray-600 dark:text-gray-300"><?= number_format($r['qty'], 2) ?></td>
                    <td class="text-xs dark:text-gray-400">
                        <?= $r['type'] === 'customer_to_shop' ? htmlspecialchars($r['customer_name'] ?? 'Walk-in') : htmlspecialchars($r['dealer_name'] ?? '—') ?>
                    </td>
                    <td class="text-xs font-mono font-bold text-brand-600 dark:text-brand-400"><?= htmlspecialchars($r['invoice_ref'] ?? '—') ?></td>
                    <td class="text-right font-bold text-emerald-600 dark:text-emerald-500">
                        <?= $r['refund_amount'] > 0 ? CURRENCY . number_format($r['refund_amount'], 2) : '—' ?>
                    </td>
                    <td class="text-xs italic text-gray-400 dark:text-gray-600"><?= htmlspecialchars($r['reason'] ?? '—') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($returns)): ?>
                <tr><td colspan="8" class="text-center text-gray-400 py-12"><?= __('no_data_period') ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Return Modal -->
<div id="return-modal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center px-4">
    <div class="bg-white dark:bg-gray-800 rounded-3xl w-full max-w-lg shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200">
        <div class="p-6 border-b dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/50 flex justify-between items-center">
            <h2 class="font-bold text-xl dark:text-white flex items-center gap-2">
                <span class="text-brand-500">🔄</span>
                <?= __('record_return') ?>
            </h2>
            <button onclick="document.getElementById('return-modal').classList.add('hidden')" class="text-gray-400 hover:text-white text-3xl">&times;</button>
        </div>
        
        <form method="post" class="p-6 space-y-5">
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="form-label dark:text-gray-300 font-bold uppercase text-[10px] tracking-widest"><?= __('return_type') ?></label>
                    <select name="type" id="ret-type" required onchange="toggleReturnFields()" class="form-control rounded-2xl dark:bg-gray-700 dark:border-gray-600 dark:text-white border-2">
                        <option value="customer_to_shop"><?= __('customer_to_shop') ?></option>
                        <option value="shop_to_supplier"><?= __('shop_to_supplier') ?></option>
                    </select>
                </div>
                
                <div class="col-span-2">
                    <label class="form-label dark:text-gray-300 font-bold uppercase text-[10px] tracking-widest"><?= __('item') ?></label>
                    <select name="product_id" required class="form-control rounded-2xl dark:bg-gray-700 dark:border-gray-600 dark:text-white border-2">
                        <option value="">— <?= __('select') ?> —</option>
                        <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> (<?= $p['unit'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label dark:text-gray-300 font-bold uppercase text-[10px] tracking-widest"><?= __('qty') ?></label>
                    <input type="number" step="0.001" name="qty" required class="form-control rounded-2xl dark:bg-gray-700 dark:border-gray-600 dark:text-white border-2" placeholder="0.00">
                </div>

                <div>
                    <label class="form-label dark:text-gray-300 font-bold uppercase text-[10px] tracking-widest"><?= __('refund_amount') ?></label>
                    <input type="number" step="0.01" name="refund_amount" class="form-control rounded-2xl dark:bg-gray-700 dark:border-gray-600 dark:text-white border-2" placeholder="0.00">
                </div>

                <div class="col-span-2">
                    <label class="form-label dark:text-gray-300 font-bold uppercase text-[10px] tracking-widest">📋 Invoice / Clip Number</label>
                    <input type="text" name="invoice_ref" class="form-control rounded-2xl dark:bg-gray-700 dark:border-gray-600 dark:text-white border-2 font-mono" placeholder="e.g. INV-20240403-001">
                    <p class="text-[10px] text-gray-400 mt-1">বিক্রির সময় যে invoice নম্বর দেওয়া হয়েছিল সেটা লিখুন।</p>
                </div>

                <div id="div-customer" class="col-span-2">
                    <label class="form-label dark:text-gray-300 font-bold uppercase text-[10px] tracking-widest"><?= __('customer') ?></label>
                    <select name="customer_id" class="form-control rounded-2xl dark:bg-gray-700 dark:border-gray-600 dark:text-white border-2">
                        <option value="">— <?= __('walk_in_customer') ?> —</option>
                        <?php foreach ($customers as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="div-dealer" class="col-span-2 hidden">
                    <label class="form-label dark:text-gray-300 font-bold uppercase text-[10px] tracking-widest"><?= __('suppliers') ?></label>
                    <select name="dealer_id" class="form-control rounded-2xl dark:bg-gray-700 dark:border-gray-600 dark:text-white border-2">
                        <option value="">— <?= __('select') ?> —</option>
                        <?php foreach ($dealers as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-span-2">
                    <label class="form-label dark:text-gray-300 font-bold uppercase text-[10px] tracking-widest"><?= __('reason') ?></label>
                    <textarea name="reason" rows="2" class="form-control rounded-2xl dark:bg-gray-700 dark:border-gray-600 dark:text-white border-2" placeholder="Damage, Expiry, Wrong item..."></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4">
                <button type="button" onclick="document.getElementById('return-modal').classList.add('hidden')" class="btn btn-secondary dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 py-3 rounded-2xl font-bold uppercase text-xs tracking-widest"><?= __('cancel') ?></button>
                <button type="submit" class="btn btn-primary py-3 rounded-2xl font-bold uppercase text-xs tracking-widest shadow-lg shadow-brand-500/30"><?= __('record_return') ?></button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleReturnFields() {
    const type = document.getElementById('ret-type').value;
    const divCust = document.getElementById('div-customer');
    const divDealer = document.getElementById('div-dealer');
    
    if (type === 'customer_to_shop') {
        divCust.classList.remove('hidden');
        divDealer.classList.add('hidden');
    } else {
        divCust.classList.add('hidden');
        divDealer.classList.remove('hidden');
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
