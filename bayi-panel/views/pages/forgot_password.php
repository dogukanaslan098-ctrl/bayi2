<?php
// views/pages/forgot_password.php
use Auth\Auth;
use Services\MailService;

if (Auth::check()) { redirect('/'); }

$message = null;
$error   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Geçerli bir e-posta adresi giriniz.';
    } else {
        $token = Auth::createResetToken($email);
        if ($token) {
            MailService::sendPasswordReset($email, $token);
        }
        // Güvenlik: her durumda aynı mesaj
        $message = 'E-posta adresiniz sistemde kayıtlıysa şifre sıfırlama bağlantısı gönderildi.';
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Şifremi Unuttum — Provanya</title>
  <link rel="stylesheet" href="/assets/css/auth.css">
</head>
<body>
<div class="auth-wrapper">
  <div class="auth-brand">
    <div class="brand-logo">🔒</div>
    <h1 class="brand-name">Şifre Sıfırlama</h1>
    <p class="brand-tagline">E-posta adresinize sıfırlama bağlantısı göndereceğiz</p>
  </div>
  <div class="auth-card">
    <h2 class="auth-title">Şifremi Unuttum</h2>
    <p class="auth-subtitle">Kayıtlı e-posta adresinizi girin</p>

    <?php if ($message): ?>
      <div class="alert alert-success">✅ <?= htmlspecialchars($message) ?></div>
    <?php elseif ($error): ?>
      <div class="alert alert-error">⚠ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!$message): ?>
    <form method="POST" class="auth-form">
      <?= csrf_field() ?>
      <div class="form-group">
        <label class="form-label">E-posta Adresi</label>
        <input type="email" name="email" class="form-input" placeholder="firma@example.com" required>
      </div>
      <button type="submit" class="btn-auth">Sıfırlama Bağlantısı Gönder</button>
    </form>
    <?php endif; ?>

    <div class="auth-footer"><a href="/login">← Giriş Sayfasına Dön</a></div>
  </div>
</div>
</body>
</html>
