<?php
// pos.php — Full POS: barcode scan, cart, payment, receipt
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/flash.php';
require_login();
$page_title = 'POS';

$customers = $pdo->query("SELECT id, name, phone, balance FROM customers WHERE is_active=1 ORDER BY name")->fetchAll();

require_once 'includes/pos-header.php';
?>

<!-- POS alert -->
<div id="pos-alert" style="display:none" class="fixed top-16 right-4 z-50 px-4 py-2 rounded-xl text-sm font-medium shadow-lg"></div>

<!-- Pass PHP constants & translations to JS -->
<script>
var BASE_URL = <?= json_encode(BASE_URL) ?>;
var SHOP_NAME = <?= json_encode(SHOP_NAME) ?>;
var RECEIPT_FOOTER = <?= json_encode(RECEIPT_FOOTER) ?>;
var CURRENCY = <?= json_encode(CURRENCY) ?>;
var POS_LANG = {
    cart_empty: <?= json_encode(__('cart_empty')) ?>,
    not_found: <?= json_encode(__('no_products_found')) ?>,
    no_stock: <?= json_encode(__('out_of_stock')) ?>,
    plus_stock: <?= json_encode(__('low_stock_warning')) ?>,
    net_error: <?= json_encode(__('error_generic')) ?>,
    added: <?= json_encode(__('added_to_cart_msg')) ?>,
    failed: <?= json_encode(__('error_generic')) ?>,
    insufficient: <?= json_encode(__('error_required')) ?>,
    stock_label: <?= json_encode(__('stock')) ?>,
    no_barcode: <?= json_encode(__('sku_barcode')) ?>
};
</script>

<!-- POS Layout: Left = Product/Barcode, Right = Cart -->
<div class="flex h-full gap-0 overflow-hidden">
    <!-- LEFT PANEL -->
    <div id="pos-left" class="flex-1 flex flex-col bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700">
        <!-- Search bar -->
        <div class="p-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
            <div class="flex gap-2 relative" id="search-container">
                <div class="relative flex-1">
                    <input type="text" id="barcode-input" autofocus autocomplete="off"
                           placeholder="<?= __('pos_search_placeholder') ?>"
                           class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-xl px-4 py-2.5 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100 dark:focus:ring-brand-900/20 transition">
                    
                    <!-- Suggestions Dropdown -->
                    <div id="pos-search-results" class="absolute left-0 right-0 top-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-xl z-50 hidden max-h-80 overflow-y-auto">
                    </div>
                </div>
                <button onclick="POS.lookupBarcode()" class="bg-brand-600 text-white px-4 py-2 rounded-xl text-sm font-medium hover:bg-brand-700 transition shadow-sm"><?= __('add') ?></button>
            </div>
        </div>

        <!-- Quick product grid (latest 24 products) -->
        <div class="flex-1 overflow-y-auto p-3">
            <p class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase mb-3"><?= __('quick_select') ?></p>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
                <?php
                $quick = $pdo->query("SELECT p.id,p.name,p.sale_price,p.unit,p.barcode,COALESCE(i.display_qty+i.warehouse_qty,0) as stock FROM products p LEFT JOIN inventory i ON i.product_id=p.id WHERE p.is_active=1 AND (i.display_qty+i.warehouse_qty)>0 ORDER BY p.name LIMIT 48")->fetchAll();
                foreach ($quick as $q): ?>
                <button onclick='POS.addToCart(<?= json_encode(['id'=>$q['id'],'name'=>$q['name'],'sale_price'=>$q['sale_price'],'unit'=>$q['unit'],'stock'=>$q['stock']]) ?>)'
                        class="text-left p-2.5 bg-gray-50 dark:bg-gray-900/40 hover:bg-brand-50 dark:hover:bg-brand-900/20 hover:border-brand-300 dark:hover:border-brand-700 border border-gray-200 dark:border-gray-700 rounded-xl transition group">
                    <div class="text-xs font-medium text-gray-800 dark:text-gray-200 group-hover:text-brand-700 dark:group-hover:text-brand-400 leading-tight truncate"><?= htmlspecialchars($q['name']) ?></div>
                    <div class="text-sm font-bold text-brand-600 dark:text-brand-400 mt-1"><?= CURRENCY ?><?= number_format($q['sale_price'], 2) ?></div>
                    <div class="text-[10px] text-gray-400 dark:text-gray-500"><?= number_format($q['stock'], 1) ?> <?= $q['unit'] ?></div>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- RIGHT PANEL: Cart -->
    <div id="pos-right" class="w-96 flex flex-col bg-gray-50 dark:bg-gray-900/50">
        <!-- Cart header -->
        <div class="px-4 py-3 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <div>
                <span class="font-bold text-gray-900 dark:text-gray-100"><?= __('cart') ?></span>
                <span class="text-xs text-gray-400 ml-2">(<span id="cart-item-count">0</span> <?= __('items') ?>)</span>
            </div>
            <button onclick="POS.clearCart()" class="text-xs text-red-500 hover:text-red-700 transition font-medium"><?= __('clear_all') ?></button>
        </div>

        <!-- Cart items -->
        <div id="cart-items" class="flex-1 overflow-y-auto">
            <table class="w-full text-sm">
                <tbody id="cart-body">
                    <tr><td colspan="6" class="text-center text-gray-400 py-8"><?= __('cart_empty') ?></td></tr>
                </tbody>
            </table>
        </div>

        <!-- Order total -->
        <div class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 p-4 space-y-3 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]">
            <div class="flex items-center justify-between text-lg font-bold">
                <span class="dark:text-gray-300"><?= __('total') ?></span>
                <span id="cart-total" class="text-brand-700 dark:text-brand-400 text-2xl"><?= CURRENCY ?>0.00</span>
            </div>

            <!-- Customer select -->
            <select id="customer-select" class="form-control text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                <option value=""><?= __('walk_in_customer') ?></option>
                <?php foreach ($customers as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> <?= $c['balance'] > 0 ? '('.__('baki').': '.CURRENCY.number_format($c['balance'],2).')' : '' ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Payment method -->
            <div class="grid grid-cols-4 gap-1" id="pay-method-group">
                <?php foreach (['cash','card','mobile','credit'] as $m): ?>
                <button onclick="selectMethod(this,'<?= $m ?>')"
                        data-method="<?= $m ?>"
                        class="pay-method text-[10px] py-2 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-brand-400 font-bold uppercase transition <?= $m === 'cash' ? 'bg-brand-600 text-white border-brand-600' : 'bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300' ?>">
                    <?= __($m) ?>
                </button>
                <?php endforeach; ?>
            </div>

            <!-- Amount paid -->
            <div>
                <label class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase mb-1 block"><?= __('amount_received') ?> (<?= CURRENCY ?>)</label>
                <input type="number" id="amount-paid" step="0.01" min="0" placeholder="0.00"
                       class="form-control text-lg font-bold dark:bg-gray-700 dark:border-gray-600 dark:text-white" oninput="calcChange()">
            </div>
            <div class="flex items-center justify-between text-sm">
                <span class="text-gray-500 dark:text-gray-400 font-medium"><?= __('change') ?></span>
                <span id="change-display" class="font-bold text-gray-800 dark:text-gray-200"><?= CURRENCY ?>0.00</span>
            </div>

            <button onclick="completeSale()" id="checkout-btn"
                    class="w-full bg-brand-600 hover:bg-brand-700 active:scale-95 text-white font-bold py-3.5 rounded-xl text-lg transition shadow-lg shadow-brand-100 dark:shadow-none">
                ✓ <?= __('charge') ?> <?= CURRENCY ?><span id="btn-total">0.00</span>
            </button>
        </div>
    </div>
</div>

<!-- Receipt area (hidden, for print) -->
<div id="receipt-area" class="hidden"></div>

<script src="<?= BASE_URL ?>assets/js/pos.js?v=<?= time() ?>"></script>
<script>
let selectedMethod = 'cash';

function selectMethod(btn, method) {
    selectedMethod = method;
    document.querySelectorAll('.pay-method').forEach(b => {
        b.className = b.className.replace('bg-brand-600 text-white border-brand-600','bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-700');
    });
    btn.className = btn.className.replace('bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-700','bg-brand-600 text-white border-brand-600');
}

function calcChange() {
    const total = POS.getTotal();
    const paid  = parseFloat(document.getElementById('amount-paid').value) || 0;
    const change = Math.max(0, paid - total);
    document.getElementById('change-display').textContent = '<?= CURRENCY ?>' + change.toFixed(2);
}

// Override renderCart to also update btn-total
const _orig = POS.renderCart.bind(POS);
POS.renderCart = function() {
    _orig();
    const t = this.getTotal().toFixed(2);
    const el = document.getElementById('btn-total');
    if (el) el.textContent = t;
    calcChange();
};

function completeSale() {
    const paid  = parseFloat(document.getElementById('amount-paid').value) || 0;
    const cid   = document.getElementById('customer-select').value || null;
    const total = POS.getTotal();
    if (!total) { POS.showAlert('Cart is empty','error'); return; }
    if (selectedMethod !== 'credit' && paid < total) { POS.showAlert('Insufficient amount paid','error'); return; }
    POS.submitSale(selectedMethod, paid || total, cid);
}
</script>

</div><!-- /pt-12 -->
<script src="<?= BASE_URL ?>assets/js/main.js"></script>
</body>
</html>
