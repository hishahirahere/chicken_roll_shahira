<?php

namespace App\Providers;

use App\Models\BahanBaku;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Isi $notifikasi (lonceng di navbar) dari data asli di database,
        // setiap kali layouts/admin ditampilkan.
        View::composer('layouts.admin', function ($view) {
            $notifikasi = collect();

            // Permintaan minyak & gas dari kasir yang belum dikirim (masuk ke lonceng notifikasi)
            try {
                $permintaanTerbuka = \Illuminate\Support\Facades\DB::table('permintaan_stok as p')
                    ->join('users as k', 'k.id', '=', 'p.kasir_id')
                    ->join('bahan_baku as b', 'b.id', '=', 'p.bahan_baku_id')
                    ->where('p.status', 'diminta')->orderBy('p.created_at')
                    ->select('p.id', 'p.kasir_id', 'p.created_at', 'p.jumlah_diminta', 'k.name as kasir', 'b.nama_bahan', 'b.satuan')->get();
            } catch (\Throwable $e) {
                $permintaanTerbuka = collect(); // tabel belum dimigrasi
            }
            if (! auth()->check()) {
                $view->with('notifikasi', $notifikasi);
                return;
            }

            $urlStok  = Route::has('bahan_baku.index') ? route('bahan_baku.index') : '#';
            $urlKasir = Route::has('kasir.index') ? route('kasir.index') : '#';

            // 0. Permintaan minyak / gas dari kasir (paling penting, tampil paling atas)
            foreach ($permintaanTerbuka->take(5) as $q) {
                $notifikasi->push([
                    'key'   => 'permintaan-' . $q->id,
                    'icon'  => 'bi-fuel-pump-fill',
                    'awal'  => 'Kasir ' . $q->kasir . ' minta',
                    'sorot' => $q->nama_bahan . ($q->jumlah_diminta ? ' ' . $q->jumlah_diminta . ' ' . $q->satuan : ''),
                    'akhir' => '',
                    'waktu' => \Illuminate\Support\Carbon::parse($q->created_at)->locale('id')->diffForHumans(),
                    'url'   => Route::has('bahan_baku.index') ? route('bahan_baku.index', ['aksi' => 'kirim', 'kasir' => $q->kasir_id]) : '#',
                ]);
            }

            // 0b. Kasir mencatat pemakaian minyak / gas (pemberitahuan ke admin, 2 hari terakhir)
            try {
                $pemakaianBaru = \Illuminate\Support\Facades\DB::table('pemakaian_kasir as m')
                    ->join('users as k', 'k.id', '=', 'm.user_id')
                    ->join('bahan_baku as b', 'b.id', '=', 'm.bahan_baku_id')
                    ->where('m.created_at', '>=', now()->subDays(2))->orderByDesc('m.id')->limit(3)
                    ->select('m.id', 'm.user_id', 'm.jumlah', 'm.created_at', 'k.name as kasir', 'b.nama_bahan', 'b.satuan')->get();
            } catch (\Throwable $e) {
                $pemakaianBaru = collect();
            }
            foreach ($pemakaianBaru as $m) {
                $notifikasi->push([
                    'key'   => 'pemakaian-' . $m->id,
                    'icon'  => 'bi-journal-check',
                    'awal'  => 'Kasir ' . $m->kasir . ' memakai',
                    'sorot' => $m->jumlah . ' ' . $m->satuan . ' ' . strtolower($m->nama_bahan),
                    'akhir' => '',
                    'waktu' => \Illuminate\Support\Carbon::parse($m->created_at)->locale('id')->diffForHumans(),
                    'url'   => Route::has('bahan_baku.index') ? route('bahan_baku.index', ['aksi' => 'kirim', 'kasir' => $m->user_id]) : '#',
                ]);
            }

            // 1. Stok bermasalah: yang habis ditampilkan lebih dulu, baru yang menipis
            $bahanBermasalah = BahanBaku::whereIn('status_stok', ['habis', 'menipis'])
                ->orderByDesc('status_stok')
                ->orderBy('nama_bahan')
                ->limit(6)
                ->get();

            foreach ($bahanBermasalah as $b) {
                $habis = $b->status_stok === 'habis';

                $notifikasi->push([
                    // key berubah kalau status berubah, jadi dianggap notifikasi baru lagi
                    'key'   => 'stok-' . $b->id . '-' . $b->status_stok,
                    'icon'  => $habis ? 'bi-x-circle-fill' : 'bi-exclamation-triangle-fill',
                    'awal'  => $habis ? 'Stok habis:' : 'Stok menipis:',
                    'sorot' => $b->nama_bahan,
                    'akhir' => $habis ? '' : ' (sisa ' . $b->stok_format . ' ' . $b->satuan . ')',
                    'waktu' => optional($b->updated_at)->locale('id')->diffForHumans(),
                    'url'   => $urlStok,
                ]);
            }

            // 1b. Stok di tangan kasir yang menipis / habis (admin memantau)
            try {
                $stokService = app(\App\Services\StokOtomatis\StokOtomatisService::class);
                foreach ($stokService->stokKasirSemua() as $kasirStok) {
                    // Hanya kasir yang pernah menerima paket
                    if (! \Illuminate\Support\Facades\DB::table('riwayat_stok_produk')->where('user_id', $kasirStok->id)->exists()) {
                        continue;
                    }
                    $dipakai = $kasirStok->bahan->filter(fn ($b) => $b->status !== 'aman');
                    foreach ($dipakai->take(2) as $b) {
                        $habis = $b->status === 'habis';
                        $notifikasi->push([
                            'key'   => 'stokkasir-' . $kasirStok->id . '-' . md5($b->nama) . '-' . $b->status,
                            'icon'  => $habis ? 'bi-x-circle-fill' : 'bi-exclamation-triangle-fill',
                            'awal'  => ($habis ? 'Habis di kasir ' : 'Menipis di kasir ') . $kasirStok->name . ':',
                            'sorot' => $b->nama,
                            'akhir' => '',
                            'waktu' => null,
                            'url'   => $urlStok,
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                // tabel stok_kasir belum dimigrasi: lewati saja
            }

            // 2. Akun kasir yang dibuat dalam 7 hari terakhir
            $kasirBaru = User::where('role', 'kasir')
                ->where('created_at', '>=', now()->subDays(7))
                ->latest()
                ->limit(3)
                ->get();

            foreach ($kasirBaru as $k) {
                $notifikasi->push([
                    'key'   => 'kasir-' . $k->id,
                    'icon'  => 'bi-person-plus-fill',
                    'awal'  => 'Akun kasir baru:',
                    'sorot' => $k->name,
                    'akhir' => '',
                    'waktu' => optional($k->created_at)->locale('id')->diffForHumans(),
                    'url'   => $urlKasir,
                ]);
            }

            $view->with('notifikasi', $notifikasi);
        });
    }
}
