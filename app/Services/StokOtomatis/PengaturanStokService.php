<?php

namespace App\Services\StokOtomatis;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Pengaturan umum, standar produksi, dan aturan dasar bahan (bungkus, status stok).
 */
class PengaturanStokService
{
    /** Pengaturan umum (nilai awal + yang sudah diubah admin). */
    public function pengaturan(): array
    {
        $set = PerhitunganStok::PENGATURAN_AWAL;

        foreach (DB::table('pengaturan_stok')->pluck('nilai', 'kunci') as $k => $v) {
            if (array_key_exists($k, $set)) {
                $set[$k] = is_numeric($v) ? (int) $v : $v;
            }
        }

        return $set;
    }

    /** [ukuran_id => ['hasil_pcs_per_kg'=>]] */
    public function standar(): array
    {
        $out = [];
        foreach (DB::table('standar_produksi')->get() as $r) {
            $out[(int) $r->ukuran_id] = [
                'hasil_pcs_per_kg' => (int) $r->hasil_pcs_per_kg,
            ];
        }

        return $out;
    }

    /** Kapasitas kali pakai per bungkus (angka standar dari Pengaturan Stok). */
    public function kapasitasBungkus(object $bahan, array $set): int
    {
        return (int) match ($bahan->peran) {
            'filling' => $set['kapasitas_filling'],
            'saus'    => $set['kapasitas_saus'],
            'bumbu'   => $set['kapasitas_bumbu'],
            default   => 0,
        };
    }

    public function bahanBerbungkus(object $bahan): bool
    {
        return in_array($bahan->peran, ['filling', 'saus', 'bumbu'], true);
    }

    /** Status stok gudang: bandingkan stok sekarang dengan stok minimum. */
    public function statusBahan(object $bahan): string
    {
        return PerhitunganStok::statusStok((int) $bahan->stok_saat_ini, (int) $bahan->stok_minimum);
    }

    public function angka(int $n): string
    {
        return number_format($n, 0, ',', '.');
    }
}
