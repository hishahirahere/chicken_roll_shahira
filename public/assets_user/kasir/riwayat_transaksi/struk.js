// Asal: resources/views/kasir/riwayat_transaksi/struk.blade.php
// Cetak struk memakai alur yang sama dengan semua halaman (lihat assets_user/unduh.js)
window.cetakStruk = function () {
  const sumber = document.querySelector('.sk-right .struk');
  if (!sumber) return;
  CCR.cetakStruk(sumber.outerHTML, document.getElementById('struk-css').href);
};
