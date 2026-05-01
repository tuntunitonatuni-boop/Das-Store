<?php
// ic_suppliers.php — Wholesale Suppliers
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/flash.php';
require_login();
$page_title = 'Ice Cream Suppliers';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $name    = trim($_POST['name'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($action === 'add') {
        $pdo->prepare("INSERT INTO ic_suppliers (name, phone, address) VALUES (?,?,?)")->execute([$name, $phone, $address]);
        set_flash('success',"Supplier '$name' added."); 
        header('Location: ic_suppliers.php'); 
        exit;
    }
    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE ic_suppliers SET name=?, phone=?, address=? WHERE id=?")->execute([$name, $phone, $address, $id]);
        set_flash('success',"Supplier updated."); 
        header('Location: ic_suppliers.php'); 
        exit;
    }
    if ($action === 'delete') {
        // Can't delete if they have purchases, handled by RESTRICT in DB
        try {
            $pdo->prepare("DELETE FROM ic_suppliers WHERE id=?")->execute([(int)$_POST['id']]);
            set_flash('success',"Supplier deleted."); 
        } catch(PDOException $e) {
            set_flash('error',"Cannot delete supplier with existing purchase records.");
        }
        header('Location: ic_suppliers.php'); 
        exit;
    }
}

$suppliers = $pdo->query("SELECT * FROM ic_suppliers ORDER BY name")->fetchAll();
$edit_sup = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare("SELECT * FROM ic_suppliers WHERE id=?"); 
    $s->execute([(int)$_GET['edit']]); 
    $edit_sup = $s->fetch();
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>
<div class="page-header flex items-center justify-between">
    <div>
        <h1 class="dark:text-white">Wholesale Suppliers</h1>
        <p class="dark:text-gray-400">Manage suppliers for Ice Cream Ingredients</p>
    </div>
    <button onclick="document.getElementById('sup-modal').classList.remove('hidden')" class="btn btn-primary">+ Add Supplier</button>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
<?php foreach ($suppliers as $s): ?>
<div class="card dark:bg-gray-800 dark:border-gray-700 hover:shadow-md transition">
    <div class="flex items-start justify-between mb-3">
        <div>
            <div class="font-bold text-gray-900 dark:text-gray-100"><?= htmlspecialchars($s['name']) ?></div>
            <div class="text-sm font-semibold text-brand-600 dark:text-brand-400 mt-1">Due: <?= CURRENCY . number_format($s['balance'], 2) ?></div>
        </div>
    </div>
    <?php if ($s['phone']): ?>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">📞 <?= htmlspecialchars($s['phone']) ?></p>
    <?php endif; ?>
    <?php if ($s['address']): ?>
        <p class="text-xs text-gray-500 dark:text-gray-500 mb-3">📍 <?= htmlspecialchars($s['address']) ?></p>
    <?php endif; ?>
    <div class="flex flex-wrap gap-2 mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
        <a href="ic_suppliers.php?edit=<?= $s['id'] ?>" class="btn btn-xs btn-secondary dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600">Edit</a>
        <form method="post" class="inline ml-auto">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $s['id'] ?>">
            <button type="submit" class="btn btn-xs btn-danger" onclick="return confirm('Delete this supplier?')">Delete</button>
        </form>
    </div>
</div>
<?php endforeach; ?>
<?php if (empty($suppliers)): ?>
<div class="sm:col-span-3 text-center text-gray-400 py-10">No suppliers found.</div>
<?php endif; ?>
</div>

<div id="sup-modal" class="<?= $edit_sup ? '' : 'hidden' ?> fixed inset-0 bg-black/50 z-50 flex items-center justify-center px-4">
    <div class="bg-white dark:bg-gray-800 rounded-2xl w-full max-w-lg shadow-2xl">
        <div class="flex items-center justify-between p-5 border-b dark:border-gray-700">
            <h2 class="font-bold dark:text-white"><?= $edit_sup ? 'Edit Supplier' : 'Add Supplier' ?></h2>
            <a href="ic_suppliers.php" class="text-gray-400 text-2xl hover:text-red-500">&times;</a>
        </div>
        <form method="post" class="p-5 flex flex-col gap-4">
            <input type="hidden" name="action" value="<?= $edit_sup ? 'edit' : 'add' ?>">
            <?php if ($edit_sup): ?><input type="hidden" name="id" value="<?= $edit_sup['id'] ?>"><?php endif; ?>
            <div>
                <label class="form-label dark:text-gray-300">Supplier Name *</label>
                <input type="text" name="name" required class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= htmlspecialchars($edit_sup['name'] ?? '') ?>">
            </div>
            <div>
                <label class="form-label dark:text-gray-300">Phone Number</label>
                <input type="text" name="phone" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= htmlspecialchars($edit_sup['phone'] ?? '') ?>">
            </div>
            <div>
                <label class="form-label dark:text-gray-300">Address</label>
                <textarea name="address" rows="2" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white"><?= htmlspecialchars($edit_sup['address'] ?? '') ?></textarea>
            </div>
            <div class="flex justify-end gap-2 mt-2">
                <a href="ic_suppliers.php" class="btn btn-secondary dark:bg-gray-700">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Supplier</button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
