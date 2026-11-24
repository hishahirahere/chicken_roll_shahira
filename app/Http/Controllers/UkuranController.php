<?php

namespace App\Http\Controllers;

use App\Models\Ukuran;
use App\Models\ItemPesanan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Concerns\CekRiwayatStokMaster;

class UkuranController extends Controller
{
    use CekRiwayatStokMaster;

    public function index()
    {
        $ukuran = Ukuran::latest()->get();
        return view('admin.data_master.ukuran.index', compact('ukuran'));
    }

    public function create()
    {
        return view('admin.data_master.ukuran.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_ukuran' => 'required|string|max:50',
            'harga' => 'required|integer|min:0',
            'foto' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('foto')) {
            $validated['foto'] = $request->file('foto')->store('ukuran', 'public');
        }

        Ukuran::create($validated);

        return redirect()->route('ukuran.index')->with('success', 'Ukuran berhasil ditambahkan.');
    }

    public function edit(Ukuran $ukuran)
    {
        return view('admin.data_master.ukuran.edit', compact('ukuran'));
    }

    public function update(Request $request, Ukuran $ukuran)
    {
        $validated = $request->validate([
            'nama_ukuran' => 'required|string|max:50',
            'harga' => 'required|integer|min:0',
            'foto' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('foto')) {
            if ($ukuran->foto) {
                Storage::disk('public')->delete($ukuran->foto);
            }
            $validated['foto'] = $request->file('foto')->store('ukuran', 'public');
        }

        $ukuran->update($validated);

        return redirect()->route('ukuran.index')->with('success', 'Ukuran berhasil diperbarui.');
    }

    public function destroy(Ukuran $ukuran)
    {
        // Sudah dipakai di transaksi -> jangan dihapus, supaya riwayat & laporan tetap utuh.
        if (ItemPesanan::where('ukuran_id', $ukuran->id)->exists()) {
            return redirect()->route('ukuran.index')->with('error',
                'Ukuran "' . $ukuran->nama_ukuran . '" sudah dipakai di transaksi, jadi tidak bisa dihapus.');
        }

        // Sudah punya stok / riwayat stok (kemasan, stok kasir, produksi) -> nonaktifkan saja.
        $adaJejak = $this->sudahPunyaJejakStok($ukuran->bahan_baku_id ?? null)
            || DB::table('stok_kasir_produk')->where('ukuran_id', $ukuran->id)->exists()
            || DB::table('riwayat_stok_produk')->where('ukuran_id', $ukuran->id)->exists()
            || DB::table('produksi_item')->where('ukuran_id', $ukuran->id)->exists();
        if ($adaJejak) {
            return redirect()->route('ukuran.index')->with('error',
                'Ukuran "' . $ukuran->nama_ukuran . '" sudah punya stok atau riwayat stok, jadi tidak bisa dihapus. Nonaktifkan saja.');
        }

        if ($ukuran->foto) {
            Storage::disk('public')->delete($ukuran->foto);
        }
        $bahanBakuId = $ukuran->bahan_baku_id ?? null;
        $ukuran->delete();
        $this->bersihkanBahanBakuKosong($bahanBakuId);

        return redirect()->route('ukuran.index')->with('success', 'Ukuran berhasil dihapus.');
    }
}
