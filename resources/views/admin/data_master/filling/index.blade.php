@extends('layouts.admin')

@section('title', 'Master Filling - Chicken Crunchy Roll')
@section('breadcrumb', 'Master Filling')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets_user/admin/data_master/master.css') }}">
@endpush

@section('content')

  <div class="page-header">
    <div class="page-header-icon"><i class="bi bi-egg-fried"></i></div>
    <div>
      <h1 class="page-title">Master Filling</h1>
      <p class="page-subtitle">Kelola data filling (isian) chicken roll.</p>
    </div>
  </div>

  @php
    // Bahan baku yang terhubung ke tiap item (pengaturan stok) -> tampil sebagai tag stok di kartu
    $bahanMap = \Illuminate\Support\Facades\Schema::hasColumn('filling', 'bahan_baku_id')
      ? \Illuminate\Support\Facades\DB::table('bahan_baku')->get()->keyBy('id')
      : collect();
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
  @endphp

  <x-master-panel judul="Daftar Filling" ikon="bi-egg-fried" :tambah-route="route('filling.create')" tambah-label="Tambah Filling"
    cari-id="search-filling" cari-hint="Cari nama filling..." :semua="$filling"
    kosong-judul="Belum ada filling" kosong-hint='Klik "Tambah Filling" untuk mulai menambahkan data.'>
    @foreach ($filling as $item)
      @php
        $b = $bahanMap->get($item->bahan_baku_id ?? null);
        $tags = [];
        if ($b) {
          $kelas = $b->status_stok === 'habis' ? 'bad' : ($b->status_stok === 'menipis' ? 'warn' : 'good');
          $tags[] = ['bi-box-seam', 'Stok: ' . $fmt($b->stok_saat_ini) . ' ' . $b->satuan, $kelas];
        }
      @endphp
      @include('admin.data_master._kartu', [
        'tipe' => 'filling', 'item' => $item, 'nama' => $item->nama_filling, 'chip' => 'Filling',
        'desc' => 'Isian di dalam chicken roll', 'tags' => $tags,
        'edit' => route('filling.edit', $item), 'hapus' => route('filling.destroy', $item),
      ])
    @endforeach
  </x-master-panel>

@endsection

@push('scripts')
  <script src="{{ asset('assets_user/admin/data_master/master.js') }}"></script>
@endpush
