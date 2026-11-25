<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Chicken Crunchy Roll - Admin Panel')</title>

    <meta name="description"
        content="Panel admin Chicken Crunchy Roll untuk mengelola penjualan, stok, dan operasional outlet.">
    <meta name="author" content="Chicken Crunchy Roll">

    <!-- Favicon -->
    @include('layouts.partials.favicon')

    <!-- Local Third-Party Libraries (100% Offline Compatible) -->
    <link rel="stylesheet" href="{{ asset('assets/assets_dashboard/libs/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/assets_dashboard/libs/bootstrap-icons/bootstrap-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/assets_dashboard/libs/apexcharts/apexcharts.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/assets_dashboard/libs/flatpickr/flatpickr.min.css') }}">

    <!-- Main Design System & Custom Stylesheet -->
    <link rel="stylesheet" href="{{ asset('assets/assets_dashboard/css/main.css') }}">

    <!-- Toast Notification Styles -->
    <link rel="stylesheet" href="{{ asset('assets_user/admin/layout/admin.css') }}">

    <!-- Header halaman (breadcrumb + judul) & foto profil kosong -->
    <link rel="stylesheet" href="{{ asset('assets_user/shared/page_header.css') }}">
    <link rel="stylesheet" href="{{ asset('assets_user/shared/cards.css') }}?v={{ @filemtime(public_path('assets_user/shared/cards.css')) }}">

    <!-- Slot buat CSS tambahan khusus 1 halaman (opsional) -->
    <link rel="stylesheet" href="{{ asset('assets_user/shared/transisi.css') }}?v={{ @filemtime(public_path('assets_user/shared/transisi.css')) }}">
    @stack('styles')
</head>

<body class="{{ request()->routeIs('dashboard') ? 'is-dashboard' : '' }}">

    {{-- Foto profil untuk sidebar & navbar: hanya dari kolom `foto` (hasil upload di Profil Saya).
         Kalau belum ada foto, dibiarkan KOSONG (tanpa gambar bawaan). --}}
    @php
        $avatarUrl = auth()->user()->foto ? asset('storage/' . auth()->user()->foto) : null;
    @endphp

    <!-- ==========================================
         START: Sidebar Component
         ========================================== -->
    <div class="sidebar-wrapper" id="sidebar">
        <!-- Brand Logo / Identity -->
        <a href="{{ route('dashboard') }}" class="sidebar-brand">
            {{-- FIXED: path disesuaikan ke public/assets/images/logo_ccr.jpg
                 (sebelumnya salah nunjuk ke assets_login/images/logo_ccr.jpg) --}}
            <img src="{{ asset('assets/images/logo_ccr.jpg') }}" alt="Chicken Crunchy Roll">
            <span>Chicken Crunchy Roll</span>
        </a>

        <!-- Navigation Menu -->
        <div class="flex-grow-1 overflow-y-auto">

            <!-- Group: Menu -->
            <div class="sidebar-menu-section">
                <div class="sidebar-menu-title">Menu</div>
                <ul class="sidebar-menu-list">
                    <li class="sidebar-menu-item">
                        <a href="{{ route('dashboard') }}"
                            class="sidebar-menu-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
                            id="menu-dashboard" title="Dashboard">
                            <i class="bi bi-house-door-fill"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Group: Data Master -->
            <div class="sidebar-menu-section">
                <div class="sidebar-menu-title">Data Master</div>
                <ul class="sidebar-menu-list">
                    <li class="sidebar-menu-item">
                        <a href="{{ route('ukuran.index') }}"
                            class="sidebar-menu-link {{ request()->routeIs('ukuran.*') ? 'active' : '' }}"
                            id="menu-master-ukuran" title="Master Ukuran">
                            <i class="bi bi-rulers"></i>
                            <span>Master Ukuran</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="{{ route('filling.index') }}"
                            class="sidebar-menu-link {{ request()->routeIs('filling.*') ? 'active' : '' }}"
                            id="menu-master-filling" title="Master Filling">
                            <i class="bi bi-egg-fried"></i>
                            <span>Master Filling</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="{{ route('saus_celup.index') }}"
                            class="sidebar-menu-link {{ request()->routeIs('saus_celup.*') ? 'active' : '' }}"
                            id="menu-master-saus-celup" title="Master Saus Celup">
                            <i class="bi bi-droplet-fill"></i>
                            <span>Master Saus Celup</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="{{ route('bumbu_tabur.index') }}"
                            class="sidebar-menu-link {{ request()->routeIs('bumbu_tabur.*') ? 'active' : '' }}"
                            id="menu-master-bumbu-tabur" title="Master Bumbu Tabur">
                            <i class="bi bi-droplet-fill"></i>
                            <span>Master Bumbu Tabur</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="{{ route('kasir.index') }}"
                            class="sidebar-menu-link {{ request()->routeIs('kasir.*') ? 'active' : '' }}"
                            id="menu-master-kasir" title="Master Kasir">
                            <i class="bi bi-person-badge-fill"></i>
                            <span>Master Kasir</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Group: Operasional -->
            <div class="sidebar-menu-section">
                <div class="sidebar-menu-title">Operasional</div>
                <ul class="sidebar-menu-list">
                    <li class="sidebar-menu-item">
                        <a href="{{ route('bahan_baku.index') }}"
                            class="sidebar-menu-link {{ request()->routeIs('bahan_baku.*') ? 'active' : '' }}"
                            id="menu-stok" title="Manajemen Stok">
                            <i class="bi bi-box-seam-fill"></i>
                            <span>Manajemen Stok</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="{{ route('stok.kasir') }}"
                            class="sidebar-menu-link {{ request()->routeIs('stok.kasir') ? 'active' : '' }}"
                            id="menu-stok-kasir" title="Stok Kasir">
                            <i class="bi bi-person-badge-fill"></i>
                            <span>Stok Kasir</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="{{ route('stok.riwayat') }}"
                            class="sidebar-menu-link {{ request()->routeIs('stok.riwayat') ? 'active' : '' }}"
                            id="menu-riwayat-stok" title="Riwayat Stok">
                            <i class="bi bi-journal-text"></i>
                            <span>Riwayat Stok</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="{{ route('admin.riwayat-transaksi') }}"
                            class="sidebar-menu-link {{ request()->routeIs('admin.riwayat-transaksi*') ? 'active' : '' }}"
                            id="menu-riwayat" title="Riwayat Transaksi">
                            <i class="bi bi-clock-history"></i>
                            <span>Riwayat Transaksi</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Group: Analisis -->
            <div class="sidebar-menu-section">
                <div class="sidebar-menu-title">Analisis</div>
                <ul class="sidebar-menu-list">
                    <li class="sidebar-menu-item">
                        <a href="{{ route('admin.laporan') }}"
                            class="sidebar-menu-link {{ request()->routeIs('admin.laporan*') ? 'active' : '' }}"
                            id="menu-laporan" title="Laporan">
                            <i class="bi bi-bar-chart-line-fill"></i>
                            <span>Laporan</span>
                        </a>
                    </li>
                </ul>
            </div>

        </div>

        {{-- Ingat posisi scroll menu sidebar supaya tidak lompat ke atas saat pindah halaman --}}
        <script>
            (function () {
                var nav = document.currentScript.previousElementSibling;
                if (!nav) return;
                var KEY = 'sidebar-scroll-admin';
                try { var y = sessionStorage.getItem(KEY); if (y !== null) nav.scrollTop = parseInt(y, 10) || 0; } catch (e) {}
                function simpan() { try { sessionStorage.setItem(KEY, nav.scrollTop); } catch (e) {} }
                nav.addEventListener('scroll', simpan, { passive: true });
                window.addEventListener('pagehide', simpan);
            })();
        </script>

        <!-- Sidebar Profile Card (Dynamic Footer) -->
        <div class="sidebar-profile">
            @if ($avatarUrl)
                <img src="{{ $avatarUrl }}" alt="{{ auth()->user()->name ?? 'Admin' }}" class="sidebar-profile-img"
                    onerror="this.outerHTML='<span class=&quot;sidebar-profile-img avatar-empty&quot;></span>'">
            @else
                <span class="sidebar-profile-img avatar-empty" aria-hidden="true"></span>
            @endif
            <div class="sidebar-profile-info">
                <div class="sidebar-profile-name">{{ auth()->user()->name ?? 'Admin Chicken Crunchy Roll' }}</div>
                <div class="sidebar-profile-email">{{ auth()->user()->email ?? 'admin@gmail.com' }}</div>
            </div>
        </div>
    </div>
    <!-- END: Sidebar Component -->


    <!-- ==========================================
         START: Main Content Area
         ========================================== -->
    <div class="main-wrapper">

        @php
            // Daftar menu tombol "Tambah" di navbar. Format: [label, ikon, nama route, parameter].
            // Menu yang route-nya belum ada otomatis tidak ditampilkan (tidak bikin error).
            $aksiCepat = [
                'Stok' => [
                    ['Input Stok Masuk',  'bi-box-arrow-in-down', 'bahan_baku.index',  ['aksi' => 'masuk']],
                    ['Input Stok Keluar', 'bi-box-arrow-up',      'bahan_baku.index',  ['aksi' => 'keluar']],
                    ['Tambah Bahan Baku', 'bi-plus-square',       'bahan_baku.create', []],
                    ['Beri Paket Ayam',     'bi-send-fill',       'bahan_baku.index',  ['aksi' => 'paket']],
                    ['Beri Bahan Pendukung','bi-droplet-fill',    'bahan_baku.index',  ['aksi' => 'pendukung']],
                    ['Kirim Minyak & Gas',  'bi-fuel-pump-fill',  'bahan_baku.index',  ['aksi' => 'kirim']],
                ],
                'Data Master' => [
                    ['Tambah Ukuran',       'bi-rulers',        'ukuran.create',       []],
                    ['Tambah Filling',      'bi-egg-fried',     'filling.create',      []],
                    ['Tambah Saus Celup',   'bi-droplet-fill',  'saus_celup.create',   []],
                    ['Tambah Bumbu Tabur',  'bi-droplet-fill',  'bumbu_tabur.create',  []],
                    ['Tambah Kasir',        'bi-person-plus',   'kasir.create',        []],
                ],
            ];
        @endphp

        <!-- START: Top Navbar Component -->
        <header class="navbar-custom">
            <div class="navbar-left">
                <button class="btn-desktop-toggle d-none d-xl-flex align-items-center justify-content-center me-3"
                    id="desktop-sidebar-toggle" aria-label="Minimize Sidebar">
                    <i class="bi bi-chevron-bar-left"></i>
                </button>
                <button class="sidebar-toggle-btn me-2" id="sidebar-toggle" aria-label="Toggle Navigation">
                    <i class="bi bi-list"></i>
                </button>

                <div class="dropdown ms-2">
                    <button class="btn-quick-action dropdown-toggle" type="button" data-bs-toggle="dropdown"
                        aria-expanded="false" id="quick-actions-dropdown">
                        <i class="bi bi-plus-lg"></i>
                        <span>Tambah</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-quick-action" aria-labelledby="quick-actions-dropdown">
                        <li class="dropdown-header">Aksi Cepat</li>
                        @foreach ($aksiCepat as $grup => $menu)
                            @php
                                $menuAda = collect($menu)->filter(fn ($m) => \Illuminate\Support\Facades\Route::has($m[2]));
                            @endphp
                            @if ($menuAda->isNotEmpty())
                                @unless ($loop->first)
                                    <li><hr class="dropdown-divider"></li>
                                @endunless
                                <li class="dropdown-header">{{ $grup }}</li>
                                @foreach ($menuAda as $m)
                                    <li>
                                        <a class="dropdown-item" href="{{ route($m[2], $m[3]) }}">
                                            <i class="bi {{ $m[1] }}"></i> {{ $m[0] }}
                                        </a>
                                    </li>
                                @endforeach
                            @endif
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="navbar-greeting">
                <span class="greeting-emoji">👋</span>
                <span id="greeting-text">Selamat datang, {{ auth()->user()->name ?? 'Admin' }}</span>
            </div>

            <div class="navbar-actions">
                <button class="navbar-action-btn me-1" aria-label="Toggle Fullscreen" id="btn-fullscreen">
                    <i class="bi bi-arrows-fullscreen"></i>
                </button>
                <div class="dropdown">
                    <button class="navbar-action-btn dropdown-toggle" type="button" data-bs-toggle="dropdown"
                        aria-expanded="false" id="btn-notifications" data-bs-auto-close="outside">
                        <i class="bi bi-bell"></i>
                        @if ($notifikasi->isNotEmpty())
                            <span class="navbar-action-badge"></span>
                        @endif
                    </button>
                    <div class="dropdown-menu dropdown-menu-end dropdown-menu-notification p-0"
                        aria-labelledby="btn-notifications">
                        <div class="notification-header">
                            <h6 class="notification-title">Notifikasi</h6>
                            @if ($notifikasi->isNotEmpty())
                                <button class="btn-clear-all" type="button">Tandai semua dibaca</button>
                            @endif
                        </div>
                        <div class="notification-list" data-user="{{ auth()->id() }}">
                            @forelse ($notifikasi as $n)
                                <a href="{{ $n['url'] }}" class="notification-item" data-key="{{ $n['key'] }}">
                                    <div class="notification-icon bg-forest-light text-lime">
                                        <i class="bi {{ $n['icon'] }}"></i>
                                    </div>
                                    <div class="notification-content">
                                        <p class="notification-text">{{ $n['awal'] }} <strong>{{ $n['sorot'] }}</strong>{{ $n['akhir'] }}</p>
                                        <span class="notification-time">{{ $n['waktu'] }}</span>
                                    </div>
                                    <span class="notification-unread-dot"></span>
                                </a>
                            @empty
                                <div class="notification-empty">
                                    <i class="bi bi-bell-slash"></i>
                                    Tidak ada notifikasi. Semua stok aman.
                                </div>
                            @endforelse
                            @if ($notifikasi->isNotEmpty())
                                {{-- Muncul lewat JS kalau semua notifikasi sudah dibaca --}}
                                <div class="notification-empty notification-empty-baru" style="display:none">
                                    <i class="bi bi-bell-slash"></i>
                                    Tidak ada notifikasi baru.
                                </div>
                            @endif
                        </div>
                        <a href="{{ route('bahan_baku.index') }}" class="notification-footer">Lihat Manajemen Stok</a>
                    </div>
                </div>

                <div class="dropdown ms-2">
                    <button class="navbar-profile-btn dropdown-toggle" type="button" data-bs-toggle="dropdown"
                        aria-expanded="false" id="profile-dropdown">
                        @if ($avatarUrl)
                            <img src="{{ $avatarUrl }}" alt="Foto profil" class="navbar-profile-img"
                                onerror="this.outerHTML='<span class=&quot;navbar-profile-img avatar-empty&quot;></span>'">
                        @else
                            <span class="navbar-profile-img avatar-empty" aria-hidden="true"></span>
                        @endif
                        <span
                            class="navbar-profile-name d-none d-md-inline">{{ auth()->user()->name ?? 'Admin Chicken Crunchy Roll' }}</span>
                        <i class="bi bi-chevron-down navbar-profile-caret"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-profile"
                        aria-labelledby="profile-dropdown">
                        <li class="dropdown-header">Selamat Datang!</li>
                        <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person"></i> Akun Saya</a></li>
                        <li><a class="dropdown-item {{ request()->routeIs('stok.pengaturan*') ? 'active' : '' }}" href="{{ route('stok.pengaturan') }}"><i class="bi bi-sliders"></i> Pengaturan Stok</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item text-danger">
                                    <i class="bi bi-box-arrow-right"></i> Logout
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </header>
        <!-- END: Top Navbar Component -->

        <!-- ==========================================
           Breadcrumb Navigation
           ========================================== -->
        @unless (request()->routeIs('dashboard'))
            <nav class="breadcrumb-custom">
                <a href="{{ route('dashboard') }}"><i class="bi bi-house-door-fill"></i> Dashboard</a>
                <span class="breadcrumb-sep">/</span>
                <span class="breadcrumb-current">@yield('breadcrumb', 'Halaman')</span>
            </nav>
        @endunless

        <!-- ==========================================
           KONTEN HALAMAN — beda-beda tiap halaman
           ========================================== -->
        @yield('content')

        <!-- START: Footer Component -->
        <footer class="footer-custom">
            <div class="footer-left">
                <span class="footer-logo">
                    <i class="bi bi-egg-fried"></i> Chicken Crunchy Roll
                </span>
                <span class="footer-separator">|</span>
                <span class="footer-copy">&copy; {{ date('Y') }} Chicken Crunchy Roll <i
                        class="bi bi-heart-fill text-danger footer-heart"></i> Admin Panel
                </span>
            </div>
            <div class="footer-right">
                <ul class="footer-links">
                    <li><a href="#" class="footer-link">Status <span class="status-dot"></span></a></li>
                </ul>
            </div>
        </footer>
        <!-- END: Footer Component -->

    </div>
    <!-- END: Main Content Area -->

    <!-- Local Third-Party Libraries Script dependencies -->
    <script src="{{ asset('assets/assets_dashboard/libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/assets_dashboard/libs/apexcharts/apexcharts.min.js') }}"></script>
    <script src="{{ asset('assets/assets_dashboard/libs/flatpickr/flatpickr.min.js') }}"></script>

    <!-- Pesan flash dari session Laravel (dibaca admin.js) -->
    <script>
        window.flashMessages = {
            success: @json(session('success')),
            error: @json(session('error')),
            warning: @json(session('warning'))
        };
    </script>
    
    <!-- JS layout (sidebar, fullscreen, notifikasi): dimuat di SEMUA halaman -->
    <script src="{{ asset('assets/assets_dashboard/js/layout.js') }}"></script>

    <!-- Slot buat script tambahan / init chart khusus 1 halaman -->
    @stack('scripts')

    <!-- ==========================================
         Sapaan Dinamis (berubah sesuai jam di perangkat pengguna)
         ========================================== -->
    <script src="{{ asset('assets_user/admin/layout/admin.js') }}"></script>

    <!-- ==========================================
         Notifikasi Berhasil/Gagal (Modal di Tengah Layar)
         ========================================== -->
</body>

</html>
