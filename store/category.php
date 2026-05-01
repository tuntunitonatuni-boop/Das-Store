<?php
// store/category.php — Products by category
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/flash.php';

$cat_id = (int)($_GET['id'] ?? 0);
if (!$cat_id) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ? AND is_active = 1");
$stmt->execute([$cat_id]);
$category = $stmt->fetch();

if (!$category) { header('Location: index.php'); exit; }

$search = trim($_GET['q'] ?? '');
$where = "WHERE p.category_id = ? AND p.is_active = 1 AND (i.display_qty + i.warehouse_qty) > 0";
$params = [$cat_id];
if ($search) {
    $where .= " AND p.name LIKE ?";
    $params[] = "%$search%";
}

$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name, c.icon as category_icon, COALESCE(i.display_qty+i.warehouse_qty, 0) as stock
    FROM products p
    LEFT JOIN inventory i ON i.product_id = p.id
    LEFT JOIN categories c ON c.id = p.category_id
    $where
    ORDER BY p.name ASC
");
$stmt->execute($params);
$products = $stmt->fetchAll();

$store_page_title = $category['name'] . ' - ' . SHOP_NAME;
require_once dirname(__DIR__) . '/includes/store-header.php';
?>

<div class="mb-10 flex flex-col md:flex-row md:items-center justify-between border-b dark:border-gray-700 pb-4 gap-4">
    <div class="flex items-center gap-3">
        <span class="text-4xl"><?= $category['icon'] ?: '🏷️' ?></span>
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white"><?= htmlspecialchars($category['name']) ?></h1>
            <p class="text-sm text-gray-400 dark:text-gray-500"><?= count($products) ?> <?= __('items_available') ?></p>
        </div>
    </div>
    <form method="get" class="no-print relative w-full md:w-auto">
        <input type="hidden" name="id" value="<?= $cat_id ?>">
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="<?= __('search_in') . htmlspecialchars($category['name']) ?>..."
               class="w-full md:w-64 bg-white dark:bg-gray-800 dark:text-white border dark:border-gray-700 rounded-full px-4 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100 dark:focus:ring-brand-900/30 transition">
        <button type="submit" class="absolute right-3 top-2.5 text-gray-400 dark:text-gray-500">🔍</button>
    </form>
</div>

<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6">
    <?php foreach ($products as $p): ?>
    <div class="bg-white dark:bg-gray-800 rounded-2xl p-3 border border-gray-100 dark:border-gray-700 shadow-sm hover:shadow-xl dark:hover:shadow-brand-900/10 transition-all group flex flex-col h-full">
        <a href="product.php?id=<?= $p['id'] ?>" class="relative block bg-gray-50 dark:bg-gray-700 rounded-xl overflow-hidden mb-3 aspect-square">
            <?php if ($p['image']): ?>
            <img src="<?= BASE_URL ?>uploads/products/<?= htmlspecialchars($p['image']) ?>"
                 class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" alt="<?= htmlspecialchars($p['name']) ?>">
            <?php else: ?>
            <div class="w-full h-full flex items-center justify-center text-4xl bg-brand-50/30 dark:bg-brand-900/10">📦</div>
            <?php endif; ?>
        </a>

        <div class="flex-1 px-1">
            <a href="product.php?id=<?= $p['id'] ?>" class="text-sm font-bold text-gray-900 dark:text-white line-clamp-2 leading-tight group-hover:text-brand-700 dark:group-hover:text-brand-400 transition-colors mb-2">
                <?= htmlspecialchars($p['name']) ?>
            </a>
            <div class="flex items-end gap-2 mb-3">
                <span class="text-lg font-extrabold text-gray-900 dark:text-white"><?= CURRENCY ?><?= fmt_price($p['sale_price']) ?></span>
                <?php if ($p['mrp'] > $p['sale_price']): ?>
                <span class="text-xs text-gray-400 dark:text-gray-500 line-through mb-0.5"><?= CURRENCY ?><?= fmt_price($p['mrp']) ?></span>
                <?php endif; ?>
                <span class="text-[10px] text-gray-400 dark:text-gray-500 mb-1">/ <?= $p['unit'] ?></span>
            </div>
        </div>

        <form action="cart.php" method="post" class="mt-auto">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
            <button type="submit" class="w-full bg-brand-600 text-white font-bold py-2.5 rounded-xl text-xs hover:bg-brand-700 dark:hover:bg-brand-500 active:scale-95 transition-all flex items-center justify-center gap-1 shadow-md shadow-brand-100 dark:shadow-none">
                <span><?= __('add_to_cart') ?></span> 🛒
            </button>
        </form>
    </div>
    <?php endforeach; ?>

    <?php if (empty($products)): ?>
    <div class="col-span-full py-20 text-center">
        <span class="text-6xl mb-4 block">📦</span>
        <p class="text-gray-400 dark:text-gray-500 text-lg"><?= __('no_products_cat') ?></p>
        <a href="index.php" class="text-brand-600 dark:text-brand-400 font-bold underline mt-4 inline-block"><?= __('browse_all_products') ?></a>
    </div>
    <?php endif; ?>
</div>

<?php require_once dirname(__DIR__) . '/includes/store-footer.php'; ?>
