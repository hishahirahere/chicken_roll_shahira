<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Penyesuaian bahan baku utama:
 *  - Tepung Premix Basah : satuan BOTOL, 1 botol = 1 liter, kebutuhan 1 paket (60 pcs) = 2 botol (2 liter)
 *  - Tepung Roti         : satuan BUNGKUS, 1 bungkus = 1 kg, kebutuhan 1 paket (60 pcs) = 1 bungkus (1 kg)
 *    (menggantikan Tepung Terigu & Tepung Premix lama)
 *  - Minyak (jerigen, 1 jerigen = 5 liter) & gas (tabung) = JATAH tetap sekali kirim untuk beberapa hari,
 *    dikirim lagi saat stok kasir habis. Jatah dinaikkan dari 1 (hanya kalau masih 1 bawaan lama).
 *
 * Angka stok tepung TIDAK dikonversi (satuan lama dan baru tidak setara): sesuaikan stok sebenarnya
 * lewat menu Manajemen Stok setelah migrate.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['paket_premix_basah_botol' => '2', 'paket_tepung_roti_bungkus' => '1'] as $kunci => $nilai) {
            if (! DB::table('pengaturan_stok')->where('kunci', $kunci)->exists()) {
                DB::table('pengaturan_stok')->insert(['kunci' => $kunci, 'nilai' => $nilai, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        DB::table('pengaturan_stok')->whereIn('kunci', ['paket_terigu_bungkus', 'paket_premix_bungkus'])->delete();

        // Jatah minyak & gas: dari bawaan lama (1) menjadi lebih dari 1. Angka yang sudah diubah manual tidak ditimpa.
        foreach (['paket_minyak_jerigen' => '3', 'paket_gas_tabung' => '2'] as $kunci => $nilai) {
            $row = DB::table('pengaturan_stok')->where('kunci', $kunci)->first();
            if (! $row) {
                DB::table('pengaturan_stok')->insert(['kunci' => $kunci, 'nilai' => $nilai, 'created_at' => now(), 'updated_at' => now()]);
            } elseif ((string) $row->nilai === '1') {
                DB::table('pengaturan_stok')->where('kunci', $kunci)->update(['nilai' => $nilai, 'updated_at' => now()]);
            }
        }

        $this->ubahBahan('tepung_crispy', 'tepung_premix_basah', 'Tepung Premix Basah', 'botol');
        $this->ubahBahan('tepung_terigu', 'tepung_roti', 'Tepung Roti', 'bungkus');
    }

    public function down(): void
    {
        $this->ubahBahan('tepung_premix_basah', 'tepung_crispy', 'Tepung Premix', 'bungkus');
        $this->ubahBahan('tepung_roti', 'tepung_terigu', 'Tepung Terigu', 'bungkus');

        DB::table('pengaturan_stok')->whereIn('kunci', ['paket_premix_basah_botol', 'paket_tepung_roti_bungkus'])->delete();
        foreach (['paket_terigu_bungkus' => '2', 'paket_premix_bungkus' => '1'] as $kunci => $nilai) {
            if (! DB::table('pengaturan_stok')->where('kunci', $kunci)->exists()) {
                DB::table('pengaturan_stok')->insert(['kunci' => $kunci, 'nilai' => $nilai, 'created_at' => now(), 'updated_at' => now()]);
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
