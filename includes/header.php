<?php
// includes/header.php — Admin panel HTML <head> + top navigation bar
if (!defined('BASE_URL')) require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/flash.php';
$user = current_user();
$page_title = $page_title ?? SHOP_NAME;
?>
<!DOCTYPE html>
<!DOCTYPE html>
<html lang="<?= get_current_lang() ?>" class="h-full" x-data="{ 
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
    <title><?= htmlspecialchars($page_title) ?> — <?= SHOP_NAME ?></title>
    <meta name="description" content="<?= SHOP_NAME ?> Smart Grocery ERP &amp; POS Management System">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Tailwind CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        darkMode: 'class',
        theme: {
            extend: {
                fontFamily: { sans: ['Inter', 'sans-serif'] },
                colors: {
                    brand: { 50:'#f0fdf4',100:'#dcfce7',200:'#bbf7d0',300:'#86efac',
                             400:'#4ade80',500:'#22c55e',600:'#16a34a',700:'#15803d',
                             800:'#166534',900:'#14532d' },
                }
            }
        }
    };
    // Early theme initialization
    if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
    </script>
    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <!-- Global CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/main.css">
    <style>
        [x-cloak] { display: none !important; }
        .dark body { background-color: #111827; color: #f3f4f6; }
        .dark .bg-white { background-color: #1f2937; }
        .dark .bg-gray-50 { background-color: #111827; }
        .dark .text-gray-700 { color: #d1d5db; }
        .dark .text-gray-800 { color: #f3f4f6; }
        .dark .border-gray-100 { border-color: #374151; }
        .dark .hover\:\bg-gray-50:hover { background-color: #374151; }
    </style>
</head>
<body class="bg-gray-50 font-sans h-full transition-colors duration-300" x-data="{ sidebarOpen: window.innerWidth >= 1024 }">

<!-- TOP NAV BAR -->
<header class="fixed top-0 left-0 right-0 z-50 h-16 bg-gradient-to-r from-brand-700 to-brand-600 dark:from-gray-900 dark:to-gray-800 shadow-lg flex items-center px-4 gap-4 transition-colors duration-300">
    <!-- Sidebar toggle -->
    <button @click="sidebarOpen = !sidebarOpen"
            class="text-white p-2 rounded-lg hover:bg-white/20 transition" id="sidebar-toggle" aria-label="Toggle sidebar">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
    </button>

    <!-- Logo / Shop Name -->
    <a href="<?= BASE_URL ?>dashboard.php" class="flex items-center gap-2 text-white font-bold text-lg no-underline">
        <span class="bg-white/20 rounded-lg px-2 py-1">🛒</span>
        <span><?= SHOP_NAME ?></span>
    </a>

    <div class="flex-1"></div>

    <!-- Top-right controls -->
    <nav class="flex items-center gap-2">
        <!-- Theme Toggle -->
        <button @click="toggleTheme()" class="p-2 rounded-lg text-white hover:bg-white/20 transition" title="<?= __('dark_mode') ?>">
            <span x-show="!darkMode">🌙</span>
            <span x-show="darkMode" x-cloak>☀️</span>
        </button>

        <!-- Language Toggle -->
        <div class="relative" x-data="{ langOpen: false }">
            <button @click="langOpen = !langOpen" class="flex items-center gap-1 text-xs font-bold uppercase p-2 rounded-lg text-white hover:bg-white/20 transition">
                🌐 <?= get_current_lang() ?>
            </button>
            <div x-show="langOpen" @click.away="langOpen = false" x-transition class="absolute right-0 mt-2 w-32 bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-xl shadow-xl overflow-hidden z-50">
                <a href="?lang=en" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-brand-50 dark:hover:bg-gray-700">English</a>
                <a href="?lang=bn" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-brand-50 dark:hover:bg-gray-700">বাংলা</a>
            </div>
        </div>

        <a href="<?= BASE_URL ?>store/" target="_blank"
           class="hidden sm:flex items-center gap-1 text-white/80 hover:text-white text-sm px-3 py-1.5 rounded-lg hover:bg-white/20 transition">
            🏪 <?= __('online_store') ?>
        </a>
        <a href="<?= BASE_URL ?>pos.php"
           class="hidden sm:flex items-center gap-1 bg-white text-brand-700 font-semibold text-sm px-3 py-1.5 rounded-lg hover:bg-brand-50 transition">
            🖩 <?= __('pos') ?>
        </a>
        <!-- User menu -->
        <div class="relative" x-data="{ open: false }">
            <button @click="open = !open" class="flex items-center gap-2 text-white hover:bg-white/20 rounded-lg px-2 py-1.5 transition" id="user-menu-btn">
                <span class="w-8 h-8 bg-white/30 rounded-full flex items-center justify-center font-bold text-sm">
                    <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
                </span>
                <span class="hidden sm:block text-sm"><?= htmlspecialchars($user['name']) ?></span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open" @click.outside="open = false" x-transition
                 class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-100 dark:border-gray-700 py-1 z-50">
                <div class="px-4 py-2 border-b border-gray-100 dark:border-gray-700">
                    <p class="text-sm font-semibold text-gray-800 dark:text-gray-200"><?= htmlspecialchars($user['name']) ?></p>
                    <p class="text-xs text-gray-500 capitalize"><?= htmlspecialchars($user['role']) ?></p>
                </div>
                <a href="<?= BASE_URL ?>settings.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">⚙️ <?= __('settings') ?></a>
                <a href="<?= BASE_URL ?>logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20">🚪 <?= __('logout') ?></a>
            </div>
        </div>
    </nav>
</header>

<!-- LAYOUT WRAPPER -->
<div class="flex pt-16 min-h-screen">
