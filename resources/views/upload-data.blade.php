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

    .preview-area { flex-grow: 1; background-color: rgba(241, 245, 249, 0.5); border-radius: 16px; padding: 1.5rem; position: relative; display: flex; flex-direction: column; gap: 1.5rem; }
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
    .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; }
    .glass-card { background: rgba(255,255,255,0.9); padding: 1rem; border-radius: 12px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
    .stat-val { font-size: 2.2rem; font-weight: 800; margin-top: 0.5rem; }
    .chart-container { background: rgba(255,255,255,0.9); border-radius: 12px; padding: 1.5rem; box-shadow: 0 4px 6px rgba(0,0,0,0.05); flex-grow: 1; display: flex; align-items: center; justify-content: center; }
</style>

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
            <input type="hidden" name="status_final" id="status_final">

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
                <div id="selected-file-name" style="margin-top: 1rem; color: #2563eb; font-weight: bold; display: none;"></div>
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
                            <option value="Public">Public</option>
                            <option value="Pending">Pending</option>
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
                    <button type="button" class="btn-kembali" id="btn-back-step1">Kembali...</button>
                    <div class="footer-right">
                        <button type="button" class="btn-reset-filter" id="btn-reset-filter">Reset Filter...</button>
                        <button type="button" class="btn-lanjutkan" id="btn-lanjutkan">Lanjutkan Lekk...</button>
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
    let previewChartInstance = null;

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
                selectedFileName.style.display = 'block';
                selectedFileName.textContent = e.target.files[0].name;

                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(e.target.files[0]);
                hiddenFileInputFinal.files = dataTransfer.files;
            }
        });

        btnToStep2.addEventListener('click', () => {
            const currentFile = fileInput.files[0];
            if(!currentFile) return alert('Pilih file Excel dulu!');

            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const data = new Uint8Array(e.target.result);
                    const workbook = XLSX.read(data, {type: 'array'});
                    const worksheet = workbook.Sheets[workbook.SheetNames[0]];
                    const json = XLSX.utils.sheet_to_json(worksheet, {header: 1});

                    const totalRows = json.length;
                    const totalCols = totalRows > 0 ? json[0].length : 0;

                    // PERBAIKAN: Menambahkan perhitungan yang benar untuk MB
                    const fileSizeMB = (currentFile.size / (1024 * 1024)).toFixed(1);

                    displayTotalBaris.textContent = totalRows.toLocaleString('id-ID');
                    displayNamaFile.textContent = currentFile.name;
                    displayInfoFile.textContent = `${totalRows.toLocaleString('id-ID')} baris - ${totalCols} kolom - ${fileSizeMB} MB`;

                    step1.classList.remove('active');
                    step2.classList.add('active');
                } catch (error) {
                    alert('Gagal membaca file Excel.');
                }
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
            if(!isFilterComplete) return alert('Selesaikan filter data terlebih dahulu!');

            // 1. Hilangkan layar abu-abu (Overlay)
            previewOverlay.style.display = 'none';
            btnSimpan.disabled = false;

            // 2. Ambil parameter untuk judul grafik
            let jenisData = document.getElementById('jenis_data_final').value || 'Ekspor';
            let tahunData = document.getElementById('tahun_final').value || new Date().getFullYear();

            // 3. Simulasi Angka Statistik (Persentase)
            document.getElementById('val-mtom').textContent = '+' + (Math.random() * 5 + 1).toFixed(2) + '%';
            document.getElementById('val-qtoq').textContent = '+' + (Math.random() * 10 + 2).toFixed(2) + '%';
            document.getElementById('val-yony').textContent = (Math.random() * 15 - 5).toFixed(2) + '%';
            document.getElementById('val-ctoc').textContent = '+' + (Math.random() * 8 + 1).toFixed(2) + '%';

            // 4. Proses Menggambar Grafik Batang dengan Chart.js
            const ctx = document.getElementById('previewChart').getContext('2d');

            // Hancurkan grafik lama jika sudah ada (mencegah error tumpang tindih saat klik apply berulang)
            if (previewChartInstance) {
                previewChartInstance.destroy();
            }

            // Tentukan warna batang berdasarkan jenis Ekspor / Impor
            const bgColor = jenisData === 'Ekspor' ? 'rgba(37, 99, 235, 0.7)' : 'rgba(16, 185, 129, 0.7)';
            const bdColor = jenisData === 'Ekspor' ? 'rgba(37, 99, 235, 1)' : 'rgba(16, 185, 129, 1)';

            previewChartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
                    datasets: [{
                        label: `Nilai ${jenisData} Tahun ${tahunData} (Juta USD)`,
                        data: Array.from({length: 12}, () => Math.floor(Math.random() * 500) + 100), // Data simulasi
                        backgroundColor: bgColor,
                        borderColor: bdColor,
                        borderWidth: 1,
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false, // Penting agar grafik mengisi kotak container
                    plugins: {
                        legend: { position: 'top' },
                        title: {
                            display: true,
                            text: `Pratinjau Tren ${jenisData} - Simulasi Data`,
                            font: { size: 16 },
                            padding: { bottom: 20 }
                        }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        });

        btnResetDashboard.addEventListener('click', () => {
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
