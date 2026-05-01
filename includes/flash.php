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
        'success' => 'bg-emerald-600 text-white shadow-emerald-500/40',
        'error'   => 'bg-red-600 text-white shadow-red-500/40',
        'warning' => 'bg-amber-500 text-white shadow-amber-500/40',
        'info'    => 'bg-blue-600 text-white shadow-blue-500/40',
    ];
    $icons = [
        'success' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>',
        'error'   => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
        'warning' => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>',
        'info'    => '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
    ];
    $cls  = $classes[$type]  ?? $classes['info'];
    $icon = $icons[$type] ?? $icons['info'];

    echo <<<HTML
    <div id="toast-msg" style="animation: slideDown 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;" class="fixed top-6 left-1/2 -translate-x-1/2 z-[99999] flex items-center gap-3 px-5 py-3.5 rounded-2xl shadow-2xl {$cls} max-w-sm w-full md:max-w-md">
        <div class="flex-shrink-0 bg-white/20 p-2 rounded-full">
            {$icon}
        </div>
        <div class="text-[15px] font-bold tracking-wide leading-snug flex-1">{$msg}</div>
        <button onclick="this.closest('#toast-msg').style.display='none'" class="flex-shrink-0 ml-2 text-white/70 hover:text-white hover:bg-white/10 p-1.5 rounded-xl transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    </div>
    <style>
        @keyframes slideDown {
            0% { transform: translate(-50%, -100%); opacity: 0; }
            100% { transform: translate(-50%, 0); opacity: 1; }
        }
        @keyframes slideUpFade {
            0% { transform: translate(-50%, 0); opacity: 1; }
            100% { transform: translate(-50%, -20px); opacity: 0; }
        }
    </style>
    <script>
        setTimeout(() => {
            const toast = document.getElementById('toast-msg');
            if (toast) {
                toast.style.animation = 'slideUpFade 0.4s ease forwards';
                setTimeout(() => toast.remove(), 400);
            }
        }, 5000);
    </script>
    HTML;
}
