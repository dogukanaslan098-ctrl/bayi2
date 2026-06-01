<?php
// views/pages/login.php
// Giriş formu — oturum yoksa gösterilir
use Auth\Auth;

if (Auth::check()) {
    header('Location: /');
    exit;
}

$error    = $_SESSION['login_error'] ?? null;
$redirect = htmlspecialchars($_GET['redirect'] ?? '/', ENT_QUOTES);
unset($_SESSION['login_error']);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Giriş Yap — Provanya Bayi Paneli</title>
  <meta name="robots" content="noindex">
  <link rel="stylesheet" href="/assets/css/auth.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
</head>
<body>
<div class="auth-wrapper">

  <div class="auth-brand">
    <div class="brand-logo">🏪</div>
    <h1 class="brand-name">Provanya</h1>
    <p class="brand-tagline">Bayi Sipariş Portalı</p>
    <div class="brand-features">
      <div class="feature-item">✅ Anlık stok takibi</div>
      <div class="feature-item">✅ Bayi özel fiyatlar</div>
      <div class="feature-item">✅ Toplu sipariş / Excel</div>
      <div class="feature-item">✅ PDF fatura indirme</div>
    </div>
  </div>

  <div class="auth-card">
    <h2 class="auth-title">Hesabınıza Giriş Yapın</h2>
    <p class="auth-subtitle">Bayi bilgilerinizi kullanarak devam edin</p>

    <?php if ($error): ?>
      <div class="alert alert-error">
        <span>⚠</span> <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="/login" class="auth-form">
      <?= csrf_field() ?>
      <input type="hidden" name="redirect" value="<?= $redirect ?>">

      <div class="form-group">
        <label for="email" class="form-label">E-posta Adresi</label>
        <input
          type="email"
          id="email"
          name="email"
          class="form-input"
          placeholder="firma@example.com"
          value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
          required
          autocomplete="email"
        >
      </div>

      <div class="form-group">
        <div class="label-row">
          <label for="password" class="form-label">Şifre</label>
          <a href="/sifremi-unuttum" class="forgot-link">Şifremi Unuttum</a>
        </div>
        <div class="input-wrap">
          <input
            type="password"
            id="password"
            name="password"
            class="form-input"
            placeholder="••••••••"
            required
            autocomplete="current-password"
          >
          <button type="button" class="toggle-pass" onclick="togglePassword()">👁</button>
        </div>
      </div>

      <div class="form-check">
        <input type="checkbox" id="remember" name="remember" value="1">
        <label for="remember">Beni hatırla</label>
      </div>

      <button type="submit" class="btn-auth">Giriş Yap →</button>
    </form>

    <div class="auth-footer">
      Henüz bayi değil misiniz?
      <a href="/basvuru">Bayi Başvurusu Yapın</a>
    </div>
  </div>

</div>

<script>
function togglePassword() {
    const input = document.getElementById('password');
    input.type = input.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
