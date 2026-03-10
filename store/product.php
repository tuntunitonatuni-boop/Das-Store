<?php
// store/product.php — Product detail page
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/flash.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name, c.icon as category_icon, COALESCE(i.display_qty+i.warehouse_qty, 0) as stock
    FROM products p
    LEFT JOIN inventory i ON i.product_id = p.id
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.id = ? AND p.is_active = 1
    LIMIT 1
");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) { header('Location: index.php'); exit; }

// Related products
$related = $pdo->prepare("
    SELECT p.*, c.name as category_name, c.icon as category_icon
    FROM products p
    JOIN categories c ON c.id = p.category_id
    WHERE p.category_id = ? AND p.id != ? AND p.is_active = 1
    LIMIT 4
");
$related->execute([$product['category_id'], $id]);
$related = $related->fetchAll();

$store_page_title = $product['name'] . ' - ' . SHOP_NAME;
require_once dirname(__DIR__) . '/includes/store-header.php';
?>

<div class="mb-4 no-print">
    <a href="index.php" class="text-sm text-gray-400 hover:text-brand-600 flex items-center gap-1 transition-all">← <?= __('back_store') ?></a>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-16">
    <!-- Product Media -->
    <div class="space-y-4">
        <div class="bg-white dark:bg-gray-800 p-4 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden aspect-square flex items-center justify-center">
            <?php if ($product['image']): ?>
            <img src="<?= BASE_URL ?>uploads/products/<?= htmlspecialchars($product['image']) ?>"
                 class="w-full h-full object-contain rounded-2xl" alt="<?= htmlspecialchars($product['name']) ?>">
            <?php else: ?>
            <div class="w-full h-full flex items-center justify-center text-8xl bg-brand-50/20 dark:bg-brand-900/10">📦</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Product Info -->
    <div class="flex flex-col">
        <div class="flex items-center gap-2 mb-3">
            <a href="category.php?id=<?= $product['category_id'] ?>" class="text-xs uppercase font-extrabold text-brand-600 dark:text-brand-400 bg-brand-50 dark:bg-brand-900/30 px-3 py-1 rounded-full">
                <?= $product['category_icon'] ?> <?= htmlspecialchars($product['category_name']) ?>
            </a>
            <?php if ($product['stock'] > 0): ?>
            <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/20 px-3 py-1 rounded-full">✓ <?= __('in_stock') ?></span>
            <?php else: ?>
            <span class="text-xs font-bold text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 px-3 py-1 rounded-full">⚠ <?= __('out_of_stock') ?></span>
            <?php endif; ?>
        </div>

        <h1 class="text-2xl md:text-3xl font-extrabold text-gray-900 dark:text-white mb-4 leading-tight">
            <?= htmlspecialchars($product['name']) ?>
        </h1>

        <div class="flex items-end gap-3 mb-6">
            <span class="text-3xl font-extrabold text-brand-700 dark:text-brand-400"><?= CURRENCY ?><?= number_format($product['sale_price'], 2) ?></span>
            <?php if ($product['mrp'] > $product['sale_price']): ?>
            <span class="text-lg text-gray-400 line-through mb-1"><?= CURRENCY ?><?= number_format($product['mrp'], 2) ?></span>
            <span class="text-sm font-bold text-red-500 mb-1"><?= __('save') ?> <?= round((($product['mrp'] - $product['sale_price'])/$product['mrp'])*100) ?>%</span>
            <?php endif; ?>
            <span class="text-sm text-gray-400 mb-1">/ <?= __('per') ?> <?= $product['unit'] ?></span>
        </div>

        <div class="prose prose-sm text-gray-600 dark:text-gray-400 mb-8 max-w-none">
            <h4 class="text-gray-900 dark:text-white font-bold mb-2"><?= __('description') ?></h4>
            <p><?= nl2br(htmlspecialchars($product['description'] ?: __('no_desc'))) ?></p>
        </div>

        <?php if ($product['stock'] > 0): ?>
        <form action="cart.php" method="post" class="mt-auto space-y-4">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="product_id" value="<?= $id ?>">

            <div class="flex items-center gap-4">
                <div class="w-32">
                    <label class="block text-xs font-bold text-gray-400 uppercase mb-2"><?= __('quantity') ?></label>
                    <div class="flex items-center border-2 border-gray-100 dark:border-gray-700 rounded-2xl overflow-hidden bg-gray-50 dark:bg-gray-900">
                        <button type="button" class="w-10 h-10 flex items-center justify-center text-lg font-bold text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-800 transition-colors" onclick="this.nextElementSibling.stepDown()">-</button>
                        <input type="number" name="qty" value="1" min="1" max="<?= $product['stock'] ?>" class="w-full text-center bg-transparent text-sm font-bold dark:text-white outline-none" id="qty-input">
                        <button type="button" class="w-10 h-10 flex items-center justify-center text-lg font-bold text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-800 transition-colors" onclick="this.previousElementSibling.stepUp()">+</button>
                    </div>
                </div>
                <div class="flex-1">
                    <label class="block text-xs font-bold text-gray-400 uppercase mb-2">&nbsp;</label>
                    <button type="submit" class="w-full bg-brand-600 text-white font-extrabold py-3.5 rounded-2xl text-base hover:bg-brand-700 active:scale-95 transition-all flex items-center justify-center gap-2 shadow-xl shadow-brand-100 dark:shadow-none">
                        <span><?= __('add_to_cart') ?></span> 🛒
                    </button>
                </div>
            </div>
            <p class="text-[10px] text-gray-400 font-semibold px-1"><?= __('max_available') ?>: <?= number_format($product['stock'], 2) ?> <?= $product['unit'] ?></p>
        </form>
        <?php else: ?>
        <div class="mt-auto bg-gray-50 dark:bg-gray-800 p-6 rounded-2xl text-center border-2 border-dashed border-gray-200 dark:border-gray-700">
            <p class="text-gray-500 dark:text-gray-400 font-bold mb-3"><?= __('out_of_stock') ?></p>
            <a href="https://wa.me/<?= WHATSAPP_NO ?>?text=<?= sprintf(__('notify_wa_msg'), urlencode($product['name'])) ?>" target="_blank" class="text-brand-600 dark:text-brand-400 font-bold underline hover:text-brand-800 transition-colors"><?= __('notify_whatsapp') ?> 📱</a>
        </div>
        <?php endif; ?>

        <div class="mt-8 grid grid-cols-2 gap-4">
            <div class="flex items-center gap-3 p-3 rounded-2xl bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700">
                <span class="text-xl">🚚</span>
                <div class="text-[10px] font-bold uppercase text-gray-400"><?= __('fast_free') ?><br><span class="text-gray-900 dark:text-white capitalize font-extrabold"><?= __('within_2_hours') ?></span></div>
            </div>
            <div class="flex items-center gap-3 p-3 rounded-2xl bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700">
                <span class="text-xl">🛡️</span>
                <div class="text-[10px] font-bold uppercase text-gray-400"><?= __('quality_assured') ?><br><span class="text-gray-900 dark:text-white capitalize font-extrabold"><?= __('fresh_100') ?></span></div>
            </div>
        </div>
    </div>
</div>

<!-- Related Products -->
<?php if ($related): ?>
<div class="mb-16">
    <div class="flex items-center justify-between mb-8">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white border-l-4 border-brand-500 pl-4"><?= __('similar_products') ?></h2>
        <a href="category.php?id=<?= $product['category_id'] ?>" class="text-sm font-bold text-brand-600 dark:text-brand-400 hover:underline"><?= __('view_all') ?></a>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
        <?php foreach ($related as $r): ?>
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-3 border border-gray-100 dark:border-gray-700 shadow-sm hover:shadow-lg transition-all group">
            <a href="product.php?id=<?= $r['id'] ?>" class="relative block bg-gray-100 dark:bg-gray-900 rounded-xl overflow-hidden mb-3 aspect-square">
                <?php if ($r['image']): ?>
                <img src="<?= BASE_URL ?>uploads/products/<?= htmlspecialchars($r['image']) ?>"
                     class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" alt="<?= htmlspecialchars($r['name']) ?>">
                <?php else: ?>
                <div class="w-full h-full flex items-center justify-center text-4xl bg-brand-50/20 dark:bg-brand-900/10">📦</div>
                <?php endif; ?>
            </a>
            <a href="product.php?id=<?= $r['id'] ?>" class="text-xs font-bold text-gray-900 dark:text-white line-clamp-1 leading-tight group-hover:text-brand-700 dark:group-hover:text-brand-400 transition-colors">
                <?= htmlspecialchars($r['name']) ?>
            </a>
            <div class="text-sm font-extrabold text-brand-600 dark:text-brand-400 mt-1"><?= CURRENCY ?><?= number_format($r['sale_price'], 2) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/store-footer.php'; ?>
