# B2B Bayi Panel — Kurulum & Dokümantasyon

## Proje Yapısı
```
b2b-bayi-panel/
├── config/config.php              ← DB, WC API, Mail yapılandırması
├── database/schema.sql            ← MySQL şeması (6 tablo)
├── src/
│   ├── bootstrap.php              ← Uygulama başlatıcı + autoloader
│   ├── Auth/Auth.php              ← Giriş, oturum, indirim hesaplama
│   ├── Api/WooCommerce.php        ← WC REST API istemcisi (cache)
│   ├── Services/
│   │   ├── OrderService.php       ← Transactional sipariş + stok kilidi
│   │   ├── ExcelImportService.php ← CSV/XLSX toplu import
│   │   ├── InvoiceService.php     ← PDF/HTML fatura üretici
│   │   └── MailService.php        ← PHPMailer bildirimleri
│   └── Helpers/Database.php       ← PDO singleton
├── public/
│   ├── index.php                  ← Front controller (router)
│   ├── .htaccess                  ← URL rewrite + güvenlik
│   └── assets/{css,js}            ← panel.css, auth.css, panel.js
├── views/
│   ├── layouts/app.php            ← Sidebar + topbar layout
│   └── pages/                     ← Tüm sayfalar ve handler'lar
├── storage/{cache,logs,invoices}  ← Çalışma zamanı dosyaları
└── cli/sync_stock.php             ← Cron: saatlik stok sync
```

## Gereksinimler
- PHP 8.1+ (PDO, pdo_mysql, curl, json, zip)
- MySQL 5.7+ / MariaDB 10.3+
- Paylaşımlı hosting (cPanel, mod_rewrite aktif)

## Kurulum Adımları

### 1. Dosya Yapısı
```
/home/kullanici/
  bayi-panel/      ← proje kökü (web DIŞI, güvenli)
  public_html/     ← OR bayi subdomain kökü
    index.php      ← public/ içeriği buraya
    .htaccess
    assets/
```

### 2. Veritabanı (cPanel > phpMyAdmin)
```sql
-- Yeni DB: bayi_panel
-- database/schema.sql içeriğini SQL sekmesinde çalıştır
```

### 3. config/config.php Düzenle
```php
define('DB_NAME', 'bayi_panel');
define('DB_USER', 'bayi_user');
define('DB_PASS', 'GüçlüŞifre123!');
define('WC_URL',  'https://provanya.com');
define('WC_KEY',  'ck_XXXXX');   // WC Admin > REST API
define('WC_SECRET','cs_XXXXX');
```

### 4. WC API Anahtarı
```
provanya.com/wp-admin
→ WooCommerce > Ayarlar > Gelişmiş > REST API
→ Yeni anahtar: Okuma/Yazma yetkisi
→ ck_ ve cs_ → config.php'ye yapıştır
```

### 5. Dizin İzinleri
```
storage/        → 755
storage/cache/  → 755
storage/logs/   → 755
storage/invoices/→ 755
```

### 6. İlk Bayi (phpMyAdmin SQL)
```sql
INSERT INTO dealers
  (company_name,contact_name,email,phone,password_hash,level,discount_type,discount_value,status)
VALUES
  ('Firma A.Ş.','Yetkili Kişi','mail@firma.com','05001234567',
   '$2y$12$HASH',  -- PHP: password_hash('sifre', PASSWORD_BCRYPT)
   'gold','percent',15.00,'active');
```

### 7. Cron Job (Stok Sync)
```
cPanel → Cron Jobs → Her saat başı:
0 * * * * php /home/KULL/bayi-panel/cli/sync_stock.php
```

### 8. HTTPS Aktif Et (.htaccess)
```apache
# Bu satırı uncomment et:
# RewriteCond %{HTTPS} off
# RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

## Fiyatlandırma Mantığı
```
Efektif İndirim = max(SeviyeIndirimi, KişiselIndirim) + Kampanya
Seviyeler: Bronz=%5  Silver=%10  Gold=%15  Platinum=%20
Bayi seviyesini GÖRMEZ — sistem arka planda uygular
```

## Güvenlik
- CSRF token: Tüm formlarda aktif
- SQL Injection: PDO prepared statements
- XSS: htmlspecialchars() ile tüm çıktılar
- Oturum: httponly + samesite=Strict + secure
- Şifre: bcrypt cost=12
- Stok kilidi: MySQL SELECT FOR UPDATE + lock tablosu
- Rate limit: Dakikada 100 istek
- storage/: .htaccess ile web erişimi kapalı

## API Endpoint'leri
| Endpoint | Yöntem | Açıklama |
|----------|--------|----------|
| /api/urunler | GET | Ürün listesi (q, cat, page) |
| /api/stok | GET | Stok sorgu (?wc_id=X) |
| /siparis/olustur | POST | Sipariş oluştur (JSON) |
| /siparis/iptal | POST | Sipariş iptal |
| /excel/isle | POST | CSV/XLSX işle |
