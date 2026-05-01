<?php
// purchases.php — Supplier purchase entry
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/flash.php';
require_login();
$page_title = 'Purchases';

// ── Check once if expiry_date column exists in purchase_order_items ──────────
$_has_expiry_col = false;
try {
    $cols = $pdo->query("SHOW COLUMNS FROM `purchase_order_items` LIKE 'expiry_date'")->fetch();
    $_has_expiry_col = !empty($cols);
} catch (Exception $e) { }

// ── Helper: UPSERT inventory (Insert if not exists, else Update) ─────────────
function upsert_inventory(PDO $pdo, int $pid, float $qty, int $po_id, int $user_id): void {
    $check = $pdo->prepare("SELECT id FROM inventory WHERE product_id = ?");
    $check->execute([$pid]);
    if ($check->fetch()) {
        $pdo->prepare("UPDATE inventory SET warehouse_qty = warehouse_qty + ? WHERE product_id = ?")
            ->execute([$qty, $pid]);
    } else {
        $pdo->prepare("INSERT INTO inventory (product_id, warehouse_qty, display_qty) VALUES (?, ?, 0)")
            ->execute([$pid, $qty]);
    }
    $pdo->prepare("INSERT INTO stock_movements (product_id, type, qty_change, location, reference_id, user_id) VALUES (?, 'purchase', ?, 'warehouse', ?, ?)")
        ->execute([$pid, $qty, $po_id, $user_id]);
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── CREATE NEW PURCHASE ORDER ─────────────────────────────────────────────
    if ($action === 'create_po') {
        $dealer_id  = (int)$_POST['dealer_id'] ?: null;
        $notes      = trim($_POST['notes'] ?? '');
        $ordered_at = $_POST['ordered_at'] ?: date('Y-m-d');
        $status     = $_POST['status'] ?? 'draft';
        $pids       = $_POST['product_id']  ?? [];
        $qtys       = $_POST['qty']         ?? [];
        $costs      = $_POST['cost_price']  ?? [];
        $expiries   = $_POST['expiry_date'] ?? [];

        // Filter out blank rows
        $valid_rows = [];
        foreach ($pids as $i => $pid) {
            if (!$pid || !isset($qtys[$i]) || !$qtys[$i]) continue;
            $valid_rows[] = [
                'pid'    => (int)$pid,
                'qty'    => (float)$qtys[$i],
                'cost'   => (float)($costs[$i] ?? 0),
                'expiry' => !empty($expiries[$i]) ? $expiries[$i] : null,
            ];
        }

        if (empty($valid_rows)) {
            set_flash('error', 'অন্তত একটি product যোগ করুন।');
            header('Location: purchases.php'); exit;
        }

        $total = array_sum(array_map(fn($r) => $r['qty'] * $r['cost'], $valid_rows));

        $pdo->beginTransaction();
        try {
            $po_number = 'PO-' . date('Ymd') . '-' . rand(100, 999);
            $pdo->prepare("INSERT INTO purchase_orders (po_number, dealer_id, user_id, total, status, notes, ordered_at) VALUES (?,?,?,?,?,?,?)")
                ->execute([$po_number, $dealer_id, current_user()['id'], $total, $status, $notes, $ordered_at]);
            $po_id = (int)$pdo->lastInsertId();

            foreach ($valid_rows as $row) {
                $sub = $row['qty'] * $row['cost'];
                if ($_has_expiry_col) {
                    $pdo->prepare("INSERT INTO purchase_order_items (po_id, product_id, qty, cost_price, subtotal, expiry_date) VALUES (?,?,?,?,?,?)")
                        ->execute([$po_id, $row['pid'], $row['qty'], $row['cost'], $sub, $row['expiry']]);
                } else {
                    $pdo->prepare("INSERT INTO purchase_order_items (po_id, product_id, qty, cost_price, subtotal) VALUES (?,?,?,?,?)")
                        ->execute([$po_id, $row['pid'], $row['qty'], $row['cost'], $sub]);
                }

                if ($status === 'received') {
                    upsert_inventory($pdo, $row['pid'], $row['qty'], $po_id, current_user()['id']);
                    if ($row['expiry']) {
                        $pdo->prepare("UPDATE products SET cost_price = ?, expiry_date = ? WHERE id = ?")
                            ->execute([$row['cost'], $row['expiry'], $row['pid']]);
                    } else {
                        $pdo->prepare("UPDATE products SET cost_price = ? WHERE id = ?")
                            ->execute([$row['cost'], $row['pid']]);
                    }
                }
            }

            if ($status === 'received' && $dealer_id) {
                $pdo->prepare("INSERT INTO supplier_ledger (dealer_id, po_id, type, amount, description) VALUES (?,?,?,?,?)")
                    ->execute([$dealer_id, $po_id, 'debit', $total, "Purchase received: $po_number"]);
                $pdo->prepare("UPDATE dealers SET balance = balance + ? WHERE id = ?")
                    ->execute([$total, $dealer_id]);
            }

            $pdo->commit();
            set_flash('success', "Purchase Order $po_number তৈরি হয়েছে।" . ($status === 'received' ? ' Inventory আপডেট হয়েছে ✅' : ''));
            header('Location: purchases.php'); exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('error', "PO তৈরি ব্যর্থ: " . $e->getMessage());
            header('Location: purchases.php'); exit;
        }
    }

    // ── RECEIVE EXISTING PO ───────────────────────────────────────────────────
    if ($action === 'receive') {
        $po_id = (int)$_POST['po_id'];
        $po = $pdo->prepare("SELECT * FROM purchase_orders WHERE id = ? AND status != 'received'");
        $po->execute([$po_id]);
        $po = $po->fetch();

        if (!$po) {
            set_flash('error', 'PO পাওয়া যায়নি বা আগেই receive হয়েছে।');
            header('Location: purchases.php'); exit;
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE purchase_orders SET status = 'received', received_at = CURDATE() WHERE id = ?")
                ->execute([$po_id]);

            $items = $pdo->prepare("SELECT * FROM purchase_order_items WHERE po_id = ?");
            $items->execute([$po_id]);
            $items = $items->fetchAll();

            if (empty($items)) {
                throw new Exception("এই PO তে কোনো item নেই!");
            }

            foreach ($items as $it) {
                upsert_inventory($pdo, (int)$it['product_id'], (float)$it['qty'], $po_id, current_user()['id']);

                $expiry = $_has_expiry_col ? ($it['expiry_date'] ?? null) : null;
                if ($expiry) {
                    $pdo->prepare("UPDATE products SET cost_price = ?, expiry_date = ? WHERE id = ?")
                        ->execute([$it['cost_price'], $expiry, $it['product_id']]);
                } else {
                    $pdo->prepare("UPDATE products SET cost_price = ? WHERE id = ?")
                        ->execute([$it['cost_price'], $it['product_id']]);
                }
            }

            if ($po['dealer_id']) {
                $pdo->prepare("INSERT INTO supplier_ledger (dealer_id, po_id, type, amount, description) VALUES (?,?,?,?,?)")
                    ->execute([$po['dealer_id'], $po_id, 'debit', $po['total'], "Purchase received: " . $po['po_number']]);
                $pdo->prepare("UPDATE dealers SET balance = balance + ? WHERE id = ?")
                    ->execute([$po['total'], $po['dealer_id']]);
            }

            $pdo->commit();
            set_flash('success', 'Received! Inventory ও Ledger আপডেট হয়েছে ✅');

        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('error', "Receive ব্যর্থ: " . $e->getMessage());
        }
        header('Location: purchases.php'); exit;
    }
}

// ── Fetch data for display ────────────────────────────────────────────────────
$orders   = $pdo->query("SELECT po.*, d.name as dealer_name FROM purchase_orders po LEFT JOIN dealers d ON d.id = po.dealer_id ORDER BY po.created_at DESC LIMIT 50")->fetchAll();
$dealers  = $pdo->query("SELECT id, name FROM dealers WHERE is_active = 1 ORDER BY name")->fetchAll();
$products = $pdo->query("SELECT id, name, unit, cost_price FROM products WHERE is_active = 1 ORDER BY name")->fetchAll();

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="page-header flex items-center justify-between">
    <div>
        <h1 class="dark:text-white"><?= __('purchases') ?></h1>
        <p class="dark:text-gray-400"><?= __('manage_purchases') ?></p>
    </div>
    <button onclick="document.getElementById('po-modal').classList.remove('hidden')" class="btn btn-primary" id="new-po-btn">
        + <?= __('new_purchase_order') ?>
    </button>
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
                        <span class="badge <?= match($o['status']) {
                            'received'  => 'badge-green',
                            'sent'      => 'badge-blue',
                            'cancelled' => 'badge-red',
                            default     => 'badge-gray'
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
                            <button type="submit" class="btn btn-xs btn-primary"
                                onclick="return confirm('এই PO receive করবেন? Inventory তে যোগ হবে।')">
                                ✅ <?= __('receive') ?>
                            </button>
                        </form>
                        <?php else: ?>
                        <span class="text-xs text-gray-400">Received <?= $o['received_at'] ? date('d M', strtotime($o['received_at'])) : '' ?></span>
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
<div id="po-modal" class="hidden fixed inset-0 bg-black/60 z-50 flex items-start justify-center pt-6 overflow-y-auto px-4">
    <div class="bg-white dark:bg-gray-800 rounded-2xl w-full max-w-4xl shadow-2xl my-4">
        <div class="flex items-center justify-between p-5 border-b dark:border-gray-700">
            <h2 class="font-bold text-lg dark:text-white">🛒 <?= __('new_purchase_order') ?></h2>
            <button onclick="document.getElementById('po-modal').classList.add('hidden')" class="text-gray-400 hover:text-red-500 text-2xl">&times;</button>
        </div>
        <form method="post" class="p-5 space-y-5">
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
                    <select name="status" id="po-status" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        <option value="draft"><?= __('draft') ?></option>
                        <option value="sent"><?= __('sent') ?></option>
                        <option value="received">✅ <?= __('received_update') ?> (Inventory এ যোগ হবে)</option>
                    </select>
                </div>
                <div>
                    <label class="form-label dark:text-gray-300"><?= __('notes') ?></label>
                    <input type="text" name="notes" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="<?= __('notes') ?>">
                </div>
            </div>

            <!-- Line Items Table -->
            <div>
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-bold text-sm dark:text-gray-200">📦 Items</h3>
                    <button type="button" onclick="addPORow()" class="btn btn-xs btn-outline dark:border-gray-600 dark:text-gray-300">
                        + Add Row
                    </button>
                </div>
                <div class="overflow-x-auto border dark:border-gray-700 rounded-xl">
                    <table class="w-full text-sm" id="po-items">
                        <thead class="bg-gray-50 dark:bg-gray-900/60 text-xs">
                            <tr>
                                <th class="text-left px-3 py-2 dark:text-gray-400">Product</th>
                                <th class="px-2 py-2 text-right dark:text-gray-400 w-24">Qty</th>
                                <th class="px-2 py-2 text-right dark:text-gray-400 w-28">Cost (<?= CURRENCY ?>)</th>
                                <?php if ($_has_expiry_col): ?>
                                <th class="px-2 py-2 text-center dark:text-gray-400 w-36">📅 Expiry</th>
                                <?php endif; ?>
                                <th class="px-2 py-2 text-right dark:text-gray-400 w-24">Subtotal</th>
                                <th class="w-8"></th>
                            </tr>
                        </thead>
                        <tbody id="po-item-body" class="divide-y dark:divide-gray-700"></tbody>
                    </table>
                </div>
                <div class="text-right font-bold mt-3 text-lg dark:text-white">
                    Total: <?= CURRENCY ?><span id="po-total" class="text-brand-600 dark:text-brand-400">0.00</span>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('po-modal').classList.add('hidden')"
                    class="btn btn-secondary dark:bg-gray-700 dark:text-gray-300"><?= __('cancel') ?></button>
                <button type="submit" class="btn btn-primary">💾 Save Purchase Order</button>
            </div>
        </form>
    </div>
</div>

<script>
const CURRENCY_SYM  = '<?= CURRENCY ?>';
const HAS_EXPIRY    = <?= $_has_expiry_col ? 'true' : 'false' ?>;
const PO_PRODUCTS   = <?= json_encode(array_map(fn($p) => [
    'id'   => $p['id'],
    'name' => $p['name'],
    'unit' => $p['unit'],
    'cost' => $p['cost_price']
], $products)) ?>;

let poRow = 0;

function addPORow() {
    poRow++;
    const opts = PO_PRODUCTS.map(p =>
        `<option value="${p.id}" data-cost="${p.cost}">${p.name} (${p.unit})</option>`
    ).join('');

    const expiryCell = HAS_EXPIRY
        ? `<td class="p-1"><input type="date" name="expiry_date[]" class="form-control text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white" style="min-width:130px"></td>`
        : '';

    const tr = document.createElement('tr');
    tr.id = 'po-row-' + poRow;
    tr.className = 'dark:bg-gray-800/30 hover:bg-gray-50 dark:hover:bg-gray-700/30';
    tr.innerHTML = `
        <td class="p-1">
            <select name="product_id[]" class="form-control text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                onchange="setPoPrice(this, ${poRow})">
                <option value="">— Select Product —</option>${opts}
            </select>
        </td>
        <td class="p-1">
            <input type="number" name="qty[]" min="0.001" step="0.001"
                class="form-control text-right text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                style="min-width:70px"
                oninput="calcPoRow(${poRow})" placeholder="0">
        </td>
        <td class="p-1">
            <input type="number" name="cost_price[]" step="0.01"
                class="form-control text-right text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                id="po-cost-${poRow}" style="min-width:90px"
                oninput="calcPoRow(${poRow})" placeholder="0.00">
        </td>
        ${expiryCell}
        <td class="p-1 text-right font-semibold pr-3 dark:text-gray-200 tabular-nums" id="po-sub-${poRow}">0.00</td>
        <td class="p-1 text-center">
            <button type="button"
                onclick="document.getElementById('po-row-${poRow}').remove(); calcPoTotal()"
                class="text-red-400 hover:text-red-600 text-xl font-bold leading-none">&times;</button>
        </td>`;
    document.getElementById('po-item-body').appendChild(tr);
}

function setPoPrice(sel, row) {
    const opt = sel.selectedOptions[0];
    if (opt && opt.dataset.cost) {
        document.getElementById('po-cost-' + row).value = parseFloat(opt.dataset.cost).toFixed(2);
        calcPoRow(row);
    }
}

function calcPoRow(row) {
    const tr = document.getElementById('po-row-' + row);
    if (!tr) return;
    const qty  = parseFloat(tr.querySelector('[name="qty[]"]')?.value) || 0;
    const cost = parseFloat(document.getElementById('po-cost-' + row)?.value) || 0;
    const sub  = document.getElementById('po-sub-' + row);
    if (sub) sub.textContent = (qty * cost).toFixed(2);
    calcPoTotal();
}

function calcPoTotal() {
    let t = 0;
    document.querySelectorAll('[id^="po-sub-"]').forEach(el => t += parseFloat(el.textContent) || 0);
    document.getElementById('po-total').textContent = t.toFixed(2);
}

// Start with one row
addPORow();
</script>

<?php require_once 'includes/footer.php'; ?>
