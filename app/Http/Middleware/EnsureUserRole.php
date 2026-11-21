<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    /**
     * Pakai: ->middleware('role:admin')  atau  ->middleware('role:kasir,admin')
     *
     * - Belum login            -> ke halaman login
     * - Akun nonaktif          -> di-logout paksa
     * - Role tidak sesuai      -> dikembalikan ke halaman "rumah" role-nya
     *                             (kasir -> dashboard kasir, admin -> dashboard)
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->status === 'nonaktif') {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Akun kamu nonaktif. Hubungi admin.',
            ]);
        }

        if (in_array($user->role, $roles, true)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'Kamu tidak punya akses ke halaman ini.');
        }

        return match ($user->role) {
            'kasir' => redirect()->route('pos.dashboard_kasir'),
            'admin' => redirect()->route('dashboard'),
            default => abort(403),
        };
    }
}
