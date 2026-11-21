{{-- Panel putih pembungkus daftar kartu Data Master (komponen Blade anonim: <x-master-panel>) --}}
@props([
  'judul', 'ikon', 'tambahRoute', 'tambahLabel', 'cariId', 'cariHint',
  'semua', 'kosongJudul', 'kosongHint',
])
@php
  $jmlAktif    = $semua->filter(fn ($x) => ($x->status ?? 'aktif') !== 'nonaktif')->count();
  $jmlNonaktif = $semua->count() - $jmlAktif;
@endphp
<div class="card">
  <div class="card-banner">
    <h2><i class="bi {{ $ikon }} me-2"></i>{{ $judul }}</h2>
  </div>

  <div class="mc-panel-tools">
    <div class="mc-left">
      <a href="{{ $tambahRoute }}" class="btn-custom btn-custom-primary">
        <i class="bi bi-plus-lg"></i> {{ $tambahLabel }}
      </a>
      <div class="mc-filter" role="group" aria-label="Filter status">
        <button type="button" class="on" data-filter="semua">Semua<b>{{ $semua->count() }}</b></button>
        <button type="button" data-filter="aktif">Aktif<b>{{ $jmlAktif }}</b></button>
        <button type="button" data-filter="nonaktif">Nonaktif<b>{{ $jmlNonaktif }}</b></button>
      </div>
    </div>
    <div class="page-search-wrapper" style="max-width:280px;">
      <input type="text" class="page-search-input" id="{{ $cariId }}" placeholder="{{ $cariHint }}">
      <i class="bi bi-search"></i>
    </div>
  </div>

  <div class="mc-board">
    <div class="mc-grid">
      {{ $slot }}

      @if ($semua->isEmpty())
        <div class="mc-empty">
          <div class="mc-empty-icon"><i class="bi {{ $ikon }}"></i></div>
          <div class="mc-empty-title">{{ $kosongJudul }}</div>
          <div class="mc-empty-hint">{{ $kosongHint }}</div>
        </div>
      @endif
      <div class="mc-nohit">Tidak ada data yang cocok dengan pencarian atau filter.</div>
    </div>
  </div>
</div>
