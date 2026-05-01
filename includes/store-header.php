<?php
// includes/store-header.php — Online store navigation header
if (!defined('BASE_URL')) require_once dirname(__DIR__) . '/config.php';
if (!isset($pdo)) require_once __DIR__ . '/db.php';
$store_page_title = $store_page_title ?? SHOP_NAME;

// Fetch categories for nav
$cats = $pdo->query("SELECT id, name, slug FROM categories WHERE is_active = 1 ORDER BY sort_order, name LIMIT 10")->fetchAll();
$cart_count = array_sum(array_column($_SESSION['cart'] ?? [], 'qty'));
?>
<!DOCTYPE html>
<html lang="<?= get_current_lang() ?>" x-data="{ 
    darkMode: localStorage.getItem('theme') === 'dark',
    toggleTheme() {
        this.darkMode = !this.darkMode;
        localStorage.setItem('theme', this.darkMode ? 'dark' : 'light');
        if (this.darkMode) document.documentElement.classList.add('dark');
        else document.documentElement.classList.remove('dark');
    }
}" :class="{ 'dark': darkMode }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($store_page_title); ?> — <?= SHOP_NAME ?></title>
    <meta name="description" content="Shop fresh groceries online at <?= SHOP_NAME ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        brand: { 50:'#f0fdf4', 100:'#dcfce7', 500:'#22c55e', 600:'#16a34a', 700:'#15803d' }
                    }
                }
            }
        }
        // Early theme initialization
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/main.css">
    <style>
        [x-cloak] { display: none !important; }
        .dark body { background-color: #111827; color: #f3f4f6; }
        .dark header { background-color: #1f2937; border-color: #374151; }
        .dark .bg-white { background-color: #1f2937; }
        .dark .text-gray-700 { color: #d1d5db; }
        .dark .text-gray-600 { color: #9ca3af; }
        .dark .border-gray-100 { border-color: #374151; }
        .dark .border-gray-200 { border-color: #374151; }
        .dark .border-gray-300 { border-color: #4b5563; }
        .dark .bg-gray-50 { background-color: #111827; }
        .dark .bg-gray-100 { background-color: #374151; }
    </style>
</head>
<body class="bg-gray-50 font-sans transition-colors duration-300" x-data="{ mobileMenu: false }">

<!-- STORE HEADER -->
<header class="bg-white shadow-sm sticky top-0 z-40 transition-colors duration-300">
    <div class="max-w-7xl mx-auto px-4">
        <!-- Top bar -->
        <div class="flex items-center gap-4 h-16">
            <!-- Logo -->
            <a href="<?= BASE_URL ?>store/" class="flex items-center gap-2 font-bold text-xl text-brand-700 dark:text-brand-500">
                <span class="text-2xl">🛒</span><?= SHOP_NAME ?>
            </a>

            <!-- Search -->
            <form action="<?= BASE_URL ?>store/" method="get" class="flex-1 max-w-md hidden md:flex">
                <div class="flex w-full border border-gray-300 dark:border-gray-600 rounded-xl overflow-hidden focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-200 transition">
                    <input type="search" name="q" placeholder="<?= __('search_placeholder') ?>"
                           value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                           class="flex-1 px-4 py-2 text-sm outline-none dark:bg-gray-800 dark:text-white">
                    <button type="submit" class="px-4 bg-brand-600 text-white hover:bg-brand-700 transition text-sm font-medium"><?= __('search') ?></button>
                </div>
            </form>

            <div class="flex-1 hidden sm:block md:hidden"></div>

            <!-- Cart, Theme, Lang, Login -->
            <div class="flex items-center gap-2 sm:gap-3">
                <!-- Theme Toggle -->
                <button @click="toggleTheme()" class="p-2 rounded-xl bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition" title="<?= __('dark_mode') ?>">
                    <span x-show="!darkMode">🌙</span>
                    <span x-show="darkMode" x-cloak>☀️</span>
                </button>

                <!-- Language Toggle -->
                <div class="relative" x-data="{ langOpen: false }">
                    <button @click="langOpen = !langOpen" class="flex items-center gap-1 text-xs font-bold uppercase p-2 rounded-xl bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                        🌐 <?= get_current_lang() ?>
                    </button>
                    <div x-show="langOpen" @click.away="langOpen = false" x-transition class="absolute right-0 mt-2 w-32 bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-xl shadow-xl overflow-hidden z-50">
                        <a href="?lang=en" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-brand-50 dark:hover:bg-gray-700">English</a>
                        <a href="?lang=bn" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-brand-50 dark:hover:bg-gray-700">বাংলা</a>
                    </div>
                </div>

                <a href="<?= BASE_URL ?>store/cart.php" class="relative flex items-center gap-2 bg-brand-600 text-white px-3 sm:px-4 py-2 rounded-xl text-sm font-medium hover:bg-brand-700 transition">
                    <span class="hidden sm:inline">🛒 <?= __('cart') ?></span>
                    <span class="sm:hidden">🛒</span>
                    <?php if ($cart_count > 0): ?>
                    <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs w-5 h-5 rounded-full flex items-center justify-center font-bold border-2 border-white dark:border-gray-900"><?= $cart_count ?></span>
                    <?php endif; ?>
                </a>

                <?php if (is_customer_logged_in()): ?>
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-700 px-3 sm:px-4 py-2 rounded-xl hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                            👤 <span class="hidden sm:inline"><?= htmlspecialchars(current_customer()['name']) ?></span>
                        </button>
                        <div x-show="open" @click.away="open = false" x-transition class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-100 dark:border-gray-700 py-2 z-50">
                            <a href="<?= BASE_URL ?>store/my-orders.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-brand-50 dark:hover:bg-gray-700"><?= __('my_orders') ?></a>
                            <?php if (function_exists('feature') && feature('customer_wishlist')): ?>
                            <a href="<?= BASE_URL ?>store/wishlist.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-brand-50 dark:hover:bg-gray-700">♥ <?= __('my_wishlist') ?? 'My Wishlist' ?></a>
                            <?php endif; ?>
                            <div class="border-t border-gray-100 dark:border-gray-700 my-1"></div>
                            <a href="<?= BASE_URL ?>store/logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20"><?= __('logout') ?></a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>store/login.php" class="text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-brand-600 transition"><?= __('login') ?></a>
                <?php endif; ?>

                <?php if (is_logged_in()): ?>
                <a href="<?= BASE_URL ?>dashboard.php" class="text-sm text-gray-600 dark:text-gray-400 hover:text-brand-600 transition hidden lg:block"><?= __('admin') ?> ›</a>
                <?php endif; ?>
            </div>

            <!-- Mobile menu btn -->
            <button @click="mobileMenu = !mobileMenu" class="md:hidden p-2 text-gray-600 dark:text-gray-400">
                <svg x-show="!mobileMenu" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                <svg x-show="mobileMenu" x-cloak class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- Category nav -->
        <nav class="hidden md:flex gap-1 pb-2 overflow-x-auto scrollbar-none">
            <a href="<?= BASE_URL ?>store/" class="px-3 py-1.5 text-sm rounded-lg text-gray-600 dark:text-gray-400 hover:bg-brand-50 dark:hover:bg-brand-900/20 hover:text-brand-700 dark:hover:text-brand-400 whitespace-nowrap transition"><?= __('all') ?></a>
            <?php foreach ($cats as $cat): ?>
            <a href="<?= BASE_URL ?>store/category.php?id=<?= $cat['id'] ?>"
               class="px-3 py-1.5 text-sm rounded-lg text-gray-600 dark:text-gray-400 hover:bg-brand-50 dark:hover:bg-brand-900/20 hover:text-brand-700 dark:hover:text-brand-400 whitespace-nowrap transition">
                <?= htmlspecialchars($cat['name']) ?>
            </a>
            <?php endforeach; ?>
        </nav>
    </div>

    <!-- Mobile menu -->
    <div x-show="mobileMenu" x-transition x-cloak class="md:hidden border-t border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-3">
        <form action="<?= BASE_URL ?>store/" method="get" class="mb-3">
            <input type="search" name="q" placeholder="<?= __('search_placeholder') ?>" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm dark:bg-gray-700 dark:text-white">
        </form>
        <nav class="flex flex-wrap gap-2">
            <a href="<?= BASE_URL ?>store/" class="px-3 py-1.5 text-sm bg-gray-100 dark:bg-gray-700 rounded-lg dark:text-gray-200"><?= __('all') ?></a>
            <?php foreach ($cats as $cat): ?>
            <a href="<?= BASE_URL ?>store/category.php?id=<?= $cat['id'] ?>" class="px-3 py-1.5 text-sm bg-gray-100 dark:bg-gray-700 rounded-lg dark:text-gray-200">
                <?= htmlspecialchars($cat['name']) ?>
            </a>
            <?php endforeach; ?>
        </nav>
    </div>
</header>

<main class="max-w-7xl mx-auto px-4 py-6">
    <?php if (function_exists('render_flash')) render_flash(); ?>
