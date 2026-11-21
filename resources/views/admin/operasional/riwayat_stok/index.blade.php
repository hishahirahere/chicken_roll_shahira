@extends('layouts.admin')

@section('title', 'Riwayat Stok - Chicken Crunchy Roll')
@section('breadcrumb', 'Riwayat Stok')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets_user/admin/operasional/riwayat_stok.css') }}">
@endpush

@section('content')

  <div class="page-header">
    <div class="page-header-icon"><i class="bi bi-journal-text"></i></div>
    <div>
      <h1 class="page-title">Riwayat Stok</h1>
      <p class="page-subtitle">Semua catatan stok masuk dan keluar bahan baku.</p>
    </div>
  </div>

  <div class="card">
    <div class="card-banner">
      <h2>
        Riwayat Stok Masuk &amp; Keluar
        <span style="margin-left:.6rem;font-size:.78rem;font-weight:800;background:rgba(255,255,255,.2);padding:.2rem .75rem;border-radius:50rem;vertical-align:middle;">{{ number_format($kejadian->total(), 0, ',', '.') }} kejadian</span>
      </h2>
    </div>

    {{-- ===== FILTER ===== --}}
    <div class="rs-toolbar">
      <form method="GET" action="{{ route('stok.riwayat') }}" class="row g-2 align-items-end">
        <div class="col-6 col-md">
          <label class="form-label">Jenis</label>
          <select name="jenis" class="form-select">
            <option value="">Semua</option>
            <option value="masuk" @selected(request('jenis') == 'masuk')>Masuk</option>
            <option value="keluar" @selected(request('jenis') == 'keluar')>Keluar</option>
          </select>
        </div>
        <div class="col-6 col-md">
          <label class="form-label">Sumber</label>
          <select name="sumber" class="form-select">
            <option value="">Semua</option>
            <option value="manual" @selected(request('sumber') == 'manual')>Manual</option>
            <option value="paket" @selected(request('sumber') == 'paket')>Paket ke kasir</option>
            <option value="kirim" @selected(request('sumber') == 'kirim')>Kirim minyak &amp; gas</option>
            <option value="produksi" @selected(request('sumber') == 'produksi')>Produksi (lama)</option>
          </select>
        </div>
        <div class="col-6 col-md">
          <label class="form-label">Bahan baku</label>
          <select name="bahan_baku_id" class="form-select">
            <option value="">Semua</option>
            @foreach ($bahanList as $b)
              <option value="{{ $b->id }}" @selected(request('bahan_baku_id') == $b->id)>{{ $b->nama_bahan }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-6 col-md">
          <label class="form-label">Dari</label>
          <input type="date" name="dari" value="{{ request('dari') }}" class="form-control">
        </div>
        <div class="col-6 col-md">
          <label class="form-label">Sampai</label>
          <input type="date" name="sampai" value="{{ request('sampai') }}" class="form-control">
        </div>
        <div class="col-12 d-flex gap-2 pt-1">
          <button type="submit" class="btn-custom btn-custom-primary justify-content-center"><i class="bi bi-funnel"></i> Terapkan</button>
          <a href="{{ route('stok.riwayat') }}" class="btn-custom justify-content-center"
             style="background:#fff;border:2px solid var(--bs-danger,#7A1E1E);color:var(--bs-danger,#7A1E1E);font-weight:700;">
            <i class="bi bi-arrow-counterclockwise"></i> Reset
          </a>
        </div>
      </form>
    </div>

    {{-- ===== TABEL: satu baris = satu catatan (paket = satu baris, isinya lewat tombol mata) ===== --}}
    <div class="table-card-custom mb-0">
      <div class="riwayat-scroll">
        <table class="table-custom rs-table">
          <thead>
            <tr>
              <th>Tanggal</th>
              <th class="text-center">Jenis</th>
              <th class="text-center">Sumber</th>
              <th>Bahan Baku</th>
              <th class="text-end">Jumlah</th>
              <th class="text-center">Stok Sesudah</th>
              <th>Keterangan</th>
              <th>Dicatat Oleh</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($kejadian as $e)
              @php
                $p = $e->pertama;
                $sumber = $e->sumber;
                $masuk = $e->jenis === 'masuk';
                $namaKasir = $p->kasir->name ?? null;
                $ikonSumber = $sumber === 'paket' ? 'bi-send-fill' : ($sumber === 'kirim' ? 'bi-fuel-pump-fill' : ($sumber === 'produksi' ? 'bi-fire' : 'bi-pencil-square'));
                $namaSumber = ['paket' => 'Paket', 'kirim' => 'Kirim', 'produksi' => 'Produksi'][$sumber] ?? 'Manual';
                $dicatat = $sumber === 'manual' ? ($p->admin->name ?? '-') : ($p->admin->name ?? 'Admin');
                $jam = $p->created_at ? $p->created_at->format('H:i') : null;
              @endphp

              @if ($sumber === 'paket')
                @php
                  $catatan = $p->keterangan && str_contains($p->keterangan, ' · ') ? trim(\Illuminate\Support\Str::after($p->keterangan, ' · ')) : null;
                  $namaBahan = $e->isi->map(fn ($r) => $r->bahan->nama_bahan ?? '-')->values();
                  $idModal = 'paket-' . substr(md5($e->kunci), 0, 10);
                @endphp
                <tr>
                  <td class="text-nowrap"><div class="rs-date">{{ $p->tanggal->format('d/m/Y') }}</div>@if ($jam)<div class="rs-jam">{{ $jam }}</div>@endif</td>
                  <td class="text-center"><span class="jenis-badge jenis-badge-keluar"><i class="bi bi-box-arrow-up"></i> Keluar</span></td>
                  <td class="text-center"><span class="rs-sumber rs-sumber-paket"><i class="bi bi-send-fill"></i> Paket</span></td>
                  <td>
                    <div class="rs-paket-cell">
                      <div class="rs-paket-teks">
                        <b>{{ $e->isi->count() }} bahan</b>
                        <small>{{ $namaBahan->take(3)->implode(', ') }}@if ($namaBahan->count() > 3), +{{ $namaBahan->count() - 3 }} lainnya @endif</small>
                      </div>
                      <button type="button" class="rs-eye" data-bs-toggle="modal" data-bs-target="#{{ $idModal }}" title="Lihat isi paket" aria-label="Lihat isi paket"><i class="bi bi-eye-fill"></i></button>
                    </div>

                    <div class="modal fade rs-modal" id="{{ $idModal }}" tabindex="-1" aria-hidden="true">
                      <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
                        <div class="modal-content">
                          <div class="fm-header">
                            <h5><i class="bi bi-send-fill me-2"></i>Paket ke kasir{{ $namaKasir ? ' ' . $namaKasir : '' }}</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
                          </div>
                          <div class="modal-body">
                            <div class="rs-modal-meta">
                              <span><i class="bi bi-calendar-event"></i> {{ $p->tanggal->locale('id')->translatedFormat('d F Y') }}</span>
                              <span><i class="bi bi-box-seam"></i> {{ $e->isi->count() }} bahan keluar dari gudang</span>
                              <span><i class="bi bi-person-check"></i> {{ $dicatat }}</span>
                            </div>
                            @if ($catatan)<div class="rs-kej-note mb-2">“{{ $catatan }}”</div>@endif
                            @include('admin.operasional.riwayat_stok._bahan', ['e' => $e, 'sumber' => $sumber])
                          </div>
                          <div class="modal-footer border-0 pt-0">
                            <button type="button" class="btn-custom btn-custom-primary w-100 justify-content-center" data-bs-dismiss="modal">Tutup</button>
                          </div>
                        </div>
                      </div>
                    </div>
                  </td>
                  <td class="text-end"><span class="rs-dash">&ndash;</span></td>
                  <td class="text-center"><span class="rs-dash">&ndash;</span></td>
                  <td class="rs-ket">
                    <span class="rs-orang"><i class="bi bi-person"></i> {{ $namaKasir ?? '-' }}</span>
                    @if ($catatan)<div class="rs-ket-note">{{ $catatan }}</div>@endif
                  </td>
                  <td class="rs-who">{{ $dicatat }}</td>
                </tr>
              @else
                @foreach ($e->isi as $r)
                  @php $fotoBahan = $r->bahan?->foto_url; @endphp
                  <tr>
                    <td class="text-nowrap"><div class="rs-date">{{ $r->tanggal->format('d/m/Y') }}</div>@if ($r->created_at)<div class="rs-jam">{{ $r->created_at->format('H:i') }}</div>@endif</td>
                    <td class="text-center">
                      <span class="jenis-badge jenis-badge-{{ $r->jenis }}"><i class="bi bi-{{ $r->jenis == 'masuk' ? 'box-arrow-in-down' : 'box-arrow-up' }}"></i> {{ ucfirst($r->jenis) }}</span>
                    </td>
                    <td class="text-center"><span class="rs-sumber rs-sumber-{{ $sumber }}"><i class="bi {{ $ikonSumber }}"></i> {{ $namaSumber }}</span></td>
                    <td>
                      <span class="rs-item">
                        <span class="rs-foto">@if ($fotoBahan)<img src="{{ $fotoBahan }}" alt="" loading="lazy">@else<i class="bi bi-box-seam"></i>@endif</span>
                        <b>{{ $r->bahan->nama_bahan ?? '-' }}</b>
                      </span>
                    </td>
                    <td class="text-end"><span class="rs-jumlah {{ $r->jenis }}">{{ $r->jenis == 'masuk' ? '+' : '-' }}{{ $r->t_jumlah }}</span></td>
                    <td class="text-center">
                      @if ($r->t_sisa !== null)<span class="rs-stok">{{ $r->t_sisa }}</span>@else<span class="rs-dash">&ndash;</span>@endif
                    </td>
                    <td class="rs-ket">
                      @if ($r->jenis === 'masuk' && $r->supplier)<span class="rs-orang"><i class="bi bi-truck"></i> {{ $r->supplier }}</span>@endif
                      @if ($r->jenis === 'keluar' && $r->kasir)<span class="rs-orang"><i class="bi bi-person"></i> {{ $r->kasir->name }}</span>@endif
                      @if ($r->keterangan)<div class="rs-ket-note">{{ $r->keterangan }}</div>@endif
                      @if (! $r->keterangan && ! ($r->jenis === 'masuk' && $r->supplier) && ! ($r->jenis === 'keluar' && $r->kasir))<span class="rs-dash">&ndash;</span>@endif
                    </td>
                    <td class="rs-who">{{ $dicatat }}</td>
                  </tr>
                @endforeach
              @endif
            @empty
              <tr class="rs-empty-row">
                <td colspan="8">
                  <div class="rs-empty">
                    <div class="rs-empty-icon"><i class="bi bi-box-seam"></i></div>
                    <div class="rs-empty-title">Belum ada riwayat stok</div>
                    <div class="rs-empty-hint">Catatan akan muncul di sini setelah ada stok masuk atau keluar. Coba ubah filter bila perlu.</div>
                  </div>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <p class="rs-note"><i class="bi bi-info-circle"></i>
      Halaman ini khusus <b>stok gudang</b> (stok masuk, stok keluar, dan paket ke kasir). Sisa stok yang dipegang kasir dilihat di menu <b>Stok Kasir</b>.</p>

    @if ($kejadian->hasPages())
      <div class="mt-4 d-flex justify-content-center">
        {{ $kejadian->links('pagination::bootstrap-5') }}
      </div>
    @endif
  </div>

@endsection

@push('scripts')
  <script>
    // Modal dipindah ke <body> supaya tidak terpotong tabel / kartu
    document.querySelectorAll('.rs-modal').forEach(function (m) { document.body.appendChild(m); });
  </script>
@endpush
