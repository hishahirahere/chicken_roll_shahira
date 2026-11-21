@extends('layouts.kasir')

@section('title', 'Transaksi Penjualan - Chicken Crunchy Roll')

@section('navbar_center')
  <div class="page-search-wrapper">
    <input type="text" class="page-search-input" id="pos-search" placeholder="Cari menu...">
    <i class="bi bi-search"></i>
  </div>
@endsection

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets_user/kasir/transaksi_penjualan/pos.css') }}">
@endpush

@section('content')

  @if ($data['ukuran']->isEmpty() || $data['filling']->isEmpty() || ($data['saus']->isEmpty() && $data['bumbu']->isEmpty()))
    <div class="card">
      <div class="empty-state">
        <i class="bi bi-exclamation-circle"></i>
        <h5 class="fw-bold">Menu belum lengkap</h5>
        <p class="mb-0">Minta admin mengisi dulu Master Ukuran, Master Filling, serta Saus Celup atau Bumbu Tabur.</p>
      </div>
    </div>
  @else
    <div id="pos-root"></div>
  @endif

  {{-- ============ MODAL PEMBAYARAN ============ --}}
  <div class="modal fade" id="modalBayar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-head-pay">
          <h5><i class="bi bi-wallet2 me-2"></i>Pembayaran</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>
        <div class="p-4">
          <div class="pay-total">
            <div class="pay-total-k">Total Pembayaran</div>
            <div class="pay-total-v" id="payTotal">Rp 0</div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold">Nama Pembeli <span class="text-muted-green fw-normal">— opsional</span></label>
            <input type="text" id="payNama" maxlength="100" class="form-control" placeholder="Kosongkan kalau tidak perlu">
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold">Metode Bayar</label>
            {{-- Hanya 2 metode (Cash & QRIS): dibuat 2 kolom sama lebar supaya simetris --}}
            <style>
              #metodeGrid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
              #metodeGrid .metode-opt { width: auto; max-width: none; min-width: 0; flex: none; }
            </style>
            <div class="metode-grid" id="metodeGrid">
              <div class="metode-opt selected" data-metode="cash"><i class="bi bi-cash-coin"></i>Cash</div>
              <div class="metode-opt" data-metode="qris"><i class="bi bi-qr-code"></i>QRIS</div>
            </div>
          </div>

          <div id="cashBox" class="mb-3">
            <label class="form-label fw-bold">Uang Diterima <span class="text-muted-green fw-normal">— opsional</span></label>
            <div class="input-group">
              <span class="input-group-text">Rp</span>
              <input type="number" id="payUang" min="0" step="500" class="form-control" placeholder="0">
            </div>
            <div class="quick-cash" id="quickCash"></div>
            <div class="kembalian-box"><span>Kembalian</span><span id="payKembali">Rp 0</span></div>
          </div>

          <div id="konfirmBox" class="mb-3 d-none">
            <div class="form-check p-3 rounded" style="background:var(--bs-body-bg);">
              <input class="form-check-input ms-0 me-2" type="checkbox" id="payKonfirm">
              <label class="form-check-label fw-bold" for="payKonfirm" id="payKonfirmLbl">Pembayaran sudah diterima</label>
            </div>
          </div>

          <div class="text-danger mb-3 d-none" id="payError" style="font-size:.88rem;"></div>

          <div class="d-flex gap-2">
            <button type="button" class="btn-pos btn-pos-outline flex-fill" data-bs-dismiss="modal">Batal</button>
            <button type="button" class="btn-pos flex-fill" id="payConfirm"><i class="bi bi-check-lg"></i> Konfirmasi</button>
          </div>
        </div>
      </div>
    </div>
  </div>

@endsection

@push('scripts')
  <script>
    window.posConfig = {
      data: @json($data),
      userId: @json(auth()->id()),
      payUrl: @json(route('pos.bayar'))
    };
  </script>
  <script src="{{ asset('assets_user/kasir/transaksi_penjualan/pos.js') }}"></script>
@endpush
