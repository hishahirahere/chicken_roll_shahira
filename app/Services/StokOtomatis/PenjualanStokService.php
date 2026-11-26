<?php

namespace App\Services\StokOtomatis;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Potong stok KASIR otomatis saat transaksi + cek stok sebelum transaksi.
 */
class PenjualanStokService
{
    public function __construct(
        private PengaturanStokService $pengaturan,
        private DataAwalStokService $dataAwal,
        private StokKasirWriter $writer,
        private StokKasirQuery $query,
    ) {}

    public function kurangiDariTransaksi(int $transaksiId): array
    {
        $this->dataAwal->siapkan();
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

            $set   = $this->pengaturan->pengaturan();
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

            foreach ($this->query->stokKasirUntuk($kasirId) as $r) {
                if ($r->status !== 'aman') {
                    $menipis[] = $r->nama . ($r->status === 'habis' ? ' HABIS' : ' menipis (sisa ' . $this->pengaturan->angka($r->jumlah) . ' ' . $r->satuan . ')');
                }
            }
        });

        foreach ($peringatan as $p) {
            Log::warning('[Stok Otomatis] ' . $p);
        }

        return ['diproses' => (bool) ($diproses ?? false), 'peringatan' => $peringatan, 'menipis' => $menipis];
    }

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

        $kemasanPerProduk = max(1, (int) ($this->pengaturan->pengaturan()['kemasan_per_produk'] ?? 1));

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

    public function pastikanStokCukup(int $userId, array $items): void
    {
        $sisa  = $this->ketersediaanKasir($userId, true);
        $pakai = PerhitunganStok::pemakaianPenjualan($items, $this->pengaturan->pengaturan());

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

        $row    = $this->writer->barisStokKasir((int) $k['kasir_id'], (int) $bahan->id);
        $stok   = (int) $row->stok;
        $baru   = max(0, $stok - $jumlah);
        $nyata  = $stok - $baru;
        $kurang = $jumlah - $nyata;

        if ($kurang > 0) {
            $peringatan[] = "Stok {$bahan->nama_bahan} di kasir tidak cukup, kurang {$kurang} {$bahan->satuan}.";
            $rincian .= " · STOK KASIR KURANG {$kurang} {$bahan->satuan}";
        }

        DB::table('stok_kasir')->where('id', $row->id)->update(['stok' => $baru, 'updated_at' => now()]);
        $this->writer->tulisRiwayat((int) $bahan->id, $nyata, $baru, $k, $rincian);
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

        $row    = $this->writer->barisStokKasir((int) $k['kasir_id'], (int) $bahan->id);
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
        $this->writer->tulisRiwayat((int) $bahan->id, $nyata, $baru, $k, $rincian);
    }
}
