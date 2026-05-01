<?php
// coupons.php — Create and manage discount coupon codes
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/flash.php';
require_once 'includes/features.php';
require_login();
$page_title = 'Coupons';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id          = (int)($_POST['id'] ?? 0);
        $code        = strtoupper(trim($_POST['code'] ?? ''));
        $type        = $_POST['type'] ?? 'percent';
        $value       = (float)$_POST['value'];
        $min_order   = (float)($_POST['min_order'] ?? 0);
        $max_disc    = (float)($_POST['max_discount'] ?? 0);
        $usage_limit = (int)($_POST['usage_limit'] ?? 0);
        $expires_at  = $_POST['expires_at'] ?: null;
        $is_active   = isset($_POST['is_active']) ? 1 : 0;

        if ($code && $value > 0) {
            if ($id) {
                $pdo->prepare("UPDATE coupons SET code=?,type=?,value=?,min_order=?,max_discount=?,usage_limit=?,expires_at=?,is_active=? WHERE id=?")
                    ->execute([$code,$type,$value,$min_order,$max_disc,$usage_limit,$expires_at,$is_active,$id]);
                set_flash('success', "Coupon '$code' updated.");
            } else {
                try {
                    $pdo->prepare("INSERT INTO coupons (code,type,value,min_order,max_discount,usage_limit,expires_at,is_active) VALUES (?,?,?,?,?,?,?,?)")
                        ->execute([$code,$type,$value,$min_order,$max_disc,$usage_limit,$expires_at,$is_active]);
                    set_flash('success', "Coupon '$code' created!");
                } catch (PDOException $e) {
                    set_flash('error', "Coupon code already exists.");
                }
            }
        }
        header('Location: coupons.php'); exit;
    }

    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM coupons WHERE id=?")->execute([(int)$_POST['id']]);
        set_flash('success', 'Coupon deleted.');
        header('Location: coupons.php'); exit;
    }

    if ($action === 'toggle') {
        $pdo->prepare("UPDATE coupons SET is_active = NOT is_active WHERE id=?")->execute([(int)$_POST['id']]);
        header('Location: coupons.php'); exit;
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $edit = $pdo->prepare("SELECT * FROM coupons WHERE id=?");
    $edit->execute([(int)$_GET['edit']]); $edit = $edit->fetch();
}

$coupons = $pdo->query("SELECT * FROM coupons ORDER BY created_at DESC")->fetchAll();

// Stats
$total_savings = $pdo->query("SELECT COALESCE(SUM(discount_amount),0) FROM sales WHERE coupon_id IS NOT NULL AND discount_amount > 0")->fetchColumn();
$active_count  = count(array_filter($coupons, fn($c) => $c['is_active']));

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="page-header flex items-center justify-between">
    <div>
        <h1 class="dark:text-white">🎟️ Coupons & Discounts</h1>
        <p class="dark:text-gray-400">Create and manage coupon codes for customer promotions</p>
    </div>
    <a href="coupons.php?new=1" class="btn btn-primary shadow-lg shadow-brand-500/30">+ New Coupon</a>
</div>

<!-- Stats Row -->
<div class="grid grid-cols-3 gap-4 mb-6">
    <div class="stat-card dark:bg-gray-800 dark:border-gray-700 text-center border-t-4 border-brand-500">
        <div class="text-xs text-gray-400 uppercase tracking-wider mb-1">Total Coupons</div>
        <div class="text-3xl font-black dark:text-white"><?= count($coupons) ?></div>
    </div>
    <div class="stat-card dark:bg-gray-800 dark:border-gray-700 text-center border-t-4 border-emerald-500">
        <div class="text-xs text-gray-400 uppercase tracking-wider mb-1">Active</div>
        <div class="text-3xl font-black text-emerald-600 dark:text-emerald-400"><?= $active_count ?></div>
    </div>
    <div class="stat-card dark:bg-gray-800 dark:border-gray-700 text-center border-t-4 border-purple-500">
        <div class="text-xs text-gray-400 uppercase tracking-wider mb-1">Total Discounts Given</div>
        <div class="text-3xl font-black text-purple-600 dark:text-purple-400"><?= CURRENCY . number_format($total_savings, 2) ?></div>
    </div>
</div>

<!-- Coupon List -->
<div class="card dark:bg-gray-800 dark:border-gray-700 p-0 overflow-hidden shadow-xl">
    <div class="overflow-x-auto">
        <table class="data-table dark:text-gray-300">
            <thead class="dark:bg-gray-900/50">
                <tr>
                    <th class="dark:text-gray-400">Code</th>
                    <th class="dark:text-gray-400">Type</th>
                    <th class="text-right dark:text-gray-400">Value</th>
                    <th class="dark:text-gray-400">Min Order</th>
                    <th class="dark:text-gray-400">Expires</th>
                    <th class="dark:text-gray-400">Usage</th>
                    <th class="dark:text-gray-400">Status</th>
                    <th class="dark:text-gray-400">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y dark:divide-gray-700">
            <?php foreach ($coupons as $c): ?>
            <tr class="dark:hover:bg-gray-700/50 transition-colors">
                <td>
                    <span class="font-mono font-black text-brand-600 dark:text-brand-400 text-base tracking-widest bg-brand-50 dark:bg-brand-900/20 px-3 py-1 rounded-lg">
                        <?= htmlspecialchars($c['code']) ?>
                    </span>
                </td>
                <td>
                    <span class="badge <?= $c['type']==='percent' ? 'badge-blue' : 'badge-green' ?>">
                        <?= $c['type'] === 'percent' ? '% Percent' : '৳ Fixed' ?>
                    </span>
                </td>
                <td class="text-right font-bold dark:text-white">
                    <?= $c['type'] === 'percent' ? $c['value'].'%' : CURRENCY.number_format($c['value'],2) ?>
                    <?php if ($c['max_discount'] > 0): ?>
                    <div class="text-xs text-gray-400">Max: <?= CURRENCY.number_format($c['max_discount'],2) ?></div>
                    <?php endif; ?>
                </td>
                <td class="text-sm dark:text-gray-300"><?= $c['min_order'] > 0 ? CURRENCY.number_format($c['min_order'],2) : '—' ?></td>
                <td class="text-sm <?= $c['expires_at'] && $c['expires_at'] < date('Y-m-d') ? 'text-red-500 font-bold' : 'dark:text-gray-300' ?>">
                    <?= $c['expires_at'] ? date('d M Y', strtotime($c['expires_at'])) : '♾️ Never' ?>
                </td>
                <td class="text-center">
                    <span class="font-bold dark:text-white"><?= $c['used_count'] ?></span>
                    <span class="text-xs text-gray-400">/ <?= $c['usage_limit'] ?: '∞' ?></span>
                </td>
                <td>
                    <form method="post" class="inline">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                        <button type="submit" class="badge <?= $c['is_active'] ? 'badge-green' : 'badge-red' ?> cursor-pointer border-0">
                            <?= $c['is_active'] ? '✅ Active' : '❌ Off' ?>
                        </button>
                    </form>
                </td>
                <td>
                    <div class="flex gap-1">
                        <a href="coupons.php?edit=<?= $c['id'] ?>" class="btn btn-xs btn-secondary dark:bg-gray-700">Edit</a>
                        <form method="post" class="inline">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <button onclick="return confirm('Delete this coupon?')" class="btn btn-xs btn-danger">✕</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($coupons)): ?>
            <tr><td colspan="8" class="text-center text-gray-400 py-12">No coupons yet. Create your first coupon!</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Modal -->
<div id="coupon-modal" class="<?= ($edit || isset($_GET['new'])) ? '' : 'hidden' ?> fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center px-4">
    <div class="bg-white dark:bg-gray-800 rounded-3xl w-full max-w-lg shadow-2xl overflow-hidden">
        <div class="flex items-center justify-between p-5 border-b dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
            <h2 class="font-bold text-xl dark:text-white">🎟️ <?= $edit ? 'Edit' : 'New' ?> Coupon</h2>
            <a href="coupons.php" class="text-gray-400 hover:text-red-500 text-3xl">&times;</a>
        </div>
        <form method="post" class="p-6 space-y-4">
            <input type="hidden" name="action" value="save">
            <?php if ($edit): ?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><?php endif; ?>

            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="form-label dark:text-gray-300 font-bold uppercase text-xs tracking-widest">Coupon Code *</label>
                    <input type="text" name="code" required class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white font-mono font-black text-lg uppercase tracking-widest" placeholder="e.g. SAVE20" value="<?= htmlspecialchars($edit['code'] ?? '') ?>" oninput="this.value=this.value.toUpperCase()">
                </div>
                <div>
                    <label class="form-label dark:text-gray-300 font-bold uppercase text-xs tracking-widest">Discount Type *</label>
                    <select name="type" id="coup-type" onchange="updateValueLabel()" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        <option value="percent" <?= ($edit['type']??'percent')==='percent'?'selected':'' ?>>% Percentage</option>
                        <option value="fixed" <?= ($edit['type']??'')==='fixed'?'selected':'' ?>>৳ Fixed Amount</option>
                    </select>
                </div>
                <div>
                    <label class="form-label dark:text-gray-300 font-bold uppercase text-xs tracking-widest" id="val-label">Discount Value *</label>
                    <input type="number" step="0.01" name="value" required class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="e.g. 10" value="<?= $edit['value'] ?? '' ?>">
                </div>
                <div>
                    <label class="form-label dark:text-gray-300 font-bold uppercase text-xs tracking-widest">Min Order (<?= CURRENCY ?>)</label>
                    <input type="number" step="0.01" name="min_order" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="0 = no limit" value="<?= $edit['min_order'] ?? '0' ?>">
                </div>
                <div>
                    <label class="form-label dark:text-gray-300 font-bold uppercase text-xs tracking-widest">Max Discount (<?= CURRENCY ?>)</label>
                    <input type="number" step="0.01" name="max_discount" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="0 = no cap" value="<?= $edit['max_discount'] ?? '0' ?>">
                </div>
                <div>
                    <label class="form-label dark:text-gray-300 font-bold uppercase text-xs tracking-widest">Usage Limit</label>
                    <input type="number" name="usage_limit" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="0 = unlimited" value="<?= $edit['usage_limit'] ?? '0' ?>">
                </div>
                <div>
                    <label class="form-label dark:text-gray-300 font-bold uppercase text-xs tracking-widest">Expires On</label>
                    <input type="date" name="expires_at" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" value="<?= $edit['expires_at'] ?? '' ?>">
                </div>
                <div class="col-span-2">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="is_active" <?= ($edit['is_active'] ?? 1) ? 'checked' : '' ?> class="w-5 h-5 rounded accent-brand-600">
                        <span class="font-bold dark:text-white">Active (customers can use this coupon)</span>
                    </label>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t dark:border-gray-700">
                <a href="coupons.php" class="btn btn-secondary dark:bg-gray-700 dark:text-gray-200">Cancel</a>
                <button type="submit" class="btn btn-primary shadow-lg shadow-brand-500/30">💾 Save Coupon</button>
            </div>
        </form>
    </div>
</div>

<script>
function updateValueLabel() {
    const type = document.getElementById('coup-type').value;
    document.getElementById('val-label').textContent = type === 'percent' ? 'Discount % *' : 'Fixed Amount (৳) *';
}
<?php if ($edit || isset($_GET['new'])): ?>
document.addEventListener('DOMContentLoaded', () => { updateValueLabel(); });
<?php endif; ?>
</script>

<?php require_once 'includes/footer.php'; ?>
