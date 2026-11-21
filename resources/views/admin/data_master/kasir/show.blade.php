@extends('layouts.admin')

@section('title', 'Detail Kasir - Chicken Crunchy Roll')
@section('breadcrumb', 'Data Kasir')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets_user/admin/data_master/kasir.css') }}">
@endpush

@section('content')

  @php
    $aktif = $kasir->status == 'aktif';
    $nomor = 'KSR-' . str_pad($kasir->id, 4, '0', STR_PAD_LEFT);
    $jk = $kasir->jenis_kelamin === 'L' ? 'Laki-laki' : ($kasir->jenis_kelamin === 'P' ? 'Perempuan' : '-');
  @endphp

  <div class="page-header">
    <div class="page-header-icon"><i class="bi bi-person-vcard-fill"></i></div>
    <div>
      <h1 class="page-title">Detail Kasir</h1>
      <p class="page-subtitle">Informasi lengkap akun {{ $kasir->name }}.</p>
    </div>
  </div>

  <div class="card">
    <div class="card-banner">
      <h2><i class="bi bi-person-badge-fill me-2"></i>Detail Kasir</h2>
    </div>

    <div class="kc-stage">
      <div class="kc-card">
        <div class="kc-stars" aria-hidden="true">{{ str_repeat('★ ', 60) }}</div>

        <div class="kc-head">
          <div>
            <div class="kc-title">Kartu Kasir</div>
            <div class="kc-brandname">Chicken Crunchy Roll</div>
          </div>
          <div class="kc-no"><small>NO. KARTU</small>{{ $nomor }}</div>
        </div>

        <div class="kc-body">
          <div class="kc-photo-wrap">
            <div class="kc-photo">
              <div class="kc-photo-inner">
                @if ($kasir->foto)
                  <img src="{{ asset('storage/' . $kasir->foto) }}" alt="{{ $kasir->name }}">
                @endif
              </div>
            </div>
            <span class="kc-role">Kasir</span>
          </div>

          <div class="kc-fields">
            <div class="kc-name">{{ $kasir->name }}</div>

            <div class="kc-row">
              <span class="kc-label">Jenis kelamin</span>
              <span class="kc-value">{{ $jk }}</span>
            </div>
            <div class="kc-row">
              <span class="kc-label">Tempat, tgl. lahir</span>
              <span class="kc-value">{{ $kasir->tempat_lahir ?? '-' }}@if($kasir->tanggal_lahir), {{ $kasir->tanggal_lahir->translatedFormat('d F Y') }}@endif</span>
            </div>
            <div class="kc-row">
              <span class="kc-label">Alamat</span>
              <span class="kc-value">{{ $kasir->alamat ?? '-' }}</span>
            </div>
            <div class="kc-row">
              <span class="kc-label">No. HP</span>
              <span class="kc-value">{{ $kasir->no_hp ?? '-' }}</span>
            </div>
            <div class="kc-row">
              <span class="kc-label">Email</span>
              <span class="kc-value">{{ $kasir->email }}</span>
            </div>
            <div class="kc-row">
              <span class="kc-label">Status</span>
              <span class="kc-value">{{ ucfirst($kasir->status) }}</span>
            </div>
          </div>
        </div>

        <div class="kc-foot">
          <div class="kc-since">Terdaftar sejak <strong>{{ $kasir->created_at->translatedFormat('d F Y') }}</strong></div>
        </div>

        <div class="kc-stamp {{ $aktif ? 'aktif' : 'nonaktif' }}">{{ $aktif ? 'Aktif' : 'Nonaktif' }}</div>

        <div class="kc-stars" style="margin-top:1.1rem;" aria-hidden="true">{{ str_repeat('★ ', 60) }}</div>
      </div>

      <div class="kc-actions d-flex gap-2">
        <a href="{{ route('kasir.index') }}" class="btn-custom flex-fill justify-content-center"
          style="background:#FFFFFF;border:2px solid var(--brand-forest-medium);color:var(--brand-forest-medium);font-weight:700;">
          <i class="bi bi-arrow-left"></i> Kembali
        </a>
        <a href="{{ route('kasir.edit', $kasir) }}" class="btn-custom btn-custom-primary flex-fill justify-content-center">
          <i class="bi bi-pencil-fill"></i> Edit
        </a>
      </div>
    </div>
  </div>

@endsection
