/*
========================================================================
   LAYOUT JAVASCRIPT - CHICKEN CRUNCHY ROLL
   Dimuat oleh layouts/admin.blade.php, jadi jalan di SEMUA halaman.
   Isinya: sidebar (mobile & desktop), tombol fullscreen, dan
   status "sudah dibaca" pada notifikasi.
========================================================================
*/

document.addEventListener('DOMContentLoaded', function () {

    // -----------------------------------------------------------------
    // 1. Mobile Sidebar Toggle & Backdrop Overlay
    // -----------------------------------------------------------------
    const sidebar = document.querySelector('.sidebar-wrapper');
    const toggleBtn = document.querySelector('.sidebar-toggle-btn');

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

    // -----------------------------------------------------------------
    // 2. Desktop Sidebar Minimize
    // -----------------------------------------------------------------
    const desktopToggleBtn = document.querySelector('#desktop-sidebar-toggle');
    if (desktopToggleBtn) {
        desktopToggleBtn.addEventListener('click', function () {
            document.body.classList.toggle('sidebar-minimized');

            const icon = desktopToggleBtn.querySelector('i');
            if (icon) {
                icon.className = document.body.classList.contains('sidebar-minimized')
                    ? 'bi bi-chevron-bar-right'
                    : 'bi bi-chevron-bar-left';
            }

            // Kasih tahu chart/elemen lain supaya menyesuaikan lebar
            setTimeout(function () {
                window.dispatchEvent(new Event('resize'));
            }, 300);
        });
    }

    // -----------------------------------------------------------------
    // 3. Fullscreen Toggle
    // -----------------------------------------------------------------
    const fullscreenBtn = document.querySelector('#btn-fullscreen');
    if (fullscreenBtn) {
        const updateFullscreenIcon = function (isFullscreen) {
            const icon = fullscreenBtn.querySelector('i');
            if (icon) {
                icon.className = isFullscreen ? 'bi bi-fullscreen-exit' : 'bi bi-arrows-fullscreen';
            }
        };

        fullscreenBtn.addEventListener('click', function () {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(function (err) {
                    console.error('Gagal masuk fullscreen: ' + err.message);
                });
            } else {
                document.exitFullscreen().catch(function (err) {
                    console.error('Gagal keluar fullscreen: ' + err.message);
                });
            }
        });

        // Ikon ikut berubah, termasuk kalau user menekan Esc
        document.addEventListener('fullscreenchange', function () {
            updateFullscreenIcon(!!document.fullscreenElement);
        });
    }

    // -----------------------------------------------------------------
    // 4. Notifikasi: tandai sudah dibaca
    //    Isi notifikasi datang dari database (NotifikasiComposer).
    //    Status "sudah dibaca" disimpan di browser (localStorage) per akun,
    //    jadi titik merah hilang setelah dibaca, dan muncul lagi kalau ada
    //    notifikasi baru (misalnya stok yang tadinya menipis jadi habis).
    // -----------------------------------------------------------------
    const notifList = document.querySelector('.notification-list');
    const notifBadge = document.querySelector('#btn-notifications .navbar-action-badge');
    const clearAllBtn = document.querySelector('.btn-clear-all');
    const notifItems = document.querySelectorAll('.notification-item[data-key]');

    if (notifList) {
        const storageKey = 'ccr_notif_dibaca_' + (notifList.dataset.user || '0');

        const ambilDibaca = function () {
            try {
                return JSON.parse(localStorage.getItem(storageKey)) || [];
            } catch (e) {
                return [];
            }
        };

        const simpanDibaca = function (daftar) {
            try {
                localStorage.setItem(storageKey, JSON.stringify(daftar.slice(-200)));
            } catch (e) { /* abaikan kalau penyimpanan browser dimatikan */ }
        };

        const segarkan = function () {
            const dibaca = ambilDibaca();
            let belumDibaca = 0;

            notifItems.forEach(function (item) {
                const sudah = dibaca.indexOf(item.dataset.key) !== -1;
                const dot = item.querySelector('.notification-unread-dot');
                if (dot) dot.style.display = sudah ? 'none' : '';
                if (!sudah) belumDibaca++;
            });

            if (notifBadge) notifBadge.style.display = belumDibaca > 0 ? '' : 'none';
        };

        // Klik satu notifikasi = tandai yang itu sudah dibaca (lalu pindah halaman seperti biasa)
        notifItems.forEach(function (item) {
            item.addEventListener('click', function () {
                const dibaca = ambilDibaca();
                if (dibaca.indexOf(item.dataset.key) === -1) {
                    dibaca.push(item.dataset.key);
                    simpanDibaca(dibaca);
                }
            });
        });

        // Tombol "Tandai semua dibaca"
        if (clearAllBtn) {
            clearAllBtn.addEventListener('click', function () {
                const dibaca = ambilDibaca();
                notifItems.forEach(function (item) {
                    if (dibaca.indexOf(item.dataset.key) === -1) dibaca.push(item.dataset.key);
                });
                simpanDibaca(dibaca);
                segarkan();
            });
        }

        segarkan();
    }
});
