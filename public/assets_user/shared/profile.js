// Asal: resources/views/profile/edit_userProfile.blade.php
document.addEventListener('DOMContentLoaded', function () {
  // ---- Pindah tab Profil / Password ----
  const tabs = document.querySelectorAll('.pf-tabs button');
  const panes = document.querySelectorAll('.pf-pane');
  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      tabs.forEach(t => t.classList.toggle('on', t === tab));
      panes.forEach(p => p.classList.toggle('on', p.id === 'pane-' + tab.dataset.pane));
    });
  });

  // ---- Preview foto sebelum disimpan ----
  const input = document.getElementById('pf-foto-input');
  const box = document.getElementById('pf-photo-box');
  const note = document.getElementById('pf-foto-note');
  const MAKS = 2 * 1024 * 1024;

  if (input) {
    input.addEventListener('change', function () {
      const file = this.files[0];
      if (!file) return;

      if (file.size > MAKS) {
        this.value = '';
        if (note) { note.textContent = 'Ukuran foto lebih dari 2MB. Pilih foto lain.'; note.className = 'pf-note is-error'; }
        return;
      }

      const reader = new FileReader();
      reader.onload = function (e) {
        box.innerHTML = '';
        const img = document.createElement('img');
        img.src = e.target.result;
        img.alt = 'Foto baru';
        box.appendChild(img);
      };
      reader.readAsDataURL(file);

      if (note) { note.textContent = 'Foto baru belum disimpan. Klik "Simpan Perubahan" di tab Profil.'; note.className = 'pf-note is-pending'; }
    });
  }

  // ---- Lihat / sembunyikan password ----
  document.querySelectorAll('.pf-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const field = document.getElementById(this.dataset.target);
      const icon = this.querySelector('i');
      const tampil = field.type === 'password';
      field.type = tampil ? 'text' : 'password';
      icon.className = tampil ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
  });

  // ---- Cek konfirmasi password cocok ----
  const pw = document.getElementById('password');
  const konf = document.getElementById('password_confirmation');
  const match = document.getElementById('pf-match');

  function cek() {
    if (!konf.value) { match.textContent = ''; match.className = 'pf-match'; return; }
    if (pw.value === konf.value) {
      match.textContent = 'Password cocok.';
      match.className = 'pf-match ok';
    } else {
      match.textContent = 'Password belum cocok.';
      match.className = 'pf-match bad';
    }
  }
  pw.addEventListener('input', cek);
  konf.addEventListener('input', cek);
});
