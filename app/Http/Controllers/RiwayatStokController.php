<?php

namespace App\Http\Controllers;

use App\Models\BahanBaku;
use App\Models\RiwayatStok;
use App\Services\StokOtomatis\StokOtomatisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RiwayatStokController extends Controller
{
    /** Jumlah "kejadian" (paket / transaksi / catatan manual) per halaman. */
    private const PER_HALAMAN = 15;

    /**
     * Riwayat stok masuk & keluar, dirapikan per KEJADIAN, bukan per baris bahan:
     *   - 1 paket ke kasir          = 1 kejadian (isinya ayam, tepung, minyak, filling, ... )
     *   - 1 stok masuk/keluar manual = 1 kejadian
     *   - kirim minyak & gas ke kasir = 1 baris per bahan (sumber 'kirim')
     *
     * Riwayat sengaja tidak bisa diedit/dihapus supaya data stok tetap bisa diaudit.
     */
    public function index(Request $request, StokOtomatisService $stok)
    {
        $request->validate([
            'jenis'         => 'nullable|in:masuk,keluar',
            'sumber'        => 'nullable|in:manual,paket,kirim,produksi',
            'bahan_baku_id' => 'nullable|integer',
            'dari'          => 'nullable|date',
            'sampai'        => 'nullable|date',
        ]);

        // Riwayat ini khusus stok GUDANG (bahan baku). Pemakaian karena penjualan tidak ditampilkan di sini:
        // sisa stoknya dilihat di menu Stok Kasir, dan riwayat pcs-nya ada di halaman Stok kasir.
        $saring = fn ($q) => $q
            ->where(fn ($w) => $w->whereNull('sumber')->orWhere('sumber', '!=', 'penjualan'))
            ->when($request->filled('jenis'), fn ($q) => $q->where('jenis', $request->jenis))
            ->when($request->filled('sumber'), fn ($q) => $q->where('sumber', $request->sumber))
            ->when($request->filled('bahan_baku_id'), fn ($q) => $q->where('bahan_baku_id', $request->bahan_baku_id))
            ->when($request->filled('dari'), fn ($q) => $q->whereDate('tanggal', '>=', $request->dari))
            ->when($request->filled('sampai'), fn ($q) => $q->whereDate('tanggal', '<=', $request->sampai));

        $grup = $this->ekspresiGrup();

        // 1) Daftar kejadian (dipaginasi per kejadian, jadi satu paket tidak terpotong di dua halaman)
        $kejadian = DB::query()
            ->fromSub(
                $saring(RiwayatStok::query())->selectRaw("riwayat_stok.id, riwayat_stok.tanggal, {$grup} as grup"),
                'x'
            )
            ->selectRaw('grup, MAX(tanggal) as tgl, MAX(id) as id_terakhir')
            ->groupBy('grup')
            ->orderByDesc('tgl')
            ->orderByDesc('id_terakhir')
            ->paginate(self::PER_HALAMAN)
            ->withQueryString();

        // 2) Isi tiap kejadian
        $kunci = $kejadian->getCollection()->pluck('grup')->all();
        $baris = collect();
        if ($kunci) {
            $baris = $saring(RiwayatStok::with(['bahan', 'kasir', 'admin']))
                ->selectRaw("riwayat_stok.*, {$grup} as grup")
                ->whereRaw("({$grup}) in (" . implode(',', array_fill(0, count($kunci), '?')) . ')', $kunci)
                ->orderBy('id')
                ->get()
                ->groupBy('grup');
        }

        $set = $stok->pengaturan();
        $entri = $kejadian->getCollection()->map(function ($g) use ($baris, $stok, $set) {
            $isi = ($baris->get($g->grup) ?? collect())->each(fn ($r) => $this->siapkanTampilan($r, $stok, $set))->values();

            return (object) [
                'kunci'  => $g->grup,
                'sumber' => $isi->first()->sumber ?: 'manual',
                'jenis'  => $isi->first()->jenis,
                'isi'    => $isi,
                'pertama' => $isi->first(),
            ];
        })->filter(fn ($e) => $e->isi->isNotEmpty())->values();

        $kejadian->setCollection($entri);

        $totalCatatan = $saring(RiwayatStok::query())->count();
        $bahanList = BahanBaku::orderBy('nama_bahan')->get(['id', 'nama_bahan']);

        return view('admin.operasional.riwayat_stok.index', [
            'kejadian'     => $kejadian,
            'bahanList'    => $bahanList,
            'totalCatatan' => $totalCatatan,
        ]);
    }

    /**
     * Pengelompokan kejadian (satu ekspresi SQL, dipakai di kedua query).
     * Baris paket yang dibuat dalam satu pemberian punya created_at & kasir yang sama.
     */
    private function ekspresiGrup(): string
    {
        $sqlite = DB::connection()->getDriverName() === 'sqlite';
        $gabung = fn (string ...$bagian) => $sqlite ? '(' . implode(' || ', $bagian) . ')' : 'CONCAT(' . implode(', ', $bagian) . ')';

        return 'CASE'
            . " WHEN riwayat_stok.sumber = 'paket' THEN " . $gabung("'paket|'", 'riwayat_stok.created_at', "'|'", 'COALESCE(riwayat_stok.kasir_id, 0)', "'|'", 'COALESCE(riwayat_stok.user_id, 0)')
            . " WHEN riwayat_stok.sumber = 'penjualan' AND riwayat_stok.transaksi_id IS NOT NULL THEN " . $gabung("'trx|'", 'riwayat_stok.transaksi_id')
            . ' ELSE ' . $gabung("'baris|'", 'riwayat_stok.id')
            . ' END';
    }

    /**
     * Angka yang tampil di layar. Aturannya:
     *  - Penjualan bahan berbungkus (filling / saus / bumbu) ditulis dalam PORSI, bukan pecahan bungkus
     *    (tadinya "0,0833 bungkus"; sekarang "1 porsi").
     *  - Selain itu angka dibulatkan maksimal 3 desimal dan nol di belakang koma dibuang.
     *  - Penjualan tidak mengubah stok gudang, jadi "stok sesudah"-nya adalah sisa di KASIR.
     */
    private function siapkanTampilan(RiwayatStok $r, StokOtomatisService $stok, array $set): void
    {
        $bahan  = $r->bahan;
        $satuan = $bahan->satuan ?? '';
        $jumlah = (float) $r->jumlah;
        $penjualan = $r->sumber === 'penjualan';

        $r->t_jumlah = self::angka($jumlah) . ' ' . $satuan;

        if ($bahan && $penjualan && $stok->bahanBerbungkus($bahan)) {
            $kap = $stok->kapasitasBungkus($bahan, $set);
            if ($kap > 0) {
                $r->t_jumlah = self::angka(round($jumlah * $kap, 2)) . ' porsi';
            }
        }

        $r->t_sisa_label = $penjualan ? 'Sisa di kasir' : 'Stok gudang';
        $r->t_sisa = $r->stok_sesudah === null ? null : self::angka((float) $r->stok_sesudah) . ' ' . $satuan;
    }

    /** 1.35 -> "1,35" | 12 -> "12" | 1000 -> "1.000" | 0.0833333 -> "0,083" */
    private static function angka(float $v): string
    {
        return rtrim(rtrim(number_format($v, 3, ',', '.'), '0'), ',');
    }
}
