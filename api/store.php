<?php
// api/store.php — Fetch store-front products and categories for the mobile app
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';

function get_auth_customer($pdo) {
    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (!$auth) return null;
    $token = str_replace('Bearer ', '', $auth);
    $decoded = base64_decode($token);
    if (!$decoded) return null;
    $parts = explode(':', $decoded);
    if (count($parts) < 3 || $parts[0] !== 'cust') return null;
    $id = (int)$parts[1];
    $stmt = $pdo->prepare("SELECT id, name, username, phone, address FROM customers WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

$action = $_GET['action'] ?? '';

// 1. Get Categories
if ($action === 'categories') {
    $categories = $pdo->query("SELECT id, name, slug, icon FROM categories WHERE is_active=1 ORDER BY sort_order")->fetchAll();
    echo json_encode(['success' => true, 'categories' => $categories]);
    exit;
}

// 2. Get Products (with pagination)
if ($action === 'products') {
    $cat_id = (int)($_GET['cat_id'] ?? 0);
    $search = trim($_GET['q'] ?? '');
    $limit  = 20;
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * $limit;

    $where = "WHERE p.is_active = 1";
    $params = [];

    if ($cat_id) { $where .= " AND p.category_id = ?"; $params[] = $cat_id; }
    if ($search) { $where .= " AND (p.name LIKE ? OR p.description LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

    // Join with inventory to see if it's in stock
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name, COALESCE(i.display_qty+i.warehouse_qty, 0) as stock
        FROM products p
        LEFT JOIN categories c ON c.id=p.category_id
        LEFT JOIN inventory i ON i.product_id=p.id
        $where
        ORDER BY p.id DESC
        LIMIT $offset, $limit
    ");
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    // Map images to full URLs
    foreach ($products as &$p) {
        $p['image_url'] = $p['image'] ? (BASE_URL . 'uploads/products/' . $p['image']) : null;
    }

    echo json_encode(['success' => true, 'products' => $products]);
    exit;
}

// 3. Product Detail
if ($action === 'product_detail') {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT p.*, c.name as category_name, co.name as company_name FROM products p LEFT JOIN categories c ON c.id=p.category_id LEFT JOIN companies co ON co.id=p.company_id WHERE p.id=? LIMIT 1");
    $stmt->execute([$id]);
    $p = $stmt->fetch();
    if ($p) {
        $p['image_url'] = $p['image'] ? (BASE_URL . 'uploads/products/' . $p['image']) : null;
        echo json_encode(['success' => true, 'product' => $p]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
    }
    exit;
}

// 4. Checkout (Mobile Order)
if ($action === 'checkout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer = get_auth_customer($pdo);
    if (!$customer) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login again.']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $items = $input['items'] ?? [];
    $total = (float)($input['total'] ?? 0);
    $address = trim($input['address'] ?? $customer['address']);

    if (empty($items)) {
        echo json_encode(['success' => false, 'message' => 'Cart is empty']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        $invoiceNo = 'WEB-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
        
        // 1. Create sale record (status 'pending' for online orders)
        $stmt = $pdo->prepare("INSERT INTO sales (invoice_no, customer_id, total, amount_paid, payment_method, status, shipping_address) VALUES (?, ?, ?, 0, 'cod', 'pending', ?)");
        $stmt->execute([$invoiceNo, $customer['id'], $total, $address]);
        $saleId = $pdo->lastInsertId();

        // 2. Process items
        foreach ($items as $item) {
            $stmt = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, product_name, qty, unit_price, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$saleId, $item['id'], $item['name'], $item['qty'], $item['price'], $item['price'] * $item['qty']]);
            
            // Stock deduction happens when admin marks as 'packaging' or 'delivered' to prevent stock jumping for fake orders
            // But for POS we do it immediately. For online, let's keep it pending.
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Order placed successfully!', 'invoice_no' => $invoiceNo]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to place order: ' . $e->getMessage()]);
    }
    exit;
}

// 5. My Orders
if ($action === 'my_orders') {
    $customer = get_auth_customer($pdo);
    if (!$customer) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM sales WHERE customer_id = ? ORDER BY id DESC");
    $stmt->execute([$customer['id']]);
    $orders = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'orders' => $orders]);
    exit;
}
?>
