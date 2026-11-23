@extends('layouts.app')

@section('title', 'Unggah Data | SIDATEX BPS Provinsi Riau')

@section('content')
<div class="upload-page-wrapper">
    <!-- Header Standar BPS -->
    <div class="upload-header-card">
        <div class="header-content-left">
            <div class="header-badge-instansi">
                <span class="badge-dot"></span>
                <span>BPS PROVINSI RIAU &mdash; SISTEM PENGELOLAAN DATA STATISTIK EKSPOR-IMPOR</span>
            </div>
            <h1 class="header-page-title">Unggah Data Ekspor-Impor</h1>
            <p class="header-page-subtitle">
                Unggah dan proses berkas data mentah DBF atau lembar kerja Excel resmi BPS untuk menghasilkan data mart statistik yang terverifikasi dan siap dianalisis.
            </p>
        </div>
        <div class="header-content-right">
            <div class="header-meta-box">
                <span class="meta-label">Format Didukung</span>
                <span class="meta-value">.DBF (dBase III/IV), .XLSX, .XLS</span>
            </div>
            <div class="header-meta-box">
                <span class="meta-label">Batas Ukuran</span>
                <span class="meta-value">Maksimal 50 MB / Berkas</span>
            </div>
        </div>
    </div>

    <!-- Stepper 6 Langkah Proses -->
    <div class="stepper-container">
        <div class="stepper-step active" id="step-node-1" data-step="1">
            <div class="step-circle">1</div>
            <div class="step-label-group">
                <span class="step-label-title">Pilih Berkas</span>
                <span class="step-label-desc">Unggah DBF / Excel</span>
            </div>
        </div>
        <div class="stepper-line" id="step-line-1"></div>

        <div class="stepper-step" id="step-node-2" data-step="2">
            <div class="step-circle">2</div>
            <div class="step-label-group">
                <span class="step-label-title">Pemeriksaan Berkas</span>
                <span class="step-label-desc">Integritas Struktur</span>
            </div>
        </div>
        <div class="stepper-line" id="step-line-2"></div>

        <div class="stepper-step" id="step-node-3" data-step="3">
            <div class="step-circle">3</div>
            <div class="step-label-group">
                <span class="step-label-title">Validasi & Pemetaan</span>
                <span class="step-label-desc">Master HS & Wilayah</span>
            </div>
        </div>
        <div class="stepper-line" id="step-line-3"></div>

        <div class="stepper-step" id="step-node-4" data-step="4">
            <div class="step-circle">4</div>
            <div class="step-label-group">
                <span class="step-label-title">Pratinjau Data</span>
                <span class="step-label-desc">Evaluasi Kualitas</span>
            </div>
        </div>
        <div class="stepper-line" id="step-line-4"></div>

        <div class="stepper-step" id="step-node-5" data-step="5">
            <div class="step-circle">5</div>
            <div class="step-label-group">
                <span class="step-label-title">Pengolahan & Agregasi</span>
                <span class="step-label-desc">Two-Tier Data Mart</span>
            </div>
        </div>
        <div class="stepper-line" id="step-line-5"></div>

        <div class="stepper-step" id="step-node-6" data-step="6">
            <div class="step-circle">6</div>
            <div class="step-label-group">
                <span class="step-label-title">Penyimpanan Hasil</span>
                <span class="step-label-desc">Tersimpan di Sistem</span>
            </div>
        </div>
    </div>

    <!-- Alert Notifikasi Global -->
    <div id="alertNotification" class="alert-box" style="display: none;">
        <div class="alert-icon" id="alertIcon"></div>
        <div class="alert-body">
            <h4 class="alert-title" id="alertTitle">Notifikasi</h4>
            <p class="alert-message" id="alertMessage">Pesan notifikasi.</p>
        </div>
        <button type="button" class="alert-close" onclick="closeAlert()">&times;</button>
    </div>

    <!-- TAHAP 1: FORM PENGATURAN & AREA UNGGAH -->
    <div id="cardStageUpload" class="content-card">
        <div class="card-header-bar">
            <div class="card-header-left">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0066cc" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                <h2 class="card-title">1. Pengaturan dan Pemilihan Berkas</h2>
            </div>
            <span class="badge-role-required">Khusus Peran Super Admin</span>
        </div>

        <form id="formUploadData" enctype="multipart/form-data">
            @csrf

            <!-- Konfigurasi Parameter Data -->
            <div class="config-grid-3">
                <!-- Sumber Data -->
                <div class="form-group-box">
                    <label class="form-label-custom">Sumber Metodologi Data <span class="text-danger">*</span></label>
                    <div class="toggle-segmented">
                        <label class="segmented-option">
                            <input type="radio" name="sumber_data_final" value="distribusi" checked id="sumber_distribusi">
                            <span class="segmented-btn">
                                <strong>Distribusi</strong>
                                <small>Pelabuhan Muat / Bongkar (POD/KDPROV)</small>
                            </span>
                        </label>
                        <label class="segmented-option">
                            <input type="radio" name="sumber_data_final" value="neraca" id="sumber_neraca">
                            <span class="segmented-btn">
                                <strong>Neraca</strong>
                                <small>Asal Barang / Bea Cukai (PROVORIG/KPPBC)</small>
                            </span>
                        </label>
                    </div>
                </div>

                <!-- Jenis Transaksi -->
                <div class="form-group-box">
                    <label class="form-label-custom">Jenis Transaksi Perdagangan <span class="text-danger">*</span></label>
                    <div class="toggle-segmented">
                        <label class="segmented-option">
                            <input type="radio" name="jenis_data_final" value="Ekspor" checked id="jenis_ekspor">
                            <span class="segmented-btn">
                                <strong>Ekspor (FOB)</strong>
                                <small>Pengiriman ke Luar Negeri</small>
                            </span>
                        </label>
                        <label class="segmented-option">
                            <input type="radio" name="jenis_data_final" value="Impor" id="jenis_impor">
                            <span class="segmented-btn">
                                <strong>Impor (CIF)</strong>
                                <small>Pemasukan dari Luar Negeri</small>
                            </span>
                        </label>
                    </div>
                </div>

                <!-- Status Target Publikasi -->
                <div class="form-group-box">
                    <label class="form-label-custom">Status Awal Data Disimpan <span class="text-danger">*</span></label>
                    <div class="toggle-segmented">
                        <label class="segmented-option">
                            <input type="radio" name="status_final" value="PRIVATE" checked id="status_private">
                            <span class="segmented-btn">
                                <strong>Internal (Private)</strong>
                                <small>Telaah Internal BPS & Eksekutif</small>
                            </span>
                        </label>
                        <label class="segmented-option">
                            <input type="radio" name="status_final" value="RILIS" id="status_rilis">
                            <span class="segmented-btn">
                                <strong>Rilis Publik</strong>
                                <small>Dapat Diakses Seluruh Pegawai</small>
                            </span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Dropzone Area -->
            <div class="dropzone-container" id="dropzoneContainer">
                <input type="file" id="realFileInput" name="excel_file_final" class="file-input-hidden" accept=".dbf,.xlsx,.xls">

                <div class="dropzone-content-initial" id="dropzoneInitial">
                    <div class="dropzone-icon-circle">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#0066cc" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="12" y1="18" x2="12" y2="12"></line><line x1="9" y1="15" x2="15" y2="15"></line></svg>
                    </div>
                    <h3 class="dropzone-title">Tarik dan letakkan berkas di sini, atau klik tombol untuk memilih berkas</h3>
                    <p class="dropzone-subtitle">Mendukung berkas DBF mentah dari BPS RI (misal: <code>14_Atap_ekspor_2608_POD_based.DBF</code>) dan berkas Excel (.xlsx / .xls)</p>
                    <button type="button" class="btn-browse-file" onclick="document.getElementById('realFileInput').click()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                        Pilih Berkas Komputer
                    </button>
                </div>

                <!-- Dropzone Selected File View -->
                <div class="dropzone-content-selected" id="dropzoneSelected" style="display: none;">
                    <div class="selected-file-card">
                        <div class="selected-file-icon">
                            <span id="badgeFileExt" class="badge-ext">DBF</span>
                        </div>
                        <div class="selected-file-details">
                            <h4 id="displayFileName" class="selected-file-name">nama_berkas.dbf</h4>
                            <div class="selected-file-meta">
                                <span id="displayFileSize" class="meta-item">0 KB</span>
                                <span class="meta-separator">&bull;</span>
                                <span id="displayFileType" class="meta-item">Format dBase Database</span>
                                <span class="meta-separator">&bull;</span>
                                <span class="meta-item text-success">Berkas Siap Diperiksa</span>
                            </div>
                        </div>
                        <div class="selected-file-actions">
                            <button type="button" class="btn-action-outline" onclick="document.getElementById('realFileInput').click()">
                                Ganti Berkas
                            </button>
                            <button type="button" class="btn-action-danger" onclick="cancelSelectedFile()">
                                Batalkan
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tombol Submit Tahap 1 -->
            <div class="form-actions-row">
                <a href="{{ route('manajemen-data') }}" class="btn-nav-back">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                    Kembali ke Manajemen Data
                </a>
                <button type="button" id="btnPeriksaBerkas" class="btn-primary-action" disabled onclick="executePreview()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    Periksa & Pratinjau Berkas
                </button>
            </div>
        </form>
    </div>

    <!-- TAHAP 2 & 3: ANIMASI PEMERIKSAAN & VALIDASI -->
    <div id="cardStageInspecting" class="content-card" style="display: none;">
        <div class="inspection-container">
            <div class="inspection-spinner"></div>
            <h3 class="inspection-title" id="inspectionStatusText">Memeriksa Integritas & Memetakan Berkas...</h3>
            <p class="inspection-subtitle" id="inspectionSubtext">Engine validasi BRS sedang membaca header, memverifikasi struktur kolom, dan memetakan kode HS terhadap master data.</p>

            <div class="progress-track">
                <div class="progress-bar-fill" id="inspectionProgressBar" style="width: 25%;"></div>
            </div>

            <div class="inspection-checklist">
                <div class="check-item active" id="chk-header">
                    <span class="check-icon">&#10003;</span>
                    <span>Pemeriksaan byte header dan format berkas</span>
                </div>
                <div class="check-item" id="chk-columns">
                    <span class="check-icon">&#10003;</span>
                    <span>Validasi ketersediaan kolom kunci BPS (HS, Nilai, Berat, Pelabuhan, Negara)</span>
                </div>
                <div class="check-item" id="chk-master">
                    <span class="check-icon">&#10003;</span>
                    <span>Pencocokan Master Komoditas (Master HS BPS & Sektor)</span>
                </div>
                <div class="check-item" id="chk-aggregation">
                    <span class="check-icon">&#10003;</span>
                    <span>Kalkulasi indikator awal & verifikasi duplikasi batch</span>
                </div>
            </div>
        </div>
    </div>

    <!-- TAHAP 4: PANEL PRATINJAU & EVALUASI KUALITAS DATA -->
    <div id="cardStagePreview" class="content-card" style="display: none;">
        <div class="card-header-bar">
            <div class="card-header-left">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0066cc" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                <h2 class="card-title">4. Pratinjau & Kualitas Data Sebelum Disimpan</h2>
            </div>
            <div class="header-tags-group">
                <span id="badgePreviewSumber" class="badge-tag-blue">DISTRIBUSI</span>
                <span id="badgePreviewJenis" class="badge-tag-indigo">EKSPOR</span>
                <span id="badgePreviewPeriode" class="badge-tag-slate">BULAN 8 / 2026</span>
            </div>
        </div>

        <!-- Banner Peringatan Duplikasi / Unmapped jika ada -->
        <div id="bannerDuplikasi" class="banner-warning" style="display: none;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            <div>
                <strong>Peringatan Duplikasi Periode:</strong>
                <span id="textDuplikasi">Periode ini sudah pernah tersimpan di sistem. Data yang sudah ada tidak boleh ditimpa tanpa verifikasi.</span>
            </div>
        </div>

        <div id="bannerUnmapped" class="banner-warning" style="display: none;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
            <div>
                <strong>Catatan Pemetaan Master:</strong>
                <span id="textUnmapped">Ditemukan kode komoditas/wilayah yang belum terdaftar pada master. Kode asli tetap dipertahankan dan diberi tanda status unmapped.</span>
            </div>
        </div>

        <!-- Ringkasan KPI Data Hasil Pengolahan -->
        <div class="kpi-summary-grid">
            <div class="kpi-card">
                <span class="kpi-card-title">Total Baris Transaksi</span>
                <div class="kpi-card-value" id="kpiTotalBaris">0</div>
                <div class="kpi-card-sub" id="kpiBarisValidDesc">
                    <span class="text-success" id="kpiBarisValid">0 Valid</span> &bull; <span class="text-danger" id="kpiBarisInvalid">0 Gagal</span>
                </div>
            </div>

            <div class="kpi-card">
                <span class="kpi-card-title">Estimasi Nilai Total</span>
                <div class="kpi-card-value" id="kpiTotalNilai">US$ 0,00 Juta</div>
                <div class="kpi-card-sub text-muted" id="kpiTotalNilaiExact">US$ 0.000000</div>
            </div>

            <div class="kpi-card">
                <span class="kpi-card-title">Estimasi Berat Bersih</span>
                <div class="kpi-card-value" id="kpiTotalBerat">0,00 Ribu Ton</div>
                <div class="kpi-card-sub text-muted" id="kpiTotalBeratExact">0.000000 Kg</div>
            </div>

            <div class="kpi-card">
                <span class="kpi-card-title">Proporsi Migas / Non-Migas</span>
                <div class="kpi-card-value" id="kpiNonMigas">US$ 0,00 Juta</div>
                <div class="kpi-card-sub" id="kpiMigas">Migas: US$ 0,00 Juta</div>
            </div>
        </div>

        <!-- Tabel Sampel Baris Data Transaksi -->
        <div class="table-section-box">
            <div class="table-section-header">
                <h3 class="table-section-title">Sampel Baris Data Terverifikasi (10 Baris Pertama)</h3>
                <span class="table-section-hint">Data ini merupakan hasil parsing dan validasi langsung dari berkas asli</span>
            </div>

            <div class="table-scroll-container">
                <table class="table-custom" id="tablePreviewSample">
                    <thead>
                        <tr>
                            <th style="width: 50px;">No</th>
                            <th>Kode HS</th>
                            <th>Uraian Komoditas</th>
                            <th>Negara Mitra</th>
                            <th>Pelabuhan / Pintu</th>
                            <th style="text-align: right;">Nilai (USD)</th>
                            <th style="text-align: right;">Berat Netto (Kg)</th>
                            <th style="text-align: center;">Status</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyPreviewSample">
                        <tr>
                            <td colspan="8" style="text-align: center; color: #94a3b8; padding: 2rem;">Belum ada data pratinjau yang dimuat.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tombol Aksi Pratinjau -->
        <div class="form-actions-row">
            <button type="button" class="btn-nav-back" onclick="backToUploadStage()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                Ganti Berkas / Pengaturan
            </button>
            <div class="action-btn-group">
                <button type="button" id="btnSimpanDatabase" class="btn-success-action" onclick="showConfirmSaveModal()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                    Simpan & Proses ke Basis Data
                </button>
            </div>
        </div>
    </div>

    <!-- TAHAP 5: ANIMASI PENGOLAHAN DUA TIER MART -->
    <div id="cardStageProcessing" class="content-card" style="display: none;">
        <div class="inspection-container">
            <div class="inspection-spinner"></div>
            <h3 class="inspection-title">Menyimpan & Menjalankan Agregasi Data Mart...</h3>
            <p class="inspection-subtitle">Sistem sedang menyimpan transaksi secara chunk (500 baris/batch) dan membangun analytical mart BRS.</p>
            <div class="progress-track">
                <div class="progress-bar-fill progress-bar-animated" style="width: 85%;"></div>
            </div>
        </div>
    </div>

    <!-- TAHAP 6: HASIL PENYIMPANAN BERHASIL -->
    <div id="cardStageSuccess" class="content-card" style="display: none;">
        <div class="success-result-box">
            <div class="success-icon-circle">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            </div>
            <h2 class="success-title">Pengunggahan dan Pemrosesan Berkas Berhasil!</h2>
            <p class="success-subtitle" id="successSummaryText">
                Seluruh transaksi telah berhasil divalidasi, disimpan ke tabel data mart, dan diagregasi secara otomatis.
            </p>

            <div class="success-detail-grid">
                <div class="success-detail-item">
                    <span class="detail-label">Nomor Batch ID</span>
                    <strong class="detail-val" id="resBatchId">Batch #--</strong>
                </div>
                <div class="success-detail-item">
                    <span class="detail-label">Status Visibilitas</span>
                    <strong class="detail-val" id="resStatus">PRIVATE</strong>
                </div>
                <div class="success-detail-item">
                    <span class="detail-label">Jumlah Baris Tersimpan</span>
                    <strong class="detail-val" id="resTotalRows">0 Transaksi</strong>
                </div>
                <div class="success-detail-item">
                    <span class="detail-label">Total Nilai Perdagangan</span>
                    <strong class="detail-val" id="resTotalNilai">US$ 0,00 Juta</strong>
                </div>
            </div>

            <div class="success-actions-row">
                <button type="button" class="btn-action-outline" onclick="resetToNewUpload()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                    Unggah Berkas Lain
                </button>
                <a href="{{ route('manajemen-data') }}" class="btn-primary-action">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    Buka Manajemen Data
                </a>
                <a href="{{ route('dashboard') }}" class="btn-success-action">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                    Lihat Dasbor Statistik
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Simpan Data -->
<div id="modalConfirmSave" class="modal-backdrop" style="display: none;">
    <div class="modal-dialog-box">
        <div class="modal-dialog-header">
            <h3 class="modal-dialog-title">Konfirmasi Penyimpanan Data</h3>
            <button type="button" class="modal-dialog-close" onclick="closeConfirmSaveModal()">&times;</button>
        </div>
        <div class="modal-dialog-body">
            <p>Anda akan memproses dan menyimpan berkas ini ke dalam basis data operasional SIDATEX.</p>
            <div class="modal-info-summary">
                <div class="info-row">
                    <span class="info-k">Nama Berkas:</span>
                    <span class="info-v" id="modalConfirmFileName">file.dbf</span>
                </div>
                <div class="info-row">
                    <span class="info-k">Sumber & Jenis:</span>
                    <span class="info-v" id="modalConfirmScope">Distribusi &bull; Ekspor</span>
                </div>
                <div class="info-row">
                    <span class="info-k">Status Target:</span>
                    <span class="info-v" id="modalConfirmStatus">PRIVATE</span>
                </div>
                <div class="info-row">
                    <span class="info-k">Jumlah Baris:</span>
                    <span class="info-v" id="modalConfirmRows">0 Baris</span>
                </div>
            </div>
            <p class="modal-disclaimer">
                Data akan disimpan ke tabel transaksi operasional dan dimasukkan ke dalam agregasi analitik bulanan. Pastikan seluruh parameter telah sesuai.
            </p>
        </div>
        <div class="modal-dialog-footer">
            <button type="button" class="btn-action-outline" onclick="closeConfirmSaveModal()">Batalkan</button>
            <button type="button" class="btn-primary-action" onclick="executeProcessSave()">
                Konfirmasi & Simpan Sekarang
            </button>
        </div>
    </div>
</div>

@push('styles')
<style>
    .upload-page-wrapper {
        min-height: calc(100vh - 120px);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        color: #1e293b;
    }

    /* HEADER */
    .upload-header-card {
        background: #ffffff;
        border-radius: 12px;
        padding: 1.5rem 1.75rem;
        margin-bottom: 1.5rem;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1.5rem;
        flex-wrap: wrap;
    }

    .header-content-left {
        max-width: 680px;
    }

    .header-badge-instansi {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.72rem;
        font-weight: 700;
        color: #0369a1;
        background: #e0f2fe;
        padding: 0.25rem 0.65rem;
        border-radius: 9999px;
        margin-bottom: 0.5rem;
        letter-spacing: 0.04em;
    }

    .badge-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #0284c7;
    }

    .header-page-title {
        font-size: 1.45rem;
        font-weight: 700;
        color: #0f172a;
        margin: 0;
        border-left: 4px solid #0066cc;
        padding-left: 0.85rem;
    }

    .header-page-subtitle {
        font-size: 0.88rem;
        color: #64748b;
        margin: 0.35rem 0 0 0;
        padding-left: 0.85rem;
        line-height: 1.5;
    }

    .header-content-right {
        display: flex;
        gap: 1rem;
    }

    .header-meta-box {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 0.6rem 0.9rem;
        text-align: right;
    }

    .header-meta-box .meta-label {
        display: block;
        font-size: 0.72rem;
        color: #64748b;
        text-transform: uppercase;
        font-weight: 600;
    }

    .header-meta-box .meta-value {
        display: block;
        font-size: 0.85rem;
        font-weight: 700;
        color: #1e293b;
        margin-top: 0.15rem;
    }

    /* STEPPER 6 LANGKAH */
    .stepper-container {
        background: #ffffff;
        border-radius: 12px;
        padding: 1.25rem 1.5rem;
        margin-bottom: 1.5rem;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        display: flex;
        align-items: center;
        justify-content: space-between;
        overflow-x: auto;
    }

    .stepper-step {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        flex-shrink: 0;
        opacity: 0.55;
        transition: all 0.2s ease;
    }

    .stepper-step.active {
        opacity: 1;
    }

    .stepper-step.completed {
        opacity: 1;
    }

    .step-circle {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: #e2e8f0;
        color: #475569;
        font-weight: 700;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }

    .stepper-step.active .step-circle {
        background: #0066cc;
        color: #ffffff;
        box-shadow: 0 0 0 4px rgba(0, 102, 204, 0.15);
    }

    .stepper-step.completed .step-circle {
        background: #16a34a;
        color: #ffffff;
    }

    .step-label-group {
        display: flex;
        flex-direction: column;
    }

    .step-label-title {
        font-size: 0.82rem;
        font-weight: 700;
        color: #1e293b;
        white-space: nowrap;
    }

    .step-label-desc {
        font-size: 0.72rem;
        color: #64748b;
        white-space: nowrap;
    }

    .stepper-line {
        flex: 1;
        min-width: 20px;
        height: 2px;
        background: #e2e8f0;
        margin: 0 0.75rem;
    }

    .stepper-line.completed {
        background: #16a34a;
    }

    /* CARD UMUM */
    .content-card {
        background: #ffffff;
        border-radius: 12px;
        padding: 1.5rem 1.75rem;
        margin-bottom: 1.5rem;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }

    .card-header-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #f1f5f9;
        flex-wrap: wrap;
        gap: 0.75rem;
    }

    .card-header-left {
        display: flex;
        align-items: center;
        gap: 0.65rem;
    }

    .card-title {
        font-size: 1.15rem;
        font-weight: 700;
        color: #0f172a;
        margin: 0;
    }

    .badge-role-required {
        font-size: 0.75rem;
        font-weight: 600;
        color: #b45309;
        background: #fef3c7;
        padding: 0.25rem 0.65rem;
        border-radius: 9999px;
    }

    /* CONFIG GRID */
    .config-grid-3 {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.25rem;
        margin-bottom: 1.5rem;
    }

    @media (max-width: 900px) {
        .config-grid-3 {
            grid-template-columns: 1fr;
        }
    }

    .form-group-box {
        display: flex;
        flex-direction: column;
        gap: 0.45rem;
    }

    .form-label-custom {
        font-size: 0.8rem;
        font-weight: 700;
        color: #334155;
        text-transform: uppercase;
        letter-spacing: 0.025em;
    }

    .text-danger { color: #ef4444; }
    .text-success { color: #16a34a; }
    .text-muted { color: #64748b; }

    .toggle-segmented {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .segmented-option input[type="radio"] {
        display: none;
    }

    .segmented-btn {
        display: block;
        padding: 0.65rem 0.85rem;
        border: 1.5px solid #cbd5e1;
        border-radius: 8px;
        background: #f8fafc;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .segmented-btn strong {
        display: block;
        font-size: 0.85rem;
        color: #1e293b;
    }

    .segmented-btn small {
        display: block;
        font-size: 0.73rem;
        color: #64748b;
        margin-top: 0.15rem;
    }

    .segmented-option input[type="radio"]:checked + .segmented-btn {
        border-color: #0066cc;
        background: #eff6ff;
    }

    .segmented-option input[type="radio"]:checked + .segmented-btn strong {
        color: #0066cc;
    }

    /* DROPZONE */
    .dropzone-container {
        border: 2px dashed #cbd5e1;
        border-radius: 12px;
        background: #fafcff;
        padding: 2.25rem 1.5rem;
        text-align: center;
        transition: all 0.2s ease;
        margin-bottom: 1.5rem;
        position: relative;
    }

    .dropzone-container.dragover {
        border-color: #0066cc;
        background: #eff6ff;
    }

    .file-input-hidden {
        display: none;
    }

    .dropzone-icon-circle {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: #e0f2fe;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1rem;
    }

    .dropzone-title {
        font-size: 1.05rem;
        font-weight: 700;
        color: #1e293b;
        margin: 0 0 0.35rem 0;
    }

    .dropzone-subtitle {
        font-size: 0.83rem;
        color: #64748b;
        margin: 0 0 1.25rem 0;
    }

    .btn-browse-file {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: #0066cc;
        color: #ffffff;
        border: none;
        padding: 0.65rem 1.35rem;
        border-radius: 8px;
        font-size: 0.88rem;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.15s ease;
    }

    .btn-browse-file:hover {
        background: #0052a3;
    }

    /* SELECTED FILE CARD */
    .selected-file-card {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        padding: 1rem 1.25rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        text-align: left;
    }

    .selected-file-icon {
        flex-shrink: 0;
    }

    .badge-ext {
        background: #0369a1;
        color: #ffffff;
        font-weight: 800;
        font-size: 0.78rem;
        padding: 0.4rem 0.65rem;
        border-radius: 6px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .selected-file-details {
        flex: 1;
        min-width: 0;
    }

    .selected-file-name {
        margin: 0;
        font-size: 0.95rem;
        font-weight: 700;
        color: #0f172a;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .selected-file-meta {
        font-size: 0.78rem;
        color: #64748b;
        margin-top: 0.25rem;
        display: flex;
        gap: 0.5rem;
        align-items: center;
        flex-wrap: wrap;
    }

    .selected-file-actions {
        display: flex;
        gap: 0.5rem;
    }

    .btn-action-outline {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        color: #334155;
        padding: 0.5rem 0.9rem;
        border-radius: 6px;
        font-size: 0.82rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
    }

    .btn-action-outline:hover {
        background: #f1f5f9;
        border-color: #94a3b8;
    }

    .btn-action-danger {
        background: #ffffff;
        border: 1px solid #fecaca;
        color: #ef4444;
        padding: 0.5rem 0.9rem;
        border-radius: 6px;
        font-size: 0.82rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .btn-action-danger:hover {
        background: #fee2e2;
    }

    /* ACTIONS ROW */
    .form-actions-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-top: 1px solid #f1f5f9;
        padding-top: 1.25rem;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .btn-nav-back {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        color: #475569;
        text-decoration: none;
        font-size: 0.88rem;
        font-weight: 600;
        padding: 0.6rem 1rem;
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        background: #ffffff;
        cursor: pointer;
    }

    .btn-nav-back:hover {
        background: #f8fafc;
        color: #0f172a;
    }

    .btn-primary-action {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: #0066cc;
        color: #ffffff;
        border: none;
        padding: 0.65rem 1.5rem;
        border-radius: 8px;
        font-size: 0.9rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.15s ease;
        box-shadow: 0 1px 2px rgba(0, 102, 204, 0.2);
    }

    .btn-primary-action:hover:not(:disabled) {
        background: #0052a3;
    }

    .btn-primary-action:disabled {
        background: #cbd5e1;
        cursor: not-allowed;
        box-shadow: none;
    }

    .btn-success-action {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: #16a34a;
        color: #ffffff;
        border: none;
        padding: 0.65rem 1.5rem;
        border-radius: 8px;
        font-size: 0.9rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .btn-success-action:hover {
        background: #15803d;
    }

    /* INSPECTION & PROGRESS */
    .inspection-container {
        padding: 3rem 1.5rem;
        text-align: center;
        max-width: 600px;
        margin: 0 auto;
    }

    .inspection-spinner {
        width: 44px;
        height: 44px;
        border: 4px solid #e2e8f0;
        border-top-color: #0066cc;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
        margin: 0 auto 1.25rem auto;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    .inspection-title {
        font-size: 1.2rem;
        font-weight: 700;
        color: #0f172a;
        margin: 0 0 0.35rem 0;
    }

    .inspection-subtitle {
        font-size: 0.85rem;
        color: #64748b;
        margin: 0 0 1.75rem 0;
        line-height: 1.5;
    }

    .progress-track {
        height: 8px;
        background: #e2e8f0;
        border-radius: 9999px;
        overflow: hidden;
        margin-bottom: 2rem;
    }

    .progress-bar-fill {
        height: 100%;
        background: #0066cc;
        border-radius: 9999px;
        transition: width 0.3s ease;
    }

    .progress-bar-animated {
        animation: progressShimmer 1.5s infinite linear;
    }

    .inspection-checklist {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        text-align: left;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 1rem 1.25rem;
    }

    .check-item {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        font-size: 0.82rem;
        color: #94a3b8;
    }

    .check-item.active {
        color: #1e293b;
        font-weight: 600;
    }

    .check-icon {
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: #e2e8f0;
        color: #ffffff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        font-weight: 800;
    }

    .check-item.active .check-icon {
        background: #16a34a;
    }

    /* BANNERS */
    .banner-warning {
        background: #fffbeb;
        border: 1px solid #fde68a;
        border-radius: 8px;
        padding: 0.9rem 1.25rem;
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        font-size: 0.85rem;
        color: #92400e;
        margin-bottom: 1.5rem;
    }

    /* KPI PREVIEW */
    .header-tags-group {
        display: flex;
        gap: 0.5rem;
    }

    .badge-tag-blue { background: #e0f2fe; color: #0369a1; padding: 0.3rem 0.7rem; border-radius: 6px; font-weight: 700; font-size: 0.75rem; }
    .badge-tag-indigo { background: #ede9fe; color: #6d28d9; padding: 0.3rem 0.7rem; border-radius: 6px; font-weight: 700; font-size: 0.75rem; }
    .badge-tag-slate { background: #f1f5f9; color: #475569; padding: 0.3rem 0.7rem; border-radius: 6px; font-weight: 700; font-size: 0.75rem; }

    .kpi-summary-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    @media (max-width: 900px) {
        .kpi-summary-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    .kpi-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 1rem 1.25rem;
    }

    .kpi-card-title {
        display: block;
        font-size: 0.74rem;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .kpi-card-value {
        font-size: 1.35rem;
        font-weight: 800;
        color: #0f172a;
        margin: 0.35rem 0 0.15rem 0;
    }

    .kpi-card-sub {
        font-size: 0.76rem;
    }

    /* TABLE SECTION */
    .table-section-box {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        overflow: hidden;
        margin-bottom: 1.5rem;
    }

    .table-section-header {
        background: #f8fafc;
        padding: 0.75rem 1.25rem;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .table-section-title {
        margin: 0;
        font-size: 0.88rem;
        font-weight: 700;
        color: #1e293b;
    }

    .table-section-hint {
        font-size: 0.76rem;
        color: #64748b;
    }

    .table-scroll-container {
        max-height: 380px;
        overflow-y: auto;
    }

    .table-custom {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.82rem;
    }

    .table-custom th {
        background: #ffffff;
        padding: 0.65rem 0.9rem;
        text-align: left;
        font-weight: 700;
        color: #475569;
        border-bottom: 2px solid #e2e8f0;
        position: sticky;
        top: 0;
        z-index: 2;
    }

    .table-custom td {
        padding: 0.65rem 0.9rem;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
    }

    .table-custom tr:hover td {
        background: #f8fafc;
    }

    /* SUCCESS RESULT CARD */
    .success-result-box {
        padding: 3rem 1.5rem;
        text-align: center;
        max-width: 650px;
        margin: 0 auto;
    }

    .success-icon-circle {
        width: 72px;
        height: 72px;
        border-radius: 50%;
        background: #dcfce7;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1.25rem;
    }

    .success-title {
        font-size: 1.5rem;
        font-weight: 800;
        color: #0f172a;
        margin: 0 0 0.5rem 0;
    }

    .success-subtitle {
        font-size: 0.92rem;
        color: #64748b;
        margin: 0 0 2rem 0;
        line-height: 1.6;
    }

    .success-detail-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 1.25rem;
        margin-bottom: 2rem;
        text-align: left;
    }

    .success-detail-item .detail-label {
        display: block;
        font-size: 0.72rem;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
    }

    .success-detail-item .detail-val {
        display: block;
        font-size: 1.05rem;
        font-weight: 700;
        color: #0f172a;
        margin-top: 0.2rem;
    }

    .success-actions-row {
        display: flex;
        justify-content: center;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    /* MODAL */
    .modal-backdrop {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(15, 23, 42, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        padding: 1rem;
    }

    .modal-dialog-box {
        background: #ffffff;
        border-radius: 12px;
        width: 100%;
        max-width: 520px;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        border: 1px solid #e2e8f0;
        overflow: hidden;
    }

    .modal-dialog-header {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-dialog-title {
        margin: 0;
        font-size: 1.15rem;
        font-weight: 700;
        color: #0f172a;
    }

    .modal-dialog-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        color: #94a3b8;
        cursor: pointer;
        line-height: 1;
    }

    .modal-dialog-body {
        padding: 1.5rem;
        font-size: 0.88rem;
        color: #334155;
        line-height: 1.5;
    }

    .modal-info-summary {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 0.85rem 1rem;
        margin: 1rem 0;
        display: flex;
        flex-direction: column;
        gap: 0.45rem;
    }

    .info-row {
        display: flex;
        justify-content: space-between;
        font-size: 0.82rem;
    }

    .info-k { color: #64748b; }
    .info-v { font-weight: 700; color: #0f172a; }

    .modal-disclaimer {
        font-size: 0.78rem;
        color: #64748b;
        margin: 0;
    }

    .modal-dialog-footer {
        padding: 1rem 1.5rem;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
    }

    /* ALERTS */
    .alert-box {
        border-radius: 10px;
        padding: 1rem 1.25rem;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
    }

    .alert-box.alert-error {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #991b1b;
    }

    .alert-box.alert-success {
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        color: #166534;
    }

    .alert-body {
        flex: 1;
    }

    .alert-title {
        margin: 0 0 0.2rem 0;
        font-size: 0.9rem;
        font-weight: 700;
    }

    .alert-message {
        margin: 0;
        font-size: 0.82rem;
    }

    .alert-close {
        background: none;
        border: none;
        font-size: 1.25rem;
        color: inherit;
        cursor: pointer;
        line-height: 1;
    }
</style>
@endpush

@push('scripts')
<script>
    let activeFile = null;
    let previewDataCache = null;

    // DOM Elements
    const dropzoneContainer = document.getElementById('dropzoneContainer');
    const realFileInput = document.getElementById('realFileInput');
    const dropzoneInitial = document.getElementById('dropzoneInitial');
    const dropzoneSelected = document.getElementById('dropzoneSelected');
    const displayFileName = document.getElementById('displayFileName');
    const displayFileSize = document.getElementById('displayFileSize');
    const displayFileType = document.getElementById('displayFileType');
    const badgeFileExt = document.getElementById('badgeFileExt');
    const btnPeriksaBerkas = document.getElementById('btnPeriksaBerkas');

    // Drag and Drop Listeners
    ['dragenter', 'dragover'].forEach(eventName => {
        dropzoneContainer.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzoneContainer.classList.add('dragover');
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropzoneContainer.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzoneContainer.classList.remove('dragover');
        }, false);
    });

    dropzoneContainer.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files.length > 0) {
            handleFileSelect(files[0]);
        }
    });

    realFileInput.addEventListener('change', function(e) {
        if (this.files.length > 0) {
            handleFileSelect(this.files[0]);
        }
    });

    function handleFileSelect(file) {
        const ext = file.name.split('.').pop().toLowerCase();
        if (!['dbf', 'xlsx', 'xls'].includes(ext)) {
            showAlert('Format Berkas Tidak Didukung', 'Harap pilih berkas dengan format .dbf, .xlsx, atau .xls.', 'error');
            return;
        }

        if (file.size > 52428800) { // 50MB
            showAlert('Ukuran Berkas Terlalu Besar', 'Batas maksimal ukuran berkas adalah 50 MB.', 'error');
            return;
        }

        activeFile = file;
        displayFileName.textContent = file.name;
        badgeFileExt.textContent = ext.toUpperCase();

        const sizeInMb = (file.size / (1024 * 1024)).toFixed(2);
        displayFileSize.textContent = sizeInMb > 0.05 ? `${sizeInMb} MB` : `${(file.size / 1024).toFixed(0)} KB`;
        displayFileType.textContent = ext === 'dbf' ? 'Format dBase III/IV DBF' : 'Lembar Kerja Excel';

        dropzoneInitial.style.display = 'none';
        dropzoneSelected.style.display = 'block';
        btnPeriksaBerkas.disabled = false;
        closeAlert();
    }

    function cancelSelectedFile() {
        activeFile = null;
        realFileInput.value = '';
        dropzoneInitial.style.display = 'block';
        dropzoneSelected.style.display = 'none';
        btnPeriksaBerkas.disabled = true;
    }

    function showAlert(title, message, type = 'error') {
        const box = document.getElementById('alertNotification');
        box.className = `alert-box alert-${type}`;
        document.getElementById('alertTitle').textContent = title;
        document.getElementById('alertMessage').textContent = message;
        box.style.display = 'flex';
        box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function closeAlert() {
        document.getElementById('alertNotification').style.display = 'none';
    }

    function updateStepperUI(stepNumber) {
        for (let i = 1; i <= 6; i++) {
            const node = document.getElementById(`step-node-${i}`);
            const line = document.getElementById(`step-line-${i}`);

            if (i < stepNumber) {
                node.className = 'stepper-step completed';
                if (line) line.className = 'stepper-line completed';
            } else if (i === stepNumber) {
                node.className = 'stepper-step active';
                if (line) line.className = 'stepper-line';
            } else {
                node.className = 'stepper-step';
                if (line) line.className = 'stepper-line';
            }
        }
    }

    // Eksekusi Pratinjau (Langkah 2 & 3 -> 4)
    async function executePreview() {
        if (!activeFile) {
            showAlert('Berkas Belum Dipilih', 'Silakan pilih berkas DBF atau Excel terlebih dahulu.', 'error');
            return;
        }

        const sumber = document.querySelector('input[name="sumber_data_final"]:checked')?.value || 'distribusi';
        const jenis = document.querySelector('input[name="jenis_data_final"]:checked')?.value || 'Ekspor';

        // Tampilkan kartu inspeksi
        document.getElementById('cardStageUpload').style.display = 'none';
        document.getElementById('cardStageInspecting').style.display = 'block';
        updateStepperUI(2);

        // Simulasi progres checklist
        setTimeout(() => {
            document.getElementById('chk-columns').classList.add('active');
            document.getElementById('inspectionProgressBar').style.width = '50%';
            updateStepperUI(3);
        }, 300);

        setTimeout(() => {
            document.getElementById('chk-master').classList.add('active');
            document.getElementById('inspectionProgressBar').style.width = '75%';
        }, 600);

        const formData = new FormData();
        formData.append('excel_file_final', activeFile);
        formData.append('jenis_data_final', jenis);
        formData.append('sumber_data_final', sumber);

        try {
            const response = await fetch("{{ route('upload.preview') }}", {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                    'Accept': 'application/json'
                },
                body: formData
            });

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.message || 'Gagal membaca dan memvalidasi berkas.');
            }

            // Simpan cache hasil pratinjau
            previewDataCache = result;

            document.getElementById('chk-aggregation').classList.add('active');
            document.getElementById('inspectionProgressBar').style.width = '100%';

            setTimeout(() => {
                renderPreviewPanel(result);
            }, 400);

        } catch (err) {
            document.getElementById('cardStageInspecting').style.display = 'none';
            document.getElementById('cardStageUpload').style.display = 'block';
            updateStepperUI(1);
            showAlert('Validasi Berkas Gagal', err.message, 'error');
        }
    }

    function renderPreviewPanel(result) {
        document.getElementById('cardStageInspecting').style.display = 'none';
        document.getElementById('cardStagePreview').style.display = 'block';
        updateStepperUI(4);

        const d = result.data || result;
        const meta = result.data || {};
        const kpi = result.kpi || result.data || {};

        // Tags
        document.getElementById('badgePreviewSumber').textContent = (result.sumber_data || meta.sumber_data || 'Distribusi').toUpperCase();
        document.getElementById('badgePreviewJenis').textContent = (result.jenis_data || meta.jenis_transaksi || 'Ekspor').toUpperCase();
        document.getElementById('badgePreviewPeriode').textContent = `BULAN ${result.bulan || meta.bulan || '--'} / ${result.tahun || meta.tahun || '--'}`;

        // KPI
        const totalBaris = meta.total_baris || result.jumlah_baris || 0;
        const barisValid = meta.baris_valid || totalBaris;
        const barisInvalid = meta.baris_invalid || 0;

        document.getElementById('kpiTotalBaris').textContent = totalBaris.toLocaleString('id-ID');
        document.getElementById('kpiBarisValid').textContent = `${barisValid.toLocaleString('id-ID')} Valid`;
        document.getElementById('kpiBarisInvalid').textContent = `${barisInvalid.toLocaleString('id-ID')} Gagal`;

        const totalNilaiJuta = (meta.total_nilai_juta_usd !== undefined) ? meta.total_nilai_juta_usd : (result.total_nilai || 0);
        document.getElementById('kpiTotalNilai').textContent = `US$ ${Number(totalNilaiJuta).toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2})} Juta`;
        document.getElementById('kpiTotalNilaiExact').textContent = meta.total_nilai_usd ? `Exact: US$ ${Number(meta.total_nilai_usd).toLocaleString('en-US', {minimumFractionDigits: 6})}` : '';

        const totalBeratRibu = (meta.total_berat_ribu_ton !== undefined) ? meta.total_berat_ribu_ton : (result.total_berat || 0);
        document.getElementById('kpiTotalBerat').textContent = `${Number(totalBeratRibu).toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2})} Ribu Ton`;
        document.getElementById('kpiTotalBeratExact').textContent = meta.total_berat_kg ? `Exact: ${Number(meta.total_berat_kg).toLocaleString('en-US', {minimumFractionDigits: 6})} Kg` : '';

        const nonMigasJuta = meta.non_migas_nilai_usd ? (meta.non_migas_nilai_usd / 1000000) : 0;
        const migasJuta = meta.migas_nilai_usd ? (meta.migas_nilai_usd / 1000000) : 0;
        document.getElementById('kpiNonMigas').textContent = `Non-Migas: US$ ${Number(nonMigasJuta).toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2})} Juta`;
        document.getElementById('kpiMigas').textContent = `Migas: US$ ${Number(migasJuta).toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2})} Juta`;

        // Banner Duplikasi
        if (meta.duplikasi_batch && meta.duplikasi_batch.exists) {
            document.getElementById('bannerDuplikasi').style.display = 'flex';
            document.getElementById('textDuplikasi').textContent = `Periode data ini sudah pernah tercatat pada sistem (Batch #${meta.duplikasi_batch.batch_id}). Data yang sudah ada tidak boleh ditimpa tanpa verifikasi.`;
        } else {
            document.getElementById('bannerDuplikasi').style.display = 'none';
        }

        // Banner Unmapped
        if (meta.unmapped && meta.unmapped.count > 0) {
            document.getElementById('bannerUnmapped').style.display = 'flex';
            document.getElementById('textUnmapped').textContent = `Terdapat ${meta.unmapped.count} kode HS yang belum terdaftar di Master HS resmi BPS. Kode dipertahankan dengan flag status unmapped sesuai aturan metodologi BPS.`;
        } else {
            document.getElementById('bannerUnmapped').style.display = 'none';
        }

        // Render Sample Table
        const tbody = document.getElementById('tbodyPreviewSample');
        tbody.innerHTML = '';

        const sampleRows = meta.preview_sample || result.sample_rows || [];
        if (sampleRows.length > 0) {
            sampleRows.slice(0, 10).forEach((row, idx) => {
                const tr = document.createElement('tr');
                const hsCode = row.hs_code || row.hs || row.kd_hs || '-';
                const uraian = row.uraian || row.hs_desc || row.commodity || 'Komoditas Perdagangan';
                const negara = row.negara || row.nama_negara || row.country || '-';
                const pelabuhan = row.pelabuhan || row.nama_pelabuhan || row.port || '-';
                const nilaiUsd = Number(row.fob || row.cif || row.nilai || row.total_nilai || 0).toLocaleString('id-ID', {minimumFractionDigits: 2});
                const beratKg = Number(row.netto || row.berat || row.total_berat || 0).toLocaleString('id-ID', {minimumFractionDigits: 2});
                const isMapped = row.is_mapped !== false;

                tr.innerHTML = `
                    <td style="text-align: center; color: #94a3b8;">${idx + 1}</td>
                    <td><strong>${hsCode}</strong></td>
                    <td style="max-width: 240px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${uraian}">${uraian}</td>
                    <td>${negara}</td>
                    <td>${pelabuhan}</td>
                    <td style="text-align: right; font-weight: 600;">$${nilaiUsd}</td>
                    <td style="text-align: right;">${beratKg}</td>
                    <td style="text-align: center;">
                        <span class="badge-tag-blue" style="font-size: 0.7rem; padding: 2px 6px;">${isMapped ? 'TERPETAKAN' : 'UNMAPPED'}</span>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; color: #94a3b8; padding: 1.5rem;">Pratinjau sampel baris tidak tersedia.</td></tr>';
        }
    }

    function backToUploadStage() {
        document.getElementById('cardStagePreview').style.display = 'none';
        document.getElementById('cardStageUpload').style.display = 'block';
        updateStepperUI(1);
    }

    function showConfirmSaveModal() {
        if (!previewDataCache) return;
        const meta = previewDataCache.data || previewDataCache;

        document.getElementById('modalConfirmFileName').textContent = activeFile ? activeFile.name : '-';
        document.getElementById('modalConfirmScope').textContent = `${(previewDataCache.sumber_data || meta.sumber_data || 'Distribusi').toUpperCase()} \u2022 ${(previewDataCache.jenis_data || meta.jenis_transaksi || 'Ekspor').toUpperCase()}`;
        document.getElementById('modalConfirmStatus').textContent = document.querySelector('input[name="status_final"]:checked')?.value || 'PRIVATE';
        document.getElementById('modalConfirmRows').textContent = `${(meta.total_baris || previewDataCache.jumlah_baris || 0).toLocaleString('id-ID')} Baris Transaksi`;

        document.getElementById('modalConfirmSave').style.display = 'flex';
    }

    function closeConfirmSaveModal() {
        document.getElementById('modalConfirmSave').style.display = 'none';
    }

    // Eksekusi Penyimpanan Final (Langkah 5 & 6)
    async function executeProcessSave() {
        closeConfirmSaveModal();

        document.getElementById('cardStagePreview').style.display = 'none';
        document.getElementById('cardStageProcessing').style.display = 'block';
        updateStepperUI(5);

        const sumber = document.querySelector('input[name="sumber_data_final"]:checked')?.value || 'distribusi';
        const jenis = document.querySelector('input[name="jenis_data_final"]:checked')?.value || 'Ekspor';
        const status = document.querySelector('input[name="status_final"]:checked')?.value || 'PRIVATE';

        const formData = new FormData();
        formData.append('excel_file_final', activeFile);
        formData.append('jenis_data_final', jenis);
        formData.append('sumber_data_final', sumber);
        formData.append('status_final', status);

        try {
            const response = await fetch("{{ route('upload.process') }}", {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                    'Accept': 'application/json'
                },
                body: formData
            });

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.message || 'Gagal menyimpan dan memproses berkas ke basis data.');
            }

            // Tampilkan hasil sukses
            document.getElementById('cardStageProcessing').style.display = 'none';
            document.getElementById('cardStageSuccess').style.display = 'block';
            updateStepperUI(6);

            const resData = result.data || {};
            document.getElementById('resBatchId').textContent = `Batch #${resData.batch_id || '--'}`;
            document.getElementById('resStatus').textContent = resData.status || status;
            document.getElementById('resTotalRows').textContent = `${(resData.total_transactions || 0).toLocaleString('id-ID')} Transaksi`;
            document.getElementById('resTotalNilai').textContent = `US$ ${Number(resData.total_nilai_juta_usd || 0).toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2})} Juta`;
            document.getElementById('successSummaryText').textContent = result.message || 'Seluruh transaksi berhasil diverifikasi dan disimpan.';

        } catch (err) {
            document.getElementById('cardStageProcessing').style.display = 'none';
            document.getElementById('cardStagePreview').style.display = 'block';
            updateStepperUI(4);
            showAlert('Penyimpanan Data Gagal', err.message, 'error');
        }
    }

    function resetToNewUpload() {
        cancelSelectedFile();
        previewDataCache = null;
        document.getElementById('cardStageSuccess').style.display = 'none';
        document.getElementById('cardStageUpload').style.display = 'block';
        updateStepperUI(1);
    }
</script>
@endpush
@endsection
