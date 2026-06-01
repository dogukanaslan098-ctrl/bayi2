<?php
// views/pages/reset_password.php
use Auth\Auth;

if (Auth::check()) { redirect('/'); }

$token   = trim($_GET['token'] ?? '');
$message = null;
$error   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $token      = trim($_POST['token'] ?? '');
    $newPass    = $_POST['new_password']     ?? '';
    $confirmPass= $_POST['confirm_password'] ?? '';

    if (strlen($newPass) < 8) {
        $error = 'Şifre en az 8 karakter olmalıdır.';
    } elseif ($newPass !== $confirmPass) {
        $error = 'Şifreler eşleşmiyor.';
    } elseif (Auth::resetPassword($token, $newPass)) {
        $message = 'Şifreniz başarıyla güncellendi. Giriş yapabilirsiniz.';
    } else {
        $error = 'Sıfırlama bağlantısı geçersiz veya süresi dolmuş.';
    }
}

if (empty($token) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    redirect('/login');
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Yeni Şifre — Provanya</title>
  <link rel="stylesheet" href="/assets/css/auth.css">
</head>
<body>
<div class="auth-wrapper">
  <div class="auth-brand">
    <div class="brand-logo">🔑</div>
    <h1 class="brand-name">Yeni Şifre</h1>
    <p class="brand-tagline">Güvenli bir şifre belirleyin</p>
  </div>
  <div class="auth-card">
    <h2 class="auth-title">Yeni Şifre Belirle</h2>

    <?php if ($message): ?>
      <div class="alert alert-success">✅ <?= htmlspecialchars($message) ?></div>
      <div class="auth-footer"><a href="/login">Giriş Yap →</a></div>
    <?php else: ?>
      <?php if ($error): ?><div class="alert alert-error">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>
      <form method="POST" class="auth-form">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <div class="form-group">
          <label class="form-label">Yeni Şifre</label>
          <input type="password" name="new_password" class="form-input" placeholder="En az 8 karakter" minlength="8" required>
        </div>
        <div class="form-group">
          <label class="form-label">Yeni Şifre Tekrar</label>
          <input type="password" name="confirm_password" class="form-input" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn-auth">Şifremi Güncelle</button>
      </form>
      <div class="auth-footer"><a href="/login">← Giriş Sayfasına Dön</a></div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
