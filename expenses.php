<?php
// expenses.php — Track shop expenses
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/flash.php';
require_login();
$page_title = 'Expenses';

// Handle Add/Edit/Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id         = (int)($_POST['id'] ?? 0);
        $category   = trim($_POST['category']);
        $amount     = (float)$_POST['amount'];
        $date       = $_POST['expense_date'] ?: date('Y-m-d');
        $notes      = trim($_POST['notes']);

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE expenses SET category=?, amount=?, expense_date=?, notes=? WHERE id=?");
            $stmt->execute([$category, $amount, $date, $notes, $id]);
            set_flash('success', "Expense updated successfully.");
        } else {
            $stmt = $pdo->prepare("INSERT INTO expenses (category, amount, expense_date, notes, user_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$category, $amount, $date, $notes, current_user()['id']]);
            set_flash('success', "Expense recorded.");
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM expenses WHERE id=?")->execute([$id]);
        set_flash('success', "Expense record deleted.");
    }
    header('Location: expenses.php'); exit;
}

// Filters
$date_from = $_GET['from'] ?? date('Y-m-01');
$date_to   = $_GET['to']   ?? date('Y-m-d');

$expenses = $pdo->prepare("SELECT e.*, u.name as user_name FROM expenses e LEFT JOIN users u ON u.id = e.user_id WHERE e.expense_date BETWEEN ? AND ? ORDER BY e.expense_date DESC");
$expenses->execute([$date_from, $date_to]);
$expenses = $expenses->fetchAll();

$totalExpense = array_sum(array_column($expenses, 'amount'));

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="page-header flex items-center justify-between">
    <div>
        <h1 class="dark:text-white"><?= __('expenses') ?></h1>
        <p class="dark:text-gray-400"><?= __('manage_shop_expenses') ?></p>
    </div>
    <button onclick="openExpenseModal()" class="btn btn-primary" id="new-expense-btn">+ <?= __('add_expense') ?></button>
</div>

<!-- Stats Card -->
<div class="stat-card mb-5 flex items-center justify-between dark:bg-gray-800 dark:border-gray-700">
    <div>
        <div class="text-xs text-gray-400 mb-1"><?= __('total_expenses_period') ?></div>
        <div class="text-2xl font-bold text-red-500"><?= CURRENCY . number_format($totalExpense, 2) ?></div>
    </div>
    <div class="text-3xl">💸</div>
</div>

<!-- Filters -->
<form method="get" class="flex flex-wrap gap-3 mb-5 no-print items-end">
    <div>
        <label class="form-label text-xs dark:text-gray-400"><?= __('from') ?></label>
        <input type="date" name="from" value="<?= $date_from ?>" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white">
    </div>
    <div>
        <label class="form-label text-xs dark:text-gray-400"><?= __('to') ?></label>
        <input type="date" name="to" value="<?= $date_to ?>" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white">
    </div>
    <button type="submit" class="btn btn-secondary dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600"><?= __('apply') ?></button>
</form>

<div class="card dark:bg-gray-800 dark:border-gray-700 p-0 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="data-table dark:text-gray-300">
            <thead class="dark:bg-gray-900/50">
                <tr>
                    <th class="dark:text-gray-400"><?= __('date') ?></th>
                    <th class="dark:text-gray-400"><?= __('category') ?></th>
                    <th class="dark:text-gray-400"><?= __('notes') ?></th>
                    <th class="dark:text-gray-400"><?= __('recorded_by') ?></th>
                    <th class="text-right dark:text-gray-400"><?= __('amount') ?></th>
                    <th class="dark:text-gray-400"><?= __('actions') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
                <?php foreach ($expenses as $e): ?>
                <tr class="dark:hover:bg-gray-700/50">
                    <td class="text-sm dark:text-gray-400"><?= $e['expense_date'] ?></td>
                    <td class="font-medium dark:text-gray-100"><?= htmlspecialchars($e['category']) ?></td>
                    <td class="text-sm text-gray-500 dark:text-gray-500"><?= htmlspecialchars($e['notes']) ?></td>
                    <td class="text-xs text-gray-400 uppercase"><?= htmlspecialchars($e['user_name'] ?? 'System') ?></td>
                    <td class="text-right font-bold text-red-600 dark:text-red-400"><?= CURRENCY . number_format($e['amount'], 2) ?></td>
                    <td>
                        <div class="flex gap-2">
                            <button onclick="editExpense(<?= htmlspecialchars(json_encode($e)) ?>)" class="text-brand-600 hover:text-brand-800">✏️</button>
                            <form method="post" class="inline" onsubmit="return confirm('<?= __('confirm_delete') ?>')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                <button type="submit" class="text-red-500 hover:text-red-700">🗑️</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($expenses)): ?>
                <tr><td colspan="6" class="text-center text-gray-400 py-8"><?= __('no_records_found') ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div id="expense-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-2xl w-full max-w-md shadow-2xl overflow-hidden">
        <div class="flex items-center justify-between p-5 border-b dark:border-gray-700">
            <h2 id="modal-title" class="font-bold text-lg dark:text-white"><?= __('add_expense') ?></h2>
            <button onclick="document.getElementById('expense-modal').classList.add('hidden')" class="text-gray-400 text-2xl">&times;</button>
        </div>
        <form method="post" class="p-5 space-y-4">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="ex-id" value="0">
            
            <div>
                <label class="form-label dark:text-gray-300"><?= __('category') ?> *</label>
                <select name="category" id="ex-category" required class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    <option value="Shop Rent"><?= __('rent') ?></option>
                    <option value="Electricity"><?= __('electricity') ?></option>
                    <option value="Staff Salary"><?= __('salary') ?></option>
                    <option value="Transportation"><?= __('transportation') ?></option>
                    <option value="Packaging"><?= __('expense_packaging') ?></option>
                    <option value="Utilities"><?= __('utilities') ?></option>
                    <option value="Tea/Snacks"><?= __('tea_snacks') ?></option>
                    <option value="Others"><?= __('others') ?></option>
                </select>
            </div>
            
            <div>
                <label class="form-label dark:text-gray-300"><?= __('amount') ?> *</label>
                <input type="number" step="0.01" name="amount" id="ex-amount" required class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="0.00">
            </div>

            <div>
                <label class="form-label dark:text-gray-300"><?= __('date') ?> *</label>
                <input type="date" name="expense_date" id="ex-date" value="<?= date('Y-m-d') ?>" required class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white">
            </div>

            <div>
                <label class="form-label dark:text-gray-300"><?= __('notes') ?></label>
                <textarea name="notes" id="ex-notes" rows="2" class="form-control dark:bg-gray-700 dark:border-gray-600 dark:text-white" placeholder="<?= __('expense_notes_placeholder') ?>"></textarea>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('expense-modal').classList.add('hidden')" class="btn btn-secondary dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600"><?= __('cancel') ?></button>
                <button type="submit" class="btn btn-primary"><?= __('save') ?></button>
            </div>
        </form>
    </div>
</div>

<script>
function openExpenseModal() {
    document.getElementById('modal-title').textContent = '<?= __('add_expense') ?>';
    document.getElementById('ex-id').value = '0';
    document.getElementById('ex-category').value = 'Shop Rent';
    document.getElementById('ex-amount').value = '';
    document.getElementById('ex-date').value = '<?= date('Y-m-d') ?>';
    document.getElementById('ex-notes').value = '';
    document.getElementById('expense-modal').classList.remove('hidden');
}

function editExpense(data) {
    document.getElementById('modal-title').textContent = '<?= __('edit_expense') ?>';
    document.getElementById('ex-id').value = data.id;
    document.getElementById('ex-category').value = data.category;
    document.getElementById('ex-amount').value = data.amount;
    document.getElementById('ex-date').value = data.expense_date;
    document.getElementById('ex-notes').value = data.notes;
    document.getElementById('expense-modal').classList.remove('hidden');
}
</script>

<?php require_once 'includes/footer.php'; ?>
