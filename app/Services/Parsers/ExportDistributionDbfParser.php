<?php

namespace App\Services\Parsers;

use App\Services\Contracts\DbfParserInterface;
use App\Services\DbfReaderService;
use Exception;
use Generator;

/**
 * Parser khusus berkas DBF Ekspor Distribusi (POD-based / Pelabuhan Muat Riau).
 * Struktur sumber: 13 kolom (YEAR, MTH, KODE_HS, HS2, PEL_MUAT, PROVPOD, PROVORIG, NGRTUJUAN, NEGARA, PELABUHAN, NETTO, FOB, KETERANGAN).
 * Tabel Target: `distribusi_transactions`.
 */
class ExportDistributionDbfParser implements DbfParserInterface
{
    protected DbfReaderService $dbfReader;

    public function __construct(DbfReaderService $dbfReader)
    {
        $this->dbfReader = $dbfReader;
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

        $required = ['YEAR', 'MTH', 'KODE_HS', 'PEL_MUAT', 'PROVPOD', 'PROVORIG', 'NGRTUJUAN', 'NETTO', 'FOB'];
        $missing = array_diff($required, $fields);

        if (!empty($missing)) {
            throw new Exception("Struktur berkas Ekspor Distribusi tidak valid. Kolom wajib tidak ditemukan: " . implode(', ', $missing));
        }

        // Ambil sampel awal untuk mendeteksi tahun dan bulan
        $firstRows = iterator_to_array($this->parseStream($filePath, 5, 0, $originalFilename));
        $tahun = null;
        $bulan = null;

        if (!empty($firstRows)) {
            $tahun = $firstRows[0]['tahun'];
            $bulan = $firstRows[0]['bulan'];
        }

        if (!$tahun || !$bulan) {
            if ($originalFilename && preg_match('/(\d{2})(\d{2})/', $originalFilename, $matches)) {
                $tahun = 2000 + (int)$matches[1];
                $bulan = (int)$matches[2];
            }
        }

        if (!$tahun || !$bulan) {
            throw new Exception("Periode transaksi (tahun dan bulan) tidak dapat dideteksi dari berkas Ekspor Distribusi.");
        }

        return [
            'format' => 'DBF',
            'modul' => 'distribusi',
            'jenis_transaksi' => 'ekspor',
            'tahun' => $tahun,
            'bulan' => $bulan,
            'total_baris' => $meta['num_records'],
            'fields' => $fields,
            'sample_rows' => $firstRows,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function parseStream(string $filePath, ?int $limit = null, int $offset = 0, ?string $originalFilename = null): Generator
    {
        // streamRecords membaca nilai numerik mentah sebagai string untuk mencegah distorsi float
        foreach ($this->dbfReader->streamRecords($filePath, $limit, $offset, true) as $row) {
            $hsRaw = trim((string)($row['KODE_HS'] ?? ''));
            $hsCode = str_pad(preg_replace('/[^0-9]/', '', $hsRaw), 8, '0', STR_PAD_LEFT);

            $tahun = (int)($row['YEAR'] ?? 0);
            $bulan = (int)($row['MTH'] ?? 0);

            // Kolom pelabuhan & negara disimpan sebagai string
            $pelabuhanBps = trim((string)($row['PELABUHAN'] ?? ''));
            $pelabuhanLocode = trim((string)($row['PEL_MUAT'] ?? ''));
            $negaraKode = trim((string)($row['NEGARA'] ?? ''));
            $negaraIso = trim((string)($row['NGRTUJUAN'] ?? ''));

            // Wilayah: PROVPOD adalah pelabuhan muat Riau (14), PROVORIG adalah kab/kota asal komoditas (misal 1471)
            $provPod = trim((string)($row['PROVPOD'] ?? '14'));
            $provOrig = trim((string)($row['PROVORIG'] ?? ''));
            $kategoriBarang = trim((string)($row['KETERANGAN'] ?? 'NonMigas'));

            // Presisi numerik mutlak: gunakan sanitizeDecimalString bebas float
            $nettoKg = DbfReaderService::sanitizeDecimalString((string)($row['NETTO'] ?? '0'), 6);
            $fobUsd = DbfReaderService::sanitizeDecimalString((string)($row['FOB'] ?? '0'), 6);

            yield [
                'raw' => $row,
                'hs_code' => $hsCode,
                'hs2' => substr($hsCode, 0, 2),
                'tahun' => $tahun,
                'bulan' => $bulan,
                'pelabuhan_bps' => $pelabuhanBps,
                'pelabuhan_locode' => $pelabuhanLocode,
                'negara_kode' => $negaraKode,
                'negara_iso' => $negaraIso,
                'provpod' => $provPod,
                'provorig' => $provOrig,
                'kategori_barang' => $kategoriBarang,
                'berat_kg' => $nettoKg,
                'nilai_usd' => $fobUsd,
                'jenis_transaksi' => 'ekspor',
                'tipe_sumber' => 'distribusi',
            ];
        }
    }
}
