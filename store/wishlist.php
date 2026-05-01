<?php
// store/wishlist.php — Customer's saved wishlist items
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/flash.php';
require_once dirname(__DIR__) . '/includes/features.php';

if (!feature('customer_wishlist')) {
    header('Location: index.php'); exit;
}

require_customer_login();

$cid = $_SESSION['customer_id'];

// Remove item logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_id'])) {
    $remove_id = (int)$_POST['remove_id'];
    $pdo->prepare("DELETE FROM wishlists WHERE customer_id=? AND product_id=?")->execute([$cid, $remove_id]);
    set_flash('success', 'Removed from Wishlist.');
    header('Location: wishlist.php'); exit;
}

// Fetch wishlist products
$stmt = $pdo->prepare("
    SELECT p.*, w.created_at as added_on, c.name as category_name, c.icon as category_icon, COALESCE(i.display_qty+i.warehouse_qty, 0) as stock
    FROM wishlists w 
    JOIN products p ON p.id = w.product_id
    LEFT JOIN inventory i ON i.product_id = p.id
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE w.customer_id = ? AND p.is_active = 1 AND p.is_published = 1
    ORDER BY w.created_at DESC
");
$stmt->execute([$cid]);
$items = $stmt->fetchAll();

$store_page_title = 'My Wishlist - ' . SHOP_NAME;
require_once dirname(__DIR__) . '/includes/store-header.php';
?>

<div class="mb-8 border-b dark:border-gray-700 pb-4 flex justify-between items-center">
    <div>
        <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white flex items-center gap-2"><span class="text-red-500">♥</span> <?= __('my_wishlist') ?? 'My Wishlist' ?></h1>
        <p class="text-gray-400 dark:text-gray-500 font-medium">Your favorite saved items (<?= count($items) ?>)</p>
    </div>
</div>

<?php if (count($items) > 0): ?>
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6 mb-16">
    <?php foreach ($items as $p): ?>
    <!-- Product Card Component -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl p-3 border border-gray-100 dark:border-gray-700 shadow-sm hover:shadow-xl transition-all group flex flex-col h-full relative">
        <form method="post" class="absolute top-2 right-2 z-10">
            <input type="hidden" name="remove_id" value="<?= $p['id'] ?>">
            <button type="submit" class="w-8 h-8 rounded-full bg-white dark:bg-gray-900/80 text-gray-400 hover:text-red-500 flex items-center justify-center shadow-sm" title="Remove">&times;</button>
        </form>
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
            <span class="absolute top-2 left-2 bg-red-500 text-white text-[10px] font-bold px-2 py-1 rounded-lg uppercase tracking-wider"><?= __('save') ?? 'SAVE' ?> <?= round((($p['mrp'] - $p['sale_price'])/$p['mrp'])*100) ?>%</span>
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
            <?php if ($p['stock'] > 0): ?>
            <button type="submit" class="w-full bg-brand-600 text-white font-bold py-2.5 rounded-xl text-xs hover:bg-brand-700 active:scale-95 transition-all flex items-center justify-center gap-1 shadow-md shadow-brand-100 dark:shadow-none">
                <span><?= __('add_to_cart') ?? 'Add to Cart' ?></span> 🛒
            </button>
            <?php else: ?>
            <button disabled type="button" class="w-full bg-gray-100 dark:bg-gray-700 text-gray-400 dark:text-gray-500 cursor-not-allowed font-bold py-2.5 rounded-xl text-xs flex items-center justify-center gap-1">
                <span><?= __('out_of_stock') ?? 'Out of Stock' ?></span> ⚠
            </button>
            <?php endif; ?>
        </form>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="bg-white dark:bg-gray-800 p-20 rounded-3xl border border-gray-100 dark:border-gray-700 border-dashed text-center mb-16">
    <div class="text-6xl mb-6 scale-150 grayscale-0 opacity-50"><span class="text-gray-300">♥</span></div>
    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Your wishlist is empty</h3>
    <p class="text-gray-400 dark:text-gray-500 mb-8 max-w-xs mx-auto">Explore our catalog and find items you love!</p>
    <a href="index.php" class="bg-brand-600 text-white font-extrabold px-10 py-3.5 rounded-2xl hover:bg-brand-700 active:scale-95 transition-all shadow-xl shadow-brand-100 dark:shadow-none inline-block">Browse Products</a>
</div>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/store-footer.php'; ?>
