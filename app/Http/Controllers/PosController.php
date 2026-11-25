<?php

namespace App\Http\Controllers;

use App\Models\BumbuTabur;
use App\Models\Filling;
use App\Models\SausCelup;
use App\Models\Transaksi;
use App\Models\Ukuran;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Services\StokOtomatis\StokOtomatisService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PosController extends Controller
{
    /** Halaman Transaksi Penjualan (view: pos/index, memakai variabel $data) */
    public function index()
    {
        $foto = fn ($m) => !empty($m->foto) ? asset('storage/' . $m->foto) : null;

        // Sisa stok di tangan kasir yang sedang login (null = tidak dibatasi). Dipakai browser untuk mengunci menu yang habis.
        $stok = app(StokOtomatisService::class)->ketersediaanKasir((int) auth()->id());

        $data = [
            'ukuran' => Ukuran::where('status', 'aktif')->orderBy('harga')->get()->map(fn ($m) => [
                'id' => $m->id, 'nama' => $m->nama_ukuran ?? $m->nama, 'harga' => (int) $m->harga, 'foto' => $foto($m),
                'stok' => $stok['ukuran'][$m->id] ?? 0,
            ])->values(),
            'filling' => Filling::where('status', 'aktif')->orderBy('nama_filling')->get()->map(fn ($m) => [
                'id' => $m->id, 'nama' => $m->nama_filling, 'foto' => $foto($m),
                'stok' => $stok['filling'][$m->id] ?? null,
            ])->values(),
            'saus' => SausCelup::where('status', 'aktif')->orderBy('nama_saus')->get()->map(fn ($m) => [
                'id' => $m->id, 'nama' => $m->nama_saus, 'foto' => $foto($m),
                'stok' => $stok['saus'][$m->id] ?? null,
            ])->values(),
            'bumbu' => BumbuTabur::where('status', 'aktif')->orderBy('nama_bumbu')->get()->map(fn ($m) => [
                'id' => $m->id, 'nama' => $m->nama_bumbu, 'foto' => $foto($m),
                'stok' => $stok['bumbu'][$m->id] ?? null,
            ])->values(),
        ];

        // View memakai $data['ukuran']->isEmpty() dst, jadi kirim sebagai collection
        return view('kasir.transaksi_penjualan.index', ['data' => collect($data)->map(fn ($v) => collect($v))]);
    }

    /** Simpan transaksi: menerima JSON dari modal pembayaran, membalas JSON {redirect} */
    public function bayar(Request $request)
    {
        $v = $request->validate([
            'token'                   => 'required|string|max:64',
            'nama_pembeli'            => 'nullable|string|max:100',
            'metode_bayar'            => 'required|in:cash,qris',
            'uang_diterima'           => 'nullable|integer|min:0',
            'konfirmasi_bayar'        => 'nullable|boolean',
            'items'                   => 'required|array|min:1|max:50',
            // Menu yang dinonaktifkan admin tidak boleh dipesan
            'items.*.ukuran_id'       => ['required', 'integer', Rule::exists('ukuran', 'id')->where('status', 'aktif')],
            'items.*.filling_id'      => ['required', 'integer', Rule::exists('filling', 'id')->where('status', 'aktif')],
            'items.*.cara_luar'       => 'required|in:saus_celup,bumbu_tabur',
            'items.*.saus_celup_id'   => ['nullable', 'integer', Rule::exists('saus_celup', 'id')->where('status', 'aktif')],
            'items.*.bumbu_tabur_id'  => ['nullable', 'integer', Rule::exists('bumbu_tabur', 'id')->where('status', 'aktif')],
            'items.*.qty'             => 'required|integer|min:1|max:50',
        ]);

        // QRIS tidak boleh otomatis dianggap lunas: kasir wajib menegaskan sudah diterima.
        if ($v['metode_bayar'] !== 'cash' && empty($v['konfirmasi_bayar'])) {
            return response()->json(['message' => 'Konfirmasi dulu bahwa pembayaran ' . strtoupper($v['metode_bayar']) . ' sudah diterima.'], 422);
        }

        // Cegah simpan ganda (tombol ditekan berulang / request terkirim dua kali).
        // Token dibuat sekali per keranjang di browser; token yang sama hanya boleh diproses sekali.
        $kunci = 'pos_bayar:' . auth()->id() . ':' . $v['token'];
        if (! Cache::add($kunci, true, now()->addMinutes(30))) {
            $sudah = Cache::get($kunci . ':trx');
            if ($sudah) {
                return response()->json(['redirect' => route('pos.struk', $sudah)]);
            }
            return response()->json(['message' => 'Pembayaran ini sedang diproses. Tunggu sebentar.'], 409);
        }

        try {
            $trx = DB::transaction(function () use ($v) {
                $hargaUkuran = Ukuran::pluck('harga', 'id'); // harga SELALU dari database, bukan dari browser
                $total = 0;
                $baris = [];

                foreach ($v['items'] as $i) {
                    $celup  = $i['cara_luar'] === 'saus_celup';
                    $rasaId = $celup ? ($i['saus_celup_id'] ?? null) : ($i['bumbu_tabur_id'] ?? null);
                    abort_if(! $rasaId, 422, 'Rasa belum dipilih.');

                    $harga = (int) $hargaUkuran[$i['ukuran_id']];

                    // 1 baris item_pesanan = 1 porsi (qty dipecah jadi beberapa baris)
                    for ($n = 0; $n < $i['qty']; $n++) {
                        $total += $harga;
                        $baris[] = [
                            'ukuran_id'      => $i['ukuran_id'],
                            'filling_id'     => $i['filling_id'],
                            'cara_luar'      => $i['cara_luar'],
                            'saus_celup_id'  => $celup ? $rasaId : null,
                            'bumbu_tabur_id' => $celup ? null : $rasaId,
                            'harga_item'     => $harga,
                        ];
                    }
                }

                // Stok habis / tidak cukup -> transaksi DITOLAK (belum ada yang tersimpan, jadi tidak perlu dibatalkan).
                // Dicek di server juga, bukan hanya di layar, supaya tetap aman walau stok baru berubah atau halaman lama masih terbuka.
                app(StokOtomatisService::class)->pastikanStokCukup((int) auth()->id(), $baris);

                // Uang tunai yang diterima (kalau diisi) harus cukup
                if ($v['metode_bayar'] === 'cash' && isset($v['uang_diterima']) && $v['uang_diterima'] > 0 && $v['uang_diterima'] < $total) {
                    abort(422, 'Uang diterima kurang dari total pembayaran.');
                }

                // Uang diterima hanya dicatat untuk CASH (QRIS = null).
                // Kembalian tidak disimpan: dihitung di struk dari uang_diterima - total_harga.
                $uangDiterima = ($v['metode_bayar'] === 'cash' && ! empty($v['uang_diterima']))
                    ? (int) $v['uang_diterima']
                    : null;

                // forceFill: kolom uang_diterima ikut tersimpan walau belum ada di $fillable model Transaksi
                $trx = new Transaksi();
                $trx->forceFill([
                    'user_id'       => auth()->id(),
                    'nama_pembeli'  => $v['nama_pembeli'] ?: 'Umum',
                    'tanggal'       => now(),
                    'total_harga'   => $total,
                    'metode_bayar'  => $v['metode_bayar'],
                    'uang_diterima' => $uangDiterima,
                ])->save();

                $trx->items()->createMany($baris);

                return $trx;
            });
        } catch (\Throwable $e) {
            Cache::forget($kunci); // gagal: izinkan kasir mencoba lagi dengan token yang sama
            throw $e;
        }

        // Stok bahan baku berkurang otomatis sesuai ukuran, filling, dan topping pesanan.
        // Kalau perhitungan stok gagal, transaksi penjualan tetap sah (kesalahan hanya dicatat di log).
        try {
            $stok = app(StokOtomatisService::class)->kurangiDariTransaksi($trx->id);
            $pesan = array_merge($stok['peringatan'] ?? [], $stok['menipis'] ?? []);
            if ($pesan) {
                // Kasir langsung tahu kalau bahan di tangannya hampir / sudah habis
                session()->flash('warning', 'Stok: ' . implode('; ', array_slice($pesan, 0, 4)) . '. Minta tambahan ke admin.');
            }
        } catch (\Throwable $e) {
            report($e);
        }

        Cache::put($kunci . ':trx', $trx->id, now()->addMinutes(30));
        session()->flash('success', 'Transaksi berhasil disimpan.');

        return response()->json(['redirect' => route('pos.struk', $trx->id)]);
    }

    /**
     * Riwayat: HANYA transaksi milik kasir yang login (user_id dipaksa dari sesi, bukan dari request).
     * Default = hari ini. Filter: satu tanggal, rentang tanggal, metode pembayaran.
     */
    public function riwayat(Request $request)
    {
        $request->validate([
            'dari'   => 'nullable|date',
            'sampai' => 'nullable|date',
            'metode' => 'nullable|in:cash,qris',
        ]);

        // Tanpa filter tanggal -> hari ini. Kalau hanya salah satu diisi, pakai tanggal itu saja.
        $dari   = $request->filled('dari') ? Carbon::parse($request->dari) : ($request->filled('sampai') ? Carbon::parse($request->sampai) : today());
        $sampai = $request->filled('sampai') ? Carbon::parse($request->sampai) : $dari->copy();
        if ($dari->gt($sampai)) {
            [$dari, $sampai] = [$sampai, $dari];
        }
        $metode = $request->input('metode');

        $query = Transaksi::berhasil()
            ->where('user_id', auth()->id())
            ->antara($dari, $sampai)
            ->when($metode, fn ($q) => $q->where('metode_bayar', $metode));

        $totalPenjualan  = (int) (clone $query)->sum('total_harga');
        $jumlahTransaksi = (clone $query)->count();
        $jumlahPorsi     = (int) \App\Models\ItemPesanan::whereIn('transaksi_id', (clone $query)->select('id'))->count();

        $transaksi = $query
            ->withCount('items')
            ->latest('tanggal')->latest('id')
            ->paginate(15)->withQueryString();

        return view('kasir.riwayat_transaksi.index', [
            'transaksi'       => $transaksi,
            'dari'            => $dari->toDateString(),
            'sampai'          => $sampai->toDateString(),
            'metode'          => $metode,
            'hariIni'         => $dari->isToday() && $sampai->isToday(),
            'totalPenjualan'  => $totalPenjualan,
            'jumlahTransaksi' => $jumlahTransaksi,
            'jumlahPorsi'     => $jumlahPorsi,
        ]);
    }

    /**
     * Cetak SELURUH transaksi milik kasir yang sedang login, sesuai filter di halaman Riwayat
     * (tanggal & metode), tanpa dipotong per halaman.
     */
    public function cetakRiwayat(Request $request)
    {
        $request->validate([
            'dari'   => 'nullable|date',
            'sampai' => 'nullable|date',
            'metode' => 'nullable|in:cash,qris',
        ]);

        $dari   = $request->filled('dari') ? Carbon::parse($request->dari) : ($request->filled('sampai') ? Carbon::parse($request->sampai) : today());
        $sampai = $request->filled('sampai') ? Carbon::parse($request->sampai) : $dari->copy();
        if ($dari->gt($sampai)) {
            [$dari, $sampai] = [$sampai, $dari];
        }
        $metode = $request->input('metode');

        $transaksi = Transaksi::berhasil()
            ->where('user_id', auth()->id())
            ->antara($dari, $sampai)
            ->when($metode, fn ($q) => $q->where('metode_bayar', $metode))
            ->with(['items.ukuran', 'items.filling', 'items.sausCelup', 'items.bumbuTabur'])
            ->withCount('items')
            ->orderBy('tanggal')->orderBy('id')
            ->get();

        return view('kasir.riwayat_transaksi.cetak', [
            'transaksi' => $transaksi,
            'dari'      => $dari,
            'sampai'    => $sampai,
            'metode'    => $metode,
            'kasir'     => auth()->user()->name,
            'total'     => (int) $transaksi->sum('total_harga'),
            'totalCash' => (int) $transaksi->where('metode_bayar', 'cash')->sum('total_harga'),
            'totalQris' => (int) $transaksi->where('metode_bayar', 'qris')->sum('total_harga'),
            'porsi'     => (int) $transaksi->sum('items_count'),
        ]);
    }

    /** Struk: kasir hanya boleh membuka transaksinya sendiri (admin boleh semua) */
    public function struk(Transaksi $transaksi)
    {
        abort_unless(auth()->user()->role === 'admin' || $transaksi->user_id === auth()->id(), 403);

        $transaksi->load(['kasir', 'items.ukuran', 'items.filling', 'items.sausCelup', 'items.bumbuTabur']);

        return view('kasir.riwayat_transaksi.struk', compact('transaksi'));
    }
}
