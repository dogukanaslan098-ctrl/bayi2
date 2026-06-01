<?php
namespace Services;

use Helpers\Database;
use Api\WooCommerce;
use Auth\Auth;

/**
 * Sipariş Servisi
 * Transactional sipariş oluşturma, stok kilidi, iptal
 * Kritik: Eş zamanlı siparişlerde stok çakışması önlenir.
 */
class OrderService
{
    private WooCommerce $wc;

    public function __construct()
    {
        $this->wc = new WooCommerce();
    }

    /**
     * Sipariş Oluştur — Tam transactional
     *
     * @param array  $cartItems      [['wc_id', 'sku', 'name', 'quantity', 'unit_price'], ...]
     * @param string $paymentMethod  'card' | 'eft'
     * @return array ['success'=>bool, 'order_number'=>string, 'order_id'=>int, 'message'=>string]
     */
    public function createOrder(array $cartItems, string $paymentMethod): array
    {
        if (empty($cartItems)) {
            return ['success' => false, 'message' => 'Sepet boş'];
        }

        $dealer   = Auth::dealer();
        if (!$dealer) {
            return ['success' => false, 'message' => 'Oturum geçersiz'];
        }

        $discount = Auth::effectiveDiscount();

        Database::beginTransaction();

        try {
            // ── 1. STOK KONTROLÜ (FOR UPDATE ile kilitle) ────────
            $stockIssues = [];

            foreach ($cartItems as &$item) {
                $product = Database::fetchOne(
                    "SELECT wc_id, sku, name, stock_qty FROM product_cache
                     WHERE wc_id = ? LIMIT 1 FOR UPDATE",
                    [(int)$item['wc_id']]
                );

                if (!$product) {
                    throw new \RuntimeException("Ürün bulunamadı: SKU {$item['sku']}");
                }

                // Aktif stok kilitleri kontrol
                $locked = Database::fetchOne(
                    "SELECT COALESCE(SUM(locked_qty), 0) as total
                     FROM stock_locks
                     WHERE product_id = ? AND expires_at > NOW()",
                    [$product['wc_id']]
                );

                $lockedQty      = (int)($locked['total'] ?? 0);
                $availableStock = $product['stock_qty'] - $lockedQty;

                if ($availableStock < $item['quantity']) {
                    $stockIssues[] = [
                        'sku'       => $item['sku'],
                        'name'      => $product['name'],
                        'requested' => $item['quantity'],
                        'available' => max(0, $availableStock),
                    ];
                }

                $item['name'] = $product['name']; // DB'den güvenli isim
            }
            unset($item);

            // Stok yetersizse siparişi beklemeye al (iptal etme)
            $orderStatus = empty($stockIssues) ? 'processing' : 'pending';

            // ── 2. FİYAT HESAPLA ─────────────────────────────────
            $subtotal  = 0.0;
            $lineItems = [];

            foreach ($cartItems as $item) {
                $dealerPrice = round((float)$item['unit_price'] * (1 - $discount), 2);
                $lineTotal   = round($dealerPrice * (int)$item['quantity'], 2);
                $subtotal   += (float)$item['unit_price'] * (int)$item['quantity'];

                $lineItems[] = array_merge($item, [
                    'dealer_price' => $dealerPrice,
                    'line_total'   => $lineTotal,
                ]);
            }

            $discountAmount = round($subtotal * $discount, 2);
            $taxBase        = $subtotal - $discountAmount;
            $taxAmount      = round($taxBase * KDV_RATE, 2);
            $total          = round($taxBase + $taxAmount, 2);

            // ── 3. SİPARİŞ NUMARASI ──────────────────────────────
            $orderNumber = $this->generateOrderNumber();

            // ── 4. DB'YE KAYDET ──────────────────────────────────
            $orderId = Database::insert('orders', [
                'order_number'    => $orderNumber,
                'dealer_id'       => (int)$dealer['id'],
                'status'          => $orderStatus,
                'payment_method'  => $paymentMethod,
                'payment_status'  => 'unpaid',
                'subtotal'        => $subtotal,
                'discount_amount' => $discountAmount,
                'tax_amount'      => $taxAmount,
                'total'           => $total,
                'created_at'      => date('Y-m-d H:i:s'),
                'updated_at'      => date('Y-m-d H:i:s'),
            ]);

            foreach ($lineItems as $item) {
                Database::insert('order_items', [
                    'order_id'     => $orderId,
                    'product_id'   => (int)$item['wc_id'],
                    'sku'          => $item['sku'],
                    'name'         => $item['name'],
                    'quantity'     => (int)$item['quantity'],
                    'unit_price'   => (float)$item['unit_price'],
                    'dealer_price' => (float)$item['dealer_price'],
                    'line_total'   => (float)$item['line_total'],
                ]);

                // Stok kilidini uygula (15 dakika)
                if ($orderStatus === 'processing') {
                    Database::query("
                        INSERT INTO stock_locks (product_id, locked_qty, expires_at)
                        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))
                        ON DUPLICATE KEY UPDATE
                            locked_qty = locked_qty + VALUES(locked_qty),
                            expires_at = VALUES(expires_at)
                    ", [(int)$item['wc_id'], (int)$item['quantity']]);
                }
            }

            // ── 5. WOOCOMMERCE'E GÖNDER ───────────────────────────
            $wcOrderId = null;
            try {
                $wcOrder = $this->wc->createOrder($dealer, $lineItems, $paymentMethod);
                if (!empty($wcOrder['id'])) {
                    $wcOrderId = (int)$wcOrder['id'];
                    Database::query(
                        "UPDATE orders SET wc_order_id = ? WHERE id = ?",
                        [$wcOrderId, $orderId]
                    );
                }
            } catch (\Exception $wcEx) {
                // WC başarısız olsa yerel sipariş korunur, log yaz
                error_log("[OrderService] WC sync failed #{$orderNumber}: " . $wcEx->getMessage());
            }

            Database::commit();

            // ── 6. BİLDİRİM ──────────────────────────────────────
            $this->createNotification($dealer['id'], 'order_created', $orderNumber, $total);

            // ── 7. STOK SENKRONU ──────────────────────────────────
            if ($orderStatus === 'processing') {
                foreach ($lineItems as $item) {
                    try {
                        $this->wc->reduceStock((int)$item['wc_id'], (int)$item['quantity']);
                    } catch (\Exception $e) {
                        error_log("[OrderService] Stock reduce failed: " . $e->getMessage());
                    }
                }
            }

            return [
                'success'      => true,
                'order_number' => $orderNumber,
                'order_id'     => $orderId,
                'wc_order_id'  => $wcOrderId,
                'total'        => $total,
                'status'       => $orderStatus,
                'stock_issues' => $stockIssues,
            ];

        } catch (\Throwable $e) {
            Database::rollback();
            error_log("[OrderService] createOrder failed: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ── Sipariş Listesi ──────────────────────────────────────
    public function getDealerOrders(int $dealerId, string $status = '', int $limit = 50): array
    {
        $sql    = "SELECT o.*,
                       (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS item_count
                   FROM orders o
                   WHERE o.dealer_id = ?";
        $params = [$dealerId];

        if ($status) {
            $sql     .= " AND o.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY o.created_at DESC LIMIT " . (int)$limit;
        return Database::fetchAll($sql, $params);
    }

    // ── Sipariş Detayı ───────────────────────────────────────
    public function getOrderDetail(int $orderId, int $dealerId): ?array
    {
        $order = Database::fetchOne(
            "SELECT * FROM orders WHERE id = ? AND dealer_id = ?",
            [$orderId, $dealerId]
        );
        if (!$order) return null;

        $order['items'] = Database::fetchAll(
            "SELECT * FROM order_items WHERE order_id = ?",
            [$orderId]
        );
        return $order;
    }

    // ── Sipariş İptal ────────────────────────────────────────
    public function cancelOrder(int $orderId, int $dealerId): array
    {
        $order = Database::fetchOne(
            "SELECT * FROM orders WHERE id = ? AND dealer_id = ?",
            [$orderId, $dealerId]
        );

        if (!$order) {
            return ['success' => false, 'message' => 'Sipariş bulunamadı'];
        }

        // Kargoya verildikten sonra iptal edilemez
        if (in_array($order['status'], ['shipped', 'delivered', 'cancelled'])) {
            return [
                'success' => false,
                'message' => "'{$order['status']}' durumundaki sipariş iptal edilemez"
            ];
        }

        Database::beginTransaction();
        try {
            Database::query(
                "UPDATE orders SET status = 'cancelled', updated_at = NOW() WHERE id = ?",
                [$orderId]
            );

            // Stok kilidi serbest bırak
            $items = Database::fetchAll(
                "SELECT product_id, quantity FROM order_items WHERE order_id = ?",
                [$orderId]
            );
            foreach ($items as $item) {
                Database::query(
                    "UPDATE stock_locks
                     SET locked_qty = GREATEST(0, locked_qty - ?)
                     WHERE product_id = ?",
                    [$item['quantity'], $item['product_id']]
                );
            }

            // WC'de de iptal et
            if ($order['wc_order_id']) {
                try {
                    $this->wc->updateOrderStatus((int)$order['wc_order_id'], 'cancelled');
                } catch (\Exception $e) {
                    error_log("[OrderService] WC cancel failed: " . $e->getMessage());
                }
            }

            Database::commit();
            return ['success' => true, 'message' => 'Sipariş iptal edildi'];

        } catch (\Throwable $e) {
            Database::rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ── İstatistikler ────────────────────────────────────────
    public function getDealerStats(int $dealerId): array
    {
        $stats = Database::fetchOne("
            SELECT
                COUNT(*)                                            AS total_orders,
                COALESCE(SUM(total), 0)                            AS total_revenue,
                COALESCE(SUM(CASE WHEN MONTH(created_at) = MONTH(NOW())
                              AND YEAR(created_at) = YEAR(NOW())
                              THEN total ELSE 0 END), 0)           AS monthly_revenue,
                COALESCE(COUNT(CASE WHEN MONTH(created_at) = MONTH(NOW())
                               AND YEAR(created_at) = YEAR(NOW())
                               THEN 1 END), 0)                     AS monthly_orders,
                COALESCE(COUNT(CASE WHEN status IN ('pending','processing') THEN 1 END), 0)
                                                                   AS pending_orders
            FROM orders
            WHERE dealer_id = ? AND status != 'cancelled'
        ", [$dealerId]);

        return $stats ?? [];
    }

    // ── Aylık Ciro (grafik için) ─────────────────────────────
    public function getMonthlyRevenue(int $dealerId, int $months = 6): array
    {
        return Database::fetchAll("
            SELECT
                DATE_FORMAT(created_at, '%Y-%m') AS month,
                COALESCE(SUM(total), 0)           AS revenue,
                COUNT(*)                           AS order_count
            FROM orders
            WHERE dealer_id = ?
              AND status != 'cancelled'
              AND created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month ASC
        ", [$dealerId, $months]);
    }

    // ── Yardımcılar ──────────────────────────────────────────
    private function generateOrderNumber(): string
    {
        do {
            $num = 'BYS-' . strtoupper(substr(uniqid(), -6));
            $exists = Database::fetchOne(
                "SELECT id FROM orders WHERE order_number = ?", [$num]
            );
        } while ($exists);

        return $num;
    }

    private function createNotification(int $dealerId, string $type, string $orderNumber, float $total): void
    {
        try {
            Database::insert('notifications', [
                'dealer_id'  => $dealerId,
                'type'       => $type,
                'title'      => "Sipariş #{$orderNumber} oluşturuldu",
                'body'       => "Toplam: " . format_money($total),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            error_log("[OrderService] Notification failed: " . $e->getMessage());
        }
    }
}
