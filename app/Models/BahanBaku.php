<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BahanBaku extends Model
{
    use HasFactory;

    protected $table = 'bahan_baku';

    protected $fillable = [
        'nama_bahan', 'satuan', 'foto', 'peran', 'stok_saat_ini', 'stok_minimum', 'status_stok',
    ];

    protected $casts = [
        'stok_saat_ini' => 'integer',
        'stok_minimum'  => 'integer',
    ];

    public function riwayat()
    {
        return $this->hasMany(RiwayatStok::class, 'bahan_baku_id');
    }

    /** Status dari stok sekarang: habis (<= 0), menipis (<= stok_minimum), selain itu aman. */
    public function hitungStatus(): string
    {
        return \App\Services\StokOtomatis\PerhitunganStok::statusStok((int) $this->stok_saat_ini, (int) $this->stok_minimum);
    }

    public function getFotoUrlAttribute(): ?string
    {
        return $this->foto ? asset('storage/' . $this->foto) : null;
    }

    /** Update kolom status_stok sesuai stok sekarang lalu simpan. */
    public function sinkronStatus(): void
    {
        $this->status_stok = $this->hitungStatus();
        $this->save();
    }

    /** 1000 -> "1.000" (semua angka stok bulat) */
    public static function formatAngka($angka): string
    {
        return number_format((int) $angka, 0, ',', '.');
    }

    public function getStokFormatAttribute(): string
    {
        return self::formatAngka($this->stok_saat_ini);
    }

    public function getStokMinimumFormatAttribute(): string
    {
        return self::formatAngka($this->stok_minimum);
    }
}
