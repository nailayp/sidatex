<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;

/*
|--------------------------------------------------------------------------
| Route Publik
|--------------------------------------------------------------------------
| Halaman ini dapat diakses tanpa login.
*/

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

    // Mengakhiri sesi login.
    Route::post('/logout', [LoginController::class, 'logout'])
        ->name('logout');
});
