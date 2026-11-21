<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tabung gas LPG yang dipakai ternyata 5 kg (bukan 3 kg). Hanya mengganti nama bahan.
 * Gas & minyak dicatat manual (tidak dipotong otomatis dari penjualan).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('bahan_baku')->where('peran', 'gas')->where('nama_bahan', 'Gas LPG 3 kg')
            ->update(['nama_bahan' => 'Gas LPG 5 kg', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('bahan_baku')->where('peran', 'gas')->where('nama_bahan', 'Gas LPG 5 kg')
            ->update(['nama_bahan' => 'Gas LPG 3 kg', 'updated_at' => now()]);
    }
};
