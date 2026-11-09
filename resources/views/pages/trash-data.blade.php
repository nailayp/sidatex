@extends('layouts.app')

@section('title', 'Sampah / Trash Dokumen | SIDATEX BPS Provinsi Riau')

@push('styles')
<style>
    /* Styling Selaras dengan Manajemen Data */
    .trash-wrapper {
        min-height: calc(100vh - 80px);
        padding: 1.5rem 2rem;
        position: relative;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    }

    .trash-header-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #ffffff;
        padding: 1.25rem 2rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        border: 1px solid #e2e8f0;
    }

    .trash-header-left {
        display: flex;
        align-items: center;
        gap: 1.25rem;
    }

    .trash-header-left h1 {
        font-size: 1.6rem;
        font-weight: 700;
        color: #1e293b;
        margin: 0;
        border-left: 3px solid #ef4444;
        padding-left: 1.25rem;
    }

    .trash-table-container {
        background: #ffffff;
        border-radius: 16px;
        padding: 1.75rem;
        box-shadow: 0 4px 15px rgba(0,0,0,0.04);
        border: 1px solid #e2e8f0;
    }

    .trash-custom-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 10px;
    }

    .trash-custom-table th {
        text-align: left;
        padding: 0 1rem 0.85rem 1rem;
        color: #475569;
        font-weight: 700;
        font-size: 0.95rem;
        border-bottom: 2px solid #e2e8f0;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .trash-custom-table td {
        background: #f8fafc;
        padding: 1rem 1.2rem;
        vertical-align: middle;
        font-size: 0.9rem;
        border-top: 1px solid #f1f5f9;
        border-bottom: 1px solid #f1f5f9;
    }

    .trash-custom-table td:first-child {
        border-top-left-radius: 10px;
        border-bottom-left-radius: 10px;
        border-left: 1px solid #f1f5f9;
    }

    .trash-custom-table td:last-child {
        border-top-right-radius: 10px;
        border-bottom-right-radius: 10px;
        border-right: 1px solid #f1f5f9;
    }

    .trash-custom-table tbody tr {
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }

    .trash-custom-table tbody tr:hover td {
        background: #ffffff;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }

    .trash-doc-info {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .trash-excel-logo {
        width: 38px;
        height: 38px;
        flex-shrink: 0;
    }

    .trash-doc-text h4 {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
        color: #1e293b;
    }

    .trash-doc-text p {
        margin: 3px 0 0 0;
        font-size: 0.8rem;
        color: #64748b;
    }

    .trash-date-time {
        line-height: 1.35;
    }

    .trash-date-time .date {
        font-weight: 600;
        color: #334155;
    }

    .trash-date-time .time {
        font-size: 0.8rem;
        color: #64748b;
    }

    .btn-table-action {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 7px;
        font-size: 0.82rem;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        border: none;
        transition: all 0.15s ease;
    }

    .btn-restore {
        background: #10b981;
        color: white;
    }
    .btn-restore:hover {
        background: #059669;
    }

    .btn-permanent-delete {
        background: #ef4444;
        color: white;
    }
    .btn-permanent-delete:hover {
        background: #dc2626;
    }

    .btn-back {
        background: #f1f5f9;
        border: 1px solid #cbd5e1;
        color: #475569;
        padding: 0.55rem 1.1rem;
        border-radius: 8px;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.85rem;
    }
    .btn-back:hover {
        background: #e2e8f0;
    }
</style>
@endpush

@section('content')
<div class="trash-wrapper">

    <!-- Flash Alert -->
    @if(session('success'))
        <div style="background: #10b981; color: white; padding: 0.85rem 1.25rem; border-radius: 8px; margin-bottom: 1.25rem; font-weight: 600; font-size: 0.9rem;">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div style="background: #ef4444; color: white; padding: 0.85rem 1.25rem; border-radius: 8px; margin-bottom: 1.25rem; font-weight: 600; font-size: 0.9rem;">
            {{ session('error') }}
        </div>
    @endif

    <!-- Header Judul -->
    <div class="trash-header-container">
        <div class="trash-header-left">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2">
                <polyline points="3 6 5 6 21 6"></polyline>
                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
            </svg>
            <div>
                <h1>Trash / Sampah Dokumen</h1>
                <p style="margin: 3px 0 0 1.25rem; color: #64748b; font-size: 0.85rem;">Dokumen yang dihapus sementara dapat dipulihkan atau dihapus permanen.</p>
            </div>
        </div>
        <div>
            <a href="{{ route('manajemen-data') }}" class="btn-back">
                &larr; Kembali ke Manajemen Data
            </a>
        </div>
    </div>

    <!-- Tabel Daftar Trash -->
    <div class="trash-table-container">
        @if($totalTrash === 0)
            <div style="text-align: center; padding: 3.5rem 1rem; color: #64748b;">
                <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5" style="margin-bottom: 1rem;">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>
                <h3 style="margin: 0; font-size: 1.15rem; color: #334155; font-weight: 700;">Trash Kosong</h3>
                <p style="margin: 0.5rem 0 0; font-size: 0.85rem;">Tidak ada dokumen batch yang sedang berada di tempat sampah.</p>
            </div>
        @else
            <table class="trash-custom-table">
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;">No</th>
                        <th>Nama Dokumen / Data</th>
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
                        <td style="text-align: center; font-weight: 700; color: #64748b;">{{ $idx + 1 }}</td>
                        <td>
                            <div class="trash-doc-info">
                                <svg class="trash-excel-logo" viewBox="0 0 24 24" fill="none">
                                    <rect width="24" height="24" rx="4" fill="#64748b"/>
                                    <path d="M15.5 17H13.5L12 12.5L10.5 17H8.5L11 11L8.5 5H10.5L12 9.5L13.5 5H15.5L13 11L15.5 17Z" fill="white"/>
                                </svg>
                                <div class="trash-doc-text">
                                    <h4>{{ $item->nama_file }}</h4>
                                    <p>{{ $item->jenis_data ?? 'Spreadsheet' }} &bull; Di-soft delete</p>
                                </div>
                            </div>
                        </td>
                        <td style="color: #334155; font-weight: 600;">
                            {{ $item->deleter_name ?? 'Super Admin' }}
                        </td>
                        <td>
                            <div class="trash-date-time">
                                <div class="date">{{ \Carbon\Carbon::parse($item->deleted_at)->format('d F Y') }}</div>
                                <div class="time">{{ \Carbon\Carbon::parse($item->deleted_at)->format('H:i') }} WIB</div>
                            </div>
                        </td>
                        <td style="color: #475569; font-weight: 600;">
                            {{ $item->ukuran_file ?? 0 }} MB
                        </td>
                        <td>
                            @if($item->batch_status === 'RILIS')
                                <span style="background: #dcfce7; color: #15803d; padding: 3px 8px; border-radius: 6px; font-weight: 700; font-size: 0.78rem;">Public</span>
                            @else
                                <span style="background: #fef9c3; color: #a16207; padding: 3px 8px; border-radius: 6px; font-weight: 700; font-size: 0.78rem;">Pending</span>
                            @endif
                        </td>
                        <td style="text-align: center;">
                            <div style="display: inline-flex; gap: 8px;">
                                <form action="{{ route('manajemen-data.restore', $item->batch_id) }}" method="POST" onsubmit="return confirm('Pulihkan dokumen \'{{ addslashes($item->nama_file) }}\' kembali ke Manajemen Data?');" style="margin: 0;">
                                    @csrf
                                    <button type="submit" class="btn-table-action btn-restore">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg>
                                        Pulihkan
                                    </button>
                                </form>
                                <form action="{{ route('manajemen-data.force-delete', $item->batch_id) }}" method="POST" onsubmit="return confirm('PERINGATAN: Apakah Anda yakin ingin menghapus permanen \'{{ addslashes($item->nama_file) }}\'? Seluruh data transaksi terkait akan dihapus selamanya!');" style="margin: 0;">
                                    @csrf
                                    <button type="submit" class="btn-table-action btn-permanent-delete">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                        Hapus
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
