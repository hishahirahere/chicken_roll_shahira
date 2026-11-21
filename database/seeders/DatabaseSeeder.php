<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{

    public function run(): void
    {
        $this->call([
            // 1. Akun admin & kasir
            UserSeeder::class,

            // 2. Data master menu
            UkuranSeeder::class,
            FillingSeeder::class,
            SausCelupSeeder::class,
            BumbuTaburSeeder::class,

            // 3. Stok gudang: bahan utama + satu bahan per filling/saus/bumbu (dihubungkan ke menu)
            BahanBakuSeeder::class,

            // 4. Standar pemakaian stok otomatis (pengaturan_stok + standar_produksi)
            PengaturanStokSeeder::class,

            // 5. Stok kasir sengaja TIDAK diisi seeder. Kasir mulai dari 0; admin yang memberi paket ayam,
            //    bahan pendukung, minyak & gas lewat Manajemen Stok (sama seperti alur nyata di gerai).
        ]);
    }
}
