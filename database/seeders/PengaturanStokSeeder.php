<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\StokOtomatis\StokOtomatisService;
use Illuminate\Database\Seeder;

/**
 * Pengaturan Stok Otomatis: standar pemakaian umum (pengaturan_stok) dan
 * standar produksi per ukuran (standar_produksi: pcs per kg ayam + tepung).
 * Juga menyambungkan bahan/menu yang belum terhubung (kalau ada).
 *
 * Jalankan SETELAH UkuranSeeder dan BahanBakuSeeder.
 * Aman dijalankan berulang (angka yang sudah diubah admin tidak ditimpa):
 *   php artisan db:seed --class=PengaturanStokSeeder
 */
class PengaturanStokSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = User::where('role', 'admin')->value('id');

        app(StokOtomatisService::class)->isiDataAwal($adminId);
    }
}
