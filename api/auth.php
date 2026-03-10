<?php
// api/auth.php — Authentication for Mobile Apps
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? $input['action'] ?? '';

if ($action === 'login') {
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';

    if (!$username || !$password) {
        echo json_encode(['success' => false, 'message' => 'Username/Email and password required']);
        exit;
    }

    // Check customers table for online shoppers
    $stmt = $pdo->prepare("SELECT id, name, username, phone, password FROM customers WHERE (username = ? OR phone = ? OR email = ?) LIMIT 1");
    $stmt->execute([$username, $username, $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        unset($user['password']);
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'token' => base64_encode('cust:' . $user['id'] . ':' . time()),
            'user' => $user
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    }
    exit;
}

if ($action === 'register') {
    $name     = trim($input['name'] ?? '');
    $username = trim($input['username'] ?? '');
    $phone    = trim($input['phone'] ?? '');
    $password = $input['password'] ?? '';
    
    if (!$name || !$username || !$phone || !$password) {
        echo json_encode(['success' => false, 'message' => 'Name, Username, Phone and password are required']);
        exit;
    }

    // Check if customer exists
    $check = $pdo->prepare("SELECT id FROM customers WHERE username = ? OR phone = ?");
    $check->execute([$username, $phone]);
    if ($check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Username or Phone already registered']);
        exit;
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO customers (name, username, phone, password) VALUES (?, ?, ?, ?)");
    if ($stmt->execute([$name, $username, $phone, $hashed])) {
        echo json_encode(['success' => true, 'message' => 'Registration successful. You can now login.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Registration failed']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>
