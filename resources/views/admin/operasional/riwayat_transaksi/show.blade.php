@extends('layouts.admin')

@section('title', 'Detail Transaksi - Chicken Crunchy Roll')
@section('breadcrumb', 'Detail Transaksi')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets_user/admin/operasional/riwayat_transaksi.css') }}">
@endpush

@section('content')
  @php
    $metode = in_array($transaksi->metode_bayar, ['cash', 'qris']) ? $transaksi->metode_bayar : 'cash';
  @endphp

  <div class="page-header">
    <div class="page-header-icon"><i class="bi bi-receipt-cutoff"></i></div>
    <div>
      <h1 class="page-title">Detail Transaksi</h1>
      <p class="page-subtitle">{{ $transaksi->kode }} &middot; {{ $transaksi->tanggal->translatedFormat('d F Y, H:i') }}</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <button type="button" data-cetak-struk="{{ route('pos.struk', $transaksi) }}" data-css="{{ asset('assets_user/kasir/riwayat_transaksi/struk.css') }}" class="btn-custom btn-custom-primary"><i class="bi bi-printer"></i> Cetak Struk</button>
      <a href="{{ route('admin.riwayat-transaksi') }}" class="btn-custom btn-custom-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
      </a>
    </div>
  </div>

  <div class="row g-4">
    {{-- ===== INFORMASI ===== --}}
    <div class="col-lg-4">
      <div class="card h-100 mb-0">
        <div class="dt-total">
          <small><span>Total pembayaran</span><span class="badge-table success">Lunas</span></small>
          <span class="nilai">Rp {{ number_format($transaksi->total_harga, 0, ',', '.') }}</span>
        </div>

        <ul class="dt-info">
          <li>
            <span class="ic"><i class="bi bi-receipt"></i></span>
            <div><span class="lbl">Nomor transaksi</span><span class="val">{{ $transaksi->kode }}</span></div>
          </li>
          <li>
            <span class="ic"><i class="bi bi-calendar-event"></i></span>
            <div><span class="lbl">Tanggal &amp; waktu</span><span class="val">{{ $transaksi->tanggal->format('d/m/Y H:i') }}</span></div>
          </li>
          <li>
            <span class="ic"><i class="bi bi-person-badge"></i></span>
            <div><span class="lbl">Kasir</span><span class="val">{{ $transaksi->kasir->name ?? '-' }}</span></div>
          </li>
          <li>
            <span class="ic"><i class="bi bi-person"></i></span>
            <div><span class="lbl">Pembeli</span><span class="val">{{ $transaksi->nama_pembeli ?: 'Umum' }}</span></div>
          </li>
          <li>
            <span class="ic"><i class="bi bi-wallet2"></i></span>
            <div>
              <span class="lbl">Metode bayar</span>
              <span class="dt-pay dt-pay-{{ $metode }}">{{ strtoupper($transaksi->metode_bayar) }}</span>
            </div>
          </li>
        </ul>
      </div>
    </div>

    {{-- ===== RINCIAN PESANAN ===== --}}
    <div class="col-lg-8">
      <div class="card h-100 mb-0">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h2 class="h5 fw-bold mb-0" style="color:var(--dt-text);"><i class="bi bi-bag-check" style="color:var(--dt-brand);"></i> Rincian pesanan</h2>
          <span class="dt-count">{{ $transaksi->items->count() }} porsi</span>
        </div>

        <div class="table-card-custom mb-0" style="overflow-x:auto;">
          <table class="table-custom dt-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Ukuran</th>
                <th>Filling</th>
                <th>Saus Celup</th>
                <th>Bubuk Tabur</th>
                <th class="text-end">Harga</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($transaksi->items as $i => $it)
                @php
                  $saus  = $it->cara_luar === 'saus_celup'  ? ($it->sausCelup->nama_saus ?? null) : null;
                  $bumbu = $it->cara_luar === 'bumbu_tabur' ? ($it->bumbuTabur->nama_bumbu ?? null) : null;
                @endphp
                <tr>
                  <td class="dt-no">{{ $i + 1 }}</td>
                  <td><span class="dt-size">{{ $it->ukuran->nama_ukuran ?? '-' }}</span></td>
                  <td class="fw-bold">{{ $it->filling->nama_filling ?? '-' }}</td>
                  <td>@if ($saus)<span class="dt-chip"><i class="bi bi-droplet-fill" style="color:var(--dt-brand);"></i> {{ $saus }}</span>@else<span class="dt-none">&ndash;</span>@endif</td>
                  <td>@if ($bumbu)<span class="dt-chip"><i class="bi bi-stars" style="color:var(--dt-brand);"></i> {{ $bumbu }}</span>@else<span class="dt-none">&ndash;</span>@endif</td>
                  <td class="text-end dt-money">Rp {{ number_format($it->harga, 0, ',', '.') }}</td>
                </tr>
              @empty
                <tr><td colspan="6" class="text-center text-muted-green py-4">Transaksi ini tidak punya rincian item.</td></tr>
              @endforelse
            </tbody>
            <tfoot>
              <tr>
                <td colspan="5" class="text-end fw-bold">Total</td>
                <td class="text-end dt-grand">Rp {{ number_format($transaksi->total_harga, 0, ',', '.') }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </div>

@endsection

@push('scripts')
<script src="{{ asset('assets_user/unduh.js') }}"></script>
@endpush
