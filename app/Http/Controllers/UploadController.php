<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UploadController extends Controller
{
    public function index()
    {
        // Mengambil semua data dari tabel master menggunakan DB Facade
        $provinsis = DB::table('master_provinsi')->orderBy('nama_provinsi', 'asc')->get();
        $kabkotas = DB::table('master_kabkota')->orderBy('nama_kabkota', 'asc')->get();
        $negaras = DB::table('master_negara')->orderBy('nama_negara', 'asc')->get();

        $hs_codes = DB::table('master_hs')
            ->select('hs2', 'hs2_desc')
            ->whereNotNull('hs2')
            ->distinct()
            ->orderBy('hs2', 'asc')
            ->get();

        return view('upload-data', compact('provinsis', 'kabkotas', 'negaras', 'hs_codes'));
    }

    public function store(Request $request)
    {
        // 1. Validasi Input (Pastikan file yang diunggah benar-benar Excel)
        $request->validate([
            'excel_file_final' => 'required|file|mimes:xlsx,xls',
            'jenis_data_final' => 'required|string',
        ]);

        // 2. Ambil File dari Form
        $file = $request->file('excel_file_final');

        // Dapatkan nama asli file dan hitung ukurannya dalam MB
        $namaFile = $file->getClientOriginalName();
        $ukuranMB = round($file->getSize() / 1048576, 2); // 1048576 = 1024 * 1024

        // 3. (Opsional) Menyimpan file fisik secara permanen ke folder storage Laravel
        // $path = $file->storeAs('uploads/excel', $namaFile, 'public');

        // 4. Simpan Informasi File ke Tabel data_batches
        DB::table('data_batches')->insert([
            'nama_file' => $namaFile,
            'jenis_data' => $request->jenis_data_final,
            'ukuran_file' => $ukuranMB,
            'status' => 'DRAFT', // Sesuai kesepakatan status awal
            'uploaded_by' => auth()->id() ?? 1, // Mengambil ID User yang login (default 1 jika auth kosong)
            'uploaded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /*
         * Catatan: Untuk input filter seperti negara_final, hscode_final, dll.
         * Nantinya akan kita gunakan saat membuat logika pembacaan isi baris Excel
         * untuk dimasukkan ke tabel 'transactions'.
         */

        // 5. Redirect ke halaman Manajemen Data dengan pesan sukses
        return redirect()->route('manajemen-data')
            ->with('success', "File {$namaFile} berukuran {$ukuranMB} MB berhasil diunggah dan disimpan sebagai DRAFT.");
    }

    public function manajemen()
    {
        $totalFile = DB::table('data_batches')->count();
        // Ubah pencarian status agar sesuai dengan ENUM di database Anda
        $terverifikasi = DB::table('data_batches')->where('status', 'RILIS')->count();
        $pending = DB::table('data_batches')->where('status', 'DRAFT')->count();

        $totalUkuranMB = DB::table('data_batches')->sum('ukuran_file');
        $totalUkuranGB = $totalUkuranMB > 1024
                            ? round($totalUkuranMB / 1024, 1).' GB'
                            : round($totalUkuranMB, 1).' MB';

        $dokumens = DB::table('data_batches')->orderBy('created_at', 'desc')->paginate(5);

        return view('manajemen-data', compact('totalFile', 'terverifikasi', 'pending', 'totalUkuranGB', 'dokumens'));
    }
}
