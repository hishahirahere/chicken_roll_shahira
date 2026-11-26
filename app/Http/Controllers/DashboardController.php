<?php

namespace App\Http\Controllers;

use App\Models\ItemPesanan;
use App\Models\Transaksi;
use App\Models\BahanBaku;
use Carbon\Carbon;
// use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $hariIni = Carbon::today();
        $kemarin = Carbon::yesterday();

        // Card 1: Penjualan Hari Ini
        $penjualanHariIni = Transaksi::whereDate('tanggal', $hariIni)->sum('total_harga');
        $penjualanKemarin = Transaksi::whereDate('tanggal', $kemarin)->sum('total_harga');
        $trendPenjualan = $penjualanKemarin > 0
            ? round((($penjualanHariIni - $penjualanKemarin) / $penjualanKemarin) * 100, 1)
            : 0;

        // Card 2: Jumlah Transaksi
        $transaksiHariIni = Transaksi::whereDate('tanggal', $hariIni)->count();
        $transaksiKemarin = Transaksi::whereDate('tanggal', $kemarin)->count();
        $trendTransaksi = $transaksiKemarin > 0
            ? round((($transaksiHariIni - $transaksiKemarin) / $transaksiKemarin) * 100, 1)
            : 0;

        // Card: Status Stok
        $stokAman = BahanBaku::where('status_stok', 'aman')->count();
        $stokMenipis = BahanBaku::where('status_stok', 'menipis')->count();
        $stokHabis = BahanBaku::where('status_stok', 'habis')->count();
        $totalStokItem = $stokAman + $stokMenipis + $stokHabis;

        // Card 3: Porsi Terjual Hari Ini
        // 1 baris item_pesanan = 1 porsi, jadi jumlah porsi = jumlah baris item pada transaksi hari itu.
        // Satu query untuk 7 hari (hasilnya juga dipakai sparkline kartu).
        $porsiPerHari = ItemPesanan::query()
            ->join('transaksi', 'transaksi.id', '=', 'item_pesanan.transaksi_id')
            ->whereDate('transaksi.tanggal', '>=', Carbon::today()->subDays(6))
            ->selectRaw('DATE(transaksi.tanggal) as hari, COUNT(*) as total')
            ->groupBy('hari')
            ->pluck('total', 'hari');
        $dataPorsi = [];
        for ($i = 6; $i >= 0; $i--) {
            $dataPorsi[] = (int) ($porsiPerHari[Carbon::today()->subDays($i)->toDateString()] ?? 0);
        }
        $porsiHariIni = $dataPorsi[6];
        $porsiKemarin = $dataPorsi[5];
        $trendPorsi = $porsiKemarin > 0
            ? round((($porsiHariIni - $porsiKemarin) / $porsiKemarin) * 100, 1)
            : 0;

        // Chart: Penjualan 7 Hari Terakhir
        $labelHari = [];
        $dataPenjualan = [];
        $dataTransaksi = [];
        for ($i = 6; $i >= 0; $i--) {
            $tanggal = Carbon::today()->subDays($i);
            $labelHari[] = $tanggal->translatedFormat('D d M');
            $dataPenjualan[] = Transaksi::whereDate('tanggal', $tanggal)->sum('total_harga');
            $dataTransaksi[] = Transaksi::whereDate('tanggal', $tanggal)->count();
        }

        // Peringatan Stok (yang menipis/habis, urut paling kritis dulu)
        $peringatanStok = BahanBaku::whereIn('status_stok', ['menipis', 'habis'])
            ->orderByRaw("CASE status_stok WHEN 'habis' THEN 0 ELSE 1 END")
            ->orderBy('nama_bahan')
            ->get(); // semua ditampilkan; kartunya yang bisa di-scroll kalau kepanjangan

        // Transaksi Hari Ini (harian: hari berganti = daftar kosong lagi)
        // Ditampilkan di area yang bisa di-scroll; transaksi hari-hari sebelumnya
        // dilihat lewat tombol "Lihat Semua" ke Riwayat Transaksi.
        $transaksiTerbaru = Transaksi::with('kasir')
            ->whereDate('tanggal', $hariIni)
            ->latest('tanggal')->latest('id')
            ->get();

        // Menu Favorit Pelanggan (dari item_pesanan = transaksi yang benar-benar terjadi)
        $favorit = $this->favorit();

        return view('admin.dashboard', compact(
            'penjualanHariIni', 'trendPenjualan',
            'transaksiHariIni', 'trendTransaksi',
            'stokAman', 'stokMenipis', 'stokHabis', 'totalStokItem',
            'labelHari', 'dataPenjualan', 'dataTransaksi',
            'porsiHariIni', 'trendPorsi', 'dataPorsi',
            'peringatanStok', 'transaksiTerbaru', 'favorit'
        ));
    }

    private function favorit(): array
    {
        $foto = fn ($f) => $f ? asset('storage/' . $f) : null;

        // Satu query: jumlah porsi per kombinasi (ukuran, filling, topping luar).
        // Semua tab dihitung dari data ini di memori.
        $rows = ItemPesanan::query()
            ->join('ukuran', 'ukuran.id', '=', 'item_pesanan.ukuran_id')
            ->join('filling', 'filling.id', '=', 'item_pesanan.filling_id')
            ->leftJoin('saus_celup', 'saus_celup.id', '=', 'item_pesanan.saus_celup_id')
            ->leftJoin('bumbu_tabur', 'bumbu_tabur.id', '=', 'item_pesanan.bumbu_tabur_id')
            ->selectRaw('
                item_pesanan.ukuran_id, ukuran.nama_ukuran, ukuran.foto as foto_ukuran,
                item_pesanan.filling_id, filling.nama_filling, filling.foto as foto_filling,
                item_pesanan.cara_luar,
                item_pesanan.saus_celup_id, saus_celup.nama_saus, saus_celup.foto as foto_saus,
                item_pesanan.bumbu_tabur_id, bumbu_tabur.nama_bumbu, bumbu_tabur.foto as foto_bumbu,
                COUNT(*) as total
            ')
            ->groupBy(
                'item_pesanan.ukuran_id', 'ukuran.nama_ukuran', 'ukuran.foto',
                'item_pesanan.filling_id', 'filling.nama_filling', 'filling.foto',
                'item_pesanan.cara_luar',
                'item_pesanan.saus_celup_id', 'saus_celup.nama_saus', 'saus_celup.foto',
                'item_pesanan.bumbu_tabur_id', 'bumbu_tabur.nama_bumbu', 'bumbu_tabur.foto'
            )
            ->get()
            ->map(function ($r) use ($foto) {
                $saus = $r->cara_luar === 'saus_celup';
                $nama = $saus ? $r->nama_saus : $r->nama_bumbu;
                $tipe = $saus ? 'Saus Celup' : 'Bubuk Tabur';

                return [
                    'ukuran_id'     => $r->ukuran_id,
                    'ukuran'        => $r->nama_ukuran,
                    'ukuran_foto'   => $foto($r->foto_ukuran),
                    'filling_id'    => $r->filling_id,
                    'filling'       => $r->nama_filling,
                    'filling_foto'  => $foto($r->foto_filling),
                    'topping_key'   => $r->cara_luar . ':' . ($saus ? $r->saus_celup_id : $r->bumbu_tabur_id),
                    'topping'       => $nama,
                    'topping_tipe'  => $tipe,
                    'topping_label' => $nama . ' (' . $tipe . ')',
                    'topping_foto'  => $foto($saus ? $r->foto_saus : $r->foto_bumbu),
                    'total'         => (int) $r->total,
                ];
            });

        // "Nama (12x)" untuk yang paling banyak di dalam satu kelompok
        $terlaris = function ($grup, string $field): string {
            $t = $grup->groupBy($field)->map(fn ($x) => $x->sum('total'))->sortDesc();

            return $t->isEmpty() ? '-' : $t->keys()->first() . ' (' . $t->first() . 'x)';
        };

        // Urutkan terlaris, ambil 5
        $top5 = fn ($koleksi) => $koleksi
            ->sortBy([['total', 'desc'], ['nama', 'asc']])
            ->take(5)->values()->all();

        // ---- Kombo: Filling + Topping Luar ----
        $kombo = $rows
            ->groupBy(fn ($r) => $r['filling_id'] . '|' . $r['topping_key'])
            ->map(function ($g) {
                $f = $g->first();
                $perUkuran = $g->groupBy('ukuran')->map(fn ($x) => $x->sum('total'))->sortDesc();
                $ukuranTop = $g->sortByDesc('total')->first();

                return [
                    'nama'   => $f['filling'] . ' + ' . $f['topping'],
                    'sub'    => $f['topping_tipe'],
                    'foto'   => $ukuranTop['ukuran_foto'] ?? $f['filling_foto'] ?? $f['topping_foto'],
                    'total'  => $g->sum('total'),
                    'detail' => [
                        'Filling'        => $f['filling'],
                        'Topping Luar'   => $f['topping_label'],
                        'Ukuran dipesan' => $perUkuran->map(fn ($n, $k) => $k . ' ' . $n . 'x')->implode(', '),
                    ],
                ];
            });

        // ---- Ukuran ----
        $ukuran = $rows->groupBy('ukuran_id')->map(fn ($g) => [
            'nama'   => $g->first()['ukuran'],
            'sub'    => null,
            'foto'   => $g->first()['ukuran_foto'],
            'total'  => $g->sum('total'),
            'detail' => [
                'Filling terlaris'      => $terlaris($g, 'filling'),
                'Topping luar terlaris' => $terlaris($g, 'topping_label'),
            ],
        ]);

        // ---- Filling ----
        $filling = $rows->groupBy('filling_id')->map(fn ($g) => [
            'nama'   => $g->first()['filling'],
            'sub'    => null,
            'foto'   => $g->first()['filling_foto'],
            'total'  => $g->sum('total'),
            'detail' => [
                'Ukuran terlaris'       => $terlaris($g, 'ukuran'),
                'Topping luar terlaris' => $terlaris($g, 'topping_label'),
            ],
        ]);

        // ---- Topping Luar (saus celup + bubuk tabur digabung) ----
        $topping = $rows->groupBy('topping_key')->map(fn ($g) => [
            'nama'   => $g->first()['topping'],
            'sub'    => $g->first()['topping_tipe'],
            'foto'   => $g->first()['topping_foto'],
            'total'  => $g->sum('total'),
            'detail' => [
                'Filling terlaris' => $terlaris($g, 'filling'),
                'Ukuran terlaris'  => $terlaris($g, 'ukuran'),
            ],
        ]);

        return [
            'kombo'   => $top5($kombo),
            'ukuran'  => $top5($ukuran),
            'filling' => $top5($filling),
            'topping' => $top5($topping),
        ];
    }
}
