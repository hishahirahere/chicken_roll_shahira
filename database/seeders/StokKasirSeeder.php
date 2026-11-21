<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\StokOtomatis\StokOtomatisService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Paket awal untuk kasir (stok gudang -> stok kasir), memakai alur yang sama dengan
 * tombol "Beri Paket ke Kasir" di Manajemen Stok. Jadi setelah seeding, kasir langsung
 * bisa jualan dan halaman Stok Saya / Stok Kasir tidak kosong.
 *
 * Isi paket mengikuti pengaturan standar (semua bilangan bulat):
 *  3 kg ayam (1 kg per ukuran = 60 pcs), 2 botol premix basah (1 liter/botol) + 1 bungkus tepung roti (1 kg/bungkus), mengurangi gudang,
 *  2 bungkus tiap filling & saus celup, 1 bungkus tiap bumbu tabur, dan kemasan per ukuran
 *  sebanyak pcs hasil ayamnya (25 Slim, 20 Reguler, 15 Jumbo).
 *
 * Minyak & gas TIDAK ikut paket (manual, jatah tetap untuk beberapa hari). Seeder menjalankan alur permintaan: admin mengirim
 * jatah minyak (jerigen) + gas (tabung) sesuai Pengaturan Stok, kasir konfirmasi diterima (stok gudang berkurang saat konfirmasi).
 *
 * Dilewati kalau kasir tersebut sudah pernah menerima paket, jadi aman dijalankan ulang.
 */
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

            $kg = DB::table('ukuran')->pluck('id')->mapWithKeys(fn ($id) => [$id => (int) ceil($set['paket_kg_per_ukuran'])])->all();

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

            try {
                $service->beriPaket(
                    (int) $kasir->id, now()->toDateString(), $kg, $bungkus,
                    $kemasan,
                    (int) $adminId, 'Paket awal',
                    ['premix_basah' => (int) $set['paket_premix_basah_botol'], 'tepung_roti' => (int) $set['paket_tepung_roti_bungkus']]
                );
            } catch (ValidationException $e) {
                $this->command?->warn('Paket awal untuk ' . $kasir->name . ' dilewati: ' . implode(' ', array_merge(...array_values($e->errors()))));
            }
        }
    }
}
