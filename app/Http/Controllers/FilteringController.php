<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FilteringController extends Controller
{
    /**
     * Menampilkan halaman filtering dan penelusuran data transaksi.
     */
    public function index(Request $request)
    {
        $provinsis = DB::table('master_provinsi')->orderBy('nama_provinsi')->get();
        $kabkotas = DB::table('master_kabkota')->orderBy('nama_kabkota')->get();
        $negaras = DB::table('master_negara')->orderBy('nama_negara')->get();
        $hs_codes = DB::table('master_hs')
            ->select('hs2', 'hs2_desc')
            ->whereNotNull('hs2')
            ->distinct()
            ->orderBy('hs2')
            ->get();

        $pelabahans = DB::table('master_pelabuhan')->orderBy('nama_pelabuhan')->get();

        return view('pages.filtering-data', compact('provinsis', 'kabkotas', 'negaras', 'hs_codes', 'pelabahans'));
    }

    /**
     * API Query penelusuran data transaksi berdasarkan kriteria filter.
     */
    public function query(Request $request)
    {
        $currentUser = Auth::user();
        $isSuperAdmin = $currentUser && ($currentUser->role_id === 1);
        $isViewerEksekutif = $currentUser && ($currentUser->role_id === 3 || ($currentUser->role && $currentUser->role->nama_role === 'Viewer Eksekutif'));

        // Validasi ketat parameter tipe_sumber (Hanya izinkan 'distribusi' atau 'neraca', default: 'distribusi')
        $tipeSumber = strtolower((string) $request->input('tipe_sumber', 'distribusi'));
        if (!in_array($tipeSumber, ['distribusi', 'neraca'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter tipe_sumber tidak valid. Hanya menerima "distribusi" atau "neraca".',
            ], 422);
        }

        $txTable = ($tipeSumber === 'neraca') ? 'neraca_transactions' : 'distribusi_transactions';

        $query = DB::table("{$txTable} as t")
            ->join('data_batches', 't.batch_id', '=', 'data_batches.batch_id')
            ->leftJoin('master_hs', 't.hs_id', '=', 'master_hs.hs_id')
            ->leftJoin('master_negara', 't.negara_id', '=', 'master_negara.negara_id')
            ->leftJoin('master_provinsi', 't.provinsi_id', '=', 'master_provinsi.provinsi_id')
            ->leftJoin('master_kabkota', 't.kabkota_id', '=', 'master_kabkota.kabkota_id')
            ->leftJoin('master_pelabuhan', 't.pelabuhan_id', '=', 'master_pelabuhan.pelabuhan_id')
            ->where('t.tipe_sumber', $tipeSumber)
            ->where('data_batches.tipe_sumber', $tipeSumber)
            ->whereNotIn('t.batch_id', function ($sub) {
                $sub->select('batch_id')->from('trash')->whereNull('restored_at');
            });

        // Aturan Hak Akses:
        // - Pegawai (role 2): HANYA melihat data berstatus RILIS!
        // - Viewer Eksekutif (role 3): melihat data RILIS dan PRIVATE!
        // - Super Admin: melihat semua status atau sesuai filter status
        if (!$isSuperAdmin) {
            if ($isViewerEksekutif) {
                $query->whereIn('data_batches.status', ['RILIS', 'PRIVATE']);
            } else {
                $query->where('data_batches.status', 'RILIS');
            }
        } else {
            // Jika Super Admin memfilter status tertentu
            if ($request->filled('status')) {
                $query->where('data_batches.status', strtoupper($request->status));
            }
        }

        // Filter Jenis Transaksi (Ekspor / Impor)
        if ($request->filled('jenis_transaksi')) {
            $query->where('t.jenis_transaksi', $request->jenis_transaksi);
        }

        // Filter Tahun
        if ($request->filled('tahun')) {
            $query->where('t.tahun', (int) $request->tahun);
        }

        // Filter Bulan
        if ($request->filled('bulan') && $request->bulan !== 'ALL' && $request->bulan !== '') {
            $query->where('t.bulan', (int) $request->bulan);
        }

        // Filter Triwulan (Q1: 1-3, Q2: 4-6, Q3: 7-9, Q4: 10-12)
        if ($request->filled('triwulan') && $request->triwulan !== 'ALL' && $request->triwulan !== '') {
            $tw = (int) $request->triwulan;
            $twMonths = match ($tw) {
                1 => [1, 2, 3],
                2 => [4, 5, 6],
                3 => [7, 8, 9],
                4 => [10, 11, 12],
                default => null,
            };
            if ($twMonths && !$request->filled('bulan')) {
                $query->whereIn('t.bulan', $twMonths);
            }
        }

        // Filter Kabupaten / Kota
        if ($request->filled('kabkota_id') && $request->kabkota_id !== 'ALL' && $request->kabkota_id !== '') {
            $query->where('t.kabkota_id', (int) $request->kabkota_id);
        }

        // Filter Negara
        if ($request->filled('negara_id') && $request->negara_id !== 'ALL') {
            $query->where('t.negara_id', (int) $request->negara_id);
        }

        // Filter HS 2 digit (untuk Neraca juga mencakup hs_code_raw yang unmapped)
        if ($request->filled('hs2') && $request->hs2 !== 'ALL') {
            $hs2Filter = trim((string) $request->hs2);
            if ($tipeSumber === 'neraca') {
                $query->where(function ($q) use ($hs2Filter) {
                    $q->where('master_hs.hs2', $hs2Filter)
                        ->orWhere(function ($sub) use ($hs2Filter) {
                            $sub->whereNull('t.hs_id')
                                ->whereRaw('SUBSTR(t.hs_code_raw, 1, 2) = ?', [$hs2Filter]);
                        });
                });
            } else {
                $query->where('master_hs.hs2', $hs2Filter);
            }
        }

        // Filter Pelabuhan
        if ($request->filled('pelabuhan_id') && $request->pelabuhan_id !== 'ALL') {
            $query->where('t.pelabuhan_id', (int) $request->pelabuhan_id);
        }

        // Hitung total ringkasan dengan presisi DECIMAL(20,6) via BCMath
        $summaryAgg = (clone $query)
            ->selectRaw('COUNT(*) as total_rows, COALESCE(SUM(t.nilai), 0) as total_nilai, COALESCE(SUM(t.berat), 0) as total_berat')
            ->first();

        $totalRows = (int) ($summaryAgg->total_rows ?? 0);
        $totalNilaiExact = bcadd((string) ($summaryAgg->total_nilai ?? '0'), '0', 6);
        $totalBeratExact = bcadd((string) ($summaryAgg->total_berat ?? '0'), '0', 6);
        $totalNilaiJuta = round((float) bcdiv($totalNilaiExact, '1000000', 6), 2);
        $totalBeratRibuTon = round((float) bcdiv($totalBeratExact, '1000000', 6), 2);

        $selectColumns = [
            't.transaction_id',
            't.batch_id',
            't.jenis_transaksi',
            't.bulan',
            't.tahun',
            't.berat',
            't.nilai',
            $tipeSumber === 'neraca'
                ? DB::raw('COALESCE(master_hs.hs_code, t.hs_code_raw) as hs_code')
                : 'master_hs.hs_code',
            'master_hs.hs2_desc',
            $tipeSumber === 'neraca'
                ? DB::raw('COALESCE(master_hs.hs_desc, t.hs_desc_raw) as hs_desc')
                : 'master_hs.hs_desc',
            'master_negara.nama_negara',
            'master_provinsi.nama_provinsi',
            'master_kabkota.nama_kabkota',
            'master_pelabuhan.nama_pelabuhan',
            'data_batches.nama_file',
            'data_batches.status as batch_status',
        ];

        if ($tipeSumber === 'neraca') {
            $selectColumns[] = 't.mapping_status';
        }

        $perPage = in_array((int) $request->input('per_page', 10), [10, 25, 50, 100], true)
            ? (int) $request->input('per_page', 10)
            : 10;

        // Ambil data dengan pagination
        $results = $query->select($selectColumns)
            ->orderBy('t.tahun', 'desc')
            ->orderBy('t.bulan', 'desc')
            ->orderBy('t.transaction_id', 'desc')
            ->paginate($perPage);

        $jenisFilter = strtolower(trim((string) $request->input('jenis_transaksi', '')));
        $nilaiLabel = match ($jenisFilter) {
            'ekspor' => 'FOB (US$)',
            'impor' => 'CIF (US$)',
            default => 'Campuran Ekspor (FOB) & Impor (CIF)',
        };

        return response()->json([
            'success' => true,
            'summary' => [
                'tipe_sumber' => $tipeSumber,
                'nilai_label' => $nilaiLabel,
                'total_rows' => $totalRows,
                'total_nilai_fob' => (float) $totalNilaiExact,
                'total_nilai_fob_exact' => $totalNilaiExact,
                'total_nilai_fob_juta' => $totalNilaiJuta,
                'total_berat_netto' => (float) $totalBeratExact,
                'total_berat_netto_exact' => $totalBeratExact,
                'total_berat_ribu_ton' => $totalBeratRibuTon,
            ],
            'data' => $results,
        ]);
    }
}
