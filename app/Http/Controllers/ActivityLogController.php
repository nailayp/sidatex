<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ActivityLogController extends Controller
{
    /**
     * Menampilkan riwayat log aktivitas pengguna SIDATEX.
     * Sesuai ketentuan, data diambil langsung dari tabel activity_logs.
     * Akses dibuka untuk pengguna terautentikasi (Super Admin & Pegawai).
     */
    public function index(Request $request)
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            return redirect()->route('login');
        }

        $isSuperAdmin = ($currentUser->role_id === 1 || ($currentUser->role && $currentUser->role->nama_role === 'Super Admin'));
        $isPegawai = ($currentUser->role_id === 2 || ($currentUser->role && $currentUser->role->nama_role === 'Pegawai'));
        $isViewerEksekutif = ($currentUser->role_id === 3 || ($currentUser->role && $currentUser->role->nama_role === 'Viewer Eksekutif'));

        // viewer eksekutif: tidak memiliki hak akses melihat audit trail internal sistem
        if ($isViewerEksekutif) {
            return redirect()->route('dashboard')->with('error', 'Akses ditolak. Viewer Eksekutif hanya memiliki akses pemantauan dashboard.');
        }

        $query = ActivityLog::with(['user.role'])
            ->leftJoin('users', 'activity_logs.user_id', '=', 'users.id')
            ->select('activity_logs.*');

        // Pembatasan data:
        // - Super Admin: Dapat melihat seluruh audit trail sistem (semua user & modul)
        // - Pegawai: Hanya melihat log aktivitas yang dilakukannya sendiri atau aktivitas modul operasional miliknya
        if (!$isSuperAdmin) {
            $query->where('activity_logs.user_id', $currentUser->id);
        }

        // Filter Pencarian (keyword di aktivitas, modul, deskripsi, atau nama user)
        if ($request->filled('q')) {
            $keyword = trim($request->q);
            $query->where(function ($q) use ($keyword) {
                $q->where('activity_logs.aktivitas', 'like', "%{$keyword}%")
                    ->orWhere('activity_logs.modul', 'like', "%{$keyword}%")
                    ->orWhere('activity_logs.deskripsi', 'like', "%{$keyword}%")
                    ->orWhere('users.name', 'like', "%{$keyword}%")
                    ->orWhere('users.nama', 'like', "%{$keyword}%")
                    ->orWhere('users.username', 'like', "%{$keyword}%");
            });
        }

        // Filter Modul
        if ($request->filled('modul') && $request->modul !== 'ALL') {
            $query->where('activity_logs.modul', $request->modul);
        }

        // Filter Aktivitas
        if ($request->filled('aktivitas') && $request->aktivitas !== 'ALL') {
            $query->where('activity_logs.aktivitas', $request->aktivitas);
        }

        // Filter Tanggal
        if ($request->filled('tanggal_mulai')) {
            $query->whereDate('activity_logs.created_at', '>=', $request->tanggal_mulai);
        }
        if ($request->filled('tanggal_selesai')) {
            $query->whereDate('activity_logs.created_at', '<=', $request->tanggal_selesai);
        }

        $perPage = in_array((int) $request->input('per_page', 10), [10, 25, 50, 100], true)
            ? (int) $request->input('per_page', 10)
            : 10;

        $logs = $query->orderBy('activity_logs.created_at', 'desc')
            ->orderBy('activity_logs.log_id', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        // Opsi modul & aktivitas untuk filter dropdown (dibatasi sesuai hak akses role)
        $modQuery = DB::table('activity_logs')->whereNotNull('modul');
        $actQuery = DB::table('activity_logs');
        $countQuery = DB::table('activity_logs');

        if (!$isSuperAdmin) {
            $modQuery->where('user_id', $currentUser->id);
            $actQuery->where('user_id', $currentUser->id);
            $countQuery->where('user_id', $currentUser->id);
        }

        $availableModules = $modQuery
            ->select('modul')
            ->distinct()
            ->orderBy('modul')
            ->pluck('modul');

        $availableActivities = $actQuery
            ->select('aktivitas')
            ->distinct()
            ->orderBy('aktivitas')
            ->pluck('aktivitas');

        $totalLogsCount = $countQuery->count();

        return view('pages.activity-logs', compact(
            'logs',
            'availableModules',
            'availableActivities',
            'totalLogsCount',
            'isSuperAdmin'
        ));
    }
}
