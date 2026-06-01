<?php
// views/pages/api/products.php
use Auth\Auth;
use Helpers\Database;
use Auth\Auth as AuthService;

Auth::require();

$q      = trim($_GET['q']   ?? '');
$cat    = trim($_GET['cat'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = min(100, max(10, (int)($_GET['limit'] ?? 50)));
$offset = ($page - 1) * $limit;

$discount = AuthService::effectiveDiscount();

// SQL oluştur
$where  = ['1=1'];
$params = [];

if ($q) {
    $where[]  = "(name LIKE ? OR sku LIKE ? OR barcode LIKE ?)";
    $like     = "%{$q}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($cat) {
    $where[]  = "category = ?";
    $params[] = $cat;
}

$whereStr = implode(' AND ', $where);

$total    = (int)Database::scalar("SELECT COUNT(*) FROM product_cache WHERE {$whereStr}", $params);
$products = Database::fetchAll(
    "SELECT * FROM product_cache WHERE {$whereStr} ORDER BY name LIMIT {$limit} OFFSET {$offset}",
    $params
);

// Bayi fiyatlarını ekle
foreach ($products as &$p) {
    $p['dealer_price']    = round((float)$p['price'] * (1 - $discount), 2);
    $p['discount_rate']   = $discount;
    $p['discount_pct']    = round($discount * 100, 1);
    $p['in_stock']        = $p['stock_qty'] > 0;
}
unset($p);

echo json_encode([
    'success'   => true,
    'data'      => $products,
    'total'     => $total,
    'page'      => $page,
    'per_page'  => $limit,
    'pages'     => ceil($total / $limit),
    'discount'  => $discount,
], JSON_UNESCAPED_UNICODE);
