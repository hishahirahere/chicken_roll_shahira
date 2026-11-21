@extends('layouts.admin')

@section('title', 'Permintaan Minyak & Gas - Chicken Crunchy Roll')
@section('breadcrumb', 'Permintaan Minyak & Gas')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets_user/admin/data_master/master.css') }}">
  <link rel="stylesheet" href="{{ asset('assets_user/admin/operasional/manajemen_stok.css') }}?v={{ @filemtime(public_path('assets_user/admin/operasional/manajemen_stok.css')) }}">
  <link rel="stylesheet" href="{{ asset('assets_user/admin/operasional/permintaan_stok.css') }}?v={{ @filemtime(public_path('assets_user/admin/operasional/permintaan_stok.css')) }}">
@endpush

@section('content')
  @php
    $n = fn ($v) => number_format((float) $v, 0, ',', '.');
    $waktu = fn ($t) => $t ? \Carbon\Carbon::parse($t)->locale('id')->translatedFormat('d M Y, H:i') : '-';
  @endphp

  <div class="page-header">
    <div class="page-header-icon"><i class="bi bi-fuel-pump-fill"></i></div>
    <div>
      <h1 class="page-title">Permintaan Minyak &amp; Gas</h1>
      <p class="page-subtitle">Kasir minta minyak atau gas, kamu antar barangnya lalu tandai "Kirim". Stok gudang baru berkurang setelah kasir mengonfirmasi barangnya sudah diterima.</p>
    </div>
  </div>

  <div class="ms-stats">
    <div class="ms-stat {{ $diminta->count() > 0 ? 'is-warn is-alert' : '' }}">
      <div class="ms-stat-icon"><i class="bi bi-bell-fill"></i></div>
      <div><span class="ms-stat-label">Perlu dikirim</span><span class="ms-stat-value">{{ $diminta->count() }}</span></div>
    </div>
    <div class="ms-stat">
      <div class="ms-stat-icon"><i class="bi bi-truck"></i></div>
      <div><span class="ms-stat-label">Menunggu konfirmasi kasir</span><span class="ms-stat-value">{{ $dikirim->count() }}</span></div>
    </div>
  </div>

  @if ($errors->any())
    <div class="pm-error"><i class="bi bi-x-octagon-fill"></i>
      <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  {{-- 1. Perlu dikirim --}}
  <div class="card pm-card">
    <div class="card-banner"><h2><i class="bi bi-bell-fill me-2"></i>Perlu Dikirim</h2></div>
    <div class="pm-body">
      @if ($diminta->isEmpty())
        <div class="pm-kosong">Belum ada permintaan baru dari kasir.</div>
      @else
        <div class="pm-wrap"><table class="pm-table">
          <thead><tr><th>Kasir</th><th>Bahan</th><th>Diminta</th><th>Stok dapur</th><th>Kirim</th></tr></thead>
          <tbody>
          @foreach ($diminta as $p)
            <tr>
              <td><b>{{ $p->nama_kasir }}</b><small>{{ $waktu($p->created_at) }}</small></td>
              <td>{{ $p->nama_bahan }}@if ($p->catatan)<small>{{ $p->catatan }}</small>@endif</td>
              <td>{{ $p->jumlah_diminta ? $n($p->jumlah_diminta) . ' ' . $p->satuan : 'Secukupnya' }}</td>
              <td>{{ $n($p->stok_saat_ini) }} {{ $p->satuan }}</td>
              <td>
                <form class="pm-aksi" method="POST" action="{{ route('permintaan.kirim', $p->id) }}">
                  @csrf
                  <input type="number" step="1" min="1" required name="jumlah" value="{{ $p->jumlah_diminta ?? '' }}" class="form-control form-control-sm" placeholder="jumlah">
                  <span>{{ $p->satuan }}</span>
                  <button class="btn btn-sm btn-dark" type="submit"><i class="bi bi-send-fill"></i> Kirim</button>
                </form>
                <form method="POST" action="{{ route('permintaan.batal', $p->id) }}" class="mt-1" onsubmit="return confirm('Batalkan permintaan ini?')">
                  @csrf <button class="btn btn-sm btn-link text-danger p-0" type="submit">Batalkan</button>
                </form>
              </td>
            </tr>
          @endforeach
          </tbody>
        </table></div>
      @endif
    </div>
  </div>

  {{-- 2. Menunggu konfirmasi kasir --}}
  <div class="card pm-card">
    <div class="card-banner"><h2><i class="bi bi-truck me-2"></i>Menunggu Konfirmasi Kasir</h2></div>
    <div class="pm-body">
      @if ($dikirim->isEmpty())
        <div class="pm-kosong">Tidak ada pengiriman yang menunggu konfirmasi.</div>
      @else
        <div class="pm-wrap"><table class="pm-table">
          <thead><tr><th>Kasir</th><th>Bahan</th><th>Dikirim</th><th>Waktu kirim</th><th></th></tr></thead>
          <tbody>
          @foreach ($dikirim as $p)
            <tr>
              <td><b>{{ $p->nama_kasir }}</b></td>
              <td>{{ $p->nama_bahan }}</td>
              <td>{{ $n($p->jumlah_dikirim) }} {{ $p->satuan }}</td>
              <td>{{ $waktu($p->dikirim_pada) }}</td>
              <td>
                <form method="POST" action="{{ route('permintaan.batal', $p->id) }}" onsubmit="return confirm('Batalkan pengiriman ini? Stok gudang tidak berubah.')">
                  @csrf <button class="btn btn-sm btn-outline-danger" type="submit">Batalkan</button>
                </form>
              </td>
            </tr>
          @endforeach
          </tbody>
        </table></div>
      @endif
    </div>
  </div>

  {{-- 3. Catatan pemakaian dari kasir --}}
  <div class="card pm-card">
    <div class="card-banner"><h2><i class="bi bi-journal-check me-2"></i>Catatan Pemakaian dari Kasir</h2></div>
    <div class="pm-body">
      @if ($pemakaian->isEmpty())
        <div class="pm-kosong">Belum ada catatan pemakaian dari kasir.</div>
      @else
        <div class="pm-wrap"><table class="pm-table">
          <thead><tr><th>Tanggal</th><th>Kasir</th><th>Bahan</th><th>Terpakai</th><th>Catatan</th></tr></thead>
          <tbody>
          @foreach ($pemakaian as $m)
            <tr>
              <td>{{ \Carbon\Carbon::parse($m->tanggal)->locale('id')->translatedFormat('d M Y') }}</td>
              <td>{{ $m->nama_kasir }}</td>
              <td>{{ $m->nama_bahan }}</td>
              <td><b>{{ $n($m->jumlah) }}</b> {{ $m->satuan }}</td>
              <td>{{ $m->catatan ?: '-' }}</td>
            </tr>
          @endforeach
          </tbody>
        </table></div>
      @endif
    </div>
  </div>

  {{-- 4. Riwayat --}}
  <div class="card pm-card">
    <div class="card-banner"><h2><i class="bi bi-clock-history me-2"></i>Riwayat Permintaan</h2></div>
    <div class="pm-body">
      @if ($selesai->isEmpty())
        <div class="pm-kosong">Belum ada riwayat.</div>
      @else
        <div class="pm-wrap"><table class="pm-table">
          <thead><tr><th>Kasir</th><th>Bahan</th><th>Dikirim</th><th>Diterima</th><th>Status</th><th>Waktu</th></tr></thead>
          <tbody>
          @foreach ($selesai as $p)
            <tr>
              <td>{{ $p->nama_kasir }}</td>
              <td>{{ $p->nama_bahan }}</td>
              <td>{{ $p->jumlah_dikirim !== null ? $n($p->jumlah_dikirim) . ' ' . $p->satuan : '-' }}</td>
              <td>{{ $p->jumlah_diterima !== null ? $n($p->jumlah_diterima) . ' ' . $p->satuan : '-' }}</td>
              <td><span class="pm-badge {{ $p->status }}">{{ $p->status === 'selesai' ? 'Selesai' : 'Dibatalkan' }}</span></td>
              <td>{{ $waktu($p->updated_at) }}</td>
            </tr>
          @endforeach
          </tbody>
        </table></div>
      @endif
    </div>
  </div>
@endsection
