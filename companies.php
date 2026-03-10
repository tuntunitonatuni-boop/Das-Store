<?php
// companies.php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/flash.php';
require_login();
$page_title = 'Companies';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $name    = trim($_POST['name'] ?? '');
    $country = trim($_POST['country'] ?? '');
    if ($action === 'add') {
        $pdo->prepare("INSERT INTO companies (name, country) VALUES (?,?)")->execute([$name,$country]);
        set_flash('success',"Company '$name' added."); header('Location: companies.php'); exit;
    }
    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        $active = isset($_POST['is_active']) ? 1 : 0;
        $pdo->prepare("UPDATE companies SET name=?,country=?,is_active=? WHERE id=?")->execute([$name,$country,$active,$id]);
        set_flash('success',"Company updated."); header('Location: companies.php'); exit;
    }
    if ($action === 'delete') {
        $pdo->prepare("UPDATE companies SET is_active=0 WHERE id=?")->execute([(int)$_POST['id']]);
        set_flash('success',"Company deactivated."); header('Location: companies.php'); exit;
    }
}

$companies = $pdo->query("SELECT co.*, COUNT(p.id) as product_count FROM companies co LEFT JOIN products p ON p.company_id=co.id AND p.is_active=1 GROUP BY co.id ORDER BY co.name")->fetchAll();
$edit_co = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare("SELECT * FROM companies WHERE id=?"); $s->execute([(int)$_GET['edit']]); $edit_co = $s->fetch();
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>
<div class="page-header flex items-center justify-between">
    <div>
        <h1 class="dark:text-white"><?= __('companies_brands') ?></h1>
        <p class="dark:text-gray-400"><?= __('manage_companies') ?></p>
    </div>
    <button onclick="document.getElementById('co-modal').classList.remove('hidden')" class="btn btn-primary" id="add-co-btn">+ <?= __('add_company') ?></button>
</div>

<div class="card dark:bg-gray-800 dark:border-gray-700 p-0 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="data-table dark:text-gray-300">
            <thead class="dark:bg-gray-900/50">
                <tr>
                    <th class="dark:text-gray-400"><?= __('name') ?></th>
                    <th class="dark:text-gray-400"><?= __('country') ?></th>
                    <th class="dark:text-gray-400"><?= __('products') ?></th>
                    <th class="dark:text-gray-400"><?= __('status') ?></th>
                    <th class="dark:text-gray-400"><?= __('actions') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
                <?php foreach ($companies as $co): ?>
                <tr class="dark:hover:bg-gray-700/50">
                    <td class="font-medium dark:text-gray-100"><?= htmlspecialchars($co['name']) ?></td>
                    <td class="text-gray-500 dark:text-gray-500"><?= htmlspecialchars($co['country'] ?? '—') ?></td>
                    <td class="dark:text-gray-300"><?= $co['product_count'] ?></td>
                    <td>
                        <span class="badge <?= $co['is_active'] ? 'badge-green' : 'badge-gray' ?>">
                            <?= $co['is_active'] ? __('active') : __('inactive') ?>
                        </span>
                    </td>
                    <td>
                        <a href="companies.php?edit=<?= $co['id'] ?>" class="btn btn-xs btn-secondary dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600"><?= __('edit') ?></a>
                        <form method="post" class="inline">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $co['id'] ?>">
                            <button type="submit" class="btn btn-xs btn-danger" onclick="return confirm('<?= __('confirm_delete') ?>')"><?= __('del') ?></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="co-modal" class="<?= $edit_co ? '' : 'hidden' ?> fixed inset-0 bg-black/50 z-50 flex items-center justify-center px-4">
    <div class="bg-white dark:bg-gray-800 rounded-2xl w-full max-w-sm shadow-2xl">
        <div class="flex items-center justify-between p-5 border-b dark:border-gray-700">
            <h2 class="font-bold dark:text-white"><?= $edit_co ? __('edit_company') : __('add_company') ?></h2>
            <a href="companies.php" class="text-gray-400 text-2xl">&times;</a>
        </div>
        <form method="post" class="p-5 space-y-4">
            <input type="hidden" name="action" value="<?= $edit_co ? 'edit' : 'add' ?>">
            <?php if ($edit_co): ?><input type="hidden" name="id" value="<?= $edit_co['id'] ?>"><?php endif; ?>
            <div>
                <label class="form-label dark:text-gray-300"><?= __('company_name') ?> *</label>
                <input type="text" name="name" required class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= htmlspecialchars($edit_co['name'] ?? '') ?>">
            </div>
            <div>
                <label class="form-label dark:text-gray-300"><?= __('country') ?></label>
                <input type="text" name="country" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= htmlspecialchars($edit_co['country'] ?? '') ?>">
            </div>
            <?php if ($edit_co): ?>
            <label class="flex items-center gap-2 dark:text-gray-300">
                <input type="checkbox" name="is_active" <?= $edit_co['is_active'] ? 'checked' : '' ?>> <?= __('active') ?>
            </label>
            <?php endif; ?>
            <div class="flex justify-end gap-2">
                <a href="companies.php" class="btn btn-secondary dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600"><?= __('cancel') ?></a>
                <button type="submit" class="btn btn-primary"><?= __('save') ?></button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
