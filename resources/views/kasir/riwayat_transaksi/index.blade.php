@extends('layouts.kasir')

@section('title', 'Riwayat Transaksi - Chicken Crunchy Roll')
@section('breadcrumb', 'Riwayat Transaksi')

@section('content')

  @php
    $label = \Carbon\Carbon::parse($dari)->translatedFormat('d F Y');
    if ($dari !== $sampai) $label .= ' – ' . \Carbon\Carbon::parse($sampai)->translatedFormat('d F Y');
    $badge = fn ($m) => $m == 'cash' ? 'success' : ($m == 'qris' ? 'pending' : 'failed');
  @endphp

  <div class="page-header">
    <div class="page-header-icon"><i class="bi bi-clock-history"></i></div>
    <div>
      <h1 class="page-title">Riwayat Transaksi</h1>
      <p class="page-subtitle">Transaksi yang kamu catat &middot; {{ $hariIni ? 'Hari ini, ' : '' }}{{ $label }}</p>
    </div>
    <a href="{{ route('pos.index') }}" class="btn-custom btn-custom-primary"><i class="bi bi-plus-lg"></i> Transaksi Baru</a>
  </div>

  {{-- Ringkasan singkat dalam satu strip --}}
  <div class="rt-ringkas">
    <div class="rt-r">
      <span class="ico"><i class="bi bi-cash-coin"></i></span>
      <div><span class="k">Total Penjualan</span><b>Rp {{ number_format($totalPenjualan, 0, ',', '.') }}</b></div>
    </div>
    <div class="rt-r">
      <span class="ico"><i class="bi bi-receipt"></i></span>
      <div><span class="k">Transaksi</span><b>{{ number_format($jumlahTransaksi, 0, ',', '.') }}</b></div>
    </div>
    <div class="rt-r">
      <span class="ico"><i class="bi bi-bag-check"></i></span>
      <div><span class="k">Porsi Terjual</span><b>{{ number_format($jumlahPorsi, 0, ',', '.') }}</b></div>
    </div>
  </div>

  <div class="card">
    <div class="card-banner rt-banner">
      <h2>
        Daftar Transaksi
        <span style="margin-left:.6rem;font-size:.78rem;font-weight:800;background:rgba(255,255,255,.2);padding:.2rem .75rem;border-radius:50rem;vertical-align:middle;">{{ number_format($jumlahTransaksi, 0, ',', '.') }} transaksi</span>
      </h2>
      <a href="{{ route('pos.riwayat.cetak', ['dari' => $dari, 'sampai' => $sampai, 'metode' => $metode]) }}" class="rt-cetak" data-unduh="cetak" data-nama="Riwayat Transaksi">
        <i class="bi bi-printer-fill"></i> Cetak Semua Transaksi
      </a>
    </div>

    {{-- ===== FILTER ===== --}}
    <div class="rt-toolbar">
      <form method="GET" action="{{ route('pos.riwayat') }}" class="row g-2 align-items-end">
        <div class="col-6 col-md-3">
          <label class="form-label">Dari tanggal</label>
          <input type="date" name="dari" value="{{ $dari }}" max="{{ today()->toDateString() }}" class="form-control">
        </div>
        <div class="col-6 col-md-3">
          <label class="form-label">Sampai tanggal</label>
          <input type="date" name="sampai" value="{{ $sampai }}" max="{{ today()->toDateString() }}" class="form-control">
        </div>
        <div class="col-6 col-md-3">
          <label class="form-label">Metode pembayaran</label>
          <select name="metode" class="form-select">
            <option value="">Semua</option>
            <option value="cash" @selected($metode == 'cash')>Cash</option>
            <option value="qris" @selected($metode == 'qris')>QRIS</option>
          </select>
        </div>
        <div class="col-6 col-md-3 d-flex gap-2">
          <button type="submit" class="btn-custom btn-custom-primary justify-content-center flex-fill"><i class="bi bi-funnel"></i> Terapkan</button>
          <a href="{{ route('pos.riwayat') }}" class="btn-custom justify-content-center"
             style="background:#fff;border:2px solid var(--brand-forest-medium,#9c1d1d);color:var(--brand-forest-medium,#9c1d1d);font-weight:700;">Hari Ini</a>
        </div>
      </form>
    </div>

    {{-- ===== TABEL ===== --}}
    <div class="table-card-custom mb-0">
      <div class="rt-scroll">
        <table class="table-custom rt-table">
          <thead>
            <tr>
              <th>No. Transaksi</th>
              <th>Tanggal &amp; Jam</th>
              <th>Pembeli</th>
              <th class="text-center">Item</th>
              <th class="text-center">Metode</th>
              <th class="text-end">Total</th>
              <th class="text-end">Detail</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($transaksi as $t)
              <tr>
                <td class="fw-bold text-nowrap">{{ $t->kode }}</td>
                <td class="text-nowrap">{{ $t->tanggal->format('d/m/Y H:i') }}</td>
                <td>{{ $t->nama_pembeli ?: 'Umum' }}</td>
                <td class="text-center">{{ $t->items_count }}</td>
                <td class="text-center"><span class="badge-table {{ $badge($t->metode_bayar) }}">{{ strtoupper($t->metode_bayar) }}</span></td>
                <td class="text-end table-amount text-nowrap">Rp {{ number_format($t->total_harga, 0, ',', '.') }}</td>
                <td class="text-end">
                  {{-- Dibuka sebagai pop-up di tengah layar; kalau JS mati, tetap jadi link ke halaman struk --}}
                  <a href="{{ route('pos.struk', $t) }}" class="rt-struk" data-struk="{{ route('pos.struk', $t) }}" data-kode="{{ $t->kode }}">
                    <i class="bi bi-receipt"></i> Struk
                  </a>
                </td>
              </tr>
            @empty
              <tr class="rt-empty-row">
                <td colspan="7">
                  <div class="rt-empty">
                    <div class="rt-empty-icon"><i class="bi bi-receipt"></i></div>
                    <div class="rt-empty-title">Tidak ada transaksi pada filter ini</div>
                    <div class="rt-empty-hint">Ubah tanggal atau metode di atas, atau klik "Hari Ini" untuk kembali.</div>
                  </div>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    @if ($transaksi->hasPages())
      <div class="mt-4 d-flex justify-content-center">{{ $transaksi->links('pagination::bootstrap-5') }}</div>
    @endif
  </div>

  {{-- ============ MODAL STRUK (muncul di tengah layar) ============ --}}
  <div class="modal fade" id="modalStruk" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable st-dialog">
      <div class="modal-content">
        <div class="st-head">
          <h5><i class="bi bi-receipt me-2"></i>Detail Struk <span id="stKode"></span></h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>
        <div class="modal-body st-body" id="stBody"></div>
        <div class="st-foot">
          <button type="button" class="btn-custom btn-custom-primary flex-fill justify-content-center" id="stPrint" disabled>
            <i class="bi bi-printer"></i> Cetak Struk
          </button>
          <button type="button" class="btn-custom flex-fill justify-content-center" data-bs-dismiss="modal"
            style="background:#fff;border:2px solid var(--brand-forest-medium,#9c1d1d);color:var(--brand-forest-medium,#9c1d1d);font-weight:700;">
            Tutup
          </button>
        </div>
      </div>
    </div>
  </div>

@endsection

@push('styles')
  {{-- Gaya struk: sama dengan halaman struk (dipakai juga untuk cetak dari pop-up) --}}
  <link rel="stylesheet" id="struk-css" href="{{ asset('assets_user/kasir/riwayat_transaksi/struk.css') }}">

  <link rel="stylesheet" href="{{ asset('assets_user/kasir/riwayat_transaksi/riwayat.css') }}">
@endpush

@push('scripts')
  <script src="{{ asset('assets_user/unduh.js') }}"></script>
  <script src="{{ asset('assets_user/kasir/riwayat_transaksi/riwayat.js') }}"></script>
@endpush
