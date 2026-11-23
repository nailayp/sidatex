<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use XMLReader;
use ZipArchive;
use SimpleXMLElement;

class ExcelTransactionParser
{
    /**
     * Memproses file Excel dan menyimpan baris transaksi ke tabel transactions.
     * Mengembalikan jumlah baris transaksi yang berhasil disimpan.
     */
    public function parseAndInsert(string $filePath, int $batchId, string $jenisTransaksi): int
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File Excel tidak ditemukan pada path: {$filePath}");
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new \Exception("Gagal membuka file Excel (.xlsx).");
        }

        // 1. Preload data master ke memori (lookup maps)
        $masterHsMap = DB::table('master_hs')->pluck('hs_id', 'hs_code')->all();
        $defaultHsId = !empty($masterHsMap) ? reset($masterHsMap) : 1;

        $masterNegaraMap = [];
        foreach (DB::table('master_negara')->select('negara_id', 'kode_negara')->get() as $n) {
            $code = trim($n->kode_negara);
            $masterNegaraMap[$code] = $n->negara_id;
            $masterNegaraMap[str_pad($code, 3, '0', STR_PAD_LEFT)] = $n->negara_id;
        }
        $defaultNegaraId = $masterNegaraMap['000'] ?? (!empty($masterNegaraMap) ? reset($masterNegaraMap) : 1);

        $masterProvinsiMap = [];
        foreach (DB::table('master_provinsi')->select('provinsi_id', 'kode_provinsi')->get() as $p) {
            $code = trim($p->kode_provinsi);
            $masterProvinsiMap[$code] = $p->provinsi_id;
            $masterProvinsiMap[str_pad($code, 2, '0', STR_PAD_LEFT)] = $p->provinsi_id;
        }
        $defaultProvinsiId = $masterProvinsiMap['14'] ?? (!empty($masterProvinsiMap) ? reset($masterProvinsiMap) : 4);

        $masterKabkotaMap = [];
        foreach (DB::table('master_kabkota')->select('kabkota_id', 'kode_kabkota', 'nama_kabkota')->get() as $k) {
            $masterKabkotaMap[trim($k->kode_kabkota)] = $k->kabkota_id;
            $cleanName = mb_strtolower(trim(preg_replace('/^(Kabupaten|Kab\.|Kota)\s*/i', '', $k->nama_kabkota)));
            $masterKabkotaMap[$cleanName] = $k->kabkota_id;
        }

        $masterPelabuhanMap = [];
        foreach (DB::table('master_pelabuhan')->select('pelabuhan_id', 'kode_pelabuhan')->get() as $pel) {
            $code = trim($pel->kode_pelabuhan);
            $masterPelabuhanMap[$code] = $pel->pelabuhan_id;
            $masterPelabuhanMap[str_pad($code, 3, '0', STR_PAD_LEFT)] = $pel->pelabuhan_id;
        }

        // 2. Baca daftar sheet dari xl/workbook.xml & relasi
        $wbXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if (!$wbXml || !$relsXml) {
            $zip->close();
            throw new \Exception("Struktur workbook Excel tidak valid.");
        }

        $wb = simplexml_load_string($wbXml);
        $rels = simplexml_load_string($relsXml);

        $relMap = [];
        foreach ($rels->Relationship as $r) {
            $relMap[(string)$r['Id']] = (string)$r['Target'];
        }

        $sheetMap = [];
        foreach ($wb->sheets->sheet as $s) {
            $name = (string)$s['name'];
            $rid = (string)$s->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            if (isset($relMap[$rid])) {
                $target = $relMap[$rid];
                $sheetMap[$name] = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/' . $target;
            }
        }

        // Tentukan sheet yang dituju
        $targetSheetPath = null;
        $sheetPriority = $jenisTransaksi === 'Impor'
            ? ['Impor', 'Import', 'RawDataImpor', 'Raw_Data_Impor', 'DataImpor', 'Data_Impor', 'RawData', 'Sheet1']
            : ['Ekspor', 'Export', 'RawDataEkspor', 'Raw_Data_Ekspor', 'DataEkspor', 'Data_Ekspor', 'RawData', 'Sheet1'];

        // 1. Coba pencarian persis dari daftar prioritas
        foreach ($sheetPriority as $sName) {
            if (isset($sheetMap[$sName])) {
                $targetSheetPath = $sheetMap[$sName];
                break;
            }
        }

        // 2. Coba pencarian case-insensitive atau parsial
        if (!$targetSheetPath) {
            $targetKeyword = strtolower($jenisTransaksi);
            $altKeyword = $jenisTransaksi === 'Impor' ? 'import' : 'export';

            foreach ($sheetMap as $sName => $sPath) {
                $cleanName = strtolower(trim($sName));
                if (str_contains($cleanName, $targetKeyword) || str_contains($cleanName, $altKeyword)) {
                    $targetSheetPath = $sPath;
                    break;
                }
            }
        }

        // 3. Fallback jika hanya ada 1 sheet di workbook
        if (!$targetSheetPath && count($sheetMap) === 1) {
            $targetSheetPath = reset($sheetMap);
        }

        if (!$targetSheetPath || $zip->locateName($targetSheetPath) === false) {
            $availableList = !empty($sheetMap) ? implode(', ', array_keys($sheetMap)) : 'Kosong / Tidak Ada Sheet';
            $zip->close();
            throw new \Exception("Worksheet untuk data {$jenisTransaksi} tidak ditemukan. Sheet yang tersedia: [{$availableList}]. Silakan periksa file Excel yang dipilih.");
        }

        // 3. Baca shared strings
        $sharedStrings = [];
        if ($zip->locateName('xl/sharedStrings.xml') !== false) {
            $reader = new XMLReader();
            $reader->XML($zip->getFromName('xl/sharedStrings.xml'));
            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'si') {
                    $item = simplexml_load_string($reader->readOuterXML());
                    $text = '';
                    foreach ($item->xpath('.//*[local-name()="t"]') as $t) {
                        $text .= (string)$t;
                    }
                    $sharedStrings[] = $text;
                }
            }
            $reader->close();
        }

        // 4. Baca sheet XML secara streaming
        $xml = new XMLReader();
        $xml->XML($zip->getFromName($targetSheetPath));

        $colMap = []; // 'BULAN' => 'B', dst.
        $chunk = [];
        $totalInserted = 0;
        $now = now();

        $namaBulanKeAngka = [
            'januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4,
            'mei' => 5, 'juni' => 6, 'juli' => 7, 'agustus' => 8,
            'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12
        ];

        while ($xml->read()) {
            if ($xml->nodeType !== XMLReader::ELEMENT || $xml->localName !== 'row') {
                continue;
            }

            $rowElem = simplexml_load_string($xml->readOuterXML());
            $rowNum = (int)$rowElem['r'];

            // Baris 1: Header kolom
            if ($rowNum === 1) {
                foreach ($rowElem->c as $cell) {
                    $ref = (string)$cell['r'];
                    preg_match('/^[A-Z]+/', $ref, $m);
                    $col = $m[0] ?? '';
                    $val = (string)$cell->v;
                    if ((string)$cell['t'] === 's') {
                        $val = $sharedStrings[(int)$val] ?? $val;
                    }
                    $headerClean = strtoupper(trim((string)$val));
                    $colMap[$headerClean] = $col;
                }
                continue;
            }

            // Ambil data baris
            $rowData = [];
            foreach ($rowElem->c as $cell) {
                $ref = (string)$cell['r'];
                preg_match('/^[A-Z]+/', $ref, $m);
                $col = $m[0] ?? '';
                $val = (string)$cell->v;
                if ((string)$cell['t'] === 's') {
                    $val = $sharedStrings[(int)$val] ?? $val;
                }
                $rowData[$col] = trim((string)$val);
            }

            // Helper untuk mengambil nilai berdasarkan variasi nama header
            $getVal = function(array $headers) use ($colMap, $rowData) {
                foreach ($headers as $h) {
                    if (isset($colMap[$h]) && isset($rowData[$colMap[$h]]) && $rowData[$colMap[$h]] !== '') {
                        return $rowData[$colMap[$h]];
                    }
                }
                return null;
            };

            // Parse Bulan
            $bulanRaw = $getVal(['BULAN', 'BLN', 'MONTH']);
            if ($bulanRaw === null) continue;

            $bulan = null;
            $bulanLower = mb_strtolower($bulanRaw);
            if (isset($namaBulanKeAngka[$bulanLower])) {
                $bulan = $namaBulanKeAngka[$bulanLower];
            } elseif (is_numeric($bulanRaw)) {
                $bulan = (int)$bulanRaw;
            }
            if (!$bulan || $bulan < 1 || $bulan > 12) continue;

            // Parse Tahun
            $tahunRaw = $getVal(['TAHUN', 'THN', 'YEAR']);
            if ($tahunRaw === null || !is_numeric($tahunRaw)) continue;
            $tahun = (int)$tahunRaw;
            if ($tahun >= 20 && $tahun <= 26) {
                $tahun += 2000;
            }

            // Parse Nilai FOB
            $nilaiRaw = $getVal(['FOB (US$)', 'NILAI', 'FOB', 'VAL', 'VALUE']);
            $nilai = $this->parseNumber($nilaiRaw);
            if ($nilai === null) $nilai = 0;

            // Parse Berat Netto
            $beratRaw = $getVal(['NETTO (KG)', 'BERAT', 'NETTO', 'WEIGHT']);
            $berat = $this->parseNumber($beratRaw);
            if ($berat === null) $berat = 0;

            // Parse HS Code -> hs_id (Wajib terdaftar di Master HS)
            $hsRaw = $getVal(['KODE HS', 'HS', 'HS_CODE', 'KODEHS']);
            $hsClean = $hsRaw ? preg_replace('/[^0-9]/', '', $hsRaw) : '';
            $hsId = null;
            if ($hsClean !== '') {
                if (isset($masterHsMap[$hsClean])) {
                    $hsId = $masterHsMap[$hsClean];
                } else {
                    // Coba prefix 8 atau 6 digit
                    $sub8 = substr($hsClean, 0, 8);
                    $sub6 = substr($hsClean, 0, 6);
                    if (isset($masterHsMap[$sub8])) {
                        $hsId = $masterHsMap[$sub8];
                    } elseif (isset($masterHsMap[$sub6])) {
                        $hsId = $masterHsMap[$sub6];
                    }
                }
            }

            if (!$hsId) {
                $xml->close();
                $zip->close();
                $kodeBermasalah = $hsRaw ?: '(Kosong)';
                throw new \Exception("Baris {$rowNum}: Kode HS '{$kodeBermasalah}' tidak ditemukan pada Master HS. Periksa kembali kode pada file Excel atau pastikan Master HS sudah diperbarui.");
            }

            // Parse Negara -> negara_id (Wajib terdaftar di Master Negara)
            $negaraRaw = $getVal(['KODE_NGR', 'NEGARA', 'KODE_NEGARA', 'NGR']);
            $negaraClean = $negaraRaw ? trim($negaraRaw) : '';
            $negaraId = null;
            if ($negaraClean !== '') {
                $negaraId = $masterNegaraMap[$negaraClean] 
                         ?? $masterNegaraMap[str_pad($negaraClean, 3, '0', STR_PAD_LEFT)] 
                         ?? null;
            }

            if (!$negaraId) {
                $xml->close();
                $zip->close();
                $kodeBermasalah = $negaraRaw ?: '(Kosong)';
                throw new \Exception("Baris {$rowNum}: Kode negara '{$kodeBermasalah}' tidak ditemukan pada Master Negara. Periksa kembali kode pada file Excel atau pastikan Master Negara sudah diperbarui.");
            }

            // Parse Provinsi -> provinsi_id
            $provRaw = $getVal(['PROVORIG', 'PROPINSI', 'PROVINSI', 'PROPASAL', 'PROV']);
            $provClean = $provRaw ? trim($provRaw) : '';
            $provinsiId = $defaultProvinsiId;
            if ($provClean !== '') {
                $provinsiId = $masterProvinsiMap[$provClean] 
                           ?? $masterProvinsiMap[str_pad($provClean, 2, '0', STR_PAD_LEFT)] 
                           ?? $defaultProvinsiId;
            }

            // Parse Kabkota (opsional) -> kabkota_id
            $kabRaw = $getVal(['KABUPATEN/KOTA', 'KABKOTA', 'KODE_KABKOTA', 'KAB']);
            $kabkotaId = null;
            if ($kabRaw !== null && $kabRaw !== '') {
                $kabClean = trim($kabRaw);
                $kabName = mb_strtolower(preg_replace('/^(Kabupaten|Kab\.|Kota)\s*/i', '', $kabClean));
                $kabkotaId = $masterKabkotaMap[$kabClean] 
                          ?? $masterKabkotaMap[$kabName] 
                          ?? null;
            }

            // Parse Pelabuhan (opsional) -> pelabuhan_id
            $pelRaw = $getVal(['PELABUHAN', 'KODE_PELABUHAN', 'PORT']);
            $pelabuhanId = null;
            if ($pelRaw !== null && $pelRaw !== '') {
                $pelClean = trim($pelRaw);
                $pelabuhanId = $masterPelabuhanMap[$pelClean] 
                            ?? $masterPelabuhanMap[str_pad($pelClean, 3, '0', STR_PAD_LEFT)] 
                            ?? null;
            }

            // Siapkan row transaksi
            $chunk[] = [
                'batch_id' => $batchId,
                'hs_id' => $hsId,
                'negara_id' => $negaraId,
                'provinsi_id' => $provinsiId,
                'kabkota_id' => $kabkotaId,
                'pelabuhan_id' => $pelabuhanId,
                'jenis_transaksi' => $jenisTransaksi,
                'bulan' => $bulan,
                'tahun' => $tahun,
                'berat' => $berat,
                'nilai' => $nilai,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // Bulk insert setiap 1000 baris
            if (count($chunk) >= 1000) {
                DB::table('distribusi_transactions')->insert($chunk);
                $totalInserted += count($chunk);
                $chunk = [];
            }
        }

        // Insert sisa baris
        if (!empty($chunk)) {
            DB::table('distribusi_transactions')->insert($chunk);
            $totalInserted += count($chunk);
            $chunk = [];
        }

        $xml->close();
        $zip->close();

        return $totalInserted;
    }

    private function parseNumber(?string $value): ?float
    {
        if ($value === null || trim($value) === '') return null;
        $clean = trim(str_replace(' ', '', $value));
        if (str_contains($clean, '.') && str_contains($clean, ',')) {
            if (strrpos($clean, ',') > strrpos($clean, '.')) {
                $clean = str_replace('.', '', $clean);
                $clean = str_replace(',', '.', $clean);
            } else {
                $clean = str_replace(',', '', $clean);
            }
        } elseif (str_contains($clean, ',')) {
            $clean = str_replace(',', '.', $clean);
        }
        return is_numeric($clean) ? (float)$clean : null;
    }
}
