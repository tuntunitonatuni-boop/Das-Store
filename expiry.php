<?php
// expiry.php — Expiry tracker: expired / 7d / 15d / 30d views
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/flash.php';
require_login();
$page_title = 'Expiry Tracker';

// Add batch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_batch') {
    $pid     = (int)$_POST['product_id'];
    $batch   = trim($_POST['batch_no'] ?? '');
    $mfg     = $_POST['mfg_date'] ?: null;
    $expiry  = $_POST['expiry_date'];
    $qty     = (float)$_POST['qty'];
    $cost    = (float)$_POST['cost_price'];
    $loc     = $_POST['location'] ?? 'warehouse';
    $pdo->prepare("INSERT INTO product_batches (product_id,batch_no,mfg_date,expiry_date,qty,cost_price,location) VALUES (?,?,?,?,?,?,?)")
        ->execute([$pid,$batch,$mfg,$expiry,$qty,$cost,$loc]);
    set_flash('success','Batch added.'); header('Location: expiry.php'); exit;
}

$range  = $_GET['range'] ?? '30';
$filter = [
    'expired' => "expiry_date < CURDATE()",
    '7'       => "expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)",
    '15'      => "expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 15 DAY)",
    '30'      => "expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)",
    'all'     => "1=1",
];
$whereClause = $filter[$range] ?? $filter['30'];
$wherePB = str_replace('expiry_date', 'pb.expiry_date', $whereClause);
$whereP  = str_replace('expiry_date', 'p.expiry_date', $whereClause);

$sql = "
    SELECT 
        pb.id, pb.product_id, pb.batch_no, pb.mfg_date, pb.expiry_date, pb.qty, pb.location, 
        p.name as product_name, p.unit, DATEDIFF(pb.expiry_date, CURDATE()) as days_left,
        'batch' as type
    FROM product_batches pb 
    JOIN products p ON p.id=pb.product_id 
    WHERE $wherePB AND pb.qty > 0
    
    UNION ALL
    
    SELECT 
        p.id, p.id as product_id, 'N/A' as batch_no, p.mfg_date, p.expiry_date, 
        CAST(COALESCE(i.display_qty + i.warehouse_qty, 0) AS DECIMAL(10,2)) as qty, 'ANY' as location,
        p.name as product_name, p.unit, DATEDIFF(p.expiry_date, CURDATE()) as days_left,
        'product' as type
    FROM products p
    LEFT JOIN inventory i ON i.product_id = p.id
    WHERE $whereP AND p.expiry_date IS NOT NULL AND (p.track_expiry = 0 OR p.track_expiry IS NULL)
    
    ORDER BY expiry_date ASC
";

$batches = $pdo->query($sql)->fetchAll();
$products = $pdo->query("SELECT id, name FROM products WHERE is_active=1 AND track_expiry=1 ORDER BY name")->fetchAll();

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="page-header flex items-center justify-between">
    <div><h1 class="dark:text-white"><?= __('expiry_tracker') ?></h1><p class="dark:text-gray-400"><?= __('expiry_monitor') ?></p></div>
    <button onclick="document.getElementById('batch-modal').classList.remove('hidden')" class="btn btn-primary" id="add-batch-btn">+ <?= __('add_batch') ?></button>
</div>

<!-- Filter tabs -->
<div class="flex gap-2 mb-5 flex-wrap">
    <?php 
    $tabs = [
        'expired' => __('expired') . ' 🔴',
        '7'       => __('7_days') . ' ⚠',
        '15'      => __('15_days'),
        '30'      => __('30_days'),
        'all'     => __('all_batches')
    ]; 
    ?>
    <?php foreach ($tabs as $k => $label): ?>
    <a href="?range=<?= $k ?>" class="px-4 py-2 rounded-xl text-sm font-medium border transition
        <?= $range === $k ? 'bg-brand-600 text-white border-brand-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700 dark:hover:bg-gray-700' ?>">
        <?= $label ?>
    </a>
    <?php endforeach; ?>
</div>

<div class="card dark:bg-gray-800 dark:border-gray-700 p-0 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="data-table dark:text-gray-300">
            <thead class="dark:bg-gray-900/50">
                <tr>
                    <th class="dark:text-gray-400"><?= __('products') ?></th>
                    <th class="dark:text-gray-400"><?= __('batch_no') ?></th>
                    <th class="dark:text-gray-400"><?= __('mfg_date') ?></th>
                    <th class="dark:text-gray-400"><?= __('expiry_date') ?></th>
                    <th class="dark:text-gray-400"><?= __('days_left') ?></th>
                    <th class="text-right dark:text-gray-400"><?= __('qty') ?></th>
                    <th class="dark:text-gray-400"><?= __('location') ?></th>
                    <th class="dark:text-gray-400"><?= __('status') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
            <?php foreach ($batches as $b): ?>
            <tr class="dark:hover:bg-gray-700/50">
                <td class="font-medium dark:text-gray-100"><?= htmlspecialchars($b['product_name']) ?></td>
                <td class="font-mono text-xs dark:text-gray-400"><?= htmlspecialchars($b['batch_no'] ?: '—') ?></td>
                <td class="text-sm text-gray-500 dark:text-gray-400"><?= $b['mfg_date'] ?: '—' ?></td>
                <td class="font-medium dark:text-gray-100"><?= $b['expiry_date'] ?></td>
                <td>
                    <?php $d = $b['days_left']; ?>
                    <span class="badge <?= $d < 0 ? 'badge-red' : ($d <= 7 ? 'badge-red' : ($d <= 15 ? 'badge-yellow' : 'badge-green')) ?>">
                        <?= $d < 0 ? __('expired') . ' ' . abs($d) . ' ' . __('ago') : $d . ' ' . __('days') ?>
                    </span>
                </td>
                <td class="text-right dark:text-gray-200"><?= number_format($b['qty'],2) ?> <?= $b['unit'] ?></td>
                <td class="capitalize text-gray-500 dark:text-gray-400">
                    <?php if($b['type'] === 'product'): ?>
                        <span class="text-[10px] italic bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded uppercase font-bold"><?= __('global_stock') ?></span>
                    <?php else: ?>
                        <?= __($b['location'] === 'display' ? 'display_shelf' : ($b['location'] === 'warehouse' ? 'warehouse' : $b['location'])) ?>
                    <?php endif; ?>
                </td>
                <td><span class="badge <?= $d < 0 ? 'badge-red' : 'badge-yellow' ?>"><?= $d < 0 ? __('expired') : __('expiring_soon_label') ?></span></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($batches)): ?>
            <tr><td colspan="8" class="text-center text-gray-400 py-8"><?= __('no_products_found') ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Batch Modal -->
<div id="batch-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center px-4">
    <div class="bg-white dark:bg-gray-800 rounded-2xl w-full max-w-md shadow-2xl">
        <div class="flex items-center justify-between p-5 border-b dark:border-gray-700"><h2 class="font-bold dark:text-white"><?= __('add_product_batch') ?></h2><button onclick="document.getElementById('batch-modal').classList.add('hidden')" class="text-gray-400 text-2xl">&times;</button></div>
        <form method="post" class="p-5 grid grid-cols-2 gap-4">
            <input type="hidden" name="action" value="add_batch">
            <div class="col-span-2"><label class="form-label dark:text-gray-300"><?= __('products') ?> *</label>
                <select name="product_id" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" required>
                    <option value="">— <?= __('select') ?> —</option>
                    <?php foreach ($products as $p): ?><option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label class="form-label dark:text-gray-300"><?= __('batch_no') ?></label><input type="text" name="batch_no" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="<?= __('batch_placeholder') ?>"></div>
            <div><label class="form-label dark:text-gray-300"><?= __('location') ?></label><select name="location" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white"><option value="warehouse"><?= __('warehouse') ?></option><option value="display"><?= __('display_shelf') ?></option></select></div>
            <div><label class="form-label dark:text-gray-300"><?= __('mfg_date') ?></label><input type="date" name="mfg_date" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white"></div>
            <div><label class="form-label dark:text-gray-300"><?= __('expiry_date') ?> *</label><input type="date" name="expiry_date" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" required></div>
            <div><label class="form-label dark:text-gray-300"><?= __('qty') ?></label><input type="number" step="0.001" name="qty" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="0"></div>
            <div><label class="form-label dark:text-gray-300"><?= __('cost_price') ?></label><input type="number" step="0.01" name="cost_price" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="0"></div>
            <div class="col-span-2 flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('batch-modal').classList.add('hidden')" class="btn btn-secondary dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600"><?= __('cancel') ?></button>
                <button type="submit" class="btn btn-primary"><?= __('add_batch') ?></button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
