<?php

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExcelPreviewController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\FilteringController;
use App\Http\Controllers\AnalisisController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ExportPptController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Route Publik
|--------------------------------------------------------------------------
| Halaman ini dapat diakses tanpa login.
*/

Route::get('/', function () {
    return Auth::check()
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
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/metrics', [DashboardController::class, 'metrics'])->name('dashboard.metrics');

    // Rute pendukung/alias & API data
    Route::get('/dashboard/data', [DashboardController::class, 'data'])->name('dashboard.data');
    Route::get('/dashboard/default', [DashboardController::class, 'index'])->name('dashboard.default');

    // --- MODUL UPLOAD DATA (Khusus Super Admin) ---
    Route::middleware('superadmin')->group(function () {
        Route::get('/upload-data', [UploadController::class, 'index'])->name('upload-data');
        Route::post('/upload-data/process', [UploadController::class, 'store'])->name('upload.process');
        Route::post('/upload-data/preview', [ExcelPreviewController::class, 'preview'])->name('upload.preview');

        // --- UPLOAD & PREVIEW KHUSUS NERACA (Tahap 2R) ---
        Route::get('/neraca/upload', [\App\Http\Controllers\NeracaController::class, 'uploadView'])->name('neraca.upload');
        Route::post('/neraca/upload/preview', [\App\Http\Controllers\NeracaController::class, 'previewUpload'])->name('neraca.upload.preview');
        Route::get('/neraca/upload/rows', [\App\Http\Controllers\NeracaController::class, 'paginatedPreview'])->name('neraca.upload.rows');

        // --- PENGELOLAAN BATCH & TRASH (Khusus Super Admin) ---
        Route::post('/manajemen-data/{batchId}/toggle-release', [UploadController::class, 'toggleRelease'])->name('manajemen-data.toggle-release');
        Route::post('/manajemen-data/{batchId}/update', [UploadController::class, 'update'])->name('manajemen-data.update');
        Route::post('/manajemen-data/{batchId}/delete', [UploadController::class, 'destroy'])->name('manajemen-data.destroy');
        Route::get('/trash-data', [UploadController::class, 'trash'])->name('trash-data');
        Route::post('/manajemen-data/{batchId}/restore', [UploadController::class, 'restore'])->name('manajemen-data.restore');
        Route::post('/manajemen-data/{batchId}/force-delete', [UploadController::class, 'forceDelete'])->name('manajemen-data.force-delete');
    });

    // --- MODUL MANAJEMEN DATA & UNDUH EXCEL ---
    // Halaman list manajemen data dan unduh Excel batch yang telah dirilis dapat diakses pengguna terautentikasi
    Route::get('/manajemen-data', [UploadController::class, 'manajemen'])->name('manajemen-data');
    Route::get('/manajemen-data/{batchId}/export', [UploadController::class, 'export'])->name('manajemen-data.export');

    // --- MODUL FILTERING DATA ---
    Route::get('/filtering-data', [FilteringController::class, 'index'])->name('filtering-data');
    Route::get('/filtering-data/query', [FilteringController::class, 'query'])->name('filtering-data.query');

    // --- MODUL ANALISIS PERDAGANGAN ---
    Route::get('/analisis-perdagangan', [AnalisisController::class, 'index'])->name('analisis-perdagangan');
    Route::get('/analisis-perdagangan/data', [AnalisisController::class, 'getData'])->name('analisis-perdagangan.data');

    // --- KOMENTAR BATCH ---
    Route::get('/comments/batch/{batchId}', [CommentController::class, 'getByBatch'])->name('comments.batch');
    Route::post('/comments', [CommentController::class, 'store'])->name('comments.store');
    Route::delete('/comments/{commentId}', [CommentController::class, 'destroy'])->name('comments.destroy');

    // --- EKSPOR POWERPOINT (BRS) ---
    Route::get('/export/powerpoint/{batchId}', [ExportPptController::class, 'export'])->name('export.powerpoint');

    // --- DASHBOARD TERPADU SIDATEX (Single Dashboard Route & Sub-Sources) ---
    Route::get('/distribusi/dashboard', function (\Illuminate\Http\Request $request) {
        $request->merge(['sumber' => 'distribusi']);
        return app(\App\Http\Controllers\DashboardController::class)->index($request);
    })->name('distribusi.dashboard');
    Route::get('/distribusi/metrics', [\App\Http\Controllers\DistributionController::class, 'getMetrics'])->name('distribusi.metrics');

    Route::get('/neraca/dashboard', function (\Illuminate\Http\Request $request) {
        $request->merge(['sumber' => 'neraca']);
        return app(\App\Http\Controllers\DashboardController::class)->index($request);
    })->name('neraca.dashboard');
    Route::get('/neraca/metrics', [\App\Http\Controllers\NeracaController::class, 'getMetrics'])->name('neraca.metrics');

    // --- LOG AKTIVITAS (AUDIT TRAIL) ---
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs');

    // --- AUTENTIKASI ---
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});
