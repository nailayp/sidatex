<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
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

        // =========================================================
        // LOGIKA PENGECEKAN ROLE UNTUK REDIRECT DASHBOARD
        // =========================================================
        // Asumsi: Anda memiliki kolom 'role_id' di tabel users.
        // Jika namanya berbeda (misal 'role' atau 'id_role'), silakan sesuaikan.

        // Arahkan semua role yang berhasil login langsung ke rute dashboard utama atau upload-data
        if ($user->role_id == 1 || $user->role_id == 3) {
            // Untuk Admin / Super Admin, langsung arahkan ke modul Upload Data atau Dashboard
            return redirect()->route('upload-data');
        } else {
            // Untuk role lainnya (misal Pimpinan)
            return redirect()->route('dashboard');
        }
    }

    /**
     * Mengakhiri sesi login pengguna.
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
