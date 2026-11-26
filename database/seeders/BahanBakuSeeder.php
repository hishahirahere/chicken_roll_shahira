<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\StokOtomatis\DataAwalStokService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BahanBakuSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = User::where('role', 'admin')->value('id');

        // [nama, satuan, peran, stok awal, stok minimum]
        $utama = [
            ['Ayam',          'kg',     'ayam',          50,  10],
            ['Tepung Premix Basah', 'botol',   'tepung_premix_basah', 40, 10],   // 1 botol = 1 liter
            ['Tepung Roti',         'bungkus', 'tepung_roti',         20, 5],    // 1 bungkus = 1 kg
            ['Minyak Goreng',       'jerigen', 'minyak',              10, 3],   // 1 jerigen = 5 liter
            ['Gas LPG 5 kg',  'tabung', 'gas',            5,   1],
        ];
        foreach ($utama as [$nama, $satuan, $peran, $stok, $min]) {
            $this->buatBahan($nama, $satuan, $peran, $stok, $min, $adminId);
        }

        $master = [
            // tabel master => [kolom nama, peran, awalan nama bahan]
            'filling'     => ['nama_filling', 'filling', 'Filling '],
            'saus_celup'  => ['nama_saus',    'saus',    'Saus '],
            'bumbu_tabur' => ['nama_bumbu',   'bumbu',   'Bumbu Tabur '],
        ];
        foreach ($master as $tabel => [$kolom, $peran, $awalan]) {
            foreach (DB::table($tabel)->orderBy('id')->get() as $item) {
                $bahanId = $this->buatBahan($awalan . $item->{$kolom}, 'bungkus', $peran, 10, 3, $adminId);

                // Hubungkan menu -> bahan baku (kalau sudah terhubung, dibiarkan)
                DB::table($tabel)->where('id', $item->id)->whereNull('bahan_baku_id')
                    ->update(['bahan_baku_id' => $bahanId]);
            }
        }

        // Kemasan per ukuran: [stok awal, stok minimum] contoh, ganti sesuai stok asli
        $stokKemasan = ['slim' => [500, 50], 'reguler' => [500, 50], 'jumbo' => [500, 50]];
        foreach (DB::table('ukuran')->orderBy('harga')->get() as $u) {
            [$stok, $min] = $stokKemasan[strtolower($u->nama_ukuran)] ?? [200, 50];
            $bahanId = $this->buatBahan('Kemasan ' . $u->nama_ukuran, 'pcs', 'kemasan', $stok, $min, $adminId);

            DB::table('ukuran')->where('id', $u->id)->whereNull('bahan_baku_id')
                ->update(['bahan_baku_id' => $bahanId]);
        }

        app(DataAwalStokService::class)->segarkanSemuaStatus();
    }

    private function buatBahan(string $nama, string $satuan, string $peran, int $stok, int $min, ?int $adminId): int
    {
        $ada = DB::table('bahan_baku')->whereRaw('LOWER(nama_bahan) = ?', [mb_strtolower($nama)])->first();

        if ($ada) {
            // Lengkapi peran yang masih kosong, stok & pengaturan lain tidak disentuh
            if ($ada->peran === null) {
                DB::table('bahan_baku')->where('id', $ada->id)->update(['peran' => $peran, 'updated_at' => now()]);
            }

            return (int) $ada->id;
        }

        $id = DB::table('bahan_baku')->insertGetId([
            'nama_bahan'    => $nama,
            'satuan'        => $satuan,
            'peran'         => $peran,
            'stok_saat_ini' => $stok,
            'stok_minimum'  => $min,
            'status_stok'   => 'aman',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        if ($adminId) {
            DB::table('riwayat_stok')->insert([
                'bahan_baku_id' => $id,
                'jenis'         => 'masuk',
                'sumber'        => 'manual',
                'jumlah'        => $stok,
                'tanggal'       => now()->toDateString(),
                'user_id'       => $adminId,
                'stok_sesudah'  => $stok,
                'keterangan'    => 'Stok awal',
                'created_at'    => now(),
            ]);
        }

        return (int) $id;
    }
}
