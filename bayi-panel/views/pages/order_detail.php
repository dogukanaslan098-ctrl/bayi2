<?php
// views/pages/order_detail.php
use Auth\Auth;
use Helpers\Database;
use Services\OrderService;

Auth::require();

$dealerId = Auth::id();
$orderId  = (int)($_GET['id'] ?? 0);

if (!$orderId) { redirect('/siparislerim'); }

$orderSvc = new OrderService();
$order    = $orderSvc->getOrderDetail($orderId, $dealerId);

if (!$order) {
    http_response_code(404);
    redirect('/siparislerim');
}

$unreadNotifCount  = (int)Database::scalar(
    "SELECT COUNT(*) FROM notifications WHERE dealer_id = ? AND is_read = 0", [$dealerId]
);
$pendingOrderCount = (int)Database::scalar(
    "SELECT COUNT(*) FROM orders WHERE dealer_id = ? AND status IN ('pending','processing')", [$dealerId]
);

// Durum zaman çizelgesi
$allStatuses = [
    'pending'    => ['label' => 'Sipariş Oluşturuldu', 'icon' => '📋'],
    'processing' => ['label' => 'Hazırlanıyor',        'icon' => '📦'],
    'shipped'    => ['label' => 'Kargoya Verildi',     'icon' => '🚚'],
    'delivered'  => ['label' => 'Teslim Edildi',       'icon' => '✅'],
];

$statusOrder = array_keys($allStatuses);
$currentIdx  = array_search($order['status'], $statusOrder);

$payLabel  = $order['payment_method'] === 'card' ? '💳 Kredi/Banka Kartı' : '🏦 Havale/EFT';
$canCancel = in_array($order['status'], ['pending', 'processing']);

$pageTitle  = 'Sipariş #' . $order['order_number'];
$activeMenu = 'orders';

ob_start();
?>

<div style="margin-bottom:16px">
  <a href="/siparislerim" class="btn btn-outline btn-sm">← Siparişlere Dön</a>
</div>

<div class="two-col">
  <!-- Sol: Timeline + Kargo -->
  <div>
    <div class="card" style="margin-bottom:16px">
      <div class="card-header">
        <span class="card-title">Sipariş Durumu</span>
        <?php
          $badgeMap = [
            'pending'    => 'badge-gray',
            'processing' => 'badge-amber',
            'shipped'    => 'badge-blue',
            'delivered'  => 'badge-green',
            'cancelled'  => 'badge-red',
          ];
          $labels = [
            'pending'    => 'Bekliyor',
            'processing' => 'Hazırlanıyor',
            'shipped'    => 'Kargoda',
            'delivered'  => 'Teslim Edildi',
            'cancelled'  => 'İptal',
          ];
        ?>
        <span class="badge <?= $badgeMap[$order['status']] ?? 'badge-gray' ?>">
          <?= $labels[$order['status']] ?? $order['status'] ?>
        </span>
      </div>
      <div class="card-body">
        <?php if ($order['status'] !== 'cancelled'): ?>
          <ul class="timeline">
            <?php foreach ($allStatuses as $key => $step):
              $stepIdx = array_search($key, $statusOrder);
              $isDone  = $currentIdx !== false && $stepIdx <= $currentIdx;
              $isActive = $stepIdx === $currentIdx;
              $dotCls   = $isDone ? 't-done' : 't-pending';
              if ($isActive && !$isDone) $dotCls = 't-active';
            ?>
              <li class="timeline-item">
                <div class="t-dot <?= $dotCls ?>">
                  <?= $isDone ? '✓' : ($isActive ? '→' : '○') ?>
                </div>
                <div>
                  <div class="t-title" style="color:<?= $isDone ? 'var(--text)' : 'var(--text2)' ?>">
                    <?= $step['icon'] ?> <?= $step['label'] ?>
                  </div>
                  <?php if ($key === $order['status']): ?>
                    <div class="t-date"><?= format_date($order['updated_at']) ?></div>
                  <?php endif; ?>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <div style="padding:16px;background:#fef2f2;border-radius:8px;color:#b91c1c;font-size:13px">
            ❌ Bu sipariş iptal edilmiştir (<?= format_date($order['updated_at']) ?>)
          </div>
        <?php endif; ?>

        <!-- Kargo Bilgisi -->
        <?php if ($order['cargo_code']): ?>
          <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:14px;margin-top:16px">
            <div style="font-size:11px;font-weight:600;color:#0369a1;margin-bottom:8px;text-transform:uppercase">Kargo Takip</div>
            <div style="font-size:13px">
              <span style="color:var(--text2)"><?= e($order['cargo_company']) ?></span>
              <span style="font-weight:700;margin-left:8px;font-family:monospace"><?= e($order['cargo_code']) ?></span>
            </div>
          </div>
        <?php endif; ?>

        <!-- İptal Butonu -->
        <?php if ($canCancel): ?>
          <button class="btn btn-danger" style="margin-top:16px;width:100%;justify-content:center"
                  onclick="cancelOrder(<?= $order['id'] ?>, '<?= e($order['order_number']) ?>')">
            Siparişi İptal Et
          </button>
        <?php endif; ?>
      </div>
    </div>

    <!-- Ödeme Bilgisi -->
    <div class="card">
      <div class="card-header"><span class="card-title">Ödeme Bilgisi</span></div>
      <div class="card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:13px">
          <div>
            <div style="font-size:11px;color:var(--text2);font-weight:600;margin-bottom:4px">YÖNTEM</div>
            <div><?= $payLabel ?></div>
          </div>
          <div>
            <div style="font-size:11px;color:var(--text2);font-weight:600;margin-bottom:4px">DURUM</div>
            <?php $paidMap = ['unpaid'=>['Ödenmedi','badge-amber'], 'paid'=>['Ödendi','badge-green'], 'refunded'=>['İade','badge-red']]; ?>
            <span class="badge <?= $paidMap[$order['payment_status']][1] ?? 'badge-gray' ?>">
              <?= $paidMap[$order['payment_status']][0] ?? $order['payment_status'] ?>
            </span>
          </div>
          <div>
            <div style="font-size:11px;color:var(--text2);font-weight:600;margin-bottom:4px">TARİH</div>
            <div><?= date('d.m.Y', strtotime($order['created_at'])) ?></div>
          </div>
          <div>
            <div style="font-size:11px;color:var(--text2);font-weight:600;margin-bottom:4px">TOPLAM</div>
            <div style="font-weight:700;color:var(--blue)"><?= format_money((float)$order['total']) ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Sağ: Ürünler ve Özet -->
  <div>
    <div class="card">
      <div class="card-header">
        <span class="card-title">Sipariş Detayı</span>
        <a href="/fatura-indir?id=<?= $order['id'] ?>" class="btn btn-outline btn-sm">📄 Fatura İndir</a>
      </div>
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr><th>Ürün</th><th>SKU</th><th style="text-align:center">Adet</th><th style="text-align:right">Birim</th><th style="text-align:right">Toplam</th></tr>
          </thead>
          <tbody>
            <?php foreach ($order['items'] as $item): ?>
              <tr>
                <td><?= e($item['name']) ?></td>
                <td><span class="sku-tag"><?= e($item['sku']) ?></span></td>
                <td style="text-align:center"><?= (int)$item['quantity'] ?></td>
                <td style="text-align:right"><?= format_money((float)$item['dealer_price']) ?></td>
                <td style="text-align:right;font-weight:600"><?= format_money((float)$item['line_total']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Toplam Özeti -->
      <div style="padding:16px 20px;border-top:1px solid var(--border)">
        <div style="max-width:280px;margin-left:auto">
          <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:8px">
            <span style="color:var(--text2)">Ara Toplam</span>
            <span><?= format_money((float)$order['subtotal']) ?></span>
          </div>
          <?php if ((float)$order['discount_amount'] > 0): ?>
            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:8px;color:var(--green)">
              <span>Bayi İndirimi</span>
              <span>-<?= format_money((float)$order['discount_amount']) ?></span>
            </div>
          <?php endif; ?>
          <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:12px">
            <span style="color:var(--text2)">KDV (%20)</span>
            <span><?= format_money((float)$order['tax_amount']) ?></span>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:15px;font-weight:700;border-top:1px solid var(--border);padding-top:10px">
            <span>TOPLAM</span>
            <span style="color:var(--blue)"><?= format_money((float)$order['total']) ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
async function cancelOrder(orderId, orderNumber) {
    if (!confirm(`#${orderNumber} siparişini iptal etmek istediğinizden emin misiniz?\nBu işlem geri alınamaz.`)) return;

    try {
        const res = await fetch('/siparis/iptal', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('[name="_csrf"]')?.value || '' },
            body: JSON.stringify({ order_id: orderId }),
        });
        const data = await res.json();
        if (data.success) {
            Toast.show('✅ Sipariş iptal edildi');
            setTimeout(() => location.href = '/siparislerim', 1200);
        } else {
            Toast.show('❌ ' + (data.message || 'İptal başarısız'));
        }
    } catch(e) { Toast.show('❌ Bağlantı hatası'); }
}
</script>

<?php
$content = ob_get_clean();
include ROOT_PATH . '/views/layouts/app.php';
