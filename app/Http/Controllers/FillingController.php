<?php

namespace App\Http\Controllers;

use App\Models\Filling;
use App\Http\Controllers\Concerns\CekRiwayatStokMaster;
use App\Models\ItemPesanan;
use Illuminate\Http\Request;
use App\Services\StokOtomatis\SinkronFotoMaster;
use Illuminate\Support\Facades\Storage;

class FillingController extends Controller
{
    use CekRiwayatStokMaster;

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $filling = Filling::latest()->get();
        return view('admin.data_master.filling.index', compact('filling'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.data_master.filling.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_filling' => 'required|string|max:50',
            'foto' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('foto')) {
            $validated['foto'] = $request->file('foto')->store('filling', 'public');
        }

        Filling::create($validated);
        $this->sinkronFoto();

        return redirect()->route('filling.index')->with('success', 'Filling berhasil ditambahkan.');
    }

    public function edit(Filling $filling)
    {
        return view('admin.data_master.filling.edit', compact('filling'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Filling $filling)
    {
       $validated = $request->validate([
        'nama_filling' => 'required|string|max:50',
        'foto' => 'nullable|image|max:2048', ]);

       if ($request->hasFile('foto')) {
           if ($filling->foto) {
               Storage::disk('public')->delete($filling->foto);
           }
           $validated['foto'] = $request->file('foto')->store('filling', 'public');
       }

        $filling->update($validated);
        $this->sinkronFoto();

        return redirect() ->route('filling.index') ->with('success', 'Filling berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Filling $filling)
    {
        // Sudah dipakai di transaksi -> jangan dihapus, supaya riwayat & laporan tetap utuh.
        if (ItemPesanan::where('filling_id', $filling->id)->exists()) {
            return redirect()->route('filling.index')->with('error',
                'Filling "' . $filling->nama_filling . '" sudah dipakai di transaksi, jadi tidak bisa dihapus.');
        }

        // Sudah punya stok / riwayat stok (masuk, keluar, diberikan ke kasir) -> jangan dihapus, nonaktifkan saja.
        if ($this->sudahPunyaJejakStok($filling->bahan_baku_id ?? null)) {
            return redirect()->route('filling.index')->with('error',
                'Filling "' . $filling->nama_filling . '" sudah punya stok atau riwayat stok, jadi tidak bisa dihapus. Nonaktifkan saja.');
        }

        if ($filling->foto) {
            Storage::disk('public')->delete($filling->foto);
        }
        $bahanBakuId = $filling->bahan_baku_id ?? null;
        $filling->delete();
        $this->bersihkanBahanBakuKosong($bahanBakuId);

        return redirect()->route('filling.index')->with('success', 'Filling berhasil dihapus.');
    }

    /** Salin foto master ke bahan baku yang sudah terhubung. Item baru ikut saat Manajemen Stok dibuka. */
    private function sinkronFoto(): void
    {
        SinkronFotoMaster::semua();
    }
}
