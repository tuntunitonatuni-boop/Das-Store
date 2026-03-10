<?php
// api/sales.php — REST: GET sales summary
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

$date = $_GET['date'] ?? date('Y-m-d');

try {
    // 1. Sales summary for date
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total), 0) as total, COUNT(*) as count,
               COALESCE(SUM(CASE WHEN payment_method='cash' THEN total ELSE 0 END), 0) as cash_total,
               COALESCE(SUM(CASE WHEN payment_method='card' THEN total ELSE 0 END), 0) as card_total,
               COALESCE(SUM(CASE WHEN payment_method='mobile' THEN total ELSE 0 END), 0) as mobile_total,
               COALESCE(SUM(CASE WHEN payment_method='credit' THEN total ELSE 0 END), 0) as credit_total
        FROM sales
        WHERE DATE(created_at) = ? AND status='completed'
    ");
    $stmt->execute([$date]);
    $summary = $stmt->fetch();

    // 2. Category-wise sales
    $catsStmt = $pdo->prepare("
        SELECT c.name, SUM(si.subtotal) as total
        FROM sale_items si
        JOIN products p ON p.id = si.product_id
        JOIN categories c ON c.id = p.category_id
        JOIN sales s ON s.id = si.sale_id
        WHERE DATE(s.created_at) = ? AND s.status='completed'
        GROUP BY c.id
        ORDER BY total DESC
    ");
    $catsStmt->execute([$date]);
    $cats = $catsStmt->fetchAll();

    echo json_encode([
        'success' => true,
        'date'    => $date,
        'summary' => $summary,
        'by_category' => $cats
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
