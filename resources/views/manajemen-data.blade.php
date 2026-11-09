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
    /* Container Utama */
    .management-wrapper {
        min-height: calc(100vh - 80px);
        padding: 1.25rem 1.5rem;
        position: relative;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    }

    /* Header Judul */
    .header-title-container {
        display: flex;
        align-items: center;
        gap: 1.25rem;
        background: #ffffff;
        padding: 1rem 1.5rem;
        border-radius: 10px;
        margin-bottom: 1.25rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        border: 1px solid #e2e8f0;
    }
    .header-title-container h1 {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1e293b;
        margin: 0;
        border-left: 3px solid #0284c7;
        padding-left: 1rem;
    }

    /* Grid Statistik */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1rem;
        margin-bottom: 1.25rem;
    }
    .stat-card {
        background: #ffffff;
        border-radius: 10px;
        padding: 1.1rem 1.25rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        border: 1px solid #e2e8f0;
    }
    .stat-info { display: flex; flex-direction: column; }
    .stat-label { font-size: 0.85rem; color: #64748b; font-weight: 600; margin-bottom: 0.2rem; }
    .stat-value { font-size: 1.8rem; font-weight: 800; color: #1e293b; line-height: 1.1; }
    .stat-icon { width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; background: #f0f9ff; color: #0284c7; }

    /* Container Tabel */
    .table-container {
        background: #ffffff;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        border: 1px solid #e2e8f0;
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
<div class="management-wrapper">

    <!-- Peringatan Sukses dari Redirect -->
    @if(session('success'))
        <div style="background: #10b981; color: white; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; text-align: center; font-weight: bold;">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div style="background: #ef4444; color: white; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; text-align: center; font-weight: bold;">
            {{ session('error') }}
        </div>
    @endif

    <!-- Header Judul -->
    <div class="header-title-container" style="display: flex; justify-content: space-between; align-items: center;">
        <div style="display: flex; align-items: center; gap: 1.5rem;">
            <h1>Manajemen Data Terunggah</h1>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <a href="{{ route('trash-data') }}" style="text-decoration: none; background: #ffffff; border: 1.5px solid #ef4444; color: #ef4444; padding: 0.55rem 1.1rem; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; box-shadow: 0 2px 8px rgba(239, 68, 68, 0.12); font-size: 0.9rem; transition: all 0.2s;">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                Trash Dokumen ({{ $totalTrash ?? 0 }})
            </a>
        </div>
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
                <span class="stat-label">Data Private</span>
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
                @php $docId = $doc->batch_id ?? $doc->id ?? $index; @endphp
                <tr>
                    <td><input type="radio" name="select_row" value="{{ $docId }}"></td>
                    <td>
                        <div class="doc-info">
                            <svg class="excel-logo" viewBox="0 0 24 24" fill="none">
                                <rect width="24" height="24" rx="4" fill="#107C41"/>
                                <path d="M15.5 17H13.5L12 12.5L10.5 17H8.5L11 11L8.5 5H10.5L12 9.5L13.5 5H15.5L13 11L15.5 17Z" fill="white"/>
                            </svg>
                            <div class="doc-text">
                                <h4>{{ $doc->nama_file }}</h4> <!-- Sesuai nama kolom di DB -->
                                <p>{{ $doc->jenis_data ?? 'SpreadSheet' }} &bull; <strong style="color: #2563eb;">{{ number_format($doc->total_transaksi ?? 0, 0, ',', '.') }} Transaksi</strong></p>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="date-time">
                            <div class="date">{{ \Carbon\Carbon::parse($doc->created_at)->format('d F Y') }}</div>
                            <div class="time">{{ \Carbon\Carbon::parse($doc->created_at)->format('H:i') }}</div>
                        </div>
                    </td>
                    <td style="color: #475569; font-weight: 500;">{{ $doc->ukuran_file ?? 0 }} MB</td>
                    <td style="color: #475569; font-weight: 500;">
                        @if($doc->status == 'RILIS' || $doc->status == 'Public')
                            <span style="color: #10b981; font-weight: 600; background: #ecfdf5; padding: 4px 8px; border-radius: 6px; font-size: 0.85rem;">Public / Rilis</span>
                        @elseif($doc->status == 'PRIVATE')
                            <span style="color: #6366f1; font-weight: 600; background: #eef2ff; padding: 4px 8px; border-radius: 6px; font-size: 0.85rem;">Private</span>
                        @else
                            <span style="color: #f59e0b; font-weight: 600; background: #fffbeb; padding: 4px 8px; border-radius: 6px; font-size: 0.85rem;">Draft</span>
                        @endif
                    </td>
                    <td class="action-cell">
                        <button class="btn-action-dots" onclick="toggleMenu('menu-{{ $docId }}')">⋮</button>
                        <div class="dropdown-menu" id="menu-{{ $docId }}">
                            <button type="button" class="btn-export-item" onclick="downloadExport({{ $docId }}, this)" style="color: #2563eb; font-weight: 600;">
                                <span class="btn-text">Ekspor Data</span>
                            </button>
                            <button type="button" onclick="openEditModal({{ $docId }}, '{{ addslashes($doc->nama_file) }}', '{{ $doc->jenis_data ?? 'Ekspor' }}', '{{ $doc->status }}')">Edit Data</button>
                            @if(Auth::user() && Auth::user()->role_id == 1)
                            <form action="{{ route('manajemen-data.destroy', $docId) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin memindahkan dokumen \'{{ addslashes($doc->nama_file) }}\' ke Trash? Data dapat dipulihkan kembali dari Trash.');" style="margin: 0; padding: 0; width: 100%;">
                                @csrf
                                <button type="submit" style="color: #ef4444;">Hapus Data</button>
                            </form>
                            <form action="{{ route('manajemen-data.toggle-release', $docId) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin {{ ($doc->status == 'RILIS' || $doc->status == 'Public') ? 'menarik status ke Private' : 'mempublikasikan data ini ke Public' }}?');" style="margin: 0; padding: 0; width: 100%;">
                                @csrf
                                <button type="submit" style="{{ ($doc->status == 'RILIS' || $doc->status == 'Public') ? 'color: #dc2626; font-weight: 600;' : 'color: #16a34a; font-weight: 600;' }}">
                                    {{ ($doc->status == 'RILIS' || $doc->status == 'Public') ? 'Tarik ke Private' : 'Publish ke Public' }}
                                </button>
                            </form>
                            @endif
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
            {{ $dokumens->links('pagination::bootstrap-4') }}
        </div>
    </div>
</div>

<!-- Modal Edit Dokumen -->
<div id="modal-edit" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.5); backdrop-filter: blur(5px); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 16px; width: 90%; max-width: 500px; padding: 2rem; box-shadow: 0 20px 40px rgba(0,0,0,0.2); animation: fadeIn 0.2s ease;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 1rem;">
            <h3 style="margin: 0; font-size: 1.3rem; color: #1e293b; font-weight: 700;">Edit Dokumen Data</h3>
            <button type="button" onclick="closeEditModal()" style="background: none; border: none; font-size: 1.6rem; cursor: pointer; color: #94a3b8; line-height: 1;">&times;</button>
        </div>
        <form id="form-edit-batch" method="POST" action="">
            @csrf
            <div style="margin-bottom: 1.2rem;">
                <label style="display: block; font-weight: 600; color: #475569; margin-bottom: 0.5rem; font-size: 0.9rem;">Nama Dokumen / File</label>
                <input type="text" name="nama_file" id="edit_nama_file" required style="width: 100%; padding: 0.75rem 1rem; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem; box-sizing: border-box; outline: none; transition: border 0.2s;">
            </div>
            <div style="margin-bottom: 1.2rem;">
                <label style="display: block; font-weight: 600; color: #475569; margin-bottom: 0.5rem; font-size: 0.9rem;">Jenis Perdagangan</label>
                <select name="jenis_data" id="edit_jenis_data" required style="width: 100%; padding: 0.75rem 1rem; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem; box-sizing: border-box; background: white; outline: none;">
                    <option value="Ekspor">Ekspor</option>
                    <option value="Impor">Impor</option>
                </select>
            </div>
            <div style="margin-bottom: 1.5rem; padding: 0.8rem 1rem; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 0.85rem; color: #64748b;">Status Saat Ini:</span>
                    <span id="edit_status_info" style="font-weight: 700; font-size: 0.95rem;"></span>
                </div>
                <p style="margin: 0.4rem 0 0 0; font-size: 0.8rem; color: #94a3b8;">*Status Pending tidak akan berubah menjadi Public saat melakukan pengeditan.</p>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 0.8rem;">
                <button type="button" onclick="closeEditModal()" style="padding: 0.7rem 1.4rem; border: 1px solid #cbd5e1; background: #f1f5f9; color: #475569; border-radius: 8px; font-weight: 600; cursor: pointer;">Batal</button>
                <button type="submit" style="padding: 0.7rem 1.6rem; border: none; background: #2563eb; color: white; border-radius: 8px; font-weight: 600; cursor: pointer;">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Trash / Sampah -->
<div id="modal-trash" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.5); backdrop-filter: blur(5px); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 16px; width: 90%; max-width: 720px; max-height: 85vh; overflow-y: auto; padding: 2rem; box-shadow: 0 20px 40px rgba(0,0,0,0.2);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 1rem;">
            <div>
                <h3 style="margin: 0; font-size: 1.3rem; color: #1e293b; font-weight: 700;">Trash / Sampah Dokumen</h3>
                <p style="margin: 0.3rem 0 0 0; font-size: 0.85rem; color: #64748b;">Dokumen yang dihapus ke Trash dapat dipulihkan sewaktu-waktu.</p>
            </div>
            <button type="button" onclick="closeTrashModal()" style="background: none; border: none; font-size: 1.6rem; cursor: pointer; color: #94a3b8; line-height: 1;">&times;</button>
        </div>

        @if(count($trashedItems ?? []) === 0)
            <div style="text-align: center; color: #64748b; padding: 3rem 1rem;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5" style="margin-bottom: 1rem;">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>
                <p style="margin: 0; font-weight: 500;">Trash kosong. Tidak ada dokumen yang terhapus.</p>
            </div>
        @else
            <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                <thead>
                    <tr style="border-bottom: 2px solid #e2e8f0; text-align: left; color: #475569;">
                        <th style="padding: 0.7rem 0.5rem;">Nama Dokumen</th>
                        <th style="padding: 0.7rem 0.5rem;">Dihapus Oleh</th>
                        <th style="padding: 0.7rem 0.5rem;">Waktu Dihapus</th>
                        <th style="padding: 0.7rem 0.5rem; text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($trashedItems as $item)
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 0.8rem 0.5rem;">
                            <strong style="color: #1e293b;">{{ $item->nama_file }}</strong>
                            <div style="font-size: 0.8rem; color: #64748b;">{{ $item->jenis_data }} &bull; {{ $item->ukuran_file ?? 0 }} MB</div>
                        </td>
                        <td style="padding: 0.8rem 0.5rem; color: #334155;">{{ $item->deleter_name ?? 'Super Admin' }}</td>
                        <td style="padding: 0.8rem 0.5rem; color: #64748b;">{{ \Carbon\Carbon::parse($item->deleted_at)->format('d M Y H:i') }}</td>
                        <td style="padding: 0.8rem 0.5rem; text-align: center;">
                            <div style="display: flex; gap: 0.4rem; justify-content: center;">
                                <form action="{{ route('manajemen-data.restore', $item->batch_id) }}" method="POST" onsubmit="return confirm('Pulihkan dokumen ini kembali ke daftar aktif?');" style="margin: 0;">
                                    @csrf
                                    <button type="submit" style="background: #10b981; color: white; border: none; padding: 0.45rem 0.85rem; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 0.8rem;">Pulihkan</button>
                                </form>
                                <form action="{{ route('manajemen-data.force-delete', $item->batch_id) }}" method="POST" onsubmit="return confirm('PERINGATAN: Apakah Anda yakin ingin menghapus dokumen ini secara PERMANEN? Data dan seluruh transaksi terkait akan dihapus selamanya!');" style="margin: 0;">
                                    @csrf
                                    <button type="submit" style="background: #ef4444; color: white; border: none; padding: 0.45rem 0.85rem; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 0.8rem;">Hapus Permanen</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        <div style="margin-top: 1.5rem; text-align: right;">
            <button type="button" onclick="closeTrashModal()" style="padding: 0.6rem 1.4rem; border: 1px solid #cbd5e1; background: #f1f5f9; color: #475569; border-radius: 8px; font-weight: 600; cursor: pointer;">Tutup</button>
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

    // Modal Edit Dokumen
    function openEditModal(batchId, namaFile, jenisData, status) {
        document.getElementById('form-edit-batch').action = `/manajemen-data/${batchId}/update`;
        document.getElementById('edit_nama_file').value = namaFile;
        document.getElementById('edit_jenis_data').value = jenisData;
        const isPublic = (status === 'RILIS' || status === 'Public');
        const statusLabel = isPublic ? 'Public' : 'Pending';
        const statusColor = isPublic ? '#10b981' : '#f59e0b';
        document.getElementById('edit_status_info').innerHTML = `<span style="color: ${statusColor};">${statusLabel}</span>`;
        document.getElementById('modal-edit').style.display = 'flex';
    }

    function closeEditModal() {
        document.getElementById('modal-edit').style.display = 'none';
    }

    // Modal Trash
    function openTrashModal() {
        document.getElementById('modal-trash').style.display = 'flex';
    }

    function closeTrashModal() {
        document.getElementById('modal-trash').style.display = 'none';
    }

    // Fungsi Download Ekspor Non-blocking (Indikator loading hanya pada tombol dan tidak mengunci halaman)
    async function downloadExport(batchId, btnElement) {
        if (!btnElement || btnElement.disabled) return;

        const originalHtml = btnElement.innerHTML;
        btnElement.disabled = true;
        btnElement.style.opacity = '0.7';
        btnElement.style.cursor = 'wait';
        btnElement.innerHTML = `
            <span style="display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem; color: #2563eb;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation: spin 1s linear infinite;">
                    <circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle>
                    <path d="M12 2a10 10 0 0 1 10 10" stroke-opacity="0.75"></path>
                </svg>
                Mengunduh...
            </span>
        `;

        try {
            const exportUrl = `/manajemen-data/${batchId}/export`;
            const response = await fetch(exportUrl, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                let errText = 'Gagal mengunduh file ekspor (Status: ' + response.status + ').';
                try {
                    const errJson = await response.json();
                    if (errJson.message) errText = errJson.message;
                } catch(e) {}
                alert(errText);
                return;
            }

            // Dapatkan nama file dari header Content-Disposition jika ada
            const disposition = response.headers.get('content-disposition');
            let filename = `SIDATEX_Ekspor_Batch_${batchId}.xlsx`;
            if (disposition && disposition.indexOf('filename=') !== -1) {
                const matches = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/.exec(disposition);
                if (matches != null && matches[1]) {
                    filename = matches[1].replace(/['"]/g, '');
                }
            }

            const blob = await response.blob();
            const downloadUrl = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = downloadUrl;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(downloadUrl);

            // Beri tanda sukses sejenak
            btnElement.innerHTML = `<span style="color: #10b981; font-weight: 600;">✓ Selesai</span>`;
            setTimeout(() => {
                btnElement.innerHTML = originalHtml;
                btnElement.disabled = false;
                btnElement.style.opacity = '1';
                btnElement.style.cursor = 'pointer';
            }, 2000);

        } catch (err) {
            console.error('Error saat download file ekspor:', err);
            alert('Terjadi kesalahan jaringan atau server saat mengunduh data: ' + err.message);
            btnElement.innerHTML = originalHtml;
            btnElement.disabled = false;
            btnElement.style.opacity = '1';
            btnElement.style.cursor = 'pointer';
        }
    }
</script>
<style>
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>
@endpush
