<?php

namespace App\Http\Controllers;

use App\Services\StatisticsCalculationService;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

class ExcelPreviewController extends Controller
{
    protected StatisticsCalculationService $statService;

    public function __construct(StatisticsCalculationService $statService)
    {
        $this->statService = $statService;
    }
    /**
     * Membaca dan memvalidasi struktur file Excel sebelum diproses.
     * Pada tahap ini, data hanya dibaca untuk pratinjau dan belum disimpan.
     */
    public function preview(Request $request)
    {
        $currentUser = \Illuminate\Support\Facades\Auth::user();
        $isSuperAdmin = $currentUser && ($currentUser->role_id === 1 || ($currentUser->role && $currentUser->role->nama_role === 'Super Admin'));

        if (!$isSuperAdmin) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Akses ditolak. Pratinjau data hanya dapat diakses oleh Super Admin.'], 403);
            }
            abort(403, 'Akses ditolak. Pratinjau data hanya dapat diakses oleh Super Admin.');
        }

        $file = $request->file('excel_file_final');
        $originalName = $file ? $file->getClientOriginalName() : '';
        $ext = $file ? strtolower($file->getClientOriginalExtension()) : '';
        if (!$ext && $originalName) {
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        }

        // Jika ekstensi tidak terbaca (misal di Windows), cek byte header DBF langsung (0x03)
        $isDbf = ($ext === 'dbf');
        if (!$isDbf && $file && file_exists($file->getRealPath())) {
            $fp = @fopen($file->getRealPath(), 'rb');
            if ($fp) {
                $firstByte = ord(fread($fp, 1));
                fclose($fp);
                if (in_array($firstByte, [0x03, 0x83, 0x8B])) {
                    $isDbf = true;
                }
            }
        }

        // Jika file berformat .dbf, alihkan langsung ke validasi DBF (Distribusi / Neraca)
        if ($isDbf) {
            $request->validate([
                'excel_file_final' => 'required|file|max:51200',
                'jenis_data_final' => 'required|in:Ekspor,Impor',
                'sumber_data_final' => 'nullable|in:Distribusi,Neraca,distribusi,neraca',
            ], [
                'excel_file_final.required' => 'File DBF wajib dipilih sebelum melakukan pratinjau.',
                'excel_file_final.max' => 'Ukuran file tidak boleh melebihi 50 MB.',
                'jenis_data_final.required' => 'Jenis data (Ekspor/Impor) wajib ditentukan.',
            ]);

            try {
                $brsService = app(\App\Services\BrsDataProcessingService::class);
                $jenisStr = strtolower($request->input('jenis_data_final'));
                $sumberStr = strtolower($request->input('sumber_data_final', 'distribusi'));
                if (!$sumberStr) {
                    $sumberStr = 'distribusi';
                }

                $previewResult = $brsService->generatePreview(
                    $file->getRealPath(),
                    $file->getClientOriginalName(),
                    $sumberStr,
                    $jenisStr,
                    15
                );

                $meta = $previewResult['meta'];
                $kpi = $previewResult['kpi'];
                $unmapped = $previewResult['unmapped'];
                $sektorSummary = $previewResult['sektor_summary'];
                $becSummary = $previewResult['bec_summary'];
                $topHs2 = $previewResult['top_hs2'];
                $topNegara = $previewResult['top_negara'];
                $topPelabuhan = $previewResult['top_pelabuhan'];
                $topKabkota = $previewResult['top_kabkota'] ?? [];
                $sampleRows = $previewResult['sample_rows'];
                $detailedErrors = $previewResult['errors'];

                $tahun = (int)($meta['tahun'] ?? date('Y'));
                $bulan = (int)($meta['bulan'] ?? date('n'));
                $jenisCapitalized = ucfirst($jenisStr);

                // Ambil agregasi historis dari basis data untuk tahun ini & tahun lalu (HANYA batch RILIS & non-trash)
                $historicalMonthly = [
                    $tahun => array_fill(1, 12, 0.0),
                    $tahun - 1 => array_fill(1, 12, 0.0),
                ];

                if ($sumberStr === 'distribusi') {
                    $summariesQuery = \Illuminate\Support\Facades\DB::table('monthly_trade_summaries as mts')
                        ->where('mts.jenis_transaksi', $jenisCapitalized)
                        ->where('mts.tipe_sumber', 'distribusi')
                        ->whereIn('mts.tahun', [$tahun, $tahun - 1])
                        ->where(function ($q) {
                            $q->where('mts.tahun', '<', 2026)
                              ->orWhere(function ($q2) {
                                  $q2->where('mts.tahun', 2026)
                                     ->where('mts.bulan', '<', 7);
                              })
                              ->orWhere('mts.pelabuhan_key', '>', 0);
                        });
                    $this->statService->applyMonthlySummaryVisibility($summariesQuery, ['RILIS'], 'mts');

                    $summaries = $summariesQuery
                        ->select('mts.bulan', 'mts.tahun', \Illuminate\Support\Facades\DB::raw('SUM(mts.total_nilai) as nilai'))
                        ->groupBy('mts.tahun', 'mts.bulan')
                        ->get();

                    foreach ($summaries as $s) {
                        $y = (int)$s->tahun;
                        $m = (int)$s->bulan;
                        if (isset($historicalMonthly[$y]) && $m >= 1 && $m <= 12) {
                            $historicalMonthly[$y][$m] = (float)$s->nilai;
                        }
                    }
                } else {
                    // Sumber Neraca: ambil historis dari neraca_transactions
                    $neracaSummaries = \Illuminate\Support\Facades\DB::table('neraca_transactions as t')
                        ->join('data_batches as b', 't.batch_id', '=', 'b.batch_id')
                        ->where('b.status', 'RILIS')
                        ->where('b.tipe_sumber', 'neraca')
                        ->where('t.jenis_transaksi', $jenisCapitalized)
                        ->whereIn('t.tahun', [$tahun, $tahun - 1])
                        ->whereNotIn('t.batch_id', function ($query) {
                            $query->select('batch_id')->from('trash')->whereNull('restored_at');
                        })
                        ->select('t.bulan', 't.tahun', \Illuminate\Support\Facades\DB::raw('SUM(t.nilai) as nilai'))
                        ->groupBy('t.tahun', 't.bulan')
                        ->get();

                    foreach ($neracaSummaries as $ns) {
                        $y = (int)$ns->tahun;
                        $m = (int)$ns->bulan;
                        if (isset($historicalMonthly[$y]) && $m >= 1 && $m <= 12) {
                            $historicalMonthly[$y][$m] = (float)$ns->nilai;
                        }
                    }
                }

                // Injeksi nilai transaksi aktual dari file DBF yang sedang dipratinjau untuk bulannya
                $tempFob = (float)$kpi['total_nilai_usd'];
                $tempNetto = (float)$kpi['total_berat_kg'];
                $tempMigasFob = (float)$kpi['migas_nilai_usd'];
                $tempNonMigasFob = (float)$kpi['non_migas_nilai_usd'];
                $tempMigasNetto = (float)($kpi['migas_berat_kg'] ?? 0);
                $tempNonMigasNetto = (float)($kpi['non_migas_berat_kg'] ?? 0);

                $historicalMonthly[$tahun][$bulan] = $tempFob;

                $namaBulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                $monthlyFob = array_fill(1, 12, 0.0);
                $monthlyNetto = array_fill(1, 12, 0.0);
                $monthlyMigasFob = array_fill(1, 12, 0.0);
                $monthlyNonMigasFob = array_fill(1, 12, 0.0);
                $monthlyMigasNetto = array_fill(1, 12, 0.0);
                $monthlyNonMigasNetto = array_fill(1, 12, 0.0);

                // Isi nilai aktual bulan yang sedang diunggah secara presisi dari file
                $monthlyFob[$bulan] = $tempFob;
                $monthlyNetto[$bulan] = $tempNetto;
                $monthlyMigasFob[$bulan] = $tempMigasFob;
                $monthlyNonMigasFob[$bulan] = $tempNonMigasFob;
                $monthlyMigasNetto[$bulan] = $tempMigasNetto;
                $monthlyNonMigasNetto[$bulan] = $tempNonMigasNetto;

                // Ambil data bulanan historis riil HANYA jika ada di basis data (tanpa asumsi proporsi statis)
                for ($m = 1; $m <= 12; $m++) {
                    if ($m !== $bulan && isset($historicalMonthly[$tahun][$m]) && $historicalMonthly[$tahun][$m] > 0) {
                        $monthlyFob[$m] = (float)$historicalMonthly[$tahun][$m];
                    }
                }

                // Indikator Pertumbuhan
                $prevM = ($bulan === 1) ? 12 : ($bulan - 1);
                $prevY = ($bulan === 1) ? ($tahun - 1) : $tahun;
                $valCurrM = $monthlyFob[$bulan] ?? 0;
                $valPrevM = ($prevY === $tahun) ? ($monthlyFob[$prevM] ?? 0) : ($historicalMonthly[$prevY][$prevM] ?? 0);
                $calcMtom = ($valCurrM > 0 && $valPrevM > 0) ? round((($valCurrM - $valPrevM) / $valPrevM) * 100, 2) : null;

                // Kebijakan B QoQ: Wajib 3 bulan penuh pada triwulan berjalan dan triwulan tepat sebelumnya
                $currQ = (int) ceil($bulan / 3);
                $qCurrStart = ($currQ - 1) * 3 + 1;
                $qCurrEnd = $currQ * 3;
                $qCurrComplete = true;
                $qCurrSum = '0.000000';
                for ($m = $qCurrStart; $m <= $qCurrEnd; $m++) {
                    $mVal = (float) ($monthlyFob[$m] ?? 0);
                    if ($mVal <= 0) {
                        $qCurrComplete = false;
                        break;
                    }
                    $qCurrSum = bcadd($qCurrSum, bcadd((string) $mVal, '0', 6), 6);
                }

                $prevQ = ($currQ === 1) ? 4 : ($currQ - 1);
                $prevQYear = ($currQ === 1) ? ($tahun - 1) : $tahun;
                $qPrevStart = ($prevQ - 1) * 3 + 1;
                $qPrevEnd = $prevQ * 3;
                $qPrevComplete = true;
                $qPrevSum = '0.000000';
                for ($m = $qPrevStart; $m <= $qPrevEnd; $m++) {
                    $mVal = (float) (($prevQYear === $tahun) ? ($monthlyFob[$m] ?? 0) : ($historicalMonthly[$prevQYear][$m] ?? 0));
                    if ($mVal <= 0) {
                        $qPrevComplete = false;
                        break;
                    }
                    $qPrevSum = bcadd($qPrevSum, bcadd((string) $mVal, '0', 6), 6);
                }

                $calcQtoq = ($qCurrComplete && $qPrevComplete)
                    ? $this->statService->calcBpsGrowth($qCurrSum, $qPrevSum)
                    : null;

                $valLastYearSameM = $historicalMonthly[$tahun - 1][$bulan] ?? 0;
                $calcYony = ($valCurrM > 0 && $valLastYearSameM > 0) ? round((($valCurrM - $valLastYearSameM) / $valLastYearSameM) * 100, 2) : null;

                $cumCurr = 0.0;
                $cumPrev = 0.0;
                for ($m = 1; $m <= $bulan; $m++) {
                    $cumCurr += $monthlyFob[$m] ?? 0;
                    $cumPrev += $historicalMonthly[$tahun - 1][$m] ?? 0;
                }
                $calcCtoc = ($cumCurr > 0 && $cumPrev > 0) ? round((($cumCurr - $cumPrev) / $cumPrev) * 100, 2) : null;

                // Susun Top Items untuk Komponen Dashboard & Tabel
                $simTopKomoditi = array_map(function ($h) {
                    return [
                        'item' => $h['code'] . ' - ' . $h['desc'],
                        'fob' => (float)$h['nilai_usd'],
                        'netto' => (float)$h['volume_ribu_ton'] * 1000000,
                        'code' => $h['code'],
                        'desc' => $h['desc'],
                        'nilai_juta_usd' => $h['nilai_juta_usd'],
                        'volume_ribu_ton' => $h['volume_ribu_ton'],
                        'share_pct' => $h['share_pct'],
                    ];
                }, $topHs2);

                $simTopNegara = array_map(function ($n) {
                    return [
                        'item' => $n['item'],
                        'fob' => (float)$n['nilai_usd'],
                        'netto' => (float)$n['volume_ribu_ton'] * 1000000,
                        'nilai_juta_usd' => $n['nilai_juta_usd'],
                        'volume_ribu_ton' => $n['volume_ribu_ton'],
                        'share_pct' => $n['share_pct'],
                    ];
                }, $topNegara);

                $simTopPelabuhan = array_map(function ($p) {
                    return [
                        'item' => $p['item'],
                        'fob' => (float)$p['nilai_usd'],
                        'netto' => (float)$p['volume_ribu_ton'] * 1000000,
                        'nilai_juta_usd' => $p['nilai_juta_usd'],
                        'volume_ribu_ton' => $p['volume_ribu_ton'],
                        'share_pct' => $p['share_pct'],
                    ];
                }, $topPelabuhan);

                $simTopKabkota = array_map(function ($k) {
                    return [
                        'kabkota' => $k['kabkota'],
                        'fob' => (float)$k['fob'],
                        'netto' => (float)$k['netto'],
                        'nilai_juta_usd' => $k['nilai_juta_usd'],
                        'volume_ribu_ton' => $k['volume_ribu_ton'],
                    ];
                }, $topKabkota);

                // Susun Tabel Perkembangan Time Series Komoditas
                $pivotBulanFob = [];
                $pivotBulanNetto = [];
                $pivotTriwulanFob = [];

                foreach (array_slice($topHs2, 0, 10) as $h) {
                    $fobVals = array_fill(0, 12, 0.0);
                    $fobVals[$bulan - 1] = (float)$h['nilai_usd'];
                    $nettoVals = array_fill(0, 12, 0.0);
                    $nettoVals[$bulan - 1] = (float)$h['volume_ribu_ton'] * 1000000;

                    $qVals = array_fill(0, 4, 0.0);
                    $currQIndex = max(0, min(3, (int)ceil($bulan / 3) - 1));
                    $qVals[$currQIndex] = (float)$h['nilai_usd'];

                    $pivotBulanFob[] = [
                        'komoditi' => $h['code'] . ' - ' . $h['desc'],
                        'values' => $fobVals,
                        'total' => (float)$h['nilai_usd'],
                        'mom_growth' => null,
                    ];
                    $pivotBulanNetto[] = [
                        'komoditi' => $h['code'] . ' - ' . $h['desc'],
                        'values' => $nettoVals,
                        'total' => (float)$h['volume_ribu_ton'] * 1000000,
                        'mom_growth' => null,
                    ];
                    $pivotTriwulanFob[] = [
                        'komoditi' => $h['code'] . ' - ' . $h['desc'],
                        'values' => $qVals,
                        'total' => (float)$h['nilai_usd'],
                    ];
                }

                $simulatedDashboard = [
                    'summary' => [
                        'total_fob' => $tempFob,
                        'total_netto' => $tempNetto,
                        'avg_per_transaksi' => $meta['baris_valid'] > 0 ? round($tempFob / $meta['baris_valid'], 2) : 0,
                        'total_negara' => count($topNegara),
                        'total_komoditi' => count($topHs2),
                        'migas_fob' => $tempMigasFob,
                        'non_migas_fob' => $tempNonMigasFob,
                        'migas_netto' => $tempMigasNetto,
                        'non_migas_netto' => $tempNonMigasNetto,
                        'mtom' => $calcMtom,
                        'qtoq' => $calcQtoq,
                        'yony' => $calcYony,
                        'ctoc' => $calcCtoc,
                    ],
                    'monthly' => [
                        'labels' => array_slice($namaBulan, 1),
                        'fobValues' => array_values($monthlyFob),
                        'nettoValues' => array_values($monthlyNetto),
                        'migasFob' => array_values($monthlyMigasFob),
                        'nonMigasFob' => array_values($monthlyNonMigasFob),
                        'migasNetto' => array_values($monthlyMigasNetto),
                        'nonMigasNetto' => array_values($monthlyNonMigasNetto),
                    ],
                    'top_komoditi' => $simTopKomoditi,
                    'top_negara' => $simTopNegara,
                    'top_pelabuhan' => $simTopPelabuhan,
                    'kabkota_distribusi' => $simTopKabkota,
                    'pivot_bulan_fob' => $pivotBulanFob,
                    'pivot_bulan_netto' => $pivotBulanNetto,
                    'pivot_triwulan_fob' => $pivotTriwulanFob,
                    'sektor_summary' => $sektorSummary,
                    'bec_summary' => $becSummary,
                    'unmapped' => $unmapped,
                ];

                // Kembalikan format serasi untuk frontend SIDATEX dan API integration test
                return response()->json([
                    'success' => true,
                    'format' => 'DBF',
                    'sumber_data' => $sumberStr,
                    'jenis_data' => $request->input('jenis_data_final'),
                    'nama_file' => $file->getClientOriginalName(),
                    'jumlah_baris' => $meta['baris_valid'],
                    'tahun' => $meta['tahun'],
                    'bulan' => $meta['bulan'],
                    'total_nilai' => $kpi['total_nilai_juta_usd'],
                    'total_berat' => $kpi['total_berat_ribu_ton'],
                    'historical_monthly' => $historicalMonthly,
                    'data' => [
                        'nama_file' => $meta['nama_file'],
                        'sumber_data' => $sumberStr,
                        'jenis_transaksi' => $jenisStr,
                        'tahun' => $meta['tahun'],
                        'bulan' => $meta['bulan'],
                        'total_baris' => $meta['total_baris'],
                        'baris_valid' => $meta['baris_valid'],
                        'baris_invalid' => $meta['baris_invalid'],
                        'total_nilai_usd' => (float)$kpi['total_nilai_usd'],
                        'total_berat_kg' => (float)$kpi['total_berat_kg'],
                        'total_nilai_juta_usd' => $kpi['total_nilai_juta_usd'],
                        'total_berat_ribu_ton' => $kpi['total_berat_ribu_ton'],
                        'migas_nilai_usd' => (float)$kpi['migas_nilai_usd'],
                        'non_migas_nilai_usd' => (float)$kpi['non_migas_nilai_usd'],
                        'ringkasan_error' => $meta['ringkasan_error'] ?? [],
                        'errors' => $detailedErrors,
                        'preview_sample' => $sampleRows,
                        'unmapped' => $unmapped,
                        'sektor_summary' => $sektorSummary,
                        'bec_summary' => $becSummary,
                        'duplikasi_batch' => $meta['is_existing_batch'] ? [
                            'exists' => true,
                            'batch_id' => $meta['existing_batch_id'],
                        ] : null,
                    ],
                    'simulated_dashboard' => $simulatedDashboard,
                    'message' => 'Berkas DBF berhasil diproses oleh BRS Data Processing Engine dan siap dipratinjau.',
                ]);
            } catch (\Throwable $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal membaca berkas DBF: ' . $e->getMessage(),
                ], 422);
            }
        }

        $request->validate([
            'excel_file_final' => 'required|file|mimes:xlsx,xls|max:51200', // max 50MB
            'jenis_data_final' => 'required|in:Ekspor,Impor',
        ], [
            'excel_file_final.required' => 'File Excel wajib dipilih sebelum melakukan pratinjau.',
            'excel_file_final.file' => 'Unggahan harus berupa file yang valid.',
            'excel_file_final.mimes' => 'File harus berekstensi .xlsx, .xls, atau .dbf.',
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

            // Ambil data tren bulanan historis untuk visualisasi komparatif simulasi Excel
            $historicalMonthly = [];
            $currentYear = (int)date('Y');
            $histYears = [$currentYear, $currentYear - 1, $currentYear - 2];
            $histRecordsQuery = \Illuminate\Support\Facades\DB::table('monthly_trade_summaries as mts')
                ->where('mts.jenis_transaksi', ucfirst(strtolower($jenisData)))
                ->where('mts.tipe_sumber', 'distribusi')
                ->whereIn('mts.tahun', $histYears)
                ->where(function ($q) {
                    $q->where('mts.tahun', '<', 2026)
                      ->orWhere(function ($q2) {
                          $q2->where('mts.tahun', 2026)
                             ->where('mts.bulan', '<', 7);
                      })
                      ->orWhere('mts.pelabuhan_key', '>', 0);
                });
            $this->statService->applyMonthlySummaryVisibility($histRecordsQuery, ['RILIS'], 'mts');

            $histRecords = $histRecordsQuery
                ->select('mts.tahun', 'mts.bulan', \Illuminate\Support\Facades\DB::raw('SUM(mts.total_nilai) as total_fob'))
                ->groupBy('mts.tahun', 'mts.bulan')
                ->get();

            foreach ($histYears as $yr) {
                $historicalMonthly[$yr] = array_fill(1, 12, 0);
            }
            foreach ($histRecords as $rec) {
                $historicalMonthly[(int)$rec->tahun][(int)$rec->bulan] = (float)$rec->total_fob;
            }

            $spreadsheet->disconnectWorksheets();

            return response()->json([
                'success' => true,
                'jenis_data' => $jenisData,
                'nama_file' => $file->getClientOriginalName(),
                'nama_sheet' => $namaSheetDitemukan ?? $jenisData,
                'jumlah_baris' => $jumlahBaris,
                'kolom' => array_values($headerTersedia),
                'data_preview' => $dataPreview,
                'historical_monthly' => $historicalMonthly,
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

