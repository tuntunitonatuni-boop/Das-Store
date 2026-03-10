<?php
// ============================================================
//  Smart Grocery ERP — Global Configuration
//  Edit ONLY this file when deploying to cPanel.
// ============================================================

// --- Database Credentials ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'grocery_erp');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// --- Shop / Brand Settings ---
define('SHOP_NAME',    'Das Store');
define('SHOP_TAGLINE', 'Your Neighbourhood Smart Grocery');
define('SHOP_ADDRESS', '123 Market Street, Dhaka');
define('SHOP_PHONE',   '+8801700000000');
define('SHOP_EMAIL',   'info@dasstore.com');
define('WHATSAPP_NO',  '8801700000000');   // digits only, no +

// --- Receipt Header ---
define('RECEIPT_HEADER', SHOP_NAME . "\n" . SHOP_ADDRESS . "\nPhone: " . SHOP_PHONE);
define('RECEIPT_FOOTER', 'Thank you for shopping with us!');

// --- URL & Path ---
define('BASE_URL',  'http://localhost/Das%20Store/');   // trailing slash; update on deploy
define('BASE_PATH', __DIR__ . '/');

// --- Timezone ---
date_default_timezone_set('Asia/Dhaka');

// --- App Globals ---
define('APP_VERSION', '1.0.0');
define('CURRENCY',    '৳');        // BDT taka symbol
define('LOW_STOCK_THRESHOLD', 10); // units below which "low stock" alert fires
define('EXPIRY_WARN_DAYS', 30);    // days before expiry for warning

// --- Translations ---
require_once __DIR__ . '/includes/lang.php';
