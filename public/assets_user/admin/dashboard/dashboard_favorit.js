// Asal: resources/views/admin/dashboard.blade.php
document.querySelectorAll('#fav-tabs .fav-tab').forEach(function (tab) {
  tab.addEventListener('click', function () {
    document.querySelectorAll('#fav-tabs .fav-tab').forEach(t => t.classList.toggle('active', t === tab));
    document.querySelectorAll('[data-fav-panel]').forEach(p => p.classList.toggle('d-none', p.dataset.favPanel !== tab.dataset.fav));
  });
});

(function () {
  var modalEl = document.getElementById('modalFavorit');
  if (!modalEl) return;

  modalEl.addEventListener('show.bs.modal', function (event) {
    var btn = event.relatedTarget;
    if (!btn || !btn.dataset.fav) return;
    var d = JSON.parse(btn.dataset.fav);

    document.getElementById('fm-kategori').textContent = 'Favorit ' + d.kategori;
    document.getElementById('fm-nama').textContent = d.nama;
    document.getElementById('fm-sub').textContent = d.sub || '';
    document.getElementById('fm-rank').textContent = '#' + d.rank;
    document.getElementById('fm-total').textContent = d.total;
    document.getElementById('fm-share').textContent = d.share + '%';

    // foto
    var photo = document.getElementById('fm-photo');
    photo.innerHTML = '';
    function kosong() {
      var e = document.createElement('div');
      e.className = 'fav-empty';
      e.innerHTML = '<i class="bi bi-egg-fried"></i>';
      photo.innerHTML = '';
      photo.appendChild(e);
    }
    if (d.foto) {
      var img = document.createElement('img');
      img.src = d.foto;
      img.alt = d.nama;
      img.onerror = kosong;
      photo.appendChild(img);
    } else {
      kosong();
    }

    // rincian (ukuran, filling, cara luar, rasa, dst.)
    var list = document.getElementById('fm-detail');
    var wrap = document.getElementById('fm-detail-wrap');
    list.innerHTML = '';
    var keys = Object.keys(d.detail || {});
    wrap.style.display = keys.length ? '' : 'none';
    keys.forEach(function (k) {
      var row = document.createElement('div');
      row.className = 'fm-row';
      var l = document.createElement('span'); l.textContent = k;
      var v = document.createElement('b');    v.textContent = d.detail[k];
      row.appendChild(l); row.appendChild(v);
      list.appendChild(row);
    });
  });
})();
