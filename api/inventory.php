<?php
// api/inventory.php — REST: GET stock levels
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';

// Check if user is logged in
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$filter = $_GET['filter'] ?? '';

// Single stock lookup
if ($id) {
    try {
        $stmt = $pdo->prepare("
            SELECT i.*, p.name as product_name, p.unit, p.barcode
            FROM inventory i
            JOIN products p ON p.id = i.product_id
            WHERE i.product_id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $inventory = $stmt->fetch();

        if ($inventory) {
            // Fetch recent movements
            $mvmt = $pdo->prepare("SELECT * FROM stock_movements WHERE product_id = ? ORDER BY created_at DESC LIMIT 10");
            $mvmt->execute([$id]);
            $inventory['movements'] = $mvmt->fetchAll();
            echo json_encode(['success' => true, 'inventory' => $inventory]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Inventory record not found']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit;
}

// Low stock filter API
if ($filter === 'low') {
    try {
        $stmt = $pdo->prepare("
            SELECT p.id, p.name, p.unit, i.display_qty, i.warehouse_qty, i.reorder_level
            FROM inventory i
            JOIN products p ON p.id = i.product_id
            WHERE (i.display_qty + i.warehouse_qty) <= i.reorder_level AND p.is_active = 1
            ORDER BY (i.display_qty + i.warehouse_qty) ASC
        ");
        $stmt->execute();
        $inventory = $stmt->fetchAll();
        echo json_encode(['success' => true, 'inventory' => $inventory]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit;
}

// Default list
try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, i.display_qty, i.warehouse_qty
        FROM inventory i
        JOIN products p ON p.id = i.product_id
        WHERE p.is_active = 1
        ORDER BY p.name ASC LIMIT 50
    ");
    $stmt->execute();
    $inventory = $stmt->fetchAll();
    echo json_encode(['success' => true, 'inventory' => $inventory]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
