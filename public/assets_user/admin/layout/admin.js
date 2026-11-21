// Asal: resources/views/layouts/admin.blade.php
document.addEventListener('DOMContentLoaded', function () {
    const el = document.getElementById('greeting-text');
    if (!el) return;

    const jam = new Date().getHours();
    let waktu = 'Malam';
    if (jam >= 4 && jam < 11) waktu = 'Pagi';
    else if (jam >= 11 && jam < 15) waktu = 'Siang';
    else if (jam >= 15 && jam < 19) waktu = 'Sore';

    const nama = el.textContent.replace('Selamat datang, ', '');
    el.textContent = `Selamat ${waktu}, ${nama}`;
});

// Asal: resources/views/layouts/admin.blade.php
function showToast(message, type = 'success', title = null) {
    // Hapus notifikasi lama kalau masih ada, biar gak numpuk
    const existing = document.querySelector('.notif-overlay');
    if (existing) existing.remove();

    const icons = {
        success: 'bi-check-lg',
        error: 'bi-x-lg',
        warning: 'bi-exclamation-lg'
    };
    const titles = {
        success: 'Berhasil!',
        error: 'Gagal',
        warning: 'Perhatian'
    };

    const overlay = document.createElement('div');
    overlay.className = 'notif-overlay';
    overlay.innerHTML = `
        <div class="notif-box notif-${type}">
            <div class="notif-icon-circle"><i class="bi ${icons[type] || icons.success}"></i></div>
            <div class="notif-title">${title || titles[type] || titles.success}</div>
            <div class="notif-message">${message}</div>
            <button type="button" class="notif-btn-ok">OK</button>
        </div>
    `;

    document.body.appendChild(overlay);
    requestAnimationFrame(() => overlay.classList.add('show'));

    const closeNotif = () => {
        overlay.classList.remove('show');
        overlay.classList.add('hide');
        overlay.addEventListener('transitionend', () => overlay.remove(), { once: true });
    };

    const timer = setTimeout(closeNotif, 2800);

    overlay.querySelector('.notif-btn-ok').addEventListener('click', () => {
        clearTimeout(timer);
        closeNotif();
    });

    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) {
            clearTimeout(timer);
            closeNotif();
        }
    });
}

// Auto-tampilkan notifikasi dari session flash Laravel (session('success'), session('error'), dll)
document.addEventListener('DOMContentLoaded', function () {
    const f = window.flashMessages || {};
    if (f.success) showToast(f.success, 'success');
    if (f.error) showToast(f.error, 'error');
    if (f.warning) showToast(f.warning, 'warning');
});
