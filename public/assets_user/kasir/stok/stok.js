// Stok Saya (kasir): tab per kelompok + filter status + pencarian.
// Satu kelompok tampil sekali (tidak perlu scroll panjang). Saat mencari, kelompok pertama yang punya hasil dibuka otomatis.
(function () {
  function mulai() {
    var tabs = Array.prototype.slice.call(document.querySelectorAll('.ks-tab'));
    var seksi = Array.prototype.slice.call(document.querySelectorAll('.ks-section'));
    var tombol = document.querySelectorAll('#ks-filter button');
    var cari = document.getElementById('search-stok');
    var kosong = document.getElementById('ks-nohit');
    var status = 'semua';
    var aktif = tabs.length ? tabs[0].dataset.tab : null;

    // Kelompok pertama yang sedang menipis/habis dibuka lebih dulu supaya langsung kelihatan
    var penting = seksi.filter(function (s) { return s.querySelector('.ks-tile.habis, .ks-tile.menipis'); })[0];
    if (penting) aktif = penting.dataset.section;

    function hitung(sec) {
      var kata = cari ? cari.value.trim().toLowerCase() : '';
      var n = 0;
      Array.prototype.forEach.call(sec.querySelectorAll('.ks-tile'), function (t) {
        var ok = (t.dataset.nama || '').indexOf(kata) !== -1 && (status === 'semua' || t.dataset.status === status);
        t.style.display = ok ? '' : 'none';
        if (ok) n++;
      });
      return n;
    }

    function terapkan() {
      var jumlah = {};
      seksi.forEach(function (s) { jumlah[s.dataset.section] = hitung(s); });

      // Kalau kelompok aktif kosong karena filter, pindah ke kelompok pertama yang ada hasilnya
      if (!jumlah[aktif]) {
        var ada = Object.keys(jumlah).filter(function (k) { return jumlah[k] > 0; })[0];
        if (ada) aktif = ada;
      }
      tabs.forEach(function (t) {
        var c = t.querySelector('[data-count]');
        if (c) c.textContent = jumlah[t.dataset.tab] || 0;
        t.classList.toggle('on', t.dataset.tab === aktif);
        t.classList.toggle('kosong', !jumlah[t.dataset.tab]);
      });
      seksi.forEach(function (s) { s.classList.toggle('on', s.dataset.section === aktif && jumlah[s.dataset.section] > 0); });
      var total = Object.keys(jumlah).reduce(function (a, k) { return a + jumlah[k]; }, 0);
      if (kosong) kosong.classList.toggle('show', seksi.length > 0 && total === 0);
    }

    tabs.forEach(function (t) {
      t.addEventListener('click', function () { aktif = t.dataset.tab; terapkan(); });
    });
    tombol.forEach(function (b) {
      b.addEventListener('click', function () {
        status = b.dataset.filter;
        tombol.forEach(function (x) { x.classList.toggle('on', x === b); });
        terapkan();
      });
    });
    if (cari) cari.addEventListener('input', terapkan);
    terapkan();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mulai);
  else mulai();
})();
