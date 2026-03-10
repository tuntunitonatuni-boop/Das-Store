// store.js — Online store: cart quantity controls, search filter

document.addEventListener('DOMContentLoaded', () => {
    // Qty stepper buttons
    document.querySelectorAll('[data-stepper]').forEach(wrapper => {
        const input = wrapper.querySelector('input[type=number]');
        wrapper.querySelector('[data-minus]')?.addEventListener('click', () => {
            input.value = Math.max(1, parseInt(input.value, 10) - 1);
        });
        wrapper.querySelector('[data-plus]')?.addEventListener('click', () => {
            input.value = parseInt(input.value, 10) + 1;
        });
    });

    // Product grid search filter (client-side)
    const searchInput = document.getElementById('product-search');
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const q = searchInput.value.toLowerCase();
            document.querySelectorAll('[data-product-card]').forEach(card => {
                const name = card.dataset.name?.toLowerCase() ?? '';
                card.style.display = name.includes(q) ? '' : 'none';
            });
        });
    }
});
