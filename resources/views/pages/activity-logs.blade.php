@extends('layouts.app')

@section('title', 'Log Aktivitas Sistem | SIDATEX BPS Provinsi Riau')

@push('styles')
<style>
    .log-wrapper {
        min-height: calc(100vh - 120px);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    }

    .log-header-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #ffffff;
        padding: 1.25rem 1.75rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        border: 1px solid #e2e8f0;
    }

    .log-header-left {
        display: flex;
        align-items: center;
        gap: 1.25rem;
    }

    .log-header-left h1 {
        font-size: 1.35rem;
        font-weight: 700;
        color: #1e293b;
        margin: 0;
        border-left: 4px solid #0284c7;
        padding-left: 1rem;
    }

    .log-header-meta {
        font-size: 0.85rem;
        color: #64748b;
        margin-top: 0.25rem;
        padding-left: 1rem;
    }

    .log-filter-card {
        background: #ffffff;
        border-radius: 12px;
        padding: 1.25rem 1.5rem;
        margin-bottom: 1.5rem;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }

    .log-filter-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1rem 1.25rem;
    }

    @media (max-width: 1024px) {
        .log-filter-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 640px) {
        .log-filter-grid {
            grid-template-columns: 1fr;
        }
    }

    .form-group-filter {
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
        width: 100%;
        min-width: 0;
    }

    .form-group-filter label {
        font-size: 0.76rem;
        font-weight: 700;
        color: #334155;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        white-space: nowrap;
    }

    .form-group-filter input,
    .form-group-filter select {
        height: 38px;
        min-height: 38px;
        width: 100%;
        padding: 0 0.75rem;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: 0.85rem;
        color: #1e293b;
        background-color: #ffffff;
        outline: none;
        transition: border-color 0.15s, box-shadow 0.15s;
        box-sizing: border-box;
    }

    .form-group-filter input:focus,
    .form-group-filter select:focus {
        border-color: #0066cc;
        box-shadow: 0 0 0 2px rgba(0, 102, 204, 0.15);
    }

    .log-filter-action-row {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 0.75rem;
        margin-top: 1.25rem;
        padding-top: 1rem;
        border-top: 1px solid #f1f5f9;
        flex-wrap: wrap;
    }

    .btn-filter-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        height: 38px;
        min-height: 38px;
        border-radius: 8px;
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.15s ease;
        white-space: nowrap;
        text-decoration: none;
        box-sizing: border-box;
    }

    .btn-filter-submit {
        background: #0066cc;
        color: #ffffff;
        border: 1px solid #0052a3;
        padding: 0 1.5rem;
        font-weight: 700;
        box-shadow: 0 1px 2px rgba(0, 102, 204, 0.2);
    }

    .btn-filter-submit:hover {
        background: #0052a3;
    }

    .btn-filter-reset {
        background: #ffffff;
        color: #475569;
        border: 1px solid #cbd5e1;
        padding: 0 1.25rem;
        font-weight: 600;
    }

    .btn-filter-reset:hover {
        background: #f1f5f9;
        border-color: #94a3b8;
        color: #0f172a;
    }

    .log-table-container {
        background: #ffffff;
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: 0 4px 15px rgba(0,0,0,0.04);
        border: 1px solid #e2e8f0;
    }

    .log-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 8px;
    }

    .log-table th {
        text-align: left;
        padding: 0.75rem 1rem;
        color: #475569;
        font-weight: 700;
        font-size: 0.85rem;
        border-bottom: 2px solid #e2e8f0;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .log-table td {
        background: #f8fafc;
        padding: 0.9rem 1rem;
        vertical-align: middle;
        font-size: 0.88rem;
        border-top: 1px solid #f1f5f9;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
    }

    .log-table td:first-child {
        border-top-left-radius: 10px;
        border-bottom-left-radius: 10px;
        border-left: 1px solid #f1f5f9;
    }

    .log-table td:last-child {
        border-top-right-radius: 10px;
        border-bottom-right-radius: 10px;
        border-right: 1px solid #f1f5f9;
    }

    .badge-activity {
        display: inline-block;
        padding: 0.25rem 0.65rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.02em;
    }

    .badge-activity-upload { background: #e0f2fe; color: #0369a1; }
    .badge-activity-release { background: #dcfce7; color: #15803d; }
    .badge-activity-unrelease { background: #fef3c7; color: #b45309; }
    .badge-activity-trash { background: #fee2e2; color: #b91c1c; }
    .badge-activity-restore { background: #ede9fe; color: #6d28d9; }
    .badge-activity-export { background: #f0fdf4; color: #166534; }
    .badge-activity-default { background: #f1f5f9; color: #475569; }

    .user-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 600;
        color: #1e293b;
    }

    .user-avatar-initial {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: #3b82f6;
        color: #ffffff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: 700;
    }

    .log-empty-state {
        text-align: center;
        padding: 4rem 2rem;
        color: #64748b;
    }

    .log-pagination {
        margin-top: 1.5rem;
        display: flex;
        justify-content: flex-end;
    }
</style>
@endpush

@section('content')
<div class="log-wrapper">
    <div class="log-header-container">
        <div class="log-header-left">
            <div>
                <h1>Log Aktivitas Sistem</h1>
                <div class="log-header-meta">
                    Catatan audit trail seluruh aktivitas operasional pengguna (Total: {{ number_format($totalLogsCount, 0, ',', '.') }} aksi tercatat)
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="log-filter-card">
        <form method="GET" action="{{ route('activity-logs') }}">
            <div class="log-filter-grid">
                <!-- Baris 1: 3 Kolom Seimbang -->
                <div class="form-group-filter">
                    <label for="q">Cari Aktivitas / Pengguna / Keterangan</label>
                    <input type="text" id="q" name="q" value="{{ request('q') }}" placeholder="Ketik kata kunci...">
                </div>

                <div class="form-group-filter">
                    <label for="modul">Modul Terkait</label>
                    <select id="modul" name="modul">
                        <option value="ALL">-- Semua Modul --</option>
                        @foreach($availableModules as $mod)
                            <option value="{{ $mod }}" {{ request('modul') == $mod ? 'selected' : '' }}>{{ $mod }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group-filter">
                    <label for="aktivitas">Jenis Aktivitas</label>
                    <select id="aktivitas" name="aktivitas">
                        <option value="ALL">-- Semua Aktivitas --</option>
                        @foreach($availableActivities as $act)
                            <option value="{{ $act }}" {{ request('aktivitas') == $act ? 'selected' : '' }}>{{ $act }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Baris 2: 3 Kolom Seimbang -->
                <div class="form-group-filter">
                    <label for="tanggal_mulai">Dari Tanggal</label>
                    <input type="date" id="tanggal_mulai" name="tanggal_mulai" value="{{ request('tanggal_mulai') }}">
                </div>

                <div class="form-group-filter">
                    <label for="tanggal_selesai">Sampai Tanggal</label>
                    <input type="date" id="tanggal_selesai" name="tanggal_selesai" value="{{ request('tanggal_selesai') }}">
                </div>

                <div class="form-group-filter">
                    <label for="per_page">Data per Halaman</label>
                    <select id="per_page" name="per_page">
                        <option value="10" {{ request('per_page', 10) == 10 ? 'selected' : '' }}>10 data</option>
                        <option value="25" {{ request('per_page', 25) == 25 ? 'selected' : '' }}>25 data</option>
                        <option value="50" {{ request('per_page', 50) == 50 ? 'selected' : '' }}>50 data</option>
                        <option value="100" {{ request('per_page', 100) == 100 ? 'selected' : '' }}>100 data</option>
                    </select>
                </div>
            </div>

            <!-- Baris Aksi Sejajar Kanan, 100% di Dalam Kotak Card -->
            <div class="log-filter-action-row">
                <a href="{{ route('activity-logs') }}" class="btn-filter-action btn-filter-reset">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path></svg>
                    Reset Filter
                </a>
                <button type="submit" class="btn-filter-action btn-filter-submit">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    Terapkan Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Table Container -->
    <div class="log-table-container">
        @if($logs->isEmpty())
            <div class="log-empty-state">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5" style="margin-bottom: 0.75rem;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                <h3 style="font-size: 1.15rem; font-weight: 700; color: #1e293b; margin-bottom: 0.4rem;">Belum ada catatan aktivitas</h3>
                <p style="margin: 0; font-size: 0.85rem;">Tidak ditemukan data aktivitas sesuai dengan kriteria filter yang dipilih.</p>
            </div>
        @else
            <div style="overflow-x: auto;">
                <table class="log-table">
                    <thead>
                        <tr>
                            <th style="width: 60px;">No</th>
                            <th style="width: 170px;">Waktu & Tanggal</th>
                            <th style="width: 180px;">Pengguna</th>
                            <th style="width: 180px;">Aktivitas</th>
                            <th style="width: 150px;">Modul</th>
                            <th>Keterangan Aktivitas</th>
                            <th style="width: 130px;">Alamat IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($logs as $index => $log)
                            @php
                                $act = $log->aktivitas;
                                $badgeClass = 'badge-activity-default';
                                if (str_contains(strtolower($act), 'upload') || str_contains(strtolower($act), 'unggah')) {
                                    $badgeClass = 'badge-activity-upload';
                                } elseif (str_contains(strtolower($act), 'rilis publikasi')) {
                                    $badgeClass = 'badge-activity-release';
                                } elseif (str_contains(strtolower($act), 'tarik') || str_contains(strtolower($act), 'private')) {
                                    $badgeClass = 'badge-activity-unrelease';
                                } elseif (str_contains(strtolower($act), 'trash') || str_contains(strtolower($act), 'hapus')) {
                                    $badgeClass = 'badge-activity-trash';
                                } elseif (str_contains(strtolower($act), 'pulih') || str_contains(strtolower($act), 'restore')) {
                                    $badgeClass = 'badge-activity-restore';
                                } elseif (str_contains(strtolower($act), 'ekspor') || str_contains(strtolower($act), 'unduh')) {
                                    $badgeClass = 'badge-activity-export';
                                }

                                $userName = $log->user ? ($log->user->name ?? $log->user->nama ?? $log->user->username) : 'Sistem / Anonim';
                                $initial = strtoupper(substr($userName, 0, 1));
                            @endphp
                            <tr>
                                <td style="text-align: center; color: #94a3b8; font-weight: 600;">
                                    {{ $logs->firstItem() + $index }}
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: #1e293b;">
                                        {{ \Carbon\Carbon::parse($log->created_at)->translatedFormat('d M Y') }}
                                    </div>
                                    <div style="font-size: 0.8rem; color: #64748b;">
                                        {{ \Carbon\Carbon::parse($log->created_at)->format('H:i:s') }} WIB
                                    </div>
                                </td>
                                <td>
                                    <div class="user-pill">
                                        <span class="user-avatar-initial">{{ $initial }}</span>
                                        <div>
                                            <div>{{ $userName }}</div>
                                            @if($log->user && $log->user->role)
                                                <div style="font-size: 0.75rem; color: #64748b; font-weight: normal;">
                                                    {{ $log->user->role->nama_role }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge-activity {{ $badgeClass }}">
                                        {{ $log->aktivitas }}
                                    </span>
                                </td>
                                <td>
                                    <span style="font-weight: 600; color: #475569;">
                                        {{ $log->modul ?? '-' }}
                                    </span>
                                </td>
                                <td>
                                    <div style="color: #1e293b; line-height: 1.45;">
                                        {{ $log->deskripsi ?? '-' }}
                                    </div>
                                </td>
                                <td>
                                    <code style="background: #e2e8f0; padding: 0.2rem 0.4rem; border-radius: 4px; font-size: 0.8rem; color: #334155;">
                                        {{ $log->ip_address ?? '127.0.0.1' }}
                                    </code>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <div style="font-size: 0.82rem; color: #64748b;">
                    @if($logs->total() > 0)
                        Menampilkan data <strong>{{ $logs->firstItem() }}</strong> &ndash; <strong>{{ $logs->lastItem() }}</strong> dari total <strong>{{ number_format($logs->total(), 0, ',', '.') }}</strong> log aktivitas
                    @else
                        Menampilkan 0 data
                    @endif
                </div>
                <div>
                    {{ $logs->links('partials.pagination') }}
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
