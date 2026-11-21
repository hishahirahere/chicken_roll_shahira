// Manajemen Stok: modal catat stok masuk / keluar (pencarian & filter kartu ada di master.js)
document.addEventListener('DOMContentLoaded', function () {
  // ---- Modal catat stok (masuk / keluar) ----
  const modalEl = document.getElementById('modalStok');
  const form = document.getElementById('form-stok');
  const header = document.getElementById('stok-header');
  const icon = document.getElementById('stok-icon');
  const judul = document.getElementById('stok-judul');
  const inputJenis = document.getElementById('stok-jenis');
  const selectBahan = document.getElementById('stok-bahan');
  const info = document.getElementById('stok-info');
  const infoLabel = document.getElementById('stok-info-label');
  const infoNilai = document.getElementById('stok-info-nilai');
  const satuanEl = document.getElementById('stok-satuan');
  const labelJumlah = document.getElementById('stok-label-jumlah');
  const inputTanggal = document.getElementById('stok-tanggal');
  const inputKeterangan = document.getElementById('stok-keterangan');

  const config = {
    masuk:  { title: 'Catat Stok Masuk',  icon: 'bi-box-arrow-in-down', action: form.dataset.actionMasuk,  info: 'Stok sekarang',  jumlah: 'Jumlah Masuk',  placeholder: 'Contoh: Belanja mingguan' },
    keluar: { title: 'Catat Stok Keluar Lain', icon: 'bi-box-arrow-up',      action: form.dataset.actionKeluar, info: 'Stok tersedia', jumlah: 'Jumlah Keluar', placeholder: 'Contoh: Rusak / dibuang' }
  };

  // Buka otomatis: setelah error validasi, atau dari menu "Input Stok Masuk"
  const autoJenis = window.stokConfig.autoJenis;
  let jenisAktif = autoJenis || 'masuk';

  function setJenis(jenis) {
    const c = config[jenis];
    form.action = c.action;
    inputJenis.value = jenis;
    header.classList.remove('masuk', 'keluar');
    header.classList.add(jenis);
    icon.className = 'bi ' + c.icon + ' me-2';
    judul.textContent = c.title;
    labelJumlah.textContent = c.jumlah;
    infoLabel.textContent = c.info;
    inputKeterangan.placeholder = c.placeholder;

    // Tampilkan field khusus jenis ini, nonaktifkan yang lain supaya tidak ikut terkirim
    form.querySelectorAll('[data-only]').forEach(function (el) {
      const aktif = el.dataset.only === jenis;
      el.classList.toggle('d-none', !aktif);
      el.querySelectorAll('input, select').forEach(function (i) { i.disabled = !aktif; });
    });
  }

  function updateInfo() {
    const opt = selectBahan.options[selectBahan.selectedIndex];
    if (selectBahan.value) {
      satuanEl.textContent = opt.dataset.satuan;
      infoNilai.textContent = opt.dataset.stok + ' ' + opt.dataset.satuan;
      info.classList.remove('d-none');
    } else {
      satuanEl.textContent = 'satuan';
      info.classList.add('d-none');
    }
  }

  function isiTanggalHariIni() {
    if (inputTanggal.value) return;
    // Pakai jam perangkat supaya tidak geser hari karena zona waktu
    inputTanggal.value = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
  }

  selectBahan.addEventListener('change', updateInfo);

  modalEl.addEventListener('show.bs.modal', function (event) {
    const trigger = event.relatedTarget;
    if (trigger && trigger.dataset && trigger.dataset.jenis) {
      jenisAktif = trigger.dataset.jenis;
    }
    setJenis(jenisAktif);
    // Tombol masuk/keluar di kartu bahan: pilih bahannya langsung
    if (trigger && trigger.dataset && trigger.dataset.bahan) {
      selectBahan.value = trigger.dataset.bahan;
    }
    isiTanggalHariIni();
    updateInfo();
  });

  // Fokus ke kolom jumlah begitu modal terbuka
  modalEl.addEventListener('shown.bs.modal', function () {
    form.querySelector('input[name="jumlah"]').focus();
  });

  // Bersihkan form setiap modal ditutup, supaya buka berikutnya mulai dari kosong
  modalEl.addEventListener('hidden.bs.modal', function () {
    form.querySelectorAll('input:not([type="hidden"]):not([type="date"]), select').forEach(function (el) {
      el.value = '';
      el.classList.remove('is-invalid');
    });
    form.querySelectorAll('.js-error').forEach(function (el) { el.remove(); });
    inputTanggal.value = '';
    updateInfo();
  });

  if (autoJenis) {
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
  }
});
