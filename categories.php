<?php
// categories.php — Category Management CRUD
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/flash.php';
require_login();

$page_title = 'Categories';

// --- Handle POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);
    $name   = trim($_POST['name'] ?? '');
    $slug   = trim($_POST['slug'] ?? '') ?: strtolower(str_replace(' ', '-', $name));
    $icon   = trim($_POST['icon'] ?? '🏷️');
    $sort   = (int)($_POST['sort_order'] ?? 0);
    $active = isset($_POST['is_active']) ? 1 : 0;

    // Image upload
    $image = null;
    if (!empty($_FILES['cat_image']['name'])) {
        $ext = strtolower(pathinfo($_FILES['cat_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','webp','gif'];
        if (in_array($ext, $allowed)) {
            $filename = 'cat_' . time() . '_' . rand(100,999) . '.' . $ext;
            $dest = BASE_PATH . 'uploads/categories/' . $filename;
            if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0775, true);
            if (move_uploaded_file($_FILES['cat_image']['tmp_name'], $dest)) {
                $image = $filename;
            }
        }
    }

    if ($action === 'add') {
        try {
            $stmt = $pdo->prepare("INSERT INTO categories (name, slug, icon, image, sort_order, is_active) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$name, $slug, $icon, $image, $sort, 1]);
            set_flash('success', "Category '$name' added successfully.");
        } catch (PDOException $e) {
            set_flash('danger', "Error: Slug must be unique or " . $e->getMessage());
        }
        header('Location: categories.php'); exit;
    }

    if ($action === 'edit') {
        try {
            $sql = "UPDATE categories SET name=?, slug=?, icon=?, sort_order=?, is_active=?";
            $params = [$name, $slug, $icon, $sort, $active];
            if ($image) {
                $sql .= ", image=?";
                $params[] = $image;
            }
            $sql .= " WHERE id=?";
            $params[] = $id;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            set_flash('success', "Category updated.");
        } catch (PDOException $e) {
            set_flash('danger', "Error updating category: " . $e->getMessage());
        }
        header('Location: categories.php'); exit;
    }

    if ($action === 'delete') {
        $pdo->prepare("UPDATE categories SET is_active=0 WHERE id=?")->execute([$id]);
        set_flash('success', "Category deactivated.");
        header('Location: categories.php'); exit;
    }
}

// --- Fetch Data ---
$categories = $pdo->query("
    SELECT c.*, COUNT(p.id) as product_count 
    FROM categories c 
    LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1
    GROUP BY c.id 
    ORDER BY c.sort_order ASC, c.name ASC
")->fetchAll();

$edit_cat = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $edit_cat = $stmt->fetch();
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="page-header flex items-center justify-between">
    <div>
        <h1 class="dark:text-white"><?= __('categories') ?></h1>
        <p class="dark:text-gray-400"><?= __('organize_categories') ?></p>
    </div>
    <button onclick="document.getElementById('cat-modal').classList.remove('hidden')" class="btn btn-primary">+ <?= __('add_category') ?></button>
</div>

<div class="card dark:bg-gray-800 dark:border-gray-700 p-0 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="data-table dark:text-gray-300">
            <thead class="dark:bg-gray-900/50">
                <tr>
                    <th class="dark:text-gray-400"><?= __('icon_image') ?></th>
                    <th class="dark:text-gray-400"><?= __('name') ?></th>
                    <th class="dark:text-gray-400"><?= __('slug') ?></th>
                    <th class="dark:text-gray-400"><?= __('sort') ?></th>
                    <th class="dark:text-gray-400"><?= __('products') ?></th>
                    <th class="dark:text-gray-400"><?= __('status') ?></th>
                    <th class="dark:text-gray-400"><?= __('actions') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
                <?php foreach ($categories as $c): ?>
                <tr class="dark:hover:bg-gray-700/50">
                    <td>
                        <div class="flex items-center gap-2">
                            <?php if ($c['image']): ?>
                            <img src="<?= BASE_URL ?>uploads/categories/<?= htmlspecialchars($c['image']) ?>" class="w-8 h-8 rounded object-cover border dark:border-gray-700">
                            <?php else: ?>
                            <span class="text-xl"><?= htmlspecialchars($c['icon']) ?></span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="font-medium text-gray-900 dark:text-gray-100"><?= htmlspecialchars($c['name']) ?></td>
                    <td class="font-mono text-xs text-gray-400 dark:text-gray-500"><?= htmlspecialchars($c['slug']) ?></td>
                    <td class="dark:text-gray-400"><?= $c['sort_order'] ?></td>
                    <td><span class="badge badge-gray dark:bg-gray-700 dark:text-gray-300"><?= $c['product_count'] ?> <?= __('products') ?></span></td>
                    <td>
                        <span class="badge <?= $c['is_active'] ? 'badge-green' : 'badge-red' ?>">
                            <?= $c['is_active'] ? __('active') : __('hidden') ?>
                        </span>
                    </td>
                    <td>
                        <div class="flex gap-1">
                            <a href="categories.php?edit=<?= $c['id'] ?>" class="btn btn-xs btn-secondary dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600"><?= __('edit') ?></a>
                            <form method="post" class="inline">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-danger" onclick="return confirm('<?= __('confirm_delete') ?>')"><?= __('del') ?></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($categories)): ?>
                <tr><td colspan="7" class="text-center text-gray-400 py-10"><?= __('no_products_found') ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Modal -->
<div id="cat-modal" class="<?= $edit_cat ? '' : 'hidden' ?> fixed inset-0 bg-black/50 z-50 flex items-center justify-center px-4">
    <div class="bg-white dark:bg-gray-800 rounded-2xl w-full max-w-sm shadow-2xl">
        <div class="flex items-center justify-between p-5 border-b border-gray-100 dark:border-gray-700">
            <h2 class="font-bold text-lg dark:text-white"><?= $edit_cat ? __('edit_category') : __('add_new_category') ?></h2>
            <a href="categories.php" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</a>
        </div>
        <form method="post" enctype="multipart/form-data" class="p-5 space-y-4">
            <input type="hidden" name="action" value="<?= $edit_cat ? 'edit' : 'add' ?>">
            <?php if ($edit_cat): ?><input type="hidden" name="id" value="<?= $edit_cat['id'] ?>"><?php endif; ?>
            
            <div>
                <label class="form-label dark:text-gray-300"><?= __('category_name') ?> *</label>
                <input type="text" name="name" required class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="<?= __('category_placeholder') ?>" value="<?= htmlspecialchars($edit_cat['name'] ?? '') ?>">
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label dark:text-gray-300"><?= __('icon_emoji') ?></label>
                    <div class="flex gap-2">
                        <input type="text" name="icon" id="cat_icon" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= htmlspecialchars($edit_cat['icon'] ?? '🏷️') ?>">
                        <button type="button" onclick="toggleEmojiPicker()" class="btn btn-secondary px-2 dark:bg-gray-700 dark:border-gray-600">😀</button>
                    </div>
                    <div id="emoji-picker" class="hidden absolute bg-white dark:bg-gray-700 border dark:border-gray-600 shadow-xl p-2 rounded-lg grid grid-cols-5 gap-1 z-50 mt-1">
                        <?php foreach(['🍎','🥦','🧴','🧼','🍪','🥩','🧊','🍼','🍟','🧹','🍷','🍞','🍬','🍚','🔋'] as $e): ?>
                            <button type="button" onclick="setEmoji('<?= $e ?>')" class="hover:bg-gray-100 dark:hover:bg-gray-600 p-1 rounded"><?= $e ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div>
                    <label class="form-label dark:text-gray-300"><?= __('sort_order') ?></label>
                    <input type="number" name="sort_order" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= $edit_cat['sort_order'] ?? '0' ?>">
                </div>
            </div>

            <div>
                <label class="form-label dark:text-gray-300"><?= __('category_image') ?></label>
                <input type="file" name="cat_image" accept="image/*" class="form-control p-1 text-xs dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                <?php if($edit_cat && $edit_cat['image']): ?>
                    <div class="mt-2 flex items-center gap-2">
                        <img src="<?= BASE_URL ?>uploads/categories/<?= $edit_cat['image'] ?>" class="w-10 h-10 rounded border dark:border-gray-600">
                        <span class="text-xs text-gray-400"><?= __('current_image') ?></span>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="hidden">
                 <label class="form-label"><?= __('slug') ?></label>
                 <input type="text" name="slug" class="form-control" value="<?= htmlspecialchars($edit_cat['slug'] ?? '') ?>">
            </div>
            
            <?php if ($edit_cat): ?>
            <label class="flex items-center gap-2 cursor-pointer dark:text-gray-300">
                <input type="checkbox" name="is_active" <?= $edit_cat['is_active'] ? 'checked' : '' ?>> <?= __('active') ?>
            </label>
            <?php endif; ?>

            <div class="flex justify-end gap-3 pt-2">
                <a href="categories.php" class="btn btn-secondary dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600"><?= __('cancel') ?></a>
                <button type="submit" class="btn btn-primary"><?= __('save_category') ?></button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleEmojiPicker() {
    document.getElementById('emoji-picker').classList.toggle('hidden');
}
function setEmoji(emoji) {
    document.getElementById('cat_icon').value = emoji;
    document.getElementById('emoji-picker').classList.add('hidden');
}
// Close picker when clicking outside
document.addEventListener('click', function(e) {
    const picker = document.getElementById('emoji-picker');
    const btn = e.target.closest('button');
    if (picker && !picker.contains(e.target) && (!btn || btn.innerHTML !== '😀')) {
        picker.classList.add('hidden');
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
