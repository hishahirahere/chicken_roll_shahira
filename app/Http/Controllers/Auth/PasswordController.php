<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PasswordController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => [
                'required', 'string', 'min:8', 'max:100',
                'regex:/[A-Za-z]/',        // ada huruf
                'regex:/[0-9]/',           // ada angka
                'regex:/[^A-Za-z0-9]/',    // ada simbol
                'regex:/^\S+$/',                 // tidak ada spasi
                'confirmed',
            ],
        ], [
            'current_password.required'         => 'Password lama wajib diisi.',
            'current_password.current_password' => 'Password lama salah.',
            'password.required'  => 'Password baru wajib diisi.',
            'password.min'       => 'Password baru minimal 8 karakter.',
            'password.regex'     => 'Password baru harus mengandung huruf, angka, dan simbol (contoh: Kasir#2026).',
            'password.confirmed' => 'Konfirmasi password tidak sama.',
            'password.regex'     => 'Password baru tidak boleh mengandung spasi.',
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()
            ->with('status', 'password-updated')
            ->with('success', 'Password berhasil diganti.');
    }
}
