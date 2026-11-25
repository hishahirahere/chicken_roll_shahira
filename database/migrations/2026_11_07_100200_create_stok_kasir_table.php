<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stok di tangan kasir.
 * Alur: admin beli dari supplier (stok masuk gudang) -> admin memberi ke kasir (stok keluar, pilih kasir)
 * -> stok kasir bertambah -> setiap pembayaran, stok kasir berkurang otomatis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stok_kasir', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('bahan_baku_id')->constrained('bahan_baku')->cascadeOnDelete();
            $table->unsignedInteger('stok')->default(0);             // filling / saus / bumbu = kali pakai; kemasan = pcs; minyak = jerigen; gas = tabung
            $table->timestamps();
            $table->unique(['user_id', 'bahan_baku_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_kasir');
    }
};
