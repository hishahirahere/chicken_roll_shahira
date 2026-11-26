<?php

namespace App\Services\StokOtomatis;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class DataAwalStokService
{
    public function __construct(
        private PengaturanStokService $pengaturan,
    ) {}

    // Peran bahan utama + nama bawaan untuk mencarinya.
    public const PERAN_UTAMA = [
        'ayam'          => ['Ayam', 'kg'],
        'tepung_premix_basah' => ['Tepung Premix Basah', 'botol'],   // 1 botol = 1 liter
        'tepung_roti'         => ['Tepung Roti', 'bungkus'],          // 1 bungkus = 1 kg
        'minyak'        => ['Minyak Goreng', 'jerigen'],   // 1 jerigen = 5 liter
        'gas'           => ['Gas LPG', 'tabung'],
    ];

    // Kata kunci nama bahan untuk mengenali tiap bahan utama (tanpa perlu pengaturan manual).
    private const KATA_KUNCI = [
        'ayam'          => ['ayam'],
        'tepung_premix_basah' => ['premix', 'basah'],
        'tepung_roti'         => ['roti', 'panir'],
        'minyak'        => ['minyak'],
        'gas'           => ['gas', 'lpg', 'elpiji'],
        'kemasan'       => ['kemasan', 'dus', 'box', 'kotak'],
    ];

    public function siapkan(?int $adminId = null): void
    {
        $perluStandar = DB::table('standar_produksi')->count() < DB::table('ukuran')->count();
        if ($perluStandar || $this->pemetaanKurang()) {
            $this->isiDataAwal($adminId);
        }
    }

    public function isiDataAwal(?int $adminId = null): void
    {
        foreach (PerhitunganStok::PENGATURAN_AWAL as $kunci => $nilai) {
            if (! DB::table('pengaturan_stok')->where('kunci', $kunci)->exists()) {
                DB::table('pengaturan_stok')->insert([
                    'kunci' => $kunci, 'nilai' => (string) $nilai, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        foreach (DB::table('ukuran')->get() as $u) {
            $nama = strtolower($u->nama_ukuran);
            $std = match (true) {
                str_contains($nama, 'slim') => PerhitunganStok::STANDAR_AWAL['slim'],
                str_contains($nama, 'reg')  => PerhitunganStok::STANDAR_AWAL['reguler'],
                str_contains($nama, 'jumbo') => PerhitunganStok::STANDAR_AWAL['jumbo'],
                default => null,
            };
            if ($std && ! DB::table('standar_produksi')->where('ukuran_id', $u->id)->exists()) {
                DB::table('standar_produksi')->insert($std + [
                    'ukuran_id' => $u->id, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        foreach (self::PERAN_UTAMA as $peran => [$nama, $satuan]) {
            if (DB::table('bahan_baku')->where('peran', $peran)->exists()) {
                continue;
            }
            $ada = $this->cariBahanUtama($peran, $nama);
            if ($ada) {
                DB::table('bahan_baku')->where('id', $ada->id)->update(['peran' => $peran]);
            } elseif ($peran === 'gas') {
                // Gas LPG belum ada di data awal -> dibuat (contoh 5 tabung)
                $id = DB::table('bahan_baku')->insertGetId([
                    'nama_bahan' => 'Gas LPG 5 kg', 'satuan' => 'tabung', 'stok_saat_ini' => 5, 'stok_minimum' => 1,
                    'peran' => 'gas', 'status_stok' => 'aman', 'created_at' => now(), 'updated_at' => now(),
                ]);
                if ($adminId) {
                    DB::table('riwayat_stok')->insert([
                        'bahan_baku_id' => $id, 'jenis' => 'masuk', 'sumber' => 'manual', 'jumlah' => 5,
                        'tanggal' => now()->toDateString(), 'user_id' => $adminId, 'stok_sesudah' => 5,
                        'keterangan' => 'Stok awal', 'created_at' => now(),
                    ]);
                }
            }
        }

        $this->hubungkanOtomatis($adminId);
        $this->segarkanSemuaStatus();
    }

    public function hubungkanOtomatis(?int $adminId = null): int
    {
        $jumlah = 0;
        $peta = [
            // tabel master => [kolom nama, peran bahan, awalan nama bahan, satuan, stok minimum]
            'filling'     => ['nama_filling', 'filling', 'Filling ', 'bungkus', 3],
            'saus_celup'  => ['nama_saus', 'saus', 'Saus ', 'bungkus', 3],
            'bumbu_tabur' => ['nama_bumbu', 'bumbu', 'Bumbu Tabur ', 'bungkus', 3],
            'ukuran'      => ['nama_ukuran', 'kemasan', 'Kemasan ', 'pcs', 50], // kemasan per ukuran
        ];

        foreach ($peta as $tabel => [$kolom, $peran, $awalan, $satuanBahan, $minBahan]) {
            foreach (DB::table($tabel)->whereNull('bahan_baku_id')->get() as $item) {
                $namaBahan = $awalan . $item->{$kolom};
                $bahan = DB::table('bahan_baku')->whereRaw('LOWER(nama_bahan) = ?', [strtolower($namaBahan)])->first();

                if ($bahan) {
                    $bahanId = $bahan->id;
                    DB::table('bahan_baku')->where('id', $bahanId)->update(['peran' => $peran]);
                } else {
                    $bahanId = DB::table('bahan_baku')->insertGetId([
                        'nama_bahan' => $namaBahan, 'satuan' => $satuanBahan, 'stok_saat_ini' => 0, 'stok_minimum' => $minBahan,
                        'peran' => $peran, 'status_stok' => 'habis', 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }

                DB::table($tabel)->where('id', $item->id)->update(['bahan_baku_id' => $bahanId]);
                $jumlah++;
            }
        }

        return $jumlah;
    }

    /** Daftar pemetaan yang belum lengkap (untuk peringatan di halaman Pengaturan). */
    public function pemetaanKurang(): array
    {
        $kurang = [];
        foreach (array_keys(self::PERAN_UTAMA) as $peran) {
            if (! DB::table('bahan_baku')->where('peran', $peran)->exists()) {
                $kurang[] = self::PERAN_UTAMA[$peran][0];
            }
        }
        foreach (['filling' => 'Filling', 'saus_celup' => 'Saus celup', 'bumbu_tabur' => 'Bumbu tabur', 'ukuran' => 'Ukuran (kemasan)'] as $t => $label) {
            $n = DB::table($t)->whereNull('bahan_baku_id')->count();
            if ($n > 0) {
                $kurang[] = "{$n} {$label} belum terhubung ke bahan baku";
            }
        }

        return $kurang;
    }

    /** Cari bahan baku yang cocok untuk satu peran (nama persis dulu, lalu kata kunci). */
    private function cariBahanUtama(string $peran, string $namaBawaan): ?object
    {
        $persis = DB::table('bahan_baku')->whereNull('peran')->whereRaw('LOWER(nama_bahan) = ?', [strtolower($namaBawaan)])->first();
        if ($persis) {
            return $persis;
        }

        foreach (self::KATA_KUNCI[$peran] as $kata) {
            $q = DB::table('bahan_baku')->whereNull('peran')->whereRaw('LOWER(nama_bahan) LIKE ?', ['%' . $kata . '%']);
            $b = $q->orderBy('id')->first();
            if ($b) {
                return $b;
            }
        }

        return null;
    }

    public function segarkanSemuaStatus(): void
    {
        foreach (DB::table('bahan_baku')->get() as $b) {
            DB::table('bahan_baku')->where('id', $b->id)->update(['status_stok' => $this->pengaturan->statusBahan($b)]);
        }
    }
}
