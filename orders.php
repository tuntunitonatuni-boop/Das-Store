<?php
// orders.php — Online Orders management
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/flash.php';
require_login();

$page_title = 'Online Orders';

// --- Handle Actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $sale_id = (int)($_POST['sale_id'] ?? 0);
    $new_status = $_POST['status'] ?? '';

    if ($sale_id > 0) {
        if ($action === 'update_status' && $new_status) {
            $stmt = $pdo->prepare("UPDATE sales SET status = ?, user_id = ? WHERE id = ?");
            $stmt->execute([$new_status, current_user()['id'], $sale_id]);
            set_flash('success', "Order status updated to " . ucfirst($new_status));
        } elseif ($action === 'cancel') {
            $pdo->beginTransaction();
            try {
                // 1. Mark sale as cancelled
                $stmt = $pdo->prepare("UPDATE sales SET status = 'cancelled', user_id = ? WHERE id = ?");
                $stmt->execute([current_user()['id'], $sale_id]);

                // 2. Restore stock
                $items = $pdo->prepare("SELECT product_id, qty FROM sale_items WHERE sale_id = ?");
                $items->execute([$sale_id]);
                foreach ($items->fetchAll() as $item) {
                    $pdo->prepare("UPDATE inventory SET warehouse_qty = warehouse_qty + ? WHERE product_id = ?")
                        ->execute([$item['qty'], $item['product_id']]);
                    
                    $pdo->prepare("INSERT INTO stock_movements (product_id, type, qty_change, location, reference_id, notes, user_id) VALUES (?, 'return', ?, 'warehouse', ?, 'Online Order Cancelled', ?)")
                        ->execute([$item['product_id'], $item['qty'], $sale_id, current_user()['id']]);
                }

                $pdo->commit();
                set_flash('success', 'Order cancelled and stock restored.');
            } catch (Exception $e) {
                $pdo->rollBack();
                set_flash('error', 'Failed to cancel order.');
            }
        }
    }
    header('Location: orders.php');
    exit;
}

// --- Fetch Data ---
$status_filter = $_GET['status'] ?? 'pending';
$params = [];
$where = "WHERE 1=1";

if ($status_filter !== 'all') {
    $where .= " AND s.status = ?";
    $params[] = $status_filter;
} else {
    $where .= " AND s.invoice_no LIKE 'WEB-%'";
}

$stmt = $pdo->prepare("
    SELECT s.*, c.name as customer_name, c.phone as customer_phone, c.address as customer_address, u.name as processed_by
    FROM sales s
    LEFT JOIN customers c ON c.id = s.customer_id
    LEFT JOIN users u ON u.id = s.user_id
    $where
    ORDER BY s.created_at DESC
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Get items for modals
$order_items = [];
if (count($orders) > 0) {
    $ids = array_column($orders, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM sale_items WHERE sale_id IN ($placeholders)");
    $stmt->execute($ids);
    while($row = $stmt->fetch()) {
        $order_items[$row['sale_id']][] = $row;
    }
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="page-header flex items-center justify-between">
    <div>
        <h1 class="dark:text-white"><?= __('online_orders') ?></h1>
        <p class="dark:text-gray-400"><?= __('online_orders_manage') ?></p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="?status=pending" class="btn <?= $status_filter === 'pending' ? 'btn-primary' : 'btn-secondary dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300' ?>"><?= __('pending') ?></a>
        <a href="?status=packaging" class="btn <?= $status_filter === 'packaging' ? 'btn-primary' : 'btn-secondary dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300' ?>"><?= __('packaging') ?></a>
        <a href="?status=delivering" class="btn <?= $status_filter === 'delivering' ? 'btn-primary' : 'btn-secondary dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300' ?>"><?= __('delivering') ?></a>
        <a href="?status=completed" class="btn <?= $status_filter === 'completed' ? 'btn-primary' : 'btn-secondary dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300' ?>"><?= __('completed') ?></a>
        <a href="?status=cancelled" class="btn <?= $status_filter === 'cancelled' ? 'btn-primary' : 'btn-secondary dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300' ?>"><?= __('cancelled') ?></a>
        <a href="?status=all" class="btn <?= $status_filter === 'all' ? 'btn-primary' : 'btn-secondary dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300' ?>"><?= __('all_web_orders') ?></a>
    </div>
</div>

<div class="card dark:bg-gray-800 dark:border-gray-700 p-0 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="data-table dark:text-gray-300">
            <thead class="dark:bg-gray-900/50">
                <tr>
                    <th class="dark:text-gray-400"><?= __('order_info') ?></th>
                    <th class="dark:text-gray-400"><?= __('customer') ?></th>
                    <th class="dark:text-gray-400"><?= __('address') ?></th>
                    <th class="text-right dark:text-gray-400"><?= __('total') ?></th>
                    <th class="dark:text-gray-400"><?= __('status') ?></th>
                    <th class="dark:text-gray-400"><?= __('date') ?></th>
                    <th class="no-print dark:text-gray-400"><?= __('actions') ?></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
                <?php foreach ($orders as $o): ?>
                <tr class="<?= $o['status'] === 'pending' ? 'bg-amber-50/30 dark:bg-amber-900/10' : '' ?> dark:hover:bg-gray-700/50">
                    <td>
                        <div class="font-mono text-xs font-bold text-brand-700 dark:text-brand-400"><?= htmlspecialchars($o['invoice_no']) ?></div>
                        <div class="text-[10px] text-gray-400 dark:text-gray-500"><?= count($order_items[$o['id']] ?? []) ?> <?= __('items') ?></div>
                    </td>
                    <td>
                        <div class="font-medium text-gray-900 dark:text-gray-100"><?= htmlspecialchars($o['customer_name'] ?? 'Guest') ?></div>
                        <div class="text-xs text-gray-500 dark:text-gray-400"><?= htmlspecialchars($o['customer_phone'] ?? '—') ?></div>
                    </td>
                    <td class="max-w-xs">
                        <div class="text-xs text-gray-600 dark:text-gray-400 truncate" title="<?= htmlspecialchars($o['customer_address'] ?? '') ?>">
                            <?= htmlspecialchars($o['customer_address'] ?? '—') ?>
                        </div>
                    </td>
                    <td class="text-right font-bold text-gray-900 dark:text-gray-100">
                        <?= CURRENCY . number_format($o['total'], 2) ?>
                        <div class="text-[10px] text-gray-400 dark:text-gray-500 font-normal uppercase"><?= htmlspecialchars($o['payment_method']) ?></div>
                    </td>
                    <td>
                        <?php if($o['status'] === 'pending'): ?>
                            <span class="badge badge-yellow"><?= __('pending') ?></span>
                        <?php elseif($o['status'] === 'packaging'): ?>
                            <span class="badge badge-blue"><?= __('packaging') ?></span>
                        <?php elseif($o['status'] === 'delivering'): ?>
                            <span class="badge badge-indigo"><?= __('delivering') ?></span>
                        <?php elseif($o['status'] === 'completed'): ?>
                            <span class="badge badge-green"><?= __('completed') ?></span>
                        <?php else: ?>
                            <span class="badge badge-red"><?= __($o['status']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="text-xs text-gray-500 dark:text-gray-400"><?= date('d M, Y', strtotime($o['created_at'])) ?></div>
                        <div class="text-[10px] text-gray-400 dark:text-gray-500"><?= date('h:i A', strtotime($o['created_at'])) ?></div>
                    </td>
                    <td class="no-print">
                        <div class="flex flex-wrap gap-1">
                            <button onclick="viewOrder(<?= $o['id'] ?>)" class="btn btn-xs btn-secondary dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600"><?= __('view') ?></button>
                            <?php if($o['status'] === 'pending'): ?>
                            <form method="post" class="inline">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="status" value="packaging">
                                <input type="hidden" name="sale_id" value="<?= $o['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-blue"><?= __('start_pack') ?></button>
                            </form>
                            <?php elseif($o['status'] === 'packaging'): ?>
                            <form method="post" class="inline">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="status" value="delivering">
                                <input type="hidden" name="sale_id" value="<?= $o['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-indigo"><?= __('ship_order') ?></button>
                            </form>
                            <?php elseif($o['status'] === 'delivering'): ?>
                            <form method="post" class="inline">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="status" value="completed">
                                <input type="hidden" name="sale_id" value="<?= $o['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-primary"><?= __('mark_delivered') ?></button>
                            </form>
                            <?php endif; ?>

                            <?php if($o['status'] !== 'completed' && $o['status'] !== 'cancelled'): ?>
                            <form method="post" class="inline" onsubmit="return confirm('<?= __('confirm_delete') ?>')">
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="sale_id" value="<?= $o['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-danger"><?= __('cancel') ?></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="7" class="text-center py-12 text-gray-400 dark:text-gray-500">
                        <div class="text-4xl mb-2">🛍️</div>
                        <?= __('no_products_found') ?>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Order Detail Modal -->
<div id="order-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden">
        <div class="flex items-center justify-between p-5 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
            <h2 class="font-bold text-lg dark:text-white"><?= __('order_details') ?> <span id="modal-invoice" class="text-brand-600 dark:text-brand-400 ml-2 font-mono text-sm"></span></h2>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
        </div>
        <div class="p-6 max-h-[80vh] overflow-y-auto">
            <div class="grid grid-cols-2 gap-4 mb-6 text-sm">
                <div>
                    <label class="text-[10px] font-bold text-gray-400 uppercase"><?= __('customer') ?></label>
                    <div id="modal-customer" class="font-bold dark:text-white"></div>
                    <div id="modal-phone" class="text-gray-500 dark:text-gray-400"></div>
                </div>
                <div>
                    <label class="text-[10px] font-bold text-gray-400 uppercase"><?= __('status') ?></label>
                    <div id="modal-status"></div>
                </div>
                <div class="col-span-2">
                    <label class="text-[10px] font-bold text-gray-400 uppercase"><?= __('address') ?></label>
                    <div id="modal-address" class="text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-700 p-2 rounded-lg border border-gray-100 dark:border-gray-600 mt-1"></div>
                </div>
                <div class="col-span-2" id="modal-notes-div">
                    <label class="text-[10px] font-bold text-gray-400 uppercase"><?= __('notes') ?></label>
                    <div id="modal-notes" class="italic text-gray-500 dark:text-gray-400 text-xs"></div>
                </div>
                <!-- Quick Status Update in Modal -->
                <div class="col-span-2 bg-gray-50 dark:bg-gray-700 p-4 rounded-xl border border-dotted border-gray-300 dark:border-gray-600 mt-2">
                    <label class="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest block mb-2"><?= __('change_order_status') ?></label>
                    <form method="post" class="flex gap-2">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="sale_id" id="modal-sale-id" value="">
                        <select name="status" id="modal-status-select" class="form-control text-xs py-2 dark:bg-gray-800 dark:border-gray-600 dark:text-white">
                            <option value="pending"><?= __('pending') ?></option>
                            <option value="packaging"><?= __('packaging') ?></option>
                            <option value="delivering"><?= __('delivering') ?></option>
                            <option value="completed"><?= __('completed') ?></option>
                            <option value="cancelled"><?= __('cancelled') ?></option>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm whitespace-nowrap"><?= __('update') ?></button>
                    </form>
                </div>
            </div>

            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-gray-700">
                        <th class="text-left py-2 font-bold text-gray-400 uppercase text-[10px]"><?= __('item') ?></th>
                        <th class="text-center py-2 font-bold text-gray-400 uppercase text-[10px]"><?= __('qty') ?></th>
                        <th class="text-right py-2 font-bold text-gray-400 uppercase text-[10px]"><?= __('total') ?></th>
                    </tr>
                </thead>
                <tbody id="modal-items" class="dark:text-gray-300"></tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-100 dark:border-gray-700 font-bold">
                        <td colspan="2" class="py-4 text-gray-500 dark:text-gray-400"><?= __('total_amount') ?></td>
                        <td id="modal-total" class="py-4 text-right text-lg text-brand-700 dark:text-brand-400"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="p-4 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-100 dark:border-gray-700 flex justify-end">
            <button onclick="closeModal()" class="btn btn-secondary dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600"><?= __('cancel') ?></button>
        </div>
    </div>
</div>

<script>
const orders = <?= json_encode($orders) ?>;
const orderItems = <?= json_encode($order_items) ?>;
const trans_pending = '<?= __('pending') ?>';
const trans_packaging = '<?= __('packaging') ?>';
const trans_delivering = '<?= __('delivering') ?>';
const trans_completed = '<?= __('completed') ?>';
const trans_cancelled = '<?= __('cancelled') ?>';

function getTranslatedStatus(status) {
    if(status === 'pending') return trans_pending;
    if(status === 'packaging') return trans_packaging;
    if(status === 'delivering') return trans_delivering;
    if(status === 'completed') return trans_completed;
    if(status === 'cancelled') return trans_cancelled;
    return status;
}

function viewOrder(id) {
    const order = orders.find(o => o.id == id);
    const items = orderItems[id] || [];
    
    document.getElementById('modal-invoice').textContent = order.invoice_no;
    document.getElementById('modal-customer').textContent = order.customer_name || 'Guest';
    document.getElementById('modal-phone').textContent = order.customer_phone || '';
    document.getElementById('modal-address').textContent = order.customer_address || '';
    document.getElementById('modal-status').innerHTML = `<span class="badge ${getStatusBadgeClass(order.status)}">${getTranslatedStatus(order.status)}</span>`;
    document.getElementById('modal-sale-id').value = order.id;
    document.getElementById('modal-status-select').value = order.status;
    
    if (order.notes) {
        document.getElementById('modal-notes-div').classList.remove('hidden');
        document.getElementById('modal-notes').textContent = order.notes;
    } else {
        document.getElementById('modal-notes-div').classList.add('hidden');
    }

    let itemsHtml = '';
    items.forEach(item => {
        itemsHtml += `
            <tr class="border-b border-gray-50 dark:border-gray-700">
                <td class="py-3">${item.product_name}</td>
                <td class="py-3 text-center">${item.qty}</td>
                <td class="py-3 text-right"><?= CURRENCY ?>${parseFloat(item.subtotal).toFixed(2)}</td>
            </tr>
        `;
    });
    document.getElementById('modal-items').innerHTML = itemsHtml;
    document.getElementById('modal-total').textContent = '<?= CURRENCY ?>' + parseFloat(order.total).toFixed(2);
    
    document.getElementById('order-modal').classList.remove('hidden');
}

function getStatusBadgeClass(status) {
    switch(status) {
        case 'pending': return 'badge-yellow';
        case 'packaging': return 'badge-blue';
        case 'delivering': return 'badge-indigo';
        case 'completed': return 'badge-green';
        case 'cancelled': return 'badge-red';
        default: return 'badge-blue';
    }
}

function closeModal() {
    document.getElementById('order-modal').classList.add('hidden');
}

// Close modal on background click
document.getElementById('order-modal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>

<?php require_once 'includes/footer.php'; ?>
