<?php
// ============================================================
//  Smart Grocery ERP — Global Configuration
//  Edit ONLY this file when deploying to cPanel.
// ============================================================

// --- Database Credentials ---
// Configure sessions for better cross-device support before session is started anywhere
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'samesite' => 'Lax'
]);

$_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_is_localhost = ($_host === 'localhost' || $_host === '127.0.0.1' || strpos($_host, '192.168.') === 0);

if ($_is_localhost) {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'grocery_erp');
    define('DB_USER', 'root');
    define('DB_PASS', '');
} else {
    // Live Server Credentials (InfinityFree)
    define('DB_HOST', 'sql113.infinityfree.com');
    define('DB_NAME', 'if0_41548430_dasstore');
    define('DB_USER', 'if0_41548430');
    define('DB_PASS', 'BFfu7uf51nZw7MB');
}
define('DB_CHARSET', 'utf8mb4');

// --- Shop / Brand Settings ---
define('SHOP_NAME', 'Das Store');
define('SHOP_TAGLINE', 'Your Neighbourhood Smart Grocery');
define('SHOP_ADDRESS', '123 Market Street, Dhaka');
define('SHOP_PHONE', '+8801700000000');
define('SHOP_EMAIL', 'info@dasstore.com');
define('WHATSAPP_NO', '8801700000000');   // digits only, no +

// --- Receipt Header ---
define('RECEIPT_HEADER', SHOP_NAME . "\n" . SHOP_ADDRESS . "\nPhone: " . SHOP_PHONE);
define('RECEIPT_FOOTER', 'Thank you for shopping with us!');

// --- URL & Path ---
// Auto-detect protocol and host so it works from any device (PC, phone, etc.)
$_protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

if ($_is_localhost) {
    define('BASE_URL', $_protocol . '://' . $_host . '/Das%20Store/');
} else {
    define('BASE_URL', $_protocol . '://' . $_host . '/');
}
define('BASE_PATH', __DIR__ . '/');

// --- Timezone ---
date_default_timezone_set('Asia/Dhaka');

// --- App Globals ---
define('APP_VERSION', '1.0.0');
define('CURRENCY', '৳');        // BDT taka symbol
define('LOW_STOCK_THRESHOLD', 10); // units below which "low stock" alert fires
define('EXPIRY_WARN_DAYS', 30);    // days before expiry for warning

// --- Price Rounding ---
// Bangladesh-specific: 50 paisa has no real value, so all prices are ceiled to the nearest whole taka.
// Use price_ceil() everywhere instead of number_format() for final displayed prices.
if (!function_exists('price_ceil')) {
    function price_ceil(float $amount): int {
        return (int)ceil($amount);
    }
    function fmt_price(float $amount): string {
        return number_format(price_ceil($amount));
    }
}

// --- Translations ---
require_once __DIR__ . '/includes/lang.php';

// --- Feature flags (loaded after DB is ready in each page) ---
// Note: features.php auto-loads when DB is available; include it per-page after db.php
