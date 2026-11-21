<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PayLater dihapus dari aplikasi: metode bayar hanya CASH dan QRIS.
 * Untuk database yang sudah dibuat dengan enum lama (cash, qris, paylater).
 * Kalau masih ada transaksi lama ber-metode paylater, enum dibiarkan supaya data tidak hilang.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (DB::table('transaksi')->where('metode_bayar', 'paylater')->exists()) {
            return;
        }

        DB::statement("ALTER TABLE transaksi MODIFY metode_bayar ENUM('cash','qris') NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE transaksi MODIFY metode_bayar ENUM('cash','qris','paylater') NOT NULL");
    }
};
