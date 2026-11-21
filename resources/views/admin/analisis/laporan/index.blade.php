@extends('layouts.admin')

@section('title', $judul . ' - Chicken Crunchy Roll')
@section('breadcrumb', 'Laporan')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets_user/admin/analisis/laporan.css') }}">
@endpush

@section('content')
  @php
    // Laporan stok hanya bulanan: stok jarang berubah tiap hari, jadi harian/mingguan tidak berguna
    $stokBulanan = $jenis === 'stok';
    if ($stokBulanan) {
        $periode = 'bulanan';
    }

    $query   = request()->except(['format', 'page']);
    if ($stokBulanan) {
        $query['periode'] = 'bulanan';
        $query['bulan']   = $dari->format('Y-m');
    }
    $ekspor  = fn ($fmt) => route('admin.laporan.export', $query + ['jenis' => $jenis, 'format' => $fmt]);
    $ikonRingkas = function ($label) {
        $l = strtolower($label);
        return match (true) {
            str_contains($l, 'pendapatan') || str_contains($l, 'nilai') => 'bi-cash-stack',
            str_contains($l, 'transaksi')                                => 'bi-receipt',
            str_contains($l, 'item') || str_contains($l, 'terjual')      => 'bi-basket2',
            str_contains($l, 'menipis') || str_contains($l, 'habis')     => 'bi-exclamation-triangle',
            default                                                      => 'bi-bar-chart-line',
        };
    };
    $utamaDipilih = false;
  @endphp

  <div class="page-header">
    <div class="page-header-icon"><i class="bi bi-bar-chart-line-fill"></i></div>
    <div>
      <h1 class="page-title">Laporan</h1>
      <p class="page-subtitle">
        {{ $judul }} &middot; {{ $dari->translatedFormat('d M Y') }}@if (! $dari->isSameDay($sampai)) &ndash; {{ $sampai->translatedFormat('d M Y') }}@endif
      </p>
    </div>
  </div>

  {{-- ===== PILIHAN JENIS LAPORAN ===== --}}
  <nav class="lap-tabs">
    @foreach ($jenisList as $key => $label)
      @php
        $paramTab = request()->only(['periode', 'tanggal', 'bulan', 'dari', 'sampai']);
        if ($key === 'stok') {
            $paramTab = ['periode' => 'bulanan', 'bulan' => $dari->format('Y-m')];
        }
      @endphp
      <a href="{{ route('admin.laporan', $paramTab + ['jenis' => $key]) }}" class="lap-tab {{ $jenis === $key ? 'active' : '' }}">{{ $label }}</a>
    @endforeach
  </nav>

  <div class="card">
    <div class="card-banner">
      <h2>
        {{ $judul }}
        <span style="margin-left:.6rem;font-size:.78rem;font-weight:800;background:rgba(255,255,255,.2);padding:.2rem .75rem;border-radius:50rem;vertical-align:middle;">{{ count($baris) }} data</span>
      </h2>
    </div>

    {{-- ===== PERIODE + FILTER ===== --}}
    <div class="lap-toolbar">
      <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
        <div class="per-chips">
          @foreach ($periodeList as $key => [$label, $ikon])
            @continue($stokBulanan && $key !== 'bulanan')
            @php
              // Pindah periode = kembali ke acuan HARI INI (harian: hari ini, mingguan: 7 hari terakhir, bulanan: bulan ini).
              // Hanya "Custom" yang membawa rentang yang sedang aktif sebagai titik awal.
              $paramChip = request()->only(['varian', 'metode']) + ['jenis' => $jenis, 'periode' => $key];
              if ($key === 'custom') {
                $paramChip += ['dari' => $dari->toDateString(), 'sampai' => $sampai->toDateString()];
              }
            @endphp
            <a href="{{ route('admin.laporan', $paramChip) }}" class="per-chip {{ $periode === $key ? 'active' : '' }}">
              <i class="bi {{ $ikon }}"></i> {{ $label }}
            </a>
          @endforeach
        </div>
        <div class="per-info">
          <i class="bi bi-calendar-check"></i>
          <span>Periode aktif: <b>{{ $dari->translatedFormat('d M Y') }}@if (! $dari->isSameDay($sampai)) &ndash; {{ $sampai->translatedFormat('d M Y') }}@endif</b></span>
        </div>
      </div>

      <form method="GET" action="{{ route('admin.laporan') }}" class="row g-2 align-items-end">
        <input type="hidden" name="jenis" value="{{ $jenis }}">
        <input type="hidden" name="periode" value="{{ $periode }}">

        @if ($periode === 'harian')
          <div class="col-6 col-md-3">
            <label class="form-label">Tanggal</label>
            <input type="date" name="tanggal" value="{{ $dari->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control">
          </div>
        @elseif ($periode === 'mingguan')
          <div class="col-6 col-md-3">
            <label class="form-label">7 hari sampai tanggal</label>
            <input type="date" name="tanggal" value="{{ $sampai->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control">
          </div>
        @elseif ($periode === 'bulanan')
          <div class="col-6 col-md-3">
            <label class="form-label">Bulan</label>
            <input type="month" name="bulan" value="{{ $dari->format('Y-m') }}" max="{{ today()->format('Y-m') }}" class="form-control">
          </div>
        @else
          <div class="col-6 col-md-3">
            <label class="form-label">Dari tanggal</label>
            <input type="date" name="dari" value="{{ $dari->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control">
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label">Sampai tanggal</label>
            <input type="date" name="sampai" value="{{ $sampai->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control">
          </div>
        @endif

        @if ($jenis === 'penjualan')
          <div class="col-6 col-md-2">
            <label class="form-label">Varian</label>
            <select name="varian" class="form-select">
              <option value="">Semua</option>
              @foreach ($ukuranList as $u)
                <option value="{{ $u->id }}" @selected(request('varian') == $u->id)>{{ $u->nama_ukuran }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-6 col-md-2">
            <label class="form-label">Metode pembayaran</label>
            <select name="metode" class="form-select">
              <option value="">Semua</option>
              <option value="cash" @selected(request('metode') == 'cash')>Cash</option>
              <option value="qris" @selected(request('metode') == 'qris')>QRIS</option>
            </select>
          </div>
        @endif

        <div class="col-12 col-md-auto d-flex gap-2">
          <button type="submit" class="btn-custom btn-custom-primary justify-content-center"><i class="bi bi-funnel"></i> Terapkan</button>
          <a href="{{ route('admin.laporan', ['jenis' => $jenis]) }}" class="btn-reset"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
        </div>
      </form>
    </div>

    {{-- ===== RINGKASAN (kartu) ===== --}}
    <div class="lap-stats">
      @foreach ($ringkasan as [$label, $nilai, $tipeR])
        @php
          $utama = ! $utamaDipilih && $tipeR === 'rupiah';
          if ($utama) $utamaDipilih = true;
        @endphp
        <div class="lap-stat {{ $utama ? 'is-main' : '' }}">
          <div class="lap-stat-icon"><i class="bi {{ $ikonRingkas($label) }}"></i></div>
          <div>
            <span class="lap-stat-label">{{ $label }}</span>
            <span class="lap-stat-value">{{ $tipeR === 'rupiah' ? 'Rp ' . number_format((int) $nilai, 0, ',', '.') : number_format((int) $nilai, 0, ',', '.') }}</span>
          </div>
        </div>
      @endforeach
    </div>

    {{-- ===== EKSPOR & CETAK (di dalam kartu, dekat datanya) ===== --}}
    <div class="lap-export-card">
      <div class="lap-export-title">
        <span class="lap-export-ic"><i class="bi bi-printer-fill"></i></span>
        <div>
          <b>Ekspor Laporan</b>
          <small>{{ $judul }} · periode {{ $dari->translatedFormat('d M Y') }}@if (! $dari->isSameDay($sampai)) &ndash; {{ $sampai->translatedFormat('d M Y') }}@endif, sesuai filter di atas.</small>
        </div>
      </div>
      <div class="lap-export-btns">
        <a href="{{ $ekspor('excel') }}" class="btn-lap btn-lap-excel" data-unduh="excel" data-nama="Laporan Excel"><i class="bi bi-file-earmark-excel-fill"></i> Ekspor Excel</a>
        <a href="{{ $ekspor('pdf') }}" class="btn-lap btn-lap-pdf" data-unduh="pdf" data-nama="Laporan PDF"><i class="bi bi-file-earmark-pdf-fill"></i> Ekspor PDF</a>
      </div>
    </div>

    {{-- ===== TABEL HASIL ===== --}}
    <div class="table-card-custom mb-0 lap-scroll">
      @include('admin.analisis.laporan._tabel', ['mode' => 'web'])
    </div>

    @if (! empty($catatan))
      <p class="text-muted-green mt-3 mb-0" style="font-size:.8rem;"><i class="bi bi-info-circle"></i> {{ $catatan }}</p>
    @endif
  </div>
@push('scripts')
  <script src="{{ asset('assets_user/unduh.js') }}"></script>
@endpush
@endsection
