<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Batas minimum stok: kalau stok <= angka ini, status jadi "menipis"
        Schema::table('bahan_baku', function (Blueprint $table) {
            $table->unsignedInteger('stok_minimum')->default(0)->after('stok_saat_ini');
        });

        Schema::table('riwayat_stok', function (Blueprint $table) {
            // Stok masuk: nama langganan / pemasok yang mengirim barang
            $table->string('supplier', 100)->nullable()->after('jumlah');
            // Stok keluar: kasir yang mengambil bahan (user_id tetap = admin yang mencatat)
            $table->foreignId('kasir_id')->nullable()->after('supplier')
                ->constrained('users')->nullOnDelete();
            // Snapshot stok setelah transaksi ini (buat audit)
            $table->unsignedInteger('stok_sesudah')->nullable()->after('kasir_id');
        });
    }

    public function down(): void
    {
        Schema::table('riwayat_stok', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kasir_id');
            $table->dropColumn(['supplier', 'stok_sesudah']);
        });

        Schema::table('bahan_baku', function (Blueprint $table) {
            $table->dropColumn('stok_minimum');
        });
    }
};
