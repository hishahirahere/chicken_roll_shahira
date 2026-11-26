// Asal: resources/views/kasir/transaksi_penjualan/index.blade.php
(function () {
  const root = document.getElementById('pos-root');
  if (!root) return;

  const DATA = window.posConfig.data;
  const USER_ID = window.posConfig.userId;
  const PAY_URL = window.posConfig.payUrl;
  const CSRF = document.querySelector('meta[name="csrf-token"]').content;
  const KEY = 'ccr_cart_user_' + USER_ID;
  const TOKEN_KEY = KEY + '_token';
  // Token unik per keranjang: server hanya memproses token yang sama satu kali (anti checkout ganda)
  const newToken = () => (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : (Date.now().toString(36) + Math.random().toString(36).slice(2));
  const getToken = () => { try { let t = localStorage.getItem(TOKEN_KEY); if (!t) { t = newToken(); localStorage.setItem(TOKEN_KEY, t); } return t; } catch (e) { return payTokenMem || (payTokenMem = newToken()); } };
  let payTokenMem = null;

  // ---------- helper ----------
  const rp = n => 'Rp ' + Number(n).toLocaleString('id-ID');
  const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const byId = (list, id) => list.find(x => x.id === id);
  const rasaList = cara => cara === 'saus_celup' ? DATA.saus : cara === 'bumbu_tabur' ? DATA.bumbu : [];
  const caraLabel = cara => cara === 'saus_celup' ? 'Saus Celup' : 'Bubuk Tabur';
  const img = (url, icon, cls) => url
    ? `<img src="${esc(url)}" alt="" class="${cls}" loading="lazy" onerror="this.outerHTML='<div class=&quot;${cls}&quot;><i class=&quot;bi ${icon}&quot;></i></div>'">`
    : `<div class="${cls}"><i class="bi ${icon}"></i></div>`;

  // ---------- state ----------
  const validItem = i =>
    byId(DATA.ukuran, i.ukuran_id) && byId(DATA.filling, i.filling_id) &&
    (i.cara_luar === 'saus_celup' || i.cara_luar === 'bumbu_tabur') &&
    byId(rasaList(i.cara_luar), i.rasa_id) && i.qty > 0;

  let cart = [];
  try { cart = (JSON.parse(localStorage.getItem(KEY) || '[]') || []).filter(validItem); } catch (e) { cart = []; }
  const save = () => { try { localStorage.setItem(KEY, JSON.stringify(cart)); } catch (e) {} };

  // ---------- stok ----------
  // item.stok = sisa stok di tangan kasir saat halaman dibuka (null = tidak dibatasi).
  // Jenis stok: 'ukuran' | 'filling' | 'saus' | 'bumbu'.
  let notice = null; // pesan singkat untuk kasir (mis. stok tidak cukup)

  const jenisRasa = cara => cara === 'saus_celup' ? 'saus' : 'bumbu';

  // Jumlah porsi menu ini yang sudah ada di keranjang
  function pakaiDiKeranjang(jenis, id) {
    return cart.reduce((n, i) => {
      const cocok = jenis === 'ukuran' ? i.ukuran_id === id
        : jenis === 'filling' ? i.filling_id === id
        : jenisRasa(i.cara_luar) === jenis && i.rasa_id === id;
      return cocok ? n + i.qty : n;
    }, 0);
  }

  // Sisa yang masih boleh ditambahkan ke pesanan (stok dikurangi yang sudah ada di keranjang)
  function sisaStok(jenis, item) {
    return item.stok == null ? Infinity : item.stok - pakaiDiKeranjang(jenis, item.id);
  }

  // Sisa porsi yang masih bisa dibuat untuk satu racikan (ukuran + filling + rasa), ambil yang paling sedikit
  function sisaRacikan(i) {
    return Math.min(
      sisaStok('ukuran', byId(DATA.ukuran, i.ukuran_id)),
      sisaStok('filling', byId(DATA.filling, i.filling_id)),
      sisaStok(jenisRasa(i.cara_luar), byId(rasaList(i.cara_luar), i.rasa_id))
    );
  }

  // Nama menu penyebab racikan tidak bisa ditambah
  function namaKurang(i) {
    return [
      ['ukuran', byId(DATA.ukuran, i.ukuran_id)],
      ['filling', byId(DATA.filling, i.filling_id)],
      [jenisRasa(i.cara_luar), byId(rasaList(i.cara_luar), i.rasa_id)],
    ].filter(([j, m]) => sisaStok(j, m) < 1).map(([, m]) => m.nama).join(', ');
  }

  // Keranjang lama (tersimpan di browser) bisa melebihi stok terbaru: kurangi jumlahnya, hapus kalau stok habis
  function sesuaikanKeranjang() {
    const dipakai = {};
    let berubah = false;
    cart = cart.filter(i => {
      const kunci = [
        ['ukuran', byId(DATA.ukuran, i.ukuran_id)],
        ['filling', byId(DATA.filling, i.filling_id)],
        [jenisRasa(i.cara_luar), byId(rasaList(i.cara_luar), i.rasa_id)],
      ];
      let maks = i.qty;
      kunci.forEach(([j, m]) => {
        if (m.stok != null) maks = Math.min(maks, m.stok - (dipakai[j + ':' + m.id] || 0));
      });
      maks = Math.max(0, maks);
      if (maks < i.qty) berubah = true;
      i.qty = maks;
      kunci.forEach(([j, m]) => { dipakai[j + ':' + m.id] = (dipakai[j + ':' + m.id] || 0) + maks; });
      return maks > 0;
    });
    if (berubah) {
      notice = 'Pesanan disesuaikan dengan stok terbaru. Ada menu yang stoknya sudah habis atau berkurang.';
      save();
    }
  }
  sesuaikanKeranjang();

  // Jenis topping awalnya kosong: kasir memilih dulu saus celup atau bubuk tabur
  const freshDraft = () => ({ ukuran: null, filling: null, cara: null, rasa: null });

  let draft = freshDraft();
  let page = 1;          // 1 = Ukuran, 2 = Filling, 3 = Topping Luar (jenis lalu rasa)
  let animate = false;   // animasi geser hanya saat pindah halaman
  let searchTerm = '';
  let flashIdx = null;

  const NEXT = ['Lanjut ke Filling', 'Lanjut ke Topping Luar', 'Tambah ke Pesanan'];
  const total = () => cart.reduce((sum, i) => sum + byId(DATA.ukuran, i.ukuran_id).harga * i.qty, 0);
  const cartCount = () => cart.reduce((n, i) => n + i.qty, 0);

  function canNext() {
    if (page === 1) return !!draft.ukuran;
    if (page === 2) return !!draft.filling;
    return !!draft.rasa;
  }

  // Pilihan yang stoknya baru saja habis (mis. terpakai pesanan lain di keranjang) tidak boleh tetap terpilih
  function bersihkanDraft() {
    const u = draft.ukuran && byId(DATA.ukuran, draft.ukuran);
    const f = draft.filling && byId(DATA.filling, draft.filling);
    const r = draft.rasa && byId(rasaList(draft.cara), draft.rasa);
    if (u && sisaStok('ukuran', u) < 1) draft.ukuran = null;
    if (f && sisaStok('filling', f) < 1) draft.filling = null;
    if (r && sisaStok(jenisRasa(draft.cara), r) < 1) draft.rasa = null;
  }

  // ---------- render ----------
  // jenis = jenis stok menu ini ('ukuran' | 'filling' | 'saus' | 'bumbu')
  function optCard(kind, item, selected, extra, jenis) {
    const habis = item.stok != null && item.stok <= 0;     // stok kasir memang kosong
    const sisa = sisaStok(jenis, item);
    const penuh = !habis && sisa <= 0;                      // stok ada, tapi semuanya sudah masuk pesanan
    const kunci = habis || penuh;

    let badge = '';
    if (habis) badge = '<div class="opt-stok habis"><i class="bi bi-slash-circle"></i> Stok habis</div>';
    else if (penuh) badge = '<div class="opt-stok habis"><i class="bi bi-slash-circle"></i> Semua sudah di pesanan</div>';
    else if (item.stok != null && sisa <= 10) badge = `<div class="opt-stok low"><i class="bi bi-exclamation-triangle-fill"></i> Sisa ${sisa}</div>`;

    // Kartu yang habis tidak punya data-act, jadi klik tidak melakukan apa pun
    const aksi = kunci ? 'aria-disabled="true"' : `data-act="pick" data-kind="${kind}" data-id="${item.id}"`;
    return `<div class="opt-card ${selected ? 'selected' : ''} ${kunci ? 'habis' : ''}" ${aksi} data-name="${esc(item.nama.toLowerCase())}">
      <span class="check"><i class="bi bi-check-lg"></i></span>
      ${img(item.foto, 'bi-image', 'opt-img')}
      <div class="opt-name">${esc(item.nama)}</div>
      ${extra || ''}
      ${badge}
    </div>`;
  }

  function secHead(icon, title, hint, doneText) {
    return `<div class="sec-head">
      <span class="sec-ico"><i class="bi ${icon}"></i></span>
      <div class="sec-t"><h3>${title}</h3><p>${hint}</p></div>
      ${doneText ? `<span class="sec-done"><i class="bi bi-check-circle-fill"></i> ${esc(doneText)}</span>` : ''}
    </div>`;
  }

  function stageHTML() {
    const u = draft.ukuran ? byId(DATA.ukuran, draft.ukuran) : null;
    const f = draft.filling ? byId(DATA.filling, draft.filling) : null;
    const r = draft.rasa ? byId(rasaList(draft.cara), draft.rasa) : null;
    let head, body;

    if (page === 1) {
      head = secHead('bi-rulers', 'Pilih Ukuran', 'Tentukan ukuran chicken roll yang diinginkan.', u && u.nama);
      body = `<div class="opt-grid">${DATA.ukuran.map(x => optCard('ukuran', x, draft.ukuran === x.id, `<div class="opt-price">${rp(x.harga)}</div>`, 'ukuran')).join('')}</div>`;
    } else if (page === 2) {
      head = secHead('bi-egg-fried', 'Pilih Filling', 'Pilih isian di dalam chicken roll.', f && f.nama);
      body = `<div class="opt-grid compact">${DATA.filling.map(x => optCard('filling', x, draft.filling === x.id, '', 'filling')).join('')}</div>`;
    } else if (!draft.cara) {
      // Langkah 3a: pilih jenis topping dulu
      head = secHead('bi-droplet-fill', 'Pilih Topping Luar', 'Mau pakai saus celup atau bubuk tabur?', null);
      const thumbs = (list, ico) => {
        const fotos = list.filter(x => x.foto).slice(0, 3);
        if (!fotos.length) return `<span class="type-ico"><i class="bi ${ico}"></i></span>`;
        return `<div class="type-thumbs">${fotos.map(x => img(x.foto, ico, 'thumb')).join('')}</div>`;
      };
      body = `<div class="type-grid">${[
        ['saus_celup', DATA.saus, 'bi-droplet-half', 'Dicelup ke saus'],
        ['bumbu_tabur', DATA.bumbu, 'bi-stars', 'Ditabur di luar roll']
      ].filter(([, l]) => l.length).map(([cara, l, ico, ket]) => `
        <div class="type-card" data-act="cara" data-id="${cara}">
          ${thumbs(l, ico)}
          <div class="type-name">${caraLabel(cara)}</div>
          <div class="type-desc">${ket}</div>
          <div class="type-count">${l.length} pilihan rasa</div>
        </div>`).join('')}</div>`;
    } else {
      // Langkah 3b: pilih rasa sesuai jenis yang dipilih
      const tabs = [['saus_celup', DATA.saus], ['bumbu_tabur', DATA.bumbu]]
        .filter(([, list]) => list.length)
        .map(([cara, list]) => `<button type="button" class="${draft.cara === cara ? 'on' : ''}" data-act="cara" data-id="${cara}">${caraLabel(cara)}<small>${list.length}</small></button>`)
        .join('');
      const list = rasaList(draft.cara);
      head = secHead('bi-droplet-fill', 'Pilih Rasa ' + caraLabel(draft.cara), 'Pilih rasa yang diinginkan.', r && (caraLabel(draft.cara) + ': ' + r.nama));
      body = `<div class="seg">${tabs}</div>` + (list.length
        ? `<div class="opt-grid compact">${list.map(x => optCard('rasa', x, draft.rasa === x.id, '', jenisRasa(draft.cara))).join('')}</div>`
        : `<div class="none-note">Belum ada pilihan untuk ${caraLabel(draft.cara)}.</div>`);
    }

    const icon = page === 3 ? 'bi-plus-lg' : 'bi-arrow-right';
    const label = page === 3 ? `<i class="bi ${icon}"></i> ${NEXT[2]}` : `${NEXT[page - 1]} <i class="bi ${icon}"></i>`;
    const foot = `<div class="stage-foot">
      ${page > 1 ? '<button type="button" class="btn-pos btn-pos-outline" data-act="prev"><i class="bi bi-arrow-left"></i> Kembali</button>' : '<span></span>'}
      <button type="button" class="btn-pos ${page === 3 ? 'gold' : ''}" data-act="next" ${canNext() ? '' : 'disabled'}>${label}</button>
    </div>`;

    return `<section class="sec">${head}<div class="sec-body ${animate ? 'enter' : ''}">${body}</div>${foot}</section>`;
  }

  function cartListHTML() {
    if (!cart.length) {
      return `<div class="op-empty"><i class="bi bi-bag"></i>Belum ada pesanan.<br>Pilih menu di sebelah kiri.</div>`;
    }
    return cart.map((i, idx) => {
      const u = byId(DATA.ukuran, i.ukuran_id), f = byId(DATA.filling, i.filling_id), r = byId(rasaList(i.cara_luar), i.rasa_id);
      return `<div class="op-item ${idx === flashIdx ? 'flash' : ''}">
        ${img(u.foto, 'bi-egg-fried', 'op-thumb')}
        <div class="op-info">
          <div class="op-title">Roll ${esc(u.nama)}</div>
          <div class="op-sub">${esc(f.nama)} &middot; ${caraLabel(i.cara_luar)} ${esc(r.nama)}</div>
          <div class="op-price">${rp(u.harga * i.qty)}</div>
        </div>
        <div class="op-ctrl">
          <div class="qty-box">
            <button type="button" class="qty-btn" data-act="qty" data-i="${idx}" data-d="-1">&minus;</button>
            <span class="qty-num">${i.qty}</span>
            <button type="button" class="qty-btn" data-act="qty" data-i="${idx}" data-d="1" ${sisaRacikan(i) < 1 ? 'disabled title="Stok tidak cukup untuk ditambah"' : ''}>+</button>
          </div>
          <button type="button" class="op-remove" title="Hapus" data-act="remove" data-i="${idx}"><i class="bi bi-trash-fill"></i> Hapus</button>
        </div>
      </div>`;
    }).join('');
  }

  function panelHTML() {
    const u = draft.ukuran ? byId(DATA.ukuran, draft.ukuran) : null;
    const f = draft.filling ? byId(DATA.filling, draft.filling) : null;
    const r = draft.rasa ? byId(rasaList(draft.cara), draft.rasa) : null;
    const chip = (icon, label, val, goPage) => `<div class="chip ${val ? 'on' : ''}" data-act="goto" data-page="${goPage}"><i class="bi ${icon}"></i><span>${val ? esc(val) : label}</span></div>`;

    return `<aside class="order-panel">
      <div class="op-head">
        <h2><i class="bi bi-bag-check-fill"></i> Pesanan</h2>
        <span class="op-badge">${cartCount()} porsi</span>
      </div>

      <div class="builder">
        <div class="bd-top" data-act="goto" data-page="1">
          ${img(u ? u.foto : null, 'bi-egg-fried', 'opt-img')}
          <div>
            <div class="bd-k">Racikan saat ini</div>
            <div class="bd-t">${u ? 'Chicken Crunchy Roll ' + esc(u.nama) : 'Belum pilih ukuran'}</div>
            <div class="bd-p">${u ? rp(u.harga) : 'Rp 0'}</div>
          </div>
        </div>
        <div class="chips">
          ${chip('bi-egg-fried', 'Pilih filling', f && f.nama, 2)}
          ${chip('bi-droplet-fill', 'Pilih topping luar', r && (caraLabel(draft.cara) + ': ' + r.nama), 3)}
        </div>
      </div>

      <div class="op-list">${cartListHTML()}</div>

      <div class="op-foot">
        <div class="op-total"><span class="k">Total Pembayaran</span><span class="v">${rp(total())}</span></div>
        <button type="button" class="btn-pos block" data-act="pay" ${cart.length ? '' : 'disabled'}>Bayar Sekarang <i class="bi bi-arrow-right"></i></button>
      </div>
    </aside>`;
  }

  function mobileBarHTML() {
    const label = page === 3 ? `<i class="bi bi-plus-lg"></i> ${NEXT[2]}` : `${NEXT[page - 1]} <i class="bi bi-arrow-right"></i>`;
    return `<div class="mobile-bar">
      ${page > 1 ? '<button type="button" class="mb-icon" data-act="prev" aria-label="Kembali"><i class="bi bi-arrow-left"></i></button>' : ''}
      <button type="button" class="mb-icon" data-act="to-panel" aria-label="Lihat pesanan"><i class="bi bi-bag"></i>${cartCount() ? `<span class="n">${cartCount()}</span>` : ''}</button>
      <button type="button" class="btn-pos ${page === 3 ? 'gold' : ''}" data-act="next" ${canNext() ? '' : 'disabled'}>${label}</button>
    </div>`;
  }

  function render() {
    bersihkanDraft();
    root.innerHTML = `
      <div class="pos-head">
        <h1>Transaksi Penjualan</h1>
        <p>Pilih menu satu per satu, lalu tambahkan ke pesanan.</p>
      </div>
      ${notice ? `<div class="pos-notice"><i class="bi bi-exclamation-triangle-fill"></i><span>${esc(notice)}</span></div>` : ''}
      <div class="row g-4">
        <div class="col-lg-8">${stageHTML()}</div>
        <div class="col-lg-4">${panelHTML()}</div>
      </div>${mobileBarHTML()}`;
    animate = false;
    flashIdx = null;
    applySearch();
  }

  function applySearch() {
    const q = searchTerm.trim().toLowerCase();
    root.querySelectorAll('.opt-card[data-name]').forEach(el => {
      el.style.display = (!q || el.dataset.name.includes(q)) ? '' : 'none';
    });
  }

  function go(n) { page = n; animate = true; render(); window.scrollTo({ top: 0, behavior: 'smooth' }); }

  function addDraftToCart() {
    const item = { ukuran_id: draft.ukuran, filling_id: draft.filling, cara_luar: draft.cara, rasa_id: draft.rasa, qty: 1 };
    // Stok tidak cukup: racikan TIDAK boleh masuk ke pesanan
    if (sisaRacikan(item) < 1) {
      notice = 'Stok ' + namaKurang(item) + ' habis, tidak bisa ditambahkan ke pesanan.';
      return false;
    }
    const sameIdx = cart.findIndex(i => i.ukuran_id === item.ukuran_id && i.filling_id === item.filling_id && i.cara_luar === item.cara_luar && i.rasa_id === item.rasa_id);
    if (sameIdx >= 0) {
      cart[sameIdx].qty = Math.min(50, cart[sameIdx].qty + 1);
      flashIdx = sameIdx;
    } else {
      cart.push(item);
      flashIdx = cart.length - 1;
    }
    save();
    draft = freshDraft();
    return true;
  }

  // ---------- klik ----------
  root.addEventListener('click', function (e) {
    const el = e.target.closest('[data-act]');
    if (!el) return;
    const act = el.dataset.act;
    notice = null;

    if (act === 'pick') {
      const kind = el.dataset.kind;
      const id = parseInt(el.dataset.id, 10);
      if (kind === 'ukuran') draft.ukuran = id;
      if (kind === 'filling') draft.filling = id;
      if (kind === 'rasa') draft.rasa = id;
      render();
    } else if (act === 'cara') {
      if (draft.cara !== el.dataset.id) { draft.cara = el.dataset.id; draft.rasa = null; }
      render();
    } else if (act === 'next') {
      if (!canNext()) return;
      if (page < 3) {
        go(page + 1);
      } else if (addDraftToCart()) {
        go(1); // selesai satu racikan: kembali ke Ukuran untuk pesanan berikutnya
      } else {
        render(); // stok tidak cukup: tetap di halaman ini, tampilkan pesan
      }
    } else if (act === 'prev') {
      if (page === 3 && draft.cara) { draft.cara = null; draft.rasa = null; render(); } // balik ke pilihan jenis
      else if (page > 1) go(page - 1);
    } else if (act === 'goto') {
      const n = parseInt(el.dataset.page, 10);
      if (n !== page) go(n);
    } else if (act === 'to-panel') {
      const p = root.querySelector('.order-panel');
      if (p) p.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } else if (act === 'qty') {
      const i = parseInt(el.dataset.i, 10), d = parseInt(el.dataset.d, 10);
      if (d > 0 && sisaRacikan(cart[i]) < 1) {
        notice = 'Stok ' + namaKurang(cart[i]) + ' habis, jumlah tidak bisa ditambah.';
        render(); return;
      }
      cart[i].qty = Math.min(50, cart[i].qty + d);
      if (cart[i].qty <= 0) cart.splice(i, 1);
      save(); render();
    } else if (act === 'remove') {
      cart.splice(parseInt(el.dataset.i, 10), 1);
      save(); render();
    } else if (act === 'pay') {
      openPay();
    }
  });

  document.getElementById('pos-search').addEventListener('input', function () {
    searchTerm = this.value; applySearch();
  });

  // ---------- pembayaran ----------
  const modalEl = document.getElementById('modalBayar');
  const payModal = bootstrap.Modal.getOrCreateInstance(modalEl);
  const elTotal = document.getElementById('payTotal');
  const elNama = document.getElementById('payNama');
  const elUang = document.getElementById('payUang');
  const elKembali = document.getElementById('payKembali');
  const elCash = document.getElementById('cashBox');
  const elErr = document.getElementById('payError');
  const elQuick = document.getElementById('quickCash');
  const elKonfirmBox = document.getElementById('konfirmBox');
  const elKonfirm = document.getElementById('payKonfirm');
  const elKonfirmLbl = document.getElementById('payKonfirmLbl');
  const btnConfirm = document.getElementById('payConfirm');
  let metode = 'cash';
  let busy = false;

  function setMetode(m) {
    metode = m;
    document.querySelectorAll('#metodeGrid .metode-opt').forEach(o => o.classList.toggle('selected', o.dataset.metode === m));
    elCash.style.display = m === 'cash' ? '' : 'none';
    elKonfirmBox.classList.toggle('d-none', m === 'cash');
    elKonfirm.checked = false;
    elKonfirmLbl.textContent = m === 'qris' ? 'Pembayaran QRIS sudah masuk / terkonfirmasi' : 'Pembayaran Paylater sudah disetujui / diterima';
  }

  function hitungKembali() {
    const uang = parseInt(elUang.value || '0', 10);
    const sisa = uang - total();
    elKembali.textContent = uang > 0 ? (sisa >= 0 ? rp(sisa) : 'Kurang ' + rp(-sisa)) : 'Rp 0';
    elKembali.style.color = uang > 0 && sisa < 0 ? 'var(--sys-red, #EF4444)' : '';
  }

  function openPay() {
    if (!cart.length) return;
    elTotal.textContent = rp(total());
    elErr.classList.add('d-none');
    elUang.value = '';
    setMetode('cash');

    const t = total();
    const opts = [{ l: 'Uang pas', v: t }, { l: '20.000', v: 20000 }, { l: '50.000', v: 50000 }, { l: '100.000', v: 100000 }]
      .filter((o, idx) => idx === 0 || o.v >= t);
    elQuick.innerHTML = opts.map(o => `<button type="button" data-v="${o.v}">${o.l}</button>`).join('');
    hitungKembali();
    payModal.show();
  }

  document.getElementById('metodeGrid').addEventListener('click', e => {
    const o = e.target.closest('.metode-opt'); if (o) setMetode(o.dataset.metode);
  });
  elQuick.addEventListener('click', e => {
    const b = e.target.closest('button[data-v]'); if (b) { elUang.value = b.dataset.v; hitungKembali(); }
  });
  elUang.addEventListener('input', hitungKembali);

  function showErr(msg) { elErr.textContent = msg; elErr.classList.remove('d-none'); }

  btnConfirm.addEventListener('click', async function () {
    elErr.classList.add('d-none');

    if (metode === 'cash' && elUang.value !== '' && parseInt(elUang.value, 10) < total()) {
      showErr('Uang diterima kurang dari total pembayaran.'); return;
    }
    if (metode !== 'cash' && !elKonfirm.checked) {
      showErr('Centang konfirmasi dulu setelah pembayaran ' + metode.toUpperCase() + ' benar-benar diterima.'); return;
    }
    if (busy) return;
    busy = true;

    btnConfirm.disabled = true;
    const oldHtml = btnConfirm.innerHTML;
    btnConfirm.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Memproses...';

    try {
      const res = await fetch(PAY_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({
          token: getToken(),
          uang_diterima: metode === 'cash' && elUang.value !== '' ? parseInt(elUang.value, 10) : null,
          konfirmasi_bayar: metode === 'cash' ? true : elKonfirm.checked,
          nama_pembeli: elNama.value.trim(),
          metode_bayar: metode,
          items: cart.map(i => ({
            ukuran_id: i.ukuran_id,
            filling_id: i.filling_id,
            cara_luar: i.cara_luar,
            saus_celup_id: i.cara_luar === 'saus_celup' ? i.rasa_id : null,
            bumbu_tabur_id: i.cara_luar === 'bumbu_tabur' ? i.rasa_id : null,
            qty: i.qty,
          })),
        }),
      });

      let data = null;
      try { data = await res.json(); } catch (e) {}

      if (res.ok && data && data.redirect) {
        try { localStorage.removeItem(KEY); localStorage.removeItem(TOKEN_KEY); } catch (e) {}
        window.location.href = data.redirect; // biarkan tombol tetap nonaktif sampai pindah halaman
        return;
      }

      if (res.status === 422 && data && data.errors) {
        showErr(Object.values(data.errors)[0][0]);
      } else if (res.status === 419) {
        showErr('Sesi habis. Muat ulang halaman (pesanan kamu tetap tersimpan).');
      } else {
        showErr((data && data.message) || 'Gagal menyimpan pembayaran. Coba lagi.');
      }
    } catch (err) {
      showErr('Tidak bisa terhubung ke server. Cek koneksi lalu coba lagi.');
    }

    busy = false;
    btnConfirm.disabled = false;
    btnConfirm.innerHTML = oldHtml;
  });

  render();
})();
