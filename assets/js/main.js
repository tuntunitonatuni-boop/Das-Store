// main.js — Global admin JS

// Auto-dismiss flash messages after 4 seconds
document.addEventListener('DOMContentLoaded', () => {
    const flash = document.getElementById('flash-msg');
    if (flash) {
        setTimeout(() => {
            flash.style.transition = 'opacity .4s';
            flash.style.opacity = '0';
            setTimeout(() => flash.remove(), 400);
        }, 4000);
    }

    // Confirm dangerous actions
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', e => {
            if (!confirm(el.dataset.confirm || 'Are you sure?')) e.preventDefault();
        });
    });

    // Auto-submit on select change where data-autosubmit is set
    document.querySelectorAll('select[data-autosubmit]').forEach(sel => {
        sel.addEventListener('change', () => sel.closest('form').submit());
    });
});
