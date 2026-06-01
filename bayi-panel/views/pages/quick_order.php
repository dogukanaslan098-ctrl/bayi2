<?php
// views/pages/quick_order.php
use Auth\Auth;
use Helpers\Database;

Auth::require();

$dealerId = Auth::id();
$discount = Auth::effectiveDiscount();

$unreadNotifCount  = (int)Database::scalar(
    "SELECT COUNT(*) FROM notifications WHERE dealer_id = ? AND is_read = 0", [$dealerId]
);
$pendingOrderCount = (int)Database::scalar(
    "SELECT COUNT(*) FROM orders WHERE dealer_id = ? AND status IN ('pending','processing')", [$dealerId]
);

$pageTitle  = 'Hızlı Sipariş';
$activeMenu = 'quickorder';

ob_start();
?>

<input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

<div class="two-col">
  <!-- Sol: SKU Arama + Sepet Tablosu -->
  <div>
    <div class="card" style="margin-bottom:16px">
      <div class="card-header">
        <span class="card-title">⚡ SKU ile Ürün Ekle</span>
        <a href="/excel-siparis" class="btn btn-outline btn-sm">📥 Excel ile Yükle</a>
      </div>
      <div class="card-body" style="padding-bottom:12px">
        <div style="display:flex;gap:8px">
          <div style="position:relative;flex:1">
            <input type="text" id="skuInput" class="form-control"
                   placeholder="SKU girin: ör. PRV-001"
                   autocomplete="off"
                   oninput="skuAutocomplete(this.value)">
            <div id="autocompleteList" style="position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid var(--border);border-radius:0 0 8px 8px;box-shadow:var(--shadow-md);z-index:50;display:none;max-height:220px;overflow-y:auto"></div>
          </div>
          <input type="number" id="qtyInput" class="form-control" style="width:80px" value="1" min="1" placeholder="Adet">
          <button class="btn btn-primary" onclick="addSkuToCart()">Ekle</button>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <span class="card-title">Sipariş Sepeti</span>
        <button class="btn btn-outline btn-sm" onclick="clearCart()">Temizle</button>
      </div>
      <div class="table-responsive" id="cartTableWrap">
        <table class="data-table" id="cartTable">
          <thead>
            <tr><th>Ürün</th><th>SKU</th><th style="text-align:right">Birim</th><th style="text-align:center">Adet</th><th style="text-align:right">Toplam</th><th></th></tr>
          </thead>
          <tbody id="cartBody">
            <tr id="emptyRow"><td colspan="6" class="empty-state">Sepet boş. SKU ile ürün ekleyin veya katalogdan seçin.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Sağ: Sipariş Özeti + Ödeme -->
  <div>
    <div class="card" id="orderSummaryCard">
      <div class="card-header"><span class="card-title">Sipariş Özeti</span></div>
      <div class="card-body" id="orderSummaryBody">
        <div class="empty-state" style="padding:30px">Sepette ürün yok</div>
      </div>
    </div>
  </div>
</div>

<script>
const DISCOUNT = <?= $discount ?>;
const KDV      = 0.20;

// Ürün cache (autocomplete için)
let productCache = null;

async function loadProducts() {
    if (productCache) return productCache;
    try {
        const res  = await fetch('/api/urunler?limit=500');
        const data = await res.json();
        productCache = data.data || [];
    } catch(e) { productCache = []; }
    return productCache;
}

// Autocomplete
let acTimer;
async function skuAutocomplete(q) {
    const list = document.getElementById('autocompleteList');
    if (!q || q.length < 2) { list.style.display = 'none'; return; }

    clearTimeout(acTimer);
    acTimer = setTimeout(async () => {
        const products = await loadProducts();
        const matches = products.filter(p =>
            p.sku.toLowerCase().includes(q.toLowerCase()) ||
            p.name.toLowerCase().includes(q.toLowerCase())
        ).slice(0, 6);

        if (!matches.length) { list.style.display = 'none'; return; }

        list.innerHTML = matches.map(p => `
            <div onclick="selectProduct(${JSON.stringify(p).replace(/"/g,'&quot;')})"
                 style="padding:10px 14px;cursor:pointer;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center;font-size:13px"
                 onmouseover="this.style.background='#f0f9ff'" onmouseout="this.style.background='#fff'">
                <div>
                    <span style="font-weight:600">${p.sku}</span>
                    <span style="color:var(--text2);margin-left:8px">${p.name}</span>
                </div>
                <div style="display:flex;align-items:center;gap:10px">
                    <span style="color:var(--green);font-weight:600">₺${Number(p.dealer_price).toFixed(2)}</span>
                    <span class="badge ${p.in_stock ? 'badge-green' : 'badge-red'}" style="font-size:10px">${p.in_stock ? p.stock_qty+' adet' : 'Yok'}</span>
                </div>
            </div>
        `).join('');

        list.style.display = 'block';
    }, 200);
}

function selectProduct(p) {
    document.getElementById('skuInput').value = p.sku;
    document.getElementById('autocompleteList').style.display = 'none';
    document.getElementById('qtyInput').focus();
}

// SKU'yu sepete ekle
async function addSkuToCart() {
    const sku = document.getElementById('skuInput').value.trim().toUpperCase();
    const qty = parseInt(document.getElementById('qtyInput').value) || 1;

    if (!sku) { Toast.show('⚠ SKU giriniz'); return; }

    const products = await loadProducts();
    const product  = products.find(p => p.sku.toUpperCase() === sku);

    if (!product) {
        Toast.show('❌ SKU bulunamadı: ' + sku);
        return;
    }
    if (!product.in_stock) {
        Toast.show('❌ Stokta yok: ' + product.name);
        return;
    }

    Cart.add({
        wc_id:      product.wc_id,
        sku:        product.sku,
        name:       product.name,
        unit_price: product.price,
    }, qty);

    document.getElementById('skuInput').value = '';
    document.getElementById('qtyInput').value = 1;
    document.getElementById('autocompleteList').style.display = 'none';

    renderCartTable();
    renderSummary();
}

// Sepet tablosu render
function renderCartTable() {
    const body  = document.getElementById('cartBody');
    const items = Cart.getItems();

    if (!items.length) {
        body.innerHTML = '<tr id="emptyRow"><td colspan="6" class="empty-state">Sepet boş. SKU ile ürün ekleyin veya katalogdan seçin.</td></tr>';
        return;
    }

    body.innerHTML = items.map(item => {
        const dealerPrice = item.unit_price * (1 - DISCOUNT);
        const lineTotal   = dealerPrice * item.quantity;
        return `<tr>
            <td style="max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${item.name}</td>
            <td><span class="sku-tag">${item.sku}</span></td>
            <td style="text-align:right">₺${dealerPrice.toFixed(2)}</td>
            <td style="text-align:center">
                <input type="number" min="1" value="${item.quantity}"
                       style="width:60px;padding:4px 6px;border:1px solid var(--border);border-radius:5px;text-align:center;font-size:13px"
                       onchange="Cart.setQty(${item.wc_id}, parseInt(this.value)||1); renderCartTable(); renderSummary()">
            </td>
            <td style="text-align:right;font-weight:600">₺${lineTotal.toFixed(2)}</td>
            <td>
                <button onclick="Cart.remove(${item.wc_id}); renderCartTable(); renderSummary()"
                        style="background:none;border:none;cursor:pointer;color:var(--red);font-size:16px;padding:2px 6px">×</button>
            </td>
        </tr>`;
    }).join('');
}

// Özet ve ödeme formu render
function renderSummary() {
    const body  = document.getElementById('orderSummaryBody');
    const items = Cart.getItems();

    if (!items.length) {
        body.innerHTML = '<div class="empty-state" style="padding:30px">Sepette ürün yok</div>';
        return;
    }

    const t = Cart.totals(DISCOUNT);

    body.innerHTML = `
        <div style="margin-bottom:20px">
            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:8px">
                <span style="color:var(--text2)">Ara Toplam</span>
                <span>₺${t.subtotal.toFixed(2)}</span>
            </div>
            ${DISCOUNT > 0 ? `<div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:8px;color:var(--green)">
                <span>İndirim (%${Math.round(DISCOUNT*100)})</span>
                <span>-₺${t.discount.toFixed(2)}</span>
            </div>` : ''}
            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:12px">
                <span style="color:var(--text2)">KDV (%20)</span>
                <span>₺${t.tax.toFixed(2)}</span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:16px;font-weight:700;border-top:1px solid var(--border);padding-top:12px">
                <span>TOPLAM</span>
                <span style="color:var(--blue)">₺${t.total.toFixed(2)}</span>
            </div>
        </div>

        <div style="margin-bottom:20px">
            <div style="font-size:12px;font-weight:600;color:var(--text2);margin-bottom:10px;text-transform:uppercase">Ödeme Yöntemi</div>
            <label style="display:flex;align-items:center;gap:8px;margin-bottom:10px;font-size:13px;cursor:pointer">
                <input type="radio" name="payment" value="card" checked> 💳 Kredi / Banka Kartı
            </label>
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
                <input type="radio" name="payment" value="eft"> 🏦 Havale / EFT
            </label>
        </div>

        <div id="eftInfo" style="display:none;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:12px;font-size:12px;margin-bottom:16px">
            <div style="font-weight:600;margin-bottom:6px;color:#92400e">EFT Hesap Bilgileri</div>
            <div>Banka: Ziraat Bankası</div>
            <div>IBAN: TR00 0001 0001 0001 0001 0001 00</div>
            <div>Hesap Adı: Provanya Tic. A.Ş.</div>
            <div style="margin-top:6px;color:#92400e">Açıklama olarak sipariş numaranızı yazınız.</div>
        </div>

        <button id="submitOrderBtn" class="btn btn-primary" style="width:100%;justify-content:center;padding:12px;font-size:14px"
                onclick="placeOrder()">
            Siparişi Onayla →
        </button>
        <div style="font-size:11px;color:var(--text2);text-align:center;margin-top:8px">
            ${items.length} kalem — ${Cart.getCount()} ürün
        </div>
    `;

    // EFT açıklama toggle
    document.querySelectorAll('[name="payment"]').forEach(radio => {
        radio.addEventListener('change', () => {
            const eftInfo = document.getElementById('eftInfo');
            if (eftInfo) eftInfo.style.display = radio.value === 'eft' ? 'block' : 'none';
        });
    });
}

function clearCart() {
    if (!confirm('Sepeti temizlemek istediğinizden emin misiniz?')) return;
    Cart.clear();
    renderCartTable();
    renderSummary();
}

async function placeOrder() {
    const items = Cart.getItems().map(item => ({
        wc_id:      item.wc_id,
        sku:        item.sku,
        name:       item.name,
        quantity:   item.quantity,
        unit_price: item.unit_price,
    }));

    const payment = document.querySelector('[name="payment"]:checked')?.value || 'card';
    await submitOrder(items, payment);
}

// Sayfa yüklenince
document.addEventListener('DOMContentLoaded', () => {
    renderCartTable();
    renderSummary();
    loadProducts(); // Preload

    // Dışarı tıklayınca autocomplete kapat
    document.addEventListener('click', e => {
        if (!e.target.closest('#skuInput') && !e.target.closest('#autocompleteList')) {
            document.getElementById('autocompleteList').style.display = 'none';
        }
    });

    // Enter ile ekleme
    document.getElementById('skuInput')?.addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); addSkuToCart(); }
    });
    document.getElementById('qtyInput')?.addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); addSkuToCart(); }
    });
});
</script>

<?php
$content = ob_get_clean();
include ROOT_PATH . '/views/layouts/app.php';
