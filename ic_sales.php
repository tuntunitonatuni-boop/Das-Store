<?php
// ic_sales.php — Wholesale Sales / Memo
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/flash.php';
require_login();
$page_title = 'Wholesale Sales';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_sale') {
    $customer_id      = (int)($_POST['customer_id'] ?? 0);
    $discount         = (float)($_POST['discount'] ?? 0);
    $transport_charge = (float)($_POST['transport_charge'] ?? 0);
    $paid_amount      = (float)($_POST['paid_amount'] ?? 0);
    $item_ids         = $_POST['item_id'] ?? [];
    $qtys             = $_POST['qty'] ?? [];
    $selling_prices   = $_POST['selling_price'] ?? [];

    if (!$customer_id || empty($item_ids)) {
        set_flash('error', 'Customer and items are required.');
        header('Location: ic_sales.php'); exit;
    }

    $pdo->beginTransaction();
    try {
        $invoice_no = 'IC-SL-' . date('Ymd') . '-' . rand(1000, 9999);
        $subtotal = 0;

        foreach ($item_ids as $k => $id) {
            if (!$id || empty($qtys[$k])) continue;
            $subtotal += (float)$qtys[$k] * (float)$selling_prices[$k];
        }

        $grand_total = $subtotal - $discount + $transport_charge;

        // Insert Sale Record
        $stmt = $pdo->prepare("INSERT INTO ic_sales (invoice_no, customer_id, subtotal, discount, transport_charge, grand_total, paid_amount) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$invoice_no, $customer_id, $subtotal, $discount, $transport_charge, $grand_total, $paid_amount]);
        $sale_id = $pdo->lastInsertId();

        // Update Customer Balance (Credit sale)
        $due = $grand_total - $paid_amount;
        if ($due > 0) {
            $pdo->prepare("UPDATE ic_customers SET balance = balance + ? WHERE id = ?")->execute([$due, $customer_id]);
        } elseif ($due < 0) {
            $pdo->prepare("UPDATE ic_customers SET balance = balance - ? WHERE id = ?")->execute([abs($due), $customer_id]);
        }

        // Process Items
        foreach ($item_ids as $k => $item_id) {
            if (!$item_id || empty($qtys[$k])) continue;
            
            $q = (float)$qtys[$k];
            $sp = (float)$selling_prices[$k];
            $item_subtotal = $q * $sp;

            // Get Current Avg Cost & Stock
            $itm = $pdo->prepare("SELECT avg_buy_price, stock_qty FROM ic_items WHERE id = ? FOR UPDATE");
            $itm->execute([$item_id]);
            $current = $itm->fetch();
            $avg_cost = (float)$current['avg_buy_price'];
            
            // Profit Calculation (Sales Price - Cost Price)
            $profit = $item_subtotal - ($q * $avg_cost);

            // Insert Sale Item
            $pdo->prepare("INSERT INTO ic_sale_items (sale_id, item_id, qty, unit_price, subtotal, avg_buy_price_at_sale, profit) VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute([$sale_id, $item_id, $q, $sp, $item_subtotal, $avg_cost, $profit]);

            // Deduct Stock
            $new_stock = (float)$current['stock_qty'] - $q;
            $pdo->prepare("UPDATE ic_items SET stock_qty = ? WHERE id = ?")->execute([$new_stock, $item_id]);
        }

        $pdo->commit();
        set_flash('success', "Sale Invoice $invoice_no created successfully!");
    } catch (\Exception $e) {
        $pdo->rollBack();
        set_flash('error', "Failed: " . $e->getMessage());
    }
    header('Location: ic_sales.php'); exit;
}

// Fetch lists
$sales = $pdo->query("SELECT s.*, c.name as customer_name FROM ic_sales s LEFT JOIN ic_customers c ON c.id = s.customer_id ORDER BY s.created_at DESC LIMIT 50")->fetchAll();
$customers = $pdo->query("SELECT id, name FROM ic_customers ORDER BY name")->fetchAll();
$items     = $pdo->query("SELECT id, name, unit, avg_buy_price, stock_qty FROM ic_items WHERE stock_qty > 0 ORDER BY name")->fetchAll();

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="page-header flex items-center justify-between">
    <div>
        <h1 class="dark:text-white">Wholesale Sales / Memo</h1>
        <p class="dark:text-gray-400">Create invoices and track profits</p>
    </div>
    <button onclick="document.getElementById('s-modal').classList.remove('hidden')" class="btn btn-primary">+ New Sale Memo</button>
</div>

<!-- Sales History List -->
<div class="card dark:bg-gray-800 dark:border-gray-700 p-0 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="data-table dark:text-gray-300 w-full text-left border-collapse">
            <thead class="bg-gray-50 dark:bg-gray-900/50 text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wider border-b border-gray-100 dark:border-gray-700">
                <tr>
                    <th class="p-4 font-semibold">Invoice No</th>
                    <th class="p-4 font-semibold">Customer</th>
                    <th class="p-4 font-semibold text-right">Items Value</th>
                    <th class="p-4 font-semibold text-right">Disc / Trans</th>
                    <th class="p-4 font-semibold text-right">Grand Total</th>
                    <th class="p-4 font-semibold text-center">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                <?php foreach ($sales as $s): 
                    $due = $s['grand_total'] - $s['paid_amount'];
                ?>
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/20 transition-colors">
                    <td class="p-4 font-mono font-bold text-brand-600 dark:text-brand-400"><?= htmlspecialchars($s['invoice_no']) ?></td>
                    <td class="p-4 font-medium text-gray-900 dark:text-gray-100"><?= htmlspecialchars($s['customer_name']) ?></td>
                    <td class="p-4 text-right text-gray-500 dark:text-gray-400"><?= CURRENCY . number_format($s['subtotal'], 2) ?></td>
                    <td class="p-4 text-right">
                        <?php if($s['discount']>0): ?><span class="text-red-500 block">- <?= CURRENCY.number_format($s['discount'],2) ?></span><?php endif; ?>
                        <?php if($s['transport_charge']>0): ?><span class="text-orange-500 block">+ <?= CURRENCY.number_format($s['transport_charge'],2) ?></span><?php endif; ?>
                    </td>
                    <td class="p-4 text-right font-bold text-gray-900 dark:text-gray-100"><?= CURRENCY . number_format($s['grand_total'], 2) ?></td>
                    <td class="p-4 text-center">
                        <?php if ($due <= 0): ?>
                            <span class="badge badge-green">Paid</span>
                        <?php else: ?>
                            <span class="badge badge-red">Due: <?= CURRENCY.number_format($due,2) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($sales)): ?>
                <tr><td colspan="6" class="p-8 text-center text-gray-400">No sales memos found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- New Sale Modal -->
<div id="s-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-start justify-center pt-10 overflow-y-auto px-4">
    <div class="bg-white dark:bg-gray-800 rounded-2xl w-full max-w-5xl shadow-2xl my-4">
        <div class="flex items-center justify-between p-5 border-b border-gray-100 dark:border-gray-700">
            <h2 class="font-bold text-lg dark:text-white">Create Wholesale Memo</h2>
            <button type="button" onclick="document.getElementById('s-modal').classList.add('hidden')" class="text-gray-400 text-2xl hover:text-red-500">&times;</button>
        </div>
        
        <form method="post" class="p-5 flex flex-col gap-6" id="sale-form">
            <input type="hidden" name="action" value="create_sale">
            
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 bg-emerald-50/50 dark:bg-emerald-900/10 p-4 border border-emerald-100 dark:border-emerald-900/30 rounded-xl">
                <div class="md:col-span-2">
                    <label class="form-label dark:text-emerald-300">Customer *</label>
                    <select name="customer_id" required class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white border-emerald-200">
                        <option value="">— Select Customer —</option>
                        <?php foreach ($customers as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label dark:text-emerald-300">Discount Given</label>
                    <input type="number" step="0.01" id="global_discount" name="discount" value="0" class="form-control dark:bg-gray-700 dark:border-gray-600 text-red-600 font-bold border-red-200" oninput="calculateTotals()">
                </div>
                <div>
                    <label class="form-label dark:text-emerald-300">Transport Charge</label>
                    <input type="number" step="0.01" id="global_trans" name="transport_charge" value="0" class="form-control dark:bg-gray-700 dark:border-gray-600 text-orange-600 font-bold border-orange-200" oninput="calculateTotals()">
                </div>
            </div>

            <!-- Items Section -->
            <div class="border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                <div class="bg-gray-50 dark:bg-gray-800 p-3 flex justify-between items-center border-b border-gray-200 dark:border-gray-700">
                    <h3 class="font-semibold text-sm dark:text-gray-300 flex items-center gap-2">🛒 Select Items</h3>
                    <button type="button" onclick="addRow()" class="btn btn-xs bg-white border border-gray-300 shadow-sm text-gray-700 hover:bg-gray-50">+ Add Line Item</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-100 dark:bg-gray-900/50 text-gray-600 dark:text-gray-400">
                            <tr>
                                <th class="p-2 w-1/3">Ingredient</th>
                                <th class="p-2 text-right w-24">Stock & Cost</th>
                                <th class="p-2 text-right w-24">Qty</th>
                                <th class="p-2 text-right w-32">Selling Price</th>
                                <th class="p-2 text-right font-bold w-32">Subtotal</th>
                                <th class="p-2 w-10"></th>
                            </tr>
                        </thead>
                        <tbody id="items-body" class="divide-y divide-gray-100 dark:divide-gray-800">
                            <!-- JS injected rows -->
                        </tbody>
                        <tfoot class="bg-gray-50 dark:bg-gray-900/30 font-bold">
                            <tr>
                                <td colspan="4" class="p-3 text-right text-gray-500 dark:text-gray-400">Item Subtotal:</td>
                                <td class="p-3 text-right text-gray-900 dark:text-gray-100" id="footer-subtotal">0.00</td>
                                <td></td>
                            </tr>
                            <tr>
                                <td colspan="4" class="p-3 text-right text-gray-500 dark:text-gray-400">Grand Total:</td>
                                <td class="p-3 text-right text-brand-600 text-lg" id="footer-grand">0.00</td>
                                <td></td>
                            </tr>
                            <tr class="bg-emerald-50 dark:bg-emerald-900/20">
                                <td colspan="4" class="p-3 text-right text-gray-700 dark:text-emerald-400">Amount Paid Now:</td>
                                <td class="p-2">
                                    <input type="number" step="0.01" name="paid_amount" id="paid_amount" value="0" class="form-control text-right dark:bg-gray-700 dark:border-gray-600 text-brand-600 font-bold border-brand-200">
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="flex justify-between items-center bg-gray-50 dark:bg-gray-900 p-4 rounded-xl border border-gray-100 dark:border-gray-800">
                <div class="text-sm text-gray-500">
                    * Profit is calculated behind the scenes comparing Avg Cost vs Selling Price.
                </div>
                <div class="flex gap-3">
                    <button type="button" onclick="document.getElementById('s-modal').classList.add('hidden')" class="btn bg-white border border-gray-300 text-gray-700 px-6 py-2 rounded-lg font-medium hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="btn btn-primary px-8 py-2 rounded-lg font-bold text-lg shadow-lg shadow-brand-500/30">Generate Memo</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
const ITEMS = <?= json_encode(array_map(fn($i) => ['id' => $i['id'], 'name' => $i['name'], 'unit' => $i['unit'], 'cost'=> $i['avg_buy_price'], 'stock'=> $i['stock_qty']], $items)) ?>;
const CURRENCY = '<?= CURRENCY ?>';
let rowCounter = 0;

function addRow() {
    rowCounter++;
    const options = ITEMS.map(i => `<option value="${i.id}" data-cost="${i.cost}" data-stock="${i.stock}">
        ${i.name}
    </option>`).join('');
    
    const tr = document.createElement('tr');
    tr.id = `row-${rowCounter}`;
    tr.className = 'dark:bg-gray-800 focus-within:bg-blue-50 dark:focus-within:bg-gray-700 transition-colors duration-150';
    tr.innerHTML = `
        <td class="p-2">
            <select name="item_id[]" required class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-md focus:ring-brand-500 focus:border-brand-500 h-9 row-item" onchange="itemSelected(${rowCounter})">
                <option value="">— Select —</option>
                ${options}
            </select>
        </td>
        <td class="p-2 text-right text-xs text-gray-500">
            <div class="row-stock-info font-mono font-medium">--</div>
            <div class="row-cost-info text-blue-500 font-mono">--</div>
        </td>
        <td class="p-2">
            <input type="number" step="0.01" min="0.01" name="qty[]" required class="row-qty w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-md text-right focus:ring-brand-500 focus:border-brand-500 h-9" value="1" oninput="calculateRow(${rowCounter})">
        </td>
        <td class="p-2">
            <input type="number" step="0.01" min="0" name="selling_price[]" required class="row-price w-full text-sm border-green-300 dark:border-green-800/50 bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 rounded-md text-right focus:ring-green-500 focus:border-green-500 h-9 font-bold" placeholder="0.00" oninput="calculateRow(${rowCounter})">
        </td>
        <td class="p-2 text-right font-mono font-bold text-gray-900 dark:text-gray-100 flex items-center justify-end h-10">
            <span class="row-subtotal">0.00</span>
        </td>
        <td class="p-2 text-center">
            <button type="button" onclick="document.getElementById('row-${rowCounter}').remove(); calculateTotals();" class="text-gray-400 hover:text-red-500 p-1">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
            </button>
        </td>
    `;
    document.getElementById('items-body').appendChild(tr);
}

function itemSelected(id) {
    const tr = document.getElementById(`row-${id}`);
    const sel = tr.querySelector('.row-item');
    const opt = sel.options[sel.selectedIndex];
    
    if (opt && opt.value) {
        tr.querySelector('.row-stock-info').textContent = "Stock: " + parseFloat(opt.dataset.stock).toFixed(2);
        tr.querySelector('.row-cost-info').textContent = "Avg Buy: " + parseFloat(opt.dataset.cost).toFixed(2);
        // Do not auto-fill selling price, let user decide based on Avg Buy price displayed
    } else {
        tr.querySelector('.row-stock-info').textContent = "--";
        tr.querySelector('.row-cost-info').textContent = "--";
    }
}

function calculateRow(id) {
    const tr = document.getElementById(`row-${id}`);
    if(!tr) return;
    
    const qty = parseFloat(tr.querySelector('.row-qty').value) || 0;
    const price = parseFloat(tr.querySelector('.row-price').value) || 0;
    
    const sub = qty * price;
    tr.querySelector('.row-subtotal').textContent = sub.toFixed(2);
    
    calculateTotals();
}

function calculateTotals() {
    let t_sub = 0;
    
    document.querySelectorAll('.row-subtotal').forEach(el => t_sub += parseFloat(el.textContent));
    
    const disc = parseFloat(document.getElementById('global_discount').value) || 0;
    const trans = parseFloat(document.getElementById('global_trans').value) || 0;
    
    const grand = t_sub - disc + trans;
    
    document.getElementById('footer-subtotal').textContent = t_sub.toFixed(2);
    document.getElementById('footer-grand').textContent = grand.toFixed(2);
    
    // Auto fill paid amount for convenience
    // document.getElementById('paid_amount').value = grand.toFixed(2);
}

// Add 2 rows by default
addRow(); addRow();
</script>

<?php require_once 'includes/footer.php'; ?>
