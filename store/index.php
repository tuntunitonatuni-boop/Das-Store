<?php
// store/index.php — Online store homepage
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/flash.php';

$search = trim($_GET['q'] ?? '');
$where = "WHERE p.is_active = 1 AND p.is_published = 1 AND (i.display_qty + i.warehouse_qty) > 0";
$params = [];
if ($search) {
    $where .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $params = ["%$search%", "%$search%"];
}

$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name, c.icon as category_icon, COALESCE(i.display_qty+i.warehouse_qty, 0) as stock
    FROM products p
    LEFT JOIN inventory i ON i.product_id = p.id
    LEFT JOIN categories c ON c.id = p.category_id
    $where
    ORDER BY p.id DESC
    LIMIT 24
");
$stmt->execute($params);
$products = $stmt->fetchAll();

// Fetch all categories for grid
$categories = $pdo->query("SELECT id, name, slug, icon FROM categories WHERE is_active=1 ORDER BY sort_order")->fetchAll();

$store_page_title = sprintf(__('welcome_to'), SHOP_NAME);
require_once dirname(__DIR__) . '/includes/store-header.php';
?>

<!-- Banner Section -->
<div class="mb-10 text-center py-10 bg-gradient-to-br from-brand-600 to-emerald-800 dark:from-brand-800 dark:to-emerald-950 rounded-3xl text-white px-6">
    <h1 class="text-3xl md:text-5xl font-extrabold mb-4 animate-fade-in"><?= __('freshness_delivered') ?></h1>
    <p class="text-white/80 text-lg md:text-xl max-w-2xl mx-auto mb-8">
        <?= __('home_subtitle') ?>
    </p>
    <a href="#all-products" class="bg-white text-brand-700 font-bold px-8 py-3.5 rounded-xl hover:shadow-xl transition-all inline-block"><?= __('shop_now') ?> ↓</a>
</div>

<!-- Category Grid -->
<div class="mb-12">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 border-l-4 border-brand-500 pl-3"><?= __('browse_categories') ?></h2>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-3">
        <?php foreach ($categories as $cat): ?>
        <a href="category.php?id=<?= $cat['id'] ?>" class="bg-white dark:bg-gray-800 p-4 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm hover:shadow-md hover:border-brand-200 dark:hover:border-brand-500 transition-all text-center group">
            <div class="text-3xl mb-2 group-hover:scale-110 transition-transform"><?= $cat['icon'] ?: '🏷️' ?></div>
            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300 group-hover:text-brand-700 dark:group-hover:text-brand-400"><?= htmlspecialchars($cat['name']) ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Product Grid -->
<div id="all-products">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 border-l-4 border-brand-500 pl-3">
            <?= $search ? __('search_results') . ': "'.htmlspecialchars($search).'"' : __('latest_arrivals') ?>
        </h2>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6">
        <?php foreach ($products as $p): ?>
        <!-- Product Card Component -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-3 border border-gray-100 dark:border-gray-700 shadow-sm hover:shadow-xl transition-all group flex flex-col h-full" data-product-card data-name="<?= htmlspecialchars($p['name']) ?>">
            <a href="product.php?id=<?= $p['id'] ?>" class="relative block bg-gray-50 dark:bg-gray-700 rounded-xl overflow-hidden mb-3 aspect-square">
                <?php if ($p['image']): ?>
                <img src="<?= BASE_URL ?>uploads/products/<?= htmlspecialchars($p['image']) ?>"
                     class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" alt="<?= htmlspecialchars($p['name']) ?>">
                <?php else: ?>
                <div class="w-full h-full flex items-center justify-center text-4xl bg-brand-50/20 dark:bg-brand-900/10">📦</div>
                <?php endif; ?>

                <?php if ($p['badge']): ?>
                <span class="absolute top-2 left-2 px-2 py-1 rounded-md text-[9px] font-black uppercase text-white shadow-sm" style="background: var(--brand-500);"><?= $p['badge'] ?></span>
                <?php elseif ($p['mrp'] > $p['sale_price']): ?>
                <span class="absolute top-2 left-2 bg-red-500 text-white text-[10px] font-bold px-2 py-1 rounded-lg uppercase tracking-wider"><?= __('save') ?> <?= round((($p['mrp'] - $p['sale_price'])/$p['mrp'])*100) ?>%</span>
                <?php endif; ?>
            </a>

            <div class="flex-1 px-1">
                <a href="category.php?id=<?= $p['category_id'] ?>" class="text-[10px] uppercase font-bold text-brand-600 dark:text-brand-400 block mb-1">
                    <?= $p['category_icon'] ?> <?= htmlspecialchars($p['category_name']) ?>
                </a>
                <a href="product.php?id=<?= $p['id'] ?>" class="text-sm font-bold text-gray-900 dark:text-gray-100 line-clamp-2 leading-tight group-hover:text-brand-700 dark:group-hover:text-brand-400 transition-colors mb-2">
                    <?= htmlspecialchars(($_SESSION['lang']??'en')==='bn' && $p['name_bn'] ? $p['name_bn'] : $p['name']) ?>
                </a>
                <div class="flex items-end gap-2 mb-3">
                    <span class="text-lg font-extrabold text-gray-900 dark:text-gray-100"><?= CURRENCY ?><?= fmt_price($p['sale_price']) ?></span>
                    <?php if ($p['mrp'] > $p['sale_price']): ?>
                    <span class="text-xs text-gray-400 dark:text-gray-500 line-through mb-0.5"><?= CURRENCY ?><?= fmt_price($p['mrp']) ?></span>
                    <?php endif; ?>
                    <span class="text-[10px] text-gray-400 dark:text-gray-500 mb-1">/ <?= $p['unit'] ?></span>
                </div>
            </div>

            <form action="cart.php" method="post" class="mt-auto">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                <button type="submit" class="w-full bg-brand-600 text-white font-bold py-2.5 rounded-xl text-xs hover:bg-brand-700 active:scale-95 transition-all flex items-center justify-center gap-1 shadow-md shadow-brand-100 dark:shadow-none">
                    <span><?= __('add_to_cart') ?></span> 🛒
                </button>
            </form>
        </div>
        <?php endforeach; ?>

        <?php if (empty($products)): ?>
        <div class="col-span-full py-20 text-center animate-pulse">
            <span class="text-6xl mb-4 block">🔎</span>
            <p class="text-gray-400 dark:text-gray-500 text-lg"><?= __('no_products_found') ?> <?= __('matching') ?> "<?= htmlspecialchars($search) ?>"</p>
            <a href="index.php" class="text-brand-600 dark:text-brand-400 font-bold underline mt-4 inline-block"><?= __('view_all_products') ?></a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/store-footer.php'; ?>
