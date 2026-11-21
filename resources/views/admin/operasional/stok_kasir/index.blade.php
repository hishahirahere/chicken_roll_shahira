@extends('layouts.admin')

@section('title', 'Stok Kasir - Chicken Crunchy Roll')
@section('breadcrumb', 'Stok Kasir')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets_user/admin/data_master/master.css') }}">
  <link rel="stylesheet" href="{{ asset('assets_user/admin/operasional/manajemen_stok.css') }}?v={{ @filemtime(public_path('assets_user/admin/operasional/manajemen_stok.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets_user/admin/operasional/stok_kasir.css') }}?v={{ @filemtime(public_path('assets_user/admin/operasional/stok_kasir.css')) }}">
@endpush

@section('content')
  @php
    $n = fn ($v) => number_format((float) $v, 0, ',', '.');
    $kategori = [
      'produk'  => ['Produk siap jual', 'bi-egg-fried'],
      'filling' => ['Filling', 'bi-layers-fill'],
      'saus'    => ['Saus Celup', 'bi-droplet-fill'],
      'bumbu'   => ['Bumbu Tabur', 'bi-stars'],
      'kemasan' => ['Kemasan', 'bi-box2'],
      'manual'  => ['Minyak & Gas', 'bi-fuel-pump-fill'],
    ];
    // Filling / saus / bumbu dipisah per jenis; sisanya memakai kategori bawaan
    $kunciGrup = fn ($b) => $b->kategori === 'porsi' ? $b->peran : $b->kategori;
    $total = ['aman' => 0, 'menipis' => 0, 'habis' => 0];
    foreach ($daftarKasir as $k) {
      foreach ($total as $st => $v) { $total[$st] += $k->ringkas[$st]; }
    }
    $totalItem = array_sum($total);
    // Ringkasan: kasir yang paling perlu diisi ulang tampil paling atas
    $urutKasir = $daftarKasir->sort(function ($a, $b) {
      $pa = $a->ringkas['habis'] * 1000 + $a->ringkas['menipis'];
      $pb = $b->ringkas['habis'] * 1000 + $b->ringkas['menipis'];
      return $pb <=> $pa ?: strcasecmp($a->name, $b->name);
    })->values();
    $perluDiisi = $daftarKasir->filter(fn ($k) => $k->ringkas['habis'] + $k->ringkas['menipis'] > 0 && $k->bahan->where('jumlah', '>', 0)->isNotEmpty())->count();
  @endphp

  <div class="page-header">
    <div class="page-header-icon"><i class="bi bi-person-badge-fill"></i></div>
    <div>
      <h1 class="page-title">Stok Kasir</h1>
      <p class="page-subtitle">Pantau stok yang sedang dipegang tiap kasir. Bertambah saat kamu memberi paket (di Manajemen Stok) dan berkurang otomatis tiap ada pembayaran.</p>
    </div>
  </div>

  <div class="ms-stats">
    <div class="ms-stat">
      <div class="ms-stat-icon"><i class="bi bi-people-fill"></i></div>
      <div><span class="ms-stat-label">Jumlah kasir</span><span class="ms-stat-value">{{ $daftarKasir->count() }}</span></div>
    </div>
    <div class="ms-stat is-warn {{ $total['menipis'] > 0 ? 'is-alert' : '' }}">
      <div class="ms-stat-icon"><i class="bi bi-exclamation-triangle"></i></div>
      <div><span class="ms-stat-label">Item menipis</span><span class="ms-stat-value">{{ $total['menipis'] }}</span></div>
    </div>
    <div class="ms-stat is-bad {{ $total['habis'] > 0 ? 'is-alert' : '' }}">
      <div class="ms-stat-icon"><i class="bi bi-x-octagon"></i></div>
      <div><span class="ms-stat-label">Item habis</span><span class="ms-stat-value">{{ $total['habis'] }}</span></div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-banner">
      <h2><i class="bi bi-funnel-fill me-2"></i>Lihat Stok Kasir</h2>
    </div>

    @if ($daftarKasir->isEmpty())
      <div class="text-center text-muted-green p-4">Belum ada akun kasir.</div>
    @else
      {{-- ===== FILTER ===== --}}
      <div class="sk-filter" id="sk-filter">
        <div class="sk-filter-row">
          <span class="sk-filter-label"><i class="bi bi-person-fill"></i> Kasir</span>
          <select id="sk-kasir" class="form-select sk-select sk-select-kasir" aria-label="Pilih kasir">
            <option value="semua" selected>Semua kasir ({{ $daftarKasir->count() }})</option>
            @foreach ($daftarKasir as $k)
              @php $perlu = $k->ringkas['habis'] + $k->ringkas['menipis']; @endphp
              <option value="{{ $k->id }}">{{ $k->name }}@if ($perlu > 0) · {{ $perlu }} perlu diisi @endif</option>
            @endforeach
          </select>
          <button type="button" class="sk-back" id="sk-kembali" hidden><i class="bi bi-arrow-left"></i> Semua kasir</button>
        </div>

        <div class="sk-filter-row sk-filter-tools">
          <div class="mc-filter" role="group" aria-label="Filter status stok" id="sk-status">
            <button type="button" class="on" data-status="semua">Semua<b>{{ $totalItem }}</b></button>
            <button type="button" data-status="habis">Habis<b>{{ $total['habis'] }}</b></button>
            <button type="button" data-status="menipis">Menipis<b>{{ $total['menipis'] }}</b></button>
            <button type="button" data-status="aman">Aman<b>{{ $total['aman'] }}</b></button>
          </div>

          <select id="sk-kategori" class="form-select sk-select" aria-label="Filter jenis stok">
            <option value="semua">Semua jenis</option>
            @foreach ($kategori as $kode => [$judul])
              <option value="{{ $kode }}">{{ $judul }}</option>
            @endforeach
          </select>

          <div class="page-search-wrapper" style="max-width:260px;">
            <input type="text" class="page-search-input" id="sk-cari" placeholder="Cari bahan / produk...">
            <i class="bi bi-search"></i>
          </div>
        </div>
      </div>

      {{-- ===== RINGKASAN SEMUA KASIR: satu baris per kasir, tinggi seragam, yang paling perlu diisi di atas ===== --}}
      <div class="sk-ringkasan" id="sk-ringkasan">
        <div class="sk-rscroll">
          <table class="sk-rtable">
            <thead>
              <tr>
                <th>Kasir</th>
                <th class="text-center">Habis</th>
                <th class="text-center">Menipis</th>
                <th class="text-center">Aman</th>
                <th class="sk-col-perlu">Perlu diisi</th>
                <th class="text-end"></th>
              </tr>
            </thead>
            <tbody>
              @foreach ($urutKasir as $k)
                @php
                  $punyaR = $k->bahan->filter(fn ($b) => $b->jumlah > 0);
                  $butuh = $k->bahan->whereIn('status', ['habis', 'menipis'])->sortBy(fn ($b) => $b->status === 'habis' ? 0 : 1)->values();
                @endphp
                <tr class="sk-rrow" data-kasir="{{ $k->id }}">
                  <td>
                    <div class="sk-who">
                      <span class="sk-avatar"><i class="bi bi-person-fill"></i></span>
                      <div class="sk-who-text">
                        <b title="{{ $k->name }}">{{ $k->name }}</b>
                        @if (($k->status ?? 'aktif') !== 'aktif')<small class="sk-off">nonaktif</small>@endif
                        @if ($punyaR->isEmpty())<small class="sk-belum">Belum diberi paket</small>@endif
                      </div>
                    </div>
                  </td>
                  <td class="text-center"><span class="sk-num is-habis" data-hitung="habis">{{ $k->ringkas['habis'] }}</span></td>
                  <td class="text-center"><span class="sk-num is-menipis" data-hitung="menipis">{{ $k->ringkas['menipis'] }}</span></td>
                  <td class="text-center"><span class="sk-num is-aman" data-hitung="aman">{{ $k->ringkas['aman'] }}</span></td>
                  <td class="sk-col-perlu">
                    @if ($butuh->isEmpty())
                      <span class="sk-ok"><i class="bi bi-check-circle-fill"></i> Semua aman</span>
                    @else
                      <span class="sk-butuh" title="{{ $butuh->pluck('nama')->implode(', ') }}">{{ $butuh->take(3)->pluck('nama')->implode(', ') }}@if ($butuh->count() > 3) <em>+{{ $butuh->count() - 3 }}</em>@endif</span>
                    @endif
                  </td>
                  <td class="text-end"><button type="button" class="sk-lihat" data-buka="{{ $k->id }}">Lihat stok <i class="bi bi-chevron-right"></i></button></td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>

      {{-- ===== DETAIL SATU KASIR (muncul saat kasir dipilih) ===== --}}
      <div class="sk-board is-single" id="sk-board" style="display:none">
      @foreach ($daftarKasir as $k)
        @php
          $punya = $k->bahan->filter(fn ($b) => $b->jumlah > 0);
          $perlu = $k->ringkas['habis'] + $k->ringkas['menipis'];
        @endphp
        <section class="sk-panel" data-kasir="{{ $k->id }}">
          <header class="sk-panel-head">
            <div class="sk-who">
              <span class="sk-avatar"><i class="bi bi-person-fill"></i></span>
              <div class="sk-who-text">
                <b>{{ $k->name }}</b>
                @if (($k->status ?? 'aktif') !== 'aktif')<small class="sk-off">nonaktif</small>@endif
              </div>
            </div>
            <span class="sk-sum">
              @if ($punya->isEmpty())
                <span class="ms-status ms-status-habis">Belum diberi paket</span>
              @elseif ($perlu > 0)
                <span class="ms-status ms-status-menipis">{{ $perlu }} perlu diisi</span>
              @else
                <span class="ms-status ms-status-aman">Semua aman</span>
              @endif
            </span>
          </header>

          <div class="sk-chips">
            <span class="sk-chip is-habis"><b>{{ $k->ringkas['habis'] }}</b> habis</span>
            <span class="sk-chip is-menipis"><b>{{ $k->ringkas['menipis'] }}</b> menipis</span>
            <span class="sk-chip is-aman"><b>{{ $k->ringkas['aman'] }}</b> aman</span>
          </div>

          <div class="sk-scroll">
            @foreach ($kategori as $kode => [$judul, $ikon])
              @php $daftar = $k->bahan->filter(fn ($b) => $kunciGrup($b) === $kode)->values(); @endphp
              @continue($daftar->isEmpty())
              <div class="sk-group" data-kategori="{{ $kode }}">
                <h3 class="sk-group-title"><i class="bi {{ $ikon }}"></i> {{ $judul }} <span>{{ $daftar->count() }}</span></h3>
                <div class="sk-grid">
                  @foreach ($daftar as $b)
                    <div class="sk-item sk-{{ $b->status }}" data-nama="{{ strtolower($b->nama) }}" data-status="{{ $b->status }}" data-kategori="{{ $kode }}">
                      <span class="sk-foto">@if ($b->foto)<img src="{{ asset('storage/' . $b->foto) }}" alt="" loading="lazy">@else<i class="bi {{ $b->ikon }}"></i>@endif</span>
                      <div class="sk-info">
                        <b title="{{ $b->nama }}">{{ $b->nama }}</b>
                        <small title="{{ $b->detail }}">{{ $b->detail }}</small>
                        <span class="sk-bar"><i style="width: {{ (int) ($b->persen ?? 0) }}%"></i></span>
                      </div>
                      <div class="sk-qty">
                        <strong>{{ $n($b->jumlah) }}</strong><small>{{ $b->satuan }}</small>
                        <span class="ms-status ms-status-{{ $b->status }}">{{ ucfirst($b->status) }}</span>
                      </div>
                    </div>
                  @endforeach
                </div>
              </div>
            @endforeach

            <div class="sk-empty">Tidak ada yang cocok dengan filter ini.</div>
          </div>

          @if ($k->riwayat->isNotEmpty())
            <details class="sk-riwayat">
              <summary><i class="bi bi-clock-history"></i> Riwayat produk terakhir ({{ $k->riwayat->count() }})</summary>
              <div class="sk-riwayat-list">
                @foreach ($k->riwayat as $r)
                  <div class="sk-r {{ $r->jenis }}">
                    <span class="sk-r-ic"><i class="bi {{ $r->jenis === 'masuk' ? 'bi-box-arrow-in-down' : 'bi-cart-check' }}"></i></span>
                    <div class="sk-r-t"><b>{{ $r->nama_ukuran }}</b><small>{{ $r->keterangan }} · {{ \Carbon\Carbon::parse($r->tanggal)->translatedFormat('d M Y') }}</small></div>
                    <div class="sk-r-n">{{ $r->jenis === 'masuk' ? '+' : '-' }}{{ $r->pcs }} pcs<small>sisa {{ $r->stok_sesudah }}</small></div>
                  </div>
                @endforeach
              </div>
            </details>
          @endif
        </section>
      @endforeach
      </div>

      <div class="sk-empty-all" id="sk-kosong">Tidak ada stok yang cocok dengan filter atau pencarian.</div>
    @endif
  </div>
@endsection

@push('scripts')
  <script src="{{ asset('assets_user/admin/operasional/stok_kasir.js') }}?v={{ @filemtime(public_path('assets_user/admin/operasional/stok_kasir.js')) }}"></script>
@endpush
