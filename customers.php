<?php
// customers.php — Credit customer management (Baki system)
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/flash.php';
require_login();
$page_title = 'Customers';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $name   = trim($_POST['name'] ?? '');
    $phone  = trim($_POST['phone'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $addr   = trim($_POST['address'] ?? '');
    $limit  = (float)($_POST['credit_limit'] ?? 0);

    if ($action === 'add') {
        $pdo->prepare("INSERT INTO customers (name,phone,email,address,credit_limit) VALUES (?,?,?,?,?)")
            ->execute([$name,$phone,$email,$addr,$limit]);
        set_flash('success',"Customer '$name' added."); header('Location: customers.php'); exit;
    }
    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        $active = isset($_POST['is_active']) ? 1 : 0;
        $pdo->prepare("UPDATE customers SET name=?,phone=?,email=?,address=?,credit_limit=?,is_active=? WHERE id=?")
            ->execute([$name,$phone,$email,$addr,$limit,$active,$id]);
        set_flash('success',"Customer updated."); header('Location: customers.php'); exit;
    }
    if ($action === 'delete') {
        $pdo->prepare("UPDATE customers SET is_active=0 WHERE id=?")->execute([(int)$_POST['id']]);
        set_flash('success',"Customer deactivated."); header('Location: customers.php'); exit;
    }
}

$search = trim($_GET['q'] ?? '');
$where  = "WHERE is_active=1";
$params = [];
if ($search) { $where .= " AND (name LIKE ? OR phone LIKE ?)"; $params = ["%$search%","%$search%"]; }

$customers = $pdo->prepare("SELECT * FROM customers $where ORDER BY name");
$customers->execute($params);
$customers = $customers->fetchAll();

$total_baki = $pdo->query("SELECT COALESCE(SUM(balance),0) FROM customers WHERE balance > 0 AND is_active=1")->fetchColumn();
$edit_cust  = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare("SELECT * FROM customers WHERE id=?"); $s->execute([(int)$_GET['edit']]); $edit_cust = $s->fetch();
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="page-header flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="dark:text-white"><?= __('customers') ?></h1>
        <p class="dark:text-gray-400"><?= __('customer_management') ?></p>
    </div>
    <div class="flex gap-2">
        <div class="stat-card py-2 px-4 text-right dark:bg-gray-800 dark:border-gray-700">
            <div class="text-xs text-gray-400"><?= __('total_baki_outstanding') ?></div>
            <div class="font-bold text-red-600 dark:text-red-400 text-lg"><?= CURRENCY . number_format($total_baki, 2) ?></div>
        </div>
        <button onclick="document.getElementById('cust-modal').classList.remove('hidden')" class="btn btn-primary" id="add-cust-btn">+ <?= __('add_customer') ?></button>
    </div>
</div>

<!-- Search -->
<form method="get" class="flex gap-3 mb-5 no-print">
    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="<?= __('customer_search_placeholder') ?>" class="form-control max-w-xs dark:bg-gray-700 dark:border-gray-600 dark:text-white">
    <button type="submit" class="btn btn-secondary dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600"><?= __('search') ?></button>
    <?php if ($search): ?><a href="customers.php" class="btn btn-secondary dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600"><?= __('clear') ?></a><?php endif; ?>
</form>

<div class="card dark:bg-gray-800 dark:border-gray-700 p-0 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="data-table dark:text-gray-300">
            <thead class="dark:bg-gray-900/50">
                <tr>
                    <th class="dark:text-gray-400"><?= __('name') ?></th>
                    <th class="dark:text-gray-400"><?= __('phone') ?></th>
                    <th class="dark:text-gray-400"><?= __('email') ?></th>
                    <th class="text-right dark:text-gray-400"><?= __('credit_limit') ?></th>
                    <th class="text-right dark:text-gray-400"><?= __('balance_baki') ?></th>
                    <th class="dark:text-gray-400"><?= __('actions') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
                <?php foreach ($customers as $c): ?>
                <tr class="dark:hover:bg-gray-700/50">
                    <td class="font-medium dark:text-gray-100"><?= htmlspecialchars($c['name']) ?></td>
                    <td class="dark:text-gray-400"><?= htmlspecialchars($c['phone'] ?? '—') ?></td>
                    <td class="text-gray-500 dark:text-gray-500 text-sm"><?= htmlspecialchars($c['email'] ?? '—') ?></td>
                    <td class="text-right dark:text-gray-300"><?= CURRENCY . number_format($c['credit_limit'], 2) ?></td>
                    <td class="text-right font-bold <?= $c['balance'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' ?>">
                        <?= $c['balance'] > 0 ? CURRENCY . number_format($c['balance'], 2) : '—' ?>
                    </td>
                    <td>
                        <div class="flex gap-1 flex-wrap">
                            <a href="customer-ledger.php?id=<?= $c['id'] ?>" class="btn btn-xs btn-secondary dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600">📒 <?= __('ledger') ?></a>
                            <a href="customer-payment.php?id=<?= $c['id'] ?>" class="btn btn-xs btn-secondary bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800">💳 <?= __('pay') ?></a>
                            <a href="customers.php?edit=<?= $c['id'] ?>" class="btn btn-xs btn-secondary dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600"><?= __('edit') ?></a>
                            <form method="post" class="inline">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-danger" onclick="return confirm('<?= __('confirm_delete') ?>')"><?= __('del') ?></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($customers)): ?>
                <tr><td colspan="6" class="text-center text-gray-400 py-10"><?= __('no_products_found') ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div id="cust-modal" class="<?= $edit_cust ? '' : 'hidden' ?> fixed inset-0 bg-black/50 z-50 flex items-center justify-center px-4">
    <div class="bg-white dark:bg-gray-800 rounded-2xl w-full max-w-md shadow-2xl">
        <div class="flex items-center justify-between p-5 border-b dark:border-gray-700">
            <h2 class="font-bold dark:text-white"><?= $edit_cust ? __('edit_customer') : __('add_customer') ?></h2>
            <a href="customers.php" class="text-gray-400 text-2xl">&times;</a>
        </div>
        <form method="post" class="p-5 grid grid-cols-2 gap-4">
            <input type="hidden" name="action" value="<?= $edit_cust ? 'edit' : 'add' ?>">
            <?php if ($edit_cust): ?><input type="hidden" name="id" value="<?= $edit_cust['id'] ?>"><?php endif; ?>
            <div class="col-span-2">
                <label class="form-label dark:text-gray-300"><?= __('full_name') ?> *</label>
                <input type="text" name="name" required class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= htmlspecialchars($edit_cust['name'] ?? '') ?>">
            </div>
            <div>
                <label class="form-label dark:text-gray-300"><?= __('phone') ?></label>
                <input type="text" name="phone" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= htmlspecialchars($edit_cust['phone'] ?? '') ?>">
            </div>
            <div>
                <label class="form-label dark:text-gray-300"><?= __('email') ?></label>
                <input type="email" name="email" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= htmlspecialchars($edit_cust['email'] ?? '') ?>">
            </div>
            <div class="col-span-2">
                <label class="form-label dark:text-gray-300"><?= __('address') ?></label>
                <textarea name="address" rows="2" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white"><?= htmlspecialchars($edit_cust['address'] ?? '') ?></textarea>
            </div>
            <div>
                <label class="form-label dark:text-gray-300"><?= __('credit_limit') ?> (<?= CURRENCY ?>)</label>
                <input type="number" step="0.01" name="credit_limit" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= $edit_cust['credit_limit'] ?? 0 ?>">
            </div>
            <?php if ($edit_cust): ?>
            <label class="flex items-center gap-2 self-end pb-1 dark:text-gray-300">
                <input type="checkbox" name="is_active" <?= $edit_cust['is_active'] ? 'checked' : '' ?>> <?= __('active') ?>
            </label>
            <?php else: ?><div></div><?php endif; ?>
            <div class="col-span-2 flex justify-end gap-2">
                <a href="customers.php" class="btn btn-secondary dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600"><?= __('cancel') ?></a>
                <button type="submit" class="btn btn-primary"><?= __('save') ?></button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
