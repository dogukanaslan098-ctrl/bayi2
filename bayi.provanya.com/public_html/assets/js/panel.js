/**
 * B2B Bayi Panel — Ana JavaScript
 */

// ── SIDEBAR TOGGLE ────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const toggle   = document.getElementById('menuToggle');
    const sidebar  = document.getElementById('sidebar');
    const overlay  = document.getElementById('sidebarOverlay');
    const closeBtn = document.getElementById('sidebarClose');

    const openSidebar  = () => { sidebar?.classList.add('open'); overlay?.classList.add('show'); };
    const closeSidebar = () => { sidebar?.classList.remove('open'); overlay?.classList.remove('show'); };

    toggle?.addEventListener('click', openSidebar);
    closeBtn?.addEventListener('click', closeSidebar);
    overlay?.addEventListener('click', closeSidebar);

    // Bar chart oluştur
    document.querySelectorAll('[data-values]').forEach(initChart);

    // Flash mesajları otomatik gizle
    setTimeout(() => {
        document.querySelectorAll('.flash').forEach(el => el.remove());
    }, 5000);
});

// ── MINI BAR CHART ───────────────────────────────────────
function initChart(el) {
    const values = JSON.parse(el.dataset.values || '[]');
    const labels = JSON.parse(el.dataset.labels || '[]');
    if (!values.length) return;

    const max = Math.max(...values, 1);

    el.innerHTML = values.map((v, i) => {
        const pct   = Math.round((v / max) * 100);
        const label = labels[i] || '';
        return `<div class="chart-bar" style="height:${pct}%;opacity:${i === values.length - 1 ? 1 : 0.4};background:${i === values.length - 1 ? 'var(--green)' : 'var(--blue)'}" title="${label}: ₺${Number(v).toLocaleString('tr-TR')}"></div>`;
    }).join('');
}

// ── GLOBAL SEPET ─────────────────────────────────────────
const Cart = {
    _items: {},

    add(product, qty = 1) {
        const id = product.wc_id;
        if (this._items[id]) {
            this._items[id].quantity += qty;
        } else {
            this._items[id] = { ...product, quantity: qty };
        }
        this._persist();
        Toast.show(`🛒 ${product.name} sepete eklendi`);
        this._updateBadge();
    },

    remove(wcId) {
        delete this._items[wcId];
        this._persist();
        this._updateBadge();
    },

    setQty(wcId, qty) {
        if (this._items[wcId]) {
            this._items[wcId].quantity = Math.max(1, qty);
            this._persist();
        }
    },

    getItems() { return Object.values(this._items); },
    getCount()  { return Object.keys(this._items).length; },

    clear() { this._items = {}; this._persist(); this._updateBadge(); },

    totals(discountRate = 0) {
        let subtotal = 0;
        this.getItems().forEach(item => {
            subtotal += item.unit_price * item.quantity;
        });
        const discount = subtotal * discountRate;
        const taxBase  = subtotal - discount;
        const tax      = taxBase * 0.20;
        const total    = taxBase + tax;
        return { subtotal, discount, tax, total };
    },

    _persist() {
        try { sessionStorage.setItem('b2b_cart', JSON.stringify(this._items)); } catch(e) {}
    },

    _load() {
        try { this._items = JSON.parse(sessionStorage.getItem('b2b_cart') || '{}'); } catch(e) {}
    },

    _updateBadge() {
        const count = this.getCount();
        document.querySelectorAll('.cart-count').forEach(el => {
            el.textContent = count;
            el.style.display = count > 0 ? 'inline' : 'none';
        });
    }
};

Cart._load();

// ── TOAST BİLDİRİM ───────────────────────────────────────
const Toast = {
    _el: null,

    show(msg, type = 'info', duration = 3000) {
        if (!this._el) {
            this._el = document.createElement('div');
            this._el.style.cssText = `
                position:fixed;bottom:24px;right:24px;
                background:#1e293b;color:#fff;padding:12px 18px;
                border-radius:10px;font-size:13px;font-weight:500;
                z-index:9999;transition:all .3s;opacity:0;transform:translateY(10px);
                font-family:-apple-system,sans-serif;max-width:320px;
                box-shadow:0 4px 12px rgba(0,0,0,.3)
            `;
            document.body.appendChild(this._el);
        }
        this._el.textContent = msg;
        this._el.style.opacity = '1';
        this._el.style.transform = 'translateY(0)';

        clearTimeout(this._timer);
        this._timer = setTimeout(() => {
            this._el.style.opacity = '0';
            this._el.style.transform = 'translateY(10px)';
        }, duration);
    }
};

// ── SİPARİŞ GÖNDERİMİ ────────────────────────────────────
async function submitOrder(cartItems, paymentMethod) {
    const btn = document.getElementById('submitOrderBtn');
    if (btn) { btn.disabled = true; btn.textContent = 'Gönderiliyor...'; }

    try {
        const res = await fetch('/siparis/olustur', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('[name="_csrf"]')?.value || '',
            },
            body: JSON.stringify({
                items: cartItems,
                payment_method: paymentMethod,
            }),
        });

        const data = await res.json();

        if (data.success) {
            Cart.clear();
            Toast.show(`✅ Sipariş #${data.order_number} oluşturuldu!`, 'success', 4000);

            if (data.stock_issues?.length) {
                Toast.show(`⚠ ${data.stock_issues.length} üründe stok sorunu — sipariş beklemeye alındı`, 'warning', 5000);
            }

            setTimeout(() => { window.location.href = '/siparislerim'; }, 1500);
        } else {
            Toast.show('❌ ' + (data.message || 'Sipariş oluşturulamadı'), 'error');
            if (btn) { btn.disabled = false; btn.textContent = 'Siparişi Onayla →'; }
        }

    } catch (err) {
        Toast.show('❌ Bağlantı hatası. Lütfen tekrar deneyin.', 'error');
        if (btn) { btn.disabled = false; btn.textContent = 'Siparişi Onayla →'; }
    }
}

// ── ÜRÜN ARAMA DEBOUNCE ───────────────────────────────────
let searchTimer;
function debounceSearch(input, delay = 400) {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        input.form?.submit();
    }, delay);
}

// ── EXCEL YÜKLEME ─────────────────────────────────────────
function initExcelUpload() {
    const zone   = document.getElementById('uploadZone');
    const input  = document.getElementById('excelFile');
    const result = document.getElementById('excelResult');

    if (!zone) return;

    zone.addEventListener('click', () => input?.click());

    zone.addEventListener('dragover', e => {
        e.preventDefault();
        zone.classList.add('drag-over');
    });

    zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));

    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.classList.remove('drag-over');
        const file = e.dataTransfer.files[0];
        if (file) processExcelFile(file, result);
    });

    input?.addEventListener('change', () => {
        if (input.files[0]) processExcelFile(input.files[0], result);
    });
}

async function processExcelFile(file, resultEl) {
    resultEl.innerHTML = '<div style="text-align:center;padding:20px;color:var(--blue);font-weight:500">📊 İşleniyor...</div>';

    const formData = new FormData();
    formData.append('file', file);
    formData.append('_csrf', document.querySelector('[name="_csrf"]')?.value || '');

    try {
        const res  = await fetch('/excel/isle', { method: 'POST', body: formData });
        const data = await res.json();

        if (!data.success) {
            resultEl.innerHTML = `<div class="alert alert-error">❌ ${data.errors?.[0] || 'Hata'}</div>`;
            return;
        }

        renderExcelResult(data, resultEl);

    } catch (err) {
        resultEl.innerHTML = '<div class="alert alert-error">❌ Bağlantı hatası</div>';
    }
}

function renderExcelResult(data, container) {
    const errorHtml = data.errors?.length
        ? `<div style="margin-bottom:12px;padding:10px;background:#fef2f2;border-radius:6px;font-size:12px;color:#b91c1c">
             ${data.errors.map(e => `⚠ ${e}`).join('<br>')}
           </div>` : '';

    const rows = data.items.map(item => `
        <tr>
            <td><span class="sku-tag">${item.sku}</span></td>
            <td>${item.name}</td>
            <td style="text-align:center">${item.quantity}</td>
            <td style="text-align:right">₺${Number(item.stock).toLocaleString('tr-TR')}</td>
            <td>${item.in_stock
                ? '<span class="badge badge-green">✓ Stokta</span>'
                : '<span class="badge badge-amber">⚠ Stok az</span>'}</td>
        </tr>
    `).join('');

    container.innerHTML = `
        ${errorHtml}
        <div style="margin-bottom:10px">
            <span class="badge badge-green">${data.count} ürün bulundu</span>
            ${data.warnings?.length ? `<span class="badge badge-amber" style="margin-left:6px">${data.warnings.length} uyarı</span>` : ''}
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>SKU</th><th>Ürün Adı</th><th>Adet</th><th>Stok</th><th>Durum</th></tr></thead>
                <tbody>${rows}</tbody>
            </table>
        </div>
        <button onclick="importToCart(${JSON.stringify(data.items).replace(/"/g, '&quot;')})" class="btn btn-primary" style="margin-top:14px;width:100%;justify-content:center">
            Sepete Aktar (${data.count} ürün) →
        </button>
    `;
}

function importToCart(items) {
    items.forEach(item => {
        Cart.add({
            wc_id:      item.wc_id,
            sku:        item.sku,
            name:       item.name,
            unit_price: item.unit_price,
        }, item.quantity);
    });
    Toast.show(`✅ ${items.length} ürün sepete eklendi`);
    setTimeout(() => { window.location.href = '/siparis'; }, 1200);
}

// ── SAYFA BAZLI INIT ──────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    initExcelUpload();
    Cart._updateBadge();
});
