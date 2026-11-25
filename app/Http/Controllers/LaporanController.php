<?php

namespace App\Http\Controllers;

use App\Models\BahanBaku;
use App\Models\RiwayatStok;
use App\Models\Transaksi;
use App\Models\Ukuran;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LaporanController extends Controller
{
    private const JENIS = [
        'penjualan' => 'Laporan Penjualan',
        'stok'      => 'Laporan Stok',
    ];

    /** Pilihan periode: kunci => [label, ikon bootstrap-icons] */
    private const PERIODE = [
        'harian'   => ['Harian',   'bi-calendar-day'],
        'mingguan' => ['Mingguan', 'bi-calendar-week'],
        'bulanan'  => ['Bulanan',  'bi-calendar-month'],
        'custom'   => ['Custom',   'bi-calendar-range'],
    ];

    /** Halaman laporan: SATU tampilan utama, jenis laporan dipilih dari tab di atas. */
    public function index(Request $request)
    {
        $r = $this->susun($request);

        return view('admin.analisis.laporan.index', $r + [
            'jenisList'   => self::JENIS,
            'periodeList' => self::PERIODE,
            'ukuranList'  => Ukuran::orderBy('nama_ukuran')->get(['id', 'nama_ukuran']),
        ]);
    }

    /** Ekspor: format = excel | pdf | print (memakai filter yang sama dengan halaman laporan). */
    public function export(Request $request)
    {
        $request->validate(['format' => 'required|in:excel,pdf,print']);
        $r = $this->susun($request);
        $nama = 'laporan-' . $r['jenis'] . '-' . now()->format('Ymd-His');

        if ($request->format === 'excel') {
            // File .xls berisi tabel HTML: langsung terbuka di Excel/LibreOffice tanpa library tambahan.
            $html = view('admin.analisis.laporan.cetak', $r + ['mode' => 'excel'])->render();

            return response($html, 200, [
                'Content-Type'        => 'application/vnd.ms-excel; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $nama . '.xls"',
                'Cache-Control'       => 'max-age=0',
            ]);
        }

        if ($request->format === 'pdf' && class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.analisis.laporan.cetak', $r + ['mode' => 'pdf'])
                ->setPaper('a4', $r['jenis'] === 'penjualan' ? 'landscape' : 'portrait')
                ->download($nama . '.pdf');
        }

        // Cetak, atau PDF tanpa paket dompdf: buka halaman siap cetak (pilih "Simpan sebagai PDF" di dialog cetak).
        return view('admin.analisis.laporan.cetak', $r + ['mode' => 'print', 'autoPrint' => true, 'sebagaiPdf' => $request->format === 'pdf']);
    }

    // ------------------------------------------------------------------
    // Penyusun data (dipakai halaman & ekspor supaya isinya selalu sama)
    // ------------------------------------------------------------------

    private function susun(Request $request): array
    {
        $request->validate([
            'jenis'   => 'nullable|string',
            'periode' => 'nullable|in:' . implode(',', array_keys(self::PERIODE)),
            'tanggal' => 'nullable|date',
            'bulan'   => 'nullable|date_format:Y-m',
            'dari'    => 'nullable|date',
            'sampai'  => 'nullable|date',
            'varian'  => 'nullable|integer',
            'metode'  => 'nullable|in:cash,qris',
        ]);

        // Jenis yang tidak dikenal (mis. link lama "restok") otomatis jadi penjualan
        $jenis = (string) $request->input('jenis');
        if (! array_key_exists($jenis, self::JENIS)) {
            $jenis = 'penjualan';
        }

        $periode = $request->input('periode', 'harian');
        [$dari, $sampai] = $this->rentang($request, $periode);

        if ($dari->diffInDays($sampai) > 366) { // batasi supaya tabel tetap ringan
            $dari = $sampai->copy()->subDays(366);
        }

        $hasil = match ($jenis) {
            'stok'  => $this->stok($dari, $sampai),
            default => $this->penjualan($dari, $sampai, $request->input('varian'), $request->input('metode')),
        };

        return $hasil + [
            'jenis'   => $jenis,
            'judul'   => self::JENIS[$jenis],
            'periode' => $periode,
            'dari'    => $dari,
            'sampai'  => $sampai,
        ];
    }

    /**
     * Ubah pilihan periode menjadi [tanggal awal, tanggal akhir]. Tanpa pilihan tanggal, hasilnya selalu
     * mengacu ke HARI INI:
     *   harian   = hari ini
     *   mingguan = 7 hari terakhir (6 hari lalu s/d hari ini)
     *   bulanan  = bulan ini (tanggal 1 s/d hari ini)
     * Tanggal yang dipilih sendiri tidak boleh melewati hari ini (belum ada datanya).
     */
    private function rentang(Request $request, string $periode): array
    {
        $hariIni = today();

        // Tanggal acuan untuk harian & mingguan (default hari ini, maksimal hari ini)
        $acuan = $request->filled('tanggal') ? Carbon::parse($request->tanggal)->startOfDay() : $hariIni->copy();
        if ($acuan->gt($hariIni)) {
            $acuan = $hariIni->copy();
        }

        switch ($periode) {
            case 'mingguan': // 7 hari yang berakhir di tanggal acuan
                return [$acuan->copy()->subDays(6), $acuan->copy()];

            case 'bulanan': // tanggal 1 s/d akhir bulan (bulan berjalan: s/d hari ini)
                $bulan = $request->filled('bulan')
                    ? Carbon::createFromFormat('!Y-m', $request->bulan)
                    : $hariIni->copy()->startOfMonth();
                if ($bulan->gt($hariIni)) {
                    $bulan = $hariIni->copy()->startOfMonth();
                }
                $akhir = $bulan->copy()->endOfMonth()->startOfDay();

                return [$bulan->copy(), $akhir->gt($hariIni) ? $hariIni->copy() : $akhir];

            case 'custom': // bebas pilih dari - sampai
                $dari   = $request->filled('dari') ? Carbon::parse($request->dari)->startOfDay() : $hariIni->copy()->startOfMonth();
                $sampai = $request->filled('sampai') ? Carbon::parse($request->sampai)->startOfDay() : $hariIni->copy();
                if ($dari->gt($sampai)) {
                    [$dari, $sampai] = [$sampai, $dari];
                }
                if ($sampai->gt($hariIni)) {
                    $sampai = $hariIni->copy();
                }
                if ($dari->gt($sampai)) {
                    $dari = $sampai->copy();
                }

                return [$dari, $sampai];

            default: // harian
                return [$acuan->copy(), $acuan->copy()];
        }
    }

    private function penjualan(Carbon $dari, Carbon $sampai, $varianId, $metode): array
    {
        $trx = Transaksi::berhasil()
            ->with(['kasir', 'items.ukuran', 'items.filling', 'items.sausCelup', 'items.bumbuTabur'])
            ->antara($dari, $sampai)
            ->when($metode, fn ($q) => $q->where('metode_bayar', $metode))
            ->when($varianId, fn ($q) => $q->whereHas('items', fn ($i) => $i->where('ukuran_id', $varianId)))
            ->orderBy('tanggal')->orderBy('id')
            ->get();

        $totalItem = 0;
        $totalRupiah = 0;
        $baris = [];

        foreach ($trx as $i => $t) {
            // Kalau difilter per varian, hitung hanya porsi varian itu
            $items = $varianId ? $t->items->where('ukuran_id', (int) $varianId) : $t->items;
            $jumlah = $items->count();
            $nilai  = $varianId ? (int) $items->sum(fn ($it) => $it->harga) : (int) $t->total_harga;

            $ringkas = $items->groupBy(fn ($it) => implode('|', [$it->ukuran_id, $it->filling_id, $it->cara_luar, $it->saus_celup_id, $it->bumbu_tabur_id]))
                ->map(function ($g) {
                    $it = $g->first();
                    $rasa = $it->cara_luar === 'saus_celup' ? ($it->sausCelup->nama_saus ?? '-') : ($it->bumbuTabur->nama_bumbu ?? '-');

                    return $g->count() . 'x ' . ($it->ukuran->nama_ukuran ?? '-') . ' ' . ($it->filling->nama_filling ?? '-') . ' (' . $rasa . ')';
                })->implode('; ');

            $totalItem   += $jumlah;
            $totalRupiah += $nilai;

            $baris[] = [
                $i + 1,
                $t->tanggal->format('d/m/Y H:i'),
                $t->kode,
                $t->kasir->name ?? '-',
                $t->nama_pembeli ?: 'Umum',
                $ringkas,
                $jumlah,
                strtoupper($t->metode_bayar),
                $nilai,
            ];
        }

        return [
            'ringkasan' => [
                ['Total Transaksi', $trx->count(), 'angka'],
                ['Total Item Terjual', $totalItem, 'angka'],
                ['Total Pendapatan', $totalRupiah, 'rupiah'],
            ],
            'kolom'  => ['No', 'Tanggal & Jam', 'No. Transaksi', 'Kasir', 'Pembeli', 'Pesanan', 'Item', 'Metode', 'Total'],
            'tipe'   => ['no', 'teks', 'teks', 'teks', 'teks', 'teks', 'angka', 'teks', 'rupiah'],
            'baris'  => $baris,
            'kosong' => 'Tidak ada penjualan pada filter ini.',
        ];
    }

    /**
     * Laporan stok GUDANG per PERIODE (bukan stok saat ini):
     *   Stok Awal  = stok gudang di awal periode
     *   Masuk      = barang datang ke gudang pada periode
     *   Keluar     = barang keluar dari gudang pada periode (paket ke kasir, stok keluar manual)
     *   Stok Akhir = stok gudang di akhir periode
     *
     * Penjualan TIDAK dihitung di sini: penjualan memakai stok yang sudah dipegang kasir (sudah keluar
     * dari gudang lewat paket), jadi tidak mengubah stok gudang. Kalau ikut dihitung, angka stok
     * awal/akhir jadi salah. Stok awal & akhir dihitung mundur dari stok gudang sekarang.
     */
    private function stok(Carbon $dari, Carbon $sampai): array
    {
        $awalPeriode  = $dari->toDateString();
        $akhirPeriode = $sampai->toDateString();

        // Hanya pergerakan yang benar-benar mengubah stok gudang
        $gudang = fn () => RiwayatStok::query()->where(function ($q) {
            $q->whereNull('sumber')->orWhere('sumber', '!=', 'penjualan');
        });

        // Pergerakan stok DI DALAM periode
        $dalam = $gudang()
            ->whereBetween('tanggal', [$awalPeriode, $akhirPeriode])
            ->selectRaw('bahan_baku_id, jenis, SUM(jumlah) as total')
            ->groupBy('bahan_baku_id', 'jenis')
            ->get()
            ->groupBy('bahan_baku_id');

        // Pergerakan stok SESUDAH periode (dipakai untuk menghitung mundur ke stok akhir periode)
        $sesudah = $gudang()
            ->where('tanggal', '>', $akhirPeriode)
            ->selectRaw('bahan_baku_id, jenis, SUM(jumlah) as total')
            ->groupBy('bahan_baku_id', 'jenis')
            ->get()
            ->groupBy('bahan_baku_id');

        // Tanggal pertama kali bahan punya riwayat stok
        $pertama = $gudang()
            ->selectRaw('bahan_baku_id, MIN(tanggal) as pertama')
            ->groupBy('bahan_baku_id')
            ->pluck('pertama', 'bahan_baku_id');

        $baris = [];
        $no = 0;

        foreach (BahanBaku::orderBy('nama_bahan')->get() as $b) {
            // Lewati bahan yang belum ada pada periode ini (dibuat / baru dicatat stoknya sesudah periode)
            $sudahAdaSejak = $b->created_at && $b->created_at->copy()->startOfDay()->lte($sampai);
            $sudahPunyaRiwayat = isset($pertama[$b->id]) && Carbon::parse($pertama[$b->id])->lte($sampai);
            if (! $sudahAdaSejak && ! $sudahPunyaRiwayat) {
                continue;
            }

            $g = $dalam->get($b->id, collect());
            $masuk  = (int) ($g->firstWhere('jenis', 'masuk')->total ?? 0);
            $keluar = (int) ($g->firstWhere('jenis', 'keluar')->total ?? 0);

            $s = $sesudah->get($b->id, collect());
            $bersihSesudah = (int) ($s->firstWhere('jenis', 'masuk')->total ?? 0)
                           - (int) ($s->firstWhere('jenis', 'keluar')->total ?? 0);

            $stokAkhir = (int) $b->stok_saat_ini - $bersihSesudah;
            $stokAwal  = $stokAkhir - $masuk + $keluar;

            $status = $stokAkhir <= 0 ? 'Habis' : ($stokAkhir <= (int) $b->stok_minimum ? 'Menipis' : 'Aman');

            $baris[] = [
                ++$no,
                $b->nama_bahan,
                $b->satuan,
                $stokAwal,
                $masuk,
                $keluar,
                $stokAkhir,
                $status,
            ];
        }

        $jumlahStatus = collect($baris)->countBy(fn ($r) => $r[7]);

        return [
            'ringkasan' => [
                ['Jenis Bahan Baku', count($baris), 'angka'],
                ['Stok Menipis', $jumlahStatus['Menipis'] ?? 0, 'angka'],
                ['Stok Habis', $jumlahStatus['Habis'] ?? 0, 'angka'],
            ],
            'kolom'  => ['No', 'Bahan Baku', 'Satuan', 'Stok Awal', 'Stok Masuk', 'Stok Keluar', 'Stok Akhir', 'Status'],
            'tipe'   => ['no', 'teks', 'teks', 'angka', 'angka', 'angka', 'angka', 'status'],
            'baris'  => $baris,
            'kosong' => 'Belum ada data stok pada periode ini.',
            'catatan' => 'Ini stok gudang. Stok Keluar = paket ke kasir + stok keluar manual. Penjualan tidak dihitung karena memakai stok yang sudah dipegang kasir (lihat menu Stok Kasir). Stok awal & akhir dihitung dari riwayat stok pada periode terpilih.',
        ];
    }
}
