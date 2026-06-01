<?php
// views/pages/do_cancel_order.php
use Auth\Auth;
use Services\OrderService;

// 1. Yanıtın her zaman JSON gitmesi için başlığı en üste alıyoruz.
header('Content-Type: application/json; charset=utf-8');

Auth::require();

// 2. Gelen JSON verisini oku
$raw     = file_get_contents('php://input');
$data    = json_decode($raw, true);
$orderId = (int)($data['order_id'] ?? 0);

// 3. JavaScript'ten (Header) gelen token ile Session'daki gerçek token'ı eşleştiriyoruz
$headers      = getallheaders();
$csrfToken    = $headers['X-CSRF-Token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$sessionToken = $_SESSION['_csrf'] ?? ''; // Tespit ettiğimiz doğru anahtar: _csrf

if (empty($csrfToken) || $csrfToken !== $sessionToken) {
    echo json_encode([
        'success' => false, 
        'message' => 'CSRF doğrulaması başarısız. Lütfen sayfayı yenileyip tekrar deneyin.'
    ]);
    exit;
}

// 4. Sipariş ID Kontrolü
if (!$orderId) {
    echo json_encode([
        'success' => false, 
        'message' => 'Geçersiz sipariş ID'
    ]);
    exit;
}

// 5. İptal İşlemi ve Sonuç Çıktısı
$orderSvc = new OrderService();
$result   = $orderSvc->cancelOrder($orderId, Auth::id());

echo json_encode($result);
exit;