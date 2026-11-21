<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardKasirController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UkuranController;
use App\Http\Controllers\FillingController;
use App\Http\Controllers\SausCelupController;
use App\Http\Controllers\BumbuTaburController;
use App\Http\Controllers\KasirController;
use App\Http\Controllers\BahanBakuController;
use App\Http\Controllers\RiwayatStokController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\RiwayatTransaksiController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\StatusMasterController;
use App\Http\Controllers\StokKasirController;
use App\Http\Controllers\StokKasirAdminController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('auth.login');
});


Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified', 'role:admin'])
    ->name('dashboard');

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::resource('ukuran', UkuranController::class);
    Route::resource('filling', FillingController::class);
    Route::resource('saus_celup', SausCelupController::class);
    Route::resource('bumbu_tabur', BumbuTaburController::class);
    Route::resource('kasir', KasirController::class);

    // Aktif / nonaktif (tombol di kartu Data Master, termasuk kasir)
    Route::patch('master/{tipe}/{id}/status', [StatusMasterController::class, 'toggle'])
        ->whereIn('tipe', ['ukuran', 'filling', 'saus_celup', 'bumbu_tabur', 'kasir'])
        ->whereNumber('id')
        ->name('master.status');

    // Manajemen Stok (bahan baku)
    Route::resource('bahan_baku', BahanBakuController::class)->except('show');
    Route::post('stok/masuk', [BahanBakuController::class, 'stokMasuk'])->name('stok.masuk');
    Route::post('stok/keluar', [BahanBakuController::class, 'stokKeluar'])->name('stok.keluar');
    Route::post('stok/paket', [BahanBakuController::class, 'beriPaket'])->name('stok.paket');

    // Kirim minyak & gas ke kasir (form seperti Beri Paket; kasir konfirmasi diterima)
    Route::post('stok/kirim', [BahanBakuController::class, 'kirimMinyakGas'])->name('stok.kirim');
    Route::get('stok/riwayat', [RiwayatStokController::class, 'index'])->name('stok.riwayat');

    // Stok Kasir: pantau stok yang dipegang tiap kasir (halaman terpisah dari Manajemen Stok)
    Route::get('stok-kasir', [StokKasirAdminController::class, 'index'])->name('stok.kasir');


    // Riwayat transaksi seluruh kasir
    Route::get('riwayat-transaksi', [RiwayatTransaksiController::class, 'index'])->name('admin.riwayat-transaksi');
    Route::get('riwayat-transaksi/{transaksi}', [RiwayatTransaksiController::class, 'show'])->name('admin.riwayat-transaksi.show');

    // Laporan penjualan + analisis varian terlaris
    Route::get('laporan', [LaporanController::class, 'index'])->name('admin.laporan');
    Route::get('laporan/ekspor', [LaporanController::class, 'export'])->name('admin.laporan.export');
});


Route::middleware(['auth', 'role:kasir,admin'])->prefix('pos')->name('pos.')->group(function () {
    Route::get('/dashboard', [DashboardKasirController::class, 'index'])->name('dashboard_kasir'); // BARU
    Route::get('/', [PosController::class, 'index'])->name('index');
    Route::post('/bayar', [PosController::class, 'bayar'])->name('bayar');
    Route::get('/riwayat', [PosController::class, 'riwayat'])->name('riwayat');
    Route::get('/riwayat/cetak', [PosController::class, 'cetakRiwayat'])->name('riwayat.cetak');
    Route::get('/stok', [StokKasirController::class, 'index'])->name('stok');
    // Minyak & gas manual: catat pemakaian, minta ke admin, konfirmasi barang diterima
    Route::post('/stok/pemakaian', [StokKasirController::class, 'catatPemakaian'])->name('pemakaian.store');
    Route::post('/stok/permintaan', [StokKasirController::class, 'minta'])->name('permintaan.store');
    Route::post('/stok/permintaan/{id}/terima', [StokKasirController::class, 'terima'])->whereNumber('id')->name('permintaan.terima');
    Route::post('/stok/permintaan/{id}/batal', [StokKasirController::class, 'batal'])->whereNumber('id')->name('permintaan.batal');
    Route::get('/struk/{transaksi}', [PosController::class, 'struk'])->name('struk');
});


Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

require __DIR__.'/auth.php';
