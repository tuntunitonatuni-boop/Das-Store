<?php
// api/admin.php — Admin/Staff mobile API endpoints
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';

// ---------- Auth Helper ----------
function get_admin_user($pdo) {
    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (!$auth) return null;
    $token = str_replace('Bearer ', '', $auth);
    $decoded = base64_decode($token);
    if (!$decoded) return null;
    $parts = explode(':', $decoded);
    if (count($parts) < 3 || $parts[0] !== 'admin') return null;
    $id = (int)$parts[1];
    $stmt = $pdo->prepare("SELECT id, name, username, role FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? $input['action'] ?? '';

// ========== LOGIN ==========
if ($action === 'login') {
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';

    if (!$username || !$password) {
        echo json_encode(['success' => false, 'message' => 'Username and password required']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, name, username, role, password FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        unset($user['password']);
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'token' => base64_encode('admin:' . $user['id'] . ':' . time()),
            'user' => $user,
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    }
    exit;
}

// ========== REQUIRE AUTH FOR ALL BELOW ==========
$admin = get_admin_user($pdo);
if (!$admin) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// ========== DASHBOARD STATS ==========
if ($action === 'dashboard') {
    $today = date('Y-m-d');

    // Today's sales
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) as today_sales, COUNT(*) as today_orders FROM sales WHERE DATE(created_at) = ?");
    $stmt->execute([$today]);
    $todaySales = $stmt->fetch();

    // Pending online orders
    $pending = $pdo->query("SELECT COUNT(*) as cnt FROM sales WHERE status='pending'")->fetch();

    // Low stock count
    $lowStock = $pdo->query("SELECT COUNT(*) as cnt FROM inventory i JOIN products p ON p.id=i.product_id WHERE (i.display_qty+i.warehouse_qty) <= i.reorder_level AND p.is_active=1")->fetch();

    // Total products
    $totalProducts = $pdo->query("SELECT COUNT(*) as cnt FROM products WHERE is_active=1")->fetch();

    // Total customers
    $totalCustomers = $pdo->query("SELECT COUNT(*) as cnt FROM customers WHERE is_active=1")->fetch();

    // This month sales
    $monthStart = date('Y-m-01');
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) as month_sales FROM sales WHERE DATE(created_at) >= ? AND status='completed'");
    $stmt->execute([$monthStart]);
    $monthSales = $stmt->fetch();

    // Last 7 days sales chart
    $chartData = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) as total FROM sales WHERE DATE(created_at) = ? AND status='completed'");
        $stmt->execute([$d]);
        $row = $stmt->fetch();
        $chartData[] = ['date' => date('d/m', strtotime($d)), 'total' => (float)$row['total']];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'today_sales' => (float)$todaySales['today_sales'],
            'today_orders' => (int)$todaySales['today_orders'],
            'pending_orders' => (int)$pending['cnt'],
            'low_stock' => (int)$lowStock['cnt'],
            'total_products' => (int)$totalProducts['cnt'],
            'total_customers' => (int)$totalCustomers['cnt'],
            'month_sales' => (float)$monthSales['month_sales'],
            'chart' => $chartData,
        ],
    ]);
    exit;
}

// ========== ORDERS LIST ==========
if ($action === 'orders') {
    $status = $_GET['status'] ?? '';
    $where = '';
    $params = [];
    if ($status) { $where = "WHERE s.status = ?"; $params[] = $status; }

    $stmt = $pdo->prepare("
        SELECT s.*, COALESCE(c.name, 'Walk-in') as customer_name, COALESCE(c.phone,'') as customer_phone
        FROM sales s LEFT JOIN customers c ON c.id = s.customer_id
        $where ORDER BY s.id DESC LIMIT 50
    ");
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
    echo json_encode(['success' => true, 'orders' => $orders]);
    exit;
}

// ========== ORDER DETAIL ==========
if ($action === 'order_detail') {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT s.*, COALESCE(c.name,'Walk-in') as customer_name, COALESCE(c.phone,'') as customer_phone, c.address as customer_address FROM sales s LEFT JOIN customers c ON c.id=s.customer_id WHERE s.id=?");
    $stmt->execute([$id]);
    $order = $stmt->fetch();
    if (!$order) { echo json_encode(['success' => false, 'message' => 'Order not found']); exit; }

    $items = $pdo->prepare("SELECT * FROM sale_items WHERE sale_id=?");
    $items->execute([$id]);
    $order['items'] = $items->fetchAll();

    echo json_encode(['success' => true, 'order' => $order]);
    exit;
}

// ========== UPDATE ORDER STATUS ==========
if ($action === 'update_order_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = (int)($input['order_id'] ?? 0);
    $newStatus = trim($input['status'] ?? '');
    $allowed = ['pending','confirmed','packaging','delivered','cancelled'];

    if (!$orderId || !in_array($newStatus, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Invalid order or status']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE sales SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $orderId]);
    echo json_encode(['success' => true, 'message' => 'Status updated']);
    exit;
}

// ========== LOW STOCK PRODUCTS ==========
if ($action === 'low_stock') {
    $stmt = $pdo->query("
        SELECT p.id, p.name, p.unit, p.sale_price, i.display_qty, i.warehouse_qty, i.reorder_level
        FROM inventory i JOIN products p ON p.id=i.product_id
        WHERE (i.display_qty+i.warehouse_qty) <= i.reorder_level AND p.is_active=1
        ORDER BY (i.display_qty+i.warehouse_qty) ASC LIMIT 50
    ");
    echo json_encode(['success' => true, 'products' => $stmt->fetchAll()]);
    exit;
}

// ========== RECENT SALES ==========
if ($action === 'recent_sales') {
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
    $stmt = $pdo->prepare("
        SELECT s.id, s.invoice_no, s.total, s.payment_method, s.status, s.created_at, COALESCE(c.name,'Walk-in') as customer_name
        FROM sales s LEFT JOIN customers c ON c.id=s.customer_id
        ORDER BY s.id DESC LIMIT ?
    ");
    $stmt->execute([$limit]);
    echo json_encode(['success' => true, 'sales' => $stmt->fetchAll()]);
    exit;
}

// ========== CASH REGISTER ==========
if ($action === 'cash_register_status') {
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT * FROM cash_register WHERE date = ?");
    $stmt->execute([$today]);
    $reg = $stmt->fetch();
    
    if (!$reg) {
        echo json_encode(['success' => true, 'status' => 'unopened', 'register' => null]);
    } else {
        echo json_encode(['success' => true, 'status' => $reg['status'], 'register' => $reg]);
    }
    exit;
}

if ($action === 'cash_register_open' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $today = date('Y-m-d');
    $openingCash = (float)($input['opening_cash'] ?? 0);
    
    $stmt = $pdo->prepare("SELECT * FROM cash_register WHERE date = ?");
    $stmt->execute([$today]);
    $reg = $stmt->fetch();
    
    if (!$reg) {
        $pdo->prepare("INSERT INTO cash_register (date, opening_cash, status, opened_by) VALUES (?,?,?,?)")
            ->execute([$today, $openingCash, 'open', $admin['id']]);
    } else {
        $pdo->prepare("UPDATE cash_register SET opening_cash=?, status='open', opened_by=? WHERE date=?")
            ->execute([$openingCash, $admin['id'], $today]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Register opened']);
    exit;
}

if ($action === 'cash_register_close' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $today = date('Y-m-d');
    $closingCash = (float)($input['closing_cash'] ?? 0);
    $notes = trim($input['notes'] ?? '');
    
    $total_sales = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM sales WHERE DATE(created_at)=? AND status='completed'");
    $total_sales->execute([$today]); $total_sales = $total_sales->fetchColumn();

    $cash_sales = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM sales WHERE DATE(created_at)=? AND payment_method='cash' AND status='completed'");
    $cash_sales->execute([$today]); $cash_sales = $cash_sales->fetchColumn();

    $mobile_sales = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM sales WHERE DATE(created_at)=? AND payment_method='mobile' AND status='completed'");
    $mobile_sales->execute([$today]); $mobile_sales = $mobile_sales->fetchColumn();

    $expenses = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date=?");
    $expenses->execute([$today]); $expenses = $expenses->fetchColumn();

    $pdo->prepare("UPDATE cash_register SET closing_cash=?,total_sales=?,total_cash=?,total_mobile=?,total_expenses=?,notes=?,status='closed',closed_by=? WHERE date=?")
        ->execute([$closingCash, $total_sales, $cash_sales, $mobile_sales, $expenses, $notes, $admin['id'], $today]);
        
    echo json_encode(['success' => true, 'message' => 'Register closed']);
    exit;
}

// ========== POS PRODUCTS ==========
if ($action === 'pos_products') {
    $search = $_GET['search'] ?? '';
    $cat = (int)($_GET['category_id'] ?? 0);
    
    $where = ["p.is_active = 1"];
    $params = [];
    
    if ($search) {
        $where[] = "(p.name LIKE ? OR p.barcode = ?)";
        $params[] = "%$search%";
        $params[] = $search;
    }
    if ($cat) {
        $where[] = "p.category_id = ?";
        $params[] = $cat;
    }
    
    $whereSql = implode(' AND ', $where);
    
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, p.barcode, p.sale_price, p.unit, p.image, 
               c.name as category_name, i.display_qty, i.warehouse_qty,
               (i.display_qty + i.warehouse_qty) as total_qty
        FROM products p 
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN inventory i ON i.product_id = p.id
        WHERE $whereSql
        ORDER BY p.name ASC LIMIT 100
    ");
    $stmt->execute($params);
    echo json_encode(['success' => true, 'products' => $stmt->fetchAll()]);
    exit;
}

// ========== CUSTOMERS ==========
if ($action === 'customers') {
    $stmt = $pdo->query("SELECT id, name, phone, address FROM customers WHERE is_active=1 ORDER BY name ASC");
    echo json_encode(['success' => true, 'customers' => $stmt->fetchAll()]);
    exit;
}

// ========== POS CHECKOUT ==========
if ($action === 'pos_checkout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerId = (int)($input['customer_id'] ?? 0);
    $paymentMethod = $input['payment_method'] ?? 'cash';
    $amountPaid = (float)($input['amount_paid'] ?? 0);
    $items = $input['items'] ?? [];
    
    if (empty($items)) {
        echo json_encode(['success' => false, 'message' => 'Cart is empty']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += ((float)$item['price'] * (float)$item['qty']);
        }
        
        $discount = (float)($input['discount'] ?? 0);
        $total = $subtotal - $discount;
        
        // Use price_ceil if function exists in PHP, or just round up. 
        // The store logic usually requires rounding up to nearest Taka.
        if (function_exists('price_ceil')) {
            $total = price_ceil($total);
        } else {
            $total = ceil($total);
        }
        
        $invoiceNo = 'INV-' . date('YmdHis') . rand(10,99);
        
        $stmt = $pdo->prepare("INSERT INTO sales (invoice_no, customer_id, user_id, subtotal, discount, total, amount_paid, payment_method, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed')");
        $stmt->execute([$invoiceNo, $customerId ?: null, $admin['id'], $subtotal, $discount, $total, max($amountPaid, $total), $paymentMethod]);
        $saleId = $pdo->lastInsertId();
        
        $itemStmt = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, product_name, unit_price, qty, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
        $invStmt = $pdo->prepare("UPDATE inventory SET display_qty = display_qty - ? WHERE product_id = ?");
        
        foreach ($items as $item) {
            $pid = (int)$item['id'];
            $qty = (float)$item['qty'];
            $price = (float)$item['price'];
            $itemSub = $price * $qty;
            
            $itemStmt->execute([$saleId, $pid, $item['name'], $price, $qty, $itemSub]);
            $invStmt->execute([$qty, $pid]);
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Sale completed', 'invoice_no' => $invoiceNo, 'sale_id' => $saleId]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// ========== INVENTORY MANAGEMENT ==========
if ($action === 'inventory_list') {
    $search = $_GET['search'] ?? '';
    $where = ["p.is_active = 1"];
    $params = [];
    if ($search) {
        $where[] = "(p.name LIKE ? OR p.barcode = ?)";
        $params[] = "%$search%";
        $params[] = $search;
    }
    $whereSql = implode(' AND ', $where);
    
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, p.barcode, p.sale_price, p.purchase_price, p.unit,
               i.display_qty, i.warehouse_qty, i.reorder_level
        FROM products p
        LEFT JOIN inventory i ON i.product_id = p.id
        WHERE $whereSql
        ORDER BY p.id DESC LIMIT 100
    ");
    $stmt->execute($params);
    echo json_encode(['success' => true, 'products' => $stmt->fetchAll()]);
    exit;
}

if ($action === 'update_inventory' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pid = (int)($input['product_id'] ?? 0);
    $display = (int)($input['display_qty'] ?? 0);
    $warehouse = (int)($input['warehouse_qty'] ?? 0);
    $price = (float)($input['sale_price'] ?? 0);
    
    if (!$pid) {
        echo json_encode(['success' => false, 'message' => 'Invalid product']);
        exit;
    }
    
    $pdo->prepare("UPDATE inventory SET display_qty=?, warehouse_qty=? WHERE product_id=?")->execute([$display, $warehouse, $pid]);
    if ($price > 0) {
        $pdo->prepare("UPDATE products SET sale_price=? WHERE id=?")->execute([$price, $pid]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Inventory updated']);
    exit;
}

// ========== EXPENSES ==========
if ($action === 'expenses') {
    $stmt = $pdo->query("SELECT * FROM expenses ORDER BY expense_date DESC, id DESC LIMIT 50");
    echo json_encode(['success' => true, 'expenses' => $stmt->fetchAll()]);
    exit;
}

if ($action === 'add_expense' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)($input['amount'] ?? 0);
    $category = trim($input['category'] ?? '');
    $description = trim($input['description'] ?? '');
    $date = $input['expense_date'] ?? date('Y-m-d');
    
    if ($amount > 0 && $category) {
        $stmt = $pdo->prepare("INSERT INTO expenses (expense_date, category, amount, description, user_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$date, $category, $amount, $description, $admin['id']]);
        echo json_encode(['success' => true, 'message' => 'Expense added']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
    }
    exit;
}

// ========== COUPONS ==========
if ($action === 'coupons') {
    $stmt = $pdo->query("SELECT * FROM coupons ORDER BY id DESC LIMIT 50");
    echo json_encode(['success' => true, 'coupons' => $stmt->fetchAll()]);
    exit;
}

if ($action === 'toggle_coupon' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($input['id'] ?? 0);
    $status = (int)($input['is_active'] ?? 0);
    $pdo->prepare("UPDATE coupons SET is_active=? WHERE id=?")->execute([$status, $id]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'add_coupon' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = strtoupper(trim($input['code'] ?? ''));
    $type = trim($input['discount_type'] ?? 'percent');
    $value = (float)($input['discount_value'] ?? 0);
    $minOrder = (float)($input['min_order_amount'] ?? 0);
    $maxDiscount = (float)($input['max_discount'] ?? 0);
    $validUntil = !empty($input['valid_until']) ? $input['valid_until'] : null;
    $isActive = (int)($input['is_active'] ?? 1);

    if (!$code || $value <= 0) {
        echo json_encode(['success' => false, 'message' => 'Code and discount value required']);
        exit;
    }

    // Check duplicate
    $check = $pdo->prepare("SELECT id FROM coupons WHERE code = ?");
    $check->execute([$code]);
    if ($check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'এই কোড আগে থেকেই আছে']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO coupons (code, discount_type, discount_value, min_order_amount, max_discount, valid_until, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$code, $type, $value, $minOrder, $maxDiscount > 0 ? $maxDiscount : null, $validUntil, $isActive]);
    echo json_encode(['success' => true, 'message' => 'Coupon added successfully']);
    exit;
}

// ========== CATEGORIES ==========
if ($action === 'get_categories') {
    $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
    echo json_encode(['success' => true, 'categories' => $stmt->fetchAll()]);
    exit;
}

// ========== ADD PRODUCT ==========
if ($action === 'add_product' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($input['name'] ?? '');
    $cat = (int)($input['category_id'] ?? 0);
    $price = (float)($input['sale_price'] ?? 0);
    $unit = trim($input['unit'] ?? 'pcs');
    $barcode = trim($input['barcode'] ?? '');
    
    if (!$name || !$cat || !$price) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO products (name, category_id, sale_price, unit, barcode, is_active) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->execute([$name, $cat, $price, $unit, $barcode]);
        $pid = $pdo->lastInsertId();
        
        $pdo->prepare("INSERT INTO inventory (product_id, display_qty, warehouse_qty, reorder_level) VALUES (?, 0, 0, 5)")->execute([$pid]);
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Product added successfully']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ========== CUSTOMER LEDGER ==========
if ($action === 'customers_ledger') {
    $search = $_GET['search'] ?? '';
    $where = ["is_active=1"];
    $params = [];
    if ($search) {
        $where[] = "(name LIKE ? OR phone LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    $whereSql = implode(' AND ', $where);
    
    $stmt = $pdo->prepare("SELECT id, name, phone, balance FROM customers WHERE $whereSql ORDER BY balance DESC, name ASC LIMIT 50");
    $stmt->execute($params);
    echo json_encode(['success' => true, 'customers' => $stmt->fetchAll()]);
    exit;
}

if ($action === 'customer_payment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $cid = (int)($input['customer_id'] ?? 0);
    $amount = (float)($input['amount'] ?? 0);
    $method = trim($input['method'] ?? 'cash');
    $notes = trim($input['notes'] ?? '');
    
    if (!$cid || $amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        $pdo->prepare("INSERT INTO customer_payments (customer_id, amount, payment_method, received_by, notes) VALUES (?, ?, ?, ?, ?)")
            ->execute([$cid, $amount, $method, $admin['id'], $notes]);
            
        $pdo->prepare("UPDATE customers SET balance = balance - ? WHERE id = ?")->execute([$amount, $cid]);
        
        $pdo->prepare("INSERT INTO customer_ledger (customer_id, type, amount, description) VALUES (?, 'payment', ?, ?)")
            ->execute([$cid, $amount, 'Payment Received ('.$method.')']);
            
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Payment received']);
    } catch(Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ========== SUPPLIER LEDGER ==========
if ($action === 'suppliers_ledger') {
    $search = $_GET['search'] ?? '';
    $where = ["is_active=1"];
    $params = [];
    if ($search) {
        $where[] = "(name LIKE ? OR phone LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    $whereSql = implode(' AND ', $where);
    
    $stmt = $pdo->prepare("SELECT id, name, phone, balance FROM dealers WHERE $whereSql ORDER BY balance DESC, name ASC LIMIT 50");
    $stmt->execute($params);
    echo json_encode(['success' => true, 'suppliers' => $stmt->fetchAll()]);
    exit;
}

if ($action === 'supplier_payment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $sid = (int)($input['supplier_id'] ?? 0);
    $amount = (float)($input['amount'] ?? 0);
    $method = trim($input['method'] ?? 'cash');
    $notes = trim($input['notes'] ?? '');
    
    if (!$sid || $amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        $pdo->prepare("INSERT INTO supplier_payments (supplier_id, amount, payment_method, user_id, notes) VALUES (?, ?, ?, ?, ?)")
            ->execute([$sid, $amount, $method, $admin['id'], $notes]);
            
        $pdo->prepare("UPDATE dealers SET balance = balance - ? WHERE id = ?")->execute([$amount, $sid]);
        
        $pdo->prepare("INSERT INTO supplier_ledger (dealer_id, type, amount, description) VALUES (?, 'payment', ?, ?)")
            ->execute([$sid, $amount, 'Payment Sent ('.$method.')']);
            
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Payment sent']);
    } catch(Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ========== PURCHASES ==========
if ($action === 'get_dealers') {
    $stmt = $pdo->query("SELECT id, name FROM dealers WHERE is_active=1 ORDER BY name ASC");
    echo json_encode(['success' => true, 'dealers' => $stmt->fetchAll()]);
    exit;
}

if ($action === 'purchases') {
    $stmt = $pdo->query("SELECT po.*, d.name as dealer_name FROM purchase_orders po LEFT JOIN dealers d ON d.id=po.dealer_id ORDER BY po.created_at DESC LIMIT 50");
    echo json_encode(['success' => true, 'purchases' => $stmt->fetchAll()]);
    exit;
}

if ($action === 'add_purchase' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $dealerId = (int)($input['dealer_id'] ?? 0);
    $status = trim($input['status'] ?? 'received');
    $items = $input['items'] ?? [];
    
    if (!$dealerId || empty($items)) {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        $total = 0;
        foreach ($items as $item) $total += ((float)$item['cost_price'] * (float)$item['qty']);
        
        $poNumber = 'PO-' . date('YmdHis');
        $pdo->prepare("INSERT INTO purchase_orders (po_number, dealer_id, user_id, total, status, ordered_at) VALUES (?, ?, ?, ?, ?, NOW())")
            ->execute([$poNumber, $dealerId, $admin['id'], $total, $status]);
        $poId = $pdo->lastInsertId();
        
        $itemStmt = $pdo->prepare("INSERT INTO purchase_order_items (po_id, product_id, qty, cost_price, subtotal) VALUES (?, ?, ?, ?, ?)");
        $invStmt = $pdo->prepare("UPDATE inventory SET warehouse_qty = warehouse_qty + ? WHERE product_id = ?");
        $checkInv = $pdo->prepare("SELECT id FROM inventory WHERE product_id = ?");
        $insertInv = $pdo->prepare("INSERT INTO inventory (product_id, warehouse_qty, display_qty) VALUES (?, ?, 0)");
        
        foreach ($items as $item) {
            $pid = (int)$item['id'];
            $qty = (float)$item['qty'];
            $cost = (float)$item['cost_price'];
            $sub = $qty * $cost;
            $itemStmt->execute([$poId, $pid, $qty, $cost, $sub]);
            
            if ($status === 'received') {
                $checkInv->execute([$pid]);
                if ($checkInv->fetch()) {
                    $invStmt->execute([$qty, $pid]);
                } else {
                    $insertInv->execute([$pid, $qty]);
                }
            }
        }
        
        if ($status === 'received') {
            $pdo->prepare("UPDATE dealers SET balance = balance + ? WHERE id = ?")->execute([$total, $dealerId]);
            $pdo->prepare("INSERT INTO supplier_ledger (dealer_id, po_id, type, amount, description) VALUES (?, ?, 'purchase', ?, 'Purchase Order Received')")
                ->execute([$dealerId, $poId, $total]);
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Purchase added']);
    } catch(Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ========== REPORTS SUMMARY ==========
if ($action === 'reports_summary') {
    // 30 days basic summary
    $today = date('Y-m-d');
    $monthAgo = date('Y-m-d', strtotime('-30 days'));
    
    $sales = $pdo->prepare("SELECT COALESCE(SUM(total),0) as revenue FROM sales WHERE status='completed' AND DATE(created_at) BETWEEN ? AND ?");
    $sales->execute([$monthAgo, $today]);
    $revenue = $sales->fetchColumn();
    
    $exp = $pdo->prepare("SELECT COALESCE(SUM(amount),0) as total_exp FROM expenses WHERE expense_date BETWEEN ? AND ?");
    $exp->execute([$monthAgo, $today]);
    $expenses = $exp->fetchColumn();
    
    $pur = $pdo->prepare("SELECT COALESCE(SUM(total),0) as total_pur FROM purchase_orders WHERE status='received' AND DATE(created_at) BETWEEN ? AND ?");
    $pur->execute([$monthAgo, $today]);
    $purchases = $pur->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'report' => [
            'revenue_30d' => (float)$revenue,
            'expenses_30d' => (float)$expenses,
            'purchases_30d' => (float)$purchases,
            'net_profit' => (float)($revenue - $expenses) // Note: actual profit needs cost of goods sold, this is simplified.
        ]
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>
