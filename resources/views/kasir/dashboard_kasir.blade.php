@extends('layouts.kasir')

@section('title', 'Dashboard - Chicken Crunchy Roll')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets_user/kasir/dashboard/dashboard.css') }}">
@endpush

@section('content')

  <div class="page-header">
    <div class="page-header-icon"><i class="bi bi-speedometer2"></i></div>
    <div>
      <h1 class="page-title">Dashboard Kasir</h1>
      <p class="page-subtitle">Ringkasan penjualanmu hari ini, {{ now()->translatedFormat('l, d F Y') }}.</p>
    </div>
    <a href="{{ route('pos.index') }}" class="btn-custom btn-custom-primary">
      <i class="bi bi-plus-lg"></i> Transaksi Baru
    </a>
  </div>

  <div class="row g-4">

    {{-- ===== 3 KARTU STATISTIK ===== --}}
    <div class="col-12">
      <div class="row g-4">

        <div class="col-md-4">
          <div class="card stat-highlight-card">
            <div class="position-relative z-index-2">
              <div class="stat-icon-circle"><i class="bi bi-cash-coin"></i></div>
              <span class="stat-label">Penjualanmu Hari Ini</span>
              <div class="stat-value">Rp {{ number_format($penjualanHariIni, 0, ',', '.') }}</div>
              <div class="trend-badge {{ $trendPenjualan >= 0 ? 'trend-up' : 'trend-down' }}">
                <i class="bi bi-arrow-{{ $trendPenjualan >= 0 ? 'up' : 'down' }}-right"></i>
                <span>{{ abs($trendPenjualan) }}% dari kemarin</span>
              </div>
            </div>
            <svg class="stat-highlight-bg-shape z-index-1" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
              <g transform="translate(50,50)">
                <rect x="-6" y="-45" width="12" height="90" rx="6" fill="#e8a33d" />
                <rect x="-6" y="-45" width="12" height="90" rx="6" fill="#e8a33d" transform="rotate(60)" />
                <rect x="-6" y="-45" width="12" height="90" rx="6" fill="#e8a33d" transform="rotate(120)" />
              </g>
            </svg>
          </div>
        </div>

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
            <div class="sparkline-container sparkline-card-footer"><div id="transaksi-sparkline"></div></div>
          </div>
        </div>

        <div class="col-md-4">
          <div class="card card-stat d-flex flex-column justify-content-between">
            <div>
              <div class="stat-icon-circle"><i class="bi bi-bag-check-fill"></i></div>
              <span class="stat-label">Item Terjual</span>
              <div class="stat-value">{{ $itemTerjual }} item</div>
              <div class="trend-badge trend-up">
                <i class="bi bi-calculator"></i>
                <span>Rata-rata Rp {{ number_format($rataRata, 0, ',', '.') }} / transaksi</span>
              </div>
            </div>
            <div class="sparkline-container sparkline-card-footer"><div id="item-sparkline"></div></div>
          </div>
        </div>

      </div>
    </div>

    {{-- ===== GRAFIK 7 HARI ===== --}}
    <div class="col-xl-9 col-lg-8">
      <div class="card mb-0">
        <div class="card-header mb-2">
          <h2 class="card-title">Penjualanmu 7 Hari Terakhir</h2>
          <div class="d-flex gap-3 align-items-center">
            <div class="chart-legend-item"><span class="legend-dot bg-forest-medium"></span><span class="chart-legend-label">Penjualan</span></div>
            <div class="chart-legend-item"><span class="legend-dot bg-lime-accent"></span><span class="chart-legend-label">Jumlah Transaksi</span></div>
          </div>
        </div>
        <div id="revenue-chart"></div>
      </div>
    </div>

    {{-- ===== DONUT METODE BAYAR ===== --}}
    <div class="col-xl-3 col-lg-4">
      <div class="card h-100 mb-0 d-flex flex-column justify-content-between">
        <div class="card-header mb-1"><h2 class="card-title">Metode Bayar</h2></div>
        <div id="metode-chart"></div>
        <div class="chart-legends-container flex-column align-items-stretch gap-2">
          @foreach ([['Cash', '#9c1d1d', 'cash'], ['QRIS', '#22C55E', 'qris']] as [$nama, $warna, $key])
            <div class="d-flex justify-content-between align-items-center">
              <span class="chart-legend-item"><span class="legend-dot" style="background-color:{{ $warna }}"></span> {{ $nama }}</span>
              <span class="fs-xs text-muted-green">{{ $metodeBayar[$key] ?? 0 }} transaksi</span>
            </div>
          @endforeach
        </div>
      </div>
    </div>

    {{-- ===== TRANSAKSI TERBARU ===== --}}
    <div class="col-lg-8">
      <div class="card h-100 mb-0">
        <div class="card-header">
          <h2 class="card-title">Transaksi Terbaru</h2>
          <a href="{{ route('pos.riwayat') }}" class="fs-xs fw-bold">Lihat Semua <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="table-card-custom mb-0" style="overflow-x:auto">
          <table class="table-custom">
            <thead><tr><th>Kode</th><th>Pembeli</th><th>Waktu</th><th>Metode</th><th>Total</th></tr></thead>
            <tbody>
              @forelse ($transaksiTerbaru as $trx)
                <tr>
                  <td class="table-order-id">{{ $trx->kode }}</td>
                  <td>{{ $trx->nama_pembeli }}</td>
                  <td>{{ \Carbon\Carbon::parse($trx->tanggal)->format('H:i') }}</td>
                  <td><span class="badge-table {{ $trx->metode_bayar == 'cash' ? 'success' : ($trx->metode_bayar == 'qris' ? 'pending' : 'failed') }}">{{ strtoupper($trx->metode_bayar) }}</span></td>
                  <td class="table-amount">Rp {{ number_format($trx->total_harga, 0, ',', '.') }}</td>
                </tr>
              @empty
                <tr><td colspan="5" class="text-center text-muted-green py-4">Belum ada transaksi hari ini</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- ===== VARIAN TERLARIS HARI INI (foto dari Data Master) ===== --}}
    <div class="col-lg-4">
      <div class="card h-100 mb-0">
        <div class="card-header"><h2 class="card-title">Varian Terlaris Hari Ini</h2></div>
        <div class="varian-list">
          @forelse ($varianTerlaris as $i => $v)
            <div class="varian-row">
              <span class="varian-rank">{{ $i + 1 }}</span>
              @if ($v->foto)
                <img src="{{ asset('storage/' . $v->foto) }}" alt="{{ $v->nama_ukuran }}" class="varian-img" loading="lazy"
                     onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'varian-img varian-img-empty',innerHTML:'<i class=&quot;bi bi-egg-fried&quot;></i>'}))">
              @else
                <div class="varian-img varian-img-empty"><i class="bi bi-egg-fried"></i></div>
              @endif
              <div class="varian-info">
                <div class="varian-name">Chicken Crunchy Roll {{ $v->nama_ukuran }}</div>
                <div class="varian-sub">{{ $v->nama_filling }}</div>
              </div>
              <div class="varian-count"><b>{{ $v->total }}</b><span>terjual</span></div>
            </div>
          @empty
            <p class="text-muted-green text-center py-4 mb-0 fs-xs">Belum ada penjualan hari ini.</p>
          @endforelse
        </div>
      </div>
    </div>

  </div>

  <script>
    window.kasirData = {
      labelHari: @json($labelHari),
      dataPenjualan: @json($dataPenjualan),
      dataTransaksi: @json($dataTransaksi),
      dataItem: @json($dataItem),
      metode: [{{ $metodeBayar['cash'] ?? 0 }}, {{ $metodeBayar['qris'] ?? 0 }}]
    };
  </script>
@endsection

@push('scripts')
  <script src="{{ asset('assets/assets_dashboard/libs/apexcharts/apexcharts.min.js') }}"></script>
  <script src="{{ asset('assets_user/kasir/dashboard/dashboard.js') }}"></script>
@endpush
