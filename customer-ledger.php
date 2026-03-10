<?php
// customer-ledger.php — Individual customer Debit/Credit/Balance ledger
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/flash.php';
require_login();
$page_title = 'Customer Ledger';

$customer_id = (int)($_GET['id'] ?? 0);
if (!$customer_id) { header('Location: customers.php'); exit; }

$customer = $pdo->prepare("SELECT * FROM customers WHERE id=?");
$customer->execute([$customer_id]);
$customer = $customer->fetch();
if (!$customer) { header('Location: customers.php'); exit; }

// Manual ledger entry
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type   = $_POST['type'];   // debit | credit
    $amount = (float)$_POST['amount'];
    $desc   = trim($_POST['description'] ?? '');
    if ($amount > 0) {
        $pdo->prepare("INSERT INTO customer_ledger (customer_id,type,amount,description) VALUES (?,?,?,?)")
            ->execute([$customer_id,$type,$amount,$desc]);
        // Update balance (debit = owes more, credit = reduces debt)
        $delta = $type === 'debit' ? $amount : -$amount;
        $pdo->prepare("UPDATE customers SET balance = balance + ? WHERE id=?")->execute([$delta,$customer_id]);
        set_flash('success','Ledger entry added.'); header('Location: customer-ledger.php?id='.$customer_id); exit;
    }
}

$entries = $pdo->prepare("SELECT cl.*, s.invoice_no FROM customer_ledger cl LEFT JOIN sales s ON s.id=cl.sale_id WHERE cl.customer_id=? ORDER BY cl.created_at DESC");
$entries->execute([$customer_id]);
$entries = $entries->fetchAll();

$payments = $pdo->prepare("SELECT * FROM customer_payments WHERE customer_id=? ORDER BY created_at DESC LIMIT 10");
$payments->execute([$customer_id]);
$payments = $payments->fetchAll();

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="page-header">
    <div class="flex items-center gap-3">
        <a href="customers.php" class="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-brand-400 transition-colors"><?= __('back_arrow') ?></a>
        <div>
            <h1 class="dark:text-white"><?= __('ledger_title') . htmlspecialchars($customer['name']) ?></h1>
            <p class="dark:text-gray-400"><?= htmlspecialchars($customer['phone'] ?? '') ?></p>
        </div>
    </div>
</div>

<!-- Summary cards -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="stat-card dark:bg-gray-800 dark:border-gray-700 text-center">
        <div class="text-xs text-gray-400 dark:text-gray-500 mb-1"><?= __('outstanding_balance') ?></div>
        <div class="text-2xl font-bold <?= $customer['balance'] > 0 ? 'text-red-600 dark:text-red-500' : 'text-emerald-600 dark:text-emerald-500' ?>"><?= CURRENCY . number_format($customer['balance'], 2) ?></div>
    </div>
    <div class="stat-card dark:bg-gray-800 dark:border-gray-700 text-center">
        <div class="text-xs text-gray-400 dark:text-gray-500 mb-1"><?= __('credit_limit') ?></div>
        <div class="text-2xl font-bold text-gray-800 dark:text-gray-100"><?= CURRENCY . number_format($customer['credit_limit'], 2) ?></div>
    </div>
    <div class="stat-card dark:bg-gray-800 dark:border-gray-700 text-center">
        <div class="text-xs text-gray-400 dark:text-gray-500 mb-1"><?= __('total_entries') ?></div>
        <div class="text-2xl font-bold text-gray-800 dark:text-gray-100"><?= count($entries) ?></div>
    </div>
    <div class="stat-card dark:bg-gray-800 dark:border-gray-700 text-center flex items-center justify-center">
        <a href="customer-payment.php?id=<?= $customer_id ?>" class="btn btn-primary w-full justify-center">💳 <?= __('record_payment') ?></a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Ledger entries -->
    <div class="lg:col-span-2 card dark:bg-gray-800 dark:border-gray-700 p-0 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-3 border-b dark:border-gray-700">
            <h2 class="font-semibold dark:text-white"><?= __('transaction_history') ?></h2>
            <button onclick="document.getElementById('entry-modal').classList.remove('hidden')" class="btn btn-xs btn-outline dark:border-gray-600 dark:text-gray-400" id="add-entry-btn">+ <?= __('manual_entry') ?></button>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table dark:text-gray-300">
                <thead class="dark:bg-gray-900/50">
                    <tr>
                        <th class="dark:text-gray-400"><?= __('date') ?></th>
                        <th class="dark:text-gray-400"><?= __('status') ?></th>
                        <th class="dark:text-gray-400"><?= __('description') ?></th>
                        <th class="dark:text-gray-400"><?= __('invoice') ?></th>
                        <th class="text-right dark:text-gray-400"><?= __('debit') ?></th>
                        <th class="text-right dark:text-gray-400"><?= __('credit_short') ?></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
                <?php
                $running = 0;
                foreach ($entries as $e):
                    if ($e['type'] === 'debit') $running += $e['amount'];
                    else $running -= $e['amount'];
                ?>
                <tr class="dark:hover:bg-gray-700/50">
                    <td class="text-xs text-gray-400 dark:text-gray-500"><?= date('d M y H:i', strtotime($e['created_at'])) ?></td>
                    <td>
                        <span class="badge <?= $e['type']==='debit' ? 'badge-red' : 'badge-green' ?>">
                            <?= __($e['type']) ?>
                        </span>
                    </td>
                    <td class="text-sm dark:text-gray-200"><?= htmlspecialchars($e['description'] ?? '—') ?></td>
                    <td class="font-mono text-xs dark:text-brand-400"><?= htmlspecialchars($e['invoice_no'] ?? '—') ?></td>
                    <td class="text-right text-red-600 dark:text-red-400 font-medium"><?= $e['type']==='debit' ? CURRENCY.number_format($e['amount'],2) : '' ?></td>
                    <td class="text-right text-emerald-600 dark:text-emerald-400 font-medium"><?= $e['type']==='credit' ? CURRENCY.number_format($e['amount'],2) : '' ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($entries)): ?>
                <tr><td colspan="6" class="text-center text-gray-400 py-8"><?= __('no_transactions') ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Payments -->
    <div class="card dark:bg-gray-800 dark:border-gray-700 p-0 overflow-hidden">
        <div class="px-4 py-3 border-b dark:border-gray-700 font-semibold text-sm dark:text-white"><?= __('recent_payments') ?></div>
        <?php if ($payments): ?>
        <ul class="divide-y divide-gray-50 dark:divide-gray-700">
        <?php foreach ($payments as $pay): ?>
        <li class="px-4 py-3 dark:hover:bg-gray-700/30 transition-colors">
            <div class="flex justify-between items-center">
                <div>
                    <div class="font-medium text-sm dark:text-gray-100"><?= CURRENCY . number_format($pay['amount'], 2) ?></div>
                    <div class="text-xs text-gray-400 dark:text-gray-500 capitalize"><?= $pay['method'] ?> · <?= date('d M Y', strtotime($pay['created_at'])) ?></div>
                </div>
                <span class="badge badge-green"><?= __('paid') ?></span>
            </div>
            <?php if ($pay['notes']): ?>
            <div class="text-xs text-gray-400 dark:text-gray-500 mt-1"><?= htmlspecialchars($pay['notes']) ?></div>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p class="text-center text-gray-400 text-sm py-6"><?= __('no_payments') ?></p>
        <?php endif; ?>
    </div>
</div>

<!-- Manual Entry Modal -->
<div id="entry-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center px-4">
    <div class="bg-white dark:bg-gray-800 rounded-2xl w-full max-w-sm shadow-2xl">
        <div class="flex items-center justify-between p-5 border-b dark:border-gray-700">
            <h2 class="font-bold dark:text-white"><?= __('add_ledger_entry') ?></h2>
            <button onclick="document.getElementById('entry-modal').classList.add('hidden')" class="text-gray-400 text-2xl">&times;</button>
        </div>
        <form method="post" class="p-5 space-y-4">
            <div>
                <label class="form-label dark:text-gray-300"><?= __('status') ?></label>
                <select name="type" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    <option value="debit"><?= __('debit_buy') ?></option>
                    <option value="credit"><?= __('credit_pay') ?></option>
                </select>
            </div>
            <div>
                <label class="form-label dark:text-gray-300"><?= __('total_amount') ?> (<?= CURRENCY ?>)</label>
                <input type="number" step="0.01" name="amount" required class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="0.00">
            </div>
            <div>
                <label class="form-label dark:text-gray-300"><?= __('description') ?></label>
                <input type="text" name="description" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="<?= __('description') ?>">
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('entry-modal').classList.add('hidden')" class="btn btn-secondary dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600"><?= __('cancel') ?></button>
                <button type="submit" class="btn btn-primary"><?= __('add_entry') ?></button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
