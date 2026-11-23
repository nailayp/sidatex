<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'SIDATEX | BPS Provinsi Riau')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
    <script>
        // Script Dropdown Pengguna Topbar & Menu Toggle (Head Section agar tidak mengintervensi test script harness)
        document.addEventListener('DOMContentLoaded', function () {
            const profileBtn = document.getElementById('userProfileBtn');
            const dropdownMenu = document.getElementById('userDropdownMenu');

            if (profileBtn && dropdownMenu) {
                profileBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    const isExpanded = profileBtn.getAttribute('aria-expanded') === 'true';
                    profileBtn.setAttribute('aria-expanded', String(!isExpanded));
                    dropdownMenu.classList.toggle('show');
                });

                document.addEventListener('click', function (e) {
                    if (!profileBtn.contains(e.target) && !dropdownMenu.contains(e.target)) {
                        dropdownMenu.classList.remove('show');
                        profileBtn.setAttribute('aria-expanded', 'false');
                    }
                });
            }

            const menuToggle = document.getElementById('menuToggle');
            const pageShell = document.getElementById('pageShell');
            const sidebarBackdrop = document.getElementById('sidebarBackdrop');

            if (menuToggle && pageShell) {
                menuToggle.addEventListener('click', function () {
                    pageShell.classList.toggle('sidebar-open');
                });
            }
            if (sidebarBackdrop && pageShell) {
                sidebarBackdrop.addEventListener('click', function () {
                    pageShell.classList.remove('sidebar-open');
                });
            }
        });
    </script>
</head>
<body class="sidatex-body">
    <div class="page-shell" id="pageShell">
        <button class="sidebar-backdrop" id="sidebarBackdrop" type="button" aria-label="Tutup menu"></button>

        <!-- Sidebar Kiri Menempel Penuh (Sticky 100vh) Sesuai Standar SiPantau BPS -->
        <aside class="sidebar" id="mainSidebar" aria-label="Navigasi utama">
            <!-- Header Brand Sidebar -->
            <a class="sidebar-brand" href="{{ route('dashboard') }}" aria-label="SIDATEX Dashboard">
                <img src="{{ asset('images/bps.png') }}" alt="Logo BPS" class="bps-logo">
                <div class="sidebar-brand-text">
                    <strong class="sidebar-brand-title">SIDATEX</strong>
                    <span class="sidebar-brand-sub">Badan Pusat Statistik</span>
                </div>
            </a>

            <!-- Navigasi Menu -->
            <div class="sidebar-menu-wrapper">
                <div class="sidebar-section-label">Dashboard</div>
                <nav class="sidebar-nav">
                    <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard*') ? 'is-active' : '' }}" @if(request()->routeIs('dashboard*')) aria-current="page" @endif>
                        <span class="nav-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                        </span>
                        <span class="nav-label">Dashboard Statistik</span>
                    </a>

                    <div class="sidebar-section-label sidebar-section-spaced">Pengelolaan Data</div>
                    @if(Auth::user() && (Auth::user()->role_id === 1 || (Auth::user()->role && Auth::user()->role->nama_role === 'Super Admin')))
                    <a href="{{ route('upload-data') }}" class="nav-item {{ request()->routeIs('upload-data') || request()->routeIs('neraca.upload') ? 'is-active' : '' }}" @if(request()->routeIs('upload-data') || request()->routeIs('neraca.upload')) aria-current="page" @endif>
                        <span class="nav-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>
                        </span>
                        <span class="nav-label">Upload Data</span>
                    </a>
                    @endif
                    <a href="{{ route('filtering-data') }}" class="nav-item {{ request()->routeIs('filtering-data') ? 'is-active' : '' }}" @if(request()->routeIs('filtering-data')) aria-current="page" @endif>
                        <span class="nav-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                        </span>
                        <span class="nav-label">Filtering Data</span>
                    </a>
                    <a href="{{ route('manajemen-data') }}" class="nav-item {{ request()->routeIs('manajemen-data') ? 'is-active' : '' }}" @if(request()->routeIs('manajemen-data')) aria-current="page" @endif>
                        <span class="nav-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M3 9h18"/><path d="M3 15h18"/><path d="M9 3v18"/><path d="M15 3v18"/></svg>
                        </span>
                        <span class="nav-label">Manajemen Data</span>
                    </a>
                    @if(Auth::user() && (Auth::user()->role_id === 1 || (Auth::user()->role && Auth::user()->role->nama_role === 'Super Admin')))
                    <a href="{{ route('trash-data') }}" class="nav-item {{ request()->routeIs('trash-data') ? 'is-active' : '' }}" @if(request()->routeIs('trash-data')) aria-current="page" @endif>
                        <span class="nav-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                        </span>
                        <span class="nav-label">Trash Dokumen</span>
                        @if(isset($sidebarTotalTrash) && $sidebarTotalTrash > 0)
                            <span class="sidebar-badge-count">{{ $sidebarTotalTrash }}</span>
                        @endif
                    </a>
                    @endif

                    <div class="sidebar-section-label sidebar-section-spaced">Analisis & Pelaporan</div>
                    <a href="{{ route('analisis-perdagangan') }}" class="nav-item {{ request()->routeIs('analisis-perdagangan') ? 'is-active' : '' }}" @if(request()->routeIs('analisis-perdagangan')) aria-current="page" @endif>
                        <span class="nav-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" x2="16.65" y1="21" y2="16.65"/></svg>
                        </span>
                        <span class="nav-label">Analisis Perdagangan</span>
                    </a>

                    @if(!Auth::check() || (Auth::user()?->role_id !== 3 && Auth::user()?->role?->nama_role !== 'Viewer Eksekutif'))
                    <div class="sidebar-section-label sidebar-section-spaced">Sistem & Audit</div>
                    <a href="{{ route('activity-logs') }}" class="nav-item {{ request()->routeIs('activity-logs') ? 'is-active' : '' }}" @if(request()->routeIs('activity-logs')) aria-current="page" @endif>
                        <span class="nav-icon" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                        </span>
                        <span class="nav-label">Log Aktivitas</span>
                    </a>
                    @endif
                </nav>
            </div>

            <!-- Tombol Keluar Menempel di Bagian Bawah Sidebar (SiPantau Style) -->
            @auth
            <div class="sidebar-logout-container">
                <form action="{{ route('logout') }}" method="POST" class="sidebar-logout-form">
                    @csrf
                    <button type="submit" class="sidebar-logout-btn" title="Keluar dari akun SIDATEX">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                            <polyline points="16 17 21 12 16 7"></polyline>
                            <line x1="21" y1="12" x2="9" y2="12"></line>
                        </svg>
                        <span>Keluar</span>
                    </button>
                </form>
            </div>
            @endauth
        </aside>

        <!-- Area Konten Utama Sebelah Kanan -->
        <div class="main-wrapper">
            <!-- Topbar Sticky SiPantau Style -->
            <header class="main-topbar">
                <div class="topbar-left">
                    <button
                        type="button"
                        class="menu-toggle"
                        id="menuToggle"
                        aria-label="Buka menu navigasi"
                        aria-controls="mainSidebar"
                        aria-expanded="true"
                    >
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="3" y1="12" x2="21" y2="12"></line>
                            <line x1="3" y1="6" x2="21" y2="6"></line>
                            <line x1="3" y1="18" x2="21" y2="18"></line>
                        </svg>
                    </button>

                    <!-- Location Badge SiPantau Style -->
                    <div class="location-badge" title="Lingkup Wilayah Kerja">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                            <circle cx="12" cy="10" r="3"></circle>
                        </svg>
                        <span>PROVINSI RIAU</span>
                    </div>
                </div>

                <!-- User Profile & Dropdown SiPantau Style -->
                <div class="topbar-right">
                    @auth
                    <div class="user-dropdown-wrapper" id="userDropdownWrapper">
                        <button type="button" class="user-profile-btn" id="userProfileBtn" aria-expanded="false" aria-haspopup="true">
                            <span class="user-avatar-circle" aria-hidden="true">
                                {{ mb_strtoupper(mb_substr(auth()->user()->nama ?? auth()->user()->name ?? 'U', 0, 2)) }}
                            </span>
                            <div class="user-profile-info">
                                <span class="user-profile-name">{{ auth()->user()->nama ?? auth()->user()->name ?? 'Pengguna SIDATEX' }}</span>
                                <span class="user-profile-role">
                                    @if(Auth::user()?->role)
                                        {{ Auth::user()->role->nama_role }}
                                    @elseif(Auth::user()?->role_id === 1)
                                        Super Admin
                                    @elseif(Auth::user()?->role_id === 3)
                                        Viewer Eksekutif
                                    @else
                                        Pegawai BPS
                                    @endif
                                </span>
                            </div>
                            <svg class="user-chevron-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </button>

                        <div class="user-dropdown-menu" id="userDropdownMenu" role="menu">
                            <div class="user-dropdown-header">
                                <div class="dropdown-user-name">{{ auth()->user()->nama ?? auth()->user()->name ?? 'Pengguna SIDATEX' }}</div>
                                <div class="dropdown-user-email">{{ auth()->user()->email ?? auth()->user()->nip ?? 'bps1400@bps.go.id' }}</div>
                                <div class="dropdown-role-tag">
                                    @if(Auth::user()?->role)
                                        {{ Auth::user()->role->nama_role }}
                                    @elseif(Auth::user()?->role_id === 1)
                                        Super Admin
                                    @elseif(Auth::user()?->role_id === 3)
                                        Viewer Eksekutif
                                    @else
                                        Pegawai BPS
                                    @endif
                                </div>
                            </div>
                            <div class="user-dropdown-divider"></div>
                            <form action="{{ route('logout') }}" method="POST" style="margin: 0;">
                                @csrf
                                <button type="submit" class="user-dropdown-logout-btn">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                        <polyline points="16 17 21 12 16 7"></polyline>
                                        <line x1="21" y1="12" x2="9" y2="12"></line>
                                    </svg>
                                    <span>Keluar dari Sistem</span>
                                </button>
                            </form>
                        </div>
                    </div>
                    @endauth
                </div>
            </header>

            <!-- Konten Halaman -->
            <main class="main-content">
                <div class="main-content-inner">
                    @yield('content')
                </div>
            </main>

            <!-- Footer Aplikasi SiPantau Style -->
            <footer class="main-footer">
                <div class="footer-copy">
                    &copy; 2026 <strong>SIDATEX</strong> &mdash; Badan Pusat Statistik (BPS) Provinsi Riau. Hak Cipta Dilindungi.
                </div>
            </footer>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
