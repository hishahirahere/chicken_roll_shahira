<?php

namespace App\Services\StokOtomatis;


class PerhitunganStok
{
    /** Nilai awal (data simulasi). Dipakai sebagai standar pemakaian bawaan aplikasi. */
    public const PENGATURAN_AWAL = [
        'kapasitas_filling'   => 25,    // porsi per bungkus filling (500 g ÷ ±19 g, dibulatkan turun)
        'kapasitas_saus'      => 35,    // porsi per bungkus saus celup (500 g ÷ 12 g ≈ 41, dikurangi sisa menetes)
        'kapasitas_bumbu'     => 35,    // porsi per bungkus bumbu tabur (250 g ÷ 7 g ≈ 35)
        'minyak_per_batch'    => 0.4,   // liter minyak terpakai per batch penggorengan (wajan besar)
        'kapasitas_batch'     => 5,     // pieces yang digoreng sekali masak (1 batch)
        'kemasan_per_produk'  => 1,     // kemasan per 1 produk terjual

        // Isi PAKET STANDAR yang diberikan admin ke kasir (bisa diubah per pemberian di form paket)
        'paket_kg_per_ukuran' => 1,     // kg ayam per ukuran (3 ukuran = paket 3 kg)
        'paket_filling_bungkus' => 2,   // bungkus per jenis filling
        'paket_saus_bungkus'  => 2,     // bungkus per jenis saus celup
        'paket_bumbu_bungkus' => 1,     // bungkus per jenis bumbu tabur
        'paket_premix_basah_botol'  => 2,  // botol (1 botol = 1 liter) tepung premix basah per paket 60 pcs = 2 liter
        'paket_tepung_roti_bungkus' => 1,  // bungkus (1 bungkus = 1 kg) tepung roti per paket 60 pcs = 1 kg
        // Minyak & gas: JATAH tetap sekali kirim (lebih dari 1), BUKAN per hari. Dikirim ulang hanya kalau stok di kasir
        // sudah habis (0), dan jatahnya dipakai kasir untuk beberapa hari (lamanya tidak pasti, tergantung paket harian kasir).
        'paket_minyak_jerigen' => 3,    // jatah minyak sekali kirim ke kasir, dalam jerigen (1 jerigen = 5 liter)
        'paket_gas_tabung'    => 2,     // jatah gas sekali kirim ke kasir, dalam tabung
        'liter_per_jerigen'   => 5,     // isi 1 jerigen minyak (liter), untuk keterangan di layar
        'paket_kemasan_pcs'   => 60,    // kemasan

        // Stok kasir dianggap "menipis" kalau sisa <= persen ini dari jumlah yang terakhir diterima
        'batas_menipis_persen' => 20,
    ];

    /**
     * Standar awal per ukuran: pcs per 1 kg ayam.
     * Tepung TIDAK dihitung dari ayam: premix basah (botol 1 liter) & tepung roti (bungkus 1 kg) jadi isi paket tetap.
     */
    public const STANDAR_AWAL = [
        'slim'    => ['hasil_pcs_per_kg' => 25],
        'reguler' => ['hasil_pcs_per_kg' => 20],
        'jumbo'   => ['hasil_pcs_per_kg' => 15],
    ];

    /**
     * Pemakaian bahan untuk SATU transaksi penjualan.
     *
     * @param  array $items   tiap elemen = 1 produk (1 baris item_pesanan):
     *                        ['ukuran_id'=>, 'filling_id'=>, 'cara_luar'=>'saus_celup|bumbu_tabur',
     *                         'saus_celup_id'=>, 'bumbu_tabur_id'=>]
     * @param  array $standar [ukuran_id => ['hasil_pcs_per_kg'=>]]
     * @param  array $set     pengaturan (lihat PENGATURAN_AWAL)
     * @param  bool  $hitungAyam  false kalau ayam dipotong saat paket (bukan saat jual)
     */
    public static function pemakaianPenjualan(array $items, array $standar, array $set, bool $hitungAyam = true): array
    {
        $set = array_merge(self::PENGATURAN_AWAL, $set);

        $hasil = [
            'pcs' => 0, 'batch' => 0,
            'ayam' => 0.0,
            'minyak' => 0.0, 'gas' => 0.0, 'kemasan' => 0.0,
            'porsi' => ['filling' => [], 'saus' => [], 'bumbu' => []],
            'per_ukuran' => [],   // ukuran_id => pcs
            'kemasan_per_ukuran' => [], // ukuran_id => kemasan terpakai (kemasan dibedakan per ukuran)
        ];

        foreach ($items as $it) {
            $uid = (int) $it['ukuran_id'];
            $hasil['pcs']++;
            $hasil['per_ukuran'][$uid] = ($hasil['per_ukuran'][$uid] ?? 0) + 1;

            // Ayam: 1 pcs = (1 kg : hasil pcs per kg) kg ayam. Tepung tidak dihitung (isi paket tetap).
            if ($hitungAyam && isset($standar[$uid]) && (float) $standar[$uid]['hasil_pcs_per_kg'] > 0) {
                $hasil['ayam'] += 1 / (float) $standar[$uid]['hasil_pcs_per_kg'];
            }

            // Filling & topping luar: 1 produk = 1 porsi
            if (! empty($it['filling_id'])) {
                $f = (int) $it['filling_id'];
                $hasil['porsi']['filling'][$f] = ($hasil['porsi']['filling'][$f] ?? 0) + 1;
            }
            if (($it['cara_luar'] ?? null) === 'saus_celup' && ! empty($it['saus_celup_id'])) {
                $x = (int) $it['saus_celup_id'];
                $hasil['porsi']['saus'][$x] = ($hasil['porsi']['saus'][$x] ?? 0) + 1;
            }
            if (($it['cara_luar'] ?? null) === 'bumbu_tabur' && ! empty($it['bumbu_tabur_id'])) {
                $x = (int) $it['bumbu_tabur_id'];
                $hasil['porsi']['bumbu'][$x] = ($hasil['porsi']['bumbu'][$x] ?? 0) + 1;
            }

            $hasil['kemasan'] += (float) $set['kemasan_per_produk'];
            $hasil['kemasan_per_ukuran'][$uid] = ($hasil['kemasan_per_ukuran'][$uid] ?? 0) + (float) $set['kemasan_per_produk'];
        }

        // Minyak & gas dihitung per BATCH penggorengan (maks. N pcs per batch), bukan per produk
        $hasil['batch']  = self::jumlahBatch($hasil['pcs'], (float) $set['kapasitas_batch']);
        $hasil['minyak'] = $hasil['batch'] * (float) $set['minyak_per_batch'];
        // Gas TIDAK dihitung per transaksi: dipotong sekali per hari jualan (lihat StokOtomatisService)
        $hasil['gas']    = 0.0;

        foreach (['ayam', 'minyak', 'gas', 'kemasan'] as $k) {
            $hasil[$k] = round($hasil[$k], 4);
        }

        return $hasil;
    }

    public static function jumlahBatch(int|float $pcs, int|float $kapasitasBatch): int
    {
        if ($pcs <= 0 || $kapasitasBatch <= 0) {
            return 0;
        }

        return (int) ceil($pcs / $kapasitasBatch);
    }

    /**
     * Kalkulator paket: kg ayam yang dialokasikan per ukuran -> estimasi pcs produk.
     * Pembagian kg per ukuran BEBAS (tidak wajib rata); angka "1 kg = 25/20/15 pcs" hanya acuan.
     * Tepung tidak ikut dihitung di sini (isi paket tetap dalam bungkus).
     *
     * @param array $alokasi [ukuran_id => kg ayam]
     */
    public static function hitungProduksi(array $alokasi, array $standar, array $set = []): array
    {
        $set = array_merge(self::PENGATURAN_AWAL, $set);

        $total = ['ayam' => 0.0, 'pcs' => 0];
        $baris = [];

        foreach ($alokasi as $uid => $kg) {
            $kg = max(0.0, (float) $kg);
            $s  = $standar[$uid] ?? null;
            if ($s === null) {
                continue;
            }
            $pcs = (int) floor($kg * (float) $s['hasil_pcs_per_kg'] + 1e-9);

            $baris[$uid] = ['kg_ayam' => round($kg, 3), 'est_pcs' => $pcs];
            $total['ayam'] += $kg;
            $total['pcs']  += $pcs;
        }

        $batch = self::jumlahBatch($total['pcs'], (float) $set['kapasitas_batch']);

        return [
            'baris' => $baris,
            'total' => ['ayam' => round($total['ayam'], 3), 'pcs' => $total['pcs']],
            'batch'  => $batch,
            'minyak' => round($batch * (float) $set['minyak_per_batch'], 4),
            'gas'    => 0.0, // gas dihitung per hari jualan, bukan per batch
        ];
    }

    /**
     * Pemakaian bahan berkemasan "bungkus" yang dihitung per PORSI.
     * - $stok  = jumlah bungkus utuh (belum dibuka) di gudang
     * - $sisa  = sisa porsi di bungkus yang sedang berjalan (sudah dibuka)
     * - $kap   = kapasitas porsi per bungkus
     * Porsi diambil dulu dari bungkus berjalan; kalau habis, bungkus baru dibuka.
     *
     * @return array{stok: float, sisa: float, dibuka: int, porsi_terpakai: float, porsi_kurang: float}
     */
    public static function pakaiPorsi(float $stok, float $sisa, float $kap, float $porsi): array
    {
        $kap = max($kap, 0.0);
        $stok = max($stok, 0.0);
        $sisa = max($sisa, 0.0);
        $porsi = max($porsi, 0.0);

        if ($kap <= 0) {
            return ['stok' => $stok, 'sisa' => $sisa, 'dibuka' => 0, 'porsi_terpakai' => 0.0, 'porsi_kurang' => $porsi];
        }

        $perlu  = $porsi;
        $dibuka = 0;

        if ($sisa >= $perlu) {
            $sisa -= $perlu;
            $perlu = 0.0;
        } else {
            $perlu -= $sisa;
            $sisa = 0.0;
            $butuhBungkus = (int) ceil($perlu / $kap - 1e-9);
            $bisaDibuka   = (int) min($butuhBungkus, floor($stok));

            $dibuka = $bisaDibuka;
            $stok  -= $bisaDibuka;
            $tersedia = $bisaDibuka * $kap;

            if ($tersedia >= $perlu) {
                $sisa  = $tersedia - $perlu;
                $perlu = 0.0;
            } else {
                $perlu -= $tersedia;   // porsi yang tidak bisa dipenuhi (stok habis)
                $sisa = 0.0;
            }
        }

        return [
            'stok' => round($stok, 4), 'sisa' => round($sisa, 4), 'dibuka' => $dibuka,
            'porsi_terpakai' => round($porsi - $perlu, 4), 'porsi_kurang' => round($perlu, 4),
        ];
    }

    /**
     * Status stok: 'aman' | 'menipis' | 'habis'.
     * Bahan bungkus dihitung "setara bungkus" = bungkus utuh + sisa porsi / kapasitas.
     */
    public static function statusStok(float $stokSetara, float $minimum): string
    {
        if ($stokSetara <= 0.00001) {
            return 'habis';
        }

        return $stokSetara <= $minimum ? 'menipis' : 'aman';
    }

    /**
     * Batch penggorengan berjalan (akumulasi, tidak dibulatkan per transaksi).
     * Contoh: sudah 6 pcs berjalan, jual 5 pcs, kapasitas 8 -> 1 batch selesai, sisa berjalan 3.
     *
     * @return array{batch: int, berjalan: int}
     */
    public static function batchBerjalan(int $berjalan, int $pcs, int|float $kapasitas): array
    {
        $kapasitas = (int) $kapasitas;
        if ($kapasitas <= 0 || $pcs <= 0) {
            return ['batch' => 0, 'berjalan' => max($berjalan, 0)];
        }
        $total = max($berjalan, 0) + $pcs;

        return ['batch' => intdiv($total, $kapasitas), 'berjalan' => $total % $kapasitas];
    }

    /** Status stok kasir berdasarkan persen dari jumlah yang terakhir diterima. */
    public static function statusPersen(float $sisa, float $acuan, float $persen): string
    {
        if ($sisa <= 0.00001) {
            return 'habis';
        }

        return $sisa <= $acuan * $persen / 100 + 1e-9 ? 'menipis' : 'aman';
    }
}
