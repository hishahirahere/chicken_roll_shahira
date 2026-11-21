// Asal: resources/views/kasir/riwayat_transaksi/index.blade.php
(function () {
  const modalEl = document.getElementById('modalStruk');
  if (!modalEl) return;

  const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
  const body = document.getElementById('stBody');
  const kodeEl = document.getElementById('stKode');
  const printBtn = document.getElementById('stPrint');
  const cache = new Map();   // url -> html struk (tidak perlu diambil ulang)
  let current = '';          // html struk yang sedang tampil
  let req = 0;               // penanda permintaan terbaru (hindari tumpang tindih)

  function tampil(html) {
    current = html;
    body.innerHTML = html;
    printBtn.disabled = false;
  }

  function gagal(url) {
    current = '';
    printBtn.disabled = true;
    body.innerHTML = '<div class="st-err"><i class="bi bi-exclamation-circle"></i>Struk tidak bisa dimuat.<br><a href="' + url + '">Buka halaman struk</a></div>';
  }

  async function buka(url, kode) {
    const id = ++req;
    kodeEl.textContent = kode ? '- ' + kode : '';
    printBtn.disabled = true;

    if (cache.has(url)) {
      tampil(cache.get(url));
      modal.show();
      return;
    }

    body.innerHTML = '<div class="st-load"><div class="spinner-border" role="status"></div><div>Memuat struk...</div></div>';
    modal.show();

    try {
      const res = await fetch(url, {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
      });
      if (!res.ok) throw new Error('HTTP ' + res.status);

      const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
      const struk = doc.querySelector('.struk');
      if (!struk) throw new Error('Struk tidak ditemukan');

      cache.set(url, struk.outerHTML);
      if (id === req) tampil(struk.outerHTML);
    } catch (e) {
      if (id === req) gagal(url);
    }
  }

  // klik tombol "Struk" di tabel
  document.addEventListener('click', function (e) {
    const a = e.target.closest('[data-struk]');
    if (!a) return;
    e.preventDefault();
    buka(a.dataset.struk, a.dataset.kode);
  });

  // cetak struk: alur yang sama dengan semua halaman (assets_user/unduh.js)
  printBtn.addEventListener('click', function () {
    if (!current) return;
    CCR.cetakStruk(current, document.getElementById('struk-css').href);
  });
})();
