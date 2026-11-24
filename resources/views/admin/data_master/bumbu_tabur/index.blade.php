@extends('layouts.admin')

@section('title', 'Master Bumbu Tabur - Chicken Crunchy Roll')
@section('breadcrumb', 'Master Bumbu Tabur')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets_user/admin/data_master/master.css') }}">
@endpush

@section('content')

  <div class="page-header">
    <div class="page-header-icon"><i class="bi bi-stars"></i></div>
    <div>
      <h1 class="page-title">Master Bumbu Tabur</h1>
      <p class="page-subtitle">Kelola data bumbu tabur.</p>
    </div>
  </div>


  <x-master-panel judul="Daftar Bumbu Tabur" ikon="bi-stars" :tambah-route="route('bumbu_tabur.create')" tambah-label="Tambah Bumbu Tabur"
    cari-id="search-bumbu" cari-hint="Cari nama bumbu..." :semua="$bumbuTabur"
    kosong-judul="Belum ada bumbu tabur" kosong-hint='Klik "Tambah Bumbu Tabur" untuk mulai menambahkan data.'>
    @foreach ($bumbuTabur as $item)
      @include('admin.data_master._kartu', [
        'tipe' => 'bumbu_tabur', 'item' => $item, 'nama' => $item->nama_bumbu, 'chip' => 'Bumbu Tabur',
        'edit' => route('bumbu_tabur.edit', $item), 'hapus' => route('bumbu_tabur.destroy', $item),
      ])
    @endforeach
  </x-master-panel>

@endsection

@push('scripts')
  <script src="{{ asset('assets_user/admin/data_master/master.js') }}"></script>
@endpush
