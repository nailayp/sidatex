<?php

namespace App\Services;

use App\Services\Parsers\ExportDistributionDbfParser;
use App\Services\Parsers\ExportNeracaDbfParser;
use App\Services\Parsers\ImportDistributionDbfParser;
use App\Services\Parsers\ImportNeracaDbfParser;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * Service terpadu untuk validasi dan penyiapan preview berkas DBF SIDATEX.
 * Mendukung 4 parser DBF modular (Distribusi Ekspor/Impor dan Neraca Ekspor/Impor).
 * Mematuhi Zero Boilerplate, PSR-12, dan Clean Query Optimization.
 *
 * PENTING: Service ini READ ONLY ke database dan TIDAK menyimpan baris transaksi.
 */
class DbfUploadValidationService
{
    protected ExportDistributionDbfParser $exportDistParser;
    protected ImportDistributionDbfParser $importDistParser;
    protected ExportNeracaDbfParser $exportNeracaParser;
    protected ImportNeracaDbfParser $importNeracaParser;

    public function __construct(
        ExportDistributionDbfParser $exportDistParser,
        ImportDistributionDbfParser $importDistParser,
        ExportNeracaDbfParser $exportNeracaParser,
        ImportNeracaDbfParser $importNeracaParser
    ) {
        $this->exportDistParser = $exportDistParser;
        $this->importDistParser = $importDistParser;
        $this->exportNeracaParser = $exportNeracaParser;
        $this->importNeracaParser = $importNeracaParser;
    }

    /**
     * Memilih instance parser yang tepat berdasarkan sumber dan jenis transaksi.
     */
    public function resolveParser(string $sourceType, string $transactionType)
    {
        $source = strtolower(trim($sourceType));
        $trans = strtolower(trim($transactionType));

        if (!in_array($source, ['distribusi', 'neraca'])) {
            throw new Exception("Sumber data '{$sourceType}' tidak valid. Harus 'distribusi' atau 'neraca'.");
        }

        if (!in_array($trans, ['ekspor', 'impor'])) {
            throw new Exception("Jenis transaksi '{$transactionType}' tidak valid. Harus 'ekspor' atau 'impor'.");
        }

        if ($source === 'distribusi') {
            return ($trans === 'ekspor') ? $this->exportDistParser : $this->importDistParser;
        }

        return ($trans === 'ekspor') ? $this->exportNeracaParser : $this->importNeracaParser;
    }

    /**
     * Memvalidasi struktur berkas DBF, memetakan dimensi master, dan menghasilkan data preview terstruktur.
     */
    public function validateAndPreview(
        string $filePath,
        string $originalFilename,
        string $sourceType,
        string $transactionType,
        int $sampleLimit = 15
    ): array {
        $sourceType = strtolower(trim($sourceType));
        $transactionType = strtolower(trim($transactionType));

        $parser = $this->resolveParser($sourceType, $transactionType);
        $meta = $parser->inspect($filePath, $originalFilename);

        $tahun = (int)$meta['tahun'];
        $bulan = (int)$meta['bulan'];

        // Cek duplikasi batch aktif di database
        $existingBatch = DB::table('data_batches')
            ->where('tipe_sumber', $sourceType)
            ->whereRaw('LOWER(jenis_data) = ?', [$transactionType])
            ->where('periode_tahun', $tahun)
            ->where('periode_bulan', $bulan)
            ->whereNotIn('batch_id', function ($query) {
                $query->select('batch_id')->from('trash')->whereNull('restored_at');
            })
            ->first();

        // Load lookup master secara efisien dalam 1 kueri per master (Clean Query Optimization)
        $masterHs = DB::table('master_hs')
            ->select('hs_id', 'hs_code', 'hs2', 'hs2_desc', 'sektor')
            ->get()
            ->keyBy('hs_code');

        $masterHsByHs2 = DB::table('master_hs')
            ->whereNotNull('hs2_desc')
            ->select('hs2', 'hs2_desc', 'sektor')
            ->get()
            ->keyBy('hs2');

        $masterNegaraKode = DB::table('master_negara')->pluck('negara_id', 'kode_negara')->toArray();
        $masterNegaraIso = DB::table('master_negara')->whereNotNull('nation')->pluck('negara_id', 'nation')->toArray();
        $masterNegaraNama = DB::table('master_negara')->pluck('nama_negara', 'negara_id')->toArray();

        $allPelabuhans = DB::table('master_pelabuhan')->get();
        $masterWilayahBps = DB::table('master_wilayah_bps')->pluck('nama_wilayah', 'kode_wilayah')->toArray();

        // Rekapitulasi validasi
        $totalRows = 0;
        $validRows = 0;
        $invalidRows = 0;
        $totalNilai = '0.000000';
        $totalBerat = '0.000000';

        $categoryErrors = [
            'struktur' => 0,
            'hs' => 0,
            'negara' => 0,
            'pelabuhan' => 0,
            'wilayah' => 0,
            'periode' => 0,
            'numerik' => 0,
            'kppbc' => 0,
        ];

        $detailedErrors = [];
        $previewSample = [];

        // Agregasi sementara untuk grafik simulasi dashboard
        $topKomoditiMap = [];
        $topNegaraMap = [];
        $topPelabuhanMap = [];
        $kabkotaMap = [];
        $migasNilai = 0.0;
        $nonMigasNilai = 0.0;
        $migasBerat = 0.0;
        $nonMigasBerat = 0.0;

        foreach ($parser->parseStream($filePath, null, 0, $originalFilename) as $row) {
            $totalRows++;
            $rowErrors = [];

            // 1. Validasi Periode
            $rowTahun = (int)($row['tahun'] ?? 0);
            $rowBulan = (int)($row['bulan'] ?? 0);
            if ($rowTahun !== $tahun || $rowBulan !== $bulan) {
                $rowErrors[] = "Inkonsistensi periode baris (Tahun: {$rowTahun}, Bulan: {$rowBulan}) dengan header (Tahun: {$tahun}, Bulan: {$bulan}).";
                $categoryErrors['periode']++;
            }

            // 2. Validasi Kode HS
            $hsCode = trim((string)($row['hs_code'] ?? ''));
            $hsInfo = $masterHs->get($hsCode);
            $hsId = $hsInfo ? $hsInfo->hs_id : null;
            $isUnmappedHs = false;

            if (!$hsId) {
                if ($sourceType === 'neraca') {
                    // Pada modul Neraca: HS unmapped diizinkan (hs_id NULL, mapping_status = 'unmapped')
                    $isUnmappedHs = true;
                } else {
                    // Pada modul Distribusi: HS wajib terdaftar di master
                    $rowErrors[] = "Kode HS '{$hsCode}' tidak ditemukan di Master HS.";
                    $categoryErrors['hs']++;
                }
            }

            // 3. Validasi Negara
            $negKode = trim((string)($row['negara_kode'] ?? ''));
            $negIso = strtoupper(trim((string)($row['negara_iso'] ?? '')));
            $negId = null;

            if ($negKode !== '' && isset($masterNegaraKode[$negKode])) {
                $negId = $masterNegaraKode[$negKode];
            } elseif ($negIso !== '' && isset($masterNegaraIso[$negIso])) {
                $negId = $masterNegaraIso[$negIso];
            }

            if (!$negId) {
                $negKey = ($negKode ?: $negIso) ?: 'KOSONG';
                $rowErrors[] = "Negara '{$negKey}' tidak ditemukan di Master Negara.";
                $categoryErrors['negara']++;
            }

            // 4. Validasi Pelabuhan
            $locode = strtoupper(trim((string)($row['pelabuhan_locode'] ?? '')));
            $bpsCode = trim((string)($row['pelabuhan_bps'] ?? ''));
            $pelId = null;
            $pelName = 'Lainnya';

            $matchedCandidates = $allPelabuhans->filter(function ($p) use ($bpsCode, $locode) {
                return ($bpsCode !== '' && $p->kode_pelabuhan === $bpsCode) ||
                       ($locode !== '' && strtoupper((string)$p->un_locode) === $locode) ||
                       ($bpsCode !== '' && strlen($bpsCode) === 5 && substr($bpsCode, 2) === $p->kode_pelabuhan);
            });

            if ($matchedCandidates->count() === 1) {
                $pelObj = $matchedCandidates->first();
                $pelId = $pelObj->pelabuhan_id;
                $pelName = $pelObj->nama_pelabuhan;
            } elseif ($matchedCandidates->count() > 1) {
                $portKey = ($bpsCode . ' / ' . $locode) ?: 'UNKNOWN';
                $candidateNames = $matchedCandidates->pluck('nama_pelabuhan')->implode(', ');
                $rowErrors[] = "Pelabuhan '{$portKey}' ambigu (ditemukan lebih dari 1 kandidat: {$candidateNames}).";
                $categoryErrors['pelabuhan']++;
            } else {
                $portKey = ($bpsCode . ' / ' . $locode) ?: 'UNKNOWN';
                if ($sourceType === 'neraca') {
                    // Pada modul Neraca: pelabuhan muat/bongkar dapat berada di luar master lokal Riau
                    // dan pelabuhan_id bersifat nullable pada neraca_transactions
                    $pelId = null;
                    $pelName = ($locode ?: $bpsCode) ?: 'Luar Daerah / Lainnya';
                } else {
                    $rowErrors[] = "Pelabuhan '{$portKey}' belum terpetakan di Master Pelabuhan.";
                    $categoryErrors['pelabuhan']++;
                }
            }

            // 5. Validasi Wilayah Sesuai Aturan Modul
            if ($sourceType === 'distribusi' && $transactionType === 'ekspor') {
                $provPod = trim((string)($row['provpod'] ?? ''));
                if ($provPod !== '' && $provPod !== '14') {
                    $rowErrors[] = "PROVPOD '{$provPod}' bukan pelabuhan muat Riau (14).";
                    $categoryErrors['wilayah']++;
                }
                $provOrig = trim((string)($row['provorig'] ?? ''));
                if ($provOrig !== '' && !isset($masterWilayahBps[$provOrig])) {
                    $rowErrors[] = "Wilayah asal komoditas '{$provOrig}' belum terdaftar di Master Wilayah.";
                    $categoryErrors['wilayah']++;
                }
            } elseif ($sourceType === 'neraca' && $transactionType === 'ekspor') {
                $provOrig2 = trim((string)($row['provorig2'] ?? ''));
                if ($provOrig2 !== '' && $provOrig2 !== '14') {
                    $rowErrors[] = "PROVORIG2 '{$provOrig2}' bukan provinsi asal komoditas Riau (14).";
                    $categoryErrors['wilayah']++;
                }
                $provOrig = trim((string)($row['provorig'] ?? ''));
                if ($provOrig !== '' && !isset($masterWilayahBps[$provOrig])) {
                    $rowErrors[] = "Kab/Kota produsen '{$provOrig}' belum terdaftar di Master Wilayah.";
                    $categoryErrors['wilayah']++;
                }
            } elseif ($sourceType === 'neraca' && $transactionType === 'impor') {
                $kppbc = trim((string)($row['kode_kppbc'] ?? ($row['kppbc'] ?? '')));
                $namaKppbc = trim((string)($row['nama_kppbc'] ?? ''));
                $provKppbc = trim((string)($row['provinsi_id_code'] ?? ($row['prov_kppbc'] ?? '')));

                if ($kppbc === '' || $namaKppbc === '') {
                    $rowErrors[] = "Informasi kantor pabean KPPBC kosong pada transaksi Neraca Impor.";
                    $categoryErrors['kppbc']++;
                }
                if ($provKppbc !== '' && $provKppbc !== '14') {
                    $rowErrors[] = "PROV_KPPBC '{$provKppbc}' bukan kantor pabean di bawah wilayah Riau (14).";
                    $categoryErrors['kppbc']++;
                }
            }

            // 6. Validasi Presisi Nilai Uang dan Berat Netto (String Decimal)
            $nilaiStr = trim((string)($row['nilai_usd'] ?? '0.000000'));
            $beratStr = trim((string)($row['berat_kg'] ?? '0.000000'));

            if (!preg_match('/^-?\d+(\.\d+)?$/', $nilaiStr)) {
                $rowErrors[] = "Nilai transaksi bukan format angka desimal valid ('{$nilaiStr}').";
                $categoryErrors['numerik']++;
            } elseif (bccomp($nilaiStr, '0', 6) < 0) {
                $rowErrors[] = "Nilai transaksi tidak boleh bernilai negatif ({$nilaiStr}).";
                $categoryErrors['numerik']++;
            }

            if (!preg_match('/^-?\d+(\.\d+)?$/', $beratStr)) {
                $rowErrors[] = "Berat netto bukan format angka desimal valid ('{$beratStr}').";
                $categoryErrors['numerik']++;
            } elseif (bccomp($beratStr, '0', 6) < 0) {
                $rowErrors[] = "Berat netto tidak boleh bernilai negatif ({$beratStr}).";
                $categoryErrors['numerik']++;
            }

            // Keputusan Baris
            $isRowValid = empty($rowErrors);
            if ($isRowValid) {
                $validRows++;
                $totalNilai = bcadd($totalNilai, $nilaiStr, 6);
                $totalBerat = bcadd($totalBerat, $beratStr, 6);

                // Agregasi untuk visualisasi preview
                $valFloat = (float)$nilaiStr;
                $netFloat = (float)$beratStr;

                $hs2 = substr($hsCode, 0, 2);
                $hs2Desc = $hsInfo ? ($hsInfo->hs2_desc ?: 'Lain-lain') : ($masterHsByHs2->get($hs2)->hs2_desc ?? 'Lain-lain');
                $isMigas = $hsInfo && ($hsInfo->hs2 === '27' || str_contains($hsInfo->sektor ?? '', 'Gas'));

                if ($isMigas) {
                    $migasNilai += $valFloat;
                    $migasBerat += $netFloat;
                } else {
                    $nonMigasNilai += $valFloat;
                    $nonMigasBerat += $netFloat;
                }

                if (!isset($topKomoditiMap[$hs2Desc])) {
                    $topKomoditiMap[$hs2Desc] = ['item' => $hs2Desc, 'fob' => 0.0, 'netto' => 0.0];
                }
                $topKomoditiMap[$hs2Desc]['fob'] += $valFloat;
                $topKomoditiMap[$hs2Desc]['netto'] += $netFloat;

                $negNama = $negId && isset($masterNegaraNama[$negId]) ? $masterNegaraNama[$negId] : ($negIso ?: $negKode);
                if (!isset($topNegaraMap[$negNama])) {
                    $topNegaraMap[$negNama] = ['item' => $negNama, 'fob' => 0.0, 'netto' => 0.0];
                }
                $topNegaraMap[$negNama]['fob'] += $valFloat;
                $topNegaraMap[$negNama]['netto'] += $netFloat;

                if (!isset($topPelabuhanMap[$pelName])) {
                    $topPelabuhanMap[$pelName] = ['item' => $pelName, 'fob' => 0.0, 'netto' => 0.0];
                }
                $topPelabuhanMap[$pelName]['fob'] += $valFloat;
                $topPelabuhanMap[$pelName]['netto'] += $netFloat;

                $kabCode = trim((string)($row['provorig'] ?? ''));
                if ($kabCode !== '' && isset($masterWilayahBps[$kabCode])) {
                    $kabNama = $masterWilayahBps[$kabCode];
                    if (!isset($kabkotaMap[$kabNama])) {
                        $kabkotaMap[$kabNama] = ['kabkota' => $kabNama, 'fob' => 0.0, 'netto' => 0.0];
                    }
                    $kabkotaMap[$kabNama]['fob'] += $valFloat;
                    $kabkotaMap[$kabNama]['netto'] += $netFloat;
                }
            } else {
                $invalidRows++;
                if (count($detailedErrors) < 30) {
                    $detailedErrors[] = [
                        'baris' => $totalRows,
                        'alasan' => implode('; ', $rowErrors),
                        'raw' => $row['raw'] ?? [],
                    ];
                }
            }

            // Ambil sampel data preview
            if (count($previewSample) < $sampleLimit) {
                $sampleItem = [
                    'baris' => $totalRows,
                    'hs' => $hsCode,
                    'mapping_status' => $isUnmappedHs ? 'unmapped' : 'mapped',
                    'negara' => ($row['negara_iso'] ?? '') ?: ($row['negara_kode'] ?? '-'),
                    'pelabuhan' => ($row['pelabuhan_locode'] ?? '') ?: ($row['pelabuhan_bps'] ?? '-'),
                    'nilai' => $nilaiStr,
                    'berat' => $beratStr,
                    'status' => $isRowValid ? 'VALID' : 'ERROR',
                    'pesan' => implode('; ', $rowErrors),
                ];

                if (isset($row['provorig'])) {
                    $sampleItem['provorig'] = $row['provorig'];
                }
                if (isset($row['kode_kppbc']) || isset($row['kppbc'])) {
                    $sampleItem['kppbc'] = $row['kode_kppbc'] ?? $row['kppbc'];
                    $sampleItem['nama_kppbc'] = $row['nama_kppbc'] ?? '';
                }

                $previewSample[] = $sampleItem;
            }
        }

        // Urutkan top ranking untuk visualisasi preview
        uasort($topKomoditiMap, fn($a, $b) => $b['fob'] <=> $a['fob']);
        uasort($topNegaraMap, fn($a, $b) => $b['fob'] <=> $a['fob']);
        uasort($topPelabuhanMap, fn($a, $b) => $b['fob'] <=> $a['fob']);
        uasort($kabkotaMap, fn($a, $b) => $b['fob'] <=> $a['fob']);

        $totalNilaiFloat = (float)$totalNilai;
        $totalBeratFloat = (float)$totalBerat;

        return [
            'format' => 'DBF',
            'sumber_data' => $sourceType,
            'jenis_transaksi' => $transactionType,
            'nama_file' => $originalFilename,
            'tahun' => $tahun,
            'bulan' => $bulan,
            'total_baris' => $totalRows,
            'baris_valid' => $validRows,
            'baris_invalid' => $invalidRows,
            'status_validasi' => ($invalidRows === 0) ? 'VALID' : (($validRows > 0) ? 'WARNING' : 'INVALID'),
            'total_nilai_usd' => $totalNilai,
            'total_berat_kg' => $totalBerat,
            'total_nilai_juta_usd' => round($totalNilaiFloat / 1000000, 2),
            'total_berat_ribu_ton' => round($totalBeratFloat / 1000000, 2),
            'ringkasan_error' => $categoryErrors,
            'errors' => $detailedErrors,
            'sample_data' => $previewSample,
            'fields_dikenali' => $meta['fields'] ?? [],
            'duplikasi_batch' => $existingBatch ? [
                'batch_id' => $existingBatch->batch_id,
                'nama_file' => $existingBatch->nama_file,
                'uploaded_at' => $existingBatch->uploaded_at,
                'status' => $existingBatch->status,
            ] : null,
            'dashboard_simulation' => [
                'kpi' => [
                    'total_fob' => $totalNilaiFloat,
                    'total_netto' => $totalBeratFloat,
                    'avg_per_tx' => $validRows > 0 ? ($totalNilaiFloat / $validRows) : 0,
                    'migas_fob' => $migasNilai,
                    'non_migas_fob' => $nonMigasNilai,
                    'migas_netto' => $migasBerat,
                    'non_migas_netto' => $nonMigasBerat,
                    'count_negara' => count($topNegaraMap),
                    'count_komoditi' => count($topKomoditiMap),
                    'count_pelabuhan' => count($topPelabuhanMap),
                ],
                'top_komoditi' => array_values(array_slice($topKomoditiMap, 0, 10)),
                'top_negara' => array_values(array_slice($topNegaraMap, 0, 10)),
                'top_pelabuhan' => array_values(array_slice($topPelabuhanMap, 0, 10)),
                'top_kabkota' => array_values(array_slice($kabkotaMap, 0, 10)),
            ],
        ];
    }
}
