<?php
// views/pages/do_update_profile.php
use Auth\Auth;
use Helpers\Database;

Auth::require();
verify_csrf();

$dealerId = Auth::id();

$allowed = ['company_name', 'contact_name', 'phone', 'tax_office', 'tax_number', 'address', 'city', 'district', 'postal_code'];
$data    = [];

foreach ($allowed as $field) {
    if (isset($_POST[$field])) {
        $data[$field] = trim($_POST[$field]);
    }
}

if (empty($data)) {
    $_SESSION['flash']['error'] = 'Güncellenecek veri bulunamadı.';
    redirect('/hesabim');
}

// Temel doğrulama
if (isset($data['company_name']) && strlen($data['company_name']) < 2) {
    $_SESSION['flash']['error'] = 'Firma adı çok kısa.';
    redirect('/hesabim');
}

Database::update('dealers', $data, ['id' => $dealerId]);

$_SESSION['flash']['success'] = 'Bilgileriniz güncellendi.';
redirect('/hesabim');
