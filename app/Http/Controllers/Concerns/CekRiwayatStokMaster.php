<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Dipakai Filling, Saus Celup, Bumbu Tabur (data master yang terhubung ke bahan baku).
 * Menu yang bahan bakunya sudah punya jejak stok tidak boleh dihapus, supaya riwayat stok
 * dan laporan stok (masuk, keluar, diberikan ke kasir) tetap utuh. Caranya: dinonaktifkan.
 */
trait CekRiwayatStokMaster
{
    /** True kalau bahan baku itu sudah punya stok atau riwayat stok (gudang maupun kasir). */
    protected function sudahPunyaJejakStok($bahanBakuId): bool
    {
        if (! $bahanBakuId) {
            return false;
        }

        return DB::table('bahan_baku')->where('id', $bahanBakuId)->where('stok_saat_ini', '>', 0)->exists()
            || DB::table('riwayat_stok')->where('bahan_baku_id', $bahanBakuId)->exists()
            || DB::table('stok_kasir')->where('bahan_baku_id', $bahanBakuId)->exists()
            || DB::table('riwayat_stok_kasir')->where('bahan_baku_id', $bahanBakuId)->exists();
    }

    /** Bahan baku kosong (tanpa stok & riwayat) ikut dibersihkan saat menunya dihapus, supaya tidak jadi yatim. */
    protected function bersihkanBahanBakuKosong($bahanBakuId): void
    {
        if ($bahanBakuId && ! $this->sudahPunyaJejakStok($bahanBakuId)) {
            DB::table('bahan_baku')->where('id', $bahanBakuId)->delete();
        }
    }
}
