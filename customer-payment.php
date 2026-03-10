<?php
// customer-payment.php — Record payment, auto-update balance
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/flash.php';
require_login();
$page_title = 'Record Payment';

$customer_id = (int)($_GET['id'] ?? 0);
if (!$customer_id) { header('Location: customers.php'); exit; }

$customer = $pdo->prepare("SELECT * FROM customers WHERE id=?");
$customer->execute([$customer_id]);
$customer = $customer->fetch();
if (!$customer) { header('Location: customers.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)$_POST['amount'];
    $method = $_POST['method'] ?? 'cash';
    $notes  = trim($_POST['notes'] ?? '');

    if ($amount > 0) {
        // Save payment
        $pdo->prepare("INSERT INTO customer_payments (customer_id,amount,method,notes,user_id) VALUES (?,?,?,?,?)")
            ->execute([$customer_id,$amount,$method,$notes,current_user()['id']]);

        // Credit ledger
        $pdo->prepare("INSERT INTO customer_ledger (customer_id,type,amount,description) VALUES (?,?,?,?)")
            ->execute([$customer_id,'credit',$amount,"Payment received ($method)".($notes?" - $notes":"")]);

        // Update balance
        $pdo->prepare("UPDATE customers SET balance = GREATEST(0, balance - ?) WHERE id=?")
            ->execute([$amount,$customer_id]);

        set_flash('success', CURRENCY . number_format($amount,2) . ' payment recorded.');
        header('Location: customer-ledger.php?id=' . $customer_id); exit;
    }
}

$recent = $pdo->prepare("SELECT * FROM customer_payments WHERE customer_id=? ORDER BY created_at DESC LIMIT 5");
$recent->execute([$customer_id]);
$recent = $recent->fetchAll();

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="page-header">
    <div class="flex items-center gap-3">
        <a href="customer-ledger.php?id=<?= $customer_id ?>" class="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-brand-400 transition-colors"><?= __('back_to_ledger') ?></a>
        <div><h1 class="dark:text-white"><?= __('record_payment') ?></h1><p class="dark:text-gray-400"><?= htmlspecialchars($customer['name']) ?></p></div>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-3xl">
    <!-- Payment form -->
    <div class="card dark:bg-gray-800 dark:border-gray-700">
        <h2 class="font-semibold text-lg mb-4 dark:text-white"><?= __('new_payment') ?></h2>

        <!-- Balance summary -->
        <div class="bg-red-50 border border-red-200 dark:bg-red-950/20 dark:border-red-900/50 rounded-xl p-4 mb-5">
            <div class="text-sm text-red-700 dark:text-red-400 mb-1"><?= __('outstanding_balance') ?> (<?= __('baki') ?>)</div>
            <div class="text-3xl font-bold text-red-600 dark:text-red-500"><?= CURRENCY . number_format($customer['balance'], 2) ?></div>
        </div>

        <form method="post" class="space-y-4">
            <div>
                <label class="form-label dark:text-gray-300"><?= __('amount_received') ?> (<?= CURRENCY ?>) *</label>
                <input type="number" name="amount" step="0.01" min="0.01" required class="form-control text-xl font-bold dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                       placeholder="0.00" max="<?= $customer['balance'] ?: '' ?>">
            </div>
            <div>
                <label class="form-label dark:text-gray-300"><?= __('payment_method') ?></label>
                <select name="method" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    <option value="cash"><?= __('cash_method') ?></option>
                    <option value="mobile"><?= __('mobile_method') ?></option>
                    <option value="card"><?= __('card_method') ?></option>
                    <option value="bank"><?= __('bank_method') ?></option>
                </select>
            </div>
            <div>
                <label class="form-label dark:text-gray-300"><?= __('notes') ?></label>
                <input type="text" name="notes" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="<?= __('optional_note') ?>">
            </div>
            <button type="submit" class="btn btn-primary w-full justify-center py-3 text-base">
                ✓ <?= __('record_payment') ?>
            </button>
        </form>
    </div>

    <!-- Recent payments -->
    <div class="card dark:bg-gray-800 dark:border-gray-700">
        <h2 class="font-semibold text-lg mb-4 dark:text-white"><?= __('recent_payments') ?></h2>
        <?php if ($recent): ?>
        <ul class="space-y-3">
        <?php foreach ($recent as $p): ?>
        <li class="flex justify-between items-start border-b border-gray-50 dark:border-gray-700 pb-3">
            <div>
                <div class="font-semibold text-emerald-600 dark:text-emerald-500"><?= CURRENCY . number_format($p['amount'], 2) ?></div>
                <div class="text-xs text-gray-400 dark:text-gray-500 capitalize"><?= $p['method'] ?> · <?= date('d M Y', strtotime($p['created_at'])) ?></div>
                <?php if ($p['notes']): ?><div class="text-xs text-gray-500 dark:text-gray-400"><?= htmlspecialchars($p['notes']) ?></div><?php endif; ?>
            </div>
            <span class="badge badge-green text-xs"><?= __('paid') ?></span>
        </li>
        <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p class="text-gray-400 dark:text-gray-500 text-sm text-center py-8"><?= __('no_payments') ?></p>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
