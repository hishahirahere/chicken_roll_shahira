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
        'porsi_per_bungkus', 'sisa_porsi_aktif',
    ];

    protected $casts = [
        // 4 desimal: pemakaian otomatis per produk sangat kecil (mis. tepung 0,0225 kg)
        'stok_saat_ini'    => 'decimal:4',
        'stok_minimum'     => 'decimal:2',
        'porsi_per_bungkus' => 'decimal:2',
        'sisa_porsi_aktif' => 'decimal:2',
    ];

    public function riwayat()
    {
        return $this->hasMany(RiwayatStok::class, 'bahan_baku_id');
    }

    /**
     * Hitung status dari stok sekarang:
     * habis (<= 0), menipis (<= stok_minimum), selain itu aman.
     */
    public function hitungStatus(): string
    {
        // Lewat service supaya bahan berbungkus (filling/saus/bumbu) ikut menghitung sisa bungkus berjalan
        $service = app(\App\Services\StokOtomatis\StokOtomatisService::class);

        $data = (object) array_merge(
            ['peran' => null, 'porsi_per_bungkus' => null, 'sisa_porsi_aktif' => 0, 'stok_saat_ini' => 0, 'stok_minimum' => 0],
            $this->getAttributes()
        );

        return $service->statusBahan($data, $service->pengaturan());
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

    /** 50.00 -> "50", 12.5 -> "12,5", 1000 -> "1.000" */
    public static function formatAngka($angka): string
    {
        $teks = number_format((float) $angka, 4, ',', '.');

        return rtrim(rtrim($teks, '0'), ',');
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
