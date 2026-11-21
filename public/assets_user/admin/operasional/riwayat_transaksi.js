// Asal: resources/views/admin/operasional/riwayat_transaksi/index.blade.php
const rp = n => 'Rp ' + Number(n).toLocaleString('id-ID');
const el = id => document.getElementById(id);

el('modalDetail').addEventListener('show.bs.modal', function (e) {
  // e.relatedTarget = tombol mata yang diklik
  const d = JSON.parse(e.relatedTarget.dataset.detail);

  el('d-kode').textContent    = d.kode;
  el('d-metode').textContent  = d.metode;
  el('d-tanggal').textContent = d.tanggal;
  el('d-kasir').textContent   = d.kasir;
  el('d-pembeli').textContent = d.pembeli;
  el('d-total').textContent   = rp(d.total);
  el('d-total2').textContent  = rp(d.total);
  el('d-struk').dataset.cetakStruk = d.struk;

  const tbody = el('d-items');
  tbody.innerHTML = '';
  d.items.forEach(function (it, i) {
    const tr = document.createElement('tr');
    const cell = (text, cls) => { const td = document.createElement('td'); td.textContent = text; if (cls) td.className = cls; return td; };

    const menu = cell(it.menu);
    const rasa = document.createElement('small');
    rasa.className = 'd-block text-muted';
    rasa.textContent = it.rasa;
    menu.appendChild(rasa);

    tr.append(cell(i + 1), menu, cell(it.ukuran), cell(rp(it.harga), 'text-end'), cell(it.qty, 'text-center'), cell(rp(it.subtotal), 'text-end'));
    tbody.appendChild(tr);
  });
});
