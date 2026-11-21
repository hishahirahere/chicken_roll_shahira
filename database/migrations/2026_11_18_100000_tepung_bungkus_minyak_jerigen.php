<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menyesuaikan satuan dengan barang aslinya:
 *  - Tepung terigu & tepung kriuk (sekarang "Tepung Premix"): kg -> BUNGKUS (1 bungkus = 500 g, jadi 1 kg = 2 bungkus)
 *  - Minyak goreng: liter -> JERIGEN (1 jerigen = 5 liter)
 *  - Tepung tidak lagi dihitung dari kg ayam: kolom terigu_per_kg & crispy_per_kg di standar_produksi dihapus.
 *    Tepung jadi isi paket tetap (default 2 bungkus terigu + 1 bungkus premix, bisa diubah di Pengaturan Stok).
 *
 * Aman dijalankan pada data lama: hanya bahan yang satuannya masih kg / liter yang dikonversi,
 * jadi kalau sudah pernah dikonversi tidak dikalikan dua kali.
 */
return new class extends Migration
{
    private const BUNGKUS_PER_KG = 2;      // 1 bungkus = 500 g
    private const LITER_PER_JERIGEN = 5;

    public function up(): void
    {
        // --- pengaturan baru ---
        foreach ([
            'paket_terigu_bungkus' => '2',
            'paket_premix_bungkus' => '1',
            'paket_minyak_jerigen' => '1',
            'liter_per_jerigen'    => (string) self::LITER_PER_JERIGEN,
        ] as $kunci => $nilai) {
            if (! DB::table('pengaturan_stok')->where('kunci', $kunci)->exists()) {
                DB::table('pengaturan_stok')->insert([
                    'kunci' => $kunci, 'nilai' => $nilai, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        // --- tepung: kg -> bungkus ---
        $tepung = DB::table('bahan_baku')->whereIn('peran', ['tepung_terigu', 'tepung_crispy'])
            ->whereRaw('LOWER(satuan) = ?', ['kg'])->get();
        foreach ($tepung as $b) {
            $this->konversi($b, self::BUNGKUS_PER_KG, 'bungkus', false);

            // Ganti nama "Tepung Kriuk" -> "Tepung Premix" (kalau namanya belum dipakai bahan lain)
            if ($b->peran === 'tepung_crispy' && mb_strtolower($b->nama_bahan) === 'tepung kriuk'
                && ! DB::table('bahan_baku')->whereRaw('LOWER(nama_bahan) = ?', ['tepung premix'])->exists()) {
                DB::table('bahan_baku')->where('id', $b->id)->update(['nama_bahan' => 'Tepung Premix']);
            }
        }

        // --- minyak: liter -> jerigen ---
        $faktor = (float) (DB::table('pengaturan_stok')->where('kunci', 'liter_per_jerigen')->value('nilai') ?: self::LITER_PER_JERIGEN);
        $faktor = $faktor > 0 ? $faktor : (float) self::LITER_PER_JERIGEN;

        $minyak = DB::table('bahan_baku')->where('peran', 'minyak')->whereRaw('LOWER(satuan) = ?', ['liter'])->get();
        foreach ($minyak as $b) {
            $this->konversi($b, 1 / $faktor, 'jerigen', true);

            // Catatan permintaan & pemakaian bertipe bilangan bulat: dibulatkan, minimal 1 jerigen
            foreach (['jumlah_diminta', 'jumlah_dikirim', 'jumlah_diterima'] as $kol) {
                foreach (DB::table('permintaan_stok')->where('bahan_baku_id', $b->id)->where($kol, '>', 0)->get(['id', $kol]) as $r) {
                    DB::table('permintaan_stok')->where('id', $r->id)->update([$kol => max(1, (int) round($r->{$kol} / $faktor))]);
                }
            }
            foreach (DB::table('pemakaian_kasir')->where('bahan_baku_id', $b->id)->where('jumlah', '>', 0)->get(['id', 'jumlah']) as $r) {
                DB::table('pemakaian_kasir')->where('id', $r->id)->update(['jumlah' => max(1, (int) round($r->jumlah / $faktor))]);
            }
        }

        // --- tepung tidak lagi per kg ayam ---
        if (Schema::hasTable('standar_produksi')) {
            foreach (['terigu_per_kg', 'crispy_per_kg'] as $kolom) {
                if (Schema::hasColumn('standar_produksi', $kolom)) {
                    Schema::table('standar_produksi', function (Blueprint $table) use ($kolom) {
                        $table->dropColumn($kolom);
                    });
                }
            }
        }
    }

    public function down(): void
    {
        // Tepung: bungkus -> kg
        $tepung = DB::table('bahan_baku')->whereIn('peran', ['tepung_terigu', 'tepung_crispy'])
            ->whereRaw('LOWER(satuan) = ?', ['bungkus'])->get();
        foreach ($tepung as $b) {
            $this->konversi($b, 1 / self::BUNGKUS_PER_KG, 'kg', false);
            if ($b->peran === 'tepung_crispy' && mb_strtolower($b->nama_bahan) === 'tepung premix'
                && ! DB::table('bahan_baku')->whereRaw('LOWER(nama_bahan) = ?', ['tepung kriuk'])->exists()) {
                DB::table('bahan_baku')->where('id', $b->id)->update(['nama_bahan' => 'Tepung Kriuk']);
            }
        }

        // Minyak: jerigen -> liter
        $faktor = (float) (DB::table('pengaturan_stok')->where('kunci', 'liter_per_jerigen')->value('nilai') ?: self::LITER_PER_JERIGEN);
        foreach (DB::table('bahan_baku')->where('peran', 'minyak')->whereRaw('LOWER(satuan) = ?', ['jerigen'])->get() as $b) {
            $this->konversi($b, $faktor, 'liter', false);
            foreach (['jumlah_diminta', 'jumlah_dikirim', 'jumlah_diterima'] as $kol) {
                DB::table('permintaan_stok')->where('bahan_baku_id', $b->id)->where($kol, '>', 0)->update([$kol => DB::raw($kol . ' * ' . $faktor)]);
            }
            DB::table('pemakaian_kasir')->where('bahan_baku_id', $b->id)->update(['jumlah' => DB::raw('jumlah * ' . $faktor)]);
        }

        // Kolom tepung per kg ayam dikembalikan dengan angka bawaan lama
        if (Schema::hasTable('standar_produksi')) {
            Schema::table('standar_produksi', function (Blueprint $table) {
                if (! Schema::hasColumn('standar_produksi', 'terigu_per_kg')) {
                    $table->decimal('terigu_per_kg', 8, 3)->default(0);
                }
                if (! Schema::hasColumn('standar_produksi', 'crispy_per_kg')) {
                    $table->decimal('crispy_per_kg', 8, 3)->default(0);
                }
            });
            $lama = ['slim' => [0.40, 0.60], 'reg' => [0.45, 0.65], 'jumbo' => [0.50, 0.75]];
            foreach (DB::table('ukuran')->get() as $u) {
                foreach ($lama as $kunci => [$terigu, $crispy]) {
                    if (str_contains(mb_strtolower($u->nama_ukuran), $kunci)) {
                        DB::table('standar_produksi')->where('ukuran_id', $u->id)
                            ->update(['terigu_per_kg' => $terigu, 'crispy_per_kg' => $crispy]);
                    }
                }
            }
        }

        DB::table('pengaturan_stok')->whereIn('kunci', [
            'paket_terigu_bungkus', 'paket_premix_bungkus', 'paket_minyak_jerigen', 'liter_per_jerigen',
        ])->delete();
    }

    /**
     * Kalikan semua angka stok milik satu bahan dengan $faktor dan ganti satuannya:
     * stok gudang, stok minimum, riwayat stok, stok di tangan kasir.
     *
     * @param bool $bulatkanMinimum true = stok minimum dibulatkan ke atas (form hanya menerima bilangan bulat)
     */
    private function konversi(object $b, float $faktor, string $satuanBaru, bool $bulatkanMinimum): void
    {
        $stok = round((float) $b->stok_saat_ini * $faktor, 2);
        $min  = (float) $b->stok_minimum * $faktor;
        $min  = $bulatkanMinimum ? (float) ceil(round($min, 4)) : round($min, 2);

        $ubah = [
            'satuan' => $satuanBaru, 'stok_saat_ini' => $stok, 'stok_minimum' => $min,
            'status_stok' => $stok <= 0 ? 'habis' : ($stok <= $min ? 'menipis' : 'aman'),
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('bahan_baku', 'minimum_kasir') && $b->minimum_kasir !== null) {
            $mk = (float) $b->minimum_kasir * $faktor;
            $ubah['minimum_kasir'] = $bulatkanMinimum ? (float) ceil(round($mk, 4)) : round($mk, 2);
        }
        DB::table('bahan_baku')->where('id', $b->id)->update($ubah);

        foreach (DB::table('riwayat_stok')->where('bahan_baku_id', $b->id)->get(['id', 'jumlah', 'stok_sesudah']) as $r) {
            DB::table('riwayat_stok')->where('id', $r->id)->update([
                'jumlah'       => round((float) $r->jumlah * $faktor, 2),
                'stok_sesudah' => $r->stok_sesudah === null ? null : round((float) $r->stok_sesudah * $faktor, 2),
            ]);
        }

        if (Schema::hasTable('stok_kasir')) {
            foreach (DB::table('stok_kasir')->where('bahan_baku_id', $b->id)->get() as $r) {
                $isi = ['stok' => round((float) $r->stok * $faktor, 4), 'updated_at' => now()];
                if (isset($r->acuan)) {
                    $isi['acuan'] = round((float) $r->acuan * $faktor, 4);
                }
                DB::table('stok_kasir')->where('id', $r->id)->update($isi);
            }
        }
    }
};
