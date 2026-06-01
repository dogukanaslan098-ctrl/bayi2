<?php
/**
 * Route Dispatcher Helper
 * Add this to your front controller (index.php) to enable the discount info route
 */

// Mevcut route dispatcher kodunuzda şu satırı ekleyin:
// Burada /indirim-bilgisi routerisini handle etmek için

$route = $_SERVER['REQUEST_URI'];
$route = parse_url($route, PHP_URL_PATH);
$route = trim($route, '/');

// Mevcut route dispatcher'a bu kontrolü ekleyin:
if (strpos($route, 'indirim-bilgisi') !== false) {
    // Discount info sayfası
    $page = 'discount_info';
} 
// ... diğer routes ...

// Sonra şunu yapın:
$pageFile = ROOT_PATH . "/views/pages/{$page}.php";
if (file_exists($pageFile)) {
    include $pageFile;
} else {
    // 404 sayfası
    include ROOT_PATH . "/views/pages/404.php";
}
