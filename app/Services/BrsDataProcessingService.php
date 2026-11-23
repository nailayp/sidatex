<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Service terpadu Data Processing Engine SIDATEX sesuai Metodologi BRS BPS Riau.
 *
 * Mengelola alur lengkap:
 * Upload DBF -> Parsing -> Cleaning -> Validation -> Transformation -> BRS Calculation -> Preview -> Storage
 *
 * Mematuhi Zero Boilerplate, PSR-12, Clean Query Optimization, dan Presisi DECIMAL(20,6).
 */
class BrsDataProcessingService
{
    protected DbfUploadValidationService $validationService;
    protected BrsAggregationService $aggregationService;

    public function __construct(
        DbfUploadValidationService $validationService,
        BrsAggregationService $aggregationService
    ) {
        $this->validationService = $validationService;
        $this->aggregationService = $aggregationService;
    }

    /**
     * Memproses berkas DBF: parsing, pembersihan, validasi, pengayaan, dan kalkulasi BRS.
     */
    public function validateAndEnrich(
        string $filePath,
        string $originalFilename,
        string $sourceType,
        string $transactionType,
        array $options = []
    ): array {
        $sourceType = strtolower(trim($sourceType));
        $transactionType = strtolower(trim($transactionType));

        if (!in_array($sourceType, ['distribusi', 'neraca'], true)) {
            throw new Exception("Sumber data '{$sourceType}' tidak valid. Harus 'distribusi' atau 'neraca'.");
        }

        if (!in_array($transactionType, ['ekspor', 'impor'], true)) {
            throw new Exception("Jenis transaksi '{$transactionType}' tidak valid. Harus 'ekspor' atau 'impor'.");
        }

        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new Exception("Berkas DBF tidak ditemukan atau tidak dapat dibaca: {$filePath}");
        }

        $parser = $this->validationService->resolveParser($sourceType, $transactionType);
        $meta = $parser->inspect($filePath, $originalFilename);

        $tahun = (int)$meta['tahun'];
        $bulan = (int)$meta['bulan'];
        $sampleLimit = $options['sample_limit'] ?? 15;

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

        // Load lookup master secara efisien (1 kueri per master)
        $masterHs = DB::table('master_hs')->get()->keyBy('hs_code');
        $masterNegaraKode = DB::table('master_negara')->pluck('negara_id', 'kode_negara')->toArray();
        $masterNegaraIso = DB::table('master_negara')->whereNotNull('nation')->pluck('negara_id', 'nation')->toArray();
        $masterNegaraObj = DB::table('master_negara')->get()->keyBy('negara_id');

        $allPelabuhans = DB::table('master_pelabuhan')->get();
        $masterWilayahBps = DB::table('master_wilayah_bps')->pluck('nama_wilayah', 'kode_wilayah')->toArray();
        $masterKabkota = DB::table('master_kabkota')->pluck('kabkota_id', 'kode_kabkota')->toArray();

        // Variabel kalkulasi presisi tinggi
        $totalRows = 0;
        $validRows = 0;
        $invalidRows = 0;
        $totalNilaiUsd = '0.000000';
        $totalBeratKg = '0.000000';

        $migasNilaiUsd = '0.000000';
        $migasBeratKg = '0.000000';
        $nonMigasNilaiUsd = '0.000000';
        $nonMigasBeratKg = '0.000000';

        // Peta Sektor (Ekspor)
        $sektorMap = [
            'INDUSTRI PENGOLAHAN' => ['usd' => '0.000000', 'kg' => '0.000000'],
            'PERTANIAN' => ['usd' => '0.000000', 'kg' => '0.000000'],
            'PERTAMBANGAN' => ['usd' => '0.000000', 'kg' => '0.000000'],
            'MIGAS' => ['usd' => '0.000000', 'kg' => '0.000000'],
            'UNMAPPED' => ['usd' => '0.000000', 'kg' => '0.000000'],
        ];

        // Peta BEC (Impor)
        $becMap = [
            'Bahan Baku/Penolong' => ['usd' => '0.000000', 'kg' => '0.000000'],
            'Barang Modal' => ['usd' => '0.000000', 'kg' => '0.000000'],
            'Barang Konsumsi' => ['usd' => '0.000000', 'kg' => '0.000000'],
            'UNMAPPED' => ['usd' => '0.000000', 'kg' => '0.000000'],
        ];

        $hs2Map = [];
        $negaraMap = [];
        $pelabuhanMap = [];
        $kabkotaMap = [];

        $unmappedHsCodes = [];
        $unmappedTotalNilai = '0.000000';
        $unmappedTotalRows = 0;

        $detailedErrors = [];
        $previewSample = [];
        $enrichedRows = [];

        foreach ($parser->parseStream($filePath, null, 0, $originalFilename) as $rawRow) {
            $totalRows++;
            $rowErrors = [];

            // 1. CLEANING & ZERO-PADDING
            $rowTahun = (int)($rawRow['tahun'] ?? 0);
            $rowBulan = (int)($rawRow['bulan'] ?? 0);
            $hsCode = str_pad(trim((string)($rawRow['hs_code'] ?? '')), 8, '0', STR_PAD_LEFT);
            $negKode = trim((string)($rawRow['negara_kode'] ?? ''));
            $negIso = strtoupper(trim((string)($rawRow['negara_iso'] ?? '')));
            $locode = strtoupper(trim((string)($rawRow['pelabuhan_locode'] ?? '')));
            $bpsCode = trim((string)($rawRow['pelabuhan_bps'] ?? ''));
            $provOrig = trim((string)($rawRow['provorig'] ?? ''));

            // Sanitasi format angka desimal
            $nilaiStr = trim((string)($rawRow['nilai_usd'] ?? '0.000000'));
            $beratStr = trim((string)($rawRow['berat_kg'] ?? '0.000000'));

            // 2. VALIDATION
            // Periode
            if ($rowTahun !== $tahun || $rowBulan !== $bulan) {
                $rowErrors[] = "Inkonsistensi periode baris ({$rowBulan}/{$rowTahun}) dengan header ({$bulan}/{$tahun}).";
            }

            // Numerik
            if (!preg_match('/^-?\d+(\.\d+)?$/', $nilaiStr) || bccomp($nilaiStr, '0', 6) < 0) {
                $rowErrors[] = "Nilai transaksi tidak valid atau negatif ({$nilaiStr}).";
            }
            if (!preg_match('/^-?\d+(\.\d+)?$/', $beratStr) || bccomp($beratStr, '0', 6) < 0) {
                $rowErrors[] = "Berat netto tidak valid atau negatif ({$beratStr}).";
            }

            // Master HS
            $hsObj = $masterHs->get($hsCode);
            $hsId = $hsObj ? $hsObj->hs_id : null;
            $isUnmappedHs = false;

            if (!$hsId) {
                if ($sourceType === 'neraca') {
                    $isUnmappedHs = true;
                    $unmappedTotalRows++;
                    $unmappedTotalNilai = bcadd($unmappedTotalNilai, $nilaiStr, 6);
                    if (!in_array($hsCode, $unmappedHsCodes, true)) {
                        $unmappedHsCodes[] = $hsCode;
                    }
                } else {
                    $rowErrors[] = "Kode HS '{$hsCode}' tidak ditemukan di Master HS.";
                }
            }

            // Master Negara
            $negId = null;
            if ($negKode !== '' && isset($masterNegaraKode[$negKode])) {
                $negId = $masterNegaraKode[$negKode];
            } elseif ($negIso !== '' && isset($masterNegaraIso[$negIso])) {
                $negId = $masterNegaraIso[$negIso];
            }

            if (!$negId) {
                $rowErrors[] = "Negara '{$negKode}/{$negIso}' belum terdaftar di Master Negara.";
            }

            // Master Pelabuhan
            $pelId = null;
            $pelNama = 'Lainnya';
            $matchedPorts = $allPelabuhans->filter(function ($p) use ($bpsCode, $locode) {
                return ($bpsCode !== '' && $p->kode_pelabuhan === $bpsCode) ||
                       ($locode !== '' && strtoupper((string)$p->un_locode) === $locode) ||
                       ($bpsCode !== '' && strlen($bpsCode) === 5 && substr($bpsCode, 2) === $p->kode_pelabuhan);
            });

            if ($matchedPorts->count() === 1) {
                $pelObj = $matchedPorts->first();
                $pelId = $pelObj->pelabuhan_id;
                $pelNama = $pelObj->nama_pelabuhan;
            } elseif ($matchedPorts->count() > 1) {
                $rowErrors[] = "Pelabuhan '{$bpsCode}/{$locode}' ambigu (ditemukan lebih dari 1 kandidat).";
            } else {
                if ($sourceType === 'neraca') {
                    $pelId = null;
                    $pelNama = ($locode ?: $bpsCode) ?: 'Luar Daerah / Lainnya';
                } else {
                    $rowErrors[] = "Pelabuhan '{$bpsCode}/{$locode}' belum terpetakan di Master Pelabuhan.";
                }
            }

            // Validasi Khusus Modul
            if ($sourceType === 'distribusi' && $transactionType === 'ekspor') {
                $provPod = trim((string)($rawRow['provpod'] ?? ''));
                if ($provPod !== '' && $provPod !== '14') {
                    $rowErrors[] = "PROVPOD '{$provPod}' bukan pelabuhan muat Riau (14).";
                }
            } elseif ($sourceType === 'neraca' && $transactionType === 'ekspor') {
                $provOrig2 = trim((string)($rawRow['provorig2'] ?? ''));
                if ($provOrig2 !== '' && $provOrig2 !== '14') {
                    $rowErrors[] = "PROVORIG2 '{$provOrig2}' bukan provinsi asal barang Riau (14).";
                }
            } elseif ($sourceType === 'neraca' && $transactionType === 'impor') {
                $kppbc = trim((string)($rawRow['kode_kppbc'] ?? ($rawRow['kppbc'] ?? '')));
                $provKppbc = trim((string)($rawRow['provinsi_id_code'] ?? ($rawRow['prov_kppbc'] ?? '')));
                if ($kppbc === '') {
                    $rowErrors[] = "Kode kantor pabean KPPBC kosong pada transaksi Neraca Impor.";
                }
                if ($provKppbc !== '' && $provKppbc !== '14') {
                    $rowErrors[] = "PROV_KPPBC '{$provKppbc}' bukan pabean wilayah Riau (14).";
                }
            }

            // Keputusan Baris
            $isRowValid = empty($rowErrors);
            if ($isRowValid) {
                $validRows++;
                $totalNilaiUsd = bcadd($totalNilaiUsd, $nilaiStr, 6);
                $totalBeratKg = bcadd($totalBeratKg, $beratStr, 6);

                // 3. TRANSFORMATION & BRS CALCULATION
                $hs2 = substr($hsCode, 0, 2);
                $hs2Desc = $hsObj ? ($hsObj->hs2_desc ?: "Bab {$hs2}") : "Bab {$hs2}";

                $isMigas = false;
                $sektor = 'UNMAPPED';
                $becDesc = 'UNMAPPED';
                $commDesc = $hs2Desc;

                if ($hsObj) {
                    $oilgrp = strtoupper(trim((string)$hsObj->oilgrp_desc));
                    $isMigas = ($oilgrp === 'MIGAS' || $hsObj->hs2 === '27');
                    $sektor = $hsObj->sektor ?: ($isMigas ? 'MIGAS' : 'LAINNYA');
                    $becDesc = $hsObj->bec_desc ?: 'Bahan Baku/Penolong';
                    $commDesc = $hsObj->comm_desc ?: $hs2Desc;
                }

                if ($isMigas) {
                    $migasNilaiUsd = bcadd($migasNilaiUsd, $nilaiStr, 6);
                    $migasBeratKg = bcadd($migasBeratKg, $beratStr, 6);
                } else {
                    $nonMigasNilaiUsd = bcadd($nonMigasNilaiUsd, $nilaiStr, 6);
                    $nonMigasBeratKg = bcadd($nonMigasBeratKg, $beratStr, 6);
                }

                // Normalisasi Sektor
                $sNorm = 'UNMAPPED';
                if (!$isUnmappedHs) {
                    if ($isMigas) {
                        $sNorm = 'MIGAS';
                    } else {
                        $sUpper = strtoupper(trim((string)$sektor));
                        if (str_contains($sUpper, 'INDUSTRI')) {
                            $sNorm = 'INDUSTRI PENGOLAHAN';
                        } elseif (str_contains($sUpper, 'TANI')) {
                            $sNorm = 'PERTANIAN';
                        } elseif (str_contains($sUpper, 'TAMBANG')) {
                            $sNorm = 'PERTAMBANGAN';
                        } else {
                            $sNorm = 'INDUSTRI PENGOLAHAN';
                        }
                    }
                }
                $sektorMap[$sNorm]['usd'] = bcadd($sektorMap[$sNorm]['usd'], $nilaiStr, 6);
                $sektorMap[$sNorm]['kg'] = bcadd($sektorMap[$sNorm]['kg'], $beratStr, 6);

                // Normalisasi BEC
                $bNorm = 'UNMAPPED';
                if (!$isUnmappedHs) {
                    $bLower = strtolower(trim((string)$becDesc));
                    if (str_contains($bLower, 'baku') || str_contains($bLower, 'penolong')) {
                        $bNorm = 'Bahan Baku/Penolong';
                    } elseif (str_contains($bLower, 'modal')) {
                        $bNorm = 'Barang Modal';
                    } elseif (str_contains($bLower, 'konsumsi')) {
                        $bNorm = 'Barang Konsumsi';
                    } else {
                        $bNorm = 'Bahan Baku/Penolong';
                    }
                }
                $becMap[$bNorm]['usd'] = bcadd($becMap[$bNorm]['usd'], $nilaiStr, 6);
                $becMap[$bNorm]['kg'] = bcadd($becMap[$bNorm]['kg'], $beratStr, 6);

                // Peta HS2
                if (!isset($hs2Map[$hs2])) {
                    $hs2Map[$hs2] = ['code' => $hs2, 'desc' => $hs2Desc, 'usd' => '0.000000', 'kg' => '0.000000'];
                }
                $hs2Map[$hs2]['usd'] = bcadd($hs2Map[$hs2]['usd'], $nilaiStr, 6);
                $hs2Map[$hs2]['kg'] = bcadd($hs2Map[$hs2]['kg'], $beratStr, 6);

                // Peta Negara
                $negObj = $negId && isset($masterNegaraObj[$negId]) ? $masterNegaraObj[$negId] : null;
                $negNama = $negObj ? $negObj->nama_negara : ($negIso ?: $negKode);
                if (!isset($negaraMap[$negNama])) {
                    $negaraMap[$negNama] = ['item' => $negNama, 'usd' => '0.000000', 'kg' => '0.000000'];
                }
                $negaraMap[$negNama]['usd'] = bcadd($negaraMap[$negNama]['usd'], $nilaiStr, 6);
                $negaraMap[$negNama]['kg'] = bcadd($negaraMap[$negNama]['kg'], $beratStr, 6);

                // Peta Pelabuhan
                if (!isset($pelabuhanMap[$pelNama])) {
                    $pelabuhanMap[$pelNama] = ['item' => $pelNama, 'usd' => '0.000000', 'kg' => '0.000000'];
                }
                $pelabuhanMap[$pelNama]['usd'] = bcadd($pelabuhanMap[$pelNama]['usd'], $nilaiStr, 6);
                $pelabuhanMap[$pelNama]['kg'] = bcadd($pelabuhanMap[$pelNama]['kg'], $beratStr, 6);

                // Peta Kabkota
                if ($provOrig !== '' && isset($masterWilayahBps[$provOrig])) {
                    $kabNama = $masterWilayahBps[$provOrig];
                    if (!isset($kabkotaMap[$kabNama])) {
                        $kabkotaMap[$kabNama] = ['kabkota' => $kabNama, 'usd' => '0.000000', 'kg' => '0.000000'];
                    }
                    $kabkotaMap[$kabNama]['usd'] = bcadd($kabkotaMap[$kabNama]['usd'], $nilaiStr, 6);
                    $kabkotaMap[$kabNama]['kg'] = bcadd($kabkotaMap[$kabNama]['kg'], $beratStr, 6);
                }

                // Row Enriched untuk storage / sampling
                $kabkotaId = null;
                if ($provOrig !== '' && isset($masterKabkota[$provOrig])) {
                    $kabkotaId = $masterKabkota[$provOrig];
                }

                $enrichedRow = [
                    'hs_id' => $hsId,
                    'hs_code' => $hsCode,
                    'hs_code_raw' => $hsCode,
                    'mapping_status' => $isUnmappedHs ? 'unmapped' : 'mapped',
                    'negara_id' => $negId,
                    'provinsi_id' => 4, // Riau
                    'kabkota_id' => $kabkotaId,
                    'provorig' => $provOrig ?: null,
                    'pelabuhan_id' => $pelId,
                    'pelabuhan_name' => $pelNama,
                    'negara_name' => $negNama,
                    'jenis_transaksi' => ucfirst($transactionType),
                    'tipe_sumber' => $sourceType,
                    'bulan' => $bulan,
                    'tahun' => $tahun,
                    'berat' => $beratStr,
                    'nilai' => $nilaiStr,
                    'is_migas' => $isMigas,
                    'sektor' => $sNorm,
                    'bec_desc' => $bNorm,
                    'comm_desc' => $commDesc,
                    'kode_kppbc' => $rawRow['kode_kppbc'] ?? ($rawRow['kppbc'] ?? null),
                    'nama_kppbc' => $rawRow['nama_kppbc'] ?? null,
                ];

                $enrichedRows[] = $enrichedRow;

                // Sample Preview
                if (count($previewSample) < $sampleLimit) {
                    $previewSample[] = [
                        'baris' => $totalRows,
                        'hs' => $hsCode,
                        'komoditas' => $commDesc,
                        'mapping_status' => $isUnmappedHs ? 'unmapped' : 'mapped',
                        'negara' => $negNama,
                        'pelabuhan' => $pelNama,
                        'sektor' => $sNorm,
                        'bec' => $bNorm,
                        'is_migas' => $isMigas ? 'MIGAS' : 'NON MIGAS',
                        'nilai_usd' => $nilaiStr,
                        'berat_kg' => $beratStr,
                    ];
                }
            } else {
                $invalidRows++;
                if (count($detailedErrors) < 30) {
                    $detailedErrors[] = [
                        'baris' => $totalRows,
                        'alasan' => implode('; ', $rowErrors),
                        'raw' => $rawRow['raw'] ?? [],
                    ];
                }
            }
        }

        // Susun Top 10 HS2
        uasort($hs2Map, function ($a, $b) {
            return bccomp($b['usd'], $a['usd'], 6);
        });
        $topHs2List = [];
        $hs2Rank = 0;
        foreach ($hs2Map as $h) {
            $hs2Rank++;
            $topHs2List[] = [
                'rank' => $hs2Rank,
                'code' => $h['code'],
                'desc' => $h['desc'],
                'nilai_usd' => $h['usd'],
                'nilai_juta_usd' => round((float)bcdiv($h['usd'], '1000000', 6), 2),
                'volume_ribu_ton' => round((float)bcdiv($h['kg'], '1000000', 6), 2),
                'share_pct' => (bccomp($totalNilaiUsd, '0', 6) > 0)
                    ? round((float)bcmul(bcdiv($h['usd'], $totalNilaiUsd, 6), '100', 2), 2)
                    : 0.0,
            ];
            if ($hs2Rank >= 10) break;
        }

        // Susun Top 10 Negara
        uasort($negaraMap, function ($a, $b) {
            return bccomp($b['usd'], $a['usd'], 6);
        });
        $topNegaraList = [];
        $negRank = 0;
        foreach ($negaraMap as $n) {
            $negRank++;
            $topNegaraList[] = [
                'rank' => $negRank,
                'item' => $n['item'],
                'nilai_usd' => $n['usd'],
                'nilai_juta_usd' => round((float)bcdiv($n['usd'], '1000000', 6), 2),
                'volume_ribu_ton' => round((float)bcdiv($n['kg'], '1000000', 6), 2),
                'share_pct' => (bccomp($totalNilaiUsd, '0', 6) > 0)
                    ? round((float)bcmul(bcdiv($n['usd'], $totalNilaiUsd, 6), '100', 2), 2)
                    : 0.0,
            ];
            if ($negRank >= 10) break;
        }

        // Susun Top Pelabuhan
        uasort($pelabuhanMap, function ($a, $b) {
            return bccomp($b['usd'], $a['usd'], 6);
        });
        $topPelabuhanList = [];
        $pelRank = 0;
        foreach ($pelabuhanMap as $p) {
            $pelRank++;
            $topPelabuhanList[] = [
                'rank' => $pelRank,
                'item' => $p['item'],
                'nilai_usd' => $p['usd'],
                'nilai_juta_usd' => round((float)bcdiv($p['usd'], '1000000', 6), 2),
                'volume_ribu_ton' => round((float)bcdiv($p['kg'], '1000000', 6), 2),
                'share_pct' => (bccomp($totalNilaiUsd, '0', 6) > 0)
                    ? round((float)bcmul(bcdiv($p['usd'], $totalNilaiUsd, 6), '100', 2), 2)
                    : 0.0,
            ];
            if ($pelRank >= 10) break;
        }

        // Susun Top Kab/Kota
        uasort($kabkotaMap, function ($a, $b) {
            return bccomp($b['usd'], $a['usd'], 6);
        });
        $topKabkotaList = [];
        $kkRank = 0;
        foreach ($kabkotaMap as $k) {
            $kkRank++;
            $topKabkotaList[] = [
                'rank' => $kkRank,
                'kabkota' => $k['kabkota'],
                'fob' => (float)$k['usd'],
                'netto' => (float)$k['kg'],
                'nilai_usd' => $k['usd'],
                'nilai_juta_usd' => round((float)bcdiv($k['usd'], '1000000', 6), 2),
                'volume_ribu_ton' => round((float)bcdiv($k['kg'], '1000000', 6), 2),
            ];
            if ($kkRank >= 10) break;
        }

        // Format Rekap Sektor
        $sektorSummary = [];
        foreach ($sektorMap as $sName => $sVal) {
            $denom = ($sName === 'MIGAS') ? $totalNilaiUsd : $nonMigasNilaiUsd;
            $sektorSummary[$sName] = [
                'nilai_usd' => $sVal['usd'],
                'nilai_juta_usd' => round((float)bcdiv($sVal['usd'], '1000000', 6), 2),
                'volume_ribu_ton' => round((float)bcdiv($sVal['kg'], '1000000', 6), 2),
                'share_pct' => (bccomp($denom, '0', 6) > 0)
                    ? round((float)bcmul(bcdiv($sVal['usd'], $denom, 6), '100', 2), 2)
                    : 0.0,
            ];
        }

        // Format Rekap BEC
        $becSummary = [];
        foreach ($becMap as $bName => $bVal) {
            $becSummary[$bName] = [
                'nilai_usd' => $bVal['usd'],
                'nilai_juta_usd' => round((float)bcdiv($bVal['usd'], '1000000', 6), 2),
                'volume_ribu_ton' => round((float)bcdiv($bVal['kg'], '1000000', 6), 2),
                'share_pct' => (bccomp($totalNilaiUsd, '0', 6) > 0)
                    ? round((float)bcmul(bcdiv($bVal['usd'], $totalNilaiUsd, 6), '100', 2), 2)
                    : 0.0,
            ];
        }

        return [
            'meta' => [
                'nama_file' => $originalFilename,
                'sumber_data' => $sourceType,
                'jenis_transaksi' => $transactionType,
                'tahun' => $tahun,
                'bulan' => $bulan,
                'total_baris' => $totalRows,
                'baris_valid' => $validRows,
                'baris_invalid' => $invalidRows,
                'is_existing_batch' => ($existingBatch !== null),
                'existing_batch_id' => $existingBatch ? $existingBatch->batch_id : null,
            ],
            'kpi' => [
                'total_nilai_usd' => $totalNilaiUsd,
                'total_berat_kg' => $totalBeratKg,
                'total_nilai_juta_usd' => round((float)bcdiv($totalNilaiUsd, '1000000', 6), 2),
                'total_berat_ribu_ton' => round((float)bcdiv($totalBeratKg, '1000000', 6), 2),
                'migas_nilai_usd' => $migasNilaiUsd,
                'migas_berat_kg' => $migasBeratKg,
                'migas_juta_usd' => round((float)bcdiv($migasNilaiUsd, '1000000', 6), 2),
                'non_migas_nilai_usd' => $nonMigasNilaiUsd,
                'non_migas_berat_kg' => $nonMigasBeratKg,
                'non_migas_juta_usd' => round((float)bcdiv($nonMigasNilaiUsd, '1000000', 6), 2),
            ],
            'unmapped' => [
                'count' => $unmappedTotalRows,
                'total_nilai_usd' => $unmappedTotalNilai,
                'total_nilai_juta_usd' => round((float)bcdiv($unmappedTotalNilai, '1000000', 6), 2),
                'codes' => $unmappedHsCodes,
            ],
            'sektor_summary' => $sektorSummary,
            'bec_summary' => $becSummary,
            'top_hs2' => $topHs2List,
            'top_negara' => $topNegaraList,
            'top_pelabuhan' => $topPelabuhanList,
            'top_kabkota' => $topKabkotaList,
            'sample_rows' => $previewSample,
            'errors' => $detailedErrors,
            'enriched_rows' => $enrichedRows,
        ];
    }

    /**
     * Menghasilkan data preview untuk antarmuka web.
     */
    public function generatePreview(
        string $filePath,
        string $originalFilename,
        string $sourceType,
        string $transactionType,
        int $sampleLimit = 15
    ): array {
        $result = $this->validateAndEnrich($filePath, $originalFilename, $sourceType, $transactionType, [
            'sample_limit' => $sampleLimit,
        ]);

        // Hapus enriched_rows dari hasil preview agar hemat memori JSON
        unset($result['enriched_rows']);

        return $result;
    }

    /**
     * Menyimpan batch dan transaksi secara atomik dan menghitung agregasi BRS.
     */
    public function storeBatchAndTransactions(
        string $sourceTempPath,
        string $originalFilename,
        string $sourceType,
        string $transactionType,
        int $userId,
        string $targetStatus = 'PRIVATE'
    ): array {
        $sourceType = strtolower(trim($sourceType));
        $transactionType = strtolower(trim($transactionType));
        $targetStatus = in_array(strtoupper(trim($targetStatus)), ['PRIVATE', 'RILIS'], true)
            ? strtoupper(trim($targetStatus))
            : 'PRIVATE';

        // 1. Staging Terisolasi
        $stagingUuid = Str::uuid()->toString();
        $stagingRelativePath = "temp_staging/stage_{$stagingUuid}.dbf";

        $sourceContent = null;
        if (file_exists($sourceTempPath)) {
            $sourceContent = file_get_contents($sourceTempPath);
        } elseif (Storage::disk('local')->exists($sourceTempPath)) {
            $sourceContent = Storage::disk('local')->get($sourceTempPath);
        } else {
            throw new Exception("Berkas sumber tidak ditemukan pada path: {$sourceTempPath}");
        }

        Storage::disk('local')->put($stagingRelativePath, $sourceContent);
        $stagingFullPath = Storage::disk('local')->path($stagingRelativePath);

        $fileHash = hash_file('sha256', $stagingFullPath);
        $fileSizeMb = round(filesize($stagingFullPath) / (1024 * 1024), 2);

        try {
            // 2. Jalankan validasi dan transformasi lengkap
            $processed = $this->validateAndEnrich($stagingFullPath, $originalFilename, $sourceType, $transactionType);

            if ($processed['meta']['total_baris'] === 0) {
                throw new Exception("Berkas DBF kosong (0 transaksi). Penyimpanan dibatalkan.");
            }

            if ($sourceType === 'distribusi' && $processed['meta']['baris_invalid'] > 0) {
                throw new Exception("Terdapat {$processed['meta']['baris_invalid']} baris tidak valid pada berkas Distribusi. Berkas ditolak.");
            }

            if ($processed['meta']['is_existing_batch']) {
                throw new Exception("Periode {$transactionType} {$sourceType} Bulan {$processed['meta']['bulan']}/{$processed['meta']['tahun']} sudah pernah tersimpan / sudah ada di sistem (Batch #{$processed['meta']['existing_batch_id']}). Data periode yang sudah ada tidak boleh ditimpa.");
            }

            $tahun = $processed['meta']['tahun'];
            $bulan = $processed['meta']['bulan'];
            $targetTable = ($sourceType === 'distribusi') ? 'distribusi_transactions' : 'neraca_transactions';

            $batchId = null;
            $now = now();

            // 3. ATOMIC STORAGE TRANSACTION
            DB::beginTransaction();

            try {
                $batchId = DB::table('data_batches')->insertGetId([
                    'nama_file' => $originalFilename,
                    'jenis_data' => ucfirst($transactionType),
                    'periode_tahun' => $tahun,
                    'periode_bulan' => $bulan,
                    'tipe_sumber' => $sourceType,
                    'ukuran_file' => (string)$fileSizeMb,
                    'status' => 'DRAFT', // status sementara sebelum file permanen diverifikasi
                    'uploaded_by' => $userId,
                    'uploaded_at' => $now,
                    'released_by' => null,
                    'released_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // Insert transactions per chunk 500 baris
                $chunk = [];
                foreach ($processed['enriched_rows'] as $r) {
                    if ($sourceType === 'distribusi') {
                        $chunk[] = [
                            'batch_id' => $batchId,
                            'hs_id' => $r['hs_id'],
                            'negara_id' => $r['negara_id'],
                            'provinsi_id' => $r['provinsi_id'],
                            'kabkota_id' => $r['kabkota_id'],
                            'provorig' => $r['provorig'],
                            'pelabuhan_id' => $r['pelabuhan_id'],
                            'jenis_transaksi' => $r['jenis_transaksi'],
                            'tipe_sumber' => 'distribusi',
                            'bulan' => $r['bulan'],
                            'tahun' => $r['tahun'],
                            'berat' => $r['berat'],
                            'nilai' => $r['nilai'],
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    } else {
                        $chunk[] = [
                            'batch_id' => $batchId,
                            'hs_id' => $r['hs_id'],
                            'hs_code_raw' => $r['hs_code_raw'],
                            'hs_desc_raw' => $r['comm_desc'],
                            'mapping_status' => $r['mapping_status'],
                            'negara_id' => $r['negara_id'],
                            'provinsi_id' => $r['provinsi_id'],
                            'kabkota_id' => $r['kabkota_id'],
                            'pelabuhan_id' => $r['pelabuhan_id'],
                            'kode_kppbc' => $r['kode_kppbc'],
                            'nama_kppbc' => $r['nama_kppbc'],
                            'jenis_transaksi' => $r['jenis_transaksi'],
                            'tipe_sumber' => 'neraca',
                            'bulan' => $r['bulan'],
                            'tahun' => $r['tahun'],
                            'berat' => $r['berat'],
                            'nilai' => $r['nilai'],
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    if (count($chunk) >= 500) {
                        DB::table($targetTable)->insert($chunk);
                        $chunk = [];
                    }
                }

                if (!empty($chunk)) {
                    DB::table($targetTable)->insert($chunk);
                }

                // Verifikasi integritas jumlah baris
                $savedCount = DB::table($targetTable)->where('batch_id', $batchId)->count();
                if ($savedCount !== $processed['meta']['baris_valid']) {
                    throw new Exception("Inkonsistensi jumlah baris: tercatat {$processed['meta']['baris_valid']} valid, tetapi tersimpan di tabel {$savedCount} baris.");
                }

                DB::commit();
            } catch (\Throwable $txErr) {
                DB::rollBack();
                throw $txErr;
            }

            // 4. PEMINDAHAN BERKAS FISIK KE ARSIP PERMANEN
            $permDir = "uploads/raw_{$sourceType}";
            $timestampPrefix = time();
            $safeOriginal = str_replace('..', '_', basename((string) $originalFilename));
            $safeOriginal = preg_replace('/[^a-zA-Z0-9._\-\s()]/', '_', $safeOriginal) ?: 'upload.dbf';
            $permFilename = "{$timestampPrefix}_{$safeOriginal}";
            $permRelativePath = "{$permDir}/{$permFilename}";

            $moveSuccess = Storage::disk('local')->put(
                $permRelativePath,
                Storage::disk('local')->get($stagingRelativePath)
            );
            $permFullPath = Storage::disk('local')->path($permRelativePath);

            if (!$moveSuccess || !file_exists($permFullPath) || filesize($permFullPath) === 0) {
                // KOMPENSASI: Hapus transaksi dan batch jika arsip gagal
                Log::error("BrsDataProcessingService: Arsip permanen gagal untuk Batch #{$batchId}. Rollback kompensasi.");
                DB::table($targetTable)->where('batch_id', $batchId)->delete();
                DB::table('data_batches')->where('batch_id', $batchId)->delete();
                throw new Exception("Gagal mengarsipkan berkas DBF permanen. Transaksi dibatalkan secara aman.");
            }

            // 5. FINALISASI STATUS BATCH
            $releasedBy = ($targetStatus === 'RILIS') ? $userId : null;
            $releasedAt = ($targetStatus === 'RILIS') ? now() : null;

            DB::table('data_batches')->where('batch_id', $batchId)->update([
                'nama_file' => $permFilename,
                'status' => $targetStatus,
                'released_by' => $releasedBy,
                'released_at' => $releasedAt,
                'updated_at' => now(),
            ]);

            // 6. TRIGGER OTOMATIS AGREGASI DUA TINGKAT
            $aggResult = $this->aggregationService->aggregateBatch($batchId, $targetStatus);

            // 7. CATAT LOG AKTIVITAS
            try {
                DB::table('activity_logs')->insert([
                    'user_id' => $userId,
                    'aktivitas' => "Processing DBF BRS " . ucfirst($sourceType),
                    'modul' => 'Data Processing Engine',
                    'deskripsi' => "Berhasil memproses & mengagregasi {$savedCount} transaksi {$transactionType} {$sourceType} periode {$bulan}/{$tahun} (Batch #{$batchId}, Status: {$targetStatus}).",
                    'ip_address' => request()->ip() ?? '127.0.0.1',
                    'created_at' => now(),
                ]);
            } catch (\Throwable $logErr) {
                Log::warning("BrsDataProcessingService: Gagal mencatat log aktivitas: " . $logErr->getMessage());
            }

            return [
                'success' => true,
                'batch_id' => $batchId,
                'sumber_data' => $sourceType,
                'jenis_transaksi' => $transactionType,
                'tahun' => $tahun,
                'bulan' => $bulan,
                'total_baris' => $savedCount,
                'total_nilai_usd' => $processed['kpi']['total_nilai_usd'],
                'total_berat_kg' => $processed['kpi']['total_berat_kg'],
                'total_nilai_juta_usd' => $processed['kpi']['total_nilai_juta_usd'],
                'total_berat_ribu_ton' => $processed['kpi']['total_berat_ribu_ton'],
                'migas_juta_usd' => $processed['kpi']['migas_juta_usd'],
                'non_migas_juta_usd' => $processed['kpi']['non_migas_juta_usd'],
                'status' => $targetStatus,
                'file_hash' => $fileHash,
                'arsip_file' => $permRelativePath,
                'unmapped_count' => $processed['unmapped']['count'],
                'aggregation' => $aggResult,
            ];
        } finally {
            // Hygiene staging
            if (Storage::disk('local')->exists($stagingRelativePath)) {
                Storage::disk('local')->delete($stagingRelativePath);
            }
        }
    }
}
