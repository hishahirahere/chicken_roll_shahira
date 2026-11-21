@extends('layouts.admin')

@section('title', 'Edit Ukuran - Chicken Crunchy Roll')

@section('content')

  <div class="page-header">
    <div class="page-header-icon"><i class="bi bi-rulers"></i></div>
    <div>
      <h1 class="page-title">Edit Ukuran</h1>
      <p class="page-subtitle">Perbarui data {{ $ukuran->nama_ukuran }}.</p>
    </div>
  </div>

  <div class="row g-4 align-items-stretch">
    <div class="col-lg-7 d-flex">
      <div class="card w-100 d-flex flex-column">
        <div class="card-banner">
          <h2><i class="bi bi-rulers me-2"></i>Detail Ukuran</h2>
        </div>

        <div class="p-4 d-flex flex-column flex-grow-1">
          <form action="{{ route('ukuran.update', $ukuran) }}" method="POST" enctype="multipart/form-data"
            class="d-flex flex-column flex-grow-1">
            @csrf
            @method('PUT')

            <div class="mb-3">
              <label class="form-label fw-bold">Nama Ukuran</label>
              <input type="text" name="nama_ukuran" id="input-nama" value="{{ old('nama_ukuran', $ukuran->nama_ukuran) }}"
                class="form-control @error('nama_ukuran') is-invalid @enderror"
                placeholder="Contoh: Slim / Reguler / Jumbo">
              @error('nama_ukuran')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label class="form-label fw-bold">Harga</label>
              <div class="input-group">
                <span class="input-group-text">Rp</span>
                <input type="number" name="harga" id="input-harga" value="{{ old('harga', $ukuran->harga) }}"
                  class="form-control @error('harga') is-invalid @enderror" placeholder="15000">
              </div>
              @error('harga')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-4">
              <label class="form-label fw-bold">Ganti Foto (opsional)</label>

              <label for="foto-input" id="foto-dropzone"
                style="display:flex;align-items:center;gap:14px;border:1.5px dashed var(--border-light);
                       border-radius:12px;padding:14px;cursor:pointer;background:var(--bs-body-bg);
                       transition:border-color .15s ease;">
                <div id="foto-preview-wrap"
                  style="width:56px;height:56px;border-radius:10px;background:#fff;flex-shrink:0;
                         display:flex;align-items:center;justify-content:center;overflow:hidden;border:1px solid var(--border-light);">
                  <i class="bi bi-image text-muted-green" id="foto-placeholder-icon"
                    style="{{ $ukuran->foto ? 'display:none;' : '' }}"></i>
                  <img id="foto-preview-img"
                    src="{{ $ukuran->foto ? asset('storage/' . $ukuran->foto) : '' }}"
                    alt=""
                    style="{{ $ukuran->foto ? 'display:block;' : 'display:none;' }}width:100%;height:100%;object-fit:cover;">
                </div>
                <div>
                  <div class="fw-bold" id="foto-label-text">
                    {{ $ukuran->foto ? 'Klik untuk ganti foto' : 'Klik untuk pilih foto' }}
                  </div>
                  <div class="text-muted-green" style="font-size:.85rem;">PNG atau JPG, maks 2MB</div>
                </div>
              </label>
              <input type="file" name="foto" id="foto-input" accept="image/*" class="d-none @error('foto') is-invalid @enderror">
              @error('foto')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>

            <div class="d-flex gap-2 mt-auto">
              <a href="{{ route('ukuran.index') }}" class="btn-custom flex-fill justify-content-center"
                style="background:#FFFFFF;border:2px solid var(--bs-danger, #7A1E1E);color:var(--bs-danger, #7A1E1E);font-weight:700;">
                <i class="bi bi-arrow-left"></i> Kembali
              </a>
              <button type="submit" class="btn-custom btn-custom-primary flex-fill justify-content-center">
                <i class="bi bi-check-lg"></i> Simpan Perubahan
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-5 d-flex">
      <div class="card w-100 d-flex flex-column">
        <div class="card-banner">
          <h2><i class="bi bi-eye me-2"></i>Preview</h2>
        </div>
        <div class="p-4 d-flex flex-column align-items-center justify-content-center text-center flex-grow-1">
          <div id="preview-photo"
            style="width:220px;height:220px;border-radius:20px;background:var(--bs-body-bg);
                   border:1px solid var(--border-light);display:flex;align-items:center;
                   justify-content:center;overflow:hidden;margin-bottom:24px;">
            <i class="bi bi-image text-muted-green" style="font-size:3.5rem;{{ $ukuran->foto ? 'display:none;' : '' }}"></i>
            <img id="preview-photo-img"
              src="{{ $ukuran->foto ? asset('storage/' . $ukuran->foto) : '' }}"
              alt=""
              style="{{ $ukuran->foto ? 'display:block;' : 'display:none;' }}width:100%;height:100%;object-fit:cover;">
          </div>
          <div class="fw-bold" id="preview-nama" data-placeholder="Nama Ukuran" style="font-size:1.5rem;">{{ $ukuran->nama_ukuran }}</div>
          <div class="text-muted-green mb-3" id="preview-harga" style="font-size:1.15rem;">
            Rp {{ number_format($ukuran->harga, 0, ',', '.') }}
          </div>
          <p class="text-muted-green" style="font-size:.9rem;max-width:280px;">
            Begini kira-kira tampilan ukuran ini nanti di daftar produk.
          </p>
        </div>
      </div>
    </div>
  </div>

@endsection

@push('scripts')
  <script src="{{ asset('assets_user/admin/data_master/master.js') }}"></script>
@endpush
