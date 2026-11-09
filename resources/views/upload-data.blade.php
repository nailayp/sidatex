@extends('layouts.app')

@section('title', 'Manajemen Upload Data | SIDATEX')

@section('content')

<!-- MEMINDAHKAN STYLE & SCRIPT KE DALAM CONTENT AGAR PASTI TERBACA OLEH BROWSER -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<!-- TAMBAHAN LIBRARY CHART.JS UNTUK GRAFIK -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    /* LAYOUT HALAMAN DASHBOARD */
    .module-container { display: flex; gap: 1.5rem; min-height: calc(100vh - 100px); }

    .action-sidebar { width: 250px; background: rgba(255, 255, 255, 0.6); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.8); border-radius: 16px; padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; box-shadow: 0 4px 15px rgba(0,0,0,0.05); flex-shrink: 0; }
    .action-title { font-size: 1.25rem; font-weight: 800; font-style: italic; color: #1e293b; margin-bottom: 1rem; text-align: center; }
    .btn-action { width: 100%; padding: 0.8rem; background-color: #1877f2; color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 6px rgba(24, 119, 242, 0.3); }
    .btn-action:hover { background-color: #145dbf; transform: translateY(-2px); }
    .btn-action:disabled { background-color: #94a3b8; cursor: not-allowed; box-shadow: none; transform: none; }

    .preview-area { flex-grow: 1; min-width: 0; background-color: rgba(241, 245, 249, 0.5); border-radius: 16px; padding: 1.5rem; position: relative; display: flex; flex-direction: column; gap: 1.5rem; }
    .preview-overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(4px); z-index: 10; display: flex; align-items: center; justify-content: center; border-radius: 16px; flex-direction: column; }

    /* MODAL MULTI-STEP (POPUP) */
    .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(5px); align-items: center; justify-content: center; }
    .modal-content { background-color: #fff; padding: 2.5rem; border-radius: 20px; width: 100%; max-width: 900px; box-shadow: 0 10px 30px rgba(0,0,0,0.25); position: relative; }

    .btn-back-circle { position: absolute; top: -15px; left: -15px; width: 40px; height: 40px; background: white; border: 2px solid #ef4444; border-radius: 50%; color: #ef4444; font-size: 1.2rem; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 2px 5px rgba(0,0,0,0.2); font-weight: bold; }

    .step-container { display: none; }
    .step-container.active { display: block; }

    /* LANGKAH 1 */
    .step1-header { margin-bottom: 2rem; border-bottom: 1px solid #cbd5e1; padding-bottom: 1rem; }
    .step1-header h3 { margin: 0; font-size: 1.5rem; color: #1e293b; font-weight: 500; }
    .step1-header p { margin: 0.2rem 0 0 0; color: #64748b; font-size: 0.9rem; }

    .upload-box-large { background: #fae8e8; border: 1px solid #fecaca; border-radius: 16px; padding: 3rem 2rem; text-align: center; position: relative; }
    .excel-icon-large { width: 60px; margin-bottom: 1rem; }
    .upload-box-text { font-weight: 700; color: #1e293b; font-size: 1.1rem; margin-bottom: 0.5rem; }
    .upload-box-sub { color: #64748b; font-size: 0.9rem; margin-bottom: 1.5rem; }

    .btn-pilih-file { background: white; border: 1px solid #10b981; color: #10b981; padding: 0.5rem 1.5rem; border-radius: 20px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; }
    .btn-upload-step1 { width: 100%; background: #6366f1; color: white; border: none; padding: 1rem; border-radius: 8px; font-size: 1rem; font-weight: 600; margin-top: 1.5rem; cursor: pointer; box-shadow: 0 4px 6px rgba(99, 102, 241, 0.3); }

    /* LANGKAH 2 */
    .modal-header-filter { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 1.5rem; }
    .modal-title-box h3 { margin: 0; font-size: 1.8rem; color: #1e293b; font-weight: 500; }
    .modal-title-box p { margin: 0.5rem 0 0 0; color: #64748b; font-size: 1rem; }

    .total-baris-box { background: #fff; border: 1px solid #94a3b8; border-radius: 10px; padding: 0.5rem 1.5rem; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
    .total-baris-label { display: block; font-size: 0.85rem; color: #475569; margin-bottom: 0.2rem; }
    .total-baris-value { display: block; font-size: 2rem; font-weight: 800; color: #000; line-height: 1; }

    .file-banner { display: flex; justify-content: space-between; align-items: center; background-color: #fce4e4; border: 1px solid #fecaca; border-radius: 10px; padding: 1rem 1.5rem; margin-bottom: 2rem; }
    .file-banner-info { display: flex; align-items: center; gap: 1rem; color: #475569; font-size: 1rem; }
    .file-banner-info strong { color: #000; font-weight: 500; }
    .ganti-file-link { color: #2563eb; text-decoration: underline; font-weight: 600; cursor: pointer; }

    .filter-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2.5rem; }
    .filter-group label { display: block; font-size: 0.9rem; color: #1e293b; margin-bottom: 0.5rem; }
    .filter-group select { width: 100%; padding: 0.8rem 1rem; border: 1px solid #cbd5e1; border-radius: 10px; background-color: #fff; font-size: 0.95rem; color: #475569; appearance: none; background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e"); background-repeat: no-repeat; background-position: right 1rem center; background-size: 1em; }

    /* CUSTOM SELECT2 AGAR MENYATU DENGAN DESAIN ASLI */
    .select2-container .select2-selection--multiple {
        min-height: 44px; border: 1px solid #cbd5e1; border-radius: 10px; padding: 4px; background-image: none;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background-color: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; border-radius: 6px; padding: 2px 8px; margin-top: 4px;
    }

    .modal-footer { display: flex; justify-content: space-between; align-items: center; }
    .btn-kembali { background-color: #94a3b8; color: #fff; border: none; padding: 0.8rem 2rem; border-radius: 8px; font-size: 1rem; cursor: pointer; }
    .footer-right { display: flex; gap: 1rem; }
    .btn-reset-filter { background-color: #e2e8f0; color: #ef4444; border: 1px solid #cbd5e1; padding: 0.8rem 1.5rem; border-radius: 8px; font-size: 1rem; cursor: pointer; }
    .btn-lanjutkan { background-color: #007bff; color: #fff; border: none; padding: 0.8rem 2rem; border-radius: 8px; font-size: 1rem; font-weight: 500; cursor: pointer; }

    /* DASHBOARD PRATINJAU */
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 1rem; width: 100%; }
    .glass-card { background: rgba(255,255,255,0.9); padding: 1rem; border-radius: 12px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
    .stat-val { font-size: clamp(1.4rem, 2.5vw, 2.2rem); font-weight: 800; margin-top: 0.5rem; word-break: break-word; }
    .chart-container { background: rgba(255,255,255,0.9); border-radius: 12px; padding: 1.5rem; box-shadow: 0 4px 6px rgba(0,0,0,0.05); flex-grow: 1; display: flex; align-items: center; justify-content: center; min-width: 0; width: 100%; }

    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>

<!-- Banner Notifikasi Server-Side -->
@if(session('success'))
<div class="alert-box alert-success" id="server-alert-success" style="margin-bottom: 1.5rem; padding: 1rem 1.5rem; background-color: #d1fae5; border: 1px solid #6ee7b7; color: #065f46; border-radius: 12px; display: flex; align-items: center; justify-content: space-between;">
    <div style="display: flex; align-items: center; gap: 0.75rem;">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
        <span>{{ session('success') }}</span>
    </div>
    <a href="{{ route('manajemen-data') }}" style="color: #047857; font-weight: 700; text-decoration: underline;">Buka Manajemen Data &rarr;</a>
</div>
@endif

@if(session('error') || $errors->any())
<div class="alert-box alert-error" id="server-alert-error" style="margin-bottom: 1.5rem; padding: 1rem 1.5rem; background-color: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; border-radius: 12px; display: flex; align-items: center; gap: 0.75rem;">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
    <span>{{ session('error') ?? $errors->first() }}</span>
</div>
@endif

<!-- Banner Notifikasi Dinamis (Client-Side AJAX) -->
<div id="dynamic-notification" style="display: none; margin-bottom: 1.5rem; padding: 1rem 1.5rem; border-radius: 12px; font-weight: 500; align-items: center; justify-content: space-between; gap: 1rem; box-shadow: 0 4px 10px rgba(0,0,0,0.05); transition: all 0.3s ease;">
    <div style="display: flex; align-items: center; gap: 0.75rem;" id="dynamic-notification-text"></div>
    <div id="dynamic-notification-action"></div>
</div>

<div class="module-container">

    <!-- PANEL KIRI -->
    <aside class="action-sidebar">
        <h2 class="action-title">Upload Data</h2>

        <button type="button" class="btn-action" id="btn-open-modal">Upload Data</button>

        <!-- Form Utama ke Backend -->
        <form action="{{ route('upload.process') }}" method="POST" id="form-simpan" enctype="multipart/form-data">
            @csrf

            <input type="file" name="excel_file_final" id="excel_file_final" style="display: none;">
            <input type="hidden" name="jenis_data_final" id="jenis_data_final">
            <input type="hidden" name="tahun_final" id="tahun_final">
            <input type="hidden" name="bulan_final" id="bulan_final">
            <input type="hidden" name="status_final" id="status_final" value="Private">

            <!-- Tambahan hidden input untuk Provinsi, Kabkota, Negara, dan HS Code -->
            <input type="hidden" name="provinsi_final" id="provinsi_final">
            <input type="hidden" name="kabkota_final" id="kabkota_final">
            <input type="hidden" name="negara_final" id="negara_final">
            <input type="hidden" name="hscode_final" id="hscode_final">

            <button type="button" class="btn-action" id="btn-apply" disabled>Apply</button>
            <button type="submit" class="btn-action" id="btn-simpan" style="width: 100%; margin-top: 1rem;" disabled>Simpan</button>
        </form>

        <button type="button" class="btn-action" id="btn-reset">Reset</button>
    </aside>

    <!-- PANEL KANAN (PRATINJAU) -->
    <main class="preview-area">
        <div class="preview-overlay" id="preview-overlay">
            <svg width="50" height="50" fill="none" stroke="#94a3b8" stroke-width="1.5" viewBox="0 0 24 24"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            <h3 style="color: #475569; margin-top: 1rem;">Area Pratinjau Kosong</h3>
            <p style="color: #94a3b8; font-size: 0.9rem;">Klik "Upload Data" dan "Apply" untuk melihat visualisasi data.</p>
        </div>

        <div class="stats-grid">
            <div class="glass-card"><div style="color: #475569; font-weight:600;">M-To-M</div><div class="stat-val" id="val-mtom" style="color: #2563eb;">00%</div></div>
            <div class="glass-card"><div style="color: #475569; font-weight:600;">Q-To-Q</div><div class="stat-val" id="val-qtoq" style="color: #10b981;">00%</div></div>
            <div class="glass-card"><div style="color: #475569; font-weight:600;">Y-on-Y</div><div class="stat-val" id="val-yony" style="color: #f59e0b;">00%</div></div>
            <div class="glass-card"><div style="color: #475569; font-weight:600;">C-To-C</div><div class="stat-val" id="val-ctoc" style="color: #ef4444;">00%</div></div>
        </div>

        <!-- Wadah Grafik Chart.js -->
        <div class="chart-container">
            <div style="width: 100%; height: 350px; position: relative;">
                <canvas id="previewChart"></canvas>
            </div>
        </div>
    </main>

</div>

<!-- MODAL POPUP -->
<div id="uploadModal" class="modal">
    <div class="modal-content">
        <button class="btn-back-circle" id="btn-close-modal" title="Tutup Modal">&times;</button>

        <!-- LANGKAH 1 -->
        <div id="step1" class="step-container active">
            <div class="step1-header">
                <h3>Langkah 1 - Upload File Excel</h3>
                <p>Unggah file data ekspor dan impor dalam format Microsoft Excel (.xlsx)</p>
            </div>

            <div class="upload-box-large">
                <svg class="excel-icon-large" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect width="24" height="24" rx="4" fill="#107C41"/>
                    <path d="M15.5 17H13.5L12 12.5L10.5 17H8.5L11 11L8.5 5H10.5L12 9.5L13.5 5H15.5L13 11L15.5 17Z" fill="white"/>
                </svg>
                <div class="upload-box-text">Seret & Lepas File Excel Disini....</div>
                <div class="upload-box-sub">atau klik tombol dibawah untuk memilih file...</div>

                <input type="file" id="real-file-input" accept=".xlsx, .xls" style="display: none;">

                <button type="button" class="btn-pilih-file" onclick="document.getElementById('real-file-input').click()">
                    <span style="background:#107C41; color:white; padding: 2px 6px; border-radius:4px; font-size:0.8rem;">X</span> Pilih File Excel...
                </button>
                <div id="selected-file-name" style="margin-top: 0.75rem; color: #2563eb; font-weight: bold; display: none;"></div>

                <div style="margin-top: 1.25rem; display: flex; flex-direction: column; gap: 0.35rem; text-align: left; max-width: 320px; margin-left: auto; margin-right: auto;">
                    <label for="step1_jenis_data" style="font-size: 0.85rem; font-weight: 600; color: #334155;">Jenis Data Transaksi:</label>
                    <select id="step1_jenis_data" style="width: 100%; padding: 0.5rem 0.75rem; border: 1.5px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem; font-weight: 500; background-color: #fff; color: #1e293b; cursor: pointer;">
                        <option value="Ekspor">Ekspor</option>
                        <option value="Impor">Impor</option>
                    </select>
                </div>
            </div>

            <button type="button" class="btn-upload-step1" id="btn-to-step2">Upload</button>
        </div>

        <!-- LANGKAH 2 -->
        <div id="step2" class="step-container">
            <div class="modal-header-filter">
                <div class="modal-title-box">
                    <h3>Langkah 2 - Filtering Data</h3>
                    <p>Tentukan cakupan data yang akan diproses dari file yang akan diupload...</p>
                </div>
                <div class="total-baris-box">
                    <span class="total-baris-label">Total Baris</span>
                    <span class="total-baris-value" id="display-total-baris">0</span>
                </div>
            </div>

            <div class="file-banner">
                <div class="file-banner-info">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <rect width="24" height="24" rx="4" fill="#107C41"/>
                        <path d="M15.5 17H13.5L12 12.5L10.5 17H8.5L11 11L8.5 5H10.5L12 9.5L13.5 5H15.5L13 11L15.5 17Z" fill="white"/>
                    </svg>
                    <span><strong id="display-nama-file">Nama_File.xlsx</strong>
                    <span id="display-info-file">0 baris - 0 kolom - 0 MB</span></span>
                </div>
                <a class="ganti-file-link" id="btn-ganti-file">Ganti File</a>
            </div>

            <!-- Form Filter dengan Data Database -->
           <form id="filtering-form">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label>Jenis Data</label>
                        <select id="filter_jenis" name="jenis_data">
                            <option value="Ekspor">Ekspor</option>
                            <option value="Impor">Impor</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Tahun</label>
                        <select id="filter_tahun" name="tahun">
                            @for($y = date('Y'); $y >= 2020; $y--)
                            <option value="{{ $y }}">{{ $y }}</option>
                            @endfor
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Bulan</label>
                        <select id="filter_bulan" name="bulan">
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

                    <div class="filter-group">
                        <label>Provinsi</label>
                        <select id="filter_provinsi" name="provinsi_id">
                            <option value="">-- Semua Provinsi --</option>
                            @foreach($provinsis ?? [] as $prov)
                            <option value="{{ $prov->provinsi_id }}">{{ $prov->nama_provinsi }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Kabupaten/Kota</label>
                        <select id="filter_kabkota" name="kabkota_id">
                            <option value="">-- Semua Kab/Kota --</option>
                            @foreach($kabkotas ?? [] as $kab)
                            <option value="{{ $kab->kabkota_id }}">{{ $kab->nama_kabkota }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Public/Pending</label>
                        <select id="filter_status" name="status">
                            <option value="Pending" selected>Pending</option>
                            <option value="Public">Public</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Negara (Bisa pilih >1)</label>
                        <select id="filter_negara" name="negara_id[]" multiple="multiple" style="width: 100%">
                            <option value="ALL">-- Semua Negara --</option>
                            @foreach($negaras ?? [] as $neg)
                            <option value="{{ $neg->kode_negara ?? $neg->negara_id }}">{{ $neg->nama_negara }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-group" style="grid-column: span 2;">
                        <label>HS CODE (Bisa pilih >1)</label>
                        <select id="filter_hscode" name="hs_code[]" multiple="multiple" style="width: 100%">
                            <option value="ALL">-- Semua HS Code --</option>
                            @foreach($hs_codes ?? [] as $hs)
                                <option value="{{ str_pad($hs->hs2, 2, '0', STR_PAD_LEFT) }}">
                                    {{ str_pad($hs->hs2, 2, '0', STR_PAD_LEFT) }} — {{$hs->hs2_desc }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-kembali" id="btn-back-sep1">Kembali...</button>
                    <div class="footer-right">
                        <button type="button" class="btn-reset-filter" id="btn-reset-filter">Reset Filter...</button>
                        <button type="button" class="btn-lanjutkan" id="btn-lanjutkan">Simpan</button>
                    </div>
                </div>
            </form>
        </div>

    </div>
</div>

<!-- MEMINDAHKAN SCRIPT KE DALAM CONTENT AGAR LANGSUNG DIEKSEKUSI -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    // Menyimpan Instance Chart.js agar bisa dihapus/dibuat ulang saat Apply ditekan
    // Menyimpan grafik agar bisa diperbarui tanpa menumpuk grafik lama.
    let previewChartInstance = null;

    // Menyimpan data Excel hasil pembacaan untuk proses Apply.
    let previewExcelHeaders = [];
    let previewExcelData = [];
    let previewExcelSheet = '';

    // INISIALISASI SELECT 2 UNTUK DROPDOWN MULTIPLE
    $(document).ready(function() {$('#filter_negara').select2({
            placeholder: "-- Pilih Negara --",
            allowClear: true,
            width: '100%'
        });

        $('#filter_hscode').select2({
            placeholder: "-- Pilih HS Code --",
            allowClear: true,
            width: '100%'
        });
    });

    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('uploadModal');
        const btnOpenModal = document.getElementById('btn-open-modal');
        const btnCloseModal = document.getElementById('btn-close-modal');

        const btnApply = document.getElementById('btn-apply');
        const btnSimpan = document.getElementById('btn-simpan');
        const btnResetDashboard = document.getElementById('btn-reset');
        const previewOverlay = document.getElementById('preview-overlay');

        const step1 = document.getElementById('step1');
        const step2 = document.getElementById('step2');
        const fileInput = document.getElementById('real-file-input');
        const hiddenFileInputFinal = document.getElementById('excel_file_final');
        const selectedFileName = document.getElementById('selected-file-name');

        const btnToStep2 = document.getElementById('btn-to-step2');
        const btnBackStep1 = document.getElementById('btn-back-step1');
        const btnGantiFile = document.getElementById('btn-ganti-file');
        const btnLanjutkan = document.getElementById('btn-lanjutkan');
        const btnResetFilter = document.getElementById('btn-reset-filter');

        const displayTotalBaris = document.getElementById('display-total-baris');
        const displayNamaFile = document.getElementById('display-nama-file');
        const displayInfoFile = document.getElementById('display-info-file');

        let isFilterComplete = false;

        btnOpenModal.addEventListener('click', () => modal.style.display = 'flex');
        btnCloseModal.addEventListener('click', () => modal.style.display = 'none');

        btnBackStep1.addEventListener('click', () => { step2.classList.remove('active'); step1.classList.add('active'); });
        btnGantiFile.addEventListener('click', () => { step2.classList.remove('active'); step1.classList.add('active'); fileInput.click(); });

        fileInput.addEventListener('change', function(e) {
            if(e.target.files.length > 0) {
                const f = e.target.files[0];
                const sizeMB = (f.size / (1024 * 1024)).toFixed(2);

                if (f.size > 50 * 1024 * 1024) {
                    alert(`Ukuran file "${f.name}" (${sizeMB} MB) melebihi batas maksimal aplikasi sebesar 50 MB. Silakan pilih file lain.`);
                    fileInput.value = '';
                    selectedFileName.style.display = 'none';
                    return;
                }

                selectedFileName.style.display = 'block';
                selectedFileName.textContent = `${f.name} (${sizeMB} MB)`;

                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(f);
                hiddenFileInputFinal.files = dataTransfer.files;
            }
        });


btnToStep2.addEventListener('click', () => {
    const currentFile = fileInput.files[0];

    if (!currentFile) {
        alert('Pilih file Excel terlebih dahulu!');
        return;
    }

    // Memastikan format file yang dipilih sesuai.
    const extension = currentFile.name.split('.').pop().toLowerCase();

    if (!['xlsx', 'xls'].includes(extension)) {
        alert('File harus berformat .xlsx atau .xls.');
        return;
    }

    // Validasi ukuran file pada sisi browser sebelum proses berat
    const fileSizeMB = currentFile.size / (1024 * 1024);
    if (currentFile.size > 50 * 1024 * 1024) {
        alert(`Ukuran file (${fileSizeMB.toFixed(2)} MB) melebihi batas maksimal 50 MB. Silakan kurangi baris data atau unggah berkas yang lebih ringkas.`);
        return;
    }

    // Ambil jenis data yang dipilih dari Langkah 1
    const step1Jenis = document.getElementById('step1_jenis_data')?.value || 'Ekspor';
    // Sinkronkan ke dropdown Langkah 2
    const filterJenisSelect = document.getElementById('filter_jenis');
    if (filterJenisSelect) {
        filterJenisSelect.value = step1Jenis;
    }

    const reader = new FileReader();

    reader.onload = function (e) {
        let workbook;

        try {
            const data = new Uint8Array(e.target.result);

            workbook = XLSX.read(data, {
                type: 'array'
            });

            const sheetNames = workbook.SheetNames || [];
            if (sheetNames.length === 0) {
                alert('File Excel tidak memiliki lembar kerja (worksheet).');
                return;
            }

            // Prioritas nama sheet berdasarkan jenis data yang dipilih
            const prioritasSheet = step1Jenis === 'Impor'
                ? ['Impor', 'Import', 'RawDataImpor', 'Raw_Data_Impor', 'DataImpor', 'Data_Impor', 'RawData', 'Sheet1']
                : ['Ekspor', 'Export', 'RawDataEkspor', 'Raw_Data_Ekspor', 'DataEkspor', 'Data_Ekspor', 'RawData', 'Sheet1'];

            let namaSheetDitemukan = null;
            let worksheet = null;

            // 1. Pencarian persis dari daftar prioritas
            for (const candidate of prioritasSheet) {
                if (workbook.Sheets[candidate]) {
                    namaSheetDitemukan = candidate;
                    worksheet = workbook.Sheets[candidate];
                    break;
                }
            }

            // 2. Pencarian case-insensitive atau parsial
            if (!worksheet) {
                const targetKeyword = step1Jenis.toLowerCase();
                const altKeyword = step1Jenis === 'Impor' ? 'import' : 'export';

                for (const sName of sheetNames) {
                    const cleanName = sName.toLowerCase().trim();
                    if (cleanName.includes(targetKeyword) || cleanName.includes(altKeyword)) {
                        namaSheetDitemukan = sName;
                        worksheet = workbook.Sheets[sName];
                        break;
                    }
                }
            }

            // 3. Fallback jika hanya ada 1 sheet di workbook
            if (!worksheet && sheetNames.length === 1) {
                namaSheetDitemukan = sheetNames[0];
                worksheet = workbook.Sheets[namaSheetDitemukan];
            }

            if (!worksheet) {
                const daftarSheet = sheetNames.join(', ');
                alert(`Worksheet untuk data ${step1Jenis} tidak ditemukan. Sheet yang tersedia: [${daftarSheet}]. Silakan periksa file Excel yang dipilih.`);
                return;
            }

            // Membaca isi sheet sebagai array.
            const rows = XLSX.utils.sheet_to_json(worksheet, {
                header: 1,
                defval: null,
                blankrows: false
            });

            if (rows.length < 2) {
                alert(`Sheet "${namaSheetDitemukan}" belum memiliki baris data.`);
                return;
            }

            const headers = rows[0].map(header =>
                String(header ?? '').trim().toUpperCase()
            );

            // Kolom yang harus tersedia untuk proses awal
            // Nilai transaksi bisa berupa 'FOB (US$)', 'CIF (US$)', 'NILAI', 'FOB', 'CIF', 'VALUE'
            const hasBulan = headers.some(h => ['BULAN', 'BLN', 'MONTH'].includes(h));
            const hasTahun = headers.some(h => ['TAHUN', 'THN', 'YEAR'].includes(h));
            const hasHS = headers.some(h => ['KODE HS', 'HS', 'HS_CODE', 'KODEHS'].includes(h));
            const hasNegara = headers.some(h => ['KODE_NGR', 'NEGARA', 'KODE_NEGARA', 'NGR'].includes(h));
            const hasNetto = headers.some(h => ['NETTO (KG)', 'BERAT', 'NETTO', 'WEIGHT'].includes(h));
            const hasNilai = headers.some(h => ['FOB (US$)', 'CIF (US$)', 'NILAI', 'FOB', 'CIF', 'VAL', 'VALUE'].includes(h));
            const hasProv = headers.some(h => ['PROVORIG', 'PROPINSI', 'PROVINSI', 'PROPASAL', 'PROV'].includes(h));

            const missingList = [];
            if (!hasBulan) missingList.push('BULAN');
            if (!hasTahun) missingList.push('TAHUN');
            if (!hasHS) missingList.push('KODE HS');
            if (!hasNegara) missingList.push('KODE_NGR');
            if (!hasNetto) missingList.push('NETTO (KG)');
            if (!hasNilai) missingList.push(step1Jenis === 'Impor' ? 'CIF/FOB/NILAI' : 'FOB (US$)');
            if (!hasProv) missingList.push('PROVORIG');

            if (missingList.length > 0) {
                alert(
                    `Kolom wajib tidak ditemukan pada sheet "${namaSheetDitemukan}":\n` +
                    missingList.join(', ')
                );
                return;
            }

            // Menghitung baris yang memiliki setidaknya satu nilai.
            const dataRows = rows.slice(1).filter(row =>
                row.some(value =>
                    value !== null &&
                    String(value).trim() !== ''
                )
            );

            if (dataRows.length === 0) {
                alert('Tidak ada baris data yang dapat diproses.');
                return;
            }

            // Simpan header dan baris data untuk digunakan oleh tombol Apply.
            previewExcelHeaders = rows[0].map(header =>
                String(header ?? '').trim()
            );

            previewExcelData = dataRows;
            previewExcelSheet = namaSheetDitemukan;

            const totalRows = dataRows.length;
            const totalCols = rows[0].length;
            const fileSizeMB = (
                currentFile.size / (1024 * 1024)
            ).toFixed(2);

            displayTotalBaris.textContent =
                totalRows.toLocaleString('id-ID');

            displayNamaFile.textContent = currentFile.name;

            displayInfoFile.textContent =
                `${totalRows.toLocaleString('id-ID')} baris data - ` +
                `${totalCols} kolom - ${fileSizeMB} MB - ` +
                `Sheet: ${namaSheetDitemukan}`;

            step1.classList.remove('active');
            step2.classList.add('active');

        } catch (error) {
            console.error('Kesalahan membaca Excel:', error);
            alert('File Excel gagal dibaca. Periksa kembali file tersebut.');
        }
    };

    reader.onerror = function () {
        alert('File gagal dibaca oleh browser.');
    };

    reader.readAsArrayBuffer(currentFile);
});


        btnResetFilter.addEventListener('click', () => {
            document.getElementById('filtering-form').reset();
            // Reset juga tampilan Select2
            $('#filter_negara').val(null).trigger('change');
            $('#filter_hscode').val(null).trigger('change');
        });

        btnLanjutkan.addEventListener('click', () => {
            // Ambil data dropdown biasa
            document.getElementById('jenis_data_final').value = document.getElementById('filter_jenis').value;
            document.getElementById('tahun_final').value = document.getElementById('filter_tahun').value;
            document.getElementById('bulan_final').value = document.getElementById('filter_bulan').value;
            document.getElementById('status_final').value = document.getElementById('filter_status').value;
            document.getElementById('provinsi_final').value = document.getElementById('filter_provinsi').value;
            document.getElementById('kabkota_final').value = document.getElementById('filter_kabkota').value;

            // Ambil data dari Select2 (Multiple)
            let selectedNegara = $('#filter_negara').val();
            let selectedHsCode = $('#filter_hscode').val();

            // Konversi Array menjadi format string yang dipisahkan koma
            document.getElementById('negara_final').value = selectedNegara ? selectedNegara.join(',') : '';
            document.getElementById('hscode_final').value = selectedHsCode ? selectedHsCode.join(',') : '';

            isFilterComplete = true;
            btnApply.disabled = false;
            modal.style.display = 'none';
        });


        btnApply.addEventListener('click', () => {
            if (!isFilterComplete) {
                alert('Selesaikan filter data terlebih dahulu!');
                return;
            }

            // Pastikan preview Excel sudah berhasil dibaca.
            if (!previewExcelHeaders.length || !previewExcelData.length) {
                alert('Data Excel belum tersedia. Silakan upload ulang file.');
                return;
            }

            // Mencari posisi kolom berdasarkan daftar alternatif nama header
            const findColumn = (aliases) =>
                previewExcelHeaders.findIndex(header => {
                    const clean = String(header).trim().toUpperCase();
                    return aliases.includes(clean);
                });

            const colBulan = findColumn(['BULAN', 'BLN', 'MONTH']);
            const colTahun = findColumn(['TAHUN', 'THN', 'YEAR']);
            const colNilai = findColumn(['FOB (US$)', 'CIF (US$)', 'NILAI', 'FOB', 'CIF', 'VAL', 'VALUE']);

            console.log('Header Excel:', previewExcelHeaders);
            console.log('Index kolom:', {
                bulan: colBulan,
                tahun: colTahun,
                nilai: colNilai
            });

            if (colBulan === -1 || colTahun === -1 || colNilai === -1) {
                alert('Kolom periode (BULAN, TAHUN) atau nilai transaksi (FOB/CIF/NILAI) tidak ditemukan pada file.');
                return;
            }

            const jenisData = document.getElementById('jenis_data_final').value;
            const tahunDipilih = Number(document.getElementById('tahun_final').value);
            const bulanDipilih = Number(document.getElementById('bulan_final').value);

            // Mengubah nilai Excel menjadi angka, termasuk format angka dengan koma.
            const parseNumber = (value) => {
                if (typeof value === 'number') {
                    return Number.isFinite(value) ? value : null;
                }

                if (value === null || value === undefined || String(value).trim() === '') {
                    return null;
                }

                let text = String(value).trim().replace(/\s/g, '');

                // Menangani format angka seperti 1,234.56 atau 1234,56.
                if (text.includes('.') && text.includes(',')) {
                    if (text.lastIndexOf(',') > text.lastIndexOf('.')) {
                        text = text.replace(/\./g, '').replace(',', '.');
                    } else {
                        text = text.replace(/,/g, '');
                    }
                } else if (text.includes(',')) {
                    text = text.replace(',', '.');
                }

                const number = Number(text);
                return Number.isFinite(number) ? number : null;
            };


            // ─────────────────────────────────────────────────────────────────
            // HELPER: Mengubah nama bulan Bahasa Indonesia menjadi angka 1–12.
            // Menerima string nama bulan atau angka langsung.
            // ─────────────────────────────────────────────────────────────────
            const NAMA_BULAN_KE_ANGKA = {
                januari: 1, februari: 2, maret: 3, april: 4,
                mei: 5, juni: 6, juli: 7, agustus: 8,
                september: 9, oktober: 10, november: 11, desember: 12
            };

            const parseBulan = (value) => {
                if (value === null || value === undefined) return null;
                const teks = String(value).trim().toLowerCase();
                if (NAMA_BULAN_KE_ANGKA[teks] !== undefined) {
                    return NAMA_BULAN_KE_ANGKA[teks];
                }
                const angka = parseNumber(value);
                return (angka !== null && Number.isInteger(angka) && angka >= 1 && angka <= 12)
                    ? angka : null;
            };

            // ─────────────────────────────────────────────────────────────────
            // HELPER: Format angka ke format Indonesia (misal: 1.234,56)
            // ─────────────────────────────────────────────────────────────────
            const formatNilai = (nilai) =>
                nilai.toLocaleString('id-ID', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });

            // ─────────────────────────────────────────────────────────────────
            // HELPER: Hitung pertumbuhan persen antara dua periode.
            // Mengembalikan "—" jika nilai pembanding nol atau tidak tersedia.
            // ─────────────────────────────────────────────────────────────────
            const hitungPertumbuhan = (nilaiSekarang, nilaiSebelumnya) => {
                if (nilaiSebelumnya === undefined || nilaiSebelumnya === null || nilaiSebelumnya === 0) {
                    return '—';
                }
                const pertumbuhan =
                    ((nilaiSekarang - nilaiSebelumnya) / Math.abs(nilaiSebelumnya)) * 100;
                return `${pertumbuhan > 0 ? '+' : ''}${pertumbuhan.toLocaleString('id-ID', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                })}%`;
            };

            // ─────────────────────────────────────────────────────────────────
            // HELPER: Jumlahkan nilai FOB pada rentang bulan [awal, akhir).
            // Indeks berbasis 0: Jan=0, Des=11.
            // Mengembalikan null jika tidak ada data untuk tahun tersebut.
            // ─────────────────────────────────────────────────────────────────
            const totalRentangBulan = (dataPerTahun, tahun, awal, akhir) => {
                const data = dataPerTahun[tahun];
                if (!data) return null;
                return data.slice(awal, akhir).reduce((total, nilai) => total + nilai, 0);
            };



            let jumlahBarisValid = 0;
            let jumlahBarisTidakValid = 0;
            const totalPerTahunBulan = {};

            previewExcelData.forEach(row => {
                let tahun = parseNumber(row[colTahun]);
                const bulanRaw = row[colBulan];
                const nilai = parseNumber(row[colNilai]);

                // Menyesuaikan kode tahun pendek 20–26 menjadi 2020–2026.
                if (tahun !== null && tahun >= 20 && tahun <= 26) {
                    tahun += 2000;
                }

                // Gunakan parseBulan yang sudah menangani nama bulan & angka.
                const bulan = parseBulan(bulanRaw);

                // Lewati baris yang periodenya atau nilainya tidak valid.
                if (
                    tahun === null ||
                    bulan === null ||
                    nilai === null ||
                    !Number.isInteger(tahun) ||
                    !Number.isInteger(bulan) ||
                    bulan < 1 || bulan > 12
                ) {
                    jumlahBarisTidakValid++;
                    return;
                }

                jumlahBarisValid++;

                // Inisialisasi array 12 bulan (semua nol) jika tahun belum ada.
                if (!totalPerTahunBulan[tahun]) {
                    totalPerTahunBulan[tahun] = Array(12).fill(0);
                }

                // Akumulasikan nilai FOB ke indeks bulan yang sesuai (0–11).
                totalPerTahunBulan[tahun][bulan - 1] += nilai;
            });

            // Log untuk transparansi — lihat di Console browser (F12).
            console.info('Baris valid:', jumlahBarisValid);
            console.info('Baris tidak valid:', jumlahBarisTidakValid);
            console.info('Distribusi data per tahun:', Object.keys(totalPerTahunBulan));

            // Sembunyikan overlay dan tampilkan area pratinjau.
            previewOverlay.style.display = 'none';

            // ─────────────────────────────────────────────────────────────────
            // STATISTIK PERTUMBUHAN
            // Semua kalkulasi menggunakan totalPerTahunBulan sebagai sumber tunggal.
            // ─────────────────────────────────────────────────────────────────

            // Nilai FOB bulan yang dipilih pada tahun yang dipilih.
            const totalBulanIni =
                totalPerTahunBulan[tahunDipilih]?.[bulanDipilih - 1] ?? 0;

            // M-to-M: nilai bulan ini vs bulan sebelumnya.
            // Jika bulan ini Januari, bandingkan dengan Desember tahun lalu.
            const bulanSebelumnya = bulanDipilih === 1 ? 12 : bulanDipilih - 1;
            const tahunBulanSebelumnya = bulanDipilih === 1 ? tahunDipilih - 1 : tahunDipilih;
            const totalBulanSebelumnya =
                totalPerTahunBulan[tahunBulanSebelumnya]?.[bulanSebelumnya - 1];
            document.getElementById('val-mtom').textContent =
                hitungPertumbuhan(totalBulanIni, totalBulanSebelumnya);

            // Q-to-Q: total kuartal berjalan vs kuartal sebelumnya.
            // Kuartal 1: Jan–Mar (indeks 0–2), Q2: Apr–Jun, Q3: Jul–Sep, Q4: Okt–Des.
            const kuartalIni = Math.ceil(bulanDipilih / 3);
            const bulanAwalKuartal = (kuartalIni - 1) * 3;   // indeks mulai (0-based)
            const bulanAkhirKuartal = kuartalIni * 3;         // indeks akhir eksklusif
            const totalKuartalIni =
                totalRentangBulan(totalPerTahunBulan, tahunDipilih, bulanAwalKuartal, bulanAkhirKuartal);

            const kuartalSebelumnya = kuartalIni === 1 ? 4 : kuartalIni - 1;
            const tahunKuartalSebelumnya = kuartalIni === 1 ? tahunDipilih - 1 : tahunDipilih;
            const totalKuartalSebelumnya = totalRentangBulan(
                totalPerTahunBulan,
                tahunKuartalSebelumnya,
                (kuartalSebelumnya - 1) * 3,
                kuartalSebelumnya * 3
            );
            document.getElementById('val-qtoq').textContent =
                hitungPertumbuhan(totalKuartalIni, totalKuartalSebelumnya);

            // Y-on-Y: nilai bulan ini vs bulan yang sama tahun lalu.
            const totalBulanTahunLalu =
                totalPerTahunBulan[tahunDipilih - 1]?.[bulanDipilih - 1];
            document.getElementById('val-yony').textContent =
                hitungPertumbuhan(totalBulanIni, totalBulanTahunLalu);

            // C-to-C: kumulatif Jan s.d. bulan dipilih, dibanding periode sama tahun lalu.
            // Indeks 0 s.d. bulanDipilih (eksklusif) = Jan hingga bulan terpilih.
            const totalKumulatifTahunIni =
                totalRentangBulan(totalPerTahunBulan, tahunDipilih, 0, bulanDipilih);
            const totalKumulatifTahunLalu =
                totalRentangBulan(totalPerTahunBulan, tahunDipilih - 1, 0, bulanDipilih);
            document.getElementById('val-ctoc').textContent =
                hitungPertumbuhan(totalKumulatifTahunIni, totalKumulatifTahunLalu);

            // ─────────────────────────────────────────────────────────────────
            // GRAFIK BULANAN
            // Ambil array 12 elemen untuk tahun dipilih; bulan tanpa data = 0.
            // Konversi ke juta US$ agar skala grafik lebih terbaca.
            // ─────────────────────────────────────────────────────────────────
            const nilaiPerBulan =
                totalPerTahunBulan[tahunDipilih] ?? Array(12).fill(0);
            const nilaiJutaUSD = nilaiPerBulan.map(v => v / 1_000_000);



            const ctx = document.getElementById('previewChart').getContext('2d');

            if (previewChartInstance) {
                previewChartInstance.destroy();
            }

            const bgColor = jenisData === 'Ekspor'
                ? 'rgba(37, 99, 235, 0.7)'
                : 'rgba(16, 185, 129, 0.7)';

            const bdColor = jenisData === 'Ekspor'
                ? 'rgba(37, 99, 235, 1)'
                : 'rgba(16, 185, 129, 1)';

            const namaBulan = [
                'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
                'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'
            ];

            previewChartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: namaBulan,
                    datasets: [{
                        label: `Total nilai ${jenisData} ${tahunDipilih} (juta US$)`,
                        data: nilaiJutaUSD,
                        backgroundColor: bgColor,
                        borderColor: bdColor,
                        borderWidth: 1,
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        },
                        title: {
                            display: true,
                            text: `Tren Nilai ${jenisData} Provinsi Riau Tahun ${tahunDipilih}`,
                            font: {
                                size: 16
                            },
                            padding: {
                                bottom: 20
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: context =>
                                    `${context.dataset.label}: ${formatNilai(context.raw)}`
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Juta US$'
                            }
                        }
                    }
                }
            });

            // Aktifkan Simpan hanya setelah Apply berhasil dijalankan.
            btnSimpan.disabled = false;

            // Log total baris valid dan nilai bulan yang dipilih untuk debugging.
            console.info('Total baris valid seluruh data:', jumlahBarisValid);
            console.info('Total baris tidak valid:', jumlahBarisTidakValid);
            console.info(
                `Total FOB bulan ${bulanDipilih}/${tahunDipilih} (US$):`,
                totalPerTahunBulan[tahunDipilih]?.[bulanDipilih - 1] ?? 0
            );

        });

        // ─────────────────────────────────────────────────────────────────
        // FUNGSI NOTIFIKASI DINAMIS
        // ─────────────────────────────────────────────────────────────────
        const notifBox = document.getElementById('dynamic-notification');
        const notifText = document.getElementById('dynamic-notification-text');
        const notifAction = document.getElementById('dynamic-notification-action');

        function showNotification(type, message, actionHtml = '') {
            if (!notifBox) return;
            notifBox.style.display = 'flex';
            if (type === 'success') {
                notifBox.style.backgroundColor = '#d1fae5';
                notifBox.style.border = '1px solid #6ee7b7';
                notifBox.style.color = '#065f46';
                notifText.innerHTML = `
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    <span>${message}</span>
                `;
            } else {
                notifBox.style.backgroundColor = '#fee2e2';
                notifBox.style.border = '1px solid #fca5a5';
                notifBox.style.color = '#991b1b';
                notifText.innerHTML = `
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <span>${message}</span>
                `;
            }
            notifAction.innerHTML = actionHtml;
            notifBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        function hideNotification() {
            if (notifBox) notifBox.style.display = 'none';
        }

        // ─────────────────────────────────────────────────────────────────
        // HANDLER SIMPAN DATA (AJAX + INDIKATOR LOADING + GUARD DOUBLE CLICK)
        // ─────────────────────────────────────────────────────────────────
        const formSimpan = document.getElementById('form-simpan');

        formSimpan.addEventListener('submit', async function(e) {
            e.preventDefault();

            // 1. Validasi awal: tombol Apply harus sudah dijalankan dan data tersedia
            if (!isFilterComplete || !previewExcelData.length) {
                showNotification('error', 'Silakan pilih file dan klik tombol "Apply" terlebih dahulu sebelum menyimpan data.');
                return;
            }

            // 2. Ambil file asli dari input (baik dari fileInput modal maupun hidden input)
            const file = (fileInput && fileInput.files && fileInput.files[0])
                      || (hiddenFileInputFinal && hiddenFileInputFinal.files && hiddenFileInputFinal.files[0]);

            if (!file) {
                showNotification('error', 'File Excel belum dipilih atau tidak terbaca. Silakan pilih kembali file Excel.');
                return;
            }

            // 3. Siapkan FormData langsung dari form + file asli
            const chosenStatus = document.getElementById('filter_status')?.value
                              || document.getElementById('status_final')?.value
                              || 'Pending';
            document.getElementById('status_final').value = chosenStatus;

            const formData = new FormData(formSimpan);
            formData.set('excel_file_final', file);
            formData.set('status_final', chosenStatus);

            // 4. Set state tombol Simpan ke kondisi LOADING
            const originalBtnHtml = btnSimpan.innerHTML;
            btnSimpan.disabled = true;
            btnSimpan.innerHTML = `
                <span style="display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation: spin 1s linear infinite;">
                        <circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle>
                        <path d="M12 2a10 10 0 0 1 10 10" stroke-opacity="0.75"></path>
                    </svg>
                    Menyimpan...
                </span>
            `;
            hideNotification();

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                               || document.querySelector('input[name="_token"]')?.value
                               || '';

                const response = await fetch(formSimpan.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    }
                });

                // Cek status HTTP khusus terlebih dahulu
                if (response.status === 419) {
                    showNotification('error', 'Sesi login atau token keamanan (CSRF) Anda telah kedaluwarsa. Silakan refresh halaman browser (F5) lalu ulangi proses upload.');
                    btnSimpan.innerHTML = originalBtnHtml;
                    btnSimpan.disabled = false;
                    return;
                }

                // Coba parse respon sebagai JSON atau teks
                const rawText = await response.text();
                let result = null;
                try {
                    result = JSON.parse(rawText);
                } catch (parseErr) {
                    console.warn('Respon server bukan JSON:', response.status, rawText);
                }

                if (response.status === 413) {
                    const serverMsg = result && result.message ? result.message : 'Ukuran payload file melampaui batas server. Pastikan ukuran file tidak melebihi 50 MB.';
                    showNotification('error', serverMsg);
                    btnSimpan.innerHTML = originalBtnHtml;
                    btnSimpan.disabled = false;
                    return;
                }

                if (response.ok && result && result.success) {
                    // Sukses menyimpan ke database
                    btnSimpan.innerHTML = '✓ Tersimpan';
                    btnSimpan.style.backgroundColor = '#10b981';

                    const actionBtn = `
                        <a href="${result.redirect_url || '{{ route('manajemen-data') }}'}"
                           style="background: #059669; color: white; padding: 0.5rem 1rem; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 0.4rem;">
                            Buka Manajemen Data &rarr;
                        </a>
                    `;
                    showNotification('success', result.message || 'Data berhasil disimpan ke database!', actionBtn);

                    // Kembalikan tombol ke aktif setelah beberapa detik jika pengguna ingin aksi lain
                    setTimeout(() => {
                        btnSimpan.innerHTML = originalBtnHtml;
                        btnSimpan.style.backgroundColor = '';
                        btnSimpan.disabled = false;
                    }, 4000);

                } else {
                    // Respon error dari server / validasi Laravel
                    let errMsg = 'Gagal menyimpan data ke database.';
                    if (result && result.message) {
                        errMsg = result.message;
                    } else if (result && result.errors) {
                        errMsg = Object.values(result.errors).flat().join('<br>');
                    } else if (response.status === 500) {
                        errMsg = 'Terjadi kesalahan pada server (Error 500). Periksa log aplikasi atau koneksi database.';
                    } else if (response.status === 404) {
                        errMsg = 'Rute penyimpanan tidak ditemukan (Error 404).';
                    }

                    showNotification('error', errMsg);
                    btnSimpan.innerHTML = originalBtnHtml;
                    btnSimpan.disabled = false;
                }

            } catch (networkError) {
                console.error('Error saat menyimpan ke database:', networkError);
                showNotification('error', 'Terjadi kesalahan koneksi atau jaringan saat menghubungi server: ' + networkError.message);
                btnSimpan.innerHTML = originalBtnHtml;
                btnSimpan.disabled = false;
            }
        });

        btnResetDashboard.addEventListener('click', () => {
            hideNotification();
            previewOverlay.style.display = 'flex';
            document.getElementById('filtering-form').reset();
            document.getElementById('form-simpan').reset();

            $('#filter_negara').val(null).trigger('change');
            $('#filter_hscode').val(null).trigger('change');

            fileInput.value = '';
            hiddenFileInputFinal.value = '';
            selectedFileName.style.display = 'none';
            btnApply.disabled = true;
            btnSimpan.disabled = true;
            isFilterComplete = false;
            previewExcelHeaders = [];
            previewExcelData = [];
            previewExcelSheet = '';
            step2.classList.remove('active');
            step1.classList.add('active');

            // Hapus grafik saat di-reset
            if (previewChartInstance) {
                previewChartInstance.destroy();
                previewChartInstance = null;
            }
        });
    });
</script>
@endsection
