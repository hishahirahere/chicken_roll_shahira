<?php

namespace App\Http\Controllers;

use App\Services\StokOtomatis\MinyakGasService;
use App\Services\StokOtomatis\StokKasirQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Stok Saya": stok yang sedang dipegang kasir, dalam bentuk pcs per ukuran dan kali pakai.
 * Produk/bahan bertambah saat admin memberi paket dan berkurang otomatis tiap pembayaran.
 * Minyak & gas manual: kasir mencatat pemakaian, minta ke admin, lalu konfirmasi saat barang diterima.
 */
class StokKasirController extends Controller
{
    public function index(StokKasirQuery $query, MinyakGasService $minyakGas)
    {
        $id = (int) auth()->id();
        $item = $query->stokKasirUntuk($id);

        return view('kasir.stok.index', [
            'item'    => $item,
            'porsi'   => $minyakGas->porsiKirim(),
            'ringkas' => [
                'aman'    => $item->where('status', 'aman')->count(),
                'menipis' => $item->where('status', 'menipis')->count(),
                'habis'   => $item->where('status', 'habis')->count(),
            ],
            'riwayat' => $query->riwayatProduk($id, 60),
            'riwayatBahan' => $query->riwayatBahanKasir($id, 60),
            'manual'  => $item->where('kategori', 'manual')->keyBy('peran'),
            'permintaanAktif' => DB::table('permintaan_stok as p')->join('bahan_baku as b', 'b.id', '=', 'p.bahan_baku_id')
                ->where('p.kasir_id', $id)->whereIn('p.status', ['diminta', 'dikirim'])
                ->select('p.*', 'b.nama_bahan', 'b.satuan', 'b.peran')->orderBy('p.created_at')->get(),
            'permintaanRiwayat' => DB::table('permintaan_stok as p')->join('bahan_baku as b', 'b.id', '=', 'p.bahan_baku_id')
                ->where('p.kasir_id', $id)->whereIn('p.status', ['selesai', 'batal'])
                ->select('p.*', 'b.nama_bahan', 'b.satuan')->orderByDesc('p.updated_at')->limit(5)->get(),
        ]);
    }

    /** Kasir mencatat pemakaian manual hari ini (minyak / gas). */
    public function catatPemakaian(Request $request, MinyakGasService $minyakGas)
    {
        $this->khususKasir();
        $v = $request->validate([
            'minyak'  => 'nullable|integer|min:0|max:9999',
            'gas'     => 'nullable|integer|min:0|max:9999',
            'catatan' => 'nullable|string|max:150',
        ]);

        try {
            $hasil = $minyakGas->catatPemakaian((int) auth()->id(), ['minyak' => $v['minyak'] ?? 0, 'gas' => $v['gas'] ?? 0], now()->toDateString(), $v['catatan'] ?? null);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with('success', 'Pemakaian dicatat: ' . implode(' + ', $hasil['dicatat']) . '.')
            ->with('peringatan', implode(' ', $hasil['peringatan']));
    }

    /** Kasir minta minyak / gas ke admin. */
    public function minta(Request $request, MinyakGasService $minyakGas)
    {
        $this->khususKasir();
        $v = $request->validate([
            'bahan'          => 'required|array|min:1',
            'bahan.*'        => 'in:minyak,gas',
            'catatan'        => 'nullable|string|max:150',
        ], ['bahan.required' => 'Pilih minyak atau gas yang diminta.']);

        // Jumlah tidak bisa dipilih kasir: selalu porsi tetap dari Pengaturan Stok
        $jumlah = [];
        foreach ($v['bahan'] as $peran) {
            $jumlah[$peran] = true;
        }

        try {
            $minyakGas->buatPermintaan((int) auth()->id(), $jumlah, $v['catatan'] ?? null);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with('success', 'Permintaan dikirim ke admin. Kalau barangnya sudah sampai, jangan lupa tekan "Sudah Diterima".');
    }

    /** Kasir mengonfirmasi minyak / gas sudah diterima (baru di sini stok gudang berkurang). */
    public function terima(Request $request, int $id, MinyakGasService $minyakGas)
    {
        $this->khususKasir();
        $v = $request->validate(['diterima' => 'required|integer|min:1|max:99999']);

        try {
            $minyakGas->konfirmasiTerima($id, (int) auth()->id(), (int) $v['diterima']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Terima kasih, stok minyak/gas kamu sudah ditambah.');
    }

    public function batal(int $id, MinyakGasService $minyakGas)
    {
        $this->khususKasir();

        try {
            $minyakGas->batalPermintaan($id, (int) auth()->id(), false);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Permintaan dibatalkan.');
    }

    private function khususKasir(): void
    {
        abort_unless(auth()->user()?->role === 'kasir', 403);
    }
}
