<?php
// ic_purchases.php — Wholesale Purchase Invoices
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/flash.php';
require_login();
$page_title = 'Wholesale Purchases';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_purchase') {
    $supplier_id    = (int)($_POST['supplier_id'] ?? 0);
    $transport_cost = (float)($_POST['transport_cost'] ?? 0);
    $paid_amount    = (float)($_POST['paid_amount'] ?? 0);
    $item_ids       = $_POST['item_id'] ?? [];
    $qtys           = $_POST['qty'] ?? [];
    $prices         = $_POST['unit_price'] ?? [];
    $alloc_trans    = $_POST['allocated_transport'] ?? []; // the manual or auto-allocated transport

    if (!$supplier_id || empty($item_ids)) {
        set_flash('error', 'Supplier and items are required.');
        header('Location: ic_purchases.php'); exit;
    }

    $pdo->beginTransaction();
    try {
        $invoice_no = 'IC-' . date('Ymd') . '-' . rand(1000, 9999);
        $total_amount = 0; // Pure item total
        $grand_total  = 0; // Item total + Transport

        // Calculate totals first
        foreach ($item_ids as $k => $id) {
            if (!$id || empty($qtys[$k])) continue;
            $sub = (float)$qtys[$k] * (float)$prices[$k];
            $total_amount += $sub;
            $grand_total += $sub + (float)($alloc_trans[$k] ?? 0);
        }

        // Insert Purchase Record
        $stmt = $pdo->prepare("INSERT INTO ic_purchases (invoice_no, supplier_id, total_amount, transport_cost, grand_total, paid_amount) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$invoice_no, $supplier_id, $total_amount, $transport_cost, $grand_total, $paid_amount]);
        $purchase_id = $pdo->lastInsertId();

        // Update Supplier Balance (Credit purchase)
        $due = $grand_total - $paid_amount;
        if ($due > 0) {
            $pdo->prepare("UPDATE ic_suppliers SET balance = balance + ? WHERE id = ?")->execute([$due, $supplier_id]);
        } elseif ($due < 0) {
            // Overpaid (Advance)
            $pdo->prepare("UPDATE ic_suppliers SET balance = balance - ? WHERE id = ?")->execute([abs($due), $supplier_id]);
        }

        // Process Items
        foreach ($item_ids as $k => $item_id) {
            if (!$item_id || empty($qtys[$k])) continue;
            
            $q = (float)$qtys[$k];
            $p = (float)$prices[$k];
            $t = (float)($alloc_trans[$k] ?? 0);
            $subtotal = $q * $p;
            $landed_cost = $subtotal + $t;
            $unit_landed_cost = $landed_cost / $q;

            // Insert Purchase Item
            $pdo->prepare("INSERT INTO ic_purchase_items (purchase_id, item_id, qty, unit_price, subtotal, allocated_transport, landed_cost) VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute([$purchase_id, $item_id, $q, $p, $subtotal, $t, $landed_cost]);

            // Weighted Average Cost & Stock Update
            // Get current stock and avg price
            $itm = $pdo->prepare("SELECT stock_qty, avg_buy_price FROM ic_items WHERE id = ?");
            $itm->execute([$item_id]);
            $current = $itm->fetch();

            $old_qty = (float)$current['stock_qty'];
            $old_avg = (float)$current['avg_buy_price'];

            // Prevent negative stock math anomalies
            $old_qty_calc = max(0, $old_qty);

            $new_qty = $old_qty + $q;
            $new_avg = (($old_qty_calc * $old_avg) + ($q * $unit_landed_cost)) / ($old_qty_calc + $q);

            $pdo->prepare("UPDATE ic_items SET stock_qty = ?, avg_buy_price = ? WHERE id = ?")
                ->execute([$new_qty, $new_avg, $item_id]);
        }

        $pdo->commit();
        set_flash('success', "Purchase Invoice $invoice_no created successfully!");
    } catch (\Exception $e) {
        $pdo->rollBack();
        set_flash('error', "Failed: " . $e->getMessage());
    }
    header('Location: ic_purchases.php'); exit;
}

// Fetch lists
$purchases = $pdo->query("SELECT p.*, s.name as supplier_name FROM ic_purchases p LEFT JOIN ic_suppliers s ON s.id = p.supplier_id ORDER BY p.created_at DESC LIMIT 50")->fetchAll();
$suppliers = $pdo->query("SELECT id, name FROM ic_suppliers ORDER BY name")->fetchAll();
$items     = $pdo->query("SELECT id, name, unit FROM ic_items ORDER BY name")->fetchAll();

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="page-header flex items-center justify-between">
    <div>
        <h1 class="dark:text-white">Wholesale Purchases</h1>
        <p class="dark:text-gray-400">Manage Ingredient Purchases & Transport Costs</p>
    </div>
    <button onclick="document.getElementById('p-modal').classList.remove('hidden')" class="btn btn-primary">+ New Purchase</button>
</div>

<!-- Purchase History List -->
<div class="card dark:bg-gray-800 dark:border-gray-700 p-0 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="data-table dark:text-gray-300 w-full text-left border-collapse">
            <thead class="bg-gray-50 dark:bg-gray-900/50 text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wider border-b border-gray-100 dark:border-gray-700">
                <tr>
                    <th class="p-4 font-semibold">Invoice No</th>
                    <th class="p-4 font-semibold">Supplier</th>
                    <th class="p-4 font-semibold text-right">Items Value</th>
                    <th class="p-4 font-semibold text-right">Transport</th>
                    <th class="p-4 font-semibold text-right">Grand Total</th>
                    <th class="p-4 font-semibold text-center">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                <?php foreach ($purchases as $p): 
                    $due = $p['grand_total'] - $p['paid_amount'];
                ?>
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/20 transition-colors">
                    <td class="p-4 font-mono font-bold text-brand-600 dark:text-brand-400"><?= htmlspecialchars($p['invoice_no']) ?></td>
                    <td class="p-4 font-medium text-gray-900 dark:text-gray-100"><?= htmlspecialchars($p['supplier_name']) ?></td>
                    <td class="p-4 text-right text-gray-500 dark:text-gray-400"><?= CURRENCY . number_format($p['total_amount'], 2) ?></td>
                    <td class="p-4 text-right text-orange-600 dark:text-orange-400">+ <?= CURRENCY . number_format($p['transport_cost'], 2) ?></td>
                    <td class="p-4 text-right font-bold text-gray-900 dark:text-gray-100"><?= CURRENCY . number_format($p['grand_total'], 2) ?></td>
                    <td class="p-4 text-center">
                        <?php if ($due <= 0): ?>
                            <span class="badge badge-green">Paid</span>
                        <?php else: ?>
                            <span class="badge badge-red">Due: <?= CURRENCY.number_format($due,2) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($purchases)): ?>
                <tr><td colspan="6" class="p-8 text-center text-gray-400">No purchases found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- New Purchase Modal -->
<div id="p-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-start justify-center pt-10 overflow-y-auto px-4">
    <div class="bg-white dark:bg-gray-800 rounded-2xl w-full max-w-5xl shadow-2xl my-4">
        <div class="flex items-center justify-between p-5 border-b border-gray-100 dark:border-gray-700">
            <h2 class="font-bold text-lg dark:text-white">New Purchase Invoice</h2>
            <button type="button" onclick="document.getElementById('p-modal').classList.add('hidden')" class="text-gray-400 text-2xl hover:text-red-500">&times;</button>
        </div>
        
        <form method="post" class="p-5 flex flex-col gap-6" id="purchase-form">
            <input type="hidden" name="action" value="create_purchase">
            
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 bg-blue-50/50 dark:bg-blue-900/10 p-4 border border-blue-100 dark:border-blue-900/30 rounded-xl">
                <div class="md:col-span-2">
                    <label class="form-label dark:text-blue-300">Supplier *</label>
                    <select name="supplier_id" required class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white border-blue-200">
                        <option value="">— Select Supplier —</option>
                        <?php foreach ($suppliers as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label dark:text-blue-300 text-orange-600">Total Transport Cost</label>
                    <input type="number" step="0.01" id="global_transport" name="transport_cost" value="0" class="form-control dark:bg-gray-700 dark:border-gray-600 text-orange-600 font-bold border-orange-200" oninput="distributeTransport()">
                    <p class="text-[10px] text-gray-500 mt-1">Value is auto-divided among items</p>
                </div>
                <div>
                    <label class="form-label dark:text-blue-300 text-green-600">Amount Paid (Now)</label>
                    <input type="number" step="0.01" name="paid_amount" value="0" class="form-control dark:bg-gray-700 dark:border-gray-600 text-green-600 font-bold border-green-200">
                </div>
            </div>

            <!-- Items Section -->
            <div class="border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                <div class="bg-gray-50 dark:bg-gray-800 p-3 flex justify-between items-center border-b border-gray-200 dark:border-gray-700">
                    <h3 class="font-semibold text-sm dark:text-gray-300 flex items-center gap-2">🛒 Items & Transport Allocation</h3>
                    <button type="button" onclick="addRow()" class="btn btn-xs bg-white border border-gray-300 shadow-sm text-gray-700 hover:bg-gray-50">+ Add Line Item</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-gray-100 dark:bg-gray-900/50 text-gray-600 dark:text-gray-400">
                            <tr>
                                <th class="p-2 w-1/4">Ingredient</th>
                                <th class="p-2 text-right w-24">Qty</th>
                                <th class="p-2 text-right w-32">Unit Price</th>
                                <th class="p-2 text-right w-32">Subtotal</th>
                                <th class="p-2 text-right w-32 text-orange-600">+ Transport</th>
                                <th class="p-2 text-right font-bold w-32 text-brand-600">= Landed Cost</th>
                                <th class="p-2 w-16"></th>
                            </tr>
                        </thead>
                        <tbody id="items-body" class="divide-y divide-gray-100 dark:divide-gray-800">
                            <!-- JS injected rows -->
                        </tbody>
                        <tfoot class="bg-gray-50 dark:bg-gray-900/30 font-bold">
                            <tr>
                                <td colspan="3" class="p-3 text-right text-gray-500 dark:text-gray-400">Total Calculation:</td>
                                <td class="p-3 text-right" id="footer-subtotal">0.00</td>
                                <td class="p-3 text-right text-orange-600" id="footer-transport">0.00</td>
                                <td class="p-3 text-right text-brand-600 text-lg" id="footer-grand">0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="flex justify-between items-center bg-gray-50 dark:bg-gray-900 p-4 rounded-xl border border-gray-100 dark:border-gray-800">
                <div class="text-sm text-gray-500">
                    * Landed Cost is automatically used to update Average Buying Price safely.<br>
                    * Supplier balance will be updated based on Paid Amount vs Grand Total.
                </div>
                <div class="flex gap-3">
                    <button type="button" onclick="document.getElementById('p-modal').classList.add('hidden')" class="btn bg-white border border-gray-300 text-gray-700 px-6 py-2 rounded-lg font-medium hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="btn btn-primary px-8 py-2 rounded-lg font-bold text-lg shadow-lg shadow-brand-500/30">Save Purchase & Update Stock</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
const ITEMS = <?= json_encode(array_map(fn($i) => ['id' => $i['id'], 'name' => $i['name'], 'unit' => $i['unit']], $items)) ?>;
const CURRENCY = '<?= CURRENCY ?>';
let rowCounter = 0;

function addRow() {
    rowCounter++;
    const options = ITEMS.map(i => `<option value="${i.id}">${i.name} (${i.unit})</option>`).join('');
    
    const tr = document.createElement('tr');
    tr.id = `row-${rowCounter}`;
    tr.className = 'dark:bg-gray-800 focus-within:bg-blue-50 dark:focus-within:bg-gray-700 transition-colors duration-150';
    tr.innerHTML = `
        <td class="p-2">
            <select name="item_id[]" required class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-md focus:ring-brand-500 focus:border-brand-500 h-9 row-item" onchange="itemSelected(${rowCounter}, this)">
                <option value="">— Select —</option>
                ${options}
            </select>
            <div id="history-${rowCounter}" class="text-[10px] text-gray-500 mt-1 max-h-24 overflow-y-auto w-48"></div>
        </td>
        <td class="p-2">
            <input type="number" step="0.01" min="0.01" name="qty[]" required class="row-qty w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-md text-right focus:ring-brand-500 focus:border-brand-500 h-9" value="1" oninput="calculateRow(${rowCounter})">
        </td>
        <td class="p-2">
            <input type="number" step="0.01" min="0" name="unit_price[]" required class="row-price w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-md text-right focus:ring-brand-500 focus:border-brand-500 h-9" placeholder="0.00" oninput="calculateRow(${rowCounter})">
        </td>
        <td class="p-2 text-right font-mono text-gray-700 dark:text-gray-300 flex items-center justify-end h-10">
            <span class="row-subtotal">0.00</span>
        </td>
        <td class="p-2">
            <input type="number" step="0.01" min="0" name="allocated_transport[]" class="row-transport w-full text-sm border-orange-300 dark:border-orange-800/50 bg-orange-50 dark:bg-orange-900/20 text-orange-700 dark:text-orange-400 rounded-md text-right focus:ring-orange-500 focus:border-orange-500 font-bold h-9" value="0" oninput="manualTransportEdit()">
        </td>
        <td class="p-2 text-right font-mono font-bold text-brand-600 flex items-center justify-end h-10">
            <span class="row-landed">0.00</span>
        </td>
        <td class="p-2 text-center">
            <button type="button" onclick="document.getElementById('row-${rowCounter}').remove(); calculateTotals();" class="text-gray-400 hover:text-red-500 p-1">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
            </button>
        </td>
    `;
    document.getElementById('items-body').appendChild(tr);
}

// Basic calculation for a single row Subtotal & Landed Cost
function calculateRow(id) {
    const tr = document.getElementById(`row-${id}`);
    if(!tr) return;
    
    const qty = parseFloat(tr.querySelector('.row-qty').value) || 0;
    const price = parseFloat(tr.querySelector('.row-price').value) || 0;
    const transport = parseFloat(tr.querySelector('.row-transport').value) || 0;
    
    const sub = qty * price;
    const landed = sub + transport;
    
    tr.querySelector('.row-subtotal').textContent = sub.toFixed(2);
    tr.querySelector('.row-landed').textContent = landed.toFixed(2);
    
    distributeTransport(); // Re-run distribution if subtotal changed
}

async function itemSelected(rowId, selectElem) {
    const itemId = selectElem.value;
    const historyDiv = document.getElementById(`history-${rowId}`);
    
    if (!itemId) {
        historyDiv.innerHTML = '';
        return;
    }
    
    historyDiv.innerHTML = '<span class="text-blue-500">Loading history...</span>';
    try {
        const res = await fetch(`api/ic_item_history.php?item_id=${itemId}`);
        const data = await res.json();
        
        if (data.success && data.history.length > 0) {
            let h = `<div class="font-semibold text-brand-600 mb-1">Last ${data.history.length} Rates:</div>`;
            data.history.forEach(r => {
                h += `<div>${r.date}: <b>${r.unit_price}</b>/unit <span class="opacity-60">(${r.supplier_name})</span></div>`;
            });
            historyDiv.innerHTML = h;
        } else {
            historyDiv.innerHTML = '<span class="opacity-50">No purchase history.</span>';
        }
    } catch(e) {
        historyDiv.innerHTML = '<span class="text-red-500">Error loading history</span>';
    }
}

// Option 1: Distribute Total Transport by Value
// Only automatically updates if manual hasn't radically changed it, but to keep it simple,
// we will just re-distribute the value whenever the global transport box or subtotals change.
// To allow FULL manual freedom, if they edit a row's transport, it will update the total.
let isDistributing = false;

function distributeTransport() {
    if(isDistributing) return;
    isDistributing = true;
    
    const global_t = parseFloat(document.getElementById('global_transport').value) || 0;
    
    let total_sub = 0;
    document.querySelectorAll('.row-subtotal').forEach(el => total_sub += parseFloat(el.textContent));
    
    if (total_sub > 0 && global_t >= 0) {
        document.querySelectorAll('tr[id^="row-"]').forEach(tr => {
            const sub = parseFloat(tr.querySelector('.row-subtotal').textContent) || 0;
            const ratio = sub / total_sub;
            const item_t = global_t * ratio;
            
            // Set input value
            tr.querySelector('.row-transport').value = item_t.toFixed(2);
            
            // Update landed cost visually
            const landed = sub + item_t;
            tr.querySelector('.row-landed').textContent = landed.toFixed(2);
        });
    } else {
        // Reset all row transports to 0 if total sub is 0 or global_t is cleared
        document.querySelectorAll('.row-transport').forEach(inp => inp.value = "0");
        document.querySelectorAll('tr[id^="row-"]').forEach(tr => {
            const sub = parseFloat(tr.querySelector('.row-subtotal').textContent) || 0;
            tr.querySelector('.row-landed').textContent = sub.toFixed(2);
        });
    }
    
    calculateTotals();
    isDistributing = false;
}

// If user manually types in the row transport box
function manualTransportEdit() {
    if(isDistributing) return;
    
    // Calculate new row landed cost visually
    document.querySelectorAll('tr[id^="row-"]').forEach(tr => {
        const sub = parseFloat(tr.querySelector('.row-subtotal').textContent) || 0;
        const trans = parseFloat(tr.querySelector('.row-transport').value) || 0;
        tr.querySelector('.row-landed').textContent = (sub + trans).toFixed(2);
    });
    
    // Recalculate global transport input based on sum of rows
    let manualTotal = 0;
    document.querySelectorAll('.row-transport').forEach(el => manualTotal += parseFloat(el.value)||0);
    
    // Update global box silently without triggering distribute loop
    isDistributing = true;
    document.getElementById('global_transport').value = manualTotal.toFixed(2);
    calculateTotals();
    isDistributing = false;
}

function calculateTotals() {
    let t_sub = 0;
    let t_trans = 0;
    let t_grand = 0;
    
    document.querySelectorAll('.row-subtotal').forEach(el => t_sub += parseFloat(el.textContent)||0);
    document.querySelectorAll('.row-transport').forEach(el => t_trans += parseFloat(el.value)||0);
    document.querySelectorAll('.row-landed').forEach(el => t_grand += parseFloat(el.textContent)||0);
    
    document.getElementById('footer-subtotal').textContent = t_sub.toFixed(2);
    document.getElementById('footer-transport').textContent = t_trans.toFixed(2);
    document.getElementById('footer-grand').textContent = t_grand.toFixed(2);
}

// Add 3 rows by default
addRow(); addRow(); addRow();
</script>

<?php require_once 'includes/footer.php'; ?>
