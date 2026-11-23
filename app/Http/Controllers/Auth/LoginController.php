<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    /**
     * Menampilkan halaman login SIDATEX.
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Memeriksa username dan password pengguna.
     */
    public function login(Request $request)
    {
        // Validasi input sebelum memeriksa akun.
        $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // Cari akun berdasarkan username.
        $user = User::where('username', $request->username)->first();

        // Tolak login jika akun tidak ditemukan, tidak aktif,
        // atau password yang dimasukkan salah.
        if (
            !$user ||
            !$user->is_active ||
            !Hash::check($request->password, $user->password)
        ) {
            return back()
                ->withErrors([
                    'username' => 'Username atau password tidak sesuai.',
                ])
                ->onlyInput('username');
        }

        // Buat sesi login baru untuk mencegah session fixation.
        Auth::login($user);
        $request->session()->regenerate();

        // Catat aktivitas Login pengguna
        ActivityLogger::log('Login Pengguna', 'Autentikasi', "Pengguna {$user->name} ({$user->username}) berhasil masuk ke sistem.", $user->id);

        // =========================================================
        // LOGIKA PENGECEKAN ROLE UNTUK REDIRECT DASHBOARD
        // =========================================================
        // Asumsi: Anda memiliki kolom 'role_id' di tabel users.
        // Jika namanya berbeda (misal 'role' atau 'id_role'), silakan sesuaikan.

        // Hanya Super Admin (Role 1) diarahkan ke Upload Data; Pegawai (Role 2) & Viewer Eksekutif (Role 3) ke Dashboard
        if ((int) $user->role_id === 1) {
            return redirect()->route('upload-data');
        }

        return redirect()->route('dashboard');
    }

    /**
     * Mengakhiri sesi login pengguna.
     */
    public function logout(Request $request)
    {
        $user = Auth::user();
        if ($user) {
            ActivityLogger::log('Logout Pengguna', 'Autentikasi', "Pengguna {$user->name} ({$user->username}) keluar dari sistem.", $user->id);
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
