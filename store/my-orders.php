<?php
// store/my-orders.php — Customer order history and status tracking
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/flash.php';
require_once dirname(__DIR__) . '/includes/auth.php';

require_customer_login();

$customer_id = $_SESSION['customer_id'];

// Fetch orders
$stmt = $pdo->prepare("SELECT * FROM sales WHERE customer_id = ? ORDER BY created_at DESC");
$stmt->execute([$customer_id]);
$orders = $stmt->fetchAll();

$store_page_title = __('my_orders') . ' - ' . SHOP_NAME;
require_once dirname(__DIR__) . '/includes/store-header.php';
?>

<div class="mb-8">
    <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white mb-2"><?= __('my_orders') ?></h1>
    <p class="text-gray-500 dark:text-gray-400"><?= __('track_purchases') ?></p>
</div>

<?php if (empty($orders)): ?>
<div class="bg-white dark:bg-gray-800 rounded-3xl p-12 text-center border border-gray-100 dark:border-gray-700 shadow-sm">
    <div class="text-6xl mb-4">🛍️</div>
    <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-2"><?= __('no_orders_yet') ?></h2>
    <p class="text-gray-500 dark:text-gray-400 mb-6"><?= __('no_orders_desc') ?></p>
    <a href="index.php" class="inline-block bg-brand-600 text-white font-bold px-8 py-3 rounded-xl hover:bg-brand-700 transition"><?= __('start_shopping') ?></a>
</div>
<?php else: ?>
<div class="space-y-4">
    <?php foreach ($orders as $o): ?>
    <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 border border-gray-100 dark:border-gray-700 shadow-sm hover:shadow-md transition">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-brand-50 dark:bg-brand-900/30 rounded-2xl flex items-center justify-center text-2xl text-brand-600 dark:text-brand-400">📦</div>
                <div>
                    <div class="text-xs font-bold text-gray-400 uppercase tracking-widest"><?= __('order_id') ?></div>
                    <div class="font-mono font-bold text-gray-900 dark:text-white"><?= htmlspecialchars($o['invoice_no']) ?></div>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <div class="text-right">
                    <div class="text-xs font-bold text-gray-400 uppercase tracking-widest"><?= __('status') ?></div>
                    <div class="flex items-center gap-2">
                        <?php 
                        $status_colors = [
                            'pending' => 'text-amber-600 bg-amber-50 border-amber-100 dark:bg-amber-900/20 dark:border-amber-900/30',
                            'packaging' => 'text-blue-600 bg-blue-50 border-blue-100 dark:bg-blue-900/20 dark:border-blue-900/30',
                            'delivering' => 'text-indigo-600 bg-indigo-50 border-indigo-100 dark:bg-indigo-900/20 dark:border-indigo-900/30',
                            'completed' => 'text-emerald-600 bg-emerald-50 border-emerald-100 dark:bg-emerald-900/20 dark:border-emerald-900/30',
                            'cancelled' => 'text-red-600 bg-red-50 border-red-100 dark:bg-red-900/20 dark:border-red-900/30',
                        ];
                        $cls = $status_colors[$o['status']] ?? 'text-gray-600 bg-gray-50 border-gray-100 dark:bg-gray-700 dark:border-gray-600';
                        ?>
                        <span class="px-3 py-1 rounded-full text-xs font-extrabold uppercase border <?= $cls ?>">
                            <?= __($o['status']) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Status Timeline (Visual) -->
        <div class="relative flex items-center justify-between mb-8 px-4">
            <div class="absolute h-1 bg-gray-100 dark:bg-gray-700 left-8 right-8 top-1/2 -translate-y-1/2 z-0"></div>
            <?php 
            $steps = ['pending' => __('pending'), 'packaging' => __('packaging'), 'delivering' => __('on_way'), 'completed' => __('done')];
            $current_idx = array_search($o['status'], array_keys($steps));
            if ($o['status'] === 'cancelled') $steps = ['cancelled' => __('cancelled')];
            
            $i = 0;
            foreach ($steps as $key => $label): 
                $active = ($i <= $current_idx) && ($o['status'] !== 'cancelled');
                if ($o['status'] === 'cancelled') $active = true;
                $color = $o['status'] === 'cancelled' ? 'bg-red-500' : 'bg-brand-600';
            ?>
                <div class="relative z-10 flex flex-col items-center">
                    <div class="w-6 h-6 rounded-full border-4 border-white dark:border-gray-800 shadow-sm <?= $active ? $color : 'bg-gray-200 dark:bg-gray-700' ?>"></div>
                    <div class="text-[10px] font-bold mt-2 uppercase <?= $active ? 'text-gray-900 dark:text-white' : 'text-gray-400' ?>"><?= $label ?></div>
                </div>
            <?php $i++; endforeach; ?>
        </div>

        <div class="border-t border-dashed border-gray-100 dark:border-gray-700 pt-4 flex items-center justify-between">
            <div class="text-sm text-gray-500 dark:text-gray-400">
                <?= __('placed_on') ?> <?= date('d M, Y', strtotime($o['created_at'])) ?> <?= __('at') ?> <?= date('h:i A', strtotime($o['created_at'])) ?>
            </div>
            <div class="text-xl font-extrabold text-brand-700 dark:text-brand-400">
                <?= CURRENCY . number_format($o['total'], 2) ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/store-footer.php'; ?>
