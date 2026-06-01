<?php
// views/pages/dashboard.php
use Auth\Auth;
use Helpers\Database;
use Services\OrderService;

Auth::require();

$dealerId    = Auth::id();
$orderSvc    = new OrderService();
$stats       = $orderSvc->getDealerStats($dealerId);
$recentOrders = $orderSvc->getDealerOrders($dealerId, '', 5);
$monthlyData  = $orderSvc->getMonthlyRevenue($dealerId, 6);
$discount     = Auth::effectiveDiscount();

// Okunmamış bildirim sayısı
$unreadNotifCount = (int)Database::scalar(
    "SELECT COUNT(*) FROM notifications WHERE dealer_id = ? AND is_read = 0",
    [$dealerId]
);

$pendingOrderCount = (int)($stats['pending_orders'] ?? 0);

$pageTitle  = 'Gösterge Paneli';
$activeMenu = 'dashboard';

ob_start();
?>

<div class="stats-grid">
  <!-- Kart 1 -->
  <div class="stat-card">
    <div class="stat-header">
      <div class="stat-icon icon-blue">📦</div>
      <div class="stat-label">Bu Ay Sipariş</div>
    </div>
    <div class="stat-value"><?= number_format((int)($stats['monthly_orders'] ?? 0)) ?></div>
    <div class="stat-sub">Toplam: <?= number_format((int)($stats['total_orders'] ?? 0)) ?> sipariş</div>
  </div>

  <!-- Kart 2 -->
  <div class="stat-card">
    <div class="stat-header">
      <div class="stat-icon icon-green">💰</div>
      <div class="stat-label">Aylık Ciro</div>
    </div>
    <div class="stat-value"><?= format_money((float)($stats['monthly_revenue'] ?? 0)) ?></div>
    <div class="stat-sub">Toplam: <?= format_money((float)($stats['total_revenue'] ?? 0)) ?></div>
  </div>

  <!-- Kart 3 -->
  <div class="stat-card">
    <div class="stat-header">
      <div class="stat-icon icon-amber">⏳</div>
      <div class="stat-label">Bekleyen Sipariş</div>
    </div>
    <div class="stat-value"><?= (int)($stats['pending_orders'] ?? 0) ?></div>
    <div class="stat-sub">Onay / hazırlık bekleyen</div>
  </div>

  <!-- Kart 4 -->
  <div class="stat-card">
    <div class="stat-header">
      <div class="stat-icon icon-purple">🎁</div>
      <div class="stat-label">Aktif İndirim</div>
    </div>
    <div class="stat-value">%<?= number_format($discount * 100, 0) ?></div>
    <div class="stat-sub">Tüm ürünlerde geçerli</div>
  </div>
</div>

<div class="two-col">
  <!-- Son Siparişler -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Son Siparişler</h3>
      <a href="/siparislerim" class="btn btn-outline btn-sm">Tümü →</a>
    </div>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr><th>Sipariş No</th><th>Tarih</th><th>Tutar</th><th>Durum</th></tr>
        </thead>
        <tbody>
          <?php if (empty($recentOrders)): ?>
            <tr><td colspan="4" class="empty-state">Henüz sipariş yok</td></tr>
          <?php else: ?>
            <?php foreach ($recentOrders as $order): ?>
              <?php
                $statusMap = [
                    'pending'    => ['label' => 'Bekliyor',      'cls' => 'badge-gray'],
                    'processing' => ['label' => 'Hazırlanıyor',  'cls' => 'badge-amber'],
                    'shipped'    => ['label' => 'Kargoda',       'cls' => 'badge-blue'],
                    'delivered'  => ['label' => 'Teslim Edildi', 'cls' => 'badge-green'],
                    'cancelled'  => ['label' => 'İptal',         'cls' => 'badge-red'],
                ];
                $s = $statusMap[$order['status']] ?? ['label' => $order['status'], 'cls' => 'badge-gray'];
              ?>
              <tr onclick="location.href='/siparis-detay?id=<?= $order['id'] ?>'" style="cursor:pointer">
                <td><strong>#<?= e($order['order_number']) ?></strong></td>
                <td class="text-muted"><?= date('d M Y', strtotime($order['created_at'])) ?></td>
                <td><strong><?= format_money((float)$order['total']) ?></strong></td>
                <td><span class="badge <?= $s['cls'] ?>"><?= $s['label'] ?></span></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Ciro Grafiği -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Aylık Ciro Trendi</h3>
    </div>
    <div class="card-body">
      <div class="chart-wrap" id="revenueChart" data-values='<?= json_encode(array_column($monthlyData, 'revenue')) ?>' data-labels='<?= json_encode(array_map(fn($r) => date('M', strtotime($r['month'] . '-01')), $monthlyData)) ?>'></div>
      <?php if (!empty($stats['monthly_revenue'])): ?>
        <div class="progress-section">
          <div class="progress-label">
            <span class="text-muted">Hedef: ₺60.000</span>
            <strong><?= format_money((float)$stats['monthly_revenue']) ?></strong>
          </div>
          <?php $pct = min(100, round(((float)$stats['monthly_revenue'] / 60000) * 100)); ?>
          <div class="progress-bar-wrap">
            <div class="progress-bar" style="width:<?= $pct ?>%"></div>
          </div>
          <div class="text-muted" style="font-size:12px;margin-top:4px">%<?= $pct ?> tamamlandı</div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Bildirimler -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title">🔔 Son Bildirimler</h3>
    <a href="/bildirimler" class="btn btn-outline btn-sm">Tümü →</a>
  </div>
  <?php
    $notifications = Database::fetchAll(
        "SELECT * FROM notifications WHERE dealer_id = ? ORDER BY created_at DESC LIMIT 5",
        [$dealerId]
    );
  ?>
  <?php if (empty($notifications)): ?>
    <div class="empty-state" style="padding:24px">Bildirim yok</div>
  <?php else: ?>
    <div class="notif-list">
      <?php foreach ($notifications as $n):
        $icons = [
            'order_created'  => ['icon' => '📦', 'bg' => '#dbeafe'],
            'order_shipped'  => ['icon' => '🚚', 'bg' => '#dbeafe'],
            'order_delivered'=> ['icon' => '✅', 'bg' => '#dcfce7'],
            'payment_received'=> ['icon' => '💰', 'bg' => '#dcfce7'],
            'campaign'       => ['icon' => '🎯', 'bg' => '#fef3c7'],
        ];
        $icon = $icons[$n['type']] ?? ['icon' => '🔔', 'bg' => '#f1f5f9'];
      ?>
        <div class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>">
          <div class="notif-icon-wrap" style="background:<?= $icon['bg'] ?>"><?= $icon['icon'] ?></div>
          <div class="notif-body">
            <div class="notif-title"><?= e($n['title']) ?></div>
            <?php if ($n['body']): ?>
              <div class="notif-desc"><?= e($n['body']) ?></div>
            <?php endif; ?>
            <div class="notif-time"><?= format_date($n['created_at']) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include ROOT_PATH . '/views/layouts/app.php';
