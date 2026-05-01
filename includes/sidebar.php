<?php
// includes/sidebar.php — Admin sidebar navigation (Phase 2 complete)
$current = basename($_SERVER['SCRIPT_FILENAME']);

function nav_item(string $icon, string $label, string $href, string $current, string $match_file): string {
    $active = ($current === $match_file)
        ? 'bg-brand-600 text-white shadow-sm'
        : 'text-gray-600 dark:text-gray-400 hover:bg-brand-50 dark:hover:bg-gray-700 hover:text-brand-700 dark:hover:text-brand-400';
    return <<<HTML
    <a href="{$href}" class="nav-item flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 {$active}">
        <span class="text-base leading-none w-6 text-center">{$icon}</span>
        <span class="sidebar-label truncate">{$label}</span>
    </a>
    HTML;
}
?>

<!-- SIDEBAR -->
<aside id="sidebar"
       :class="sidebarOpen ? 'w-60' : 'w-0 -translate-x-full lg:w-16 lg:translate-x-0'"
       class="fixed top-16 left-0 bottom-0 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 shadow-sm
              flex flex-col transition-all duration-300 ease-in-out overflow-hidden z-40">

    <div class="flex-1 py-3 px-2 overflow-y-auto">
        <!-- MAIN -->
        <p class="sidebar-label text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest px-3 mb-1.5">Main</p>
        <nav class="space-y-0.5 mb-3">
            <?= nav_item('📊', __('dashboard'),     BASE_URL.'dashboard.php',    $current, 'dashboard.php') ?>
            <?= nav_item('🖩',  __('pos'),           BASE_URL.'pos.php',          $current, 'pos.php') ?>
            <?= nav_item('🛍️', __('online_orders'), BASE_URL.'orders.php',       $current, 'orders.php') ?>
            <?= nav_item('🏦', 'Cash Register',     BASE_URL.'cash-register.php',$current, 'cash-register.php') ?>
        </nav>

        <!-- INVENTORY -->
        <p class="sidebar-label text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest px-3 mb-1.5">Inventory</p>
        <nav class="space-y-0.5 mb-3">
            <?= nav_item('📦', __('products'),       BASE_URL.'products.php',        $current, 'products.php') ?>
            <?= nav_item('🏷️', __('categories'),     BASE_URL.'categories.php',      $current, 'categories.php') ?>
            <?= nav_item('🏢', __('companies'),      BASE_URL.'companies.php',       $current, 'companies.php') ?>
            <?= nav_item('🏪', __('inventory'),      BASE_URL.'inventory.php',       $current, 'inventory.php') ?>
            <?= nav_item('🔄', __('stock_movements'),BASE_URL.'stock-movements.php', $current, 'stock-movements.php') ?>
            <?= nav_item('↩️', __('returns'),        BASE_URL.'returns.php',         $current, 'returns.php') ?>
            <?= nav_item('⏰', __('expiry_tracker'), BASE_URL.'expiry.php',          $current, 'expiry.php') ?>
        </nav>

        <!-- PROCUREMENT -->
        <p class="sidebar-label text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest px-3 mb-1.5">Procurement</p>
        <nav class="space-y-0.5 mb-3">
            <?= nav_item('🚚', __('suppliers'),       BASE_URL.'suppliers.php',       $current, 'suppliers.php') ?>
            <?= nav_item('🛒', __('purchases'),       BASE_URL.'purchases.php',       $current, 'purchases.php') ?>
            <?= nav_item('💰', __('price_history'),   BASE_URL.'price-history.php',   $current, 'price-history.php') ?>
            <?= nav_item('📒', __('supplier_ledger'), BASE_URL.'supplier-ledger.php', $current, 'supplier-ledger.php') ?>
            <?= nav_item('💳', __('supplier_payments'),BASE_URL.'supplier-payment.php',$current,'supplier-payment.php') ?>
        </nav>

        <!-- FINANCE -->
        <p class="sidebar-label text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest px-3 mb-1.5">Finance</p>
        <nav class="space-y-0.5 mb-3">
            <?= nav_item('💸', __('expenses'),    BASE_URL.'expenses.php',      $current, 'expenses.php') ?>
            <?= nav_item('🎟️', 'Coupons',        BASE_URL.'coupons.php',       $current, 'coupons.php') ?>
            <?= nav_item('🎁', 'Loyalty Points', BASE_URL.'loyalty.php',       $current, 'loyalty.php') ?>
            <?= nav_item('📈', __('reports'),     BASE_URL.'reports.php',       $current, 'reports.php') ?>
        </nav>

        <!-- CUSTOMERS -->
        <p class="sidebar-label text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest px-3 mb-1.5">Customers</p>
        <nav class="space-y-0.5 mb-3">
            <?= nav_item('👥', __('customers'),      BASE_URL.'customers.php',       $current, 'customers.php') ?>
            <?= nav_item('📒', __('customer_ledger'),BASE_URL.'customer-ledger.php', $current, 'customer-ledger.php') ?>
            <?= nav_item('💳', __('payments'),       BASE_URL.'customer-payment.php',$current, 'customer-payment.php') ?>
        </nav>

        <!-- DIVIDER: IC WHOLESALE -->
        <div class="h-px bg-gradient-to-r from-transparent via-gray-300 dark:via-gray-600 to-transparent mx-3 my-3"></div>
        <p class="sidebar-label text-[10px] font-bold text-blue-500 dark:text-blue-400 uppercase tracking-widest px-3 mb-1.5">🍦 IC Wholesale</p>
        <nav class="space-y-0.5 mb-3">
            <?= nav_item('🖩',  'IC POS',       BASE_URL.'pos-ic.php',       $current, 'pos-ic.php') ?>
            <?= nav_item('📦', 'Ingredients',   BASE_URL.'ic_items.php',     $current, 'ic_items.php') ?>
            <?= nav_item('🚚', 'IC Suppliers',  BASE_URL.'ic_suppliers.php', $current, 'ic_suppliers.php') ?>
            <?= nav_item('📥', 'IC Purchases',  BASE_URL.'ic_purchases.php', $current, 'ic_purchases.php') ?>
            <?= nav_item('👥', 'IC Customers',  BASE_URL.'ic_customers.php', $current, 'ic_customers.php') ?>
            <?= nav_item('📤', 'IC Sales',      BASE_URL.'ic_sales.php',     $current, 'ic_sales.php') ?>
        </nav>

        <!-- ADMIN -->
        <?php if (is_owner()): ?>
        <div class="h-px bg-gray-100 dark:bg-gray-700 mx-3 my-3"></div>
        <p class="sidebar-label text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest px-3 mb-1.5">Admin</p>
        <nav class="space-y-0.5 mb-3">
            <?= nav_item('⚙️', __('settings'), BASE_URL.'settings.php', $current, 'settings.php') ?>
        </nav>
        <?php endif; ?>
    </div>

    <!-- Sidebar footer -->
    <div class="sidebar-label p-3 border-t border-gray-100 dark:border-gray-700 text-[10px] text-gray-400 text-center">
        Das Store v<?= APP_VERSION ?> · Phase 2
    </div>
</aside>

<!-- Page content wrapper -->
<main :class="sidebarOpen ? 'lg:ml-60' : 'lg:ml-16'" class="flex-1 min-w-0 transition-all duration-300 ease-in-out">
    <div class="p-4 md:p-6 max-w-screen-2xl mx-auto">
        <?php render_flash(); ?>
