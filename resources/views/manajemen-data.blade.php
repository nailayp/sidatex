@extends('layouts.app')

@section('title', 'Manajemen Data Terunggah | SIDATEX BPS Provinsi Riau')

@push('styles')
<style>
    .management-wrapper {
        min-height: calc(100vh - 120px);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    }

    .header-title-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #ffffff;
        padding: 1.25rem 1.75rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        border: 1px solid #e2e8f0;
    }

    .header-title-container h1 {
        font-size: 1.35rem;
        font-weight: 700;
        color: #0f172a;
        margin: 0;
        border-left: 4px solid #0284c7;
        padding-left: 1rem;
    }

    .header-title-container p {
        margin: 0.25rem 0 0 1rem;
        color: #64748b;
        font-size: 0.85rem;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1.25rem;
        margin-bottom: 1.5rem;
    }

    @media (max-width: 1024px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 640px) {
        .stats-grid { grid-template-columns: 1fr; }
    }

    .stat-card {
        background: #ffffff;
        border-radius: 12px;
        padding: 1.25rem 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        border: 1px solid #e2e8f0;
    }

    .stat-info { display: flex; flex-direction: column; }
    .stat-label { font-size: 0.82rem; color: #64748b; font-weight: 600; margin-bottom: 0.25rem; }
    .stat-value { font-size: 1.65rem; font-weight: 800; color: #0f172a; line-height: 1.1; }
    .stat-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; }

    /* Filter Card SiPantau Style */
    .filter-card-bar {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1.25rem 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    }

    .filter-form-grid {
        display: grid;
        grid-template-columns: 2fr 1.2fr 1fr auto;
        gap: 1rem;
        align-items: flex-end;
    }

    @media (max-width: 900px) {
        .filter-form-grid { grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 600px) {
        .filter-form-grid { grid-template-columns: 1fr; }
    }

    .form-group-custom {
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
    }

    .form-group-custom label {
        font-size: 0.78rem;
        font-weight: 700;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .form-input-custom,
    .form-select-custom {
        padding: 0.55rem 0.85rem;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: 0.88rem;
        color: #1e293b;
        background-color: #ffffff;
        outline: none;
        transition: border-color 0.15s;
    }

    .form-input-custom:focus,
    .form-select-custom:focus {
        border-color: #0284c7;
        box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.15);
    }

    .btn-submit-filter {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0 1.25rem;
        height: 38px;
        min-height: 38px;
        background: #0066cc;
        color: #ffffff;
        border: 1px solid #0052a3;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.15s ease;
        box-shadow: 0 1px 2px rgba(0, 102, 204, 0.2);
    }

    .btn-submit-filter:hover { background: #0052a3; }

    .btn-detail-link {
        display: inline-block;
        padding: 2px 7px;
        border-radius: 4px;
        background: #eff6ff;
        color: #1d4ed8;
        font-size: 0.75rem;
        font-weight: 600;
        border: 1px solid #bfdbfe;
        cursor: pointer;
        margin-left: 6px;
        vertical-align: middle;
        transition: all 0.15s;
    }
    .btn-detail-link:hover {
        background: #2563eb;
        color: #ffffff;
    }

    .btn-reset-filter {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0 1.1rem;
        height: 38px;
        min-height: 38px;
        background: #ffffff;
        color: #475569;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .btn-reset-filter:hover { background: #f1f5f9; border-color: #94a3b8; color: #0f172a; }

    /* Action bar di atas tabel */
    .table-action-bar {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-bottom: none;
        border-radius: 12px 12px 0 0;
        padding: 1rem 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .action-group-left {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .action-group-right {
        display: flex;
        align-items: center;
        gap: 0.6rem;
    }

    .btn-bps-export {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.5rem 0.9rem;
        border-radius: 7px;
        font-size: 0.82rem;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        border: 1px solid transparent;
        transition: all 0.15s ease;
    }

    .btn-export-excel { background: #ecfdf5; color: #065f46; border-color: #a7f3d0; }
    .btn-export-excel:hover { background: #10b981; color: #ffffff; }

    .btn-export-ppt { background: #fff7ed; color: #c2410c; border-color: #fed7aa; }
    .btn-export-ppt:hover { background: #f97316; color: #ffffff; }

    .btn-export-print { background: #f1f5f9; color: #334155; border-color: #cbd5e1; }
    .btn-export-print:hover { background: #334155; color: #ffffff; }

    /* Container Tabel */
    .table-container {
        background: #ffffff;
        border-radius: 0 0 12px 12px;
        padding: 0 1.5rem 1.5rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        border: 1px solid #e2e8f0;
        border-top: none;
        overflow-x: auto;
    }

    .custom-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
    }

    .custom-table th {
        background: #f8fafc;
        color: #475569;
        font-weight: 700;
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        padding: 1rem 0.85rem;
        border-top: 1px solid #e2e8f0;
        border-bottom: 1px solid #e2e8f0;
        white-space: nowrap;
    }

    .custom-table td {
        padding: 1rem 0.85rem;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
        font-size: 0.88rem;
    }

    .custom-table tbody tr:hover td {
        background: #f8fafc;
    }

    .checkbox-box {
        width: 17px;
        height: 17px;
        cursor: pointer;
        accent-color: #0284c7;
        vertical-align: middle;
    }

    .doc-info { display: flex; align-items: center; gap: 0.85rem; }
    .doc-icon-dbf {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        background: #f1f5f9;
        border: 1px solid #cbd5e1;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 0.7rem;
        color: #475569;
        flex-shrink: 0;
    }
    .doc-icon-excel {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        background: #ecfdf5;
        border: 1px solid #a7f3d0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 0.7rem;
        color: #059669;
        flex-shrink: 0;
    }

    .doc-text h4 { margin: 0; font-size: 0.92rem; font-weight: 700; color: #0f172a; }
    .doc-text p { margin: 0.2rem 0 0; font-size: 0.8rem; color: #64748b; }

    .badge-status {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.65rem;
        border-radius: 9999px;
        font-size: 0.74rem;
        font-weight: 700;
        letter-spacing: 0.02em;
    }
    .badge-status-public { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
    .badge-status-private { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
    .badge-status-draft { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }

    /* Action Cell */
    .action-cell { position: relative; text-align: center; }
    .action-buttons-inline {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
    }

    .btn-action-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 6px;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        color: #475569;
        cursor: pointer;
        transition: all 0.15s;
    }
    .btn-action-icon:hover {
        background: #f1f5f9;
        color: #0284c7;
        border-color: #cbd5e1;
    }

    .btn-action-comment {
        color: #0284c7;
        border-color: #bae6fd;
        background: #f0f9ff;
    }
    .btn-action-comment:hover {
        background: #0284c7;
        color: #ffffff;
    }

    .btn-action-dots {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        font-size: 1.1rem;
        font-weight: bold;
        cursor: pointer;
        color: #475569;
        padding: 0.25rem 0.6rem;
        line-height: 1;
        transition: all 0.15s;
    }
    .btn-action-dots:hover {
        background: #0284c7;
        color: #ffffff;
        border-color: #0284c7;
    }

    .dropdown-menu {
        display: none;
        position: absolute;
        right: 40px;
        top: 50%;
        transform: translateY(-50%);
        background: #ffffff;
        border-radius: 8px;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        border: 1px solid #e2e8f0;
        width: 190px;
        z-index: 100;
        overflow: hidden;
    }
    .dropdown-menu.show { display: block; }
    .dropdown-menu button, .dropdown-menu a {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        width: 100%;
        text-align: left;
        padding: 0.65rem 1rem;
        border: none;
        background: none;
        border-bottom: 1px solid #f1f5f9;
        cursor: pointer;
        color: #334155;
        font-weight: 600;
        font-size: 0.82rem;
        text-decoration: none;
        box-sizing: border-box;
        transition: background 0.15s;
    }
    .dropdown-menu button:hover, .dropdown-menu a:hover { background: #f8fafc; color: #0284c7; }
    .dropdown-menu button:last-child { border-bottom: none; }

    /* Modal Overlay Umum */
    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.45);
        backdrop-filter: blur(4px);
        z-index: 9999;
        align-items: center;
        justify-content: center;
    }

    .modal-card {
        background: #ffffff;
        border-radius: 14px;
        width: 90%;
        max-width: 560px;
        padding: 1.75rem;
        box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
        max-height: 90vh;
        display: flex;
        flex-direction: column;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1.25rem;
        border-bottom: 1px solid #e2e8f0;
        padding-bottom: 0.85rem;
    }

    .modal-header h3 {
        margin: 0;
        font-size: 1.15rem;
        color: #0f172a;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .modal-close-btn {
        background: none;
        border: none;
        font-size: 1.4rem;
        cursor: pointer;
        color: #94a3b8;
        line-height: 1;
        padding: 2px 6px;
    }
    .modal-close-btn:hover { color: #0f172a; }

    /* Timeline Komentar */
    .comments-timeline {
        flex: 1;
        overflow-y: auto;
        padding: 0.5rem 0;
        margin-bottom: 1rem;
        display: flex;
        flex-direction: column;
        gap: 0.85rem;
        max-height: 320px;
    }

    .comment-item {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 0.85rem 1rem;
    }

    .comment-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.4rem;
        font-size: 0.78rem;
    }

    .comment-author {
        font-weight: 700;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }

    .comment-badge-role {
        font-size: 0.68rem;
        padding: 1px 6px;
        border-radius: 4px;
        font-weight: 600;
        background: #e2e8f0;
        color: #475569;
    }
    .comment-badge-superadmin { background: #fee2e2; color: #991b1b; }
    .comment-badge-viewer { background: #dbeafe; color: #1e40af; }

    .comment-time {
        color: #64748b;
    }

    .comment-text {
        font-size: 0.85rem;
        color: #334155;
        line-height: 1.45;
        white-space: pre-wrap;
    }

    .comment-empty-state {
        text-align: center;
        padding: 2rem 1rem;
        color: #64748b;
        font-size: 0.85rem;
    }
</style>
@endpush

@section('content')
<div class="management-wrapper">

    @if(session('success'))
        <div style="background: #ecfdf5; border-left: 4px solid #10b981; color: #065f46; padding: 1rem 1.25rem; border-radius: 8px; margin-bottom: 1.25rem; font-weight: 600; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div style="background: #fef2f2; border-left: 4px solid #ef4444; color: #991b1b; padding: 1rem 1.25rem; border-radius: 8px; margin-bottom: 1.25rem; font-weight: 600; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
            {{ session('error') }}
        </div>
    @endif

    <!-- Header Judul -->
    <div class="header-title-container">
        <div>
            <h1>Manajemen Data Terunggah</h1>
            <p>Pengelolaan berkas dan batch data ekspor serta impor terdaftar dalam sistem SIDATEX BPS Provinsi Riau.</p>
        </div>
        <div>
            @if(Auth::user() && (Auth::user()->role_id === 1 || (Auth::user()->role && Auth::user()->role->nama_role === 'Super Admin')))
            <a href="{{ route('trash-data') }}" style="text-decoration: none; background: #ffffff; border: 1.5px solid #ef4444; color: #ef4444; padding: 0.55rem 1.1rem; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; transition: all 0.2s;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                Sampah Data ({{ $totalTrash ?? 0 }})
            </a>
            @endif
        </div>
    </div>

    <!-- 4 Kartu Statistik Atas -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-info">
                <span class="stat-label">Total File</span>
                <span class="stat-value">{{ number_format($totalFile, 0, ',', '.') }}</span>
            </div>
            <div class="stat-icon" style="background: #f0f9ff; color: #0284c7;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-info">
                <span class="stat-label">Terverifikasi (Public / Rilis)</span>
                <span class="stat-value">{{ number_format($terverifikasi, 0, ',', '.') }}</span>
            </div>
            <div class="stat-icon" style="background: #ecfdf5; color: #10b981;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-info">
                <span class="stat-label">Data Private / Draft</span>
                <span class="stat-value">{{ number_format($pending, 0, ',', '.') }}</span>
            </div>
            <div class="stat-icon" style="background: #f1f5f9; color: #64748b;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-info">
                <span class="stat-label">Total Ukuran Penyimpanan</span>
                <span class="stat-value">{{ $totalUkuranGB }}</span>
            </div>
            <div class="stat-icon" style="background: #fef3c7; color: #d97706;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
            </div>
        </div>
    </div>

    <!-- Filter Card Standard SiPantau -->
    <div class="filter-card-bar">
        <form method="GET" action="{{ route('manajemen-data') }}" class="filter-form-grid">
            <div class="form-group-custom">
                <label for="q">Pencarian Dokumen</label>
                <input type="text" id="q" name="q" value="{{ request('q') }}" class="form-input-custom" placeholder="Ketik nama berkas, nomor batch, atau jenis...">
            </div>

            <div class="form-group-custom">
                <label for="status">Status Verifikasi</label>
                <select id="status" name="status" class="form-select-custom">
                    <option value="ALL">-- Semua Status --</option>
                    <option value="RILIS" {{ request('status') === 'RILIS' ? 'selected' : '' }}>Rilis / Public</option>
                    <option value="PRIVATE" {{ request('status') === 'PRIVATE' ? 'selected' : '' }}>Private</option>
                    <option value="DRAFT" {{ request('status') === 'DRAFT' ? 'selected' : '' }}>Draft</option>
                </select>
            </div>

            <div class="form-group-custom">
                <label for="per_page">Data per Halaman</label>
                <select id="per_page" name="per_page" class="form-select-custom">
                    <option value="10" {{ request('per_page', 10) == 10 ? 'selected' : '' }}>10 data</option>
                    <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25 data</option>
                    <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50 data</option>
                    <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100 data</option>
                </select>
            </div>

            <div style="display: flex; gap: 0.5rem; align-items: flex-end;">
                <a href="{{ route('manajemen-data') }}" class="btn-reset-filter" title="Reset filter">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path></svg>
                    Reset Filter
                </a>
                <button type="submit" class="btn-submit-filter">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    Terapkan Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Bar Aksi Multi-Format Export & Seleksi -->
    <div class="table-action-bar">
        <div class="action-group-left">
            <label style="display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; font-weight: 600; color: #334155; cursor: pointer;">
                <input type="checkbox" id="selectAllCheckbox" class="checkbox-box" onchange="toggleSelectAll(this)">
                Pilih Semua
            </label>
            <span id="selectedCountBadge" style="font-size: 0.8rem; color: #64748b; background: #f1f5f9; padding: 0.2rem 0.6rem; border-radius: 9999px;">0 batch terpilih</span>
        </div>
        <div class="action-group-right">
            <span style="font-size: 0.8rem; font-weight: 600; color: #64748b; margin-right: 0.2rem;">Ekspor Terpilih:</span>
            <button type="button" class="btn-bps-export btn-export-excel" onclick="exportSelected('excel')" title="Ekspor transaksi terpilih ke Excel">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="9" x2="15" y2="15"></line><line x1="15" y1="9" x2="9" y2="15"></line></svg>
                Excel (.xlsx)
            </button>
            <button type="button" class="btn-bps-export btn-export-ppt" onclick="exportSelected('ppt')" title="Ekspor slide presentasi BRS PowerPoint">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
                PowerPoint (.pptx)
            </button>
            <button type="button" class="btn-bps-export btn-export-print" onclick="window.print()" title="Cetak / Simpan PDF Daftar Dokumen">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                Cetak / PDF
            </button>
        </div>
    </div>

    <!-- Tabel Daftar Data -->
    <div class="table-container">
        <table class="custom-table">
            <thead>
                <tr>
                    <th style="width: 40px; text-align: center;">No</th>
                    <th style="width: 35px; text-align: center;"></th>
                    <th>Nama Dokumen & Batch</th>
                    <th>Tanggal Unggah (WIB)</th>
                    <th>Ukuran Berkas</th>
                    <th>Status Rilis</th>
                    <th style="text-align: center; width: 140px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($dokumens as $index => $doc)
                @php
                    $docId = $doc->batch_id ?? $doc->id ?? $index;
                    $isDbf = str_ends_with(strtolower($doc->nama_file), '.dbf');
                @endphp
                <tr>
                    <td style="text-align: center; font-weight: 600; color: #64748b;">
                        {{ $dokumens->firstItem() + $index }}
                    </td>
                    <td style="text-align: center;">
                        <input type="checkbox" name="batch_ids[]" value="{{ $docId }}" class="checkbox-box batch-checkbox" onchange="updateSelectedCount()">
                    </td>
                    <td>
                        <div class="doc-info">
                            @if($isDbf)
                                <div class="doc-icon-dbf">DBF</div>
                            @else
                                <div class="doc-icon-excel">XLS</div>
                            @endif
                            <div class="doc-text">
                                <h4>
                                    @if(strlen($doc->nama_file) > 38)
                                        <span title="{{ $doc->nama_file }}">{{ \Illuminate\Support\Str::limit($doc->nama_file, 35) }}</span>
                                    @else
                                        <span>{{ $doc->nama_file }}</span>
                                    @endif
                                </h4>
                                <p>{{ $doc->jenis_data ?? 'Data Distribusi' }} &bull; Batch #{{ $doc->batch_id }} &bull; <strong style="color: #0284c7;">{{ number_format($doc->total_transaksi ?? 0, 0, ',', '.') }} Transaksi</strong></p>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div style="font-weight: 600; color: #334155;">{{ \Carbon\Carbon::parse($doc->created_at)->format('d F Y') }}</div>
                        <div style="font-size: 0.8rem; color: #64748b;">{{ \Carbon\Carbon::parse($doc->created_at)->format('H:i') }} WIB</div>
                    </td>
                    <td style="color: #475569; font-weight: 600;">{{ $doc->ukuran_file ?? 0 }} MB</td>
                    <td>
                        @if($doc->status == 'RILIS' || $doc->status == 'Public')
                            <span class="badge-status badge-status-public">Public</span>
                        @elseif($doc->status == 'PRIVATE')
                            <span class="badge-status badge-status-private">Private</span>
                        @else
                            <span class="badge-status badge-status-draft">Draft</span>
                        @endif
                    </td>
                    <td class="action-cell">
                        <div class="action-buttons-inline">
                            <!-- Tombol Cepat Lihat Detail Batch -->
                            <button type="button" class="btn-table-action btn-action-view" onclick="openBatchDetailModal('{{ addslashes($doc->nama_file) }}', '{{ $doc->batch_id }}', '{{ addslashes($doc->jenis_data ?? 'Data Distribusi') }}', '{{ $doc->tipe_sumber ?? 'distribusi' }}', '{{ $doc->periode_tahun }}-{{ sprintf('%02d', $doc->periode_bulan) }}', '{{ number_format($doc->total_transaksi ?? 0, 0, ',', '.') }}', '{{ $doc->ukuran_file ?? 0 }} MB', '{{ $doc->status }}', '{{ \Carbon\Carbon::parse($doc->created_at)->format('d F Y, H:i') }} WIB', '{{ $doc->released_at ? \Carbon\Carbon::parse($doc->released_at)->format('d F Y, H:i') . ' WIB' : 'Belum dirilis' }}')" title="Lihat Detail Metadata Batch">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                <span>Detail</span>
                            </button>

                            <!-- Tombol Cepat Catatan Verifikasi -->
                            <button type="button" class="btn-action-icon btn-action-comment" onclick="openCommentModal({{ $docId }}, '{{ addslashes($doc->nama_file) }}', '{{ $doc->status }}')" title="Catatan Verifikasi & Telaah Batch">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                                </svg>
                            </button>

                            <!-- Dropdown Titik Tiga -->
                            <button class="btn-action-dots" onclick="toggleMenu('menu-{{ $docId }}')" title="Pilihan aksi lanjutan">⋮</button>
                            <div class="dropdown-menu" id="menu-{{ $docId }}">
                                <button type="button" onclick="openCommentModal({{ $docId }}, '{{ addslashes($doc->nama_file) }}', '{{ $doc->status }}')">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                                    Catatan Telaah
                                </button>
                                <button type="button" onclick="downloadSingleExport({{ $docId }}, 'excel', this)">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="9" x2="15" y2="15"></line><line x1="15" y1="9" x2="9" y2="15"></line></svg>
                                    Ekspor Excel (.xlsx)
                                </button>
                                <button type="button" onclick="downloadSingleExport({{ $docId }}, 'ppt', this)">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
                                    Ekspor PowerPoint (.pptx)
                                </button>
                                <button type="button" onclick="openEditModal({{ $docId }}, '{{ addslashes($doc->nama_file) }}', '{{ $doc->jenis_data ?? 'Ekspor' }}', '{{ $doc->status }}')">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                                    Edit Dokumen
                                </button>
                                @if(Auth::user() && Auth::user()->role_id == 1)
                                <form action="{{ route('manajemen-data.toggle-release', $docId) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin {{ ($doc->status == 'RILIS' || $doc->status == 'Public') ? 'menarik status ke Private' : 'mempublikasikan data ini ke Public' }}?');" style="margin: 0; padding: 0;">
                                    @csrf
                                    <button type="submit" style="{{ ($doc->status == 'RILIS' || $doc->status == 'Public') ? 'color: #b45309;' : 'color: #065f46;' }}">
                                        @if($doc->status == 'RILIS' || $doc->status == 'Public')
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                                            Tarik ke Private
                                        @else
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
                                            Publish ke Public
                                        @endif
                                    </button>
                                </form>
                                <form action="{{ route('manajemen-data.destroy', $docId) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin memindahkan dokumen \'{{ addslashes($doc->nama_file) }}\' ke Trash? Data dapat dipulihkan kembali sewaktu-waktu.');" style="margin: 0; padding: 0;">
                                    @csrf
                                    <button type="submit" style="color: #ef4444;">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                        Hapus ke Trash
                                    </button>
                                </form>
                                @endif
                            </div>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align: center; padding: 3rem 1rem; color: #64748b;">
                        Tidak ada dokumen batch data yang sesuai dengan pencarian atau filter.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Navigasi Pagination Berangka Rapi & Indikator Record -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem; flex-wrap: wrap; gap: 1rem;">
            <div style="font-size: 0.82rem; color: #64748b;">
                @if($dokumens->total() > 0)
                    Menampilkan data <strong>{{ $dokumens->firstItem() }}</strong> &ndash; <strong>{{ $dokumens->lastItem() }}</strong> dari total <strong>{{ number_format($dokumens->total(), 0, ',', '.') }}</strong> batch
                @else
                    Menampilkan 0 data
                @endif
            </div>
            <div>
                {{ $dokumens->links('partials.pagination') }}
            </div>
        </div>
    </div>
</div>

<!-- Modal Catatan Verifikasi & Telaah Batch (Viewer Eksekutif & Super Admin) -->
<div id="modal-komentar" class="modal-overlay">
    <div class="modal-card">
        <div class="modal-header">
            <div>
                <h3>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0284c7" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                    Catatan Verifikasi &amp; Telaah
                </h3>
                <p id="comment_modal_subtitle" style="margin: 0.2rem 0 0; font-size: 0.8rem; color: #64748b;">Batch #... &bull; File ...</p>
            </div>
            <button type="button" class="modal-close-btn" onclick="closeCommentModal()">&times;</button>
        </div>

        <!-- Daftar Komentar / Timeline -->
        <div class="comments-timeline" id="commentsTimeline">
            <div class="comment-empty-state">Memuat catatan verifikasi...</div>
        </div>

        <!-- Input Komentar Baru (Super Admin & Viewer Eksekutif) -->
        @if(Auth::user() && in_array(Auth::user()->role_id, [1, 3]))
        <div style="border-top: 1px solid #e2e8f0; padding-top: 1rem;">
            <form id="form-add-comment" onsubmit="submitComment(event)">
                <input type="hidden" id="comment_batch_id" name="batch_id" value="">
                <div style="margin-bottom: 0.75rem;">
                    <label for="input_komentar" style="display: block; font-size: 0.8rem; font-weight: 700; color: #475569; margin-bottom: 0.35rem;">
                        Tambah Catatan Telaah / Arahan
                    </label>
                    <textarea id="input_komentar" name="komentar" rows="3" required placeholder="Tuliskan catatan hasil telaah, temuan anomali, atau arahan verifikasi..." style="width: 100%; box-sizing: border-box; padding: 0.6rem 0.85rem; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; outline: none; font-family: inherit; resize: vertical;"></textarea>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 0.6rem;">
                    <button type="button" onclick="closeCommentModal()" style="padding: 0.5rem 1rem; border: 1px solid #cbd5e1; background: #ffffff; color: #475569; border-radius: 6px; font-weight: 600; font-size: 0.82rem; cursor: pointer;">Tutup</button>
                    <button type="submit" id="btnSubmitComment" style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.5rem 1.2rem; border: none; background: #0284c7; color: white; border-radius: 6px; font-weight: 600; font-size: 0.82rem; cursor: pointer;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                        Kirim Catatan
                    </button>
                </div>
            </form>
        </div>
        @else
        <div style="border-top: 1px solid #e2e8f0; padding-top: 0.85rem; font-size: 0.8rem; color: #64748b; text-align: center;">
            Pegawai BPS memiliki akses baca terhadap catatan verifikasi pada data berstatus Rilis.
        </div>
        @endif
    </div>
</div>

<!-- Modal Edit Dokumen -->
<div id="modal-edit" class="modal-overlay">
    <div class="modal-card" style="max-width: 480px;">
        <div class="modal-header">
            <h3>Edit Dokumen Data</h3>
            <button type="button" class="modal-close-btn" onclick="closeEditModal()">&times;</button>
        </div>
        <form id="form-edit-batch" method="POST" action="">
            @csrf
            <div style="margin-bottom: 1.2rem;">
                <label style="display: block; font-weight: 600; color: #475569; margin-bottom: 0.4rem; font-size: 0.85rem;">Nama Dokumen / File</label>
                <input type="text" name="nama_file" id="edit_nama_file" required style="width: 100%; padding: 0.65rem 0.85rem; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 0.9rem; box-sizing: border-box; outline: none;">
            </div>
            <div style="margin-bottom: 1.2rem;">
                <label style="display: block; font-weight: 600; color: #475569; margin-bottom: 0.4rem; font-size: 0.85rem;">Jenis Perdagangan</label>
                <select name="jenis_data" id="edit_jenis_data" required style="width: 100%; padding: 0.65rem 0.85rem; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 0.9rem; box-sizing: border-box; background: white; outline: none;">
                    <option value="Ekspor">Ekspor</option>
                    <option value="Impor">Impor</option>
                </select>
            </div>
            <div style="margin-bottom: 1.5rem; padding: 0.75rem 1rem; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 0.85rem; color: #64748b;">Status Saat Ini:</span>
                    <span id="edit_status_info" style="font-weight: 700; font-size: 0.9rem;"></span>
                </div>
                <p style="margin: 0.3rem 0 0 0; font-size: 0.75rem; color: #94a3b8;">*Status Private tidak otomatis berubah menjadi Public saat melakukan pengeditan nama.</p>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" onclick="closeEditModal()" style="padding: 0.6rem 1.2rem; border: 1px solid #cbd5e1; background: #ffffff; color: #475569; border-radius: 6px; font-weight: 600; font-size: 0.85rem; cursor: pointer;">Batal</button>
                <button type="submit" style="padding: 0.6rem 1.4rem; border: none; background: #0284c7; color: white; border-radius: 6px; font-weight: 600; font-size: 0.85rem; cursor: pointer;">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Pop-up Detail Dokumen Batch (SiPantau Style) -->
<div id="modalBatchDetail" class="modal-overlay">
    <div class="modal-card" style="max-width: 520px;">
        <div class="modal-header">
            <h3>Detail Dokumen & Batch</h3>
            <button type="button" class="modal-close-btn" onclick="closeBatchDetailModal()">&times;</button>
        </div>
        <div style="display: flex; flex-direction: column; gap: 0.85rem; margin-top: 0.5rem; margin-bottom: 1.5rem;">
            <div>
                <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Nama Berkas Lengkap:</label>
                <div id="detailBatchFileName" style="font-size: 0.9rem; font-weight: 600; color: #0f172a; word-break: break-all; background: #f8fafc; padding: 0.6rem 0.75rem; border-radius: 6px; border: 1px solid #e2e8f0; margin-top: 0.25rem;"></div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Nomor Batch:</label>
                    <div id="detailBatchId" style="font-size: 0.88rem; font-weight: 600; color: #1e293b; background: #f8fafc; padding: 0.5rem 0.75rem; border-radius: 6px; border: 1px solid #e2e8f0; margin-top: 0.25rem;"></div>
                </div>
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Tipe Metodologi:</label>
                    <div id="detailBatchSource" style="font-size: 0.88rem; font-weight: 600; color: #1e293b; background: #f8fafc; padding: 0.5rem 0.75rem; border-radius: 6px; border: 1px solid #e2e8f0; margin-top: 0.25rem; text-transform: capitalize;"></div>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Periode Data:</label>
                    <div id="detailBatchPeriod" style="font-size: 0.88rem; font-weight: 600; color: #1e293b; background: #f8fafc; padding: 0.5rem 0.75rem; border-radius: 6px; border: 1px solid #e2e8f0; margin-top: 0.25rem;"></div>
                </div>
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Jumlah Transaksi:</label>
                    <div id="detailBatchRows" style="font-size: 0.88rem; font-weight: 700; color: #0284c7; background: #f0f9ff; padding: 0.5rem 0.75rem; border-radius: 6px; border: 1px solid #bae6fd; margin-top: 0.25rem;"></div>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Status Rilis:</label>
                    <div id="detailBatchStatus" style="font-size: 0.88rem; font-weight: 700; margin-top: 0.25rem;"></div>
                </div>
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Ukuran Berkas:</label>
                    <div id="detailBatchSize" style="font-size: 0.88rem; font-weight: 600; color: #1e293b; background: #f8fafc; padding: 0.5rem 0.75rem; border-radius: 6px; border: 1px solid #e2e8f0; margin-top: 0.25rem;"></div>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Waktu Unggah:</label>
                    <div id="detailBatchUploaded" style="font-size: 0.82rem; color: #475569; margin-top: 0.25rem;"></div>
                </div>
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Waktu Rilis:</label>
                    <div id="detailBatchReleased" style="font-size: 0.82rem; color: #475569; margin-top: 0.25rem;"></div>
                </div>
            </div>
        </div>
        <div style="display: flex; justify-content: flex-end;">
            <button type="button" onclick="closeBatchDetailModal()" style="padding: 0.55rem 1.25rem; border: 1px solid #cbd5e1; background: #ffffff; color: #475569; border-radius: 6px; font-weight: 600; font-size: 0.85rem; cursor: pointer;">Tutup</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function toggleMenu(menuId) {
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            if(menu.id !== menuId) menu.classList.remove('show');
        });
        const selectedMenu = document.getElementById(menuId);
        if (selectedMenu) selectedMenu.classList.toggle('show');
    }

    window.onclick = function(event) {
        if (!event.target.matches('.btn-action-dots')) {
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.classList.remove('show');
            });
        }
    }

    function toggleSelectAll(masterCheckbox) {
        const checkboxes = document.querySelectorAll('.batch-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = masterCheckbox.checked;
        });
        updateSelectedCount();
    }

    function updateSelectedCount() {
        const checked = document.querySelectorAll('.batch-checkbox:checked');
        const count = checked.length;
        document.getElementById('selectedCountBadge').textContent = count + ' batch terpilih';

        const allCheckboxes = document.querySelectorAll('.batch-checkbox');
        const master = document.getElementById('selectAllCheckbox');
        if (master) {
            master.checked = (allCheckboxes.length > 0 && checked.length === allCheckboxes.length);
        }
    }

    function getSelectedBatchIds() {
        const checked = document.querySelectorAll('.batch-checkbox:checked');
        return Array.from(checked).map(cb => cb.value);
    }

    function exportSelected(format) {
        const ids = getSelectedBatchIds();
        if (ids.length === 0) {
            alert('Silakan pilih minimal 1 batch data untuk diekspor.');
            return;
        }

        if (format === 'excel') {
            ids.forEach((id, index) => {
                setTimeout(() => {
                    window.location.href = `/manajemen-data/${id}/export`;
                }, index * 800);
            });
        } else if (format === 'ppt') {
            ids.forEach((id, index) => {
                setTimeout(() => {
                    window.location.href = `/export/powerpoint/${id}`;
                }, index * 800);
            });
        }
    }

    function downloadSingleExport(batchId, format, btnElement) {
        if (format === 'excel') {
            window.location.href = `/manajemen-data/${batchId}/export`;
        } else if (format === 'ppt') {
            window.location.href = `/export/powerpoint/${batchId}`;
        }
    }

    function openEditModal(batchId, namaFile, jenisData, status) {
        document.getElementById('form-edit-batch').action = `/manajemen-data/${batchId}/update`;
        document.getElementById('edit_nama_file').value = namaFile;
        document.getElementById('edit_jenis_data').value = jenisData;
        const isPublic = (status === 'RILIS' || status === 'Public');
        const statusLabel = isPublic ? 'Public' : 'Private';
        const statusColor = isPublic ? '#065f46' : '#475569';
        document.getElementById('edit_status_info').innerHTML = `<span style="color: ${statusColor};">${statusLabel}</span>`;
        document.getElementById('modal-edit').style.display = 'flex';
    }

    function closeEditModal() {
        document.getElementById('modal-edit').style.display = 'none';
    }

    // ==========================================
    // LOGIKA MODAL CATATAN VERIFIKASI (KOMENTAR)
    // ==========================================
    let activeCommentBatchId = null;

    function openCommentModal(batchId, fileName, status) {
        activeCommentBatchId = batchId;
        const sub = document.getElementById('comment_modal_subtitle');
        if (sub) {
            sub.textContent = `Batch #${batchId} • ${fileName} (${status})`;
        }
        const inputBatchId = document.getElementById('comment_batch_id');
        if (inputBatchId) {
            inputBatchId.value = batchId;
        }
        document.getElementById('modal-komentar').style.display = 'flex';
        fetchComments(batchId);
    }

    function closeCommentModal() {
        document.getElementById('modal-komentar').style.display = 'none';
        activeCommentBatchId = null;
    }

    function fetchComments(batchId) {
        const timeline = document.getElementById('commentsTimeline');
        timeline.innerHTML = '<div class="comment-empty-state">Memuat catatan verifikasi...</div>';

        fetch(`/comments/batch/${batchId}`)
            .then(res => res.json())
            .then(res => {
                if (!res.success) {
                    timeline.innerHTML = `<div class="comment-empty-state" style="color: #dc2626;">${res.message || 'Gagal memuat catatan.'}</div>`;
                    return;
                }

                if (!res.data || res.data.length === 0) {
                    timeline.innerHTML = `
                        <div class="comment-empty-state">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5" style="margin-bottom: 0.5rem;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                            <div>Belum ada catatan verifikasi pada batch ini.</div>
                        </div>
                    `;
                    return;
                }

                timeline.innerHTML = '';
                res.data.forEach(item => {
                    const el = document.createElement('div');
                    el.className = 'comment-item';

                    const roleBadgeClass = (item.nama_role === 'Super Admin')
                        ? 'comment-badge-superadmin'
                        : (item.nama_role === 'Viewer Eksekutif' ? 'comment-badge-viewer' : '');

                    const dateStr = item.created_at ? new Date(item.created_at).toLocaleString('id-ID', {
                        day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit'
                    }) + ' WIB' : '';

                    el.innerHTML = `
                        <div class="comment-meta">
                            <div class="comment-author">
                                <span>${item.user_name || 'Pengguna'}</span>
                                <span class="comment-badge-role ${roleBadgeClass}">${item.nama_role || 'Staff'}</span>
                            </div>
                            <div class="comment-time">${dateStr}</div>
                        </div>
                        <div class="comment-text">${escapeHtml(item.komentar)}</div>
                    `;
                    timeline.appendChild(el);
                });
            })
            .catch(err => {
                console.error(err);
                timeline.innerHTML = '<div class="comment-empty-state" style="color: #dc2626;">Terjadi kesalahan saat memuat catatan verifikasi.</div>';
            });
    }

    function submitComment(event) {
        event.preventDefault();
        const batchId = document.getElementById('comment_batch_id').value;
        const komentar = document.getElementById('input_komentar').value.trim();
        const btn = document.getElementById('btnSubmitComment');

        if (!komentar) {
            alert('Isi komentar tidak boleh kosong.');
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Mengirim...';

        const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        fetch('/comments', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                batch_id: batchId,
                komentar: komentar
            })
        })
        .then(res => res.json())
        .then(res => {
            btn.disabled = false;
            btn.innerHTML = `
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                Kirim Catatan
            `;

            if (!res.success) {
                alert(res.message || 'Gagal menambahkan catatan.');
                return;
            }

            document.getElementById('input_komentar').value = '';
            fetchComments(batchId);
        })
        .catch(err => {
            console.error(err);
            btn.disabled = false;
            btn.textContent = 'Kirim Catatan';
            alert('Terjadi kesalahan jaringan saat mengirim catatan.');
        });
    }

    window.openBatchDetailModal = function(fileName, batchId, jenisData, tipeSumber, periode, totalRows, ukuran, status, uploadedAt, releasedAt) {
        document.getElementById('detailBatchFileName').textContent = fileName || '—';
        document.getElementById('detailBatchId').textContent = 'Batch #' + batchId;
        document.getElementById('detailBatchSource').textContent = (tipeSumber || 'distribusi') + ' (' + (jenisData || 'Ekspor/Impor') + ')';
        document.getElementById('detailBatchPeriod').textContent = periode || '—';
        document.getElementById('detailBatchRows').textContent = totalRows + ' Baris Transaksi';
        document.getElementById('detailBatchSize').textContent = ukuran || '—';

        const statusEl = document.getElementById('detailBatchStatus');
        statusEl.textContent = status || 'DRAFT';
        if (status === 'RILIS' || status === 'Public') {
            statusEl.style.color = '#15803d';
        } else if (status === 'PRIVATE') {
            statusEl.style.color = '#475569';
        } else {
            statusEl.style.color = '#b45309';
        }

        document.getElementById('detailBatchUploaded').textContent = uploadedAt || '—';
        document.getElementById('detailBatchReleased').textContent = releasedAt || 'Belum dirilis';
        document.getElementById('modalBatchDetail').style.display = 'flex';
    };

    window.closeBatchDetailModal = function() {
        document.getElementById('modalBatchDetail').style.display = 'none';
    };

    function escapeHtml(text) {
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }
</script>
@endpush
