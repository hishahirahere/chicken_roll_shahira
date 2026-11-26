<?php

namespace App\Http\Controllers;

use App\Models\BahanBaku;
use App\Models\RiwayatStok;
use App\Models\User;
use App\Services\StokOtomatis\DataAwalStokService;
use App\Services\StokOtomatis\MinyakGasService;
use App\Services\StokOtomatis\PaketKasirService;
use App\Services\StokOtomatis\PengaturanStokService;
use App\Services\StokOtomatis\SinkronFotoMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BahanBakuController extends Controller
{
    public function index()
    {
        // mengenali bahan utama dan menghubungkan filling/saus/bumbu secara otomatis
        app(DataAwalStokService::class)->siapkan((int) auth()->id());

        // menyamakan foto dari Data Master ke Manajemen Stok, jadi tidak diisi dua kali
        SinkronFotoMaster::semua();

        //  daftar semua bahan baku untuk tabel di halaman
        $bahanBaku = BahanBaku::orderBy('nama_bahan')->get();

        // pilihan kasir di form "Beri Paket" dan "Beri Bahan Pendukung" dan "Kirim Minyak & Gas"
        $kasirList = User::where('role', 'kasir')
            ->where('status', 'aktif')
            ->orderBy('name')
            ->get();

        // Bahan untuk form "Beri Paket"
        $pengaturanStok = app(PengaturanStokService::class);
        $paketKasir     = app(PaketKasirService::class);
        $minyakGas      = app(MinyakGasService::class);
        $set = $pengaturanStok->pengaturan(); // angka pengaturan stok (kali pakai, kemasan, dan lain-lain)
        $ukuranPaket = DB::table('ukuran')->where('status', 'aktif')->orderBy('harga')->get();
        $standarPaket = $pengaturanStok->standar();
        $kgPerPaket = $paketKasir->kgPerPaket($set);
        $nonaktif = collect()
            ->merge(DB::table('filling')->where('status', 'nonaktif')->pluck('bahan_baku_id'))
            ->merge(DB::table('saus_celup')->where('status', 'nonaktif')->pluck('bahan_baku_id'))
            ->merge(DB::table('bumbu_tabur')->where('status', 'nonaktif')->pluck('bahan_baku_id'))
            ->filter()->all();
        $bahanPaket = DB::table('bahan_baku')->whereIn('peran', ['filling', 'saus', 'bumbu'])->orderBy('nama_bahan')->get()
            ->reject(fn ($b) => in_array($b->id, $nonaktif))
            ->each(fn ($b) => $b->kap = $pengaturanStok->kapasitasBungkus($b, $set))
            ->groupBy('peran');

        // Stok gudang kemasan tiap ukuran: [ukuran_id => stok]
        $stokKemasan = DB::table('ukuran')->join('bahan_baku', 'bahan_baku.id', '=', 'ukuran.bahan_baku_id')
            ->pluck('bahan_baku.stok_saat_ini', 'ukuran.id')->all();

        // Foto kemasan tiap ukuran: [ukuran_id => foto] diambil dari foto BAHAN BAKU kemasan (bukan foto produk ukuran)
        $fotoKemasan = DB::table('ukuran')->join('bahan_baku', 'bahan_baku.id', '=', 'ukuran.bahan_baku_id')
            ->pluck('bahan_baku.foto', 'ukuran.id')->all();

        $premixBasah = DB::table('bahan_baku')->where('peran', 'tepung_premix_basah')->orderBy('id')->first();
        $tepungRoti  = DB::table('bahan_baku')->where('peran', 'tepung_roti')->orderBy('id')->first();

        $gasBahan   = DB::table('bahan_baku')->where('peran', 'gas')->orderBy('id')->first();
        $minyakBahan = DB::table('bahan_baku')->where('peran', 'minyak')->orderBy('id')->first();

        // Form Kirim Minyak & Gas: isi awal standar, atau mengikuti permintaan kasir yang masuk
        $kirimStandar = $minyakGas->porsiKirim($set); // jatah tetap sekali kirim: minyak (jerigen) & gas (tabung) dari Pengaturan Stok
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
            'kirimStandar', 'permintaanKasir', 'kgPerPaket', 'bahanBaku', 'kasirList', 'set', 'ukuranPaket', 'standarPaket', 'bahanPaket', 'gasBahan', 'minyakBahan', 'stokKemasan', 'fotoKemasan', 'premixBasah', 'tepungRoti'
        ));
    }

    public function create(Request $request)
    {
        // kelompok awal diambil dari tab yang sedang dibuka di halaman Manajemen Stok
        $grupAwal = in_array($request->query('grup'), ['utama', 'operasional'], true)
            ? $request->query('grup') : 'utama';

        return view('admin.operasional.manajemen_stok.create', compact('grupAwal'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_bahan'   => 'required|string|max:50|unique:bahan_baku,nama_bahan',
            'satuan'       => 'required|string|max:20',
            'kelompok'     => 'required|in:utama,operasional',
            'stok_minimum' => 'required|integer|min:0|max:99999999',
            'stok_awal'    => 'nullable|integer|min:0|max:99999999',
            'foto'         => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $stokAwal = (int) ($validated['stok_awal'] ?? 0);
        $foto = $request->hasFile('foto') ? $request->file('foto')->store('bahan_baku', 'public') : null;

        DB::transaction(function () use ($validated, $stokAwal, $foto) {
            $bahan = BahanBaku::create([
                'nama_bahan'    => $validated['nama_bahan'],
                'satuan'        => $validated['satuan'],
                'foto'          => $foto,
                'peran'         => $validated['kelompok'], // menentukan tab di Manajemen Stok
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

        // balik ke tab yang sama dengan kelompok bahan yang baru ditambah
        return redirect()->route('bahan_baku.index', ['grup' => $validated['kelompok']])
            ->with('success', 'Bahan baku berhasil ditambahkan.');
    }

    public function edit(BahanBaku $bahanBaku)
    {
        return view('admin.operasional.manajemen_stok.edit', compact('bahanBaku'));
    }

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
        if ($fotoLama && array_key_exists('foto', $data) && ! SinkronFotoMaster::dipakaiMaster($fotoLama)) {
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
        if ($foto && ! SinkronFotoMaster::dipakaiMaster($foto)) {
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
        $jumlah    = (int) $validated['jumlah'];

        $bahan = DB::transaction(function () use ($validated, $jumlah, $masuk, $jenis) {
            $bahan = BahanBaku::lockForUpdate()->findOrFail($validated['bahan_baku_id']);

            // Stok keluar tidak boleh melebihi stok yang ada
            if (! $masuk && $jumlah > (int) $bahan->stok_saat_ini) {
                throw ValidationException::withMessages([
                    'jumlah' => 'Stok ' . $bahan->nama_bahan . ' hanya tersisa ' .
                        $bahan->stok_format . ' ' . $bahan->satuan . '.',
                ]);
            }

            $stokBaru = (int) $bahan->stok_saat_ini + ($masuk ? $jumlah : -$jumlah);
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

    public function beriPaket(Request $request, PaketKasirService $paket)
    {
        $v = $request->validate([
            'kasir_id'  => ['required', Rule::exists('users', 'id')->where('role', 'kasir')],
            'tanggal'   => 'required|date',
            'catatan'   => 'nullable|string|max:150',
            'kg'        => 'nullable|array',
            'kg.*'      => 'nullable|integer|min:0|max:9999',
        ]);

        try {
            $hasil = $paket->beriPaketAyam(
                (int) $v['kasir_id'], $v['tanggal'], $v['kg'] ?? [],
                (int) auth()->id(), $v['catatan'] ?? null
            );
        } catch (ValidationException $e) {
            return back()->withInput(array_merge($request->all(), ['_modal' => 'paket']))->withErrors($e->errors());
        }

        return redirect()->route('bahan_baku.index')->with('success',
            'Paket ayam diberikan ke ' . $hasil['kasir'] . ': ' . $hasil['paket'] . ' paket (±' . $hasil['pcs'] . ' pcs produk). Stok dapur sudah dikurangi.'
        );
    }

    public function beriBahanPendukung(Request $request, PaketKasirService $paket)
    {
        $v = $request->validate([
            'kasir_id'  => ['required', Rule::exists('users', 'id')->where('role', 'kasir')],
            'tanggal'   => 'required|date',
            'catatan'   => 'nullable|string|max:150',
            'bungkus'   => 'nullable|array',
            'bungkus.*' => 'nullable|integer|min:0|max:9999',
        ]);

        try {
            $hasil = $paket->beriBahanPendukung((int) $v['kasir_id'], $v['tanggal'], $v['bungkus'] ?? [], (int) auth()->id(), $v['catatan'] ?? null);
        } catch (ValidationException $e) {
            return back()->withInput(array_merge($request->all(), ['_modal' => 'pendukung']))->withErrors($e->errors());
        }

        return redirect()->route('bahan_baku.index')->with('success',
            'Bahan pendukung diberikan ke ' . $hasil['kasir'] . '. Stok dapur sudah dikurangi, stok kasir bertambah dalam kali pakai.'
        );
    }

    public function kirimMinyakGas(Request $request, MinyakGasService $minyakGas)
    {
        $v = $request->validate([
            'kasir_id' => ['required', Rule::exists('users', 'id')->where('role', 'kasir')],
            'catatan'  => 'nullable|string|max:150',
            'minyak'   => 'nullable|boolean',
            'gas'      => 'nullable|boolean',
        ]);

        // Jumlah tidak diketik: yang dikirim selalu sebesar jatah tetap (Pengaturan Stok)
        $porsi = $minyakGas->porsiKirim();
        $minyak = $request->boolean('minyak') ? $porsi['minyak'] : 0;
        $gas    = $request->boolean('gas') ? $porsi['gas'] : 0;

        try {
            $hasil = $minyakGas->kirimKeKasir(
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
