@extends('layouts.app')

@section('title', 'Sampah Data | SIDATEX BPS Provinsi Riau')

@push('styles')
<style>
    .trash-container {
        min-height: calc(100vh - 120px);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    }

    .trash-header-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1.25rem 1.75rem;
        margin-bottom: 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .trash-header-title-box {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .trash-icon-badge {
        width: 44px;
        height: 44px;
        background: #fee2e2;
        color: #ef4444;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
    }

    .trash-header-title-box h1 {
        font-size: 1.4rem;
        font-weight: 700;
        color: #0f172a;
        margin: 0;
    }

    .trash-header-title-box p {
        margin: 0.2rem 0 0;
        color: #64748b;
        font-size: 0.85rem;
    }

    .btn-bps-outline {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: #ffffff;
        border: 1.5px solid #cbd5e1;
        color: #334155;
        padding: 0.6rem 1.2rem;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s;
    }

    .btn-bps-outline:hover {
        background: #f8fafc;
        border-color: #94a3b8;
        color: #0f172a;
    }

    .trash-table-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        overflow: hidden;
    }

    .trash-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
    }

    .trash-table th {
        background: #f8fafc;
        color: #475569;
        font-weight: 700;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 1rem 1.25rem;
        border-bottom: 1px solid #e2e8f0;
    }

    .trash-table td {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
        font-size: 0.9rem;
        vertical-align: middle;
    }

    .trash-table tr:hover td {
        background: #f8fafc;
    }

    .trash-doc-title {
        font-weight: 600;
        color: #0f172a;
        margin-bottom: 0.2rem;
    }

    .trash-doc-sub {
        font-size: 0.8rem;
        color: #64748b;
    }

    .badge-status-pill {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.65rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 700;
    }

    .badge-status-public {
        background: #ecfdf5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }

    .badge-status-private {
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #cbd5e1;
    }

    .btn-action-restore {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        background: #0284c7;
        color: #ffffff;
        border: none;
        padding: 0.45rem 0.85rem;
        border-radius: 6px;
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s;
    }

    .btn-action-restore:hover {
        background: #0369a1;
    }

    .btn-action-delete {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        background: #fee2e2;
        color: #b91c1c;
        border: 1px solid #fecaca;
        padding: 0.45rem 0.85rem;
        border-radius: 6px;
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-action-delete:hover {
        background: #ef4444;
        color: #ffffff;
        border-color: #ef4444;
    }

    /* Modal Konfirmasi */
    .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.45);
        backdrop-filter: blur(4px);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    }

    .modal-content-card {
        background: #ffffff;
        border-radius: 12px;
        width: 90%;
        max-width: 480px;
        padding: 1.75rem;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }
</style>
@endpush

@section('content')
<div class="trash-container">

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

    <div class="trash-header-card">
        <div class="trash-header-title-box">
            <div class="trash-icon-badge">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>
            </div>
            <div>
                <h1>Sampah Data Dokumen</h1>
                <p>Dokumen batch yang dihapus sementara dapat dipulihkan kembali ke Manajemen Data atau dihapus permanen.</p>
            </div>
        </div>
        <div>
            <a href="{{ route('manajemen-data') }}" class="btn-bps-outline">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                Kembali ke Manajemen Data
            </a>
        </div>
    </div>

    <!-- Filter & Kontrol Halaman Trash -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem 1.5rem; margin-bottom: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <form method="GET" action="{{ route('trash-data') }}" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 200px;">
                <label for="q" style="display: block; font-size: 0.78rem; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 0.35rem;">Pencarian Trash</label>
                <input type="text" id="q" name="q" value="{{ request('q') }}" placeholder="Ketik nama berkas, nomor batch..." style="width: 100%; padding: 0.55rem 0.85rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; box-sizing: border-box;">
            </div>
            <div style="width: 150px;">
                <label for="per_page" style="display: block; font-size: 0.78rem; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 0.35rem;">Data per Halaman</label>
                <select id="per_page" name="per_page" style="width: 100%; padding: 0.55rem 0.85rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; background: #fff; box-sizing: border-box;">
                    <option value="10" {{ request('per_page', 10) == 10 ? 'selected' : '' }}>10 data</option>
                    <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25 data</option>
                    <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50 data</option>
                    <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100 data</option>
                </select>
            </div>
            <div style="display: flex; gap: 0.5rem; align-items: flex-end;">
                <a href="{{ route('trash-data') }}" style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0 1rem; height: 38px; min-height: 38px; background: #ffffff; color: #475569; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.85rem; font-weight: 600; text-decoration: none;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path></svg>
                    Reset Filter
                </a>
                <button type="submit" style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0 1.25rem; height: 38px; min-height: 38px; background: #0066cc; color: #ffffff; border: 1px solid #0052a3; border-radius: 8px; font-size: 0.85rem; font-weight: 700; cursor: pointer; box-shadow: 0 1px 2px rgba(0, 102, 204, 0.2);">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    Terapkan Filter
                </button>
            </div>
        </form>
    </div>

    <div class="trash-table-card">
        @if($totalTrash === 0)
            <div style="text-align: center; padding: 4rem 1.5rem; color: #64748b;">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5" style="margin-bottom: 1rem;">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>
                <h3 style="margin: 0; font-size: 1.15rem; color: #1e293b; font-weight: 700;">Trash Kosong</h3>
                <p style="margin: 0.4rem 0 0; font-size: 0.875rem;">Tidak ada dokumen batch data yang sedang berada di tempat sampah.</p>
            </div>
        @else
            <table class="trash-table">
                <thead>
                    <tr>
                        <th style="width: 50px; text-align: center;">No</th>
                        <th>Nama Dokumen / Batch</th>
                        <th>Dihapus Oleh</th>
                        <th>Waktu Penghapusan</th>
                        <th>Ukuran</th>
                        <th>Status Asal</th>
                        <th style="text-align: center; width: 220px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($trashedItems as $idx => $item)
                    <tr>
                        <td style="text-align: center; font-weight: 600; color: #64748b;">{{ $trashedItems->firstItem() + $idx }}</td>
                        <td>
                            <div class="trash-doc-title">{{ $item->nama_file }}</div>
                            <div class="trash-doc-sub">{{ $item->jenis_data ?? 'Batch' }} &bull; Batch #{{ $item->batch_id }}</div>
                        </td>
                        <td style="font-weight: 600; color: #334155;">
                            {{ $item->deleter_name ?? 'Super Admin' }}
                        </td>
                        <td>
                            <div style="font-weight: 600; color: #334155;">{{ \Carbon\Carbon::parse($item->deleted_at)->format('d F Y') }}</div>
                            <div style="font-size: 0.8rem; color: #64748b;">{{ \Carbon\Carbon::parse($item->deleted_at)->format('H:i') }} WIB</div>
                        </td>
                        <td style="font-weight: 600; color: #475569;">
                            {{ $item->ukuran_file ?? 0 }} MB
                        </td>
                        <td>
                            @if($item->batch_status === 'RILIS')
                                <span class="badge-status-pill badge-status-public">Public</span>
                            @else
                                <span class="badge-status-pill badge-status-private">Private</span>
                            @endif
                        </td>
                        <td style="text-align: center;">
                            <div style="display: inline-flex; gap: 8px;">
                                <form action="{{ route('manajemen-data.restore', $item->batch_id) }}" method="POST" onsubmit="return confirm('Pulihkan dokumen \'{{ addslashes($item->nama_file) }}\' kembali ke Manajemen Data?');" style="margin: 0;">
                                    @csrf
                                    <button type="submit" class="btn-action-restore">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg>
                                        Pulihkan
                                    </button>
                                </form>
                                <form action="{{ route('manajemen-data.force-delete', $item->batch_id) }}" method="POST" onsubmit="return confirm('PERINGATAN PERMANEN:\nApakah Anda yakin ingin menghapus permanen \'{{ addslashes($item->nama_file) }}\'?\nSeluruh data transaksi terkait di database akan dihapus selamanya!');" style="margin: 0;">
                                    @csrf
                                    <button type="submit" class="btn-action-delete">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                        Hapus Permanen
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <!-- Pagination Bernomor SiPantau Style -->
            <div style="padding: 1rem 1.5rem; border-top: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div style="font-size: 0.85rem; color: #64748b;">
                    Menampilkan data <strong>{{ $trashedItems->firstItem() ?? 0 }}</strong> &ndash; <strong>{{ $trashedItems->lastItem() ?? 0 }}</strong> dari total <strong>{{ $trashedItems->total() }}</strong> sampah dokumen
                </div>
                <div>
                    {{ $trashedItems->links('partials.pagination') }}
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
