<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // --- pengaturan baru, pengaturan lama dihapus ---
        foreach ([
            'paket_premix_basah_botol'  => '2',
            'paket_tepung_roti_bungkus' => '1',
            'paket_minyak_botol'        => '1',
            'liter_per_botol'           => '0',
        ] as $kunci => $nilai) {
            if (! DB::table('pengaturan_stok')->where('kunci', $kunci)->exists()) {
                DB::table('pengaturan_stok')->insert([
                    'kunci' => $kunci, 'nilai' => $nilai, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
        DB::table('pengaturan_stok')->whereIn('kunci', [
            'paket_terigu_bungkus', 'paket_premix_bungkus', 'paket_minyak_jerigen', 'liter_per_jerigen',
        ])->delete();

        // --- Tepung Premix (lama) -> Tepung Premix Basah (botol) ---
        $this->ubahBahan('tepung_crispy', 'tepung_premix_basah', 'Tepung Premix Basah', 'botol');
        // --- Tepung Terigu (lama) -> Tepung Roti (bungkus 1 kg) ---
        $this->ubahBahan('tepung_terigu', 'tepung_roti', 'Tepung Roti', 'bungkus');

        // --- Minyak: jerigen -> botol ---
        DB::table('bahan_baku')->where('peran', 'minyak')->whereRaw('LOWER(satuan) = ?', ['jerigen'])
            ->update(['satuan' => 'botol', 'updated_at' => now()]);
    }

    public function down(): void
    {
        $this->ubahBahan('tepung_premix_basah', 'tepung_crispy', 'Tepung Premix', 'bungkus');
        $this->ubahBahan('tepung_roti', 'tepung_terigu', 'Tepung Terigu', 'bungkus');

        DB::table('bahan_baku')->where('peran', 'minyak')->whereRaw('LOWER(satuan) = ?', ['botol'])
            ->update(['satuan' => 'jerigen', 'updated_at' => now()]);

        DB::table('pengaturan_stok')->whereIn('kunci', [
            'paket_premix_basah_botol', 'paket_tepung_roti_bungkus', 'paket_minyak_botol', 'liter_per_botol',
        ])->delete();

        foreach ([
            'paket_terigu_bungkus' => '2', 'paket_premix_bungkus' => '1',
            'paket_minyak_jerigen' => '1', 'liter_per_jerigen' => '5',
        ] as $kunci => $nilai) {
            if (! DB::table('pengaturan_stok')->where('kunci', $kunci)->exists()) {
                DB::table('pengaturan_stok')->insert([
                    'kunci' => $kunci, 'nilai' => $nilai, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    /** Ganti peran, nama, dan satuan satu bahan. Nama tidak diganti kalau sudah dipakai bahan lain. */
    private function ubahBahan(string $peranLama, string $peranBaru, string $namaBaru, string $satuanBaru): void
    {
        $b = DB::table('bahan_baku')->where('peran', $peranLama)->orderBy('id')->first();
        if (! $b) {
            return;
        }

        $ubah = ['peran' => $peranBaru, 'satuan' => $satuanBaru, 'updated_at' => now()];

        $dipakai = DB::table('bahan_baku')->where('id', '!=', $b->id)
            ->whereRaw('LOWER(nama_bahan) = ?', [mb_strtolower($namaBaru)])->exists();
        if (! $dipakai) {
            $ubah['nama_bahan'] = $namaBaru;
        }

        DB::table('bahan_baku')->where('id', $b->id)->update($ubah);
    }
};
