<?php
// pos-ic.php — Dedicated POS for Ice Cream Wholesale / Medicine Selling
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/flash.php';
require_login();
$page_title = 'IC Wholesale POS';

// Handle sale submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'checkout') {
    $customer_id = (int)($_POST['customer_id'] ?? 0) ?: null;
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $notes       = trim($_POST['notes'] ?? '');
    $items       = json_decode($_POST['cart_json'] ?? '[]', true);
    $is_credit   = isset($_POST['is_credit']) ? 1 : 0;

    if (!empty($items)) {
        $pdo->beginTransaction();
        try {
            $total = 0;
            foreach ($items as $item) {
                $total += (float)$item['price'] * (float)$item['qty'];
            }

            $memo_no = 'WS-' . date('Ymd') . '-' . rand(100,999);
            $pdo->prepare("INSERT INTO ic_sales (memo_no, customer_id, total, payment_method, notes, is_credit, user_id)
                           VALUES (?,?,?,?,?,?,?)")
                ->execute([$memo_no, $customer_id, $total, $payment_method, $notes, $is_credit, current_user()['id']]);
            $sale_id = $pdo->lastInsertId();

            foreach ($items as $item) {
                $subtotal = (float)$item['price'] * (float)$item['qty'];
                $pdo->prepare("INSERT INTO ic_sale_items (sale_id, item_id, qty, rate, subtotal) VALUES (?,?,?,?,?)")
                    ->execute([$sale_id, $item['id'], $item['qty'], $item['price'], $subtotal]);
                // Deduct stock
                $pdo->prepare("UPDATE ic_items SET stock = GREATEST(0, stock - ?) WHERE id=?")
                    ->execute([$item['qty'], $item['id']]);
            }

            // Update customer balance if credit
            if ($is_credit && $customer_id) {
                $pdo->prepare("UPDATE ic_customers SET balance = balance + ? WHERE id=?")->execute([$total, $customer_id]);
            }

            $pdo->commit();
            set_flash('success', "✅ Memo #$memo_no — Total: " . CURRENCY . number_format($total, 2));
            header('Location: pos-ic.php'); exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('error', "Error: " . $e->getMessage());
        }
    }
}

// Data
$items     = $pdo->query("SELECT * FROM ic_items WHERE is_active=1 ORDER BY name")->fetchAll();
$customers = $pdo->query("SELECT * FROM ic_customers ORDER BY name")->fetchAll();
$recent    = $pdo->query("SELECT s.*, COALESCE(c.name,'Walk-in') as cname FROM ic_sales s LEFT JOIN ic_customers c ON c.id=s.customer_id ORDER BY s.created_at DESC LIMIT 10")->fetchAll();

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<style>
#ic-cart { min-height: 300px; }
.ic-item-card { cursor:pointer; transition: all .15s; }
.ic-item-card:hover { transform: scale(1.03); box-shadow: 0 8px 24px rgba(0,0,0,.15); }
.ic-item-card.in-cart { background: linear-gradient(135deg,#dbeafe,#ede9fe); }
.dark .ic-item-card.in-cart { background: linear-gradient(135deg, rgba(30,58,138,.4), rgba(76,29,149,.3)); }
</style>

<div class="flex items-center justify-between mb-4">
    <div>
        <h1 class="text-2xl font-black dark:text-white">🍦 IC Wholesale POS</h1>
        <p class="text-sm text-gray-400"><?= date('l, d F Y') ?> · <?= htmlspecialchars(current_user()['name']) ?></p>
    </div>
    <a href="pos.php" class="btn btn-secondary dark:bg-gray-700 text-sm">Switch to Regular POS</a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5" x-data="icPOS()">

    <!-- Product Grid -->
    <div class="lg:col-span-2">
        <!-- Search -->
        <div class="mb-3">
            <input type="text" x-model="search" placeholder="🔍 Search ingredients..." class="form-control dark:bg-gray-800 dark:border-gray-700 dark:text-white text-base py-3">
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 max-h-[60vh] overflow-y-auto pr-1">
            <?php foreach ($items as $item): ?>
            <div class="ic-item-card bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-3 shadow-sm select-none"
                 :class="{ 'in-cart': isInCart(<?= $item['id'] ?>) }"
                 x-show="'<?= strtolower(htmlspecialchars($item['name'])) ?>'.includes(search.toLowerCase())"
                 @click="addToCart(<?= $item['id'] ?>, '<?= addslashes($item['name']) ?>', <?= $item['rate'] ?>, '<?= $item['unit'] ?>', <?= $item['stock'] ?>)">
                <div class="w-10 h-10 rounded-xl bg-brand-100 dark:bg-brand-900/30 text-brand-600 dark:text-brand-400 flex items-center justify-center text-xl mb-2">🧊</div>
                <div class="font-bold text-xs dark:text-white leading-tight mb-1"><?= htmlspecialchars($item['name']) ?></div>
                <div class="text-brand-600 dark:text-brand-400 font-black text-sm"><?= CURRENCY . number_format($item['rate'], 2) ?><span class="text-xs font-normal text-gray-400">/<?= $item['unit'] ?></span></div>
                <div class="text-[10px] text-gray-400 mt-0.5">Stock: <?= number_format($item['stock'], 2) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Cart & Checkout -->
    <div class="flex flex-col gap-4">
        <!-- Customer -->
        <div class="card dark:bg-gray-800 dark:border-gray-700 p-4">
            <label class="form-label dark:text-gray-300 font-bold text-xs uppercase tracking-widest mb-2">Customer</label>
            <select x-model="customer_id" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm">
                <option value="">Walk-in Customer</option>
                <?php foreach ($customers as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> <?= $c['balance'] > 0 ? '(Due: '.CURRENCY.number_format($c['balance'],2).')' : '' ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Cart Items -->
        <div class="card dark:bg-gray-800 dark:border-gray-700 p-0 overflow-hidden flex-1" id="ic-cart">
            <div class="px-4 py-3 border-b dark:border-gray-700 font-bold dark:text-white text-sm flex justify-between items-center">
                <span>🛒 Cart (<span x-text="cart.length"></span> items)</span>
                <button @click="clearCart()" class="text-xs text-red-400 hover:text-red-600" x-show="cart.length > 0">Clear</button>
            </div>
            <div class="divide-y dark:divide-gray-700 max-h-64 overflow-y-auto" x-show="cart.length > 0">
                <template x-for="item in cart" :key="item.id">
                    <div class="px-3 py-2.5 flex items-center gap-2">
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-sm dark:text-white truncate" x-text="item.name"></div>
                            <div class="text-xs text-gray-400" x-text="'<?= CURRENCY ?>' + item.price.toFixed(2) + '/' + item.unit"></div>
                        </div>
                        <div class="flex items-center gap-1 shrink-0">
                            <button @click="adjustQty(item.id, -0.5)" class="w-6 h-6 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 font-bold text-sm hover:bg-red-100 dark:hover:bg-red-900/30 flex items-center justify-center">−</button>
                            <input type="number" step="0.001" :value="item.qty" @change="setQty(item.id, parseFloat($event.target.value) || 0.5)" class="w-14 text-center text-xs font-bold dark:bg-gray-700 dark:text-white border dark:border-gray-600 rounded-lg py-1">
                            <button @click="adjustQty(item.id, 0.5)" class="w-6 h-6 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 font-bold text-sm hover:bg-brand-100 dark:hover:bg-brand-900/30 flex items-center justify-center">+</button>
                        </div>
                        <div class="text-sm font-black dark:text-white w-16 text-right shrink-0" x-text="'<?= CURRENCY ?>' + (item.price * item.qty).toFixed(2)"></div>
                        <button @click="removeItem(item.id)" class="text-gray-300 hover:text-red-500 transition ml-1">✕</button>
                    </div>
                </template>
            </div>
            <div x-show="cart.length === 0" class="py-12 flex flex-col items-center text-gray-400">
                <span class="text-4xl mb-2">🛒</span>
                <p class="text-sm">Click items to add to cart</p>
            </div>

            <!-- Totals -->
            <div class="border-t dark:border-gray-700 p-4 bg-gray-50/50 dark:bg-gray-900/50 space-y-1.5 text-sm" x-show="cart.length > 0">
                <div class="flex justify-between font-black text-lg dark:text-white pt-1">
                    <span>Total</span><span class="text-brand-600 dark:text-brand-400" x-text="'<?= CURRENCY ?>' + total.toFixed(2)"></span>
                </div>
            </div>
        </div>

        <!-- Payment -->
        <div class="card dark:bg-gray-800 dark:border-gray-700 p-4 space-y-3" x-show="cart.length > 0">
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="form-label dark:text-gray-300 text-xs font-bold uppercase tracking-widest">Payment</label>
                    <select x-model="payment_method" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm">
                        <option value="cash">💵 Cash</option>
                        <option value="mobile">📱 bKash/Nagad</option>
                        <option value="credit">📋 Credit/Due</option>
                        <option value="bank">🏦 Bank</option>
                    </select>
                </div>
                <div class="flex flex-col justify-end">
                    <label class="flex items-center gap-2 cursor-pointer dark:text-gray-300 text-sm font-bold">
                        <input type="checkbox" x-model="is_credit" class="w-4 h-4 rounded accent-brand-600"> Mark as Due
                    </label>
                </div>
            </div>
            <textarea x-model="notes" rows="2" placeholder="Notes (optional)..." class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm"></textarea>
            <form method="post" @submit.prevent="submitSale($el)">
                <input type="hidden" name="action" value="checkout">
                <input type="hidden" name="customer_id" :value="customer_id">
                <input type="hidden" name="payment_method" :value="payment_method">
                <input type="hidden" name="notes" :value="notes">
                <input type="hidden" name="is_credit" :value="is_credit ? 1 : 0">
                <input type="hidden" name="cart_json" :value="JSON.stringify(cart)">
                <button type="submit" :disabled="cart.length === 0"
                        class="btn btn-primary w-full py-4 text-lg font-black shadow-xl shadow-brand-500/40 disabled:opacity-50 disabled:cursor-not-allowed rounded-2xl">
                    ✅ Complete Sale · <span x-text="'<?= CURRENCY ?>' + total.toFixed(2)"></span>
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Recent Sales -->
<div class="mt-6 card dark:bg-gray-800 dark:border-gray-700 p-0 overflow-hidden shadow-xl">
    <div class="px-5 py-4 border-b dark:border-gray-700 font-bold dark:text-white bg-gray-50/50 dark:bg-gray-900/50">🕒 Recent Wholesale Sales</div>
    <div class="overflow-x-auto">
        <table class="data-table dark:text-gray-300">
            <thead class="dark:bg-gray-900/50"><tr>
                <th class="dark:text-gray-400">Memo #</th>
                <th class="dark:text-gray-400">Customer</th>
                <th class="dark:text-gray-400">Payment</th>
                <th class="text-right dark:text-gray-400">Total</th>
                <th class="dark:text-gray-400">Time</th>
            </tr></thead>
            <tbody class="divide-y dark:divide-gray-700">
            <?php foreach ($recent as $s): ?>
            <tr class="dark:hover:bg-gray-700/40">
                <td class="font-mono font-bold dark:text-brand-400"><?= htmlspecialchars($s['memo_no']) ?></td>
                <td class="dark:text-gray-200"><?= htmlspecialchars($s['cname']) ?></td>
                <td><span class="badge badge-blue capitalize"><?= $s['payment_method'] ?><?= $s['is_credit'] ? ' (Due)' : '' ?></span></td>
                <td class="text-right font-black dark:text-white"><?= CURRENCY.number_format($s['total'],2) ?></td>
                <td class="text-xs text-gray-400"><?= date('d M H:i', strtotime($s['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($recent)): ?><tr><td colspan="5" class="text-center py-8 text-gray-400">No sales yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function icPOS() {
    return {
        cart: [], search: '', customer_id: '', payment_method: 'cash', notes: '', is_credit: false,
        get total() { return this.cart.reduce((s,i)=>s+i.price*i.qty, 0); },
        isInCart(id) { return this.cart.some(i=>i.id===id); },
        addToCart(id, name, price, unit, stock) {
            const existing = this.cart.find(i=>i.id===id);
            if (existing) { existing.qty += 1; }
            else { this.cart.push({id, name, price, unit, qty:1, max:stock}); }
        },
        adjustQty(id, delta) {
            const item = this.cart.find(i=>i.id===id);
            if (!item) return;
            item.qty = Math.max(0.001, parseFloat((item.qty + delta).toFixed(3)));
            if (item.qty <= 0) this.removeItem(id);
        },
        setQty(id, qty) {
            const item = this.cart.find(i=>i.id===id);
            if (!item) return;
            if (qty <= 0) { this.removeItem(id); return; }
            item.qty = qty;
        },
        removeItem(id) { this.cart = this.cart.filter(i=>i.id!==id); },
        clearCart() { if(confirm('Clear cart?')) this.cart = []; },
        submitSale(form) {
            if (this.cart.length === 0) return;
            if (!confirm('Complete this sale for <?= CURRENCY ?>' + this.total.toFixed(2) + '?')) return;
            form.querySelector('[name=cart_json]').value = JSON.stringify(this.cart);
            form.querySelector('[name=customer_id]').value = this.customer_id;
            form.querySelector('[name=payment_method]').value = this.payment_method;
            form.querySelector('[name=notes]').value = this.notes;
            form.querySelector('[name=is_credit]').value = this.is_credit ? 1 : 0;
            form.submit();
        }
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
