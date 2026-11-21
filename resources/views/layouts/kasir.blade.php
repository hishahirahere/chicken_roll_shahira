<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Kasir - Chicken Crunchy Roll')</title>

    @include('layouts.partials.favicon')
    <link rel="stylesheet" href="{{ asset('assets/assets_dashboard/libs/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/assets_dashboard/libs/bootstrap-icons/bootstrap-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/assets_dashboard/libs/apexcharts/apexcharts.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/assets_dashboard/css/main.css') }}">

    <link rel="stylesheet" href="{{ asset('assets_user/kasir/layout/kasir.css') }}">
    <link rel="stylesheet" href="{{ asset('assets_user/shared/page_header.css') }}">
    <link rel="stylesheet" href="{{ asset('assets_user/shared/cards.css') }}?v={{ @filemtime(public_path('assets_user/shared/cards.css')) }}">

    <link rel="stylesheet" href="{{ asset('assets_user/shared/transisi.css') }}?v={{ @filemtime(public_path('assets_user/shared/transisi.css')) }}">
    @stack('styles')
</head>

<body class="{{ request()->routeIs('pos.dashboard_kasir') ? 'is-dashboard' : '' }}">
    @php
        // Jumlah bahan menipis / habis di tangan kasir (lencana di menu Stok Saya)
        try {
            $stokRingkas = app(\App\Services\StokOtomatis\StokOtomatisService::class)->ringkasKasir((int) auth()->id());
        } catch (\Throwable $e) {
            $stokRingkas = ['menipis' => 0, 'habis' => 0];
        }
        // Minyak/gas yang sudah dikirim admin dan menunggu konfirmasi "Sudah Diterima"
        try {
            $menungguTerima = \Illuminate\Support\Facades\DB::table('permintaan_stok')
                ->where('kasir_id', auth()->id())->where('status', 'dikirim')->count();
        } catch (\Throwable $e) {
            $menungguTerima = 0;
        }
    @endphp

    {{-- ================= SIDEBAR KASIR ================= --}}
    <div class="sidebar-wrapper" id="sidebar">
        <a href="{{ route('pos.dashboard_kasir') }}" class="sidebar-brand">
            <img src="{{ asset('assets/images/logo_ccr.jpg') }}" alt="Chicken Crunchy Roll">
            <span>Chicken Crunchy Roll</span>
        </a>

        <div class="flex-grow-1 overflow-y-auto">
            <div class="sidebar-menu-section">
                <div class="sidebar-menu-title">Menu</div>
                <ul class="sidebar-menu-list">
                    <li class="sidebar-menu-item">
                        <a href="{{ route('pos.dashboard_kasir') }}"
                            class="sidebar-menu-link {{ request()->routeIs('pos.dashboard_kasir') ? 'active' : '' }}"
                            title="Dashboard">
                            <i class="bi bi-house-door-fill"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                </ul>
            </div>

            <div class="sidebar-menu-section">
                <div class="sidebar-menu-title">Transaksi</div>
                <ul class="sidebar-menu-list">
                    <li class="sidebar-menu-item">
                        <a href="{{ route('pos.index') }}"
                            class="sidebar-menu-link {{ request()->routeIs('pos.index') ? 'active' : '' }}"
                            title="Transaksi Penjualan">
                            <i class="bi bi-cart-fill"></i>
                            <span>Transaksi Penjualan</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="{{ route('pos.stok') }}"
                            class="sidebar-menu-link {{ request()->routeIs('pos.stok') ? 'active' : '' }}"
                            title="Stok Saya">
                            <i class="bi bi-box-seam-fill"></i>
                            <span>Stok Saya</span>
                            @if (($stokRingkas['habis'] + $stokRingkas['menipis'] + $menungguTerima) > 0)
                                <span class="stok-badge {{ ($stokRingkas['habis'] > 0 || $menungguTerima > 0) ? 'bad' : '' }}">{{ $stokRingkas['habis'] + $stokRingkas['menipis'] + $menungguTerima }}</span>
                            @endif
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="{{ route('pos.riwayat') }}"
                            class="sidebar-menu-link {{ request()->routeIs('pos.riwayat', 'pos.struk') ? 'active' : '' }}"
                            title="Riwayat Transaksi">
                            <i class="bi bi-clock-history"></i>
                            <span>Riwayat Transaksi</span>
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
                var KEY = 'sidebar-scroll-kasir';
                try { var y = sessionStorage.getItem(KEY); if (y !== null) nav.scrollTop = parseInt(y, 10) || 0; } catch (e) {}
                function simpan() { try { sessionStorage.setItem(KEY, nav.scrollTop); } catch (e) {} }
                nav.addEventListener('scroll', simpan, { passive: true });
                window.addEventListener('pagehide', simpan);
            })();
        </script>

        <div class="sidebar-profile">
            @if (auth()->user()->foto)
                <img src="{{ asset('storage/' . auth()->user()->foto) }}" alt="{{ auth()->user()->name }}" class="sidebar-profile-img"
                    onerror="this.outerHTML='<span class=&quot;sidebar-profile-img avatar-empty&quot;></span>'">
            @else
                <span class="sidebar-profile-img avatar-empty" aria-hidden="true"></span>
            @endif
            <div class="sidebar-profile-info">
                <div class="sidebar-profile-name">{{ auth()->user()->name }}</div>
                <div class="sidebar-profile-email">Kasir</div>
            </div>
        </div>
    </div>

    {{-- ================= KONTEN ================= --}}
    <div class="main-wrapper">

        <header class="navbar-custom">
            <div class="navbar-left">
                <button class="btn-desktop-toggle d-none d-xl-flex align-items-center justify-content-center me-3"
                    id="desktop-sidebar-toggle" aria-label="Minimize Sidebar">
                    <i class="bi bi-chevron-bar-left"></i>
                </button>
                <button class="sidebar-toggle-btn me-2" id="sidebar-toggle" aria-label="Toggle Navigation">
                    <i class="bi bi-list"></i>
                </button>
            </div>

            <div class="navbar-center">
                @hasSection('navbar_center')
                    @yield('navbar_center')
                @else
                    <div class="navbar-greeting">
                        <span class="greeting-emoji">👋</span>
                        <span id="greeting-text">Selamat datang, {{ auth()->user()->name }}</span>
                    </div>
                @endif
            </div>

            <div class="navbar-actions">
                <button class="navbar-action-btn me-1" aria-label="Fullscreen" id="btn-fullscreen">
                    <i class="bi bi-arrows-fullscreen"></i>
                </button>

                <div class="dropdown ms-2">
                    <button class="navbar-profile-btn dropdown-toggle" type="button" data-bs-toggle="dropdown"
                        aria-expanded="false" id="profile-dropdown">
                        @if (auth()->user()->foto)
                            <img src="{{ asset('storage/' . auth()->user()->foto) }}" alt="Foto profil" class="navbar-profile-img"
                                onerror="this.outerHTML='<span class=&quot;navbar-profile-img avatar-empty&quot;></span>'">
                        @else
                            <span class="navbar-profile-img avatar-empty" aria-hidden="true"></span>
                        @endif
                        <span class="navbar-profile-name d-none d-md-inline">{{ auth()->user()->name }}</span>
                        <i class="bi bi-chevron-down navbar-profile-caret"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-profile" aria-labelledby="profile-dropdown">
                        <li class="dropdown-header">Halo, {{ auth()->user()->name }}!</li>
                        <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person"></i> Akun Saya</a></li>
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

        {{-- Breadcrumb (tidak tampil di Dashboard) --}}
        @unless (request()->routeIs('pos.dashboard_kasir'))
            <nav class="breadcrumb-custom">
                <a href="{{ route('pos.dashboard_kasir') }}"><i class="bi bi-house-door-fill"></i> Dashboard</a>
                <span class="breadcrumb-sep">/</span>
                <span class="breadcrumb-current">@yield('breadcrumb', 'Halaman')</span>
            </nav>
        @endunless

        @yield('content')

        <footer class="footer-custom">
            <div class="footer-left">
                <span class="footer-logo"><i class="bi bi-egg-fried"></i> Chicken Crunchy Roll</span>
                <span class="footer-separator">|</span>
                <span class="footer-copy">&copy; {{ date('Y') }} Chicken Crunchy Roll &middot; Kasir</span>
            </div>
        </footer>
    </div>

    <script src="{{ asset('assets/assets_dashboard/libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>

    <script>
        window.flashMessages = {
            success: @json(session('success')),
            error: @json(session('error')),
            warning: @json(session('warning'))
        };
    </script>

    @stack('scripts')

    <script src="{{ asset('assets_user/kasir/layout/kasir.js') }}"></script>
</body>

</html>
