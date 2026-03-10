<?php
// store/order-success.php — Post-checkout thank you page
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/flash.php';

if (!isset($_SESSION['last_order'])) {
    header('Location: index.php');
    exit;
}

$order = $_SESSION['last_order'];
// Optionally unset after view, but let's keep it for this session in case they refresh.
// unset($_SESSION['last_order']);

$store_page_title = __('order_placed_title') . ' - ' . SHOP_NAME;
require_once dirname(__DIR__) . '/includes/store-header.php';
?>

<div class="max-w-2xl mx-auto py-16 text-center">
    <div class="mb-10 animate-scale-in">
        <div class="w-24 h-24 bg-brand-100 dark:bg-brand-900/40 text-brand-600 dark:text-brand-400 rounded-full flex items-center justify-center text-5xl mx-auto mb-6 shadow-xl shadow-brand-100 dark:shadow-none">🎉</div>
        <h1 class="text-4xl font-extrabold text-gray-900 dark:text-white mb-4"><?= __('shukriya') ?>, <?= htmlspecialchars($order['name']) ?>!</h1>
        <p class="text-lg text-gray-400 dark:text-gray-400 font-medium"><?= __('order_success_msg') ?></p>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-3xl p-8 border border-gray-100 dark:border-gray-700 shadow-2xl shadow-gray-200/50 dark:shadow-none mb-12 relative overflow-hidden">
        <!-- Design elements -->
        <div class="absolute top-0 right-0 w-32 h-32 bg-brand-50 dark:bg-brand-900/20 rounded-bl-full -mr-16 -mt-16 opacity-50"></div>
        <div class="absolute bottom-0 left-0 w-24 h-24 bg-amber-50 dark:bg-amber-900/20 rounded-tr-full -ml-12 -mb-12 opacity-50"></div>

        <div class="relative z-10 grid grid-cols-2 gap-8 text-left max-w-md mx-auto">
            <div>
                <div class="text-[10px] uppercase font-extrabold text-gray-400 tracking-widest mb-1"><?= __('order_id') ?></div>
                <div class="font-extrabold text-gray-900 dark:text-white text-lg font-mono">#<?= htmlspecialchars($order['invoice']) ?></div>
            </div>
            <div>
                <div class="text-[10px] uppercase font-extrabold text-gray-400 tracking-widest mb-1"><?= __('total_amount') ?></div>
                <div class="font-extrabold text-brand-700 dark:text-brand-400 text-xl tracking-tight"><?= CURRENCY ?><?= number_format($order['total'], 2) ?></div>
            </div>
            <div>
                <div class="text-[10px] uppercase font-extrabold text-gray-400 tracking-widest mb-1"><?= __('est_delivery') ?></div>
                <div class="font-extrabold text-emerald-600 dark:text-emerald-400 text-sm"><?= __('within_2_hours') ?> 🚀</div>
            </div>
            <div>
                <div class="text-[10px] uppercase font-extrabold text-gray-400 tracking-widest mb-1"><?= __('status') ?></div>
                <span class="badge badge-yellow"><?= __('order_pending_status') ?></span>
            </div>
        </div>
    </div>

    <div class="space-y-4">
        <p class="text-sm text-gray-500 dark:text-gray-400 max-w-sm mx-auto">
            <?= __('order_success_note') ?>
        </p>
        
        <div class="flex flex-col sm:flex-row gap-4 justify-center pt-6">
            <a href="index.php" class="bg-brand-600 text-white font-extrabold px-10 py-4 rounded-2xl hover:bg-brand-700 active:scale-95 transition-all shadow-xl shadow-brand-100 dark:shadow-none">
                <?= __('continue_shopping') ?>
            </a>
            <a href="my-orders.php" class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-white font-extrabold px-10 py-4 rounded-2xl hover:bg-gray-200 dark:hover:bg-gray-600 active:scale-95 transition-all">
                <?= __('track_my_order') ?>
            </a>
            <a href="https://wa.me/<?= WHATSAPP_NO ?>?text=I'd like to check order status for #<?= $order['invoice'] ?>" target="_blank" class="bg-white dark:bg-gray-800 text-gray-700 dark:text-white border-2 border-gray-100 dark:border-gray-700 font-extrabold px-6 py-4 rounded-2xl hover:bg-gray-50 dark:hover:bg-gray-700 active:scale-95 transition-all flex items-center justify-center gap-2">
                📱
            </a>
        </div>
    </div>
    
    <div class="mt-20 pt-10 border-t border-dashed border-gray-200 dark:border-gray-700 text-xs text-gray-400 font-bold uppercase tracking-widest grayscale opacity-50">
        <?= SHOP_NAME ?> · <?= __('reliable_fast_fresh') ?>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/store-footer.php'; ?>
