<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2>🔍 Sistem Parçaları Kontrol Ediliyor...</h2>";

try {
    // 1. Kritik sabitlerin varlığı
    if (!defined('KDV_RATE')) {
        echo "⚠️ <b>KDV_RATE</b> sabiti bu dosyada tanımlı değil (Muhtemelen config.php yüklenmedi).<br>";
    } else {
        echo "✅ KDV_RATE tanımlı: " . KDV_RATE . "<br>";
    }

    // 2. Kritik fonksiyonların varlığı
    if (!function_exists('format_money')) {
        echo "⚠️ <b>format_money()</b> fonksiyonu sistemde bulunamadı.<br>";
    } else {
        echo "✅ format_money() fonksiyonu hazır.<br>";
    }

    // 3. Veritabanı sınıfının varlığı
    if (!class_exists('\Helpers\Database')) {
        echo "⚠️ <b>\Helpers\Database</b> sınıfı yüklenemedi (Autoload veya require eksik).<br>";
    } else {
        echo "✅ Database sınıfı hazır.<br>";
    }

    echo "<br>🔄 <b>Şimdi OrderService.php dosyası test ediliyor...</b><br>";
    
    // NOT: Dosya yolunuz farklıysa burayı güncelleyin (Örn: 'app/Services/OrderService.php' vb.)
    require_once 'OrderService.php'; 
    
    echo "✅ <b>Harika!</b> OrderService.php dosyasında hiçbir yazım (Syntax) hatası yok.<br>";

    if (class_exists('\Services\OrderService')) {
        echo "✅ \Services\OrderService sınıfı başarıyla belleğe alındı.<br>";
    }

} catch (\Throwable $e) {
    echo "<br>💥 <b>İŞTE KATİL BULUNDU! PHP FATAL ERROR:</b><br>";
    echo "<span style='color:red; font-family:monospace; background:#f8d7da; padding:5px; display:block;'>";
    echo "<b>Hata:</b> " . $e->getMessage() . "<br>";
    echo "<b>Dosya:</b> " . $e->getFile() . "<br>";
    echo "<b>Satır:</b> " . $e->getLine();
    echo "</span>";
}