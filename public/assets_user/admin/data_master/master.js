// Data Master (Ukuran, Filling, Saus Celup, Bumbu Tabur, Kasir) + kartu Bahan Baku.
// Satu file untuk: pencarian & filter kartu (index) dan pratinjau foto/nama/harga (create/edit).
document.addEventListener('DOMContentLoaded', function () {
  initFilterKartu();
  initPratinjauForm();

  // Cari nama + filter status (Semua / Aktif / Nonaktif) pada kartu.
  function initFilterKartu() {
    // Bisa kartu (.mc-grid) atau baris tabel (.ms-row), dan bisa beberapa grid dalam satu halaman
    const kartu = Array.from(document.querySelectorAll('.mc-grid .mc, tr.ms-row'));
    if (!document.querySelector('.mc-grid') && !kartu.length) return;
    const cari = document.querySelector('input[id^="search-"]');
    const tombol = document.querySelectorAll('.mc-filter button');
    const nohit = document.querySelector('.mc-nohit, tr.ms-nohit');
    // Tab kelompok (opsional, mis. Manajemen Stok): hanya kartu dari kelompok terpilih yang tampil
    const tabGrup = document.querySelectorAll('.ms-tabs button[data-grup]');
    let grup = tabGrup.length ? (document.querySelector('.ms-tabs button.on') || tabGrup[0]).dataset.grup : null;
    let status = 'semua';

    // Ingat tab, filter, dan posisi scroll supaya setelah simpan / hapus (halaman dimuat ulang)
    // tampilan kembali ke tempat yang sama, bukan ke tab pertama.
    const simpanKey = 'stok-ui:' + location.pathname;
    const simpan = (k, v) => { try { sessionStorage.setItem(simpanKey + ':' + k, v); } catch (e) {} };
    const baca = (k) => { try { return sessionStorage.getItem(simpanKey + ':' + k); } catch (e) { return null; } };
    if (tabGrup.length) {
      const tersimpan = baca('grup');
      const cocok = Array.from(tabGrup).find(function (t) { return t.dataset.grup === tersimpan; });
      if (cocok) {
        grup = cocok.dataset.grup;
        tabGrup.forEach(function (x) { x.classList.toggle('on', x === cocok); });
      }
      const st = baca('status');
      const tombolSt = Array.from(tombol).find(function (b) { return b.dataset.filter === st; });
      if (tombolSt) {
        status = st;
        tombol.forEach(function (x) { x.classList.toggle('on', x === tombolSt); });
      }
      // Datang dari notifikasi lonceng (?grup=...&status=...): buka tab & filter yang diminta,
      // mengalahkan tab terakhir yang tersimpan.
      const qs = new URLSearchParams(location.search);
      const grupUrl = qs.get('grup');
      const statusUrl = qs.get('status');
      const dariNotif = !!(grupUrl || statusUrl);
      const tabUrl = Array.from(tabGrup).find(function (t) { return t.dataset.grup === grupUrl; });
      if (tabUrl) {
        grup = tabUrl.dataset.grup;
        simpan('grup', grup);
        tabGrup.forEach(function (x) { x.classList.toggle('on', x === tabUrl); });
      }
      const tombolUrl = Array.from(tombol).find(function (b) { return b.dataset.filter === statusUrl; });
      if (tombolUrl) {
        status = statusUrl;
        simpan('status', status);
        tombol.forEach(function (x) { x.classList.toggle('on', x === tombolUrl); });
      }
      document.addEventListener('submit', function () { simpan('scroll', String(window.scrollY)); });
      const y = dariNotif ? NaN : parseInt(baca('scroll') || '', 10);
      if (!isNaN(y)) {
        try { sessionStorage.removeItem(simpanKey + ':scroll'); } catch (e) {}
        window.addEventListener('load', function () { window.scrollTo(0, y); });
      }
    }

    function terapkan() {
      const kata = cari ? cari.value.trim().toLowerCase() : '';
      let tampil = 0;
      const hitung = { semua: 0, aman: 0, menipis: 0, habis: 0, aktif: 0, nonaktif: 0 };
      kartu.forEach(function (k) {
        const cocokNama = (k.dataset.nama || '').includes(kata);
        const cocokGrup = grup === null || k.dataset.grup === grup;
        const cocokStatus = status === 'semua' || (k.dataset.status || 'aktif') === status;
        const ok = cocokNama && cocokStatus && cocokGrup;
        k.style.display = ok ? '' : 'none';
        if (ok) tampil++;
        if (cocokNama && cocokGrup) {
          hitung.semua++;
          hitung[k.dataset.status || 'aktif'] = (hitung[k.dataset.status || 'aktif'] || 0) + 1;
        }
      });
      // Angka pada tombol filter mengikuti tab yang sedang dibuka
      if (grup !== null) {
        tombol.forEach(function (b) {
          const n = b.querySelector('b');
          if (n) n.textContent = hitung[b.dataset.filter] || 0;
        });
      }
      if (nohit) nohit.classList.toggle('show', kartu.length > 0 && tampil === 0);
    }

    if (cari) cari.addEventListener('input', terapkan);
    tabGrup.forEach(function (t) {
      t.addEventListener('click', function () {
        grup = t.dataset.grup;
        simpan('grup', grup);
        tabGrup.forEach(function (x) { x.classList.toggle('on', x === t); });
        terapkan();
      });
    });
    terapkan();
    tombol.forEach(function (b) {
      b.addEventListener('click', function () {
        status = b.dataset.filter;
        if (tabGrup.length) simpan('status', status);
        tombol.forEach(function (x) { x.classList.toggle('on', x === b); });
        terapkan();
      });
    });
  }

  // Pratinjau langsung di form tambah/ubah.
  function initPratinjauForm() {
    const input = document.getElementById('foto-input');
    if (!input) return;

    const img = document.getElementById('foto-preview-img');
    const icon = document.getElementById('foto-placeholder-icon');
    const labelText = document.getElementById('foto-label-text');

    const previewImg = document.getElementById('preview-photo-img');
    const previewImgIcon = document.querySelector('#preview-photo i');
    const previewNama = document.getElementById('preview-nama');
    const previewHarga = document.getElementById('preview-harga');

    const namaInput = document.getElementById('input-nama');
    const hargaInput = document.getElementById('input-harga'); // hanya ada di halaman Ukuran

    input.addEventListener('change', function () {
      const file = this.files[0];
      if (!file) return;

      labelText.textContent = file.name;
      const reader = new FileReader();
      reader.onload = function (e) {
        img.src = e.target.result;
        img.style.display = 'block';
        if (icon) icon.style.display = 'none';

        previewImg.src = e.target.result;
        previewImg.style.display = 'block';
        if (previewImgIcon) previewImgIcon.style.display = 'none';
      };
      reader.readAsDataURL(file);
    });

    if (namaInput && previewNama) {
      namaInput.addEventListener('input', function () {
        previewNama.textContent = this.value.trim() || previewNama.dataset.placeholder || 'Nama';
      });
    }

    if (hargaInput && previewHarga) {
      hargaInput.addEventListener('input', function () {
        const angka = this.value ? Number(this.value).toLocaleString('id-ID') : '0';
        previewHarga.textContent = 'Rp ' + angka;
      });
    }
  }
});
