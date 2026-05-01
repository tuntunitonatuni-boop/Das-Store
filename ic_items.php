<?php
// ic_items.php — Wholesale Ingredients
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/flash.php';
require_login();
$page_title = 'Wholesale Ingredients';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $name = trim($_POST['name'] ?? '');
        $unit = trim($_POST['unit'] ?? 'Kg');
        $avg_buy_price = (float)($_POST['avg_buy_price'] ?? 0);
        $stock_qty = (float)($_POST['stock_qty'] ?? 0);

        if ($action === 'add') {
            $pdo->prepare("INSERT INTO ic_items (name, unit, avg_buy_price, stock_qty) VALUES (?,?,?,?)")->execute([$name, $unit, $avg_buy_price, $stock_qty]);
            set_flash('success',"Ingredient '$name' added."); 
        } else {
            $id = (int)$_POST['id'];
            $pdo->prepare("UPDATE ic_items SET name=?, unit=?, avg_buy_price=?, stock_qty=? WHERE id=?")->execute([$name, $unit, $avg_buy_price, $stock_qty, $id]);
            set_flash('success',"Ingredient updated."); 
        }
        header('Location: ic_items.php'); 
        exit;
    }
    if ($action === 'delete') {
        try {
            $pdo->prepare("DELETE FROM ic_items WHERE id=?")->execute([(int)$_POST['id']]);
            set_flash('success',"Ingredient deleted."); 
        } catch(PDOException $e) {
            set_flash('error',"Cannot delete ingredient that has been used in purchases or sales.");
        }
        header('Location: ic_items.php'); 
        exit;
    }
}

$items = $pdo->query("SELECT * FROM ic_items ORDER BY name")->fetchAll();
$edit_i = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare("SELECT * FROM ic_items WHERE id=?"); 
    $s->execute([(int)$_GET['edit']]); 
    $edit_i = $s->fetch();
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>
<div class="page-header flex items-center justify-between">
    <div>
        <h1 class="dark:text-white">Wholesale Ingredients</h1>
        <p class="dark:text-gray-400">Manage Ice Cream Raw Materials & Stock</p>
    </div>
    <button onclick="document.getElementById('i-modal').classList.remove('hidden')" class="btn btn-primary">+ Add Ingredient</button>
</div>

<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-gray-50 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wider border-b border-gray-100 dark:border-gray-700">
                    <th class="p-4 font-semibold">Ingredient Name</th>
                    <th class="p-4 font-semibold">Unit</th>
                    <th class="p-4 font-semibold text-right">Stock Qty</th>
                    <th class="p-4 font-semibold text-right">Avg. Buy Price</th>
                    <th class="p-4 font-semibold text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                <?php foreach ($items as $i): ?>
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/20 transition-colors">
                    <td class="p-4 font-medium text-gray-900 dark:text-gray-100"><?= htmlspecialchars($i['name']) ?></td>
                    <td class="p-4 text-gray-500 dark:text-gray-400"><span class="badge badge-gray"><?= htmlspecialchars($i['unit']) ?></span></td>
                    <td class="p-4 text-right font-bold <?= $i['stock_qty'] <= 0 ? 'text-red-500' : 'text-emerald-600 dark:text-emerald-400' ?>">
                        <?= floatval($i['stock_qty']) ?> <?= htmlspecialchars($i['unit']) ?>
                    </td>
                    <td class="p-4 text-right font-medium text-gray-900 dark:text-gray-300">
                        <?= CURRENCY . number_format($i['avg_buy_price'], 2) ?>
                    </td>
                    <td class="p-4 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <a href="ic_items.php?edit=<?= $i['id'] ?>" class="btn btn-xs btn-secondary">Edit</a>
                            <form method="post" class="inline">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $i['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-danger" onclick="return confirm('Delete this ingredient? Note: Best to avoid deleting items with history.')">Del</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($items)): ?>
                <tr><td colspan="5" class="p-8 text-center text-gray-400">No ingredients found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="i-modal" class="<?= $edit_i ? '' : 'hidden' ?> fixed inset-0 bg-black/50 z-50 flex items-center justify-center px-4">
    <div class="bg-white dark:bg-gray-800 rounded-2xl w-full max-w-lg shadow-2xl">
        <div class="flex items-center justify-between p-5 border-b dark:border-gray-700">
            <h2 class="font-bold dark:text-white"><?= $edit_i ? 'Edit Ingredient' : 'Add Ingredient' ?></h2>
            <a href="ic_items.php" class="text-gray-400 text-2xl hover:text-red-500">&times;</a>
        </div>
        <form method="post" class="p-5 flex flex-col gap-4">
            <input type="hidden" name="action" value="<?= $edit_i ? 'edit' : 'add' ?>">
            <?php if ($edit_i): ?><input type="hidden" name="id" value="<?= $edit_i['id'] ?>"><?php endif; ?>
            
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="form-label dark:text-gray-300">Ingredient Name * (e.g. Saccharin)</label>
                    <input type="text" name="name" required class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= htmlspecialchars($edit_i['name'] ?? '') ?>">
                </div>
                <div>
                    <label class="form-label dark:text-gray-300">Unit (Kg, Pcs, Ltr, Bag)</label>
                    <input type="text" name="unit" required class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= htmlspecialchars($edit_i['unit'] ?? 'Kg') ?>">
                </div>
                <div class="col-span-2 pt-2 border-t border-gray-100 dark:border-gray-700 mt-2">
                    <p class="text-xs text-orange-500 mb-3">Only edit the below fields if you are inserting initial/old stock. Normal purchases will update these automatically.</p>
                </div>
                <div>
                    <label class="form-label dark:text-gray-300">Current Stock Qty</label>
                    <input type="number" step="0.01" name="stock_qty" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= htmlspecialchars($edit_i['stock_qty'] ?? '0') ?>">
                </div>
                <div>
                    <label class="form-label dark:text-gray-300">Avg Buy Price (Per Unit)</label>
                    <input type="number" step="0.01" name="avg_buy_price" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= htmlspecialchars($edit_i['avg_buy_price'] ?? '0') ?>">
                </div>
            </div>
            
            <div class="flex justify-end gap-2 mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
                <a href="ic_items.php" class="btn btn-secondary dark:bg-gray-700">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Ingredient</button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
