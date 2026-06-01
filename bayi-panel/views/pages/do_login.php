<?php
// views/pages/do_login.php
use Auth\Auth;

verify_csrf();

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$redirect = $_POST['redirect'] ?? '/';

// Güvenli redirect kontrolü — sadece kendi domaine
if (!str_starts_with($redirect, '/') || str_starts_with($redirect, '//')) {
    $redirect = '/';
}

if (empty($email) || empty($password)) {
    $_SESSION['login_error'] = 'E-posta ve şifre zorunludur.';
    redirect('/login');
}

if (Auth::login($email, $password)) {
    redirect($redirect);
} else {
    $_SESSION['login_error'] = 'E-posta veya şifre hatalı. Lütfen tekrar deneyin.';
    redirect('/login' . ($redirect !== '/' ? '?redirect=' . urlencode($redirect) : ''));
}
