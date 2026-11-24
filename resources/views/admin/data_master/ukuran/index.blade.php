@extends('layouts.admin')

@section('title', 'Master Ukuran - Chicken Crunchy Roll')
@section('breadcrumb', 'Master Ukuran')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets_user/admin/data_master/master.css') }}">
@endpush

@section('content')

  <div class="page-header">
    <div class="page-header-icon"><i class="bi bi-rulers"></i></div>
    <div>
      <h1 class="page-title">Master Ukuran</h1>
      <p class="page-subtitle">Kelola data ukuran produk beserta harganya.</p>
    </div>
  </div>


  <x-master-panel judul="Daftar Ukuran" ikon="bi-rulers" :tambah-route="route('ukuran.create')" tambah-label="Tambah Ukuran"
    cari-id="search-ukuran" cari-hint="Cari nama ukuran..." :semua="$ukuran"
    kosong-judul="Belum ada ukuran" kosong-hint='Klik "Tambah Ukuran" untuk mulai menambahkan data.'>
    @foreach ($ukuran as $item)
      @include('admin.data_master._kartu', [
        'tipe' => 'ukuran', 'item' => $item, 'nama' => $item->nama_ukuran, 'chip' => 'Ukuran',
        'harga' => 'Rp ' . number_format($item->harga, 0, ',', '.'),
        'edit' => route('ukuran.edit', $item), 'hapus' => route('ukuran.destroy', $item),
      ])
    @endforeach
  </x-master-panel>

@endsection

@push('scripts')
  <script src="{{ asset('assets_user/admin/data_master/master.js') }}"></script>
@endpush
