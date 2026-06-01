<?php
// ============================================================
//  Bootstrap — Uygulama başlatma ve sınıf yükleme
// ============================================================

require_once __DIR__ . '/../config/config.php';

// ── Oturum güvenlik ayarları ─────────────────────────────────
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure',   APP_ENV === 'production' ? 1 : 0);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime',  SESSION_LIFETIME);
session_name(SESSION_NAME);
session_start();

// ── Hata raporlama ───────────────────────────────────────────
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    set_error_handler(function($code, $msg, $file, $line) {
        error_log("[{$code}] {$msg} in {$file}:{$line}");
    });
}

// ── PSR-4 benzeri sınıf yükleyici ────────────────────────────
spl_autoload_register(function (string $class): void {
    $base = __DIR__ . '/';
    $map  = [
        'Auth\\'     => 'Auth/',
        'Api\\'      => 'Api/',
        'Models\\'   => 'Models/',
        'Services\\' => 'Services/',
        'Helpers\\'  => 'Helpers/',
    ];
    foreach ($map as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $base . $dir . substr($class, strlen($prefix)) . '.php';
            if (file_exists($file)) { require_once $file; }
            return;
        }
    }
});

// ── Zaman dilimi ─────────────────────────────────────────────
date_default_timezone_set('Europe/Istanbul');

// ── CSRF token oluştur ───────────────────────────────────────
if (empty($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}

// ── Global yardımcı fonksiyonlar ─────────────────────────────

function csrf_token(): string
{
    return $_SESSION[CSRF_TOKEN_NAME] ?? '';
}

function csrf_field(): string
{
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . csrf_token() . '">';
}

function verify_csrf(): void
{
    $token = $_POST[CSRF_TOKEN_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION[CSRF_TOKEN_NAME] ?? '', $token)) {
        http_response_code(403);
        die('CSRF doğrulama başarısız');
    }
}

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): never
{
    header("Location: {$url}");
    exit;
}

function json_response(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function format_money(float $amount): string
{
    return '₺' . number_format($amount, 2, ',', '.');
}

function format_date(string $date): string
{
    return date('d.m.Y H:i', strtotime($date));
}
