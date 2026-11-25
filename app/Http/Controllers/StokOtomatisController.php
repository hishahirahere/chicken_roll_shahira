<?php

namespace App\Http\Controllers;

use App\Services\StokOtomatis\PerhitunganStok;
use App\Services\StokOtomatis\StokOtomatisService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Pengaturan Stok (angka standar pemakaian + isi paket standar).
 */
class StokOtomatisController extends Controller
{
    public function __construct(private StokOtomatisService $stok)
    {
    }

    public function pengaturan()
    {
        return view('admin.operasional.pengaturan_stok.index', [
            'set'     => $this->stok->pengaturan(),
            'standar' => $this->stok->standar(),
            'ukuran'  => DB::table('ukuran')->orderBy('harga')->get(),
            'kurang'  => $this->stok->pemetaanKurang(),
        ]);
    }

    public function simpanPengaturan(Request $request): RedirectResponse
    {
        // Semua angka stok & paket = bilangan bulat
        $angka = 'required|integer|min:0|max:99999';
        $v = $request->validate([
            'kapasitas_filling'  => 'required|integer|min:1|max:9999',
            'kapasitas_saus'     => 'required|integer|min:1|max:9999',
            'kapasitas_bumbu'    => 'required|integer|min:1|max:9999',
            'paket_kg_per_ukuran'   => $angka,
            'paket_filling_bungkus' => $angka,
            'paket_saus_bungkus'    => $angka,
            'paket_bumbu_bungkus'   => $angka,
            'paket_premix_basah_botol'  => $angka,   // botol (1 liter)
            'paket_tepung_roti_bungkus' => $angka,   // bungkus (1 kg)
            'paket_minyak_jerigen'  => 'required|integer|min:1|max:99',   // jatah minyak sekali kirim (jerigen)
            'paket_gas_tabung'      => 'required|integer|min:1|max:99',   // jatah gas sekali kirim (tabung)

            'hasil_pcs_per_kg'     => 'nullable|array',
            'hasil_pcs_per_kg.*'   => 'required|integer|min:1|max:999',
        ]);

        DB::transaction(function () use ($v) {
            // 1) Pengaturan umum
            foreach (array_keys(PerhitunganStok::PENGATURAN_AWAL) as $kunci) {
                if (! array_key_exists($kunci, $v)) {
                    continue; // pengaturan yang tidak ada di form dibiarkan di nilai bawaan
                }
                DB::table('pengaturan_stok')->updateOrInsert(
                    ['kunci' => $kunci],
                    ['nilai' => (string) $v[$kunci], 'updated_at' => now(), 'created_at' => now()]
                );
            }

            // 2) Hasil potong per kg ayam untuk tiap ukuran
            foreach (($v['hasil_pcs_per_kg'] ?? []) as $ukuranId => $pcs) {
                if (! DB::table('ukuran')->where('id', $ukuranId)->exists()) {
                    continue;
                }
                DB::table('standar_produksi')->updateOrInsert(
                    ['ukuran_id' => (int) $ukuranId],
                    ['hasil_pcs_per_kg' => (int) $pcs, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        });

        return redirect()->route('stok.pengaturan')->with('success', 'Pengaturan stok berhasil disimpan.');
    }

    public function hubungkan(): RedirectResponse
    {
        $n = $this->stok->hubungkanOtomatis(auth()->id());
        $this->stok->segarkanSemuaStatus();

        return redirect()->route('stok.pengaturan')->with('success',
            $n > 0 ? "{$n} item dihubungkan ke bahan baku (bahan baru dibuat bila belum ada, stok awal 0)."
                   : 'Semua filling, saus, dan bumbu sudah terhubung.');
    }
}
