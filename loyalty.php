<?php
// loyalty.php — Customer loyalty points management
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/flash.php';
require_once 'includes/features.php';
require_login();
$page_title = 'Loyalty Points';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'manual_points') {
        $customer_id = (int)$_POST['customer_id'];
        $points      = (int)$_POST['points'];
        $type        = $_POST['type']; // earn | redeem | expire
        $desc        = trim($_POST['description'] ?? 'Manual adjustment');
        if ($customer_id && $points > 0) {
            $delta = $type === 'earn' ? $points : -$points;
            $pdo->prepare("INSERT INTO loyalty_points (customer_id,points,type,description) VALUES (?,?,?,?)")->execute([$customer_id,$points,$type,$desc]);
            $pdo->prepare("UPDATE customers SET total_points = GREATEST(0, total_points + ?) WHERE id=?")->execute([$delta,$customer_id]);
            set_flash('success', "Points updated for customer.");
        }
        header('Location: loyalty.php'); exit;
    }
}

$pts_per_tk   = (float)(feature_val('loyalty_points_per_tk', 1));
$redeem_rate  = (float)(feature_val('points_redeem_value', 1));

$customers = $pdo->query("SELECT id, name, phone, total_points FROM customers ORDER BY total_points DESC")->fetchAll();

// Overall stats
$total_earned   = $pdo->query("SELECT COALESCE(SUM(points),0) FROM loyalty_points WHERE type='earn'")->fetchColumn();
$total_redeemed = $pdo->query("SELECT COALESCE(SUM(points),0) FROM loyalty_points WHERE type='redeem'")->fetchColumn();
$top_customer   = $customers[0] ?? null;

// Recent log
$recent_log = $pdo->query("SELECT lp.*, c.name as cname FROM loyalty_points lp JOIN customers c ON c.id=lp.customer_id ORDER BY lp.created_at DESC LIMIT 20")->fetchAll();

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="page-header flex items-center justify-between">
    <div>
        <h1 class="dark:text-white">🎁 Loyalty Points</h1>
        <p class="dark:text-gray-400">Customer reward points — earn on purchase, redeem for discount</p>
    </div>
    <button onclick="document.getElementById('pts-modal').classList.remove('hidden')" class="btn btn-primary shadow-lg shadow-brand-500/30">+ Manual Adjustment</button>
</div>

<!-- Config Banner -->
<div class="flex gap-4 mb-6 flex-wrap">
    <div class="stat-card dark:bg-gray-800 dark:border-gray-700 flex-1 border-t-4 border-brand-500">
        <div class="text-xs text-gray-400 uppercase tracking-wider mb-1">Points Per ৳ Spent</div>
        <div class="text-3xl font-black dark:text-white"><?= $pts_per_tk ?> <span class="text-sm font-normal text-gray-400">pts/৳</span></div>
    </div>
    <div class="stat-card dark:bg-gray-800 dark:border-gray-700 flex-1 border-t-4 border-emerald-500">
        <div class="text-xs text-gray-400 uppercase tracking-wider mb-1">1 Point =</div>
        <div class="text-3xl font-black text-emerald-600 dark:text-emerald-400"><?= CURRENCY ?><?= $redeem_rate ?></div>
    </div>
    <div class="stat-card dark:bg-gray-800 dark:border-gray-700 flex-1 border-t-4 border-purple-500">
        <div class="text-xs text-gray-400 uppercase tracking-wider mb-1">Total Earned</div>
        <div class="text-3xl font-black text-purple-600 dark:text-purple-400"><?= number_format($total_earned) ?></div>
    </div>
    <div class="stat-card dark:bg-gray-800 dark:border-gray-700 flex-1 border-t-4 border-orange-500">
        <div class="text-xs text-gray-400 uppercase tracking-wider mb-1">Total Redeemed</div>
        <div class="text-3xl font-black text-orange-600 dark:text-orange-400"><?= number_format($total_redeemed) ?></div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Customer Leaderboard -->
    <div class="lg:col-span-2 card dark:bg-gray-800 dark:border-gray-700 p-0 overflow-hidden shadow-xl">
        <div class="px-5 py-4 border-b dark:border-gray-700 font-bold dark:text-white flex items-center gap-2 bg-gray-50/50 dark:bg-gray-900/50">
            🏆 Customer Points Leaderboard
        </div>
        <div class="overflow-x-auto">
            <table class="data-table dark:text-gray-300">
                <thead class="dark:bg-gray-900/50">
                    <tr>
                        <th class="dark:text-gray-400">#</th>
                        <th class="dark:text-gray-400">Customer</th>
                        <th class="dark:text-gray-400">Phone</th>
                        <th class="text-right dark:text-gray-400">Points Balance</th>
                        <th class="text-right dark:text-gray-400">Value (৳)</th>
                    </tr>
                </thead>
                <tbody class="divide-y dark:divide-gray-700">
                <?php foreach ($customers as $i => $c): if ($c['total_points'] < 0) continue; ?>
                <tr class="dark:hover:bg-gray-700/40 transition-colors <?= $i < 3 ? 'bg-amber-50/30 dark:bg-amber-900/10' : '' ?>">
                    <td class="text-lg">
                        <?= ['🥇','🥈','🥉'][$i] ?? '#'.($i+1) ?>
                    </td>
                    <td class="font-semibold dark:text-white"><?= htmlspecialchars($c['name']) ?></td>
                    <td class="text-sm text-gray-400"><?= htmlspecialchars($c['phone'] ?? '—') ?></td>
                    <td class="text-right">
                        <span class="font-black text-brand-600 dark:text-brand-400 text-base"><?= number_format($c['total_points']) ?></span>
                        <span class="text-xs text-gray-400"> pts</span>
                    </td>
                    <td class="text-right font-bold text-emerald-600 dark:text-emerald-400">
                        <?= CURRENCY.number_format($c['total_points'] * $redeem_rate, 2) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($customers)): ?>
                <tr><td colspan="5" class="text-center text-gray-400 py-10">No customers yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Log -->
    <div class="card dark:bg-gray-800 dark:border-gray-700 p-0 overflow-hidden shadow-xl">
        <div class="px-5 py-4 border-b dark:border-gray-700 font-bold dark:text-white bg-gray-50/50 dark:bg-gray-900/50">🕒 Recent Activity</div>
        <ul class="divide-y dark:divide-gray-700 max-h-[500px] overflow-y-auto">
        <?php foreach ($recent_log as $log): ?>
        <li class="px-4 py-3">
            <div class="flex justify-between items-start">
                <div class="font-medium text-sm dark:text-white"><?= htmlspecialchars($log['cname']) ?></div>
                <span class="badge <?= $log['type']==='earn' ? 'badge-green' : ($log['type']==='redeem' ? 'badge-yellow' : 'badge-red') ?> text-[10px]">
                    <?= $log['type'] === 'earn' ? '+' : '-' ?><?= number_format($log['points']) ?>
                </span>
            </div>
            <div class="text-xs text-gray-400 mt-0.5"><?= htmlspecialchars($log['description'] ?? '') ?> · <?= date('d M y', strtotime($log['created_at'])) ?></div>
        </li>
        <?php endforeach; ?>
        <?php if (empty($recent_log)): ?>
        <li class="py-8 text-center text-gray-400 text-sm">No activity yet.</li>
        <?php endif; ?>
        </ul>
    </div>
</div>

<!-- Manual Adjustment Modal -->
<div id="pts-modal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center px-4">
    <div class="bg-white dark:bg-gray-800 rounded-3xl w-full max-w-md shadow-2xl overflow-hidden">
        <div class="flex items-center justify-between p-5 border-b dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
            <h2 class="font-bold text-xl dark:text-white">➕ Manual Points Adjustment</h2>
            <button onclick="this.closest('#pts-modal, [id=pts-modal]').classList.add('hidden')" class="text-3xl text-gray-400 hover:text-red-500">&times;</button>
        </div>
        <form method="post" class="p-6 space-y-4">
            <input type="hidden" name="action" value="manual_points">
            <div>
                <label class="form-label dark:text-gray-300 font-bold">Customer *</label>
                <select name="customer_id" required class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    <option value="">— Select Customer —</option>
                    <?php foreach ($customers as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= number_format($c['total_points']) ?> pts)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label dark:text-gray-300 font-bold">Type</label>
                    <select name="type" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        <option value="earn">➕ Add Points</option>
                        <option value="redeem">➖ Redeem Points</option>
                        <option value="expire">💀 Expire Points</option>
                    </select>
                </div>
                <div>
                    <label class="form-label dark:text-gray-300 font-bold">Points *</label>
                    <input type="number" name="points" required min="1" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="100">
                </div>
            </div>
            <div>
                <label class="form-label dark:text-gray-300 font-bold">Note / Reason</label>
                <input type="text" name="description" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="Manual adjustment reason...">
            </div>
            <div class="flex justify-end gap-3 pt-2 border-t dark:border-gray-700">
                <button type="button" onclick="document.getElementById('pts-modal').classList.add('hidden')" class="btn btn-secondary dark:bg-gray-700 dark:text-gray-200">Cancel</button>
                <button type="submit" class="btn btn-primary shadow-lg shadow-brand-500/30">💾 Save</button>
            </div>
        </form>
    </div>
</div>

<div class="mt-6 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-2xl text-sm text-amber-800 dark:text-amber-300">
    ⚙️ <strong>Rate settings:</strong> Change points per ৳ and redemption rates in <a href="settings.php?tab=features" class="underline font-bold">Settings → Feature Toggles</a>
</div>

<?php require_once 'includes/footer.php'; ?>
