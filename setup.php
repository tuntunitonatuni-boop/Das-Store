<?php
// setup.php — One-time database installer. DELETE THIS FILE AFTER USE.
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';

$messages = [];
$errors   = [];
$step     = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 1. Connect without db selected first, create db if needed
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET,
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `" . DB_NAME . "`");
        $messages[] = '✓ Database "' . DB_NAME . '" ready.';
        $step++;

        // 2. Run migrations
        $sql = file_get_contents(__DIR__ . '/database/migrations/create_tables.sql');
        // Strip SET statements that can cause issues, run each statement
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $stmt) {
            if ($stmt) $pdo->exec($stmt);
        }
        $messages[] = '✓ All tables created.';
        $step++;

        // 3. Run seeder
        if (isset($_POST['seed'])) {
            $sql2 = file_get_contents(__DIR__ . '/database/seeders/seed_data.sql');
            $stmts2 = array_filter(array_map('trim', explode(';', $sql2)));
            foreach ($stmts2 as $stmt) {
                if ($stmt) $pdo->exec($stmt);
            }
            $messages[] = '✓ Sample data seeded.';
            $step++;
        }

        $messages[] = '🎉 Setup complete! Login at <a href="login.php" class="underline text-brand-600">login.php</a> with <strong>admin / admin123</strong>';
    } catch (PDOException $e) {
        $errors[] = '✕ ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Setup — <?= SHOP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','sans-serif']},colors:{brand:{600:'#16a34a',700:'#15803d'}}}}}</script>
</head>
<body class="bg-gradient-to-br from-brand-700 to-emerald-900 min-h-screen flex items-center justify-center font-sans">
<div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full mx-4 overflow-hidden">
    <!-- Header -->
    <div class="bg-gradient-to-r from-brand-600 to-brand-700 text-white p-8 text-center">
        <div class="text-5xl mb-3">🛒</div>
        <h1 class="text-2xl font-bold"><?= SHOP_NAME ?></h1>
        <p class="text-white/80 text-sm mt-1">Smart Grocery ERP — One-Time Setup</p>
    </div>

    <div class="p-8">
        <?php if (!empty($messages)): ?>
        <div class="mb-6 space-y-2">
            <?php foreach ($messages as $m): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-4 py-2 text-sm"><?= $m ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
        <div class="mb-6 space-y-2">
            <?php foreach ($errors as $e): ?>
            <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-2 text-sm"><?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($step === 0): ?>
        <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-lg p-4 text-sm mb-6">
            ⚠️ <strong>Delete this file</strong> (<code>setup.php</code>) after setup is complete to prevent unauthorized re-installation.
        </div>

        <div class="mb-4 text-sm text-gray-600 space-y-1">
            <p>This will create the database <strong><?= DB_NAME ?></strong> and all required tables.</p>
            <p>Database host: <strong><?= DB_HOST ?></strong> | User: <strong><?= DB_USER ?></strong></p>
        </div>

        <form method="post">
            <label class="flex items-center gap-3 mb-6 cursor-pointer">
                <input type="checkbox" name="seed" id="seed" checked
                       class="w-5 h-5 accent-green-600 rounded">
                <span class="text-sm text-gray-700 font-medium">Also insert sample data (categories, products, demo users)</span>
            </label>
            <button type="submit"
                    class="w-full bg-brand-600 hover:bg-brand-700 text-white font-bold py-3 rounded-xl transition text-base">
                🚀 Run Setup Now
            </button>
        </form>
        <?php else: ?>
        <div class="text-center mt-4">
            <a href="login.php" class="inline-block bg-brand-600 hover:bg-brand-700 text-white font-bold px-8 py-3 rounded-xl transition">
                Go to Login →
            </a>
            <p class="text-xs text-gray-400 mt-4">⚠️ Please delete <code>setup.php</code> now for security.</p>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
