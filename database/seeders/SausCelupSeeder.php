<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SausCelupSeeder extends Seeder
{
    /** Rasa saus celup menu asli. */
    public const DAFTAR = ['BBQ Spicy', 'Hot Lava', 'Keju', 'Lada Hitam', 'Sadis', 'Teriyaki'];

    public function run(): void
    {
        foreach (self::DAFTAR as $nama) {
            if (DB::table('saus_celup')->where('nama_saus', $nama)->exists()) {
                continue;
            }

            DB::table('saus_celup')->insert([
                'nama_saus'  => $nama,
                'status'     => 'aktif',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
