<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemPesanan extends Model
{
    protected $table = 'item_pesanan';

    // Tabel ini tidak punya kolom created_at / updated_at
    public $timestamps = false;

    // 'harga' bukan kolom asli; nilainya dipetakan ke kolom 'harga_item' lewat setHargaAttribute()
    protected $fillable = ['transaksi_id', 'ukuran_id', 'filling_id', 'cara_luar', 'saus_celup_id', 'bumbu_tabur_id', 'harga', 'harga_item'];

    public function transaksi()   { return $this->belongsTo(Transaksi::class, 'transaksi_id'); }
    public function ukuran()      { return $this->belongsTo(Ukuran::class, 'ukuran_id'); }
    public function filling()     { return $this->belongsTo(Filling::class, 'filling_id'); }
    public function sausCelup()   { return $this->belongsTo(SausCelup::class, 'saus_celup_id'); }
    public function bumbuTabur()  { return $this->belongsTo(BumbuTabur::class, 'bumbu_tabur_id'); }

    /** Saat kode menulis `harga`, simpan ke kolom asli `harga_item`. */
    public function setHargaAttribute($value): void
    {
        $this->attributes['harga_item'] = (int) $value;
    }

    /**
     * Harga per porsi. Kalau tabel item_pesanan tidak punya kolom `harga`,
     * ambil dari harga ukuran (harga selalu dikelola di tabel ukuran).
     */
    public function getHargaAttribute($value)
    {
        foreach ([$value, $this->attributes['harga_item'] ?? null, $this->attributes['harga_satuan'] ?? null] as $v) {
            if ($v !== null) {
                return (int) $v;
            }
        }

        return (int) ($this->ukuran->harga ?? 0);
    }
}
