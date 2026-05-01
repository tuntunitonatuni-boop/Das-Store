<?php
// settings.php — Shop settings, user management, feature toggles
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/flash.php';
require_once 'includes/features.php';
require_login();
require_role('owner');
$page_title = 'Settings';

$tab = $_GET['tab'] ?? 'users';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_user') {
        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role = $_POST['role'] ?? 'staff';
        if ($name && $username && $password) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            try {
                $pdo->prepare("INSERT INTO users (name, username, password, role) VALUES (?, ?, ?, ?)")->execute([$name, $username, $hashed, $role]);
                set_flash('success', "User '$name' added.");
            } catch (PDOException $e) {
                set_flash('error', "Username already exists.");
            }
        }
    }

    if ($action === 'edit_user') {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $role = $_POST['role'] ?? 'staff';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $pdo->prepare("UPDATE users SET name=?, username=?, role=?, is_active=? WHERE id=?")->execute([$name, $username, $role, $is_active, $id]);
        if (!empty($_POST['password'])) {
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($_POST['password'], PASSWORD_DEFAULT), $id]);
        }
        set_flash('success', "User updated.");
    }

    if ($action === 'save_features') {
        $keys   = $_POST['feature_key'] ?? [];
        $vals   = $_POST['feature_value'] ?? [];
        $enabled = $_POST['feature_enabled'] ?? [];

        foreach ($keys as $i => $key) {
            $key = trim($key);
            if (!$key) continue;
            $is_on = isset($enabled[$key]) ? 1 : 0;
            $val   = $vals[$i] ?? null;
            $pdo->prepare("INSERT INTO feature_settings (feature_key, is_enabled, value) VALUES (?,?,?)
                           ON DUPLICATE KEY UPDATE is_enabled=VALUES(is_enabled), value=VALUES(value)")
                ->execute([$key, $is_on, $val ?: null]);
        }
        set_flash('success', '✅ Feature settings saved!');
    }

    header('Location: settings.php?tab=' . $tab); exit;
}

$users = $pdo->query("SELECT * FROM users ORDER BY name")->fetchAll();
$edit_user = null;
if (isset($_GET['edit'])) {
    $euid = (int)$_GET['edit'];
    foreach ($users as $u) if ($u['id'] == $euid) $edit_user = $u;
}

// Load all feature settings with labels
try {
    $feat_rows = $pdo->query("SELECT * FROM feature_settings ORDER BY id")->fetchAll();
} catch (Exception $e) {
    $feat_rows = [];
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';

// Feature groups for organized display
$feature_groups = [
    'Customer Website' => [
        'online_store'        => ['label' => '🌐 Online Store (Customer Website)', 'type' => 'toggle', 'desc' => 'Show/hide the entire customer-facing website'],
        'bangla_product_names'=> ['label' => '🇧🇩 Bangla Product Names & Description', 'type' => 'toggle', 'desc' => 'Show Bangla names on website if available'],
        'product_zoom'        => ['label' => '🔍 Product Image Zoom', 'type' => 'toggle', 'desc' => 'Let customers zoom in/out on product images'],
        'multiple_media'      => ['label' => '🖼️ Multiple Product Images/Video', 'type' => 'toggle', 'desc' => 'Show image gallery on product pages'],
        'order_tracking'      => ['label' => '📦 Customer Order Tracking', 'type' => 'toggle', 'desc' => 'Let customers track their order status'],
    ],
    'Shopping Features' => [
        'wishlist'            => ['label' => '❤️ Wishlist', 'type' => 'toggle', 'desc' => 'Allow customers to save products to wishlist'],
        'customer_reviews'    => ['label' => '⭐ Product Reviews & Ratings', 'type' => 'toggle', 'desc' => 'Allow customers to leave product reviews'],
        'coupons'             => ['label' => '🎟️ Coupon / Discount Codes', 'type' => 'toggle', 'desc' => 'Enable coupon code system at checkout'],
        'custom_qty'          => ['label' => '⚖️ Custom Qty / Partial Selling', 'type' => 'toggle', 'desc' => 'Allow selling fractional amounts per product'],
        'free_delivery'       => ['label' => '🚚 Free Delivery Threshold (৳)', 'type' => 'value', 'desc' => 'Orders above this amount get free delivery (0 = disabled)'],
        'delivery_charge'     => ['label' => '💳 Default Delivery Charge (৳)', 'type' => 'value', 'desc' => 'Default delivery fee when below free threshold'],
    ],
    'Loyalty & Communication' => [
        'loyalty_points'      => ['label' => '🎁 Loyalty Points System', 'type' => 'toggle', 'desc' => 'Customers earn points on every purchase'],
        'loyalty_points_per_tk'=> ['label' => '💰 Points Earned Per ৳ Spent', 'type' => 'value', 'desc' => 'e.g. 1 = customer gets 1 point per taka spent'],
        'points_redeem_value' => ['label' => '🔁 1 Point = ৳ (Redemption Rate)', 'type' => 'value', 'desc' => 'e.g. 1 = 100 points = ৳100 discount'],
        'whatsapp_order'      => ['label' => '💬 WhatsApp Order Button', 'type' => 'toggle', 'desc' => 'Show floating WhatsApp button on website'],
        'whatsapp_number'     => ['label' => '📱 WhatsApp Number (digits only)', 'type' => 'value', 'desc' => 'e.g. 8801712345678 (no + sign)'],
    ],
    'Business Operations' => [
        'cash_register'       => ['label' => '🏦 Daily Cash Register', 'type' => 'toggle', 'desc' => 'Enable opening/closing cash drawer daily'],
        'low_stock_alert'     => ['label' => '⚠️ Low Stock Alert', 'type' => 'toggle', 'desc' => 'Show low-stock warning on dashboard'],
        'pos_ic'              => ['label' => '🍦 IC Wholesale POS', 'type' => 'toggle', 'desc' => 'Enable separate POS for ice cream wholesale'],
    ],
];
?>

<div class="page-header">
    <h1 class="dark:text-white"><?= __('settings') ?></h1>
    <p class="dark:text-gray-400"><?= __('manage_shop_config') ?></p>
</div>

<!-- Tabs -->
<div class="flex gap-1 mb-6 border-b border-gray-200 dark:border-gray-700 no-print flex-wrap">
    <?php foreach (['users' => '👤 '.__('user_management'), 'features' => '⚙️ Feature Toggles', 'shop' => '🏪 '.__('shop_config')] as $t => $label): ?>
    <a href="?tab=<?= $t ?>" class="px-5 py-2.5 text-sm font-medium transition rounded-t-lg <?= $tab===$t ? 'text-brand-700 border-b-2 border-brand-600 bg-brand-50 dark:text-brand-400 dark:border-brand-400 dark:bg-brand-900/10' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800' ?>"><?= $label ?></a>
    <?php endforeach; ?>
</div>

<?php if ($tab === 'users'): ?>
<!-- USER MANAGEMENT -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 card p-0 overflow-hidden dark:bg-gray-800 dark:border-gray-700">
        <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <h3 class="font-semibold dark:text-white"><?= __('all_users') ?></h3>
            <button onclick="document.getElementById('user-modal').classList.remove('hidden')" class="btn btn-xs btn-primary">+ <?= __('add_new') ?></button>
        </div>
        <table class="data-table">
            <thead><tr class="dark:bg-gray-700/50">
                <th class="dark:text-gray-300"><?= __('name') ?></th>
                <th class="dark:text-gray-300"><?= __('username') ?></th>
                <th class="dark:text-gray-300"><?= __('role') ?></th>
                <th class="dark:text-gray-300"><?= __('status') ?></th>
                <th class="dark:text-gray-300"><?= __('actions') ?></th>
            </tr></thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr class="dark:hover:bg-gray-700/30 transition-colors">
                    <td>
                        <div class="flex items-center gap-2">
                            <span class="w-8 h-8 rounded-full bg-brand-100 text-brand-700 dark:bg-brand-900/30 dark:text-brand-400 flex items-center justify-center font-bold text-xs uppercase"><?= substr($u['name'],0,1) ?></span>
                            <span class="font-medium dark:text-white"><?= htmlspecialchars($u['name']) ?></span>
                        </div>
                    </td>
                    <td><span class="text-gray-500 dark:text-gray-400 text-sm">@<?= htmlspecialchars($u['username']) ?></span></td>
                    <td><span class="badge <?= $u['role']==='owner' ? 'badge-blue' : 'badge-gray' ?> capitalize"><?= __($u['role'].'_role') ?></span></td>
                    <td><span class="badge <?= $u['is_active'] ? 'badge-green' : 'badge-red' ?>"><?= $u['is_active'] ? __('active') : __('inactive') ?></span></td>
                    <td><a href="?tab=users&edit=<?= $u['id'] ?>" class="btn btn-xs btn-secondary dark:bg-gray-700 dark:text-gray-200"><?= __('edit') ?></a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card bg-brand-50/50 dark:bg-brand-900/10 border-brand-100 dark:border-brand-900/30">
        <h3 class="font-semibold mb-3 dark:text-brand-400"><?= __('roles_overview') ?></h3>
        <div class="space-y-4">
            <div><b class="text-sm block text-brand-800 dark:text-brand-300"><?= __('owner_role') ?></b><p class="text-xs text-brand-600 dark:text-brand-400/80"><?= __('owner_desc') ?></p></div>
            <div><b class="text-sm block text-brand-800 dark:text-brand-300"><?= __('staff_role') ?></b><p class="text-xs text-brand-600 dark:text-brand-400/80"><?= __('staff_desc') ?></p></div>
        </div>
    </div>
</div>

<!-- User Modal -->
<div id="user-modal" class="<?= $edit_user ? '' : 'hidden' ?> fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center px-4">
    <div class="bg-white dark:bg-gray-800 rounded-2xl w-full max-w-sm shadow-2xl overflow-hidden">
        <div class="flex items-center justify-between p-5 border-b dark:border-gray-700">
            <h2 class="font-bold dark:text-white"><?= $edit_user ? __('edit') : __('add') ?> <?= __('user') ?></h2>
            <a href="?tab=users" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</a>
        </div>
        <form method="post" class="p-5 space-y-4">
            <input type="hidden" name="action" value="<?= $edit_user ? 'edit_user' : 'add_user' ?>">
            <?php if ($edit_user): ?><input type="hidden" name="id" value="<?= $edit_user['id'] ?>"><?php endif; ?>
            <div><label class="form-label dark:text-gray-300"><?= __('full_name_label') ?></label>
                <input type="text" name="name" required class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= htmlspecialchars($edit_user['name'] ?? '') ?>"></div>
            <div><label class="form-label dark:text-gray-300"><?= __('username') ?> *</label>
                <input type="text" name="username" required class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= htmlspecialchars($edit_user['username'] ?? '') ?>"></div>
            <div><label class="form-label dark:text-gray-300"><?= __('password') ?> <?= $edit_user ? __('password_blank_note') : '*' ?></label>
                <input type="password" name="password" <?= $edit_user ? '' : 'required' ?> class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="<?= $edit_user ? '••••••••' : '' ?>"></div>
            <div><label class="form-label dark:text-gray-300"><?= __('role') ?></label>
                <select name="role" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    <option value="staff" <?= ($edit_user['role'] ?? '') === 'staff' ? 'selected' : '' ?>><?= __('staff_member') ?></option>
                    <option value="owner" <?= ($edit_user['role'] ?? '') === 'owner' ? 'selected' : '' ?>><?= __('shop_owner') ?></option>
                </select></div>
            <?php if ($edit_user): ?>
            <label class="flex items-center gap-2 cursor-pointer font-medium text-sm dark:text-gray-300">
                <input type="checkbox" name="is_active" <?= $edit_user['is_active'] ? 'checked' : '' ?> class="w-4 h-4 rounded text-brand-600">
                <?= __('user_active_label') ?>
            </label>
            <?php endif; ?>
            <div class="flex justify-end gap-2 pt-2">
                <a href="?tab=users" class="btn btn-secondary dark:bg-gray-700 dark:text-gray-200"><?= __('cancel') ?></a>
                <button type="submit" class="btn btn-primary"><?= __('save_user') ?></button>
            </div>
        </form>
    </div>
</div>

<?php elseif ($tab === 'features'): ?>
<!-- FEATURE TOGGLES -->
<?php
// Build current values map
$feat_map = [];
foreach ($feat_rows as $r) {
    $feat_map[$r['feature_key']] = ['enabled' => (bool)$r['is_enabled'], 'value' => $r['value'], 'label' => $r['label']];
}
?>
<form method="post">
    <input type="hidden" name="action" value="save_features">

    <div class="space-y-6">
    <?php foreach ($feature_groups as $group_name => $features): ?>
    <div class="card dark:bg-gray-800 dark:border-gray-700 shadow-lg overflow-hidden p-0">
        <div class="px-6 py-4 bg-gradient-to-r from-brand-600 to-brand-700 dark:from-brand-800 dark:to-brand-900">
            <h3 class="font-bold text-white text-base"><?= $group_name ?></h3>
        </div>
        <div class="divide-y divide-gray-100 dark:divide-gray-700">
        <?php foreach ($features as $key => $meta):
            $current_enabled = $feat_map[$key]['enabled'] ?? true;
            $current_value   = $feat_map[$key]['value']   ?? '';
        ?>
        <div class="flex items-center justify-between px-6 py-4 hover:bg-gray-50/50 dark:hover:bg-gray-700/20 transition-colors">
            <div class="flex-1 pr-8">
                <div class="font-semibold text-gray-800 dark:text-gray-100 text-sm"><?= $meta['label'] ?></div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5"><?= $meta['desc'] ?></div>
                <?php if ($meta['type'] === 'value'): ?>
                <input type="text" name="feature_value[]" value="<?= htmlspecialchars($current_value) ?>"
                       class="mt-2 form-control text-sm py-1.5 max-w-xs dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                       placeholder="Enter value...">
                <?php else: ?>
                <input type="hidden" name="feature_value[]" value="">
                <?php endif; ?>
                <input type="hidden" name="feature_key[]" value="<?= $key ?>">
            </div>

            <?php if ($meta['type'] === 'toggle'): ?>
            <!-- Toggle Switch -->
            <label class="relative inline-flex items-center cursor-pointer shrink-0" title="<?= $meta['label'] ?>">
                <input type="checkbox" name="feature_enabled[<?= $key ?>]" value="1"
                       <?= $current_enabled ? 'checked' : '' ?> class="sr-only peer" id="ft-<?= $key ?>">
                <div class="w-14 h-7 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-brand-400 dark:peer-focus:ring-brand-600 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-7 peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all dark:border-gray-600 peer-checked:bg-brand-600 shadow-inner"></div>
                <span class="ml-2 text-xs font-bold <?= $current_enabled ? 'text-brand-600 dark:text-brand-400' : 'text-gray-400' ?> peer-checked:text-brand-600 w-8" id="ft-lbl-<?= $key ?>"><?= $current_enabled ? 'ON' : 'OFF' ?></span>
            </label>
            <?php else: ?>
            <span class="badge badge-blue text-[10px] shrink-0">VALUE</span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
    </div>

    <div class="mt-6 flex justify-end gap-3">
        <a href="dashboard.php" class="btn btn-secondary dark:bg-gray-700 dark:text-gray-300">Cancel</a>
        <button type="submit" class="btn btn-primary px-8 shadow-lg shadow-brand-500/30">💾 Save All Settings</button>
    </div>
</form>

<script>
// Live ON/OFF label update
document.querySelectorAll('[id^="ft-"]').forEach(cb => {
    if (!cb.classList.contains('sr-only')) return;
    cb.addEventListener('change', () => {
        const key = cb.id.replace('ft-','');
        const lbl = document.getElementById('ft-lbl-'+key);
        if (lbl) lbl.textContent = cb.checked ? 'ON' : 'OFF';
        lbl?.classList.toggle('text-brand-600', cb.checked);
        lbl?.classList.toggle('dark:text-brand-400', cb.checked);
        lbl?.classList.toggle('text-gray-400', !cb.checked);
    });
});
</script>

<?php elseif ($tab === 'shop'): ?>
<!-- SHOP CONFIG -->
<div class="card max-w-2xl dark:bg-gray-800 dark:border-gray-700">
    <h3 class="font-semibold text-lg mb-5 border-b pb-3 text-gray-800 dark:text-white dark:border-gray-700"><?= __('shop_identity_policy') ?></h3>
    <p class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 p-4 rounded-xl text-sm text-amber-800 dark:text-amber-300 mb-5">
        ⚠️ Shop settings are managed in <code class="bg-amber-100 dark:bg-amber-900/30 px-1 rounded">config.php</code>. Edit that file to change Name, Address, WhatsApp, etc.
    </p>
    <div class="grid grid-cols-2 gap-5 text-sm">
        <div class="bg-gray-50 dark:bg-gray-700/30 rounded-xl p-4"><div class="text-gray-400 dark:text-gray-500 text-xs mb-1"><?= __('current_name') ?></div><div class="font-bold text-gray-900 dark:text-white text-lg"><?= SHOP_NAME ?></div></div>
        <div class="bg-gray-50 dark:bg-gray-700/30 rounded-xl p-4"><div class="text-gray-400 dark:text-gray-500 text-xs mb-1">WhatsApp</div><div class="font-bold text-gray-900 dark:text-white">+<?= WHATSAPP_NO ?></div></div>
        <div class="bg-gray-50 dark:bg-gray-700/30 rounded-xl p-4 col-span-2"><div class="text-gray-400 dark:text-gray-500 text-xs mb-1"><?= __('address') ?></div><div class="font-bold text-gray-900 dark:text-white"><?= SHOP_ADDRESS ?></div></div>
        <div class="bg-gray-50 dark:bg-gray-700/30 rounded-xl p-4"><div class="text-gray-400 dark:text-gray-500 text-xs mb-1">Currency</div><div class="font-bold text-gray-900 dark:text-white text-2xl"><?= CURRENCY ?></div></div>
        <div class="bg-gray-50 dark:bg-gray-700/30 rounded-xl p-4"><div class="text-gray-400 dark:text-gray-500 text-xs mb-1">App Version</div><div class="font-bold text-gray-900 dark:text-white"><?= APP_VERSION ?></div></div>
        <div class="bg-gray-50 dark:bg-gray-700/30 rounded-xl p-4 col-span-2"><div class="text-gray-400 dark:text-gray-500 text-xs mb-1">Database</div><div class="font-mono text-xs dark:text-white"><?= DB_HOST ?> / <?= DB_NAME ?></div></div>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
