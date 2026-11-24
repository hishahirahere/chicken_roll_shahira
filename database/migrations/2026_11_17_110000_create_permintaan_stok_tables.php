<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pemakaian_kasir', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();          // kasir
            $table->foreignId('bahan_baku_id')->constrained('bahan_baku')->cascadeOnDelete();
            $table->unsignedInteger('jumlah');
            $table->date('tanggal');
            $table->string('catatan')->nullable();
            $table->timestamps();
        });

        Schema::create('permintaan_stok', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kasir_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('bahan_baku_id')->constrained('bahan_baku')->cascadeOnDelete();
            $table->unsignedInteger('jumlah_diminta')->nullable();   // boleh kosong = "kirim secukupnya"
            $table->unsignedInteger('jumlah_dikirim')->nullable();
            $table->unsignedInteger('jumlah_diterima')->nullable();
            $table->string('status', 10)->default('diminta')->index();
            $table->string('catatan')->nullable();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('dikirim_pada')->nullable();
            $table->dateTime('diterima_pada')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permintaan_stok');
        Schema::dropIfExists('pemakaian_kasir');
    }
};
