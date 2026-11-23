<?php

namespace Tests\Feature;

use App\Services\NeracaUploadService;
use Exception;
use Tests\TestCase;

/**
 * Test Suite Terisolasi untuk NeracaUploadService (Tahap 2P).
 * Memverifikasi kemampuan parsing XLSX streaming, validasi header, normalisasi periode,
 * dan presisi desimal tanpa modifikasi database operasional/staging.
 */
class NeracaUploadServiceTest extends TestCase
{
    protected NeracaUploadService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new NeracaUploadService();
    }

    /**
     * Uji parser membaca file Ekspor BRS asli secara streaming dan menghasilkan preview presisi.
     */
    public function test_ekspor_parser_reads_rawdata_ekspor_worksheet_precisely(): void
    {
        $filePath = base_path('referensi_bps/1. (Olah Data BRS) Ekspor LN Riau.xlsx');
        $this->assertFileExists($filePath);

        $preview = $this->service->generatePreview($filePath, 'ekspor', 5);

        $this->assertTrue($preview['success']);
        $this->assertEquals('ekspor', $preview['source_type']);
        $this->assertEquals('RawDataEkspor', $preview['worksheet']);
        $this->assertEquals(53066, $preview['valid_rows_count']);
        $this->assertEquals(0, $preview['invalid_rows_count']);

        // Presisi total nilai sesuai pembuktian rekonsiliasi Tahap 2P
        $this->assertEquals('127069371387.58', $preview['totals']['total_nilai']);
        $this->assertCount(7, $preview['periods']['years_available']); // 2020 s.d 2026
        $this->assertEquals(79, $preview['periods']['total_months']);
    }

    /**
     * Uji parser membaca file Impor BRS asli secara streaming.
     */
    public function test_impor_parser_reads_rawdata_impor_worksheet_precisely(): void
    {
        $filePath = base_path('referensi_bps/2. (Olah Data BRS) Impor LN Riau.xlsx');
        $this->assertFileExists($filePath);

        $preview = $this->service->generatePreview($filePath, 'impor', 5);

        $this->assertTrue($preview['success']);
        $this->assertEquals('impor', $preview['source_type']);
        $this->assertEquals('RawDataImpor', $preview['worksheet']);
        $this->assertEquals(92820, $preview['valid_rows_count']);
        $this->assertEquals(0, $preview['invalid_rows_count']);

        $this->assertCount(8, $preview['periods']['years_available']); // 2019 s.d 2026
        $this->assertEquals(92, $preview['periods']['total_months']);
    }

    /**
     * Uji normalisasi representasi tahun 2-digit dan index BPS.
     */
    public function test_year_normalization_handles_various_formats(): void
    {
        $this->assertEquals(2020, $this->service->normalizeYear('20'));
        $this->assertEquals(2026, $this->service->normalizeYear('26'));
        $this->assertEquals(2019, $this->service->normalizeYear('19'));
        $this->assertEquals(2025, $this->service->normalizeYear('2025'));

        // Shared string index BPS
        $this->assertEquals(2020, $this->service->normalizeYear('216'));
        $this->assertEquals(2026, $this->service->normalizeYear('265'));
        $this->assertEquals(2019, $this->service->normalizeYear('207'));

        $this->assertNull($this->service->normalizeYear(''));
        $this->assertNull($this->service->normalizeYear('abc'));
    }

    /**
     * Uji normalisasi representasi bulan.
     */
    public function test_month_normalization_handles_various_formats(): void
    {
        $this->assertEquals(1, $this->service->normalizeMonth('01'));
        $this->assertEquals(12, $this->service->normalizeMonth('12'));
        $this->assertEquals(7, $this->service->normalizeMonth('7'));

        // Shared string index BPS
        $this->assertEquals(1, $this->service->normalizeMonth('23'));
        $this->assertEquals(7, $this->service->normalizeMonth('92'));

        $this->assertNull($this->service->normalizeMonth(''));
        $this->assertNull($this->service->normalizeMonth('13'));
    }

    /**
     * Uji evaluasi neraca pada periode asimetris dan periode lengkap.
     */
    public function test_trade_balance_asymmetric_and_complete_periods(): void
    {
        // 1. Tahun 2019: hanya Impor
        $res2019 = $this->service->evaluateTradeBalanceStatus(2019, null, false, true, 0, 1437967188);
        $this->assertFalse($res2019['is_complete']);
        $this->assertEquals('Data Ekspor Belum Tersedia', $res2019['status']);
        $this->assertNull($res2019['neraca_perdagangan']);

        // 2. Agustus 2026: hanya Impor
        $resAgu26 = $this->service->evaluateTradeBalanceStatus(2026, 8, false, true, 0, 2242059712);
        $this->assertFalse($resAgu26['is_complete']);
        $this->assertEquals('Data Ekspor Belum Tersedia', $resAgu26['status']);
        $this->assertNull($resAgu26['neraca_perdagangan']);

        // 3. Juli 2026: Lengkap
        $resJul26 = $this->service->evaluateTradeBalanceStatus(2026, 7, true, true, 1910605445.02, 199716065.41);
        $this->assertTrue($resJul26['is_complete']);
        $this->assertEquals('Surplus', $resJul26['status']);
        $this->assertEquals(1710889379.61, $resJul26['neraca_perdagangan']);

        // 4. Keduanya belum ada
        $resEmpty = $this->service->evaluateTradeBalanceStatus(2099, null, false, false);
        $this->assertFalse($resEmpty['is_complete']);
        $this->assertEquals('Data Belum Tersedia', $resEmpty['status']);
        $this->assertNull($resEmpty['neraca_perdagangan']);
    }

    /**
     * Uji penolakan berkas jika worksheet yang diwajibkan tidak ada.
     */
    public function test_parser_rejects_missing_worksheet(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Worksheet 'RawDataEkspor' tidak ditemukan");

        // Berkas Impor tidak memiliki sheet RawDataEkspor
        $imporFile = base_path('referensi_bps/2. (Olah Data BRS) Impor LN Riau.xlsx');
        $this->service->generatePreview($imporFile, 'ekspor');
    }

    /**
     * Uji deteksi dimensi unik aktual (resolusi sharedStrings) pada berkas Ekspor BRS.
     */
    public function test_ekspor_dimension_resolution_accuracy(): void
    {
        $eksporFile = base_path('referensi_bps/1. (Olah Data BRS) Ekspor LN Riau.xlsx');
        $preview = $this->service->generatePreview($eksporFile, 'ekspor');

        // Total baris transaksi ekspor
        $this->assertEquals(53066, $preview['valid_rows_count']);

        // Verifikasi dimensi terurai yang benar setelah resolusi sharedStrings
        $this->assertEquals(666, $preview['dimensions']['unique_hs_count']);
        $this->assertEquals(174, $preview['dimensions']['unique_country_count']);
        $this->assertEquals(8, $preview['dimensions']['unique_kabkota_count']);

        // Cek range tahun
        $this->assertEquals([2020, 2021, 2022, 2023, 2024, 2025, 2026], $preview['periods']['years_available']);

        // Verifikasi rekonsiliasi total nilai dan total berat
        $this->assertEquals('127069371387.58', $preview['totals']['total_nilai']);
        $this->assertEquals('172767369262.555', $preview['totals']['total_berat_kg']);

        // Verifikasi deteksi peringatan master data (27 HS unmapped & normalisasi Meranti)
        $warnings = $preview['dimensions']['mapping_warnings'];
        $this->assertCount(27, $warnings['unregistered_hs']);
        $this->assertEmpty($warnings['unregistered_countries']); // 174 negara 100% cocok
        $this->assertNotEmpty($warnings['kabkota_normalization']);
    }

    /**
     * Uji penolakan berkas non-zip/invalid file.
     */
    public function test_parser_rejects_corrupted_or_non_zip_file(): void
    {
        $tempBadFile = tempnam(sys_get_temp_dir(), 'bad_xlsx_');
        file_put_contents($tempBadFile, 'Bukan berkas zip xlsx yang valid');

        try {
            $this->expectException(Exception::class);
            $this->expectExceptionMessage("Berkas bukan format ZIP / XLSX yang valid.");
            $this->service->generatePreview($tempBadFile, 'ekspor');
        } finally {
            if (file_exists($tempBadFile)) {
                unlink($tempBadFile);
            }
        }
    }
}
