<?php

namespace App\Services\StokOtomatis;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Penulis stok & riwayat milik kasir (dipakai bersama oleh penjualan, paket, minyak/gas).
 */
class StokKasirWriter
{
    public function __construct(
        private PengaturanStokService $pengaturan,
    ) {}

    /** Ambil (atau buat) baris stok milik kasir untuk satu bahan, terkunci selama transaksi. */
    public function barisStokKasir(int $userId, int $bahanId): object
    {
        DB::table('stok_kasir')->insertOrIgnore([
            'user_id' => $userId, 'bahan_baku_id' => $bahanId, 'stok' => 0, 'acuan' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('stok_kasir')->where('user_id', $userId)->where('bahan_baku_id', $bahanId)->lockForUpdate()->first();
    }

    public function tambahProduk(int $kasirId, int $ukuranId, int $pcs, string $tanggal, string $ket): void
    {
        DB::table('stok_kasir_produk')->insertOrIgnore([
            'user_id' => $kasirId, 'ukuran_id' => $ukuranId, 'pcs' => 0, 'acuan' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $row  = DB::table('stok_kasir_produk')->where('user_id', $kasirId)->where('ukuran_id', $ukuranId)->lockForUpdate()->first();
        $baru = (int) $row->pcs + $pcs;
        DB::table('stok_kasir_produk')->where('id', $row->id)->update(['pcs' => $baru, 'acuan' => $baru, 'updated_at' => now()]);
        DB::table('riwayat_stok_produk')->insert([
            'user_id' => $kasirId, 'ukuran_id' => $ukuranId, 'jenis' => 'masuk', 'pcs' => $pcs,
            'stok_sesudah' => $baru, 'transaksi_id' => null, 'keterangan' => $ket, 'tanggal' => $tanggal, 'created_at' => now(),
        ]);
    }

    /**
     * Tambah stok bahan milik kasir dan perbarui acuan bar "menipis".
     * $jumlah dalam satuan gudang. Filling / saus / bumbu: bungkus x kali pakai per bungkus = kali pakai di tangan kasir.
     */
    public function tambahStokKasirBahan(int $kasirId, int $bahanId, int $jumlah, array $set, string $ket = 'Paket dari admin'): void
    {
        $bahan = DB::table('bahan_baku')->where('id', $bahanId)->first();
        if ($bahan && $this->pengaturan->bahanBerbungkus($bahan)) {
            $jumlah *= $this->pengaturan->kapasitasBungkus($bahan, $set);
        }

        $row  = $this->barisStokKasir($kasirId, $bahanId);
        $stok = (int) $row->stok + $jumlah;

        DB::table('stok_kasir')->where('id', $row->id)->update(['stok' => $stok, 'acuan' => $stok, 'updated_at' => now()]);
        $this->tulisRiwayatKasir($kasirId, $bahanId, 'masuk', str_starts_with($ket, 'Paket') ? 'paket' : (str_starts_with($ket, 'Bahan pendukung') ? 'pendukung' : 'kirim'), $jumlah, $stok, $ket, null, now()->toDateString());
    }

    public function tulisRiwayat(int $bahanId, int $jumlah, int $stokSesudah, array $k, string $rincian): void
    {
        DB::table('riwayat_stok')->insert([
            'bahan_baku_id' => $bahanId, 'jenis' => 'keluar', 'sumber' => $k['sumber'],
            'jumlah' => $jumlah, 'tanggal' => $k['tanggal'], 'user_id' => $k['user_id'], 'kasir_id' => $k['kasir_id'] ?? null,
            'keterangan' => $k['label'], 'transaksi_id' => $k['transaksi_id'],
            'rincian' => mb_substr($rincian, 0, 255), 'stok_sesudah' => $stokSesudah, 'created_at' => now(),
        ]);

        // Penjualan memotong stok KASIR: catat juga di riwayat bahan milik kasir (tampil di Stok Saya)
        if ($k['sumber'] === 'penjualan' && ! empty($k['kasir_id'])) {
            $this->tulisRiwayatKasir((int) $k['kasir_id'], $bahanId, 'keluar', 'penjualan', $jumlah, $stokSesudah,
                $k['label'], $rincian, $k['tanggal'], $k['transaksi_id'] ?? null);
        }
    }

    /** Riwayat stok bahan di tangan kasir (filling, saus, bumbu = kali pakai; kemasan, minyak, gas). */
    public function tulisRiwayatKasir(int $kasirId, int $bahanId, string $jenis, string $sumber, int $jumlah, int $stokSesudah,
        ?string $keterangan, ?string $rincian, string $tanggal, ?int $transaksiId = null): void
    {
        DB::table('riwayat_stok_kasir')->insert([
            'user_id' => $kasirId, 'bahan_baku_id' => $bahanId, 'jenis' => $jenis, 'sumber' => $sumber,
            'jumlah' => $jumlah, 'stok_sesudah' => $stokSesudah, 'transaksi_id' => $transaksiId,
            'keterangan' => $keterangan ? mb_substr($keterangan, 0, 255) : null,
            'rincian' => $rincian ? mb_substr($rincian, 0, 255) : null, 'tanggal' => $tanggal, 'created_at' => now(),
        ]);
    }
}
