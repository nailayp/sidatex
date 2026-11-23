<?php

namespace App\Services\Contracts;

use Generator;

/**
 * Interface DbfParserInterface
 * Kontrak standar untuk seluruh parser DBF SIDATEX (Distribusi Ekspor/Impor dan Neraca Ekspor/Impor).
 * Mematuhi Zero Boilerplate dan PSR-12 Standard.
 */
interface DbfParserInterface
{
    /**
     * Memeriksa struktur header, field wajib, dan mendeteksi periode (tahun & bulan).
     *
     * @param string $filePath Path fisik berkas .DBF
     * @param string|null $originalFilename Nama berkas asli untuk bantuan deteksi fallback
     * @return array Metadata ringkas berkas dan sampel baris
     */
    public function inspect(string $filePath, ?string $originalFilename = null): array;

    /**
     * Membaca baris berkas secara streaming (generator) menghasilkan data transaksi terstandarisasi.
     *
     * @param string $filePath Path fisik berkas .DBF
     * @param int|null $limit Batas jumlah baris (untuk sampel preview)
     * @param int $offset Offset baris awal
     * @param string|null $originalFilename Nama berkas asli
     * @return Generator
     */
    public function parseStream(string $filePath, ?int $limit = null, int $offset = 0, ?string $originalFilename = null): Generator;
}
