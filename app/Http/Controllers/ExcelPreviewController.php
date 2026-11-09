<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

class ExcelPreviewController extends Controller
{
    /**
     * Membaca dan memvalidasi struktur file Excel sebelum diproses.
     * Pada tahap ini, data hanya dibaca untuk pratinjau dan belum disimpan.
     */
    public function preview(Request $request)
    {
        $request->validate([
            'excel_file_final' => 'required|file|mimes:xlsx,xls|max:51200', // max 50MB
            'jenis_data_final' => 'required|in:Ekspor,Impor',
        ], [
            'excel_file_final.required' => 'File Excel wajib dipilih sebelum melakukan pratinjau.',
            'excel_file_final.file' => 'Unggahan harus berupa file yang valid.',
            'excel_file_final.mimes' => 'File harus berekstensi .xlsx atau .xls.',
            'excel_file_final.max' => 'Ukuran file tidak boleh melebihi 50 MB.',
            'jenis_data_final.required' => 'Jenis data (Ekspor/Impor) wajib ditentukan.',
            'jenis_data_final.in' => 'Jenis data harus berupa Ekspor atau Impor.',
        ]);

        $file = $request->file('excel_file_final');
        $jenisData = $request->input('jenis_data_final');

        try {
            $spreadsheet = IOFactory::load($file->getRealPath());

            // Daftar sheet yang dicoba berdasarkan jenis data yang dipilih
            $prioritasSheet = $jenisData === 'Impor'
                ? ['Impor', 'Import', 'RawDataImpor', 'Raw_Data_Impor', 'DataImpor', 'Data_Impor', 'RawData', 'Sheet1']
                : ['Ekspor', 'Export', 'RawDataEkspor', 'Raw_Data_Ekspor', 'DataEkspor', 'Data_Ekspor', 'RawData', 'Sheet1'];

            $availableSheets = $spreadsheet->getSheetNames();
            $sheet = null;
            $namaSheetDitemukan = null;

            // 1. Coba pencarian persis dari daftar prioritas
            foreach ($prioritasSheet as $candidate) {
                if ($spreadsheet->sheetNameExists($candidate)) {
                    $sheet = $spreadsheet->getSheetByName($candidate);
                    $namaSheetDitemukan = $candidate;
                    break;
                }
            }

            // 2. Jika belum ketemu, coba pencarian case-insensitive / partial match
            if (!$sheet) {
                $targetKeyword = strtolower($jenisData); // 'ekspor' atau 'impor'
                $altKeyword = $jenisData === 'Impor' ? 'import' : 'export';

                foreach ($availableSheets as $sName) {
                    $cleanName = strtolower(trim($sName));
                    if (str_contains($cleanName, $targetKeyword) || str_contains($cleanName, $altKeyword)) {
                        $sheet = $spreadsheet->getSheetByName($sName);
                        $namaSheetDitemukan = $sName;
                        break;
                    }
                }
            }

            // 3. Jika masih belum ditemukan, tampilkan pesan informatif beserta daftar sheet yang ada
            if (!$sheet) {
                $spreadsheet->disconnectWorksheets();
                $daftarSheetStr = !empty($availableSheets) ? implode(', ', $availableSheets) : 'Kosong / Tidak Ada Sheet';

                return response()->json([
                    'success' => false,
                    'message' => "Worksheet untuk data {$jenisData} tidak ditemukan. Sheet yang tersedia: [{$daftarSheetStr}]. Silakan periksa file Excel yang dipilih.",
                ], 422);
            }

            $rows = $sheet->toArray(null, true, true, true);

            if (count($rows) < 2) {
                $spreadsheet->disconnectWorksheets();

                return response()->json([
                    'success' => false,
                    'message' => 'File Excel belum memiliki data transaksi.',
                ], 422);
            }

            // Header dinormalisasi agar perbedaan huruf besar-kecil tidak bermasalah.
            $headers = array_map(
                fn ($value) => strtoupper(trim((string) $value)),
                $rows[1] ?? []
            );

            $headerTersedia = array_values(array_filter($headers, fn ($header) => $header !== ''));

            // Periksa keberadaan masing-masing kolom esensial dengan variasi penamaan
            $hasBulan = !empty(array_intersect($headerTersedia, ['BULAN', 'BLN', 'MONTH']));
            $hasTahun = !empty(array_intersect($headerTersedia, ['TAHUN', 'THN', 'YEAR']));
            $hasHs = !empty(array_intersect($headerTersedia, ['KODE HS', 'HS', 'HS_CODE', 'KODEHS']));
            $hasNgr = !empty(array_intersect($headerTersedia, ['KODE_NGR', 'NEGARA', 'KODE_NEGARA', 'NGR']));
            $hasNetto = !empty(array_intersect($headerTersedia, ['NETTO (KG)', 'BERAT', 'NETTO', 'WEIGHT']));
            $hasNilai = !empty(array_intersect($headerTersedia, ['FOB (US$)', 'CIF (US$)', 'NILAI', 'FOB', 'CIF', 'VAL', 'VALUE']));
            $hasProv = !empty(array_intersect($headerTersedia, ['PROVORIG', 'PROPINSI', 'PROVINSI', 'PROPASAL', 'PROV']));

            $kolomHilang = [];
            if (!$hasBulan) $kolomHilang[] = 'BULAN';
            if (!$hasTahun) $kolomHilang[] = 'TAHUN';
            if (!$hasHs) $kolomHilang[] = 'KODE HS';
            if (!$hasNgr) $kolomHilang[] = 'KODE_NGR';
            if (!$hasNetto) $kolomHilang[] = 'NETTO (KG)';
            if (!$hasNilai) $kolomHilang[] = $jenisData === 'Impor' ? 'CIF/FOB/NILAI' : 'FOB (US$)';
            if (!$hasProv) $kolomHilang[] = 'PROVORIG';

            if (!empty($kolomHilang)) {
                $spreadsheet->disconnectWorksheets();

                return response()->json([
                    'success' => false,
                    'message' => 'Kolom wajib tidak ditemukan: ' . implode(', ', $kolomHilang),
                ], 422);
            }

            // Validasi keberadaan kode HS dan Kode Negara pada Master Data
            $hsColKey = null;
            $ngrColKey = null;
            foreach ($headers as $colKey => $colHeader) {
                if (in_array($colHeader, ['KODE HS', 'HS', 'HS_CODE', 'KODEHS'])) {
                    $hsColKey = $colKey;
                }
                if (in_array($colHeader, ['KODE_NGR', 'NEGARA', 'KODE_NEGARA', 'NGR'])) {
                    $ngrColKey = $colKey;
                }
            }

            // Ambil sampel data untuk diperiksa ke master
            $masterHsList = \Illuminate\Support\Facades\DB::table('master_hs')->pluck('hs_code')->flip()->toArray();
            $masterNgrList = \Illuminate\Support\Facades\DB::table('master_negara')->pluck('kode_negara')->flip()->toArray();

            $errorPreview = [];
            $rowCounter = 1;
            foreach ($rows as $rIdx => $rData) {
                if ($rIdx === 1) continue; // lewati header
                $rowCounter++;
                if ($rowCounter > 50) break; // cek 50 baris pertama untuk preview instan

                if ($hsColKey && isset($rData[$hsColKey])) {
                    $rawHs = preg_replace('/[^0-9]/', '', (string)$rData[$hsColKey]);
                    $sub8 = substr($rawHs, 0, 8);
                    $sub6 = substr($rawHs, 0, 6);
                    if ($rawHs !== '' && !isset($masterHsList[$rawHs]) && !isset($masterHsList[$sub8]) && !isset($masterHsList[$sub6])) {
                        $errorPreview[] = "Baris {$rowCounter}: Kode HS '{$rData[$hsColKey]}' tidak ditemukan pada Master HS. Periksa kembali kode pada file Excel atau pastikan Master HS sudah diperbarui.";
                        break;
                    }
                }

                if ($ngrColKey && isset($rData[$ngrColKey])) {
                    $rawNgr = trim((string)$rData[$ngrColKey]);
                    $rawNgrPad = str_pad($rawNgr, 3, '0', STR_PAD_LEFT);
                    if ($rawNgr !== '' && !isset($masterNgrList[$rawNgr]) && !isset($masterNgrList[$rawNgrPad])) {
                        $errorPreview[] = "Baris {$rowCounter}: Kode negara '{$rData[$ngrColKey]}' tidak ditemukan pada Master Negara.";
                        break;
                    }
                }
            }

            if (!empty($errorPreview)) {
                $spreadsheet->disconnectWorksheets();
                return response()->json([
                    'success' => false,
                    'message' => $errorPreview[0],
                ], 422);
            }

            // Hitung baris data, tidak termasuk header.
            $jumlahBaris = max(count($rows) - 1, 0);

            // Ambil beberapa baris pertama untuk ditampilkan sebagai pratinjau.
            $dataPreview = array_slice(array_values($rows), 1, 10);

            $spreadsheet->disconnectWorksheets();

            return response()->json([
                'success' => true,
                'jenis_data' => $jenisData,
                'nama_file' => $file->getClientOriginalName(),
                'nama_sheet' => $namaSheetDitemukan ?? $jenisData,
                'jumlah_baris' => $jumlahBaris,
                'kolom' => array_values($headerTersedia),
                'data_preview' => $dataPreview,
                'message' => 'Struktur file Excel berhasil diperiksa.',
            ]);
        } catch (ReaderException $e) {
            return response()->json([
                'success' => false,
                'message' => 'File Excel tidak dapat dibaca. Periksa kembali format dan isi file.',
            ], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat membaca file Excel.',
            ], 500);
        }
    }
}

