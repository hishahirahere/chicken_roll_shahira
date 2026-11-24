@extends('layouts.admin')

@section('title', 'Tambah Kasir - Chicken Crunchy Roll')
@section('breadcrumb', 'Data Kasir')

@section('content')

  <div class="page-header">
    <div class="page-header-icon"><i class="bi bi-person-plus-fill"></i></div>
    <div>
      <h1 class="page-title">Tambah Kasir</h1>
      <p class="page-subtitle">Buat akun kasir baru untuk login ke sistem.</p>
    </div>
  </div>

  <div class="row g-4 align-items-stretch">
    <div class="col-lg-7 d-flex">
      <div class="card w-100 d-flex flex-column">
        <div class="card-banner">
          <h2><i class="bi bi-person-badge-fill me-2"></i>Detail Kasir</h2>
        </div>

        <div class="p-4 d-flex flex-column flex-grow-1">
          <form action="{{ route('kasir.store') }}" method="POST" enctype="multipart/form-data"
            class="d-flex flex-column flex-grow-1">
            @csrf

            <div class="mb-3">
              <label class="form-label fw-bold">Nama</label>
              <input type="text" name="name" id="input-nama" value="{{ old('name') }}"
                class="form-control @error('name') is-invalid @enderror" placeholder="Nama lengkap kasir">
              @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label class="form-label fw-bold">Jenis Kelamin</label>
              <div class="d-flex gap-3">
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="jenis_kelamin" id="jk-l" value="L"
                    {{ old('jenis_kelamin') == 'L' ? 'checked' : '' }}>
                  <label class="form-check-label" for="jk-l">Laki-laki</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="jenis_kelamin" id="jk-p" value="P"
                    {{ old('jenis_kelamin') == 'P' ? 'checked' : '' }}>
                  <label class="form-check-label" for="jk-p">Perempuan</label>
                </div>
              </div>
              @error('jenis_kelamin')
                <div class="text-danger" style="font-size:.85rem;">{{ $message }}</div>
              @enderror
            </div>

            <div class="row g-2 mb-3">
              <div class="col-6">
                <label class="form-label fw-bold">Tempat Lahir</label>
                <input type="text" name="tempat_lahir" value="{{ old('tempat_lahir') }}"
                  class="form-control @error('tempat_lahir') is-invalid @enderror">
                @error('tempat_lahir')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
              <div class="col-6">
                <label class="form-label fw-bold">Tanggal Lahir</label>
                <input type="date" name="tanggal_lahir" value="{{ old('tanggal_lahir') }}"
                  class="form-control @error('tanggal_lahir') is-invalid @enderror">
                @error('tanggal_lahir')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label fw-bold">Alamat</label>
              <textarea name="alamat" rows="2"
                class="form-control @error('alamat') is-invalid @enderror">{{ old('alamat') }}</textarea>
              @error('alamat')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label class="form-label fw-bold">No. HP</label>
              <input type="text" name="no_hp" value="{{ old('no_hp') }}"
                class="form-control @error('no_hp') is-invalid @enderror" placeholder="08xxxxxxxxxx">
              @error('no_hp')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label class="form-label fw-bold">Email</label>
              <input type="email" name="email" id="input-email" value="{{ old('email') }}"
                class="form-control @error('email') is-invalid @enderror" placeholder="kasir@gmail.com">
              @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label class="form-label fw-bold">Password</label>
              <div class="input-group has-validation">
                <input type="password" name="password" id="kasir-password" autocomplete="new-password"
                  class="form-control @error('password') is-invalid @enderror" placeholder="Minimal 8 karakter">
                <button type="button" id="kasir-password-toggle" class="btn btn-outline-secondary"
                  aria-label="Tampilkan password" title="Tampilkan / sembunyikan password"
                  style="border-color:#ced4da;background:#fff;color:#6b7280;">
                  <i class="bi bi-eye" id="kasir-password-icon"></i>
                </button>
                @error('password')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
              <div class="form-text">Minimal 8 karakter, kombinasi huruf, angka, dan simbol.</div>
            </div>

            <div class="mb-4">
              <label class="form-label fw-bold">Status</label>
              <select name="status" id="input-status" class="form-select @error('status') is-invalid @enderror">
                <option value="aktif" {{ old('status') == 'aktif' ? 'selected' : '' }}>Aktif</option>
                <option value="nonaktif" {{ old('status') == 'nonaktif' ? 'selected' : '' }}>Nonaktif</option>
              </select>
              @error('status')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-4">
              <label class="form-label fw-bold">Foto (opsional)</label>

              <label for="foto-input" id="foto-dropzone"
                style="display:flex;align-items:center;gap:14px;border:1.5px dashed var(--border-light);
                       border-radius:12px;padding:14px;cursor:pointer;background:var(--bs-body-bg);
                       transition:border-color .15s ease;">
                <div id="foto-preview-wrap"
                  style="width:56px;height:56px;border-radius:10px;background:#fff;flex-shrink:0;
                         display:flex;align-items:center;justify-content:center;overflow:hidden;border:1px solid var(--border-light);">
                  <i class="bi bi-person text-muted-green" id="foto-placeholder-icon"></i>
                  <img id="foto-preview-img" src="" alt="" style="display:none;width:100%;height:100%;object-fit:cover;">
                </div>
                <div>
                  <div class="fw-bold" id="foto-label-text">Klik untuk pilih foto</div>
                  <div class="text-muted-green" style="font-size:.85rem;">PNG atau JPG, maks 2MB</div>
                </div>
              </label>
              <input type="file" name="foto" id="foto-input" accept="image/*" class="d-none @error('foto') is-invalid @enderror">
              @error('foto')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>

            <div class="d-flex gap-2 mt-auto">
              <a href="{{ route('kasir.index') }}" class="btn-custom flex-fill justify-content-center"
                style="background:#FFFFFF;border:2px solid var(--brand-forest-medium);color:var(--brand-forest-medium);font-weight:700;">
                <i class="bi bi-arrow-left"></i> Kembali
              </a>
              <button type="submit" class="btn-custom btn-custom-primary flex-fill justify-content-center">
                <i class="bi bi-check-lg"></i> Simpan
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
            style="width:150px;height:150px;border-radius:50%;background:var(--bs-body-bg);
                   border:1px solid var(--border-light);display:flex;align-items:center;
                   justify-content:center;overflow:hidden;margin-bottom:20px;">
            <i class="bi bi-person text-muted-green" style="font-size:3.5rem;"></i>
            <img id="preview-photo-img" src="" alt="" style="display:none;width:100%;height:100%;object-fit:cover;">
          </div>
          <div class="fw-bold" id="preview-nama" style="font-size:1.3rem;">Nama Kasir</div>
          <div class="text-muted-green mb-2" id="preview-email" style="font-size:.9rem;">email@gmail.com</div>
          <span class="badge-table success" id="preview-status">Aktif</span>
          <p class="text-muted-green mt-3" style="font-size:.9rem;max-width:280px;">
            Begini kira-kira tampilan akun kasir ini di daftar.
          </p>
        </div>
      </div>
    </div>
  </div>

@endsection

@push('scripts')
  <script>
    (function () {
      var input = document.getElementById('kasir-password');
      var tombol = document.getElementById('kasir-password-toggle');
      var ikon = document.getElementById('kasir-password-icon');
      if (!input || !tombol) return;
      tombol.addEventListener('click', function () {
        var tampil = input.type === 'password';
        input.type = tampil ? 'text' : 'password';
        ikon.className = tampil ? 'bi bi-eye-slash' : 'bi bi-eye';
        tombol.setAttribute('aria-label', tampil ? 'Sembunyikan password' : 'Tampilkan password');
      });
    })();
  </script>
  <script src="{{ asset('assets_user/admin/data_master/kasir.js') }}"></script>
@endpush
