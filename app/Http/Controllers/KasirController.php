<?php

namespace App\Http\Controllers;

use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Master akun kasir (khusus admin, dijaga middleware role:admin di route). */
class KasirController extends Controller
{
    private function aturan(?User $kasir = null): array
    {
        return [
            'name'          => 'required|string|max:100',
            'jenis_kelamin' => 'nullable|in:L,P',
            'tempat_lahir'  => 'nullable|string|max:100',
            'tanggal_lahir' => 'nullable|date|before:today',
            'alamat'        => 'nullable|string|max:255',
            'no_hp'         => 'nullable|string|max:20',
            'status'        => 'required|in:aktif,nonaktif',
            'foto'          => 'nullable|image|max:2048',
        ];
    }

    public function index()
    {
        $kasir = User::where('role', 'kasir')->orderBy('name')->get();

        return view('admin.data_master.kasir.index', compact('kasir'));
    }

    public function create()
    {
        return view('admin.data_master.kasir.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->aturan() + [
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => 'required|string|min:6|max:100',
        ]);

        if ($request->hasFile('foto')) {
            $data['foto'] = $request->file('foto')->store('kasir', 'public');
        }

        $data['email'] = strtolower($data['email']);
        $data['role']  = 'kasir'; // dikunci: lewat form ini hanya bisa membuat kasir
        User::create($data);   // password otomatis di-hash oleh cast 'hashed' di model User

        return redirect()->route('kasir.index')->with('success', 'Akun kasir berhasil ditambahkan.');
    }

    public function show(User $kasir)
    {
        abort_unless($kasir->role === 'kasir', 404);

        return view('admin.data_master.kasir.show', compact('kasir'));
    }

    public function edit(User $kasir)
    {
        abort_unless($kasir->role === 'kasir', 404);

        return view('admin.data_master.kasir.edit', compact('kasir'));
    }

    public function update(Request $request, User $kasir)
    {
        abort_unless($kasir->role === 'kasir', 404);

        // Email & password sengaja tidak bisa diubah admin dari sini (kasir mengurusnya sendiri lewat Profil Saya).
        $data = $request->validate($this->aturan($kasir));

        if ($request->hasFile('foto')) {
            if ($kasir->foto) {
                Storage::disk('public')->delete($kasir->foto);
            }
            $data['foto'] = $request->file('foto')->store('kasir', 'public');
        }

        $kasir->update($data);

        return redirect()->route('kasir.index')->with('success', 'Data kasir berhasil diperbarui.');
    }

    public function destroy(User $kasir)
    {
        abort_unless($kasir->role === 'kasir', 404);

        // Kasir yang sudah punya transaksi tidak dihapus supaya riwayat & laporan tetap utuh; cukup dinonaktifkan.
        if (Transaksi::where('user_id', $kasir->id)->exists()) {
            return redirect()->route('kasir.index')->with('error',
                'Kasir ' . $kasir->name . ' sudah punya transaksi, jadi tidak bisa dihapus. Ubah statusnya menjadi Nonaktif.');
        }

        if ($kasir->foto) {
            Storage::disk('public')->delete($kasir->foto);
        }
        $kasir->delete();

        return redirect()->route('kasir.index')->with('success', 'Akun kasir berhasil dihapus.');
    }
}
