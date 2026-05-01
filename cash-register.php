<?php
// cash-register.php — Daily cash drawer open/close with summary
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/flash.php';
require_login();
$page_title = 'Cash Register';

$today = date('Y-m-d');

// Fetch or create today's register
$reg = $pdo->prepare("SELECT * FROM cash_register WHERE date=?");
$reg->execute([$today]); $reg = $reg->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'open') {
        $opening = (float)$_POST['opening_cash'];
        if (!$reg) {
            $pdo->prepare("INSERT INTO cash_register (date, opening_cash, status, opened_by) VALUES (?,?,?,?)")
                ->execute([$today, $opening, 'open', current_user()['id']]);
        } else {
            $pdo->prepare("UPDATE cash_register SET opening_cash=?, status='open', opened_by=? WHERE date=?")
                ->execute([$opening, current_user()['id'], $today]);
        }
        set_flash('success', "Cash register opened with " . CURRENCY . number_format($opening, 2));
        header('Location: cash-register.php'); exit;
    }

    if ($action === 'close') {
        $closing = (float)$_POST['closing_cash'];
        $notes   = trim($_POST['notes'] ?? '');

        // Calculate totals from today's sales
        $total_sales = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM sales WHERE DATE(created_at)=? AND status='completed'");
        $total_sales->execute([$today]); $total_sales = $total_sales->fetchColumn();

        $cash_sales = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM sales WHERE DATE(created_at)=? AND payment_method='cash' AND status='completed'");
        $cash_sales->execute([$today]); $cash_sales = $cash_sales->fetchColumn();

        $mobile_sales = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM sales WHERE DATE(created_at)=? AND payment_method='mobile' AND status='completed'");
        $mobile_sales->execute([$today]); $mobile_sales = $mobile_sales->fetchColumn();

        $expenses = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date=?");
        $expenses->execute([$today]); $expenses = $expenses->fetchColumn();

        $pdo->prepare("UPDATE cash_register SET closing_cash=?,total_sales=?,total_cash=?,total_mobile=?,total_expenses=?,notes=?,status='closed',closed_by=? WHERE date=?")
            ->execute([$closing, $total_sales, $cash_sales, $mobile_sales, $expenses, $notes, current_user()['id'], $today]);

        set_flash('success', "✅ Cash register closed for " . date('d M Y'));
        header('Location: cash-register.php'); exit;
    }
}

// Reload after POST
$reg = $pdo->prepare("SELECT cr.*, ou.name as opened_by_name, cu.name as closed_by_name FROM cash_register cr LEFT JOIN users ou ON ou.id=cr.opened_by LEFT JOIN users cu ON cu.id=cr.closed_by WHERE cr.date=?");
$reg->execute([$today]); $reg = $reg->fetch();

// Today's live stats
$today_sales = $pdo->prepare("SELECT COALESCE(SUM(total),0) as total, COUNT(*) as cnt FROM sales WHERE DATE(created_at)=? AND status='completed'");
$today_sales->execute([$today]); $today_sales = $today_sales->fetch();

$by_method = $pdo->prepare("SELECT payment_method, COALESCE(SUM(total),0) as total FROM sales WHERE DATE(created_at)=? AND status='completed' GROUP BY payment_method");
$by_method->execute([$today]); $by_method = $by_method->fetchAll(PDO::FETCH_KEY_PAIR);

$today_exp = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date=?");
$today_exp->execute([$today]); $today_exp = $today_exp->fetchColumn();

$returns_today = $pdo->prepare("SELECT COALESCE(SUM(refund_amount),0) FROM returns WHERE DATE(created_at)=?");
$returns_today->execute([$today]); $returns_today = $returns_today->fetchColumn();

$net_cash = ($by_method['cash'] ?? 0) + ($reg['opening_cash'] ?? 0) - $today_exp - $returns_today;

// Last 7 days history
$history = $pdo->query("SELECT * FROM cash_register ORDER BY date DESC LIMIT 7")->fetchAll();

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="page-header flex items-center justify-between">
    <div>
        <h1 class="dark:text-white">🏦 Cash Register</h1>
        <p class="dark:text-gray-400 font-medium"><?= date('l, d F Y') ?></p>
    </div>
    <div>
        <?php if ($reg && $reg['status'] === 'open'): ?>
        <span class="badge badge-green text-sm px-4 py-2 animate-pulse">🟢 OPEN</span>
        <?php elseif ($reg && $reg['status'] === 'closed'): ?>
        <span class="badge badge-red text-sm px-4 py-2">🔴 CLOSED</span>
        <?php else: ?>
        <span class="badge badge-gray text-sm px-4 py-2">⬛ NOT STARTED</span>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- LEFT: Register Panel -->
    <div class="lg:col-span-2 space-y-5">
        <!-- Live Totals -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="stat-card dark:bg-gray-800 dark:border-gray-700 text-center border-t-4 border-emerald-500 p-4">
                <div class="text-xs text-gray-400 uppercase mb-1">Today's Sales</div>
                <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400"><?= CURRENCY . number_format($today_sales['total'], 2) ?></div>
                <div class="text-xs text-gray-400"><?= $today_sales['cnt'] ?> transactions</div>
            </div>
            <div class="stat-card dark:bg-gray-800 dark:border-gray-700 text-center border-t-4 border-blue-500 p-4">
                <div class="text-xs text-gray-400 uppercase mb-1">Cash Sales</div>
                <div class="text-2xl font-black text-blue-600 dark:text-blue-400"><?= CURRENCY . number_format($by_method['cash'] ?? 0, 2) ?></div>
                <div class="text-xs text-gray-400">Physical cash</div>
            </div>
            <div class="stat-card dark:bg-gray-800 dark:border-gray-700 text-center border-t-4 border-purple-500 p-4">
                <div class="text-xs text-gray-400 uppercase mb-1">Mobile/Card</div>
                <div class="text-2xl font-black text-purple-600 dark:text-purple-400"><?= CURRENCY . number_format(($by_method['mobile']??0) + ($by_method['card']??0) + ($by_method['bank']??0), 2) ?></div>
                <div class="text-xs text-gray-400">bKash / Card</div>
            </div>
            <div class="stat-card dark:bg-gray-800 dark:border-gray-700 text-center border-t-4 border-red-500 p-4">
                <div class="text-xs text-gray-400 uppercase mb-1">Expenses</div>
                <div class="text-2xl font-black text-red-600 dark:text-red-400"><?= CURRENCY . number_format($today_exp, 2) ?></div>
                <div class="text-xs text-gray-400">Today's costs</div>
            </div>
        </div>

        <!-- Expected Cash in Drawer -->
        <div class="card dark:bg-gray-800 dark:border-gray-700 shadow-xl">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-xl dark:text-white flex items-center gap-2">💵 Expected Cash in Drawer</h3>
                <?php if ($reg && $reg['status'] === 'open'): ?>
                <span class="text-xs text-emerald-600 dark:text-emerald-400 font-bold">Opened at <?= date('H:i', strtotime($reg['created_at'])) ?> by <?= htmlspecialchars($reg['opened_by_name'] ?? '') ?></span>
                <?php endif; ?>
            </div>
            <div class="bg-gradient-to-br from-gray-900 to-gray-800 dark:from-black dark:to-gray-900 rounded-2xl p-6 text-white space-y-3 font-mono">
                <div class="flex justify-between text-sm"><span class="text-gray-400">Opening Cash</span><span><?= CURRENCY . number_format($reg['opening_cash'] ?? 0, 2) ?></span></div>
                <div class="flex justify-between text-sm text-emerald-400"><span>+ Cash Sales Today</span><span>+<?= CURRENCY . number_format($by_method['cash'] ?? 0, 2) ?></span></div>
                <div class="flex justify-between text-sm text-red-400"><span>− Expenses</span><span>−<?= CURRENCY . number_format($today_exp, 2) ?></span></div>
                <div class="flex justify-between text-sm text-orange-400"><span>− Refunds (Returns)</span><span>−<?= CURRENCY . number_format($returns_today, 2) ?></span></div>
                <div class="border-t border-gray-600 pt-3 flex justify-between text-xl font-black">
                    <span class="text-white">Expected Balance</span>
                    <span class="text-emerald-400"><?= CURRENCY . number_format($net_cash, 2) ?></span>
                </div>
            </div>
        </div>

        <!-- Open/Close Actions -->
        <?php if (!$reg || $reg['status'] !== 'open'): ?>
        <?php if (!$reg || $reg['status'] !== 'closed'): ?>
        <div class="card dark:bg-gray-800 dark:border-gray-700 shadow-xl">
            <h3 class="font-bold text-lg dark:text-white mb-4">🟢 Open Today's Register</h3>
            <form method="post" class="flex gap-4 items-end">
                <input type="hidden" name="action" value="open">
                <div class="flex-1">
                    <label class="form-label dark:text-gray-300 font-bold">Opening Cash (<?= CURRENCY ?>) — cash in drawer right now</label>
                    <input type="number" step="0.01" name="opening_cash" required class="form-control text-2xl font-black dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="0.00" autofocus>
                </div>
                <button type="submit" class="btn btn-primary py-3 px-8 text-base shadow-lg shadow-emerald-500/30">🟢 Open Register</button>
            </form>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <?php if ($reg && $reg['status'] === 'open'): ?>
        <div class="card dark:bg-gray-800 dark:border-gray-700 shadow-xl border-l-4 border-red-500">
            <h3 class="font-bold text-lg dark:text-white mb-4">🔴 Close Register for Today</h3>
            <form method="post" class="space-y-4">
                <input type="hidden" name="action" value="close">
                <div>
                    <label class="form-label dark:text-gray-300 font-bold">Actual Cash in Drawer (Physical Count)</label>
                    <input type="number" step="0.01" name="closing_cash" required class="form-control text-2xl font-black dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="0.00">
                </div>
                <div>
                    <label class="form-label dark:text-gray-300 font-bold">Notes</label>
                    <textarea name="notes" rows="2" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="Any notes for today..."></textarea>
                </div>
                <button type="submit" class="btn btn-danger w-full py-3 text-base font-bold" onclick="return confirm('ন্টিতে বন্ধ করুন Today\'s register?')">🔴 Close Day & Save Summary</button>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($reg && $reg['status'] === 'closed'): ?>
        <div class="card dark:bg-gray-800 dark:border-gray-700 bg-red-50 dark:bg-red-950/20 border-red-200 dark:border-red-900 shadow-xl">
            <div class="flex items-center gap-3 mb-4">
                <span class="text-3xl">✅</span>
                <div>
                    <h3 class="font-bold text-lg dark:text-white">Register Closed for Today</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Closed by <?= htmlspecialchars($reg['closed_by_name'] ?? '') ?></p>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div class="bg-white dark:bg-gray-700 rounded-xl p-3"><div class="text-gray-400 text-xs mb-1">Total Sales</div><div class="font-black text-lg dark:text-white"><?= CURRENCY.number_format($reg['total_sales'],2) ?></div></div>
                <div class="bg-white dark:bg-gray-700 rounded-xl p-3"><div class="text-gray-400 text-xs mb-1">Closing Cash</div><div class="font-black text-lg dark:text-white"><?= CURRENCY.number_format($reg['closing_cash'],2) ?></div></div>
                <?php $diff = $reg['closing_cash'] - ($reg['opening_cash'] + $reg['total_cash'] - $reg['total_expenses']); ?>
                <div class="bg-white dark:bg-gray-700 rounded-xl p-3 col-span-2">
                    <div class="text-gray-400 text-xs mb-1">Cash Variance</div>
                    <div class="font-black text-lg <?= $diff >= 0 ? 'text-emerald-600' : 'text-red-600' ?>"><?= ($diff >= 0 ? '+' : '') . CURRENCY.number_format($diff,2) ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- RIGHT: History -->
    <div>
        <div class="card dark:bg-gray-800 dark:border-gray-700 p-0 overflow-hidden shadow-xl sticky top-6">
            <div class="px-5 py-4 border-b dark:border-gray-700 font-bold dark:text-white bg-gray-50/50 dark:bg-gray-900/50">📅 Last 7 Days</div>
            <ul class="divide-y dark:divide-gray-700">
            <?php foreach ($history as $h): ?>
            <li class="px-5 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                <div class="flex justify-between mb-1">
                    <span class="font-bold text-sm dark:text-white"><?= date('d M', strtotime($h['date'])) ?></span>
                    <span class="badge <?= $h['status']==='closed' ? 'badge-green' : ($h['status']==='open' ? 'badge-yellow' : 'badge-gray') ?> text-[10px]"><?= strtoupper($h['status']) ?></span>
                </div>
                <div class="text-xs text-gray-400 space-y-0.5">
                    <div>Sales: <span class="font-bold text-gray-600 dark:text-gray-200"><?= CURRENCY.number_format($h['total_sales'],2) ?></span></div>
                    <?php if ($h['closing_cash']): ?>
                    <div>Close: <span class="font-bold text-emerald-600 dark:text-emerald-400"><?= CURRENCY.number_format($h['closing_cash'],2) ?></span></div>
                    <?php endif; ?>
                </div>
            </li>
            <?php endforeach; ?>
            <?php if (empty($history)): ?>
            <li class="py-8 text-center text-gray-400 text-sm">No history yet.</li>
            <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
