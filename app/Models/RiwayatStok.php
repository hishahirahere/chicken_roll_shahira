<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiwayatStok extends Model
{
    protected $table = 'riwayat_stok';

    // Tabel ini cuma punya created_at (tidak ada updated_at)
    const UPDATED_AT = null;

    protected $fillable = [
        'bahan_baku_id', 'jenis', 'jumlah', 'tanggal', 'user_id',
        'supplier', 'kasir_id', 'stok_sesudah', 'keterangan',
        'sumber', 'transaksi_id', 'rincian',
    ];

    protected $casts = [
        'tanggal'      => 'date',
        'jumlah'       => 'integer',
        'stok_sesudah' => 'integer',
    ];

    public function bahan()
    {
        return $this->belongsTo(BahanBaku::class, 'bahan_baku_id');
    }

    /** Admin yang mencatat */
    public function admin()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Kasir yang mengambil bahan (khusus stok keluar) */
    public function kasir()
    {
        return $this->belongsTo(User::class, 'kasir_id');
    }

    public function getJumlahFormatAttribute(): string
    {
        return BahanBaku::formatAngka($this->jumlah);
    }

    public function getStokSesudahFormatAttribute(): ?string
    {
        return $this->stok_sesudah === null ? null : BahanBaku::formatAngka($this->stok_sesudah);
    }
}
