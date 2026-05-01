<?php
// supplier-ledger.php — Individual supplier Debit/Credit/Balance ledger
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/flash.php';
require_login();
$page_title = 'Supplier Ledger';

$supplier_id = (int)($_GET['id'] ?? 0);
if (!$supplier_id) { header('Location: suppliers.php'); exit; }

$supplier = $pdo->prepare("SELECT * FROM dealers WHERE id=?");
$supplier->execute([$supplier_id]);
$supplier = $supplier->fetch();
if (!$supplier) { header('Location: suppliers.php'); exit; }

// Manual ledger entry
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type   = $_POST['type'];   // debit | credit
    $amount = (float)$_POST['amount'];
    $desc   = trim($_POST['description'] ?? '');
    if ($amount > 0) {
        $pdo->prepare("INSERT INTO supplier_ledger (dealer_id,type,amount,description) VALUES (?,?,?,?)")
            ->execute([$supplier_id,$type,$amount,$desc]);
        // Update balance (debit = we owe more, credit = we paid/reduced debt)
        $delta = $type === 'debit' ? $amount : -$amount;
        $pdo->prepare("UPDATE dealers SET balance = balance + ? WHERE id=?")->execute([$delta,$supplier_id]);
        set_flash('success', __('ledger_entry_added')); header('Location: supplier-ledger.php?id='.$supplier_id); exit;
    }
}

$entries = $pdo->prepare("SELECT sl.*, p.po_number as order_number FROM supplier_ledger sl LEFT JOIN purchase_orders p ON p.id=sl.po_id WHERE sl.dealer_id=? ORDER BY sl.created_at DESC");
$entries->execute([$supplier_id]);
$entries = $entries->fetchAll();

$payments = $pdo->prepare("SELECT * FROM supplier_payments WHERE supplier_id=? ORDER BY created_at DESC LIMIT 10");
$payments->execute([$supplier_id]);
$payments = $payments->fetchAll();

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="page-header">
    <div class="flex items-center gap-3">
        <a href="suppliers.php" class="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-brand-400 transition-colors"><?= __('back_arrow') ?></a>
        <div>
            <h1 class="dark:text-white"><?= __('ledger_title') . htmlspecialchars($supplier['name']) ?></h1>
            <p class="dark:text-gray-400"><?= htmlspecialchars($supplier['phone'] ?? '') ?></p>
        </div>
    </div>
</div>

<!-- Summary cards -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="stat-card dark:bg-gray-800 dark:border-gray-700 text-center border-t-4 border-t-brand-500">
        <div class="text-xs text-gray-400 dark:text-gray-500 mb-1"><?= __('outstanding_balance') ?></div>
        <div class="text-2xl font-bold <?= $supplier['balance'] > 0 ? 'text-red-600 dark:text-red-500' : 'text-emerald-600 dark:text-emerald-500' ?>"><?= CURRENCY . number_format($supplier['balance'], 2) ?></div>
    </div>
    <div class="stat-card dark:bg-gray-800 dark:border-gray-700 text-center">
        <div class="text-xs text-gray-400 dark:text-gray-500 mb-1"><?= __('company') ?></div>
        <div class="text-xl font-semibold text-gray-800 dark:text-gray-100"><?= htmlspecialchars($supplier['company'] ?? '—') ?></div>
    </div>
    <div class="stat-card dark:bg-gray-800 dark:border-gray-700 text-center">
        <div class="text-xs text-gray-400 dark:text-gray-500 mb-1"><?= __('total_entries') ?></div>
        <div class="text-2xl font-bold text-gray-800 dark:text-gray-100"><?= count($entries) ?></div>
    </div>
    <div class="stat-card dark:bg-gray-800 dark:border-gray-700 text-center flex items-center justify-center">
        <a href="supplier-payment.php?id=<?= $supplier_id ?>" class="btn btn-primary w-full justify-center shadow-lg shadow-brand-500/20">💳 <?= __('record_payment') ?></a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Ledger entries -->
    <div class="lg:col-span-2 card dark:bg-gray-800 dark:border-gray-700 p-0 overflow-hidden shadow-xl">
        <div class="flex items-center justify-between px-5 py-4 border-b dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/50">
            <h2 class="font-bold dark:text-white flex items-center gap-2">
                <span class="p-1.5 bg-brand-100 dark:bg-brand-900/30 text-brand-600 dark:text-brand-400 rounded-lg">📊</span>
                <?= __('transaction_history') ?>
            </h2>
            <button onclick="document.getElementById('entry-modal').classList.remove('hidden')" class="btn btn-xs btn-outline dark:border-gray-600 dark:text-gray-400" id="add-entry-btn">+ <?= __('manual_entry') ?></button>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table dark:text-gray-300">
                <thead class="dark:bg-gray-900/80">
                    <tr>
                        <th class="dark:text-gray-400"><?= __('date') ?></th>
                        <th class="dark:text-gray-400"><?= __('status') ?></th>
                        <th class="dark:text-gray-400"><?= __('description') ?></th>
                        <th class="dark:text-gray-400"><?= __('po_number') ?></th>
                        <th class="text-right dark:text-gray-400"><?= __('debit') ?></th>
                        <th class="text-right dark:text-gray-400"><?= __('credit_short') ?></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
                <?php foreach ($entries as $e): ?>
                <tr class="dark:hover:bg-gray-700/50 transition-colors">
                    <td class="text-xs text-gray-400 dark:text-gray-500"><?= date('d M y H:i', strtotime($e['created_at'])) ?></td>
                    <td>
                        <span class="badge <?= $e['type']==='debit' ? 'badge-red' : 'badge-green' ?>">
                            <?= __($e['type']) ?>
                        </span>
                    </td>
                    <td class="text-sm dark:text-gray-200"><?= htmlspecialchars($e['description'] ?? '—') ?></td>
                    <td class="font-mono text-xs dark:text-brand-400"><?= htmlspecialchars($e['order_number'] ?? '—') ?></td>
                    <td class="text-right text-red-600 dark:text-red-400 font-medium"><?= $e['type']==='debit' ? CURRENCY.number_format($e['amount'],2) : '' ?></td>
                    <td class="text-right text-emerald-600 dark:text-emerald-400 font-medium"><?= $e['type']==='credit' ? CURRENCY.number_format($e['amount'],2) : '' ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($entries)): ?>
                <tr><td colspan="6" class="text-center text-gray-400 py-12"><?= __('no_transactions') ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Payments -->
    <div class="card dark:bg-gray-800 dark:border-gray-700 p-0 overflow-hidden shadow-xl">
        <div class="px-5 py-4 border-b dark:border-gray-700 font-bold dark:text-white flex items-center gap-2 bg-gray-50/50 dark:bg-gray-900/50">
            <span class="p-1.5 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 rounded-lg">💸</span>
            <?= __('recent_payments') ?>
        </div>
        <?php if ($payments): ?>
        <ul class="divide-y divide-gray-50 dark:divide-gray-700">
        <?php foreach ($payments as $pay): ?>
        <li class="px-5 py-4 dark:hover:bg-gray-700/30 transition-colors">
            <div class="flex justify-between items-center">
                <div>
                    <div class="font-bold text-lg dark:text-white"><?= CURRENCY . number_format($pay['amount'], 2) ?></div>
                    <div class="text-xs text-gray-400 dark:text-gray-500 capitalize font-medium"><?= $pay['method'] ?> · <?= date('d M Y', strtotime($pay['created_at'])) ?></div>
                </div>
                <span class="badge badge-green px-3 py-1"><?= __('paid') ?></span>
            </div>
            <?php if ($pay['notes']): ?>
            <div class="text-xs text-gray-400 dark:text-gray-500 mt-2 italic">"<?= htmlspecialchars($pay['notes']) ?>"</div>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <div class="flex flex-col items-center justify-center py-12 text-gray-400">
            <span class="text-4xl mb-2">📭</span>
            <p class="text-sm"><?= __('no_payments') ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Manual Entry Modal -->
<div id="entry-modal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center px-4 transition-all">
    <div class="bg-white dark:bg-gray-800 rounded-2xl w-full max-w-sm shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200">
        <div class="flex items-center justify-between p-5 border-b dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
            <h2 class="font-bold dark:text-white flex items-center gap-2">
                <span class="text-brand-500">📝</span>
                <?= __('add_ledger_entry') ?>
            </h2>
            <button onclick="document.getElementById('entry-modal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 dark:hover:text-white text-2xl transition-colors">&times;</button>
        </div>
        <form method="post" class="p-5 space-y-4">
            <div>
                <label class="form-label dark:text-gray-300 font-semibold mb-1.5"><?= __('status') ?></label>
                <select name="type" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white rounded-xl">
                    <option value="debit"><?= __('debit') ?> (We owe more)</option>
                    <option value="credit"><?= __('credit_short') ?> (Payment/Adjustment)</option>
                </select>
            </div>
            <div>
                <label class="form-label dark:text-gray-300 font-semibold mb-1.5"><?= __('total_amount') ?> (<?= CURRENCY ?>) *</label>
                <input type="number" step="0.01" name="amount" required class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white rounded-xl" placeholder="0.00">
            </div>
            <div>
                <label class="form-label dark:text-gray-300 font-semibold mb-1.5"><?= __('description') ?></label>
                <input type="text" name="description" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white rounded-xl" placeholder="<?= __('description') ?>">
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('entry-modal').classList.add('hidden')" class="btn btn-secondary dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 rounded-xl"><?= __('cancel') ?></button>
                <button type="submit" class="btn btn-primary rounded-xl shadow-lg shadow-brand-500/30"><?= __('add_entry') ?></button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
