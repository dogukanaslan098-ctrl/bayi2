<?php
// views/pages/notifications.php
use Auth\Auth;
use Helpers\Database;

Auth::require();

$dealerId = Auth::id();

// Tümünü okundu yap
if (isset($_GET['read_all'])) {
    Database::query(
        "UPDATE notifications SET is_read = 1 WHERE dealer_id = ?",
        [$dealerId]
    );
    redirect('/bildirimler');
}

$notifications = Database::fetchAll(
    "SELECT * FROM notifications WHERE dealer_id = ? ORDER BY created_at DESC LIMIT 100",
    [$dealerId]
);

$unreadNotifCount  = (int)Database::scalar("SELECT COUNT(*) FROM notifications WHERE dealer_id = ? AND is_read = 0", [$dealerId]);
$pendingOrderCount = (int)Database::scalar("SELECT COUNT(*) FROM orders WHERE dealer_id = ? AND status IN ('pending','processing')", [$dealerId]);

$iconMap = [
    'order_created'   => ['icon' => '📦', 'bg' => '#dbeafe'],
    'order_shipped'   => ['icon' => '🚚', 'bg' => '#dbeafe'],
    'order_delivered' => ['icon' => '✅', 'bg' => '#dcfce7'],
    'payment_received'=> ['icon' => '💰', 'bg' => '#dcfce7'],
    'order_cancelled' => ['icon' => '❌', 'bg' => '#fee2e2'],
    'campaign'        => ['icon' => '🎯', 'bg' => '#fef3c7'],
    'invoice'         => ['icon' => '📄', 'bg' => '#f5f3ff'],
];

$pageTitle  = 'Bildirimler';
$activeMenu = 'notifications';
ob_start();
?>

<div class="card">
  <div class="card-header">
    <span class="card-title">
      Tüm Bildirimler
      <?php if ($unreadNotifCount > 0): ?>
        <span class="badge badge-blue" style="margin-left:8px"><?= $unreadNotifCount ?> yeni</span>
      <?php endif; ?>
    </span>
    <?php if ($unreadNotifCount > 0): ?>
      <a href="/bildirimler?read_all=1" class="btn btn-outline btn-sm">✓ Tümünü Okundu İşaretle</a>
    <?php endif; ?>
  </div>

  <?php if (empty($notifications)): ?>
    <div class="empty-state" style="padding:60px">
      <div style="font-size:36px;margin-bottom:12px">🔔</div>
      <div style="font-size:15px;font-weight:600">Henüz bildirim yok</div>
    </div>
  <?php else: ?>
    <div class="notif-list">
      <?php foreach ($notifications as $n):
        $ic = $iconMap[$n['type']] ?? ['icon' => '🔔', 'bg' => '#f1f5f9'];
      ?>
        <div class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>"
             onclick="markRead(<?= $n['id'] ?>)">
          <div class="notif-icon-wrap" style="background:<?= $ic['bg'] ?>"><?= $ic['icon'] ?></div>
          <div class="notif-body" style="flex:1">
            <div class="notif-title"><?= e($n['title']) ?></div>
            <?php if ($n['body']): ?>
              <div class="notif-desc"><?= e($n['body']) ?></div>
            <?php endif; ?>
            <div class="notif-time"><?= format_date($n['created_at']) ?></div>
          </div>
          <?php if (!$n['is_read']): ?>
            <div style="width:8px;height:8px;background:var(--blue);border-radius:50%;flex-shrink:0;margin-top:4px"></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script>
async function markRead(id) {
    try {
        await fetch('/bildirimler/okundu', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('[name="_csrf"]')?.value || '' },
            body: JSON.stringify({ id }),
        });
    } catch(e) {}
}
</script>

<?php
$content = ob_get_clean();
include ROOT_PATH . '/views/layouts/app.php';
