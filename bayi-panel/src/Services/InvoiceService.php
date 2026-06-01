<?php
namespace Services;

use Helpers\Database;

/**
 * PDF Fatura Üretici
 * TCPDF yüklüyse PDF, değilse HTML fatura oluşturur.
 * Kurulum: composer require tecnickcom/tcpdf
 */
class InvoiceService
{
    private string $invoiceDir;

    public function __construct()
    {
        $this->invoiceDir = ROOT_PATH . '/storage/invoices/';
        if (!is_dir($this->invoiceDir)) {
            mkdir($this->invoiceDir, 0755, true);
        }
    }

    /**
     * Sipariş için fatura oluştur
     * @return string|null Dosya yolu veya null (hata)
     */
    public function generate(int $orderId): ?string
    {
        $order = Database::fetchOne("
            SELECT
                o.*,
                d.company_name, d.contact_name, d.email, d.phone,
                d.tax_office,   d.tax_number,   d.tckn,
                d.address,      d.city,          d.district,    d.postal_code
            FROM orders o
            JOIN dealers d ON d.id = o.dealer_id
            WHERE o.id = ?
        ", [$orderId]);

        if (!$order) return null;

        $items = Database::fetchAll(
            "SELECT * FROM order_items WHERE order_id = ? ORDER BY id",
            [$orderId]
        );

        $invoiceNo = 'FTR-' . $order['order_number'];
        $filename  = $invoiceNo . '.pdf';
        $path      = $this->invoiceDir . $filename;

        // HTML şablonu render et
        $html = $this->renderTemplate($order, $items, $invoiceNo);

        // TCPDF varsa PDF üret
        if (class_exists('\TCPDF')) {
            $this->generateWithTcpdf($html, $path, $invoiceNo);
        } else {
            // Fallback: HTML olarak kaydet
            $filename = $invoiceNo . '.html';
            $path     = $this->invoiceDir . $filename;
            file_put_contents($path, $html);
        }

        // DB'ye kaydet
        Database::query(
            "UPDATE orders SET invoice_path = ? WHERE id = ?",
            [$filename, $orderId]
        );

        return $path;
    }

    /**
     * Fatura HTML şablonu
     */
    private function renderTemplate(array $order, array $items, string $invoiceNo): string
    {
        $date        = date('d.m.Y', strtotime($order['created_at']));
        $companyName = htmlspecialchars($order['company_name']);
        $contactName = htmlspecialchars($order['contact_name']);
        $address     = htmlspecialchars($order['address'] . ' ' . $order['city']);
        $taxOffice   = htmlspecialchars($order['tax_office'] ?? '');
        $taxNo       = htmlspecialchars($order['tax_number'] ?? '');
        $email       = htmlspecialchars($order['email']);

        $rowsHtml = '';
        foreach ($items as $i => $item) {
            $no       = $i + 1;
            $name     = htmlspecialchars($item['name']);
            $sku      = htmlspecialchars($item['sku']);
            $qty      = (int)$item['quantity'];
            $unitP    = number_format((float)$item['dealer_price'], 2, ',', '.');
            $lineT    = number_format((float)$item['line_total'], 2, ',', '.');

            $rowsHtml .= "
            <tr>
                <td>{$no}</td>
                <td>{$name}<br><small style='color:#888'>{$sku}</small></td>
                <td style='text-align:center'>{$qty}</td>
                <td style='text-align:right'>₺{$unitP}</td>
                <td style='text-align:right'>₺{$lineT}</td>
            </tr>";
        }

        $subtotal = number_format((float)$order['subtotal'], 2, ',', '.');
        $discount = number_format((float)$order['discount_amount'], 2, ',', '.');
        $tax      = number_format((float)$order['tax_amount'], 2, ',', '.');
        $total    = number_format((float)$order['total'], 2, ',', '.');
        $payment  = $order['payment_method'] === 'card' ? 'Kredi/Banka Kartı' : 'Havale/EFT';

        return <<<HTML
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Arial, sans-serif; font-size: 12px; color: #333; margin: 0; padding: 20px; }
  .header { display: flex; justify-content: space-between; margin-bottom: 30px; border-bottom: 2px solid #3b82f6; padding-bottom: 15px; }
  .logo { font-size: 22px; font-weight: bold; color: #3b82f6; }
  .invoice-title { font-size: 20px; font-weight: bold; color: #333; text-align: right; }
  .invoice-no { font-size: 14px; color: #666; }
  .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px; }
  .info-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; }
  .info-box h4 { margin: 0 0 8px; font-size: 11px; text-transform: uppercase; color: #888; letter-spacing: 0.5px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
  th { background: #3b82f6; color: #fff; padding: 8px 10px; text-align: left; font-size: 11px; }
  td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; }
  tr:last-child td { border-bottom: none; }
  .totals { margin-left: auto; width: 280px; }
  .totals table { margin-bottom: 0; }
  .totals td { font-size: 12px; }
  .totals .grand-total td { font-size: 14px; font-weight: bold; color: #3b82f6; background: #eff6ff; }
  .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #e2e8f0; font-size: 11px; color: #888; text-align: center; }
</style>
</head>
<body>
  <div class="header">
    <div>
      <div class="logo">🏪 Provanya</div>
      <div style="font-size:11px;color:#888;margin-top:4px">provanya.com</div>
    </div>
    <div style="text-align:right">
      <div class="invoice-title">FATURA</div>
      <div class="invoice-no">{$invoiceNo}</div>
      <div style="font-size:11px;color:#888;margin-top:4px">Tarih: {$date}</div>
    </div>
  </div>

  <div class="info-grid">
    <div class="info-box">
      <h4>Alıcı Bilgileri</h4>
      <strong>{$companyName}</strong><br>
      {$contactName}<br>
      {$address}<br>
      {$email}
    </div>
    <div class="info-box">
      <h4>Vergi Bilgileri</h4>
      Vergi Dairesi: <strong>{$taxOffice}</strong><br>
      Vergi No: <strong>{$taxNo}</strong><br><br>
      Ödeme Yöntemi: <strong>{$payment}</strong><br>
      Sipariş No: <strong>{$order['order_number']}</strong>
    </div>
  </div>

  <table>
    <thead>
      <tr><th>#</th><th>Ürün</th><th style="text-align:center">Adet</th><th style="text-align:right">Birim Fiyat</th><th style="text-align:right">Toplam</th></tr>
    </thead>
    <tbody>
      {$rowsHtml}
    </tbody>
  </table>

  <div style="display:flex;justify-content:flex-end">
    <div class="totals">
      <table>
        <tr><td>Ara Toplam</td><td style="text-align:right">₺{$subtotal}</td></tr>
        <tr><td style="color:#16a34a">İndirim</td><td style="text-align:right;color:#16a34a">-₺{$discount}</td></tr>
        <tr><td>KDV (%20)</td><td style="text-align:right">₺{$tax}</td></tr>
        <tr class="grand-total"><td><strong>GENEL TOPLAM</strong></td><td style="text-align:right"><strong>₺{$total}</strong></td></tr>
      </table>
    </div>
  </div>

  <div class="footer">
    Bu fatura {$date} tarihinde Provanya Bayi Paneli tarafından otomatik oluşturulmuştur.<br>
    Sorularınız için: bayi@provanya.com | provanya.com
  </div>
</body>
</html>
HTML;
    }

    /**
     * TCPDF ile PDF Oluştur
     */
    private function generateWithTcpdf(string $html, string $path, string $title): void
    {
        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator(APP_NAME);
        $pdf->SetAuthor('Provanya');
        $pdf->SetTitle($title);
        $pdf->SetHeaderData('', 0, '', '');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Output($path, 'F');
    }

    /**
     * Tarayıcıya fatura gönder (inline veya download)
     */
    public function sendToClient(int $orderId, bool $download = true): void
    {
        $order = Database::fetchOne(
            "SELECT invoice_path FROM orders WHERE id = ?", [$orderId]
        );

        if (!$order || !$order['invoice_path']) {
            $path = $this->generate($orderId);
        } else {
            $path = $this->invoiceDir . $order['invoice_path'];
        }

        if (!$path || !file_exists($path)) {
            http_response_code(404);
            echo 'Fatura bulunamadı';
            return;
        }

        $ext  = pathinfo($path, PATHINFO_EXTENSION);
        $mime = $ext === 'pdf' ? 'application/pdf' : 'text/html';
        $disp = $download ? 'attachment' : 'inline';
        $name = basename($path);

        header("Content-Type: {$mime}");
        header("Content-Disposition: {$disp}; filename=\"{$name}\"");
        header("Content-Length: " . filesize($path));
        readfile($path);
        exit;
    }
}
