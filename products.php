<?php
// products.php — Full Phase 2 upgrade: publish, bangla, custom qty, badge, multi-media
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/flash.php';
require_once 'includes/features.php';
require_login();
$page_title = 'Products';

// ─── POST Handler ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $id             = (int)($_POST['id'] ?? 0);
        $name           = trim($_POST['name'] ?? '');
        $name_bn        = trim($_POST['name_bn'] ?? '');
        $sku            = trim($_POST['sku'] ?? '');
        $barcode        = trim($_POST['barcode'] ?? '');
        $cat_id         = (int)$_POST['category_id'] ?: null;
        $company_id     = (int)$_POST['company_id']  ?: null;
        $dealer_id      = (int)$_POST['dealer_id']   ?: null;
        $unit           = trim($_POST['unit'] ?? 'pcs');
        $weight_size    = trim($_POST['weight_size'] ?? '');
        $cost_price     = (float)$_POST['cost_price'];
        $sale_price     = (float)$_POST['sale_price'];
        $mrp            = (float)($_POST['mrp'] ?? 0);
        $description    = trim($_POST['description'] ?? '');
        $description_bn = trim($_POST['description_bn'] ?? '');
        $is_active      = isset($_POST['is_active'])       ? 1 : 0;
        $is_published   = isset($_POST['is_published'])    ? 1 : 0;
        $track_expiry   = isset($_POST['track_expiry'])    ? 1 : 0;
        $allow_custom   = isset($_POST['allow_custom_qty'])? 1 : 0;
        $min_order_amt  = (float)($_POST['min_order_amount'] ?? 0);
        $badge          = $_POST['badge'] ?? 'none';
        $mfg_date       = $_POST['mfg_date']    ?: null;
        $expiry_date    = $_POST['expiry_date']  ?: null;

        // Primary image upload
        $image = null;
        if (!empty($_FILES['image']['name'])) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
                $filename = 'prod_'.time().'_'.rand(100,999).'.'.$ext;
                $dest = BASE_PATH.'uploads/products/'.$filename;
                if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0775, true);
                if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) $image = $filename;
            }
        }

        if ($action === 'add') {
            // Auto generate SKU and Barcode backend safety if left empty
            if (empty($sku)) {
                $base_sku = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $name));
                if (!empty($weight_size)) $base_sku .= '-' . strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $weight_size));
                $sku = $base_sku;
                $count = 1;
                while ($pdo->query("SELECT id FROM products WHERE sku=". $pdo->quote($sku))->fetch()) {
                    $sku = $base_sku . '-' . $count++;
                }
            }
            if (empty($barcode)) {
                $barcode = rand(100000000000, 999999999999);
                while ($pdo->query("SELECT id FROM products WHERE barcode=". $pdo->quote($barcode))->fetch()) {
                    $barcode = rand(100000000000, 999999999999);
                }
            }

            try {
                $stmt = $pdo->prepare("INSERT INTO products (name,name_bn,sku,barcode,category_id,company_id,dealer_id,unit,weight_size,cost_price,sale_price,mrp,description,description_bn,image,is_active,is_published,track_expiry,allow_custom_qty,min_order_amount,badge,mfg_date,expiry_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$name,$name_bn,$sku?:null,$barcode?:null,$cat_id,$company_id,$dealer_id,$unit,$weight_size,$cost_price,$sale_price,$mrp,$description,$description_bn,$image,$is_active,$is_published,$track_expiry,$allow_custom,$min_order_amt,$badge,$mfg_date,$expiry_date]);
                $new_id = $pdo->lastInsertId();
                $init_qty = (float)($_POST['initial_qty'] ?? 0);
                $pdo->prepare("INSERT IGNORE INTO inventory (product_id, display_qty) VALUES (?,?)")->execute([$new_id, $init_qty]);
                if ($init_qty > 0)
                    $pdo->prepare("INSERT INTO stock_movements (product_id,type,qty_change,location,notes,user_id) VALUES (?,'opening',?,'display','Initial stock',?)")->execute([$new_id,$init_qty,current_user()['id']]);
                set_flash('success', "Product '$name' added.");
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    set_flash('error', "Cannot add product: SKU or Barcode already exists.");
                } else {
                    set_flash('error', "Database error: " . $e->getMessage());
                }
            }
        } else {
            file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . " - EDIT Triggered for ID: $id\n", FILE_APPEND);
            $old = $pdo->prepare("SELECT cost_price,sale_price FROM products WHERE id=?");
            $old->execute([$id]); $oldp = $old->fetch();
            if ($oldp && ($oldp['cost_price'] != $cost_price || $oldp['sale_price'] != $sale_price))
                $pdo->prepare("INSERT INTO price_history (product_id,old_cost,new_cost,old_sale,new_sale,changed_by) VALUES (?,?,?,?,?,?)")->execute([$id,$oldp['cost_price'],$cost_price,$oldp['sale_price'],$sale_price,current_user()['id']]);

            $sql = "UPDATE products SET name=?,name_bn=?,sku=?,barcode=?,category_id=?,company_id=?,dealer_id=?,unit=?,weight_size=?,cost_price=?,sale_price=?,mrp=?,description=?,description_bn=?,is_active=?,is_published=?,track_expiry=?,allow_custom_qty=?,min_order_amount=?,badge=?,mfg_date=?,expiry_date=?";
            $params = [$name,$name_bn,$sku?:null,$barcode?:null,$cat_id,$company_id,$dealer_id,$unit,$weight_size,$cost_price,$sale_price,$mrp,$description,$description_bn,$is_active,$is_published,$track_expiry,$allow_custom,$min_order_amt,$badge,$mfg_date,$expiry_date];
            if ($image) { $sql .= ",image=?"; $params[] = $image; }
            $sql .= " WHERE id=?"; $params[] = $id;

            try {
                $pdo->prepare($sql)->execute($params);
                set_flash('success', "Product '$name' updated.");
            } catch (PDOException $e) {
                set_flash('error', "Database error: " . $e->getMessage());
            }
        }

        // Handle multiple media uploads
        if (!empty($_FILES['media_files']['name'][0])) {
            $pid = $id ?: $pdo->lastInsertId();
            if ($action === 'add') $pid = $new_id ?? $pid;
            foreach ($_FILES['media_files']['tmp_name'] as $i => $tmp) {
                if (!$tmp || $_FILES['media_files']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $orig = $_FILES['media_files']['name'][$i];
                $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                $type = in_array($ext, ['mp4','webm','mov']) ? 'video' : (in_array($ext, ['gif']) ? 'gif' : 'image');
                if (!in_array($ext, ['jpg','jpeg','png','webp','gif','mp4','webm','mov'])) continue;
                $fname = 'media_'.time().'_'.$i.'_'.rand(100,999).'.'.$ext;
                $dest  = BASE_PATH.'uploads/products/'.$fname;
                if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0775, true);
                if (move_uploaded_file($tmp, $dest)) {
                    $pdo->prepare("INSERT INTO product_media (product_id,file_path,media_type,sort_order) VALUES (?,?,?,?)")->execute([$pid, $fname, $type, $i]);
                }
            }
        }

        header('Location: products.php'); exit;
    }

    if ($action === 'delete_media') {
        $mid = (int)$_POST['media_id'];
        $pid = (int)$_POST['pid'];
        $row = $pdo->prepare("SELECT file_path FROM product_media WHERE id=? AND product_id=?");
        $row->execute([$mid,$pid]); $row = $row->fetch();
        if ($row) {
            @unlink(BASE_PATH.'uploads/products/'.$row['file_path']);
            $pdo->prepare("DELETE FROM product_media WHERE id=?")->execute([$mid]);
        }
        header('Location: products.php?edit='.$pid); exit;
    }

    if ($action === 'toggle_publish') {
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE products SET is_published = NOT is_published WHERE id=?")->execute([$id]);
        header('Location: products.php'); exit;
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE products SET is_active=0, 
            sku = CASE WHEN sku IS NOT NULL THEN CONCAT(sku, '_del_', id) ELSE NULL END,
            barcode = CASE WHEN barcode IS NOT NULL AND barcode != '' THEN CONCAT(barcode, '_del_', id) ELSE barcode END
            WHERE id=?")->execute([$id]);
        set_flash('success', 'Product deactivated.');
        header('Location: products.php'); exit;
    }
}

// ─── Fetch ─────────────────────────────────────────────────────────────────
$search    = trim($_GET['q'] ?? '');
$cat_filter= (int)($_GET['cat'] ?? 0);
$pub_filter= $_GET['pub'] ?? '';
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 20;
$offset    = ($page - 1) * $perPage;

$where = "WHERE p.is_active=1";
$params = [];
if ($search)     { $where .= " AND (p.name LIKE ? OR p.name_bn LIKE ? OR p.barcode LIKE ? OR p.sku LIKE ?)"; $params = [...$params,"%$search%","%$search%","%$search%","%$search%"]; }
if ($cat_filter) { $where .= " AND p.category_id=?"; $params[] = $cat_filter; }
if ($pub_filter === '1') { $where .= " AND p.is_published=1"; }
if ($pub_filter === '0') { $where .= " AND p.is_published=0"; }

$totalRows  = $pdo->prepare("SELECT COUNT(*) FROM products p $where"); $totalRows->execute($params); $totalRows = $totalRows->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

$stmt = $pdo->prepare("SELECT p.*, c.name as cat_name, co.name as company_name, d.name as dealer_name, d.phone as dealer_phone, COALESCE(i.display_qty+i.warehouse_qty,0) as stock FROM products p LEFT JOIN categories c ON c.id=p.category_id LEFT JOIN companies co ON co.id=p.company_id LEFT JOIN dealers d ON d.id=p.dealer_id LEFT JOIN inventory i ON i.product_id=p.id $where ORDER BY p.name ASC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = $pdo->query("SELECT id, name FROM categories WHERE is_active=1 ORDER BY name")->fetchAll();
$companies  = $pdo->query("SELECT id, name FROM companies WHERE is_active=1 ORDER BY name")->fetchAll();
$dealers    = $pdo->query("SELECT id, name FROM dealers WHERE is_active=1 ORDER BY name")->fetchAll();

$edit_product = null;
$edit_media   = [];
if (isset($_GET['edit'])) {
    $ep = $pdo->prepare("SELECT * FROM products WHERE id=?");
    $ep->execute([(int)$_GET['edit']]); $edit_product = $ep->fetch();
    if ($edit_product) {
        $em = $pdo->prepare("SELECT * FROM product_media WHERE product_id=? ORDER BY sort_order");
        $em->execute([$edit_product['id']]); $edit_media = $em->fetchAll();
    }
}

$badge_options = ['none'=>'—','new'=>'🆕 New','popular'=>'🔥 Popular','sale'=>'🏷️ Sale','hot'=>'⚡ Hot'];

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="page-header flex flex-wrap items-center justify-between gap-3">
    <div><h1 class="dark:text-white"><?= __('products') ?></h1><p class="dark:text-gray-400"><?= __('manage_catalogue') ?></p></div>
    <button onclick="document.getElementById('product-modal').classList.remove('hidden')" class="btn btn-primary shadow-lg shadow-brand-500/30" id="add-product-btn">+ <?= __('add_product') ?></button>
</div>

<!-- Search & Filter -->
<form method="get" class="flex flex-wrap gap-2 mb-5 items-center">
    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="<?= __('search_products') ?> (EN/BN)" class="form-control max-w-xs dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200">
    <select name="cat" class="form-control max-w-[160px] dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200" onchange="this.form.submit()">
        <option value=""><?= __('all_categories') ?></option>
        <?php foreach ($categories as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $cat_filter == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="pub" class="form-control max-w-[160px] dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200" onchange="this.form.submit()">
        <option value="">🌐 All</option>
        <option value="1" <?= $pub_filter==='1'?'selected':'' ?>>✅ Published</option>
        <option value="0" <?= $pub_filter==='0'?'selected':'' ?>>🔒 Unpublished</option>
    </select>
    <button type="submit" class="btn btn-secondary"><?= __('filter') ?></button>
    <?php if ($search || $cat_filter || $pub_filter !== ''): ?><a href="products.php" class="btn btn-secondary"><?= __('clear') ?></a><?php endif; ?>
</form>

<!-- Product Table -->
<div class="card dark:bg-gray-800 dark:border-gray-700 p-0 overflow-hidden shadow-xl">
    <div class="px-5 py-3 border-b dark:border-gray-700 flex items-center justify-between bg-gray-50/50 dark:bg-gray-900/40">
        <span class="text-sm text-gray-500 dark:text-gray-400 font-medium"><?= $totalRows ?> <?= __('products_found') ?></span>
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
                    <th class="dark:text-gray-400 text-center">🌐 Web</th>
                    <th class="dark:text-gray-400 text-center">⚖️ Custom Qty</th>
                    <th class="dark:text-gray-400"><?= __('actions') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
            <?php foreach ($products as $p): ?>
            <tr class="dark:hover:bg-gray-700/50 transition-colors">
                <td>
                    <div class="flex items-center gap-3">
                        <?php if ($p['image']): ?>
                        <img src="<?= BASE_URL ?>uploads/products/<?= htmlspecialchars($p['image']) ?>" class="w-10 h-10 rounded-xl object-cover border border-gray-200 dark:border-gray-600 shadow-sm">
                        <?php else: ?>
                        <div class="w-10 h-10 bg-gray-100 dark:bg-gray-700 rounded-xl flex items-center justify-center text-xl">📦</div>
                        <?php endif; ?>
                        <div>
                            <div class="font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-1 flex-wrap">
                                <?= htmlspecialchars($p['name']) ?>
                                <?php if ($p['weight_size']): ?><span class="badge badge-blue text-[9px] py-0"><?= htmlspecialchars($p['weight_size']) ?></span><?php endif; ?>
                                <?php if ($p['badge'] && $p['badge'] !== 'none'): ?>
                                <span class="badge <?= ['new'=>'badge-green','popular'=>'badge-red','sale'=>'badge-yellow','hot'=>'badge-red'][$p['badge']] ?? 'badge-gray' ?> text-[9px]"><?= $badge_options[$p['badge']] ?? '' ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($p['name_bn']): ?>
                            <div class="text-xs text-brand-600 dark:text-brand-400 font-medium"><?= htmlspecialchars($p['name_bn']) ?></div>
                            <?php endif; ?>
                            <div class="text-xs text-gray-400"><?= htmlspecialchars($p['company_name'] ?? '') ?><?= $p['dealer_name'] ? ' · '.$p['dealer_name'] : '' ?></div>
                        </div>
                    </div>
                </td>
                <td class="font-mono text-xs text-gray-500 dark:text-gray-400"><?= htmlspecialchars($p['sku'] ?? '—') ?><br><span class="text-gray-400"><?= htmlspecialchars($p['barcode'] ?? '—') ?></span></td>
                <td class="text-sm dark:text-gray-300"><?= htmlspecialchars($p['cat_name'] ?? '—') ?></td>
                <td class="text-right dark:text-gray-300"><?= CURRENCY.fmt_price($p['cost_price']) ?></td>
                <td class="text-right font-bold dark:text-gray-100"><?= CURRENCY.fmt_price($p['sale_price']) ?></td>
                <td class="text-right">
                    <span class="badge <?= $p['stock'] <= LOW_STOCK_THRESHOLD ? 'badge-yellow' : 'badge-green' ?>">
                        <?= number_format($p['stock'],1) ?> <?= htmlspecialchars($p['unit']) ?>
                    </span>
                </td>
                <td class="text-center">
                    <form method="post" class="inline">
                        <input type="hidden" name="action" value="toggle_publish">
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                        <button type="submit" class="text-xl leading-none transition-transform hover:scale-110" title="Click to toggle website visibility">
                            <?= $p['is_published'] ? '✅' : '⬜' ?>
                        </button>
                    </form>
                </td>
                <td class="text-center">
                    <?= $p['allow_custom_qty'] ? '<span class="badge badge-blue text-[10px]">⚖️ ON</span>' : '<span class="text-gray-300 dark:text-gray-600 text-xs">—</span>' ?>
                </td>
                <td>
                    <div class="flex gap-1">
                        <a href="products.php?edit=<?= $p['id'] ?>" class="btn btn-xs btn-secondary dark:bg-gray-700"><?= __('edit') ?></a>
                        <form method="post" class="inline">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <button type="submit" class="btn btn-xs btn-danger" onclick="return confirm('Deactivate <?= htmlspecialchars($p['name']) ?>?')">✕</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($products)): ?>
            <tr><td colspan="9" class="text-center text-gray-400 py-14"><?= __('no_products_found') ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="px-5 py-3 border-t border-gray-100 dark:border-gray-700 flex items-center gap-2 flex-wrap">
        <?php for ($pg=1; $pg<=$totalPages; $pg++): ?>
        <a href="?page=<?= $pg ?>&q=<?= urlencode($search) ?>&cat=<?= $cat_filter ?>&pub=<?= urlencode($pub_filter) ?>" class="px-3 py-1 rounded-lg text-sm <?= $pg===$page ? 'bg-brand-600 text-white' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700' ?>"><?= $pg ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Add/Edit Modal -->
<div id="product-modal" class="<?= $edit_product ? '' : 'hidden' ?> fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-start justify-center pt-4 overflow-y-auto px-4">
  <div class="bg-white dark:bg-gray-800 rounded-3xl w-full max-w-3xl shadow-2xl my-4 overflow-hidden">
    <div class="flex items-center justify-between p-5 border-b dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
        <h2 class="font-bold text-xl dark:text-white"><?= $edit_product ? '✏️ '.__('edit').' '.__('products') : '✨ '.__('add_product') ?></h2>
        <a href="products.php" class="text-gray-400 hover:text-red-500 text-3xl leading-none">&times;</a>
    </div>
    <form method="post" enctype="multipart/form-data" class="p-6" novalidate>
        <input type="hidden" name="action" value="<?= $edit_product ? 'edit' : 'add' ?>">
        <?php if ($edit_product): ?><input type="hidden" name="id" value="<?= $edit_product['id'] ?>"><?php endif; ?>

        <!-- Tab Navigation inside modal -->
        <div class="flex gap-1 mb-5 border-b dark:border-gray-700 pb-0 overflow-x-auto" id="modal-tabs">
            <?php $tabs = ['basic'=>'📦 Basic Info','bangla'=>'🇧🇩 Bangla','pricing'=>'💰 Pricing','publish'=>'🌐 Publish & Custom','media'=>'🖼️ Media']; ?>
            <?php foreach ($tabs as $k=>$v): ?>
            <button type="button" onclick="showTab('<?= $k ?>')" id="tab-btn-<?= $k ?>" class="tab-modal-btn px-4 py-2 text-xs font-bold rounded-t-lg transition whitespace-nowrap <?= $k==='basic' ? 'bg-brand-600 text-white' : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-gray-400' ?>"><?= $v ?></button>
            <?php endforeach; ?>
        </div>

        <!-- Tab: Basic Info -->
        <div id="tab-basic" class="modal-tab grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <label class="form-label dark:text-gray-300 font-bold">Product Name (English) *</label>
                <input type="text" name="name" required class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= htmlspecialchars($edit_product['name'] ?? '') ?>">
            </div>
            <div>
                <label class="form-label dark:text-gray-300 flex justify-between">
                    SKU 
                    <button type="button" onclick="generateSKU()" class="text-brand-500 hover:text-brand-600 dark:hover:text-brand-400 text-xs font-bold transition">⚡ Auto</button>
                </label>
                <input type="text" name="sku" id="sku-input" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= htmlspecialchars($edit_product['sku'] ?? '') ?>">
            </div>
            <div>
                <label class="form-label dark:text-gray-300 flex justify-between">
                    Barcode
                    <button type="button" onclick="generateBarcode()" class="text-brand-500 hover:text-brand-600 dark:hover:text-brand-400 text-xs font-bold transition">⚡ Auto</button>
                </label>
                <input type="text" name="barcode" id="barcode-input" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= htmlspecialchars($edit_product['barcode'] ?? '') ?>">
            </div>
            <div>
                <label class="form-label dark:text-gray-300"><?= __('categories') ?></label>
                <select name="category_id" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    <option value=""><?= __('select') ?></option>
                    <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" <?= ($edit_product['category_id']??'') == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label dark:text-gray-300"><?= __('company_brand') ?></label>
                <select name="company_id" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    <option value=""><?= __('select') ?></option>
                    <?php foreach ($companies as $c): ?><option value="<?= $c['id'] ?>" <?= ($edit_product['company_id']??'') == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label dark:text-gray-300"><?= __('dealer_supplier') ?></label>
                <select name="dealer_id" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    <option value=""><?= __('select_dealer') ?></option>
                    <?php foreach ($dealers as $d): ?><option value="<?= $d['id'] ?>" <?= ($edit_product['dealer_id']??'') == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label dark:text-gray-300"><?= __('weight_size') ?></label>
                <input type="text" name="weight_size" placeholder="e.g. 5kg, 1L, 500gm" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= htmlspecialchars($edit_product['weight_size'] ?? '') ?>">
            </div>
            <div>
                <label class="form-label dark:text-gray-300"><?= __('unit') ?></label>
                <select name="unit" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    <?php foreach (['pcs','kg','gm','ltr','ml','bag','box','doz','pack','roll'] as $u): ?>
                    <option <?= ($edit_product['unit']??'pcs') === $u ? 'selected' : '' ?>><?= $u ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (!$edit_product): ?>
            <div>
                <label class="form-label font-bold text-brand-700 dark:text-brand-400"><?= __('starting_stock') ?></label>
                <input type="number" step="0.001" name="initial_qty" class="form-control border-brand-200 dark:bg-gray-700 dark:text-white" value="0">
            </div>
            <?php endif; ?>
            <div>
                <label class="form-label dark:text-gray-300">Mfg Date</label>
                <input type="date" name="mfg_date" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= $edit_product['mfg_date'] ?? '' ?>">
            </div>
            <div>
                <label class="form-label dark:text-gray-300">Expiry Date</label>
                <input type="date" name="expiry_date" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= $edit_product['expiry_date'] ?? '' ?>">
            </div>
            <div class="sm:col-span-2">
                <label class="form-label dark:text-gray-300">Description (English)</label>
                <textarea name="description" rows="2" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white"><?= htmlspecialchars($edit_product['description'] ?? '') ?></textarea>
            </div>
            <div class="flex flex-wrap gap-4">
                <label class="flex items-center gap-2 cursor-pointer dark:text-gray-300 font-medium"><input type="checkbox" name="is_active" <?= ($edit_product['is_active'] ?? 1) ? 'checked' : '' ?> class="w-4 h-4 rounded accent-brand-600"> Active</label>
                <label class="flex items-center gap-2 cursor-pointer dark:text-gray-300 font-medium"><input type="checkbox" name="track_expiry" <?= ($edit_product['track_expiry'] ?? 0) ? 'checked' : '' ?> class="w-4 h-4 rounded accent-brand-600"> Track Expiry</label>
            </div>
        </div>

        <!-- Tab: Bangla -->
        <div id="tab-bangla" class="modal-tab hidden grid grid-cols-1 gap-4">
            <div class="bg-brand-50 dark:bg-brand-900/20 border border-brand-100 dark:border-brand-800 rounded-2xl p-4 text-sm text-brand-700 dark:text-brand-300">
                🇧🇩 এই ফিল্ডগুলো পূরণ করুন যাতে website-এ বাংলায় দেখায়।
            </div>
            <div>
                <label class="form-label dark:text-gray-300 font-bold">পণ্যের নাম (বাংলায়)</label>
                <input type="text" name="name_bn" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white text-lg" placeholder="যেমন: আটা, লবণ, সরিষার তেল" value="<?= htmlspecialchars($edit_product['name_bn'] ?? '') ?>">
            </div>
            <div>
                <label class="form-label dark:text-gray-300 font-bold">বিবরণ (বাংলায়)</label>
                <textarea name="description_bn" rows="4" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="পণ্যের বিবরণ বাংলায় লিখুন..."><?= htmlspecialchars($edit_product['description_bn'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- Tab: Pricing -->
        <div id="tab-pricing" class="modal-tab hidden grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="form-label dark:text-gray-300 font-bold"><?= __('cost_price') ?> (<?= CURRENCY ?>)</label>
                <input type="number" step="0.01" name="cost_price" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= $edit_product['cost_price'] ?? '0' ?>">
            </div>
            <div>
                <label class="form-label dark:text-gray-300 font-bold"><?= __('sale_price') ?> (<?= CURRENCY ?>) *</label>
                <input type="number" step="0.01" name="sale_price" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= $edit_product['sale_price'] ?? '0' ?>">
            </div>
            <div>
                <label class="form-label dark:text-gray-300 font-bold"><?= __('mrp') ?> (<?= CURRENCY ?>)</label>
                <input type="number" step="0.01" name="mrp" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= $edit_product['mrp'] ?? '0' ?>">
            </div>
        </div>

        <!-- Tab: Publish & Custom -->
        <div id="tab-publish" class="modal-tab hidden space-y-5">
            <!-- Website Badge -->
            <div class="bg-gray-50 dark:bg-gray-700/30 rounded-2xl p-5 border dark:border-gray-600">
                <h4 class="font-bold dark:text-white mb-4">🏷️ Product Badge</h4>
                <div class="flex flex-wrap gap-3">
                    <?php foreach ($badge_options as $bk => $bv): ?>
                    <label class="flex items-center gap-2 cursor-pointer px-3 py-2 rounded-xl border-2 transition <?= ($edit_product['badge'] ?? 'none') === $bk ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/20' : 'border-gray-200 dark:border-gray-600 hover:border-brand-300' ?>">
                        <input type="radio" name="badge" value="<?= $bk ?>" <?= ($edit_product['badge'] ?? 'none') === $bk ? 'checked' : '' ?> class="accent-brand-600">
                        <span class="text-sm font-semibold dark:text-white"><?= $bv ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Website Publish -->
            <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-2xl p-5">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="is_published" id="chk-publish" <?= ($edit_product['is_published'] ?? 0) ? 'checked' : '' ?> class="w-5 h-5 rounded accent-emerald-600">
                    <div>
                        <div class="font-bold text-emerald-800 dark:text-emerald-300">🌐 Website-এ Publish করুন</div>
                        <div class="text-xs text-emerald-600 dark:text-emerald-400">Tick করলে customer website-এ এই product দেখা যাবে।</div>
                    </div>
                </label>
            </div>

            <!-- Custom Qty -->
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-2xl p-5">
                <label class="flex items-center gap-3 cursor-pointer mb-3">
                    <input type="checkbox" name="allow_custom_qty" id="chk-custom" <?= ($edit_product['allow_custom_qty'] ?? 0) ? 'checked' : '' ?> class="w-5 h-5 rounded accent-blue-600" onchange="toggleMinOrder()">
                    <div>
                        <div class="font-bold text-blue-800 dark:text-blue-300">⚖️ ভাঙ্গা/পরিমাণ বিক্রি চালু করুন</div>
                        <div class="text-xs text-blue-600 dark:text-blue-400">Customer টাকার পরিমাণ দিয়ে অর্ডার করতে পারবে (যেমন: ১০০৳ এর চিনি, ৫০৳ এর তেল)।</div>
                    </div>
                </label>
                <div id="div-min-order" class="<?= ($edit_product['allow_custom_qty'] ?? 0) ? '' : 'hidden' ?> mt-3">
                    <label class="form-label dark:text-gray-300">সর্বনিম্ন অর্ডার পরিমাণ (<?= CURRENCY ?> টাকায়) — 0 = কোনো সীমা নেই</label>
                    <div class="flex items-center gap-2 max-w-xs">
                        <span class="text-xl font-bold text-emerald-600"><?= CURRENCY ?></span>
                        <input type="number" step="1" min="0" name="min_order_amount" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= price_ceil((float)($edit_product['min_order_amount'] ?? 0)) ?>" placeholder="0">
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1">উদাহরণ: 50 দিলে customer কমপক্ষে ৳৫০ এর অর্ডার করতে পারবে।</p>
                </div>
            </div>
        </div>

        <!-- Tab: Media -->
        <div id="tab-media" class="modal-tab hidden space-y-5">
            <!-- Primary Image -->
            <div class="bg-gray-50 dark:bg-gray-700/30 rounded-2xl p-4 border dark:border-gray-600">
                <label class="form-label dark:text-gray-300 font-bold mb-2">Primary Image</label>
                <?php if ($edit_product && $edit_product['image']): ?>
                <img src="<?= BASE_URL ?>uploads/products/<?= htmlspecialchars($edit_product['image']) ?>" class="w-24 h-24 rounded-xl object-cover mb-3 border-2 border-brand-200">
                <?php endif; ?>
                <input type="file" name="image" accept="image/*" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white">
            </div>

            <!-- Multiple Media -->
            <div class="bg-gray-50 dark:bg-gray-700/30 rounded-2xl p-4 border dark:border-gray-600">
                <label class="form-label dark:text-gray-300 font-bold mb-2">Additional Media (Images / GIF / Video)</label>
                <p class="text-xs text-gray-400 mb-3">Multiple files select করতে পারবেন। Supported: jpg, png, webp, gif, mp4, webm</p>
                <input type="file" name="media_files[]" accept="image/*,video/*,.gif" multiple class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white">
            </div>

            <!-- Existing Media Gallery -->
            <?php if ($edit_media): ?>
            <div class="bg-gray-50 dark:bg-gray-700/30 rounded-2xl p-4 border dark:border-gray-600">
                <h4 class="font-bold dark:text-white mb-3">📸 Current Media (<?= count($edit_media) ?> files)</h4>
                <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-3">
                    <?php foreach ($edit_media as $m): ?>
                    <div class="relative group">
                        <?php if ($m['media_type'] === 'video'): ?>
                        <video src="<?= BASE_URL ?>uploads/products/<?= htmlspecialchars($m['file_path']) ?>" class="w-full aspect-square object-cover rounded-lg border dark:border-gray-600" muted></video>
                        <?php else: ?>
                        <img src="<?= BASE_URL ?>uploads/products/<?= htmlspecialchars($m['file_path']) ?>" class="w-full aspect-square object-cover rounded-lg border dark:border-gray-600">
                        <?php endif; ?>
                        <form method="post" class="absolute top-1 right-1 opacity-0 group-hover:opacity-100 transition">
                            <input type="hidden" name="action" value="delete_media">
                            <input type="hidden" name="media_id" value="<?= $m['id'] ?>">
                            <input type="hidden" name="pid" value="<?= $edit_product['id'] ?>">
                            <button type="submit" class="w-6 h-6 bg-red-600 text-white rounded-full text-xs flex items-center justify-center" onclick="return confirm('Delete?')">&times;</button>
                        </form>
                        <div class="absolute bottom-0 left-0 right-0 text-center">
                            <span class="text-[9px] bg-black/60 text-white px-1 rounded"><?= strtoupper($m['media_type']) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Submit -->
        <div class="flex justify-end gap-3 pt-6 mt-6 border-t dark:border-gray-700">
            <a href="products.php" class="btn btn-secondary dark:bg-gray-700 dark:text-gray-200"><?= __('cancel') ?></a>
            <button type="submit" class="btn btn-primary px-8 shadow-lg shadow-brand-500/30"><?= $edit_product ? '💾 '.__('update_product') : '✨ '.__('add_product') ?></button>
        </div>
    </form>
  </div>
</div>

<script>
function generateSKU() {
    let name = document.querySelector('input[name="name"]').value;
    let size = document.querySelector('input[name="weight_size"]').value;
    if (!name) return;
    
    let sku = name.substring(0, 12).replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
    if (size) sku += '-' + size.replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
    
    document.getElementById('sku-input').value = sku;
    document.getElementById('sku-input').classList.add('ring-2', 'ring-brand-500');
    setTimeout(() => document.getElementById('sku-input').classList.remove('ring-2', 'ring-brand-500'), 500);
}

function generateBarcode() {
    let bc = Math.floor(100000000000 + Math.random() * 900000000000).toString();
    document.getElementById('barcode-input').value = bc;
    document.getElementById('barcode-input').classList.add('ring-2', 'ring-brand-500');
    setTimeout(() => document.getElementById('barcode-input').classList.remove('ring-2', 'ring-brand-500'), 500);
}

function showTab(name) {
    document.querySelectorAll('.modal-tab').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.tab-modal-btn').forEach(el => {
        el.classList.remove('bg-brand-600','text-white');
        el.classList.add('text-gray-500','dark:text-gray-400');
    });
    document.getElementById('tab-'+name).classList.remove('hidden');
    const btn = document.getElementById('tab-btn-'+name);
    btn.classList.add('bg-brand-600','text-white');
    btn.classList.remove('text-gray-500','dark:text-gray-400');
}

function toggleMinOrder() {
    const chk = document.getElementById('chk-custom');
    const div = document.getElementById('div-min-order');
    div.classList.toggle('hidden', !chk.checked);
}
</script>

<?php require_once 'includes/footer.php'; ?>
