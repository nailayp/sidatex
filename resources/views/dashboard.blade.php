@extends('layouts.app')

@section('title', 'Dashboard Analisis Ekspor-Impor | SIDATEX BPS Provinsi Riau')

@push('styles')
<!-- SheetJS untuk Fitur Ekspor Excel Langsung di Halaman 2 & 3 -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<!-- Chart.js & Leaflet Maps -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
    /* Kontainer Utama Dashboard SIDATEX */
    .dashboard-wrapper {
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
        padding: 0.25rem 0.5rem;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        color: #1e293b;
    }

    /* Header & Navigasi Horizontal 3 Halaman */
    .dashboard-header-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1.25rem 1.5rem;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .dashboard-title-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.75rem;
    }

    .dashboard-title-group h1 {
        font-size: 1.5rem;
        font-weight: 800;
        color: #0f172a;
        margin: 0;
        letter-spacing: -0.02em;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .dashboard-title-group p {
        font-size: 0.85rem;
        color: #64748b;
        margin: 0.25rem 0 0 0;
    }

    .badge-status-rilis {
        font-size: 0.78rem;
        font-weight: 700;
        color: #0369a1;
        background: #e0f2fe;
        border: 1px solid #bae6fd;
        padding: 0.35rem 0.75rem;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
    }

    /* TAB NAVIGASI HORIZONTAL 3 HALAMAN (Overview, Time Series, Detail & Mapping) */
    .dashboard-nav-tabs {
        display: flex;
        gap: 0.5rem;
        border-bottom: 2px solid #e2e8f0;
        padding-bottom: 0;
        margin-top: 0.25rem;
        overflow-x: auto;
    }

    .nav-tab-button {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.25rem;
        font-size: 0.92rem;
        font-weight: 700;
        color: #64748b;
        background: transparent;
        border: none;
        border-bottom: 3px solid transparent;
        margin-bottom: -2px;
        cursor: pointer;
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .nav-tab-button:hover {
        color: #0369a1;
        background: #f8fafc;
        border-radius: 6px 6px 0 0;
    }

    .nav-tab-button.active {
        color: #0f172a;
        border-bottom-color: #0284c7;
        background: #f0f9ff;
        border-radius: 8px 8px 0 0;
    }

    .nav-tab-button .tab-icon {
        font-size: 1.1rem;
    }

    /* AREA KONTROL FILTER HORIZONTAL RINGKAS */
    .filter-bar-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1rem 1.25rem;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
    }

    .filter-bar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.75rem;
    }

    .filter-bar-title {
        font-size: 0.8rem;
        font-weight: 800;
        text-transform: uppercase;
        color: #0f172a;
        letter-spacing: 0.05em;
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }

    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
        gap: 0.75rem;
        align-items: flex-end;
    }

    .filter-item {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .filter-item label {
        font-size: 0.72rem;
        font-weight: 700;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.02em;
    }

    .filter-item select {
        width: 100%;
        padding: 0.5rem 0.65rem;
        font-size: 0.82rem;
        font-weight: 600;
        color: #1e293b;
        background: #f8fafc;
        border: 1.5px solid #cbd5e1;
        border-radius: 8px;
        outline: none;
        transition: border-color 0.15s, box-shadow 0.15s;
    }

    .filter-item select:focus {
        border-color: #0284c7;
        box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
        background: #ffffff;
    }

    .btn-reset-filter {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        padding: 0.5rem 1rem;
        font-size: 0.82rem;
        font-weight: 700;
        color: #dc2626;
        background: #fef2f2;
        border: 1.5px solid #fecaca;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.15s;
        height: 38px;
    }

    .btn-reset-filter:hover {
        background: #fee2e2;
        border-color: #f87171;
    }

    /* KONTEN HALAMAN (Tab Sections) */
    .dashboard-page-section {
        display: none;
        flex-direction: column;
        gap: 1.25rem;
        animation: fadeIn 0.2s ease-in-out;
    }

    .dashboard-page-section.active {
        display: flex;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(4px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* KARTU KPI MODERN */
    .kpi-grid-4 {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1rem;
    }

    .kpi-grid-5 {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 1rem;
    }

    .kpi-grid-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.25rem;
    }

    .kpi-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1.1rem 1.25rem;
        box-shadow: 0 2px 6px rgba(15, 23, 42, 0.03);
        display: flex;
        flex-direction: column;
        justify-content: center;
        transition: transform 0.15s, box-shadow 0.15s;
    }

    .kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
    }

    .kpi-label {
        font-size: 0.72rem;
        font-weight: 800;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .kpi-value {
        font-size: 1.45rem;
        font-weight: 800;
        color: #0f172a;
        margin-top: 0.35rem;
        line-height: 1.15;
    }

    .kpi-subtext {
        font-size: 0.75rem;
        color: #64748b;
        margin-top: 0.35rem;
    }

    /* CARD GRAFIK & TABEL */
    .section-title-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 0.25rem;
        padding-bottom: 0.4rem;
        border-bottom: 2px solid #0284c7;
    }

    .section-title-row h2 {
        font-size: 1.05rem;
        font-weight: 800;
        color: #0f172a;
        text-transform: uppercase;
        letter-spacing: 0.02em;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .charts-grid-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.25rem;
    }

    .content-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1.25rem;
        box-shadow: 0 2px 6px rgba(15, 23, 42, 0.03);
        display: flex;
        flex-direction: column;
    }

    .content-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.85rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid #f1f5f9;
    }

    .content-card-header h3 {
        font-size: 0.9rem;
        font-weight: 800;
        color: #0f172a;
        margin: 0;
    }

    .chart-container-box {
        position: relative;
        height: 280px;
        width: 100%;
    }

    /* TABEL MODERN & SCROLLABLE */
    .table-responsive-box {
        overflow-x: auto;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        max-height: 480px;
    }

    .modern-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.82rem;
    }

    .modern-table th {
        position: sticky;
        top: 0;
        background: #0f172a;
        color: #ffffff;
        font-weight: 700;
        text-align: left;
        padding: 0.65rem 0.85rem;
        white-space: nowrap;
        z-index: 10;
        border-bottom: 2px solid #0284c7;
    }

    .modern-table th.text-right,
    .modern-table td.text-right {
        text-align: right;
    }

    .modern-table td {
        padding: 0.65rem 0.85rem;
        border-bottom: 1px solid #f1f5f9;
        white-space: nowrap;
        background: #ffffff;
        color: #1e293b;
    }

    .modern-table tbody tr:nth-child(even) td {
        background: #f8fafc;
    }

    .modern-table tbody tr:hover td {
        background: #f0f9ff;
    }

    .btn-export-excel {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.45rem 0.85rem;
        font-size: 0.78rem;
        font-weight: 700;
        color: #166534;
        background: #dcfce7;
        border: 1px solid #86efac;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.15s;
    }

    .btn-export-excel:hover {
        background: #bbf7d0;
    }

    .growth-badge {
        display: inline-block;
        font-size: 0.7rem;
        font-weight: 700;
        padding: 2px 6px;
        border-radius: 4px;
    }
    .growth-pos { background: #dcfce7; color: #15803d; }
    .growth-neg { background: #fee2e2; color: #dc2626; }
    .growth-zero { background: #f1f5f9; color: #64748b; }

    /* Responsif Layar Laptop & Desktop */
    @media (max-width: 1024px) {
        .kpi-grid-4, .kpi-grid-5 { grid-template-columns: repeat(2, 1fr); }
        .charts-grid-2, .kpi-grid-2 { grid-template-columns: 1fr; }
        .filter-grid { grid-template-columns: repeat(2, 1fr); }
    }
</style>
@endpush

@section('content')
<div class="dashboard-wrapper">

    <!-- 1. HEADER JUDUL & TAB NAVIGASI HORIZONTAL 3 HALAMAN -->
    <div class="dashboard-header-card">
        <div class="dashboard-title-row">
            <div class="dashboard-title-group">
                <h1>Dashboard Statistik Perdagangan Luar Negeri</h1>
                <p>Sistem Informasi Data Ekspor-Impor (SIDATEX) — Badan Pusat Statistik Provinsi Riau</p>
            </div>
            <div>
                <span class="badge-status-rilis">
                    <span style="font-size: 0.6rem;">●</span> Data Resmi (Status: RILIS)
                </span>
            </div>
        </div>

        <!-- 3 Tab Navigasi Horizontal Tepat di Bawah Judul -->
        <nav class="dashboard-nav-tabs" aria-label="Navigasi Analisis Dashboard">
            <button type="button" class="nav-tab-button active" onclick="switchDashboardTab('tabOverview', this)">
                <span class="tab-icon">🌐</span>
                <span>Overview</span>
            </button>
            <button type="button" class="nav-tab-button" onclick="switchDashboardTab('tabTimeSeries', this)">
                <span class="tab-icon">📈</span>
                <span>Time Series Komoditas</span>
            </button>
            <button type="button" class="nav-tab-button" onclick="switchDashboardTab('tabDetailMapping', this)">
                <span class="tab-icon">🗺️</span>
                <span>Detail &amp; Mapping</span>
            </button>
        </nav>
    </div>

    <!-- 2. KONTROL FILTER HORIZONTAL RINGKAS (Tepat di bawah tab) -->
    <div class="filter-bar-card">
        <div class="filter-bar-header">
            <span class="filter-bar-title">
                <span>⚡</span> Filter Analisis Data
            </span>
            <button type="button" class="btn-reset-filter" onclick="resetAllFilters()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path></svg>
                Reset Filter
            </button>
        </div>

        <div class="filter-grid">
            <div class="filter-item">
                <label for="filterJenis">Jenis</label>
                <select id="filterJenis" onchange="fetchDashboardData()">
                    <option value="Ekspor" selected>Ekspor</option>
                    <option value="Impor">Impor</option>
                </select>
            </div>

            <div class="filter-item">
                <label for="filterTahun">Tahun</label>
                <select id="filterTahun" onchange="fetchDashboardData()">
                    <option value="ALL">Semua Tahun</option>
                    @foreach($availableYears as $y)
                        <option value="{{ $y }}" {{ ($metrics['tahun'] == $y) ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-item">
                <label for="filterBulan">Bulan</label>
                <select id="filterBulan" onchange="fetchDashboardData()">
                    <option value="ALL">Semua Bulan</option>
                    <option value="1">Januari</option>
                    <option value="2">Februari</option>
                    <option value="3">Maret</option>
                    <option value="4">April</option>
                    <option value="5">Mei</option>
                    <option value="6">Juni</option>
                    <option value="7">Juli</option>
                    <option value="8">Agustus</option>
                    <option value="9">September</option>
                    <option value="10">Oktober</option>
                    <option value="11">November</option>
                    <option value="12">Desember</option>
                </select>
            </div>

            <div class="filter-item">
                <label for="filterTriwulan">Triwulan</label>
                <select id="filterTriwulan" onchange="fetchDashboardData()">
                    <option value="ALL">Semua Triwulan</option>
                    <option value="Tw-I">Triwulan I (Jan-Mar)</option>
                    <option value="Tw-II">Triwulan II (Apr-Jun)</option>
                    <option value="Tw-III">Triwulan III (Jul-Sep)</option>
                    <option value="Tw-IV">Triwulan IV (Okt-Des)</option>
                </select>
            </div>

            <div class="filter-item">
                <label for="filterMigas">Kategori</label>
                <select id="filterMigas" onchange="fetchDashboardData()">
                    <option value="ALL">Semua Kategori</option>
                    <option value="Non-Migas">Non-Migas</option>
                    <option value="Migas">Migas</option>
                </select>
            </div>

            <div class="filter-item">
                <label for="filterKabkota">Kab/Kota Asal</label>
                <select id="filterKabkota" onchange="fetchDashboardData()">
                    <option value="ALL">Semua Wilayah</option>
                    @foreach($kabkotas as $kb)
                        <option value="{{ $kb->kabkota_id }}">{{ $kb->nama_kabkota }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-item">
                <label for="filterKomoditi">Komoditas</label>
                <select id="filterKomoditi" onchange="fetchDashboardData()">
                    <option value="ALL">Semua Komoditas</option>
                    @foreach($komoditis as $k)
                        <option value="{{ $k->hs2 }}">HS {{ $k->hs2 }} — {{ \Illuminate\Support\Str::limit($k->hs2_desc, 22) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-item">
                <label for="filterNegara">Negara Tujuan</label>
                <select id="filterNegara" onchange="fetchDashboardData()">
                    <option value="ALL">Semua Negara</option>
                    @foreach($negaras as $neg)
                        <option value="{{ $neg->negara_id }}">{{ $neg->kode_negara }} - {{ $neg->nama_negara }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-item">
                <label for="filterPelabuhan">Pelabuhan</label>
                <select id="filterPelabuhan" onchange="fetchDashboardData()">
                    <option value="ALL">Semua Pelabuhan</option>
                    @foreach($pelabahans ?? [] as $pel)
                        <option value="{{ $pel->pelabuhan_id }}">{{ $pel->nama_pelabuhan }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- HALAMAN 1 — OVERVIEW (Ringkasan Utama, KPI, Tren Bulanan, Sektor Migas) -->
    <!-- ========================================================================= -->
    <section id="tabOverview" class="dashboard-page-section active">

        <!-- 4 Indikator Pertumbuhan Resmi BPS -->
        <div class="kpi-grid-4">
            <div class="kpi-card" style="border-left: 4px solid #0284c7;">
                <span class="kpi-label">Pertumbuhan M-to-M</span>
                <div class="kpi-value" id="kpiMtom">0%</div>
                <span class="kpi-subtext">Bulan ke bulan sebelumnya</span>
            </div>
            <div class="kpi-card" style="border-left: 4px solid #16a34a;">
                <span class="kpi-label">Pertumbuhan Q-to-Q</span>
                <div class="kpi-value" id="kpiQtoq">0%</div>
                <span class="kpi-subtext">Triwulan ke triwulan sebelumnya</span>
            </div>
            <div class="kpi-card" style="border-left: 4px solid #f59e0b;">
                <span class="kpi-label">Pertumbuhan Y-on-Y</span>
                <div class="kpi-value" id="kpiYony">0%</div>
                <span class="kpi-subtext">vs periode sama tahun lalu</span>
            </div>
            <div class="kpi-card" style="border-left: 4px solid #6366f1;">
                <span class="kpi-label">Pertumbuhan C-to-C</span>
                <div class="kpi-value" id="kpiCtoc">0%</div>
                <span class="kpi-subtext">Kumulatif tahun berjalan</span>
            </div>
        </div>

        <!-- 5 Kartu KPI Utama Sesuai Spesifikasi -->
        <div class="kpi-grid-5">
            <div class="kpi-card">
                <span class="kpi-label">Total Nilai FOB (US$)</span>
                <div class="kpi-value" id="p1KpiFob" style="color: #0f172a;">$0,00</div>
                <span class="kpi-subtext">Nilai devisa ekspor</span>
            </div>
            <div class="kpi-card">
                <span class="kpi-label">Total Berat NETTO (kg)</span>
                <div class="kpi-value" id="p1KpiNetto" style="color: #0284c7;">0 kg</div>
                <span class="kpi-subtext">Volume bersih barang</span>
            </div>
            <div class="kpi-card">
                <span class="kpi-label">Rata-Rata / Transaksi</span>
                <div class="kpi-value" id="p1KpiAvg">$0,00</div>
                <span class="kpi-subtext">Rerata nilai transaksi</span>
            </div>
            <div class="kpi-card">
                <span class="kpi-label">Jumlah Negara Tujuan</span>
                <div class="kpi-value" id="p1KpiNegara">0</div>
                <span class="kpi-subtext">Mitra dagang aktif</span>
            </div>
            <div class="kpi-card">
                <span class="kpi-label">Kelompok Komoditas</span>
                <div class="kpi-value" id="p1KpiKomoditi">0</div>
                <span class="kpi-subtext">Golongan barang HS</span>
            </div>
        </div>

        <!-- Grafik Tren Perdagangan Bulanan (FOB & Netto) -->
        <div class="section-title-row">
            <h2><span>📈</span> Grafik Tren Perdagangan Bulanan</h2>
            <span style="font-size: 0.78rem; color: #64748b;">Analisis Nilai FOB &amp; Berat Netto Sepanjang Periode</span>
        </div>

        <div class="charts-grid-2">
            <div class="content-card">
                <div class="content-card-header">
                    <h3>Tren Nilai FOB per Bulan (US$)</h3>
                </div>
                <div class="chart-container-box"><canvas id="chartTrenFobBulan"></canvas></div>
            </div>

            <div class="content-card">
                <div class="content-card-header">
                    <h3>Tren Berat Bersih Netto per Bulan (kg)</h3>
                </div>
                <div class="chart-container-box"><canvas id="chartTrenVolBulan"></canvas></div>
            </div>
        </div>

        <!-- Perbandingan Migas vs Non-Migas -->
        <div class="section-title-row">
            <h2><span>⚖️</span> Perbandingan Sektor Migas dan Non-Migas</h2>
            <span style="font-size: 0.78rem; color: #64748b;">Proporsi Nilai FOB dan Berat Netto Komparatif</span>
        </div>

        <div class="charts-grid-2">
            <div class="content-card">
                <div class="content-card-header">
                    <h3>Perbandingan Nilai FOB: Migas vs Non-Migas (US$)</h3>
                </div>
                <div class="chart-container-box"><canvas id="chartTrenMigasFob"></canvas></div>
            </div>

            <div class="content-card">
                <div class="content-card-header">
                    <h3>Perbandingan Berat Netto: Migas vs Non-Migas (kg)</h3>
                </div>
                <div class="chart-container-box"><canvas id="chartTrenMigasNetto"></canvas></div>
            </div>
        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- HALAMAN 2 — TIME SERIES KOMODITAS (Tabel FOB & Netto, MoM, Sorting, Ekspor) -->
    <!-- ========================================================================= -->
    <section id="tabTimeSeries" class="dashboard-page-section">
        <div class="section-title-row">
            <h2><span>📊</span> Analisis Time Series Perkembangan Komoditas</h2>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <button type="button" class="btn-export-excel" onclick="exportTableToExcel('tableTsBulanFob', 'Time_Series_FOB_Komoditas')">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    Ekspor Tabel FOB ke Excel
                </button>
            </div>
        </div>

        <!-- Tabel 1: Perkembangan Nilai FOB per Bulan Berdasarkan Komoditas -->
        <div class="content-card">
            <div class="content-card-header">
                <h3>1. Tabel Perkembangan Nilai FOB per Bulan Berdasarkan Komoditas (US$)</h3>
                <span style="font-size: 0.75rem; color: #64748b;">Klik judul kolom untuk mengurutkan nilai</span>
            </div>
            <div class="table-responsive-box">
                <table class="modern-table" id="tableTsBulanFob">
                    <thead>
                        <tr>
                            <th onclick="sortTable('tableTsBulanFob', 0)" style="cursor: pointer;">Kelompok Komoditas ⇕</th>
                            <th>Jan</th><th>Feb</th><th>Mar</th><th>Apr</th><th>Mei</th><th>Jun</th>
                            <th>Jul</th><th>Agu</th><th>Sep</th><th>Okt</th><th>Nov</th><th>Des</th>
                            <th class="text-right" onclick="sortTableNumber('tableTsBulanFob', 13)" style="cursor: pointer; background: #0369a1;">Total FOB (US$) ⇕</th>
                            <th class="text-right">Pertumbuhan MoM</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        <!-- Tabel 2: Perkembangan Berat Netto per Bulan Berdasarkan Komoditas -->
        <div class="content-card">
            <div class="content-card-header">
                <h3>2. Tabel Perkembangan Berat Netto per Bulan Berdasarkan Komoditas (kg)</h3>
                <button type="button" class="btn-export-excel" onclick="exportTableToExcel('tableTsBulanNetto', 'Time_Series_Netto_Komoditas')">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    Ekspor Tabel Netto ke Excel
                </button>
            </div>
            <div class="table-responsive-box">
                <table class="modern-table" id="tableTsBulanNetto">
                    <thead>
                        <tr>
                            <th onclick="sortTable('tableTsBulanNetto', 0)" style="cursor: pointer;">Kelompok Komoditas ⇕</th>
                            <th>Jan</th><th>Feb</th><th>Mar</th><th>Apr</th><th>Mei</th><th>Jun</th>
                            <th>Jul</th><th>Agu</th><th>Sep</th><th>Okt</th><th>Nov</th><th>Des</th>
                            <th class="text-right" onclick="sortTableNumber('tableTsBulanNetto', 13)" style="cursor: pointer; background: #0369a1;">Total Netto (kg) ⇕</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        <!-- Tabel 3: Perkembangan Triwulanan -->
        <div class="content-card">
            <div class="content-card-header">
                <h3>3. Tabel Perkembangan Nilai FOB per Triwulan (US$)</h3>
            </div>
            <div class="table-responsive-box">
                <table class="modern-table" id="tableTsTriwulanFob">
                    <thead>
                        <tr>
                            <th>Kelompok Komoditas</th>
                            <th class="text-right">Triwulan I</th>
                            <th class="text-right">Triwulan II</th>
                            <th class="text-right">Triwulan III</th>
                            <th class="text-right">Triwulan IV</th>
                            <th class="text-right" style="background: #0369a1;">Total Kumulatif (US$)</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- HALAMAN 3 — DETAIL & MAPPING (Peta Riau, Top Wilayah, Komoditas, Negara, Pelabuhan) -->
    <!-- ========================================================================= -->
    <section id="tabDetailMapping" class="dashboard-page-section">

        <!-- 2 KPI Besar Terpisah Migas & Non-Migas Sesuai Spesifikasi -->
        <div class="kpi-grid-2">
            <div class="kpi-card" style="border-left: 5px solid #0284c7; padding: 1.25rem 1.5rem;">
                <span class="kpi-label">Nilai FOB (US$) Sektoral</span>
                <div class="kpi-value" id="detailFobVal" style="font-size: 1.8rem; color: #0f172a;">US$ 0,00</div>
                <div style="margin-top: 0.75rem; font-size: 0.85rem; color: #475569; display: flex; justify-content: space-between; border-top: 1px solid #f1f5f9; padding-top: 0.5rem;">
                    <span>Non-Migas: <strong id="detailNonMigasFob" style="color: #0284c7;">US$ 0,00</strong></span>
                    <span>Migas: <strong id="detailMigasFob" style="color: #f59e0b;">US$ 0,00</strong></span>
                </div>
            </div>

            <div class="kpi-card" style="border-left: 5px solid #16a34a; padding: 1.25rem 1.5rem;">
                <span class="kpi-label">Berat Bersih NETTO (kg) Sektoral</span>
                <div class="kpi-value" id="detailNettoVal" style="font-size: 1.8rem; color: #16a34a;">0 kg</div>
                <div style="margin-top: 0.75rem; font-size: 0.85rem; color: #475569; display: flex; justify-content: space-between; border-top: 1px solid #f1f5f9; padding-top: 0.5rem;">
                    <span>Non-Migas: <strong id="detailNonMigasNetto" style="color: #16a34a;">0 kg</strong></span>
                    <span>Migas: <strong id="detailMigasNetto" style="color: #f59e0b;">0 kg</strong></span>
                </div>
            </div>
        </div>

        <!-- Peta Interaktif Kontribusi Daerah & Top 10 Kab/Kota -->
        <div class="charts-grid-2">
            <div class="content-card" style="min-height: 380px;">
                <div class="content-card-header">
                    <h3>🗺️ Peta Persebaran Kontribusi Ekspor Daerah Riau</h3>
                    <span style="font-size: 0.75rem; color: #64748b;">Koordinat Sentra Pelabuhan &amp; Wilayah</span>
                </div>
                <div style="flex: 1; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; min-height: 300px;">
                    <div id="mapInteractive" style="width: 100%; height: 100%; min-height: 300px;"></div>
                </div>
            </div>

            <div class="content-card" style="min-height: 380px;">
                <div class="content-card-header">
                    <h3>Top 10 Kabupaten / Kota Penghasil Ekspor (FOB US$)</h3>
                </div>
                <div class="chart-container-box" style="height: 300px;"><canvas id="chartKabkotaBar"></canvas></div>
            </div>
        </div>

        <!-- 3 Tabel Tabulasi Detail: Negara Tujuan, Komoditas, dan Pelabuhan Muat -->
        <div class="section-title-row">
            <h2><span>📋</span> Tabulasi Rincian Perdagangan Berdasarkan Kategori</h2>
            <button type="button" class="btn-export-excel" onclick="exportTableToExcel('tableNegaraDetail', 'Rincian_Negara_Tujuan')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                Ekspor Tabulasi ke Excel
            </button>
        </div>

        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem;">
            <!-- Tabulasi Negara -->
            <div class="content-card">
                <div class="content-card-header">
                    <h3>Rincian Negara Tujuan</h3>
                </div>
                <div class="table-responsive-box" style="max-height: 320px;">
                    <table class="modern-table" id="tableNegaraDetail">
                        <thead>
                            <tr>
                                <th>Negara Mitra</th>
                                <th class="text-right">FOB (US$)</th>
                                <th class="text-right">Netto (kg)</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <!-- Tabulasi Komoditas -->
            <div class="content-card">
                <div class="content-card-header">
                    <h3>Rincian Komoditas Unggulan</h3>
                </div>
                <div class="table-responsive-box" style="max-height: 320px;">
                    <table class="modern-table" id="tableKomoditiDetail">
                        <thead>
                            <tr>
                                <th>Kelompok Komoditas</th>
                                <th class="text-right">FOB (US$)</th>
                                <th class="text-right">Netto (kg)</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <!-- Tabulasi Pelabuhan Muat -->
            <div class="content-card">
                <div class="content-card-header">
                    <h3>Rincian Pelabuhan Muat/Bongkar</h3>
                </div>
                <div class="table-responsive-box" style="max-height: 320px;">
                    <table class="modern-table" id="tablePelabuhanDetail">
                        <thead>
                            <tr>
                                <th>Pelabuhan</th>
                                <th class="text-right">FOB (US$)</th>
                                <th class="text-right">Netto (kg)</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

    </section>

</div>
@endsection

@push('scripts')
<script>
let dashboardCharts = {};
let riauMap = null;
let riauMapLayer = null;

// Koordinat Resmi Kabupaten/Kota di Provinsi Riau
const riauCoords = {
    "Kota Pekanbaru": [0.5071, 101.4478],
    "Kota Dumai": [1.6671, 101.4431],
    "Kab. Bengkalis": [1.4822, 102.0789],
    "Kab. Siak": [0.7937, 102.0494],
    "Kab. Kampar": [0.3394, 101.0253],
    "Kab. Rokan Hilir": [2.1585, 100.8239],
    "Kab. Rokan Hulu": [0.8631, 100.5283],
    "Kab. Indragiri Hilir": [-0.3236, 103.1558],
    "Kab. Indragiri Hulu": [-0.5517, 102.3168],
    "Kab. Pelalawan": [0.2238, 102.0833],
    "Kab. Kuantan Singingi": [-0.5303, 101.4485],
    "Kab. Kepulauan Meranti": [1.0113, 102.7118]
};

/**
 * Mekanisme Navigasi 3 Halaman Analisis
 */
function switchDashboardTab(tabId, buttonElement) {
    // Sembunyikan semua section halaman
    document.querySelectorAll('.dashboard-page-section').forEach(sec => {
        sec.classList.remove('active');
    });

    // Hilangkan status aktif pada semua tombol tab
    document.querySelectorAll('.nav-tab-button').forEach(btn => {
        btn.classList.remove('active');
    });

    // Aktifkan section dan tombol yang dipilih
    const targetSection = document.getElementById(tabId);
    if (targetSection) {
        targetSection.classList.add('active');
    }
    if (buttonElement) {
        buttonElement.classList.add('active');
    }

    // Refresh ukuran peta Leaflet jika berpindah ke Halaman 3 (Detail & Mapping)
    if (tabId === 'tabDetailMapping' && riauMap) {
        setTimeout(() => {
            riauMap.invalidateSize();
        }, 150);
    }
}

/**
 * Format Angka Indonesia
 */
function formatNumber(num) {
    if (num === null || num === undefined || isNaN(num)) return '0';
    return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(num);
}

/**
 * Reset Seluruh Input Filter
 */
function resetAllFilters() {
    document.getElementById('filterJenis').value = 'Ekspor';
    document.getElementById('filterTahun').value = 'ALL';
    document.getElementById('filterBulan').value = 'ALL';
    document.getElementById('filterTriwulan').value = 'ALL';
    document.getElementById('filterMigas').value = 'ALL';
    document.getElementById('filterKabkota').value = 'ALL';
    document.getElementById('filterKomoditi').value = 'ALL';
    document.getElementById('filterNegara').value = 'ALL';
    document.getElementById('filterPelabuhan').value = 'ALL';
    fetchDashboardData();
}

/**
 * Mengambil Data Komprehensif Melalui AJAX Berdasarkan Filter
 */
function fetchDashboardData() {
    const params = new URLSearchParams({
        jenis: document.getElementById('filterJenis').value,
        tahun: document.getElementById('filterTahun').value,
        bulan: document.getElementById('filterBulan').value,
        triwulan: document.getElementById('filterTriwulan').value,
        migas: document.getElementById('filterMigas').value,
        kabkota_id: document.getElementById('filterKabkota').value,
        komoditi: document.getElementById('filterKomoditi').value,
        negara_id: document.getElementById('filterNegara').value,
        pelabuhan_id: document.getElementById('filterPelabuhan').value,
    });

    fetch(`{{ route('dashboard.data') }}?${params.toString()}`)
        .then(res => res.json())
        .then(res => {
            if (!res.success) return;
            renderDashboardData(res);
        })
        .catch(err => console.error("Error fetching dashboard data:", err));
}

/**
 * Render Seluruh Komponen 3 Halaman
 */
function renderDashboardData(data) {
    const s = data.summary;

    // --- 1. RENDER HALAMAN 1 (OVERVIEW) ---
    // Pertumbuhan 4 Kartu Resmi BPS
    document.getElementById('kpiMtom').textContent = (s.mtom > 0 ? '+' : '') + s.mtom + '%';
    document.getElementById('kpiQtoq').textContent = (s.qtoq > 0 ? '+' : '') + s.qtoq + '%';
    document.getElementById('kpiYony').textContent = (s.yony > 0 ? '+' : '') + s.yony + '%';
    document.getElementById('kpiCtoc').textContent = (s.ctoc > 0 ? '+' : '') + s.ctoc + '%';

    // 5 KPI Utama
    document.getElementById('p1KpiFob').textContent = '$ ' + formatNumber(s.total_fob);
    document.getElementById('p1KpiNetto').textContent = formatNumber(s.total_netto) + ' kg';
    document.getElementById('p1KpiAvg').textContent = '$ ' + formatNumber(s.avg_per_transaksi);
    document.getElementById('p1KpiNegara').textContent = formatNumber(s.total_negara);
    document.getElementById('p1KpiKomoditi').textContent = formatNumber(s.total_komoditi);

    // Grafik Tren Bulanan FOB & Netto
    renderLineChart('chartTrenFobBulan', data.monthly.labels, data.monthly.fobValues, 'FOB (US$)', '#0284c7');
    renderLineChart('chartTrenVolBulan', data.monthly.labels, data.monthly.nettoValues, 'Netto (kg)', '#16a34a');

    // Grafik Perbandingan Migas vs Non-Migas (FOB & Netto)
    renderDualLineChart('chartTrenMigasFob', data.monthly.labels, data.monthly.migasFob, data.monthly.nonMigasFob, 'Migas ($)', 'Non-Migas ($)');
    renderDualLineChart('chartTrenMigasNetto', data.monthly.labels, data.monthly.migasNetto, data.monthly.nonMigasNetto, 'Migas (kg)', 'Non-Migas (kg)');

    // --- 2. RENDER HALAMAN 2 (TIME SERIES KOMODITAS) ---
    renderTimeSeriesTable('tableTsBulanFob', data.pivot_bulan_fob, true);
    renderTimeSeriesTable('tableTsBulanNetto', data.pivot_bulan_netto, false);
    renderTriwulanTable('tableTsTriwulanFob', data.pivot_triwulan_fob);

    // --- 3. RENDER HALAMAN 3 (DETAIL & MAPPING) ---
    document.getElementById('detailFobVal').textContent = 'US$ ' + formatNumber(s.total_fob);
    document.getElementById('detailNonMigasFob').textContent = 'US$ ' + formatNumber(s.non_migas_fob);
    document.getElementById('detailMigasFob').textContent = 'US$ ' + formatNumber(s.migas_fob);

    document.getElementById('detailNettoVal').textContent = formatNumber(s.total_netto) + ' kg';
    document.getElementById('detailNonMigasNetto').textContent = formatNumber(s.non_migas_netto) + ' kg';
    document.getElementById('detailMigasNetto').textContent = formatNumber(s.migas_netto) + ' kg';

    renderLeafletMap(data.kabkota_distribusi);
    renderBarChart('chartKabkotaBar', data.kabkota_distribusi);
    renderSimpleTable('tableNegaraDetail', data.top_negara);
    renderSimpleTable('tableKomoditiDetail', data.top_komoditi);
    renderSimpleTable('tablePelabuhanDetail', data.top_pelabuhan);
}

/**
 * Line Chart Reusable
 */
function renderLineChart(canvasId, labels, values, label, color) {
    if (dashboardCharts[canvasId]) dashboardCharts[canvasId].destroy();
    const ctx = document.getElementById(canvasId).getContext('2d');
    dashboardCharts[canvasId] = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: label,
                data: values,
                borderColor: color,
                backgroundColor: color + '15',
                fill: true,
                tension: 0.25,
                borderWidth: 2.5,
                pointRadius: 3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false } },
                y: { grid: { color: '#f1f5f9' }, ticks: { callback: v => '$' + (v >= 1000000 ? (v/1000000).toFixed(1) + 'M' : v) } }
            }
        }
    });
}

/**
 * Dual Line Chart Reusable (Migas vs Non-Migas)
 */
function renderDualLineChart(canvasId, labels, dataMigas, dataNonMigas, labelMigas, labelNonMigas) {
    if (dashboardCharts[canvasId]) dashboardCharts[canvasId].destroy();
    const ctx = document.getElementById(canvasId).getContext('2d');
    dashboardCharts[canvasId] = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                { label: labelNonMigas, data: dataNonMigas, borderColor: '#0284c7', backgroundColor: '#0284c715', fill: true, tension: 0.25, borderWidth: 2, pointRadius: 2.5 },
                { label: labelMigas, data: dataMigas, borderColor: '#f59e0b', backgroundColor: '#f59e0b15', fill: true, tension: 0.25, borderWidth: 2, pointRadius: 2.5 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'top', labels: { boxWidth: 12, font: { weight: 'bold' } } } },
            scales: { x: { grid: { display: false } }, y: { grid: { color: '#f1f5f9' } } }
        }
    });
}

/**
 * Horizontal Bar Chart (Top 10 Kab/Kota)
 */
function renderBarChart(canvasId, list) {
    if (dashboardCharts[canvasId]) dashboardCharts[canvasId].destroy();
    const top = (list || []).slice(0, 10);
    const labels = top.map(i => i.kabkota);
    const values = top.map(i => i.fob);

    const ctx = document.getElementById(canvasId).getContext('2d');
    dashboardCharts[canvasId] = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'FOB (US$)',
                data: values,
                backgroundColor: '#0284c7',
                borderRadius: 4
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { grid: { color: '#f1f5f9' } }, y: { grid: { display: false } } }
        }
    });
}

/**
 * Render Tabel Time Series Komoditas Bulanan
 */
function renderTimeSeriesTable(tableId, dataList, showMom) {
    const tbody = document.querySelector(`#${tableId} tbody`);
    tbody.innerHTML = '';
    if (!dataList || dataList.length === 0) {
        tbody.innerHTML = `<tr><td colspan="${showMom ? 15 : 14}" style="text-align:center; padding: 1.5rem; color:#94a3b8;">Tidak ada data komoditas untuk filter ini</td></tr>`;
        return;
    }

    dataList.forEach(item => {
        const tr = document.createElement('tr');
        let html = `<td style="font-weight:700; color:#0f172a;">${item.komoditi}</td>`;
        item.values.forEach(v => {
            html += `<td style="text-align:right;">${v > 0 ? formatNumber(v) : '—'}</td>`;
        });
        html += `<td style="text-align:right; font-weight:800; color:#0284c7;">${formatNumber(item.total)}</td>`;

        if (showMom) {
            let badgeClass = 'growth-zero';
            let sign = '';
            if (item.mom_growth > 0) { badgeClass = 'growth-pos'; sign = '+'; }
            else if (item.mom_growth < 0) { badgeClass = 'growth-neg'; }
            const momText = item.mom_growth !== null ? `${sign}${item.mom_growth}%` : '—';
            html += `<td style="text-align:right;"><span class="growth-badge ${badgeClass}">${momText}</span></td>`;
        }

        tr.innerHTML = html;
        tbody.appendChild(tr);
    });
}

/**
 * Render Tabel Time Series Triwulanan
 */
function renderTriwulanTable(tableId, dataList) {
    const tbody = document.querySelector(`#${tableId} tbody`);
    tbody.innerHTML = '';
    if (!dataList || dataList.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding: 1.5rem; color:#94a3b8;">Tidak ada data triwulanan</td></tr>`;
        return;
    }

    dataList.forEach(item => {
        const tr = document.createElement('tr');
        let html = `<td style="font-weight:700; color:#0f172a;">${item.komoditi}</td>`;
        item.values.forEach(v => {
            html += `<td style="text-align:right;">${v > 0 ? '$ ' + formatNumber(v) : '—'}</td>`;
        });
        html += `<td style="text-align:right; font-weight:800; color:#0369a1;">$ ${formatNumber(item.total)}</td>`;
        tr.innerHTML = html;
        tbody.appendChild(tr);
    });
}

/**
 * Render Tabel Detail Sederhana (Negara, Komoditas, Pelabuhan)
 */
function renderSimpleTable(tableId, dataList) {
    const tbody = document.querySelector(`#${tableId} tbody`);
    tbody.innerHTML = '';
    if (!dataList || dataList.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding: 1.25rem; color:#94a3b8;">Tidak ada data</td></tr>';
        return;
    }

    dataList.forEach(item => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td style="font-weight:600; color:#0f172a;">${item.item}</td>
            <td style="text-align:right; font-weight:700; color:#0284c7;">$ ${formatNumber(item.fob)}</td>
            <td style="text-align:right; color:#16a34a;">${formatNumber(item.netto)}</td>
        `;
        tbody.appendChild(tr);
    });
}

/**
 * Peta Interaktif Leaflet Kontribusi Daerah
 */
function renderLeafletMap(kabList) {
    if (!riauMap) {
        riauMap = L.map('mapInteractive').setView([0.55, 101.8], 7);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap & BPS Riau'
        }).addTo(riauMap);
        riauMapLayer = L.layerGroup().addTo(riauMap);
    }
    riauMapLayer.clearLayers();

    if (!kabList || kabList.length === 0) return;

    kabList.forEach(item => {
        const coords = riauCoords[item.kabkota] || [0.5071, 101.4478];
        const circle = L.circleMarker(coords, {
            radius: Math.min(Math.max((item.fob / 50000000) * 12, 8), 24),
            fillColor: '#0284c7',
            color: '#ffffff',
            weight: 2,
            fillOpacity: 0.85
        });
        circle.bindPopup(`
            <div style="font-family: sans-serif; font-size: 0.85rem;">
                <strong style="color: #0f172a;">${item.kabkota}</strong><br/>
                <span style="color:#0284c7;">FOB: US$ ${formatNumber(item.fob)}</span><br/>
                <span style="color:#16a34a;">Netto: ${formatNumber(item.netto)} kg</span>
            </div>
        `);
        circle.addTo(riauMapLayer);
    });
}

/**
 * Fitur Ekspor Tabel HTML ke File Excel (.xlsx) Menggunakan SheetJS
 */
function exportTableToExcel(tableId, fileName) {
    const table = document.getElementById(tableId);
    if (!table) return;
    const wb = XLSX.utils.table_to_book(table, { sheet: "Sheet1" });
    XLSX.writeFile(wb, `${fileName}_${new Date().toISOString().slice(0,10)}.xlsx`);
}

/**
 * Fitur Pengurutan Tabel (Sort Alphabetic & Numeric)
 */
function sortTable(tableId, colIdx) {
    const table = document.getElementById(tableId);
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    rows.sort((a, b) => {
        const aText = a.children[colIdx]?.innerText.trim() || '';
        const bText = b.children[colIdx]?.innerText.trim() || '';
        return aText.localeCompare(bText);
    });
    tbody.innerHTML = '';
    rows.forEach(r => tbody.appendChild(r));
}

function sortTableNumber(tableId, colIdx) {
    const table = document.getElementById(tableId);
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    rows.sort((a, b) => {
        const aVal = parseFloat((a.children[colIdx]?.innerText.replace(/[^0-9,-]/g, '') || '0').replace(',', '.')) || 0;
        const bVal = parseFloat((b.children[colIdx]?.innerText.replace(/[^0-9,-]/g, '') || '0').replace(',', '.')) || 0;
        return bVal - aVal; // Highest to lowest
    });
    tbody.innerHTML = '';
    rows.forEach(r => tbody.appendChild(r));
}

// Inisialisasi Pertama
document.addEventListener('DOMContentLoaded', function() {
    fetchDashboardData();
});
</script>
@endpush
