<?php

namespace App\Http\Controllers;

use App\Services\StokOtomatis\StokOtomatisService;


class StokKasirAdminController extends Controller
{
    public function index(StokOtomatisService $stok)
    {
        $daftarKasir = $stok->stokKasirSemua()->map(function ($kasir) use ($stok) {
            $kasir->ringkas = [
                'aman'    => $kasir->bahan->where('status', 'aman')->count(),
                'menipis' => $kasir->bahan->where('status', 'menipis')->count(),
                'habis'   => $kasir->bahan->where('status', 'habis')->count(),
            ];
            $kasir->riwayat = $stok->riwayatProduk((int) $kasir->id, 8);

            return $kasir;
        });

        return view('admin.operasional.stok_kasir.index', compact('daftarKasir'));
    }
}
