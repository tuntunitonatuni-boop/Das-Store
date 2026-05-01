<?php
// supplier-payment.php — Record payment to supplier, auto-update balance
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/flash.php';
require_login();
$page_title = 'Record Supplier Payment';

$supplier_id = (int)($_GET['id'] ?? 0);
if (!$supplier_id) { header('Location: suppliers.php'); exit; }

$supplier = $pdo->prepare("SELECT * FROM dealers WHERE id=?");
$supplier->execute([$supplier_id]);
$supplier = $supplier->fetch();
if (!$supplier) { header('Location: suppliers.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)$_POST['amount'];
    $method = $_POST['method'] ?? 'cash';
    $notes  = trim($_POST['notes'] ?? '');

    if ($amount > 0) {
        $pdo->beginTransaction();
        try {
            // Save payment
            $pdo->prepare("INSERT INTO supplier_payments (supplier_id,amount,method,notes) VALUES (?,?,?,?)")
                ->execute([$supplier_id,$amount,$method,$notes]);

            // Credit ledger (reduces our debt)
            $pdo->prepare("INSERT INTO supplier_ledger (dealer_id,type,amount,description) VALUES (?,?,?,?)")
                ->execute([$supplier_id,'credit',$amount,"Payment made ($method)".($notes?" - $notes":"")]);

            // Update dealer balance
            $pdo->prepare("UPDATE dealers SET balance = balance - ? WHERE id=?")
                ->execute([$amount,$supplier_id]);

            $pdo->commit();
            set_flash('success', CURRENCY . number_format($amount,2) . ' ' . __('payment_recorded'));
            header('Location: supplier-ledger.php?id=' . $supplier_id); exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('error', __('error_generic'));
        }
    }
}

$recent = $pdo->prepare("SELECT * FROM supplier_payments WHERE supplier_id=? ORDER BY created_at DESC LIMIT 10");
$recent->execute([$supplier_id]);
$recent = $recent->fetchAll();

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="page-header">
    <div class="flex items-center gap-3">
        <a href="supplier-ledger.php?id=<?= $supplier_id ?>" class="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-brand-400 transition-colors"><?= __('back_to_ledger') ?></a>
        <div>
            <h1 class="dark:text-white"><?= __('record_payment') ?></h1>
            <p class="dark:text-gray-400"><?= htmlspecialchars($supplier['name']) ?> (<?= htmlspecialchars($supplier['company'] ?? '') ?>)</p>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <!-- Payment form -->
        <div class="card dark:bg-gray-800 dark:border-gray-700 shadow-xl overflow-hidden">
            <div class="p-6 border-b dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/50">
                <h2 class="font-bold text-lg dark:text-white flex items-center gap-2">
                    <span class="text-brand-500">💰</span>
                    <?= __('new_payment') ?>
                </h2>
            </div>
            
            <div class="p-6">
                <!-- Balance summary -->
                <div class="bg-gradient-to-br from-red-50 to-orange-50 border border-red-100 dark:from-red-950/30 dark:to-orange-950/30 dark:border-red-900/50 rounded-2xl p-6 mb-8 flex justify-between items-center shadow-inner">
                    <div>
                        <div class="text-sm font-semibold text-red-700 dark:text-red-400 mb-1 uppercase tracking-wider"><?= __('outstanding_balance') ?></div>
                        <div class="text-4xl font-extrabold text-red-600 dark:text-red-500"><?= CURRENCY . number_format($supplier['balance'], 2) ?></div>
                    </div>
                    <div class="text-red-100 dark:text-red-900/30">
                        <svg class="w-16 h-16" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                    </div>
                </div>

                <form method="post" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="form-label dark:text-gray-300 font-bold mb-2 uppercase text-xs tracking-widest"><?= __('payable_amount') ?> (<?= CURRENCY ?>) *</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 font-bold"><?= CURRENCY ?></span>
                                <input type="number" name="amount" step="0.01" min="0.01" required 
                                       class="form-control pl-10 text-2xl font-black rounded-2xl dark:bg-gray-700 dark:border-gray-600 dark:text-white focus:ring-brand-500 border-2"
                                       placeholder="0.00" value="<?= $supplier['balance'] > 0 ? $supplier['balance'] : '' ?>">
                            </div>
                        </div>
                        <div>
                            <label class="form-label dark:text-gray-300 font-bold mb-2 uppercase text-xs tracking-widest"><?= __('payment_method') ?></label>
                            <select name="method" class="form-control rounded-2xl dark:bg-gray-700 dark:border-gray-600 dark:text-white py-3 border-2">
                                <option value="cash"><?= __('cash_method') ?></option>
                                <option value="mobile"><?= __('mobile_method') ?></option>
                                <option value="card"><?= __('card_method') ?></option>
                                <option value="bank"><?= __('bank_method') ?></option>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <label class="form-label dark:text-gray-300 font-bold mb-2 uppercase text-xs tracking-widest"><?= __('notes') ?></label>
                        <textarea name="notes" rows="3" class="form-control rounded-2xl dark:bg-gray-700 dark:border-gray-600 dark:text-white border-2" placeholder="<?= __('optional_note') ?>"></textarea>
                    </div>

                    <div class="pt-4">
                        <button type="submit" class="btn btn-primary w-full justify-center py-4 text-lg font-bold rounded-2xl shadow-xl shadow-brand-500/40 hover:scale-[1.02] active:scale-[0.98] transition-all">
                            ✓ <?= __('record_payment') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Column: Recent Payments -->
    <div>
        <div class="card dark:bg-gray-800 dark:border-gray-700 p-0 overflow-hidden shadow-xl sticky top-6">
            <div class="px-5 py-4 border-b dark:border-gray-700 font-bold dark:text-white flex items-center gap-2 bg-gray-50/50 dark:bg-gray-900/50">
                <span class="p-1.5 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 rounded-lg">🕒</span>
                <?= __('recent_payments') ?>
            </div>
            <?php if ($recent): ?>
            <ul class="divide-y divide-gray-50 dark:divide-gray-700">
            <?php foreach ($recent as $p): ?>
            <li class="px-5 py-4 dark:hover:bg-gray-700/30 transition-colors">
                <div class="flex justify-between items-start mb-1">
                    <div class="font-bold text-lg text-emerald-600 dark:text-emerald-500"><?= CURRENCY . number_format($p['amount'], 2) ?></div>
                    <span class="badge badge-green text-[10px] uppercase tracking-tighter"><?= __('paid') ?></span>
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400 uppercase font-bold tracking-widest mb-2"><?= $p['method'] ?> · <?= date('d M Y', strtotime($p['created_at'])) ?></div>
                <?php if ($p['notes']): ?>
                <div class="text-xs text-gray-400 dark:text-gray-500 border-l-2 border-gray-200 dark:border-gray-700 pl-2 italic">
                    <?= htmlspecialchars($p['notes']) ?>
                </div>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
            </ul>
            <div class="p-4 bg-gray-50/50 dark:bg-gray-900/50 text-center">
                <a href="supplier-ledger.php?id=<?= $supplier_id ?>" class="text-xs font-bold text-brand-600 dark:text-brand-400 hover:underline"><?= __('view_all') ?></a>
            </div>
            <?php else: ?>
            <div class="flex flex-col items-center justify-center py-12 text-gray-400">
                <span class="text-4xl mb-2">📭</span>
                <p class="text-sm"><?= __('no_payments') ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
