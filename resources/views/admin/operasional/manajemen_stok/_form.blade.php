{{--
  Form tambah / ubah bahan baku, tampilannya sama dengan Tambah/Edit di Data Master
  (kartu detail di kiri + kartu preview di kanan).
  Props: $mode = 'create' | 'edit', $bahanBaku (hanya untuk edit)
--}}
@php
  $edit    = ($mode ?? 'create') === 'edit';
  $b       = $edit ? $bahanBaku : null;
  $fotoUrl = $edit ? $b->foto_url : null;
  $namaAwal = old('nama_bahan', $edit ? $b->nama_bahan : '');
  $satuanAwal = old('satuan', $edit ? $b->satuan : '');
  $minAwal = old('stok_minimum', $edit ? $b->stok_minimum_format : 0);
@endphp

<div class="row g-4 align-items-stretch">
  <div class="col-lg-7 d-flex">
    <div class="card w-100 d-flex flex-column">
      <div class="card-banner">
        <h2><i class="bi bi-box-seam me-2"></i>Detail Bahan Baku</h2>
      </div>

      <div class="p-4 d-flex flex-column flex-grow-1">
        <form action="{{ $edit ? route('bahan_baku.update', $b) : route('bahan_baku.store') }}" method="POST" enctype="multipart/form-data"
          class="d-flex flex-column flex-grow-1">
          @csrf
          @if ($edit) @method('PUT') @endif

          <div class="mb-3">
            <label class="form-label fw-bold">Nama Bahan Baku</label>
            <input type="text" name="nama_bahan" id="input-nama" value="{{ $namaAwal }}" maxlength="50"
              class="form-control @error('nama_bahan') is-invalid @enderror"
              placeholder="Contoh: Ayam / Tepung Roti / Minyak Goreng">
            @error('nama_bahan')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          @unless ($edit)
            <div class="mb-3">
              <label class="form-label fw-bold">Kelompok Bahan (Tab)</label>
              <select name="kelompok" class="form-select @error('kelompok') is-invalid @enderror">
                @foreach (['utama' => 'Bahan Utama', 'operasional' => 'Bahan Operasional'] as $kodeGrup => $namaGrup)
                  <option value="{{ $kodeGrup }}" @selected(old('kelompok', $grupAwal ?? 'utama') === $kodeGrup)>{{ $namaGrup }}</option>
                @endforeach
              </select>
              <div class="form-text">Menentukan bahan ini muncul di tab mana pada Manajemen Stok.</div>
              @error('kelompok')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
          @endunless

          <div class="mb-3">
            <label class="form-label fw-bold">Satuan</label>
            <input type="text" name="satuan" id="input-satuan" value="{{ $satuanAwal }}" maxlength="20" list="daftar-satuan"
              class="form-control @error('satuan') is-invalid @enderror"
              placeholder="Contoh: kg / bungkus / tabung / pcs">
            <datalist id="daftar-satuan">
              @foreach (['bungkus', 'kg', 'gram', 'liter', 'ml', 'pcs', 'pack', 'botol', 'dus', 'tabung'] as $sat)
                <option value="{{ $sat }}"></option>
              @endforeach
            </datalist>
            @error('satuan')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          <div class="mb-3">
            <label class="form-label fw-bold">{{ $edit ? 'Ganti Foto' : 'Foto' }} (opsional)</label>

            <label for="foto-input" id="foto-dropzone"
              style="display:flex;align-items:center;gap:14px;border:1.5px dashed var(--border-light);
                     border-radius:12px;padding:14px;cursor:pointer;background:var(--bs-body-bg);
                     transition:border-color .15s ease;">
              <div id="foto-preview-wrap"
                style="width:56px;height:56px;border-radius:10px;background:#fff;flex-shrink:0;
                       display:flex;align-items:center;justify-content:center;overflow:hidden;border:1px solid var(--border-light);">
                <i class="bi bi-image text-muted-green" id="foto-placeholder-icon" style="{{ $fotoUrl ? 'display:none;' : '' }}"></i>
                <img id="foto-preview-img" src="{{ $fotoUrl ?? '' }}" alt=""
                  style="{{ $fotoUrl ? 'display:block;' : 'display:none;' }}width:100%;height:100%;object-fit:cover;">
              </div>
              <div>
                <div class="fw-bold" id="foto-label-text">{{ $fotoUrl ? 'Klik untuk ganti foto' : 'Klik untuk pilih foto' }}</div>
                <div class="text-muted-green" style="font-size:.85rem;">PNG, JPG, atau WEBP, maks 2MB</div>
              </div>
            </label>
            <input type="file" name="foto" id="foto-input" accept="image/png,image/jpeg,image/webp" class="d-none @error('foto') is-invalid @enderror">
            @error('foto')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

            @if ($edit && $fotoUrl)
              <label class="form-check mt-2" style="font-size:.85rem;">
                <input type="checkbox" name="hapus_foto" value="1" class="form-check-input"> Hapus foto yang sekarang
              </label>
            @endif
          </div>

          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label class="form-label fw-bold">Stok Minimum</label>
              <input type="number" name="stok_minimum" id="input-min" step="1" min="0" value="{{ $minAwal }}"
                class="form-control @error('stok_minimum') is-invalid @enderror">
              <div class="form-text">Segini atau kurang, statusnya jadi "Menipis".</div>
              @error('stok_minimum')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            @if ($edit)
              <div class="col-md-6">
                <label class="form-label fw-bold">Stok Saat Ini</label>
                <input type="text" class="form-control" value="{{ $b->stok_format }} {{ $b->satuan }}" disabled>
                <div class="form-text">Ubah lewat Stok Masuk atau Stok Keluar.</div>
              </div>
            @else
              <div class="col-md-6">
                <label class="form-label fw-bold">Stok Awal <span class="text-muted-green fw-normal">— opsional</span></label>
                <input type="number" name="stok_awal" step="1" min="0" value="{{ old('stok_awal', 0) }}"
                  class="form-control @error('stok_awal') is-invalid @enderror">
                <div class="form-text">Stok yang sudah ada sekarang, dicatat sebagai stok masuk.</div>
                @error('stok_awal')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            @endif

          </div>

          <div class="d-flex gap-2 mt-auto">
            <a href="{{ route('bahan_baku.index') }}" class="btn-custom flex-fill justify-content-center"
              style="background:#FFFFFF;border:2px solid var(--bs-danger, #7A1E1E);color:var(--bs-danger, #7A1E1E);font-weight:700;">
              <i class="bi bi-arrow-left"></i> Kembali
            </a>
            <button type="submit" class="btn-custom btn-custom-primary flex-fill justify-content-center">
              <i class="bi bi-check-lg"></i> {{ $edit ? 'Simpan Perubahan' : 'Simpan' }}
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
          <i class="bi bi-image text-muted-green" style="font-size:3.5rem;{{ $fotoUrl ? 'display:none;' : '' }}"></i>
          <img id="preview-photo-img" src="{{ $fotoUrl ?? '' }}" alt=""
            style="{{ $fotoUrl ? 'display:block;' : 'display:none;' }}width:100%;height:100%;object-fit:cover;">
        </div>
        <div class="fw-bold" id="preview-nama" data-placeholder="Nama Bahan Baku" style="font-size:1.5rem;">{{ $namaAwal !== '' ? $namaAwal : 'Nama Bahan Baku' }}</div>
        <div class="text-muted-green fw-bold mt-1" id="preview-stok" style="font-size:.95rem;"></div>
        <p class="text-muted-green" style="font-size:.9rem;max-width:280px;">
          Begini kira-kira tampilan bahan ini nanti di daftar stok.
        </p>
      </div>
    </div>
  </div>
</div>

@push('scripts')
  <script src="{{ asset('assets_user/admin/data_master/master.js') }}"></script>
  <script>
    (function () {
      var min = document.getElementById('input-min');
      var sat = document.getElementById('input-satuan');
      var out = document.getElementById('preview-stok');
      function segarkan() {
        var m = min.value !== '' ? min.value : '0';
        out.textContent = 'Menipis di ' + m + ' ' + (sat.value.trim() || 'satuan');
      }
      min.addEventListener('input', segarkan);
      sat.addEventListener('input', segarkan);
      segarkan();
    })();
  </script>
@endpush
