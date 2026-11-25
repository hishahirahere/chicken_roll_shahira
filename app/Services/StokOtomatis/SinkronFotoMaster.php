<?php

namespace App\Services\StokOtomatis;

use Illuminate\Support\Facades\DB;

// Menyamakan foto dari Data Master ke Manajemen Stok, jadi tidak diisi dua kali.
class SinkronFotoMaster
{
    private const TABEL_MASTER = ['filling', 'saus_celup', 'bumbu_tabur'];

    /** Sinkronkan semua master sekaligus (aman dijalankan berulang). */
    public static function semua(): void
    {
        foreach (self::TABEL_MASTER as $tabel) {
            $baris = DB::table($tabel)
                ->join('bahan_baku', 'bahan_baku.id', '=', "{$tabel}.bahan_baku_id")
                ->whereNotNull("{$tabel}.foto")
                ->where(function ($q) use ($tabel) {
                    $q->whereNull('bahan_baku.foto')->orWhereColumn('bahan_baku.foto', '!=', "{$tabel}.foto");
                })
                ->select('bahan_baku.id as bahan_id', "{$tabel}.foto as foto")
                ->get();

            foreach ($baris as $b) {
                DB::table('bahan_baku')->where('id', $b->bahan_id)->update(['foto' => $b->foto]);
            }
        }
    }

    /** True kalau path foto ini masih dipakai oleh salah satu master (file jangan dihapus). */
    public static function dipakaiMaster(?string $path): bool
    {
        if (! $path) {
            return false;
        }

        foreach (self::TABEL_MASTER as $tabel) {
            if (DB::table($tabel)->where('foto', $path)->exists()) {
                return true;
            }
        }

        return false;
    }
}
