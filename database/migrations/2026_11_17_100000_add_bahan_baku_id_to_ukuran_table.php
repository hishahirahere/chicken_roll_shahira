<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kemasan dibedakan per ukuran (Slim / Reguler / Jumbo): tiap ukuran terhubung ke
 * bahan baku kemasannya sendiri, jadi saat ada yang beli Slim, kemasan Slim yang berkurang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ukuran', function (Blueprint $table) {
            $table->foreignId('bahan_baku_id')->nullable()->after('status')
                ->constrained('bahan_baku')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ukuran', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bahan_baku_id');
        });
    }
};
