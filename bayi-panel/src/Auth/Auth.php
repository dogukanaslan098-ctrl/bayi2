<?php
namespace Auth;

use Helpers\Database;

/**
 * Kimlik Doğrulama Servisi
 * Giriş, çıkış, oturum kontrolü, şifre sıfırlama
 * ve efektif bayi indirim hesaplama içerir.
 */
class Auth
{
    // ── Giriş ───────────────────────────────────────────────
    public static function login(string $email, string $password): bool
    {
        $email  = strtolower(trim($email));
        $dealer = Database::fetchOne(
            "SELECT * FROM dealers WHERE email = ? AND status = 'active' LIMIT 1",
            [$email]
        );

        if (!$dealer || !password_verify($password, $dealer['password_hash'])) {
            // Brute-force geciktirmesi
            usleep(random_int(200000, 400000));
            return false;
        }

        // Oturumu başlat
        $_SESSION['dealer_id']            = $dealer['id'];
        $_SESSION['dealer_company']        = $dealer['company_name'];
        $_SESSION['dealer_email']          = $dealer['email'];
        $_SESSION['dealer_level']          = $dealer['level'];
        $_SESSION['dealer_discount_type']  = $dealer['discount_type'];
        $_SESSION['dealer_discount_value'] = $dealer['discount_value'];
        $_SESSION['login_at']             = time();

        // Son giriş güncelle
        Database::query(
            "UPDATE dealers SET last_login = NOW() WHERE id = ?",
            [$dealer['id']]
        );

        session_regenerate_id(true);
        return true;
    }

    // ── Çıkış ───────────────────────────────────────────────
    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']
            );
        }
        session_destroy();
    }

    // ── Oturum Kontrolü ─────────────────────────────────────
    public static function check(): bool
    {
        if (empty($_SESSION['dealer_id'])) return false;
        if (time() - ($_SESSION['login_at'] ?? 0) > SESSION_LIFETIME) {
            self::logout();
            return false;
        }
        return true;
    }

    // ── Sayfa Koruma ────────────────────────────────────────
    public static function require(): void
    {
        if (!self::check()) {
            header('Location: /login?redirect=' . urlencode($_SERVER['REQUEST_URI']));
            exit;
        }
    }

    // ── Aktif Bayi Verisi ────────────────────────────────────
    public static function dealer(): ?array
    {
        if (!self::check()) return null;
        return Database::fetchOne(
            "SELECT * FROM dealers WHERE id = ? AND status = 'active' LIMIT 1",
            [(int) $_SESSION['dealer_id']]
        );
    }

    // ── Dealer ID ────────────────────────────────────────────
    public static function id(): int
    {
        return (int) ($_SESSION['dealer_id'] ?? 0);
    }

    // ── Efektif İndirim Hesaplama ────────────────────────────
    // Seviye + kişisel indirim + aktif kampanya → en yüksek uygulanır
    public static function effectiveDiscount(): float
    {
        $dealer = self::dealer();
        if (!$dealer) return 0.0;

        // 1. Seviye bazlı indirim
        $levels       = DEALER_LEVELS;
        $levelDisc    = (float)($levels[$dealer['level']]['discount'] ?? 0);

        // 2. Kişisel indirim (admin tarafından özel tanımlanmış)
        $personalDisc = 0.0;
        if ($dealer['discount_type'] === 'percent') {
            $personalDisc = (float)$dealer['discount_value'] / 100;
        } elseif ($dealer['discount_type'] === 'fixed') {
            // Sabit indirim → ürün bazında değil sipariş bazında uygulanır
            // Bu hesaplamada pas geç, OrderService'te ayrıca handle edilir
            $personalDisc = 0.0;
        }

        // 3. Aktif kampanya indirimi
        $campaign = Database::fetchOne(
            "SELECT * FROM campaigns
             WHERE is_active = 1
               AND NOW() BETWEEN starts_at AND ends_at
               AND (target = 'all' OR target = ?)
             ORDER BY discount_value DESC
             LIMIT 1",
            [$dealer['level']]
        );
        $campaignDisc = 0.0;
        if ($campaign && $campaign['discount_type'] === 'percent') {
            $campaignDisc = (float)$campaign['discount_value'] / 100;
        }

        // Seviye veya kişisel indirimden büyük olanı al, üstüne kampanya ekle
        $base = max($levelDisc, $personalDisc);
        return min(0.50, $base + $campaignDisc); // Maksimum %50 indirim güvenliği
    }

    // ── Şifre Sıfırlama Token Oluştur ────────────────────────
    public static function createResetToken(string $email): ?string
    {
        $dealer = Database::fetchOne(
            "SELECT id FROM dealers WHERE email = ? AND status = 'active'",
            [strtolower($email)]
        );
        if (!$dealer) return null;

        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        Database::query(
            "UPDATE dealers SET reset_token = ?, reset_expires = ? WHERE id = ?",
            [$token, $expires, $dealer['id']]
        );

        return $token;
    }

    // ── Şifre Sıfırla ────────────────────────────────────────
    public static function resetPassword(string $token, string $newPassword): bool
    {
        if (strlen($newPassword) < 8) return false;

        $dealer = Database::fetchOne(
            "SELECT id FROM dealers WHERE reset_token = ? AND reset_expires > NOW()",
            [$token]
        );
        if (!$dealer) return false;

        Database::query(
            "UPDATE dealers SET
                password_hash = ?,
                reset_token   = NULL,
                reset_expires = NULL
             WHERE id = ?",
            [
                password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]),
                $dealer['id'],
            ]
        );
        return true;
    }

    // ── Şifre Değiştir ────────────────────────────────────────
    public static function changePassword(int $dealerId, string $current, string $newPass): bool
    {
        if (strlen($newPass) < 8) return false;

        $dealer = Database::fetchOne(
            "SELECT password_hash FROM dealers WHERE id = ?",
            [$dealerId]
        );
        if (!$dealer || !password_verify($current, $dealer['password_hash'])) {
            return false;
        }

        Database::query(
            "UPDATE dealers SET password_hash = ? WHERE id = ?",
            [password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]), $dealerId]
        );
        return true;
    }
}
