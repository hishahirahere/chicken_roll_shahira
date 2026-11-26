<?php

namespace App\Services\StokOtomatis;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PaketKasirService
{
    public function __construct(
        private PengaturanStokService $pengaturan,
        private DataAwalStokService $dataAwal,
        private StokKasirWriter $writer,
    ) {}

    public function kgPerPaket(?array $set = null): int
    {
        $set = $set ?? $this->pengaturan->pengaturan();
        $jumlahUkuran = DB::table('ukuran')->where('status', 'aktif')->count() ?: DB::table('ukuran')->count();

        return max(1, (int) $set['paket_kg_per_ukuran'] * max(1, $jumlahUkuran));
    }

    public function beriPaketAyam(int $kasirId, string $tanggal, array $kg, int $adminId, ?string $catatan = null): array
    {
        $this->dataAwal->siapkan($adminId);
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
        $set     = $this->pengaturan->pengaturan();
        $standar = $this->pengaturan->standar();
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

    public function beriPaket(int $kasirId, string $tanggal, array $kg, array $bungkus, array $kemasan, int $adminId, ?string $catatan = null, array $tepung = [], string $jenis = 'ayam'): array
    {
        $pendukung = $jenis === 'pendukung';
        $this->dataAwal->siapkan($adminId);
        $set     = $this->pengaturan->pengaturan();
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

            $hitung = PerhitunganStok::hitungProduksi($alokasi, $this->pengaturan->standar());
            $namaUkuran = DB::table('ukuran')->pluck('nama_ukuran', 'id');
            $rincianAyam = collect($hitung['baris'])
                ->map(fn ($b, $id) => $this->pengaturan->angka($b['kg_ayam']) . ' kg ' . ($namaUkuran[$id] ?? 'ukuran') . ' = ' . $b['est_pcs'] . ' pcs')
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
                    $galat[] = 'Bahan "' . (DataAwalStokService::PERAN_UTAMA[$peran][0] ?? $peran) . '" belum tersedia di data bahan baku. Jalankan seeder PengaturanStokSeeder.';

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
                if (! $b || ! $this->pengaturan->bahanBerbungkus($b)) {
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
                    $galat[] = 'Stok ' . $b->nama_bahan . ' di dapur hanya ' . $this->pengaturan->angka((int) $b->stok_saat_ini) . ' ' . $b->satuan
                        . ', paket butuh ' . $this->pengaturan->angka($jumlah) . ' ' . $b->satuan . '.';
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
                    'stok_saat_ini' => $baru, 'status_stok' => $this->pengaturan->statusBahan($b), 'updated_at' => now(),
                ]);
                $this->writer->tulisRiwayat((int) $bahanId, $jumlah, $baru, $konteks, $rincian);
            }

            // --- tambah stok kasir ---
            foreach ($hitung['baris'] as $ukuranId => $r) {
                if ($r['est_pcs'] > 0) {
                    $this->writer->tambahProduk($kasirId, (int) $ukuranId, (int) $r['est_pcs'], $tanggal, 'Paket dari admin · ' . $this->pengaturan->angka($r['kg_ayam']) . ' kg ayam');
                }
            }
            foreach ($bahanBungkus as $bahanId => $n) {
                $this->writer->tambahStokKasirBahan($kasirId, (int) $bahanId, (int) $n, $set, $pendukung ? 'Bahan pendukung dari admin' : 'Paket dari admin');
            }
            // Kemasan (per ukuran) masuk ke stok kasir
            foreach ($kemasanBahan as $bahanId => $n) {
                $this->writer->tambahStokKasirBahan($kasirId, (int) $bahanId, (int) $n, $set);
            }

            return [
                'kasir' => $kasir->name, 'pcs' => $hitung['total']['pcs'], 'ayam' => $hitung['total']['ayam'], 'baris' => $hitung['baris'],
                'premix_basah' => (int) ($tepungBungkus['tepung_premix_basah'] ?? 0), 'tepung_roti' => (int) ($tepungBungkus['tepung_roti'] ?? 0),
            ];
        });
    }
}
