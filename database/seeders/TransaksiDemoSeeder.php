<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * OPSIONAL - data contoh riwayat penjualan 14 hari ke belakang supaya Dashboard
 * (menu favorit, grafik) dan Laporan Penjualan tidak kosong saat demo.
 * Tidak ikut dijalankan oleh DatabaseSeeder:
 *
 *   php artisan db:seed --class=TransaksiDemoSeeder
 *
 * Catatan: ini hanya riwayat penjualan masa lalu, stok kasir TIDAK dipotong
 * (stok kasir hanya berkurang oleh transaksi asli lewat halaman kasir).
 * Dilewati kalau sudah ada transaksi, jadi tidak menimpa data asli.
 */
class TransaksiDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('transaksi')->exists()) {
            $this->command?->warn('Sudah ada transaksi, TransaksiDemoSeeder dilewati.');

            return;
        }

        $kasir   = User::where('role', 'kasir')->first();
        $ukuran  = DB::table('ukuran')->get();
        $filling = DB::table('filling')->pluck('id')->all();
        $saus    = DB::table('saus_celup')->pluck('id')->all();
        $bumbu   = DB::table('bumbu_tabur')->pluck('id')->all();

        if (! $kasir || $ukuran->isEmpty() || ! $filling || (! $saus && ! $bumbu)) {
            $this->command?->warn('Data kasir / master belum lengkap, jalankan DatabaseSeeder dulu.');

            return;
        }

        $pembeli = ['Umum', 'Ibu Rina', 'Pak Dedi', 'Teh Nisa', 'Kang Asep', 'Siti', 'Rafi', 'Bu Wati', 'Dimas', 'Putri'];
        $pick    = fn (array $a) => $a[array_rand($a)];

        for ($hari = 13; $hari >= 0; $hari--) {
            $jumlahTransaksi = random_int(5, 9);

            for ($t = 0; $t < $jumlahTransaksi; $t++) {
                $waktu = now()->subDays($hari)->setTime(random_int(10, 20), random_int(0, 59), random_int(0, 59));

                $baris = [];
                for ($i = random_int(1, 4); $i > 0; $i--) {
                    $u = $ukuran->random();
                    // Pilih cara luar yang datanya tersedia
                    $cara = ($saus && $bumbu) ? (random_int(0, 1) ? 'saus_celup' : 'bumbu_tabur') : ($saus ? 'saus_celup' : 'bumbu_tabur');

                    $baris[] = [
                        'ukuran_id'      => $u->id,
                        'filling_id'     => $pick($filling),
                        'cara_luar'      => $cara,
                        'saus_celup_id'  => $cara === 'saus_celup' ? $pick($saus) : null,
                        'bumbu_tabur_id' => $cara === 'bumbu_tabur' ? $pick($bumbu) : null,
                        'harga_item'     => $u->harga,
                        'created_at'     => $waktu,
                    ];
                }

                $total  = array_sum(array_column($baris, 'harga_item'));
                $metode = random_int(0, 2) === 0 ? 'qris' : 'cash';

                DB::transaction(function () use ($kasir, $pick, $pembeli, $waktu, $total, $metode, $baris) {
                    $id = DB::table('transaksi')->insertGetId([
                        'user_id'       => $kasir->id,
                        'nama_pembeli'  => $pick($pembeli),
                        'tanggal'       => $waktu,
                        'total_harga'   => $total,
                        'metode_bayar'  => $metode,
                        'uang_diterima' => $metode === 'cash' ? (int) (ceil($total / 5000) * 5000) : null,
                        'created_at'    => $waktu,
                    ]);

                    DB::table('item_pesanan')->insert(array_map(fn ($b) => $b + ['transaksi_id' => $id], $baris));
                });
            }
        }
    }
}
