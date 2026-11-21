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
        // Pengaturan umum: kapasitas bungkus, minyak/gas per batch, kapasitas batch, dst.
        Schema::create('pengaturan_stok', function (Blueprint $table) {
            $table->id();
            $table->string('kunci', 50)->unique();
            $table->string('nilai', 100);
            $table->timestamps();
        });

        // Standar produksi per ukuran: hasil pcs per 1 kg ayam + tepung (paket per 1 kg ayam)
        Schema::create('standar_produksi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ukuran_id')->unique()->constrained('ukuran')->cascadeOnDelete();
            $table->decimal('hasil_pcs_per_kg', 8, 2);
            $table->decimal('terigu_per_kg', 8, 3);   // kg tepung terigu per 1 kg ayam
            $table->decimal('crispy_per_kg', 8, 3);   // kg tepung crispy per 1 kg ayam
            $table->timestamps();
        });

        // Catatan produksi: alokasi kg ayam per ukuran
        Schema::create('produksi', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('catatan')->nullable();
            $table->decimal('total_ayam_kg', 10, 3)->default(0);
            $table->decimal('total_terigu_kg', 10, 4)->default(0);
            $table->decimal('total_crispy_kg', 10, 4)->default(0);
            $table->unsignedInteger('total_pcs')->default(0);
            $table->boolean('potong_stok')->default(false); // true = stok bahan ikut dipotong saat dicatat
            $table->timestamps();
        });

        Schema::create('produksi_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produksi_id')->constrained('produksi')->cascadeOnDelete();
            $table->foreignId('ukuran_id')->constrained('ukuran');
            $table->decimal('kg_ayam', 8, 3);
            $table->unsignedInteger('est_pcs');
            $table->decimal('terigu_kg', 10, 4);
            $table->decimal('crispy_kg', 10, 4);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produksi_item');
        Schema::dropIfExists('produksi');
        Schema::dropIfExists('standar_produksi');
        Schema::dropIfExists('pengaturan_stok');
    }
};
