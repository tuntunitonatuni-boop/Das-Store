<?php
// includes/pos-header.php — Minimal header for POS interface (no sidebar)
if (!defined('BASE_URL')) require_once dirname(__DIR__) . '/config.php';
$page_title = $page_title ?? 'POS';
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
    <title><?= htmlspecialchars($page_title) ?> — <?= SHOP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: { colors: { brand: { 50: '#f0fdf4', 100: '#dcfce7', 200: '#bbf7d0', 300: '#86efac', 400: '#4ade80', 500: '#22c55e', 600: '#16a34a', 700: '#15803d', 800: '#166534', 900: '#14532d' } }, fontFamily: { sans: ['Inter', 'sans-serif'] } } }
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
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/pos.css">
</head>
<body class="bg-gray-100 font-sans h-screen overflow-hidden dark:bg-gray-950 transition-colors duration-300">
<!-- POS Top Bar -->
<header class="h-12 bg-brand-700 dark:bg-brand-900 text-white flex items-center px-4 gap-4 shadow-md fixed top-0 left-0 right-0 z-40">
    <a href="<?= BASE_URL ?>dashboard.php" class="text-white/70 hover:text-white text-sm flex items-center gap-1 transition-colors">
        ← <?= __('back') ?>
    </a>
    <span class="font-bold flex items-center gap-2">
        <span class="text-xl">🖩</span> <?= SHOP_NAME ?> <?= __('pos') ?>
    </span>
    <div class="flex-1"></div>
    <span class="text-xs text-white/50 font-mono hidden sm:inline" id="pos-clock"></span>
    
    <!-- Theme Toggle -->
    <button @click="toggleTheme()" class="p-1.5 rounded-lg bg-white/10 text-white/80 hover:bg-white/20 transition" title="<?= __('dark_mode') ?>">
        <span x-show="!darkMode">🌙</span>
        <span x-show="darkMode" x-cloak>☀️</span>
    </button>

    <!-- Lang Switcher -->
    <div class="flex items-center gap-1 bg-white/10 rounded-lg p-0.5">
        <a href="?lang=en" class="px-2 py-0.5 text-[10px] font-bold rounded <?= get_current_lang() === 'en' ? 'bg-white text-brand-700' : 'text-white/60 hover:text-white' ?>">EN</a>
        <a href="?lang=bn" class="px-2 py-0.5 text-[10px] font-bold rounded <?= get_current_lang() === 'bn' ? 'bg-white text-brand-700' : 'text-white/60 hover:text-white' ?>">বাংলা</a>
    </div>

    <a href="<?= BASE_URL ?>logout.php" class="text-xs text-white/70 hover:text-white transition-colors"><?= __('logout') ?></a>
</header>
<div class="pt-12 h-full">
<?php
// Inline style for brand colors in tailwind config
?>
<style>
    .bg-brand-700{background:#15803d}.text-brand-700{color:#15803d}
    .bg-brand-600{background:#16a34a}.hover\:bg-brand-700:hover{background:#15803d}
</style>
