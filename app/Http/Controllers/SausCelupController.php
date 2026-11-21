<?php

namespace App\Http\Controllers;

use App\Models\SausCelup;
use App\Models\ItemPesanan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SausCelupController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $sausCelup = SausCelup::latest()->get();
        return view('admin.data_master.saus_celup.index', compact('sausCelup'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.data_master.saus_celup.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_saus' => 'required|string|max:50',
            'foto' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('foto')) {
            $validated['foto'] = $request->file('foto')->store('saus_celup', 'public');
        }

        SausCelup::create($validated);

        return redirect()->route('saus_celup.index')->with('success', 'Saus Celup berhasil ditambahkan.');
    }

    public function edit(SausCelup $sausCelup)
    {
        return view('admin.data_master.saus_celup.edit', compact('sausCelup'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SausCelup $sausCelup)
    {
       $validated = $request->validate([
        'nama_saus' => 'required|string|max:50',
        'foto' => 'nullable|image|max:2048', ]);

       if ($request->hasFile('foto')) {
           if ($sausCelup->foto) {
               Storage::disk('public')->delete($sausCelup->foto);
           }
           $validated['foto'] = $request->file('foto')->store('saus_celup', 'public');
       }

        $sausCelup->update($validated);

        return redirect() ->route('saus_celup.index') ->with('success', 'Saus Celup berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SausCelup $sausCelup)
    {
        // Sudah dipakai di transaksi -> jangan dihapus, supaya riwayat & laporan tetap utuh.
        if (ItemPesanan::where('saus_celup_id', $sausCelup->id)->exists()) {
            return redirect()->route('saus_celup.index')->with('error',
                'Saus Celup "' . $sausCelup->nama_saus . '" sudah dipakai di transaksi, jadi tidak bisa dihapus.');
        }

        if ($sausCelup->foto) {
            Storage::disk('public')->delete($sausCelup->foto);
        }
        $sausCelup->delete();

        return redirect()->route('saus_celup.index')->with('success', 'Saus Celup berhasil dihapus.');
    }
}
