<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stok kasir berbasis PAKET:
 *  - ayam + tepung dari paket diubah jadi stok PCS per ukuran (stok_kasir_produk)
 *  - filling / saus / bumbu / minyak / gas / kemasan tetap di stok_kasir (dihitung "kali pakai")
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stok_kasir_produk', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ukuran_id')->constrained('ukuran')->cascadeOnDelete();
            $table->unsignedInteger('pcs')->default(0);
            $table->unsignedInteger('acuan')->default(0); // pcs setelah pemberian paket terakhir
            $table->timestamps();
            $table->unique(['user_id', 'ukuran_id']);
        });

        Schema::create('riwayat_stok_produk', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();  // kasir pemilik stok
            $table->foreignId('ukuran_id')->constrained('ukuran')->cascadeOnDelete();
            $table->string('jenis', 10);                 // masuk (paket) | keluar (penjualan)
            $table->integer('pcs');
            $table->unsignedInteger('stok_sesudah');
            $table->unsignedBigInteger('transaksi_id')->nullable()->index();
            $table->string('keterangan')->nullable();
            $table->date('tanggal');
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('stok_kasir', function (Blueprint $table) {
            $table->unsignedInteger('acuan')->default(0)->after('stok'); // jumlah stok setelah pemberian terakhir (untuk bar sisa)
        });
    }

    public function down(): void
    {
        Schema::table('stok_kasir', function (Blueprint $table) {
            $table->dropColumn('acuan');
        });
        Schema::dropIfExists('riwayat_stok_produk');
        Schema::dropIfExists('stok_kasir_produk');
    }
};
