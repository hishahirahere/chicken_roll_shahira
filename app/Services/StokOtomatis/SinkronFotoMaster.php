<?php

namespace App\Services\StokOtomatis;

use Illuminate\Support\Facades\DB;

/**
 * Foto Filling, Saus Celup, dan Bumbu Tabur disimpan di tabel masternya (kolom foto),
 * sedangkan Manajemen Stok membaca dari tabel bahan_baku. Class ini menyalin PATH foto
 * dari master ke bahan_baku yang terhubung (bahan_baku_id), jadi foto tidak perlu diisi dua kali.
 *
 * Yang disalin hanya path-nya, file gambarnya tetap satu. Master yang tidak punya foto
 * tidak menimpa foto manual yang sudah ada di bahan baku.
 */
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
