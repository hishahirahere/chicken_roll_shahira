// Asal: resources/views/layouts/kasir.blade.php
// Sidebar: buka/tutup di HP, minimize di desktop, fullscreen
document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.querySelector('.sidebar-wrapper');
    const toggleBtn = document.getElementById('sidebar-toggle');
    const overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    document.body.appendChild(overlay);

    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show', sidebar.classList.contains('show'));
        });
        overlay.addEventListener('click', function () {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
    }

    const desktopBtn = document.getElementById('desktop-sidebar-toggle');
    if (desktopBtn) {
        desktopBtn.addEventListener('click', function () {
            document.body.classList.toggle('sidebar-minimized');
            const icon = desktopBtn.querySelector('i');
            if (icon) icon.className = document.body.classList.contains('sidebar-minimized')
                ? 'bi bi-chevron-bar-right' : 'bi bi-chevron-bar-left';
            setTimeout(() => window.dispatchEvent(new Event('resize')), 300); // chart ikut menyesuaikan lebar
        });
    }

    const fsBtn = document.getElementById('btn-fullscreen');
    if (fsBtn) {
        fsBtn.addEventListener('click', function () {
            if (!document.fullscreenElement) document.documentElement.requestFullscreen().catch(() => {});
            else document.exitFullscreen();
        });
        document.addEventListener('fullscreenchange', function () {
            const icon = fsBtn.querySelector('i');
            if (icon) icon.className = document.fullscreenElement ? 'bi bi-fullscreen-exit' : 'bi bi-arrows-fullscreen';
        });
    }

    // Sapaan dinamis sesuai jam perangkat
    const g = document.getElementById('greeting-text');
    if (g) {
        const jam = new Date().getHours();
        let waktu = 'Malam';
        if (jam >= 4 && jam < 11) waktu = 'Pagi';
        else if (jam >= 11 && jam < 15) waktu = 'Siang';
        else if (jam >= 15 && jam < 19) waktu = 'Sore';
        g.textContent = 'Selamat ' + waktu + ', ' + g.textContent.replace('Selamat datang, ', '');
    }
});

// Notifikasi berhasil/gagal di tengah layar
function showToast(message, type = 'success', title = null) {
    const existing = document.querySelector('.notif-overlay');
    if (existing) existing.remove();

    const icons = { success: 'bi-check-lg', error: 'bi-x-lg', warning: 'bi-exclamation-lg' };
    const titles = { success: 'Berhasil!', error: 'Gagal', warning: 'Perhatian' };

    const overlay = document.createElement('div');
    overlay.className = 'notif-overlay';
    overlay.innerHTML = `
        <div class="notif-box notif-${type}">
            <div class="notif-icon-circle"><i class="bi ${icons[type] || icons.success}"></i></div>
            <div class="notif-title">${title || titles[type] || titles.success}</div>
            <div class="notif-message"></div>
            <button type="button" class="notif-btn-ok">OK</button>
        </div>`;
    overlay.querySelector('.notif-message').textContent = message;

    document.body.appendChild(overlay);
    requestAnimationFrame(() => overlay.classList.add('show'));

    const closeNotif = () => {
        overlay.classList.remove('show');
        overlay.classList.add('hide');
        overlay.addEventListener('transitionend', () => overlay.remove(), { once: true });
    };
    const timer = setTimeout(closeNotif, 2800);
    overlay.querySelector('.notif-btn-ok').addEventListener('click', () => { clearTimeout(timer); closeNotif(); });
    overlay.addEventListener('click', e => { if (e.target === overlay) { clearTimeout(timer); closeNotif(); } });
}

document.addEventListener('DOMContentLoaded', function () {
    const f = window.flashMessages || {};
    if (f.success) showToast(f.success, 'success');
    if (f.error) showToast(f.error, 'error');
    if (f.warning) showToast(f.warning, 'warning');
});
