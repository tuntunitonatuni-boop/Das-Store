<?php
// suppliers.php — Dealer profiles + WhatsApp/Call buttons
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/flash.php';
require_login();
$page_title = 'Suppliers';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $name    = trim($_POST['name'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $wapp    = trim($_POST['whatsapp'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($action === 'add') {
        $pdo->prepare("INSERT INTO dealers (name,company,phone,whatsapp,email,address) VALUES (?,?,?,?,?,?)")->execute([$name,$company,$phone,$wapp,$email,$address]);
        set_flash('success',"Supplier '$name' added."); header('Location: suppliers.php'); exit;
    }
    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        $active = isset($_POST['is_active']) ? 1 : 0;
        $pdo->prepare("UPDATE dealers SET name=?,company=?,phone=?,whatsapp=?,email=?,address=?,is_active=? WHERE id=?")->execute([$name,$company,$phone,$wapp,$email,$address,$active,$id]);
        set_flash('success',"Supplier updated."); header('Location: suppliers.php'); exit;
    }
    if ($action === 'delete') {
        $pdo->prepare("UPDATE dealers SET is_active=0 WHERE id=?")->execute([(int)$_POST['id']]);
        set_flash('success',"Supplier deactivated."); header('Location: suppliers.php'); exit;
    }
}

$dealers = $pdo->query("SELECT * FROM dealers ORDER BY name")->fetchAll();
$edit_dealer = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare("SELECT * FROM dealers WHERE id=?"); $s->execute([(int)$_GET['edit']]); $edit_dealer = $s->fetch();
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>
<div class="page-header flex items-center justify-between">
    <div>
        <h1 class="dark:text-white"><?= __('suppliers') ?></h1>
        <p class="dark:text-gray-400"><?= __('manage_suppliers') ?></p>
    </div>
    <button onclick="document.getElementById('sup-modal').classList.remove('hidden')" class="btn btn-primary" id="add-sup-btn">+ <?= __('add_supplier') ?></button>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
<?php foreach ($dealers as $d): ?>
<div class="card dark:bg-gray-800 dark:border-gray-700 hover:shadow-md transition">
    <div class="flex items-start justify-between mb-3">
        <div>
            <div class="font-bold text-gray-900 dark:text-gray-100"><?= htmlspecialchars($d['name']) ?></div>
            <div class="text-sm text-gray-500 dark:text-gray-500"><?= htmlspecialchars($d['company'] ?? '') ?></div>
        </div>
        <span class="badge <?= $d['is_active'] ? 'badge-green' : 'badge-gray' ?>">
            <?= $d['is_active'] ? __('active') : 'Off' ?>
        </span>
    </div>
    <?php if ($d['address']): ?>
    <p class="text-xs text-gray-500 dark:text-gray-500 mb-3">📍 <?= htmlspecialchars($d['address']) ?></p>
    <?php endif; ?>
    <div class="flex flex-wrap gap-2">
        <?php if ($d['phone']): ?>
        <a href="tel:<?= htmlspecialchars($d['phone']) ?>" class="btn btn-xs btn-secondary dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600">📞 <?= __('call') ?></a>
        <?php endif; ?>
        <?php if ($d['whatsapp']): ?>
        <a href="https://wa.me/<?= preg_replace('/\D/','',$d['whatsapp']) ?>" target="_blank" class="btn btn-xs flex items-center gap-1" style="background:#25D366;color:#fff">
            <svg class="w-3 h-3 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.417-.003 6.557-5.338 11.892-11.893 11.892-1.997-.001-3.951-.5-5.688-1.448l-6.305 1.652zm6.599-3.835c1.516.893 3.135 1.364 4.795 1.365 5.179 0 9.393-4.214 9.395-9.393.001-2.51-.974-4.87-2.744-6.641-1.77-1.77-4.133-2.744-6.641-2.744-5.181 0-9.397 4.215-9.399 9.394 0 1.704.469 3.371 1.357 4.834l-.993 3.626 3.71-.973zm11.231-6.19c-.3.149-1.774.875-2.048.974-.275.1-.475.149-.675-.149-.2-.299-.774-.974-.949-1.173-.175-.199-.349-.224-.649-.075-.298.149-1.26.464-2.4 1.48-1.025.918-1.58 1.942-1.78 2.243-.199.299-.021.46.128.608.134.133.299.349.449.523.149.174.199.299.299.497.101.199.049.374-.026.523-.075.149-.675 1.623-.924 2.221-.242.584-.349.505-.474.505s-.275.025-.425-.124c-.15-.15-.575-.548-1.124-1.037-2.618-2.338-3.41-3.82-3.41-3.82z"/></svg>
            <?= __('whatsapp') ?>
        </a>
        <?php endif; ?>
        <a href="suppliers.php?edit=<?= $d['id'] ?>" class="btn btn-xs btn-secondary ml-auto dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600"><?= __('edit') ?></a>
        <form method="post" class="inline">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $d['id'] ?>">
            <button type="submit" class="btn btn-xs btn-danger" onclick="return confirm('<?= __('confirm_delete') ?>')"><?= __('del') ?></button>
        </form>
    </div>
</div>
<?php endforeach; ?>
<?php if (empty($dealers)): ?>
<div class="sm:col-span-3 text-center text-gray-400 py-10"><?= __('no_products_found') ?></div>
<?php endif; ?>
</div>

<!-- Modal -->
<div id="sup-modal" class="<?= $edit_dealer ? '' : 'hidden' ?> fixed inset-0 bg-black/50 z-50 flex items-center justify-center px-4">
    <div class="bg-white dark:bg-gray-800 rounded-2xl w-full max-w-lg shadow-2xl">
        <div class="flex items-center justify-between p-5 border-b dark:border-gray-700">
            <h2 class="font-bold dark:text-white"><?= $edit_dealer ? __('edit_supplier') : __('add_supplier') ?></h2>
            <a href="suppliers.php" class="text-gray-400 text-2xl">&times;</a>
        </div>
        <form method="post" class="p-5 grid grid-cols-2 gap-4">
            <input type="hidden" name="action" value="<?= $edit_dealer ? 'edit' : 'add' ?>">
            <?php if ($edit_dealer): ?><input type="hidden" name="id" value="<?= $edit_dealer['id'] ?>"><?php endif; ?>
            <div class="col-span-2">
                <label class="form-label dark:text-gray-300"><?= __('full_name') ?> *</label>
                <input type="text" name="name" required class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= htmlspecialchars($edit_dealer['name'] ?? '') ?>">
            </div>
            <div>
                <label class="form-label dark:text-gray-300"><?= __('company') ?></label>
                <input type="text" name="company" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= htmlspecialchars($edit_dealer['company'] ?? '') ?>">
            </div>
            <div>
                <label class="form-label dark:text-gray-300"><?= __('phone') ?></label>
                <input type="text" name="phone" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= htmlspecialchars($edit_dealer['phone'] ?? '') ?>">
            </div>
            <div>
                <label class="form-label dark:text-gray-300"><?= __('whatsapp') ?></label>
                <input type="text" name="whatsapp" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= htmlspecialchars($edit_dealer['whatsapp'] ?? '') ?>">
            </div>
            <div>
                <label class="form-label dark:text-gray-300"><?= __('email') ?></label>
                <input type="email" name="email" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= htmlspecialchars($edit_dealer['email'] ?? '') ?>">
            </div>
            <div class="col-span-2">
                <label class="form-label dark:text-gray-300"><?= __('address') ?></label>
                <textarea name="address" rows="2" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white"><?= htmlspecialchars($edit_dealer['address'] ?? '') ?></textarea>
            </div>
            <?php if ($edit_dealer): ?>
            <label class="col-span-2 flex items-center gap-2 dark:text-gray-300">
                <input type="checkbox" name="is_active" <?= $edit_dealer['is_active'] ? 'checked' : '' ?>> <?= __('active') ?>
            </label>
            <?php endif; ?>
            <div class="col-span-2 flex justify-end gap-2">
                <a href="suppliers.php" class="btn btn-secondary dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600"><?= __('cancel') ?></a>
                <button type="submit" class="btn btn-primary"><?= __('save') ?></button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
