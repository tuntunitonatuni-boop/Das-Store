<?php
// settings.php — Shop settings, user management
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/flash.php';
require_login();
require_role('owner'); // Only owners can access settings
$page_title = 'Settings';

$tab = $_GET['tab'] ?? 'users';

// Handle user creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_user') {
        $name     = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role     = $_POST['role'] ?? 'staff';

        if ($name && $username && $password) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, username, password, role) VALUES (?, ?, ?, ?)");
            try {
                $stmt->execute([$name, $username, $hashed, $role]);
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

        $sql = "UPDATE users SET name=?, username=?, role=?, is_active=? WHERE id=?";
        $params = [$name, $username, $role, $is_active, $id];
        $pdo->prepare($sql)->execute($params);

        if (!empty($_POST['password'])) {
            $hashed = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hashed, $id]);
        }
        set_flash('success', "User updated.");
    }
    header('Location: settings.php?tab='.$tab); exit;
}

$users = $pdo->query("SELECT * FROM users ORDER BY name")->fetchAll();
$edit_user = null;
if (isset($_GET['edit'])) {
    $euid = (int)$_GET['edit'];
    foreach ($users as $u) if ($u['id'] == $euid) $edit_user = $u;
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="page-header">
    <h1 class="dark:text-white"><?= __('settings') ?></h1>
    <p class="dark:text-gray-400"><?= __('manage_shop_config') ?></p>
</div>

<!-- Tabs -->
<div class="flex gap-2 mb-6 border-b border-gray-200 dark:border-gray-700 no-print">
    <a href="?tab=users" class="px-5 py-2 text-sm font-medium transition <?= $tab==='users' ? 'text-brand-700 border-b-2 border-brand-700 bg-brand-50/50 dark:text-brand-400 dark:border-brand-400 dark:bg-brand-900/10' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50 dark:text-gray-400 dark:hover:text-gray-300 dark:hover:bg-gray-800' ?>"><?= __('user_management') ?></a>
    <a href="?tab=shop" class="px-5 py-2 text-sm font-medium transition <?= $tab==='shop' ? 'text-brand-700 border-b-2 border-brand-700 bg-brand-50/50 dark:text-brand-400 dark:border-brand-400 dark:bg-brand-900/10' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50 dark:text-gray-400 dark:hover:text-gray-300 dark:hover:bg-gray-800' ?>"><?= __('shop_config') ?></a>
</div>

<?php if ($tab === 'users'): ?>
<!-- USER MANAGEMENT -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- User List -->
    <div class="lg:col-span-2 card p-0 overflow-hidden dark:bg-gray-800 dark:border-gray-700">
        <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <h3 class="font-semibold dark:text-white"><?= __('all_users') ?></h3>
            <button onclick="document.getElementById('user-modal').classList.remove('hidden')" class="btn btn-xs btn-primary">+ <?= __('add_new') ?></button>
        </div>
        <table class="data-table">
            <thead>
                <tr class="dark:bg-gray-700/50">
                    <th class="dark:text-gray-300"><?= __('name') ?></th>
                    <th class="dark:text-gray-300"><?= __('username') ?></th>
                    <th class="dark:text-gray-300"><?= __('role') ?></th>
                    <th class="dark:text-gray-300"><?= __('status') ?></th>
                    <th class="dark:text-gray-300"><?= __('actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr class="dark:hover:bg-gray-700/30 transition-colors">
                    <td>
                        <div class="flex items-center gap-2">
                            <span class="w-8 h-8 rounded-full bg-brand-100 text-brand-700 dark:bg-brand-900/30 dark:text-brand-400 flex items-center justify-center font-bold text-xs uppercase ring-1 ring-brand-200 dark:ring-brand-800">
                                <?= substr($u['name'], 0, 1) ?>
                            </span>
                            <span class="font-medium dark:text-white"><?= htmlspecialchars($u['name']) ?></span>
                        </div>
                    </td>
                    <td><span class="text-gray-500 dark:text-gray-400 text-sm">@<?= htmlspecialchars($u['username']) ?></span></td>
                    <td><span class="badge <?= $u['role']==='owner' ? 'badge-blue' : 'badge-gray dark:bg-gray-700 dark:text-gray-300' ?> capitalize"><?= __($u['role'].'_role') ?></span></td>
                    <td><span class="badge <?= $u['is_active'] ? 'badge-green' : 'badge-red' ?>"><?= $u['is_active'] ? __('active') : __('inactive') ?></span></td>
                    <td>
                        <a href="?tab=users&edit=<?= $u['id'] ?>" class="btn btn-xs btn-secondary dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"><?= __('edit') ?></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Right info card -->
    <div class="card bg-brand-50/50 dark:bg-brand-900/10 border-brand-100 dark:border-brand-900/30">
        <h3 class="font-semibold mb-3 dark:text-brand-400"><?= __('roles_overview') ?></h3>
        <div class="space-y-4">
            <div>
                <b class="text-sm block text-brand-800 dark:text-brand-300"><?= __('owner_role') ?></b>
                <p class="text-xs text-brand-600 dark:text-brand-400/80"><?= __('owner_desc') ?></p>
            </div>
            <div>
                <b class="text-sm block text-brand-800 dark:text-brand-300"><?= __('staff_role') ?></b>
                <p class="text-xs text-brand-600 dark:text-brand-400/80"><?= __('staff_desc') ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div id="user-modal" class="<?= $edit_user ? '' : 'hidden' ?> fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center px-4">
    <div class="bg-white dark:bg-gray-800 rounded-2xl w-full max-w-sm shadow-2xl overflow-hidden">
        <div class="flex items-center justify-between p-5 border-b dark:border-gray-700">
            <h2 class="font-bold dark:text-white"><?= $edit_user ? __('edit') : __('add') ?> <?= __('user') ?></h2>
            <a href="?tab=users" class="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 text-2xl leading-none">&times;</a>
        </div>
        <form method="post" class="p-5 space-y-4">
            <input type="hidden" name="action" value="<?= $edit_user ? 'edit_user' : 'add_user' ?>">
            <?php if ($edit_user): ?><input type="hidden" name="id" value="<?= $edit_user['id'] ?>"><?php endif; ?>
            <div>
                <label class="form-label dark:text-gray-300"><?= __('full_name_label') ?></label>
                <input type="text" name="name" required class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= htmlspecialchars($edit_user['name'] ?? '') ?>">
            </div>
            <div>
                <label class="form-label dark:text-gray-300"><?= __('username') ?> *</label>
                <input type="text" name="username" required class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= htmlspecialchars($edit_user['username'] ?? '') ?>">
            </div>
            <div>
                <label class="form-label dark:text-gray-300"><?= __('password') ?> <?= $edit_user ? __('password_blank_note') : '*' ?></label>
                <input type="password" name="password" <?= $edit_user ? '' : 'required' ?> class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="<?= $edit_user ? '••••••••' : '' ?>">
            </div>
            <div>
                <label class="form-label dark:text-gray-300"><?= __('role') ?></label>
                <select name="role" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    <option value="staff" <?= ($edit_user['role'] ?? '') === 'staff' ? 'selected' : '' ?>><?= __('staff_member') ?></option>
                    <option value="owner" <?= ($edit_user['role'] ?? '') === 'owner' ? 'selected' : '' ?>><?= __('shop_owner') ?></option>
                </select>
            </div>
            <?php if ($edit_user): ?>
            <label class="flex items-center gap-2 cursor-pointer font-medium text-sm dark:text-gray-300">
                <input type="checkbox" name="is_active" <?= $edit_user['is_active'] ? 'checked' : '' ?> class="w-4 h-4 rounded text-brand-600 focus:ring-brand-500 dark:bg-gray-700 dark:border-gray-600">
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

<?php elseif ($tab === 'shop'): ?>
<!-- SHOP CONFIGURATION -->
<div class="card max-w-2xl dark:bg-gray-800 dark:border-gray-700">
    <h3 class="font-semibold text-lg mb-5 border-b pb-3 text-gray-800 dark:text-white dark:border-gray-700"><?= __('shop_identity_policy') ?></h3>
    <div class="space-y-4">
        <p class="bg-gray-50 dark:bg-gray-700/30 p-4 border dark:border-gray-600 rounded-xl text-sm leading-relaxed text-gray-600 dark:text-gray-400">
            Shop settings (Name, Address, WhatsApp, etc.) are managed via <code>config.php</code> to ensure they are available to every file and remain persistent after core updates.
            To change these values, edit the <b>Global Constants</b> in your config file.
        </p>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div><span class="text-gray-400 dark:text-gray-500 block mb-1"><?= __('current_name') ?>:</span> <b class="text-gray-800 dark:text-white"><?= SHOP_NAME ?></b></div>
            <div><span class="text-gray-400 dark:text-gray-500 block mb-1"><?= __('whatsapp_no_label') ?>:</span> <b class="text-gray-800 dark:text-white">+<?= WHATSAPP_NO ?></b></div>
            <div class="col-span-2"><span class="text-gray-400 dark:text-gray-500 block mb-1"><?= __('address') ?>:</span> <b class="text-gray-800 dark:text-white"><?= SHOP_ADDRESS ?></b></div>
            <div><span class="text-gray-400 dark:text-gray-500 block mb-1"><?= __('currency_label') ?>:</span> <b class="text-gray-800 dark:text-white"><?= CURRENCY ?></b></div>
            <div><span class="text-gray-400 dark:text-gray-500 block mb-1"><?= __('timezone_label') ?>:</span> <b class="text-gray-800 dark:text-white"><?= date_default_timezone_get() ?></b></div>
        </div>

        <div class="pt-6 border-t mt-6 dark:border-gray-700">
            <h4 class="font-semibold mb-3 dark:text-white"><?= __('db_connection') ?></h4>
            <div class="bg-gray-100/50 dark:bg-gray-900/30 p-3 rounded-xl border border-gray-200 dark:border-gray-700">
                <code class="text-xs text-gray-500 dark:text-gray-400 font-mono">
                    Host: <?= DB_HOST ?><br>
                    DB: <?= DB_NAME ?>
                </code>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
