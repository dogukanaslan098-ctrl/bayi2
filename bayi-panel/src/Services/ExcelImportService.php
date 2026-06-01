<?php
namespace Services;

use Helpers\Database;

/**
 * Excel / CSV Toplu Sipariş Import Servisi
 * Desteklenen format: SKU, Adet kolonları
 */
class ExcelImportService
{
    private const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
    private const ALLOWED_TYPES = ['text/csv', 'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain'];

    /**
     * Yüklenen dosyayı işle
     * @return array ['items'=>[], 'errors'=>[], 'warnings'=>[]]
     */
    public function processUpload(array $file): array
    {
        // Dosya doğrulama
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return $this->fail('Dosya yükleme hatası: ' . $this->uploadErrorMessage($file['error']));
        }

        if ($file['size'] > self::MAX_FILE_SIZE) {
            return $this->fail('Dosya boyutu çok büyük (max 5MB)');
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, ['csv', 'xlsx', 'xls'])) {
            return $this->fail('Sadece CSV ve Excel (.xlsx, .xls) dosyaları kabul edilir');
        }

        // Geçici dosyaya kaydet
        $tmpPath = sys_get_temp_dir() . '/bayi_import_' . uniqid() . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $tmpPath)) {
            return $this->fail('Dosya kaydedilemedi');
        }

        try {
            if ($ext === 'csv') {
                $result = $this->parseCsv($tmpPath);
            } else {
                $result = $this->parseXlsx($tmpPath);
            }
        } finally {
            @unlink($tmpPath);
        }

        return $result;
    }

    /**
     * CSV Dosyası Oku
     */
    private function parseCsv(string $path): array
    {
        $items    = [];
        $errors   = [];
        $warnings = [];
        $rowNum   = 0;
        $skuMap   = []; // Tekrar eden SKU kontrolü

        $handle = fopen($path, 'r');
        if (!$handle) {
            return $this->fail('CSV dosyası açılamadı');
        }

        // BOM temizle
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        // Ayırıcı tespit et (virgül veya noktalı virgül)
        $firstLine = fgets($handle);
        rewind($handle);
        $sep = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        while (($row = fgetcsv($handle, 1000, $sep)) !== false) {
            $rowNum++;

            // Başlık satırını atla
            if ($rowNum === 1 && !is_numeric(trim($row[1] ?? ''))) {
                continue;
            }

            $sku = strtoupper(trim($row[0] ?? ''));
            $qty = (int) trim($row[1] ?? 0);

            if (empty($sku)) {
                $errors[] = "Satır {$rowNum}: SKU boş";
                continue;
            }

            if ($qty < 1) {
                $errors[] = "Satır {$rowNum} ({$sku}): Geçersiz adet ({$qty})";
                continue;
            }

            // Tekrar eden SKU
            if (isset($skuMap[$sku])) {
                $warnings[] = "Satır {$rowNum}: '{$sku}' tekrar ediyor — adetler birleştirildi";
                $skuMap[$sku]['quantity'] += $qty;
                continue;
            }

            // DB'de kontrol
            $product = $this->lookupProduct($sku);

            if (!$product) {
                $errors[] = "Satır {$rowNum}: SKU bulunamadı — '{$sku}'";
                continue;
            }

            $inStock = $qty <= $product['stock_qty'];

            $skuMap[$sku] = [
                'sku'        => $sku,
                'wc_id'      => $product['wc_id'],
                'name'       => $product['name'],
                'quantity'   => $qty,
                'unit_price' => (float)$product['price'],
                'stock'      => (int)$product['stock_qty'],
                'in_stock'   => $inStock,
            ];

            if (!$inStock) {
                $warnings[] = "{$sku}: İstenen adet ({$qty}) mevcut stoktan ({$product['stock_qty']}) fazla";
            }
        }

        fclose($handle);

        $items = array_values($skuMap);

        return [
            'success'  => true,
            'items'    => $items,
            'errors'   => $errors,
            'warnings' => $warnings,
            'count'    => count($items),
        ];
    }

    /**
     * XLSX Dosyası Oku (PhpSpreadsheet ile)
     */
    private function parseXlsx(string $path): array
    {
        // PhpSpreadsheet composer ile kuruluysa kullan
        if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
            // Fallback: xlsx'i zip olarak aç ve XML oku
            return $this->parseXlsxFallback($path);
        }

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $sheet       = $spreadsheet->getActiveSheet();
        $rows        = $sheet->toArray();

        // CSV parser'a benzer mantık için geçici CSV oluştur
        $tmpCsv = sys_get_temp_dir() . '/bayi_xlsx_' . uniqid() . '.csv';
        $fp     = fopen($tmpCsv, 'w');
        foreach ($rows as $row) {
            fputcsv($fp, $row);
        }
        fclose($fp);

        $result = $this->parseCsv($tmpCsv);
        @unlink($tmpCsv);
        return $result;
    }

    /**
     * XLSX Fallback — PhpSpreadsheet yoksa basit XML parse
     */
    private function parseXlsxFallback(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return $this->fail('XLSX dosyası açılamadı. CSV formatını deneyin.');
        }

        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if (!$xml) {
            return $this->fail('XLSX içeriği okunamadı. CSV formatını deneyin.');
        }

        // Basit regex ile hücre değerlerini çek
        preg_match_all('/<c r="[A-Z]+(\d+)"[^>]*><v>([^<]+)<\/v><\/c>/', $xml, $matches);

        // Ham değerleri satır/sütuna göre grupla (basit yaklaşım)
        return $this->fail('XLSX için composer ile PhpSpreadsheet kurulumu gerekli. CSV formatını kullanın.');
    }

    // ── Ürün DB Arama ────────────────────────────────────────
    private function lookupProduct(string $sku): ?array
    {
        return Database::fetchOne(
            "SELECT wc_id, name, price, stock_qty FROM product_cache
             WHERE sku = ? OR barcode = ? LIMIT 1",
            [$sku, $sku]
        );
    }

    // ── Şablon CSV İndirme İçeriği ────────────────────────────
    public static function getTemplateCsv(): string
    {
        $lines = ["SKU,Adet"];

        $products = Database::fetchAll(
            "SELECT sku, name FROM product_cache ORDER BY sku LIMIT 5"
        );

        foreach ($products as $p) {
            $lines[] = "{$p['sku']},10";
        }

        if (count($lines) === 1) {
            $lines[] = "PRV-001,50";
            $lines[] = "PRV-012,30";
            $lines[] = "PRV-024,100";
        }

        return implode("\n", $lines) . "\n";
    }

    // ── Yardımcılar ──────────────────────────────────────────
    private function fail(string $message): array
    {
        return ['success' => false, 'items' => [], 'errors' => [$message], 'warnings' => [], 'count' => 0];
    }

    private function uploadErrorMessage(int $code): string
    {
        return match($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Dosya boyutu sınırı aşıldı',
            UPLOAD_ERR_PARTIAL  => 'Dosya kısmen yüklendi',
            UPLOAD_ERR_NO_FILE  => 'Dosya seçilmedi',
            default             => "Hata kodu: {$code}",
        };
    }
}
