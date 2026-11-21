// Data Master Kasir: halaman index (pencarian) dan create/edit (pratinjau kartu).
document.addEventListener('DOMContentLoaded', function () {
  initPencarianTabel();
  initPratinjauForm();

  function initPencarianTabel() {
    const searchInput = document.getElementById('search-kasir');
    const rows = document.querySelectorAll('#tabel-kasir tbody tr');
    if (!searchInput) return;

    searchInput.addEventListener('input', function () {
      const keyword = this.value.trim().toLowerCase();
      rows.forEach(function (row) {
        const nama = row.querySelector('td:first-child');
        if (!nama || row.classList.contains('mt-empty-row')) return;
        row.style.display = nama.textContent.toLowerCase().includes(keyword) ? '' : 'none';
      });
    });
  }

  function initPratinjauForm() {
    const input = document.getElementById('foto-input');
    if (!input) return;

    const img = document.getElementById('foto-preview-img');
    const icon = document.getElementById('foto-placeholder-icon');
    const labelText = document.getElementById('foto-label-text');

    const previewImg = document.getElementById('preview-photo-img');
    const previewImgIcon = document.querySelector('#preview-photo i');
    const previewNama = document.getElementById('preview-nama');
    const previewEmail = document.getElementById('preview-email');
    const previewStatus = document.getElementById('preview-status');

    const namaInput = document.getElementById('input-nama');
    const emailInput = document.getElementById('input-email');
    const statusInput = document.getElementById('input-status');

    input.addEventListener('change', function () {
      const file = this.files[0];
      if (!file) return;

      labelText.textContent = file.name;
      const reader = new FileReader();
      reader.onload = function (e) {
        img.src = e.target.result;
        img.style.display = 'block';
        icon.style.display = 'none';

        previewImg.src = e.target.result;
        previewImg.style.display = 'block';
        previewImgIcon.style.display = 'none';
      };
      reader.readAsDataURL(file);
    });

    namaInput.addEventListener('input', function () {
      previewNama.textContent = this.value.trim() || 'Nama Kasir';
    });

    emailInput.addEventListener('input', function () {
      previewEmail.textContent = this.value.trim() || 'email@gmail.com';
    });

    statusInput.addEventListener('change', function () {
      const aktif = this.value === 'aktif';
      previewStatus.textContent = aktif ? 'Aktif' : 'Nonaktif';
      previewStatus.className = 'badge-table ' + (aktif ? 'success' : 'failed');
    });
  }
});
