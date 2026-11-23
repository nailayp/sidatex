<?php

namespace App\Services\Parsers;

use App\Services\Contracts\DbfParserInterface;
use App\Services\DbfReaderService;
use Exception;
use Generator;

/**
 * Parser khusus berkas DBF Impor Neraca (kppbc / Pengawasan Kantor Pabean Bea Cukai Riau).
 * Struktur sumber: 15 KOLOM KHUSUS
 * (TIPE, JENIS, HS, K_NEGARA, NEG_ASAL, NM_NEGARA, K_PELB, PEL_BONG, NM_PELABUH, KPPBC, NAMA_KPPBC, PROV_KPPBC, NM_PROV_BC, B[MMYY], N[MMYY]).
 * Tabel Target: `neraca_transactions`.
 *
 * Perbedaan Domain vs Distribusi:
 * - Memetakan kantor pengawasan pabean KPPBC (KPPBC, NAMA_KPPBC, PROV_KPPBC).
 * - Tidak memiliki dimensi kab/kota konsumen (kabkota_id selalu NULL).
 * - Transaksi Neraca mendukung kode HS unmapped (hs_code_raw disimpan, hs_id nullable, mapping_status = 'unmapped').
 */
class ImportNeracaDbfParser implements DbfParserInterface
{
    protected DbfReaderService $dbfReader;

    public function __construct(DbfReaderService $dbfReader)
    {
        $this->dbfReader = $dbfReader;
    }

    /**
     * Mengekstrak metadata kolom dinamis volume & nilai dari daftar field tanpa rekursi.
     */
    protected function detectDynamicColumns(array $fields): array
    {
        $volCol = null;
        $valCol = null;
        $bulan = null;
        $tahun = null;

        foreach ($fields as $f) {
            if (preg_match('/^B(\d{2})(\d{2})$/i', $f, $matches)) {
                $volCol = $f;
                $bulan = (int)$matches[1];
                $tahun = 2000 + (int)$matches[2];
            } elseif (preg_match('/^N(\d{2})(\d{2})$/i', $f, $matches)) {
                $valCol = $f;
                if (!$bulan) {
                    $bulan = (int)$matches[1];
                    $tahun = 2000 + (int)$matches[2];
                }
            }
        }

        if (!$volCol || !$valCol) {
            throw new Exception("Kolom dinamis volume (BMMYY) atau nilai (NMMYY) tidak ditemukan pada berkas Impor Neraca.");
        }

        return [
            'vol_col' => $volCol,
            'val_col' => $valCol,
            'bulan' => $bulan,
            'tahun' => $tahun,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function inspect(string $filePath, ?string $originalFilename = null): array
    {
        if (!$this->dbfReader->isDbf($filePath)) {
            throw new Exception("Berkas bukan format DBF (dBase III) yang valid.");
        }

        $meta = $this->dbfReader->getHeader($filePath);
        $fields = array_keys($meta['fields']);

        $required = ['HS', 'K_NEGARA', 'K_PELB', 'PEL_BONG', 'KPPBC', 'NAMA_KPPBC', 'PROV_KPPBC'];
        $missing = array_diff($required, $fields);
        if (!empty($missing)) {
            throw new Exception("Struktur berkas Impor Neraca tidak valid. Kolom pabean tidak ditemukan: " . implode(', ', $missing));
        }

        $dyn = $this->detectDynamicColumns($fields);

        // Ambil sampel awal secara langsung
        $sampleRows = [];
        foreach ($this->parseRecords($filePath, $dyn['vol_col'], $dyn['val_col'], $dyn['tahun'], $dyn['bulan'], 5, 0) as $row) {
            $sampleRows[] = $row;
        }

        return [
            'format' => 'DBF',
            'modul' => 'neraca',
            'jenis_transaksi' => 'impor',
            'tahun' => $dyn['tahun'],
            'bulan' => $dyn['bulan'],
            'vol_col' => $dyn['vol_col'],
            'val_col' => $dyn['val_col'],
            'total_baris' => $meta['num_records'],
            'fields' => $fields,
            'sample_rows' => $sampleRows,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function parseStream(string $filePath, ?int $limit = null, int $offset = 0, ?string $originalFilename = null): Generator
    {
        $meta = $this->dbfReader->getHeader($filePath);
        $fields = array_keys($meta['fields']);
        $dyn = $this->detectDynamicColumns($fields);

        return $this->parseRecords($filePath, $dyn['vol_col'], $dyn['val_col'], $dyn['tahun'], $dyn['bulan'], $limit, $offset);
    }

    /**
     * Generator internal untuk membaca dan memetakan baris rekaman.
     */
    protected function parseRecords(string $filePath, string $volCol, string $valCol, int $tahun, int $bulan, ?int $limit, int $offset): Generator
    {
        foreach ($this->dbfReader->streamRecords($filePath, $limit, $offset, true) as $row) {
            $hsRaw = trim((string)($row['HS'] ?? ''));
            $hsCode = str_pad(preg_replace('/[^0-9]/', '', $hsRaw), 8, '0', STR_PAD_LEFT);

            $pelabuhanBps = trim((string)($row['K_PELB'] ?? ''));
            $pelabuhanLocode = trim((string)($row['PEL_BONG'] ?? ''));
            $namaPelabuhan = trim((string)($row['NM_PELABUH'] ?? ''));

            $negaraKode = trim((string)($row['K_NEGARA'] ?? ''));
            $negaraIso = trim((string)($row['NEG_ASAL'] ?? ''));
            $namaNegara = trim((string)($row['NM_NEGARA'] ?? ''));

            // Kolom Kepabeanan Khusus Neraca Impor
            $kppbc = trim((string)($row['KPPBC'] ?? ''));
            $namaKppbc = trim((string)($row['NAMA_KPPBC'] ?? ''));
            $provKppbc = trim((string)($row['PROV_KPPBC'] ?? '14'));

            $tipe = trim((string)($row['TIPE'] ?? ''));
            $jenisRaw = trim((string)($row['JENIS'] ?? 'NM'));
            $kategoriBarang = ($jenisRaw === 'M') ? 'Migas' : 'NonMigas';

            $nettoKg = DbfReaderService::sanitizeDecimalString((string)($row[$volCol] ?? '0'), 6);
            $cifUsd = DbfReaderService::sanitizeDecimalString((string)($row[$valCol] ?? '0'), 6);

            yield [
                'raw' => $row,
                'hs_code_raw' => $hsRaw,
                'hs_code' => $hsCode,
                'hs2' => substr($hsCode, 0, 2),
                'tahun' => $tahun,
                'bulan' => $bulan,
                'pelabuhan_bps' => $pelabuhanBps,
                'pelabuhan_locode' => $pelabuhanLocode,
                'nama_pelabuhan_raw' => $namaPelabuhan,
                'negara_kode' => $negaraKode,
                'negara_iso' => $negaraIso,
                'nama_negara_raw' => $namaNegara,
                'provinsi_id_code' => $provKppbc, // Wilayah kantor pabean Riau
                'kabkota_code' => null,           // Neraca Impor tidak memiliki kabkota
                'kode_kppbc' => $kppbc,           // Misal: 021200
                'nama_kppbc' => $namaKppbc,       // Misal: KPPBC PEKANBARU
                'tipe' => $tipe,
                'kategori_barang' => $kategoriBarang,
                'berat_kg' => $nettoKg,
                'nilai_usd' => $cifUsd,
                'jenis_transaksi' => 'impor',
                'tipe_sumber' => 'neraca',
            ];
        }
    }
}
