<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FillingSeeder extends Seeder
{
    public const DAFTAR = ['BBQ', 'BBQ Spicy', 'Garlic', 'Hot Lava', 'Keju', 'Mentai'];

    public function run(): void
    {
        foreach (self::DAFTAR as $nama) {
            if (DB::table('filling')->where('nama_filling', $nama)->exists()) {
                continue;
            }

            DB::table('filling')->insert([
                'nama_filling' => $nama,
                'status'       => 'aktif',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }
}
