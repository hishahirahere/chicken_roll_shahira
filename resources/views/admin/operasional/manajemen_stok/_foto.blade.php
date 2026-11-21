{{-- Pilih foto bahan baku (opsional). Props: $fotoUrl (url foto sekarang atau null), $ubah (true di halaman edit) --}}
@php $fotoUrl = $fotoUrl ?? null; $ubah = $ubah ?? false; @endphp
<div class="bb-section">
  <div class="bb-section-title"><i class="bi bi-image"></i> Foto bahan <span class="text-muted-green fw-normal">— opsional</span></div>

  <div class="bb-foto">
    <div class="bb-foto-box" id="bb-foto-box">
      @if ($fotoUrl)
        <img src="{{ $fotoUrl }}" alt="Foto bahan" id="bb-foto-img">
      @else
        <i class="bi bi-image" id="bb-foto-icon"></i>
      @endif
    </div>
    <div class="bb-foto-side">
      <label for="bb-foto-input" class="btn-custom btn-custom-primary" style="cursor:pointer;">
        <i class="bi bi-upload"></i> {{ $fotoUrl ? 'Ganti Foto' : 'Pilih Foto' }}
      </label>
      <input type="file" name="foto" id="bb-foto-input" accept="image/png,image/jpeg,image/webp" class="d-none">
      <div class="form-text" id="bb-foto-note">PNG, JPG, atau WEBP, maksimal 2MB. Contoh: foto ayam, tepung, minyak.</div>
      @if ($ubah && $fotoUrl)
        <label class="form-check mt-2" style="font-size:.85rem;">
          <input type="checkbox" name="hapus_foto" value="1" class="form-check-input"> Hapus foto yang sekarang
        </label>
      @endif
      @error('foto')<div class="text-danger mt-1" style="font-size:.85rem;">{{ $message }}</div>@enderror
    </div>
  </div>
</div>

<script>
  (function () {
    var input = document.getElementById('bb-foto-input');
    if (!input) return;
    input.addEventListener('change', function () {
      var file = this.files[0];
      var note = document.getElementById('bb-foto-note');
      if (!file) return;
      if (file.size > 2 * 1024 * 1024) { this.value = ''; note.textContent = 'Ukuran foto lebih dari 2MB. Pilih foto lain.'; note.style.color = '#b3202f'; return; }
      var r = new FileReader();
      r.onload = function (e) {
        var box = document.getElementById('bb-foto-box');
        box.innerHTML = '<img src="' + e.target.result + '" alt="Foto baru" id="bb-foto-img">';
        note.textContent = file.name; note.style.color = '';
      };
      r.readAsDataURL(file);
    });
  })();
</script>
