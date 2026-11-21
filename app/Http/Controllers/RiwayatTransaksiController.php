<?php

namespace App\Http\Controllers;

use App\Models\Transaksi;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class RiwayatTransaksiController extends Controller
{
    /**
     * Riwayat semua transaksi dari seluruh kasir (khusus admin, dijaga middleware role:admin di route).
     * Default = hari ini. Filter: tanggal / rentang tanggal, kasir, metode bayar, nomor transaksi / nama pembeli.
     */
    public function index(Request $request)
    {
        $request->validate([
            'q'       => 'nullable|string|max:60',
            'dari'    => 'nullable|date',
            'sampai'  => 'nullable|date',
            'metode'  => 'nullable|in:cash,qris',
            'kasir'   => 'nullable|integer',
        ]);

        // Tanpa filter tanggal -> hari ini. Kalau hanya satu tanggal diisi, pakai tanggal itu saja.
        $dari   = $request->filled('dari') ? Carbon::parse($request->dari) : ($request->filled('sampai') ? Carbon::parse($request->sampai) : today());
        $sampai = $request->filled('sampai') ? Carbon::parse($request->sampai) : $dari->copy();
        if ($dari->gt($sampai)) {
            [$dari, $sampai] = [$sampai, $dari];
        }

        $q = trim((string) $request->input('q', ''));

        $query = Transaksi::berhasil()
            // items + relasinya ikut diambil supaya modal detail tidak perlu query lagi
            ->with(['kasir', 'items.ukuran', 'items.filling', 'items.sausCelup', 'items.bumbuTabur'])
            ->withCount('items')
            ->antara($dari, $sampai)
            ->when($q !== '', function ($w) use ($q) {
                // "TRX-00012", "00012" atau "12" -> cari nomor transaksi; selain itu cari nama pembeli
                if (preg_match('/^(trx-?)?\d+$/i', $q)) {
                    $w->where('id', (int) preg_replace('/\D/', '', $q));
                } else {
                    $w->where('nama_pembeli', 'like', '%' . $q . '%');
                }
            })
            ->when($request->filled('metode'), fn ($w) => $w->where('metode_bayar', $request->metode))
            ->when($request->filled('kasir'), fn ($w) => $w->where('user_id', $request->kasir));

        // Ringkasan mengikuti filter yang sedang dipakai
        $ringkasan = [
            'jumlah' => (clone $query)->count(),
            'total'  => (int) (clone $query)->sum('total_harga'),
        ];

        $transaksi = $query->orderByDesc('tanggal')->orderByDesc('id')->paginate(15)->withQueryString();

        $kasirList = User::where('role', 'kasir')->orderBy('name')->get(['id', 'name']);

        return view('admin.operasional.riwayat_transaksi.index', [
            'transaksi' => $transaksi,
            'ringkasan' => $ringkasan,
            'kasirList' => $kasirList,
            'dari'      => $dari->toDateString(),
            'sampai'    => $sampai->toDateString(),
            'hariIni'   => $dari->isToday() && $sampai->isToday(),
        ]);
    }

    public function show(Transaksi $transaksi)
    {
        $transaksi->load(['kasir', 'items.ukuran', 'items.filling', 'items.sausCelup', 'items.bumbuTabur']);

        return view('admin.operasional.riwayat_transaksi.show', compact('transaksi'));
    }
}
