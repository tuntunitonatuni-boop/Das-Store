<?php
// store/register.php — Online customer registration
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/flash.php';
require_once dirname(__DIR__) . '/includes/auth.php';

if (is_customer_logged_in()) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $address  = trim($_POST['address'] ?? '');

    if ($name && $phone && $username && $password) {
        // Check if username already exists
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE username = ? OR phone = ?");
        $stmt->execute([$username, $phone]);
        if ($stmt->fetch()) {
            set_flash('error', __('user_phone_already'));
        } else {
            $hashed_pw = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO customers (name, username, phone, email, password, address) VALUES (?, ?, ?, ?, ?, ?)");
            try {
                $stmt->execute([$name, $username, $phone, $email, $hashed_pw, $address]);
                set_flash('success', __('reg_success'));
                header('Location: login.php');
                exit;
            } catch (PDOException $e) {
                set_flash('error', __('reg_failed'));
            }
        }
    } else {
        set_flash('error', __('error_required'));
    }
}

$store_page_title = __('create_account') . ' - ' . SHOP_NAME;
require_once dirname(__DIR__) . '/includes/store-header.php';
?>

<div class="max-w-md mx-auto my-12 bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-xl overflow-hidden">
    <div class="bg-brand-600 dark:bg-brand-700 p-8 text-center text-white">
        <h1 class="text-2xl font-bold"><?= __('create_account') ?></h1>
        <p class="text-white/80 text-sm"><?= __('join_family') ?> <?= SHOP_NAME ?></p>
    </div>
    
    <form method="post" class="p-8 space-y-4">
        <div>
            <label class="form-label dark:text-gray-300"><?= __('full_name') ?> *</label>
            <input type="text" name="name" required class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="John Doe">
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="form-label dark:text-gray-300"><?= __('phone') ?> *</label>
                <input type="tel" name="phone" required class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="01XXX-XXXXXX">
            </div>
            <div>
                <label class="form-label dark:text-gray-300"><?= __('email') ?> (<?= __('optional_note') ?>)</label>
                <input type="email" name="email" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="john@example.com">
            </div>
        </div>
        <div>
            <label class="form-label dark:text-gray-300"><?= __('username') ?> *</label>
            <input type="text" name="username" required class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="johndoe123">
        </div>
        <div>
            <label class="form-label dark:text-gray-300"><?= __('password') ?> *</label>
            <input type="password" name="password" required class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="••••••••">
        </div>
        <div>
            <label class="form-label dark:text-gray-300"><?= __('delivery_address') ?></label>
            <textarea name="address" rows="2" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="<?= __('address_short_placeholder') ?>"></textarea>
        </div>
        
        <button type="submit" class="w-full bg-brand-600 text-white font-bold py-3 rounded-xl hover:bg-brand-700 dark:hover:bg-brand-500 transition active:scale-95 shadow-lg shadow-brand-100 dark:shadow-none">
            <?= __('register_btn') ?>
        </button>
        
        <p class="text-center text-sm text-gray-500 dark:text-gray-400">
            <?= __('already_account') ?> <a href="login.php" class="text-brand-600 dark:text-brand-400 font-bold hover:underline"><?= __('login_here') ?></a>
        </p>
    </form>
</div>

<?php require_once dirname(__DIR__) . '/includes/store-footer.php'; ?>
