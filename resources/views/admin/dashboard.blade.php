@extends('layouts.admin')

@section('title', 'Dashboard - Chicken Crunchy Roll')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets_user/admin/dashboard/dashboard.css') }}">
@endpush

@section('content')


  <div class="page-header">
    <div class="page-header-icon"><i class="bi bi-speedometer2"></i></div>
    <div>
      <h1 class="page-title">Dashboard</h1>
      <p class="page-subtitle">Pantau penjualan, stok, dan operasional Chicken Crunchy Roll.</p>
    </div>
    <div class="btn-date-picker" style="cursor:default;">
      <i class="bi bi-calendar4-event"></i>
      <span>{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span>
    </div>
  </div>


  <div class="row g-4">

    <!-- TOP AREA: 3 Stat Cards -->
    <div class="col-12">
      <div class="row g-4">

        <!-- Card 1: Penjualan Hari Ini (highlight card, maroon + asterisk decoration) -->
        <div class="col-md-4">
          <div class="card stat-highlight-card">
            <div class="position-relative z-index-2">
              <div class="stat-icon-circle"><i class="bi bi-cash-coin"></i></div>
              <span class="stat-label">Penjualan Hari Ini</span>
              <div class="stat-value">Rp {{ number_format($penjualanHariIni, 0, ',', '.') }}</div>
              <div class="trend-badge {{ $trendPenjualan >= 0 ? 'trend-up' : 'trend-down' }}">
                <i class="bi bi-arrow-{{ $trendPenjualan >= 0 ? 'up' : 'down' }}-right"></i>
                <span>{{ abs($trendPenjualan) }}% dari kemarin</span>
              </div>
              <a href="{{ route('admin.riwayat-transaksi') }}" class="btn-highlight-link" id="btn-lihat-detail-penjualan"
                style="text-decoration:none;">
                <span>Lihat Detail</span>
                <i class="bi bi-arrow-right"></i>
              </a>
            </div>
            <svg class="stat-highlight-bg-shape z-index-1" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
              <g transform="translate(50,50)">
                <rect x="-6" y="-45" width="12" height="90" rx="6" ry="6" fill="#e8a33d" />
                <rect x="-6" y="-45" width="12" height="90" rx="6" ry="6" fill="#e8a33d" transform="rotate(60)" />
                <rect x="-6" y="-45" width="12" height="90" rx="6" ry="6" fill="#e8a33d" transform="rotate(120)" />
              </g>
            </svg>
          </div>
        </div>

        <!-- Card 2: Jumlah Transaksi (sparkline dari data 7 hari asli) -->
        <div class="col-md-4">
          <div class="card card-stat d-flex flex-column justify-content-between">
            <div>
              <div class="stat-icon-circle"><i class="bi bi-receipt"></i></div>
              <span class="stat-label">Jumlah Transaksi</span>
              <div class="stat-value">{{ $transaksiHariIni }} transaksi</div>
              <div class="trend-badge {{ $trendTransaksi >= 0 ? 'trend-up' : 'trend-down' }}">
                <i class="bi bi-arrow-{{ $trendTransaksi >= 0 ? 'up' : 'down' }}-right"></i>
                <span>{{ abs($trendTransaksi) }}% dari kemarin</span>
              </div>
            </div>
            <div class="sparkline-container sparkline-card-footer">
              <div id="transaksi-sparkline"></div>
            </div>
          </div>
        </div>

        <!-- Card 3: Porsi Terjual Hari Ini (sparkline dari data 7 hari asli) -->
        <div class="col-md-4">
          <div class="card card-stat d-flex flex-column justify-content-between">
            <div>
              <div class="stat-icon-circle"><i class="bi bi-egg-fried"></i></div>
              <span class="stat-label">Porsi Terjual Hari Ini</span>
              <div class="stat-value">{{ $porsiHariIni }} porsi</div>
              <div class="trend-badge {{ $trendPorsi >= 0 ? 'trend-up' : 'trend-down' }}">
                <i class="bi bi-arrow-{{ $trendPorsi >= 0 ? 'up' : 'down' }}-right"></i>
                <span>{{ abs($trendPorsi) }}% dari kemarin</span>
              </div>
            </div>
            <div class="sparkline-container sparkline-card-footer">
              <div id="porsi-sparkline"></div>
            </div>
          </div>
        </div>

      </div>
    </div>
    <!-- END: TOP AREA -->

    <!-- Revenue Chart (left, big) -->
    <div class="col-xl-9 col-lg-8">
      <div class="card mb-0">
        <div class="card-header mb-2">
          <h2 class="card-title">Penjualan 7 Hari Terakhir</h2>
          <div class="d-flex gap-3 align-items-center">
            <div class="chart-legend-item">
              <span class="legend-dot bg-forest-medium"></span>
              <span class="chart-legend-label">Penjualan</span>
            </div>
            <div class="chart-legend-item">
              <span class="legend-dot bg-lime-accent"></span>
              <span class="chart-legend-label">Jumlah Transaksi</span>
            </div>
          </div>
        </div>
        <div id="revenue-chart"></div>
      </div>
    </div>

    <!-- Status Stok Donut (right) -->
    <div class="col-xl-3 col-lg-4">
      <div class="card h-100 mb-0 d-flex flex-column justify-content-between">
        <div class="card-header mb-1">
          <h2 class="card-title">Status Stok</h2>
        </div>
        <div id="stock-status-chart"></div>
        <div class="chart-legends-container flex-column align-items-stretch gap-2">
          <div class="d-flex justify-content-between align-items-center">
            <span class="chart-legend-item"><span class="legend-dot" style="background-color:#22C55E"></span> Aman</span>
            <span class="fs-xs text-muted-green">{{ $stokAman }} item &middot; {{ $totalStokItem > 0 ? round($stokAman / $totalStokItem * 100) : 0 }}%</span>
          </div>
          <div class="d-flex justify-content-between align-items-center">
            <span class="chart-legend-item"><span class="legend-dot bg-lime-accent"></span> Menipis</span>
            <span class="fs-xs text-muted-green">{{ $stokMenipis }} item &middot; {{ $totalStokItem > 0 ? round($stokMenipis / $totalStokItem * 100) : 0 }}%</span>
          </div>
          <div class="d-flex justify-content-between align-items-center">
            <span class="chart-legend-item"><span class="legend-dot" style="background-color:#EF4444"></span> Habis</span>
            <span class="fs-xs text-muted-green">{{ $stokHabis }} item &middot; {{ $totalStokItem > 0 ? round($stokHabis / $totalStokItem * 100) : 0 }}%</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Peringatan Stok -->
    <div class="col-lg-4">
      <div class="card mb-0 dash-card-fixed">
        <div class="card-header">
          <h2 class="card-title">Peringatan Stok
            @if ($peringatanStok->count())<span class="dash-count">{{ $peringatanStok->count() }} item</span>@endif
          </h2>
          <a href="{{ route('bahan_baku.index') }}" class="fs-xs fw-bold">Lihat Semua <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="custom-list dash-scroll">
          @forelse ($peringatanStok as $bahan)
            <div class="custom-list-item">
              <div class="item-left">
                <div class="item-icon-wrapper"><i class="bi bi-egg-fried"></i></div>
                <div class="item-info">
                  <div class="item-name">{{ $bahan->nama_bahan }}</div>
                  <div class="item-sub">Bahan baku</div>
                </div>
              </div>
              <div class="item-status-wrapper">
                <span class="badge-table {{ $bahan->status_stok == 'habis' ? 'failed' : 'pending' }}">
                  {{ ucfirst($bahan->status_stok) }}
                </span>
                <p class="item-hash mt-1">{{ $bahan->stok_format }} {{ $bahan->satuan }}</p>
              </div>
            </div>
          @empty
            <p class="text-muted-green text-center mb-0">Semua stok aman 🎉</p>
          @endforelse
        </div>
      </div>
    </div>

    <!-- Transaksi Hari Ini: hanya transaksi hari ini, bisa di-scroll di dalam kartu -->
    <div class="col-lg-8">
      <div class="card mb-0 dash-card-fixed">
        <div class="card-header">
          <h2 class="card-title">Transaksi Hari Ini
            @if ($transaksiTerbaru->count())<span class="dash-count">{{ $transaksiTerbaru->count() }} transaksi</span>@endif
          </h2>
          <a href="{{ route('admin.riwayat-transaksi') }}" class="fs-xs fw-bold">Lihat Semua <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="table-card-custom mb-0 dash-scroll trx-terbaru-scroll">
          <table class="table-custom">
            <thead>
              <tr>
                <th>No. Transaksi</th>
                <th>Pembeli</th>
                <th>Kasir</th>
                <th class="text-end">Total</th>
                <th class="text-center">Metode</th>
                <th class="text-end">Waktu</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($transaksiTerbaru as $trx)
                <tr>
                  <td class="table-order-id text-nowrap">{{ $trx->kode }}</td>
                  <td>{{ $trx->nama_pembeli ?: 'Umum' }}</td>
                  <td>{{ $trx->kasir->name ?? '-' }}</td>
                  <td class="text-end table-amount text-nowrap">Rp {{ number_format($trx->total_harga, 0, ',', '.') }}</td>
                  <td class="text-center">
                    <span class="badge-table {{ $trx->metode_bayar == 'cash' ? 'success' : ($trx->metode_bayar == 'qris' ? 'pending' : 'failed') }}">
                      {{ strtoupper($trx->metode_bayar) }}
                    </span>
                  </td>
                  <td class="text-end text-nowrap">
                    {{ $trx->tanggal->format('H:i') }}
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="6" class="text-center text-muted-green py-4">Belum ada transaksi hari ini</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Menu Favorit Pelanggan: foto dari Data Master, dihitung dari transaksi nyata -->
    <div class="col-12">
      <div class="card mb-0">
        <div class="card-header">
          <h2 class="card-title">Menu Favorit Pelanggan</h2>
          <div class="fav-tabs" role="tablist" id="fav-tabs">
            @foreach (['kombo' => 'Kombo', 'ukuran' => 'Ukuran', 'filling' => 'Filling', 'topping' => 'Topping Luar'] as $key => $label)
              <button type="button" class="fav-tab {{ $loop->first ? 'active' : '' }}" data-fav="{{ $key }}">{{ $label }}</button>
            @endforeach
          </div>
        </div>

        @php $labelFav = ['kombo' => 'Kombo', 'ukuran' => 'Ukuran', 'filling' => 'Filling', 'topping' => 'Topping Luar']; @endphp
        @foreach ($favorit as $key => $daftar)
          <div class="fav-panel {{ $loop->first ? '' : 'd-none' }}" data-fav-panel="{{ $key }}">
            @php
              $maks   = collect($daftar)->max('total') ?: 1;
              $jumlah = collect($daftar)->sum('total') ?: 1;
            @endphp

            @if (count($daftar))
              <div class="fav-grid">
                @foreach ($daftar as $i => $f)
                  @php
                    $detail = $f['detail'] ?? [];
                    $payload = [
                      'nama'     => $f['nama'],
                      'sub'      => $f['sub'] ?? null,
                      'foto'     => $f['foto'] ?? null,
                      'total'    => number_format($f['total'], 0, ',', '.'),
                      'rank'     => $i + 1,
                      'share'    => round($f['total'] / $jumlah * 100),
                      'kategori' => $labelFav[$key] ?? ucfirst($key),
                      'detail'   => $detail,
                    ];
                  @endphp
                  <div class="fav-item {{ $i === 0 ? 'is-top' : '' }}">
                    <div class="fav-thumb">
                      @if ($f['foto'])
                        <img src="{{ $f['foto'] }}" alt="{{ $f['nama'] }}" loading="lazy"
                             onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'fav-empty',innerHTML:'<i class=&quot;bi bi-egg-fried&quot;></i>'}))">
                      @else
                        <div class="fav-empty"><i class="bi bi-egg-fried"></i></div>
                      @endif
                      <span class="fav-rank">{{ $i + 1 }}</span>
                      @if ($i === 0)
                        <span class="fav-crown"><i class="bi bi-trophy-fill"></i> Terlaris</span>
                      @endif
                    </div>

                    <div class="fav-info">
                      <div class="fav-name">{{ $f['nama'] }}</div>
                      @if ($f['sub'])
                        <div class="fav-sub">{{ $f['sub'] }}</div>
                      @endif

                      <div class="fav-bar"><i style="width: {{ round($f['total'] / $maks * 100) }}%"></i></div>

                      <div class="fav-meta">
                        <div class="fav-count"><b>{{ number_format($f['total'], 0, ',', '.') }}</b><span>terjual</span></div>
                        <span class="fav-share">{{ round($f['total'] / $jumlah * 100) }}%</span>
                      </div>

                      {{-- Rincian hanya untuk tab Kombo --}}
                      @if ($key === 'kombo')
                        <button type="button" class="fav-more" data-bs-toggle="modal" data-bs-target="#modalFavorit"
                          data-fav="{{ json_encode($payload, JSON_UNESCAPED_UNICODE) }}">
                          Lihat selengkapnya <i class="bi bi-arrow-right"></i>
                        </button>
                      @endif
                    </div>
                  </div>
                @endforeach
              </div>
            @else
              <div class="fav-none">
                <div class="fav-none-icon"><i class="bi bi-trophy"></i></div>
                <div class="fav-none-title">Belum ada data penjualan</div>
                <div class="fav-none-hint">Kombo, ukuran, filling, dan topping favorit akan muncul setelah ada transaksi.</div>
              </div>
            @endif
          </div>
        @endforeach
      </div>
    </div>

  </div>

  {{-- ============ MODAL DETAIL MENU FAVORIT ============ --}}
  <div class="modal fade" id="modalFavorit" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="fm-header">
          <h5><i class="bi bi-trophy-fill me-2"></i>Detail Menu Favorit</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>

        <div class="fm-body">
          <div class="fm-photo" id="fm-photo"></div>

          <div class="fm-info">
            <span class="fm-badge" id="fm-kategori"></span>
            <div class="fm-name" id="fm-nama"></div>
            <div class="fm-sub" id="fm-sub"></div>

            <div class="fm-stats">
              <div class="fm-stat"><b id="fm-rank"></b><span>Peringkat</span></div>
              <div class="fm-stat"><b id="fm-total"></b><span>Terjual</span></div>
              <div class="fm-stat"><b id="fm-share"></b><span>Kontribusi</span></div>
            </div>

            <div class="fm-detail" id="fm-detail-wrap">
              <div class="fm-detail-title">Rincian</div>
              <div id="fm-detail"></div>
            </div>
          </div>
        </div>

        <div class="fm-foot">
          <button type="button" class="btn-custom btn-custom-primary w-100 justify-content-center" data-bs-dismiss="modal">
            Tutup
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Data dikirim ke dashboard.js — SEMUA angka di sini murni dari database,
       tidak ada satupun nilai dummy/hardcode. -->
  <script>
    window.dashboardData = {
      labelHari: @json($labelHari),
      dataPenjualan: @json($dataPenjualan),
      dataTransaksi: @json($dataTransaksi),
      dataPorsi: @json($dataPorsi),
      stok: [{{ $stokAman }}, {{ $stokMenipis }}, {{ $stokHabis }}]
    };
  </script>

@endsection

@push('scripts')
  <script src="{{ asset('assets/assets_dashboard/js/dashboard.js') }}"></script>
  <script src="{{ asset('assets_user/admin/dashboard/dashboard_favorit.js') }}"></script>
@endpush
