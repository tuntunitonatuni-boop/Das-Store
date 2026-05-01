<?php
// dashboard.php — Admin home: sales summary, alerts, expiry warnings
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/flash.php';
require_login();

$page_title = 'Dashboard';

// --- Stats ---
$today = date('Y-m-d');
$todaySalesStmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) as total, COUNT(*) as count FROM sales WHERE DATE(created_at) = ? AND status='completed'");
$todaySalesStmt->execute([$today]);
$todaySales = $todaySalesStmt->fetch();
$monthSales  = $pdo->query("SELECT COALESCE(SUM(total),0) as total FROM sales WHERE YEAR(created_at)=YEAR(NOW()) AND MONTH(created_at)=MONTH(NOW()) AND status='completed'")->fetch();
$totalProducts = $pdo->query("SELECT COUNT(*) as c FROM products WHERE is_active=1")->fetchColumn();
$lowStockCount = $pdo->query("SELECT COUNT(*) as c FROM inventory i JOIN products p ON p.id=i.product_id WHERE (i.display_qty+i.warehouse_qty) <= i.reorder_level AND p.is_active=1")->fetchColumn();
$totalCredit   = $pdo->query("SELECT COALESCE(SUM(balance),0) as b FROM customers WHERE balance > 0")->fetchColumn();
$totalSupplierDebt = $pdo->query("SELECT COALESCE(SUM(balance),0) as b FROM dealers WHERE balance > 0")->fetchColumn();
$todayExpenses     = $pdo->prepare("SELECT COALESCE(SUM(amount),0) as total FROM expenses WHERE expense_date = ?");
$todayExpenses->execute([$today]);
$todayExpenses = $todayExpenses->fetchColumn();
$expiryCount = $pdo->query("
    SELECT (
        SELECT COUNT(*) FROM product_batches WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ".EXPIRY_WARN_DAYS." DAY) AND qty > 0
    ) + (
        SELECT COUNT(*) FROM products WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ".EXPIRY_WARN_DAYS." DAY) AND track_expiry = 0 AND is_active = 1
    ) as c
")->fetchColumn();
$pendingOrdersCount = $pdo->query("SELECT COUNT(*) as c FROM sales WHERE status='pending'")->fetchColumn();

// Recent sales
$recentSales = $pdo->query("SELECT s.*, COALESCE(c.name,'Walk-in') as customer_name, u.name as cashier FROM sales s LEFT JOIN customers c ON c.id=s.customer_id LEFT JOIN users u ON u.id=s.user_id ORDER BY s.created_at DESC LIMIT 8")->fetchAll();

// Low stock products
$lowStock = $pdo->query("SELECT p.name, p.unit, (i.display_qty+i.warehouse_qty) as total_qty, i.reorder_level FROM inventory i JOIN products p ON p.id=i.product_id WHERE (i.display_qty+i.warehouse_qty) <= i.reorder_level AND p.is_active=1 ORDER BY total_qty ASC LIMIT 8")->fetchAll();

// Expiry alerts
$expiryAlerts = $pdo->query("
    SELECT pb.id, pb.expiry_date, p.name as product_name, DATEDIFF(pb.expiry_date, CURDATE()) as days_left 
    FROM product_batches pb 
    JOIN products p ON p.id=pb.product_id 
    WHERE pb.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ".EXPIRY_WARN_DAYS." DAY) AND pb.qty > 0 
    
    UNION ALL
    
    SELECT p.id, p.expiry_date, p.name as product_name, DATEDIFF(p.expiry_date, CURDATE()) as days_left
    FROM products p
    WHERE p.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ".EXPIRY_WARN_DAYS." DAY) AND p.track_expiry = 0 AND p.is_active = 1
    
    ORDER BY expiry_date ASC LIMIT 6
")->fetchAll();

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<!-- Page Header -->
<div class="page-header flex items-center justify-between">
    <div>
        <h1 class="dark:text-white"><?= __('dashboard') ?></h1>
        <p class="dark:text-gray-400"><?= __('welcome_back') ?>, <strong><?= htmlspecialchars(current_user()['name']) ?></strong> 👋 — <?= date('l, F j, Y') ?></p>
    </div>
    <a href="pos.php" class="btn btn-primary text-base px-5 py-2.5"><?= __('open_pos') ?></a>
</div>

<!-- Stat Cards -->
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
    <div class="stat-card dark:bg-gray-800 dark:border-gray-700 p-4 border-t-4 border-emerald-500">
        <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2"><?= __('today_sales') ?></div>
        <div class="text-xl font-bold text-gray-900 dark:text-gray-100"><?= CURRENCY . number_format($todaySales['total'], 2) ?></div>
        <div class="text-[10px] text-gray-400 mt-1"><?= $todaySales['count'] ?> trans.</div>
    </div>
    <div class="stat-card dark:bg-gray-800 dark:border-gray-700 p-4 border-t-4 border-red-500">
        <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2"><?= __('expenses') ?> (Today)</div>
        <div class="text-xl font-bold text-red-600 dark:text-red-500"><?= CURRENCY . number_format($todayExpenses, 2) ?></div>
        <div class="text-[10px] text-gray-400 mt-1"><?= __('today') ?></div>
    </div>
    <div class="stat-card dark:bg-gray-800 dark:border-gray-700 p-4 border-t-4 border-blue-500">
        <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2"><?= __('total_credit') ?></div>
        <div class="text-xl font-bold text-blue-600 dark:text-blue-500"><?= CURRENCY . number_format($totalCredit, 2) ?></div>
        <div class="text-[10px] text-gray-400 mt-1"><?= __('customer_ledger') ?></div>
    </div>
    <div class="stat-card dark:bg-gray-800 dark:border-gray-700 p-4 border-t-4 border-orange-500">
        <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2"><?= __('supplier_ledger') ?></div>
        <div class="text-xl font-bold text-orange-600 dark:text-orange-500"><?= CURRENCY . number_format($totalSupplierDebt, 2) ?></div>
        <div class="text-[10px] text-gray-400 mt-1"><?= __('outstanding_balance') ?></div>
    </div>
    <div class="stat-card dark:bg-gray-800 dark:border-gray-700 p-4 border-t-4 border-brand-500">
        <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2"><?= __('this_month') ?></div>
        <div class="text-xl font-bold text-gray-900 dark:text-gray-100"><?= CURRENCY . number_format($monthSales['total'], 2) ?></div>
        <div class="text-[10px] text-gray-400 mt-1"><?= date('F Y') ?></div>
    </div>
    <div class="stat-card dark:bg-gray-800 dark:border-gray-700 p-4">
        <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2"><?= __('products_active') ?></div>
        <div class="text-xl font-bold text-gray-900 dark:text-gray-100"><?= $totalProducts ?></div>
        <div class="text-[10px] mt-1">
            <?php if ($lowStockCount > 0): ?>
            <span class="text-amber-600 font-bold">⚠ <?= $lowStockCount ?> LOW</span>
            <?php else: ?>
            <span class="text-emerald-600">✓ OK</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Alert Banners -->
<?php if ($lowStockCount > 0 || $expiryCount > 0 || $pendingOrdersCount > 0): ?>
<div class="flex flex-col sm:flex-row gap-3 mb-6">
    <?php if ($pendingOrdersCount > 0): ?>
    <a href="orders.php" class="flex-1 flex items-center gap-3 bg-brand-50 dark:bg-brand-900/20 border border-brand-200 dark:border-brand-800 text-brand-800 dark:text-brand-300 rounded-xl px-4 py-3 hover:bg-brand-100 transition animate-pulse">
        <span class="text-2xl">🛍️</span>
        <div><div class="font-semibold"><?= $pendingOrdersCount ?> <?= __('new_online_orders') ?></div><div class="text-xs"><?= __('click_manage') ?></div></div>
    </a>
    <?php endif; ?>
    <?php if ($lowStockCount > 0): ?>
    <a href="inventory.php?filter=low" class="flex-1 flex items-center gap-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-300 rounded-xl px-4 py-3 hover:bg-amber-100 transition">
        <span class="text-2xl">⚠️</span>
        <div><div class="font-semibold"><?= $lowStockCount ?> <?= __('low_stock_warning') ?></div><div class="text-xs"><?= __('click_manage') ?></div></div>
    </a>
    <?php endif; ?>
    <?php if ($expiryCount > 0): ?>
    <a href="expiry.php" class="flex-1 flex items-center gap-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-300 rounded-xl px-4 py-3 hover:bg-red-100 transition">
        <span class="text-2xl">⏰</span>
        <div><div class="font-semibold"><?= $expiryCount ?> <?= __('expiring_soon') ?></div><div class="text-xs"><?= __('click_manage') ?></div></div>
    </a>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Main Grid -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Recent Sales (2/3 width) -->
    <div class="lg:col-span-2 card dark:bg-gray-800 dark:border-gray-700 p-0 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
            <h2 class="font-semibold text-gray-900 dark:text-gray-100"><?= __('recent_sales') ?></h2>
            <a href="reports.php" class="text-sm text-brand-600 hover:text-brand-700 font-medium"><?= __('view_all') ?> →</a>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table dark:text-gray-300">
                <thead class="dark:bg-gray-900/50">
                    <tr>
                        <th class="dark:text-gray-400"><?= __('invoice') ?></th>
                        <th class="dark:text-gray-400"><?= __('customer') ?></th>
                        <th class="dark:text-gray-400"><?= __('cashier') ?></th>
                        <th class="dark:text-gray-400"><?= __('method') ?></th>
                        <th class="text-right dark:text-gray-400"><?= __('total') ?></th>
                        <th class="dark:text-gray-400"><?= __('time') ?></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
                <?php foreach ($recentSales as $sale): ?>
                <tr class="dark:hover:bg-gray-700/50">
                    <td class="font-mono text-xs text-gray-600 dark:text-gray-400"><?= htmlspecialchars($sale['invoice_no']) ?></td>
                    <td class="dark:text-gray-300"><?= htmlspecialchars($sale['customer_name']) ?></td>
                    <td class="text-gray-500 dark:text-gray-400"><?= htmlspecialchars($sale['cashier'] ?? '—') ?></td>
                    <td><span class="badge badge-blue capitalize"><?= htmlspecialchars($sale['payment_method']) ?></span></td>
                    <td class="text-right font-semibold dark:text-gray-200"><?= CURRENCY . number_format($sale['total'], 2) ?></td>
                    <td class="text-gray-400 dark:text-gray-500 text-xs"><?= date('h:i A', strtotime($sale['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recentSales)): ?>
                <tr><td colspan="6" class="text-center text-gray-400 py-8"><?= __('no_sales_today') ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right column -->
    <div class="space-y-4">
        <!-- Low Stock -->
        <div class="card dark:bg-gray-800 dark:border-gray-700 p-0 overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700">
                <h3 class="font-semibold text-sm text-gray-900 dark:text-gray-100"><?= __('low_stock_alert') ?></h3>
                <a href="inventory.php" class="text-xs text-brand-600"><?= __('view_all') ?> →</a>
            </div>
            <?php if ($lowStock): ?>
            <ul class="divide-y divide-gray-50 dark:divide-gray-700">
                <?php foreach ($lowStock as $item): ?>
                <li class="flex items-center justify-between px-4 py-2.5">
                    <span class="text-sm text-gray-700 dark:text-gray-300 truncate max-w-[60%]"><?= htmlspecialchars($item['name']) ?></span>
                    <span class="badge <?= $item['total_qty'] <= 0 ? 'badge-red' : 'badge-yellow' ?>">
                        <?= number_format($item['total_qty'], 1) ?> <?= $item['unit'] ?>
                    </span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p class="text-center text-sm text-gray-400 py-6">✓ <?= __('all_stocked') ?></p>
            <?php endif; ?>
        </div>

        <!-- Expiry Alerts -->
        <?php if ($expiryAlerts): ?>
        <div class="card dark:bg-gray-800 dark:border-gray-700 p-0 overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-gray-700">
                <h3 class="font-semibold text-sm text-gray-900 dark:text-gray-100"><?= __('expiry_alerts') ?></h3>
                <a href="expiry.php" class="text-xs text-brand-600"><?= __('view_all') ?> →</a>
            </div>
            <ul class="divide-y divide-gray-50 dark:divide-gray-700">
                <?php foreach ($expiryAlerts as $a): ?>
                <li class="flex items-center justify-between px-4 py-2.5">
                    <span class="text-sm text-gray-700 dark:text-gray-300 truncate max-w-[60%]"><?= htmlspecialchars($a['product_name']) ?></span>
                    <span class="badge <?= $a['days_left'] <= 7 ? 'badge-red' : 'badge-yellow' ?>">
                        <?= $a['days_left'] ?>d
                    </span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
