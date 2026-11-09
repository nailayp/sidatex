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

        $query = DB::table('transactions')
            ->join('data_batches', 'transactions.batch_id', '=', 'data_batches.batch_id')
            ->leftJoin('master_hs', 'transactions.hs_id', '=', 'master_hs.hs_id')
            ->leftJoin('master_negara', 'transactions.negara_id', '=', 'master_negara.negara_id')
            ->leftJoin('master_provinsi', 'transactions.provinsi_id', '=', 'master_provinsi.provinsi_id')
            ->leftJoin('master_kabkota', 'transactions.kabkota_id', '=', 'master_kabkota.kabkota_id')
            ->leftJoin('master_pelabuhan', 'transactions.pelabuhan_id', '=', 'master_pelabuhan.pelabuhan_id');

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
            $query->where('transactions.jenis_transaksi', $request->jenis_transaksi);
        }

        // Filter Tahun
        if ($request->filled('tahun')) {
            $query->where('transactions.tahun', (int) $request->tahun);
        }

        // Filter Bulan
        if ($request->filled('bulan')) {
            $query->where('transactions.bulan', (int) $request->bulan);
        }

        // Filter Negara
        if ($request->filled('negara_id') && $request->negara_id !== 'ALL') {
            $query->where('transactions.negara_id', (int) $request->negara_id);
        }

        // Filter HS 2 digit
        if ($request->filled('hs2') && $request->hs2 !== 'ALL') {
            $query->where('master_hs.hs2', $request->hs2);
        }

        // Filter Pelabuhan
        if ($request->filled('pelabuhan_id') && $request->pelabuhan_id !== 'ALL') {
            $query->where('transactions.pelabuhan_id', (int) $request->pelabuhan_id);
        }

        // Hitung total ringkasan
        $totalRows = (clone $query)->count();
        $totalNilaiFob = (clone $query)->sum('transactions.nilai');
        $totalBeratNetto = (clone $query)->sum('transactions.berat');

        // Ambil data dengan pagination
        $results = $query->select(
            'transactions.transaction_id',
            'transactions.batch_id',
            'transactions.jenis_transaksi',
            'transactions.bulan',
            'transactions.tahun',
            'transactions.berat',
            'transactions.nilai',
            'master_hs.hs_code',
            'master_hs.hs2_desc',
            'master_hs.hs_desc',
            'master_negara.nama_negara',
            'master_provinsi.nama_provinsi',
            'master_kabkota.nama_kabkota',
            'master_pelabuhan.nama_pelabuhan',
            'data_batches.nama_file',
            'data_batches.status as batch_status'
        )
        ->orderBy('transactions.tahun', 'desc')
        ->orderBy('transactions.bulan', 'desc')
        ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'summary' => [
                'total_rows' => $totalRows,
                'total_nilai_fob' => (float) $totalNilaiFob,
                'total_nilai_fob_juta' => round($totalNilaiFob / 1000000, 2),
                'total_berat_netto' => (float) $totalBeratNetto,
                'total_berat_ribu_ton' => round($totalBeratNetto / 1000000, 2),
            ],
            'data' => $results,
        ]);
    }
}
