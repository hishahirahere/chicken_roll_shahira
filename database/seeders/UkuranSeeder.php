<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UkuranSeeder extends Seeder
{
    /** [nama_ukuran, harga]. Harga contoh, sesuaikan dengan harga asli. */
    public const DAFTAR = [
        ['Slim',    10000],
        ['Reguler', 18000],
        ['Jumbo',   27000],
    ];

    public function run(): void
    {
        foreach (self::DAFTAR as [$nama, $harga]) {
            // Aman dijalankan ulang: yang sudah ada dibiarkan (harga hasil edit admin tidak ditimpa)
            if (DB::table('ukuran')->where('nama_ukuran', $nama)->exists()) {
                continue;
            }

            DB::table('ukuran')->insert([
                'nama_ukuran' => $nama,
                'harga'       => $harga,
                'status'      => 'aktif',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }
}
