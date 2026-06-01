<?php
namespace Helpers;

use Auth\Auth;
use Helpers\Database;

/**
 * İndirim Bilgisi Yardımcısı
 * Müşteriye gösterilebilir indirim detaylarını hazırlar
 */
class DiscountHelper
{
    /**
     * Bayi'nin indirim detaylarını kompakt format da getir
     */
    public static function getDiscountDetails(int $dealerId): array
    {
        $dealer = Database::fetchOne(
            "SELECT level, discount_type, discount_value FROM dealers WHERE id = ? AND status = 'active'",
            [$dealerId]
        );
        
        if (!$dealer) return ['error' => 'Bayi bulunamadı'];
        
        $levels = [
            'bronz'    => ['label' => 'Bronz', 'discount' => 0.05, 'icon' => '🥉'],
            'silver'   => ['label' => 'Gümüş', 'discount' => 0.10, 'icon' => '🥈'],
            'gold'     => ['label' => 'Altın', 'discount' => 0.15, 'icon' => '🥇'],
            'platinum' => ['label' => 'Platinum', 'discount' => 0.20, 'icon' => '💎'],
        ];
        
        // 1. Seviye indirimi
        $levelInfo = $levels[$dealer['level']] ?? ['label' => 'Bilinmeyen', 'discount' => 0, 'icon' => '❓'];
        $levelDiscount = (float)$levelInfo['discount'];
        
        // 2. Kişisel indirim
        $personalDiscount = 0.0;
        $personalLabel = 'Yok';
        if ($dealer['discount_type'] === 'percent' && (float)$dealer['discount_value'] > 0) {
            $personalDiscount = (float)$dealer['discount_value'] / 100;
            $personalLabel = $dealer['discount_value'] . '%';
        } elseif ($dealer['discount_type'] === 'fixed' && (float)$dealer['discount_value'] > 0) {
            $personalLabel = '₺' . number_format((float)$dealer['discount_value'], 2, ',', '.');
        }
        
        // 3. Kampanya indirimi
        $campaign = Database::fetchOne(
            "SELECT * FROM campaigns
             WHERE is_active = 1
               AND NOW() BETWEEN starts_at AND ends_at
               AND (target = 'all' OR target = ?)
             ORDER BY discount_value DESC
             LIMIT 1",
            [$dealer['level']]
        );
        
        $campaignDiscount = 0.0;
        $campaignLabel = 'Yok';
        $campaignName = '';
        if ($campaign) {
            if ($campaign['discount_type'] === 'percent') {
                $campaignDiscount = (float)$campaign['discount_value'] / 100;
                $campaignLabel = $campaign['discount_value'] . '%';
            } else {
                $campaignLabel = '₺' . number_format((float)$campaign['discount_value'], 2, ',', '.');
            }
            $campaignName = $campaign['name'];
        }
        
        // Efektif indirim hesapla
        $baseDiscount = max($levelDiscount, $personalDiscount);
        $effectiveDiscount = min(0.50, $baseDiscount + $campaignDiscount);
        
        return [
            'level' => [
                'code' => $dealer['level'],
                'label' => $levelInfo['label'],
                'icon' => $levelInfo['icon'],
                'discount' => $levelDiscount,
                'percent' => number_format($levelDiscount * 100, 0),
            ],
            'personal' => [
                'type' => $dealer['discount_type'],
                'value' => (float)$dealer['discount_value'],
                'label' => $personalLabel,
                'discount' => $personalDiscount,
                'percent' => number_format($personalDiscount * 100, 0),
            ],
            'campaign' => [
                'active' => !empty($campaign),
                'name' => $campaignName,
                'discount' => $campaignDiscount,
                'percent' => number_format($campaignDiscount * 100, 0),
                'label' => $campaignLabel,
                'startDate' => $campaign['starts_at'] ?? null,
                'endDate' => $campaign['ends_at'] ?? null,
            ],
            'effective' => [
                'discount' => $effectiveDiscount,
                'percent' => number_format($effectiveDiscount * 100, 0),
                'source' => self::getEffectiveDiscountSource($levelDiscount, $personalDiscount, $campaignDiscount),
            ],
        ];
    }
    
    /**
     * Efektif indirimin hangi kaynaktan geldiğini belirle
     */
    private static function getEffectiveDiscountSource(float $level, float $personal, float $campaign): string
    {
        $sources = [];
        
        if ($level > 0) {
            $sources[] = "Seviye İndirimi (" . round($level * 100) . "%)";
        }
        if ($personal > 0 && $personal > $level) {
            $sources[] = "Özel İndirim (" . round($personal * 100) . "%)";
        }
        if ($campaign > 0) {
            $sources[] = "Kampanya (" . round($campaign * 100) . "%)";
        }
        
        return !empty($sources) ? implode(' + ', $sources) : 'Temel Fiyat';
    }
    
    /**
     * Bir ürün için gösterilecek fiyatları hesapla
     */
    public static function getPricing(float $listPrice, float $discount): array
    {
        $dealerPrice = round($listPrice * (1 - $discount), 2);
        $savings = $listPrice - $dealerPrice;
        
        return [
            'list_price' => $listPrice,
            'dealer_price' => $dealerPrice,
            'savings' => $savings,
            'savings_percent' => number_format(($discount * 100), 0),
        ];
    }
}
