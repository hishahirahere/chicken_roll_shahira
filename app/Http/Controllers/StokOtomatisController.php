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

    // ================================================================
    // PENGATURAN STOK
    // ================================================================
    public function pengaturan()
    {
        $bahan   = DB::table('bahan_baku')->orderBy('nama_bahan')->get();
        $ukuran  = DB::table('ukuran')->orderBy('harga')->get();

        return view('admin.operasional.pengaturan_stok.index', [
            'set'        => $this->stok->pengaturan(),
            'standar'    => $this->stok->standar(),
            'ukuran'     => $ukuran,
            'bahan'      => $bahan,
            'bahanById'  => $bahan->keyBy('id'),
            'peranUtama' => StokOtomatisService::PERAN_UTAMA,
            'peranBahan' => $bahan->whereNotNull('peran')->pluck('id', 'peran'),
            'filling'    => DB::table('filling')->orderBy('nama_filling')->get(),
            'saus'       => DB::table('saus_celup')->orderBy('nama_saus')->get(),
            'bumbu'      => DB::table('bumbu_tabur')->orderBy('nama_bumbu')->get(),
            'kurang'     => $this->stok->pemetaanKurang(),
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
            'kemasan_per_produk' => $angka,
            'paket_kg_per_ukuran'   => $angka,
            'paket_filling_bungkus' => $angka,
            'paket_saus_bungkus'    => $angka,
            'paket_bumbu_bungkus'   => $angka,
            'paket_premix_basah_botol'  => $angka,   // botol (1 liter)
            'paket_tepung_roti_bungkus' => $angka,   // bungkus (1 kg)
            'paket_minyak_jerigen'  => 'required|integer|min:1|max:99',   // jatah minyak sekali kirim (jerigen)
            'paket_gas_tabung'      => 'required|integer|min:1|max:99',   // jatah gas sekali kirim (tabung)
            'liter_per_jerigen'     => 'required|integer|min:1|max:999',
            'batas_menipis_persen'  => 'required|integer|min:1|max:90',

            'peran'                => 'nullable|array',
            'peran.*'              => 'nullable|integer|exists:bahan_baku,id',
            'hubungan'             => 'nullable|array',
            'hubungan.*'           => 'nullable|array',
            'hubungan.*.*'         => 'nullable|integer|exists:bahan_baku,id',
            'porsi_per_bungkus'    => 'nullable|array',
            'porsi_per_bungkus.*'  => 'nullable|integer|min:1|max:9999',
        ]);

        DB::transaction(function () use ($v) {
            // 1) Pengaturan umum
            foreach (array_keys(PerhitunganStok::PENGATURAN_AWAL) as $kunci) {
                if (! array_key_exists($kunci, $v)) {
                    continue; // mis. pengaturan yang tidak ada di form dibiarkan di nilai bawaan
                }
                DB::table('pengaturan_stok')->updateOrInsert(
                    ['kunci' => $kunci],
                    ['nilai' => (string) $v[$kunci], 'updated_at' => now(), 'created_at' => now()]
                );
            }

            // 2) Hasil pcs per 1 kg ayam (25 / 20 / 15) sudah baku, tidak diubah dari form.
            //    Tepung tidak dihitung dari ayam: premix basah (botol) & tepung roti (bungkus) = isi paket tetap.

            // 3) Bahan utama (ayam, tepung, minyak, gas, kemasan): 1 bahan per peran
            foreach (array_keys(StokOtomatisService::PERAN_UTAMA) as $peran) {
                $dipilih = $v['peran'][$peran] ?? null;
                DB::table('bahan_baku')->where('peran', $peran)->update(['peran' => null]);
                if ($dipilih) {
                    DB::table('bahan_baku')->where('id', $dipilih)->update(['peran' => $peran]);
                }
            }

            // 4) Hubungan filling / saus celup / bumbu tabur -> bahan baku
            foreach ([
                'filling' => 'filling', 'saus_celup' => 'saus', 'bumbu_tabur' => 'bumbu',
            ] as $tabel => $peran) {
                foreach (($v['hubungan'][$tabel] ?? []) as $id => $bahanId) {
                    DB::table($tabel)->where('id', $id)->update(['bahan_baku_id' => $bahanId ?: null]);
                    if ($bahanId) {
                        DB::table('bahan_baku')->where('id', $bahanId)->update(['peran' => $peran]);
                    }
                }
            }

            // 5) Porsi per bungkus khusus per bahan (kosong = ikut standar umum)
            foreach (($v['porsi_per_bungkus'] ?? []) as $bahanId => $porsi) {
                DB::table('bahan_baku')->where('id', $bahanId)->update(['porsi_per_bungkus' => $porsi ?: null]);
            }
        });

        $this->stok->segarkanSemuaStatus();

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
