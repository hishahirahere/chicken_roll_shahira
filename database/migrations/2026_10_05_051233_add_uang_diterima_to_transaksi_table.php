<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Uang yang diterima kasir saat pembayaran CASH (null untuk QRIS
     * atau kalau kasir tidak mengisinya). Kembalian dihitung di struk.
     */
    public function up(): void
    {
        Schema::table('transaksi', function (Blueprint $table) {
            $table->unsignedInteger('uang_diterima')->nullable()->after('metode_bayar');
        });
    }

    public function down(): void
    {
        Schema::table('transaksi', function (Blueprint $table) {
            $table->dropColumn('uang_diterima');
        });
    }
};
