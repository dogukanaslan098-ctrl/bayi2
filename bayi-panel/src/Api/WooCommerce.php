<?php
namespace Api;

use Helpers\Database;

/**
 * WooCommerce REST API İstemcisi
 * Ürün çekme, stok senkronizasyonu, sipariş oluşturma
 */
class WooCommerce
{
    private string $baseUrl;
    private string $key;
    private string $secret;

    public function __construct()
    {
        $this->baseUrl = rtrim(WC_URL, '/') . '/wp-json/wc/' . WC_VERSION;
        $this->key     = WC_KEY;
        $this->secret  = WC_SECRET;
    }

    // ── HTTP İstek ───────────────────────────────────────────
    private function request(string $method, string $endpoint, array $data = []): array
    {
        $url = $this->baseUrl . $endpoint;
        $ch  = curl_init();

        $options = [
            CURLOPT_USERPWD        => "{$this->key}:{$this->secret}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ];

        if ($method === 'GET') {
            $options[CURLOPT_URL] = $data
                ? $url . '?' . http_build_query($data)
                : $url;
        } else {
            $options[CURLOPT_URL]           = $url;
            $options[CURLOPT_CUSTOMREQUEST] = $method;
            $options[CURLOPT_POSTFIELDS]    = json_encode($data);
        }

        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException("WooCommerce API bağlantı hatası: {$error}");
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            $msg = $decoded['message'] ?? 'Bilinmeyen hata';
            throw new \RuntimeException("WC API Hata {$httpCode}: {$msg}");
        }

        return $decoded ?? [];
    }

    // ── Tüm Ürünleri Çek (sayfalama + cache) ─────────────────
    public function getAllProducts(): array
    {
        $cacheFile = CACHE_DIR . 'all_products.json';

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < CACHE_PRODUCTS) {
            return json_decode(file_get_contents($cacheFile), true) ?? [];
        }

        $allProducts = [];
        $page        = 1;

        do {
            $products = $this->request('GET', '/products', [
                'per_page'     => 100,
                'page'         => $page,
                'status'       => 'publish',
                //'stock_status' => 'any',
            ]);

            if (empty($products)) break;

            foreach ($products as $p) {
                $this->syncProductToDb($p);
            }

            $allProducts = array_merge($allProducts, $products);
            $page++;

        } while (count($products) === 100); // 100 geliyorsa sonraki sayfa var

        if (!is_dir(CACHE_DIR)) mkdir(CACHE_DIR, 0755, true);
        file_put_contents($cacheFile, json_encode($allProducts));

        return $allProducts;
    }

    // ── Tekil Ürün ───────────────────────────────────────────
    public function getProduct(int $wcId): array
    {
        return $this->request('GET', "/products/{$wcId}");
    }

    // ── Anlık Stok Kontrolü ──────────────────────────────────
    public function getStock(int $wcId): int
    {
        $cacheFile = CACHE_DIR . "stock_{$wcId}.json";

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < CACHE_STOCK) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            return (int)($cached['qty'] ?? 0);
        }

        $product = $this->request('GET', "/products/{$wcId}");
        $qty     = (int)($product['stock_quantity'] ?? 0);

        file_put_contents($cacheFile, json_encode(['qty' => $qty, 'ts' => time()]));

        // DB'yi güncelle
        Database::query(
            "UPDATE product_cache SET stock_qty = ?, synced_at = NOW() WHERE wc_id = ?",
            [$qty, $wcId]
        );

        return $qty;
    }

    // ── WooCommerce'e Sipariş Oluştur ─────────────────────────
    public function createOrder(array $dealer, array $items, string $paymentMethod): array
    {
        $lineItems = array_map(fn($item) => [
            'product_id' => (int)$item['wc_id'],
            'quantity'   => (int)$item['quantity'],
            'price'      => (float)$item['dealer_price'], // Bayi fiyatını gönder
        ], $items);

        return $this->request('POST', '/orders', [
            'payment_method'       => 'bacs',
            'payment_method_title' => $paymentMethod === 'card' ? 'Kredi/Banka Kartı' : 'Havale/EFT',
            'set_paid'             => false,
            'status'               => 'processing',
            'billing' => [
                'first_name' => explode(' ', $dealer['contact_name'])[0] ?? '',
                'last_name'  => explode(' ', $dealer['contact_name'])[1] ?? '',
                'company'    => $dealer['company_name'],
                'email'      => $dealer['email'],
                'phone'      => $dealer['phone'],
                'address_1'  => $dealer['address'] ?? '',
                'city'       => $dealer['city'] ?? '',
                'country'    => 'TR',
            ],
            'shipping'    => [], // Billing ile aynı
            'line_items'  => $lineItems,
            'customer_id' => (int)($dealer['wc_customer_id'] ?? 0),
            'meta_data'   => [
                ['key' => '_bayi_panel_id',    'value' => (string)$dealer['id']],
                ['key' => '_bayi_panel_order', 'value' => '1'],
                ['key' => '_bayi_level',       'value' => $dealer['level']],
            ],
        ]);
    }

    // ── Sipariş Durumu Güncelle ───────────────────────────────
    public function updateOrderStatus(int $wcOrderId, string $status): void
    {
        $this->request('PUT', "/orders/{$wcOrderId}", ['status' => $status]);
    }

    // ── Kargo Bilgisi Ekle ────────────────────────────────────
    public function addTrackingNote(int $wcOrderId, string $cargo, string $code): void
    {
        $this->request('POST', "/orders/{$wcOrderId}/notes", [
            'note'            => "Kargo: {$cargo} | Takip: {$code}",
            'customer_note'   => true,
        ]);
    }

    // ── Stok Düş (sipariş sonrası) ────────────────────────────
    public function reduceStock(int $wcId, int $qty): void
    {
        $current = $this->getStock($wcId);
        $new     = max(0, $current - $qty);
        $this->request('PUT', "/products/{$wcId}", ['stock_quantity' => $new]);

        // Cache temizle
        @unlink(CACHE_DIR . "stock_{$wcId}.json");
    }

    // ── DB Senkronizasyon Yardımcısı ─────────────────────────
    private function syncProductToDb(array $p): void
    {
        $sku     = $p['sku'] ?? '';
        $price   = (float)($p['price'] ?? 0);
        $stock   = (int)($p['stock_quantity'] ?? 0);
        $image   = $p['images'][0]['src'] ?? null;
        $barcode = null;

        foreach ($p['meta_data'] ?? [] as $meta) {
            if (in_array($meta['key'], ['_barkod', 'barcode', '_barcode', 'barkod'])) {
                $barcode = $meta['value'];
                break;
            }
        }

        // Kategori adı
        $category = $p['categories'][0]['name'] ?? null;

        Database::query("
            INSERT INTO product_cache
                (wc_id, sku, barcode, name, price, stock_qty, image_url, category, synced_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                sku       = VALUES(sku),
                barcode   = VALUES(barcode),
                name      = VALUES(name),
                price     = VALUES(price),
                stock_qty = VALUES(stock_qty),
                image_url = VALUES(image_url),
                category  = VALUES(category),
                synced_at = NOW()
        ", [$p['id'], $sku, $barcode, $p['name'], $price, $stock, $image, $category]);
    }

    // ── Cache Temizle ────────────────────────────────────────
    public function clearCache(): void
    {
        $files = glob(CACHE_DIR . '*.json');
        if ($files) {
            foreach ($files as $file) { @unlink($file); }
        }
    }
}
