<?php
// includes/flash.php — write and render one-time flash messages

function set_flash(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function render_flash(): void {
    if (!isset($_SESSION['flash'])) return;
    $f    = $_SESSION['flash'];
    $type = $f['type'];   // success | error | warning | info
    $msg  = htmlspecialchars($f['message']);
    unset($_SESSION['flash']);

    $classes = [
        'success' => 'bg-emerald-50 border-emerald-400 text-emerald-800',
        'error'   => 'bg-red-50 border-red-400 text-red-800',
        'warning' => 'bg-amber-50 border-amber-400 text-amber-800',
        'info'    => 'bg-blue-50 border-blue-400 text-blue-800',
    ];
    $icons = [
        'success' => '✓',
        'error'   => '✕',
        'warning' => '⚠',
        'info'    => 'ℹ',
    ];
    $cls  = $classes[$type]  ?? $classes['info'];
    $icon = $icons[$type] ?? 'ℹ';

    echo <<<HTML
    <div id="flash-msg" class="flash-msg border-l-4 p-4 mb-4 rounded-lg flex items-center gap-3 {$cls}">
        <span class="flash-icon font-bold text-lg">{$icon}</span>
        <span>{$msg}</span>
        <button onclick="this.parentElement.remove()" class="ml-auto text-lg leading-none opacity-60 hover:opacity-100">&times;</button>
    </div>
    HTML;
}
