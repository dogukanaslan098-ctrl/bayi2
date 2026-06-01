<?php
namespace Services;

/**
 * E-posta Servisi
 * PHPMailer ile sipariş bildirimleri gönderir.
 * Kurulum: composer require phpmailer/phpmailer
 */
class MailService
{
    /**
     * Sipariş oluşturuldu bildirimi
     */
    public static function sendOrderCreated(array $dealer, array $order): bool
    {
        $total    = format_money((float)$order['total']);
        $subject  = "Sipariş #{$order['order_number']} Alındı — Provanya Bayi";
        $body     = self::wrapTemplate("Siparişiniz Alındı 🎉", "
            <p>Sayın <strong>" . e($dealer['company_name']) . "</strong>,</p>
            <p>Sipariş numaranız: <strong>#{$order['order_number']}</strong><br>
            Toplam tutar: <strong>{$total}</strong><br>
            Ödeme yöntemi: <strong>" . ($order['payment_method'] === 'card' ? 'Kredi/Banka Kartı' : 'Havale/EFT') . "</strong></p>
            <p>Siparişiniz incelemeye alınmıştır. Stok onayı sonrası hazırlanmaya başlanacaktır.</p>
            <a href='" . APP_URL . "/siparislerim' style='background:#3b82f6;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;display:inline-block;margin-top:12px'>Siparişimi Görüntüle</a>
        ");

        return self::send($dealer['email'], $subject, $body);
    }

    /**
     * Kargoya verildi bildirimi
     */
    public static function sendOrderShipped(array $dealer, array $order): bool
    {
        $subject = "Sipariş #{$order['order_number']} Kargoya Verildi — Provanya Bayi";
        $body    = self::wrapTemplate("Siparişiniz Yola Çıktı 🚚", "
            <p>Sayın <strong>" . e($dealer['company_name']) . "</strong>,</p>
            <p>Sipariş numaranız <strong>#{$order['order_number']}</strong> kargoya teslim edilmiştir.</p>
            <table style='border-collapse:collapse;width:100%;margin:16px 0'>
                <tr><td style='padding:8px;background:#f8fafc;font-weight:600'>Kargo Firması</td><td style='padding:8px'>" . e($order['cargo_company']) . "</td></tr>
                <tr><td style='padding:8px;background:#f8fafc;font-weight:600'>Takip Kodu</td><td style='padding:8px'><strong>" . e($order['cargo_code']) . "</strong></td></tr>
            </table>
            <a href='" . APP_URL . "/siparislerim' style='background:#3b82f6;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;display:inline-block'>Takip Et</a>
        ");

        return self::send($dealer['email'], $subject, $body);
    }

    /**
     * Ödeme alındı bildirimi
     */
    public static function sendPaymentReceived(array $dealer, array $order): bool
    {
        $total   = format_money((float)$order['total']);
        $subject = "Ödeme Alındı #{$order['order_number']} — Provanya Bayi";
        $body    = self::wrapTemplate("Ödemeniz Alındı ✅", "
            <p>Sayın <strong>" . e($dealer['company_name']) . "</strong>,</p>
            <p>Sipariş #{$order['order_number']} için <strong>{$total}</strong> tutarındaki ödemeniz alınmıştır.</p>
            <p>Siparişiniz hazırlanmaya başlanacaktır.</p>
        ");

        return self::send($dealer['email'], $subject, $body);
    }

    /**
     * Şifre sıfırlama e-postası
     */
    public static function sendPasswordReset(string $email, string $token): bool
    {
        $link    = APP_URL . '/sifre-sifirla?token=' . $token;
        $subject = 'Şifre Sıfırlama — Provanya Bayi';
        $body    = self::wrapTemplate('Şifre Sıfırlama', "
            <p>Şifre sıfırlama talebiniz alındı.</p>
            <p>Aşağıdaki bağlantı <strong>1 saat</strong> geçerlidir:</p>
            <a href='{$link}' style='background:#3b82f6;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;display:inline-block;margin:12px 0'>Şifremi Sıfırla</a>
            <p style='color:#888;font-size:12px'>Bu talebi siz yapmadıysanız bu e-postayı dikkate almayın.</p>
        ");

        return self::send($email, $subject, $body);
    }

    /**
     * Bayi başvuru onayı
     */
    public static function sendApplicationApproved(array $dealer, string $tempPassword): bool
    {
        $subject = 'Bayi Başvurunuz Onaylandı — Provanya';
        $body    = self::wrapTemplate('Başvurunuz Onaylandı 🎉', "
            <p>Sayın <strong>" . e($dealer['company_name']) . "</strong>,</p>
            <p>Provanya bayi başvurunuz onaylanmıştır. Aşağıdaki bilgilerle giriş yapabilirsiniz:</p>
            <table style='border-collapse:collapse;width:100%;margin:16px 0'>
                <tr><td style='padding:8px;background:#f8fafc;font-weight:600'>Giriş Adresi</td><td style='padding:8px'>" . APP_URL . "</td></tr>
                <tr><td style='padding:8px;background:#f8fafc;font-weight:600'>E-posta</td><td style='padding:8px'>" . e($dealer['email']) . "</td></tr>
                <tr><td style='padding:8px;background:#f8fafc;font-weight:600'>Geçici Şifre</td><td style='padding:8px'><strong>{$tempPassword}</strong></td></tr>
            </table>
            <p style='color:#ef4444'>Güvenliğiniz için ilk girişte şifrenizi değiştirin.</p>
            <a href='" . APP_URL . "/login' style='background:#3b82f6;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;display:inline-block'>Giriş Yap</a>
        ");

        return self::send($dealer['email'], $subject, $body);
    }

    // ── Core Gönderici ────────────────────────────────────────
    private static function send(string $to, string $subject, string $body): bool
    {
        // PHPMailer yüklüyse kullan
        if (class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
            return self::sendWithPhpMailer($to, $subject, $body);
        }

        // Fallback: PHP mail()
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
        $headers .= "Reply-To: " . MAIL_FROM . "\r\n";

        return mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
    }

    private static function sendWithPhpMailer(string $to, string $subject, string $body): bool
    {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_USER;
            $mail->Password   = MAIL_PASS;
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = MAIL_PORT;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;

            $mail->send();
            return true;
        } catch (\Exception $e) {
            error_log("[MailService] Send failed to {$to}: " . $e->getMessage());
            return false;
        }
    }

    // ── HTML Wrapper ─────────────────────────────────────────
    private static function wrapTemplate(string $title, string $content): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="tr">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif">
  <table width="100%" cellpadding="0" cellspacing="0">
    <tr><td align="center" style="padding:30px 20px">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08)">
        <tr><td style="background:#3b82f6;padding:24px 32px">
          <div style="font-size:20px;font-weight:bold;color:#fff">🏪 Provanya Bayi Portalı</div>
        </td></tr>
        <tr><td style="padding:32px">
          <h2 style="margin:0 0 20px;font-size:18px;color:#1e293b">{$title}</h2>
          {$content}
        </td></tr>
        <tr><td style="background:#f8fafc;padding:16px 32px;border-top:1px solid #e2e8f0">
          <p style="margin:0;font-size:11px;color:#94a3b8;text-align:center">
            Bu e-posta Provanya Bayi Paneli tarafından gönderilmiştir.<br>
            bayi@provanya.com | bayi.provanya.com
          </p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    }
}
