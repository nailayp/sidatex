@extends('layouts.app')

@section('title', 'Manajemen Data Terunggah | SIDATEX')

@push('styles')
<style>
    /* ====================================================================
       TEMPAT SETTING FOTO LATAR BELAKANG (BACKGROUND)
       ====================================================================
       Cara Mengubah Foto:
       1. Siapkan foto gedung BPS Anda.
       2. Copy/Masukkan foto tersebut ke dalam folder: public/images/ di proyek Laravel Anda.
          (Jika folder images belum ada, buat foldernya di dalam folder public)
       3. Ubah tulisan 'foto-gedung-bps.jpg' di bawah ini dengan nama file foto Anda.
    ==================================================================== */
    #full-background {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        z-index: -999;
        background-image: linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.4)), url('{{ asset("images/foto-kantor.jpg") }}');
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
    }

    /* Container Utama */
    .management-wrapper {
        min-height: calc(100vh - 80px);
        padding: 2rem;
        position: relative;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    /* Header Judul */
    .header-title-container {
        display: flex;
        align-items: center;
        gap: 1.5rem;
        background: rgba(255, 255, 255, 0.95);
        padding: 1rem 2rem;
        border-radius: 12px;
        margin-bottom: 2rem;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .header-title-container h1 {
        font-size: 2rem;
        font-weight: 700;
        font-style: italic;
        color: #1e293b;
        margin: 0;
        border-left: 2px solid #cbd5e1;
        padding-left: 1.5rem;
    }

    /* Grid Statistik */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    .stat-card {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(10px);
        border-radius: 16px;
        padding: 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 8px 32px rgba(0,0,0,0.1);
        border: 1px solid rgba(255,255,255,0.5);
    }
    .stat-info { display: flex; flex-direction: column; }
    .stat-label { font-size: 1rem; color: #475569; font-weight: 500; margin-bottom: 0.2rem; }
    .stat-value { font-size: 2.5rem; font-weight: 800; color: #0f172a; line-height: 1; }
    .stat-icon { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: white; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }

    /* Container Tabel */
    .table-container {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(15px);
        border-radius: 20px;
        padding: 2rem;
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    }

    /* Tabel Desain Kustom */
    .custom-table { width: 100%; border-collapse: separate; border-spacing: 0 12px; }
    .custom-table th { text-align: left; padding: 0 1rem 1rem 1rem; color: #1e293b; font-weight: 700; font-size: 1.1rem; border-bottom: 2px solid #e2e8f0; }
    .custom-table td { background: transparent; padding: 1rem; vertical-align: middle; }
    .custom-table tbody tr { transition: background 0.2s; border-bottom: 1px solid #f1f5f9; }
    .custom-table tbody tr:hover { background: rgba(255,255,255,0.5); border-radius: 10px; }

    /* Elemen Dalam Tabel */
    .doc-info { display: flex; align-items: center; gap: 1rem; }
    .excel-logo { width: 40px; height: 40px; }
    .doc-text h4 { margin: 0; font-size: 1.05rem; font-weight: 700; color: #1e293b; }
    .doc-text p { margin: 0; font-size: 0.85rem; color: #64748b; }

    .date-time { line-height: 1.3; }
    .date-time .date { font-weight: 600; color: #334155; }
    .date-time .time { font-size: 0.85rem; color: #64748b; }

    /* Dropdown Action (Titik Tiga) */
    .action-cell { position: relative; text-align: center; }
    .btn-action-dots { background: none; border: none; font-size: 1.5rem; font-weight: bold; cursor: pointer; color: #475569; padding: 0.5rem; }

    .dropdown-menu {
        display: none;
        position: absolute;
        right: 50px;
        top: 50%;
        transform: translateY(-50%);
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        border: 1px solid #e2e8f0;
        width: 150px;
        z-index: 100;
        overflow: hidden;
    }
    .dropdown-menu.show { display: block; }
    .dropdown-menu button {
        width: 100%; text-align: center; padding: 0.8rem; border: none; background: none; border-bottom: 1px solid #f1f5f9; cursor: pointer; color: #334155; font-weight: 500; font-size: 0.9rem; transition: background 0.2s;
    }
    .dropdown-menu button:hover { background: #f8fafc; color: #2563eb; }
    .dropdown-menu button:last-child { border-bottom: none; }

    /* Pagination */
    .pagination-wrapper { display: flex; justify-content: flex-end; align-items: center; gap: 0.5rem; margin-top: 2rem; }
    .page-btn { background: white; border: 1px solid #cbd5e1; padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer; font-weight: 500; color: #475569; transition: all 0.2s; }
    .page-btn:hover { background: #f1f5f9; }
    .page-btn.active { background: #3b82f6; color: white; border-color: #3b82f6; }
</style>
@endpush

@section('content')
<!-- Layer Background Full Layar -->
<div id="full-background"></div>

<div class="management-wrapper">

    <!-- Peringatan Sukses dari Redirect -->
    @if(session('success'))
        <div style="background: #10b981; color: white; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; text-align: center; font-weight: bold;">
            {{ session('success') }}
        </div>
    @endif

    <!-- Header Judul -->
    <div class="header-title-container">
        <h1>Manajement Data Terunggah</h1>
    </div>

    <!-- 4 Kartu Statistik Atas -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-info">
                <span class="stat-label">Total File</span>
                <span class="stat-value">{{ number_format($totalFile, 0, ',', '.') }}</span>
            </div>
            <!-- ... icon ... -->
        </div>

        <div class="stat-card">
            <div class="stat-info">
                <span class="stat-label">Terverifikasi</span>
                <span class="stat-value">{{ number_format($terverifikasi, 0, ',', '.') }}</span>
            </div>
            <!-- ... icon ... -->
        </div>

        <div class="stat-card">
            <div class="stat-info">
                <span class="stat-label">Pending Public</span>
                <span class="stat-value">{{ number_format($pending, 0, ',', '.') }}</span>
            </div>
            <!-- ... icon ... -->
        </div>

        <div class="stat-card">
            <div class="stat-info">
                <span class="stat-label">Total Ukuran</span>
                <span class="stat-value">{{ $totalUkuranGB }}</span>
            </div>
            <!-- ... icon ... -->
        </div>
    </div>

    <!-- Tabel Daftar Data -->
    <div class="table-container">
        <table class="custom-table">
            <thead>
                <tr>
                    <th style="width: 50px;"></th>
                    <th>Nama Dokumen / Data</th>
                    <th>Tanggal Unggah</th>
                    <th>Ukuran</th>
                    <th>Status</th>
                    <th style="text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($dokumens as $index => $doc)
                <tr>
                    <td><input type="radio" name="select_row" value="{{ $doc->id }}"></td>
                    <td>
                        <div class="doc-info">
                            <svg class="excel-logo" viewBox="0 0 24 24" fill="none">
                                <rect width="24" height="24" rx="4" fill="#107C41"/>
                                <path d="M15.5 17H13.5L12 12.5L10.5 17H8.5L11 11L8.5 5H10.5L12 9.5L13.5 5H15.5L13 11L15.5 17Z" fill="white"/>
                            </svg>
                            <div class="doc-text">
                                <h4>{{ $doc->nama_file }}</h4> <!-- Sesuai nama kolom di DB -->
                                <p>{{ $doc->jenis_data ?? 'SpreadSheet' }}</p>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="date-time">
                            <div class="date">{{ \Carbon\Carbon::parse($doc->created_at)->format('d F Y') }}</div>
                            <div class="time">{{ \Carbon\Carbon::parse($doc->created_at)->format('H:i') }}</div>
                        </div>
                    </td>
                    <td style="color: #475569; font-weight: 500;">{{ $doc->ukuran_file }} MB</td>
                    <td style="color: #475569; font-weight: 500;">
                        @if($doc->status == 'Public')
                            <span style="color: #10b981;">Public</span>
                        @else
                            <span style="color: #f59e0b;">Pending</span>
                        @endif
                    </td>
                    <td class="action-cell">
                        <button class="btn-action-dots" onclick="toggleMenu('menu-{{ $doc->id }}')">⋮</button>
                        <!-- Menu Dropdown -->
                        <div class="dropdown-menu" id="menu-{{ $doc->id }}">
                            <button>Ekspor Data</button>
                            <button>Edit Data</button>
                            <button>Hapus Data</button>
                            <button>{{ $doc->status == 'Public' ? 'Tarik Public' : 'Public Data' }}</button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align: center; padding: 2rem;">Belum ada data dokumen yang diunggah.</td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Navigasi Pagination Otomatis dari Laravel -->
        <div class="pagination-wrapper" style="margin-top: 2rem; display: flex; justify-content: flex-end;">
            {{ $dokumens->links('pagination::bootstrap-4') }} <!-- Atau sesuaikan dengan view pagination kustom Anda -->
        </div>
        </table>

        <!-- Navigasi Pagination Bawah -->
        <div class="pagination-wrapper">
            <button class="page-btn">Sebelumnya</button>
            <button class="page-btn active">1</button>
            <button class="page-btn">2</button>
            <button class="page-btn">3</button>
            <button class="page-btn">4</button>
            <button class="page-btn">Selanjutnya</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Fungsi untuk memunculkan dan menyembunyikan menu aksi (Titik Tiga)
    function toggleMenu(menuId) {
        // Tutup semua menu yang sedang terbuka
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            if(menu.id !== menuId) menu.classList.remove('show');
        });

        // Buka menu yang diklik
        const selectedMenu = document.getElementById(menuId);
        selectedMenu.classList.toggle('show');
    }

    // Menutup menu secara otomatis jika klik di area kosong layar
    window.onclick = function(event) {
        if (!event.target.matches('.btn-action-dots')) {
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.classList.remove('show');
            });
        }
    }
</script>
@endpush
