<?php
// api/ic_item_history.php
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$item_id = (int)($_GET['item_id'] ?? 0);

if (!$item_id) {
    echo json_encode(['success' => false, 'history' => []]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            p.invoice_no,
            s.name as supplier_name,
            pi.unit_price,
            pi.qty,
            DATE_FORMAT(p.created_at, '%d %b %Y') as date
        FROM ic_purchase_items pi
        JOIN ic_purchases p ON p.id = pi.purchase_id
        LEFT JOIN ic_suppliers s ON s.id = p.supplier_id
        WHERE pi.item_id = ?
        ORDER BY p.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$item_id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'history' => $history]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
