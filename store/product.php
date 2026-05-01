<?php
// store/product.php — Product Detail Page (Phase 2: Media, Zoom, Wishlist, Reviews, Custom Qty, WhatsApp)
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/flash.php';
require_once dirname(__DIR__) . '/includes/features.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

// Fetch Product
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name, c.icon as category_icon, COALESCE(i.display_qty+i.warehouse_qty, 0) as stock
    FROM products p
    LEFT JOIN inventory i ON i.product_id = p.id
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.id = ? AND p.is_active = 1 AND p.is_published = 1
    LIMIT 1
");
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    set_flash('error', __('product_not_found') ?? 'Product not available.');
    header('Location: index.php'); exit;
}

// Name and description based on language
$is_bn = ($_SESSION['lang'] ?? 'en') === 'bn';
$p_name = ($is_bn && !empty($product['name_bn'])) ? $product['name_bn'] : $product['name'];
$p_desc = ($is_bn && !empty($product['description_bn'])) ? $product['description_bn'] : $product['description'];

// Fetch Media Gallery
$media = $pdo->prepare("SELECT * FROM product_media WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC");
$media->execute([$id]);
$media = $media->fetchAll();

// Add main image as first media if it exists and wasn't migrated
if (empty($media) && $product['image']) {
    $media[] = ['file_path' => $product['image'], 'media_type' => 'image', 'is_primary' => 1];
}

// Fetch Reviews
$reviews = [];
$avg_rating = 0;
if (feature('product_reviews')) {
    $rev = $pdo->prepare("SELECT r.*, COALESCE(c.name, 'Anonymous') as name FROM product_reviews r LEFT JOIN customers c ON c.id=r.customer_id WHERE r.product_id = ? AND r.is_approved=1 ORDER BY r.created_at DESC");
    $rev->execute([$id]);
    $reviews = $rev->fetchAll();
    if (count($reviews) > 0) {
        $avg_rating = array_reduce($reviews, fn($c, $r) => $c + $r['rating'], 0) / count($reviews);
    }
}

// Wishlist check
$in_wishlist = false;
$cust = current_user(); // Assuming current_user() works for customers in store or logic needs adjusting. Wait, auth is user-based for admin.
// If customer is logged in: (assuming $_SESSION['customer_id'] exists)
$cid = $_SESSION['customer_id'] ?? null;
if ($cid && feature('customer_wishlist')) {
    $check_w = $pdo->prepare("SELECT 1 FROM wishlists WHERE customer_id=? AND product_id=?");
    $check_w->execute([$cid, $id]);
    $in_wishlist = (bool)$check_w->fetch();
}

// Handle Add to Wishlist / Review via POST if needed.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $cid) {
    $action = $_POST['action'] ?? '';
    if ($action === 'toggle_wishlist' && feature('customer_wishlist')) {
        if ($in_wishlist) {
            $pdo->prepare("DELETE FROM wishlists WHERE customer_id=? AND product_id=?")->execute([$cid, $id]);
            set_flash('success', 'Removed from Wishlist');
        } else {
            $pdo->prepare("INSERT IGNORE INTO wishlists (customer_id, product_id) VALUES (?,?)")->execute([$cid, $id]);
            set_flash('success', 'Added to Wishlist');
        }
        header("Location: product.php?id=$id"); exit;
    }
    if ($action === 'submit_review' && feature('product_reviews')) {
        $rating = (int)$_POST['rating'];
        $review = trim($_POST['review_text']);
        if ($rating >= 1 && $rating <= 5) {
            $pdo->prepare("INSERT INTO product_reviews (product_id, customer_id, rating, review_text) VALUES (?,?,?,?)")->execute([$id, $cid, $rating, $review]);
            set_flash('success', 'Review submitted for approval!');
            header("Location: product.php?id=$id"); exit;
        }
    }
}

// Related products
$related = $pdo->prepare("
    SELECT p.*, c.name as category_name, c.icon as category_icon
    FROM products p
    JOIN categories c ON c.id = p.category_id
    WHERE p.category_id = ? AND p.id != ? AND p.is_active = 1 AND p.is_published = 1
    LIMIT 4
");
$related->execute([$product['category_id'], $id]);
$related = $related->fetchAll();

$store_page_title = $p_name . ' - ' . SHOP_NAME;
require_once dirname(__DIR__) . '/includes/store-header.php';
?>

<!-- Zoom Setup -->
<style>
.zoom-container { overflow: hidden; position: relative; cursor: zoom-in; }
.zoom-container img { transition: transform 0.3s ease; }
.zoom-container:hover img { transform: scale(1.5); }
.product-badge { position: absolute; top: 12px; left: 12px; z-index: 10; padding: 4px 12px; border-radius: 8px; font-weight: 900; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.badge-new { background: linear-gradient(135deg, #3b82f6, #6366f1); }
.badge-sale { background: linear-gradient(135deg, #ef4444, #f43f5e); }
.badge-popular { background: linear-gradient(135deg, #f59e0b, #eab308); }
.badge-hot { background: linear-gradient(135deg, #ec4899, #f43f5e); }
</style>

<div class="mb-4 no-print">
    <a href="index.php" class="text-sm font-bold text-gray-500 hover:text-brand-600 flex items-center gap-1 transition-all">← Back to Store</a>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-16">
    
    <!-- LEFT: Media Gallery -->
    <div class="space-y-4" x-data="{ mainMedia: '<?= !empty($media) ? addslashes($media[0]['file_path']) : '' ?>', mediaType: '<?= !empty($media) ? $media[0]['media_type'] : 'image' ?>' }">
        <div class="bg-gray-50 dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden aspect-square flex items-center justify-center relative">
            
            <?php if ($product['badge']): ?>
            <div class="product-badge badge-<?= $product['badge'] ?>"><?= $product['badge'] ?></div>
            <?php endif; ?>

            <?php if (feature('customer_wishlist')): ?>
            <form method="post" class="absolute top-3 right-3 z-10">
                <input type="hidden" name="action" value="toggle_wishlist">
                <button type="submit" class="w-10 h-10 rounded-full flex items-center justify-center bg-white dark:bg-gray-900 shadow-md hover:scale-110 transition-transform">
                    <span class="text-xl <?= $in_wishlist ? 'text-red-500' : 'text-gray-300 dark:text-gray-600' ?>">♥</span>
                </button>
            </form>
            <?php endif; ?>

            <!-- Main Media Display -->
            <template x-if="mediaType === 'image'">
                <div class="w-full h-full p-4 zoom-container" @mousemove="zoomImage(event)" @mouseleave="resetZoom(event)">
                    <img :src="'<?= BASE_URL ?>uploads/products/' + mainMedia" class="w-full h-full object-contain rounded-2xl pointer-events-none" alt="<?= htmlspecialchars($p_name) ?>">
                </div>
            </template>
            <template x-if="mediaType === 'video'">
                <video :src="'<?= BASE_URL ?>uploads/products/' + mainMedia" controls autoplay muted loop class="w-full h-full object-contain bg-black"></video>
            </template>
            <template x-if="!mainMedia">
                <div class="text-6xl">📦</div>
            </template>
        </div>

        <!-- Thumbnails -->
        <?php if (count($media) > 1): ?>
        <div class="flex gap-3 overflow-x-auto pb-2 scrollbar-hide">
            <?php foreach ($media as $m): ?>
            <button @click="mainMedia = '<?= addslashes($m['file_path']) ?>'; mediaType = '<?= $m['media_type'] ?>'"
                    class="w-20 h-20 shrink-0 border-2 rounded-xl overflow-hidden shadow-sm transition-all"
                    :class="mainMedia === '<?= addslashes($m['file_path']) ?>' ? 'border-brand-500 ring-2 ring-brand-200' : 'border-gray-200 dark:border-gray-700 opacity-60 hover:opacity-100'">
                <?php if ($m['media_type'] === 'video'): ?>
                <div class="w-full h-full bg-black flex items-center justify-center text-white text-xs">▶ Video</div>
                <?php else: ?>
                <img src="<?= BASE_URL ?>uploads/products/<?= htmlspecialchars($m['file_path']) ?>" class="w-full h-full object-cover">
                <?php endif; ?>
            </button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- RIGHT: Product Info -->
    <div class="flex flex-col">
        <div class="flex items-center gap-2 mb-3">
            <a href="category.php?id=<?= $product['category_id'] ?>" class="text-[10px] uppercase font-black text-brand-600 dark:text-brand-400 bg-brand-50 dark:bg-brand-900/30 px-3 py-1 rounded-full">
                <?= $product['category_icon'] ?> <?= htmlspecialchars($product['category_name']) ?>
            </a>
            <?php if ($product['stock'] > 0): ?>
            <span class="text-[10px] uppercase font-black text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/20 px-3 py-1 rounded-full">✓ <?= __('in_stock') ?? 'In Stock' ?></span>
            <?php else: ?>
            <span class="text-[10px] uppercase font-black text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 px-3 py-1 rounded-full">⚠ <?= __('out_of_stock') ?? 'Out of Stock' ?></span>
            <?php endif; ?>
            
            <?php if (feature('product_reviews') && $avg_rating > 0): ?>
            <div class="flex items-center gap-1 text-sm bg-amber-50 dark:bg-amber-900/20 px-2 py-1 rounded-full">
                <span class="text-amber-500">★</span>
                <span class="font-bold dark:text-white"><?= number_format($avg_rating, 1) ?></span>
                <span class="text-xs text-gray-400 font-medium">(<?= count($reviews) ?>)</span>
            </div>
            <?php endif; ?>
        </div>

        <h1 class="text-2xl md:text-4xl font-extrabold text-gray-900 dark:text-white mb-4 leading-tight">
            <?= htmlspecialchars($p_name) ?>
        </h1>

        <div class="flex flex-wrap items-end gap-3 mb-6">
            <span class="text-4xl font-black text-brand-700 dark:text-brand-400"><?= CURRENCY ?><?= fmt_price($product['sale_price']) ?></span>
            <?php if ($product['mrp'] > $product['sale_price']): ?>
            <span class="text-xl font-bold text-gray-400 line-through mb-1"><?= CURRENCY ?><?= fmt_price($product['mrp']) ?></span>
            <span class="text-sm font-black text-red-500 bg-red-50 dark:bg-red-900/20 px-2 py-0.5 rounded-lg mb-1"><?= __('save') ?? 'Save' ?> <?= round((($product['mrp'] - $product['sale_price'])/$product['mrp'])*100) ?>%</span>
            <?php endif; ?>
            <span class="text-sm font-medium text-gray-400 mb-1">/ <?= $product['unit'] ?></span>
        </div>

        <div class="prose prose-sm text-gray-600 dark:text-gray-300 mb-8 max-w-none">
            <p><?= nl2br(htmlspecialchars($p_desc ?: 'No description available for this product.')) ?></p>
        </div>

        <!-- Add to Cart Form -->
        <?php if ($product['stock'] > 0): ?>
        <form action="cart.php" method="post" class="mt-auto space-y-5 bg-white dark:bg-gray-800 p-6 border border-gray-100 dark:border-gray-700 rounded-3xl shadow-xl shadow-brand-500/5">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="product_id" value="<?= $id ?>">

            <?php if ($product['allow_custom_qty']): ?>
            <!-- Custom Qty Dual-Input: Weight/Volume AND Taka Amount -->
            <?php
                $unit_label = $product['unit']; // kg, ltr, gm, ml, pcs etc.
                $price_per_unit = price_ceil((float)$product['sale_price']); // ceiled price per unit
                $min_taka = price_ceil((float)$product['min_order_amount']); // min order in taka (ceiled)
                $max_stock = (float)$product['stock'];
                // Preset steps based on unit
                $presets = [];
                if (in_array($unit_label, ['kg','ltr'])) {
                    $presets = [
                        ['label'=>'২৫০'.($unit_label=='kg'?'গ্রাম':'মিলি'), 'val'=>0.25],
                        ['label'=>'৫০০'.($unit_label=='kg'?'গ্রাম':'মিলি'), 'val'=>0.5],
                        ['label'=>'১ '.($unit_label=='kg'?'কেজি':'লিটার'),  'val'=>1],
                        ['label'=>'২ '.($unit_label=='kg'?'কেজি':'লিটার'),  'val'=>2],
                    ];
                } elseif (in_array($unit_label, ['gm','ml'])) {
                    $presets = [
                        ['label'=>'১০০ '.$unit_label, 'val'=>100],
                        ['label'=>'২৫০ '.$unit_label, 'val'=>250],
                        ['label'=>'৫০০ '.$unit_label, 'val'=>500],
                    ];
                }
                // default qty: if min_taka set, compute qty from taka; else unit-based default
                if ($min_taka > 0 && $price_per_unit > 0) {
                    $default_qty = round($min_taka / $price_per_unit, 3);
                } else {
                    $default_qty = in_array($unit_label, ['kg','ltr']) ? 1 : (in_array($unit_label, ['gm','ml']) ? 250 : 1);
                }
                $default_taka = price_ceil($default_qty * $price_per_unit);
            ?>
            <div class="space-y-4">
                <label class="block text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-1">⚖️ কতটুকু নেবেন? (পরিমাণ অথবা টাকা দিন)</label>

                <!-- Preset Quick Buttons -->
                <?php if ($presets): ?>
                <div class="flex flex-wrap gap-2" id="preset-btns">
                    <?php foreach ($presets as $pr): ?>
                    <button type="button"
                        onclick="setQty(<?= $pr['val'] ?>)"
                        class="preset-btn px-3 py-1.5 rounded-xl border-2 border-gray-200 dark:border-gray-600 text-xs font-black text-gray-600 dark:text-gray-300 hover:border-brand-500 hover:text-brand-600 hover:bg-brand-50 dark:hover:bg-brand-900/20 transition-all">
                        <?= $pr['label'] ?>
                    </button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Dual Input Row -->
                <div class="grid grid-cols-2 gap-3">
                    <!-- Qty Input -->
                    <div>
                        <label class="text-[10px] font-extrabold uppercase text-gray-400 tracking-widest mb-1 block">পরিমাণ (<?= $unit_label ?>)</label>
                        <div class="flex items-center border-2 border-brand-200 dark:border-brand-800 focus-within:border-brand-500 rounded-2xl overflow-hidden bg-brand-50 dark:bg-gray-900 transition-colors">
                            <input type="number"
                                step="0.001"
                                name="qty"
                                id="qty-input"
                                required
                                value="<?= $default_qty ?>"
                                min="<?= $min_taka > 0 && $price_per_unit > 0 ? round($min_taka / $price_per_unit, 3) : '0.001' ?>"
                                max="<?= $max_stock ?>"
                                class="w-full text-center bg-transparent text-xl font-black text-brand-700 dark:text-white outline-none py-3"
                                oninput="syncFromQty(this.value)">
                        </div>
                        <p class="text-[10px] text-gray-400 text-center mt-1 font-bold"><?= $unit_label ?></p>
                    </div>

                    <!-- OR divider -->
                    <div class="relative flex flex-col items-center justify-center">
                        <div class="absolute inset-y-0 left-0 flex items-center">
                            <div class="w-px h-full bg-gray-200 dark:bg-gray-700"></div>
                        </div>
                    </div>
                </div>

                <!-- Taka Input (separate full-width row for clarity) -->
                <div>
                    <label class="text-[10px] font-extrabold uppercase text-gray-400 tracking-widest mb-1 block">৳ টাকার পরিমাণ</label>
                    <div class="flex items-center border-2 border-emerald-200 dark:border-emerald-800 focus-within:border-emerald-500 rounded-2xl overflow-hidden bg-emerald-50 dark:bg-gray-900 transition-colors">
                        <span class="pl-4 text-xl font-black text-emerald-600 dark:text-emerald-400">৳</span>
                        <input type="number"
                            step="1"
                            id="taka-input"
                            value="<?= $default_taka ?>"
                            min="<?= $min_taka > 0 ? $min_taka : 1 ?>"
                            class="w-full text-center bg-transparent text-xl font-black text-emerald-700 dark:text-white outline-none py-3"
                            oninput="syncFromTaka(this.value)">
                    </div>
                    <p id="calc-preview" class="text-[10px] text-emerald-600 dark:text-emerald-400 text-center mt-1 font-bold">
                        ৳<?= fmt_price($price_per_unit) ?> / <?= $unit_label ?>
                    </p>
                </div>

                <?php if ($min_taka > 0): ?>
                <p class="text-[10px] font-bold text-amber-500 flex items-center gap-1">
                    ⚠️ সর্বনিম্ন অর্ডার: ৳<?= $min_taka ?> টাকা
                </p>
                <?php endif; ?>
            </div>

            <button type="submit" id="atc-btn" class="w-full bg-brand-600 text-white font-black py-4 rounded-2xl text-lg hover:bg-brand-700 active:scale-95 transition-all flex items-center justify-center gap-2 shadow-xl shadow-brand-500/30">
                <span><?= __('add_to_cart') ?? 'Add to Cart' ?></span> 🛒
            </button>

            <script>
            (function() {
                const pricePerUnit = <?= $price_per_unit ?>;
                const maxStock     = <?= $max_stock ?>;
                const minTaka      = <?= $min_taka ?>; // minimum order in taka
                const minQty       = minTaka > 0 && pricePerUnit > 0 ? minTaka / pricePerUnit : 0.001;

                const qtyInput  = document.getElementById('qty-input');
                const takaInput = document.getElementById('taka-input');
                const preview   = document.getElementById('calc-preview');
                const atcBtn    = document.getElementById('atc-btn');
                const presetBtns = document.querySelectorAll('.preset-btn');

                function ceilTaka(t) { return Math.ceil(t); }

                function updatePreview(qty, taka) {
                    const ceiled = ceilTaka(taka);
                    preview.textContent = '৳' + ceiled + ' = ' + qty.toFixed(3) + ' <?= $unit_label ?>';
                    // Validate
                    const ok = qty >= minQty && qty <= maxStock;
                    atcBtn.disabled = !ok;
                    atcBtn.classList.toggle('opacity-50', !ok);
                    atcBtn.classList.toggle('cursor-not-allowed', !ok);
                    // Highlight active preset
                    presetBtns.forEach(btn => {
                        const bv = parseFloat(btn.getAttribute('onclick').match(/setQty\(([^)]+)\)/)[1]);
                        btn.classList.toggle('border-brand-500', Math.abs(parseFloat(qty) - bv) < 0.0001);
                        btn.classList.toggle('text-brand-600', Math.abs(parseFloat(qty) - bv) < 0.0001);
                        btn.classList.toggle('bg-brand-50', Math.abs(parseFloat(qty) - bv) < 0.0001);
                    });
                }

                window.syncFromQty = function(val) {
                    const qty  = parseFloat(val) || 0;
                    const taka = qty * pricePerUnit;
                    const ceiled = ceilTaka(taka);
                    takaInput.value = ceiled;
                    updatePreview(qty, taka);
                };

                window.syncFromTaka = function(val) {
                    const taka = parseFloat(val) || 0;
                    const qty  = pricePerUnit > 0 ? taka / pricePerUnit : 0;
                    qtyInput.value = qty.toFixed(3);
                    updatePreview(qty, taka);
                };

                window.setQty = function(val) {
                    qtyInput.value = val;
                    syncFromQty(val);
                };

                // Init
                syncFromQty(qtyInput.value);
            })();
            </script>

            <?php else: ?>
            <!-- Standard +/- Qty Input -->
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-4">
                <div class="flex items-center border-2 border-gray-200 dark:border-gray-700 rounded-2xl overflow-hidden bg-gray-50 dark:bg-gray-900 h-14">
                    <button type="button" class="w-14 h-full flex items-center justify-center text-2xl font-bold text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-800 transition-colors" onclick="this.nextElementSibling.stepDown()">-</button>
                    <input type="number" name="qty" value="1" min="1" max="<?= $product['stock'] ?>" class="w-16 text-center bg-transparent text-lg font-black dark:text-white outline-none" id="qty-input">
                    <button type="button" class="w-14 h-full flex items-center justify-center text-2xl font-bold text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-800 transition-colors" onclick="this.previousElementSibling.stepUp()">+</button>
                </div>
                <button type="submit" class="flex-1 h-14 bg-brand-600 text-white font-black px-6 rounded-2xl text-lg hover:bg-brand-700 active:scale-95 transition-all flex items-center justify-center gap-2 shadow-xl shadow-brand-500/30">
                    <span><?= __('add_to_cart') ?? 'Add to Cart' ?></span> 🛒
                </button>
            </div>
            <?php endif; ?>
            
        </form>
        <?php else: ?>
        <div class="mt-auto bg-gray-50 dark:bg-gray-800 p-8 rounded-3xl border-2 border-dashed border-gray-200 dark:border-gray-700 text-center shadow-inner">
            <div class="text-4xl mb-3">🪹</div>
            <p class="text-gray-500 dark:text-gray-400 font-bold mb-4"><?= __('out_of_stock') ?? 'Out of stock right now.' ?></p>
        </div>
        <?php endif; ?>

        <!-- WhatsApp Order Button -->
        <?php if (feature('whatsapp_order_btn')): ?>
        <a href="https://wa.me/<?= WHATSAPP_NO ?>?text=Hello,%20I%20want%20to%20order:%20<?= urlencode($p_name) ?>" target="_blank" class="mt-4 w-full bg-[#25D366] text-white font-bold py-3.5 rounded-2xl text-center hover:bg-[#1ebd5a] active:scale-95 transition-all flex items-center justify-center gap-2 shadow-lg shadow-[#25D366]/30">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            <?= __('order_on_whatsapp') ?? 'Order via WhatsApp' ?>
        </a>
        <?php endif; ?>

    </div>
</div>

<!-- Product Reviews Section -->
<?php if (feature('product_reviews')): ?>
<div class="mb-16 card dark:bg-gray-800 dark:border-gray-700 p-6 md:p-8 shadow-xl">
    <div class="flex items-center justify-between mb-8 pb-4 border-b dark:border-gray-700">
        <h2 class="text-2xl font-black text-gray-900 dark:text-white flex items-center gap-2">⭐ <?= __('reviews') ?? 'Customer Reviews' ?> (<?= count($reviews) ?>)</h2>
        <?php if ($avg_rating > 0): ?>
        <div class="flex items-center gap-2 text-2xl"><span class="text-amber-500">★</span><span class="font-black dark:text-white"><?= number_format($avg_rating, 1) ?></span></div>
        <?php endif; ?>
    </div>

    <!-- Review Form -->
    <?php if ($cid): ?>
    <form method="post" class="mb-10 bg-gray-50 dark:bg-gray-900 p-6 rounded-3xl border border-gray-100 dark:border-gray-700">
        <h3 class="font-bold text-gray-900 dark:text-white mb-4"><?= __('write_review') ?? 'Write your review' ?></h3>
        <input type="hidden" name="action" value="submit_review">
        <div class="mb-4">
            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Rating</label>
            <div class="flex gap-2 flex-row-reverse justify-end" id="star-rating">
                <input type="radio" name="rating" value="5" id="r5" class="peer hidden" required><label for="r5" class="text-3xl text-gray-300 peer-checked:text-amber-500 hover:text-amber-400 cursor-pointer transition">★</label>
                <input type="radio" name="rating" value="4" id="r4" class="peer hidden"><label for="r4" class="text-3xl text-gray-300 peer-checked:text-amber-500 hover:text-amber-400 cursor-pointer transition">★</label>
                <input type="radio" name="rating" value="3" id="r3" class="peer hidden"><label for="r3" class="text-3xl text-gray-300 peer-checked:text-amber-500 hover:text-amber-400 cursor-pointer transition">★</label>
                <input type="radio" name="rating" value="2" id="r2" class="peer hidden"><label for="r2" class="text-3xl text-gray-300 peer-checked:text-amber-500 hover:text-amber-400 cursor-pointer transition">★</label>
                <input type="radio" name="rating" value="1" id="r1" class="peer hidden"><label for="r1" class="text-3xl text-gray-300 peer-checked:text-amber-500 hover:text-amber-400 cursor-pointer transition">★</label>
            </div>
            <style>
                #star-rating label:hover, #star-rating label:hover ~ label, #star-rating input:checked ~ label { color: #f59e0b; }
            </style>
        </div>
        <div class="mb-4">
            <textarea name="review_text" required rows="3" class="w-full bg-white dark:bg-gray-800 border dark:border-gray-700 rounded-2xl p-4 dark:text-white outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20" placeholder="What did you like or dislike?"></textarea>
        </div>
        <button type="submit" class="bg-gray-900 dark:bg-brand-600 text-white font-bold px-6 py-2.5 rounded-xl text-sm shadow-md">Submit Review</button>
    </form>
    <?php else: ?>
    <div class="mb-10 text-sm text-gray-500 dark:text-gray-400">Please <a href="login.php" class="text-brand-600 font-bold underline">log in</a> to write a review.</div>
    <?php endif; ?>

    <!-- Review List -->
    <div class="space-y-6">
        <?php foreach ($reviews as $rev): ?>
        <div class="border-b dark:border-gray-700 pb-6 last:border-0 last:pb-0">
            <div class="flex items-center gap-3 mb-2">
                <div class="bg-brand-100 dark:bg-brand-900/30 text-brand-700 dark:text-brand-400 w-10 h-10 rounded-full flex items-center justify-center font-black uppercase text-sm"><?= substr($rev['name'],0,1) ?></div>
                <div>
                    <div class="font-bold text-gray-900 dark:text-white text-sm"><?= htmlspecialchars($rev['name']) ?></div>
                    <div class="text-[10px] text-gray-400"><?= date('M d, Y', strtotime($rev['created_at'])) ?></div>
                </div>
                <div class="ml-auto text-amber-500 tracking-widest text-sm">
                    <?= str_repeat('★', $rev['rating']) ?><span class="text-gray-300 dark:text-gray-600"><?= str_repeat('★', 5-$rev['rating']) ?></span>
                </div>
            </div>
            <p class="text-gray-600 dark:text-gray-300 text-sm mt-3"><?= nl2br(htmlspecialchars($rev['review_text'])) ?></p>
        </div>
        <?php endforeach; ?>
        <?php if (empty($reviews)): ?>
        <p class="text-center text-gray-400 py-8 italic"><?= __('no_reviews_yet') ?? 'No reviews yet for this product.' ?></p>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Related Products -->
<?php if ($related): ?>
<div class="mb-16">
    <div class="flex items-center justify-between mb-8">
        <h2 class="text-2xl font-black text-gray-900 dark:text-white border-l-4 border-brand-500 pl-4"><?= __('related_products') ?? 'Related Products' ?></h2>
        <a href="category.php?id=<?= $product['category_id'] ?>" class="text-sm font-bold text-brand-600 dark:text-brand-400 hover:underline">View All Category</a>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6">
        <?php foreach ($related as $r): ?>
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-3 border border-gray-100 dark:border-gray-700 shadow-sm hover:shadow-xl transition-all group flex flex-col h-full">
            <a href="product.php?id=<?= $r['id'] ?>" class="relative block bg-gray-50 dark:bg-gray-700 rounded-xl overflow-hidden mb-3 aspect-square">
                <?php if ($r['image']): ?>
                <img src="<?= BASE_URL ?>uploads/products/<?= htmlspecialchars($r['image']) ?>"
                     class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" alt="<?= htmlspecialchars($r['name']) ?>">
                <?php else: ?>
                <div class="w-full h-full flex items-center justify-center text-4xl bg-brand-50/20 dark:bg-brand-900/10">📦</div>
                <?php endif; ?>
                <?php if ($r['badge']): ?>
                <span class="absolute top-2 left-2 px-2 py-1 rounded-md text-[9px] font-black uppercase text-white badge-<?= $r['badge'] ?> shadow-sm"><?= $r['badge'] ?></span>
                <?php endif; ?>
            </a>
            <a href="product.php?id=<?= $r['id'] ?>" class="text-sm font-bold text-gray-900 dark:text-white line-clamp-2 leading-tight group-hover:text-brand-700 dark:group-hover:text-brand-400 transition-colors mb-2">
                <?= htmlspecialchars(($_SESSION['lang']??'en')==='bn' && $r['name_bn'] ? $r['name_bn'] : $r['name']) ?>
            </a>
            <div class="flex items-end gap-2 mt-auto">
                <span class="text-lg font-black text-brand-700 dark:text-brand-400"><?= CURRENCY ?><?= fmt_price($r['sale_price']) ?></span>
                <?php if ($r['mrp'] > $r['sale_price']): ?>
                <span class="text-[10px] text-gray-400 line-through mb-1"><?= CURRENCY ?><?= fmt_price($r['mrp']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Image Zoom Logic -->
<script>
function zoomImage(e) {
    const container = e.currentTarget;
    const img = container.querySelector('img');
    const rect = container.getBoundingClientRect();
    const x = e.clientX - rect.left; // x position within the element
    const y = e.clientY - rect.top;  // y position within the element
    
    // Calculate percentage and apply transform origin to image
    const xPercent = (x / rect.width) * 100;
    const yPercent = (y / rect.height) * 100;
    
    img.style.transformOrigin = `${xPercent}% ${yPercent}%`;
}
function resetZoom(e) {
    const img = e.currentTarget.querySelector('img');
    img.style.transformOrigin = 'center center';
}
</script>

<?php require_once dirname(__DIR__) . '/includes/store-footer.php'; ?>
