<?php
// views/pages/do_excel_import.php
use Auth\Auth;
use Services\ExcelImportService;

Auth::require();
header('Content-Type: application/json; charset=utf-8');

if (empty($_FILES['file'])) {
    echo json_encode(['success' => false, 'errors' => ['Dosya bulunamadı']]);
    exit;
}

$svc    = new ExcelImportService();
$result = $svc->processUpload($_FILES['file']);

echo json_encode($result, JSON_UNESCAPED_UNICODE);
