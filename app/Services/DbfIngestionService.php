<?php

namespace App\Services;

use App\Services\Parsers\ExportDistributionDbfParser;
use App\Services\Parsers\ExportNeracaDbfParser;
use App\Services\Parsers\ImportDistributionDbfParser;
use App\Services\Parsers\ImportNeracaDbfParser;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Service terpadu untuk Data Ingestion berkas DBF SIDATEX.
 * Menangani alur 5-Fase:
 * 1. Staging Berkas & Fingerprint SHA-256
 * 2. Inisialisasi Batch DRAFT & Chunk Insert Transaksi (500 baris)
 * 3. Pemindahan Berkas Fisik ke Arsip Permanen
 * 4. Finalisasi Status Batch (PRIVATE / RILIS) & Log Aktivitas (dengan Kompensasi Aman)
 * 5. Pembersihan Staging (Cleanup)
 *
 * Mematuhi Zero Boilerplate, PSR-12, dan Clean Query Optimization.
 */
class DbfIngestionService
{
    protected DbfUploadValidationService $validationService;
    protected TradeAggregationService $aggregationService;

    public function __construct(
        DbfUploadValidationService $validationService,
        TradeAggregationService $aggregationService
    ) {
        $this->validationService = $validationService;
        $this->aggregationService = $aggregationService;
    }

    /**
     * Menjalankan proses ingestion berkas DBF secara aman dan atomik.
     *
     * @param string $sourceTempPath Path berkas temporer yang diunggah
     * @param string $originalFilename Nama berkas asli
     * @param string $sourceType 'distribusi' atau 'neraca'
     * @param string $transactionType 'ekspor' atau 'impor'
     * @param int $userId ID Super Admin
     * @param string $targetStatus 'PRIVATE', 'DRAFT', atau 'RILIS'
     * @param bool $overwriteDuplicate Izin menimpa batch periode yang sama (hanya jika DRAFT/PRIVATE)
     * @return array
     * @throws Exception
     */
    public function ingest(
        string $sourceTempPath,
        string $originalFilename,
        string $sourceType,
        string $transactionType,
        int $userId,
        string $targetStatus = 'PRIVATE',
        bool $overwriteDuplicate = false
    ): array {
        $sourceType = strtolower(trim($sourceType));
        $transactionType = strtolower(trim($transactionType));
        $targetStatus = strtoupper(trim($targetStatus));

        if (!in_array($sourceType, ['distribusi', 'neraca'])) {
            throw new Exception("Sumber data '{$sourceType}' tidak valid. Harus 'distribusi' atau 'neraca'.");
        }
        if (!in_array($transactionType, ['ekspor', 'impor'])) {
            throw new Exception("Jenis transaksi '{$transactionType}' tidak valid. Harus 'ekspor' atau 'impor'.");
        }
        if (!in_array($targetStatus, ['PRIVATE', 'DRAFT', 'RILIS'])) {
            $targetStatus = 'PRIVATE';
        }

        if (!file_exists($sourceTempPath) || !is_readable($sourceTempPath)) {
            throw new Exception("Berkas sumber tidak ditemukan atau tidak dapat dibaca: {$sourceTempPath}");
        }

        // ─────────────────────────────────────────────────────────────────
        // FASE 1: STAGING & FINGERPRINT SHA-256
        // ─────────────────────────────────────────────────────────────────
        $stagingUuid = Str::uuid()->toString();
        $stagingDir = 'temp_staging';
        $stagingFilename = "stage_{$stagingUuid}.dbf";
        $stagingRelativePath = "{$stagingDir}/{$stagingFilename}";

        // Simpan salinan ke direktori staging terisolasi
        Storage::disk('local')->put($stagingRelativePath, file_get_contents($sourceTempPath));
        $stagingFullPath = Storage::disk('local')->path($stagingRelativePath);

        $fileHash = hash_file('sha256', $stagingFullPath);
        $fileSizeMb = round(filesize($stagingFullPath) / (1024 * 1024), 2);

        try {
            // Lakukan validasi terstruktur menyeluruh menggunakan validation service
            $validation = $this->validationService->validateAndPreview(
                $stagingFullPath,
                $originalFilename,
                $sourceType,
                $transactionType,
                5
            );

            if ($validation['total_baris'] === 0) {
                throw new Exception("Berkas DBF kosong (0 record). Proses penyimpanan dibatalkan.");
            }

            // Pada modul Distribusi: Seluruh baris harus valid
            if ($sourceType === 'distribusi' && $validation['baris_invalid'] > 0) {
                throw new Exception("Terdapat {$validation['baris_invalid']} baris tidak valid pada berkas Distribusi. Berkas ditolak.");
            }

            $tahun = (int)$validation['tahun'];
            $bulan = (int)$validation['bulan'];

            // ─────────────────────────────────────────────────────────────────
            // CEK DUPLIKASI PERIODE: TIDAK BOLEH DITIMPA SAMA SEKALI
            // ─────────────────────────────────────────────────────────────────
            $existingBatch = DB::table('data_batches')
                ->where('tipe_sumber', $sourceType)
                ->whereRaw('LOWER(jenis_data) = ?', [$transactionType])
                ->where('periode_tahun', $tahun)
                ->where('periode_bulan', $bulan)
                ->whereNotIn('batch_id', function ($query) {
                    $query->select('batch_id')->from('trash')->whereNull('restored_at');
                })
                ->first();

            if ($existingBatch) {
                throw new Exception("Periode {$transactionType} {$sourceType} Bulan {$bulan}/{$tahun} sudah pernah tersimpan di sistem (Batch #{$existingBatch->batch_id}) dengan status {$existingBatch->status}. Data periode yang sudah ada tidak boleh ditimpa.");
            }

            // ─────────────────────────────────────────────────────────────────
            // FASE 2: INISIALISASI BATCH SEMENTARA (DRAFT) & CHUNK INSERT
            // ─────────────────────────────────────────────────────────────────
            $targetTable = ($sourceType === 'distribusi') ? 'distribusi_transactions' : 'neraca_transactions';
            $batchId = null;
            $savedCount = 0;
            $totalNilaiSum = '0.000000';
            $totalBeratSum = '0.000000';

            // Load master lookup in-memory (Clean Query Optimization)
            $masterHs = DB::table('master_hs')->pluck('hs_id', 'hs_code')->toArray();
            $masterNegaraKode = DB::table('master_negara')->pluck('negara_id', 'kode_negara')->toArray();
            $masterNegaraIso = DB::table('master_negara')->whereNotNull('nation')->pluck('negara_id', 'nation')->toArray();
            $allPelabuhans = DB::table('master_pelabuhan')->get();
            $masterKabkota = DB::table('master_kabkota')->pluck('kabkota_id', 'kode_kabkota')->toArray();

            $parser = $this->validationService->resolveParser($sourceType, $transactionType);
            $now = now();

            DB::beginTransaction();

            try {
                // Buat batch baru dengan status awal DRAFT (terisolasi mutlak dari dashboard)
                $batchId = DB::table('data_batches')->insertGetId([
                    'nama_file' => $originalFilename,
                    'jenis_data' => ucfirst($transactionType),
                    'periode_tahun' => $tahun,
                    'periode_bulan' => $bulan,
                    'tipe_sumber' => $sourceType,
                    'ukuran_file' => (string)$fileSizeMb,
                    'status' => 'DRAFT',
                    'uploaded_by' => $userId,
                    'uploaded_at' => $now,
                    'released_by' => null,
                    'released_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $chunk = [];
                foreach ($parser->parseStream($stagingFullPath, null, 0, $originalFilename) as $row) {
                    // Petakan Master Data
                    $hsCode = $row['hs_code'] ?? '';
                    $hsId = $masterHs[$hsCode] ?? null;

                    $negKode = $row['negara_kode'] ?? '';
                    $negIso = strtoupper($row['negara_iso'] ?? '');
                    $negId = $masterNegaraKode[$negKode] ?? ($masterNegaraIso[$negIso] ?? null);

                    $locode = strtoupper($row['pelabuhan_locode'] ?? '');
                    $bpsCode = $row['pelabuhan_bps'] ?? '';
                    $matchedPort = $allPelabuhans->first(function ($p) use ($bpsCode, $locode) {
                        return ($bpsCode !== '' && $p->kode_pelabuhan === $bpsCode) ||
                               ($locode !== '' && strtoupper((string)$p->un_locode) === $locode) ||
                               ($bpsCode !== '' && strlen($bpsCode) === 5 && substr($bpsCode, 2) === $p->kode_pelabuhan);
                    });
                    $pelId = $matchedPort ? $matchedPort->pelabuhan_id : null;

                    $kabkotaId = null;
                    $origCode = trim((string)($row['provorig'] ?? ''));
                    if ($origCode !== '' && isset($masterKabkota[$origCode])) {
                        $kabkotaId = $masterKabkota[$origCode];
                    }

                    $nilaiStr = (string)$row['nilai_usd'];
                    $beratStr = (string)$row['berat_kg'];

                    if ($sourceType === 'distribusi') {
                        $chunk[] = [
                            'batch_id' => $batchId,
                            'hs_id' => $hsId,
                            'negara_id' => $negId,
                            'provinsi_id' => 4, // Riau
                            'kabkota_id' => $kabkotaId,
                            'provorig' => $origCode ?: null,
                            'pelabuhan_id' => $pelId,
                            'jenis_transaksi' => ucfirst($transactionType),
                            'tipe_sumber' => 'distribusi',
                            'bulan' => $bulan,
                            'tahun' => $tahun,
                            'berat' => $beratStr,
                            'nilai' => $nilaiStr,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    } else {
                        // Modul Neraca
                        $isUnmapped = ($hsId === null);
                        $chunk[] = [
                            'batch_id' => $batchId,
                            'hs_id' => $hsId,
                            'hs_code_raw' => $row['hs_code_raw'] ?? $hsCode,
                            'hs_desc_raw' => null,
                            'mapping_status' => $isUnmapped ? 'unmapped' : 'mapped',
                            'negara_id' => $negId,
                            'provinsi_id' => 4, // Riau
                            'kabkota_id' => $kabkotaId,
                            'pelabuhan_id' => $pelId,
                            'kode_kppbc' => $row['kode_kppbc'] ?? null,
                            'nama_kppbc' => $row['nama_kppbc'] ?? null,
                            'jenis_transaksi' => ucfirst($transactionType),
                            'tipe_sumber' => 'neraca',
                            'bulan' => $bulan,
                            'tahun' => $tahun,
                            'berat' => $beratStr,
                            'nilai' => $nilaiStr,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    $savedCount++;
                    $totalNilaiSum = bcadd($totalNilaiSum, $nilaiStr, 6);
                    $totalBeratSum = bcadd($totalBeratSum, $beratStr, 6);

                    // Insert per chunk 500 baris
                    if (count($chunk) >= 500) {
                        DB::table($targetTable)->insert($chunk);
                        $chunk = [];
                    }
                }

                // Sisa chunk terakhir
                if (!empty($chunk)) {
                    DB::table($targetTable)->insert($chunk);
                }

                // Verifikasi integritas jumlah baris tersimpan
                $insertedRows = DB::table($targetTable)->where('batch_id', $batchId)->count();
                if ($insertedRows !== $savedCount) {
                    throw new Exception("Inkonsistensi jumlah baris: tercatat {$savedCount} baris, tetapi tersimpan di tabel {$insertedRows} baris.");
                }

                DB::commit();
            } catch (\Throwable $dbException) {
                DB::rollBack();
                throw $dbException;
            }

            // ─────────────────────────────────────────────────────────────────
            // FASE 3: PEMINDAHAN BERKAS FISIK KE ARSIP PERMANEN
            // ─────────────────────────────────────────────────────────────────
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

            // ─────────────────────────────────────────────────────────────────
            // FASE 4: FINALISASI STATUS BATCH & LOG AKTIVITAS (DENGAN KOMPENSASI)
            // ─────────────────────────────────────────────────────────────────
            if (!$moveSuccess || !file_exists($permFullPath) || filesize($permFullPath) === 0) {
                // KOMPENSASI AMAN: Hapus transaksi dan batch jika pemindahan berkas gagal
                Log::error("DbfIngestionService: Pemindahan berkas permanen gagal untuk Batch #{$batchId}. Mengeksekusi kompensasi rollback.");
                DB::table($targetTable)->where('batch_id', $batchId)->delete();
                DB::table('data_batches')->where('batch_id', $batchId)->delete();
                throw new Exception("Gagal memindahkan berkas fisik ke arsip permanen. Transaksi database telah dibatalkan secara aman.");
            }

            // Pemindahan berkas berhasil: Finalisasi status batch dan sinkronisasi Two-Tier Mart secara atomik
            $releasedBy = ($targetStatus === 'RILIS') ? $userId : null;
            $releasedAt = ($targetStatus === 'RILIS') ? now() : null;

            DB::transaction(function () use ($batchId, $permFilename, $targetStatus, $releasedBy, $releasedAt) {
                DB::table('data_batches')->where('batch_id', $batchId)->update([
                    'nama_file' => $permFilename,
                    'status' => $targetStatus,
                    'released_by' => $releasedBy,
                    'released_at' => $releasedAt,
                    'updated_at' => now(),
                ]);

                // Bangun Two-Tier Analytical Mart untuk Distribusi maupun Neraca saat status PRIVATE atau RILIS
                if (in_array($targetStatus, ['PRIVATE', 'RILIS'], true)) {
                    app(BrsAggregationService::class)->aggregateBatch((int) $batchId, $targetStatus);
                }
            });

            // Catat log aktivitas (terisolasi)
            try {
                DB::table('activity_logs')->insert([
                    'user_id' => $userId,
                    'aktivitas' => "Ingestion DBF " . ucfirst($sourceType),
                    'modul' => 'Upload Data',
                    'deskripsi' => "Berhasil menyimpan {$savedCount} transaksi {$transactionType} {$sourceType} periode {$bulan}/{$tahun} ke tabel {$targetTable} (Batch #{$batchId}, Status: {$targetStatus}).",
                    'ip_address' => request()->ip() ?? '127.0.0.1',
                    'created_at' => now(),
                ]);
            } catch (\Throwable $logErr) {
                Log::warning("DbfIngestionService: Gagal mencatat log aktivitas: " . $logErr->getMessage());
            }

            return [
                'success' => true,
                'batch_id' => $batchId,
                'sumber_data' => $sourceType,
                'jenis_transaksi' => $transactionType,
                'tahun' => $tahun,
                'bulan' => $bulan,
                'total_baris' => $savedCount,
                'total_nilai_usd' => $totalNilaiSum,
                'total_berat_kg' => $totalBeratSum,
                'total_nilai_juta_usd' => round((float)$totalNilaiSum / 1000000, 2),
                'total_berat_ribu_ton' => round((float)$totalBeratSum / 1000000, 2),
                'status' => $targetStatus,
                'file_hash' => $fileHash,
                'arsip_file' => $permRelativePath,
            ];
        } finally {
            // ─────────────────────────────────────────────────────────────────
            // FASE 5: CLEANUP & HYGIENE (Selalu Bersihkan Staging)
            // ─────────────────────────────────────────────────────────────────
            if (Storage::disk('local')->exists($stagingRelativePath)) {
                Storage::disk('local')->delete($stagingRelativePath);
            }
        }
    }
}
