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

  @php
    // Bahan baku yang terhubung ke tiap item (pengaturan stok) -> tampil sebagai tag stok di kartu
    $bahanMap = \Illuminate\Support\Facades\Schema::hasColumn('bumbu_tabur', 'bahan_baku_id')
      ? \Illuminate\Support\Facades\DB::table('bahan_baku')->get()->keyBy('id')
      : collect();
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
  @endphp

  <x-master-panel judul="Daftar Bumbu Tabur" ikon="bi-stars" :tambah-route="route('bumbu_tabur.create')" tambah-label="Tambah Bumbu Tabur"
    cari-id="search-bumbu" cari-hint="Cari nama bumbu..." :semua="$bumbuTabur"
    kosong-judul="Belum ada bumbu tabur" kosong-hint='Klik "Tambah Bumbu Tabur" untuk mulai menambahkan data.'>
    @foreach ($bumbuTabur as $item)
      @php
        $b = $bahanMap->get($item->bahan_baku_id ?? null);
        $tags = [];
        if ($b) {
          $kelas = $b->status_stok === 'habis' ? 'bad' : ($b->status_stok === 'menipis' ? 'warn' : 'good');
          $tags[] = ['bi-box-seam', 'Stok: ' . $fmt($b->stok_saat_ini) . ' ' . $b->satuan, $kelas];
        }
      @endphp
      @include('admin.data_master._kartu', [
        'tipe' => 'bumbu_tabur', 'item' => $item, 'nama' => $item->nama_bumbu, 'chip' => 'Bumbu Tabur',
        'desc' => 'Bumbu yang ditabur di luar roll', 'tags' => $tags,
        'edit' => route('bumbu_tabur.edit', $item), 'hapus' => route('bumbu_tabur.destroy', $item),
      ])
    @endforeach
  </x-master-panel>

@endsection

@push('scripts')
  <script src="{{ asset('assets_user/admin/data_master/master.js') }}"></script>
@endpush
