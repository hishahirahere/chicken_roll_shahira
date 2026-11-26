<?php

namespace App\Services\StokOtomatis;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Minyak & gas manual: kasir catat pemakaian / minta, admin kirim, kasir konfirmasi.
 */
class MinyakGasService
{
    public function __construct(
        private PengaturanStokService $pengaturan,
        private DataAwalStokService $dataAwal,
        private StokKasirWriter $writer,
        private StokKasirQuery $query,
    ) {}

    /** Peran bahan yang dikelola manual (tidak berkurang otomatis dari penjualan). */
    public const PERAN_MANUAL = ['minyak', 'gas'];

    public function catatPemakaian(int $kasirId, array $jumlah, string $tanggal, ?string $catatan = null): array
    {
        $jumlah = array_filter(array_map('intval', $jumlah), fn ($v) => $v > 0);
        $jumlah = array_intersect_key($jumlah, array_flip(self::PERAN_MANUAL));
        if (! $jumlah) {
            throw ValidationException::withMessages(['pemakaian' => 'Isi minimal pemakaian minyak atau gas.']);
        }

        return DB::transaction(function () use ($kasirId, $jumlah, $tanggal, $catatan) {
            $dicatat = [];
            $peringatan = [];
            foreach ($jumlah as $peran => $n) {
                $bahan = DB::table('bahan_baku')->where('peran', $peran)->orderBy('id')->first();
                if (! $bahan) {
                    throw ValidationException::withMessages(['pemakaian' => 'Bahan ' . DataAwalStokService::PERAN_UTAMA[$peran][0] . ' belum tersedia di data bahan baku.']);
                }

                $row  = $this->writer->barisStokKasir($kasirId, (int) $bahan->id);
                $stok = (int) $row->stok;
                if ($n > $stok) {
                    $peringatan[] = 'Stok ' . $bahan->nama_bahan . ' di catatanmu hanya ' . $stok . ' ' . $bahan->satuan
                        . ', pemakaian ' . $n . ' tetap dicatat. Jangan lupa minta ke admin.';
                }
                DB::table('stok_kasir')->where('id', $row->id)->update(['stok' => max(0, $stok - $n), 'updated_at' => now()]);
                if ($n <= $stok && $stok - $n === 0) {
                    $peringatan[] = $bahan->nama_bahan . ' kamu sudah habis. Silakan minta jatah baru ke admin.';
                }

                $this->writer->tulisRiwayatKasir($kasirId, (int) $bahan->id, 'keluar', 'pemakaian', $n, max(0, $stok - $n),
                    'Pemakaian manual', $catatan, $tanggal);
                DB::table('pemakaian_kasir')->insert([
                    'user_id' => $kasirId, 'bahan_baku_id' => $bahan->id, 'jumlah' => $n, 'tanggal' => $tanggal,
                    'catatan' => $catatan ? mb_substr($catatan, 0, 255) : null, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $dicatat[] = $n . ' ' . $bahan->satuan . ' ' . strtolower($bahan->nama_bahan);
            }

            return ['dicatat' => $dicatat, 'peringatan' => $peringatan];
        });
    }

    public function porsiKirim(?array $set = null): array
    {
        $set ??= $this->pengaturan->pengaturan();

        return [
            'minyak' => max(1, (int) $set['paket_minyak_jerigen']),
            'gas'    => max(1, (int) $set['paket_gas_tabung']),
        ];
    }

    public function buatPermintaan(int $kasirId, array $jumlah, ?string $catatan = null): int
    {
        $jumlah = array_intersect_key($jumlah, array_flip(self::PERAN_MANUAL));
        if (! $jumlah) {
            throw ValidationException::withMessages(['permintaan' => 'Pilih minyak atau gas yang diminta.']);
        }

        $porsi = $this->porsiKirim();
        $stokKasir = $this->query->stokKasirUntuk($kasirId);

        return DB::transaction(function () use ($kasirId, $jumlah, $catatan, $porsi, $stokKasir) {
            $dibuat = 0;
            foreach ($jumlah as $peran => $n) {
                $bahan = DB::table('bahan_baku')->where('peran', $peran)->orderBy('id')->first();
                if (! $bahan) {
                    throw ValidationException::withMessages(['permintaan' => 'Bahan ' . DataAwalStokService::PERAN_UTAMA[$peran][0] . ' belum tersedia di data bahan baku.']);
                }

                // Jatah dikirim ulang hanya kalau stok di tangan kasir sudah habis (0)
                $milik = $stokKasir->firstWhere('peran', $peran);
                if ($milik && (int) $milik->jumlah > 0) {
                    throw ValidationException::withMessages(['permintaan' => 'Stok ' . strtolower($bahan->nama_bahan) . ' kamu masih ' . (int) $milik->jumlah . ' ' . $bahan->satuan
                        . '. Minta baru bisa kalau sudah habis (0).']);
                }

                $masihAda = DB::table('permintaan_stok')->where('kasir_id', $kasirId)->where('bahan_baku_id', $bahan->id)
                    ->whereIn('status', ['diminta', 'dikirim'])->exists();
                if ($masihAda) {
                    throw ValidationException::withMessages(['permintaan' => 'Permintaan ' . strtolower($bahan->nama_bahan) . ' sebelumnya masih berjalan. Tunggu sampai selesai atau batalkan dulu.']);
                }

                DB::table('permintaan_stok')->insert([
                    'kasir_id' => $kasirId, 'bahan_baku_id' => $bahan->id,
                    'jumlah_diminta' => $porsi[$peran],
                    'status' => 'diminta', 'catatan' => $catatan ? mb_substr($catatan, 0, 150) : null,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $dibuat++;
            }

            return $dibuat;
        });
    }

    public function kirimKeKasir(int $kasirId, int $minyak, int $gas, int $adminId, ?string $catatan = null): array
    {
        $this->dataAwal->siapkan($adminId);

        $jumlah = array_filter(['minyak' => $minyak, 'gas' => $gas], fn ($v) => $v > 0);
        if (! $jumlah) {
            throw ValidationException::withMessages(['kirim' => 'Pilih minimal minyak atau gas.']);
        }

        return DB::transaction(function () use ($kasirId, $jumlah, $adminId, $catatan, $minyak, $gas) {
            $kasir = DB::table('users')->where('id', $kasirId)->where('role', 'kasir')->first();
            if (! $kasir) {
                throw ValidationException::withMessages(['kasir_id' => 'Kasir tidak ditemukan.']);
            }

            $galat = [];
            $rencana = [];
            foreach ($jumlah as $peran => $n) {
                $bahan = DB::table('bahan_baku')->where('peran', $peran)->orderBy('id')->first();
                if (! $bahan) {
                    $galat[] = 'Bahan ' . DataAwalStokService::PERAN_UTAMA[$peran][0] . ' belum tersedia di data bahan baku.';
                } elseif ($n > (int) $bahan->stok_saat_ini) {
                    $galat[] = 'Stok ' . $bahan->nama_bahan . ' di dapur hanya ' . (int) $bahan->stok_saat_ini . ' ' . $bahan->satuan . ', yang mau dikirim ' . $n . ' ' . $bahan->satuan . '.';
                } else {
                    $rencana[] = [$bahan, $n];
                }
            }
            if ($galat) {
                throw ValidationException::withMessages(['kirim' => $galat]);
            }

            foreach ($rencana as [$bahan, $n]) {
                $buka = DB::table('permintaan_stok')->where('kasir_id', $kasirId)->where('bahan_baku_id', $bahan->id)
                    ->whereIn('status', ['diminta', 'dikirim'])->orderBy('id')->lockForUpdate()->first();

                $isi = [
                    'status' => 'dikirim', 'jumlah_dikirim' => $n, 'admin_id' => $adminId,
                    'dikirim_pada' => now(), 'updated_at' => now(),
                ];
                if ($catatan) {
                    $isi['catatan'] = mb_substr($catatan, 0, 150);
                }

                if ($buka) {
                    DB::table('permintaan_stok')->where('id', $buka->id)->update($isi);
                } else {
                    DB::table('permintaan_stok')->insert($isi + ['kasir_id' => $kasirId, 'bahan_baku_id' => $bahan->id, 'created_at' => now()]);
                }
            }

            return ['kasir' => $kasir->name, 'minyak' => $minyak, 'gas' => $gas];
        });
    }

    public function konfirmasiTerima(int $permintaanId, int $kasirId, int $diterima): void
    {
        DB::transaction(function () use ($permintaanId, $kasirId, $diterima) {
            $p = DB::table('permintaan_stok')->where('id', $permintaanId)->lockForUpdate()->first();
            if (! $p || (int) $p->kasir_id !== $kasirId || $p->status !== 'dikirim') {
                throw ValidationException::withMessages(['diterima' => 'Permintaan ini tidak bisa dikonfirmasi.']);
            }
            if ($diterima < 1 || $diterima > (int) $p->jumlah_dikirim) {
                throw ValidationException::withMessages(['diterima' => 'Jumlah diterima harus 1 sampai ' . $p->jumlah_dikirim . '.']);
            }

            $set   = $this->pengaturan->pengaturan();
            $bahan = DB::table('bahan_baku')->where('id', $p->bahan_baku_id)->lockForUpdate()->first();
            if ($diterima > (int) $bahan->stok_saat_ini) {
                throw ValidationException::withMessages(['diterima' => 'Stok ' . $bahan->nama_bahan . ' di dapur tidak cukup (' . (int) $bahan->stok_saat_ini . ' ' . $bahan->satuan . '). Hubungi admin.']);
            }

            $kasir = DB::table('users')->where('id', $kasirId)->value('name');
            $baru  = (int) $bahan->stok_saat_ini - $diterima;
            $bahan->stok_saat_ini = $baru;
            DB::table('bahan_baku')->where('id', $bahan->id)->update([
                'stok_saat_ini' => $baru, 'status_stok' => $this->pengaturan->statusBahan($bahan), 'updated_at' => now(),
            ]);

            $this->writer->tulisRiwayat((int) $bahan->id, $diterima, $baru, [
                'sumber' => 'kirim', 'transaksi_id' => null,
                'user_id' => (int) ($p->admin_id ?: $kasirId), 'kasir_id' => $kasirId, 'tanggal' => now()->toDateString(),
                'label' => 'Kirim ke kasir ' . $kasir . ' · dikonfirmasi diterima',
            ], 'Dikirim admin ' . $p->jumlah_dikirim . ', diterima kasir ' . $diterima);

            $this->writer->tambahStokKasirBahan($kasirId, (int) $bahan->id, $diterima, $set, 'Dikirim admin · sudah diterima');

            DB::table('permintaan_stok')->where('id', $p->id)->update([
                'status' => 'selesai', 'jumlah_diterima' => $diterima, 'diterima_pada' => now(), 'updated_at' => now(),
            ]);
        });
    }

    /** Batalkan permintaan: kasir hanya saat masih "diminta"; admin saat "diminta" atau "dikirim". */
    public function batalPermintaan(int $permintaanId, int $userId, bool $admin): void
    {
        $p = DB::table('permintaan_stok')->where('id', $permintaanId)->first();
        $boleh = $p && ($admin ? in_array($p->status, ['diminta', 'dikirim'], true)
            : ((int) $p->kasir_id === $userId && $p->status === 'diminta'));
        if (! $boleh) {
            throw ValidationException::withMessages(['permintaan' => 'Permintaan ini tidak bisa dibatalkan.']);
        }

        DB::table('permintaan_stok')->where('id', $permintaanId)->update(['status' => 'batal', 'updated_at' => now()]);
    }
}
