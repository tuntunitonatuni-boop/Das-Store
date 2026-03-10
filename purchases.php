<?php
// purchases.php — Supplier purchase entry
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/flash.php';
require_login();
$page_title = 'Purchases';

// Handle new purchase order save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_po') {
        $dealer_id  = (int)$_POST['dealer_id'] ?: null;
        $notes      = trim($_POST['notes'] ?? '');
        $ordered_at = $_POST['ordered_at'] ?: date('Y-m-d');
        $status     = $_POST['status'] ?? 'draft';
        $pids       = $_POST['product_id'] ?? [];
        $qtys       = $_POST['qty'] ?? [];
        $costs      = $_POST['cost_price'] ?? [];

        $total = 0;
        foreach ($pids as $i => $pid) {
            if (!$pid || !$qtys[$i]) continue;
            $total += $qtys[$i] * $costs[$i];
        }

        $po_number = 'PO-' . date('Ymd') . '-' . rand(100,999);
        $pdo->prepare("INSERT INTO purchase_orders (po_number,dealer_id,user_id,total,status,notes,ordered_at) VALUES (?,?,?,?,?,?,?)")
            ->execute([$po_number,$dealer_id,current_user()['id'],$total,$status,$notes,$ordered_at]);
        $po_id = $pdo->lastInsertId();

        foreach ($pids as $i => $pid) {
            if (!$pid || !$qtys[$i]) continue;
            $sub = $qtys[$i] * $costs[$i];
            $pdo->prepare("INSERT INTO purchase_order_items (po_id,product_id,qty,cost_price,subtotal) VALUES (?,?,?,?,?)")
                ->execute([$po_id,$pid,$qtys[$i],$costs[$i],$sub]);

            if ($status === 'received') {
                // Update inventory + price
                $pdo->prepare("UPDATE inventory SET warehouse_qty = warehouse_qty + ? WHERE product_id=?")->execute([$qtys[$i],$pid]);
                $pdo->prepare("UPDATE products SET cost_price=? WHERE id=?")->execute([$costs[$i],$pid]);
                $pdo->prepare("INSERT INTO stock_movements (product_id,type,qty_change,location,reference_id,user_id) VALUES (?,?,?,'warehouse',?,?)")
                    ->execute([$pid,'purchase',$qtys[$i],$po_id,current_user()['id']]);
            }
        }

        set_flash('success',"Purchase Order $po_number created.");
        header('Location: purchases.php'); exit;
    }

    if ($action === 'receive') {
        $po_id = (int)$_POST['po_id'];
        $pdo->prepare("UPDATE purchase_orders SET status='received', received_at=CURDATE() WHERE id=?")->execute([$po_id]);
        // Stock gets added — fetch items
        $items = $pdo->prepare("SELECT * FROM purchase_order_items WHERE po_id=?"); $items->execute([$po_id]); $items = $items->fetchAll();
        foreach ($items as $it) {
            $pdo->prepare("UPDATE inventory SET warehouse_qty=warehouse_qty+? WHERE product_id=?")->execute([$it['qty'],$it['product_id']]);
            $pdo->prepare("UPDATE products SET cost_price=? WHERE id=?")->execute([$it['cost_price'],$it['product_id']]);
            $pdo->prepare("INSERT INTO stock_movements (product_id,type,qty_change,location,reference_id,user_id) VALUES (?,?,'purchase','warehouse',?,?)")
                ->execute([$it['product_id'],$it['qty'],$po_id,current_user()['id']]);
        }
        set_flash('success','Purchase received. Inventory updated.'); header('Location: purchases.php'); exit;
    }
}

$orders = $pdo->query("SELECT po.*, d.name as dealer_name FROM purchase_orders po LEFT JOIN dealers d ON d.id=po.dealer_id ORDER BY po.created_at DESC LIMIT 50")->fetchAll();
$dealers   = $pdo->query("SELECT id, name FROM dealers WHERE is_active=1 ORDER BY name")->fetchAll();
$products  = $pdo->query("SELECT id, name, unit, cost_price FROM products WHERE is_active=1 ORDER BY name")->fetchAll();

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="page-header flex items-center justify-between">
    <div>
        <h1 class="dark:text-white"><?= __('purchases') ?></h1>
        <p class="dark:text-gray-400"><?= __('manage_purchases') ?></p>
    </div>
    <button onclick="document.getElementById('po-modal').classList.remove('hidden')" class="btn btn-primary" id="new-po-btn">+ <?= __('new_purchase_order') ?></button>
</div>

<!-- PO List -->
<div class="card dark:bg-gray-800 dark:border-gray-700 p-0 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="data-table dark:text-gray-300">
            <thead class="dark:bg-gray-900/50">
                <tr>
                    <th class="dark:text-gray-400"><?= __('po_number') ?></th>
                    <th class="dark:text-gray-400"><?= __('suppliers') ?></th>
                    <th class="dark:text-gray-400"><?= __('date') ?></th>
                    <th class="dark:text-gray-400"><?= __('status') ?></th>
                    <th class="text-right dark:text-gray-400"><?= __('total') ?></th>
                    <th class="dark:text-gray-400"><?= __('actions') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
                <?php foreach ($orders as $o): ?>
                <tr class="dark:hover:bg-gray-700/50">
                    <td class="font-mono font-semibold dark:text-brand-400"><?= htmlspecialchars($o['po_number']) ?></td>
                    <td class="dark:text-gray-100"><?= htmlspecialchars($o['dealer_name'] ?? '—') ?></td>
                    <td class="text-sm text-gray-500 dark:text-gray-500"><?= $o['ordered_at'] ?></td>
                    <td>
                        <span class="badge <?= match($o['status']){
                            'received'=>'badge-green','sent'=>'badge-blue','cancelled'=>'badge-red',default=>'badge-gray'
                        } ?>">
                            <?= __($o['status']) ?>
                        </span>
                    </td>
                    <td class="text-right font-semibold dark:text-gray-100"><?= CURRENCY . number_format($o['total'], 2) ?></td>
                    <td>
                        <?php if ($o['status'] !== 'received'): ?>
                        <form method="post" class="inline">
                            <input type="hidden" name="action" value="receive">
                            <input type="hidden" name="po_id" value="<?= $o['id'] ?>">
                            <button type="submit" class="btn btn-xs btn-primary" onclick="return confirm('<?= __('confirm_receive') ?>')"><?= __('receive') ?></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($orders)): ?>
                <tr><td colspan="6" class="text-center text-gray-400 py-8"><?= __('no_po_found') ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- New PO Modal -->
<div id="po-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-start justify-center pt-10 overflow-y-auto px-4">
    <div class="bg-white dark:bg-gray-800 rounded-2xl w-full max-w-3xl shadow-2xl my-4">
        <div class="flex items-center justify-between p-5 border-b dark:border-gray-700">
            <h2 class="font-bold text-lg dark:text-white"><?= __('new_purchase_order') ?></h2>
            <button onclick="document.getElementById('po-modal').classList.add('hidden')" class="text-gray-400 text-2xl">&times;</button>
        </div>
        <form method="post" class="p-5 space-y-4">
            <input type="hidden" name="action" value="create_po">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label dark:text-gray-300"><?= __('suppliers') ?></label>
                    <select name="dealer_id" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        <option value="">— <?= __('select') ?> —</option>
                        <?php foreach ($dealers as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label dark:text-gray-300"><?= __('order_date') ?></label>
                    <input type="date" name="ordered_at" value="<?= date('Y-m-d') ?>" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                </div>
                <div>
                    <label class="form-label dark:text-gray-300"><?= __('status') ?></label>
                    <select name="status" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        <option value="draft"><?= __('draft') ?></option>
                        <option value="sent"><?= __('sent') ?></option>
                        <option value="received"><?= __('received_update') ?></option>
                    </select>
                </div>
                <div>
                    <label class="form-label dark:text-gray-300"><?= __('notes') ?></label>
                    <input type="text" name="notes" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="<?= __('notes') ?>">
                </div>
            </div>

            <!-- Line Items -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-semibold text-sm dark:text-gray-300"><?= __('items') ?></h3>
                    <button type="button" onclick="addPORow()" class="btn btn-xs btn-outline dark:border-gray-600 dark:text-gray-400">+ <?= __('add_row') ?></button>
                </div>
                <div class="overflow-x-auto border dark:border-gray-700 rounded-lg">
                    <table class="w-full text-sm" id="po-items">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="text-left p-2 dark:text-gray-400"><?= __('products') ?></th>
                                <th class="p-2 text-right dark:text-gray-400"><?= __('qty') ?></th>
                                <th class="p-2 text-right dark:text-gray-400"><?= __('cost') ?> (<?= CURRENCY ?>)</th>
                                <th class="p-2 text-right dark:text-gray-400"><?= __('subtotal') ?></th>
                                <th class="p-2"></th>
                            </tr>
                        </thead>
                        <tbody id="po-item-body" class="divide-y dark:divide-gray-700"></tbody>
                    </table>
                </div>
                <div class="text-right font-bold mt-2 dark:text-white"><?= __('total') ?>: <?= CURRENCY ?><span id="po-total">0.00</span></div>
            </div>

            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('po-modal').classList.add('hidden')" class="btn btn-secondary dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600"><?= __('cancel') ?></button>
                <button type="submit" class="btn btn-primary"><?= __('save_po') ?></button>
            </div>
        </form>
    </div>
</div>

<script>
const CURRENCY_SYM = '<?= CURRENCY ?>';
const SELECT_TEXT = '— <?= __('select') ?> —';
const PO_PRODUCTS = <?= json_encode(array_map(fn($p)=>['id'=>$p['id'],'name'=>$p['name'],'unit'=>$p['unit'],'cost'=>$p['cost_price']], $products)) ?>;
let poRow = 0;

function addPORow() {
    poRow++;
    const opts = PO_PRODUCTS.map(p=>`<option value="${p.id}" data-cost="${p.cost}">${p.name} (${p.unit})</option>`).join('');
    const tr = document.createElement('tr');
    tr.id = 'po-row-'+poRow;
    tr.className = 'dark:bg-gray-800/50';
    tr.innerHTML = `
        <td class="p-1"><select name="product_id[]" class="form-control text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white" onchange="setPoPrice(this, ${poRow})"><option value="">${SELECT_TEXT}</option>${opts}</select></td>
        <td class="p-1"><input type="number" name="qty[]" min="0.001" step="0.001" class="form-control text-right text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white" oninput="calcPoRow(${poRow})" placeholder="0"></td>
        <td class="p-1"><input type="number" name="cost_price[]" step="0.01" class="form-control text-right text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white" id="po-cost-${poRow}" oninput="calcPoRow(${poRow})" placeholder="0"></td>
        <td class="p-1 text-right font-semibold pr-2 dark:text-gray-200" id="po-sub-${poRow}">0.00</td>
        <td class="p-1 text-center"><button type="button" onclick="document.getElementById('po-row-${poRow}').remove();calcPoTotal()" class="text-red-400 hover:text-red-600 text-xl">&times;</button></td>`;
    document.getElementById('po-item-body').appendChild(tr);
}

function setPoPrice(sel, row) {
    const opt = sel.selectedOptions[0];
    if (opt && opt.dataset.cost) {
        document.getElementById('po-cost-'+row).value = opt.dataset.cost;
        calcPoRow(row);
    }
}

function calcPoRow(row) {
    const tr = document.getElementById('po-row-'+row);
    if (!tr) return;
    const qty  = parseFloat(tr.querySelector('[name="qty[]"]')?.value)||0;
    const cost = parseFloat(document.getElementById('po-cost-'+row)?.value)||0;
    const sub  = document.getElementById('po-sub-'+row);
    if (sub) sub.textContent = (qty*cost).toFixed(2);
    calcPoTotal();
}

function calcPoTotal() {
    let t = 0;
    document.querySelectorAll('[id^="po-sub-"]').forEach(el=>t+=parseFloat(el.textContent)||0);
    document.getElementById('po-total').textContent = t.toFixed(2);
}

// Add one row by default
addPORow();
</script>

<?php require_once 'includes/footer.php'; ?>
