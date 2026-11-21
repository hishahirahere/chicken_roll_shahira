@extends('layouts.admin')

@section('title', 'Riwayat Transaksi - Chicken Crunchy Roll')
@section('breadcrumb', 'Riwayat Transaksi')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets_user/admin/operasional/riwayat_transaksi.css') }}">
@endpush

@section('content')
  @php
    $jumlahTrx = (int) $ringkasan['jumlah'];
    $totalTrx  = (int) $ringkasan['total'];
    $rataTrx   = $jumlahTrx > 0 ? (int) round($totalTrx / $jumlahTrx) : 0;
  @endphp

  <div class="page-header">
    <div class="page-header-icon"><i class="bi bi-clock-history"></i></div>
    <div>
      <h1 class="page-title">Riwayat Transaksi</h1>
      <p class="page-subtitle">Semua kasir &middot; {{ $hariIni ? "Hari ini, " : "" }}{{ \Carbon\Carbon::parse($dari)->translatedFormat("d M Y") }}@if ($dari !== $sampai) &ndash; {{ \Carbon\Carbon::parse($sampai)->translatedFormat("d M Y") }}@endif</p>
    </div>
  </div>

  {{-- ===== RINGKASAN ===== --}}
  <div class="trx-stats">
    <div class="trx-stat">
      <div class="trx-stat-icon"><i class="bi bi-receipt"></i></div>
      <div>
        <span class="trx-stat-label">Jumlah transaksi</span>
        <span class="trx-stat-value">{{ number_format($jumlahTrx, 0, ',', '.') }}</span>
      </div>
    </div>
    <div class="trx-stat is-main">
      <div class="trx-stat-icon"><i class="bi bi-cash-stack"></i></div>
      <div>
        <span class="trx-stat-label">Total nilai</span>
        <span class="trx-stat-value">Rp {{ number_format($totalTrx, 0, ',', '.') }}</span>
      </div>
    </div>
    <div class="trx-stat">
      <div class="trx-stat-icon"><i class="bi bi-graph-up-arrow"></i></div>
      <div>
        <span class="trx-stat-label">Rata-rata per transaksi</span>
        <span class="trx-stat-value">Rp {{ number_format($rataTrx, 0, ',', '.') }}</span>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-banner">
      <h2>
        Daftar Transaksi
        <span style="margin-left:.6rem;font-size:.78rem;font-weight:800;background:rgba(255,255,255,.2);padding:.2rem .75rem;border-radius:50rem;vertical-align:middle;">{{ number_format($transaksi->total(), 0, ',', '.') }} data</span>
      </h2>
    </div>

    {{-- ===== FILTER ===== --}}
    <div class="trx-toolbar">
      <form method="GET" action="{{ route('admin.riwayat-transaksi') }}" class="row g-2 align-items-end">
        <div class="col-6 col-md-2">
          <label class="form-label">Dari tanggal</label>
          <input type="date" name="dari" value="{{ $dari }}" class="form-control">
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label">Sampai tanggal</label>
          <input type="date" name="sampai" value="{{ $sampai }}" class="form-control">
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label">Kasir</label>
          <select name="kasir" class="form-select">
            <option value="">Semua kasir</option>
            @foreach ($kasirList as $k)
              <option value="{{ $k->id }}" @selected(request('kasir') == $k->id)>{{ $k->name }}</option>
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
        <div class="col-12 col-md-4">
          <label class="form-label">No. transaksi / nama pembeli</label>
          <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="mis. TRX-00012 atau nama pembeli">
        </div>
        <div class="col-12 d-flex gap-2 pt-1">
          <button type="submit" class="btn-custom btn-custom-primary justify-content-center">
            <i class="bi bi-funnel"></i> Terapkan
          </button>
          <a href="{{ route('admin.riwayat-transaksi') }}" class="btn-custom justify-content-center"
             style="background:#fff;border:2px solid var(--bs-danger,#7A1E1E);color:var(--bs-danger,#7A1E1E);font-weight:700;">
            <i class="bi bi-arrow-counterclockwise"></i> Hari ini
          </a>
        </div>
      </form>
    </div>

    {{-- ===== TABEL ===== --}}
    <div class="table-card-custom mb-0">
      <div class="trx-scroll">
        <table class="table-custom trx-table">
          <thead>
            <tr>
              <th>No. Transaksi</th>
              <th>Tanggal &amp; Jam</th>
              <th>Pembeli</th>
              <th>Kasir</th>
              <th class="text-center">Item</th>
              <th class="text-end">Total</th>
              <th class="text-center">Metode</th>
              <th class="text-end">Aksi</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($transaksi as $t)
              <tr>
                <td><span class="trx-code">{{ $t->kode }}</span></td>
                <td class="text-nowrap">
                  <div class="trx-date">{{ $t->tanggal->format('d/m/Y') }}</div>
                  <div class="trx-time">{{ $t->tanggal->format('H:i') }}</div>
                </td>
                <td>{{ $t->nama_pembeli ?: 'Umum' }}</td>
                <td>{{ $t->kasir->name ?? '-' }}</td>
                <td class="text-center"><span class="trx-qty">{{ $t->items_count }}</span></td>
                <td class="text-end text-nowrap trx-money">Rp {{ number_format($t->total_harga, 0, ',', '.') }}</td>
                <td class="text-center">
                  @php $m = in_array($t->metode_bayar, ['cash', 'qris']) ? $t->metode_bayar : 'cash'; @endphp
                  <span class="trx-pay trx-pay-{{ $m }}">{{ strtoupper($t->metode_bayar) }}</span>
                </td>
                <td class="text-end text-nowrap">
                  @php
                    // Gabungkan porsi yang persis sama jadi satu baris (qty), sama seperti struk
                    $baris = $t->items->groupBy(fn ($it) => implode('|', [$it->ukuran_id, $it->filling_id, $it->cara_luar, $it->saus_celup_id, $it->bumbu_tabur_id]));
                    $detail = [
                      'kode'    => $t->kode,
                      'tanggal' => $t->tanggal->translatedFormat('d M Y H:i'),
                      'kasir'   => $t->kasir->name ?? '-',
                      'pembeli' => $t->nama_pembeli ?: 'Umum',
                      'metode'  => strtoupper($t->metode_bayar),
                      'total'   => (int) $t->total_harga,
                      'struk'   => route('pos.struk', $t),
                      'items'   => $baris->values()->map(function ($grup) {
                          $it    = $grup->first();
                          $celup = $it->cara_luar === 'saus_celup';
                          $qty   = $grup->count();
                          return [
                            'menu'     => $it->filling->nama_filling ?? '-',
                            'ukuran'   => $it->ukuran->nama_ukuran ?? '-',
                            'rasa'     => ($celup ? 'Saus Celup: ' : 'Bubuk Tabur: ') . ($celup ? ($it->sausCelup->nama_saus ?? '-') : ($it->bumbuTabur->nama_bumbu ?? '-')),
                            'harga'    => $it->harga,
                            'qty'      => $qty,
                            'subtotal' => $it->harga * $qty,
                          ];
                      })->all(),
                    ];
                  @endphp
                  <div class="trx-actions">
                    <button type="button" class="btn-icon-action btn-icon-edit" title="Lihat detail"
                      data-bs-toggle="modal" data-bs-target="#modalDetail"
                      data-detail="{{ json_encode($detail) }}">
                      <i class="bi bi-eye"></i>
                    </button>
                    <a href="{{ route('pos.struk', $t) }}" class="btn-icon-action" title="Lihat &amp; cetak struk"><i class="bi bi-printer"></i></a>
                  </div>
                </td>
              </tr>
            @empty
              <tr class="trx-empty-row">
                <td colspan="8">
                  <div class="trx-empty">
                    <div class="trx-empty-icon"><i class="bi bi-receipt-cutoff"></i></div>
                    <div class="trx-empty-title">Tidak ada transaksi yang sesuai filter</div>
                    <div class="trx-empty-hint">Ubah tanggal atau filter di atas, atau klik "Hari ini" untuk kembali.</div>
                  </div>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    @if ($transaksi->hasPages())
      <div class="mt-4 d-flex justify-content-center">
        {{ $transaksi->links('pagination::bootstrap-5') }}
      </div>
    @endif
  </div>

  {{-- Modal detail transaksi / struk --}}
  <div class="modal fade" id="modalDetail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width:480px;">
      <div class="modal-content">
        <div class="modal-header border-0 pb-0">
          <div class="d-flex align-items-center gap-2">
            <span class="struk-icon"><i class="bi bi-receipt"></i></span>
            <h5 class="modal-title fw-bold mb-0">Detail Transaksi</h5>
          </div>
          <button type="button" class="btn-close no-print" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>

        <div class="modal-body">
          <div class="row g-3 mb-3">
            <div class="col-6"><div class="lbl">No Transaksi</div><div class="val" id="d-kode"></div></div>
            <div class="col-6"><div class="lbl">Metode Bayar</div><div class="val" id="d-metode"></div></div>
            <div class="col-6"><div class="lbl">Tanggal &amp; Waktu</div><div class="val" id="d-tanggal"></div></div>
            <div class="col-6"><div class="lbl">Total</div><div class="val" id="d-total"></div></div>
            <div class="col-6"><div class="lbl">Kasir</div><div class="val" id="d-kasir"></div></div>
            <div class="col-6"><div class="lbl">Pembeli</div><div class="val" id="d-pembeli"></div></div>
            <div class="col-6"><div class="lbl">Status</div><span class="badge-table success">Lunas</span></div>
          </div>

          <div class="fw-bold mb-2">Item yang Dibeli</div>
          <table class="table table-sm struk-items mb-0">
            <thead>
              <tr><th>No</th><th>Menu</th><th>Ukuran</th><th class="text-end">Harga</th><th class="text-center">Qty</th><th class="text-end">Subtotal</th></tr>
            </thead>
            <tbody id="d-items"></tbody>
          </table>
          <div class="struk-total d-flex justify-content-between">
            <span>Total</span><span id="d-total2"></span>
          </div>
        </div>

        <div class="modal-footer border-0 no-print">
          <button type="button" id="d-struk" data-cetak-struk="#" data-css="{{ asset('assets_user/kasir/riwayat_transaksi/struk.css') }}" class="btn-custom btn-custom-primary justify-content-center"><i class="bi bi-printer"></i> Cetak Struk</button>
        </div>
      </div>
    </div>
  </div>

@endsection

@push('scripts')
<script src="{{ asset('assets_user/unduh.js') }}"></script>
<script src="{{ asset('assets_user/admin/operasional/riwayat_transaksi.js') }}"></script>
@endpush
