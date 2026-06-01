<?php // views/pages/excel_template.php
use Services\ExcelImportService;

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="bayi_siparis_sablon.csv"');
header('Cache-Control: no-cache');

echo "\xEF\xBB\xBF"; // UTF-8 BOM (Excel için)
echo ExcelImportService::getTemplateCsv();
exit;
