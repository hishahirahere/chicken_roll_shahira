@extends('layouts.admin')

@section('title', 'Tambah Bahan Baku - Chicken Crunchy Roll')
@section('breadcrumb', 'Tambah Bahan Baku')

@section('content')

  <div class="page-header">
    <div class="page-header-icon"><i class="bi bi-box-seam-fill"></i></div>
    <div>
      <h1 class="page-title">Tambah Bahan Baku</h1>
      <p class="page-subtitle">Isi data bahan baku baru di bawah ini.</p>
    </div>
  </div>

  @include('admin.operasional.manajemen_stok._form', ['mode' => 'create', 'grupAwal' => $grupAwal ?? 'utama'])

@endsection
