@extends(auth()->user()->role === 'admin' ? 'layouts.admin' : 'layouts.kasir')

@section('title', 'Struk ' . $transaksi->kode . ' - Chicken Crunchy Roll')
@section('breadcrumb', 'Struk')

@push('styles')
<link rel="stylesheet" id="struk-css" href="{{ asset('assets_user/kasir/riwayat_transaksi/struk.css') }}">
<link rel="stylesheet" href="{{ asset('assets_user/kasir/riwayat_transaksi/struk_halaman.css') }}">
@endpush

@section('content')
@php
  $isAdmin = auth()->user()->role === 'admin';
  $kembali = $isAdmin ? route('admin.riwayat-transaksi') : route('pos.riwayat');
  // Gabungkan porsi yang persis sama jadi satu baris (qty x harga)
  $baris = $transaksi->items->groupBy(fn ($it) => implode('|', [$it->ukuran_id, $it->filling_id, $it->cara_luar, $it->saus_celup_id, $it->bumbu_tabur_id]));
  $jumlahPorsi = $transaksi->items->count();
  $rp = fn ($n) => 'Rp ' . number_format((int) $n, 0, ',', '.');

  // Uang diterima & kembalian hanya untuk pembayaran CASH (QRIS tidak ada).
  // Dibaca lewat getAttributes() supaya aman walau kolomnya belum ada.
  $attr = $transaksi->getAttributes();
  $uangDiterima = ($transaksi->metode_bayar === 'cash' && isset($attr['uang_diterima']) && (int) $attr['uang_diterima'] > 0)
      ? (int) $attr['uang_diterima']
      : null;
  $kembalian = $uangDiterima !== null ? max(0, $uangDiterima - (int) $transaksi->total_harga) : 0;

  $metodeLabel = strtoupper($transaksi->metode_bayar);

  // Tampilan "berhasil" hanya tepat setelah kasir menyelesaikan pembayaran (session 'success' dari PosController)
  $baruSaja = ! $isAdmin && session()->has('success');
@endphp

<div class="page-header">
  <div class="page-header-icon"><i class="bi bi-receipt"></i></div>
  <div>
    <h1 class="page-title">{{ $baruSaja ? 'Transaksi Selesai' : 'Struk Transaksi' }}</h1>
    <p class="page-subtitle">{{ $transaksi->kode }} &middot; {{ $transaksi->tanggal->format('d/m/Y H:i') }}</p>
  </div>
</div>

<div class="sk-done">

  {{-- ============ PANEL KIRI ============ --}}
  <section class="sk-left">
    <svg class="sk-deco" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
      <g transform="translate(50,50)">
        <rect x="-6" y="-45" width="12" height="90" rx="6" fill="#e8a33d"/>
        <rect x="-6" y="-45" width="12" height="90" rx="6" fill="#e8a33d" transform="rotate(60)"/>
        <rect x="-6" y="-45" width="12" height="90" rx="6" fill="#e8a33d" transform="rotate(120)"/>
      </g>
    </svg>

    @if ($baruSaja)
      <div class="confetti" aria-hidden="true">
        @foreach (range(1, 20) as $n)
          <i style="left:{{ ($n * 5.3) % 100 }}%;animation-delay:{{ ($n % 6) * .1 }}s;background:{{ ['#ffffff', '#e8a33d', '#22c55e', '#f4c28a'][$n % 4] }}"></i>
        @endforeach
      </div>

      <div class="sk-ico" aria-hidden="true">
        <svg viewBox="0 0 52 52"><circle class="c" cx="26" cy="26" r="25"/><path class="p" d="M14 27l8 8 16-17"/></svg>
      </div>
      <h2>Pembayaran Berhasil!</h2>
      <p class="sub">Transaksi <b>{{ $transaksi->kode }}</b> sudah tersimpan<br>{{ $jumlahPorsi }} porsi</p>
      <span class="sk-pill">{{ $metodeLabel }}</span>
    @else
      <div class="sk-ico plain"><i class="bi bi-receipt"></i></div>
      <h2>Detail Transaksi</h2>
      <p class="sub"><b>{{ $transaksi->kode }}</b><br>{{ $transaksi->tanggal->format('d/m/Y H:i') }} &middot; {{ $jumlahPorsi }} porsi</p>
    @endif

    <div class="sk-tiles">
      <div class="sk-tile total"><span class="k">Total Pembayaran</span><span class="v">{{ $rp($transaksi->total_harga) }}</span></div>
      @unless ($baruSaja)
        <div class="sk-tile"><span class="k">Metode Bayar</span><span class="v">{{ $metodeLabel }}</span></div>
      @endunless
      @if ($uangDiterima !== null)
        <div class="sk-tile"><span class="k">Uang Diterima</span><span class="v">{{ $rp($uangDiterima) }}</span></div>
        @if ($kembalian > 0)
          <div class="sk-tile kembali"><span class="k">Kembalian</span><span class="v">{{ $rp($kembalian) }}</span></div>
        @else
          <div class="sk-tile"><span class="k">Kembalian</span><span class="v">Uang pas</span></div>
        @endif
      @endif
    </div>

    @if ($baruSaja)
      <a href="{{ route('pos.index') }}" class="sk-btn solid"><i class="bi bi-plus-lg"></i> Transaksi Baru</a>
      <button type="button" class="sk-btn line" onclick="cetakStruk()"><i class="bi bi-printer"></i> Cetak Struk</button>
      <a href="{{ $kembali }}" class="sk-link"><i class="bi bi-clock-history"></i> Lihat riwayat transaksi</a>
    @else
      <button type="button" class="sk-btn solid" onclick="cetakStruk()"><i class="bi bi-printer"></i> Cetak Struk</button>
      <a href="{{ $kembali }}" class="sk-btn line"><i class="bi bi-arrow-left"></i> Riwayat</a>
      @unless ($isAdmin)
        <a href="{{ route('pos.index') }}" class="sk-btn line"><i class="bi bi-plus-lg"></i> Transaksi Baru</a>
      @endunless
    @endif
  </section>

  {{-- ============ PANEL KANAN: STRUK ============ --}}
  <section class="sk-right">
    <div class="struk">
      <div class="brand">
        <img src="{{ asset('assets/images/logo_ccr.jpg') }}" alt="" onerror="this.style.display='none'">
        <h3>CHICKEN CRUNCHY ROLL</h3>
        <p>Struk Pembelian</p>
      </div>
      <hr>
      <div class="row-s"><span>No. Transaksi</span><span>{{ $transaksi->kode }}</span></div>
      <div class="row-s"><span>Tanggal</span><span>{{ $transaksi->tanggal->format('d/m/Y') }}</span></div>
      <div class="row-s"><span>Jam</span><span>{{ $transaksi->tanggal->format('H:i') }}</span></div>
      <div class="row-s"><span>Kasir</span><span>{{ $transaksi->kasir->name ?? '-' }}</span></div>
      <div class="row-s"><span>Pembeli</span><span>{{ $transaksi->nama_pembeli ?: 'Umum' }}</span></div>
      <hr>

      @foreach ($baris as $grup)
        @php
          $it = $grup->first();
          $celup = $it->cara_luar === 'saus_celup';
          $rasa = $celup ? ($it->sausCelup->nama_saus ?? '-') : ($it->bumbuTabur->nama_bumbu ?? '-');
          $qty = $grup->count();
          $harga = $it->harga;
        @endphp
        <div class="item">
          <div class="row-s">
            <span class="nm">Roll {{ $it->ukuran->nama_ukuran ?? '-' }}</span>
            <span>{{ $rp($harga * $qty) }}</span>
          </div>
          <small>Filling : {{ $it->filling->nama_filling ?? '-' }}</small>
          <small>Topping : {{ $celup ? 'Saus Celup' : 'Bubuk Tabur' }} - {{ $rasa }}</small>
          <small>{{ $qty }} x {{ $rp($harga) }}</small>
        </div>
      @endforeach

      <hr class="solid">
      <div class="row-s"><span>Jumlah Item</span><span>{{ $jumlahPorsi }}</span></div>
      <div class="row-s total"><span>TOTAL</span><span>{{ $rp($transaksi->total_harga) }}</span></div>
      <div class="row-s"><span>Metode Bayar</span><span>{{ $metodeLabel }}</span></div>
      @if ($uangDiterima !== null)
        <div class="row-s"><span>Uang Diterima</span><span>{{ $rp($uangDiterima) }}</span></div>
        @if ($kembalian > 0)
          <div class="row-s kembalian"><span>Kembalian</span><span>{{ $rp($kembalian) }}</span></div>
        @endif
      @endif
      <hr>
      <div class="thanks">Terima kasih sudah jajan di<br>Chicken Crunchy Roll!</div>
      <div class="stars" aria-hidden="true">★ ★ ★ ★ ★</div>
    </div>
  </section>

</div>
@endsection

@push('scripts')
  <script src="{{ asset('assets_user/unduh.js') }}"></script>
  <script src="{{ asset('assets_user/kasir/riwayat_transaksi/struk.js') }}"></script>
@endpush
