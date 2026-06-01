<?php
// views/pages/download_invoice.php
use Auth\Auth;
use Helpers\Database;
use Services\InvoiceService;

Auth::require();

$orderId  = (int)($_GET['id'] ?? 0);
$dealerId = Auth::id();

if (!$orderId) { redirect('/siparislerim'); }

// Bayinin siparişi olduğunu doğrula
$order = Database::fetchOne(
    "SELECT id, order_number, invoice_path FROM orders WHERE id = ? AND dealer_id = ?",
    [$orderId, $dealerId]
);

if (!$order) {
    http_response_code(403);
    die('Bu siparişe erişim yetkiniz yok.');
}

$invSvc = new InvoiceService();
$invSvc->sendToClient($orderId, true);
