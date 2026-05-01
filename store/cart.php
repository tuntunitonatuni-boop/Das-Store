<?php
// store/cart.php — Shopping cart management
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/flash.php';
require_once dirname(__DIR__) . '/includes/features.php';

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $pid    = (int)($_POST['product_id'] ?? 0);

    if ($action === 'add' && $pid) {
        $qty = (float)($_POST['qty'] ?? 1);
        $stmt = $pdo->prepare("SELECT p.id, p.name, p.sale_price, p.unit, p.image, p.allow_custom_qty, p.min_order_amount FROM products p WHERE p.id = ? AND p.is_active = 1");
        $stmt->execute([$pid]);
        $product = $stmt->fetch();

        if ($product) {
            if (isset($_SESSION['cart'][$pid])) {
                $_SESSION['cart'][$pid]['qty'] += $qty;
            } else {
                $_SESSION['cart'][$pid] = [
                    'id'           => $product['id'],
                    'name'         => $product['name'],
                    'price'        => (float)$product['sale_price'],
                    'unit'         => $product['unit'],
                    'image'        => $product['image'],
                    'allow_custom' => $product['allow_custom_qty'],
                    'min_order'    => $product['min_order_amount'],
                    'qty'          => $qty
                ];
            }
            set_flash('success', "'{$product['name']}' added to cart! 🛒");
        }
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?: 'index.php')); exit;
    }

    if ($action === 'update' && $pid) {
        $qty = (float)($_POST['qty'] ?? 1);
        if ($qty <= 0) unset($_SESSION['cart'][$pid]);
        else if (isset($_SESSION['cart'][$pid])) $_SESSION['cart'][$pid]['qty'] = $qty;
        header('Location: cart.php'); exit;
    }

    if ($action === 'remove' && $pid) {
        unset($_SESSION['cart'][$pid]);
        set_flash('success', 'Item removed from cart.');
        header('Location: cart.php'); exit;
    }

    if ($action === 'clear') {
        $_SESSION['cart'] = [];
        set_flash('success', 'Cart cleared.');
        header('Location: cart.php'); exit;
    }

    if ($action === 'apply_coupon') {
        $code = trim($_POST['coupon_code'] ?? '');
        $c = $pdo->prepare("SELECT * FROM coupons WHERE code=?");
        $c->execute([strtoupper($code)]);
        $c = $c->fetch();
        
        $current_total = 0;
        foreach ($_SESSION['cart'] as $item) $current_total += $item['price'] * $item['qty'];
        
        if (!$c) {
            set_flash('error', "দুঃখিত, এই কুপন কোডটি সঠিক নয়। (Invalid coupon)");
        } elseif ($c['is_active'] != 1) {
            set_flash('error', "এই কুপনটি বর্তমানে বন্ধ আছে। (Coupon is inactive)");
        } elseif ($c['expires_at'] && strtotime($c['expires_at']) < strtotime(date('Y-m-d'))) {
            set_flash('error', "এই কুপনের মেয়াদ শেষ হয়ে গেছে। (Coupon expired)");
        } elseif ($c['usage_limit'] > 0 && $c['used_count'] >= $c['usage_limit']) {
            set_flash('error', "এই কুপনটির ব্যবহারের সীমা শেষ হয়ে গেছে। (Limit reached)");
        } elseif ($current_total < ($c['min_order'] ?? 0)) {
            set_flash('error', "এই কুপনটি ব্যবহার করতে হলে অন্তত ৳" . number_format($c['min_order'], 2) . " এর অর্ডার করতে হবে।");
        } else {
            $_SESSION['checkout_coupon'] = $c;
            set_flash('success', "কুপন সফলভাবে যোগ করা হয়েছে! 🎉");
        }
        header('Location: cart.php'); exit;
    }
    
    if ($action === 'remove_coupon') {
        unset($_SESSION['checkout_coupon']);
        header('Location: cart.php'); exit;
    }
}

$cart_items = $_SESSION['cart'];
$subtotal = 0;
foreach ($cart_items as $item) $subtotal += $item['price'] * $item['qty'];

// Calculate Discounts
$discount = 0;
if (isset($_SESSION['checkout_coupon'])) {
    $c = $_SESSION['checkout_coupon'];
    if ($c['type'] === 'percent') {
        $discount = $subtotal * ($c['value'] / 100);
        if ($c['max_discount'] > 0 && $discount > $c['max_discount']) {
            $discount = $c['max_discount'];
        }
    } else {
        $discount = $c['value'];
    }
    // Re-verify min order in case cart changed
    if ($subtotal < ($c['min_order'] ?? 0)) {
        unset($_SESSION['checkout_coupon']);
        $discount = 0;
        set_flash('error', "কার্টের পরিমাণ কমে যাওয়ায় কুপনটি বাতিল করা হয়েছে।");
    }
}

$total = max(0, $subtotal - $discount);

$store_page_title = 'Your Cart - ' . SHOP_NAME;
require_once dirname(__DIR__) . '/includes/store-header.php';
?>

<div class="mb-8 border-b dark:border-gray-700 pb-4">
    <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white"><?= __('shopping_cart') ?></h1>
    <p class="text-gray-400 dark:text-gray-500 font-medium"><?= __('review_items') ?></p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-20">
    <!-- Items list -->
    <div class="lg:col-span-2 space-y-4">
        <?php if ($cart_items): ?>
        <div class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-700/50 border-b dark:border-gray-700 text-gray-400 dark:text-gray-300 uppercase text-[10px] font-extrabold tracking-widest">
                        <th class="p-4 text-left"><?= __('products') ?></th>
                        <th class="p-4 text-center"><?= __('quantity') ?></th>
                        <th class="p-4 text-right"><?= __('subtotal') ?></th>
                        <th class="p-4"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
                    <?php foreach ($cart_items as $id => $item): ?>
                    <tr class="group hover:bg-brand-50/20 dark:hover:bg-brand-900/10 transition-all">
                        <td class="p-4">
                            <div class="flex items-center gap-4">
                                <div class="w-14 h-14 bg-gray-50 dark:bg-gray-700 rounded-xl overflow-hidden flex-shrink-0 border border-gray-100 dark:border-gray-600">
                                    <?php if ($item['image']): ?>
                                    <img src="<?= BASE_URL ?>uploads/products/<?= $item['image'] ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center text-xl">📦</div>
                                    <?php endif; ?>
                                </div>
                                <div class="min-w-0">
                                    <div class="font-bold text-gray-900 dark:text-white group-hover:text-brand-700 dark:group-hover:text-brand-400 transition-colors truncate"><?= htmlspecialchars($item['name']) ?></div>
                                    <div class="text-[10px] text-gray-400 dark:text-gray-500 font-bold uppercase"><?= CURRENCY ?><?= fmt_price($item['price']) ?> / <?= $item['unit'] ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="p-4">
                            <form action="cart.php" method="post" class="flex items-center justify-center gap-1">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="product_id" value="<?= $id ?>">
                                
                                <?php if (!empty($item['allow_custom'])): ?>
                                <?php $cart_price = $item['price']; ?>
                                <div class="flex flex-col items-center gap-1">
                                    <!-- Qty field -->
                                    <div class="flex items-center border-2 border-brand-100 dark:border-brand-900 rounded-xl overflow-hidden bg-brand-50 dark:bg-gray-800">
                                        <input type="number" name="qty" step="0.001"
                                            min="<?= $item['min_order'] > 0 ? round($item['min_order'] / max(price_ceil($item['price']), 1), 3) : '0.001' ?>"
                                            value="<?= $item['qty'] ?>"
                                            id="cart-qty-<?= $id ?>"
                                            class="w-16 py-1.5 text-center font-bold text-brand-700 dark:text-white outline-none text-xs bg-transparent"
                                            oninput="cartSyncQty(<?= $id ?>, <?= price_ceil($item['price']) ?>)"
                                            onchange="this.form.submit()">
                                        <span class="pr-2 text-[10px] text-gray-400 font-bold"><?= $item['unit'] ?></span>
                                    </div>
                                    <!-- Taka display (readonly live calc) -->
                                    <div class="flex items-center border-2 border-emerald-100 dark:border-emerald-900 rounded-xl overflow-hidden bg-emerald-50 dark:bg-gray-800">
                                        <span class="pl-2 text-[10px] font-bold text-emerald-500">৳</span>
                                        <input type="number" step="1"
                                            id="cart-taka-<?= $id ?>"
                                            value="<?= price_ceil($item['qty'] * $item['price']) ?>"
                                            class="w-16 py-1.5 text-center font-bold text-emerald-600 dark:text-white outline-none text-xs bg-transparent"
                                            oninput="cartSyncTaka(<?= $id ?>, <?= price_ceil($cart_price) ?>)">
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="flex items-center border-2 border-gray-100 dark:border-gray-700 rounded-xl overflow-hidden bg-white dark:bg-gray-800">
                                    <button type="submit" name="qty" value="<?= $item['qty'] - 1 ?>" class="px-3 py-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400 font-bold transition-all">-</button>
                                    <input type="text" readonly value="<?= $item['qty'] ?>" class="w-10 text-center font-bold text-gray-700 dark:text-white outline-none text-xs bg-transparent">
                                    <button type="submit" name="qty" value="<?= $item['qty'] + 1 ?>" class="px-3 py-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 dark:text-gray-400 font-bold transition-all">+</button>
                                </div>
                                <?php endif; ?>
                            </form>
                        </td>
                        <td class="p-4 text-right font-extrabold text-gray-900 dark:text-white text-base"><?= CURRENCY ?><?= fmt_price($item['price'] * $item['qty']) ?></td>
                        <td class="p-4 text-center">
                            <form action="cart.php" method="post">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="product_id" value="<?= $id ?>">
                                <button type="submit" class="text-gray-300 hover:text-red-500 dark:text-gray-600 dark:hover:text-red-400 transition-colors text-xl font-bold">&times;</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="text-right">
            <form action="cart.php" method="post">
                <input type="hidden" name="action" value="clear">
                <button type="submit" class="text-xs font-bold text-red-500 hover:underline dark:text-red-400"><?= __('empty_cart') ?></button>
            </form>
        </div>
        <?php else: ?>
        <div class="bg-white dark:bg-gray-800 p-20 rounded-3xl border border-gray-100 dark:border-gray-700 border-dashed text-center">
            <div class="text-6xl mb-6 scale-150 grayscale-0 opacity-50">🛒</div>
            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2"><?= __('cart_lonely') ?></h3>
            <p class="text-gray-400 dark:text-gray-500 mb-8 max-w-xs mx-auto"><?= __('cart_lonely_desc') ?></p>
            <a href="index.php" class="bg-brand-600 text-white font-extrabold px-10 py-3.5 rounded-2xl hover:bg-brand-700 active:scale-95 transition-all shadow-xl shadow-brand-100 dark:shadow-none inline-block"><?= __('start_shopping') ?></a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Summary -->
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 border border-gray-100 dark:border-gray-700 shadow-sm sticky top-24">
            <h3 class="text-lg font-extrabold text-gray-900 dark:text-white mb-6 border-b dark:border-gray-700 pb-4"><?= __('order_summary') ?></h3>
            <div class="space-y-4 mb-8">
                <div class="flex justify-between items-center text-sm font-medium text-gray-500 dark:text-gray-400">
                    <span><?= __('subtotal') ?></span>
                    <span class="text-gray-900 dark:text-white"><?= CURRENCY ?><?= fmt_price($subtotal) ?></span>
                </div>
                
                <?php if ($discount > 0): ?>
                <div class="flex justify-between items-center text-sm font-medium text-red-500">
                    <span>Discount (<?= htmlspecialchars($_SESSION['checkout_coupon']['code']) ?>)
                        <form action="cart.php" method="post" class="inline ml-1">
                            <input type="hidden" name="action" value="remove_coupon">
                            <button type="submit" class="text-red-400 underline">Remove</button>
                        </form>
                    </span>
                    <span>- <?= CURRENCY ?><?= number_format($discount, 2) ?></span>
                </div>
                <?php endif; ?>

                <div class="flex justify-between items-center text-sm font-medium text-gray-500 dark:text-gray-400">
                    <span><?= __('delivery_fee') ?></span>
                    <span class="text-emerald-600 dark:text-emerald-400 font-bold uppercase text-[10px]"><?= __('free_delivery') ?></span>
                </div>

                <!-- Coupons Section in Cart -->
                <?php if (feature('discount_coupons') && !isset($_SESSION['checkout_coupon'])): ?>
                <div class="pt-2 pb-1">
                    <form action="cart.php" method="post" class="flex gap-2">
                        <input type="hidden" name="action" value="apply_coupon">
                        <input type="text" name="coupon_code" placeholder="Coupon Code" class="form-control flex-1 py-1.5 px-3 text-sm uppercase dark:bg-gray-700 dark:border-gray-600 dark:text-white rounded-lg border-gray-200" required>
                        <button type="submit" class="bg-gray-900 hover:bg-gray-800 text-white py-1.5 px-4 text-xs font-bold rounded-lg dark:bg-gray-600">Apply</button>
                    </form>
                </div>
                <?php endif; ?>

                <div class="flex justify-between items-center pt-4 border-t border-dashed border-gray-200 dark:border-gray-700">
                    <span class="text-base font-extrabold text-gray-900 dark:text-white"><?= __('total') ?></span>
                    <span class="text-2xl font-extrabold text-brand-700 dark:text-brand-400"><?= CURRENCY ?><?= fmt_price($total) ?></span>
                </div>
            </div>

            <?php if ($cart_items): ?>
                <?php if (is_customer_logged_in()): ?>
                    <a href="checkout.php" class="w-full block bg-brand-600 text-white text-center font-extrabold py-4 rounded-2xl text-lg hover:bg-brand-700 active:scale-95 transition-all shadow-2xl shadow-brand-100 dark:shadow-none mb-4">
                        <?= __('proceed_checkout') ?>
                    </a>
                <?php else: ?>
                    <a href="login.php" class="w-full block bg-brand-600 text-white text-center font-extrabold py-4 rounded-2xl text-lg hover:bg-brand-700 active:scale-95 transition-all shadow-2xl shadow-brand-100 dark:shadow-none mb-4">
                        <?= __('login_checkout') ?>
                    </a>
                <?php endif; ?>
                <p class="text-[10px] text-center text-gray-400 dark:text-gray-500 font-bold uppercase tracking-widest"><?= __('secure_payment') ?></p>
            <?php else: ?>
            <button disabled class="w-full bg-gray-100 dark:bg-gray-700 text-gray-400 dark:text-gray-500 cursor-not-allowed font-extrabold py-4 rounded-2xl text-lg"><?= __('checkout_empty') ?></button>
            <?php endif; ?>
        </div>

        <div class="bg-brand-50 dark:bg-brand-900/10 p-6 rounded-3xl border border-brand-100 dark:border-brand-900/30">
            <div class="flex gap-4 items-center">
                <span class="text-2xl">📱</span>
                <div>
                    <div class="text-xs font-bold text-brand-800 dark:text-brand-300 tracking-tight leading-4"><?= __('need_help_order') ?></div>
                    <a href="https://wa.me/<?= WHATSAPP_NO ?>" class="text-[10px] font-extrabold text-brand-600 dark:text-brand-400 uppercase hover:underline"><?= __('whatsapp_now') ?></a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function cartSyncQty(pid, price) {
    const qtyEl  = document.getElementById('cart-qty-' + pid);
    const takaEl = document.getElementById('cart-taka-' + pid);
    if (!qtyEl || !takaEl) return;
    const qty  = parseFloat(qtyEl.value) || 0;
    takaEl.value = Math.ceil(qty * price);
}
function cartSyncTaka(pid, price) {
    const qtyEl  = document.getElementById('cart-qty-' + pid);
    const takaEl = document.getElementById('cart-taka-' + pid);
    if (!qtyEl || !takaEl) return;
    const taka = parseFloat(takaEl.value) || 0;
    const qty  = price > 0 ? taka / price : 0;
    qtyEl.value = qty.toFixed(3);
}
</script>

<?php require_once dirname(__DIR__) . '/includes/store-footer.php'; ?>
