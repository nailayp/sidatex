@extends('layouts.app')

@section('title', 'Upload & Pratinjau Data Neraca | SIDATEX BPS Provinsi Riau')

@section('content')
<div class="neraca-upload-container">
    {{-- Header Banner Halaman --}}
    <div class="page-header-card">
        <div class="header-content">
            <div class="header-icon-box">
                <span class="icon-main">
                    <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                </span>
            </div>
            <div>
                <div class="header-badge-row">
                    <span class="badge-tag">MODUL NERACA PERDAGANGAN (BRS)</span>
                    <span class="badge-role">KHUSUS SUPER ADMIN</span>
                    <span class="badge-isolation">MODE PRATINJAU (READ-ONLY)</span>
                </div>
                <h1 class="page-title">Upload & Validasi Data BRS Neraca</h1>
                <p class="page-subtitle">Unggah berkas resmi BPS (.xlsx) Ekspor atau Impor Luar Negeri untuk pemeriksaan struktur, rekonsiliasi dimensi, dan pratinjau data.</p>
            </div>
        </div>
        <div class="header-actions">
            <a href="{{ route('neraca.dashboard') }}" class="btn-outline-back">
                <span>←</span> Kembali ke Dashboard Neraca
            </a>
        </div>
    </div>

    {{-- Form Area Upload & Konfigurasi --}}
    <div class="upload-card" id="uploadFormCard">
        <div class="card-section-title">
            <h3>Pilih Berkas & Parameter Data</h3>
            <p>Pastikan berkas Excel yang diunggah sesuai dengan format resmi Olah Data BRS dari BPS Provinsi Riau.</p>
        </div>

        <form id="neracaUploadForm" enctype="multipart/form-data">
            @csrf
            <div class="form-grid">
                {{-- Parameter Jenis Transaksi --}}
                <div class="form-group">
                    <label for="source_type" class="form-label">
                        Jenis Transaksi <span class="required-star">*</span>
                    </label>
                    <div class="radio-toggle-group">
                        <label class="radio-toggle-item active" id="labelEkspor">
                            <input type="radio" name="source_type" value="ekspor" checked>
                            <span class="toggle-icon">
                                <svg class="w-5 h-5 inline-block text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 11l3-3m0 0l3 3m-3-3v8m-7 4h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                            </span>
                            <span class="toggle-text">
                                <strong>Ekspor Luar Negeri</strong>
                                <small>Worksheet: RawDataEkspor (Nilai FOB)</small>
                            </span>
                        </label>
                        <label class="radio-toggle-item" id="labelImpor">
                            <input type="radio" name="source_type" value="impor">
                            <span class="toggle-icon">
                                <svg class="w-5 h-5 inline-block text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13l-3 3m0 0l-3-3m3 3V8m7 13H5a2 2 0 01-2-2V7a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2z"/>
                                </svg>
                            </span>
                            <span class="toggle-text">
                                <strong>Impor Luar Negeri</strong>
                                <small>Worksheet: RawDataImpor (Nilai CIF)</small>
                            </span>
                        </label>
                    </div>
                </div>

                {{-- Dropzone / File Picker --}}
                <div class="form-group">
                    <label class="form-label">
                        Berkas Excel BRS (.xlsx) <span class="required-star">*</span>
                    </label>
                    <div class="dropzone-area" id="dropzoneBox">
                        <input type="file" name="file" id="excelFile" accept=".xlsx" class="file-input-hidden" required>
                        <div class="dropzone-content" id="dropzoneContent">
                            <div class="dropzone-icon">
                                <svg class="w-12 h-12 text-slate-400 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            <h4 class="dropzone-title">Seret & lepas berkas ke sini, atau klik untuk memilih</h4>
                            <p class="dropzone-hint">Format didukung: <strong>.xlsx</strong> (Maksimum 100 MB). Engine XML streaming siap memproses hingga >50.000 baris.</p>
                            <div class="btn-browse-file">Pilih Berkas Excel</div>
                        </div>
                        <div class="dropzone-selected-file" id="selectedFileInfo" style="display: none;">
                            <div class="file-card-info">
                                <span class="file-icon-badge">XLSX</span>
                                <div class="file-details">
                                    <strong id="fileNameDisplay">-</strong>
                                    <span id="fileSizeDisplay">-</span>
                                </div>
                            </div>
                            <button type="button" class="btn-remove-file" id="btnRemoveFile" title="Ganti Berkas">Ganti</button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Action Buttons --}}
            <div class="form-actions-bar">
                <div class="action-info-hint">
                    <span class="hint-icon">ℹ️</span>
                    <span>Proses ini <strong>100% aman</strong>. Data hanya dibaca secara streaming ke memori pratinjau dan <strong>tidak dimasukkan</strong> ke tabel database.</span>
                </div>
                <button type="submit" class="btn-submit-preview" id="btnSubmitPreview">
                    <span class="btn-icon">
                        <svg class="w-4 h-4 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </span>
                    <span id="btnSubmitText">Mulai Verifikasi & Pratinjau</span>
                </button>
            </div>
        </form>

        {{-- Loading Overlay Card --}}
        <div class="loading-overlay" id="loadingOverlay" style="display: none;">
            <div class="loading-spinner"></div>
            <h4 class="loading-title">Sedang Membaca & Memvalidasi Berkas...</h4>
            <p class="loading-desc" id="loadingStatusText">Membaca struktur workbook XLSX dan shared strings secara streaming...</p>
            <div class="loading-progress-pulse"></div>
        </div>
    </div>

    {{-- Error Alert Box --}}
    <div class="alert-box-danger" id="alertErrorBox" style="display: none;">
        <div class="alert-icon">
            <svg class="w-5 h-5 text-amber-500 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
        </div>
        <div class="alert-content">
            <h4 class="alert-title" id="alertErrorTitle">Gagal Membaca Berkas</h4>
            <p class="alert-message" id="alertErrorMessage">-</p>
        </div>
        <button type="button" class="btn-close-alert" onclick="document.getElementById('alertErrorBox').style.display='none'">×</button>
    </div>

    {{-- HASIL PREVIEW AREA (TERSEMBUNYI HINGGA FILE BERHASIL DIBACA) --}}
    <div id="previewResultsContainer" style="display: none;" class="preview-results-wrapper">

        {{-- Top KPI Cards Grid --}}
        <div class="kpi-banner-grid">
            <div class="kpi-box">
                <span class="kpi-label">TOTAL BARIS TRANSAKSI</span>
                <span class="kpi-value highlight-blue" id="kpiTotalRows">-</span>
                <span class="kpi-sub" id="kpiValidRows">Baris Valid: -</span>
            </div>
            <div class="kpi-box">
                <span class="kpi-label" id="kpiNilaiLabel">TOTAL NILAI FOB (USD)</span>
                <span class="kpi-value highlight-navy" id="kpiTotalNilai">-</span>
                <span class="kpi-sub" id="kpiNilaiJuta">Setara: -</span>
            </div>
            <div class="kpi-box">
                <span class="kpi-label">TOTAL BERAT NETTO (KG)</span>
                <span class="kpi-value highlight-cyan" id="kpiTotalBerat">-</span>
                <span class="kpi-sub" id="kpiBeratRibuTon">Setara: -</span>
            </div>
            <div class="kpi-box">
                <span class="kpi-label">CAKUPAN PERIODE WAKTU</span>
                <span class="kpi-value" id="kpiPeriode">-</span>
                <span class="kpi-sub" id="kpiTotalBulan">Total: - bulan data</span>
            </div>
        </div>

        {{-- Mapping Warnings & Dimension Validation Section --}}
        <div class="validation-summary-card" id="validationSummaryCard">
            <div class="validation-header">
                <div class="status-indicator-badge" id="statusIndicatorBadge">
                    <span class="status-dot"></span>
                    <span id="statusIndicatorText">Status Validasi</span>
                </div>
                <div class="dimensions-count-badges">
                    <span class="badge-dim">
                        <svg class="w-4 h-4 inline-block mr-1 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                        <strong id="countHs">-</strong> Kode HS
                    </span>
                    <span class="badge-dim">
                        <svg class="w-4 h-4 inline-block mr-1 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <strong id="countNegara">-</strong> Negara Tujuan
                    </span>
                    <span class="badge-dim">
                        <svg class="w-4 h-4 inline-block mr-1 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <strong id="countKabkota">-</strong> Kab/Kota
                    </span>
                </div>
            </div>

            <div class="warning-details-container" id="warningDetailsContainer">
                {{-- Warning HS belum terdaftar --}}
                <div class="warning-callout" id="unmappedHsCallout" style="display: none;">
                    <div class="callout-icon">
                        <svg class="w-5 h-5 text-amber-500 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <div class="callout-body">
                        <div class="callout-title">
                            <strong>Ditemukan <span id="unmappedHsCountBadge">27</span> Kode HS Belum Terdaftar di Master HS</strong>
                            <span class="callout-badge-warning">Perlu Ditinjau</span>
                        </div>
                        <p class="callout-text">
                            Kode-kode HS berikut terdapat pada berkas sumber asli BRS namun belum tercatat dalam tabel referensi <code>master_hs</code>.
                            Sistem <strong>tidak melakukan fallback otomatis</strong> ke kode default (misal: <code>010000</code>) agar granularitas data tetap terjaga utuh.
                        </p>
                        <div class="unmapped-tags-list" id="unmappedHsTagsList"></div>
                    </div>
                </div>

                {{-- Normalisasi Meranti Alert --}}
                <div class="info-callout" id="merantiCallout" style="display: none;">
                    <div class="callout-icon">
                        <svg class="w-5 h-5 text-blue-500 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="callout-body">
                        <div class="callout-title">
                            <strong>Normalisasi Otomatis Kabupaten / Kota Berhasil</strong>
                            <span class="callout-badge-success">Tervalidasi</span>
                        </div>
                        <p class="callout-text">
                            Entri <strong>"Meranti"</strong> pada berkas sumber BRS secara otomatis disesuaikan ke nama resmi master data <strong>"Kab. Kepulauan Meranti"</strong> (ID: 10), sehingga mencegah terjadinya data tanpa daerah (<code>NULL</code>).
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Ringkasan Dimensi Tabs / Grid --}}
        <div class="dimension-summary-card">
            <div class="section-toolbar">
                <h3 class="section-title">Ringkasan Klasifikasi & Dimensi Analitik</h3>
                <span class="text-muted">Data diekstrak langsung dari worksheet resmi BRS</span>
            </div>
            <div class="dimension-info-grid">
                <div class="dim-info-item">
                    <span class="dim-icon">
                        <svg class="w-5 h-5 text-blue-600 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </span>
                    <div>
                        <strong>Dimensi Negara Tujuan</strong>
                        <p>174 negara diidentifikasi berdasarkan kode ISO/BPS asli, tanpa fallback placeholder.</p>
                    </div>
                </div>
                <div class="dim-info-item">
                    <span class="dim-icon">
                        <svg class="w-5 h-5 text-indigo-600 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                    </span>
                    <div>
                        <strong>Sektor Komoditas & KBLI</strong>
                        <p>Sektor Industri Pengolahan, Pertanian, Pertambangan, dan Migas terpetakan per baris.</p>
                    </div>
                </div>
                <div class="dim-info-item">
                    <span class="dim-icon">
                        <svg class="w-5 h-5 text-slate-700 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/>
                        </svg>
                    </span>
                    <div>
                        <strong>Wilayah Administratif</strong>
                        <p>Aktivitas ekspor tercatat dari 8 kabupaten/kota aktif di Provinsi Riau.</p>
                    </div>
                </div>
                <div class="dim-info-item">
                    <span class="dim-icon">
                        <svg class="w-5 h-5 text-sky-600 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </span>
                    <div>
                        <strong>Klasifikasi BEC & HS2</strong>
                        <p>Mendukung pengelompokan Barang Konsumsi, Bahan Baku/Penolong, dan Barang Modal.</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tabel Sampel & Pagination Pratinjau --}}
        <div class="preview-table-card">
            <div class="table-card-header">
                <div>
                    <h3 class="table-card-title">Sampel Data Transaksi</h3>
                    <p class="table-card-subtitle">Menampilkan baris transaksi nyata dari berkas sumber dengan navigasi halaman streaming.</p>
                </div>
                <div class="pagination-info-box" id="paginationInfoBox">
                    Menampilkan baris <strong id="pageRangeText">1 - 20</strong> dari total <strong id="pageTotalText">53.066</strong>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table-preview" id="tablePreview">
                    <thead>
                        <tr>
                            <th style="width: 60px;">No</th>
                            <th style="width: 80px;">Tahun</th>
                            <th style="width: 70px;">Bulan</th>
                            <th style="width: 120px;">Kode HS</th>
                            <th>Deskripsi Komoditas / HS</th>
                            <th style="width: 130px;">Negara</th>
                            <th style="width: 150px;">Kabupaten / Kota</th>
                            <th style="width: 140px; text-align: right;">Berat (Kg)</th>
                            <th style="width: 160px; text-align: right;" id="thNilaiHeader">Nilai FOB (USD)</th>
                        </tr>
                    </thead>
                    <tbody id="tablePreviewBody">
                        <tr>
                            <td colspan="9" class="text-center py-4">Memuat data sampel...</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Pagination Controls --}}
            <div class="table-pagination-footer">
                <div class="pagination-left">
                    <span id="pageStateText">Halaman 1 dari -</span>
                </div>
                <div class="pagination-right">
                    <button type="button" class="btn-page" id="btnPrevPage" disabled>← Sebelumnya</button>
                    <span class="page-current-pill" id="pageNumberPill">1</span>
                    <button type="button" class="btn-page" id="btnNextPage">Berikutnya →</button>
                </div>
            </div>
        </div>

        {{-- Proteksi Ingestion Alert Banner --}}
        <div class="ingestion-gate-card">
            <div class="gate-icon-badge">
                <svg class="w-5 h-5 text-slate-600 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
            <div class="gate-body">
                <h4>Fitur Ingestion & Penyimpanan Belum Diaktifkan</h4>
                <p>
                    Pada <strong>Tahap 2R</strong>, integrasi sistem difokuskan sepenuhnya pada validasi struktur berkas, verifikasi total metrik, dan pemeriksaan mapping warning.
                    Penyimpanan permanen ke tabel <code>transactions</code> dinonaktifkan sesuai protokol integritas data SIDATEX.
                </p>
                <div class="gate-buttons">
                    <button type="button" class="btn-disabled-ingest" disabled title="Fitur dinonaktifkan pada Tahap 2R">
                        <span>
                            <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                            </svg>
                        </span> Simpan ke Database (Belum Diaktifkan)
                    </button>
                    <button type="button" class="btn-disabled-ingest" disabled title="Fitur dinonaktifkan pada Tahap 2R">
                        <span>
                            <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </span> Proses & Rilis Batch (Belum Diaktifkan)
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>

<style>
/* ==========================================================================
   STYLE KHUSUS HALAMAN UPLOAD & PREVIEW NERACA (SIDATEX DESIGN SYSTEM)
   ========================================================================== */
.neraca-upload-container {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    padding: 0.5rem;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    color: #1e293b;
}

/* Page Header Card */
.page-header-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
    flex-wrap: wrap;
    gap: 1.25rem;
}
.header-content {
    display: flex;
    align-items: center;
    gap: 1.25rem;
}
.header-icon-box {
    width: 56px;
    height: 56px;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    flex-shrink: 0;
}
.header-badge-row {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 0.4rem;
    flex-wrap: wrap;
}
.badge-tag {
    background: #1e3a8a;
    color: #ffffff;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.2rem 0.55rem;
    border-radius: 4px;
    letter-spacing: 0.5px;
}
.badge-role {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #cbd5e1;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.2rem 0.55rem;
    border-radius: 4px;
}
.badge-isolation {
    background: #ecfdf5;
    color: #065f46;
    border: 1px solid #a7f3d0;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.2rem 0.55rem;
    border-radius: 4px;
}
.page-title {
    font-size: 1.4rem;
    font-weight: 800;
    color: #0f172a;
    margin: 0;
}
.page-subtitle {
    font-size: 0.88rem;
    color: #64748b;
    margin: 0.25rem 0 0 0;
}
.btn-outline-back {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.6rem 1.1rem;
    background: #ffffff;
    color: #1e40af;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 0.88rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s;
}
.btn-outline-back:hover {
    background: #f8fafc;
    border-color: #94a3b8;
    color: #1d4ed8;
}

/* Upload Card & Form */
.upload-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.75rem;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
    position: relative;
}
.card-section-title h3 {
    font-size: 1.15rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
}
.card-section-title p {
    font-size: 0.85rem;
    color: #64748b;
    margin: 0.25rem 0 1.25rem 0;
}
.form-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
}
.form-label {
    display: block;
    font-size: 0.88rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 0.5rem;
}
.required-star {
    color: #ef4444;
}

/* Radio Toggle Group */
.radio-toggle-group {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}
.radio-toggle-item {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 1rem;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    cursor: pointer;
    background: #ffffff;
    transition: all 0.2s ease;
}
.radio-toggle-item input[type="radio"] {
    display: none;
}
.radio-toggle-item .toggle-icon {
    font-size: 1.6rem;
}
.radio-toggle-item .toggle-text {
    display: flex;
    flex-direction: column;
}
.radio-toggle-item .toggle-text strong {
    font-size: 0.95rem;
    color: #1e293b;
}
.radio-toggle-item .toggle-text small {
    font-size: 0.78rem;
    color: #64748b;
}
.radio-toggle-item.active {
    border-color: #2563eb;
    background: #eff6ff;
}
.radio-toggle-item.active .toggle-text strong {
    color: #1d4ed8;
}

/* Dropzone */
.dropzone-area {
    border: 2px dashed #cbd5e1;
    border-radius: 12px;
    padding: 2.25rem 1.5rem;
    text-align: center;
    background: #f8fafc;
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
}
.dropzone-area:hover {
    border-color: #3b82f6;
    background: #f0f7ff;
}
.file-input-hidden {
    position: absolute;
    width: 100%;
    height: 100%;
    top: 0;
    left: 0;
    opacity: 0;
    cursor: pointer;
}
.dropzone-icon {
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
}
.dropzone-title {
    font-size: 1rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 0.25rem 0;
}
.dropzone-hint {
    font-size: 0.82rem;
    color: #64748b;
    margin: 0 0 1rem 0;
}
.btn-browse-file {
    display: inline-block;
    padding: 0.45rem 1.25rem;
    background: #ffffff;
    color: #2563eb;
    border: 1px solid #bfdbfe;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 600;
}
.dropzone-selected-file {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 0.85rem 1.25rem;
}
.file-card-info {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}
.file-icon-badge {
    background: #10b981;
    color: white;
    font-size: 0.72rem;
    font-weight: 800;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
}
.file-details {
    display: flex;
    flex-direction: column;
    text-align: left;
}
.file-details strong {
    font-size: 0.9rem;
    color: #0f172a;
}
.file-details span {
    font-size: 0.78rem;
    color: #64748b;
}
.btn-remove-file {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fecaca;
    padding: 0.35rem 0.75rem;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
}

/* Form Actions */
.form-actions-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 1.5rem;
    padding-top: 1.25rem;
    border-top: 1px solid #f1f5f9;
    flex-wrap: wrap;
    gap: 1rem;
}
.action-info-hint {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.82rem;
    color: #475569;
}
.btn-submit-preview {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.75rem;
    background: #1d4ed8;
    color: #ffffff;
    border: none;
    border-radius: 8px;
    font-size: 0.95rem;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 2px 4px rgba(29, 78, 216, 0.25);
    transition: all 0.2s;
}
.btn-submit-preview:hover {
    background: #1e40af;
    transform: translateY(-1px);
}
.btn-submit-preview:disabled {
    background: #94a3b8;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

/* Loading Overlay */
.loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(4px);
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    z-index: 50;
    padding: 2rem;
    text-align: center;
}
.loading-spinner {
    width: 48px;
    height: 48px;
    border: 4px solid #e2e8f0;
    border-top: 4px solid #1d4ed8;
    border-radius: 50%;
    animation: spin 0.9s linear infinite;
    margin-bottom: 1rem;
}
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
.loading-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 0.25rem 0;
}
.loading-desc {
    font-size: 0.85rem;
    color: #64748b;
    margin: 0;
}

/* Error Box */
.alert-box-danger {
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 10px;
    padding: 1rem 1.25rem;
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
}
.alert-icon {
    font-size: 1.4rem;
    color: #dc2626;
}
.alert-content {
    flex-grow: 1;
}
.alert-title {
    font-size: 0.92rem;
    font-weight: 700;
    color: #991b1b;
    margin: 0 0 0.2rem 0;
}
.alert-message {
    font-size: 0.85rem;
    color: #b91c1c;
    margin: 0;
}
.btn-close-alert {
    background: transparent;
    border: none;
    font-size: 1.2rem;
    color: #991b1b;
    cursor: pointer;
}

/* KPI Banner Grid */
.kpi-banner-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap: 1rem;
}
.kpi-box {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.25rem;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
    display: flex;
    flex-direction: column;
}
.kpi-label {
    font-size: 0.72rem;
    font-weight: 700;
    color: #64748b;
    letter-spacing: 0.5px;
    margin-bottom: 0.35rem;
}
.kpi-value {
    font-size: 1.55rem;
    font-weight: 800;
    line-height: 1.2;
    margin-bottom: 0.25rem;
}
.highlight-blue { color: #2563eb; }
.highlight-navy { color: #0f172a; }
.highlight-cyan { color: #0891b2; }
.kpi-sub {
    font-size: 0.78rem;
    color: #64748b;
}

/* Validation Summary Card */
.validation-summary-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.25rem 1.5rem;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
}
.validation-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1rem;
    padding-bottom: 0.85rem;
    border-bottom: 1px solid #f1f5f9;
}
.status-indicator-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.35rem 0.85rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 700;
}
.status-indicator-badge.valid {
    background: #ecfdf5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}
.status-indicator-badge.review {
    background: #fffbeb;
    color: #92400e;
    border: 1px solid #fde68a;
}
.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: currentColor;
}
.dimensions-count-badges {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.badge-dim {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    font-size: 0.8rem;
    padding: 0.3rem 0.65rem;
    border-radius: 6px;
    color: #334155;
}
.warning-details-container {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}
.warning-callout {
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 8px;
    padding: 1rem 1.25rem;
    display: flex;
    gap: 0.85rem;
}
.callout-icon {
    font-size: 1.3rem;
}
.callout-body {
    flex-grow: 1;
}
.callout-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    color: #78350f;
    margin-bottom: 0.25rem;
}
.callout-badge-warning {
    background: #fef3c7;
    color: #92400e;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.15rem 0.45rem;
    border-radius: 4px;
}
.callout-badge-success {
    background: #dcfce7;
    color: #166534;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.15rem 0.45rem;
    border-radius: 4px;
}
.callout-text {
    font-size: 0.82rem;
    color: #92400e;
    margin: 0 0 0.5rem 0;
    line-height: 1.4;
}
.unmapped-tags-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}
.tag-hs {
    background: #ffffff;
    border: 1px solid #fcd34d;
    color: #78350f;
    font-family: monospace;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 0.2rem 0.45rem;
    border-radius: 4px;
}
.info-callout {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 8px;
    padding: 0.85rem 1.25rem;
    display: flex;
    gap: 0.85rem;
}
.info-callout .callout-title {
    color: #14532d;
}
.info-callout .callout-text {
    color: #166534;
}

/* Dimension Summary Grid */
.dimension-summary-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.25rem 1.5rem;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
}
.section-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.section-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
}
.text-muted {
    font-size: 0.8rem;
    color: #64748b;
}
.dimension-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1rem;
}
.dim-info-item {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 0.85rem 1rem;
    display: flex;
    gap: 0.75rem;
    align-items: flex-start;
}
.dim-icon {
    font-size: 1.4rem;
}
.dim-info-item strong {
    font-size: 0.85rem;
    color: #1e293b;
    display: block;
    margin-bottom: 0.2rem;
}
.dim-info-item p {
    font-size: 0.78rem;
    color: #64748b;
    margin: 0;
    line-height: 1.35;
}

/* Preview Table Card */
.preview-table-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.25rem 1.5rem;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
}
.table-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    flex-wrap: wrap;
    gap: 0.75rem;
}
.table-card-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
}
.table-card-subtitle {
    font-size: 0.8rem;
    color: #64748b;
    margin: 0.2rem 0 0 0;
}
.pagination-info-box {
    font-size: 0.85rem;
    color: #475569;
}
.table-responsive {
    overflow-x: auto;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
}
.table-preview {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
}
.table-preview th {
    background: #f8fafc;
    color: #334155;
    font-weight: 700;
    padding: 0.75rem 0.85rem;
    border-bottom: 1px solid #cbd5e1;
    text-align: left;
    white-space: nowrap;
}
.table-preview td {
    padding: 0.65rem 0.85rem;
    border-bottom: 1px solid #f1f5f9;
    color: #1e293b;
}
.table-preview tr:hover td {
    background: #f8fafc;
}

/* Pagination Footer */
.table-pagination-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 1rem;
    padding-top: 0.75rem;
    border-top: 1px solid #f1f5f9;
}
.pagination-left {
    font-size: 0.85rem;
    color: #64748b;
}
.pagination-right {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.btn-page {
    padding: 0.4rem 0.85rem;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 0.82rem;
    font-weight: 600;
    color: #334155;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-page:hover:not(:disabled) {
    background: #f1f5f9;
    border-color: #94a3b8;
}
.btn-page:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.page-current-pill {
    padding: 0.35rem 0.75rem;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 6px;
    font-size: 0.82rem;
    font-weight: 700;
    color: #1d4ed8;
}

/* Ingestion Gate Card */
.ingestion-gate-card {
    background: #f8fafc;
    border: 2px dashed #cbd5e1;
    border-radius: 12px;
    padding: 1.5rem;
    display: flex;
    gap: 1.25rem;
    align-items: center;
    margin-top: 0.5rem;
}
.gate-icon-badge {
    width: 48px;
    height: 48px;
    background: #e2e8f0;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
}
.gate-body h4 {
    font-size: 1rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 0.25rem 0;
}
.gate-body p {
    font-size: 0.82rem;
    color: #64748b;
    margin: 0 0 1rem 0;
    line-height: 1.4;
}
.gate-buttons {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}
.btn-disabled-ingest {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.6rem 1.1rem;
    background: #e2e8f0;
    color: #94a3b8;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: not-allowed;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Radio Toggle Selection
    const radioEkspor = document.querySelector('input[name="source_type"][value="ekspor"]');
    const radioImpor = document.querySelector('input[name="source_type"][value="impor"]');
    const labelEkspor = document.getElementById('labelEkspor');
    const labelImpor = document.getElementById('labelImpor');

    function updateRadioState() {
        if (radioEkspor.checked) {
            labelEkspor.classList.add('active');
            labelImpor.classList.remove('active');
        } else {
            labelImpor.classList.add('active');
            labelEkspor.classList.remove('active');
        }
    }
    radioEkspor.addEventListener('change', updateRadioState);
    radioImpor.addEventListener('change', updateRadioState);

    // 2. Dropzone & File Pick
    const excelFileInput = document.getElementById('excelFile');
    const dropzoneContent = document.getElementById('dropzoneContent');
    const selectedFileInfo = document.getElementById('selectedFileInfo');
    const fileNameDisplay = document.getElementById('fileNameDisplay');
    const fileSizeDisplay = document.getElementById('fileSizeDisplay');
    const btnRemoveFile = document.getElementById('btnRemoveFile');

    excelFileInput.addEventListener('change', function () {
        if (this.files && this.files[0]) {
            const f = this.files[0];
            fileNameDisplay.textContent = f.name;
            fileSizeDisplay.textContent = (f.size / (1024 * 1024)).toFixed(2) + ' MB';
            dropzoneContent.style.display = 'none';
            selectedFileInfo.style.display = 'flex';
        }
    });

    btnRemoveFile.addEventListener('click', function (e) {
        e.stopPropagation();
        excelFileInput.value = '';
        dropzoneContent.style.display = 'block';
        selectedFileInfo.style.display = 'none';
    });

    // 3. Upload & Preview Submission
    const form = document.getElementById('neracaUploadForm');
    const loadingOverlay = document.getElementById('loadingOverlay');
    const btnSubmitPreview = document.getElementById('btnSubmitPreview');
    const alertErrorBox = document.getElementById('alertErrorBox');
    const alertErrorMessage = document.getElementById('alertErrorMessage');
    const previewResults = document.getElementById('previewResultsContainer');

    // Pagination State
    let currentPage = 1;
    let totalPages = 1;
    let perPage = 20;

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        if (!excelFileInput.files || !excelFileInput.files[0]) {
            alert('Silakan pilih berkas Excel terlebih dahulu.');
            return;
        }

        const formData = new FormData(form);

        // UI Loading State
        loadingOverlay.style.display = 'flex';
        btnSubmitPreview.disabled = true;
        alertErrorBox.style.display = 'none';
        previewResults.style.display = 'none';

        fetch("{{ route('neraca.upload.preview') }}", {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json().then(data => ({ status: response.status, body: data })))
        .then(({ status, body }) => {
            loadingOverlay.style.display = 'none';
            btnSubmitPreview.disabled = false;

            if (status !== 200 || !body.success) {
                alertErrorMessage.textContent = body.message || 'Terjadi kesalahan saat memproses berkas.';
                alertErrorBox.style.display = 'flex';
                return;
            }

            // Render Preview Data
            renderPreviewDashboard(body.preview);
        })
        .catch(err => {
            loadingOverlay.style.display = 'none';
            btnSubmitPreview.disabled = false;
            alertErrorMessage.textContent = 'Gagal mengirim berkas ke server: ' + err.message;
            alertErrorBox.style.display = 'flex';
        });
    });

    function renderPreviewDashboard(p) {
        // KPI Values
        document.getElementById('kpiTotalRows').textContent = Number(p.total_rows_read).toLocaleString('id-ID');
        document.getElementById('kpiValidRows').textContent = 'Baris Valid: ' + Number(p.valid_rows_count).toLocaleString('id-ID');

        const isEkspor = (p.source_type === 'ekspor');
        document.getElementById('kpiNilaiLabel').textContent = isEkspor ? 'TOTAL NILAI FOB (USD)' : 'TOTAL NILAI CIF (USD)';
        document.getElementById('thNilaiHeader').textContent = isEkspor ? 'Nilai FOB (USD)' : 'Nilai CIF (USD)';

        const valNilai = parseFloat(p.totals.total_nilai);
        document.getElementById('kpiTotalNilai').textContent = '$' + valNilai.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('kpiNilaiJuta').textContent = 'Setara: ' + Number(p.totals.total_nilai_juta_usd).toLocaleString('id-ID') + ' Juta USD';

        const valBerat = parseFloat(p.totals.total_berat_kg);
        document.getElementById('kpiTotalBerat').textContent = valBerat.toLocaleString('id-ID', { minimumFractionDigits: 3, maximumFractionDigits: 3 }) + ' kg';
        document.getElementById('kpiBeratRibuTon').textContent = 'Setara: ' + Number(p.totals.total_berat_ribu_ton).toLocaleString('id-ID') + ' Ribu Ton';

        // Periode
        const years = p.periods.years_available || [];
        const startY = years[0] || '-';
        const endY = years[years.length - 1] || '-';
        document.getElementById('kpiPeriode').textContent = startY === endY ? startY : (startY + ' s.d. ' + endY);
        document.getElementById('kpiTotalBulan').textContent = 'Total: ' + p.periods.total_months + ' bulan data';

        // Dimensi Count
        document.getElementById('countHs').textContent = p.dimensions.unique_hs_count;
        document.getElementById('countNegara').textContent = p.dimensions.unique_country_count;
        document.getElementById('countKabkota').textContent = p.dimensions.unique_kabkota_count;

        // Warnings & Validation Status
        const warnings = p.dimensions.mapping_warnings || {};
        const statusBadge = document.getElementById('statusIndicatorBadge');
        const statusText = document.getElementById('statusIndicatorText');
        const unmappedCallout = document.getElementById('unmappedHsCallout');
        const unmappedTags = document.getElementById('unmappedHsTagsList');
        const merantiCallout = document.getElementById('merantiCallout');

        statusBadge.className = 'status-indicator-badge ' + (warnings.status_indicator === 'Valid' ? 'valid' : 'review');
        statusText.textContent = warnings.status_indicator || 'Valid';

        if (warnings.unregistered_hs && warnings.unregistered_hs.length > 0) {
            unmappedCallout.style.display = 'flex';
            document.getElementById('unmappedHsCountBadge').textContent = warnings.unregistered_hs.length;
            unmappedTags.innerHTML = warnings.unregistered_hs.map(hs => `<span class="tag-hs">${hs}</span>`).join('');
        } else {
            unmappedCallout.style.display = 'none';
        }

        if (warnings.kabkota_normalization && warnings.kabkota_normalization.length > 0) {
            merantiCallout.style.display = 'flex';
        } else {
            merantiCallout.style.display = 'none';
        }

        // Tampilkan preview container
        previewResults.style.display = 'flex';
        previewResults.scrollIntoView({ behavior: 'smooth' });

        // Load halaman 1 transaksi
        fetchPaginatedRows(1);
    }

    // 4. Pagination Handler
    const btnPrevPage = document.getElementById('btnPrevPage');
    const btnNextPage = document.getElementById('btnNextPage');
    const pageNumberPill = document.getElementById('pageNumberPill');
    const pageStateText = document.getElementById('pageStateText');
    const pageRangeText = document.getElementById('pageRangeText');
    const pageTotalText = document.getElementById('pageTotalText');
    const tableBody = document.getElementById('tablePreviewBody');

    function fetchPaginatedRows(page) {
        tableBody.innerHTML = '<tr><td colspan="9" class="text-center py-4">Memuat data baris...</td></tr>';

        fetch("{{ route('neraca.upload.rows') }}?page=" + page + "&per_page=" + perPage, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(json => {
            if (!json.success || !json.pagination) {
                tableBody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-danger">' + (json.message || 'Gagal memuat halaman.') + '</td></tr>';
                return;
            }

            const p = json.pagination;
            currentPage = p.current_page;
            totalPages = p.last_page;

            pageNumberPill.textContent = currentPage;
            pageStateText.textContent = 'Halaman ' + currentPage + ' dari ' + totalPages;
            pageRangeText.textContent = p.from + ' - ' + p.to;
            pageTotalText.textContent = Number(p.total).toLocaleString('id-ID');

            btnPrevPage.disabled = (currentPage <= 1);
            btnNextPage.disabled = (currentPage >= totalPages);

            // Render Table Rows
            if (!p.data || p.data.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="9" class="text-center py-4">Tidak ada data transaksi.</td></tr>';
                return;
            }

            let html = '';
            p.data.forEach(r => {
                html += `
                    <tr>
                        <td style="color: #64748b; font-weight: 600;">${r.row_index}</td>
                        <td><strong>${r.tahun}</strong></td>
                        <td>${String(r.bulan).padStart(2, '0')}</td>
                        <td><code style="background: #f1f5f9; padding: 2px 4px; border-radius: 4px; font-weight: 700;">${r.raw_hs}</code></td>
                        <td>${r.hs_desc || '<span style="color: #94a3b8;">-</span>'}</td>
                        <td>${r.negara_nama || r.raw_negara}</td>
                        <td>${r.raw_kabkota || '<span style="color: #94a3b8;">-</span>'}</td>
                        <td style="text-align: right; font-family: monospace;">${Number(r.berat).toLocaleString('id-ID', { minimumFractionDigits: 3, maximumFractionDigits: 3 })}</td>
                        <td style="text-align: right; font-family: monospace; font-weight: 700; color: #0f172a;">$${Number(r.nilai).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                    </tr>
                `;
            });
            tableBody.innerHTML = html;
        })
        .catch(err => {
            tableBody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-danger">Gagal memuat baris: ' + err.message + '</td></tr>';
        });
    }

    btnPrevPage.addEventListener('click', function () {
        if (currentPage > 1) {
            fetchPaginatedRows(currentPage - 1);
        }
    });

    btnNextPage.addEventListener('click', function () {
        if (currentPage < totalPages) {
            fetchPaginatedRows(currentPage + 1);
        }
    });
});
</script>
@endsection
