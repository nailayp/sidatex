<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Halaman Dashboard Utama
     */
    public function index()
    {
        // Langsung arahkan ke file resources/views/dashboard.blade.php
        return view('dashboard');
    }

    // Fungsi lain seperti uploadData(), filteringData() dll bisa Anda tambahkan nanti
    // saat modulnya sudah siap, dan rute di web.php diperbarui.
}
