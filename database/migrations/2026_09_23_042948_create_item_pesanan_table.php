<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('item_pesanan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaksi_id')->constrained('transaksi')->cascadeOnDelete();
            $table->foreignId('ukuran_id')->constrained('ukuran');
            $table->foreignId('filling_id')->constrained('filling');
            $table->enum('cara_luar', ['saus_celup', 'bumbu_tabur']);
            $table->foreignId('saus_celup_id')->nullable()->constrained('saus_celup');
            $table->foreignId('bumbu_tabur_id')->nullable()->constrained('bumbu_tabur');
            $table->integer('harga_item');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_pesanan');
    }
};
