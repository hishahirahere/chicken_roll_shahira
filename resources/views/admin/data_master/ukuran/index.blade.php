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

  @php
    // Standar produksi (pengaturan stok) per ukuran, dipakai untuk tag info di kartu
    $standar = \Illuminate\Support\Facades\Schema::hasTable('standar_produksi')
      ? \Illuminate\Support\Facades\DB::table('standar_produksi')->get()->keyBy('ukuran_id')
      : collect();
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
  @endphp

  <x-master-panel judul="Daftar Ukuran" ikon="bi-rulers" :tambah-route="route('ukuran.create')" tambah-label="Tambah Ukuran"
    cari-id="search-ukuran" cari-hint="Cari nama ukuran..." :semua="$ukuran"
    kosong-judul="Belum ada ukuran" kosong-hint='Klik "Tambah Ukuran" untuk mulai menambahkan data.'>
    @foreach ($ukuran as $item)
      @php
        $s = $standar->get($item->id);
        $tags = $s ? [['bi-egg-fried', '1 kg ayam = ' . $fmt($s->hasil_pcs_per_kg) . ' pcs']] : [];
      @endphp
      @include('admin.data_master._kartu', [
        'tipe' => 'ukuran', 'item' => $item, 'nama' => $item->nama_ukuran, 'chip' => 'Ukuran',
        'desc' => 'Ukuran chicken crunchy roll', 'harga' => 'Rp ' . number_format($item->harga, 0, ',', '.'),
        'tags' => $tags,
        'edit' => route('ukuran.edit', $item), 'hapus' => route('ukuran.destroy', $item),
      ])
    @endforeach
  </x-master-panel>

@endsection

@push('scripts')
  <script src="{{ asset('assets_user/admin/data_master/master.js') }}"></script>
@endpush
