<?php
// includes/auth.php — session guard + role enforcement
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Pages that do NOT require login
$public_pages = ['login.php', 'store/index.php', 'store/category.php',
                 'store/product.php', 'store/cart.php', 'store/checkout.php'];

$current_script = str_replace(BASE_PATH, '', str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME']));

function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
}

function require_role(string $role): void {
    require_login();
    if (($_SESSION['role'] ?? '') !== $role) {
        header('Location: ' . BASE_URL . 'dashboard.php?error=access_denied');
        exit;
    }
}

function current_user(): array {
    return [
        'id'       => $_SESSION['user_id']   ?? 0,
        'name'     => $_SESSION['user_name'] ?? 'Guest',
        'role'     => $_SESSION['role']      ?? '',
        'avatar'   => $_SESSION['avatar']    ?? '',
    ];
}

function is_owner(): bool { return ($_SESSION['role'] ?? '') === 'owner'; }
function is_staff(): bool  { return ($_SESSION['role'] ?? '') === 'staff'; }

// --- Customer Auth ---
function is_customer_logged_in(): bool {
    return isset($_SESSION['customer_id']) && !empty($_SESSION['customer_id']);
}

function require_customer_login(): void {
    if (!is_customer_logged_in()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . BASE_URL . 'store/login.php');
        exit;
    }
}

function current_customer(): array {
    return [
        'id'   => $_SESSION['customer_id']   ?? 0,
        'name' => $_SESSION['customer_name'] ?? 'Guest',
    ];
}

// Auto-enforce login for every page except public store pages & login page
// (individual pages call require_login() after this include)
