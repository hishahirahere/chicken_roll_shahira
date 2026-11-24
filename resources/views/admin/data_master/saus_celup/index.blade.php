@extends('layouts.admin')

@section('title', 'Master Saus Celup - Chicken Crunchy Roll')
@section('breadcrumb', 'Master Saus Celup')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets_user/admin/data_master/master.css') }}">
@endpush

@section('content')

  <div class="page-header">
    <div class="page-header-icon"><i class="bi bi-droplet-fill"></i></div>
    <div>
      <h1 class="page-title">Master Saus Celup</h1>
      <p class="page-subtitle">Kelola data saus celup.</p>
    </div>
  </div>


  <x-master-panel judul="Daftar Saus Celup" ikon="bi-droplet-fill" :tambah-route="route('saus_celup.create')" tambah-label="Tambah Saus Celup"
    cari-id="search-saus" cari-hint="Cari nama saus..." :semua="$sausCelup"
    kosong-judul="Belum ada saus celup" kosong-hint='Klik "Tambah Saus Celup" untuk mulai menambahkan data.'>
    @foreach ($sausCelup as $item)
      @include('admin.data_master._kartu', [
        'tipe' => 'saus_celup', 'item' => $item, 'nama' => $item->nama_saus, 'chip' => 'Saus Celup',
        'edit' => route('saus_celup.edit', $item), 'hapus' => route('saus_celup.destroy', $item),
      ])
    @endforeach
  </x-master-panel>

@endsection

@push('scripts')
  <script src="{{ asset('assets_user/admin/data_master/master.js') }}"></script>
@endpush
