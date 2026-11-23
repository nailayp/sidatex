<?php

namespace App\Services;

use Exception;
use Generator;

/**
 * Native PHP reader for dBase III (.DBF) files.
 * Membaca struktur dan data dBase III secara streaming tanpa ekstensi eksternal C.
 * Mematuhi prinsip unslop: Zero Boilerplate, PSR-12, dan Clean Query Optimization.
 *
 * Catatan Presisi Numerik:
 * Seluruh field numerik ('N' dan 'F') dipertahankan sebagai representasi string ASCII asli
 * atau dinormalisasi via sanitizeDecimalString() untuk mencegah distorsi floating-point IEEE 754.
 */
class DbfReaderService
{
    /**
     * Memeriksa apakah berkas memiliki signature valid dBase III (0x03, 0x83, 0x8B).
     */
    public function isDbf(string $filePath): bool
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return false;
        }

        $fp = @fopen($filePath, 'rb');
        if (!$fp) {
            return false;
        }

        $firstByte = ord(fread($fp, 1));
        fclose($fp);

        return in_array($firstByte, [0x03, 0x83, 0x8B], true);
    }

    /**
     * Membaca metadata dan struktur field dari header berkas DBF.
     *
     * @param string $filePath
     * @return array
     * @throws Exception
     */
    public function getHeader(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new Exception("Berkas DBF tidak ditemukan: {$filePath}");
        }

        if (!is_readable($filePath)) {
            throw new Exception("Berkas DBF sedang terkunci atau tidak memiliki izin akses baca oleh sistem operasi: {$filePath}");
        }

        $fp = @fopen($filePath, 'rb');
        if (!$fp) {
            throw new Exception("Gagal membuka berkas DBF (mungkin sedang terkunci): {$filePath}");
        }

        $headerRaw = fread($fp, 32);
        if (strlen($headerRaw) < 32) {
            fclose($fp);
            throw new Exception("Berkas DBF corrupt atau tidak valid (header kurang dari 32 byte).");
        }

        $header = unpack('Cversion/Cy/Cm/Cd/Vnum_records/vheader_len/vrecord_len', $headerRaw);
        $numRecords = $header['num_records'];
        $headerLen = $header['header_len'];
        $recordLen = $header['record_len'];

        $fields = [];
        while (ftell($fp) < $headerLen - 1) {
            $fieldHeader = fread($fp, 32);
            if (strlen($fieldHeader) < 32 || ord($fieldHeader[0]) === 0x0D) {
                break;
            }
            $name = rtrim(substr($fieldHeader, 0, 11), "\0 ");
            $type = chr(ord($fieldHeader[11]));
            $len = ord($fieldHeader[16]);
            $dec = ord($fieldHeader[17]);

            $fields[$name] = [
                'name' => $name,
                'type' => $type,
                'len' => $len,
                'dec' => $dec,
            ];
        }

        fclose($fp);

        return [
            'version' => $header['version'],
            'last_update' => sprintf('%04d-%02d-%02d', 1900 + $header['y'], $header['m'], $header['d']),
            'num_records' => $numRecords,
            'header_len' => $headerLen,
            'record_len' => $recordLen,
            'fields' => $fields,
        ];
    }

    /**
     * Stream baris data DBF menggunakan generator untuk efisiensi memori (flat memory usage).
     *
     * @param string $filePath
     * @param int|null $limit
     * @param int $offset
     * @param bool $rawNumericString Jika true, field N/F dikembalikan apa adanya sebagai string teks asli tanpa konversi float
     * @return Generator
     * @throws Exception
     */
    public function streamRecords(string $filePath, ?int $limit = null, int $offset = 0, bool $rawNumericString = true): Generator
    {
        $meta = $this->getHeader($filePath);
        $fp = @fopen($filePath, 'rb');
        if (!$fp) {
            throw new Exception("Gagal membuka berkas DBF untuk pembacaan stream: {$filePath}");
        }

        $fields = $meta['fields'];
        $recordLen = $meta['record_len'];
        $totalRecords = $meta['num_records'];

        $startPos = $meta['header_len'] + ($offset * $recordLen);
        fseek($fp, $startPos);

        $yielded = 0;
        for ($i = $offset; $i < $totalRecords; $i++) {
            if ($limit !== null && $yielded >= $limit) {
                break;
            }

            $raw = fread($fp, $recordLen);
            if (strlen($raw) < $recordLen) {
                break;
            }

            // 0x2A (*) menandakan record ditandai terhapus (deleted record dBase)
            if (ord($raw[0]) === 0x2A) {
                continue;
            }

            $record = [];
            $pos = 1; // Lewati byte deletion flag

            foreach ($fields as $fieldName => $fieldMeta) {
                $rawVal = substr($raw, $pos, $fieldMeta['len']);
                $pos += $fieldMeta['len'];

                // Bersihkan spasi kosong dan null byte
                $val = trim($rawVal, " \t\n\r\0\x0B");

                if ($rawNumericString) {
                    // Simpan representasi string murni tanpa float casting untuk menjaga presisi penuh
                    $record[$fieldName] = $val;
                } else {
                    // Fallback kompatibilitas jika pemanggil meminta casting lama
                    if ($fieldMeta['type'] === 'N' || $fieldMeta['type'] === 'F') {
                        if ($val === '') {
                            $record[$fieldName] = 0;
                        } elseif ($fieldMeta['dec'] > 0) {
                            $record[$fieldName] = (float)$val;
                        } else {
                            $record[$fieldName] = (int)$val;
                        }
                    } else {
                        $record[$fieldName] = $val;
                    }
                }
            }

            yield $record;
            $yielded++;
        }

        fclose($fp);
    }

    /**
     * Membaca seluruh baris data ke dalam array (khusus sampel atau berkas kecil).
     *
     * @param string $filePath
     * @param int|null $limit
     * @return array
     * @throws Exception
     */
    public function readAll(string $filePath, ?int $limit = null): array
    {
        $records = [];
        foreach ($this->streamRecords($filePath, $limit) as $rec) {
            $records[] = $rec;
        }

        return $records;
    }

    /**
     * Sanitasi dan normalisasi string angka desimal untuk penyimpanan presisi tinggi ke MySQL DECIMAL(20,6).
     * Bekerja murni pada level manipulasi string tanpa pernah mengonversi ke float.
     *
     * @param string|null $rawValue
     * @param int $decimals Skala desimal target (default 6 desimal)
     * @return string
     */
    public static function sanitizeDecimalString(?string $rawValue, int $decimals = 6): string
    {
        if ($rawValue === null) {
            return '0.' . str_repeat('0', $decimals);
        }

        $clean = trim($rawValue);
        if ($clean === '' || !is_numeric($clean)) {
            return '0.' . str_repeat('0', $decimals);
        }

        // Tangani jika ada tanda minus / negatif (tidak lazim di transaksi perdagangan, dinormalkan jika ada)
        $sign = '';
        if (str_starts_with($clean, '-')) {
            $sign = '-';
            $clean = substr($clean, 1);
        }

        // Pisahkan bagian integer dan pecahan
        if (str_contains($clean, '.')) {
            [$intPart, $fracPart] = explode('.', $clean, 2);
            $intPart = ltrim($intPart, '0');
            if ($intPart === '') {
                $intPart = '0';
            }

            // Normalisasi panjang bagian desimal
            $fracPart = rtrim($fracPart);
            if (strlen($fracPart) > $decimals) {
                $fracPart = substr($fracPart, 0, $decimals);
            } else {
                $fracPart = str_pad($fracPart, $decimals, '0', STR_PAD_RIGHT);
            }

            return $sign . $intPart . '.' . $fracPart;
        }

        // Angka integer murni tanpa desimal
        $intPart = ltrim($clean, '0');
        if ($intPart === '') {
            $intPart = '0';
        }

        return $sign . $intPart . '.' . str_repeat('0', $decimals);
    }
}
