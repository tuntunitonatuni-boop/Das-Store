<?php
// store/login.php — Online customer login
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/flash.php';
require_once dirname(__DIR__) . '/includes/auth.php';

if (isset($_SESSION['customer_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE username = ? OR phone = ? LIMIT 1");
        $stmt->execute([$username, $username]);
        $customer = $stmt->fetch();

        if ($customer && password_verify($password, $customer['password'])) {
            $_SESSION['customer_id']   = $customer['id'];
            $_SESSION['customer_name'] = $customer['name'];
            
            set_flash('success', __('welcome_back_flash') . $customer['name'] . "!");
            
            // Redirect to intended page or home
            $redirect = $_SESSION['redirect_after_login'] ?? 'index.php';
            unset($_SESSION['redirect_after_login']);
            header('Location: ' . $redirect);
            exit;
        } else {
            set_flash('error', __('invalid_cust_login'));
        }
    } else {
        set_flash('error', __('enter_credentials'));
    }
}

$store_page_title = __('customer_login_title') . ' - ' . SHOP_NAME;
require_once dirname(__DIR__) . '/includes/store-header.php';
?>

<div class="max-w-md mx-auto my-12 bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-xl overflow-hidden">
    <div class="bg-brand-600 dark:bg-brand-700 p-8 text-center text-white">
        <h1 class="text-2xl font-bold"><?= __('welcome_back') ?></h1>
        <p class="text-white/80 text-sm"><?= __('login_order_track') ?></p>
    </div>
    
    <form method="post" class="p-8 space-y-6">
        <div>
            <label class="form-label dark:text-gray-300"><?= __('username_phone') ?></label>
            <input type="text" name="username" required class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="<?= __('user_phone_placeholder') ?>">
        </div>
        <div>
            <label class="form-label dark:text-gray-300"><?= __('password') ?></label>
            <input type="password" name="password" required class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="••••••••">
        </div>
        
        <button type="submit" class="w-full bg-brand-600 text-white font-bold py-3 rounded-xl hover:bg-brand-700 dark:hover:bg-brand-500 transition active:scale-95">
            <?= __('sign_in') ?>
        </button>
        
        <p class="text-center text-sm text-gray-500 dark:text-gray-400">
            <?= __('no_account') ?> <a href="register.php" class="text-brand-600 dark:text-brand-400 font-bold hover:underline"><?= __('register_here') ?></a>
        </p>
    </form>
</div>

<?php require_once dirname(__DIR__) . '/includes/store-footer.php'; ?>
