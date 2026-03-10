<?php
// includes/db.php — PDO connection singleton
if (!defined('DB_HOST')) {
    require_once dirname(__DIR__) . '/config.php';
}

if (!isset($pdo)) {
    try {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // Show a friendly error; never expose credentials
        http_response_code(500);
        die('<div style="font-family:sans-serif;padding:2rem;color:#c0392b;">
                <h2>Database Connection Failed</h2>
                <p>Could not connect to the database. Please check <code>config.php</code>.</p>
                <small>' . htmlspecialchars($e->getMessage()) . '</small>
             </div>');
    }
}
