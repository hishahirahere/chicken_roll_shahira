<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gas LPG dipotong per HARI jualan (bukan per batch penggorengan).
 * Kolom ini mencatat tanggal terakhir gas dipotong untuk tiap kasir,
 * supaya gas hanya berkurang sekali per hari.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kasir_batch', function (Blueprint $table) {
            $table->date('gas_terakhir')->nullable()->after('pcs_berjalan');
        });
    }

    public function down(): void
    {
        Schema::table('kasir_batch', function (Blueprint $table) {
            $table->dropColumn('gas_terakhir');
        });
    }
};
