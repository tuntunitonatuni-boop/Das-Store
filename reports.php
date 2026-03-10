<?php
// reports.php — Sales, inventory, credit reports
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/flash.php';
require_login();
$page_title = 'Reports';

$type      = $_GET['type'] ?? 'sales';
$date_from = $_GET['from'] ?? date('Y-m-01');
$date_to   = $_GET['to']   ?? date('Y-m-d');

// === SALES REPORT ===
$salesReport = [];
if ($type === 'sales') {
    $salesReport = $pdo->prepare("
        SELECT s.id, s.invoice_no, s.created_at, s.total, s.payment_method, s.status,
               COALESCE(c.name,'Walk-in') as customer, u.name as cashier
        FROM sales s
        LEFT JOIN customers c ON c.id = s.customer_id
        LEFT JOIN users u ON u.id = s.user_id
        WHERE DATE(s.created_at) BETWEEN ? AND ?
        ORDER BY s.created_at DESC");
    $salesReport->execute([$date_from, $date_to]);
    $salesReport = $salesReport->fetchAll();

    $salesSummary = $pdo->prepare("SELECT COUNT(*) as count, COALESCE(SUM(total),0) as total, COALESCE(SUM(discount),0) as discount FROM sales WHERE DATE(created_at) BETWEEN ? AND ? AND status='completed'");
    $salesSummary->execute([$date_from, $date_to]);
    $salesSummary = $salesSummary->fetch();
}

// === INVENTORY REPORT ===
$invReport = [];
if ($type === 'inventory') {
    $invReport = $pdo->query("
        SELECT p.name, p.unit, p.cost_price, p.sale_price, c.name as category,
               COALESCE(i.display_qty,0) as display, COALESCE(i.warehouse_qty,0) as warehouse,
               COALESCE(i.display_qty+i.warehouse_qty,0) as total,
               (p.cost_price * COALESCE(i.display_qty+i.warehouse_qty,0)) as stock_value
        FROM products p
        LEFT JOIN inventory i ON i.product_id = p.id
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE p.is_active=1
        ORDER BY total ASC")->fetchAll();
    $totalStockValue = array_sum(array_column($invReport,'stock_value'));
}

// === CREDIT REPORT ===
$creditReport = [];
if ($type === 'credit') {
    $creditReport = $pdo->query("
        SELECT c.name, c.phone, c.balance, c.credit_limit,
               (SELECT COUNT(*) FROM customer_ledger cl WHERE cl.customer_id=c.id AND cl.type='debit') as purchase_count,
               (SELECT COALESCE(SUM(amount),0) FROM customer_payments cp WHERE cp.customer_id=c.id) as total_paid
        FROM customers c WHERE c.balance > 0 AND c.is_active=1
        ORDER BY c.balance DESC")->fetchAll();
}

// === TOP PRODUCTS REPORT ===
$topProducts = [];
if ($type === 'top_products') {
    $topProducts = $pdo->prepare("
        SELECT p.name, SUM(si.qty) as total_sold, SUM(si.subtotal) as revenue, COUNT(DISTINCT si.sale_id) as transactions
        FROM sale_items si
        JOIN products p ON p.id = si.product_id
        JOIN sales s ON s.id = si.sale_id
        WHERE DATE(s.created_at) BETWEEN ? AND ? AND s.status='completed'
        GROUP BY si.product_id
        ORDER BY revenue DESC LIMIT 20");
    $topProducts->execute([$date_from, $date_to]);
    $topProducts = $topProducts->fetchAll();
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="page-header flex items-center justify-between">
    <div>
        <h1 class="dark:text-white"><?= __('reports') ?></h1>
        <p class="dark:text-gray-400"><?= __('reports_subtitle') ?></p>
    </div>
    <button onclick="window.print()" class="btn btn-secondary no-print dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600">🖨 <?= __('print') ?></button>
</div>

<!-- Report type tabs -->
<div class="flex gap-2 mb-5 flex-wrap no-print">
    <?php 
    $tabs = [
        'sales' => '📊 ' . __('sales_report'),
        'inventory' => '📦 ' . __('inventory_report'),
        'credit' => '📒 ' . __('credit_report'),
        'top_products' => '🏆 ' . __('top_products_report')
    ]; 
    ?>
    <?php foreach ($tabs as $k=>$label): ?>
    <a href="?type=<?= $k ?>&from=<?= $date_from ?>&to=<?= $date_to ?>"
       class="px-4 py-2 rounded-xl text-sm font-medium border transition
           <?= $type===$k ? 'bg-brand-600 text-white border-brand-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700 dark:hover:bg-gray-700' ?>">
        <?= $label ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- Date filter (not for inventory) -->
<?php if ($type !== 'inventory' && $type !== 'credit'): ?>
<form method="get" class="flex flex-wrap gap-3 mb-5 no-print items-end">
    <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
    <div>
        <label class="form-label text-xs dark:text-gray-400"><?= __('from') ?></label>
        <input type="date" name="from" value="<?= $date_from ?>" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white">
    </div>
    <div>
        <label class="form-label text-xs dark:text-gray-400"><?= __('to') ?></label>
        <input type="date" name="to" value="<?= $date_to ?>" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white">
    </div>
    <button type="submit" class="btn btn-secondary dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600"><?= __('apply') ?></button>
</form>
<?php endif; ?>

<!-- ===== SALES REPORT ===== -->
<?php if ($type === 'sales'): ?>
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
    <div class="stat-card text-center dark:bg-gray-800 dark:border-gray-700">
        <div class="text-xs text-gray-400 mb-1"><?= __('total_sales') ?></div>
        <div class="text-2xl font-bold dark:text-white"><?= CURRENCY . number_format($salesSummary['total'], 2) ?></div>
    </div>
    <div class="stat-card text-center dark:bg-gray-800 dark:border-gray-700">
        <div class="text-xs text-gray-400 mb-1"><?= __('transactions') ?></div>
        <div class="text-2xl font-bold dark:text-white"><?= $salesSummary['count'] ?></div>
    </div>
    <div class="stat-card text-center dark:bg-gray-800 dark:border-gray-700">
        <div class="text-xs text-gray-400 mb-1"><?= __('total_discounts') ?></div>
        <div class="text-2xl font-bold text-red-500"><?= CURRENCY . number_format($salesSummary['discount'], 2) ?></div>
    </div>
</div>
<div class="card dark:bg-gray-800 dark:border-gray-700 p-0 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="data-table dark:text-gray-300">
            <thead class="dark:bg-gray-900/50">
                <tr>
                    <th class="dark:text-gray-400"><?= __('invoice') ?></th>
                    <th class="dark:text-gray-400"><?= __('customer') ?></th>
                    <th class="dark:text-gray-400"><?= __('cashier') ?></th>
                    <th class="dark:text-gray-400"><?= __('method') ?></th>
                    <th class="dark:text-gray-400"><?= __('status') ?></th>
                    <th class="text-right dark:text-gray-400"><?= __('total') ?></th>
                    <th class="dark:text-gray-400"><?= __('date') ?> & <?= __('time') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
                <?php foreach ($salesReport as $s): ?>
                <tr class="dark:hover:bg-gray-700/50">
                    <td class="font-mono text-xs dark:text-brand-400"><?= htmlspecialchars($s['invoice_no']) ?></td>
                    <td class="dark:text-gray-100"><?= htmlspecialchars($s['customer']) ?></td>
                    <td class="text-gray-500 dark:text-gray-400"><?= htmlspecialchars($s['cashier'] ?? '—') ?></td>
                    <td><span class="badge badge-blue capitalize"><?= __($s['payment_method']) ?></span></td>
                    <td>
                        <span class="badge <?= $s['status']==='completed' ? 'badge-green' : 'badge-red' ?>">
                            <?= __($s['status']) ?>
                        </span>
                    </td>
                    <td class="text-right font-semibold dark:text-gray-100"><?= CURRENCY . number_format($s['total'], 2) ?></td>
                    <td class="text-xs text-gray-400 dark:text-gray-500"><?= date('d M Y H:i', strtotime($s['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($salesReport)): ?>
                <tr><td colspan="7" class="text-center text-gray-400 py-8"><?= __('no_sales_period') ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ===== INVENTORY REPORT ===== -->
<?php elseif ($type === 'inventory'): ?>
<div class="stat-card mb-5 flex items-center justify-between dark:bg-gray-800 dark:border-gray-700">
    <div>
        <div class="text-xs text-gray-400"><?= __('total_inventory_value') ?></div>
        <div class="text-2xl font-bold dark:text-white"><?= CURRENCY . number_format($totalStockValue, 2) ?></div>
    </div>
    <div class="text-4xl">📦</div>
</div>
<div class="card dark:bg-gray-800 dark:border-gray-700 p-0 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="data-table dark:text-gray-300">
            <thead class="dark:bg-gray-900/50">
                <tr>
                    <th class="dark:text-gray-400"><?= __('products') ?></th>
                    <th class="dark:text-gray-400"><?= __('category') ?></th>
                    <th class="text-right dark:text-gray-400"><?= __('cost') ?></th>
                    <th class="text-right dark:text-gray-400"><?= __('price') ?></th>
                    <th class="text-right dark:text-gray-400"><?= __('display_shelf') ?></th>
                    <th class="text-right dark:text-gray-400"><?= __('warehouse') ?></th>
                    <th class="text-right dark:text-gray-400"><?= __('total') ?></th>
                    <th class="text-right dark:text-gray-400"><?= __('stock_value') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
                <?php foreach ($invReport as $r): ?>
                <tr class="dark:hover:bg-gray-700/50">
                    <td class="font-medium dark:text-gray-100"><?= htmlspecialchars($r['name']) ?></td>
                    <td class="text-gray-500 dark:text-gray-400 text-sm"><?= htmlspecialchars($r['category'] ?? '—') ?></td>
                    <td class="text-right dark:text-gray-300"><?= CURRENCY . number_format($r['cost_price'], 2) ?></td>
                    <td class="text-right dark:text-gray-300"><?= CURRENCY . number_format($r['sale_price'], 2) ?></td>
                    <td class="text-right dark:text-gray-400"><?= number_format($r['display'], 2) ?></td>
                    <td class="text-right dark:text-gray-400"><?= number_format($r['warehouse'], 2) ?></td>
                    <td class="text-right font-semibold dark:text-gray-100"><?= number_format($r['total'], 2) ?></td>
                    <td class="text-right font-semibold text-brand-700 dark:text-brand-400"><?= CURRENCY . number_format($r['stock_value'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ===== CREDIT REPORT ===== -->
<?php elseif ($type === 'credit'): ?>
<div class="card dark:bg-gray-800 dark:border-gray-700 p-0 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="data-table dark:text-gray-300">
            <thead class="dark:bg-gray-900/50">
                <tr>
                    <th class="dark:text-gray-400"><?= __('name') ?></th>
                    <th class="dark:text-gray-400"><?= __('contact') ?></th>
                    <th class="text-right dark:text-gray-400"><?= __('credit_limit') ?></th>
                    <th class="text-right dark:text-gray-400"><?= __('total_paid') ?></th>
                    <th class="text-right dark:text-gray-400"><?= __('balance_baki') ?></th>
                    <th class="dark:text-gray-400"><?= __('purchases') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
                <?php foreach ($creditReport as $r): ?>
                <tr class="dark:hover:bg-gray-700/50">
                    <td class="font-medium dark:text-gray-100"><?= htmlspecialchars($r['name']) ?></td>
                    <td class="dark:text-gray-400"><?= htmlspecialchars($r['phone'] ?? '—') ?></td>
                    <td class="text-right dark:text-gray-300"><?= CURRENCY . number_format($r['credit_limit'], 2) ?></td>
                    <td class="text-right text-emerald-600 dark:text-emerald-400"><?= CURRENCY . number_format($r['total_paid'], 2) ?></td>
                    <td class="text-right font-bold text-red-600 dark:text-red-400"><?= CURRENCY . number_format($r['balance'], 2) ?></td>
                    <td class="dark:text-gray-400"><?= $r['purchase_count'] ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($creditReport)): ?>
                <tr><td colspan="6" class="text-center text-gray-400 py-8"><?= __('no_outstanding_balances') ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ===== TOP PRODUCTS ===== -->
<?php elseif ($type === 'top_products'): ?>
<div class="card dark:bg-gray-800 dark:border-gray-700 p-0 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="data-table dark:text-gray-300">
            <thead class="dark:bg-gray-900/50">
                <tr>
                    <th class="dark:text-gray-400">#</th>
                    <th class="dark:text-gray-400"><?= __('product_name') ?></th>
                    <th class="text-right dark:text-gray-400"><?= __('units_sold') ?></th>
                    <th class="text-right dark:text-gray-400"><?= __('revenue') ?></th>
                    <th class="text-right dark:text-gray-400"><?= __('transactions') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
                <?php foreach ($topProducts as $i => $p): ?>
                <tr class="dark:hover:bg-gray-700/50">
                    <td class="font-bold text-gray-400 dark:text-gray-500"><?= $i+1 ?></td>
                    <td class="font-medium dark:text-gray-100"><?= htmlspecialchars($p['name']) ?></td>
                    <td class="text-right dark:text-gray-300"><?= number_format($p['total_sold'], 2) ?></td>
                    <td class="text-right font-bold text-brand-700 dark:text-brand-400"><?= CURRENCY . number_format($p['revenue'], 2) ?></td>
                    <td class="text-right dark:text-gray-400"><?= $p['transactions'] ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($topProducts)): ?>
                <tr><td colspan="5" class="text-center text-gray-400 py-8"><?= __('no_data_period') ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
