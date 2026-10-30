<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Department;
use App\Models\PaymentTerm;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MasterExportImportService
{
    /**
     * Get configuration for each master type
     */
    public function getConfig(string $type): ?array
    {
        $configs = [
            'products' => [
                'title' => 'Master Produk',
                'filename' => 'master_produk_' . date('Ymd_His') . '.xlsx',
                'template_filename' => 'template_import_produk.xlsx',
                'headers' => [
                    'Kode / SKU',
                    'Nama Produk',
                    'Kategori',
                    'Satuan',
                    'Harga Satuan (Rp)',
                    'Stok Saat Ini',
                    'Stok Minimum',
                    'Deskripsi',
                    'Status (Aktif/Nonaktif)',
                ],
                'sample' => [
                    ['PRD-001', 'Kopi Kapal Api Spesial 65g', 'Minuman', 'pcs', 7500, 100, 20, 'Kopi bubuk kemasan sachet', 'Aktif'],
                    ['PRD-002', 'Minyak Goreng Sania 2L', 'Sembako', 'pouch', 34500, 50, 10, 'Minyak goreng kelapa sawit', 'Aktif'],
                ],
            ],
            'vendors' => [
                'title' => 'Master Vendor',
                'filename' => 'master_vendor_' . date('Ymd_His') . '.xlsx',
                'template_filename' => 'template_import_vendor.xlsx',
                'headers' => [
                    'Kode Vendor',
                    'Nama Vendor / Perusahaan',
                    'Contact Person (PIC)',
                    'No Telepon / WA',
                    'Email',
                    'Term of Payment',
                    'Tipe NPWP',
                    'NPWP Vendor',
                    'Informasi Rekening Bank',
                    'Alamat Kantor / Gudang',
                    'Status (Aktif/Nonaktif)',
                ],
                'sample' => [
                    ['VND0001', 'PT. Mitra Sejahtera Bersama', 'Budi Santoso', '08123456789', 'budi@mitra.co.id', 'Net 30 Hari', 'Badan', '01.234.567.8-901.000', 'BCA 123456789 a.n PT Mitra', 'Jl. Rungkut Industri No. 12, Surabaya', 'Aktif'],
                    ['VND0002', 'CV. Sumber Pangan Makmur', 'Siti Rahma', '08219876543', 'order@sumberpangan.com', 'COD', 'Orang Pribadi', '', 'Mandiri 987654321 a.n Siti', 'Kawasan Pergudangan Margomulyo Indah B-5', 'Aktif'],
                ],
            ],
            'categories' => [
                'title' => 'Master Kategori',
                'filename' => 'master_kategori_' . date('Ymd_His') . '.xlsx',
                'template_filename' => 'template_import_kategori.xlsx',
                'headers' => [
                    'Nama Kategori',
                    'Asset Group (HRGA/MIS)',
                    'Deskripsi',
                    'Status (Aktif/Nonaktif)',
                ],
                'sample' => [
                    ['Alat Tulis Kantor', 'HRGA', 'Perlengkapan alat tulis dan kertas kantor', 'Aktif'],
                    ['Hardware Komputer', 'MIS', 'Perangkat keras PC, printer, dan server', 'Aktif'],
                ],
            ],
            'branches' => [
                'title' => 'Master Cabang',
                'filename' => 'master_cabang_' . date('Ymd_His') . '.xlsx',
                'template_filename' => 'template_import_cabang.xlsx',
                'headers' => [
                    'Kode Cabang',
                    'Nama Cabang',
                    'Alamat',
                    'No Telepon',
                    'Email',
                    'NPWP',
                    'Catatan Footer',
                    'Status (Aktif/Nonaktif)',
                ],
                'sample' => [
                    ['CBG-SBY', 'Cabang Surabaya', 'Jl. Darmo No. 45, Surabaya', '031-5678901', 'surabaya@panganlestari.co.id', '01.999.888.7-001.000', 'Terima kasih atas kerja sama Anda', 'Aktif'],
                    ['CBG-JKT', 'Cabang Jakarta Pusat', 'Jl. Hayam Wuruk No. 88, Jakarta', '021-3456789', 'jakarta@panganlestari.co.id', '01.999.888.7-002.000', '', 'Aktif'],
                ],
            ],
            'departments' => [
                'title' => 'Master Departemen',
                'filename' => 'master_departemen_' . date('Ymd_His') . '.xlsx',
                'template_filename' => 'template_import_departemen.xlsx',
                'headers' => [
                    'Kode Departemen',
                    'Nama Departemen',
                    'Deskripsi',
                    'Status (Aktif/Nonaktif)',
                ],
                'sample' => [
                    ['DPT-PROC', 'Procurement & Purchasing', 'Pengadaan barang, vendor management, dan logistik', 'Aktif'],
                    ['DPT-FIN', 'Finance & Accounting', 'Keuangan, perpajakan, dan pencatatan pembayaran', 'Aktif'],
                ],
            ],
            'payment-terms' => [
                'title' => 'Master Term Pembayaran',
                'filename' => 'master_term_pembayaran_' . date('Ymd_His') . '.xlsx',
                'template_filename' => 'template_import_term_pembayaran.xlsx',
                'headers' => [
                    'Nama Term',
                    'Jumlah Hari (Jatuh Tempo)',
                    'Tipe (full/partial)',
                    'Deskripsi',
                    'Status (Aktif/Nonaktif)',
                ],
                'sample' => [
                    ['Cash on Delivery (COD)', 0, 'full', 'Pembayaran langsung saat barang tiba', 'Aktif'],
                    ['Net 30 Hari', 30, 'full', 'Jatuh tempo 30 hari kalender setelah invoice', 'Aktif'],
                    ['Termin 2 Tahap (50-50)', 60, 'partial', 'Uang muka 50% dan pelunasan 50%', 'Aktif'],
                ],
            ],
        ];

        return $configs[$type] ?? null;
    }

    /**
     * Generate and download template Excel
     */
    public function downloadTemplate(string $type): StreamedResponse
    {
        $config = $this->getConfig($type);
        if (!$config) {
            abort(404, 'Master type tidak ditemukan');
        }

        return $this->streamExcelDownload(
            $config['template_filename'],
            substr($config['title'], 0, 31),
            $config['headers'],
            $config['sample'] ?? []
        );
    }

    /**
     * Export all master data to Excel
     */
    public function exportData(string $type): StreamedResponse
    {
        $config = $this->getConfig($type);
        if (!$config) {
            abort(404, 'Master type tidak ditemukan');
        }

        $dataRows = $this->fetchDataRows($type);

        return $this->streamExcelDownload(
            $config['filename'],
            substr($config['title'], 0, 31),
            $config['headers'],
            $dataRows
        );
    }

    /**
     * Generate Excel binary (via PhpSpreadsheet if available, or pure-PHP native XLSX fallback)
     * and stream it safely to the browser without failing on missing ext-zip or restricted /tmp.
     */
    protected function streamExcelDownload(string $filename, string $sheetTitle, array $headers, array $dataRows): StreamedResponse
    {
        $xlsxBinary = null;

        if (class_exists(Spreadsheet::class) && class_exists(Xlsx::class) && class_exists(\ZipArchive::class)) {
            try {
                $spreadsheet = new Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle($sheetTitle ?: 'Sheet1');

                $colCount = count($headers);
                for ($i = 0; $i < $colCount; $i++) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
                    $sheet->setCellValue("{$colLetter}1", $headers[$i]);
                }

                $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colCount);
                $headerRange = "A1:{$lastCol}1";
                $sheet->getStyle($headerRange)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '1E293B'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(28);

                $rowIndex = 2;
                foreach ($dataRows as $row) {
                    $rowValues = array_values((array) $row);
                    for ($j = 0; $j < count($rowValues); $j++) {
                        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($j + 1);
                        $sheet->setCellValue("{$colLetter}{$rowIndex}", $rowValues[$j]);
                    }
                    $rowIndex++;
                }

                for ($i = 1; $i <= $colCount; $i++) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
                    $sheet->getColumnDimension($colLetter)->setAutoSize(true);
                }

                $tempDir = storage_path('app/temp');
                if (!is_dir($tempDir)) {
                    @mkdir($tempDir, 0775, true);
                }
                if (!is_dir($tempDir) || !is_writable($tempDir)) {
                    $tempDir = sys_get_temp_dir();
                }

                $tempFile = rtrim($tempDir, '/\\') . DIRECTORY_SEPARATOR . 'xlsx_' . uniqid('', true) . '.xlsx';
                $writer = new Xlsx($spreadsheet);
                $writer->save($tempFile);

                if (is_file($tempFile) && filesize($tempFile) > 0) {
                    $xlsxBinary = file_get_contents($tempFile);
                }
                @unlink($tempFile);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('PhpSpreadsheet export fallback triggered: ' . $e->getMessage());
                $xlsxBinary = null;
            }
        }

        if (!$xlsxBinary) {
            $xlsxBinary = $this->buildNativeXlsxBinary($sheetTitle ?: 'Sheet1', $headers, $dataRows);
        }

        return response()->streamDownload(function () use ($xlsxBinary) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
            echo $xlsxBinary;
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
            'Pragma' => 'public',
        ]);
    }

    /**
     * Fetch formatted rows for export
     */
    protected function fetchDataRows(string $type): array
    {
        $rows = [];

        switch ($type) {
            case 'products':
                $items = Product::with('category')->orderBy('name')->get();
                foreach ($items as $item) {
                    $rows[] = [
                        $item->sku ?: '',
                        $item->name ?: '',
                        $item->category->name ?? '',
                        $item->unit ?: 'pcs',
                        (float)$item->price,
                        (int)$item->stock,
                        (int)$item->min_stock,
                        $item->description ?: '',
                        $item->is_active ? 'Aktif' : 'Nonaktif',
                    ];
                }
                break;

            case 'vendors':
                $items = Vendor::with('paymentTerm')->orderBy('name')->get();
                foreach ($items as $item) {
                    $rows[] = [
                        $item->code ?: '',
                        $item->name ?: '',
                        $item->contact_person ?: '',
                        $item->phone ?: '',
                        $item->email ?: '',
                        $item->paymentTerm->name ?? '',
                        $item->npwp_type ?: '',
                        $item->npwp ?: '',
                        $item->bank_info ?: '',
                        $item->address ?: '',
                        $item->is_active ? 'Aktif' : 'Nonaktif',
                    ];
                }
                break;

            case 'categories':
                $items = Category::orderBy('name')->get();
                foreach ($items as $item) {
                    $rows[] = [
                        $item->name ?: '',
                        $item->asset ?: 'HRGA',
                        $item->description ?: '',
                        $item->is_active ? 'Aktif' : 'Nonaktif',
                    ];
                }
                break;

            case 'branches':
                $items = Branch::orderBy('name')->get();
                foreach ($items as $item) {
                    $rows[] = [
                        $item->code ?: '',
                        $item->name ?: '',
                        $item->address ?: '',
                        $item->phone ?: '',
                        $item->email ?: '',
                        $item->npwp ?: '',
                        $item->footer_note ?: '',
                        $item->is_active ? 'Aktif' : 'Nonaktif',
                    ];
                }
                break;

            case 'departments':
                $items = Department::orderBy('name')->get();
                foreach ($items as $item) {
                    $rows[] = [
                        $item->code ?: '',
                        $item->name ?: '',
                        $item->description ?: '',
                        $item->is_active ? 'Aktif' : 'Nonaktif',
                    ];
                }
                break;

            case 'payment-terms':
                $items = PaymentTerm::orderBy('days')->get();
                foreach ($items as $item) {
                    $rows[] = [
                        $item->name ?: '',
                        (int)$item->days,
                        $item->payment_type ?: 'full',
                        $item->description ?: '',
                        $item->is_active ? 'Aktif' : 'Nonaktif',
                    ];
                }
                break;
        }

        return $rows;
    }

    /**
     * Import uploaded Excel or CSV file
     */
    public function importData(string $type, UploadedFile $file, bool $updateExisting = true): array
    {
        $config = $this->getConfig($type);
        if (!$config) {
            return ['success' => false, 'message' => 'Tipe master tidak valid.'];
        }

        try {
            $rows = $this->extractRowsFromUploadedFile($file);

            if (count($rows) < 2) {
                return [
                    'success' => false,
                    'message' => 'File Excel/CSV kosong atau tidak memiliki data baris untuk diimpor.',
                ];
            }

            // Remove header row
            array_shift($rows);

            $added = 0;
            $updated = 0;
            $failed = 0;
            $errors = [];

            foreach ($rows as $index => $row) {
                $line = $index + 2; // Excel row numbering

                if (!is_array($row)) {
                    continue;
                }

                // Skip completely empty rows
                if (empty(array_filter($row, fn($val) => !is_null($val) && trim((string) $val) !== ''))) {
                    continue;
                }

                try {
                    $res = $this->processRowImport($type, array_values($row), $updateExisting);
                    if ($res === 'added') {
                        $added++;
                    } elseif ($res === 'updated') {
                        $updated++;
                    } else {
                        $failed++;
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    $errors[] = "Baris {$line}: " . $e->getMessage();
                }
            }

            $totalProcessed = $added + $updated;

            return [
                'success' => true,
                'message' => "Proses import selesai. Berhasil: {$totalProcessed} (Baru: {$added}, Update: {$updated})" . ($failed > 0 ? ", Dilewati/Gagal: {$failed}." : '.'),
                'added' => $added,
                'updated' => $updated,
                'failed' => $failed,
                'errors' => array_slice($errors, 0, 10),
            ];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Master Import Parse Error [{$type}]: " . $e->getMessage(), [
                'exception' => $e,
            ]);

            return [
                'success' => false,
                'message' => 'Gagal membaca file: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Extract 2D array of rows from an uploaded .xlsx, .xls, or .csv file.
     * Supports PhpSpreadsheet when available and falls back to pure-PHP native XLSX/CSV/HTML parsers
     * when ext-zip or PhpSpreadsheet is unavailable in production.
     */
    protected function extractRowsFromUploadedFile(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $filePath = $file->getRealPath() ?: $file->getPathname();
        $rawContent = null;

        if ($filePath && is_readable($filePath)) {
            $rawContent = @file_get_contents($filePath);
        }
        if ($rawContent === false || $rawContent === null) {
            $rawContent = $file->get();
        }

        // 1. If CSV or TXT, parse directly with delimiter auto-detection
        if (in_array($ext, ['csv', 'txt'], true)) {
            return $this->parseCsvContentRows((string) $rawContent);
        }

        // 2. Try PhpSpreadsheet if available (and ZipArchive is loaded for .xlsx)
        $isZip = strncmp((string) $rawContent, "PK\x03\x04", 4) === 0;
        if (
            $filePath &&
            is_readable($filePath) &&
            class_exists(IOFactory::class) &&
            (!$isZip || class_exists(\ZipArchive::class))
        ) {
            try {
                $spreadsheet = IOFactory::load($filePath);
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray(null, true, true, false);
                if (!empty($rows)) {
                    return $rows;
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('PhpSpreadsheet import fallback triggered: ' . $e->getMessage());
            }
        }

        // 3. Pure-PHP native .xlsx (ZIP + OpenXML) parser fallback
        if ($isZip) {
            $rows = $this->parseXlsxBinaryRows((string) $rawContent);
            if (!empty($rows)) {
                return $rows;
            }
        }

        // 4. HTML/XML Table (.xls) fallback
        if (stripos((string) $rawContent, '<table') !== false || stripos((string) $rawContent, '<Row') !== false) {
            $rows = $this->parseXmlOrHtmlTableRows((string) $rawContent);
            if (!empty($rows)) {
                return $rows;
            }
        }

        // 5. Fallback to CSV parser
        return $this->parseCsvContentRows((string) $rawContent);
    }

    /**
     * Parse CSV / TSV content with UTF-8 BOM removal and automatic delimiter detection (, vs ; vs \t)
     */
    protected function parseCsvContentRows(string $content): array
    {
        // Strip UTF-8 BOM if present
        if (strncmp($content, "\xEF\xBB\xBF", 3) === 0) {
            $content = substr($content, 3);
        }

        $content = str_replace(["\r\n", "\r"], "\n", trim($content));
        if ($content === '') {
            return [];
        }

        $firstLine = strtok($content, "\n");
        $delimiters = [',' => substr_count($firstLine, ','), ';' => substr_count($firstLine, ';'), "\t" => substr_count($firstLine, "\t")];
        arsort($delimiters);
        $delimiter = array_key_first($delimiters) ?: ',';

        $rows = [];
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        while (($data = fgetcsv($stream, 0, $delimiter)) !== false) {
            if ($data === [null]) {
                continue;
            }
            $rows[] = array_map(fn($v) => is_string($v) ? trim($v) : $v, $data);
        }
        fclose($stream);

        return $rows;
    }

    /**
     * Parse HTML or SpreadsheetML (.xls) table rows
     */
    protected function parseXmlOrHtmlTableRows(string $content): array
    {
        $rows = [];
        if (preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $content, $trMatches)) {
            foreach ($trMatches[1] as $trHtml) {
                if (preg_match_all('/<t[dh][^>]*>(.*?)<\/t[dh]>/is', $trHtml, $tdMatches)) {
                    $row = [];
                    foreach ($tdMatches[1] as $cellHtml) {
                        $row[] = trim(html_entity_decode(strip_tags($cellHtml), ENT_QUOTES | ENT_XML1, 'UTF-8'));
                    }
                    $rows[] = $row;
                }
            }
        } elseif (preg_match_all('/<Row[^>]*>(.*?)<\/Row>/is', $content, $rowMatches)) {
            foreach ($rowMatches[1] as $rowXml) {
                if (preg_match_all('/<Cell[^>]*>(.*?)<\/Cell>/is', $rowXml, $cellMatches)) {
                    $row = [];
                    foreach ($cellMatches[1] as $cellXml) {
                        $row[] = trim(html_entity_decode(strip_tags($cellXml), ENT_QUOTES | ENT_XML1, 'UTF-8'));
                    }
                    $rows[] = $row;
                }
            }
        }
        return $rows;
    }

    /**
     * Pure-PHP .xlsx (ZIP + OpenXML) reader that works even without ext-zip (ZipArchive)
     */
    protected function parseXlsxBinaryRows(string $binary): array
    {
        $entries = $this->extractZipEntriesPurePhp($binary);
        if (empty($entries)) {
            return [];
        }

        // Parse sharedStrings.xml if present
        $sharedStrings = [];
        if (isset($entries['xl/sharedStrings.xml'])) {
            $ssXml = @simplexml_load_string($entries['xl/sharedStrings.xml']);
            if ($ssXml !== false) {
                foreach ($ssXml->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string) $si->t;
                    } elseif (isset($si->r)) {
                        $text = '';
                        foreach ($si->r as $run) {
                            $text .= (string) ($run->t ?? '');
                        }
                        $sharedStrings[] = $text;
                    } else {
                        $sharedStrings[] = '';
                    }
                }
            }
        }

        // Locate worksheet XML
        $sheetXmlStr = $entries['xl/worksheets/sheet1.xml'] ?? null;
        if (!$sheetXmlStr) {
            foreach ($entries as $name => $content) {
                if (str_starts_with($name, 'xl/worksheets/sheet') && str_ends_with($name, '.xml')) {
                    $sheetXmlStr = $content;
                    break;
                }
            }
        }
        if (!$sheetXmlStr) {
            return [];
        }

        $sheetXml = @simplexml_load_string($sheetXmlStr);
        if ($sheetXml === false || !isset($sheetXml->sheetData->row)) {
            return [];
        }

        $rows = [];
        $maxColIndex = 0;

        foreach ($sheetXml->sheetData->row as $rowElem) {
            $rowCells = [];
            $nextColIdx = 0;

            foreach ($rowElem->c as $cell) {
                $ref = (string) ($cell['r'] ?? '');
                $colIdx = $ref !== '' ? $this->columnRefToIndex($ref) : $nextColIdx;
                $nextColIdx = $colIdx + 1;
                if ($colIdx > $maxColIndex) {
                    $maxColIndex = $colIdx;
                }

                $type = (string) ($cell['t'] ?? '');
                $val = '';

                if ($type === 's') {
                    $ssIdx = (int) ($cell->v ?? 0);
                    $val = $sharedStrings[$ssIdx] ?? '';
                } elseif ($type === 'inlineStr') {
                    if (isset($cell->is->t)) {
                        $val = (string) $cell->is->t;
                    } elseif (isset($cell->is->r)) {
                        foreach ($cell->is->r as $run) {
                            $val .= (string) ($run->t ?? '');
                        }
                    }
                } elseif (isset($cell->v)) {
                    $val = (string) $cell->v;
                }

                $rowCells[$colIdx] = $val;
            }

            $rows[] = $rowCells;
        }

        // Normalize sparse rows into 0..$maxColIndex arrays
        $normalized = [];
        foreach ($rows as $rowCells) {
            $fullRow = [];
            for ($c = 0; $c <= $maxColIndex; $c++) {
                $fullRow[$c] = $rowCells[$c] ?? '';
            }
            $normalized[] = $fullRow;
        }

        return $normalized;
    }

    /**
     * Convert Excel cell reference (e.g. "A1", "BC12") to 0-based column index
     */
    protected function columnRefToIndex(string $cellRef): int
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($cellRef));
        if ($letters === '') {
            return 0;
        }
        $index = 0;
        $len = strlen($letters);
        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }
        return max(0, $index - 1);
    }

    /**
     * Convert 1-based column number (1 = A, 2 = B, ...) to Excel column letters
     */
    protected function columnIndexToLetter(int $colNumber): string
    {
        $letter = '';
        while ($colNumber > 0) {
            $mod = ($colNumber - 1) % 26;
            $letter = chr(65 + $mod) . $letter;
            $colNumber = (int) (($colNumber - $mod) / 26);
        }
        return $letter ?: 'A';
    }

    /**
     * Extract files from a ZIP binary in pure PHP (using Central Directory for reliability with data descriptors)
     */
    protected function extractZipEntriesPurePhp(string $binary): array
    {
        $entries = [];

        // Find End of Central Directory Record (PK\x05\x06) from the end of the file
        $eocdPos = strrpos($binary, "PK\x05\x06");
        if ($eocdPos !== false && strlen($binary) >= $eocdPos + 22) {
            $eocd = unpack('vdisk/vstartDisk/ventriesOnDisk/vtotalEntries/VcdSize/VcdOffset', substr($binary, $eocdPos + 4, 16));
            $cdPos = $eocd['cdOffset'];
            $total = $eocd['totalEntries'];

            for ($i = 0; $i < $total; $i++) {
                if (substr($binary, $cdPos, 4) !== "PK\x01\x02") {
                    break;
                }
                $cd = unpack(
                    'vverMade/vverNeeded/vflags/vmethod/vmodTime/vmodDate/Vcrc/VcompSize/VuncompSize/vnameLen/vextraLen/vcommentLen/vdiskStart/vintAttr/VextAttr/VlocalOffset',
                    substr($binary, $cdPos + 4, 42)
                );
                $name = substr($binary, $cdPos + 46, $cd['nameLen']);
                $cdPos += 46 + $cd['nameLen'] + $cd['extraLen'] + $cd['commentLen'];

                $localPos = $cd['localOffset'];
                if (substr($binary, $localPos, 4) !== "PK\x03\x04") {
                    continue;
                }
                $local = unpack('vver/vflags/vmethod/vmodTime/vmodDate/Vcrc/VcompSize/VuncompSize/vnameLen/vextraLen', substr($binary, $localPos + 4, 26));
                $dataStart = $localPos + 30 + $local['nameLen'] + $local['extraLen'];
                $compData = substr($binary, $dataStart, $cd['compSize']);

                if ($cd['method'] === 0) {
                    $entries[$name] = $compData;
                } elseif ($cd['method'] === 8 && function_exists('gzinflate')) {
                    $inflated = @gzinflate($compData);
                    if ($inflated !== false) {
                        $entries[$name] = $inflated;
                    }
                }
            }

            if (!empty($entries)) {
                return $entries;
            }
        }

        // Fallback sequential Local File Header scan if EOCD wasn't matched
        $offset = 0;
        $len = strlen($binary);
        while ($offset + 30 <= $len && substr($binary, $offset, 4) === "PK\x03\x04") {
            $local = unpack('vver/vflags/vmethod/vmodTime/vmodDate/Vcrc/VcompSize/VuncompSize/vnameLen/vextraLen', substr($binary, $offset + 4, 26));
            $name = substr($binary, $offset + 30, $local['nameLen']);
            $dataStart = $offset + 30 + $local['nameLen'] + $local['extraLen'];
            $compData = substr($binary, $dataStart, $local['compSize']);

            if ($local['method'] === 0) {
                $entries[$name] = $compData;
            } elseif ($local['method'] === 8 && function_exists('gzinflate')) {
                $inflated = @gzinflate($compData);
                if ($inflated !== false) {
                    $entries[$name] = $inflated;
                }
            }

            $offset = $dataStart + $local['compSize'];
        }

        return $entries;
    }

    /**
     * Build a genuine, styled .xlsx binary in pure PHP without requiring ZipArchive or PhpSpreadsheet
     */
    protected function buildNativeXlsxBinary(string $sheetTitle, array $headers, array $dataRows): string
    {
        $safeSheetTitle = htmlspecialchars(substr($sheetTitle ?: 'Sheet1', 0, 31), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $allRows = array_merge([$headers], $dataRows);
        $colCount = max(1, count($headers));

        // Calculate auto column widths
        $colWidths = array_fill(0, $colCount, 14);
        foreach ($allRows as $row) {
            $vals = array_values((array) $row);
            foreach ($vals as $cIdx => $val) {
                if ($cIdx >= $colCount) {
                    continue;
                }
                $len = mb_strlen((string) $val, 'UTF-8') + 4;
                if ($len > $colWidths[$cIdx]) {
                    $colWidths[$cIdx] = min(50, $len);
                }
            }
        }

        $colsXml = '<cols>';
        for ($i = 0; $i < $colCount; $i++) {
            $colNum = $i + 1;
            $w = $colWidths[$i];
            $colsXml .= "<col min=\"{$colNum}\" max=\"{$colNum}\" width=\"{$w}\" customWidth=\"1\"/>";
        }
        $colsXml .= '</cols>';

        $sheetDataXml = '<sheetData>';
        foreach ($allRows as $rIdx => $row) {
            $rowNum = $rIdx + 1;
            $isHeader = ($rowNum === 1);
            $htAttr = $isHeader ? ' ht="26" customHeight="1"' : '';
            $sheetDataXml .= "<row r=\"{$rowNum}\"{$htAttr}>";

            $vals = array_values((array) $row);
            for ($cIdx = 0; $cIdx < count($vals); $cIdx++) {
                $colLetter = $this->columnIndexToLetter($cIdx + 1);
                $cellRef = "{$colLetter}{$rowNum}";
                $val = $vals[$cIdx];
                $styleAttr = $isHeader ? ' s="1"' : ' s="0"';

                if (!$isHeader && (is_int($val) || is_float($val))) {
                    $sheetDataXml .= "<c r=\"{$cellRef}\"{$styleAttr}><v>{$val}</v></c>";
                } else {
                    $escaped = htmlspecialchars((string) ($val ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
                    // Strip invalid XML control chars
                    $escaped = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $escaped);
                    $sheetDataXml .= "<c r=\"{$cellRef}\" t=\"inlineStr\"{$styleAttr}><is><t xml:space=\"preserve\">{$escaped}</t></is></c>";
                }
            }

            $sheetDataXml .= '</row>';
        }
        $sheetDataXml .= '</sheetData>';

        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                . '</Types>',

            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                . '</Relationships>',

            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<sheets><sheet name="' . $safeSheetTitle . '" sheetId="1" r:id="rId1"/></sheets>'
                . '</workbook>',

            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                . '</Relationships>',

            'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                . '<fonts count="2">'
                . '<font><sz val="11"/><color rgb="FF1E293B"/><name val="Calibri"/></font>'
                . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
                . '</fonts>'
                . '<fills count="3">'
                . '<fill><patternFill patternType="none"/></fill>'
                . '<fill><patternFill patternType="gray125"/></fill>'
                . '<fill><patternFill patternType="solid"><fgColor rgb="FF1E293B"/><bgColor indexed="64"/></patternFill></fill>'
                . '</fills>'
                . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
                . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
                . '<cellXfs count="2">'
                . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
                . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1">'
                . '<alignment horizontal="center" vertical="center"/>'
                . '</xf>'
                . '</cellXfs>'
                . '</styleSheet>',

            'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                . $colsXml
                . $sheetDataXml
                . '</worksheet>',
        ];

        return $this->packZipArchivePurePhp($files);
    }

    /**
     * Create a valid ZIP binary in pure PHP without requiring ext-zip
     */
    protected function packZipArchivePurePhp(array $files): string
    {
        $localData = '';
        $centralDir = '';
        $offset = 0;
        $count = 0;
        $useDeflate = function_exists('gzdeflate');

        foreach ($files as $name => $content) {
            $uncompSize = strlen($content);
            $crc = crc32($content);

            if ($useDeflate) {
                $compData = gzdeflate($content, 6);
                $method = 8;
            } else {
                $compData = $content;
                $method = 0;
            }
            $compSize = strlen($compData);
            $nameLen = strlen($name);

            $localHeader = "PK\x03\x04"
                . pack('vvvvvVVVvv', 20, 0, $method, 0, 0, $crc, $compSize, $uncompSize, $nameLen, 0)
                . $name;

            $localData .= $localHeader . $compData;

            $centralHeader = "PK\x01\x02"
                . pack('vvvvvvVVVvvvvvVV', 20, 20, 0, $method, 0, 0, $crc, $compSize, $uncompSize, $nameLen, 0, 0, 0, 0, 32, $offset)
                . $name;

            $centralDir .= $centralHeader;
            $offset += strlen($localHeader) + $compSize;
            $count++;
        }

        $eocd = "PK\x05\x06"
            . pack('vvvvVVv', 0, 0, $count, $count, strlen($centralDir), $offset, 0);

        return $localData . $centralDir . $eocd;
    }

    /**
     * Process a single row import
     */
    protected function processRowImport(string $type, array $row, bool $updateExisting): string
    {
        switch ($type) {
            case 'products':
                // Headers: [SKU, Name, Category, Unit, Price, Stock, Min Stock, Description, Status]
                $sku = trim((string)($row[0] ?? ''));
                $name = trim((string)($row[1] ?? ''));
                $categoryName = trim((string)($row[2] ?? ''));
                $unit = trim((string)($row[3] ?? 'pcs')) ?: 'pcs';
                $price = floatval(preg_replace('/[^\d.]/', '', (string)($row[4] ?? 0)));
                $stock = intval($row[5] ?? 0);
                $minStock = intval($row[6] ?? 0);
                $desc = trim((string)($row[7] ?? ''));
                $statusStr = strtolower(trim((string)($row[8] ?? 'aktif')));
                $isActive = in_array($statusStr, ['1', 'aktif', 'active', 'ya', 'true']);

                if (empty($name)) {
                    throw new \Exception('Nama produk wajib diisi.');
                }

                // Match or create category
                $categoryId = null;
                if (!empty($categoryName)) {
                    $cat = Category::firstOrCreate(
                        ['name' => $categoryName],
                        ['asset' => 'HRGA', 'slug' => Str::slug($categoryName), 'is_active' => true]
                    );
                    $categoryId = $cat->id;
                }

                // If SKU is empty, auto-generate
                if (empty($sku)) {
                    $sku = 'PRD-' . strtoupper(Str::random(6));
                }

                $existing = Product::where('sku', $sku)->orWhere('name', $name)->first();

                if ($existing) {
                    if (!$updateExisting) {
                        return 'skipped';
                    }
                    $existing->update([
                        'name' => $name,
                        'sku' => $sku,
                        'category_id' => $categoryId ?? $existing->category_id,
                        'unit' => $unit,
                        'price' => $price,
                        'stock' => $stock,
                        'min_stock' => $minStock,
                        'description' => $desc ?: $existing->description,
                        'is_active' => $isActive,
                    ]);
                    return 'updated';
                }

                Product::create([
                    'sku' => $sku,
                    'name' => $name,
                    'category_id' => $categoryId,
                    'unit' => $unit,
                    'price' => $price,
                    'stock' => $stock,
                    'min_stock' => $minStock,
                    'description' => $desc,
                    'is_active' => $isActive,
                ]);
                return 'added';

            case 'vendors':
                // Headers: [Code, Name, Contact, Phone, Email, Payment Term, (optional Tipe NPWP), NPWP, Bank Info, Address, Status]
                $code = trim((string)($row[0] ?? ''));
                $name = trim((string)($row[1] ?? ''));
                $contact = trim((string)($row[2] ?? ''));
                $phone = trim((string)($row[3] ?? ''));
                $email = trim((string)($row[4] ?? ''));
                $paymentTermName = trim((string)($row[5] ?? ''));

                if (count($row) >= 11) {
                    $npwpType = trim((string)($row[6] ?? ''));
                    $npwp = trim((string)($row[7] ?? ''));
                    $bankInfo = trim((string)($row[8] ?? ''));
                    $address = trim((string)($row[9] ?? ''));
                    $statusStr = strtolower(trim((string)($row[10] ?? 'aktif')));
                } else {
                    $npwpType = null;
                    $npwp = trim((string)($row[6] ?? ''));
                    $bankInfo = trim((string)($row[7] ?? ''));
                    $address = trim((string)($row[8] ?? ''));
                    $statusStr = strtolower(trim((string)($row[9] ?? 'aktif')));
                }
                $isActive = in_array($statusStr, ['1', 'aktif', 'active', 'ya', 'true']);

                if (empty($name)) {
                    throw new \Exception('Nama vendor wajib diisi.');
                }

                // Match payment term
                $paymentTermId = null;
                if (!empty($paymentTermName)) {
                    $term = PaymentTerm::where('name', 'like', "%{$paymentTermName}%")->first();
                    if ($term) {
                        $paymentTermId = $term->id;
                    }
                }

                if (empty($code)) {
                    $code = Vendor::generateCode();
                }

                $existing = Vendor::where('code', $code)->orWhere('name', $name)->first();

                if ($existing) {
                    if (!$updateExisting) {
                        return 'skipped';
                    }
                    $existing->update([
                        'code' => $code,
                        'name' => $name,
                        'contact_person' => $contact ?: $existing->contact_person,
                        'phone' => $phone ?: $existing->phone,
                        'email' => $email ?: $existing->email,
                        'payment_term_id' => $paymentTermId ?? $existing->payment_term_id,
                        'npwp_type' => $npwpType ?: $existing->npwp_type,
                        'npwp' => $npwp ?: $existing->npwp,
                        'bank_info' => $bankInfo ?: $existing->bank_info,
                        'address' => $address ?: $existing->address,
                        'is_active' => $isActive,
                    ]);
                    return 'updated';
                }

                Vendor::create([
                    'code' => $code,
                    'name' => $name,
                    'contact_person' => $contact,
                    'phone' => $phone,
                    'email' => $email,
                    'payment_term_id' => $paymentTermId,
                    'npwp_type' => $npwpType,
                    'npwp' => $npwp,
                    'bank_info' => $bankInfo,
                    'address' => $address,
                    'is_active' => $isActive,
                ]);
                return 'added';

            case 'categories':
                // Headers: [Name, Asset, Description, Status]
                $name = trim((string)($row[0] ?? ''));
                $asset = strtoupper(trim((string)($row[1] ?? 'HRGA')));
                if (!in_array($asset, ['HRGA', 'MIS'])) {
                    $asset = 'HRGA';
                }
                $desc = trim((string)($row[2] ?? ''));
                $statusStr = strtolower(trim((string)($row[3] ?? 'aktif')));
                $isActive = in_array($statusStr, ['1', 'aktif', 'active', 'ya', 'true']);

                if (empty($name)) {
                    throw new \Exception('Nama kategori wajib diisi.');
                }

                $existing = Category::where('name', $name)->first();
                if ($existing) {
                    if (!$updateExisting) {
                        return 'skipped';
                    }
                    $existing->update([
                        'asset' => $asset,
                        'description' => $desc ?: $existing->description,
                        'is_active' => $isActive,
                    ]);
                    return 'updated';
                }

                Category::create([
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'asset' => $asset,
                    'description' => $desc,
                    'is_active' => $isActive,
                ]);
                return 'added';

            case 'branches':
                // Headers: [Code, Name, Address, Phone, Email, NPWP, Footer Note, Status]
                $code = trim((string)($row[0] ?? ''));
                $name = trim((string)($row[1] ?? ''));
                $address = trim((string)($row[2] ?? ''));
                $phone = trim((string)($row[3] ?? ''));
                $email = trim((string)($row[4] ?? ''));
                $npwp = trim((string)($row[5] ?? ''));
                $footer = trim((string)($row[6] ?? ''));
                $statusStr = strtolower(trim((string)($row[7] ?? 'aktif')));
                $isActive = in_array($statusStr, ['1', 'aktif', 'active', 'ya', 'true']);

                if (empty($name)) {
                    throw new \Exception('Nama cabang wajib diisi.');
                }
                if (empty($code)) {
                    $code = 'CBG-' . strtoupper(Str::random(3));
                }

                $existing = Branch::where('code', $code)->orWhere('name', $name)->first();
                if ($existing) {
                    if (!$updateExisting) {
                        return 'skipped';
                    }
                    $existing->update([
                        'code' => $code,
                        'name' => $name,
                        'address' => $address ?: $existing->address,
                        'phone' => $phone ?: $existing->phone,
                        'email' => $email ?: $existing->email,
                        'npwp' => $npwp ?: $existing->npwp,
                        'footer_note' => $footer ?: $existing->footer_note,
                        'is_active' => $isActive,
                    ]);
                    return 'updated';
                }

                Branch::create([
                    'code' => $code,
                    'name' => $name,
                    'address' => $address,
                    'phone' => $phone,
                    'email' => $email,
                    'npwp' => $npwp,
                    'footer_note' => $footer,
                    'is_active' => $isActive,
                ]);
                return 'added';

            case 'departments':
                // Headers: [Code, Name, Description, Status]
                $code = trim((string)($row[0] ?? ''));
                $name = trim((string)($row[1] ?? ''));
                $desc = trim((string)($row[2] ?? ''));
                $statusStr = strtolower(trim((string)($row[3] ?? 'aktif')));
                $isActive = in_array($statusStr, ['1', 'aktif', 'active', 'ya', 'true']);

                if (empty($name)) {
                    throw new \Exception('Nama departemen wajib diisi.');
                }
                if (empty($code)) {
                    $code = 'DPT-' . strtoupper(Str::random(4));
                }

                $existing = Department::where('code', $code)->orWhere('name', $name)->first();
                if ($existing) {
                    if (!$updateExisting) {
                        return 'skipped';
                    }
                    $existing->update([
                        'code' => $code,
                        'name' => $name,
                        'description' => $desc ?: $existing->description,
                        'is_active' => $isActive,
                    ]);
                    return 'updated';
                }

                Department::create([
                    'code' => $code,
                    'name' => $name,
                    'description' => $desc,
                    'is_active' => $isActive,
                ]);
                return 'added';

            case 'payment-terms':
                // Headers: [Name, Days, Type, Description, Status]
                $name = trim((string)($row[0] ?? ''));
                $days = intval($row[1] ?? 0);
                $payType = strtolower(trim((string)($row[2] ?? 'full')));
                if (!in_array($payType, ['full', 'partial'])) {
                    $payType = 'full';
                }
                $desc = trim((string)($row[3] ?? ''));
                $statusStr = strtolower(trim((string)($row[4] ?? 'aktif')));
                $isActive = in_array($statusStr, ['1', 'aktif', 'active', 'ya', 'true']);

                if (empty($name)) {
                    throw new \Exception('Nama term pembayaran wajib diisi.');
                }

                $existing = PaymentTerm::where('name', $name)->first();
                if ($existing) {
                    if (!$updateExisting) {
                        return 'skipped';
                    }
                    $existing->update([
                        'days' => $days,
                        'payment_type' => $payType,
                        'description' => $desc ?: $existing->description,
                        'is_active' => $isActive,
                    ]);
                    return 'updated';
                }

                PaymentTerm::create([
                    'name' => $name,
                    'days' => $days,
                    'payment_type' => $payType,
                    'description' => $desc,
                    'is_active' => $isActive,
                ]);
                return 'added';

            default:
                throw new \Exception("Tipe master {$type} tidak dikenali.");
        }
    }
}
