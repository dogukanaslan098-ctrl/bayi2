<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle ?? 'Bayi Paneli') ?> — Provanya</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="stylesheet" href="/assets/css/panel.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <a href="/" class="logo">
      <span class="logo-icon">🏪</span>
      <div>
        <div class="logo-name">Provanya</div>
        <div class="logo-sub">Bayi Portalı</div>
      </div>
    </a>
    <button class="sidebar-close" id="sidebarClose">✕</button>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-group-label">Ana Menü</div>

    <a href="/" class="nav-item <?= $activeMenu === 'dashboard' ? 'active' : '' ?>">
      <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
      </svg>
      <span>Gösterge Paneli</span>
    </a>

    <a href="/urunler" class="nav-item <?= $activeMenu === 'products' ? 'active' : '' ?>">
      <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
      </svg>
      <span>Ürün Kataloğu</span>
    </a>

    <a href="/siparis" class="nav-item <?= $activeMenu === 'quickorder' ? 'active' : '' ?>">
      <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
      </svg>
      <span>Hızlı Sipariş</span>
    </a>

    <div class="nav-group-label">Siparişler</div>

    <a href="/siparislerim" class="nav-item <?= $activeMenu === 'orders' ? 'active' : '' ?>">
      <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
      </svg>
      <span>Siparişlerim</span>
      <?php if (!empty($pendingOrderCount) && $pendingOrderCount > 0): ?>
        <span class="nav-badge"><?= $pendingOrderCount ?></span>
      <?php endif; ?>
    </a>

    <a href="/excel-siparis" class="nav-item <?= $activeMenu === 'excel' ? 'active' : '' ?>">
      <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
      </svg>
      <span>Excel Yükleme</span>
    </a>

    <div class="nav-group-label">Hesap</div>

    <a href="/indirim-bilgisi" class="nav-item <?= $activeMenu === 'discount_info' ? 'active' : '' ?>">
      <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
      <span>İndirim Bilgisi</span>
    </a>

    <a href="/bildirimler" class="nav-item <?= $activeMenu === 'notifications' ? 'active' : '' ?>">
      <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0h-6"/>
      </svg>
      <span>Bildirimler</span>
      <?php if (!empty($unreadNotifCount) && $unreadNotifCount > 0): ?>
        <span class="nav-badge"><?= $unreadNotifCount ?></span>
      <?php endif; ?>
    </a>

    <a href="/hesabim" class="nav-item <?= $activeMenu === 'profile' ? 'active' : '' ?>">
      <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
      </svg>
      <span>Hesabım</span>
    </a>
  </nav>

  <!-- Kullanıcı Bilgisi -->
  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="user-avatar">
        <?= strtoupper(substr($_SESSION['dealer_company'] ?? 'B', 0, 2)) ?>
      </div>
      <div class="user-meta">
        <div class="user-name"><?= e($_SESSION['dealer_company'] ?? '') ?></div>
        <div class="user-email"><?= e($_SESSION['dealer_email'] ?? '') ?></div>
      </div>
    </div>
    <a href="/logout" class="logout-btn" title="Çıkış Yap">
      <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
      </svg>
    </a>
  </div>
</aside>

<!-- Overlay (mobil) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- MAIN CONTENT AREA -->
<div class="main-wrapper">

  <!-- TOPBAR -->
  <header class="topbar">
    <button class="menu-toggle" id="menuToggle">☰</button>
    <h1 class="page-title"><?= e($pageTitle ?? '') ?></h1>

    <div class="topbar-actions">
      <!-- Ürün Arama -->
      <form action="/urunler" method="GET" class="topbar-search">
        <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
        </svg>
        <input type="text" name="q" placeholder="SKU, barkod, ürün ara..." class="search-input"
               value="<?= e($_GET['q'] ?? '') ?>">
      </form>

      <!-- Bildirim -->
      <a href="/bildirimler" class="topbar-notif" title="Bildirimler">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0h-6"/>
        </svg>
        <?php if (!empty($unreadNotifCount) && $unreadNotifCount > 0): ?>
          <span class="notif-dot"></span>
        <?php endif; ?>
      </a>

      <!-- Hızlı Sipariş -->
      <a href="/siparis" class="btn btn-primary btn-sm">+ Sipariş Ver</a>
    </div>
  </header>

  <!-- FLASH MESAJLAR -->
  <?php if (!empty($_SESSION['flash'])): ?>
    <?php foreach ($_SESSION['flash'] as $type => $msg): ?>
      <div class="flash flash-<?= e($type) ?>">
        <?= e($msg) ?>
        <button onclick="this.parentElement.remove()" class="flash-close">✕</button>
      </div>
    <?php endforeach; ?>
    <?php unset($_SESSION['flash']); ?>
  <?php endif; ?>

  <!-- SAYFA İÇERİĞİ -->
  <main class="page-content">
    <?= $content ?? '' ?>
  </main>

</div><!-- /main-wrapper -->

<script src="/assets/js/panel.js"></script>
</body>
</html>
