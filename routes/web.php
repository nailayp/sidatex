<?php

use App\Http\Controllers\Auth\LoginController;
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
    // Halaman utama setelah pengguna berhasil login.
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // Halaman awal tiap modul memakai satu view informatif sampai fiturnya siap.
    Route::view('/upload-data', 'pages.module-placeholder', [
        'pageTitle' => 'Upload Data',
        'pageDescription' => 'Unggah dan validasi data ekspor-impor SIDATEX.',
    ])->name('upload-data');

    Route::view('/filtering-data', 'pages.module-placeholder', [
        'pageTitle' => 'Filtering Data',
        'pageDescription' => 'Telusuri data berdasarkan komoditas dan periode.',
    ])->name('filtering-data');

    Route::view('/manajemen-data', 'pages.module-placeholder', [
        'pageTitle' => 'Manajemen Data',
        'pageDescription' => 'Kelola data ekspor-impor dan status publikasinya.',
    ])->name('manajemen-data');

    Route::view('/analisis-perdagangan', 'pages.module-placeholder', [
        'pageTitle' => 'Analisis Perdagangan',
        'pageDescription' => 'Analisis ringkasan perdagangan ekspor-impor Provinsi Riau.',
    ])->name('analisis-perdagangan');

    // Mengakhiri sesi login.
    Route::post('/logout', [LoginController::class, 'logout'])
        ->name('logout');
});
