<?php

namespace App\Http\Controllers;

use App\Services\StokOtomatis\StokOtomatisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin: permintaan minyak & gas dari kasir.
 * Alur: kasir minta -> admin kirim (barang diantar) -> kasir konfirmasi diterima -> stok gudang baru berkurang.
 */
class PermintaanStokController extends Controller
{
    public function index()
    {
        $dasar = fn () => DB::table('permintaan_stok as p')
            ->join('users as k', 'k.id', '=', 'p.kasir_id')
            ->join('bahan_baku as b', 'b.id', '=', 'p.bahan_baku_id')
            ->select('p.*', 'k.name as nama_kasir', 'b.nama_bahan', 'b.satuan', 'b.stok_saat_ini');

        $diminta = $dasar()->where('p.status', 'diminta')->orderBy('p.created_at')->get();
        $dikirim = $dasar()->where('p.status', 'dikirim')->orderBy('p.dikirim_pada')->get();
        $selesai = $dasar()->whereIn('p.status', ['selesai', 'batal'])->orderByDesc('p.updated_at')->limit(15)->get();

        $pemakaian = DB::table('pemakaian_kasir as m')
            ->join('users as k', 'k.id', '=', 'm.user_id')
            ->join('bahan_baku as b', 'b.id', '=', 'm.bahan_baku_id')
            ->select('m.*', 'k.name as nama_kasir', 'b.nama_bahan', 'b.satuan')
            ->orderByDesc('m.tanggal')->orderByDesc('m.id')->limit(15)->get();

        return view('admin.operasional.permintaan_stok.index', compact('diminta', 'dikirim', 'selesai', 'pemakaian'));
    }

    public function kirim(Request $request, int $id, StokOtomatisService $stok)
    {
        $v = $request->validate(['jumlah' => 'required|integer|min:1|max:99999']);

        try {
            $stok->kirimPermintaan($id, (int) $v['jumlah'], (int) auth()->id());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput(['aksi_id' => $id]);
        }

        return back()->with('success', 'Ditandai sudah dikirim. Stok gudang akan berkurang setelah kasir mengonfirmasi barangnya diterima.');
    }

    public function batal(int $id, StokOtomatisService $stok)
    {
        try {
            $stok->batalPermintaan($id, (int) auth()->id(), true);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Permintaan dibatalkan.');
    }
}
