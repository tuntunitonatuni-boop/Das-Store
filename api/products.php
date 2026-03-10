<?php
// api/products.php — REST: GET products, search, barcode lookup
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

$action = $_GET['action'] ?? '';
$barcode = $_GET['barcode'] ?? '';
$search = $_GET['q'] ?? '';

// Barcode lookup
if ($barcode) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, COALESCE(i.display_qty+i.warehouse_qty, 0) as stock, c.name as category_name
            FROM products p
            LEFT JOIN inventory i ON i.product_id = p.id
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE p.barcode = ? AND p.is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$barcode]);
        $product = $stmt->fetch();

        if ($product) {
            echo json_encode(['success' => true, 'product' => $product]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Product not found']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit;
}

// Global Search
if ($search) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.id, p.name, p.sale_price, p.unit, p.barcode, COALESCE(i.display_qty+i.warehouse_qty, 0) as stock
            FROM products p
            LEFT JOIN inventory i ON i.product_id = p.id
            WHERE (p.name LIKE ? OR p.barcode LIKE ? OR p.sku LIKE ?) AND p.is_active = 1
            ORDER BY p.name ASC
            LIMIT 20
        ");
        $stmt->execute(["%$search%", "%$search%", "%$search%"]);
        $products = $stmt->fetchAll();
        echo json_encode(['success' => true, 'products' => $products]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit;
}

// Default list
try {
    $stmt = $pdo->prepare("
        SELECT id, name, sale_price, unit, barcode, mfg_date, expiry_date
        FROM products WHERE is_active = 1
        ORDER BY name ASC LIMIT 100
    ");
    $stmt->execute();
    $products = $stmt->fetchAll();
    echo json_encode(['success' => true, 'products' => $products]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
