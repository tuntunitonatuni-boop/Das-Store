<?php
// stock-movements.php — Full movement history: sales, returns, transfers
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/flash.php';
require_login();
$page_title = 'Stock Movements';

$search = trim($_GET['q'] ?? '');
$type   = $_GET['type'] ?? '';
$where = "WHERE 1=1";
$params = [];
if ($search) {
    $where .= " AND (p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}
if ($type) {
    $where .= " AND sm.type = ?";
    $params[] = $type;
}

$movements = $pdo->prepare("
    SELECT sm.*, p.name as product_name, p.unit, u.name as user_name
    FROM stock_movements sm
    JOIN products p ON p.id = sm.product_id
    LEFT JOIN users u ON u.id = sm.user_id
    $where
    ORDER BY sm.created_at DESC
    LIMIT 200
");
$movements->execute($params);
$movements = $movements->fetchAll();

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="page-header flex items-center justify-between">
    <div>
        <h1 class="dark:text-white"><?= __('stock_movements') ?></h1>
        <p class="dark:text-gray-400"><?= __('stock_audit_trail') ?></p>
    </div>
</div>

<!-- Search and Filter -->
<form method="get" class="flex flex-wrap gap-3 mb-5 no-print items-end">
    <div>
        <label class="form-label text-xs dark:text-gray-400"><?= __('product_search') ?></label>
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="<?= __('search_products') ?>" class="form-control max-w-xs dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200">
    </div>
    <div>
        <label class="form-label text-xs dark:text-gray-400"><?= __('movement_type') ?></label>
        <select name="type" class="form-control dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200">
            <option value=""><?= __('all_movements') ?></option>
            <option value="sale" <?= $type==='sale' ? 'selected' : '' ?>><?= __('sale') ?></option>
            <option value="purchase" <?= $type==='purchase' ? 'selected' : '' ?>><?= __('purchase') ?></option>
            <option value="transfer_in" <?= $type==='transfer_in' ? 'selected' : '' ?>><?= __('transfer_in') ?></option>
            <option value="transfer_out" <?= $type==='transfer_out' ? 'selected' : '' ?>><?= __('transfer_out') ?></option>
            <option value="adjustment" <?= $type==='adjustment' ? 'selected' : '' ?>><?= __('adjustment') ?></option>
            <option value="return" <?= $type==='return' ? 'selected' : '' ?>><?= __('return') ?></option>
            <option value="opening" <?= $type==='opening' ? 'selected' : '' ?>><?= __('opening') ?></option>
        </select>
    </div>
    <button type="submit" class="btn btn-secondary dark:bg-gray-700 dark:text-gray-200"><?= __('filter') ?></button>
    <?php if ($search || $type): ?><a href="stock-movements.php" class="btn btn-secondary dark:bg-gray-700 dark:text-gray-300"><?= __('clear') ?></a><?php endif; ?>
</form>

<div class="card dark:bg-gray-800 dark:border-gray-700 p-0 overflow-hidden">
    <table class="data-table dark:text-gray-300">
        <thead class="dark:bg-gray-900/50">
            <tr>
                <th class="dark:text-gray-400"><?= __('date') ?></th>
                <th class="dark:text-gray-400"><?= __('products') ?></th>
                <th class="dark:text-gray-400"><?= __('type') ?></th>
                <th class="text-right dark:text-gray-400"><?= __('qty_change') ?></th>
                <th class="dark:text-gray-400"><?= __('location') ?></th>
                <th class="dark:text-gray-400"><?= __('ref_id') ?></th>
                <th class="dark:text-gray-400"><?= __('notes') ?></th>
                <th class="dark:text-gray-400"><?= __('user') ?></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
        <?php foreach ($movements as $m): ?>
        <tr class="dark:hover:bg-gray-700/50">
            <td class="text-xs text-gray-500 dark:text-gray-400"><?= date('d M Y, h:i A', strtotime($m['created_at'])) ?></td>
            <td class="font-medium dark:text-gray-100">
                <?= htmlspecialchars($m['product_name']) ?>
                <span class="text-xs text-gray-400 font-normal dark:text-gray-500">(<?= htmlspecialchars($m['unit']) ?>)</span>
            </td>
            <td>
                <span class="badge <?= match($m['type']) {
                    'sale' => 'badge-blue',
                    'purchase' => 'badge-green',
                    'transfer_in', 'transfer_out' => 'badge-yellow',
                    'adjustment' => 'badge-gray',
                    'return' => 'badge-red',
                    'opening' => 'badge-emerald',
                    default => 'badge-gray'
                } ?> capitalize">
                    <?= __($m['type']) ?>
                </span>
            </td>
            <td class="text-right font-bold <?= $m['qty_change'] > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' ?>">
                <?= $m['qty_change'] > 0 ? '+' : '' ?><?= number_format($m['qty_change'], 2) ?>
            </td>
            <td class="capitalize text-xs text-gray-500 dark:text-gray-400"><?= __($m['location'] === 'display' ? 'display_shelf' : ($m['location'] === 'warehouse' ? 'warehouse' : $m['location'])) ?></td>
            <td class="text-xs font-mono dark:text-gray-400">#<?= $m['reference_id'] ?: '—' ?></td>
            <td class="text-xs text-gray-500 dark:text-gray-400 max-w-xs truncate" title="<?= htmlspecialchars($m['notes'] ?? '') ?>"><?= htmlspecialchars($m['notes'] ?? '—') ?></td>
            <td><span class="badge badge-gray dark:bg-gray-700 dark:text-gray-300"><?= htmlspecialchars($m['user_name'] ?? 'System') ?></span></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($movements)): ?>
        <tr><td colspan="8" class="text-center text-gray-400 py-10"><?= __('no_products_found') ?></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once 'includes/footer.php'; ?>
