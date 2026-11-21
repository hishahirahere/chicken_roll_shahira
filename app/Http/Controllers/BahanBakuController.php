<?php

namespace App\Http\Controllers;

use App\Models\BahanBaku;
use App\Models\RiwayatStok;
use App\Models\User;
use App\Services\StokOtomatis\StokOtomatisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BahanBakuController extends Controller
{
    /**
     * Halaman Manajemen Stok: daftar bahan baku + tombol catat masuk/keluar.
     */
    public function index()
    {
        // Kenali bahan utama & hubungkan filling/saus/bumbu otomatis (tanpa menu pengaturan)
        app(StokOtomatisService::class)->siapkan((int) auth()->id());

        $bahanBaku = BahanBaku::orderBy('nama_bahan')->get();

        // Kasir aktif buat pilihan "diambil oleh" di form stok keluar
        $kasirList = User::where('role', 'kasir')
            ->where('status', 'aktif')
            ->orderBy('name')
            ->get();

        // Bahan untuk form "Beri Paket"
        $svc = app(StokOtomatisService::class);
        $set = $svc->pengaturan();
        $ukuranPaket = DB::table('ukuran')->where('status', 'aktif')->orderBy('harga')->get();
        $standarPaket = $svc->standar();
        $nonaktif = collect()
            ->merge(DB::table('filling')->where('status', 'nonaktif')->pluck('bahan_baku_id'))
            ->merge(DB::table('saus_celup')->where('status', 'nonaktif')->pluck('bahan_baku_id'))
            ->merge(DB::table('bumbu_tabur')->where('status', 'nonaktif')->pluck('bahan_baku_id'))
            ->filter()->all();
        $bahanPaket = DB::table('bahan_baku')->whereIn('peran', ['filling', 'saus', 'bumbu'])->orderBy('nama_bahan')->get()
            ->reject(fn ($b) => in_array($b->id, $nonaktif))
            ->each(fn ($b) => $b->kap = $svc->kapasitasBungkus($b, $set))
            ->groupBy('peran');

        // Stok gudang kemasan tiap ukuran: [ukuran_id => stok]
        $stokKemasan = DB::table('ukuran')->join('bahan_baku', 'bahan_baku.id', '=', 'ukuran.bahan_baku_id')
            ->pluck('bahan_baku.stok_saat_ini', 'ukuran.id')->all();

        $premixBasah = DB::table('bahan_baku')->where('peran', 'tepung_premix_basah')->orderBy('id')->first();
        $tepungRoti  = DB::table('bahan_baku')->where('peran', 'tepung_roti')->orderBy('id')->first();

        $gasBahan   = DB::table('bahan_baku')->where('peran', 'gas')->orderBy('id')->first();
        $minyakBahan = DB::table('bahan_baku')->where('peran', 'minyak')->orderBy('id')->first();

        // Form Kirim Minyak & Gas: isi awal standar, atau mengikuti permintaan kasir yang masuk
        $kirimStandar = $svc->porsiKirim($set); // jatah tetap sekali kirim: minyak (jerigen) & gas (tabung) dari Pengaturan Stok
        $permintaanKasir = DB::table('permintaan_stok as p')->join('bahan_baku as b', 'b.id', '=', 'p.bahan_baku_id')
            ->where('p.status', 'diminta')->select('p.kasir_id', 'p.jumlah_diminta', 'p.catatan', 'b.peran')->get()
            ->groupBy('kasir_id')->map(function ($g) use ($kirimStandar) {
                $out = ['minyak' => 0, 'gas' => 0, 'catatan' => $g->pluck('catatan')->filter()->first()];
                foreach ($g as $q) {
                    $out[$q->peran] = (int) ($q->jumlah_diminta ?: $kirimStandar[$q->peran]);
                }

                return $out;
            })->all();
        return view('admin.operasional.manajemen_stok.index', compact(
            'kirimStandar', 'permintaanKasir', 'bahanBaku', 'kasirList', 'set', 'ukuranPaket', 'standarPaket', 'bahanPaket', 'gasBahan', 'minyakBahan', 'stokKemasan', 'premixBasah', 'tepungRoti'
        ));
    }

    public function create()
    {
        return view('admin.operasional.manajemen_stok.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_bahan'   => 'required|string|max:50|unique:bahan_baku,nama_bahan',
            'satuan'       => 'required|string|max:20',
            'stok_minimum' => 'required|integer|min:0|max:99999999',
            'stok_awal'    => 'nullable|integer|min:0|max:99999999',
            'foto'         => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $stokAwal = round((float) ($validated['stok_awal'] ?? 0), 4);
        $foto = $request->hasFile('foto') ? $request->file('foto')->store('bahan_baku', 'public') : null;

        DB::transaction(function () use ($validated, $stokAwal, $foto) {
            $bahan = BahanBaku::create([
                'nama_bahan'    => $validated['nama_bahan'],
                'satuan'        => $validated['satuan'],
                'foto'          => $foto,
                'stok_minimum'  => $validated['stok_minimum'],
                'stok_saat_ini' => $stokAwal,
            ]);
            $bahan->sinkronStatus();

            // Stok awal dicatat sebagai stok masuk supaya riwayatnya lengkap
            if ($stokAwal > 0) {
                RiwayatStok::create([
                    'bahan_baku_id' => $bahan->id,
                    'jenis'         => 'masuk',
                    'jumlah'        => $stokAwal,
                    'tanggal'       => now()->toDateString(),
                    'user_id'       => auth()->id(),
                    'stok_sesudah'  => $stokAwal,
                    'keterangan'    => 'Stok awal',
                ]);
            }
        });

        return redirect()->route('bahan_baku.index')
            ->with('success', 'Bahan baku berhasil ditambahkan.');
    }

    public function edit(BahanBaku $bahanBaku)
    {
        return view('admin.operasional.manajemen_stok.edit', compact('bahanBaku'));
    }

    /**
     * Update data bahan. Jumlah stok TIDAK diubah di sini,
     * hanya lewat catat stok masuk/keluar biar riwayatnya selalu cocok.
     */
    public function update(Request $request, BahanBaku $bahanBaku)
    {
        $validated = $request->validate([
            'nama_bahan'   => ['required', 'string', 'max:50',
                Rule::unique('bahan_baku', 'nama_bahan')->ignore($bahanBaku->id)],
            'satuan'       => 'required|string|max:20',
            'stok_minimum' => 'required|integer|min:0|max:99999999',
            'foto'         => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'hapus_foto'   => 'nullable|boolean',
        ]);

        $fotoLama = $bahanBaku->foto;
        $data = collect($validated)->only(['nama_bahan', 'satuan', 'stok_minimum'])->all();

        if ($request->hasFile('foto')) {
            $data['foto'] = $request->file('foto')->store('bahan_baku', 'public');
        } elseif ($request->boolean('hapus_foto')) {
            $data['foto'] = null;
        }

        $bahanBaku->update($data);
        if ($fotoLama && array_key_exists('foto', $data)) {
            Storage::disk('public')->delete($fotoLama);
        }
        $bahanBaku->sinkronStatus();

        return redirect()->route('bahan_baku.index')
            ->with('success', 'Bahan baku berhasil diperbarui.');
    }

    public function destroy(BahanBaku $bahanBaku)
    {
        // Riwayat stok bahan ini ikut terhapus (cascade di database)
        $foto = $bahanBaku->foto;
        $bahanBaku->delete();
        if ($foto) {
            Storage::disk('public')->delete($foto);
        }

        return redirect()->route('bahan_baku.index')
            ->with('success', 'Bahan baku berhasil dihapus.');
    }

    /** Stok MASUK: barang datang dari langganan/pemasok. */
    public function stokMasuk(Request $request)
    {
        return $this->catatStok($request, 'masuk');
    }

    /** Stok KELUAR biasa (rusak, dibuang, dll). Untuk memberi bahan ke kasir pakai "Beri Paket". */
    public function stokKeluar(Request $request)
    {
        return $this->catatStok($request, 'keluar');
    }

    /**
     * Satu-satunya tempat stok berubah. Dipakai stok masuk & keluar:
     * ubah stok_saat_ini + tulis 1 baris riwayat dalam satu transaksi.
     */
    private function catatStok(Request $request, string $jenis)
    {
        $masuk = $jenis === 'masuk';

        $rules = [
            'bahan_baku_id' => 'required|exists:bahan_baku,id',
            'jumlah'        => 'required|integer|min:1|max:99999999',
            'tanggal'       => 'required|date',
            'keterangan'    => 'nullable|string|max:255',
        ];

        $rules += $masuk
            ? ['supplier' => 'nullable|string|max:100']
            : [];

        $validated = $request->validate($rules);
        $jumlah    = (float) $validated['jumlah'];

        $bahan = DB::transaction(function () use ($validated, $jumlah, $masuk, $jenis) {
            $bahan = BahanBaku::lockForUpdate()->findOrFail($validated['bahan_baku_id']);

            // Stok keluar tidak boleh melebihi stok yang ada
            if (! $masuk && $jumlah > (float) $bahan->stok_saat_ini) {
                throw ValidationException::withMessages([
                    'jumlah' => 'Stok ' . $bahan->nama_bahan . ' hanya tersisa ' .
                        $bahan->stok_format . ' ' . $bahan->satuan . '.',
                ]);
            }

            $stokBaru = round((float) $bahan->stok_saat_ini + ($masuk ? $jumlah : -$jumlah), 4);
            $bahan->stok_saat_ini = $stokBaru;
            $bahan->sinkronStatus();

            RiwayatStok::create([
                'bahan_baku_id' => $bahan->id,
                'jenis'         => $jenis,
                'jumlah'        => $jumlah,
                'tanggal'       => $validated['tanggal'],
                'user_id'       => auth()->id(),
                'supplier'      => $masuk ? ($validated['supplier'] ?? null) : null,
                'kasir_id'      => null,
                'stok_sesudah'  => $stokBaru,
                'keterangan'    => $validated['keterangan'] ?? null,
            ]);

            return $bahan;
        });

        return redirect()->route('bahan_baku.index')->with('success',
            'Stok ' . $jenis . ' dicatat: ' . $bahan->nama_bahan . ' ' .
            ($masuk ? '+' : '-') . BahanBaku::formatAngka($jumlah) . ' ' . $bahan->satuan .
            '. Stok sekarang ' . $bahan->stok_format . ' ' . $bahan->satuan . '.'
        );
    }

    /**
     * Beri PAKET ke kasir: ayam per ukuran (kg), tepung premix basah (botol 1 liter) & tepung roti (bungkus 1 kg), filling/saus/bumbu (bungkus),
     * kemasan per ukuran. Semua bilangan bulat.
     * Minyak & gas TIDAK ikut paket: lewat alur permintaan (kasir minta -> admin kirim -> kasir konfirmasi).
     * Gudang berkurang per bahan; stok kasir bertambah dalam pcs per ukuran + kali pakai.
     */
    public function beriPaket(Request $request, StokOtomatisService $stok)
    {
        $v = $request->validate([
            'kasir_id'  => ['required', Rule::exists('users', 'id')->where('role', 'kasir')],
            'tanggal'   => 'required|date',
            'catatan'   => 'nullable|string|max:150',
            'kg'        => 'nullable|array',
            'kg.*'      => 'nullable|integer|min:0|max:9999',
            'premix_basah' => 'nullable|integer|min:0|max:9999',
            'tepung_roti'  => 'nullable|integer|min:0|max:9999',
            'bungkus'   => 'nullable|array',
            'bungkus.*' => 'nullable|integer|min:0|max:9999',
            'kemasan'   => 'nullable|array',
            'kemasan.*' => 'nullable|integer|min:0|max:99999',
        ]);

        try {
            $hasil = $stok->beriPaket(
                (int) $v['kasir_id'], $v['tanggal'], $v['kg'] ?? [], $v['bungkus'] ?? [],
                $v['kemasan'] ?? [],
                (int) auth()->id(), $v['catatan'] ?? null,
                ['premix_basah' => (int) ($v['premix_basah'] ?? 0), 'tepung_roti' => (int) ($v['tepung_roti'] ?? 0)]
            );
        } catch (ValidationException $e) {
            return back()->withInput(array_merge($request->all(), ['_modal' => 'paket']))->withErrors($e->errors());
        }

        return redirect()->route('bahan_baku.index')->with('success',
            'Paket diberikan ke ' . $hasil['kasir'] . ($hasil['pcs'] > 0 ? ': ±' . $hasil['pcs'] . ' pcs produk' : '') . '. Stok dapur sudah dikurangi.'
        );
    }

    /**
     * Kirim MINYAK & GAS ke kasir (jatah tetap: pilih minyak dan/atau gas). Kasir harus mengonfirmasi "Sudah Diterima"
     * dulu, baru stok gudang berkurang dan tercatat di Riwayat Stok.
     */
    public function kirimMinyakGas(Request $request, StokOtomatisService $stok)
    {
        $v = $request->validate([
            'kasir_id' => ['required', Rule::exists('users', 'id')->where('role', 'kasir')],
            'catatan'  => 'nullable|string|max:150',
            'minyak'   => 'nullable|boolean',
            'gas'      => 'nullable|boolean',
        ]);

        // Jumlah tidak diketik: yang dikirim selalu sebesar jatah tetap (Pengaturan Stok)
        $porsi = $stok->porsiKirim();
        $minyak = $request->boolean('minyak') ? $porsi['minyak'] : 0;
        $gas    = $request->boolean('gas') ? $porsi['gas'] : 0;

        try {
            $hasil = $stok->kirimKeKasir(
                (int) $v['kasir_id'], $minyak, $gas,
                (int) auth()->id(), $v['catatan'] ?? null
            );
        } catch (ValidationException $e) {
            return back()->withInput(array_merge($request->all(), ['_modal' => 'kirim']))->withErrors($e->errors());
        }

        $isi = array_filter([
            $hasil['minyak'] > 0 ? $hasil['minyak'] . ' jerigen minyak' : null,
            $hasil['gas'] > 0 ? $hasil['gas'] . ' tabung gas' : null,
        ]);

        return redirect()->route('bahan_baku.index')->with('success',
            'Dikirim ke ' . $hasil['kasir'] . ': ' . implode(' + ', $isi) . '. Stok dapur berkurang setelah kasir menekan "Sudah Diterima".'
        );
    }
}
