<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service to process Distribution data uploads (DBF/Excel) for Ekspor & Impor.
 */
class DistributionUploadService
{
    protected ExportDistributionParser $exportParser;
    protected ImportDistributionParser $importParser;
    protected TradeAggregationService $aggregationService;

    public function __construct(
        ExportDistributionParser $exportParser,
        ImportDistributionParser $importParser,
        TradeAggregationService $aggregationService
    ) {
        $this->exportParser = $exportParser;
        $this->importParser = $importParser;
        $this->aggregationService = $aggregationService;
    }

    /**
     * Inspect uploaded file and validate records before saving.
     *
     * @param string $filePath
     * @param string $originalFilename
     * @param string $jenis 'ekspor' or 'impor'
     * @return array
     * @throws Exception
     */
    public function validateAndPreview(string $filePath, string $originalFilename, string $jenis): array
    {
        $jenis = strtolower($jenis);
        $parser = ($jenis === 'ekspor') ? $this->exportParser : $this->importParser;

        $meta = $parser->inspect($filePath, $originalFilename);
        $tahun = $meta['tahun'];
        $bulan = $meta['bulan'];

        // Cek apakah batch periode ini sudah pernah diunggah
        $existingBatch = DB::table('data_batches')
            ->where('tipe_sumber', 'distribusi')
            ->whereRaw('LOWER(jenis_data) = ?', [$jenis])
            ->where('periode_tahun', $tahun)
            ->where('periode_bulan', $bulan)
            ->whereNotIn('batch_id', function ($query) {
                $query->select('batch_id')->from('trash')->whereNull('restored_at');
            })
            ->first();

        // Load master lookups into memory for fast batch validation
        $masterHs = DB::table('master_hs')->pluck('hs_id', 'hs_code')->toArray();
        $masterNegaraKode = DB::table('master_negara')->pluck('negara_id', 'kode_negara')->toArray();
        $masterNegaraIso = DB::table('master_negara')->whereNotNull('nation')->pluck('negara_id', 'nation')->toArray();

        // Master Pelabuhan: check exact 5-digit, UN/LOCODE, and 3-digit suffix
        $allPelabuhans = DB::table('master_pelabuhan')->get();
        $masterWilayahBps = DB::table('master_wilayah_bps')->pluck('nama_wilayah', 'kode_wilayah')->toArray();

        $totalRows = 0;
        $validRows = 0;
        $invalidRows = 0;
        $errors = [];
        $unmappedSummary = [
            'hs' => [],
            'negara' => [],
            'pelabuhan' => [],
            'wilayah' => [],
        ];
        $previewSample = [];
        $totalNilaiUsd = '0.000000';
        $totalBeratKg = '0.000000';

        foreach ($parser->parseStream($filePath, null, 0, $originalFilename) as $row) {
            $totalRows++;
            $rowErrors = [];

            // 1. Validasi HS
            $hsCode = $row['hs_code'];
            $hsId = $masterHs[$hsCode] ?? null;
            if (!$hsId) {
                $rowErrors[] = "Kode HS '{$hsCode}' tidak ditemukan di Master HS.";
                $unmappedSummary['hs'][$hsCode] = ($unmappedSummary['hs'][$hsCode] ?? 0) + 1;
            }

            // 2. Validasi Negara
            $negId = null;
            if (!empty($row['negara_kode']) && isset($masterNegaraKode[$row['negara_kode']])) {
                $negId = $masterNegaraKode[$row['negara_kode']];
            } elseif (!empty($row['negara_iso']) && isset($masterNegaraIso[strtoupper($row['negara_iso'])])) {
                $negId = $masterNegaraIso[strtoupper($row['negara_iso'])];
            }
            if (!$negId) {
                $negKey = ($row['negara_kode'] ?: $row['negara_iso']) ?: 'UNKNOWN';
                $rowErrors[] = "Negara '{$negKey}' tidak ditemukan di Master Negara.";
                $unmappedSummary['negara'][$negKey] = ($unmappedSummary['negara'][$negKey] ?? 0) + 1;
            }

            // 3. Validasi Pelabuhan (Tanpa Fallback Otomatis & Cek Ambiguitas)
            $pelId = null;
            $locode = strtoupper($row['pelabuhan_locode'] ?? '');
            $bpsCode = $row['pelabuhan_bps'] ?? '';

            $matchedCandidates = $allPelabuhans->filter(function ($p) use ($bpsCode, $locode) {
                return ($bpsCode !== '' && $p->kode_pelabuhan === $bpsCode) ||
                       ($locode !== '' && strtoupper($p->un_locode ?? '') === $locode) ||
                       ($bpsCode !== '' && strlen($bpsCode) === 5 && substr($bpsCode, 2) === $p->kode_pelabuhan);
            });

            if ($matchedCandidates->count() === 1) {
                $pelId = $matchedCandidates->first()->pelabuhan_id;
            } elseif ($matchedCandidates->count() > 1) {
                $portKey = ($bpsCode . ' / ' . $locode) ?: 'UNKNOWN';
                $candidateNames = $matchedCandidates->pluck('nama_pelabuhan')->implode(', ');
                $rowErrors[] = "Pelabuhan '{$portKey}' ambigu (ditemukan lebih dari 1 kandidat: {$candidateNames}).";
                $unmappedSummary['pelabuhan'][$portKey] = ($unmappedSummary['pelabuhan'][$portKey] ?? 0) + 1;
            } else {
                $portKey = ($bpsCode . ' / ' . $locode) ?: 'UNKNOWN';
                $rowErrors[] = "Pelabuhan '{$portKey}' belum terpetakan di Master Pelabuhan.";
                $unmappedSummary['pelabuhan'][$portKey] = ($unmappedSummary['pelabuhan'][$portKey] ?? 0) + 1;
            }

            // 4. Validasi Wilayah Asal (PROVORIG untuk Ekspor)
            if ($jenis === 'ekspor' && !empty($row['provorig'])) {
                $origCode = $row['provorig'];
                if (!isset($masterWilayahBps[$origCode])) {
                    $rowErrors[] = "Wilayah asal '{$origCode}' belum terdaftar di Master Wilayah.";
                    $unmappedSummary['wilayah'][$origCode] = ($unmappedSummary['wilayah'][$origCode] ?? 0) + 1;
                }
            }

            // 5. Validasi Nilai & Berat
            if ($row['nilai_usd'] < 0) {
                $rowErrors[] = "Nilai USD tidak boleh negatif ({$row['nilai_usd']}).";
            }
            if ($row['berat_kg'] < 0) {
                $rowErrors[] = "Berat Kg tidak boleh negatif ({$row['berat_kg']}).";
            }

            if (empty($rowErrors)) {
                $validRows++;
                $totalNilaiUsd = bcadd($totalNilaiUsd, (string) $row['nilai_usd'], 6);
                $totalBeratKg = bcadd($totalBeratKg, (string) $row['berat_kg'], 6);
            } else {
                $invalidRows++;
                if (count($errors) < 20) {
                    $errors[] = [
                        'baris' => $totalRows,
                        'alasan' => implode('; ', $rowErrors),
                        'data' => $row['raw']
                    ];
                }
            }

            if (count($previewSample) < 10) {
                $previewSample[] = [
                    'baris' => $totalRows,
                    'hs' => $hsCode,
                    'negara' => $row['negara_iso'] ?: $row['negara_kode'],
                    'pelabuhan' => $row['pelabuhan_locode'] ?: $row['pelabuhan_bps'],
                    'provorig' => $row['provorig'] ?? '-',
                    'nilai_usd' => $row['nilai_usd'],
                    'berat_kg' => $row['berat_kg'],
                    'status' => empty($rowErrors) ? 'VALID' : 'ERROR',
                    'pesan' => implode('; ', $rowErrors)
                ];
            }
        }

        return [
            'nama_file' => $originalFilename,
            'jenis_transaksi' => $jenis,
            'tahun' => $tahun,
            'bulan' => $bulan,
            'total_baris' => $totalRows,
            'baris_valid' => $validRows,
            'baris_invalid' => $invalidRows,
            'total_nilai_usd' => (float) $totalNilaiUsd,
            'total_berat_kg' => (float) $totalBeratKg,
            'total_nilai_usd_exact' => $totalNilaiUsd,
            'total_berat_kg_exact' => $totalBeratKg,
            'total_nilai_juta_usd' => round((float) bcdiv($totalNilaiUsd, '1000000', 6), 2),
            'total_berat_ribu_ton' => round((float) bcdiv($totalBeratKg, '1000000', 6), 2),
            'unmapped' => $unmappedSummary,
            'duplikasi_batch' => $existingBatch ? [
                'batch_id' => $existingBatch->batch_id,
                'nama_file' => $existingBatch->nama_file,
                'uploaded_at' => $existingBatch->uploaded_at,
                'status' => $existingBatch->status
            ] : null,
            'errors' => $errors,
            'preview_sample' => $previewSample
        ];
    }

    /**
     * Store validated distribution data into database with transaction and batch tracking.
     *
     * @param string $filePath
     * @param string $originalFilename
     * @param string $jenis 'ekspor' or 'impor'
     * @param int $userId
     * @param bool $overwriteDuplicate
     * @return array
     * @throws Exception
     */
    public function processAndStore(
        string $filePath,
        string $originalFilename,
        string $jenis,
        int $userId,
        bool $overwriteDuplicate = false,
        string $status = 'RILIS'
    ): array {
        $jenis = strtolower($jenis);
        $preview = $this->validateAndPreview($filePath, $originalFilename, $jenis);

        if ($preview['baris_invalid'] > 0 && $preview['baris_valid'] === 0) {
            throw new Exception("Seluruh data pada file ({$preview['total_baris']} baris) tidak valid atau memiliki kode yang belum terpetakan. Proses penyimpanan dibatalkan.");
        }

        $tahun = $preview['tahun'];
        $bulan = $preview['bulan'];

        return DB::transaction(function () use ($filePath, $originalFilename, $jenis, $userId, $overwriteDuplicate, $preview, $tahun, $bulan, $status) {
            // Check for duplicate batch handling
            if ($preview['duplikasi_batch'] && $overwriteDuplicate) {
                $oldBatchId = $preview['duplikasi_batch']['batch_id'];
                // Delete transactions of old batch
                DB::table('distribusi_transactions')->where('batch_id', $oldBatchId)->delete();
                // Mark old batch as superseded
                DB::table('data_batches')->where('batch_id', $oldBatchId)->update([
                    'status' => 'PRIVATE',
                    'updated_at' => now(),
                ]);
                Log::info("DistributionUploadService: Overwrote existing batch {$oldBatchId} for {$jenis} {$tahun}-{$bulan}");
            } elseif ($preview['duplikasi_batch'] && !$overwriteDuplicate) {
                throw new Exception("Periode {$jenis} bulan {$bulan} tahun {$tahun} sudah pernah diunggah (Batch #{$preview['duplikasi_batch']['batch_id']}). Aktifkan opsi timpa periode jika ingin mengganti.");
            }

            // 1. Create data_batch
            $fileSizeMb = round(filesize($filePath) / (1024 * 1024), 2);
            $batchStatus = in_array(strtoupper($status), ['RILIS', 'PRIVATE', 'DRAFT', 'NON-RILIS']) ? strtoupper($status) : 'PRIVATE';
            $releasedBy = ($batchStatus === 'RILIS') ? $userId : null;
            $releasedAt = ($batchStatus === 'RILIS') ? now() : null;

            $batchId = DB::table('data_batches')->insertGetId([
                'nama_file' => $originalFilename,
                'jenis_data' => ucfirst($jenis),
                'periode_tahun' => $tahun,
                'periode_bulan' => $bulan,
                'tipe_sumber' => 'distribusi',
                'ukuran_file' => (string)$fileSizeMb,
                'status' => $batchStatus,
                'uploaded_by' => $userId,
                'uploaded_at' => now(),
                'released_by' => $releasedBy,
                'released_at' => $releasedAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Master lookup maps
            $masterHs = DB::table('master_hs')->pluck('hs_id', 'hs_code')->toArray();
            $masterNegaraKode = DB::table('master_negara')->pluck('negara_id', 'kode_negara')->toArray();
            $masterNegaraIso = DB::table('master_negara')->whereNotNull('nation')->pluck('negara_id', 'nation')->toArray();
            $allPelabuhans = DB::table('master_pelabuhan')->get();

            // Default Provinsi ID = 4 (Riau)
            $defaultProvId = 4;

            $parser = ($jenis === 'ekspor') ? $this->exportParser : $this->importParser;
            $insertBatch = [];
            $savedCount = 0;
            $now = now();

            foreach ($parser->parseStream($filePath, null, 0, $originalFilename) as $row) {
                $hsId = $masterHs[$row['hs_code']] ?? null;
                if (!$hsId) continue; // skip unmapped HS

                $negId = null;
                if (!empty($row['negara_kode']) && isset($masterNegaraKode[$row['negara_kode']])) {
                    $negId = $masterNegaraKode[$row['negara_kode']];
                } elseif (!empty($row['negara_iso']) && isset($masterNegaraIso[strtoupper($row['negara_iso'])])) {
                    $negId = $masterNegaraIso[strtoupper($row['negara_iso'])];
                }
                if (!$negId) continue; // skip unmapped negara

                // Matched port without wrong fallback & no ambiguity
                $locode = strtoupper($row['pelabuhan_locode'] ?? '');
                $bpsCode = $row['pelabuhan_bps'] ?? '';
                $matchedCandidates = $allPelabuhans->filter(function ($p) use ($bpsCode, $locode) {
                    return ($bpsCode !== '' && $p->kode_pelabuhan === $bpsCode) ||
                           ($locode !== '' && strtoupper($p->un_locode ?? '') === $locode) ||
                           ($bpsCode !== '' && strlen($bpsCode) === 5 && substr($bpsCode, 2) === $p->kode_pelabuhan);
                });

                if ($matchedCandidates->count() !== 1) continue; // skip unmapped or ambiguous port
                $pelId = $matchedCandidates->first()->pelabuhan_id;

                $kabkotaId = null;
                if (!empty($row['provorig'])) {
                    $kab = DB::table('master_kabkota')->where('kode_kabkota', $row['provorig'])->first();
                    if ($kab) {
                        $kabkotaId = $kab->kabkota_id;
                    }
                }

                $insertBatch[] = [
                    'batch_id' => $batchId,
                    'hs_id' => $hsId,
                    'negara_id' => $negId,
                    'provinsi_id' => $defaultProvId,
                    'kabkota_id' => $kabkotaId,
                    'pelabuhan_id' => $pelId,
                    'provorig' => $row['provorig'] ?? null,
                    'jenis_transaksi' => ucfirst($jenis),
                    'tipe_sumber' => 'distribusi',
                    'bulan' => $bulan,
                    'tahun' => $tahun,
                    'berat' => $row['berat_kg'],
                    'nilai' => $row['nilai_usd'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($insertBatch) >= 1000) {
                    DB::table('distribusi_transactions')->insert($insertBatch);
                    $savedCount += count($insertBatch);
                    $insertBatch = [];
                }
            }

            if (!empty($insertBatch)) {
                DB::table('distribusi_transactions')->insert($insertBatch);
                $savedCount += count($insertBatch);
            }

            // Bangun Two-Tier Analytical Mart & Origin Summary secara atomik untuk batch PRIVATE/RILIS
            if (in_array($batchStatus, ['PRIVATE', 'RILIS'], true)) {
                app(BrsAggregationService::class)->aggregateBatch((int) $batchId, $batchStatus);
            }

            // Log activity
            DB::table('activity_logs')->insert([
                'user_id' => $userId,
                'aktivitas' => 'Upload Distribusi',
                'modul' => 'Distribusi',
                'deskripsi' => "Berhasil mengunggah {$savedCount} baris data Distribusi {$jenis} periode {$bulan}/{$tahun} (Batch #{$batchId}).",
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'created_at' => $now,
            ]);

            return [
                'success' => true,
                'batch_id' => $batchId,
                'jenis_transaksi' => $jenis,
                'tahun' => $tahun,
                'bulan' => $bulan,
                'baris_disimpan' => $savedCount,
                'total_fob_cif' => $preview['total_nilai_usd'],
                'total_fob_cif_juta' => $preview['total_nilai_juta_usd'],
            ];
        });
    }
}
