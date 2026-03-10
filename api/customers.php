<?php
// api/customers.php — REST: GET/POST customers, ledger
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
$q = $_GET['q'] ?? '';

// Single customer lookup with ledger
if ($id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([$id]);
        $customer = $stmt->fetch();

        if ($customer) {
            // Fetch recent ledger
            $ledger = $pdo->prepare("SELECT * FROM customer_ledger WHERE customer_id = ? ORDER BY created_at DESC LIMIT 10");
            $ledger->execute([$id]);
            $customer['ledger'] = $ledger->fetchAll();
            echo json_encode(['success' => true, 'customer' => $customer]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Customer not found']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit;
}

// Customer search
if ($q) {
    try {
        $stmt = $pdo->prepare("SELECT id, name, phone, balance FROM customers WHERE (name LIKE ? OR phone LIKE ?) AND is_active = 1 LIMIT 20");
        $stmt->execute(["%$q%", "%$q%"]);
        $customers = $stmt->fetchAll();
        echo json_encode(['success' => true, 'customers' => $customers]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit;
}

// Default list
try {
    $stmt = $pdo->prepare("SELECT id, name, phone, balance FROM customers WHERE is_active = 1 ORDER BY name ASC LIMIT 50");
    $stmt->execute();
    $customers = $stmt->fetchAll();
    echo json_encode(['success' => true, 'customers' => $customers]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
