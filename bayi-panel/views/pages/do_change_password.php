<?php
// views/pages/do_change_password.php
use Auth\Auth;

Auth::require();
verify_csrf();

$current = $_POST['current_password'] ?? '';
$new     = $_POST['new_password']     ?? '';
$confirm = $_POST['confirm_password'] ?? '';

if (strlen($new) < 8) {
    $_SESSION['flash']['error'] = 'Yeni şifre en az 8 karakter olmalıdır.';
    redirect('/hesabim');
}

if ($new !== $confirm) {
    $_SESSION['flash']['error'] = 'Yeni şifreler eşleşmiyor.';
    redirect('/hesabim');
}

if (Auth::changePassword(Auth::id(), $current, $new)) {
    $_SESSION['flash']['success'] = 'Şifreniz başarıyla güncellendi.';
} else {
    $_SESSION['flash']['error'] = 'Mevcut şifre hatalı.';
}

redirect('/hesabim');
