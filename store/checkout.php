<?php
// store/checkout.php — Finalize online order
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/flash.php';
require_once dirname(__DIR__) . '/includes/features.php';

if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header('Location: index.php');
    exit;
}

// Require login to checkout
require_customer_login();

$cart_items = $_SESSION['cart'];
$subtotal = 0;
foreach ($cart_items as $item) $subtotal += $item['price'] * $item['qty'];

// Handle Coupon application
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['apply_coupon'])) {
        $code = trim($_POST['coupon_code']);
        $c = $pdo->prepare("SELECT * FROM coupons WHERE code=?");
        $c->execute([strtoupper($code)]);
        $c = $c->fetch();
        
        if (!$c) {
            set_flash('error', "দুঃখিত, এই কুপন কোডটি সঠিক নয়। (Invalid coupon)");
        } elseif ($c['is_active'] != 1) {
            set_flash('error', "এই কুপনটি বর্তমানে বন্ধ আছে। (Coupon is inactive)");
        } elseif ($c['expires_at'] && strtotime($c['expires_at']) < strtotime(date('Y-m-d'))) {
            set_flash('error', "এই কুপনের মেয়াদ শেষ হয়ে গেছে। (Coupon expired)");
        } elseif ($c['usage_limit'] > 0 && $c['used_count'] >= $c['usage_limit']) {
            set_flash('error', "এই কুপনটির ব্যবহারের সীমা শেষ হয়ে গেছে। (Limit reached)");
        } elseif ($subtotal < ($c['min_order'] ?? 0)) {
            set_flash('error', "এই কুপনটি ব্যবহার করতে হলে অন্তত ৳" . number_format($c['min_order'], 2) . " এর অর্ডার করতে হবে।");
        } else {
            $_SESSION['checkout_coupon'] = $c;
            set_flash('success', "কুপন সফলভাবে যোগ করা হয়েছে! 🎉");
        }
        header('Location: checkout.php'); exit;
    }
    if (isset($_POST['remove_coupon'])) {
        unset($_SESSION['checkout_coupon']);
        header('Location: checkout.php'); exit;
    }
}

// Calculate Discounts
$discount = 0;
$coupon_id = null;
if (isset($_SESSION['checkout_coupon'])) {
    $c = $_SESSION['checkout_coupon'];
    if ($subtotal < ($c['min_order'] ?? 0)) {
        unset($_SESSION['checkout_coupon']);
        set_flash('error', "কার্টের পরিমাণ কমে যাওয়ায় কুপনটি বাতিল করা হয়েছে।");
    } else {
        $coupon_id = $c['id'];
        if ($c['type'] === 'percent') {
            $discount = $subtotal * ($c['value'] / 100);
            if ($c['max_discount'] > 0 && $discount > $c['max_discount']) {
                $discount = $c['max_discount'];
            }
        } else {
            $discount = $c['value'];
        }
    }
}

// Calculate Loyalty Points redemption
$redeemed_points = 0;
$points_discount = 0;
if (is_customer_logged_in() && feature('loyalty_points')) {
    $cust = $pdo->prepare("SELECT total_points FROM customers WHERE id=?");
    $cust->execute([$_SESSION['customer_id']]);
    $pts = $cust->fetchColumn() ?: 0;
    
    // Auto-apply points if they want, or maybe we just add a switch. For simplicity let's say they checking a box.
    if (isset($_POST['redeem_pts']) || isset($_SESSION['redeem_pts'])) {
        if (isset($_POST['checkout'])) $_SESSION['redeem_pts'] = true;
        if ($_SESSION['redeem_pts'] ?? false) {
            $redeem_rate = (float)feature_val('points_redeem_value', 1);
            $redeemed_points = min($pts, floor(($subtotal - $discount) / $redeem_rate));
            $points_discount = $redeemed_points * $redeem_rate;
        }
    }
}

$total = max(0, $subtotal - $discount - $points_discount);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $name    = trim($_POST['name'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $method  = $_POST['payment_method'] ?? 'cod';
    $notes   = trim($_POST['notes'] ?? '');

    if ($name && $phone && $address) {
        $pdo->beginTransaction();
        try {
            // 1. Determine Customer ID
            if (is_customer_logged_in()) {
                $customer_id = $_SESSION['customer_id'];
                // Update profile info if changed
                $stmt = $pdo->prepare("UPDATE customers SET name = ?, phone = ?, address = ? WHERE id = ?");
                $stmt->execute([$name, $phone, $address, $customer_id]);
                $_SESSION['customer_name'] = $name; // Sync name
            } else {
                // Find or create customer (online customers are identified by phone)
                $stmt = $pdo->prepare("SELECT id FROM customers WHERE phone = ? LIMIT 1");
                $stmt->execute([$phone]);
                $customer = $stmt->fetch();

                if (!$customer) {
                    $stmt = $pdo->prepare("INSERT INTO customers (name, phone, address) VALUES (?, ?, ?)");
                    $stmt->execute([$name, $phone, $address]);
                    $customer_id = $pdo->lastInsertId();
                } else {
                    $customer_id = $customer['id'];
                    $stmt = $pdo->prepare("UPDATE customers SET address = ? WHERE id = ?");
                    $stmt->execute([$address, $customer_id]);
                }
            }

            // 2. Create Sale Record (Status = 'pending' for online orders)
            $invoiceNo = 'WEB-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
            $stmt = $pdo->prepare("INSERT INTO sales (invoice_no, customer_id, subtotal, discount, total, payment_method, status, notes, coupon_id) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?)");
            $stmt->execute([$invoiceNo, $customer_id, $subtotal, $discount + $points_discount, $total, $method, $notes, $coupon_id]);
            $saleId = $pdo->lastInsertId();

            if ($coupon_id) {
                $pdo->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id=?")->execute([$coupon_id]);
            }
            if ($redeemed_points > 0) {
                $pdo->prepare("INSERT INTO loyalty_points (customer_id, points, type, description) VALUES (?, ?, 'redeem', ?)")->execute([$customer_id, $redeemed_points, "Redeemed for Order #$invoiceNo"]);
                $pdo->prepare("UPDATE customers SET total_points = GREATEST(0, total_points - ?) WHERE id=?")->execute([$redeemed_points, $customer_id]);
            }

            // 3. Create Sale Items
            foreach ($cart_items as $item) {
                $itemStmt = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, product_name, qty, unit_price, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
                $itemStmt->execute([$saleId, $item['id'], $item['name'], $item['qty'], $item['price'], $item['price'] * $item['qty']]);
                
                // Do NOT deduct stock yet for 'pending' online orders? 
                // PRD workflow usually deducts on completion. 
                // But for grocery, we should probably mark it as committed or deduct now to prevent overselling.
                // Let's deduct now.
                // Deduct stock (Prefer warehouse for online orders, then display)
                $qty_to_deduct = $item['qty'];
                $inv = $pdo->prepare("SELECT display_qty, warehouse_qty FROM inventory WHERE product_id = ?");
                $inv->execute([$item['id']]);
                $stock = $inv->fetch();

                if ($stock['warehouse_qty'] >= $qty_to_deduct) {
                    $pdo->prepare("UPDATE inventory SET warehouse_qty = warehouse_qty - ? WHERE product_id = ?")->execute([$qty_to_deduct, $item['id']]);
                    $location = 'warehouse';
                } else if (($stock['warehouse_qty'] + $stock['display_qty']) >= $qty_to_deduct) {
                    $remain = $qty_to_deduct - $stock['warehouse_qty'];
                    $pdo->prepare("UPDATE inventory SET warehouse_qty = 0, display_qty = display_qty - ? WHERE product_id = ?")->execute([$remain, $item['id']]);
                    $location = 'any';
                } else {
                    // Force deduct from warehouse even if it goes negative (for backordering)
                    $pdo->prepare("UPDATE inventory SET warehouse_qty = warehouse_qty - ? WHERE product_id = ?")->execute([$qty_to_deduct, $item['id']]);
                    $location = 'warehouse';
                }

                $moveStmt = $pdo->prepare("INSERT INTO stock_movements (product_id, type, qty_change, location, reference_id, notes) VALUES (?, 'sale', ?, ?, ?, 'Online Order')");
                $moveStmt->execute([$item['id'], -$qty_to_deduct, $location, $saleId]);
            }

            $pdo->commit();
            $_SESSION['cart'] = []; // Clear cart
            unset($_SESSION['checkout_coupon']);
            unset($_SESSION['redeem_pts']);
            $_SESSION['last_order'] = ['id' => $saleId, 'invoice' => $invoiceNo, 'total' => $total, 'name' => $name];
            header('Location: order-success.php');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('error', 'Something went wrong. Please try again.');
        }
    } else {
        set_flash('error', 'Please fill in all required fields.');
    }
}

$store_page_title = 'Checkout - ' . SHOP_NAME;

// Pre-fill data for logged in customers
$cust_prefill = ['name' => '', 'phone' => '', 'address' => ''];
if (is_customer_logged_in()) {
    $stmt = $pdo->prepare("SELECT name, phone, address, total_points FROM customers WHERE id = ?");
    $stmt->execute([$_SESSION['customer_id']]);
    $res = $stmt->fetch();
    if ($res) {
        $cust_prefill = $res;
        $customer_pts = $res['total_points'] ?? 0;
    }
}

require_once dirname(__DIR__) . '/includes/store-header.php';
?>

<div class="mb-8 flex items-center gap-3">
    <a href="cart.php" class="text-gray-400 dark:text-gray-500 hover:text-brand-600 dark:hover:text-brand-400 transition-colors"><?= __('back_to_cart') ?></a>
    <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white"><?= __('secure_checkout') ?></h1>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-20">
    <!-- Left: Shipping & Payment -->
    <div class="lg:col-span-2 space-y-6">
        <form method="post" id="checkout-form">
            <input type="hidden" name="checkout" value="1">
            <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 md:p-8 border border-gray-100 dark:border-gray-700 shadow-sm mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 md:p-8 border border-gray-100 dark:border-gray-700 shadow-sm">
            <h2 class="text-xl font-bold mb-6 flex items-center gap-2 dark:text-white">
                <span class="bg-brand-50 dark:bg-brand-900/30 text-brand-700 dark:text-brand-400 w-8 h-8 rounded-full flex items-center justify-center text-sm">1</span>
                <?= __('delivery_info') ?>
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="form-label dark:text-gray-300"><?= __('full_name') ?> *</label>
                    <input type="text" name="name" required class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="John Doe" value="<?= htmlspecialchars($cust_prefill['name']) ?>">
                </div>
                <div>
                    <label class="form-label dark:text-gray-300"><?= __('phone') ?> *</label>
                    <input type="tel" name="phone" required class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="01XXX-XXXXXX" value="<?= htmlspecialchars($cust_prefill['phone']) ?>">
                    <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1 uppercase font-bold"><?= __('phone_confirm_note') ?></p>
                </div>
                <div>
                    <label class="form-label dark:text-gray-300"><?= __('alt_phone') ?></label>
                    <input type="tel" name="alt_phone" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="01XXX-XXXXXX">
                </div>
                <div class="md:col-span-2">
                    <label class="form-label dark:text-gray-300"><?= __('detailed_address') ?> *</label>
                    <textarea name="address" required rows="3" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="<?= __('address_placeholder') ?>"><?= htmlspecialchars($cust_prefill['address']) ?></textarea>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 md:p-8 border border-gray-100 dark:border-gray-700 shadow-sm">
            <h2 class="text-xl font-bold mb-6 flex items-center gap-2 dark:text-white">
                <span class="bg-brand-50 dark:bg-brand-900/30 text-brand-700 dark:text-brand-400 w-8 h-8 rounded-full flex items-center justify-center text-sm">2</span>
                <?= __('payment_method_label') ?>
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <label class="relative flex items-center p-4 border-2 dark:border-gray-700 rounded-2xl cursor-pointer hover:border-brand-200 dark:hover:border-brand-800 transition-all has-[:checked]:border-brand-500 dark:has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50/30 dark:has-[:checked]:bg-brand-900/10">
                    <input type="radio" name="payment_method" value="cod" checked class="hidden peer">
                    <span class="text-2xl mr-4">💵</span>
                    <div>
                        <div class="text-sm font-bold text-gray-900 dark:text-white"><?= __('cod') ?></div>
                        <div class="text-[10px] text-gray-400 dark:text-gray-500 font-bold uppercase"><?= __('pay_on_receive') ?></div>
                    </div>
                    <div class="ml-auto w-5 h-5 border-2 border-gray-200 dark:border-gray-600 rounded-full peer-checked:border-4 peer-checked:border-brand-600 dark:peer-checked:border-brand-400 transition-all"></div>
                </label>

                <label class="relative flex items-center p-4 border-2 dark:border-gray-700 rounded-2xl cursor-pointer opacity-60">
                    <input type="radio" name="payment_method" value="bkash" disabled class="hidden peer">
                    <span class="text-2xl mr-4">📱</span>
                    <div>
                        <div class="text-sm font-bold text-gray-900 dark:text-white"><?= __('online_payment') ?></div>
                        <div class="text-[10px] text-gray-400 dark:text-gray-500 font-bold uppercase"><?= __('coming_soon') ?></div>
                    </div>
                </label>
            </div>
            
            <div class="mt-6">
                <label class="form-label dark:text-gray-300"><?= __('order_notes') ?></label>
                <input type="text" name="notes" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="<?= __('notes_placeholder') ?? 'Delivery instructions...' ?>">
            </div>
        </div>
        </form>
    </div>

    <!-- Right: Order Summary -->
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 border border-gray-100 dark:border-gray-700 shadow-sm sticky top-24">
            <h3 class="text-lg font-extrabold text-gray-900 dark:text-white mb-6 border-b dark:border-gray-700 pb-4"><?= __('receipt_preview') ?></h3>
            
            <!-- Mini cart -->
            <ul class="space-y-3 mb-6 max-h-48 overflow-y-auto pr-2 custom-scrollbar">
                <?php foreach ($cart_items as $item): ?>
                <li class="flex justify-between items-start text-xs">
                    <div class="flex-1">
                        <div class="font-bold text-gray-800 dark:text-gray-200"><?= htmlspecialchars($item['name']) ?></div>
                        <div class="text-gray-400 dark:text-gray-500"><?= $item['qty'] ?> x <?= CURRENCY ?><?= fmt_price($item['price']) ?></div>
                    </div>
                    <div class="font-bold text-gray-900 dark:text-white"><?= CURRENCY ?><?= fmt_price($item['price'] * $item['qty']) ?></div>
                </li>
                <?php endforeach; ?>
            </ul>

            <div class="space-y-4 mb-6 pt-4 border-t border-gray-100 dark:border-gray-700">
                <div class="flex justify-between items-center text-sm font-medium text-gray-500 dark:text-gray-400">
                    <span><?= __('subtotal') ?? 'Subtotal' ?></span>
                    <span class="text-gray-900 dark:text-white"><?= CURRENCY ?><?= fmt_price($subtotal) ?></span>
                </div>
                
                <?php if ($discount > 0): ?>
                <div class="flex justify-between items-center text-sm font-medium text-red-500">
                    <span>Discount (<?= htmlspecialchars($_SESSION['checkout_coupon']['code']) ?>)
                        <form method="post" class="inline ml-1"><button type="submit" name="remove_coupon" class="text-red-400 underline">Remove</button></form>
                    </span>
                    <span>- <?= CURRENCY ?><?= number_format($discount, 2) ?></span>
                </div>
                <?php endif; ?>

                <?php if ($points_discount > 0): ?>
                <div class="flex justify-between items-center text-sm font-medium text-emerald-500">
                    <span>Loyalty Points (-<?= $redeemed_points ?> pts)</span>
                    <span>- <?= CURRENCY ?><?= number_format($points_discount, 2) ?></span>
                </div>
                <?php endif; ?>

                <div class="flex justify-between items-center text-sm font-medium text-gray-500 dark:text-gray-400">
                    <span><?= __('shipping') ?? 'Shipping' ?></span>
                    <span class="text-emerald-600 dark:text-emerald-400 font-bold uppercase text-[10px]"><?= __('free') ?? 'FREE' ?></span>
                </div>

                <!-- Coupons Section in Summary -->
                <?php if (feature('discount_coupons') && !$coupon_id): ?>
                <div class="pt-2 pb-1">
                    <form method="post" class="flex gap-2">
                        <input type="text" name="coupon_code" placeholder="Coupon Code" class="form-control flex-1 py-1.5 px-3 text-sm uppercase dark:bg-gray-700 dark:border-gray-600 dark:text-white rounded-lg border-gray-200" required>
                        <button type="submit" name="apply_coupon" class="bg-gray-900 hover:bg-gray-800 text-white py-1.5 px-4 text-xs font-bold rounded-lg dark:bg-gray-600">Apply</button>
                    </form>
                </div>
                <?php endif; ?>
                <div class="flex justify-between items-center pt-4 border-t border-dashed border-gray-200 dark:border-gray-700">
                    <span class="text-base font-extrabold text-gray-900 dark:text-white"><?= __('payable_amount') ?? 'Total' ?></span>
                    <span class="text-2xl font-extrabold text-brand-700 dark:text-brand-400"><?= CURRENCY ?><?= fmt_price($total) ?></span>
                </div>
            </div>



            <!-- Loyalty Points Checkbox -->
            <?php if (feature('loyalty_points') && ($customer_pts ?? 0) > 0): ?>
            <div class="mb-6 p-4 bg-brand-50 dark:bg-gray-700/50 rounded-xl border border-brand-100 dark:border-gray-600 text-sm">
                <input type="checkbox" form="checkout-form" name="redeem_pts" id="redeem_pts" value="1" <?= isset($_SESSION['redeem_pts']) ? 'checked' : '' ?> class="mr-2 accent-brand-600" onchange="this.form.submit()">
                <label for="redeem_pts" class="font-bold cursor-pointer dark:text-white">Use <?= $customer_pts ?> Loyalty Points</label>
                <div class="text-[10px] text-gray-500 mt-1">Saves you up to <?= CURRENCY ?><?= number_format($customer_pts * (float)feature_val('points_redeem_value',1), 2) ?></div>
            </div>
            <?php endif; ?>

            <button type="button" onclick="document.getElementById('checkout-form').submit()" class="w-full bg-brand-600 text-white text-center font-extrabold py-4 rounded-2xl text-lg hover:bg-brand-700 dark:hover:bg-brand-500 active:scale-95 transition-all shadow-2xl shadow-brand-100 dark:shadow-none">
                <?= __('place_order') ?? 'Place Order' ?> ➔
            </button>
            <p class="text-[10px] text-center text-gray-400 dark:text-gray-500 font-bold uppercase mt-4"><?= __('terms_agree') ?></p>
        </div>
        
        <div class="bg-emerald-50 dark:bg-emerald-900/10 p-6 rounded-3xl border border-emerald-100 dark:border-emerald-900/30 flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 flex items-center justify-center text-2xl">🚛</div>
            <div>
                <div class="text-xs font-bold text-emerald-800 dark:text-emerald-400 uppercase tracking-wider"><?= __('fast_free') ?></div>
                <div class="text-sm text-emerald-700/80 dark:text-emerald-500/80"><?= __('delivery_promise') ?></div>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/store-footer.php'; ?>
