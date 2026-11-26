<?php

namespace App\Services\StokOtomatis;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Tampilan stok di tangan kasir + riwayatnya (hanya membaca).
 */
class StokKasirQuery
{
    public function __construct(
        private PengaturanStokService $pengaturan,
    ) {}

    public const BATAS_MENIPIS = 10;

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

            if ($this->pengaturan->bahanBerbungkus($b)) { // filling / saus / bumbu: kali pakai
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
}
