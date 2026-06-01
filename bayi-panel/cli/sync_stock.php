#!/usr/bin/env php
<?php
// ============================================================
//  CLI Cron: Stok Senkronizasyonu
//  Çalıştırma: php /path/to/bayi-panel/cli/sync_stock.php
//  cPanel Cron: 0 * * * * php /home/user/bayi-panel/cli/sync_stock.php >> /home/user/bayi-panel/storage/logs/cron.log 2>&1
// ============================================================

// Web üzerinden erişimi engelle
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

// KESİN ÇÖZÜM (Tam Yol Tanımlama)
require_once '/home/provanya/bayi-panel/src/bootstrap.php';

use Api\WooCommerce;
use Helpers\Database;

$startTime = microtime(true);
$log       = fn(string $msg) => fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL);

$log('Stok senkronizasyonu başlatıldı...');

try {
    $wc       = new WooCommerce();
    $products = Database::fetchAll("SELECT wc_id, sku, stock_qty FROM product_cache ORDER BY wc_id");

    if (empty($products)) {
        $log('Ürün bulunamadı. Tam senkronizasyon yapılıyor...');
        $wc->getAllProducts();
        $log('Tam senkronizasyon tamamlandı.');
        exit(0);
    }

    $updated = 0;
    $errors  = 0;

    foreach ($products as $product) {
        try {
            $newStock = $wc->getStock((int)$product['wc_id']);

            if ($newStock !== (int)$product['stock_qty']) {
                Database::query(
                    "UPDATE product_cache SET stock_qty = ?, synced_at = NOW() WHERE wc_id = ?",
                    [$newStock, $product['wc_id']]
                );
                $log("Güncellendi: SKU={$product['sku']} | Eski={$product['stock_qty']} → Yeni={$newStock}");
                $updated++;
            }

            // API rate limit için kısa bekleme
            usleep(50000); // 50ms

        } catch (\Exception $e) {
            $log("HATA: SKU={$product['sku']} — " . $e->getMessage());
            $errors++;
        }
    }

    // Süresi geçmiş stok kilitlerini temizle
    $cleaned = Database::query(
        "DELETE FROM stock_locks WHERE expires_at < NOW()"
    )->rowCount();

    $elapsed = round(microtime(true) - $startTime, 2);
    $log("Tamamlandı: {$updated} güncellendi, {$errors} hata, {$cleaned} kilit temizlendi ({$elapsed}s)");

} catch (\Throwable $e) {
    $log('KRİTİK HATA: ' . $e->getMessage());
    exit(1);
}

exit(0);
