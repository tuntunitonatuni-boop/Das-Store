<?php
// login.php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/flash.php';

if (is_logged_in()) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    // Some mobile keyboards add trailing spaces to passwords
    $password = $_POST['password'] ?? '';
    if (str_ends_with($password, ' ')) {
        $password = rtrim($password); 
    }

    if ($username && $password) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user) {
            if (password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['role']      = $user['role'];
                set_flash('success', 'Welcome back, ' . $user['name'] . '!');
                header('Location: ' . BASE_URL . 'dashboard.php');
                exit;
            } else {
                $error = 'Incorrect password.';
            }
        } else {
            $error = 'Username not found.';
        }
    } else {
        $error = 'Please enter both username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="<?= get_current_lang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= __('login_title') . SHOP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        brand: {
                            50: '#f0fdf4',
                            100: '#dcfce7',
                            500: '#22c55e',
                            600: '#16a34a',
                            700: '#15803d',
                            800: '#166534'
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gradient-to-br from-brand-800 via-brand-700 to-emerald-900 min-h-screen flex items-center justify-center font-sans px-4">

<!-- Animated background blobs -->
<div class="fixed inset-0 overflow-hidden pointer-events-none">
    <div class="absolute -top-40 -left-40 w-96 h-96 bg-brand-500/20 rounded-full blur-3xl animate-pulse"></div>
    <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-emerald-400/20 rounded-full blur-3xl animate-pulse" style="animation-delay:1s"></div>
</div>

<div class="relative w-full max-w-md">
    <!-- Language Switcher -->
    <div class="flex justify-center gap-4 mb-6 relative z-10">
        <a href="?lang=en" class="px-3 py-1 rounded-full text-xs font-medium transition-all <?= get_current_lang() === 'en' ? 'bg-white text-brand-700 shadow-lg' : 'bg-brand-600/30 text-white hover:bg-brand-600/50' ?>">English</a>
        <a href="?lang=bn" class="px-3 py-1 rounded-full text-xs font-medium transition-all <?= get_current_lang() === 'bn' ? 'bg-white text-brand-700 shadow-lg' : 'bg-brand-600/30 text-white hover:bg-brand-600/50' ?>">বাংলা</a>
    </div>

    <!-- Card -->
    <div class="bg-white/95 backdrop-blur-sm rounded-3xl shadow-2xl overflow-hidden border border-white/20">
        <!-- Top gradient bar -->
        <div class="h-2 bg-gradient-to-r from-brand-500 to-emerald-400"></div>

        <div class="p-8">
            <!-- Logo -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-brand-50 rounded-2xl text-5xl mb-4 shadow-inner ring-4 ring-brand-100/50">🛒</div>
                <h1 class="text-2xl font-bold text-gray-900"><?= SHOP_NAME ?></h1>
                <p class="text-gray-500 text-sm mt-1 uppercase tracking-wider font-semibold opacity-70"><?= __('erp_pos_system') ?></p>
            </div>

            <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm mb-5 flex items-center gap-2 animate-shake">
                <span class="text-lg">✕</span> <?= __($error) ?>
            </div>
            <?php endif; ?>

            <form method="post" class="space-y-5" id="login-form">
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-1.5"><?= __('username') ?></label>
                    <input type="text" name="username" id="username" required autocomplete="username"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm outline-none
                                  focus:border-brand-500 focus:ring-4 focus:ring-brand-100 transition duration-200"
                           placeholder="<?= __('enter_username') ?>">
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5"><?= __('password') ?></label>
                    <div class="relative">
                        <input type="password" name="password" id="password" required autocomplete="current-password"
                               class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm outline-none
                                      focus:border-brand-500 focus:ring-4 focus:ring-brand-100 transition duration-200 pr-12"
                               placeholder="<?= __('enter_password') ?>">
                        <button type="button" id="toggle-pw"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-brand-600 transition-colors text-lg">👁</button>
                    </div>
                </div>
                <button type="submit"
                        class="w-full bg-brand-600 hover:bg-brand-700 active:scale-95 text-white font-bold
                               py-3.5 rounded-xl transition-all duration-150 shadow-lg shadow-brand-200 text-base">
                    <?= __('sign_in_btn') ?>
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-gray-100 text-center">
                <a href="<?= BASE_URL ?>store/" class="inline-flex items-center gap-2 text-sm text-brand-600 hover:text-brand-700 font-bold transition-colors group">
                    <?= __('visit_online_store') ?>
                    <span class="group-hover:translate-x-1 transition-transform">→</span>
                </a>
            </div>
        </div>
    </div>

    <p class="text-center text-white/50 text-xs mt-8 font-medium">&copy; <?= date('Y') ?> <?= SHOP_NAME ?>. Powered by Smart Grocery ERP.</p>
</div>

<script>
document.getElementById('toggle-pw').addEventListener('click', function() {
    const pw = document.getElementById('password');
    pw.type = pw.type === 'password' ? 'text' : 'password';
});
</script>
</body>
</html>
