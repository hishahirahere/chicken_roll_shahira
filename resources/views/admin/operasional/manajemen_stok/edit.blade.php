@extends('layouts.admin')

@section('title', 'Edit Bahan Baku - Chicken Crunchy Roll')
@section('breadcrumb', 'Edit Bahan Baku')

@section('content')

  <div class="page-header">
    <div class="page-header-icon"><i class="bi bi-box-seam-fill"></i></div>
    <div>
      <h1 class="page-title">Edit Bahan Baku</h1>
      <p class="page-subtitle">Perbarui data {{ $bahanBaku->nama_bahan }}.</p>
    </div>
  </div>

  @include('admin.operasional.manajemen_stok._form', ['mode' => 'edit', 'bahanBaku' => $bahanBaku])

@endsection
