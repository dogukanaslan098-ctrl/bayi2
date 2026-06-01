<?php
// views/pages/products.php
use Auth\Auth;
use Helpers\Database;
use Auth\Auth as AuthService;

Auth::require();

$dealerId = Auth::id();
$discount = AuthService::effectiveDiscount();

// Filtreler
$q       = trim($_GET['q']    ?? '');
$cat     = trim($_GET['cat']  ?? '');
$stock   = trim($_GET['stock'] ?? '');
$sort    = trim($_GET['sort']  ?? 'name_asc');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 24;
$offset  = ($page - 1) * $perPage;

// SQL oluştur
$where  = ['1=1'];
$params = [];

if ($q) {
    $where[]  = "(p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)";
    $like     = "%{$q}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($cat)   { $where[] = "p.category = ?"; $params[] = $cat; }
if ($stock === 'instock')  { $where[] = "p.stock_qty > 0"; }
if ($stock === 'low')      { $where[] = "p.stock_qty BETWEEN 1 AND 10"; }
if ($stock === 'nostock')  { $where[] = "p.stock_qty = 0"; }

$sortMap = [
    'name_asc'    => 'p.name ASC',
    'name_desc'   => 'p.name DESC',
    'price_asc'   => 'p.price ASC',
    'price_desc'  => 'p.price DESC',
    'stock_desc'  => 'p.stock_qty DESC',
];
$orderBy = $sortMap[$sort] ?? 'p.name ASC';
$whereStr = implode(' AND ', $where);

$total    = (int)Database::scalar("SELECT COUNT(*) FROM product_cache p WHERE {$whereStr}", $params);
$products = Database::fetchAll(
    "SELECT * FROM product_cache p WHERE {$whereStr} ORDER BY {$orderBy} LIMIT {$perPage} OFFSET {$offset}",
    $params
);

// Kategori listesi
$categories = Database::fetchAll(
    "SELECT DISTINCT category FROM product_cache WHERE category IS NOT NULL ORDER BY category"
);

$totalPages = (int)ceil($total / $perPage);

// Bildirim sayısı
$unreadNotifCount = (int)Database::scalar(
    "SELECT COUNT(*) FROM notifications WHERE dealer_id = ? AND is_read = 0", [$dealerId]
);
$pendingOrderCount = (int)Database::scalar(
    "SELECT COUNT(*) FROM orders WHERE dealer_id = ? AND status IN ('pending','processing')", [$dealerId]
);

$pageTitle  = 'Ürün Kataloğu';
$activeMenu = 'products';

ob_start();
?>

<!-- Filtre Satırı -->
<div class="filter-row" style="margin-bottom:20px">
  <form method="GET" action="/urunler" style="display:contents">
    <input type="hidden" name="q" value="<?= e($q) ?>">

    <select name="cat" class="form-control form-select" style="width:160px" onchange="this.form.submit()">
      <option value="">Tüm Kategoriler</option>
      <?php foreach ($categories as $c): ?>
        <option value="<?= e($c['category']) ?>" <?= $cat === $c['category'] ? 'selected' : '' ?>>
          <?= e($c['category']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select name="stock" class="form-control form-select" style="width:160px" onchange="this.form.submit()">
      <option value="">Stok: Tümü</option>
      <option value="instock" <?= $stock === 'instock' ? 'selected' : '' ?>>Stokta Var</option>
      <option value="low"     <?= $stock === 'low'     ? 'selected' : '' ?>>Stok Az (&lt;10)</option>
      <option value="nostock" <?= $stock === 'nostock' ? 'selected' : '' ?>>Tükendi</option>
    </select>

    <select name="sort" class="form-control form-select" style="width:160px" onchange="this.form.submit()">
      <option value="name_asc"   <?= $sort === 'name_asc'   ? 'selected' : '' ?>>İsim: A→Z</option>
      <option value="name_desc"  <?= $sort === 'name_desc'  ? 'selected' : '' ?>>İsim: Z→A</option>
      <option value="price_asc"  <?= $sort === 'price_asc'  ? 'selected' : '' ?>>Fiyat: Artan</option>
      <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Fiyat: Azalan</option>
      <option value="stock_desc" <?= $sort === 'stock_desc' ? 'selected' : '' ?>>Stok: Çok→Az</option>
    </select>
  </form>

  <div style="margin-left:auto;font-size:12px;color:var(--text2);display:flex;align-items:center;gap:12px">
    <span><?= number_format($total) ?> ürün</span>
    <?php if ($q || $cat || $stock): ?>
      <a href="/urunler" class="btn btn-outline btn-sm">✕ Filtreyi Temizle</a>
    <?php endif; ?>
  </div>
</div>

<!-- İndirim Bilgisi -->
<?php if ($discount > 0): ?>
<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:10px 16px;margin-bottom:16px;font-size:13px;color:#15803d;display:flex;align-items:center;gap:8px">
  🎁 <strong>Aktif İndiriminiz: %<?= number_format($discount * 100, 0) ?></strong>
  — Aşağıdaki tüm ürünlerde bayi fiyatı otomatik uygulanmaktadır.
</div>
<?php endif; ?>

<!-- Ürün Grid -->
<?php if (empty($products)): ?>
  <div class="card" style="padding:60px;text-align:center;color:var(--text2)">
    <div style="font-size:40px;margin-bottom:12px">📦</div>
    <div style="font-size:15px;font-weight:600">Ürün bulunamadı</div>
    <div style="font-size:13px;margin-top:6px">Arama kriterlerinizi değiştirmeyi deneyin</div>
    <?php if ($q || $cat || $stock): ?>
      <a href="/urunler" class="btn btn-outline" style="margin-top:16px">Tüm Ürünleri Göster</a>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="product-grid" id="productGrid">
    <?php foreach ($products as $p):
      $dealerPrice = round((float)$p['price'] * (1 - $discount), 2);
      $inStock     = (int)$p['stock_qty'] > 0;
      $stockLow    = $inStock && (int)$p['stock_qty'] <= 10;
      $stockColor  = !$inStock ? 'var(--red)' : ($stockLow ? 'var(--amber)' : 'var(--green)');
      $stockLabel  = !$inStock ? 'Stok Yok' : ($stockLow ? $p['stock_qty'].' adet (Az)' : $p['stock_qty'].' adet');
    ?>
      <div class="product-card">
        <div class="product-img">
          <?php if ($p['image_url']): ?>
            <img src="<?= e($p['image_url']) ?>" alt="<?= e($p['name']) ?>" style="width:100%;height:100%;object-fit:cover">
          <?php else: ?>
            📦
          <?php endif; ?>
        </div>
        <div class="product-body">
          <div class="product-name"><?= e($p['name']) ?></div>
          <div class="product-meta">
            <span class="sku-tag"><?= e($p['sku']) ?></span>
            <?php if ($p['barcode']): ?>
              &nbsp;· Barkod: <code style="font-size:11px"><?= e($p['barcode']) ?></code>
            <?php endif; ?>
          </div>

          <div class="price-row" style="margin-bottom:8px">
            <div>
              <?php if ($discount > 0): ?>
                <div class="price-normal">₺<?= number_format((float)$p['price'], 2, ',', '.') ?></div>
              <?php endif; ?>
              <div class="price-dealer">₺<?= number_format($dealerPrice, 2, ',', '.') ?></div>
            </div>
            <div class="stock-info">
              <div class="stock-dot" style="background:<?= $stockColor ?>"></div>
              <span style="color:<?= $stockColor ?>"><?= $stockLabel ?></span>
            </div>
          </div>

          <?php if ($inStock): ?>
            <div class="qty-row">
              <button class="qty-btn" onclick="changeQty('<?= $p['wc_id'] ?>', -1)">−</button>
              <input class="qty-input" type="number" min="1" max="<?= (int)$p['stock_qty'] ?>"
                     value="1" id="qty-<?= $p['wc_id'] ?>">
              <button class="qty-btn" onclick="changeQty('<?= $p['wc_id'] ?>', 1)">+</button>
              <button class="add-to-cart" onclick="addToCart(<?= htmlspecialchars(json_encode([
                  'wc_id'      => (int)$p['wc_id'],
                  'sku'        => $p['sku'],
                  'name'       => $p['name'],
                  'unit_price' => (float)$p['price'],
              ]), ENT_QUOTES) ?>)">
                + Sepet
              </button>
            </div>
          <?php else: ?>
            <div style="text-align:center;padding:8px 0;font-size:12px;color:var(--red);font-weight:600">
              Stokta Yok
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Sayfalama -->
  <?php if ($totalPages > 1): ?>
  <div style="display:flex;justify-content:center;gap:6px;margin-top:24px">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <?php
        $params2 = array_merge($_GET, ['page' => $i]);
        $href = '/urunler?' . http_build_query($params2);
      ?>
      <a href="<?= $href ?>"
         class="btn <?= $i === $page ? 'btn-primary' : 'btn-outline' ?> btn-sm">
        <?= $i ?>
      </a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
<?php endif; ?>

<script>
function changeQty(id, delta) {
    const input = document.getElementById('qty-' + id);
    if (!input) return;
    const max = parseInt(input.max) || 9999;
    input.value = Math.max(1, Math.min(max, parseInt(input.value || 1) + delta));
}

function addToCart(product) {
    const input = document.getElementById('qty-' + product.wc_id);
    const qty   = parseInt(input?.value || 1);
    Cart.add(product, qty);
    if (input) input.value = 1;
}
</script>

<?php
$content = ob_get_clean();
include ROOT_PATH . '/views/layouts/app.php';
