<?php

namespace App\Http\Controllers;

use App\Models\BumbuTabur;
use App\Http\Controllers\Concerns\CekRiwayatStokMaster;
use App\Models\ItemPesanan;
use Illuminate\Http\Request;
use App\Services\StokOtomatis\SinkronFotoMaster;
use Illuminate\Support\Facades\Storage;

class BumbuTaburController extends Controller
{
    use CekRiwayatStokMaster;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $bumbuTabur = BumbuTabur::latest()->get();
        return view('admin.data_master.bumbu_tabur.index', compact('bumbuTabur'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.data_master.bumbu_tabur.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_bumbu' => 'required|string|max:50',
            'foto' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('foto')) {
            $validated['foto'] = $request->file('foto')->store('bumbu_tabur', 'public');
        }

        BumbuTabur::create($validated);
        $this->sinkronFoto();

        return redirect()->route('bumbu_tabur.index')->with('success', 'Bumbu Tabur berhasil ditambahkan.');
    }

    public function edit(BumbuTabur $bumbuTabur)
    {
        return view('admin.data_master.bumbu_tabur.edit', compact('bumbuTabur'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, BumbuTabur $bumbuTabur)
    {
       $validated = $request->validate([
        'nama_bumbu' => 'required|string|max:50',
        'foto' => 'nullable|image|max:2048', ]);

       if ($request->hasFile('foto')) {
           if ($bumbuTabur->foto) {
               Storage::disk('public')->delete($bumbuTabur->foto);
           }
           $validated['foto'] = $request->file('foto')->store('bumbu_tabur', 'public');
       }

        $bumbuTabur->update($validated);
        $this->sinkronFoto();

        return redirect() ->route('bumbu_tabur.index') ->with('success', 'Bumbu Tabur berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(BumbuTabur $bumbuTabur)
    {
        // Sudah dipakai di transaksi -> jangan dihapus, supaya riwayat & laporan tetap utuh.
        if (ItemPesanan::where('bumbu_tabur_id', $bumbuTabur->id)->exists()) {
            return redirect()->route('bumbu_tabur.index')->with('error',
                'Bumbu Tabur "' . $bumbuTabur->nama_bumbu . '" sudah dipakai di transaksi, jadi tidak bisa dihapus.');
        }

        // Sudah punya stok / riwayat stok (masuk, keluar, diberikan ke kasir) -> jangan dihapus, nonaktifkan saja.
        if ($this->sudahPunyaJejakStok($bumbuTabur->bahan_baku_id ?? null)) {
            return redirect()->route('bumbu_tabur.index')->with('error',
                'Bumbu Tabur "' . $bumbuTabur->nama_bumbu . '" sudah punya stok atau riwayat stok, jadi tidak bisa dihapus. Nonaktifkan saja.');
        }

        if ($bumbuTabur->foto) {
            Storage::disk('public')->delete($bumbuTabur->foto);
        }
        $bahanBakuId = $bumbuTabur->bahan_baku_id ?? null;
        $bumbuTabur->delete();
        $this->bersihkanBahanBakuKosong($bahanBakuId);

        return redirect()->route('bumbu_tabur.index')->with('success', 'Bumbu Tabur berhasil dihapus.');
    }

    /** Salin foto master ke bahan baku yang sudah terhubung. Item baru ikut saat Manajemen Stok dibuka. */
    private function sinkronFoto(): void
    {
        SinkronFotoMaster::semua();
    }
}
