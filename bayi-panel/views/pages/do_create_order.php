<?php
// views/pages/do_create_order.php
use Auth\Auth;
use Services\OrderService;

Auth::require();
verify_csrf();

header('Content-Type: application/json; charset=utf-8');

$rawBody = file_get_contents('php://input');
$data    = json_decode($rawBody, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz JSON verisi']);
    exit;
}

$cartItems     = $data['items']          ?? [];
$paymentMethod = $data['payment_method'] ?? 'card';

// Doğrulama
if (empty($cartItems) || !is_array($cartItems)) {
    echo json_encode(['success' => false, 'message' => 'Sepet boş']);
    exit;
}

if (!in_array($paymentMethod, ['card', 'eft'])) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz ödeme yöntemi']);
    exit;
}

// Her kalem için zorunlu alanlar
foreach ($cartItems as $i => $item) {
    if (empty($item['wc_id']) || empty($item['sku']) || empty($item['quantity']) || empty($item['unit_price'])) {
        echo json_encode(['success' => false, 'message' => "Kalem #{$i}: eksik alan"]);
        exit;
    }
    if ((int)$item['quantity'] < 1) {
        echo json_encode(['success' => false, 'message' => "Kalem #{$i}: geçersiz adet"]);
        exit;
    }
}

// Sipariş oluştur
$orderService = new OrderService();
$result       = $orderService->createOrder($cartItems, $paymentMethod);

echo json_encode($result);
