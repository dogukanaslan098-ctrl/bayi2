<?php
// views/pages/orders.php
use Auth\Auth;
use Helpers\Database;
use Services\OrderService;

Auth::require();

$dealerId  = Auth::id();
$orderSvc  = new OrderService();

$status = trim($_GET['status'] ?? '');
$orders = $orderSvc->getDealerOrders($dealerId, $status);

$unreadNotifCount  = (int)Database::scalar(
    "SELECT COUNT(*) FROM notifications WHERE dealer_id = ? AND is_read = 0", [$dealerId]
);
$pendingOrderCount = (int)Database::scalar(
    "SELECT COUNT(*) FROM orders WHERE dealer_id = ? AND status IN ('pending','processing')", [$dealerId]
);

$statusMap = [
    ''           => ['label' => 'Tümü',          'cls' => ''],
    'pending'    => ['label' => 'Bekliyor',       'cls' => 'badge-gray'],
    'processing' => ['label' => 'Hazırlanıyor',   'cls' => 'badge-amber'],
    'shipped'    => ['label' => 'Kargoda',        'cls' => 'badge-blue'],
    'delivered'  => ['label' => 'Teslim Edildi',  'cls' => 'badge-green'],
    'cancelled'  => ['label' => 'İptal',          'cls' => 'badge-red'],
];

$pageTitle  = 'Siparişlerim';
$activeMenu = 'orders';

ob_start();
?>

<!-- Durum Filtreleri -->
<div class="tabs" style="margin-bottom:20px">
  <?php foreach ($statusMap as $key => $val): ?>
    <a href="/siparislerim<?= $key ? '?status=' . $key : '' ?>"
       class="tab <?= $status === $key ? 'active' : '' ?>"><?= $val['label'] ?></a>
  <?php endforeach; ?>
</div>

<div class="card">
  <?php if (empty($orders)): ?>
    <div style="text-align:center;padding:60px;color:var(--text2)">
      <div style="font-size:36px;margin-bottom:12px">📋</div>
      <div style="font-size:15px;font-weight:600">Bu filtreye ait sipariş yok</div>
      <a href="/siparis" class="btn btn-primary" style="margin-top:16px">İlk Siparişi Ver →</a>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>Sipariş No</th>
            <th>Tarih</th>
            <th>Kalem</th>
            <th>Tutar</th>
            <th>Ödeme</th>
            <th>Kargo</th>
            <th>Durum</th>
            <th>İşlem</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $order):
            $s = $statusMap[$order['status']] ?? ['label' => $order['status'], 'cls' => 'badge-gray'];
            $canCancel = in_array($order['status'], ['pending', 'processing']);
            $payLabel  = $order['payment_method'] === 'card' ? '💳 Kart' : '🏦 EFT';
          ?>
            <tr>
              <td>
                <a href="/siparis-detay?id=<?= $order['id'] ?>" style="font-weight:600;color:var(--blue);text-decoration:none">
                  #<?= e($order['order_number']) ?>
                </a>
              </td>
              <td class="text-muted" style="white-space:nowrap">
                <?= date('d.m.Y', strtotime($order['created_at'])) ?><br>
                <span style="font-size:11px"><?= date('H:i', strtotime($order['created_at'])) ?></span>
              </td>
              <td><?= (int)$order['item_count'] ?> kalem</td>
              <td style="font-weight:600"><?= format_money((float)$order['total']) ?></td>
              <td style="font-size:12px;color:var(--text2)"><?= $payLabel ?></td>
              <td style="font-size:12px;color:var(--text2)">
                <?php if ($order['cargo_code']): ?>
                  <?= e($order['cargo_company']) ?><br>
                  <code style="font-size:11px"><?= e($order['cargo_code']) ?></code>
                <?php else: ?>
                  <span style="color:#cbd5e1">—</span>
                <?php endif; ?>
              </td>
              <td><span class="badge <?= $s['cls'] ?>"><?= $s['label'] ?></span></td>
              <td>
                <div style="display:flex;gap:6px;align-items:center">
                  <a href="/siparis-detay?id=<?= $order['id'] ?>" class="btn btn-outline btn-sm">Detay</a>

                  <?php if ($order['invoice_path']): ?>
                    <a href="/fatura-indir?id=<?= $order['id'] ?>" class="btn btn-outline btn-sm">📄</a>
                  <?php endif; ?>

                  <?php if ($canCancel): ?>
                    <button class="btn btn-danger btn-sm"
                            onclick="cancelOrder(<?= $order['id'] ?>, '<?= e($order['order_number']) ?>')">
                      İptal
                    </button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<input type="hidden" name="_csrf" value="<?= $_SESSION['_csrf'] ?? '' ?>">

<script>
async function cancelOrder(orderId, orderNumber) {
    if (!confirm(`#${orderNumber} numaralı siparişi iptal etmek istediğinizden emin misiniz?`)) return;

    try {
        const res = await fetch('/siparis/iptal', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('[name="_csrf"]')?.value || '',
            },
            body: JSON.stringify({ order_id: orderId }),
        });
        const data = await res.json();

        if (data.success) {
            Toast.show('✅ Sipariş iptal edildi');
            setTimeout(() => location.reload(), 1000);
        } else {
            Toast.show('❌ ' + (data.message || 'İptal başarısız'));
        }
    } catch (e) {
    console.error("Hata Detayı:", e);
    Toast.show('❌ Bağlantı hatası: ' + e.message);
}
}
</script>

<?php
$content = ob_get_clean();
include ROOT_PATH . '/views/layouts/app.php';
