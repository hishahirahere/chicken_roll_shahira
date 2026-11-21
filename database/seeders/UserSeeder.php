<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Akun bawaan. Aman dijalankan berulang (pakai email sebagai kunci),
     * jadi tidak error "email sudah dipakai" dan password/profil yang sudah
     * diubah lewat aplikasi tidak ditimpa.
     */
    public function run(): void
    {
        $akun = [
            [
                'email' => 'admin@gmail.com',
                'isi'   => [
                    'name'     => 'Admin',
                    'password' => Hash::make('admin123'),
                    'role'     => 'admin',
                    'status'   => 'aktif',
                ],
            ],
            [
                'email' => 'kasir@gmail.com',
                'isi'   => [
                    'name'          => 'Kasir',
                    'password'      => Hash::make('kasir123'),
                    'role'          => 'kasir',
                    'status'        => 'aktif',
                    // Data profil supaya halaman Data Kasir (kartu identitas) tidak kosong
                    'jenis_kelamin' => 'P',
                    'tempat_lahir'  => 'Bandung Barat',
                    'tanggal_lahir' => '2001-05-17',
                    'alamat'        => 'Batujajar, Kabupaten Bandung Barat',
                    'no_hp'         => '081234567890',
                ],
            ],
        ];

        foreach ($akun as $a) {
            // firstOrCreate: kalau akun sudah ada, tidak diubah sama sekali
            User::firstOrCreate(['email' => $a['email']], $a['isi']);
        }
    }
}
