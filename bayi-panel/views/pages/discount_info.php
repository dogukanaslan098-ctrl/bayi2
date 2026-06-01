<?php
// views/pages/discount_info.php
use Auth\Auth;
use Helpers\DiscountHelper;

Auth::require();

$dealerId = Auth::id();
$discountDetails = DiscountHelper::getDiscountDetails($dealerId);

// Bildirim sayıları
use Helpers\Database;
$unreadNotifCount = (int)Database::scalar(
    "SELECT COUNT(*) FROM notifications WHERE dealer_id = ? AND is_read = 0", [$dealerId]
);
$pendingOrderCount = (int)Database::scalar(
    "SELECT COUNT(*) FROM orders WHERE dealer_id = ? AND status IN ('pending','processing')", [$dealerId]
);

$pageTitle  = 'İndirim Bilgisi';
$activeMenu = 'discount_info';

ob_start();
?>

<div style="max-width:900px;margin:0 auto">
  
  <!-- Ana Efektif İndirim Kartı -->
  <div class="card" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;padding:40px;border-radius:12px;margin-bottom:24px;box-shadow:0 10px 30px rgba(102,126,234,0.2)">
    <div style="text-align:center">
      <div style="font-size:48px;margin-bottom:12px">🎁</div>
      <div style="font-size:14px;opacity:0.9;margin-bottom:8px">Aktif İndiriminiz</div>
      <div style="font-size:56px;font-weight:700;margin-bottom:8px"><?= $discountDetails['effective']['percent'] ?>%</div>
      <div style="font-size:13px;opacity:0.9">Tüm ürünlerde otomatik uygulanmaktadır</div>
      <div style="font-size:11px;opacity:0.8;margin-top:12px;font-style:italic">
        Kaynak: <?= e($discountDetails['effective']['source']) ?>
      </div>
    </div>
  </div>

  <!-- İndirim Kaynakları -->
  <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; margin-bottom: 24px">
    
    <!-- 1. Seviye İndirimi -->
    <div class="card" style="padding:20px;border-left:4px solid #3b82f6">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
        <div style="font-size:28px"><?= $discountDetails['level']['icon'] ?></div>
        <div>
          <div style="font-size:12px;color:var(--text2)">Bayi Seviyesi</div>
          <div style="font-size:16px;font-weight:600"><?= e($discountDetails['level']['label']) ?></div>
        </div>
      </div>
      <div style="padding:12px;background:#f0f9ff;border-radius:6px;text-align:center">
        <div style="font-size:24px;font-weight:700;color:#3b82f6">%<?= $discountDetails['level']['percent'] ?></div>
        <div style="font-size:11px;color:#0369a1;margin-top:4px">Temel İndirim</div>
      </div>
      <div style="font-size:12px;color:var(--text2);margin-top:12px">
        Bayi seviyeniz otomatik olarak sistemde uygulanır. Daha yüksek seviyelere çıkmak için sipariş miktarınızı artırın.
      </div>
    </div>

    <!-- 2. Kişisel İndirim -->
    <div class="card" style="padding:20px;border-left:4px solid #8b5cf6">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
        <div style="font-size:28px">⭐</div>
        <div>
          <div style="font-size:12px;color:var(--text2)">Özel İndirim</div>
          <div style="font-size:16px;font-weight:600">Yönetici Tarafından</div>
        </div>
      </div>
      <div style="padding:12px;background:#faf5ff;border-radius:6px;text-align:center">
        <div style="font-size:24px;font-weight:700;color:#8b5cf6">
          <?php if ($discountDetails['personal']['value'] > 0): ?>
            <?= $discountDetails['personal']['label'] ?>
          <?php else: ?>
            <span style="color:#9ca3af">-</span>
          <?php endif; ?>
        </div>
        <div style="font-size:11px;color:#7c3aed;margin-top:4px">
          <?= $discountDetails['personal']['type'] === 'percent' ? 'Yüzde İndirim' : 'Sabit İndirim' ?>
        </div>
      </div>
      <div style="font-size:12px;color:var(--text2);margin-top:12px">
        Yönetici tarafından özel olarak belirlenen indirim oranınız burada gösterilmektedir.
      </div>
    </div>

    <!-- 3. Kampanya İndirimi -->
    <div class="card" style="padding:20px;border-left:4px solid <?= $discountDetails['campaign']['active'] ? '#10b981' : '#d1d5db' ?>">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
        <div style="font-size:28px"><?= $discountDetails['campaign']['active'] ? '🎯' : '❌' ?></div>
        <div>
          <div style="font-size:12px;color:var(--text2)">Aktif Kampanya</div>
          <div style="font-size:16px;font-weight:600">
            <?= $discountDetails['campaign']['active'] ? 'Evet' : 'Yok' ?>
          </div>
        </div>
      </div>
      <?php if ($discountDetails['campaign']['active']): ?>
        <div style="padding:12px;background:#f0fdf4;border-radius:6px;text-align:center">
          <div style="font-size:24px;font-weight:700;color:#10b981">+%<?= $discountDetails['campaign']['percent'] ?></div>
          <div style="font-size:11px;color:#059669;margin-top:4px">Ek İndirim</div>
        </div>
        <div style="font-size:12px;color:var(--text2);margin-top:12px">
          <strong><?= e($discountDetails['campaign']['name']) ?></strong><br>
          Geçerli: <?= date('d.m.Y', strtotime($discountDetails['campaign']['startDate'])) ?> → 
          <?= date('d.m.Y', strtotime($discountDetails['campaign']['endDate'])) ?>
        </div>
      <?php else: ?>
        <div style="padding:12px;background:#f3f4f6;border-radius:6px;text-align:center;color:#6b7280">
          <div style="font-size:12px">Şu anda aktif bir kampanya bulunmamaktadır.</div>
        </div>
      <?php endif; ?>
    </div>

  </div>

  <!-- Fiyatlandırma Örneği -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">💡 Fiyatlandırma Örneği</h3>
    </div>
    <div class="card-body">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px">
        
        <!-- Örnek 1: 100 TL ürün -->
        <div style="padding:16px;border:1px solid #e5e7eb;border-radius:8px">
          <div style="font-size:12px;color:var(--text2);margin-bottom:8px">Örnek Ürün Fiyatı</div>
          <div style="font-size:24px;font-weight:600;color:#6b7280;text-decoration:line-through;margin-bottom:12px">
            ₺100,00
          </div>
          <div style="background:#f0fdf4;padding:12px;border-radius:6px;text-align:center">
            <div style="font-size:12px;color:#059669;margin-bottom:4px">Sizin Ödeyeceğiniz</div>
            <div style="font-size:28px;font-weight:700;color:#10b981">
              ₺<?php
                $example = DiscountHelper::getPricing(100, (float)$discountDetails['effective']['discount']);
                echo number_format($example['dealer_price'], 2, ',', '.');
              ?>
            </div>
            <div style="font-size:11px;color:#059669;margin-top:4px">
              Tasarruf: ₺<?= number_format($example['savings'], 2, ',', '.') ?>
            </div>
          </div>
        </div>

        <!-- Örnek 2: 500 TL ürün -->
        <div style="padding:16px;border:1px solid #e5e7eb;border-radius:8px">
          <div style="font-size:12px;color:var(--text2);margin-bottom:8px">Örnek Ürün Fiyatı</div>
          <div style="font-size:24px;font-weight:600;color:#6b7280;text-decoration:line-through;margin-bottom:12px">
            ₺500,00
          </div>
          <div style="background:#f0fdf4;padding:12px;border-radius:6px;text-align:center">
            <div style="font-size:12px;color:#059669;margin-bottom:4px">Sizin Ödeyeceğiniz</div>
            <div style="font-size:28px;font-weight:700;color:#10b981">
              ₺<?php
                $example = DiscountHelper::getPricing(500, (float)$discountDetails['effective']['discount']);
                echo number_format($example['dealer_price'], 2, ',', '.');
              ?>
            </div>
            <div style="font-size:11px;color:#059669;margin-top:4px">
              Tasarruf: ₺<?= number_format($example['savings'], 2, ',', '.') ?>
            </div>
          </div>
        </div>

        <!-- Örnek 3: 1000 TL ürün -->
        <div style="padding:16px;border:1px solid #e5e7eb;border-radius:8px">
          <div style="font-size:12px;color:var(--text2);margin-bottom:8px">Örnek Ürün Fiyatı</div>
          <div style="font-size:24px;font-weight:600;color:#6b7280;text-decoration:line-through;margin-bottom:12px">
            ₺1.000,00
          </div>
          <div style="background:#f0fdf4;padding:12px;border-radius:6px;text-align:center">
            <div style="font-size:12px;color:#059669;margin-bottom:4px">Sizin Ödeyeceğiniz</div>
            <div style="font-size:28px;font-weight:700;color:#10b981">
              ₺<?php
                $example = DiscountHelper::getPricing(1000, (float)$discountDetails['effective']['discount']);
                echo number_format($example['dealer_price'], 2, ',', '.');
              ?>
            </div>
            <div style="font-size:11px;color:#059669;margin-top:4px">
              Tasarruf: ₺<?= number_format($example['savings'], 2, ',', '.') ?>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

  <!-- Bilgi Metni -->
  <div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:8px;padding:16px;margin-top:24px;display:flex;gap:12px">
    <div style="font-size:20px;flex-shrink:0">ℹ️</div>
    <div>
      <div style="font-weight:600;color:#78350f;margin-bottom:4px">Önemli Bilgi</div>
      <div style="font-size:13px;color:#92400e;line-height:1.6">
        Yukarıda gösterilen indirimlerin en yüksek olanı otomatik olarak ürünlere uygulanır. 
        Sizin efektif indiriminiz <strong>%<?= $discountDetails['effective']['percent'] ?></strong>'dir. 
        Tüm siparişleriniz bu oranda indirimle hesaplanmaktadır.
      </div>
    </div>
  </div>

</div>

<?php
$content = ob_get_clean();
include ROOT_PATH . '/views/layouts/app.php';
