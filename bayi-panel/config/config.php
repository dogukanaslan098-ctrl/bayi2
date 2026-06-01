<?php
// ============================================================
//  B2B Bayi Paneli — Ana Yapılandırma Dosyası
//  Bu dosya web'den erişilemeyen bir dizinde olmalıdır.
// ============================================================

define('APP_NAME', 'Provanya Bayi Paneli');
define('APP_URL',  'https://bayi.provanya.com');
define('APP_ENV',  'production'); // development | production

// ── Veritabanı ──────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'provanya_bayipanel');
define('DB_USER', 'provanya_bayipanel');
define('DB_PASS', 'qkSHeH3DWbmEKBjEfbwG');
define('DB_CHAR', 'utf8mb4');

// ── WooCommerce REST API ─────────────────────────────────────
// provanya.com/wp-admin → WooCommerce > Ayarlar > Gelişmiş > REST API
define('WC_URL',    'https://provanya.com');
define('WC_KEY',    'ck_d16a16e87aa3bf64b5cc3927aa5aa60f2a00273f');
define('WC_SECRET', 'cs_b652485fb037188500c78f4c34b4b5a2ec3e224d');
define('WC_VERSION','v3');

// ── Cache ────────────────────────────────────────────────────
define('CACHE_DIR',      __DIR__ . '/../storage/cache/');
define('CACHE_PRODUCTS', 3600);   // Ürün cache: 1 saat
define('CACHE_STOCK',    300);    // Stok cache: 5 dakika

// ── Oturum ──────────────────────────────────────────────────
define('SESSION_NAME',     'bayi_session');
define('SESSION_LIFETIME',  7200);  // 2 saat

// ── E-posta ─────────────────────────────────────────────────
define('MAIL_HOST',      'mail.provanya.com');
define('MAIL_PORT',      587);
define('MAIL_USER',      'bayi@provanya.com');
define('MAIL_PASS',      'MAIL_ŞİFRESİ');
define('MAIL_FROM',      'bayi@provanya.com');
define('MAIL_FROM_NAME', 'Provanya Bayi Portalı');

// ── Vergi & Fiyatlandırma ────────────────────────────────────
define('KDV_RATE', 0.20); // %20 KDV

// ── Bayi Seviyeleri (bayiye gösterilmez — arka planda uygulanır) ──
define('DEALER_LEVELS', [
    'bronz'    => ['min_orders' => 0,   'discount' => 0.05],  // %5
    'silver'   => ['min_orders' => 20,  'discount' => 0.10],  // %10
    'gold'     => ['min_orders' => 50,  'discount' => 0.15],  // %15
    'platinum' => ['min_orders' => 100, 'discount' => 0.20],  // %20
]);

// ── Kargo Firmaları ─────────────────────────────────────────
define('CARGO_COMPANIES', [
    'MNG Kargo', 'Aras Kargo', 'Yurtiçi Kargo', 'PTT Kargo', 'Sürat Kargo',
]);

// ── Güvenlik ─────────────────────────────────────────────────
define('CSRF_TOKEN_NAME',    '_csrf');
define('RATE_LIMIT_REQUESTS', 100);  // Dakikada maksimum istek
define('RATE_LIMIT_WINDOW',   60);   // Saniye

define('ROOT_PATH', dirname(__DIR__));
