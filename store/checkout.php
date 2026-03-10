<?php
// store/checkout.php — Finalize online order
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/flash.php';

if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header('Location: index.php');
    exit;
}

// Require login to checkout
require_customer_login();

$cart_items = $_SESSION['cart'];
$total = 0;
foreach ($cart_items as $item) $total += $item['price'] * $item['qty'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            $stmt = $pdo->prepare("INSERT INTO sales (invoice_no, customer_id, subtotal, total, payment_method, status, notes) VALUES (?, ?, ?, ?, ?, 'pending', ?)");
            $stmt->execute([$invoiceNo, $customer_id, $total, $total, $method, $notes]);
            $saleId = $pdo->lastInsertId();

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
    $stmt = $pdo->prepare("SELECT name, phone, address FROM customers WHERE id = ?");
    $stmt->execute([$_SESSION['customer_id']]);
    $res = $stmt->fetch();
    if ($res) $cust_prefill = $res;
}

require_once dirname(__DIR__) . '/includes/store-header.php';
?>

<div class="mb-8 flex items-center gap-3">
    <a href="cart.php" class="text-gray-400 dark:text-gray-500 hover:text-brand-600 dark:hover:text-brand-400 transition-colors"><?= __('back_to_cart') ?></a>
    <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white"><?= __('secure_checkout') ?></h1>
</div>

<form method="post" class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-20">
    <!-- Left: Shipping & Payment -->
    <div class="lg:col-span-2 space-y-6">
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
                <input type="text" name="notes" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="<?= __('notes_placeholder') ?>">
            </div>
        </div>
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
                        <div class="text-gray-400 dark:text-gray-500"><?= $item['qty'] ?> x <?= CURRENCY ?><?= number_format($item['price'], 2) ?></div>
                    </div>
                    <div class="font-bold text-gray-900 dark:text-white"><?= CURRENCY ?><?= number_format($item['price'] * $item['qty'], 2) ?></div>
                </li>
                <?php endforeach; ?>
            </ul>

            <div class="space-y-4 mb-8 pt-4 border-t border-gray-100 dark:border-gray-700">
                <div class="flex justify-between items-center text-sm font-medium text-gray-500 dark:text-gray-400">
                    <span><?= __('subtotal') ?></span>
                    <span class="text-gray-900 dark:text-white"><?= CURRENCY ?><?= number_format($total, 2) ?></span>
                </div>
                <div class="flex justify-between items-center text-sm font-medium text-gray-500 dark:text-gray-400">
                    <span><?= __('shipping') ?></span>
                    <span class="text-emerald-600 dark:text-emerald-400 font-bold uppercase text-[10px]"><?= __('free') ?></span>
                </div>
                <div class="flex justify-between items-center pt-4 border-t border-dashed border-gray-200 dark:border-gray-700">
                    <span class="text-base font-extrabold text-gray-900 dark:text-white"><?= __('payable_amount') ?></span>
                    <span class="text-2xl font-extrabold text-brand-700 dark:text-brand-400"><?= CURRENCY ?><?= number_format($total, 2) ?></span>
                </div>
            </div>

            <button type="submit" class="w-full bg-brand-600 text-white text-center font-extrabold py-4 rounded-2xl text-lg hover:bg-brand-700 dark:hover:bg-brand-500 active:scale-95 transition-all shadow-2xl shadow-brand-100 dark:shadow-none">
                <?= __('place_order') ?> ➔
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
</form>

<?php require_once dirname(__DIR__) . '/includes/store-footer.php'; ?>
