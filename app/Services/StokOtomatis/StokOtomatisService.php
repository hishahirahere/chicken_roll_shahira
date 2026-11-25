<?php

namespace App\Services\StokOtomatis;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Menghubungkan rumus (PerhitunganStok) dengan database:
 *  - memotong stok KASIR otomatis saat ada transaksi penjualan (pcs, filling/saus/bumbu, kemasan)
 *  - admin memberi paket ayam & bahan pendukung ke kasir (stok gudang berkurang)
 *  - minyak & gas manual: kasir mencatat pemakaian / minta, admin kirim, kasir konfirmasi
 *  - menulis riwayat stok + memperbarui status (aman / menipis / habis)
 *
 * Hanya memakai DB::table supaya tidak bergantung pada $fillable / casts model lama.
 */
class StokOtomatisService
{
    /**
     * Batas menipis di tangan kasir (satu aturan sederhana):
     *  - pcs, kali pakai, kemasan : menipis kalau sisa <= 10, habis kalau 0
     *  - minyak & gas (manual)    : tidak ada menipis, hanya aman / habis (habis = 0, lalu kasir minta ke admin)
     */
    private const BATAS_MENIPIS = 10;

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

    // ------------------------------------------------------------------
    // PENJUALAN (mengurangi STOK KASIR yang sedang jualan)
    // ------------------------------------------------------------------

    /**
     * Kurangi stok kasir untuk satu transaksi. Aman dipanggil dua kali (transaksi yang sama hanya diproses sekali).
     *  - pcs per ukuran (hasil dari ayam + tepung paket)
     *  - filling / saus celup / bumbu tabur: 1 kali pakai per produk
     *  - kemasan: per produk, dibedakan per ukuran (Slim / Reguler / Jumbo)
     *  - minyak & gas TIDAK dipotong otomatis (manual: kasir mencatat pemakaian, minta ke admin lewat permintaan)
     *
     * @return array{diproses: bool, peringatan: string[], menipis: string[]}
     */
    public function kurangiDariTransaksi(int $transaksiId): array
    {
        $this->siapkan();
        $peringatan = [];
        $menipis = [];

        DB::transaction(function () use ($transaksiId, &$peringatan, &$diproses, &$menipis) {
            $diproses = false;

            $transaksi = DB::table('transaksi')->where('id', $transaksiId)->first();
            if (! $transaksi) {
                return;
            }

            // Sudah pernah diproses? lewati (mencegah pemotongan ganda)
            if (DB::table('riwayat_stok')->where('transaksi_id', $transaksiId)->exists()
                || DB::table('riwayat_stok_produk')->where('transaksi_id', $transaksiId)->exists()) {
                return;
            }

            $items = DB::table('item_pesanan')->where('transaksi_id', $transaksiId)
                ->get(['ukuran_id', 'filling_id', 'cara_luar', 'saus_celup_id', 'bumbu_tabur_id'])
                ->map(fn ($r) => (array) $r)->all();
            if (! $items) {
                return;
            }

            $set   = $this->pengaturan();
            $kasirId = (int) $transaksi->user_id;
            $pakai = PerhitunganStok::pemakaianPenjualan($items, $set);
            $tanggal = substr((string) $transaksi->tanggal, 0, 10);
            $kode = 'TRX-' . str_pad((string) $transaksiId, 5, '0', STR_PAD_LEFT);

            $konteks = [
                'sumber' => 'penjualan', 'transaksi_id' => $transaksiId,
                'user_id' => $kasirId, 'kasir_id' => $kasirId, 'tanggal' => $tanggal,
                'label' => 'Penjualan otomatis · ' . $kode,
            ];

            // --- pcs per ukuran ---
            $namaUkuran = DB::table('ukuran')->pluck('nama_ukuran', 'id');
            foreach ($pakai['per_ukuran'] as $ukuranId => $pcs) {
                $this->potongProduk($kasirId, (int) $ukuranId, (int) $pcs, $transaksiId, $tanggal,
                    'Penjualan ' . $kode, $namaUkuran[$ukuranId] ?? 'ukuran', $peringatan);
            }

            // --- kemasan: dibedakan per ukuran (beli Slim -> kemasan Slim yang berkurang) ---
            foreach ($pakai['kemasan_per_ukuran'] as $ukuranId => $jumlahKemasan) {
                $bahanKemasan = DB::table('ukuran')->where('id', $ukuranId)->value('bahan_baku_id');
                if (! $bahanKemasan) {
                    $peringatan[] = 'Ukuran ' . ($namaUkuran[$ukuranId] ?? '') . ' belum terhubung ke bahan kemasan, kemasannya tidak dipotong.';
                    continue;
                }
                $this->potongBiasa('kemasan', (int) $jumlahKemasan, $konteks,
                    ($pakai['per_ukuran'][$ukuranId] ?? 0) . ' produk ' . ($namaUkuran[$ukuranId] ?? '') . ' terjual', $peringatan, (int) $bahanKemasan);
            }

            // --- filling, saus celup, bumbu tabur (per kali pakai) ---
            foreach ([
                'filling' => 'filling', 'saus' => 'saus_celup', 'bumbu' => 'bumbu_tabur',
            ] as $jenis => $tabelMaster) {
                foreach ($pakai['porsi'][$jenis] as $masterId => $porsi) {
                    $bahanId = DB::table($tabelMaster)->where('id', $masterId)->value('bahan_baku_id');
                    $this->potongPorsi($bahanId ? (int) $bahanId : null, (int) $porsi, $konteks, $peringatan);
                }
            }

            $diproses = true;

            foreach ($this->stokKasirUntuk($kasirId) as $r) {
                if ($r->status !== 'aman') {
                    $menipis[] = $r->nama . ($r->status === 'habis' ? ' HABIS' : ' menipis (sisa ' . $this->angka($r->jumlah) . ' ' . $r->satuan . ')');
                }
            }
        });

        foreach ($peringatan as $p) {
            Log::warning('[Stok Otomatis] ' . $p);
        }

        return ['diproses' => (bool) ($diproses ?? false), 'peringatan' => $peringatan, 'menipis' => $menipis];
    }

    // ------------------------------------------------------------------
    // CEK STOK SEBELUM TRANSAKSI (menu yang habis tidak boleh dijual)
    // ------------------------------------------------------------------

    /**
     * Sisa stok kasir per menu, dipakai halaman transaksi untuk mengunci menu yang habis.
     *  - ukuran : yang paling sedikit antara sisa pcs produk dan sisa kemasan ukuran itu
     *  - filling / saus / bumbu : sisa kali pakai
     * Nilai null = tidak dibatasi (menu belum dihubungkan ke bahan baku, sama seperti saat pemotongan stok).
     *
     * @param  bool $kunci  true = kunci baris stok (wajib di dalam DB::transaction) agar dua transaksi tidak berebut stok yang sama
     * @return array{ukuran: array<int,int>, filling: array<int,?int>, saus: array<int,?int>, bumbu: array<int,?int>}
     */
    public function ketersediaanKasir(int $userId, bool $kunci = false): array
    {
        $qBahan = DB::table('stok_kasir')->where('user_id', $userId);
        $qPcs   = DB::table('stok_kasir_produk')->where('user_id', $userId);
        if ($kunci) {
            $qBahan->lockForUpdate();
            $qPcs->lockForUpdate();
        }
        $stokBahan = $qBahan->pluck('stok', 'bahan_baku_id');
        $stokPcs   = $qPcs->pluck('pcs', 'ukuran_id');

        $kemasanPerProduk = max(1, (int) ($this->pengaturan()['kemasan_per_produk'] ?? 1));

        $hasil = ['ukuran' => [], 'filling' => [], 'saus' => [], 'bumbu' => []];

        foreach (DB::table('ukuran')->get(['id', 'bahan_baku_id']) as $u) {
            $sisa = (int) $stokPcs->get($u->id, 0);
            if ($u->bahan_baku_id) {
                $sisa = min($sisa, intdiv((int) $stokBahan->get($u->bahan_baku_id, 0), $kemasanPerProduk));
            }
            $hasil['ukuran'][(int) $u->id] = max(0, $sisa);
        }

        foreach (['filling' => 'filling', 'saus' => 'saus_celup', 'bumbu' => 'bumbu_tabur'] as $jenis => $tabel) {
            foreach (DB::table($tabel)->get(['id', 'bahan_baku_id']) as $m) {
                $hasil[$jenis][(int) $m->id] = $m->bahan_baku_id
                    ? max(0, (int) $stokBahan->get($m->bahan_baku_id, 0))
                    : null;
            }
        }

        return $hasil;
    }

    /**
     * Tolak transaksi kalau ada menu yang stoknya habis atau tidak cukup untuk jumlah yang dipesan.
     * Dipanggil di dalam DB::transaction sebelum transaksi disimpan: kalau gagal, tidak ada yang tersimpan.
     *
     * @param  array $items  1 elemen = 1 porsi (ukuran_id, filling_id, cara_luar, saus_celup_id, bumbu_tabur_id)
     * @throws ValidationException
     */
    public function pastikanStokCukup(int $userId, array $items): void
    {
        $sisa  = $this->ketersediaanKasir($userId, true);
        $pakai = PerhitunganStok::pemakaianPenjualan($items, $this->pengaturan());

        // [jenis hasil ketersediaan => [kunci di $pakai, tabel master, kolom nama]]
        $cek = [
            'ukuran'  => [$pakai['per_ukuran'],      'ukuran',      'nama_ukuran'],
            'filling' => [$pakai['porsi']['filling'], 'filling',     'nama_filling'],
            'saus'    => [$pakai['porsi']['saus'],    'saus_celup',  'nama_saus'],
            'bumbu'   => [$pakai['porsi']['bumbu'],   'bumbu_tabur', 'nama_bumbu'],
        ];

        $pesan = [];
        foreach ($cek as $jenis => [$dipesan, $tabel, $kolomNama]) {
            foreach ($dipesan as $id => $jumlah) {
                $tersedia = $sisa[$jenis][$id] ?? null;
                if ($tersedia === null || $jumlah <= $tersedia) {
                    continue; // tidak dibatasi, atau stok cukup
                }
                $nama = DB::table($tabel)->where('id', $id)->value($kolomNama) ?? ('#' . $id);
                $pesan[] = $tersedia <= 0
                    ? "Stok {$nama} habis."
                    : "Stok {$nama} tidak cukup (sisa {$tersedia}, dipesan {$jumlah}).";
            }
        }

        if ($pesan) {
            throw ValidationException::withMessages([
                'items' => [implode(' ', $pesan) . ' Muat ulang halaman, lalu sesuaikan pesanan.'],
            ]);
        }
    }

    /** Kurangi pcs satu ukuran di stok kasir + catat di riwayat pcs. */
    private function potongProduk(int $kasirId, int $ukuranId, int $pcs, int $transaksiId, string $tanggal, string $ket, string $namaUkuran, array &$peringatan): void
    {
        DB::table('stok_kasir_produk')->insertOrIgnore([
            'user_id' => $kasirId, 'ukuran_id' => $ukuranId, 'pcs' => 0, 'acuan' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $row   = DB::table('stok_kasir_produk')->where('user_id', $kasirId)->where('ukuran_id', $ukuranId)->lockForUpdate()->first();
        $baru  = max(0, (int) $row->pcs - $pcs);
        $nyata = (int) $row->pcs - $baru;

        if ($nyata < $pcs) {
            $peringatan[] = 'Stok ' . $namaUkuran . ' di kasir tidak cukup, kurang ' . ($pcs - $nyata) . ' pcs.';
            $ket .= ' · STOK KURANG ' . ($pcs - $nyata) . ' pcs';
        }

        DB::table('stok_kasir_produk')->where('id', $row->id)->update(['pcs' => $baru, 'updated_at' => now()]);
        DB::table('riwayat_stok_produk')->insert([
            'user_id' => $kasirId, 'ukuran_id' => $ukuranId, 'jenis' => 'keluar', 'pcs' => $nyata,
            'stok_sesudah' => $baru, 'transaksi_id' => $transaksiId, 'keterangan' => mb_substr($ket, 0, 255),
            'tanggal' => $tanggal, 'created_at' => now(),
        ]);
    }

    // ------------------------------------------------------------------
    // Detail pemotongan
    // ------------------------------------------------------------------

    /** Ambil (atau buat) baris stok milik kasir untuk satu bahan, terkunci selama transaksi. */
    private function barisStokKasir(int $userId, int $bahanId): object
    {
        DB::table('stok_kasir')->insertOrIgnore([
            'user_id' => $userId, 'bahan_baku_id' => $bahanId, 'stok' => 0, 'acuan' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('stok_kasir')->where('user_id', $userId)->where('bahan_baku_id', $bahanId)->lockForUpdate()->first();
    }

    /** Bahan biasa (kemasan): dikurangi dari stok kasir. Semua angka bulat. */
    private function potongBiasa(string $peran, int $jumlah, array $k, string $rincian, array &$peringatan, ?int $bahanId = null): void
    {
        if ($jumlah <= 0) {
            return;
        }

        $bahan = $bahanId
            ? DB::table('bahan_baku')->where('id', $bahanId)->first()
            : DB::table('bahan_baku')->where('peran', $peran)->orderBy('id')->first();
        if (! $bahan) {
            $peringatan[] = "Bahan dengan peran '{$peran}' belum ada di daftar bahan baku, pemakaian {$jumlah} tidak dipotong.";

            return;
        }

        $row    = $this->barisStokKasir((int) $k['kasir_id'], (int) $bahan->id);
        $stok   = (int) $row->stok;
        $baru   = max(0, $stok - $jumlah);
        $nyata  = $stok - $baru;
        $kurang = $jumlah - $nyata;

        if ($kurang > 0) {
            $peringatan[] = "Stok {$bahan->nama_bahan} di kasir tidak cukup, kurang {$kurang} {$bahan->satuan}.";
            $rincian .= " · STOK KASIR KURANG {$kurang} {$bahan->satuan}";
        }

        DB::table('stok_kasir')->where('id', $row->id)->update(['stok' => $baru, 'updated_at' => now()]);
        $this->tulisRiwayat((int) $bahan->id, $nyata, $baru, $k, $rincian);
    }

    /** Filling / saus / bumbu: stok kasir dihitung langsung dalam KALI PAKAI (1 produk = 1 kali pakai). */
    private function potongPorsi(?int $bahanId, int $porsi, array $k, array &$peringatan): void
    {
        if (! $bahanId) {
            $peringatan[] = 'Ada filling/saus/bumbu yang belum dihubungkan ke bahan baku, pemakaiannya tidak dipotong.';

            return;
        }

        $bahan = DB::table('bahan_baku')->where('id', $bahanId)->first();
        if (! $bahan) {
            return;
        }

        $row    = $this->barisStokKasir((int) $k['kasir_id'], (int) $bahan->id);
        $stok   = (int) $row->stok;
        $baru   = max(0, $stok - $porsi);
        $nyata  = $stok - $baru;
        $kurang = $porsi - $nyata;

        $rincian = $nyata . ' kali pakai';
        if ($kurang > 0) {
            $peringatan[] = "Stok {$bahan->nama_bahan} di kasir tidak cukup, kurang {$kurang} kali pakai.";
            $rincian .= ' · STOK KASIR KURANG ' . $kurang . ' kali pakai';
        }

        DB::table('stok_kasir')->where('id', $row->id)->update(['stok' => $baru, 'updated_at' => now()]);
        $this->tulisRiwayat((int) $bahan->id, $nyata, $baru, $k, $rincian);
    }

    // ------------------------------------------------------------------
    // PAKET: admin memberi bahan ke kasir
    // ------------------------------------------------------------------

    /**
     * Isi 1 PAKET AYAM = (kg ayam per ukuran x jumlah ukuran aktif) kg, default 3 kg.
     * Paket hanya berisi bahan yang habisnya barengan: ayam, tepung (melumuri ayam), dan kemasan (1 pcs = 1 dus).
     */
    public function kgPerPaket(?array $set = null): int
    {
        $set = $set ?? $this->pengaturan();
        $jumlahUkuran = DB::table('ukuran')->where('status', 'aktif')->count() ?: DB::table('ukuran')->count();

        return max(1, (int) $set['paket_kg_per_ukuran'] * max(1, $jumlahUkuran));
    }

    /**
     * PAKET AYAM: total kg ayam harus kelipatan isi paket (mis. 3, 6, 9 kg); pembagian ke tiap ukuran BEBAS.
     * Tepung & kemasan ikut paket. Filling / saus / bumbu TIDAK ikut (lihat beriBahanPendukung).
     */
    public function beriPaketAyam(int $kasirId, string $tanggal, array $kg, int $adminId, ?string $catatan = null): array
    {
        $this->siapkan($adminId);
        $perPaket = $this->kgPerPaket();
        $kg       = array_filter(array_map('intval', $kg), fn ($v) => $v > 0);
        $totalKg  = array_sum($kg);

        if ($totalKg <= 0) {
            throw ValidationException::withMessages(['paket' => 'Isi minimal 1 paket ayam (' . $perPaket . ' kg).']);
        }
        if ($totalKg % $perPaket !== 0) {
            throw ValidationException::withMessages(['paket' => 'Total ayam ' . $totalKg . ' kg bukan kelipatan paket. 1 paket = ' . $perPaket . ' kg, jadi isi ' . $perPaket . ', ' . ($perPaket * 2) . ', ' . ($perPaket * 3) . ' kg dst. (pembagian per ukuran bebas).']);
        }

        // Tepung & kemasan TIDAK bisa diubah admin: otomatis mengikuti jumlah paket dan ayam per ukuran.
        $jumlahPaket = intdiv($totalKg, $perPaket);
        $set     = $this->pengaturan();
        $standar = $this->standar();
        $tepung  = [
            'premix_basah' => $jumlahPaket * (int) $set['paket_premix_basah_botol'],
            'tepung_roti'  => $jumlahPaket * (int) $set['paket_tepung_roti_bungkus'],
        ];
        $kemasan = [];
        foreach ($kg as $ukuranId => $kgUkuran) {
            if ($kgUkuran > 0) {
                $kemasan[(int) $ukuranId] = (int) $kgUkuran * (int) ($standar[(int) $ukuranId]['hasil_pcs_per_kg'] ?? 0);
            }
        }

        $hasil = $this->beriPaket($kasirId, $tanggal, $kg, [], $kemasan, $adminId, $catatan, $tepung, 'ayam');
        $hasil['paket'] = $jumlahPaket;

        return $hasil;
    }

    /**
     * BAHAN PENDUKUNG (filling, saus celup, bumbu tabur): diambil bebas per rasa dan per bungkus, kapan saja kasir
     * butuh. Tidak terikat paket ayam karena habisnya tergantung rasa yang laku.
     * Gudang berkurang dalam bungkus, stok kasir bertambah dalam kali pakai (bungkus x kapasitas).
     *
     * @param array $bungkus [bahan_baku_id => jumlah bungkus]
     */
    public function beriBahanPendukung(int $kasirId, string $tanggal, array $bungkus, int $adminId, ?string $catatan = null): array
    {
        $bungkus = array_filter(array_map('intval', $bungkus), fn ($v) => $v > 0);
        if (! $bungkus) {
            throw ValidationException::withMessages(['paket' => 'Isi minimal satu filling, saus celup, atau bumbu tabur.']);
        }

        return $this->beriPaket($kasirId, $tanggal, [], $bungkus, [], $adminId, $catatan, [], 'pendukung');
    }

    /**
     * Beri satu paket ke kasir.
     *  - Stok dapur berkurang dalam satuan bahan (kg ayam, bungkus tepung, bungkus filling, ...)
     *  - Tepung premix basah (BOTOL, 1 botol = 1 liter) & tepung roti (BUNGKUS, 1 bungkus = 1 kg) = isi paket tetap, tidak dihitung dari ayam
     *  - Stok kasir bertambah dalam bentuk: pcs per ukuran, bungkus (kali pakai), kemasan per ukuran
     *  - Minyak & gas TIDAK ikut paket: lewat alur permintaan (minta -> kirim -> konfirmasi)
     *  - Semua angka paket bilangan bulat
     *
     * @param array $kg      [ukuran_id => kg ayam]
     * @param array $bungkus [bahan_baku_id => jumlah bungkus] (filling / saus / bumbu)
     * @param array $tepung  ['premix_basah' => botol, 'tepung_roti' => bungkus] (hanya mengurangi gudang)
     * @return array ringkasan untuk pesan sukses
     * @throws ValidationException bila stok gudang tidak cukup atau bahan utama belum diatur
     */
    public function beriPaket(int $kasirId, string $tanggal, array $kg, array $bungkus, array $kemasan, int $adminId, ?string $catatan = null, array $tepung = [], string $jenis = 'ayam'): array
    {
        $pendukung = $jenis === 'pendukung';
        $this->siapkan($adminId);
        $set     = $this->pengaturan();
        $alokasi = [];
        foreach ($kg as $ukuranId => $v) {
            if ((int) $v > 0 && DB::table('ukuran')->where('id', $ukuranId)->exists()) {
                $alokasi[(int) $ukuranId] = (int) $v;
            }
        }
        $bungkus = array_filter(array_map('intval', $bungkus), fn ($v) => $v > 0);

        // Kemasan per ukuran: [ukuran_id => pcs]
        $kemasan = array_filter(array_map('intval', $kemasan), fn ($v) => $v > 0);

        // Tepung: [peran bahan => jumlah] (premix basah = botol, tepung roti = bungkus)
        $tepungBungkus = array_filter([
            'tepung_premix_basah' => (int) ($tepung['premix_basah'] ?? 0),
            'tepung_roti'         => (int) ($tepung['tepung_roti'] ?? 0),
        ], fn ($v) => $v > 0);

        if (! $alokasi && ! $bungkus && ! $kemasan && ! $tepungBungkus) {
            throw ValidationException::withMessages(['paket' => 'Isi minimal satu bahan pada paket.']);
        }

        return DB::transaction(function () use ($kasirId, $tanggal, $alokasi, $bungkus, $kemasan, $tepungBungkus, $adminId, $catatan, $set, $pendukung) {
            $kasir = DB::table('users')->where('id', $kasirId)->where('role', 'kasir')->first();
            if (! $kasir) {
                throw ValidationException::withMessages(['kasir_id' => 'Kasir tidak ditemukan.']);
            }

            $hitung = PerhitunganStok::hitungProduksi($alokasi, $this->standar());
            $namaUkuran = DB::table('ukuran')->pluck('nama_ukuran', 'id');
            $rincianAyam = collect($hitung['baris'])
                ->map(fn ($b, $id) => $this->angka($b['kg_ayam']) . ' kg ' . ($namaUkuran[$id] ?? 'ukuran') . ' = ' . $b['est_pcs'] . ' pcs')
                ->implode(', ');

            // --- susun kebutuhan gudang: [bahan_id => [jumlah, rincian]] ---
            $perlu = [];
            $galat = [];
            $tambah = function (string $peran, int $jumlah, string $rincian) use (&$perlu, &$galat) {
                if ($jumlah <= 0) {
                    return;
                }
                $b = DB::table('bahan_baku')->where('peran', $peran)->orderBy('id')->first();
                if (! $b) {
                    $galat[] = 'Bahan "' . (self::PERAN_UTAMA[$peran][0] ?? $peran) . '" belum tersedia di data bahan baku. Jalankan seeder PengaturanStokSeeder.';

                    return;
                }
                $perlu[$b->id] = [($perlu[$b->id][0] ?? 0) + $jumlah, $rincian];
            };

            $tambah('ayam', $hitung['total']['ayam'], $rincianAyam);
            // Tepung: isi paket tetap, diatur admin di form paket
            //  - premix basah: botol, 1 botol = 1 liter | tepung roti: bungkus, 1 bungkus = 1 kg
            $ketTepung = ['tepung_premix_basah' => 'botol (1 liter)', 'tepung_roti' => 'bungkus (1 kg)'];
            foreach ($tepungBungkus as $peranTepung => $nTepung) {
                $tambah($peranTepung, (int) $nTepung, 'Paket ke kasir · ' . $nTepung . ' ' . ($ketTepung[$peranTepung] ?? ''));
            }

            $bahanBungkus = [];
            foreach ($bungkus as $bahanId => $n) {
                $b = DB::table('bahan_baku')->where('id', $bahanId)->first();
                if (! $b || ! $this->bahanBerbungkus($b)) {
                    continue;
                }
                $bahanBungkus[$b->id] = $n;
                $perlu[$b->id] = [($perlu[$b->id][0] ?? 0) + $n, $pendukung ? 'Bahan pendukung ke kasir' : 'Paket ke kasir'];
            }

            // Kemasan dibedakan per ukuran: tiap ukuran punya bahan kemasannya sendiri
            $kemasanBahan = [];
            foreach ($kemasan as $ukuranId => $n) {
                $u = DB::table('ukuran')->where('id', $ukuranId)->first(['nama_ukuran', 'bahan_baku_id']);
                if (! $u) {
                    continue;
                }
                if (! $u->bahan_baku_id) {
                    $galat[] = 'Ukuran ' . $u->nama_ukuran . ' belum terhubung ke bahan kemasan.';
                    continue;
                }
                $kemasanBahan[$u->bahan_baku_id] = ($kemasanBahan[$u->bahan_baku_id] ?? 0) + $n;
                $perlu[$u->bahan_baku_id] = [($perlu[$u->bahan_baku_id][0] ?? 0) + $n, 'Kemasan ' . $u->nama_ukuran . ' · paket ke kasir'];
            }

            // --- cek stok gudang (semua atau tidak sama sekali) ---
            $gudang = [];
            foreach ($perlu as $bahanId => [$jumlah, $rincian]) {
                $b = DB::table('bahan_baku')->where('id', $bahanId)->lockForUpdate()->first();
                $gudang[$bahanId] = $b;
                if ($jumlah > (int) $b->stok_saat_ini) {
                    $galat[] = 'Stok ' . $b->nama_bahan . ' di dapur hanya ' . $this->angka((int) $b->stok_saat_ini) . ' ' . $b->satuan
                        . ', paket butuh ' . $this->angka($jumlah) . ' ' . $b->satuan . '.';
                }
            }
            if ($galat) {
                throw ValidationException::withMessages(['paket' => $galat]);
            }

            // --- kurangi gudang + riwayat ---
            $konteks = [
                'sumber' => 'paket', 'transaksi_id' => null,
                'user_id' => $adminId, 'kasir_id' => $kasirId, 'tanggal' => $tanggal,
                'label' => ($pendukung ? 'Bahan pendukung ke kasir ' : 'Paket ayam ke kasir ') . $kasir->name . ($catatan ? ' · ' . $catatan : ''),
            ];
            foreach ($perlu as $bahanId => [$jumlah, $rincian]) {
                $b = $gudang[$bahanId];
                $baru = max(0, (int) $b->stok_saat_ini - $jumlah);
                $b->stok_saat_ini = $baru;
                DB::table('bahan_baku')->where('id', $bahanId)->update([
                    'stok_saat_ini' => $baru, 'status_stok' => $this->statusBahan($b), 'updated_at' => now(),
                ]);
                $this->tulisRiwayat((int) $bahanId, $jumlah, $baru, $konteks, $rincian);
            }

            // --- tambah stok kasir ---
            foreach ($hitung['baris'] as $ukuranId => $r) {
                if ($r['est_pcs'] > 0) {
                    $this->tambahProduk($kasirId, (int) $ukuranId, (int) $r['est_pcs'], $tanggal, 'Paket dari admin · ' . $this->angka($r['kg_ayam']) . ' kg ayam');
                }
            }
            foreach ($bahanBungkus as $bahanId => $n) {
                $this->tambahStokKasirBahan($kasirId, (int) $bahanId, (int) $n, $set, $pendukung ? 'Bahan pendukung dari admin' : 'Paket dari admin');
            }
            // Kemasan (per ukuran) masuk ke stok kasir
            foreach ($kemasanBahan as $bahanId => $n) {
                $this->tambahStokKasirBahan($kasirId, (int) $bahanId, (int) $n, $set);
            }

            return [
                'kasir' => $kasir->name, 'pcs' => $hitung['total']['pcs'], 'ayam' => $hitung['total']['ayam'], 'baris' => $hitung['baris'],
                'premix_basah' => (int) ($tepungBungkus['tepung_premix_basah'] ?? 0), 'tepung_roti' => (int) ($tepungBungkus['tepung_roti'] ?? 0),
            ];
        });
    }

    // ------------------------------------------------------------------
    // MINYAK & GAS: manual (kasir minta -> admin kirim -> kasir konfirmasi)
    // ------------------------------------------------------------------

    /** Peran bahan yang dikelola manual (tidak berkurang otomatis dari penjualan). */
    public const PERAN_MANUAL = ['minyak', 'gas'];

    /**
     * KASIR mencatat pemakaian manual hari ini (mis. gas 1 tabung, minyak 1 jerigen).
     * Stok minyak/gas di tangan kasir berkurang sesuai yang dicatat, dan admin bisa melihat catatannya.
     *
     * @param array $jumlah [peran => bilangan bulat]
     * @return array{dicatat: string[], peringatan: string[]}
     */
    public function catatPemakaian(int $kasirId, array $jumlah, string $tanggal, ?string $catatan = null): array
    {
        $jumlah = array_filter(array_map('intval', $jumlah), fn ($v) => $v > 0);
        $jumlah = array_intersect_key($jumlah, array_flip(self::PERAN_MANUAL));
        if (! $jumlah) {
            throw ValidationException::withMessages(['pemakaian' => 'Isi minimal pemakaian minyak atau gas.']);
        }

        return DB::transaction(function () use ($kasirId, $jumlah, $tanggal, $catatan) {
            $dicatat = [];
            $peringatan = [];
            foreach ($jumlah as $peran => $n) {
                $bahan = DB::table('bahan_baku')->where('peran', $peran)->orderBy('id')->first();
                if (! $bahan) {
                    throw ValidationException::withMessages(['pemakaian' => 'Bahan ' . self::PERAN_UTAMA[$peran][0] . ' belum tersedia di data bahan baku.']);
                }

                $row  = $this->barisStokKasir($kasirId, (int) $bahan->id);
                $stok = (int) $row->stok;
                if ($n > $stok) {
                    $peringatan[] = 'Stok ' . $bahan->nama_bahan . ' di catatanmu hanya ' . $stok . ' ' . $bahan->satuan
                        . ', pemakaian ' . $n . ' tetap dicatat. Jangan lupa minta ke admin.';
                }
                DB::table('stok_kasir')->where('id', $row->id)->update(['stok' => max(0, $stok - $n), 'updated_at' => now()]);
                if ($n <= $stok && $stok - $n === 0) {
                    $peringatan[] = $bahan->nama_bahan . ' kamu sudah habis. Silakan minta jatah baru ke admin.';
                }

                $this->tulisRiwayatKasir($kasirId, (int) $bahan->id, 'keluar', 'pemakaian', $n, max(0, $stok - $n),
                    'Pemakaian manual', $catatan, $tanggal);
                DB::table('pemakaian_kasir')->insert([
                    'user_id' => $kasirId, 'bahan_baku_id' => $bahan->id, 'jumlah' => $n, 'tanggal' => $tanggal,
                    'catatan' => $catatan ? mb_substr($catatan, 0, 255) : null, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $dicatat[] = $n . ' ' . $bahan->satuan . ' ' . strtolower($bahan->nama_bahan);
            }

            return ['dicatat' => $dicatat, 'peringatan' => $peringatan];
        });
    }

    /**
     * Jatah tetap minyak & gas sekali kirim ke kasir (dari Pengaturan Stok).
     * Jatah ini BUKAN untuk satu hari: dipakai kasir untuk beberapa hari (tidak pasti, tergantung paket harian),
     * dan dikirim lagi baru kalau stok di kasir sudah habis. Kasir tidak menentukan jumlah sendiri.
     *
     * @return array{minyak: int, gas: int} minyak dalam jerigen, gas dalam tabung
     */
    public function porsiKirim(?array $set = null): array
    {
        $set ??= $this->pengaturan();

        return [
            'minyak' => max(1, (int) $set['paket_minyak_jerigen']),
            'gas'    => max(1, (int) $set['paket_gas_tabung']),
        ];
    }

    /**
     * KASIR minta minyak / gas ke admin (admin dapat notifikasi).
     * Jumlah TIDAK bisa dipilih kasir: selalu sebesar jatah tetap (lihat porsiKirim),
     * dan baru boleh diminta kalau stok di tangan kasir sudah HABIS (0).
     *
     * @param array $jumlah peran yang diminta (key = peran, nilai diabaikan)
     * @return int banyaknya permintaan yang dibuat
     */
    public function buatPermintaan(int $kasirId, array $jumlah, ?string $catatan = null): int
    {
        $jumlah = array_intersect_key($jumlah, array_flip(self::PERAN_MANUAL));
        if (! $jumlah) {
            throw ValidationException::withMessages(['permintaan' => 'Pilih minyak atau gas yang diminta.']);
        }

        $porsi = $this->porsiKirim();
        $stokKasir = $this->stokKasirUntuk($kasirId);

        return DB::transaction(function () use ($kasirId, $jumlah, $catatan, $porsi, $stokKasir) {
            $dibuat = 0;
            foreach ($jumlah as $peran => $n) {
                $bahan = DB::table('bahan_baku')->where('peran', $peran)->orderBy('id')->first();
                if (! $bahan) {
                    throw ValidationException::withMessages(['permintaan' => 'Bahan ' . self::PERAN_UTAMA[$peran][0] . ' belum tersedia di data bahan baku.']);
                }

                // Jatah dikirim ulang hanya kalau stok di tangan kasir sudah habis (0)
                $milik = $stokKasir->firstWhere('peran', $peran);
                if ($milik && (int) $milik->jumlah > 0) {
                    throw ValidationException::withMessages(['permintaan' => 'Stok ' . strtolower($bahan->nama_bahan) . ' kamu masih ' . (int) $milik->jumlah . ' ' . $bahan->satuan
                        . '. Minta baru bisa kalau sudah habis (0).']);
                }

                $masihAda = DB::table('permintaan_stok')->where('kasir_id', $kasirId)->where('bahan_baku_id', $bahan->id)
                    ->whereIn('status', ['diminta', 'dikirim'])->exists();
                if ($masihAda) {
                    throw ValidationException::withMessages(['permintaan' => 'Permintaan ' . strtolower($bahan->nama_bahan) . ' sebelumnya masih berjalan. Tunggu sampai selesai atau batalkan dulu.']);
                }

                DB::table('permintaan_stok')->insert([
                    'kasir_id' => $kasirId, 'bahan_baku_id' => $bahan->id,
                    'jumlah_diminta' => $porsi[$peran],
                    'status' => 'diminta', 'catatan' => $catatan ? mb_substr($catatan, 0, 150) : null,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $dibuat++;
            }

            return $dibuat;
        });
    }

    /**
     * ADMIN mengirim minyak dan/atau gas ke kasir lewat satu form (seperti Beri Paket).
     * Kalau kasir sudah minta, permintaannya otomatis terpenuhi; kalau belum, pengiriman dibuat baru.
     * Stok gudang BELUM berkurang: baru berkurang setelah kasir mengonfirmasi barangnya diterima.
     * Jumlah bilangan bulat. Mengirim lagi untuk bahan yang masih menunggu konfirmasi = mengganti jumlahnya.
     *
     * @return array{kasir: string, minyak: int, gas: int}
     */
    public function kirimKeKasir(int $kasirId, int $minyak, int $gas, int $adminId, ?string $catatan = null): array
    {
        $this->siapkan($adminId);

        $jumlah = array_filter(['minyak' => $minyak, 'gas' => $gas], fn ($v) => $v > 0);
        if (! $jumlah) {
            throw ValidationException::withMessages(['kirim' => 'Pilih minimal minyak atau gas.']);
        }

        return DB::transaction(function () use ($kasirId, $jumlah, $adminId, $catatan, $minyak, $gas) {
            $kasir = DB::table('users')->where('id', $kasirId)->where('role', 'kasir')->first();
            if (! $kasir) {
                throw ValidationException::withMessages(['kasir_id' => 'Kasir tidak ditemukan.']);
            }

            $galat = [];
            $rencana = [];
            foreach ($jumlah as $peran => $n) {
                $bahan = DB::table('bahan_baku')->where('peran', $peran)->orderBy('id')->first();
                if (! $bahan) {
                    $galat[] = 'Bahan ' . self::PERAN_UTAMA[$peran][0] . ' belum tersedia di data bahan baku.';
                } elseif ($n > (int) $bahan->stok_saat_ini) {
                    $galat[] = 'Stok ' . $bahan->nama_bahan . ' di dapur hanya ' . (int) $bahan->stok_saat_ini . ' ' . $bahan->satuan . ', yang mau dikirim ' . $n . ' ' . $bahan->satuan . '.';
                } else {
                    $rencana[] = [$bahan, $n];
                }
            }
            if ($galat) {
                throw ValidationException::withMessages(['kirim' => $galat]);
            }

            foreach ($rencana as [$bahan, $n]) {
                $buka = DB::table('permintaan_stok')->where('kasir_id', $kasirId)->where('bahan_baku_id', $bahan->id)
                    ->whereIn('status', ['diminta', 'dikirim'])->orderBy('id')->lockForUpdate()->first();

                $isi = [
                    'status' => 'dikirim', 'jumlah_dikirim' => $n, 'admin_id' => $adminId,
                    'dikirim_pada' => now(), 'updated_at' => now(),
                ];
                if ($catatan) {
                    $isi['catatan'] = mb_substr($catatan, 0, 150);
                }

                if ($buka) {
                    DB::table('permintaan_stok')->where('id', $buka->id)->update($isi);
                } else {
                    DB::table('permintaan_stok')->insert($isi + ['kasir_id' => $kasirId, 'bahan_baku_id' => $bahan->id, 'created_at' => now()]);
                }
            }

            return ['kasir' => $kasir->name, 'minyak' => $minyak, 'gas' => $gas];
        });
    }

    /**
     * KASIR mengonfirmasi barang sudah diterima. Baru di sini stok gudang berkurang (tercatat di Riwayat Stok)
     * dan stok minyak/gas di tangan kasir bertambah. Jumlah boleh dikoreksi, tapi tidak boleh lebih dari yang dikirim.
     */
    public function konfirmasiTerima(int $permintaanId, int $kasirId, int $diterima): void
    {
        DB::transaction(function () use ($permintaanId, $kasirId, $diterima) {
            $p = DB::table('permintaan_stok')->where('id', $permintaanId)->lockForUpdate()->first();
            if (! $p || (int) $p->kasir_id !== $kasirId || $p->status !== 'dikirim') {
                throw ValidationException::withMessages(['diterima' => 'Permintaan ini tidak bisa dikonfirmasi.']);
            }
            if ($diterima < 1 || $diterima > (int) $p->jumlah_dikirim) {
                throw ValidationException::withMessages(['diterima' => 'Jumlah diterima harus 1 sampai ' . $p->jumlah_dikirim . '.']);
            }

            $set   = $this->pengaturan();
            $bahan = DB::table('bahan_baku')->where('id', $p->bahan_baku_id)->lockForUpdate()->first();
            if ($diterima > (int) $bahan->stok_saat_ini) {
                throw ValidationException::withMessages(['diterima' => 'Stok ' . $bahan->nama_bahan . ' di dapur tidak cukup (' . (int) $bahan->stok_saat_ini . ' ' . $bahan->satuan . '). Hubungi admin.']);
            }

            $kasir = DB::table('users')->where('id', $kasirId)->value('name');
            $baru  = (int) $bahan->stok_saat_ini - $diterima;
            $bahan->stok_saat_ini = $baru;
            DB::table('bahan_baku')->where('id', $bahan->id)->update([
                'stok_saat_ini' => $baru, 'status_stok' => $this->statusBahan($bahan), 'updated_at' => now(),
            ]);

            $this->tulisRiwayat((int) $bahan->id, $diterima, $baru, [
                'sumber' => 'kirim', 'transaksi_id' => null,
                'user_id' => (int) ($p->admin_id ?: $kasirId), 'kasir_id' => $kasirId, 'tanggal' => now()->toDateString(),
                'label' => 'Kirim ke kasir ' . $kasir . ' · dikonfirmasi diterima',
            ], 'Dikirim admin ' . $p->jumlah_dikirim . ', diterima kasir ' . $diterima);

            $this->tambahStokKasirBahan($kasirId, (int) $bahan->id, $diterima, $set, 'Dikirim admin · sudah diterima');

            DB::table('permintaan_stok')->where('id', $p->id)->update([
                'status' => 'selesai', 'jumlah_diterima' => $diterima, 'diterima_pada' => now(), 'updated_at' => now(),
            ]);
        });
    }

    /** Batalkan permintaan: kasir hanya saat masih "diminta"; admin saat "diminta" atau "dikirim". */
    public function batalPermintaan(int $permintaanId, int $userId, bool $admin): void
    {
        $p = DB::table('permintaan_stok')->where('id', $permintaanId)->first();
        $boleh = $p && ($admin ? in_array($p->status, ['diminta', 'dikirim'], true)
            : ((int) $p->kasir_id === $userId && $p->status === 'diminta'));
        if (! $boleh) {
            throw ValidationException::withMessages(['permintaan' => 'Permintaan ini tidak bisa dibatalkan.']);
        }

        DB::table('permintaan_stok')->where('id', $permintaanId)->update(['status' => 'batal', 'updated_at' => now()]);
    }

    private function tambahProduk(int $kasirId, int $ukuranId, int $pcs, string $tanggal, string $ket): void
    {
        DB::table('stok_kasir_produk')->insertOrIgnore([
            'user_id' => $kasirId, 'ukuran_id' => $ukuranId, 'pcs' => 0, 'acuan' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $row  = DB::table('stok_kasir_produk')->where('user_id', $kasirId)->where('ukuran_id', $ukuranId)->lockForUpdate()->first();
        $baru = (int) $row->pcs + $pcs;
        DB::table('stok_kasir_produk')->where('id', $row->id)->update(['pcs' => $baru, 'acuan' => $baru, 'updated_at' => now()]);
        DB::table('riwayat_stok_produk')->insert([
            'user_id' => $kasirId, 'ukuran_id' => $ukuranId, 'jenis' => 'masuk', 'pcs' => $pcs,
            'stok_sesudah' => $baru, 'transaksi_id' => null, 'keterangan' => $ket, 'tanggal' => $tanggal, 'created_at' => now(),
        ]);
    }

    /**
     * Tambah stok bahan milik kasir dan perbarui acuan bar "menipis".
     * $jumlah dalam satuan gudang. Filling / saus / bumbu: bungkus x kali pakai per bungkus = kali pakai di tangan kasir.
     */
    private function tambahStokKasirBahan(int $kasirId, int $bahanId, int $jumlah, array $set, string $ket = 'Paket dari admin'): void
    {
        $bahan = DB::table('bahan_baku')->where('id', $bahanId)->first();
        if ($bahan && $this->bahanBerbungkus($bahan)) {
            $jumlah *= $this->kapasitasBungkus($bahan, $set);
        }

        $row  = $this->barisStokKasir($kasirId, $bahanId);
        $stok = (int) $row->stok + $jumlah;

        DB::table('stok_kasir')->where('id', $row->id)->update(['stok' => $stok, 'acuan' => $stok, 'updated_at' => now()]);
        $this->tulisRiwayatKasir($kasirId, $bahanId, 'masuk', str_starts_with($ket, 'Paket') ? 'paket' : (str_starts_with($ket, 'Bahan pendukung') ? 'pendukung' : 'kirim'), $jumlah, $stok, $ket, null, now()->toDateString());
    }

    // ------------------------------------------------------------------
    // STOK DI KASIR (tampilan)
    // ------------------------------------------------------------------

    /**
     * Stok satu kasir dalam bentuk yang dipahami kasir:
     *  - pcs per ukuran (Slim / Reguler / Jumbo)
     *  - kali pakai filling / saus celup / bumbu tabur
     *  - pcs kemasan per ukuran, minyak & gas (manual)
     * Tiap item: kategori, nama, foto, jumlah, satuan, detail, status (aman|menipis|habis), persen, ikon.
     */
    public function stokKasirUntuk(int $userId)
    {
        $out = collect();

        // 1) PCS per ukuran
        $produk = DB::table('stok_kasir_produk')->where('user_id', $userId)->get()->keyBy('ukuran_id');
        foreach (DB::table('ukuran')->orderBy('harga')->get() as $u) {
            $r = $produk->get($u->id);
            $pcs = $r ? (int) $r->pcs : 0;
            $acuan = $r ? (int) $r->acuan : 0;
            $out->push((object) [
                'kategori' => 'produk', 'peran' => 'produk', 'nama' => $u->nama_ukuran, 'foto' => $u->foto ?? null,
                'ikon' => 'bi-egg-fried', 'jumlah' => $pcs, 'satuan' => 'pcs',
                'detail' => '',
                'status' => PerhitunganStok::statusStok($pcs, self::BATAS_MENIPIS),
                'persen' => $this->persenBar($pcs, $acuan),
            ]);
        }

        // 2) Bahan: filling / saus / bumbu (kali pakai), kemasan, minyak & gas (manual)
        $milik = DB::table('stok_kasir')->where('user_id', $userId)->get()->keyBy('bahan_baku_id');
        $nonaktif = collect()
            ->merge(DB::table('filling')->where('status', 'nonaktif')->pluck('bahan_baku_id'))
            ->merge(DB::table('saus_celup')->where('status', 'nonaktif')->pluck('bahan_baku_id'))
            ->merge(DB::table('bumbu_tabur')->where('status', 'nonaktif')->pluck('bahan_baku_id'))
            ->filter()->all();

        // Kemasan tampil per ukuran (urut harga), hanya ukuran aktif yang punya bahan kemasan
        $urutKemasan = DB::table('ukuran')->whereNotNull('bahan_baku_id')->where('status', 'aktif')->orderBy('harga')
            ->pluck('bahan_baku_id')->values()->flip()->all();

        $urutPeran = ['filling' => 1, 'saus' => 2, 'bumbu' => 3, 'kemasan' => 4, 'minyak' => 5, 'gas' => 6]; // minyak & gas: manual (dicatat kasir)
        $bahan = DB::table('bahan_baku')->whereIn('peran', array_keys($urutPeran))->orderBy('nama_bahan')->get()
            ->reject(fn ($b) => in_array($b->id, $nonaktif))
            ->reject(fn ($b) => $b->peran === 'kemasan' && $urutKemasan && ! isset($urutKemasan[$b->id]))
            ->sortBy(fn ($b) => $urutPeran[$b->peran] * 1000 + ($urutKemasan[$b->id] ?? 0))->values();

        foreach ($bahan as $b) {
            $r     = $milik->get($b->id);
            $stok  = $r ? (int) $r->stok : 0;
            $acuan = $r ? (int) $r->acuan : 0;
            $item  = ['peran' => $b->peran, 'nama' => $b->nama_bahan, 'foto' => $b->foto ?? null, 'jumlah' => $stok, 'detail' => ''];

            if ($this->bahanBerbungkus($b)) { // filling / saus / bumbu: kali pakai
                $item += [
                    'kategori' => 'porsi', 'ikon' => ['filling' => 'bi-egg-fried', 'saus' => 'bi-droplet-fill', 'bumbu' => 'bi-stars'][$b->peran],
                    'satuan' => 'kali pakai',
                ];
            } elseif ($b->peran === 'kemasan') {
                $item += ['kategori' => 'kemasan', 'ikon' => 'bi-box2', 'satuan' => 'pcs'];
            } else { // minyak & gas: stok dicatat manual (kasir mencatat pemakaian, admin mengirim lewat permintaan)
                $item += ['kategori' => 'manual', 'ikon' => $b->peran === 'gas' ? 'bi-fire' : 'bi-droplet-half', 'satuan' => $b->satuan];
            }

            // Minyak & gas: hanya aman / habis (minta ke admin kalau habis). Lainnya: menipis kalau sisa <= BATAS_MENIPIS
            $item['status'] = $item['kategori'] === 'manual'
                ? ($stok <= 0 ? 'habis' : 'aman')
                : PerhitunganStok::statusStok($stok, self::BATAS_MENIPIS);
            $item['persen'] = $this->persenBar($stok, $acuan);
            $out->push((object) $item);
        }

        return $out;
    }

    private function persenBar(int $sisa, int $acuan): int
    {
        if ($sisa <= 0) {
            return 0;
        }

        return $acuan > 0 ? max(4, min(100, intdiv($sisa * 100, $acuan))) : 100;
    }

    /** Jumlah item menipis / habis di kasir (untuk lencana di menu kasir). */
    public function ringkasKasir(int $userId): array
    {
        $rows = $this->stokKasirUntuk($userId);

        return [
            'menipis' => $rows->where('status', 'menipis')->count(),
            'habis'   => $rows->where('status', 'habis')->count(),
        ];
    }

    /** Stok di semua kasir (dipantau admin): tiap user punya ->bahan = daftar item di atas. */
    public function stokKasirSemua()
    {
        return DB::table('users')->where('role', 'kasir')->orderBy('name')->get()->map(function ($u) {
            $u->bahan = $this->stokKasirUntuk((int) $u->id);

            return $u;
        });
    }

    /** Riwayat pcs (paket masuk / penjualan keluar) satu kasir. */
    public function riwayatProduk(int $userId, int $batas = 15)
    {
        return DB::table('riwayat_stok_produk')
            ->join('ukuran', 'ukuran.id', '=', 'riwayat_stok_produk.ukuran_id')
            ->where('riwayat_stok_produk.user_id', $userId)
            ->orderByDesc('riwayat_stok_produk.id')->limit($batas)
            ->get(['riwayat_stok_produk.*', 'ukuran.nama_ukuran']);
    }

    private function tulisRiwayat(int $bahanId, int $jumlah, int $stokSesudah, array $k, string $rincian): void
    {
        DB::table('riwayat_stok')->insert([
            'bahan_baku_id' => $bahanId, 'jenis' => 'keluar', 'sumber' => $k['sumber'],
            'jumlah' => $jumlah, 'tanggal' => $k['tanggal'], 'user_id' => $k['user_id'], 'kasir_id' => $k['kasir_id'] ?? null,
            'keterangan' => $k['label'], 'transaksi_id' => $k['transaksi_id'],
            'rincian' => mb_substr($rincian, 0, 255), 'stok_sesudah' => $stokSesudah, 'created_at' => now(),
        ]);

        // Penjualan memotong stok KASIR: catat juga di riwayat bahan milik kasir (tampil di Stok Saya)
        if ($k['sumber'] === 'penjualan' && ! empty($k['kasir_id'])) {
            $this->tulisRiwayatKasir((int) $k['kasir_id'], $bahanId, 'keluar', 'penjualan', $jumlah, $stokSesudah,
                $k['label'], $rincian, $k['tanggal'], $k['transaksi_id'] ?? null);
        }
    }

    /** Riwayat stok bahan di tangan kasir (filling, saus, bumbu = kali pakai; kemasan, minyak, gas). */
    private function tulisRiwayatKasir(int $kasirId, int $bahanId, string $jenis, string $sumber, int $jumlah, int $stokSesudah,
        ?string $keterangan, ?string $rincian, string $tanggal, ?int $transaksiId = null): void
    {
        DB::table('riwayat_stok_kasir')->insert([
            'user_id' => $kasirId, 'bahan_baku_id' => $bahanId, 'jenis' => $jenis, 'sumber' => $sumber,
            'jumlah' => $jumlah, 'stok_sesudah' => $stokSesudah, 'transaksi_id' => $transaksiId,
            'keterangan' => $keterangan ? mb_substr($keterangan, 0, 255) : null,
            'rincian' => $rincian ? mb_substr($rincian, 0, 255) : null, 'tanggal' => $tanggal, 'created_at' => now(),
        ]);
    }

    /**
     * Riwayat bahan milik kasir per kelompok, untuk halaman Stok Saya.
     *
     * @return array<string, \Illuminate\Support\Collection> kunci: filling, saus, bumbu, kemasan, manual (minyak & gas)
     */
    public function riwayatBahanKasir(int $kasirId, int $batas = 15): array
    {
        $kelompok = ['filling' => ['filling'], 'saus' => ['saus'], 'bumbu' => ['bumbu'], 'kemasan' => ['kemasan'], 'manual' => ['minyak', 'gas']];
        $hasil = [];
        foreach ($kelompok as $kunci => $peran) {
            $hasil[$kunci] = DB::table('riwayat_stok_kasir as r')
                ->join('bahan_baku as b', 'b.id', '=', 'r.bahan_baku_id')
                ->where('r.user_id', $kasirId)->whereIn('b.peran', $peran)
                ->orderByDesc('r.id')->limit($batas)
                ->get(['r.*', 'b.nama_bahan', 'b.satuan', 'b.peran'])
                ->each(function ($r) {
                    if (in_array($r->peran, ['filling', 'saus', 'bumbu'], true)) {
                        $r->satuan = 'kali pakai';
                    }
                });
        }

        return $hasil;
    }

    public function segarkanSemuaStatus(): void
    {
        foreach (DB::table('bahan_baku')->get() as $b) {
            DB::table('bahan_baku')->where('id', $b->id)->update(['status_stok' => $this->statusBahan($b)]);
        }
    }

    // ------------------------------------------------------------------
    // Data awal & pemetaan bahan
    // ------------------------------------------------------------------

    /** Peran bahan utama + nama bawaan untuk mencarinya. */
    public const PERAN_UTAMA = [
        'ayam'          => ['Ayam', 'kg'],
        'tepung_premix_basah' => ['Tepung Premix Basah', 'botol'],   // 1 botol = 1 liter
        'tepung_roti'         => ['Tepung Roti', 'bungkus'],          // 1 bungkus = 1 kg
        'minyak'        => ['Minyak Goreng', 'jerigen'],   // 1 jerigen = 5 liter
        'gas'           => ['Gas LPG', 'tabung'],
    ];

    /** Kata kunci nama bahan untuk mengenali tiap bahan utama (tanpa perlu pengaturan manual). */
    private const KATA_KUNCI = [
        'ayam'          => ['ayam'],
        'tepung_premix_basah' => ['premix', 'basah'],
        'tepung_roti'         => ['roti', 'panir'],
        'minyak'        => ['minyak'],
        'gas'           => ['gas', 'lpg', 'elpiji'],
        'kemasan'       => ['kemasan', 'dus', 'box', 'kotak'],
    ];

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

    /**
     * Dipanggil otomatis sebelum halaman stok / paket / penjualan dipakai:
     * mengenali bahan utama dan menghubungkan filling, saus, bumbu ke bahan baku.
     * Murah kalau semua sudah siap (hanya beberapa query hitung).
     */
    public function siapkan(?int $adminId = null): void
    {
        $perluStandar = DB::table('standar_produksi')->count() < DB::table('ukuran')->count();
        if ($perluStandar || $this->pemetaanKurang()) {
            $this->isiDataAwal($adminId);
        }
    }

    /**
     * Mengisi data simulasi: pengaturan umum, standar per ukuran, peran bahan utama (+ Gas LPG),
     * lalu menghubungkan filling / saus / bumbu ke bahan baku. Aman dijalankan berulang
     * (angka yang sudah diubah admin TIDAK ditimpa).
     */
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

    /**
     * Hubungkan setiap filling / saus celup / bumbu tabur yang belum punya bahan baku:
     * cari bahan dengan nama yang cocok, kalau tidak ada dibuat baru (satuan bungkus, stok 0).
     *
     * @return int jumlah item yang berhasil dihubungkan
     */
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

    private function angka(int $n): string
    {
        return number_format($n, 0, ',', '.');
    }
}
