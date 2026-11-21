<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\StokOtomatis\StokOtomatisService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StokKasirSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(StokOtomatisService::class);
        $adminId = User::where('role', 'admin')->value('id');
        if (! $adminId) {
            return;
        }

        $service->siapkan($adminId);
        $set = $service->pengaturan();

        foreach (User::where('role', 'kasir')->get() as $kasir) {
            $sudahPaket = DB::table('riwayat_stok')->where('sumber', 'paket')->where('kasir_id', $kasir->id)->exists();
            // Minyak & gas manual: pakai alur yang sama dengan aplikasi (kasir minta -> admin kirim -> kasir konfirmasi)
            $sudahAda = DB::table('permintaan_stok')->where('kasir_id', $kasir->id)->exists();
            if (! $sudahAda) {
                try {
                    $service->kirimKeKasir((int) $kasir->id, max(1, (int) $set['paket_minyak_jerigen']), 1, (int) $adminId, 'Minyak & gas awal');
                    foreach (DB::table('permintaan_stok')->where('kasir_id', $kasir->id)->where('status', 'dikirim')->get() as $q) {
                        $service->konfirmasiTerima((int) $q->id, (int) $kasir->id, (int) $q->jumlah_dikirim);
                    }
                } catch (ValidationException $e) {
                    $this->command?->warn('Minyak & gas awal untuk ' . $kasir->name . ' dilewati: ' . implode(' ', array_merge(...array_values($e->errors()))));
                }
            }

            if ($sudahPaket) {
                continue;
            }

            $kg = DB::table('ukuran')->where('status', 'aktif')->pluck('id')->mapWithKeys(fn ($id) => [$id => (int) ceil($set['paket_kg_per_ukuran'])])->all();

            // Kemasan tiap ukuran = jumlah pcs hasil ayam ukuran itu
            $standar = $service->standar();
            $kemasan = [];
            foreach ($kg as $ukuranId => $kgAyam) {
                $kemasan[$ukuranId] = (int) floor($kgAyam * ($standar[$ukuranId]['hasil_pcs_per_kg'] ?? 0) + 1e-9);
            }

            $bungkus = [];
            foreach ([
                'filling'     => ['peran' => 'filling', 'jumlah' => (int) ceil($set['paket_filling_bungkus'])],
                'saus_celup'  => ['peran' => 'saus',   'jumlah' => (int) ceil($set['paket_saus_bungkus'])],
                'bumbu_tabur' => ['peran' => 'bumbu',  'jumlah' => (int) ceil($set['paket_bumbu_bungkus'])],
            ] as $tabel => $cfg) {
                foreach (DB::table($tabel)->where('status', 'aktif')->whereNotNull('bahan_baku_id')->pluck('bahan_baku_id') as $bahanId) {
                    $bungkus[$bahanId] = $cfg['jumlah'];
                }
            }

            // Dua pengambilan terpisah, seperti di aplikasi: Paket Ayam (+tepung, kemasan) dan Bahan Pendukung (per rasa)
            try {
                $service->beriPaketAyam(
                    (int) $kasir->id, now()->toDateString(), $kg, $kemasan,
                    (int) $adminId, 'Paket awal',
                    ['premix_basah' => (int) $set['paket_premix_basah_botol'], 'tepung_roti' => (int) $set['paket_tepung_roti_bungkus']]
                );
                $service->beriBahanPendukung((int) $kasir->id, now()->toDateString(), $bungkus, (int) $adminId, 'Bahan pendukung awal');
            } catch (ValidationException $e) {
                $this->command?->warn('Paket awal untuk ' . $kasir->name . ' dilewati: ' . implode(' ', array_merge(...array_values($e->errors()))));
            }
        }
    }
}
