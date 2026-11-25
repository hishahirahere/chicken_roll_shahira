// Stok Kasir (admin): ringkasan semua kasir (1 baris per kasir) + detail satu kasir.
// Filter status, jenis stok, dan pencarian bahan berlaku di keduanya. Semua di sisi browser.
(function () {
  function mulai() {
    var panel = Array.prototype.slice.call(document.querySelectorAll('.sk-panel'));
    if (!panel.length) return;

    var pilihKasir = document.getElementById('sk-kasir');
    var tombolStatus = document.querySelectorAll('#sk-status button');
    var pilihKategori = document.getElementById('sk-kategori');
    var cari = document.getElementById('sk-cari');
    var kosong = document.getElementById('sk-kosong');
    var papan = document.getElementById('sk-board');
    var ringkasan = document.getElementById('sk-ringkasan');
    var kembali = document.getElementById('sk-kembali');
    var baris = Array.prototype.slice.call(document.querySelectorAll('.sk-rrow'));

    var KEY = 'stok-kasir-ui';
    var baca = function (k) { try { return sessionStorage.getItem(KEY + ':' + k); } catch (e) { return null; } };
    var simpan = function (k, v) { try { sessionStorage.setItem(KEY + ':' + k, v); } catch (e) {} };

    var kasir = baca('kasir') || 'semua';
    var status = baca('status') || 'semua';
    var kategori = baca('kategori') || 'semua';

    // Datang dari notifikasi lonceng (?kasir=ID&status=...): langsung buka kasir itu
    try {
      var qs = new URLSearchParams(location.search);
      if (qs.get('kasir')) kasir = qs.get('kasir');
      if (qs.get('status')) status = qs.get('status');
    } catch (e) {}

    // Pulihkan pilihan terakhir (kalau kasirnya masih ada)
    if (pilihKasir) {
      if (!Array.prototype.some.call(pilihKasir.options, function (o) { return o.value === kasir; })) kasir = 'semua';
      pilihKasir.value = kasir;
    }
    if (!Array.prototype.some.call(tombolStatus, function (t) { return t.dataset.status === status; })) status = 'semua';
    if (pilihKategori) {
      if (!Array.prototype.some.call(pilihKategori.options, function (o) { return o.value === kategori; })) kategori = 'semua';
      pilihKategori.value = kategori;
    }
    tombolStatus.forEach(function (t) { t.classList.toggle('on', t.dataset.status === status); });

    function pilihKasirBaru(id) {
      kasir = id; simpan('kasir', id);
      if (pilihKasir) pilihKasir.value = id;
      terapkan();
      var f = document.getElementById('sk-filter');
      if (f && f.scrollIntoView) f.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }

    function terapkan() {
      var kata = cari ? cari.value.trim().toLowerCase() : '';
      var disaring = kata !== '' || kategori !== 'semua' || status !== 'semua';
      var hitung = { semua: 0, aman: 0, menipis: 0, habis: 0 };
      var perKasir = {};
      var detail = kasir !== 'semua';

      panel.forEach(function (p) {
        var id = p.dataset.kasir;
        var c = perKasir[id] = { semua: 0, aman: 0, menipis: 0, habis: 0 };
        var adaItem = false;

        p.querySelectorAll('.sk-group').forEach(function (g) {
          var adaDiGrup = false;
          g.querySelectorAll('.sk-item').forEach(function (it) {
            var cocokNama = (it.dataset.nama || '').indexOf(kata) !== -1;
            var cocokKat = kategori === 'semua' || it.dataset.kategori === kategori;
            // Angka mengikuti jenis + pencarian yang aktif (belum termasuk filter status)
            if (cocokNama && cocokKat) { c.semua++; c[it.dataset.status] = (c[it.dataset.status] || 0) + 1; }
            var ok = cocokNama && cocokKat && (status === 'semua' || it.dataset.status === status);
            it.style.display = ok ? '' : 'none';
            if (ok) adaDiGrup = true;
          });
          g.style.display = adaDiGrup ? '' : 'none';
          if (adaDiGrup) adaItem = true;
        });

        if (kasir === 'semua' || id === kasir) {
          hitung.semua += c.semua; hitung.aman += c.aman; hitung.menipis += c.menipis; hitung.habis += c.habis;
        }
        p.classList.toggle('sk-none', !adaItem && disaring);
        p.style.display = detail && id === kasir ? '' : 'none';
      });

      // Ringkasan: satu baris per kasir
      var barisTampil = 0;
      baris.forEach(function (r) {
        var c = perKasir[r.dataset.kasir] || { semua: 0, aman: 0, menipis: 0, habis: 0 };
        ['habis', 'menipis', 'aman'].forEach(function (k) {
          var el = r.querySelector('[data-hitung="' + k + '"]');
          if (el) { el.textContent = c[k]; el.classList.toggle('is-nol', c[k] === 0); }
        });
        var cocok = status === 'semua' ? c.semua : c[status];
        var tampil = !disaring || cocok > 0;
        r.style.display = tampil ? '' : 'none';
        if (tampil) barisTampil++;
      });

      if (ringkasan) ringkasan.style.display = detail ? 'none' : '';
      if (papan) papan.style.display = detail ? '' : 'none';
      if (kembali) kembali.hidden = !detail;

      tombolStatus.forEach(function (b) {
        var n = b.querySelector('b');
        if (n) n.textContent = hitung[b.dataset.status] || 0;
      });
      if (kosong) kosong.classList.toggle('show', !detail && barisTampil === 0);
    }

    if (pilihKasir) pilihKasir.addEventListener('change', function () { pilihKasirBaru(this.value); });
    if (kembali) kembali.addEventListener('click', function () { pilihKasirBaru('semua'); });
    document.querySelectorAll('.sk-lihat').forEach(function (b) {
      b.addEventListener('click', function () { pilihKasirBaru(b.dataset.buka); });
    });
    baris.forEach(function (r) {
      r.addEventListener('click', function (e) { if (!e.target.closest('button')) pilihKasirBaru(r.dataset.kasir); });
    });
    tombolStatus.forEach(function (b) {
      b.addEventListener('click', function () {
        status = b.dataset.status; simpan('status', status);
        tombolStatus.forEach(function (x) { x.classList.toggle('on', x === b); });
        terapkan();
      });
    });
    if (pilihKategori) pilihKategori.addEventListener('change', function () { kategori = this.value; simpan('kategori', kategori); terapkan(); });
    if (cari) cari.addEventListener('input', terapkan);

    // Kunci tinggi daftar ringkasan sesuai kondisi awal (semua baris tampil), supaya
    // kotaknya tidak mengecil / membesar saat filter diganti.
    var rsc = document.querySelector('.sk-rscroll');
    if (rsc) rsc.style.height = Math.min(rsc.scrollHeight, 560) + 'px';

    terapkan();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mulai);
  else mulai();
})();
