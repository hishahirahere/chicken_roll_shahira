<?php

namespace App\Http\Controllers;

use App\Models\BumbuTabur;
use App\Models\ItemPesanan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BumbuTaburController extends Controller
{
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

        if ($bumbuTabur->foto) {
            Storage::disk('public')->delete($bumbuTabur->foto);
        }
        $bumbuTabur->delete();

        return redirect()->route('bumbu_tabur.index')->with('success', 'Bumbu Tabur berhasil dihapus.');
    }
}
