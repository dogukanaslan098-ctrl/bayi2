<?php
// views/pages/excel_order.php
use Auth\Auth;
use Helpers\Database;

Auth::require();
$dealerId = Auth::id();
$unreadNotifCount  = (int)Database::scalar("SELECT COUNT(*) FROM notifications WHERE dealer_id = ? AND is_read = 0", [$dealerId]);
$pendingOrderCount = (int)Database::scalar("SELECT COUNT(*) FROM orders WHERE dealer_id = ? AND status IN ('pending','processing')", [$dealerId]);

$pageTitle  = 'Excel ile Toplu Sipariş';
$activeMenu = 'excel';
ob_start();
?>

<input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

<div class="two-col">
  <!-- Sol: Yükleme Alanı -->
  <div class="card">
    <div class="card-header"><span class="card-title">📥 Dosya Yükleme</span></div>
    <div class="card-body">
      <div class="upload-zone" id="uploadZone">
        <div class="upload-icon">📊</div>
        <div class="upload-title">Excel veya CSV Dosyanızı Seçin</div>
        <div class="upload-sub" style="margin-bottom:16px">SKU ve Adet kolonlarını içeren dosyayı sürükleyin ya da tıklayın<br>Desteklenen: .xlsx, .xls, .csv (max 5MB)</div>
        <button type="button" class="btn btn-outline" onclick="document.getElementById('excelFile').click()">Dosya Seç</button>
        <input type="file" id="excelFile" accept=".xlsx,.xls,.csv" style="display:none">
      </div>

      <!-- Şablon -->
      <div style="margin-top:24px;padding-top:20px;border-top:1px solid var(--border)">
        <div style="font-size:12px;font-weight:600;color:var(--text2);margin-bottom:12px;text-transform:uppercase">
          📋 Beklenen Format
        </div>
        <div class="table-responsive">
          <table class="data-table">
            <thead><tr><th>SKU</th><th>Adet</th></tr></thead>
            <tbody>
              <tr><td><span class="sku-tag">PRV-001</span></td><td>50</td></tr>
              <tr><td><span class="sku-tag">PRV-012</span></td><td>30</td></tr>
              <tr><td><span class="sku-tag">PRV-024</span></td><td>100</td></tr>
            </tbody>
          </table>
        </div>
        <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
          <a href="/excel-sablon" class="btn btn-outline btn-sm">⬇ CSV Şablon İndir</a>
          <div style="font-size:11px;color:var(--text2);display:flex;align-items:center">
            Noktalı virgül (;) veya virgül (,) ayırıcı desteklenir
          </div>
        </div>
      </div>

      <!-- Kullanım İpuçları -->
      <div style="margin-top:20px;padding:14px;background:#f8fafc;border-radius:8px;font-size:12px;color:var(--text2)">
        <div style="font-weight:600;margin-bottom:8px;color:var(--text)">💡 İpuçları</div>
        <ul style="padding-left:16px;display:flex;flex-direction:column;gap:4px">
          <li>Başlık satırı (SKU, Adet) opsiyonel — otomatik algılanır</li>
          <li>Aynı SKU birden fazla satırda varsa adetler birleştirilir</li>
          <li>SKU bulunamazsa hata olarak raporlanır, geri kalanlar eklenir</li>
          <li>Stok yetersizse ürün beklemeye alınır, sipariş iptal edilmez</li>
        </ul>
      </div>
    </div>
  </div>

  <!-- Sağ: Yükleme Sonucu -->
  <div class="card">
    <div class="card-header"><span class="card-title">Önizleme</span></div>
    <div class="card-body" id="excelResult">
      <div class="empty-state" style="padding:50px">
        <div style="font-size:30px;margin-bottom:12px">📂</div>
        Dosya yüklendikten sonra burada önizleme görünecek
      </div>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();
include ROOT_PATH . '/views/layouts/app.php';
