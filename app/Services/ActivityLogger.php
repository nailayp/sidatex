<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class ActivityLogger
{
    /**
     * Mencatat aksi pengguna ke tabel activity_logs.
     *
     * @param string $aktivitas Nama aktivitas (misal: 'Upload Data', 'Rilis Publikasi')
     * @param string $modul Nama modul (misal: 'Upload Data', 'Manajemen Data')
     * @param string|null $deskripsi Keterangan detail
     * @param int|null $userId ID pengguna (opsional, default: Auth::id())
     */
    public static function log(string $aktivitas, string $modul, ?string $deskripsi = null, ?int $userId = null): void
    {
        try {
            $currentUserId = $userId ?? Auth::id() ?? 1;

            DB::table('activity_logs')->insert([
                'user_id' => $currentUserId,
                'aktivitas' => $aktivitas,
                'modul' => $modul,
                'deskripsi' => $deskripsi,
                'ip_address' => Request::ip() ?? '127.0.0.1',
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            logger()->warning("Gagal mencatat ActivityLog [{$aktivitas}]: " . $e->getMessage());
        }
    }
}
