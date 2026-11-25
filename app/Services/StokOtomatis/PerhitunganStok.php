<?php

namespace App\Services\StokOtomatis;


class PerhitunganStok
{
    /** Nilai awal (data simulasi). Dipakai sebagai standar pemakaian bawaan aplikasi. */
    public const PENGATURAN_AWAL = [
        'kapasitas_filling'   => 25,    // porsi per bungkus filling (500 g ÷ ±19 g, dibulatkan turun)
        'kapasitas_saus'      => 35,    // porsi per bungkus saus celup (500 g ÷ 12 g ≈ 41, dikurangi sisa menetes)
        'kapasitas_bumbu'     => 35,    // porsi per bungkus bumbu tabur (250 g ÷ 7 g ≈ 35)
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
     * Pemakaian bahan untuk SATU transaksi penjualan. Semua angka bulat.
     * Ayam & tepung sudah jadi stok PCS saat paket diberikan, minyak & gas dicatat manual oleh kasir,
     * jadi yang dihitung di sini hanya pcs, filling/saus/bumbu (1 produk = 1 kali pakai) dan kemasan.
     *
     * @param  array $items   tiap elemen = 1 produk (1 baris item_pesanan):
     *                        ['ukuran_id'=>, 'filling_id'=>, 'cara_luar'=>'saus_celup|bumbu_tabur',
     *                         'saus_celup_id'=>, 'bumbu_tabur_id'=>]
     * @param  array $set     pengaturan (lihat PENGATURAN_AWAL)
     */
    public static function pemakaianPenjualan(array $items, array $set): array
    {
        $set = array_merge(self::PENGATURAN_AWAL, $set);
        $kemasanPerProduk = (int) $set['kemasan_per_produk'];

        $hasil = [
            'per_ukuran' => [],          // ukuran_id => pcs
            'kemasan_per_ukuran' => [],  // ukuran_id => kemasan terpakai (kemasan dibedakan per ukuran)
            'porsi' => ['filling' => [], 'saus' => [], 'bumbu' => []],
        ];

        foreach ($items as $it) {
            $uid = (int) $it['ukuran_id'];
            $hasil['per_ukuran'][$uid] = ($hasil['per_ukuran'][$uid] ?? 0) + 1;
            $hasil['kemasan_per_ukuran'][$uid] = ($hasil['kemasan_per_ukuran'][$uid] ?? 0) + $kemasanPerProduk;

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
        }

        return $hasil;
    }

    /**
     * Kalkulator paket: kg ayam (bulat) per ukuran -> jumlah pcs produk.
     * Pembagian kg per ukuran bebas; "1 kg = X pcs" diisi manual di Pengaturan Stok.
     *
     * @param array $alokasi [ukuran_id => kg ayam]
     * @param array $standar [ukuran_id => ['hasil_pcs_per_kg' => int]]
     */
    public static function hitungProduksi(array $alokasi, array $standar): array
    {
        $total = ['ayam' => 0, 'pcs' => 0];
        $baris = [];

        foreach ($alokasi as $uid => $kg) {
            $kg = max(0, (int) $kg);
            if (! isset($standar[$uid])) {
                continue;
            }
            $pcs = $kg * (int) $standar[$uid]['hasil_pcs_per_kg'];

            $baris[$uid] = ['kg_ayam' => $kg, 'est_pcs' => $pcs];
            $total['ayam'] += $kg;
            $total['pcs']  += $pcs;
        }

        return ['baris' => $baris, 'total' => $total];
    }

    /**
     * Status stok: 'aman' (di atas minimum) | 'menipis' (<= minimum) | 'habis' (0).
     */
    public static function statusStok(int $stok, int $minimum): string
    {
        if ($stok <= 0) {
            return 'habis';
        }

        return $stok <= $minimum ? 'menipis' : 'aman';
    }
}
