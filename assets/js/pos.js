// pos.js — POS barcode scanner, cart logic, receipt print

const POS = {
    cart: {},  // { productId: { name, price, qty, barcode, stock } }

    init() {
        this.barcodeInput = document.getElementById('barcode-input');
        this.resultsEl    = document.getElementById('pos-search-results');
        this.cartBody     = document.getElementById('cart-body');
        this.totalEl      = document.getElementById('cart-total');
        this.itemCountEl  = document.getElementById('cart-item-count');

        // Focus barcode field on load
        if (this.barcodeInput) this.barcodeInput.focus();

        // Live Search Input
        this.barcodeInput?.addEventListener('input', e => {
            const val = e.target.value.trim();
            clearTimeout(this._searchTimer);
            if (val.length < 2) {
                this.hideSuggestions();
                return;
            }
            this._searchTimer = setTimeout(() => this.showSuggestions(val), 300);
        });

        // Barcode enter key / Navigation
        this.barcodeInput?.addEventListener('keydown', e => {
            if (e.key === 'Enter') {
                e.preventDefault();
                // If there's a selected result, add it. Otherwise lookup.
                const selected = this.resultsEl.querySelector('.active-result');
                if (selected) {
                    selected.click();
                } else {
                    this.lookupBarcode();
                }
            }
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                this.navigateResults(1);
            }
            if (e.key === 'ArrowUp') {
                e.preventDefault();
                this.navigateResults(-1);
            }
            if (e.key === 'Escape') {
                this.hideSuggestions();
                this.barcodeInput.focus();
            }
        });

        // Hide suggestions on click outside
        document.addEventListener('click', e => {
            if (!this.resultsEl?.contains(e.target) && e.target !== this.barcodeInput) {
                this.hideSuggestions();
            }
        });

        // Clock
        this.startClock();

        // Load saved cart from sessionStorage
        const saved = sessionStorage.getItem('pos_cart');
        if (saved) { this.cart = JSON.parse(saved); this.renderCart(); }
    },

    async showSuggestions(q) {
        if (!q) return;
        console.log('Searching for:', q);
        try {
            const res = await fetch(`${BASE_URL}api/products.php?q=${encodeURIComponent(q)}`);
            if (!res.ok) throw new Error('Network response was not ok');
            const data = await res.json();
            console.log('Search results:', data);
            if (data.success && data.products && data.products.length > 0) {
                this.renderSuggestions(data.products);
            } else {
                this.hideSuggestions();
            }
        } catch (err) {
            console.error('Search error:', err);
        }
    },    renderSuggestions(products) {
        if (!this.resultsEl) return;
        this._currentSuggestions = products; // Store for selection
        this.resultsEl.innerHTML = products.map((p, i) => `
            <div onclick='POS.handleSelect(${i})' 
                 class="suggestion-item p-3 border-b border-gray-50 dark:border-gray-700 hover:bg-brand-50 dark:hover:bg-brand-900/40 cursor-pointer flex justify-between items-center transition ${i === 0 ? 'active-result bg-brand-50/50 dark:bg-brand-900/20' : ''}">
                <div>
                    <div class="text-sm font-bold text-gray-900 dark:text-gray-100">${this.esc(p.name)}</div>
                    <div class="text-[10px] text-gray-400 dark:text-gray-500 font-mono">${p.barcode || (POS_LANG.no_barcode || 'NO BARCODE')}</div>
                </div>
                <div class="text-right">
                    <div class="text-sm font-bold text-brand-600 dark:text-brand-400">${CURRENCY}${parseFloat(p.sale_price).toFixed(2)}</div>
                    <div class="text-[10px] ${p.stock > 0 ? 'text-green-500' : 'text-red-500'} font-bold">${POS_LANG.stock_label || 'Stock'}: ${p.stock || 0}</div>
                </div>
            </div>
        `).join('');
        this.resultsEl.classList.remove('hidden');
    },

    handleSelect(index) {
        const product = this._currentSuggestions[index];
        if (product) this.addToCart(product);
        this.hideSuggestions();
        this.barcodeInput.value = '';
        this.barcodeInput.focus();
    },

    hideSuggestions() {
        if (this.resultsEl) {
            this.resultsEl.classList.add('hidden');
            this.resultsEl.innerHTML = '';
        }
    },

    navigateResults(dir) {
        const items = this.resultsEl.querySelectorAll('.suggestion-item');
        if (!items.length) return;
        let activeIdx = Array.from(items).findIndex(it => it.classList.contains('active-result'));
        
        items.forEach(it => it.classList.remove('active-result', 'bg-brand-50/50', 'dark:bg-brand-900/20'));
        
        activeIdx += dir;
        if (activeIdx < 0) activeIdx = items.length - 1;
        if (activeIdx >= items.length) activeIdx = 0;
        
        items[activeIdx].classList.add('active-result', 'bg-brand-50/50', 'dark:bg-brand-900/20');
        items[activeIdx].scrollIntoView({ block: 'nearest' });
    },

    async lookupBarcode() {
        this.hideSuggestions();
        const bc = this.barcodeInput.value.trim();
        if (!bc) return;
        this.barcodeInput.value = '';
        this.barcodeInput.classList.add('opacity-50');

        try {
            const res = await fetch(BASE_URL + 'api/products.php?barcode=' + encodeURIComponent(bc));
            const data = await res.json();
            if (data.success && data.product) {
                this.addToCart(data.product);
            } else {
                this.showAlert((POS_LANG.not_found || 'Product not found') + ': ' + bc, 'error');
            }
        } catch (err) {
            this.showAlert(POS_LANG.net_error || 'Network error', 'error');
        } finally {
            this.barcodeInput.classList.remove('opacity-50');
            this.barcodeInput.focus();
        }
    },

    addToCart(product) {
        const id = product.id;
        if (this.cart[id]) {
            if (this.cart[id].qty >= product.stock) {
                this.showAlert(POS_LANG.no_stock || 'Not enough stock!', 'error'); return;
            }
            this.cart[id].qty++;
        } else {
            this.cart[id] = { id, name: product.name, price: parseFloat(product.sale_price), qty: 1, stock: product.stock, unit: product.unit || 'pcs' };
        }
        this.saveCart(); this.renderCart();
        this.showAlert(product.name + ' ' + (POS_LANG.added || 'added'), 'success');
    },

    removeFromCart(id) { delete this.cart[id]; this.saveCart(); this.renderCart(); },

    updateQty(id, qty) {
        qty = parseInt(qty, 10);
        if (qty <= 0) { this.removeFromCart(id); return; }
        if (qty > (this.cart[id]?.stock ?? 9999)) { this.showAlert(POS_LANG.no_stock || 'Not enough stock', 'error'); return; }
        this.cart[id].qty = qty;
        this.saveCart(); this.renderCart();
    },

    renderCart() {
        if (!this.cartBody) return;
        const items = Object.values(this.cart);
        this.cartBody.innerHTML = items.length === 0
            ? `<tr><td colspan="6" class="text-center text-gray-400 py-8">${POS_LANG.cart_empty || 'Cart is empty'}</td></tr>`
            : items.map((it, i) => `
                <tr class="border-b border-gray-100 dark:border-gray-700/50 hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition-colors">
                    <td class="py-3 px-3 text-xs text-gray-400">${i+1}</td>
                    <td class="py-3 px-3 text-sm font-medium dark:text-gray-200">${this.esc(it.name)}</td>
                    <td class="py-3 px-3 text-sm text-right text-gray-500 dark:text-gray-400">${CURRENCY}${it.price.toFixed(2)}</td>
                    <td class="py-3 px-3">
                        <input type="number" value="${it.qty}" min="1" max="${it.stock}"
                                onchange="POS.updateQty(${it.id}, this.value)"
                                class="w-16 text-center border border-gray-200 dark:border-gray-700 dark:bg-gray-800 dark:text-white rounded-lg text-sm py-1 outline-none focus:border-brand-500">
                    </td>
                    <td class="py-3 px-3 text-sm text-right font-bold text-gray-900 dark:text-white">${CURRENCY}${(it.price * it.qty).toFixed(2)}</td>
                    <td class="py-3 px-1 text-center">
                        <button onclick="POS.removeFromCart(${it.id})" class="text-red-300 hover:text-red-500 text-xl leading-none transition-colors">×</button>
                    </td>
                </tr>`).join('');

        const total = items.reduce((s, it) => s + it.price * it.qty, 0);
        if (this.totalEl) this.totalEl.textContent = CURRENCY + total.toFixed(2);
        if (this.itemCountEl) this.itemCountEl.textContent = items.reduce((s, it) => s + it.qty, 0);
    },

    getTotal() { return Object.values(this.cart).reduce((s, it) => s + it.price * it.qty, 0); },

    clearCart() { this.cart = {}; this.saveCart(); this.renderCart(); },

    saveCart() { sessionStorage.setItem('pos_cart', JSON.stringify(this.cart)); },

    async submitSale(paymentMethod, amountPaid, customerId) {
        const items = Object.values(this.cart);
        if (!items.length) { this.showAlert(POS_LANG.cart_empty || 'Cart is empty!', 'error'); return; }
        const total = this.getTotal();
        const res = await fetch(BASE_URL + 'api/orders.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ items, total, payment_method: paymentMethod, amount_paid: amountPaid, customer_id: customerId })
        });
        const data = await res.json();
        if (data.success) {
            this.printReceipt(data.sale_id, items, total, paymentMethod, amountPaid, customerId);
            this.clearCart();
        } else {
            this.showAlert(data.message || (POS_LANG.failed || 'Sale failed'), 'error');
        }
    },

    printReceipt(saleId, items, total, method, paid, customerId) {
        const change = Math.max(0, paid - total);
        const lines = items.map(it => `${it.name.padEnd(20)} x${it.qty}  ${CURRENCY}${(it.price*it.qty).toFixed(2)}`).join('\n');
        const win = window.open('', '_blank', 'width=300,height=600');
        win.document.write(`<pre style="font-family:monospace;font-size:12px;padding:8px">
================================
        ${SHOP_NAME}
================================
Sale #${saleId}   ${new Date().toLocaleString()}
--------------------------------
${lines}
--------------------------------
TOTAL:       ${CURRENCY}${total.toFixed(2)}
PAID (${method}): ${CURRENCY}${parseFloat(paid).toFixed(2)}
CHANGE:      ${CURRENCY}${change.toFixed(2)}
================================
   ${RECEIPT_FOOTER}
================================
</pre>`);
        win.document.close();
        win.print();
    },

    showAlert(msg, type='success') {
        const el = document.getElementById('pos-alert');
        if (!el) return;
        el.textContent = msg;
        el.className = `fixed top-16 right-4 z-50 px-4 py-2 rounded-xl text-sm font-medium shadow-lg transition
            ${type === 'success' ? 'bg-emerald-500 text-white' : 'bg-red-500 text-white'}`;
        el.style.display = 'block';
        clearTimeout(this._alertTimer);
        this._alertTimer = setTimeout(() => { el.style.display = 'none'; }, 2500);
    },
; }, 2500);
    },

    startClock() {
        const el = document.getElementById('pos-clock');
        if (!el) return;
        const tick = () => { el.textContent = new Date().toLocaleTimeString(); };
        tick(); setInterval(tick, 1000);
    },

    esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
};

// Globals injected by pos.php
var BASE_URL = window.BASE_URL || '/';
var SHOP_NAME = window.SHOP_NAME || 'Store';
var RECEIPT_FOOTER = window.RECEIPT_FOOTER || '';

document.addEventListener('DOMContentLoaded', () => POS.init());
