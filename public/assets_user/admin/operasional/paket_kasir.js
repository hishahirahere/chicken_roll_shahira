// Form "Beri Paket ke Kasir": hitung otomatis pcs, kali pakai, dan jumlah tepung (premix basah: 1 botol = 1 liter, tepung roti: 1 bungkus = 1 kg).
(function () {
  const form = document.getElementById('form-paket');
  const cfg = window.paketConfig;
  if (!form || !cfg) return;

  const set = cfg.set;
  const num = (v) => { const n = parseFloat(v); return isNaN(n) ? 0 : n; };
  const fmt = (n, d = 2) => (Math.round(n * Math.pow(10, d)) / Math.pow(10, d)).toLocaleString('id-ID', { maximumFractionDigits: d });

  const tanggal = document.getElementById('paket-tanggal');
  if (tanggal && !tanggal.value) {
    const t = new Date();
    tanggal.value = t.getFullYear() + '-' + String(t.getMonth() + 1).padStart(2, '0') + '-' + String(t.getDate()).padStart(2, '0');
  }

  function hitung() {
    let kg = 0, pcs = 0;
    cfg.ukuran.forEach((u) => {
      const el = form.querySelector('input[name="kg[' + u.id + ']"]');
      const v = el ? num(el.value) : 0;
      const p = Math.floor(v * u.hasil + 1e-9);
      kg += v; pcs += p;
      const out = form.querySelector('[data-hasil="' + u.id + '"]');
      if (out) out.textContent = '= ' + p + ' pcs';
    });
    document.getElementById('paket-total-ayam').textContent = 'Total: ' + fmt(kg, 0) + ' kg ayam · ' + pcs + ' pcs';

    // Tepung: premix basah 1 botol = 1 liter, tepung roti 1 bungkus = 1 kg (data-isi per satuan, data-unit = liter / kg)
    form.querySelectorAll('[data-tepung]').forEach((row) => {
      const v = num(row.querySelector('input').value);
      const out = row.querySelector('[data-hasil-tepung]');
      if (out) out.textContent = '= ' + fmt(v * num(row.dataset.isi || 1), 1) + ' ' + (row.dataset.unit || '');
    });

    form.querySelectorAll('[data-bahan]').forEach((row) => {
      const v = num(row.querySelector('input').value);
      const out = row.querySelector('[data-hasil-bahan]');
      if (out) out.textContent = '= ' + fmt(v * num(row.dataset.kap), 0) + ' kali pakai';
    });
  }

  function isiStandar(tambah) {
    const plus = (el, v) => { el.value = fmt((tambah ? num(el.value) : 0) + v, 0).replace(/\./g, '').replace(',', '.'); };
    cfg.ukuran.forEach((u) => plus(form.querySelector('input[name="kg[' + u.id + ']"]'), num(set.paket_kg_per_ukuran)));
    // Tepung: premix basah (botol) & tepung roti (bungkus)
    const premixBasah = form.querySelector('input[name="premix_basah"]');
    const tepungRoti = form.querySelector('input[name="tepung_roti"]');
    if (premixBasah) plus(premixBasah, num(set.paket_premix_basah_botol));
    if (tepungRoti) plus(tepungRoti, num(set.paket_tepung_roti_bungkus));
    form.querySelectorAll('[data-bahan]').forEach((row) => {
      const key = { filling: 'paket_filling_bungkus', saus: 'paket_saus_bungkus', bumbu: 'paket_bumbu_bungkus' }[row.dataset.peran];
      plus(row.querySelector('input'), num(set[key]));
    });
    // Kemasan tiap ukuran = jumlah pcs hasil ayam ukuran itu
    cfg.ukuran.forEach((u) => {
      const el = form.querySelector('input[name="kemasan[' + u.id + ']"]');
      if (el) plus(el, Math.floor(num(set.paket_kg_per_ukuran) * u.hasil + 1e-9));
    });
    hitung();
  }

  document.getElementById('paket-std').addEventListener('click', () => isiStandar(false));
  document.getElementById('paket-tambah').addEventListener('click', () => isiStandar(true));
  document.getElementById('paket-reset').addEventListener('click', () => {
    form.querySelectorAll('input[type=number]').forEach((i) => { i.value = ''; });
    hitung();
  });
  form.addEventListener('input', hitung);
  hitung();

  if (window.stokConfig && window.stokConfig.bukaPaket && window.bootstrap) {
    new bootstrap.Modal(document.getElementById('modalPaket')).show();
  }
})();
