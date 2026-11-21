// Dua form pengambilan stok kasir:
//  1) Paket Ayam (#form-paket): ayam per ukuran (total kelipatan 1 paket) + tepung + kemasan.
//  2) Bahan Pendukung (#form-pendukung): filling / saus celup / bumbu tabur, bebas per rasa (tidak ikut paket).
(function () {
  const cfg = window.paketConfig;
  if (!cfg) return;

  const set = cfg.set;
  const perPaket = Math.max(1, parseInt(cfg.kgPerPaket, 10) || 3);
  const num = (v) => { const n = parseFloat(v); return isNaN(n) ? 0 : n; };
  const fmt = (n, d = 2) => (Math.round(n * Math.pow(10, d)) / Math.pow(10, d)).toLocaleString('id-ID', { maximumFractionDigits: d });
  const plain = (n) => String(Math.round(n));

  function isiTanggal(id) {
    const el = document.getElementById(id);
    if (el && !el.value) {
      const t = new Date();
      el.value = t.getFullYear() + '-' + String(t.getMonth() + 1).padStart(2, '0') + '-' + String(t.getDate()).padStart(2, '0');
    }
  }

  // ================= 1) PAKET AYAM =================
  const form = document.getElementById('form-paket');
  if (form) {
    isiTanggal('paket-tanggal');
    const submit = form.querySelector('button[type=submit]');
    const premix = form.querySelector('input[name="premix_basah"]');
    const roti = form.querySelector('input[name="tepung_roti"]');

    const kgInput = (id) => form.querySelector('input[name="kg[' + id + ']"]');
    const totalKg = () => cfg.ukuran.reduce((a, u) => a + (kgInput(u.id) ? num(kgInput(u.id).value) : 0), 0);

    // Tepung & kemasan terkunci: selalu mengikuti jumlah paket dan ayam per ukuran
    function sinkron() {
      const paket = Math.floor(totalKg() / perPaket + 1e-9);
      const auto = (el, v) => { if (el) el.value = v > 0 ? plain(v) : ''; };
      auto(premix, paket * num(set.paket_premix_basah_botol));
      auto(roti, paket * num(set.paket_tepung_roti_bungkus));
      cfg.ukuran.forEach((u) => {
        auto(form.querySelector('input[name="kemasan[' + u.id + ']"]'), Math.floor(num(kgInput(u.id) ? kgInput(u.id).value : 0) * u.hasil + 1e-9));
      });
    }

    function hitung() {
      let kg = 0, pcs = 0;
      cfg.ukuran.forEach((u) => {
        const v = kgInput(u.id) ? num(kgInput(u.id).value) : 0;
        const p = Math.floor(v * u.hasil + 1e-9);
        kg += v; pcs += p;
        const out = form.querySelector('[data-hasil="' + u.id + '"]');
        if (out) out.textContent = '= ' + p + ' pcs';
      });
      document.getElementById('paket-total-ayam').textContent = 'Total: ' + fmt(kg, 0) + ' kg ayam · ' + pcs + ' pcs';

      // Cek kelipatan paket
      const cek = document.getElementById('paket-cek');
      let valid = false;
      if (kg <= 0) {
        cek.textContent = '1 paket = ' + perPaket + ' kg ayam.';
        cek.style.color = '';
      } else if (Math.round(kg) % perPaket === 0 && kg === Math.round(kg)) {
        valid = true;
        cek.textContent = '✓ ' + (kg / perPaket) + ' paket';
        cek.style.color = '#15803d';
      } else {
        const bawah = Math.floor(kg / perPaket) * perPaket;
        cek.textContent = '✗ Bukan kelipatan paket. Gunakan ' + (bawah > 0 ? bawah + ' atau ' : '') + (bawah + perPaket) + ' kg.';
        cek.style.color = '#b91c1c';
      }
      if (submit) submit.disabled = !valid;

      form.querySelectorAll('[data-tepung]').forEach((row) => {
        const v = num(row.querySelector('input').value);
        const out = row.querySelector('[data-hasil-tepung]');
        if (out) out.textContent = '= ' + fmt(v * num(row.dataset.isi || 1), 1) + ' ' + (row.dataset.unit || '');
      });
    }

    function isiPaket(tambah) {
      // 1 paket standar: kg per ukuran sesuai pengaturan (total = perPaket kg)
      cfg.ukuran.forEach((u) => {
        const el = kgInput(u.id);
        if (el) el.value = plain((tambah ? num(el.value) : 0) + num(set.paket_kg_per_ukuran));
      });
      sinkron();
      hitung();
    }

    document.getElementById('paket-std').addEventListener('click', () => isiPaket(false));
    document.getElementById('paket-tambah').addEventListener('click', () => isiPaket(true));
    document.getElementById('paket-reset').addEventListener('click', () => {
      form.querySelectorAll('input[type=number]').forEach((i) => { i.value = ''; });
      hitung();
    });
    form.addEventListener('input', (e) => {
      const t = e.target;
      if (t.name && t.name.indexOf('kg[') === 0) {
        sinkron();
      }
      hitung();
    });
    hitung();
  }

  // ================= 2) BAHAN PENDUKUNG =================
  const fp = document.getElementById('form-pendukung');
  if (fp) {
    isiTanggal('pendukung-tanggal');

    function hitungP() {
      fp.querySelectorAll('[data-bahan]').forEach((row) => {
        const v = num(row.querySelector('input').value);
        const out = row.querySelector('[data-hasil-bahan]');
        if (out) out.textContent = '= ' + fmt(v * num(row.dataset.kap), 0) + ' kali pakai';
      });
    }

    document.getElementById('pendukung-std').addEventListener('click', () => {
      const key = { filling: 'paket_filling_bungkus', saus: 'paket_saus_bungkus', bumbu: 'paket_bumbu_bungkus' };
      fp.querySelectorAll('[data-bahan]').forEach((row) => { row.querySelector('input').value = plain(num(set[key[row.dataset.peran]])); });
      hitungP();
    });
    document.getElementById('pendukung-reset').addEventListener('click', () => {
      fp.querySelectorAll('input[type=number]').forEach((i) => { i.value = ''; });
      hitungP();
    });
    fp.addEventListener('input', hitungP);
    hitungP();
  }

  // Buka modal otomatis (setelah galat validasi atau dari menu samping)
  if (window.stokConfig && window.bootstrap) {
    if (window.stokConfig.bukaPaket) new bootstrap.Modal(document.getElementById('modalPaket')).show();
    if (window.stokConfig.bukaPendukung) new bootstrap.Modal(document.getElementById('modalPendukung')).show();
  }
})();
