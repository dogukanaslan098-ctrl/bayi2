<?php
namespace Services;

use Helpers\Database;

/**
 * Sepet Servisi
 * Müşteri sepetini yönetir (Ekleme, silme, güncelleme)
 */
class CartService
{
    /**
     * Sepete ürün ekle veya miktarı güncelle
     */
    public static function addItem(int $dealerId, int $productId, string $sku, string $name, float $unitPrice, int $qty = 1): bool
    {
        if ($qty <= 0) $qty = 1;
        
        // Zaten sepette var mı?
        $existing = Database::fetchOne(
            "SELECT * FROM carts WHERE dealer_id = ? AND product_id = ?",
            [$dealerId, $productId]
        );
        
        if ($existing) {
            // Miktarı güncelle
            return Database::query(
                "UPDATE carts SET quantity = quantity + ? WHERE dealer_id = ? AND product_id = ?",
                [$qty, $dealerId, $productId]
            );
        } else {
            // Yeni ürün ekle
            return Database::query(
                "INSERT INTO carts (dealer_id, product_id, sku, name, quantity, unit_price) 
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$dealerId, $productId, $sku, $name, $qty, $unitPrice]
            );
        }
    }
    
    /**
     * Sepet öğesinin miktarını ayarla
     */
    public static function setQuantity(int $dealerId, int $productId, int $qty): bool
    {
        if ($qty <= 0) {
            // 0 veya daha az ise sil
            return self::removeItem($dealerId, $productId);
        }
        
        return Database::query(
            "UPDATE carts SET quantity = ? WHERE dealer_id = ? AND product_id = ?",
            [$qty, $dealerId, $productId]
        );
    }
    
    /**
     * Sepetten ürün sil
     */
    public static function removeItem(int $dealerId, int $productId): bool
    {
        return Database::query(
            "DELETE FROM carts WHERE dealer_id = ? AND product_id = ?",
            [$dealerId, $productId]
        );
    }
    
    /**
     * Sepeti temizle
     */
    public static function clear(int $dealerId): bool
    {
        return Database::query(
            "DELETE FROM carts WHERE dealer_id = ?",
            [$dealerId]
        );
    }
    
    /**
     * Sepeti getir
     */
    public static function getCart(int $dealerId): array
    {
        return Database::fetchAll(
            "SELECT * FROM carts WHERE dealer_id = ? ORDER BY added_at DESC",
            [$dealerId]
        ) ?? [];
    }
    
    /**
     * Sepet sayısı
     */
    public static function getCartCount(int $dealerId): int
    {
        $result = Database::scalar(
            "SELECT COUNT(*) FROM carts WHERE dealer_id = ?",
            [$dealerId]
        );
        return (int)$result;
    }
    
    /**
     * Sepet total'ü hesapla (indirim uygulanmış)
     */
    public static function getCartTotal(int $dealerId, float $discountRate = 0.0): array
    {
        $items = self::getCart($dealerId);
        
        $subtotal = 0.0;
        $discountAmount = 0.0;
        
        foreach ($items as $item) {
            $itemPrice = (float)$item['unit_price'] * (int)$item['quantity'];
            $subtotal += $itemPrice;
            
            if ($discountRate > 0) {
                $discountAmount += $itemPrice * $discountRate;
            }
        }
        
        $tax = ($subtotal - $discountAmount) * 0.20; // KDV
        $total = $subtotal - $discountAmount + $tax;
        
        return [
            'items_count' => count($items),
            'subtotal' => round($subtotal, 2),
            'discount_amount' => round($discountAmount, 2),
            'tax_amount' => round($tax, 2),
            'total' => round($total, 2),
        ];
    }
}
