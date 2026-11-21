<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BumbuTaburSeeder extends Seeder
{
    /** Rasa bumbu tabur menu asli. */
    public const DAFTAR = ['Balado', 'BBQ', 'Bubuk Cabe', 'Garlic', 'Jagung Manis', 'Keju'];

    public function run(): void
    {
        foreach (self::DAFTAR as $nama) {
            if (DB::table('bumbu_tabur')->where('nama_bumbu', $nama)->exists()) {
                continue;
            }

            DB::table('bumbu_tabur')->insert([
                'nama_bumbu' => $nama,
                'status'     => 'aktif',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
