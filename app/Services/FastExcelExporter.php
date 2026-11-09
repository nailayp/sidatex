<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use ZipArchive;

class FastExcelExporter
{
    /**
     * Menghasilkan file Excel (.xlsx) dengan struktur OpenXML secara streaming langsung ke file temporer.
     * Menggunakan memory seminimal mungkin (< 5 MB) dan tidak blocking.
     */
    public function exportBatchToPath(int $batchId, string $destinationPath): int
    {
        $batch = DB::table('data_batches')->where('batch_id', $batchId)->first();
        if (!$batch) {
            throw new \Exception('Data batch tidak ditemukan.');
        }

        $sheetName = htmlspecialchars(substr($batch->jenis_data ?? 'Data', 0, 31), ENT_XML1, 'UTF-8');

        $zip = new ZipArchive();
        if ($zip->open($destinationPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception("Gagal membuat file arsip Excel di {$destinationPath}");
        }

        // 1. [Content_Types].xml
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
            '<Default Extension="xml" ContentType="application/xml"/>' .
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
            '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
            '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' .
            '</Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);

        // 2. _rels/.rels
        $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
            '</Relationships>';
        $zip->addFromString('_rels/.rels', $rootRels);

        // 3. xl/_rels/workbook.xml.rels
        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
            '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' .
            '</Relationships>';
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);

        // 4. xl/workbook.xml
        $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
            '<sheets>' .
            '<sheet name="' . $sheetName . '" sheetId="1" r:id="rId1"/>' .
            '</sheets>' .
            '</workbook>';
        $zip->addFromString('xl/workbook.xml', $workbookXml);

        // 5. xl/styles.xml (Style 0 = default, Style 1 = Header Hijau BPS, Style 2 = Desimal 3 angka (berat), Style 3 = Desimal 2 angka (nilai))
        $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
            '<numFmts count="2">' .
            '<numFmt numFmtId="164" formatCode="#,##0.000"/>' .
            '<numFmt numFmtId="165" formatCode="#,##0.00"/>' .
            '</numFmts>' .
            '<fonts count="2">' .
            '<font><sz val="11"/><name val="Calibri"/></font>' .
            '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>' .
            '</fonts>' .
            '<fills count="3">' .
            '<fill><patternFill patternType="none"/></fill>' .
            '<fill><patternFill patternType="gray125"/></fill>' .
            '<fill><patternFill patternType="solid"><fgColor rgb="FF107C41"/></patternFill></fill>' . // Hijau BPS
            '</fills>' .
            '<borders count="1">' .
            '<border><left/><right/><top/><bottom/><diagonal/></border>' .
            '</borders>' .
            '<cellXfs count="4">' .
            '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' .
            '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' .
            '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>' .
            '<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>' .
            '</cellXfs>' .
            '</styleSheet>';
        $zip->addFromString('xl/styles.xml', $stylesXml);

        // 6. Buat xl/worksheets/sheet1.xml secara streaming ke file temporary disk
        $tempSheetPath = tempnam(sys_get_temp_dir(), 'sidatex_sheet_');
        $handle = fopen($tempSheetPath, 'w+');
        if (!$handle) {
            $zip->close();
            throw new \Exception('Gagal membuat buffer disk sheet.');
        }

        fwrite($handle, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n");
        fwrite($handle, '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . "\n");
        
        // Lebar kolom yang proporsional
        fwrite($handle, '<cols>' .
            '<col min="1" max="1" width="8" customWidth="1"/>' .
            '<col min="2" max="2" width="10" customWidth="1"/>' .
            '<col min="3" max="3" width="10" customWidth="1"/>' .
            '<col min="4" max="4" width="12" customWidth="1"/>' .
            '<col min="5" max="5" width="14" customWidth="1"/>' .
            '<col min="6" max="6" width="35" customWidth="1"/>' .
            '<col min="7" max="7" width="14" customWidth="1"/>' .
            '<col min="8" max="8" width="22" customWidth="1"/>' .
            '<col min="9" max="9" width="18" customWidth="1"/>' .
            '<col min="10" max="10" width="22" customWidth="1"/>' .
            '<col min="11" max="11" width="22" customWidth="1"/>' .
            '<col min="12" max="12" width="18" customWidth="1"/>' .
            '<col min="13" max="13" width="18" customWidth="1"/>' .
            '</cols>' . "\n");

        fwrite($handle, '<sheetData>' . "\n");

        // Header Row (Row 1) dengan style index 1 (Hijau BPS bold white)
        $headers = [
            'A1' => 'No',
            'B1' => 'BULAN',
            'C1' => 'TAHUN',
            'D1' => 'JENIS',
            'E1' => 'KODE HS',
            'F1' => 'DESKRIPSI HS',
            'G1' => 'KODE NEGARA',
            'H1' => 'NEGARA',
            'I1' => 'PROVINSI',
            'J1' => 'KABUPATEN/KOTA',
            'K1' => 'PELABUHAN',
            'L1' => 'NETTO (KG)',
            'M1' => 'FOB (US$)',
        ];

        fwrite($handle, '<row r="1" ht="26" customHeight="1">');
        foreach ($headers as $cellRef => $title) {
            $escaped = htmlspecialchars($title, ENT_XML1, 'UTF-8');
            fwrite($handle, '<c r="' . $cellRef . '" t="inlineStr" s="1"><is><t>' . $escaped . '</t></is></c>');
        }
        fwrite($handle, '</row>' . "\n");

        // Query transaksi bertahap (chunking cursor) menggunakan orderBy transaction_id
        $query = DB::table('transactions')
            ->where('transactions.batch_id', $batchId)
            ->leftJoin('master_hs', 'transactions.hs_id', '=', 'master_hs.hs_id')
            ->leftJoin('master_negara', 'transactions.negara_id', '=', 'master_negara.negara_id')
            ->leftJoin('master_provinsi', 'transactions.provinsi_id', '=', 'master_provinsi.provinsi_id')
            ->leftJoin('master_kabkota', 'transactions.kabkota_id', '=', 'master_kabkota.kabkota_id')
            ->leftJoin('master_pelabuhan', 'transactions.pelabuhan_id', '=', 'master_pelabuhan.pelabuhan_id')
            ->select(
                'transactions.transaction_id',
                'transactions.bulan',
                'transactions.tahun',
                'transactions.jenis_transaksi',
                'master_hs.hs_code',
                'master_hs.hs_desc',
                'master_negara.kode_negara',
                'master_negara.nama_negara',
                'master_provinsi.nama_provinsi',
                'master_kabkota.nama_kabkota',
                'master_pelabuhan.nama_pelabuhan',
                'transactions.berat',
                'transactions.nilai'
            )
            ->orderBy('transactions.transaction_id', 'asc');

        $rowNum = 2;
        $totalRows = 0;

        foreach ($query->cursor() as $t) {
            $totalRows++;
            $r = $rowNum++;

            $cNo = '<c r="A' . $r . '"><v>' . ($totalRows) . '</v></c>';
            $cBulan = '<c r="B' . $r . '"><v>' . ((int)$t->bulan) . '</v></c>';
            $cTahun = '<c r="C' . $r . '"><v>' . ((int)$t->tahun) . '</v></c>';
            
            $jns = htmlspecialchars((string)($t->jenis_transaksi ?? ''), ENT_XML1, 'UTF-8');
            cJenis: $cJenis = '<c r="D' . $r . '" t="inlineStr"><is><t>' . $jns . '</t></is></c>';
            
            $hs = htmlspecialchars((string)($t->hs_code ?? ''), ENT_XML1, 'UTF-8');
            $cHs = '<c r="E' . $r . '" t="inlineStr"><is><t>' . $hs . '</t></is></c>';
            
            $hsDesc = htmlspecialchars((string)($t->hs_desc ?? ''), ENT_XML1, 'UTF-8');
            $cHsDesc = '<c r="F' . $r . '" t="inlineStr"><is><t>' . $hsDesc . '</t></is></c>';
            
            $ngrCode = htmlspecialchars((string)($t->kode_negara ?? ''), ENT_XML1, 'UTF-8');
            $cNgrCode = '<c r="G' . $r . '" t="inlineStr"><is><t>' . $ngrCode . '</t></is></c>';
            
            $ngrName = htmlspecialchars((string)($t->nama_negara ?? ''), ENT_XML1, 'UTF-8');
            $cNgrName = '<c r="H' . $r . '" t="inlineStr"><is><t>' . $ngrName . '</t></is></c>';
            
            $prov = htmlspecialchars((string)($t->nama_provinsi ?? ''), ENT_XML1, 'UTF-8');
            $cProv = '<c r="I' . $r . '" t="inlineStr"><is><t>' . $prov . '</t></is></c>';
            
            $kab = htmlspecialchars((string)($t->nama_kabkota ?? ''), ENT_XML1, 'UTF-8');
            $cKab = '<c r="J' . $r . '" t="inlineStr"><is><t>' . $kab . '</t></is></c>';
            
            $pel = htmlspecialchars((string)($t->nama_pelabuhan ?? ''), ENT_XML1, 'UTF-8');
            $cPel = '<c r="K' . $r . '" t="inlineStr"><is><t>' . $pel . '</t></is></c>';
            
            $cBerat = '<c r="L' . $r . '" s="2"><v>' . sprintf('%.3F', (float)$t->berat) . '</v></c>';
            $cNilai = '<c r="M' . $r . '" s="3"><v>' . sprintf('%.2F', (float)$t->nilai) . '</v></c>';

            fwrite($handle, '<row r="' . $r . '">' .
                $cNo . $cBulan . $cTahun . $cJenis . $cHs . $cHsDesc .
                $cNgrCode . $cNgrName . $cProv . $cKab . $cPel .
                $cBerat . $cNilai .
                '</row>' . "\n");
        }

        fwrite($handle, '</sheetData>' . "\n");
        fwrite($handle, '</worksheet>');
        fclose($handle);

        $zip->addFile($tempSheetPath, 'xl/worksheets/sheet1.xml');
        $zip->close();

        @unlink($tempSheetPath);

        return $totalRows;
    }
}
