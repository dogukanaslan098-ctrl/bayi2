<?php
// ============================================================
//  Front Controller — Tüm istekler buraya yönlendirilir
//  .htaccess ile: RewriteRule ^ index.php [L,QSA]
// ============================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ============================================================
//  PATH: My Files -> /home/KULLANICI/bayi-panel
// ============================================================
$basePath = dirname(__DIR__, 3) . '/bayi-panel';

if (!is_dir($basePath)) {
    die("❌ bayi-panel bulunamadı: " . $basePath);
}

// ============================================================
//  BOOTSTRAP
// ============================================================
$bootstrap = $basePath . '/src/bootstrap.php';

if (!file_exists($bootstrap)) {
    die("❌ bootstrap.php bulunamadı: " . $bootstrap);
}

require_once $bootstrap;


use Auth\Auth;

$uri    = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') ?: '/';
$method = $_SERVER['REQUEST_METHOD'];

// ── Route Tanımları ──────────────────────────────────────────
$routes = [
    'GET' => [
        '/'                  => 'dashboard',
        '/login'             => 'login',
        '/logout'            => 'logout',
        '/sifremi-unuttum'   => 'forgot_password',
        '/sifre-sifirla'     => 'reset_password',
        '/basvuru'           => 'apply',
        '/urunler'           => 'products',
        '/siparis'           => 'quick_order',
        '/siparislerim'      => 'orders',
        '/siparis-detay'     => 'order_detail',
        '/fatura-indir'      => 'download_invoice',
        '/excel-siparis'     => 'excel_order',
        '/excel-sablon'      => 'excel_template',
        '/bildirimler'       => 'notifications',
        '/hesabim'           => 'profile',

        // JSON API uç noktaları
        '/api/urunler'       => 'api/products',
        '/api/stok'          => 'api/stock',
        '/api/bildirimler'   => 'api/notifications',
        '/api/sepet-kontrol' => 'api/cart_check',
    ],
    'POST' => [
        '/login'                => 'do_login',
        '/logout'               => 'do_logout',
        '/sifremi-unuttum'      => 'do_forgot_password',
        '/sifre-sifirla'        => 'do_reset_password',
        '/basvuru'              => 'do_apply',
        '/siparis/olustur'      => 'do_create_order',
        '/siparis/iptal'        => 'do_cancel_order',
        '/excel/isle'           => 'do_excel_import',
        '/hesabim/guncelle'     => 'do_update_profile',
        '/hesabim/sifre'        => 'do_change_password',
        '/bildirimler/okundu'   => 'do_mark_notifications_read',
    ],
];

// ── Statik Dosyalar ──────────────────────────────────────────
if (str_starts_with($uri, '/assets/')) {
    $file = __DIR__ . $uri;
    if (file_exists($file) && !is_dir($file)) {
        readfile($file);
    } else {
        http_response_code(404);
    }
    exit;
}

// ── Rate Limiting ────────────────────────────────────────────
rateLimitCheck();

// ── Route Eşleştir ──────────────────────────────────────────
$action = $routes[$method][$uri] ?? null;

if ($action === null) {
    // 404
    http_response_code(404);
    renderPage('404');
    exit;
}

// ── Public Rotalar (oturum gerekmez) ────────────────────────
$publicActions = [
    'login', 'do_login', 'forgot_password', 'do_forgot_password',
    'reset_password', 'do_reset_password', 'apply', 'do_apply',
];

if (!in_array($action, $publicActions)) {
    // API rotaları için JSON hata döndür
    if (str_starts_with($action, 'api/')) {
        if (!Auth::check()) {
            jsonResponse(['error' => 'Oturum gerekli'], 401);
        }
    } else {
        Auth::require();
    }
}

// ── API Rotaları ─────────────────────────────────────────────
if (str_starts_with($action, 'api/')) {
    header('Content-Type: application/json; charset=utf-8');
    renderPage($action);
    exit;
}

// ── Sayfa Render ─────────────────────────────────────────────
renderPage($action);

// ═══════════════════════════════════════════════════════════════
//  Yardımcı Fonksiyonlar
// ═══════════════════════════════════════════════════════════════

function renderPage(string $action): void
{
    $file = ROOT_PATH . '/views/pages/' . $action . '.php';
    if (!file_exists($file)) {
        http_response_code(404);
        $file = ROOT_PATH . '/views/pages/404.php';
        if (!file_exists($file)) { echo '<h1>404 — Sayfa bulunamadı</h1>'; return; }
    }
    require $file;
}

function jsonResponse(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function rateLimitCheck(): void
{
    $ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = 'rl_' . md5($ip);

    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'reset' => time() + RATE_LIMIT_WINDOW];
    }

    if (time() > $_SESSION[$key]['reset']) {
        $_SESSION[$key] = ['count' => 0, 'reset' => time() + RATE_LIMIT_WINDOW];
    }

    $_SESSION[$key]['count']++;

    if ($_SESSION[$key]['count'] > RATE_LIMIT_REQUESTS) {
        http_response_code(429);
        header('Retry-After: ' . ($_SESSION[$key]['reset'] - time()));
        die(json_encode(['error' => 'Çok fazla istek. Lütfen bekleyin.']));
    }
}
