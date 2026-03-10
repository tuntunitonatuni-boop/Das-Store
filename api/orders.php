<?php
// api/orders.php — REST: POST order, GET order history
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';

// Check if user is logged in (staff or owner)
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit;
    }

    $items          = $input['items']          ?? [];
    $total          = (float)($input['total']  ?? 0);
    $paymentMethod  = $input['payment_method'] ?? 'cash';
    $amountPaid     = (float)($input['amount_paid'] ?? 0);
    $customerId     = (int)($input['customer_id']   ?? 0);
    $discount       = (float)($input['discount']    ?? 0);
    $tax            = (float)($input['tax']         ?? 0);
    $userId         = current_user()['id'];

    if (empty($items)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Cart is empty']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        $invoiceNo = 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

        // 1. Create sale record
        $stmt = $pdo->prepare("INSERT INTO sales (invoice_no, customer_id, user_id, subtotal, discount, tax, total, amount_paid, change_given, payment_method) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $changeGiven = max(0, $amountPaid - $total);
        if ($paymentMethod === 'credit') $changeGiven = 0; // Credit sales have no change

        $stmt->execute([
            $invoiceNo,
            ($customerId ?: null),
            $userId,
            $total + $discount - $tax,
            $discount,
            $tax,
            $total,
            $amountPaid,
            $changeGiven,
            $paymentMethod
        ]);
        $saleId = $pdo->lastInsertId();

        // 2. Process each item
        foreach ($items as $item) {
            $pid = $item['id'];
            $qty = $item['qty'];
            $price = $item['price'];
            $name = $item['name'];
            $sub = $price * $qty;

            // Create sale_item record
            $itemStmt = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, product_name, qty, unit_price, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
            $itemStmt->execute([$saleId, $pid, $name, $qty, $price, $sub]);

            // Deduct from inventory (POS sales usually from display shelf)
            $invStmt = $pdo->prepare("UPDATE inventory SET display_qty = display_qty - ? WHERE product_id = ?");
            $invStmt->execute([$qty, $pid]);

            // Log stock movement
            $moveStmt = $pdo->prepare("INSERT INTO stock_movements (product_id, type, qty_change, location, reference_id, user_id) VALUES (?, 'sale', ?, 'display', ?, ?)");
            $moveStmt->execute([$pid, -$qty, $saleId, $userId]);
        }

        // 3. Handle credit sale
        if ($paymentMethod === 'credit') {
            if (!$customerId) throw new Exception("Customer is required for credit sales");
            $outstanding = $total - $amountPaid; // Usually 0 is paid on full credit
            if ($outstanding > 0) {
                // Update customer balance
                $custStmt = $pdo->prepare("UPDATE customers SET balance = balance + ? WHERE id = ?");
                $custStmt->execute([$outstanding, $customerId]);

                // Log customer ledger
                $ledgerStmt = $pdo->prepare("INSERT INTO customer_ledger (customer_id, sale_id, type, amount, description) VALUES (?, ?, 'debit', ?, ?)");
                $ledgerStmt->execute([$customerId, $saleId, $outstanding, "Credit purchase - Invoice $invoiceNo"]);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'sale_id' => $saleId, 'invoice_no' => $invoiceNo]);

    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// GET order history
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $limit = (int)($_GET['limit'] ?? 50);
        $stmt = $pdo->prepare("
            SELECT s.*, COALESCE(c.name, 'Walk-in') as customer_name, u.name as cashier_name
            FROM sales s
            LEFT JOIN customers c ON c.id = s.customer_id
            LEFT JOIN users u ON u.id = s.user_id
            ORDER BY s.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $orders = $stmt->fetchAll();
        echo json_encode(['success' => true, 'orders' => $orders]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}
?>
