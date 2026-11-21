(function () {
  function notif(pesan, galat) {
    var lama = document.getElementById('ccr-bilah');
    if (lama) lama.remove();
    var b = document.createElement('div');
    b.id = 'ccr-bilah';
    b.setAttribute('role', 'status');
    b.textContent = pesan;
    b.style.cssText = 'position:fixed;top:16px;left:50%;transform:translateX(-50%);z-index:99999;max-width:92vw;' +
      'background:#fff;color:#3b1f1a;border:1px solid #e6d9cc;border-left:4px solid ' + (galat ? '#c62828' : '#9c1d1d') + ';' +
      'padding:10px 18px;border-radius:6px;box-shadow:0 2px 10px rgba(0,0,0,.15);font:14px/1.4 Arial,sans-serif;text-align:center';
    document.body.appendChild(b);
    setTimeout(function () { b.remove(); }, 6000);
  }

  function sibuk(el, ya) {
    if (ya) {
      el.dataset.sibuk = '1';
      el.dataset.isi = el.innerHTML;
      el.style.pointerEvents = 'none'; el.style.opacity = '.75';
      el.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Menyiapkan…';
    } else {
      delete el.dataset.sibuk;
      if (el.dataset.isi !== undefined) el.innerHTML = el.dataset.isi;
      el.style.pointerEvents = ''; el.style.opacity = '';
    }
  }

  function namaFile(resp, cadangan) {
    var cd = resp.headers.get('Content-Disposition') || '';
    var m = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(cd);
    return m ? decodeURIComponent(m[1]) : cadangan;
  }

  function simpanBlob(blob, nama) {
    var u = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = u; a.download = nama;
    document.body.appendChild(a); a.click(); a.remove();
    setTimeout(function () { URL.revokeObjectURL(u); }, 4000);
  }

  function cetakHtml(html) {
    var f = document.createElement('iframe');
    f.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;';
    document.body.appendChild(f);
    var d = f.contentWindow.document;
    d.open(); d.write(html); d.close();

    var selesai = false;
    function beres() {
      if (selesai) return;
      selesai = true;
      setTimeout(function () { f.remove(); }, 500);
    }
    f.contentWindow.addEventListener('afterprint', beres);
    setTimeout(function () { if (!selesai) f.remove(); }, 300000);   // pengaman

    setTimeout(function () {
      f.contentWindow.focus();
      f.contentWindow.print();
    }, 400);
  }

  // Cetak struk (hanya struk-nya saja)
  function cetakStruk(htmlStruk, urlCss) {
    return fetch(urlCss, { credentials: 'same-origin' })
      .then(function (r) { return r.text(); })
      .then(function (css) {
        cetakHtml('<!doctype html><html><head><meta charset="utf-8"><title>Struk</title><style>' + css +
          '@page{margin:6mm}body{margin:0}' +
          '.struk{box-shadow:none;border-radius:0;padding:0;filter:none;max-width:100%}' +
          '.struk::before,.struk::after{display:none}.struk .total{background:none;padding:.1rem 0}' +
          '</style></head><body>' + htmlStruk + '</body></html>');
      })
      .catch(function () { notif('Dokumen tidak dapat disiapkan untuk dicetak. Silakan coba kembali.', true); });
  }

  function embedUrl(href) {
    var u = new URL(href, window.location.href);
    u.searchParams.set('embed', '1');    // halaman cetak tanpa tombol bawaan
    return u.toString();
  }

  document.addEventListener('click', function (e) {
    // ---- cetak struk dari halaman/pop-up struk ----
    var s = e.target.closest('[data-cetak-struk]');
    if (s) {
      e.preventDefault();
      if (s.dataset.sibuk) return;
      sibuk(s, true);
      fetch(s.dataset.cetakStruk, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } })
        .then(function (r) { if (!r.ok) throw new Error('Gagal (' + r.status + ')'); return r.text(); })
        .then(function (t) {
          var struk = new DOMParser().parseFromString(t, 'text/html').querySelector('.struk');
          if (!struk) throw new Error('Struk tidak ditemukan');
          sibuk(s, false);
          return cetakStruk(struk.outerHTML, s.dataset.css);
        })
        .catch(function (err) { sibuk(s, false); notif('Struk tidak dapat dicetak. ' + err.message, true); });
      return;
    }

    // ---- ekspor / cetak laporan ----
    var el = e.target.closest('[data-unduh]');
    if (!el) return;
    e.preventDefault();
    if (el.dataset.sibuk) return;
    sibuk(el, true);

    var label = el.dataset.nama || 'File';

    fetch(embedUrl(el.href), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) {
        if (!r.ok) throw new Error('Gagal (' + r.status + ')');
        var tipe = (r.headers.get('Content-Type') || '').toLowerCase();
        if (tipe.indexOf('text/html') !== -1) {            // halaman cetak (juga PDF tanpa paket dompdf)
          return r.text().then(function (html) { sibuk(el, false); cetakHtml(html); });
        }
        var nama = namaFile(r, label);
        return r.blob().then(function (b) {
          simpanBlob(b, nama);
          sibuk(el, false);
        });
      })
      .catch(function (err) {
        sibuk(el, false);
        notif(label + ' tidak dapat diunduh. ' + (err && err.message ? err.message : 'Silakan coba kembali.'), true);
      });
  });

  window.CCR = { cetakHtml: cetakHtml, cetakStruk: cetakStruk };
})();
