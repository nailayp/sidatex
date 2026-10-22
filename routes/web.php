<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController; // Pastikan Controller ini di-import
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Route Publik
|--------------------------------------------------------------------------
| Halaman ini dapat diakses tanpa login.
*/

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::get('/login', [LoginController::class, 'showLoginForm'])
    ->name('login');

Route::post('/login', [LoginController::class, 'login'])
    ->name('login.process');

/*
|--------------------------------------------------------------------------
| Route Pengguna yang Sudah Login
|--------------------------------------------------------------------------
| Hanya pengguna yang sudah terautentikasi yang boleh mengaksesnya.
*/

Route::middleware('auth')->group(function () {

    // --- DASHBOARD UTAMA ---
    // Halaman utama setelah login (Ditangani oleh Controller)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // --- MODUL UPLOAD DATA ---
    // Menampilkan halaman form upload
    Route::get('/upload-data', [UploadController::class, 'index'])->name('upload-data');
    // Rute untuk memproses file yang di-submit
    Route::post('/upload-data/process', [UploadController::class, 'store'])->name('upload.process');

    // Rute tambahan sesuai role di LoginController
    Route::get('/dashboard/data', [UploadController::class, 'index'])->name('dashboard.data');

    Route::get('/dashboard/visual', function () {
        return view('dashboard.visual');
    })->name('dashboard.visual');

    // --- MODUL DALAM PENGEMBANGAN (Placeholder) ---
    Route::view('/filtering-data', 'pages.module-placeholder', [
        'pageTitle' => 'Filtering Data',
        'pageDescription' => 'Telusuri data berdasarkan komoditas dan periode.',
    ])->name('filtering-data');

    Route::get('/manajemen-data', [\App\Http\Controllers\UploadController::class, 'manajemen'])->name('manajemen-data');

    Route::view('/analisis-perdagangan', 'pages.module-placeholder', [
        'pageTitle' => 'Analisis Perdagangan',
        'pageDescription' => 'Analisis ringkasan perdagangan ekspor-impor Provinsi Riau.',
    ])->name('analisis-perdagangan');



    Route::get('/dashboard/master', [UploadController::class, 'index'])->name('dashboard.master');

    Route::get('/dashboard/default', [DashboardController::class, 'index'])->name('dashboard.default');

    // --- AUTENTIKASI ---
    // Mengakhiri sesi login.
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});
