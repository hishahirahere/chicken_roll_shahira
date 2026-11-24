@extends('layouts.admin')

@section('title', 'Data Kasir - Chicken Crunchy Roll')
@section('breadcrumb', 'Data Kasir')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets_user/admin/data_master/master.css') }}">
@endpush

@section('content')

  <div class="page-header">
    <div class="page-header-icon"><i class="bi bi-person-badge-fill"></i></div>
    <div>
      <h1 class="page-title">Data Kasir</h1>
      <p class="page-subtitle">Kelola akun kasir yang bisa login ke sistem kasir.</p>
    </div>
  </div>

  <x-master-panel judul="Daftar Kasir" ikon="bi-person-badge-fill" :tambah-route="route('kasir.create')" tambah-label="Tambah Kasir"
    cari-id="search-kasir" cari-hint="Cari nama kasir..." :semua="$kasir"
    kosong-judul="Belum ada data kasir" kosong-hint='Klik "Tambah Kasir" untuk membuat akun kasir pertama.'>
    @foreach ($kasir as $item)
      @php
        $tags = [['bi-envelope', $item->email]];
        if ($item->no_hp) $tags[] = ['bi-telephone', $item->no_hp];
      @endphp
      @include('admin.data_master._kartu', [
        'tipe' => 'kasir', 'item' => $item, 'nama' => $item->name, 'chip' => 'Kasir',
        'tags' => $tags, 'bulat' => true, 'ikonKosong' => null,
        'lihat' => route('kasir.show', $item),
        'edit' => route('kasir.edit', $item), 'hapus' => route('kasir.destroy', $item),
        'konfirmHapus' => 'Yakin mau hapus akun kasir ' . e($item->name) . '?',
      ])
    @endforeach
  </x-master-panel>

@endsection

@push('scripts')
  <script src="{{ asset('assets_user/admin/data_master/master.js') }}"></script>
@endpush
