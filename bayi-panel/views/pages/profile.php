<?php
// views/pages/profile.php
use Auth\Auth;
use Helpers\Database;

Auth::require();

$dealerId = Auth::id();
$dealer   = Auth::dealer();

$unreadNotifCount  = (int)Database::scalar("SELECT COUNT(*) FROM notifications WHERE dealer_id = ? AND is_read = 0", [$dealerId]);
$pendingOrderCount = (int)Database::scalar("SELECT COUNT(*) FROM orders WHERE dealer_id = ? AND status IN ('pending','processing')", [$dealerId]);

// İstatistikler
$stats = Database::fetchOne("
    SELECT
        COUNT(*) as total_orders,
        COALESCE(SUM(total), 0) as total_revenue,
        COALESCE(AVG(total), 0) as avg_order
    FROM orders
    WHERE dealer_id = ? AND status != 'cancelled'
", [$dealerId]);

$levelLabels = ['bronz' => '🥉 Bronz', 'silver' => '🥈 Silver', 'gold' => '🥇 Gold', 'platinum' => '💎 Platinum'];
$discount    = Auth::effectiveDiscount();

$pageTitle  = 'Hesabım';
$activeMenu = 'profile';
ob_start();
?>

<input type="hidden" name="_csrf" value="<?= csrf_token() ?>">

<div class="two-col">
  <!-- Firma Bilgileri -->
  <div>
    <div class="card" style="margin-bottom:16px">
      <div class="card-header">
        <span class="card-title">🏢 Firma Bilgileri</span>
        <button class="btn btn-outline btn-sm" onclick="toggleEdit()">Düzenle</button>
      </div>
      <div id="profileView" class="card-body">
        <?php
          $fields = [
            'FİRMA ADI'       => $dealer['company_name'],
            'YETKİLİ KİŞİ'    => $dealer['contact_name'],
            'E-POSTA'          => $dealer['email'],
            'TELEFON'          => $dealer['phone'],
            'VERGİ DAİRESİ'   => $dealer['tax_office'] ?? '—',
            'VERGİ NO / TCKN' => ($dealer['tax_number'] ?? '') ?: ($dealer['tckn'] ?? '—'),
            'ADRES'            => trim(($dealer['address'] ?? '') . ' ' . ($dealer['city'] ?? '') . ' ' . ($dealer['district'] ?? '')) ?: '—',
            'SON GİRİŞ'       => $dealer['last_login'] ? format_date($dealer['last_login']) : '—',
          ];
        ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
          <?php foreach ($fields as $label => $value): ?>
            <div>
              <div style="font-size:11px;font-weight:600;color:var(--text2);margin-bottom:4px;letter-spacing:.4px"><?= $label ?></div>
              <div style="font-size:13px"><?= e($value) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Düzenleme Formu (gizli) -->
      <div id="profileEdit" style="display:none">
        <form method="POST" action="/hesabim/guncelle" class="card-body">
          <?= csrf_field() ?>
          <div class="form-grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:0 16px">
            <div class="form-group">
              <label class="form-label">Firma Adı</label>
              <input type="text" name="company_name" class="form-control" value="<?= e($dealer['company_name']) ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">Yetkili Kişi</label>
              <input type="text" name="contact_name" class="form-control" value="<?= e($dealer['contact_name']) ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">Telefon</label>
              <input type="tel" name="phone" class="form-control" value="<?= e($dealer['phone']) ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">Vergi Dairesi</label>
              <input type="text" name="tax_office" class="form-control" value="<?= e($dealer['tax_office'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Vergi No</label>
              <input type="text" name="tax_number" class="form-control" value="<?= e($dealer['tax_number'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Şehir</label>
              <input type="text" name="city" class="form-control" value="<?= e($dealer['city'] ?? '') ?>">
            </div>
            <div class="form-group" style="grid-column:1/-1">
              <label class="form-label">Adres</label>
              <textarea name="address" class="form-control" rows="2"><?= e($dealer['address'] ?? '') ?></textarea>
            </div>
          </div>
          <div style="display:flex;gap:8px">
            <button type="submit" class="btn btn-primary">Kaydet</button>
            <button type="button" class="btn btn-outline" onclick="toggleEdit()">İptal</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Şifre Değiştir -->
    <div class="card">
      <div class="card-header"><span class="card-title">🔒 Şifre Değiştir</span></div>
      <form method="POST" action="/hesabim/sifre" class="card-body">
        <?= csrf_field() ?>
        <div class="form-group">
          <label class="form-label">Mevcut Şifre</label>
          <input type="password" name="current_password" class="form-control" placeholder="••••••••" required>
        </div>
        <div class="form-group">
          <label class="form-label">Yeni Şifre</label>
          <input type="password" name="new_password" class="form-control" placeholder="En az 8 karakter" minlength="8" required>
        </div>
        <div class="form-group">
          <label class="form-label">Yeni Şifre Tekrar</label>
          <input type="password" name="confirm_password" class="form-control" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn btn-primary">Şifreyi Güncelle</button>
      </form>
    </div>
  </div>

  <!-- İstatistikler -->
  <div>
    <!-- Hesap Durumu -->
    <div class="card" style="margin-bottom:16px">
      <div class="card-header"><span class="card-title">📊 Hesap İstatistikleri</span></div>
      <div class="card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div style="text-align:center;padding:16px;background:#f8fafc;border-radius:8px">
            <div style="font-size:24px;font-weight:700;color:var(--blue)"><?= number_format((int)$stats['total_orders']) ?></div>
            <div style="font-size:12px;color:var(--text2);margin-top:4px">Toplam Sipariş</div>
          </div>
          <div style="text-align:center;padding:16px;background:#f8fafc;border-radius:8px">
            <div style="font-size:22px;font-weight:700;color:var(--green)"><?= format_money((float)$stats['total_revenue']) ?></div>
            <div style="font-size:12px;color:var(--text2);margin-top:4px">Toplam Ciro</div>
          </div>
          <div style="text-align:center;padding:16px;background:#f8fafc;border-radius:8px">
            <div style="font-size:22px;font-weight:700;color:var(--amber)">%<?= number_format($discount * 100, 0) ?></div>
            <div style="font-size:12px;color:var(--text2);margin-top:4px">İndirim Oranı</div>
          </div>
          <div style="text-align:center;padding:16px;background:#f8fafc;border-radius:8px">
            <div style="font-size:18px;font-weight:700;color:var(--purple)"><?= $levelLabels[$dealer['level']] ?? $dealer['level'] ?></div>
            <div style="font-size:12px;color:var(--text2);margin-top:4px">Hesap Seviyesi</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Aktif Kampanyalar -->
    <?php
      $campaigns = Database::fetchAll("
          SELECT * FROM campaigns
          WHERE is_active = 1 AND NOW() BETWEEN starts_at AND ends_at
            AND (target = 'all' OR target = ?)
          ORDER BY discount_value DESC
      ", [$dealer['level']]);
    ?>
    <?php if (!empty($campaigns)): ?>
    <div class="card">
      <div class="card-header"><span class="card-title">🎯 Aktif Kampanyalar</span></div>
      <div style="padding:16px">
        <?php foreach ($campaigns as $c): ?>
          <div style="padding:14px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;margin-bottom:10px">
            <div style="font-weight:600;font-size:13px;margin-bottom:4px"><?= e($c['name']) ?></div>
            <div style="font-size:12px;color:var(--text2)">
              %<?= number_format((float)$c['discount_value'], 0) ?> indirim ·
              <?= date('d.m.Y', strtotime($c['ends_at'])) ?> tarihine kadar
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
function toggleEdit() {
    const view = document.getElementById('profileView');
    const edit = document.getElementById('profileEdit');
    const isEditing = edit.style.display !== 'none';
    view.style.display = isEditing ? 'block' : 'none';
    edit.style.display = isEditing ? 'none' : 'block';
}
</script>

<?php
$content = ob_get_clean();
include ROOT_PATH . '/views/layouts/app.php';
