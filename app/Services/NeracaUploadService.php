<?php

namespace App\Services;

use Exception;
use SimpleXMLElement;
use XMLReader;
use ZipArchive;

/**
 * Service Parser & Profiler Berkas Spreadsheet Olah Data BRS (Ekspor & Impor)
 * Khusus Modul Neraca Perdagangan SIDATEX.
 *
 * Menggunakan arsitektur XML Streaming (XMLReader) langsung pada payload OpenXML XLSX
 * untuk mencegah memory exhaustion pada berkas berukuran besar (14 - 90+ MB).
 */
class NeracaUploadService
{
    /**
     * Definisi Header Kolom Wajib untuk Masing-masing Jenis Sumber Neraca.
     */
    public const REQUIRED_HEADERS = [
        'ekspor' => [
            'sheet' => 'RawDataEkspor',
            'headers' => ['BULAN', 'TAHUN', 'PROPINSI', 'HS', 'NEGARA', 'BERAT', 'NILAI', 'PROPASAL']
        ],
        'impor' => [
            'sheet' => 'RawDataImpor',
            'headers' => ['BULAN', 'TAHUN', 'PROPINSI', 'HS', 'NEGARA', 'BERAT', 'NILAI']
        ]
    ];

    /**
     * Memvalidasi berkas dan menghasilkan Preview Metadata tanpa menyimpan data ke database.
     *
     * @param string $filePath Path lengkap berkas .xlsx
     * @param string $sourceType 'ekspor' atau 'impor'
     * @param int $previewLimit Jumlah sampel baris yang disertakan pada preview
     * @return array
     * @throws Exception
     */
    public function generatePreview(string $filePath, string $sourceType, int $previewLimit = 10): array
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(300);

        $sourceType = strtolower(trim($sourceType));
        if (!in_array($sourceType, ['ekspor', 'impor'])) {
            throw new Exception("Jenis sumber data tidak valid. Harus 'ekspor' atau 'impor'.");
        }

        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new Exception("Berkas tidak ditemukan atau tidak dapat dibaca: {$filePath}");
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new Exception("Berkas bukan format ZIP / XLSX yang valid.");
        }

        try {
            // 1. Ekstrak struktur Workbook dan cari Worksheet yang sesuai
            $workbookInfo = $this->parseWorkbookStructure($zip);
            $expectedSheet = self::REQUIRED_HEADERS[$sourceType]['sheet'];

            if (!isset($workbookInfo['sheets'][$expectedSheet])) {
                $available = implode(', ', array_keys($workbookInfo['sheets']));
                throw new Exception("Worksheet '{$expectedSheet}' tidak ditemukan. Worksheet yang tersedia: {$available}");
            }

            $targetXmlFile = $workbookInfo['sheets'][$expectedSheet];

            // 2. Baca Kamus Shared Strings yang diperlukan untuk header dan mapping teks
            // Gunakan streaming untuk efisiensi
            $headers = $this->readSheetHeaders($zip, $targetXmlFile);

            // 3. Validasi Keberadaan Seluruh Header Wajib
            $missingHeaders = [];
            foreach (self::REQUIRED_HEADERS[$sourceType]['headers'] as $reqH) {
                if (!in_array($reqH, $headers)) {
                    $missingHeaders[] = $reqH;
                }
            }

            if (!empty($missingHeaders)) {
                $missingStr = implode(', ', $missingHeaders);
                throw new Exception("Header wajib tidak lengkap pada sheet '{$expectedSheet}'. Kolom hilang: {$missingStr}");
            }

            // Peta Kolom (misal: 'B' => 'BULAN', 'C' => 'TAHUN')
            $colToHeader = $headers; // asosiasi huruf kolom ke nama header
            $headerToCol = array_flip($headers);

            // 4. Baca Data Transaksi Secara Streaming dengan Validasi Presisi
            $stats = $this->streamProcessSheetData(
                $zip,
                $targetXmlFile,
                $headerToCol,
                $sourceType,
                $previewLimit
            );

            return [
                'success' => true,
                'file_name' => basename($filePath),
                'file_size' => filesize($filePath),
                'file_size_formatted' => round(filesize($filePath) / (1024 * 1024), 2) . ' MB',
                'source_type' => $sourceType,
                'worksheet' => $expectedSheet,
                'total_rows_read' => $stats['total_rows_read'],
                'valid_rows_count' => $stats['valid_rows_count'],
                'invalid_rows_count' => $stats['invalid_rows_count'],
                'validation_errors' => $stats['validation_errors'],
                'periods' => [
                    'years_available' => array_keys($stats['period_matrix']),
                    'total_months' => $stats['total_distinct_months'],
                    'matrix' => $stats['period_matrix'],
                ],
                'totals' => [
                    'total_nilai' => $stats['sum_nilai'], // string presisi desimal 2 digit
                    'total_nilai_juta_usd' => round((float)$stats['sum_nilai'] / 1000000, 2),
                    'total_berat_kg' => $stats['sum_berat'], // string presisi desimal 3 digit
                    'total_berat_ribu_ton' => round((float)$stats['sum_berat'] / 1000000, 3),
                ],
                'dimensions' => [
                    'unique_hs_count' => count($stats['unique_hs']),
                    'unique_country_count' => count($stats['unique_countries']),
                    'unique_kabkota_count' => count($stats['unique_kabkota']),
                    'mapping_warnings' => $this->inspectDimensionMappings($stats['unique_hs'], $stats['unique_countries'], $stats['unique_kabkota'], $sourceType),
                ],
                'sample_data' => $stats['samples'],
            ];

        } finally {
            $zip->close();
        }
    }

    /**
     * Ekstrak relasi Workbook untuk mendapatkan letak XML sheet target.
     */
    protected function parseWorkbookStructure(ZipArchive $zip): array
    {
        $wbXml = $zip->getFromName('xl/workbook.xml');
        if (!$wbXml) {
            throw new Exception("Struktur berkas XLSX korup: xl/workbook.xml tidak ditemukan.");
        }

        $wb = simplexml_load_string($wbXml);
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        $rels = simplexml_load_string($relsXml);

        $relMap = [];
        foreach ($rels->Relationship as $rel) {
            $relMap[(string)$rel['Id']] = 'xl/' . (string)$rel['Target'];
        }

        $sheetMap = [];
        foreach ($wb->sheets->sheet as $sheet) {
            $name = (string)$sheet['name'];
            $rId = (string)$sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            if (isset($relMap[$rId])) {
                $sheetMap[$name] = $relMap[$rId];
            }
        }

        return ['sheets' => $sheetMap];
    }

    /**
     * Membaca header (baris 1) dari sheet secara akurat termasuk resolving shared strings.
     */
    protected function readSheetHeaders(ZipArchive $zip, string $targetXmlFile): array
    {
        $reader = new XMLReader();
        $reader->xml($zip->getFromName($targetXmlFile));

        $headerCellIndices = [];
        while ($reader->read()) {
            if ($reader->nodeType == XMLReader::ELEMENT && $reader->localName == 'row') {
                $rowXml = simplexml_load_string($reader->readOuterXml());
                foreach ($rowXml->c as $c) {
                    $colRef = preg_replace('/[0-9]/', '', (string)$c['r']);
                    $val = (string)$c->v;
                    $type = (string)$c['t'];
                    $headerCellIndices[$colRef] = ['type' => $type, 'val' => $val];
                }
                break; // hanya baris 1
            }
        }

        // Kumpulkan shared string index yang dibutuhkan oleh header
        $targetStringIndices = [];
        foreach ($headerCellIndices as $col => $info) {
            if ($info['type'] === 's') {
                $targetStringIndices[(int)$info['val']] = $col;
            }
        }

        $resolvedHeaders = [];
        foreach ($headerCellIndices as $col => $info) {
            if ($info['type'] !== 's') {
                $resolvedHeaders[$col] = trim($info['val']);
            }
        }

        if (!empty($targetStringIndices)) {
            $xmlReader = new XMLReader();
            $xmlReader->xml($zip->getFromName('xl/sharedStrings.xml'));
            $currentIndex = 0;
            while ($xmlReader->read()) {
                if ($xmlReader->nodeType == XMLReader::ELEMENT && $xmlReader->localName == 'si') {
                    if (isset($targetStringIndices[$currentIndex])) {
                        $node = simplexml_load_string($xmlReader->readOuterXml());
                        $text = '';
                        if (isset($node->t)) {
                            $text = (string)$node->t;
                        } elseif (isset($node->r)) {
                            foreach ($node->r as $r) {
                                $text .= (string)$r->t;
                            }
                        }
                        $col = $targetStringIndices[$currentIndex];
                        $resolvedHeaders[$col] = trim($text);
                    }
                    $currentIndex++;
                }
            }
        }

        ksort($resolvedHeaders);
        return $resolvedHeaders;
    }

    /**
     * Membaca tabel sharedStrings.xml secara streaming ke memori.
     */
    protected function loadSharedStrings(ZipArchive $zip): array
    {
        $ssContent = $zip->getFromName('xl/sharedStrings.xml');
        if (!$ssContent) {
            return [];
        }

        $xmlReader = new XMLReader();
        $xmlReader->xml($ssContent);
        $sharedStrings = [];

        while ($xmlReader->read()) {
            if ($xmlReader->nodeType == XMLReader::ELEMENT && $xmlReader->localName == 'si') {
                $node = simplexml_load_string($xmlReader->readOuterXml());
                $text = '';
                if (isset($node->t)) {
                    $text = (string)$node->t;
                } elseif (isset($node->r)) {
                    foreach ($node->r as $r) {
                        $text .= (string)$r->t;
                    }
                }
                $sharedStrings[] = trim($text);
            }
        }

        return $sharedStrings;
    }

    /**
     * Membaca dan menghitung agregat data transaksi secara streaming tanpa membebani memori.
     */
    protected function streamProcessSheetData(
        ZipArchive $zip,
        string $targetXmlFile,
        array $headerToCol,
        string $sourceType,
        int $previewLimit
    ): array {
        // Ekstrak shared strings jika ada untuk me-resolve sel string
        $sharedStrings = $this->loadSharedStrings($zip);

        $colBulan = $headerToCol['BULAN'] ?? 'B';
        $colTahun = $headerToCol['TAHUN'] ?? 'C';
        $colNilai = $headerToCol['NILAI'] ?? 'I';
        $colBerat = $headerToCol['BERAT'] ?? 'H';
        $colHs = $headerToCol['HS'] ?? 'F';
        $colNegara = $headerToCol['NEGARA'] ?? 'G';
        $colKabKota = $headerToCol['Kabupaten/Kota'] ?? 'P';
        $colPropAsal = $headerToCol['PROPASAL'] ?? 'J';
        $colHsDesc = $headerToCol['COMMDESC'] ?? ($headerToCol['DeskripsiHS2'] ?? null);
        $colNegaraDesc = $headerToCol['Negara'] ?? null;
        $colSektor = $headerToCol['SEKTOR KOMODITAS'] ?? null;
        $colBec = $headerToCol['BECCODE'] ?? null;
        $colKbli = $headerToCol['DeskKBLI20'] ?? null;

        $reader = new XMLReader();
        $reader->xml($zip->getFromName($targetXmlFile));

        $totalRowsRead = 0;
        $validRowsCount = 0;
        $invalidRowsCount = 0;
        $validationErrors = [];
        $samples = [];

        $sumNilai = '0.00';
        $sumBerat = '0.000';

        $uniqueHs = [];
        $uniqueCountries = [];
        $uniqueKabkota = [];
        $periodMatrix = [];

        while ($reader->read()) {
            if ($reader->nodeType == XMLReader::ELEMENT && $reader->localName == 'row') {
                $totalRowsRead++;
                if ($totalRowsRead === 1) {
                    continue; // Skip baris header
                }

                $rowXml = simplexml_load_string($reader->readOuterXml());
                $cellVals = [];
                foreach ($rowXml->c as $c) {
                    $colRef = preg_replace('/[0-9]/', '', (string)$c['r']);
                    $val = (string)$c->v;
                    $type = (string)$c['t'];
                    // Resolve shared string jika bertipe 's'
                    if ($type === 's' && isset($sharedStrings[(int)$val])) {
                        $val = $sharedStrings[(int)$val];
                    }
                    $cellVals[$colRef] = $val;
                }

                // 1. Ekstrak dan Normalisasi Nilai & Berat dengan Presisi Tinggi
                $rawNilai = $cellVals[$colNilai] ?? '0';
                $rawBerat = $cellVals[$colBerat] ?? '0';

                if (!is_numeric($rawNilai) || !is_numeric($rawBerat)) {
                    $invalidRowsCount++;
                    if (count($validationErrors) < 10) {
                        $validationErrors[] = "Baris {$totalRowsRead}: Nilai atau berat tidak numerik (Nilai: {$rawNilai}, Berat: {$rawBerat})";
                    }
                    continue;
                }

                // Sesuai pembuktian Tahap 2P: BPS menggunakan round half-up 2 desimal untuk nilai, 3 desimal untuk berat
                $roundedNilai = sprintf('%.2f', round((float)$rawNilai, 2, PHP_ROUND_HALF_UP));
                $roundedBerat = sprintf('%.3f', round((float)$rawBerat, 3, PHP_ROUND_HALF_UP));

                // 2. Ekstrak dan Normalisasi Tahun & Bulan
                $rawTahun = $cellVals[$colTahun] ?? '';
                $rawBulan = $cellVals[$colBulan] ?? '';

                $normTahun = $this->normalizeYear($rawTahun);
                $normBulan = $this->normalizeMonth($rawBulan);

                if ($normTahun === null || $normBulan === null) {
                    $invalidRowsCount++;
                    if (count($validationErrors) < 10) {
                        $validationErrors[] = "Baris {$totalRowsRead}: Periode tidak valid (Tahun: {$rawTahun}, Bulan: {$rawBulan})";
                    }
                    continue;
                }

                // Valid
                $validRowsCount++;

                // Akumulasi agregasi dengan bcadd untuk jaminan presisi absolut
                $sumNilai = bcadd($sumNilai, $roundedNilai, 2);
                $sumBerat = bcadd($sumBerat, $roundedBerat, 3);

                // Catat matriks periode
                if (!isset($periodMatrix[$normTahun])) {
                    $periodMatrix[$normTahun] = [];
                }
                $periodMatrix[$normTahun][$normBulan] = ($periodMatrix[$normTahun][$normBulan] ?? 0) + 1;

                // Catat dimensi unik
                if (!empty($cellVals[$colHs])) {
                    $uniqueHs[$cellVals[$colHs]] = true;
                }
                if (!empty($cellVals[$colNegara])) {
                    $uniqueCountries[$cellVals[$colNegara]] = true;
                }
                if (!empty($cellVals[$colKabKota])) {
                    $uniqueKabkota[$cellVals[$colKabKota]] = true;
                }

                // Ambil sampel data preview
                if (count($samples) < $previewLimit) {
                    $samples[] = [
                        'row_index' => $totalRowsRead,
                        'tahun' => $normTahun,
                        'bulan' => $normBulan,
                        'nilai' => (float)$roundedNilai,
                        'berat' => (float)$roundedBerat,
                        'raw_hs' => $cellVals[$colHs] ?? '',
                        'hs_desc' => $colHsDesc && isset($cellVals[$colHsDesc]) ? $cellVals[$colHsDesc] : '',
                        'raw_negara' => $cellVals[$colNegara] ?? '',
                        'negara_nama' => $colNegaraDesc && isset($cellVals[$colNegaraDesc]) ? $cellVals[$colNegaraDesc] : '',
                        'raw_kabkota' => $cellVals[$colKabKota] ?? '',
                        'sektor' => $colSektor && isset($cellVals[$colSektor]) ? $cellVals[$colSektor] : '',
                        'bec' => $colBec && isset($cellVals[$colBec]) ? $cellVals[$colBec] : '',
                        'kbli' => $colKbli && isset($cellVals[$colKbli]) ? $cellVals[$colKbli] : '',
                        'propasal' => ($sourceType === 'ekspor') ? ($cellVals[$colPropAsal] ?? '') : null,
                    ];
                }
            }
        }

        ksort($periodMatrix);
        $totalMonths = 0;
        foreach ($periodMatrix as &$months) {
            ksort($months);
            $totalMonths += count($months);
        }

        return [
            'total_rows_read' => $totalRowsRead - 1,
            'valid_rows_count' => $validRowsCount,
            'invalid_rows_count' => $invalidRowsCount,
            'validation_errors' => $validationErrors,
            'sum_nilai' => $sumNilai,
            'sum_berat' => $sumBerat,
            'period_matrix' => $periodMatrix,
            'total_distinct_months' => $totalMonths,
            'unique_hs' => $uniqueHs,
            'unique_countries' => $uniqueCountries,
            'unique_kabkota' => $uniqueKabkota,
            'samples' => $samples,
        ];
    }

    /**
     * Normalisasi representasi tahun 2-digit atau 4-digit.
     */
    public function normalizeYear(string $rawYear): ?int
    {
        $rawYear = trim($rawYear);
        if ($rawYear === '') {
            return null;
        }

        // Kasus numeric langsung
        if (is_numeric($rawYear)) {
            $y = (int)$rawYear;
            if ($y >= 2000 && $y <= 2099) {
                return $y;
            }
            if ($y >= 0 && $y <= 99) {
                return 2000 + $y;
            }
            // Kasus khusus index string BPS: '207' -> 2019, '216' -> 2020, dst
            $indexYearMap = [
                207 => 2019,
                216 => 2020,
                224 => 2021,
                232 => 2022,
                240 => 2023,
                248 => 2024,
                257 => 2025,
                265 => 2026,
            ];
            if (isset($indexYearMap[$y])) {
                return $indexYearMap[$y];
            }
        }

        return null;
    }

    /**
     * Normalisasi representasi bulan BPS menjadi integer 1..12.
     */
    public function normalizeMonth(string $rawMonth): ?int
    {
        $rawMonth = trim($rawMonth);
        if ($rawMonth === '') {
            return null;
        }

        if (is_numeric($rawMonth)) {
            $m = (int)$rawMonth;
            if ($m >= 1 && $m <= 12) {
                return $m;
            }
            // Kasus khusus shared string index bulan BPS
            $indexMonthMap = [
                23 => 1,
                37 => 2,
                47 => 3,
                59 => 4,
                70 => 5,
                81 => 6,
                92 => 7,
                103 => 8,
                114 => 9,
                126 => 10,
                137 => 11,
                148 => 12,
            ];
            if (isset($indexMonthMap[$m])) {
                return $indexMonthMap[$m];
            }
        }

        return null;
    }

    /**
     * Evaluasi kelengkapan periode untuk menghitung Neraca Perdagangan.
     * Mengembalikan status apakah periode simetris atau asimetris.
     *
     * @param int $tahun
     * @param int|null $bulan
     * @param bool $hasEkspor
     * @param bool $hasImpor
     * @param float|null $totalFob
     * @param float|null $totalCif
     * @return array
     */
    public function evaluateTradeBalanceStatus(
        int $tahun,
        ?int $bulan,
        bool $hasEkspor,
        bool $hasImpor,
        ?float $totalFob = null,
        ?float $totalCif = null
    ): array {
        $hasComplete = ($hasEkspor && $hasImpor);

        if ($hasComplete) {
            $fob = $totalFob ?? 0.0;
            $cif = $totalCif ?? 0.0;
            $diff = $fob - $cif;

            if ($diff > 0) {
                $status = 'Surplus';
            } elseif ($diff < 0) {
                $status = 'Defisit';
            } else {
                $status = 'Seimbang';
            }

            return [
                'is_complete' => true,
                'status' => $status,
                'neraca_perdagangan' => $diff,
                'message' => 'Data Ekspor dan Impor tersedia lengkap untuk periode ini.',
            ];
        }

        $neraca = null;
        if ($hasEkspor && !$hasImpor) {
            $status = 'Data Impor Belum Tersedia';
        } elseif (!$hasEkspor && $hasImpor) {
            $status = 'Data Ekspor Belum Tersedia';
        } else {
            $status = 'Data Belum Tersedia';
        }

        return [
            'is_complete' => false,
            'status' => $status,
            'neraca_perdagangan' => null,
            'message' => 'Perhitungan Neraca Perdagangan (FOB - CIF) tidak dapat dihitung karena data salah satu atau kedua sisi belum tersedia.',
        ];
    }

    /**
     * Memeriksa pemetaan master data tanpa melakukan fallback yang merusak informasi.
     */
    protected function inspectDimensionMappings(array $uniqueHs, array $uniqueCountries, array $uniqueKabkota, string $sourceType): array
    {
        $warnings = [
            'unregistered_hs' => [],
            'unregistered_hs_count' => 0,
            'unregistered_countries' => [],
            'unregistered_countries_count' => 0,
            'kabkota_normalization' => [],
            'status_indicator' => 'Valid',
        ];

        try {
            // 1. Cek Master HS
            if (!empty($uniqueHs)) {
                $registeredHs = \Illuminate\Support\Facades\DB::table('master_hs')
                    ->whereIn('hs_code', array_keys($uniqueHs))
                    ->pluck('hs_code')
                    ->all();
                $registeredHsMap = array_flip($registeredHs);

                foreach (array_keys($uniqueHs) as $hs) {
                    if (!isset($registeredHsMap[$hs])) {
                        $warnings['unregistered_hs'][] = $hs;
                    }
                }
                $warnings['unregistered_hs_count'] = count($warnings['unregistered_hs']);
            }

            // 2. Cek Master Negara
            if (!empty($uniqueCountries)) {
                $registeredCountries = \Illuminate\Support\Facades\DB::table('master_negara')
                    ->whereIn('kode_negara', array_keys($uniqueCountries))
                    ->pluck('kode_negara')
                    ->all();
                $registeredCountryMap = array_flip($registeredCountries);

                foreach (array_keys($uniqueCountries) as $c) {
                    if (!isset($registeredCountryMap[$c])) {
                        $warnings['unregistered_countries'][] = $c;
                    }
                }
                $warnings['unregistered_countries_count'] = count($warnings['unregistered_countries']);
            }

            // 3. Cek Master KabKota (khusus Ekspor Provinsi Riau)
            if ($sourceType === 'ekspor' && !empty($uniqueKabkota)) {
                $masterKab = \Illuminate\Support\Facades\DB::table('master_kabkota')
                    ->where('provinsi_id', 4)
                    ->get();
                $kabByName = [];
                foreach ($masterKab as $k) {
                    $kabByName[strtoupper(trim($k->nama_kabkota))] = $k->kabkota_id;
                }

                foreach (array_keys($uniqueKabkota) as $rawKab) {
                    $normName = $rawKab;
                    if (strcasecmp($rawKab, 'Meranti') === 0) {
                        $normName = 'Kab. Kepulauan Meranti';
                    } elseif (stripos($rawKab, 'Kab.') === false && stripos($rawKab, 'Kota') === false) {
                        if (in_array(strtoupper($rawKab), ['PEKANBARU', 'DUMAI'])) {
                            $normName = 'Kota ' . $rawKab;
                        } else {
                            $normName = 'Kab. ' . $rawKab;
                        }
                    }

                    $matchedId = $kabByName[strtoupper($normName)] ?? null;
                    if ($matchedId !== null) {
                        if ($normName !== $rawKab) {
                            $warnings['kabkota_normalization'][] = [
                                'raw' => $rawKab,
                                'normalized' => $normName,
                                'kabkota_id' => $matchedId,
                            ];
                        }
                    } else {
                        $warnings['kabkota_unmapped'][] = $rawKab;
                    }
                }
            }

            // Tentukan status indikator validasi
            if (!empty($warnings['unregistered_hs']) || !empty($warnings['unregistered_countries'])) {
                $warnings['status_indicator'] = 'Perlu Ditinjau';
            } elseif (!empty($warnings['kabkota_unmapped'])) {
                $warnings['status_indicator'] = 'Belum Terpetakan';
            } else {
                $warnings['status_indicator'] = 'Valid';
            }

        } catch (\Throwable $e) {
            // Jika DB tidak terkoneksi (misal unit test offline), catat status
            $warnings['db_error'] = $e->getMessage();
            $warnings['status_indicator'] = 'Perlu Ditinjau';
        }

        return $warnings;
    }

    /**
     * Membaca halaman data sampel (pagination) dari file Excel tanpa memuat seluruh file ke memori.
     */
    public function getPaginatedRows(string $filePath, string $sourceType, int $page = 1, int $perPage = 20): array
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(300);

        if (!file_exists($filePath)) {
            throw new Exception("Berkas '{$filePath}' tidak ditemukan.");
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new Exception("Berkas bukan format ZIP / XLSX yang valid.");
        }

        try {
            $workbookInfo = $this->parseWorkbookStructure($zip);
            $expectedSheet = ($sourceType === 'ekspor') ? 'RawDataEkspor' : 'RawDataImpor';
            if (!isset($workbookInfo['sheets'][$expectedSheet])) {
                throw new Exception("Worksheet '{$expectedSheet}' tidak ditemukan dalam berkas.");
            }
            $targetXmlFile = $workbookInfo['sheets'][$expectedSheet];

            $headers = $this->readSheetHeaders($zip, $targetXmlFile);
            $headerToCol = array_flip($headers);
            $sharedStrings = $this->loadSharedStrings($zip);

            $colBulan = $headerToCol['BULAN'] ?? 'B';
            $colTahun = $headerToCol['TAHUN'] ?? 'C';
            $colNilai = $headerToCol['NILAI'] ?? 'I';
            $colBerat = $headerToCol['BERAT'] ?? 'H';
            $colHs = $headerToCol['HS'] ?? 'F';
            $colNegara = $headerToCol['NEGARA'] ?? 'G';
            $colKabKota = $headerToCol['Kabupaten/Kota'] ?? 'P';
            $colPropAsal = $headerToCol['PROPASAL'] ?? 'J';
            $colHsDesc = $headerToCol['COMMDESC'] ?? ($headerToCol['DeskripsiHS2'] ?? null);
            $colNegaraDesc = $headerToCol['Negara'] ?? null;
            $colSektor = $headerToCol['SEKTOR KOMODITAS'] ?? null;
            $colBec = $headerToCol['BECCODE'] ?? null;
            $colKbli = $headerToCol['DeskKBLI20'] ?? null;

            $reader = new XMLReader();
            $reader->xml($zip->getFromName($targetXmlFile), null, LIBXML_NONET);

            $startRow = ($page - 1) * $perPage + 1; // 1-based index transaksi (mengabaikan row 1 header)
            $endRow = $page * $perPage;

            $currentRow = 0;
            $rows = [];
            $totalCount = 0;

            while ($reader->read()) {
                if ($reader->nodeType == XMLReader::ELEMENT && $reader->localName == 'row') {
                    $currentRow++;
                    if ($currentRow === 1) continue; // header

                    $totalCount++;
                    $dataRowIndex = $currentRow - 1;

                    if ($dataRowIndex >= $startRow && $dataRowIndex <= $endRow) {
                        $rowXml = simplexml_load_string($reader->readOuterXml());
                        $cellVals = [];
                        foreach ($rowXml->c as $c) {
                            $colRef = preg_replace('/[0-9]/', '', (string)$c['r']);
                            $val = (string)$c->v;
                            $type = (string)$c['t'];
                            if ($type === 's' && isset($sharedStrings[(int)$val])) {
                                $val = $sharedStrings[(int)$val];
                            }
                            $cellVals[$colRef] = $val;
                        }

                        $rawNilai = $cellVals[$colNilai] ?? '0';
                        $rawBerat = $cellVals[$colBerat] ?? '0';
                        $normTahun = $this->normalizeYear($cellVals[$colTahun] ?? '');
                        $normBulan = $this->normalizeMonth($cellVals[$colBulan] ?? '');

                        $rows[] = [
                            'row_index' => $dataRowIndex,
                            'tahun' => $normTahun,
                            'bulan' => $normBulan,
                            'nilai' => (float)round((float)$rawNilai, 2, PHP_ROUND_HALF_UP),
                            'berat' => (float)round((float)$rawBerat, 3, PHP_ROUND_HALF_UP),
                            'raw_hs' => $cellVals[$colHs] ?? '',
                            'hs_desc' => $colHsDesc && isset($cellVals[$colHsDesc]) ? $cellVals[$colHsDesc] : '',
                            'raw_negara' => $cellVals[$colNegara] ?? '',
                            'negara_nama' => $colNegaraDesc && isset($cellVals[$colNegaraDesc]) ? $cellVals[$colNegaraDesc] : '',
                            'raw_kabkota' => $cellVals[$colKabKota] ?? '',
                            'sektor' => $colSektor && isset($cellVals[$colSektor]) ? $cellVals[$colSektor] : '',
                            'bec' => $colBec && isset($cellVals[$colBec]) ? $cellVals[$colBec] : '',
                            'kbli' => $colKbli && isset($cellVals[$colKbli]) ? $cellVals[$colKbli] : '',
                            'propasal' => ($sourceType === 'ekspor') ? ($cellVals[$colPropAsal] ?? '') : null,
                        ];
                    }
                }
            }
            $reader->close();

            $lastPage = (int)ceil($totalCount / $perPage);

            return [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $totalCount,
                'last_page' => $lastPage,
                'from' => $totalCount > 0 ? $startRow : 0,
                'to' => min($endRow, $totalCount),
                'data' => $rows,
            ];
        } finally {
            $zip->close();
        }
    }
}
