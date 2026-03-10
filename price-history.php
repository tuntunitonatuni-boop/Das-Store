<?php
// price-history.php — Log of all price changes
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/flash.php';
require_login();
$page_title = 'Price History';

$search = trim($_GET['q'] ?? '');
$where = "";
$params = [];
if ($search) {
    $where = "WHERE p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%"];
}

$history = $pdo->prepare("
    SELECT ph.*, p.name as product_name, p.unit, u.name as user_name
    FROM price_history ph
    JOIN products p ON p.id = ph.product_id
    LEFT JOIN users u ON u.id = ph.changed_by
    $where
    ORDER BY ph.created_at DESC
    LIMIT 100
");
$history->execute($params);
$history = $history->fetchAll();

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="page-header flex items-center justify-between">
    <div>
        <h1 class="dark:text-white"><?= __('price_history') ?></h1>
        <p class="dark:text-gray-400"><?= __('track_price_changes') ?></p>
    </div>
</div>

<!-- Search -->
<form method="get" class="flex gap-3 mb-5 no-print">
    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="<?= __('search_products') ?>" class="form-control max-w-xs dark:bg-gray-700 dark:border-gray-600 dark:text-white">
    <button type="submit" class="btn btn-secondary dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"><?= __('filter') ?></button>
    <?php if ($search): ?><a href="price-history.php" class="btn btn-secondary dark:bg-gray-700 dark:text-gray-200"><?= __('clear') ?></a><?php endif; ?>
</form>

<div class="card p-0 overflow-hidden dark:bg-gray-800 dark:border-gray-700">
    <table class="data-table">
        <thead>
            <tr class="dark:bg-gray-700/50">
                <th class="dark:text-gray-300"><?= __('date_time') ?></th>
                <th class="dark:text-gray-300"><?= __('products') ?></th>
                <th class="text-right dark:text-gray-300"><?= __('old_cost') ?></th>
                <th class="text-right dark:text-gray-300"><?= __('new_cost') ?></th>
                <th class="text-right dark:text-gray-300"><?= __('old_sale') ?></th>
                <th class="text-right dark:text-gray-300"><?= __('new_sale') ?></th>
                <th class="dark:text-gray-300"><?= __('changed_by') ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($history as $h): ?>
        <tr class="dark:hover:bg-gray-700/30 transition-colors">
            <td class="text-xs text-gray-500 dark:text-gray-400"><?= date('d M Y, h:i A', strtotime($h['created_at'])) ?></td>
            <td class="font-medium dark:text-white">
                <?= htmlspecialchars($h['product_name']) ?>
                <span class="text-xs text-gray-400 dark:text-gray-500 font-normal">(<?= htmlspecialchars($h['unit']) ?>)</span>
            </td>
            <td class="text-right text-gray-400 dark:text-gray-500"><?= CURRENCY . number_format($h['old_cost'], 2) ?></td>
            <td class="text-right font-semibold <?= $h['new_cost'] > $h['old_cost'] ? 'text-red-500 dark:text-red-400' : ($h['new_cost'] < $h['old_cost'] ? 'text-emerald-500 dark:text-emerald-400' : 'dark:text-white') ?>">
                <?= CURRENCY . number_format($h['new_cost'], 2) ?>
            </td>
            <td class="text-right text-gray-400 dark:text-gray-500"><?= CURRENCY . number_format($h['old_sale'], 2) ?></td>
            <td class="text-right font-semibold <?= $h['new_sale'] > $h['old_sale'] ? 'text-emerald-500 dark:text-emerald-400' : ($h['new_sale'] < $h['old_sale'] ? 'text-red-500 dark:text-red-400' : 'dark:text-white') ?>">
                <?= CURRENCY . number_format($h['new_sale'], 2) ?>
            </td>
            <td>
                <span class="badge badge-gray dark:bg-gray-700 dark:text-gray-300"><?= htmlspecialchars($h['user_name'] ?? 'System') ?></span>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($history)): ?>
        <tr><td colspan="7" class="text-center text-gray-400 dark:text-gray-500 py-10"><?= __('no_price_changes') ?></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once 'includes/footer.php'; ?>
