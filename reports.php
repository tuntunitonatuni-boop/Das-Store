<?php
// reports.php — Sales, inventory, credit, P&L reports
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/flash.php';
require_once 'includes/features.php';
require_login();
$page_title = 'Reports';

$type      = $_GET['type'] ?? 'sales';
$date_from = $_GET['from'] ?? date('Y-m-01');
$date_to   = $_GET['to']   ?? date('Y-m-d');

// === P&L ANALYTICS DATA (always load for analytics tab) ===
// Last 30 days daily sales + expenses
$pnl_days = $pdo->query("
    SELECT DATE(created_at) as day, COALESCE(SUM(total),0) as revenue
    FROM sales WHERE status='completed' AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
    GROUP BY DATE(created_at) ORDER BY day
")->fetchAll(PDO::FETCH_KEY_PAIR);

$expense_days = $pdo->query("
    SELECT expense_date as day, COALESCE(SUM(amount),0) as expense
    FROM expenses WHERE expense_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
    GROUP BY expense_date ORDER BY expense_date
")->fetchAll(PDO::FETCH_KEY_PAIR);

// Best selling products
$best_sellers = $pdo->query("
    SELECT p.name, SUM(si.qty) as total_qty, SUM(si.subtotal) as revenue
    FROM sale_items si JOIN products p ON p.id=si.product_id
    JOIN sales s ON s.id=si.sale_id
    WHERE s.status='completed' AND DATE(s.created_at) >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
    GROUP BY si.product_id ORDER BY revenue DESC LIMIT 8
")->fetchAll();

// Monthly trend (6 months)
$monthly = $pdo->query("
    SELECT DATE_FORMAT(created_at,'%Y-%m') as month, COALESCE(SUM(total),0) as revenue
    FROM sales WHERE status='completed' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month ORDER BY month
")->fetchAll(PDO::FETCH_KEY_PAIR);

$monthly_exp = $pdo->query("
    SELECT DATE_FORMAT(expense_date,'%Y-%m') as month, COALESCE(SUM(amount),0) as expense
    FROM expenses WHERE expense_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month ORDER BY month
")->fetchAll(PDO::FETCH_KEY_PAIR);

// Customer stats
$top_customers = $pdo->query("
    SELECT c.name, COUNT(*) as orders, COALESCE(SUM(s.total),0) as total
    FROM sales s JOIN customers c ON c.id=s.customer_id
    WHERE s.status='completed' AND DATE(s.created_at) >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
    GROUP BY s.customer_id ORDER BY total DESC LIMIT 5
")->fetchAll();

// Build chart labels (last 30 days)
$chart_labels = [];
$chart_revenue = [];
$chart_expense = [];
for ($d = 29; $d >= 0; $d--) {
    $day = date('Y-m-d', strtotime("-$d days"));
    $chart_labels[] = date('d M', strtotime($day));
    $chart_revenue[] = round($pnl_days[$day] ?? 0, 2);
    $chart_expense[] = round($expense_days[$day] ?? 0, 2);
}

$monthly_labels  = array_keys($monthly + $monthly_exp);
sort($monthly_labels);
$monthly_rev_data = array_map(fn($m) => round($monthly[$m] ?? 0,2), $monthly_labels);
$monthly_exp_data = array_map(fn($m) => round($monthly_exp[$m] ?? 0,2), $monthly_labels);
$monthly_labels   = array_map(fn($m) => date('M Y', strtotime($m.'-01')), $monthly_labels);


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
<div class="flex gap-1 mb-5 flex-wrap no-print border-b dark:border-gray-700 pb-0">
    <?php
    $tabs = [
        'sales'        => '📊 ' . __('sales_report'),
        'inventory'    => '📦 ' . __('inventory_report'),
        'credit'       => '📒 ' . __('credit_report'),
        'top_products' => '🏆 ' . __('top_products_report'),
        'analytics'    => '📈 P&L Analytics',
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

<?php if ($type === 'analytics'): ?>
<!-- P&L ANALYTICS TAB -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<!-- Totals Row -->
<?php
$total_rev_30 = array_sum($chart_revenue);
$total_exp_30 = array_sum($chart_expense);
$net_profit   = $total_rev_30 - $total_exp_30;
$best_month   = $monthly_labels ? $monthly_labels[array_search(max($monthly_rev_data), $monthly_rev_data)] : '—';
?>
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="stat-card dark:bg-gray-800 dark:border-gray-700 border-t-4 border-emerald-500 text-center">
        <div class="text-xs text-gray-400 uppercase tracking-wider mb-1">Revenue (30 days)</div>
        <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400"><?= CURRENCY . number_format($total_rev_30, 2) ?></div>
    </div>
    <div class="stat-card dark:bg-gray-800 dark:border-gray-700 border-t-4 border-red-500 text-center">
        <div class="text-xs text-gray-400 uppercase tracking-wider mb-1">Expenses (30 days)</div>
        <div class="text-2xl font-black text-red-600 dark:text-red-400"><?= CURRENCY . number_format($total_exp_30, 2) ?></div>
    </div>
    <div class="stat-card dark:bg-gray-800 dark:border-gray-700 border-t-4 <?= $net_profit >= 0 ? 'border-brand-500' : 'border-orange-500' ?> text-center">
        <div class="text-xs text-gray-400 uppercase tracking-wider mb-1">Net Profit</div>
        <div class="text-2xl font-black <?= $net_profit >= 0 ? 'text-brand-600 dark:text-brand-400' : 'text-orange-600 dark:text-orange-400' ?>"><?= CURRENCY . number_format($net_profit, 2) ?></div>
    </div>
    <div class="stat-card dark:bg-gray-800 dark:border-gray-700 border-t-4 border-purple-500 text-center">
        <div class="text-xs text-gray-400 uppercase tracking-wider mb-1">Best Month</div>
        <div class="text-lg font-black text-purple-600 dark:text-purple-400"><?= $best_month ?></div>
    </div>
</div>

<!-- Charts Grid -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- 30-Day Revenue vs Expense Line Chart -->
    <div class="card dark:bg-gray-800 dark:border-gray-700 shadow-xl col-span-full">
        <h3 class="font-bold text-base dark:text-white mb-4">📈 Revenue vs Expenses — Last 30 Days</h3>
        <div style="height:260px;"><canvas id="pnlChart"></canvas></div>
    </div>

    <!-- Monthly Bar -->
    <div class="card dark:bg-gray-800 dark:border-gray-700 shadow-xl">
        <h3 class="font-bold text-base dark:text-white mb-4">📊 Monthly Revenue vs Expenses</h3>
        <div style="height:240px;"><canvas id="monthlyChart"></canvas></div>
    </div>

    <!-- Best Sellers -->
    <div class="card dark:bg-gray-800 dark:border-gray-700 shadow-xl">
        <h3 class="font-bold text-base dark:text-white mb-4">🏆 Best Sellers (30 Days)</h3>
        <div style="height:240px;"><canvas id="bestSellerChart"></canvas></div>
    </div>
</div>

<!-- Top Customers -->
<div class="card dark:bg-gray-800 dark:border-gray-700 shadow-xl p-0 overflow-hidden">
    <div class="px-5 py-4 border-b dark:border-gray-700 font-bold dark:text-white bg-gray-50/50 dark:bg-gray-900/50">👑 Top Customers (30 Days)</div>
    <table class="data-table dark:text-gray-300">
        <thead><tr>
            <th class="dark:text-gray-400">#</th>
            <th class="dark:text-gray-400">Customer</th>
            <th class="text-center dark:text-gray-400">Orders</th>
            <th class="text-right dark:text-gray-400">Total Spent</th>
        </tr></thead>
        <tbody class="divide-y dark:divide-gray-700">
        <?php foreach ($top_customers as $i => $tc): ?>
        <tr class="dark:hover:bg-gray-700/40">
            <td class="text-xl"><?= ['🥇','🥈','🥉'][$i] ?? '#'.($i+1) ?></td>
            <td class="font-bold dark:text-white"><?= htmlspecialchars($tc['name']) ?></td>
            <td class="text-center text-sm text-gray-400"><?= $tc['orders'] ?> orders</td>
            <td class="text-right font-black text-brand-600 dark:text-brand-400"><?= CURRENCY . number_format($tc['total'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($top_customers)): ?>
        <tr><td colspan="4" class="text-center py-8 text-gray-400">No data available yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Charts JS -->
<script>
const isDark = document.documentElement.classList.contains('dark');
const gridColor = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';
const textColor = isDark ? '#9ca3af' : '#6b7280';

Chart.defaults.color = textColor;
Chart.defaults.borderColor = gridColor;
Chart.defaults.font.family = "'Inter','system-ui',sans-serif";

// 30-Day P&L Line Chart
new Chart(document.getElementById('pnlChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($chart_labels) ?>,
        datasets: [
            {
                label: 'Revenue', 
                data: <?= json_encode($chart_revenue) ?>,
                borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.08)',
                tension: 0.4, fill: true, pointRadius: 2, borderWidth: 2.5
            },
            {
                label: 'Expenses',
                data: <?= json_encode($chart_expense) ?>,
                borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,0.06)',
                tension: 0.4, fill: true, pointRadius: 2, borderWidth: 2.5
            }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'top', labels: { usePointStyle: true, padding: 16 } } },
        scales: {
            x: { ticks: { maxTicksLimit: 10, font: { size: 10 } } },
            y: { ticks: { callback: v => '<?= CURRENCY ?>' + v.toLocaleString(), font: { size: 10 } } }
        }
    }
});

// Monthly Bar Chart
new Chart(document.getElementById('monthlyChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($monthly_labels) ?>,
        datasets: [
            { label: 'Revenue', data: <?= json_encode($monthly_rev_data) ?>, backgroundColor: 'rgba(99,102,241,0.75)', borderRadius: 6, borderSkipped: false },
            { label: 'Expenses', data: <?= json_encode($monthly_exp_data) ?>, backgroundColor: 'rgba(239,68,68,0.5)', borderRadius: 6, borderSkipped: false }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'top', labels: { usePointStyle: true, padding: 12 } } },
        scales: {
            y: { ticks: { callback: v => '<?= CURRENCY ?>' + v.toLocaleString() } }
        }
    }
});

// Best Sellers Donut
new Chart(document.getElementById('bestSellerChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($best_sellers, 'name')) ?>,
        datasets: [{
            data: <?= json_encode(array_map(fn($r) => round($r['revenue'],2), $best_sellers)) ?>,
            backgroundColor: ['#6366f1','#8b5cf6','#ec4899','#f97316','#eab308','#10b981','#06b6d4','#3b82f6'],
            borderWidth: 2,
            borderColor: isDark ? '#1f2937' : '#fff',
            hoverOffset: 8
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false, cutout: '60%',
        plugins: {
            legend: { position: 'right', labels: { font: { size: 11 }, padding: 8, boxWidth: 12 } },
            tooltip: { callbacks: { label: ctx => ' <?= CURRENCY ?>'+ctx.raw.toLocaleString() } }
        }
    }
});
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
