<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Transaksi extends Model
{
    protected $table = 'transaksi';

    // Tabel ini tidak punya kolom updated_at; created_at diisi otomatis oleh database (useCurrent)
    public $timestamps = false;

    protected $fillable = ['user_id', 'nama_pembeli', 'tanggal', 'total_harga', 'metode_bayar'];

    protected $casts = ['tanggal' => 'datetime'];

    /**
     * Transaksi yang dihitung sebagai pendapatan.
     *
     * Tabel `transaksi` saat ini TIDAK punya kolom status pembayaran, dan POS hanya
     * menyimpan transaksi setelah kasir menekan Konfirmasi di modal pembayaran.
     * Jadi semua baris yang tersimpan dianggap sudah dibayar.
     * Kalau nanti ada kolom status (mis. status_bayar), cukup tambahkan filter di sini
     * dan dashboard, riwayat, serta laporan ikut menyesuaikan.
     */
    public function scopeBerhasil($query)
    {
        return $query;
    }

    /** Batasi ke rentang tanggal (inklusif) pada kolom `tanggal`. */
    public function scopeAntara($query, $dari, $sampai)
    {
        return $query->whereBetween('tanggal', [
            Carbon::parse($dari)->startOfDay(),
            Carbon::parse($sampai)->endOfDay(),
        ]);
    }

    /** TRX-00012 */
    public function getKodeAttribute(): string
    {
        return 'TRX-' . str_pad($this->id, 5, '0', STR_PAD_LEFT);
    }

    public function kasir()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items()
    {
        return $this->hasMany(ItemPesanan::class, 'transaksi_id');
    }
}
