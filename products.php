<?php
// products.php — Product CRUD: add, edit, delete, barcode, price, batch, expiry
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/flash.php';
require_login();

$page_title = 'Products';

// --- Handle POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $id          = (int)($_POST['id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $sku         = trim($_POST['sku'] ?? '') ?: null;
        $barcode     = trim($_POST['barcode'] ?? '') ?: null;
        $cat_id      = (int)$_POST['category_id'] ?: null;
        $company_id  = (int)$_POST['company_id']  ?: null;
        $dealer_id   = (int)$_POST['dealer_id']   ?: null;
        $unit        = trim($_POST['unit'] ?? 'pcs');
        $weight_size = trim($_POST['weight_size'] ?? '');
        $cost_price  = (float)$_POST['cost_price'];
        $sale_price  = (float)$_POST['sale_price'];
        $mrp         = (float)($_POST['mrp'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $is_active   = isset($_POST['is_active']) ? 1 : 0;
        $track_expiry= isset($_POST['track_expiry']) ? 1 : 0;
        $mfg_date    = $_POST['mfg_date'] ?: null;
        $expiry_date = $_POST['expiry_date'] ?: null;

        // Image upload
        $image = null;
        if (!empty($_FILES['image']['name'])) {
            $ext   = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','webp','gif'];
            if (in_array($ext, $allowed)) {
                $filename = 'prod_' . time() . '_' . rand(100,999) . '.' . $ext;
                $dest     = BASE_PATH . 'uploads/products/' . $filename;
                if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0775, true);
                if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                    $image = $filename;
                }
            }
        }

        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO products (name,sku,barcode,category_id,company_id,dealer_id,unit,weight_size,cost_price,sale_price,mrp,description,image,is_active,track_expiry,mfg_date,expiry_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$name,$sku,$barcode,$cat_id,$company_id,$dealer_id,$unit,$weight_size,$cost_price,$sale_price,$mrp,$description,$image,$is_active,$track_expiry,$mfg_date,$expiry_date]);
            $new_id = $pdo->lastInsertId();
            // Create inventory record with initial stock if provided
            $init_qty = (float)($_POST['initial_qty'] ?? 0);
            $pdo->prepare("INSERT IGNORE INTO inventory (product_id, display_qty) VALUES (?,?)")->execute([$new_id, $init_qty]);
            
            // Log as stock movement (Opening stock)
            if ($init_qty > 0) {
                $pdo->prepare("INSERT INTO stock_movements (product_id, type, qty_change, location, notes, user_id) VALUES (?, 'opening', ?, 'display', 'Initial stock on product creation', ?)")
                    ->execute([$new_id, $init_qty, current_user()['id']]);
            }
            set_flash('success', "Product '$name' added.");
        } else {
            // Log price history if changed
            $old = $pdo->prepare("SELECT cost_price, sale_price FROM products WHERE id=?");
            $old->execute([$id]); $oldp = $old->fetch();
            if ($oldp && ($oldp['cost_price'] != $cost_price || $oldp['sale_price'] != $sale_price)) {
                $pdo->prepare("INSERT INTO price_history (product_id,old_cost,new_cost,old_sale,new_sale,changed_by) VALUES (?,?,?,?,?,?)")
                    ->execute([$id,$oldp['cost_price'],$cost_price,$oldp['sale_price'],$sale_price,current_user()['id']]);
            }
            $sql = "UPDATE products SET name=?,sku=?,barcode=?,category_id=?,company_id=?,dealer_id=?,unit=?,weight_size=?,cost_price=?,sale_price=?,mrp=?,description=?,is_active=?,track_expiry=?,mfg_date=?,expiry_date=?";
            $params = [$name,$sku,$barcode,$cat_id,$company_id,$dealer_id,$unit,$weight_size,$cost_price,$sale_price,$mrp,$description,$is_active,$track_expiry,$mfg_date,$expiry_date];
            if ($image) { $sql .= ",image=?"; $params[] = $image; }
            $sql .= " WHERE id=?"; $params[] = $id;
            $pdo->prepare($sql)->execute($params);
            set_flash('success', "Product '$name' updated.");
        }
        header('Location: products.php'); exit;
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE products SET is_active=0 WHERE id=?")->execute([$id]);
        set_flash('success', 'Product deactivated.');
        header('Location: products.php'); exit;
    }
}

// --- Fetch data ---
$search  = trim($_GET['q'] ?? '');
$cat_filter = (int)($_GET['cat'] ?? 0);
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where = "WHERE p.is_active = 1";
$params = [];
if ($search) { $where .= " AND (p.name LIKE ? OR p.barcode LIKE ? OR p.sku LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
if ($cat_filter) { $where .= " AND p.category_id=?"; $params[] = $cat_filter; }

$total    = $pdo->prepare("SELECT COUNT(*) FROM products p $where"); $total->execute($params); $totalRows = $total->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

$stmt = $pdo->prepare("SELECT p.*, c.name as cat_name, co.name as company_name, d.name as dealer_name, d.phone as dealer_phone, COALESCE(i.display_qty+i.warehouse_qty,0) as stock FROM products p LEFT JOIN categories c ON c.id=p.category_id LEFT JOIN companies co ON co.id=p.company_id LEFT JOIN dealers d ON d.id=p.dealer_id LEFT JOIN inventory i ON i.product_id=p.id $where ORDER BY p.name ASC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = $pdo->query("SELECT id, name FROM categories WHERE is_active=1 ORDER BY name")->fetchAll();
$companies  = $pdo->query("SELECT id, name FROM companies WHERE is_active=1 ORDER BY name")->fetchAll();
$dealers    = $pdo->query("SELECT id, name FROM dealers WHERE is_active=1 ORDER BY name")->fetchAll();

$edit_product = null;
if (isset($_GET['edit'])) {
    $ep = $pdo->prepare("SELECT * FROM products WHERE id=?");
    $ep->execute([(int)$_GET['edit']]);
    $edit_product = $ep->fetch();
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="page-header flex flex-wrap items-center justify-between gap-3">
    <div><h1 class="dark:text-white"><?= __('products') ?></h1><p class="dark:text-gray-400"><?= __('manage_catalogue') ?></p></div>
    <button onclick="document.getElementById('product-modal').classList.remove('hidden')"
            class="btn btn-primary" id="add-product-btn">+ <?= __('add_product') ?></button>
</div>

<!-- Search & Filter -->
<form method="get" class="flex flex-wrap gap-3 mb-5">
    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="<?= __('search_products') ?>"
           class="form-control max-w-xs dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200">
    <select name="cat" class="form-control max-w-xs dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200" data-autosubmit>
        <option value=""><?= __('all_categories') ?></option>
        <?php foreach ($categories as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $cat_filter == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-secondary"><?= __('filter') ?></button>
    <?php if ($search || $cat_filter): ?><a href="products.php" class="btn btn-secondary"><?= __('clear') ?></a><?php endif; ?>
</form>

<!-- Product Table -->
<div class="card dark:bg-gray-800 dark:border-gray-700 p-0 overflow-hidden">
    <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
        <span class="text-sm text-gray-500 dark:text-gray-400"><?= $totalRows ?> <?= __('products_found') ?></span>
    </div>
    <div class="overflow-x-auto">
        <table class="data-table dark:text-gray-300">
            <thead class="dark:bg-gray-900/50">
                <tr>
                    <th class="dark:text-gray-400"><?= __('products') ?></th>
                    <th class="dark:text-gray-400"><?= __('sku_barcode') ?></th>
                    <th class="dark:text-gray-400"><?= __('categories') ?></th>
                    <th class="text-right dark:text-gray-400"><?= __('cost') ?></th>
                    <th class="text-right dark:text-gray-400"><?= __('price') ?></th>
                    <th class="text-right dark:text-gray-400"><?= __('stock') ?></th>
                    <th class="dark:text-gray-400"><?= __('expiry_mfg') ?></th>
                    <th class="dark:text-gray-400"><?= __('status') ?></th>
                    <th class="dark:text-gray-400"><?= __('actions') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
            <?php foreach ($products as $p): ?>
            <tr class="dark:hover:bg-gray-700/50">
                <td>
                    <div class="flex items-center gap-3">
                        <?php if ($p['image']): ?>
                        <img src="<?= BASE_URL ?>uploads/products/<?= htmlspecialchars($p['image']) ?>" class="w-9 h-9 rounded-lg object-cover border border-gray-200 dark:border-gray-600">
                        <?php else: ?>
                        <div class="w-9 h-9 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center text-lg">📦</div>
                        <?php endif; ?>
                        <div>
                            <div class="font-medium text-gray-900 dark:text-gray-100">
                                <?= htmlspecialchars($p['name']) ?>
                                <?php if($p['weight_size']): ?>
                                 <span class="text-brand-600 bg-brand-50 dark:bg-brand-900/20 px-1.5 py-0.5 rounded text-[10px] font-bold uppercase ml-1 border border-brand-100 dark:border-brand-800"><?= htmlspecialchars($p['weight_size']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="text-xs text-gray-400 dark:text-gray-500">
                                <?= htmlspecialchars($p['company_name'] ?? '') ?>
                                <?php if($p['dealer_name']): ?>
                                 • <?= __('dealer_supplier') ?>: <span class="text-brand-600 dark:text-brand-400 font-semibold" title="Phone: <?= $p['dealer_phone'] ?>"><?= htmlspecialchars($p['dealer_name']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </td>
                <td class="font-mono text-xs text-gray-600 dark:text-gray-400">
                    <?= htmlspecialchars($p['sku'] ?? '—') ?><br>
                    <span class="text-gray-400 dark:text-gray-500"><?= htmlspecialchars($p['barcode'] ?? '—') ?></span>
                </td>
                <td class="dark:text-gray-300"><?= htmlspecialchars($p['cat_name'] ?? '—') ?></td>
                <td class="text-right dark:text-gray-300"><?= CURRENCY . number_format($p['cost_price'], 2) ?></td>
                <td class="text-right font-semibold dark:text-gray-100"><?= CURRENCY . number_format($p['sale_price'], 2) ?></td>
                <td class="text-right">
                    <span class="badge <?= $p['stock'] <= LOW_STOCK_THRESHOLD ? 'badge-yellow' : 'badge-green' ?>">
                        <?= number_format($p['stock'], 1) ?> <?= htmlspecialchars($p['unit']) ?>
                    </span>
                </td>
                <td>
                    <div class="text-[10px] space-y-0.5">
                        <?php 
                        $mfg = $p['mfg_date'] ? __('mfg') . ': ' . date('d/m/y', strtotime($p['mfg_date'])) : '';
                        $exp = $p['expiry_date'] ? __('exp') . ': ' . date('d/m/y', strtotime($p['expiry_date'])) : '';
                        ?>
                        <div class="text-gray-500 dark:text-gray-500"><?= $mfg ?></div>
                        <div class="font-bold text-red-600/80 dark:text-red-400"><?= $exp ?></div>
                    </div>
                </td>
                <td><span class="badge badge-green"><?= __('active') ?></span></td>
                <td>
                    <div class="flex gap-1">
                        <a href="products.php?edit=<?= $p['id'] ?>" class="btn btn-xs btn-secondary"><?= __('edit') ?></a>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <button type="submit" class="btn btn-xs btn-danger" data-confirm="<?= __('del') ?> '<?= htmlspecialchars($p['name']) ?>'?"><?= __('del') ?></button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($products)): ?>
            <tr><td colspan="9" class="text-center text-gray-400 py-10"><?= __('no_products_found') ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="px-5 py-3 border-t border-gray-100 dark:border-gray-700 flex items-center gap-2">
        <?php for ($pg = 1; $pg <= $totalPages; $pg++): ?>
        <a href="?page=<?= $pg ?>&q=<?= urlencode($search) ?>&cat=<?= $cat_filter ?>"
           class="px-3 py-1 rounded-lg text-sm <?= $pg === $page ? 'bg-brand-600 text-white' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700' ?>">
            <?= $pg ?>
        </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Add/Edit Modal -->
<div id="product-modal" class="<?= $edit_product ? '' : 'hidden' ?> fixed inset-0 bg-black/50 z-50 flex items-start justify-center pt-8 md:pt-16 overflow-y-auto px-4">
    <div class="bg-white dark:bg-gray-800 rounded-2xl w-full max-w-2xl shadow-2xl my-4">
        <div class="flex items-center justify-between p-5 border-b border-gray-100 dark:border-gray-700">
            <h2 class="font-bold text-lg dark:text-white"><?= $edit_product ? __('edit') . ' ' . __('products') : __('add_product') ?></h2>
            <a href="products.php" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 text-2xl leading-none">&times;</a>
        </div>
        <form method="post" enctype="multipart/form-data" class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <input type="hidden" name="action" value="<?= $edit_product ? 'edit' : 'add' ?>">
            <?php if ($edit_product): ?><input type="hidden" name="id" value="<?= $edit_product['id'] ?>"><?php endif; ?>

            <div class="sm:col-span-2">
                <label class="form-label dark:text-gray-300"><?= __('product_name') ?> *</label>
                <input type="text" name="name" required class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= htmlspecialchars($edit_product['name'] ?? '') ?>">
            </div>
            <div>
                <label class="form-label dark:text-gray-300">SKU</label>
                <input type="text" name="sku" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= htmlspecialchars($edit_product['sku'] ?? '') ?>">
            </div>
            <div>
                <label class="form-label dark:text-gray-300">Barcode</label>
                <input type="text" name="barcode" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= htmlspecialchars($edit_product['barcode'] ?? '') ?>">
            </div>
            <div>
                <label class="form-label dark:text-gray-300"><?= __('categories') ?></label>
                <select name="category_id" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    <option value=""><?= __('select') ?></option>
                    <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($edit_product['category_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label dark:text-gray-300"><?= __('company_brand') ?></label>
                <select name="company_id" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    <option value=""><?= __('select') ?></option>
                    <?php foreach ($companies as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($edit_product['company_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label dark:text-gray-300"><?= __('dealer_supplier') ?></label>
                <select name="dealer_id" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    <option value=""><?= __('select_dealer') ?></option>
                    <?php foreach ($dealers as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= ($edit_product['dealer_id'] ?? '') == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label dark:text-gray-300"><?= __('weight_size') ?></label>
                <input type="text" name="weight_size" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="e.g. 5kg, 1L, 500gm" value="<?= htmlspecialchars($edit_product['weight_size'] ?? '') ?>">
            </div>
            <div>
                <label class="form-label dark:text-gray-300"><?= __('unit') ?></label>
                <select name="unit" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    <?php foreach (['pcs','kg','gm','ltr','ml','bag','box','doz','pack','roll'] as $u): ?>
                    <option <?= ($edit_product['unit'] ?? 'pcs') === $u ? 'selected' : '' ?>><?= $u ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (!$edit_product): ?>
            <div>
                <label class="form-label font-semibold text-brand-600 dark:text-brand-400"><?= __('starting_stock') ?></label>
                <input type="number" step="0.001" name="initial_qty" class="form-control border-brand-200 dark:bg-gray-700 dark:border-brand-500/50 dark:text-white" value="0">
            </div>
            <?php endif; ?>
            <div>
                <label class="form-label dark:text-gray-300"><?= __('cost_price') ?> (<?= CURRENCY ?>)</label>
                <input type="number" step="0.01" name="cost_price" required class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= $edit_product['cost_price'] ?? '0' ?>">
            </div>
            <div>
                <label class="form-label dark:text-gray-300"><?= __('sale_price') ?> (<?= CURRENCY ?>)</label>
                <input type="number" step="0.01" name="sale_price" required class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= $edit_product['sale_price'] ?? '0' ?>">
            </div>
            <div>
                <label class="form-label dark:text-gray-300"><?= __('mrp') ?> (<?= CURRENCY ?>)</label>
                <input type="number" step="0.01" name="mrp" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= $edit_product['mrp'] ?? '0' ?>">
            </div>
            <div class="sm:col-span-2">
                <label class="form-label dark:text-gray-300"><?= __('description') ?></label>
                <textarea name="description" rows="2" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white"><?= htmlspecialchars($edit_product['description'] ?? '') ?></textarea>
            </div>
            <div>
                <label class="form-label dark:text-gray-300"><?= __('product_image') ?></label>
                <input type="file" name="image" accept="image/*" class="form-control p-2 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
            </div>
            <div class="flex flex-col justify-center gap-3">
                <label class="flex items-center gap-2 cursor-pointer dark:text-gray-300">
                    <input type="checkbox" name="is_active" <?= ($edit_product['is_active'] ?? 1) ? 'checked' : '' ?>> <?= __('active') ?>
                </label>
                <label class="flex items-center gap-2 cursor-pointer dark:text-gray-300">
                    <input type="checkbox" name="track_expiry" <?= ($edit_product['track_expiry'] ?? 0) ? 'checked' : '' ?>> <?= __('track_expiry') ?>
                </label>
            </div>
            <div>
                <label class="form-label dark:text-gray-300"><?= __('mfg_date') ?></label>
                <input type="date" name="mfg_date" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= $edit_product['mfg_date'] ?? '' ?>">
            </div>
            <div>
                <label class="form-label dark:text-gray-300"><?= __('expiry_date') ?></label>
                <input type="date" name="expiry_date" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= $edit_product['expiry_date'] ?? '' ?>">
            </div>
            <div class="sm:col-span-2 flex justify-end gap-3 pt-2">
                <a href="products.php" class="btn btn-secondary dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600"><?= __('cancel') ?></a>
                <button type="submit" class="btn btn-primary"><?= $edit_product ? __('update_product') : __('add_product') ?></button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
