<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat stok bahan di tangan kasir (filling, saus celup, bumbu tabur, kemasan, minyak, gas).
 * Sebelumnya halaman Stok Saya hanya menampilkan riwayat produk (pcs per ukuran).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_stok_kasir', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();        // kasir pemilik stok
            $table->foreignId('bahan_baku_id')->constrained('bahan_baku')->cascadeOnDelete();
            $table->string('jenis', 10);                      // masuk | keluar
            $table->string('sumber', 20)->nullable();         // paket | kirim | penjualan | pemakaian
            $table->unsignedInteger('jumlah');
            $table->unsignedInteger('stok_sesudah')->default(0);
            $table->unsignedBigInteger('transaksi_id')->nullable()->index();
            $table->string('keterangan')->nullable();
            $table->string('rincian')->nullable();
            $table->date('tanggal');
            $table->timestamp('created_at')->nullable();
            $table->index(['user_id', 'bahan_baku_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_stok_kasir');
    }
};
