<?php

namespace App\Http\Controllers;

use App\Models\ItemPesanan;
use App\Models\Transaksi;
use Carbon\Carbon;

class DashboardKasirController extends Controller
{
    public function index()
    {
        $uid     = auth()->id();
        $hariIni = Carbon::today();
        $kemarin = Carbon::yesterday();
        $awal    = $hariIni->copy()->startOfDay();
        $akhir   = $hariIni->copy()->endOfDay();

        // Semua angka dashboard = transaksi milik kasir yang login
        $trx  = fn () => Transaksi::berhasil()->where('user_id', $uid);
        $item = fn () => ItemPesanan::query()
            ->join('transaksi', 'transaksi.id', '=', 'item_pesanan.transaksi_id')
            ->where('transaksi.user_id', $uid);

        $penjualanHariIni = (int) $trx()->antara($hariIni, $hariIni)->sum('total_harga');
        $penjualanKemarin = (int) $trx()->antara($kemarin, $kemarin)->sum('total_harga');
        $transaksiHariIni = $trx()->antara($hariIni, $hariIni)->count();
        $transaksiKemarin = $trx()->antara($kemarin, $kemarin)->count();

        $persen = fn ($now, $prev) => $prev > 0 ? round(($now - $prev) / $prev * 100) : ($now > 0 ? 100 : 0);

        // 1 baris item_pesanan = 1 porsi, jadi count(*) = jumlah porsi terjual
        $itemHariIni = $item()->whereBetween('transaksi.tanggal', [$awal, $akhir])->count();

        // Deret 7 hari (termasuk hari ini): 2 query saja
        $mulai   = Carbon::today()->subDays(6);
        $perHari = $trx()->antara($mulai, $hariIni)
            ->selectRaw('DATE(tanggal) as tgl, COUNT(*) as n, SUM(total_harga) as total')
            ->groupBy('tgl')->get()->keyBy('tgl');
        $itemPerHari = $item()->whereBetween('transaksi.tanggal', [$mulai->copy()->startOfDay(), $akhir])
            ->selectRaw('DATE(transaksi.tanggal) as tgl, COUNT(*) as n')
            ->groupBy('tgl')->pluck('n', 'tgl');

        $labelHari = $dataPenjualan = $dataTransaksi = $dataItem = [];
        for ($i = 6; $i >= 0; $i--) {
            $tgl = Carbon::today()->subDays($i);
            $key = $tgl->toDateString();
            $labelHari[]     = $tgl->translatedFormat('D, d M');
            $dataPenjualan[] = (int) ($perHari[$key]->total ?? 0);
            $dataTransaksi[] = (int) ($perHari[$key]->n ?? 0);
            $dataItem[]      = (int) ($itemPerHari[$key] ?? 0);
        }

        // Varian terlaris hari ini = kombinasi Ukuran + Filling, lengkap dengan foto dari Data Master.
        // 1 baris item_pesanan = 1 porsi, jadi COUNT(*) = jumlah porsi terjual.
        $varianTerlaris = $item()->whereBetween('transaksi.tanggal', [$awal, $akhir])
            ->join('ukuran', 'ukuran.id', '=', 'item_pesanan.ukuran_id')
            ->join('filling', 'filling.id', '=', 'item_pesanan.filling_id')
            ->selectRaw('ukuran.nama_ukuran, ukuran.foto as foto_ukuran, filling.nama_filling, filling.foto as foto_filling, COUNT(*) as total')
            ->groupBy('ukuran.id', 'ukuran.nama_ukuran', 'ukuran.foto', 'filling.id', 'filling.nama_filling', 'filling.foto')
            ->orderByDesc('total')->orderBy('ukuran.nama_ukuran')
            ->take(5)->get()
            ->map(function ($v) {
                $v->foto = $v->foto_ukuran ?: $v->foto_filling; // foto produk (ukuran) dulu, kalau kosong pakai foto filling
                return $v;
            });

        return view('kasir.dashboard_kasir', [
            'penjualanHariIni' => $penjualanHariIni,
            'trendPenjualan'   => $persen($penjualanHariIni, $penjualanKemarin),
            'transaksiHariIni' => $transaksiHariIni,
            'trendTransaksi'   => $persen($transaksiHariIni, $transaksiKemarin),
            'itemTerjual'      => $itemHariIni,
            'rataRata'         => $transaksiHariIni ? intdiv($penjualanHariIni, $transaksiHariIni) : 0,
            'labelHari'        => $labelHari,
            'dataPenjualan'    => $dataPenjualan,
            'dataTransaksi'    => $dataTransaksi,
            'dataItem'         => $dataItem,
            'metodeBayar'      => $trx()->antara($hariIni, $hariIni)
                                    ->selectRaw('metode_bayar, COUNT(*) as n')->groupBy('metode_bayar')
                                    ->pluck('n', 'metode_bayar')->all(),
            'transaksiTerbaru' => $trx()->antara($hariIni, $hariIni)->latest('tanggal')->take(6)->get(),
            'varianTerlaris'   => $varianTerlaris,
        ]);
    }
}
