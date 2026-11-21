<?php

namespace App\Http\Controllers;

use App\Models\Filling;
use App\Models\ItemPesanan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FillingController extends Controller
{
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

        if ($filling->foto) {
            Storage::disk('public')->delete($filling->foto);
        }
        $filling->delete();

        return redirect()->route('filling.index')->with('success', 'Filling berhasil dihapus.');
    }
}
