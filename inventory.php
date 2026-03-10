<?php
// inventory.php — Display stock vs. warehouse stock + manual transfer
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/flash.php';
require_login();
$page_title = 'Inventory';

// Handle stock transfer
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'transfer') {
        $pid      = (int)$_POST['product_id'];
        $from     = $_POST['from_loc'];
        $to       = $_POST['to_loc'];
        $qty      = (float)$_POST['qty'];

        if ($from === $to || $qty <= 0) { set_flash('error','Invalid transfer.'); header('Location: inventory.php'); exit; }

        // Check available stock in from_loc
        $inv = $pdo->prepare("SELECT * FROM inventory WHERE product_id=?"); $inv->execute([$pid]); $inv = $inv->fetch();
        $avail = ($from === 'display') ? $inv['display_qty'] : $inv['warehouse_qty'];
        if ($qty > $avail) { set_flash('error',"Only $avail available in $from."); header('Location: inventory.php'); exit; }

        // Update inventory
        $fromCol = $from . '_qty'; $toCol = $to . '_qty';
        $pdo->prepare("UPDATE inventory SET $fromCol = $fromCol - ?, $toCol = $toCol + ? WHERE product_id=?")->execute([$qty,$qty,$pid]);

        // Log transfer
        $tid = null;
        $pdo->prepare("INSERT INTO stock_transfers (product_id,from_loc,to_loc,qty,user_id) VALUES (?,?,?,?,?)")->execute([$pid,$from,$to,$qty,current_user()['id']]);
        $tid = $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO stock_movements (product_id,type,qty_change,location,reference_id,user_id) VALUES (?,?,?,?,?,?)")->execute([$pid,'transfer_out',-$qty,$from,$tid,current_user()['id']]);
        $pdo->prepare("INSERT INTO stock_movements (product_id,type,qty_change,location,reference_id,user_id) VALUES (?,?,?,?,?,?)")->execute([$pid,'transfer_in',$qty,$to,$tid,current_user()['id']]);

        set_flash('success',"Transferred $qty units from $from to $to.");
        header('Location: inventory.php'); exit;
    }

    if ($action === 'adjust') {
        $pid  = (int)$_POST['product_id'];
        $loc  = $_POST['location'];
        $qty  = (float)$_POST['qty'];
        $col  = $loc . '_qty';
        $pdo->prepare("UPDATE inventory SET $col = ? WHERE product_id=?")->execute([$qty,$pid]);
        $pdo->prepare("INSERT INTO stock_movements (product_id,type,qty_change,location,notes,user_id) VALUES (?,?,?,?,?,?)")->execute([$pid,'adjustment',$qty,$loc,'Manual adjustment',current_user()['id']]);
        set_flash('success','Stock adjusted.'); header('Location: inventory.php'); exit;
    }
}

$filter = $_GET['filter'] ?? '';
$search = trim($_GET['q'] ?? '');

$where = "WHERE p.is_active=1";
$params = [];
if ($search) { $where .= " AND p.name LIKE ?"; $params[] = "%$search%"; }
if ($filter === 'low') { $where .= " AND (i.display_qty+i.warehouse_qty) <= i.reorder_level"; }

$products = $pdo->prepare("SELECT p.id, p.name, p.unit, c.name as category, i.display_qty, i.warehouse_qty, i.reorder_level, (i.display_qty+i.warehouse_qty) as total FROM products p LEFT JOIN inventory i ON i.product_id=p.id LEFT JOIN categories c ON c.id=p.category_id $where ORDER BY p.name");
$products->execute($params);
$products = $products->fetchAll();

$all_products = $pdo->query("SELECT id, name, unit FROM products WHERE is_active=1 ORDER BY name")->fetchAll();

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="page-header flex flex-wrap items-center justify-between gap-3">
    <div><h1 class="dark:text-white"><?= __('inventory') ?></h1><p class="dark:text-gray-400"><?= __('inventory_management') ?></p></div>
</div>

<!-- Filters -->
<form method="get" class="flex flex-wrap gap-3 mb-5">
    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="<?= __('search_products') ?>" class="form-control max-w-xs dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200">
    <a href="inventory.php" class="btn btn-secondary <?= !$filter ? 'bg-brand-600 text-white' : 'dark:bg-gray-700 dark:text-gray-300' ?>"><?= __('all') ?></a>
    <a href="inventory.php?filter=low" class="btn btn-secondary <?= $filter==='low' ? 'bg-amber-500 text-white' : 'dark:bg-gray-700 dark:text-gray-300' ?>">⚠ <?= __('low_stock') ?></a>
    <button type="submit" class="btn btn-secondary dark:bg-gray-700 dark:text-gray-200"><?= __('search') ?></button>
</form>

<div class="card dark:bg-gray-800 dark:border-gray-700 p-0 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="data-table dark:text-gray-300">
            <thead class="dark:bg-gray-900/50">
                <tr>
                    <th class="dark:text-gray-400"><?= __('products') ?></th>
                    <th class="dark:text-gray-400"><?= __('categories') ?></th>
                    <th class="text-right dark:text-gray-400"><?= __('display_shelf') ?></th>
                    <th class="text-right dark:text-gray-400"><?= __('warehouse') ?></th>
                    <th class="text-right dark:text-gray-400"><?= __('total') ?></th>
                    <th class="text-right dark:text-gray-400"><?= __('reorder_level') ?></th>
                    <th class="dark:text-gray-400"><?= __('status') ?></th>
                    <th class="dark:text-gray-400"><?= __('actions') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
            <?php foreach ($products as $p): ?>
            <tr class="dark:hover:bg-gray-700/50">
                <td class="font-medium dark:text-gray-100"><?= htmlspecialchars($p['name']) ?></td>
                <td class="text-gray-500 dark:text-gray-400 text-sm"><?= htmlspecialchars($p['category'] ?? '—') ?></td>
                <td class="text-right dark:text-gray-300"><?= number_format($p['display_qty'] ?? 0, 1) ?> <span class="text-gray-400 text-xs"><?= $p['unit'] ?></span></td>
                <td class="text-right dark:text-gray-300"><?= number_format($p['warehouse_qty'] ?? 0, 1) ?> <span class="text-gray-400 text-xs"><?= $p['unit'] ?></span></td>
                <td class="text-right font-semibold dark:text-gray-100"><?= number_format($p['total'] ?? 0, 1) ?></td>
                <td class="text-right text-gray-400 dark:text-gray-500"><?= number_format($p['reorder_level'] ?? 0, 1) ?></td>
                <td>
                    <?php $total = $p['total'] ?? 0; $rl = $p['reorder_level'] ?? 10; ?>
                    <span class="badge <?= $total <= 0 ? 'badge-red' : ($total <= $rl ? 'badge-yellow' : 'badge-green') ?>">
                        <?= $total <= 0 ? __('out_of_stock') : ($total <= $rl ? __('low_stock') : __('in_stock')) ?>
                    </span>
                </td>
                <td>
                    <button onclick="openTransfer(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['name'])) ?>', <?= $p['display_qty'] ?? 0 ?>, <?= $p['warehouse_qty'] ?? 0 ?>)"
                            class="btn btn-xs btn-secondary dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600"><?= __('transfer') ?></button>
                    <button onclick="openAdjust(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['name'])) ?>')"
                            class="btn btn-xs btn-outline dark:text-gray-400 dark:border-gray-600"><?= __('adjust') ?></button>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($products)): ?>
            <tr><td colspan="8" class="text-center text-gray-400 py-10"><?= __('no_products_found') ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Transfer Modal -->
<div id="transfer-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center px-4">
    <div class="bg-white dark:bg-gray-800 rounded-2xl w-full max-w-sm shadow-2xl">
        <div class="p-5 border-b dark:border-gray-700 flex justify-between items-center"><h2 class="font-bold dark:text-white"><?= __('stock_transfer') ?></h2><button onclick="closeModal('transfer-modal')" class="text-gray-400 text-2xl">&times;</button></div>
        <form method="post" class="p-5 space-y-4">
            <input type="hidden" name="action" value="transfer">
            <input type="hidden" name="product_id" id="t-pid">
            <p class="font-medium text-gray-800 dark:text-gray-200" id="t-name"></p>
            <div class="flex gap-4 text-sm text-gray-500 dark:text-gray-400">
                <span><?= __('display_shelf') ?>: <strong id="t-disp"></strong></span>
                <span><?= __('warehouse') ?>: <strong id="t-ware"></strong></span>
            </div>
            <div>
                <label class="form-label dark:text-gray-300"><?= __('from') ?></label>
                <select name="from_loc" id="t-from" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    <option value="warehouse"><?= __('warehouse') ?></option>
                    <option value="display"><?= __('display_shelf') ?></option>
                </select>
            </div>
            <div>
                <label class="form-label dark:text-gray-300"><?= __('to') ?></label>
                <select name="to_loc" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    <option value="display"><?= __('display_shelf') ?></option>
                    <option value="warehouse"><?= __('warehouse') ?></option>
                </select>
            </div>
            <div><label class="form-label dark:text-gray-300"><?= __('unit') ?></label><input type="number" step="0.001" min="0.001" name="qty" required class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="0"></div>
            <div class="flex justify-end gap-2"><button type="button" onclick="closeModal('transfer-modal')" class="btn btn-secondary dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600"><?= __('cancel') ?></button><button type="submit" class="btn btn-primary"><?= __('transfer') ?></button></div>
        </form>
    </div>
</div>

<!-- Adjust Modal -->
<div id="adjust-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center px-4">
    <div class="bg-white dark:bg-gray-800 rounded-2xl w-full max-w-sm shadow-2xl">
        <div class="p-5 border-b dark:border-gray-700 flex justify-between items-center"><h2 class="font-bold dark:text-white"><?= __('manual_adjustment') ?></h2><button onclick="closeModal('adjust-modal')" class="text-gray-400 text-2xl">&times;</button></div>
        <form method="post" class="p-5 space-y-4">
            <input type="hidden" name="action" value="adjust">
            <input type="hidden" name="product_id" id="a-pid">
            <p class="font-medium dark:text-gray-200" id="a-name"></p>
            <div><label class="form-label dark:text-gray-300"><?= __('location') ?></label><select name="location" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white"><option value="display"><?= __('display_shelf') ?></option><option value="warehouse"><?= __('warehouse') ?></option></select></div>
            <div><label class="form-label dark:text-gray-300"><?= __('set_qty_to') ?></label><input type="number" step="0.001" name="qty" required class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="0"></div>
            <div class="flex justify-end gap-2"><button type="button" onclick="closeModal('adjust-modal')" class="btn btn-secondary dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600"><?= __('cancel') ?></button><button type="submit" class="btn btn-primary"><?= __('adjust') ?></button></div>
        </form>
    </div>
</div>

<script>
function openTransfer(id, name, disp, ware) {
    document.getElementById('t-pid').value  = id;
    document.getElementById('t-name').textContent = name;
    document.getElementById('t-disp').textContent = disp;
    document.getElementById('t-ware').textContent = ware;
    document.getElementById('transfer-modal').classList.remove('hidden');
}
function openAdjust(id, name) {
    document.getElementById('a-pid').value = id;
    document.getElementById('a-name').textContent = name;
    document.getElementById('adjust-modal').classList.remove('hidden');
}
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
</script>

<?php require_once 'includes/footer.php'; ?>
