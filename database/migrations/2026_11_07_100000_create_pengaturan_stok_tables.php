<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel pengaturan untuk fitur Manajemen Stok Bahan Baku Otomatis.
 * Semua angka standar pemakaian disimpan di sini supaya bisa diubah admin tanpa ubah kode.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Pengaturan umum: kapasitas bungkus, isi paket, dst.
        Schema::create('pengaturan_stok', function (Blueprint $table) {
            $table->id();
            $table->string('kunci', 50)->unique();
            $table->string('nilai', 100);
            $table->timestamps();
        });

        // Standar per ukuran: hasil pcs per 1 kg ayam
        Schema::create('standar_produksi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ukuran_id')->unique()->constrained('ukuran')->cascadeOnDelete();
            $table->unsignedInteger('hasil_pcs_per_kg');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('standar_produksi');
        Schema::dropIfExists('pengaturan_stok');
    }
};
